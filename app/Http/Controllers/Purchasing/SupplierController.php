<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Support\Facades\DB;
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

        $suppliers = $query->with('party.customer')->orderBy('name')->paginate(20)->withQueryString();

        $allForStats = Supplier::where('tenant_id', $tenant->id);
        $stats = [
            'total'          => (clone $allForStats)->count(),
            'active'         => (clone $allForStats)->where('is_active', true)->count(),
            'total_payable'  => (float) (clone $allForStats)->sum('current_balance'),
        ];

        return Inertia::render('Purchasing/Suppliers/Index', [
            'suppliers' => [
                'data'         => collect($suppliers->items())->map(fn ($s) => SupplierService::indexRow($s)),
                'current_page' => $suppliers->currentPage(),
                'last_page'    => $suppliers->lastPage(),
                'total'        => $suppliers->total(),
            ],
            'stats'   => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Supplier $supplier): Response
    {
        return Inertia::render('Purchasing/Suppliers/Show', SupplierService::showData($supplier));
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

        $tenant = auth()->user()->tenant;

        $supplier = DB::transaction(function () use ($validated, $tenant) {
            // Reuse an existing party if name+phone match (contractor scenario)
            $party = null;
            if (! empty($validated['phone'])) {
                $party = Party::where('tenant_id', $tenant->id)
                    ->where('phone', $validated['phone'])
                    ->first();
            }
            if (! $party && ! empty($validated['name'])) {
                $party = Party::where('tenant_id', $tenant->id)
                    ->where('name', $validated['name'])
                    ->first();
            }

            if ($party) {
                $party->update(['is_supplier' => true]);
            } else {
                $party = Party::create([
                    'tenant_id'   => $tenant->id,
                    'name'        => $validated['name'],
                    'company'     => $validated['company'] ?? null,
                    'phone'       => $validated['phone'] ?? null,
                    'email'       => $validated['email'] ?? null,
                    'address'     => $validated['address'] ?? null,
                    'city'        => $validated['city'] ?? null,
                    'ntn'         => $validated['ntn'] ?? null,
                    'is_customer' => false,
                    'is_supplier' => true,
                ]);
            }

            return Supplier::create(array_merge($validated, [
                'tenant_id'       => $tenant->id,
                'party_id'        => $party->id,
                'current_balance' => $validated['opening_balance'] ?? 0,
            ]));
        });

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
