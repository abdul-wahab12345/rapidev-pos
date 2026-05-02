<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\DefaultChartOfAccounts;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountsController extends Controller
{
    // ── Chart of Accounts ─────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        DefaultChartOfAccounts::seedForTenant($tenant->id);

        // Chart of Accounts grouped by type
        $accounts = Account::where('tenant_id', $tenant->id)
            ->orderBy('code')
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->map(fn ($a) => [
                'id'          => $a->id,
                'code'        => $a->code,
                'name'        => $a->name,
                'type'        => $a->type,
                'sub_type'    => $a->sub_type,
                'is_system'   => $a->is_system,
                'is_active'   => $a->is_active,
                'description' => $a->description,
            ])->values());

        // Recent journal entries
        $entries = JournalEntry::with(['lines.account', 'creator'])
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('entry_date')
            ->orderByDesc('entry_number')
            ->paginate(20);

        $entryData = $entries->items();
        $mappedEntries = collect($entryData)->map(fn ($e) => [
            'id'             => $e->id,
            'entry_number'   => $e->entry_number,
            'entry_date'     => $e->entry_date?->format('Y-m-d'),
            'description'    => $e->description,
            'reference_type' => $e->reference_type,
            'status'         => $e->status,
            'created_by'     => $e->creator?->name,
            'total_debit'    => (float) $e->lines->sum('debit'),
            'lines'          => $e->lines->map(fn ($l) => [
                'account_code' => $l->account?->code,
                'account_name' => $l->account?->name,
                'debit'        => (float) $l->debit,
                'credit'       => (float) $l->credit,
                'description'  => $l->description,
            ]),
        ]);

        $accountList = Account::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Accounts/Index', [
            'accounts'     => $accounts,
            'entries'      => [
                'data'         => $mappedEntries,
                'current_page' => $entries->currentPage(),
                'last_page'    => $entries->lastPage(),
                'total'        => $entries->total(),
            ],
            'account_list' => $accountList->map(fn ($a) => [
                'id'   => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
            ]),
            'filters' => $request->only(['tab']),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20',
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:asset,liability,equity,income,expense',
            'sub_type'    => 'nullable|string|max:40',
            'description' => 'nullable|string|max:300',
        ]);

        $tenant = auth()->user()->tenant;

        if (Account::where('tenant_id', $tenant->id)->where('code', $validated['code'])->exists()) {
            return back()->withErrors(['code' => 'Account code already exists.']);
        }

        Account::create(array_merge($validated, ['tenant_id' => $tenant->id]));

        return back()->with('success', "Account {$validated['code']} – {$validated['name']} created.");
    }

    public function updateAccount(Request $request, Account $account): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'sub_type'    => 'nullable|string|max:40',
            'description' => 'nullable|string|max:300',
            'is_active'   => 'boolean',
        ]);

        // System accounts: only allow toggling active status
        if ($account->is_system) {
            $account->update(['is_active' => $validated['is_active'] ?? $account->is_active]);
        } else {
            $account->update($validated);
        }

        return back()->with('success', 'Account updated.');
    }

    // ── Journal Entries ───────────────────────────────────────────────

    public function storeEntry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date'  => 'required|date',
            'description' => 'required|string|max:300',
            'lines'       => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit'      => 'required|numeric|min:0',
            'lines.*.credit'     => 'required|numeric|min:0',
            'lines.*.description'=> 'nullable|string|max:200',
        ]);

        $totalDebit  = collect($validated['lines'])->sum('debit');
        $totalCredit = collect($validated['lines'])->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return back()->withErrors(['lines' => 'Journal entry must be balanced (total debits must equal total credits).']);
        }

        $tenant = auth()->user()->tenant;

        DB::transaction(function () use ($validated, $tenant) {
            $entry = JournalEntry::create([
                'tenant_id'    => $tenant->id,
                'entry_number' => JournalEntry::nextNumber($tenant->id),
                'entry_date'   => $validated['entry_date'],
                'description'  => $validated['description'],
                'reference_type' => 'manual',
                'status'       => 'posted',
                'created_by'   => auth()->id(),
            ]);

            foreach ($validated['lines'] as $line) {
                if ($line['debit'] > 0 || $line['credit'] > 0) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $line['account_id'],
                        'debit'            => $line['debit'],
                        'credit'           => $line['credit'],
                        'description'      => $line['description'] ?? null,
                    ]);
                }
            }
        });

        return back()->with('success', 'Journal entry posted.');
    }

    public function destroyEntry(JournalEntry $entry): RedirectResponse
    {
        if ($entry->reference_type !== 'manual') {
            return back()->with('error', 'Only manual journal entries can be deleted.');
        }

        $entry->delete();
        return back()->with('success', 'Journal entry deleted.');
    }

    // ── Reports ───────────────────────────────────────────────────────

    public function reports(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        DefaultChartOfAccounts::seedForTenant($tenant->id);

        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to',   now()->format('Y-m-d'));

        $accounts = Account::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // ── Trial Balance ─────────────────────────────────────────────
        $trialBalance = $accounts->map(function (Account $a) use ($from, $to) {
            $q = JournalLine::where('account_id', $a->id)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_entries.entry_date', '>=', $from)
                ->where('journal_entries.entry_date', '<=', $to);

            $debit  = (float) (clone $q)->sum('journal_lines.debit');
            $credit = (float) (clone $q)->sum('journal_lines.credit');

            if ($debit == 0 && $credit == 0) return null;

            return [
                'code'    => $a->code,
                'name'    => $a->name,
                'type'    => $a->type,
                'debit'   => $debit,
                'credit'  => $credit,
                'balance' => $a->normalBalance() === 'debit' ? $debit - $credit : $credit - $debit,
            ];
        })->filter()->values();

        // ── Profit & Loss ─────────────────────────────────────────────
        $incomeAccounts  = $accounts->where('type', 'income');
        $expenseAccounts = $accounts->where('type', 'expense');

        $pnlIncome = $incomeAccounts->map(function (Account $a) use ($from, $to) {
            $balance = $this->accountBalance($a->id, $from, $to);
            return $balance != 0 ? ['code' => $a->code, 'name' => $a->name, 'sub_type' => $a->sub_type, 'amount' => $balance] : null;
        })->filter()->values();

        $pnlExpenses = $expenseAccounts->map(function (Account $a) use ($from, $to) {
            $balance = $this->accountBalance($a->id, $from, $to);
            return $balance != 0 ? ['code' => $a->code, 'name' => $a->name, 'sub_type' => $a->sub_type, 'amount' => $balance] : null;
        })->filter()->values();

        $totalIncome   = $pnlIncome->sum('amount');
        $totalExpenses = $pnlExpenses->sum('amount');
        $netProfit     = $totalIncome - $totalExpenses;

        // ── Balance Sheet ─────────────────────────────────────────────
        // For balance sheet we use all-time balances (no date range)
        $assetAccounts     = $accounts->where('type', 'asset');
        $liabilityAccounts = $accounts->where('type', 'liability');
        $equityAccounts    = $accounts->where('type', 'equity');

        $bsAssets = $assetAccounts->map(function (Account $a) {
            $b = $this->accountBalance($a->id);
            return $b != 0 ? ['code' => $a->code, 'name' => $a->name, 'sub_type' => $a->sub_type, 'amount' => $b] : null;
        })->filter()->values();

        $bsLiabilities = $liabilityAccounts->map(function (Account $a) {
            $b = $this->accountBalance($a->id);
            return $b != 0 ? ['code' => $a->code, 'name' => $a->name, 'sub_type' => $a->sub_type, 'amount' => $b] : null;
        })->filter()->values();

        $bsEquity = $equityAccounts->map(function (Account $a) {
            $b = $this->accountBalance($a->id);
            return $b != 0 ? ['code' => $a->code, 'name' => $a->name, 'sub_type' => $a->sub_type, 'amount' => $b] : null;
        })->filter()->values();

        return Inertia::render('Accounts/Reports', [
            'trial_balance'   => $trialBalance,
            'pnl' => [
                'income'        => $pnlIncome,
                'expenses'      => $pnlExpenses,
                'total_income'  => $totalIncome,
                'total_expenses'=> $totalExpenses,
                'net_profit'    => $netProfit,
            ],
            'balance_sheet' => [
                'assets'           => $bsAssets,
                'liabilities'      => $bsLiabilities,
                'equity'           => $bsEquity,
                'total_assets'     => $bsAssets->sum('amount'),
                'total_liabilities'=> $bsLiabilities->sum('amount'),
                'total_equity'     => $bsEquity->sum('amount') + $netProfit,
            ],
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    // ── Receivables & Payables Sub-ledger ────────────────────────────

    public function receivables(Request $request): Response
    {
        $tenant = auth()->user()->tenant;

        // ── Accounts Receivable: customers with outstanding udhaar ────
        $receivables = \App\Models\Customer::where('tenant_id', $tenant->id)
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->get()
            ->map(function ($c) {
                // Oldest unpaid sale date (first sale that contributed to udhaar)
                $oldestSale = \App\Models\Sale::where('customer_id', $c->id)
                    ->where('udhaar_amount', '>', 0)
                    ->whereIn('status', ['completed', 'partially_returned'])
                    ->orderBy('created_at')
                    ->value('created_at');

                $ageDays = $oldestSale
                    ? (int) max(0, now()->startOfDay()->diffInDays(Carbon::parse($oldestSale)->startOfDay()))
                    : null;

                return [
                    'id'              => $c->id,
                    'name'            => $c->name,
                    'phone'           => $c->phone,
                    'balance'         => (float) $c->current_balance,
                    'credit_limit'    => (float) $c->credit_limit,
                    'oldest_sale_date'=> $oldestSale?->format('Y-m-d'),
                    'age_days'        => $ageDays,
                ];
            });

        $totalReceivable = $receivables->sum('balance');

        // ── Accounts Payable: suppliers with outstanding balance ──────────
        $payables = \App\Models\Supplier::where('tenant_id', $tenant->id)
            ->where('current_balance', '>', 0)
            ->with('party.customer')
            ->orderByDesc('current_balance')
            ->get()
            ->map(function ($s) {
                // Oldest unpaid PO date
                $oldestPo = \App\Models\PurchaseOrder::where('supplier_id', $s->id)
                    ->whereIn('status', ['ordered', 'received', 'partial'])
                    ->where('paid_amount', '<', \DB::raw('total'))
                    ->orderBy('order_date')
                    ->value('order_date');

                $ageDays = $oldestPo
                    ? (int) max(0, now()->startOfDay()->diffInDays(Carbon::parse($oldestPo)->startOfDay()))
                    : null;

                $ap = (float) $s->current_balance;
                $ar = (float) ($s->party?->customer?->current_balance ?? 0);

                return [
                    'id'             => $s->id,
                    'name'           => $s->name,
                    'company'        => $s->company,
                    'phone'          => $s->phone,
                    'balance'        => $ap,
                    'ar_balance'     => $ar,
                    'net_payable'    => max(0, $ap - $ar),
                    'oldest_po_date' => $oldestPo ? Carbon::parse($oldestPo)->format('Y-m-d') : null,
                    'age_days'       => $ageDays,
                ];
            });

        $totalPayable = $payables->sum('net_payable');

        $payableAccount = Account::where('tenant_id', $tenant->id)->where('code', '2010')->first();

        return Inertia::render('Accounts/Receivables', [
            'receivables'      => $receivables->values(),
            'payables'         => $payables->values(),
            'total_receivable' => $totalReceivable,
            'total_payable'    => $totalPayable,
            'ar_account_id'    => Account::where('tenant_id', $tenant->id)->where('code', '1030')->value('id'),
            'ap_account_id'    => $payableAccount?->id,
        ]);
    }

    // ── General Ledger ────────────────────────────────────────────────

    public function ledger(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        DefaultChartOfAccounts::seedForTenant($tenant->id);

        $from      = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to        = $request->get('to',   now()->format('Y-m-d'));
        $accountId = $request->get('account');

        // All active accounts for the picker
        $accountList = Account::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type]);

        $account = null;
        $lines   = collect();
        $openingBalance = 0.0;

        if ($accountId) {
            $account = Account::where('tenant_id', $tenant->id)->find($accountId);
        }

        // Default to Accounts Receivable (1030) when no account selected
        if (!$account) {
            $account = Account::where('tenant_id', $tenant->id)->where('code', '1030')->first();
            $accountId = $account?->id;
        }

        if ($account) {
            // Opening balance = all transactions BEFORE the from date
            $obQ = JournalLine::where('account_id', $account->id)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_entries.entry_date', '<', $from);

            $obDebit  = (float) (clone $obQ)->sum('journal_lines.debit');
            $obCredit = (float) (clone $obQ)->sum('journal_lines.credit');
            $openingBalance = $account->normalBalance() === 'debit'
                ? $obDebit - $obCredit
                : $obCredit - $obDebit;

            // Lines in range
            $rawLines = JournalLine::where('account_id', $account->id)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_entries.entry_date', '>=', $from)
                ->where('journal_entries.entry_date', '<=', $to)
                ->orderBy('journal_entries.entry_date')
                ->orderBy('journal_entries.entry_number')
                ->select(
                    'journal_lines.id',
                    'journal_lines.debit',
                    'journal_lines.credit',
                    'journal_lines.description as line_description',
                    'journal_entries.entry_number',
                    'journal_entries.entry_date',
                    'journal_entries.description as entry_description',
                    'journal_entries.reference_type',
                )
                ->get();

            // Build running balance
            $running = $openingBalance;
            $lines = $rawLines->map(function ($l) use ($account, &$running) {
                $debit  = (float) $l->debit;
                $credit = (float) $l->credit;
                $running += $account->normalBalance() === 'debit'
                    ? ($debit - $credit)
                    : ($credit - $debit);

                return [
                    'entry_number'   => $l->entry_number,
                    'entry_date'     => $l->entry_date,
                    'description'    => $l->line_description ?: $l->entry_description,
                    'reference_type' => $l->reference_type,
                    'debit'          => $debit,
                    'credit'         => $credit,
                    'balance'        => $running,
                ];
            });
        }

        return Inertia::render('Accounts/Ledger', [
            'account_list'    => $accountList,
            'selected_account'=> $account ? [
                'id'   => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ] : null,
            'opening_balance' => $openingBalance,
            'lines'           => $lines->values(),
            'filters'         => ['account' => $accountId, 'from' => $from, 'to' => $to],
        ]);
    }

    private function accountBalance(string $accountId, string $from = null, string $to = null): float
    {
        $account = Account::find($accountId);
        if (!$account) return 0;

        $q = JournalLine::where('account_id', $accountId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted');

        if ($from) $q->where('journal_entries.entry_date', '>=', $from);
        if ($to)   $q->where('journal_entries.entry_date', '<=', $to);

        $debit  = (float) (clone $q)->sum('journal_lines.debit');
        $credit = (float) (clone $q)->sum('journal_lines.credit');

        return $account->normalBalance() === 'debit'
            ? $debit - $credit
            : $credit - $debit;
    }
}
