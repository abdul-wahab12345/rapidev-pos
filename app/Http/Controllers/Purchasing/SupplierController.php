<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;

        $query = Supplier::where('tenant_id', $tenant->id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('company', 'ilike', "%{$s}%")
                  ->orWhere('phone', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $suppliers = $query->orderBy('name')->paginate(20)->withQueryString();

        $stats = [
            'total'          => Supplier::where('tenant_id', $tenant->id)->count(),
            'active'         => Supplier::where('tenant_id', $tenant->id)->where('is_active', true)->count(),
            'total_payable'  => (float) Supplier::where('tenant_id', $tenant->id)->sum('current_balance'),
        ];

        return Inertia::render('Purchasing/Suppliers/Index', [
            'suppliers' => [
                'data'         => collect($suppliers->items())->map(fn ($s) => [
                    'id'              => $s->id,
                    'name'            => $s->name,
                    'company'         => $s->company,
                    'phone'           => $s->phone,
                    'email'           => $s->email,
                    'city'            => $s->city,
                    'payment_terms'   => $s->payment_terms,
                    'current_balance' => (float) $s->current_balance,
                    'is_active'       => $s->is_active,
                ]),
                'current_page' => $suppliers->currentPage(),
                'last_page'    => $suppliers->lastPage(),
                'total'        => $suppliers->total(),
            ],
            'stats'   => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'company'         => 'nullable|string|max:100',
            'phone'           => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:100',
            'address'         => 'nullable|string|max:300',
            'city'            => 'nullable|string|max:100',
            'ntn'             => 'nullable|string|max:20',
            'payment_terms'   => 'integer|min:0|max:365',
            'opening_balance' => 'numeric|min:0',
            'notes'           => 'nullable|string|max:500',
        ]);

        $tenant   = auth()->user()->tenant;
        $supplier = Supplier::create(array_merge($validated, [
            'tenant_id'       => $tenant->id,
            'current_balance' => $validated['opening_balance'] ?? 0,
        ]));

        return back()->with('success', "Supplier {$supplier->name} added.");
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'company'       => 'nullable|string|max:100',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:100',
            'address'       => 'nullable|string|max:300',
            'city'          => 'nullable|string|max:100',
            'ntn'           => 'nullable|string|max:20',
            'payment_terms' => 'integer|min:0|max:365',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string|max:500',
        ]);

        $supplier->update($validated);
        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->current_balance > 0) {
            return back()->with('error', 'Cannot delete a supplier with outstanding balance.');
        }
        $supplier->delete();
        return back()->with('success', 'Supplier removed.');
    }
}
