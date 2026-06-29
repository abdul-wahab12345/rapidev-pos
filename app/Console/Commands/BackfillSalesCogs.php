<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Sale;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time fix: older sales posted revenue but no Cost of Goods Sold, so the
 * P&L showed net profit ≈ revenue. This posts the missing COGS entry
 * (Dr COGS 5010 / Cr Inventory 1040) for each completed sale that lacks one.
 * Idempotent — safe to run more than once.
 */
class BackfillSalesCogs extends Command
{
    protected $signature   = 'accounting:backfill-cogs {--dry-run : Show what would be posted without writing}';
    protected $description  = 'Post missing Cost of Goods Sold journal entries for past sales';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $sales = Sale::withoutGlobalScopes()
            ->where('status', '!=', 'voided')
            ->with('items')
            ->get();

        $posted = 0;
        $skipped = 0;
        $totalCogs = 0.0;

        foreach ($sales as $sale) {
            // Already backfilled?
            $already = JournalEntry::withoutGlobalScopes()
                ->where('reference_type', 'sale_cogs_backfill')
                ->where('reference_id', $sale->id)
                ->exists();

            // Sale created after the fix already has a COGS line in its main entry
            $hasCogsInSaleEntry = JournalEntry::withoutGlobalScopes()
                ->where('reference_type', 'sale')
                ->where('reference_id', $sale->id)
                ->whereHas('lines', fn ($q) => $q->whereHas('account', fn ($a) => $a->where('code', AccountingService::COGS)))
                ->exists();

            if ($already || $hasCogsInSaleEntry) {
                $skipped++;
                continue;
            }

            $cogs = (float) $sale->items->sum(fn ($i) => (float) $i->cost_price * (float) $i->quantity);
            if ($cogs <= 0) {
                $skipped++;
                continue;
            }

            $accounts = DB::table('accounts')
                ->where('tenant_id', $sale->tenant_id)
                ->whereIn('code', [AccountingService::COGS, AccountingService::INVENTORY])
                ->where('is_active', true)
                ->pluck('id', 'code');

            if (! isset($accounts[AccountingService::COGS], $accounts[AccountingService::INVENTORY])) {
                $this->warn("Sale {$sale->invoice_number}: COGS/Inventory accounts missing for tenant — skipped.");
                $skipped++;
                continue;
            }

            $totalCogs += $cogs;
            $posted++;

            $this->line(sprintf('  %s  COGS Rs %s', $sale->invoice_number, number_format($cogs)));

            if ($dry) {
                continue;
            }

            DB::transaction(function () use ($sale, $accounts, $cogs) {
                $entry = JournalEntry::create([
                    'tenant_id'      => $sale->tenant_id,
                    'entry_number'   => JournalEntry::nextNumber($sale->tenant_id),
                    'entry_date'     => $sale->created_at->format('Y-m-d'),
                    'description'    => "COGS backfill – {$sale->invoice_number}",
                    'reference_type' => 'sale_cogs_backfill',
                    'reference_id'   => $sale->id,
                    'status'         => 'posted',
                    'created_by'     => $sale->user_id,
                ]);

                $entry->lines()->createMany([
                    ['account_id' => $accounts[AccountingService::COGS],      'debit' => $cogs, 'credit' => 0,    'description' => 'Cost of goods sold (backfill)'],
                    ['account_id' => $accounts[AccountingService::INVENTORY], 'debit' => 0,    'credit' => $cogs, 'description' => 'Inventory sold (backfill)'],
                ]);
            });
        }

        $this->newLine();
        $this->info(($dry ? '[DRY RUN] Would post' : 'Posted')." COGS for {$posted} sale(s), total Rs ".number_format($totalCogs).". Skipped {$skipped}.");

        return self::SUCCESS;
    }
}
