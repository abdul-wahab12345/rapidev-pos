<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    // Standard system account codes
    const CASH       = '1010';
    const BANK       = '1020';
    const RECEIVABLE = '1030';
    const REVENUE    = '4010';

    /**
     * Auto-post a journal entry when a sale is completed.
     */
    public static function postSale(Sale $sale): void
    {
        $tenantId = $sale->tenant_id;
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK, self::RECEIVABLE, self::REVENUE]);

        // Skip silently if the chart of accounts hasn't been set up yet
        if (!isset($accounts[self::REVENUE])) return;

        $total     = (float) $sale->total;
        $cash      = (float) $sale->cash_amount;
        $jazzcash  = (float) $sale->jazzcash_amount;
        $easypaisa = (float) $sale->easypaisa_amount;
        $udhaar    = (float) $sale->udhaar_amount;
        $digital   = $jazzcash + $easypaisa;

        $lines = [];

        // Debit side — what was received
        if ($cash > 0 && isset($accounts[self::CASH])) {
            $lines[] = ['account_id' => $accounts[self::CASH], 'debit' => $cash,    'credit' => 0, 'description' => 'Cash received'];
        }
        if ($digital > 0 && isset($accounts[self::BANK])) {
            $lines[] = ['account_id' => $accounts[self::BANK], 'debit' => $digital, 'credit' => 0, 'description' => 'Digital payment'];
        }
        if ($udhaar > 0 && isset($accounts[self::RECEIVABLE])) {
            $lines[] = ['account_id' => $accounts[self::RECEIVABLE], 'debit' => $udhaar, 'credit' => 0, 'description' => 'Udhaar (credit sale)'];
        }

        // Edge case: payment method has no breakdown (e.g. pure "cash" type stored without cash_amount)
        $totalDebits = $cash + $digital + $udhaar;
        if ($totalDebits < 0.01) {
            $debitAccountId = match ($sale->payment_method) {
                'jazzcash', 'easypaisa', 'bank' => $accounts[self::BANK]  ?? $accounts[self::CASH] ?? null,
                'udhaar'                         => $accounts[self::RECEIVABLE] ?? null,
                default                          => $accounts[self::CASH] ?? null,
            };
            if ($debitAccountId) {
                $lines[] = ['account_id' => $debitAccountId, 'debit' => $total, 'credit' => 0, 'description' => ucfirst($sale->payment_method)];
            }
        }

        // Credit side — Sales Revenue
        $lines[] = ['account_id' => $accounts[self::REVENUE], 'debit' => 0, 'credit' => $total, 'description' => "Sale {$sale->invoice_number}"];

        if (empty($lines)) return;

        self::createEntry($tenantId, $sale->user_id, [
            'entry_date'     => $sale->created_at->format('Y-m-d'),
            'description'    => "Sale {$sale->invoice_number}",
            'reference_type' => 'sale',
            'reference_id'   => $sale->id,
            'lines'          => $lines,
        ]);
    }

    /**
     * Reverse the journal entry for a voided sale.
     */
    public static function reverseSale(Sale $sale): void
    {
        $tenantId = $sale->tenant_id;

        // Find the original posted entry
        $original = JournalEntry::where('tenant_id', $tenantId)
            ->where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->where('status', 'posted')
            ->with('lines')
            ->first();

        if (!$original) return;

        // Create reversal by swapping debit/credit
        $reversalLines = $original->lines->map(fn ($l) => [
            'account_id'  => $l->account_id,
            'debit'       => (float) $l->credit,
            'credit'      => (float) $l->debit,
            'description' => 'Reversal: ' . ($l->description ?? ''),
        ])->toArray();

        self::createEntry($tenantId, auth()->id() ?? $sale->user_id, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Void: {$sale->invoice_number}",
            'reference_type' => 'void',
            'reference_id'   => $sale->id,
            'lines'          => $reversalLines,
        ]);
    }

    /**
     * Post journal entry when a PO is received.
     * Dr Inventory (1040), Cr AP (2010) for credit purchases, or Cr Cash/Bank for immediate payment.
     */
    public static function postPurchaseReceived(\App\Models\PurchaseOrder $po): void
    {
        $tenantId = $po->tenant_id;
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK, '1040', '2010']);

        if (!isset($accounts['1040'])) return;

        $total = (float) $po->total;
        if ($total <= 0) return;

        $creditAccountId = match ($po->payment_method) {
            'cash'  => $accounts[self::CASH]  ?? null,
            'bank'  => $accounts[self::BANK]  ?? null,
            default => $accounts['2010']       ?? null,  // credit → AP
        };

        if (!$creditAccountId) return;

        self::createEntry($tenantId, $po->created_by, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Goods received – {$po->po_number}",
            'reference_type' => 'purchase',
            'reference_id'   => $po->id,
            'lines'          => [
                ['account_id' => $accounts['1040'],  'debit' => $total, 'credit' => 0,     'description' => "Stock received – {$po->po_number}"],
                ['account_id' => $creditAccountId,   'debit' => 0,      'credit' => $total, 'description' => $po->payment_method === 'credit' ? "Payable to {$po->supplier?->name}" : 'Paid on receipt'],
            ],
        ]);

        // Update supplier AP balance for credit purchases
        if ($po->payment_method === 'credit') {
            \App\Models\Supplier::where('id', $po->supplier_id)
                ->increment('current_balance', $total);
        }
    }

    /**
     * Post journal entry when a supplier is paid.
     * Dr AP (2010), Cr Cash (1010) or Bank (1020).
     */
    public static function postPurchasePayment(\App\Models\PurchaseOrder $po, float $amount, string $method): void
    {
        $tenantId = $po->tenant_id;
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK, '2010']);

        $debitAccountId  = $accounts['2010']      ?? null;
        $creditAccountId = $method === 'bank'
            ? ($accounts[self::BANK] ?? $accounts[self::CASH] ?? null)
            : ($accounts[self::CASH] ?? null);

        if (!$debitAccountId || !$creditAccountId) return;

        self::createEntry($tenantId, auth()->id() ?? $po->created_by, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Payment to {$po->supplier?->name} – {$po->po_number}",
            'reference_type' => 'payment',
            'reference_id'   => $po->id,
            'lines'          => [
                ['account_id' => $debitAccountId,  'debit' => $amount, 'credit' => 0,      'description' => "AP cleared – {$po->po_number}"],
                ['account_id' => $creditAccountId, 'debit' => 0,       'credit' => $amount, 'description' => ucfirst($method) . ' payment'],
            ],
        ]);
    }

    /**
     * Post a journal entry when a customer makes an udhaar payment.
     */
    public static function postCustomerPayment(string $tenantId, int $userId, float $amount, string $customerId, string $invoiceRef = ''): void
    {
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::RECEIVABLE]);

        if (!isset($accounts[self::CASH]) || !isset($accounts[self::RECEIVABLE])) return;

        self::createEntry($tenantId, $userId, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Udhaar payment received" . ($invoiceRef ? " – {$invoiceRef}" : ''),
            'reference_type' => 'payment',
            'reference_id'   => $customerId,
            'lines'          => [
                ['account_id' => $accounts[self::CASH],       'debit' => $amount, 'credit' => 0,      'description' => 'Cash received'],
                ['account_id' => $accounts[self::RECEIVABLE], 'debit' => 0,       'credit' => $amount, 'description' => 'Udhaar cleared'],
            ],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    private static function loadAccounts(string $tenantId, array $codes): array
    {
        return Account::where('tenant_id', $tenantId)
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->toArray();
    }

    private static function createEntry(string $tenantId, int $userId, array $data): void
    {
        DB::transaction(function () use ($tenantId, $userId, $data) {
            $entry = JournalEntry::create([
                'tenant_id'      => $tenantId,
                'entry_number'   => JournalEntry::nextNumber($tenantId),
                'entry_date'     => $data['entry_date'],
                'description'    => $data['description'],
                'reference_type' => $data['reference_type'] ?? 'manual',
                'reference_id'   => $data['reference_id']   ?? null,
                'status'         => 'posted',
                'created_by'     => $userId,
            ]);

            foreach ($data['lines'] as $line) {
                if (($line['debit'] ?? 0) > 0 || ($line['credit'] ?? 0) > 0) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $line['account_id'],
                        'debit'            => $line['debit']  ?? 0,
                        'credit'           => $line['credit'] ?? 0,
                        'description'      => $line['description'] ?? null,
                    ]);
                }
            }
        });
    }
}
