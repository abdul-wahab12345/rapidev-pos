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
    const CASH        = '1010';
    const BANK        = '1020';
    const RECEIVABLE  = '1030';
    const INVENTORY   = '1040';
    const PAYABLE     = '2010';
    const OPENING_EQ  = '3040';  // Opening Balance Equity
    const REVENUE     = '4010';
    const OTHER_INCOME = '4020';
    const COGS        = '5010';  // Cost of Goods Sold
    const BAD_DEBT    = '5110';  // Bad Debts Written Off

    /**
     * Auto-post a journal entry when a sale is completed.
     */
    public static function postSale(Sale $sale): void
    {
        $tenantId = $sale->tenant_id;
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK, self::RECEIVABLE, self::REVENUE, self::COGS, self::INVENTORY]);

        // Skip silently if the chart of accounts hasn't been set up yet
        if (!isset($accounts[self::REVENUE])) return;

        $total     = (float) $sale->total;
        $cash      = (float) $sale->cash_amount;
        $jazzcash  = (float) $sale->jazzcash_amount;
        $easypaisa = (float) $sale->easypaisa_amount;
        $bank      = (float) ($sale->bank_amount ?? 0);
        $udhaar    = (float) $sale->udhaar_amount;
        $digital   = $jazzcash + $easypaisa + $bank; // all non-cash electronic payments → Bank account

        $lines = [];

        // Debit side — what was received
        if ($cash > 0 && isset($accounts[self::CASH])) {
            $lines[] = ['account_id' => $accounts[self::CASH], 'debit' => $cash,    'credit' => 0, 'description' => 'Cash received'];
        }
        if ($digital > 0 && isset($accounts[self::BANK])) {
            $lines[] = ['account_id' => $accounts[self::BANK], 'debit' => $digital, 'credit' => 0, 'description' => 'Digital/bank payment'];
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

        // Cost of goods sold — Dr COGS (5010), Cr Inventory (1040) for the cost of items sold.
        // Keeps the same journal entry balanced (debits/credits both rise by COGS) and lets
        // reverseSale() undo it automatically on void.
        $sale->loadMissing('items');
        $cogs = (float) $sale->items->sum(fn ($i) => (float) $i->cost_price * (float) $i->quantity);
        if ($cogs > 0 && isset($accounts[self::COGS]) && isset($accounts[self::INVENTORY])) {
            $lines[] = ['account_id' => $accounts[self::COGS],      'debit' => $cogs, 'credit' => 0,    'description' => "COGS – {$sale->invoice_number}"];
            $lines[] = ['account_id' => $accounts[self::INVENTORY], 'debit' => 0,    'credit' => $cogs, 'description' => "Inventory sold – {$sale->invoice_number}"];
        }

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

    /**
     * Post a journal entry when a sale return is processed.
     * Dr Revenue (4010), Cr Cash (1010) / Bank (1020) / Receivable (1030).
     */
    public static function postReturn(\App\Models\SaleReturn $return): void
    {
        $tenantId = $return->tenant_id;
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK, self::RECEIVABLE, self::REVENUE, self::COGS, self::INVENTORY]);

        if (!isset($accounts[self::REVENUE])) return;

        $amount = (float) $return->total_refund;
        if ($amount <= 0) return;

        $creditAccountId = match ($return->refund_method) {
            'bank'         => $accounts[self::BANK]       ?? $accounts[self::CASH] ?? null,
            'store_credit' => $accounts[self::RECEIVABLE] ?? null,
            default        => $accounts[self::CASH]       ?? null,
        };

        if (!$creditAccountId) return;

        $saleInvoice = $return->sale?->invoice_number ?? $return->sale_id;

        $lines = [
            ['account_id' => $accounts[self::REVENUE], 'debit' => $amount, 'credit' => 0,      'description' => "Revenue reversed – {$return->return_number}"],
            ['account_id' => $creditAccountId,         'debit' => 0,       'credit' => $amount, 'description' => ucfirst(str_replace('_', ' ', $return->refund_method)) . ' refund'],
        ];

        // Reverse COGS only for restocked items — goods physically back in inventory.
        // Dr Inventory (1040), Cr COGS (5010). Damaged (non-restock) returns keep the cost as a loss.
        $return->loadMissing('items.saleItem');
        $returnedCogs = (float) $return->items
            ->where('restock', true)
            ->sum(fn ($ri) => (float) ($ri->saleItem?->cost_price ?? 0) * (float) $ri->quantity_returned);
        if ($returnedCogs > 0 && isset($accounts[self::COGS]) && isset($accounts[self::INVENTORY])) {
            $lines[] = ['account_id' => $accounts[self::INVENTORY], 'debit' => $returnedCogs, 'credit' => 0,            'description' => "Inventory restocked – {$return->return_number}"];
            $lines[] = ['account_id' => $accounts[self::COGS],      'debit' => 0,             'credit' => $returnedCogs, 'description' => "COGS reversed – {$return->return_number}"];
        }

        self::createEntry($tenantId, $return->created_by, [
            'entry_date'     => $return->return_date->format('Y-m-d'),
            'description'    => "Return {$return->return_number} – {$saleInvoice}",
            'reference_type' => 'return',
            'reference_id'   => $return->id,
            'lines'          => $lines,
        ]);
    }

    /**
     * Post a journal entry when an expense is recorded.
     * Dr Expense Account (5xxx), Cr Cash (1010) or Bank (1020).
     */
    public static function postExpense(\App\Models\Expense $expense): void
    {
        $tenantId = $expense->tenant_id;
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK]);

        $creditAccountId = $expense->payment_method === 'bank'
            ? ($accounts[self::BANK] ?? $accounts[self::CASH] ?? null)
            : ($accounts[self::CASH] ?? null);

        if (!$creditAccountId) return;

        $amount = (float) $expense->amount;

        self::createEntry($tenantId, $expense->created_by, [
            'entry_date'     => $expense->expense_date->format('Y-m-d'),
            'description'    => $expense->description ?? "Expense {$expense->expense_number}",
            'reference_type' => 'expense',
            'reference_id'   => $expense->id,
            'lines'          => [
                ['account_id' => $expense->account_id, 'debit' => $amount, 'credit' => 0,      'description' => $expense->description ?? $expense->expense_number],
                ['account_id' => $creditAccountId,     'debit' => 0,       'credit' => $amount, 'description' => ucfirst($expense->payment_method) . ' payment'],
            ],
        ]);
    }

    /**
     * Post a journal entry for a tracked PO payment record.
     * Dr AP (2010), Cr Cash (1010) or Bank (1020).
     */
    public static function postPurchasePaymentRecord(\App\Models\PurchaseOrderPayment $payment, \App\Models\PurchaseOrder $po): void
    {
        $tenantId = $payment->tenant_id;
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK, '2010']);

        $debitAccountId  = $accounts['2010'] ?? null;
        $creditAccountId = $payment->payment_method === 'bank'
            ? ($accounts[self::BANK] ?? $accounts[self::CASH] ?? null)
            : ($accounts[self::CASH] ?? null);

        if (!$debitAccountId || !$creditAccountId) return;

        $amount = (float) $payment->amount;

        self::createEntry($tenantId, $payment->created_by ?? auth()->id(), [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Payment to {$po->supplier?->name} – {$po->po_number}",
            'reference_type' => 'po_payment',
            'reference_id'   => $payment->id,
            'lines'          => [
                ['account_id' => $debitAccountId,  'debit' => $amount, 'credit' => 0,      'description' => "AP cleared – {$po->po_number}"],
                ['account_id' => $creditAccountId, 'debit' => 0,       'credit' => $amount, 'description' => ucfirst($payment->payment_method) . ' payment'],
            ],
        ]);
    }

    /**
     * Reverse a PO payment journal entry (on void).
     */
    public static function reversePurchasePayment(\App\Models\PurchaseOrderPayment $payment): void
    {
        $original = JournalEntry::where('tenant_id', $payment->tenant_id)
            ->where('reference_type', 'po_payment')
            ->where('reference_id', $payment->id)
            ->where('status', 'posted')
            ->with('lines')
            ->first();

        if (!$original) return;

        $reversalLines = $original->lines->map(fn ($l) => [
            'account_id'  => $l->account_id,
            'debit'       => (float) $l->credit,
            'credit'      => (float) $l->debit,
            'description' => 'Reversal: ' . ($l->description ?? ''),
        ])->toArray();

        self::createEntry($payment->tenant_id, auth()->id() ?? $payment->created_by, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Void payment – {$payment->purchaseOrder?->po_number}",
            'reference_type' => 'po_payment_void',
            'reference_id'   => $payment->id,
            'lines'          => $reversalLines,
        ]);
    }

    /**
     * Post a journal entry for a supplier return (debit note).
     * Dr AP (2010) — reduce what we owe, Cr Inventory (1040) — reduce stock value.
     */
    public static function postSupplierReturn(\App\Models\SupplierReturn $return): void
    {
        $tenantId = $return->tenant_id;
        $accounts = self::loadAccounts($tenantId, ['1040', '2010']);

        if (!isset($accounts['2010']) || !isset($accounts['1040'])) return;

        $amount = (float) $return->total_amount;
        if ($amount <= 0) return;

        self::createEntry($tenantId, $return->created_by ?? auth()->id(), [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Supplier return {$return->return_number} – {$return->purchaseOrder?->po_number}",
            'reference_type' => 'supplier_return',
            'reference_id'   => $return->id,
            'lines'          => [
                ['account_id' => $accounts['2010'], 'debit' => $amount, 'credit' => 0,      'description' => "AP reduced – {$return->return_number}"],
                ['account_id' => $accounts['1040'], 'debit' => 0,       'credit' => $amount, 'description' => "Inventory returned – {$return->return_number}"],
            ],
        ]);
    }

    /**
     * Reverse a customer payment ledger entry (on void).
     * Dr Receivable (1030), Cr Cash (1010).
     */
    public static function reverseCustomerPayment(string $tenantId, float $amount, string $ledgerEntryId): void
    {
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::RECEIVABLE]);

        if (!isset($accounts[self::CASH]) || !isset($accounts[self::RECEIVABLE])) return;

        self::createEntry($tenantId, auth()->id(), [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => 'Customer payment voided',
            'reference_type' => 'customer_payment_void',
            'reference_id'   => $ledgerEntryId,
            'lines'          => [
                ['account_id' => $accounts[self::RECEIVABLE], 'debit' => $amount, 'credit' => 0,      'description' => 'Payment reversed – udhaar restored'],
                ['account_id' => $accounts[self::CASH],       'debit' => 0,       'credit' => $amount, 'description' => 'Cash reversed'],
            ],
        ]);
    }

    /**
     * Reverse an expense journal entry (on delete/update).
     */
    public static function reverseExpense(\App\Models\Expense $expense): void
    {
        $tenantId = $expense->tenant_id;

        $original = JournalEntry::where('tenant_id', $tenantId)
            ->where('reference_type', 'expense')
            ->where('reference_id', $expense->id)
            ->where('status', 'posted')
            ->with('lines')
            ->first();

        if (!$original) return;

        $reversalLines = $original->lines->map(fn ($l) => [
            'account_id'  => $l->account_id,
            'debit'       => (float) $l->credit,
            'credit'      => (float) $l->debit,
            'description' => 'Reversal: ' . ($l->description ?? ''),
        ])->toArray();

        self::createEntry($tenantId, auth()->id() ?? $expense->created_by, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "Void expense: {$expense->expense_number}",
            'reference_type' => 'expense_void',
            'reference_id'   => $expense->id,
            'lines'          => $reversalLines,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────

    /**
     * Post a customer's opening udhaar balance.
     * Dr Accounts Receivable (1030), Cr Opening Balance Equity (3040).
     */
    public static function postCustomerOpeningBalance(string $tenantId, int $userId, float $amount, string $customerId, string $name = ''): void
    {
        if ($amount <= 0) return;
        $ar  = self::ensureAccount($tenantId, self::RECEIVABLE);
        $eq  = self::ensureAccount($tenantId, self::OPENING_EQ);
        if (!$ar || !$eq) return;

        self::createEntry($tenantId, $userId, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => 'Opening udhaar balance' . ($name ? " – {$name}" : ''),
            'reference_type' => 'customer_opening',
            'reference_id'   => $customerId,
            'lines'          => [
                ['account_id' => $ar, 'debit' => $amount, 'credit' => 0,      'description' => 'Opening receivable'],
                ['account_id' => $eq, 'debit' => 0,      'credit' => $amount, 'description' => 'Opening balance equity'],
            ],
        ]);
    }

    /**
     * Post a supplier's opening payable balance.
     * Dr Opening Balance Equity (3040), Cr Accounts Payable (2010).
     */
    public static function postSupplierOpeningBalance(string $tenantId, int $userId, float $amount, string $supplierId, string $name = ''): void
    {
        if ($amount <= 0) return;
        $ap = self::ensureAccount($tenantId, self::PAYABLE);
        $eq = self::ensureAccount($tenantId, self::OPENING_EQ);
        if (!$ap || !$eq) return;

        self::createEntry($tenantId, $userId, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => 'Opening payable balance' . ($name ? " – {$name}" : ''),
            'reference_type' => 'supplier_opening',
            'reference_id'   => $supplierId,
            'lines'          => [
                ['account_id' => $eq, 'debit' => $amount, 'credit' => 0,      'description' => 'Opening balance equity'],
                ['account_id' => $ap, 'debit' => 0,      'credit' => $amount, 'description' => 'Opening payable'],
            ],
        ]);
    }

    /**
     * Post a manual charge that increases a customer's udhaar (debit note).
     * Dr Accounts Receivable (1030), Cr Other Income (4020).
     */
    public static function postCustomerCharge(string $tenantId, int $userId, float $amount, string $customerId, string $desc = ''): void
    {
        if ($amount <= 0) return;
        $ar  = self::ensureAccount($tenantId, self::RECEIVABLE);
        $inc = self::ensureAccount($tenantId, self::OTHER_INCOME);
        if (!$ar || !$inc) return;

        self::createEntry($tenantId, $userId, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => 'Customer charge' . ($desc ? " – {$desc}" : ''),
            'reference_type' => 'customer_charge',
            'reference_id'   => $customerId,
            'lines'          => [
                ['account_id' => $ar,  'debit' => $amount, 'credit' => 0,      'description' => 'Charge to customer'],
                ['account_id' => $inc, 'debit' => 0,      'credit' => $amount, 'description' => 'Other income'],
            ],
        ]);
    }

    /**
     * Write off a customer's bad debt (credit note that reduces udhaar).
     * Dr Bad Debts Written Off (5110), Cr Accounts Receivable (1030).
     */
    public static function postCustomerWriteOff(string $tenantId, int $userId, float $amount, string $customerId, string $desc = ''): void
    {
        if ($amount <= 0) return;
        $ar = self::ensureAccount($tenantId, self::RECEIVABLE);
        $bd = self::ensureAccount($tenantId, self::BAD_DEBT);
        if (!$ar || !$bd) return;

        self::createEntry($tenantId, $userId, [
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => 'Bad debt written off' . ($desc ? " – {$desc}" : ''),
            'reference_type' => 'customer_writeoff',
            'reference_id'   => $customerId,
            'lines'          => [
                ['account_id' => $bd, 'debit' => $amount, 'credit' => 0,      'description' => 'Bad debt expense'],
                ['account_id' => $ar, 'debit' => 0,      'credit' => $amount, 'description' => 'Receivable written off'],
            ],
        ]);
    }

    private static function loadAccounts(string $tenantId, array $codes): array
    {
        return Account::where('tenant_id', $tenantId)
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->toArray();
    }

    /**
     * Return the account id for a code, lazily creating it if the tenant's chart
     * predates the account (e.g. Opening Balance Equity / Bad Debts added later).
     * Returns null only if the tenant has no chart of accounts at all.
     */
    private static function ensureAccount(string $tenantId, string $code): ?string
    {
        $account = Account::where('tenant_id', $tenantId)->where('code', $code)->first();
        if ($account) {
            return $account->is_active ? $account->id : null;
        }

        // Don't conjure a chart for tenants that never set one up
        if (! Account::where('tenant_id', $tenantId)->exists()) {
            return null;
        }

        $defaults = [
            self::OPENING_EQ => ['name' => 'Opening Balance Equity', 'type' => 'equity',  'sub_type' => 'equity'],
            self::BAD_DEBT   => ['name' => 'Bad Debts Written Off',  'type' => 'expense', 'sub_type' => 'other_expense'],
            self::OTHER_INCOME => ['name' => 'Other Income',         'type' => 'income',  'sub_type' => 'revenue'],
        ];
        $def = $defaults[$code] ?? null;
        if (! $def) return null;

        return Account::create([
            'tenant_id' => $tenantId,
            'code'      => $code,
            'name'      => $def['name'],
            'type'      => $def['type'],
            'sub_type'  => $def['sub_type'],
            'is_system' => true,
            'is_active' => true,
        ])->id;
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
