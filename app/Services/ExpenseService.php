<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * Create an expense and post the journal entry atomically.
     */
    public static function create(array $data, string $tenantId, int $userId, ?string $branchId): Expense
    {
        return DB::transaction(function () use ($data, $tenantId, $userId, $branchId) {
            $expense = Expense::create([
                'tenant_id'      => $tenantId,
                'branch_id'      => $branchId,
                'account_id'     => $data['account_id'],
                'created_by'     => $userId,
                'expense_number' => Expense::nextNumber($tenantId),
                'expense_date'   => $data['expense_date'],
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'],
                'description'    => $data['description'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'reference'      => $data['reference'] ?? null,
            ]);

            AccountingService::postExpense($expense);

            return $expense;
        });
    }

    /**
     * Update an expense: reverse the old journal entry and post a new one.
     */
    public static function update(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            AccountingService::reverseExpense($expense);

            $expense->update([
                'account_id'     => $data['account_id'],
                'expense_date'   => $data['expense_date'],
                'amount'         => $data['amount'],
                'payment_method' => $data['payment_method'],
                'description'    => $data['description'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'reference'      => $data['reference'] ?? null,
            ]);

            $expense->refresh();
            AccountingService::postExpense($expense);

            return $expense;
        });
    }

    /**
     * Delete an expense and reverse its journal entry.
     */
    public static function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            AccountingService::reverseExpense($expense);
            $expense->delete();
        });
    }

    /**
     * Expense accounts available for selection (type = expense, active).
     */
    public static function expenseAccounts(string $tenantId): array
    {
        return Account::where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'sub_type'])
            ->map(fn ($a) => [
                'id'       => $a->id,
                'code'     => $a->code,
                'name'     => $a->name,
                'sub_type' => $a->sub_type,
                'label'    => "{$a->code} — {$a->name}",
            ])
            ->values()
            ->toArray();
    }

    /**
     * Summary stats for the index page.
     */
    public static function stats(string $tenantId): array
    {
        $base = Expense::where('expenses.tenant_id', $tenantId);

        return [
            'this_month'  => (float) (clone $base)->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount'),
            'this_year'   => (float) (clone $base)->whereYear('expense_date', now()->year)->sum('amount'),
            'total_count' => (clone $base)->whereYear('expense_date', now()->year)->count(),
            'by_category' => (clone $base)
                ->whereYear('expense_date', now()->year)
                ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                ->selectRaw('accounts.name as category, accounts.code, SUM(expenses.amount) as total')
                ->groupBy('accounts.id', 'accounts.name', 'accounts.code')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($r) => ['category' => $r->category, 'code' => $r->code, 'total' => (float) $r->total])
                ->toArray(),
        ];
    }
}
