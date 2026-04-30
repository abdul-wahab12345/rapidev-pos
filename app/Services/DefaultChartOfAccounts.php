<?php

namespace App\Services;

use App\Models\Account;

class DefaultChartOfAccounts
{
    // Default accounts grouped by type for Pakistani retail
    private static array $accounts = [
        // ── Assets ────────────────────────────────────────────────────
        ['code' => '1010', 'name' => 'Cash in Hand',           'type' => 'asset',     'sub_type' => 'current_asset',  'is_system' => true],
        ['code' => '1020', 'name' => 'Bank Account',           'type' => 'asset',     'sub_type' => 'bank',           'is_system' => true],
        ['code' => '1030', 'name' => 'Accounts Receivable (Udhaar)', 'type' => 'asset', 'sub_type' => 'receivable',  'is_system' => true],
        ['code' => '1040', 'name' => 'Inventory / Stock',      'type' => 'asset',     'sub_type' => 'current_asset',  'is_system' => true],
        ['code' => '1050', 'name' => 'Advance Payments',       'type' => 'asset',     'sub_type' => 'current_asset',  'is_system' => false],
        ['code' => '1100', 'name' => 'Shop / Furniture',       'type' => 'asset',     'sub_type' => 'fixed_asset',    'is_system' => false],
        ['code' => '1110', 'name' => 'Equipment',              'type' => 'asset',     'sub_type' => 'fixed_asset',    'is_system' => false],

        // ── Liabilities ────────────────────────────────────────────────
        ['code' => '2010', 'name' => 'Accounts Payable (Suppliers)', 'type' => 'liability', 'sub_type' => 'payable',  'is_system' => true],
        ['code' => '2020', 'name' => 'Sales Tax Payable (GST)',     'type' => 'liability', 'sub_type' => 'current_liability', 'is_system' => false],
        ['code' => '2030', 'name' => 'Salary Payable',         'type' => 'liability', 'sub_type' => 'current_liability', 'is_system' => false],
        ['code' => '2040', 'name' => 'Short-term Loan',        'type' => 'liability', 'sub_type' => 'current_liability', 'is_system' => false],
        ['code' => '2100', 'name' => 'Long-term Loan',         'type' => 'liability', 'sub_type' => 'long_term_liability', 'is_system' => false],

        // ── Equity ─────────────────────────────────────────────────────
        ['code' => '3010', 'name' => "Owner's Capital",        'type' => 'equity',    'sub_type' => 'equity',         'is_system' => true],
        ['code' => '3020', 'name' => "Owner's Drawings",       'type' => 'equity',    'sub_type' => 'equity',         'is_system' => false],
        ['code' => '3030', 'name' => 'Retained Earnings',      'type' => 'equity',    'sub_type' => 'equity',         'is_system' => true],

        // ── Income ─────────────────────────────────────────────────────
        ['code' => '4010', 'name' => 'Sales Revenue',          'type' => 'income',    'sub_type' => 'revenue',        'is_system' => true],
        ['code' => '4020', 'name' => 'Other Income',           'type' => 'income',    'sub_type' => 'revenue',        'is_system' => false],
        ['code' => '4030', 'name' => 'Sales Discount (Income)', 'type' => 'income',   'sub_type' => 'revenue',        'is_system' => false],

        // ── Expenses ───────────────────────────────────────────────────
        ['code' => '5010', 'name' => 'Cost of Goods Sold',     'type' => 'expense',   'sub_type' => 'cogs',           'is_system' => true],
        ['code' => '5020', 'name' => 'Rent Expense',           'type' => 'expense',   'sub_type' => 'operating_expense', 'is_system' => false],
        ['code' => '5030', 'name' => 'Salary & Wages',         'type' => 'expense',   'sub_type' => 'operating_expense', 'is_system' => false],
        ['code' => '5040', 'name' => 'Electricity & Utilities','type' => 'expense',   'sub_type' => 'operating_expense', 'is_system' => false],
        ['code' => '5050', 'name' => 'Mobile & Internet',      'type' => 'expense',   'sub_type' => 'operating_expense', 'is_system' => false],
        ['code' => '5060', 'name' => 'Transport & Delivery',   'type' => 'expense',   'sub_type' => 'operating_expense', 'is_system' => false],
        ['code' => '5070', 'name' => 'Repairs & Maintenance',  'type' => 'expense',   'sub_type' => 'operating_expense', 'is_system' => false],
        ['code' => '5080', 'name' => 'Marketing & Advertising','type' => 'expense',   'sub_type' => 'operating_expense', 'is_system' => false],
        ['code' => '5090', 'name' => 'Miscellaneous Expense',  'type' => 'expense',   'sub_type' => 'other_expense',  'is_system' => false],
        ['code' => '5100', 'name' => 'Bank Charges',           'type' => 'expense',   'sub_type' => 'other_expense',  'is_system' => false],
    ];

    public static function seedForTenant(string $tenantId): void
    {
        // Only seed if no accounts exist yet
        if (Account::where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $now = now();
        $rows = array_map(fn ($a) => array_merge($a, [
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'  => $tenantId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), self::$accounts);

        // Insert in chunks to avoid parameter limits
        foreach (array_chunk($rows, 10) as $chunk) {
            Account::insert($chunk);
        }
    }
}
