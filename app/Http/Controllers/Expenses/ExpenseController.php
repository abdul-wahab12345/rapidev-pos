<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;

        $query = Expense::with('account')
            ->where('tenant_id', $tenant->id);

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date_from')) {
            $query->where('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('expense_date', '<=', $request->date_to);
        }

        $expenses = $query->orderByDesc('expense_date')
            ->orderByDesc('expense_number')
            ->paginate(20)
            ->through(fn ($e) => [
                'id'             => $e->id,
                'expense_number' => $e->expense_number,
                'expense_date'   => $e->expense_date->format('Y-m-d'),
                'account_id'     => $e->account_id,
                'account_name'   => $e->account?->name,
                'account_code'   => $e->account?->code,
                'amount'         => (float) $e->amount,
                'payment_method' => $e->payment_method,
                'description'    => $e->description,
                'notes'          => $e->notes,
                'reference'      => $e->reference,
            ]);

        return Inertia::render('Expenses/Index', [
            'expenses'        => $expenses,
            'stats'           => ExpenseService::stats($tenant->id),
            'expense_accounts' => ExpenseService::expenseAccounts($tenant->id),
            'filters'         => $request->only(['account_id', 'payment_method', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id'     => 'required|uuid|exists:accounts,id',
            'expense_date'   => 'required|date',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank',
            'description'    => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:1000',
            'reference'      => 'nullable|string|max:100',
        ]);

        $user   = auth()->user();
        $branch = $user->branch_id ?? null;

        ExpenseService::create($data, $user->tenant_id, $user->id, $branch);

        return back()->with('success', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'account_id'     => 'required|uuid|exists:accounts,id',
            'expense_date'   => 'required|date',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank',
            'description'    => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:1000',
            'reference'      => 'nullable|string|max:100',
        ]);

        ExpenseService::update($expense, $data);

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        ExpenseService::delete($expense);

        return back()->with('success', 'Expense deleted.');
    }
}
