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
        $accounts = self::loadAccounts($tenantId, [self::CASH, self::BANK, self::RECEIVABLE, self::REVENUE]);

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

        self::createEntry($tenantId, $return->created_by, [
            'entry_date'     => $return->return_date->format('Y-m-d'),
            'description'    => "Return {$return->return_number} – {$saleInvoice}",
            'reference_type' => 'return',
            'reference_id'   => $return->id,
            'lines'          => [
                ['account_id' => $accounts[self::REVENUE], 'debit' => $amount, 'credit' => 0,      'description' => "Revenue reversed – {$return->return_number}"],
                ['account_id' => $creditAccountId,         'debit' => 0,       'credit' => $amount, 'description' => ucfirst(str_replace('_', ' ', $return->refund_method)) . ' refund'],
            ],
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
