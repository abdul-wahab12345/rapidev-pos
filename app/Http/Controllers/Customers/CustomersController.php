<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomersController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::when($request->filled('search'), fn ($q) => $q->search($request->search))
            ->when($request->filled('balance'), function ($q) use ($request) {
                match ($request->balance) {
                    'has_udhaar' => $q->where('current_balance', '>', 0),
                    'clear'      => $q->where('current_balance', 0),
                    default      => null,
                };
            })
            ->orderByDesc('current_balance')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total'        => Customer::count(),
            'with_udhaar'  => Customer::where('current_balance', '>', 0)->count(),
            'total_udhaar' => (float) Customer::sum('current_balance'),
            'total_spend'  => (float) Customer::sum('total_spend'),
        ];

        return Inertia::render('Customers/Index', [
            'customers' => [
                'data'         => collect($customers->items())->map(fn ($c) => [
                    'id'              => $c->id,
                    'name'            => $c->name,
                    'phone'           => $c->phone,
                    'cnic'            => $c->cnic,
                    'address'         => $c->address,
                    'current_balance' => (float) $c->current_balance,
                    'credit_limit'    => (float) $c->credit_limit,
                    'total_spend'     => (float) $c->total_spend,
                    'created_at'      => $c->created_at,
                ]),
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'total'        => $customers->total(),
                'links'        => $customers->linkCollection()->toArray(),
            ],
            'stats'   => $stats,
            'filters' => $request->only(['search', 'balance']),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $ledger = CustomerLedgerEntry::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        // Recent sales
        $recentSales = $customer->sales()
            ->where('status', '!=', 'voided')
            ->with('branch:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id'              => $customer->id,
                'name'            => $customer->name,
                'phone'           => $customer->phone,
                'cnic'            => $customer->cnic,
                'address'         => $customer->address,
                'notes'           => $customer->notes,
                'current_balance' => (float) $customer->current_balance,
                'credit_limit'    => (float) $customer->credit_limit,
                'total_spend'     => (float) $customer->total_spend,
                'created_at'      => $customer->created_at,
            ],
            'ledger' => [
                'data'         => collect($ledger->items())->map(fn ($e) => [
                    'id'              => $e->id,
                    'type'            => $e->type,
                    'amount'          => (float) $e->amount,
                    'running_balance' => (float) $e->running_balance,
                    'description'     => $e->description,
                    'payment_method'  => $e->payment_method,
                    'created_at'      => $e->created_at,
                ]),
                'current_page' => $ledger->currentPage(),
                'last_page'    => $ledger->lastPage(),
                'total'        => $ledger->total(),
                'links'        => $ledger->linkCollection()->toArray(),
            ],
            'recent_sales' => $recentSales->map(fn ($s) => [
                'id'             => $s->id,
                'invoice_number' => $s->invoice_number,
                'total'          => (float) $s->total,
                'udhaar_amount'  => (float) $s->udhaar_amount,
                'payment_method' => $s->payment_method,
                'status'         => $s->status,
                'created_at'     => $s->created_at,
                'branch'         => $s->branch?->name,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Form', ['customer' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'cnic'         => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:500',
            'notes'        => 'nullable|string|max:1000',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        Customer::create([
            'name'         => $validated['name'],
            'phone'        => $validated['phone'] ?? null,
            'cnic'         => $validated['cnic'] ?? null,
            'address'      => $validated['address'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'credit_limit' => isset($validated['credit_limit']) ? (float) $validated['credit_limit'] : 0,
            'current_balance' => 0,
            'total_spend'     => 0,
        ]);

        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Form', [
            'customer' => [
                'id'           => $customer->id,
                'name'         => $customer->name,
                'phone'        => $customer->phone,
                'cnic'         => $customer->cnic,
                'address'      => $customer->address,
                'notes'        => $customer->notes,
                'credit_limit' => (float) $customer->credit_limit,
            ],
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'phone'        => 'nullable|string|max:20',
            'cnic'         => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:500',
            'notes'        => 'nullable|string|max:1000',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $customer->update([
            'name'         => $validated['name'],
            'phone'        => $validated['phone'] ?? null,
            'cnic'         => $validated['cnic'] ?? null,
            'address'      => $validated['address'] ?? null,
            'notes'        => $validated['notes'] ?? null,
            'credit_limit' => isset($validated['credit_limit']) ? (float) $validated['credit_limit'] : (float) $customer->credit_limit,
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->current_balance > 0) {
            return back()->with('error', 'Cannot delete a customer with outstanding udhaar balance.');
        }
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Customer deleted.');
    }

    // Record a payment against outstanding udhaar balance
    public function recordPayment(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'amount'  => 'required|numeric|min:1',
            'method'  => 'required|in:cash,jazzcash,easypaisa',
            'notes'   => 'nullable|string|max:500',
        ]);

        $amount = (float) $validated['amount'];

        if ($amount > (float) $customer->current_balance) {
            return back()->with('error', 'Payment exceeds outstanding balance.');
        }

        DB::transaction(function () use ($customer, $amount, $validated) {
            $newBalance = round((float) $customer->current_balance - $amount, 2);

            DB::table('customers')
                ->where('id', $customer->id)
                ->update(['current_balance' => $newBalance]);

            CustomerLedgerEntry::create([
                'tenant_id'       => $customer->tenant_id,
                'customer_id'     => $customer->id,
                'sale_id'         => null,
                'type'            => 'payment',
                'amount'          => -$amount,
                'running_balance' => $newBalance,
                'description'     => 'Payment received' . ($validated['notes'] ? ': ' . $validated['notes'] : ''),
                'payment_method'  => $validated['method'],
            ]);
        });

        // Post to accounting ledger
        try {
            AccountingService::postCustomerPayment(
                $customer->tenant_id,
                auth()->id(),
                $amount,
                $customer->id,
                $customer->name
            );
        } catch (\Throwable $e) {
            \Log::warning("AccountingService::postCustomerPayment failed: " . $e->getMessage());
        }

        return back()->with('success', 'Payment of Rs ' . number_format($amount) . ' recorded.');
    }
}
