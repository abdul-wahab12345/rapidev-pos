<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Locations\LocationsController;
use App\Models\City;
use App\Models\Party;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $suppliers = $query
            ->with(['party.customer', 'districtCity:id,name', 'locality:id,name'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $allForStats = Supplier::where('tenant_id', $tenant->id);
        $stats = [
            'total'         => (clone $allForStats)->count(),
            'active'        => (clone $allForStats)->where('is_active', true)->count(),
            'total_payable' => (float) (clone $allForStats)->sum('current_balance'),
        ];

        return Inertia::render('Purchasing/Suppliers/Index', [
            'suppliers' => [
                'data' => collect($suppliers->items())->map(fn ($s) => SupplierService::indexRow($s)),
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
            'city_id'         => 'nullable|integer|exists:cities,id',
            'area_id'         => 'nullable|integer|exists:areas,id',
            'ntn'             => 'nullable|string|max:20',
            'payment_terms'   => 'integer|min:0|max:365',
            'opening_balance' => 'numeric|min:0',
            'notes'           => 'nullable|string|max:500',
        ]);

        $tenantId = (string) $request->user()->tenant_id;
        LocationsController::assertAreaMatchesCityTenant(
            isset($validated['city_id']) ? (int) $validated['city_id'] : null,
            isset($validated['area_id']) ? (int) $validated['area_id'] : null,
            $tenantId,
        );

        $cityLabel = isset($validated['city_id'])
            ? City::find((int) $validated['city_id'])?->name
            : null;

        $tenant = $request->user()->tenant;

        $supplier = DB::transaction(function () use ($validated, $tenant, $cityLabel, $tenantId) {
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
                $party->update([
                    'is_supplier' => true,
                    'company'     => $validated['company'] ?? $party->company,
                    'phone'       => $validated['phone'] ?? $party->phone,
                    'email'       => $validated['email'] ?? $party->email,
                    'address'     => $validated['address'] ?? $party->address,
                    'city'        => $cityLabel ?? $party->city,
                    'ntn'         => $validated['ntn'] ?? $party->ntn,
                ]);
            } else {
                $party = Party::create([
                    'tenant_id'   => $tenantId,
                    'name'        => $validated['name'],
                    'company'     => $validated['company'] ?? null,
                    'phone'       => $validated['phone'] ?? null,
                    'email'       => $validated['email'] ?? null,
                    'address'     => $validated['address'] ?? null,
                    'city'        => $cityLabel,
                    'ntn'         => $validated['ntn'] ?? null,
                    'is_customer' => false,
                    'is_supplier' => true,
                ]);
            }

            $opening = (float) ($validated['opening_balance'] ?? 0);

            return Supplier::create([
                'tenant_id'         => $tenant->id,
                'party_id'          => $party->id,
                'name'              => $validated['name'],
                'company'           => $validated['company'] ?? null,
                'phone'             => $validated['phone'] ?? null,
                'email'             => $validated['email'] ?? null,
                'address'           => $validated['address'] ?? null,
                'city'              => $cityLabel,
                'city_id'           => $validated['city_id'] ?? null,
                'area_id'           => $validated['area_id'] ?? null,
                'ntn'               => $validated['ntn'] ?? null,
                'payment_terms'     => $validated['payment_terms'] ?? 30,
                'opening_balance'   => $opening,
                'current_balance'   => $opening,
                'notes'             => $validated['notes'] ?? null,
                'is_active'         => true,
            ]);
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
            'city_id'       => 'nullable|integer|exists:cities,id',
            'area_id'       => 'nullable|integer|exists:areas,id',
            'ntn'           => 'nullable|string|max:20',
            'payment_terms' => 'integer|min:0|max:365',
            'is_active'     => 'boolean',
            'notes'         => 'nullable|string|max:500',
        ]);

        LocationsController::assertAreaMatchesCityTenant(
            isset($validated['city_id']) ? (int) $validated['city_id'] : null,
            isset($validated['area_id']) ? (int) $validated['area_id'] : null,
            (string) $request->user()->tenant_id,
        );

        $cityLabel = isset($validated['city_id'])
            ? City::find((int) $validated['city_id'])?->name
            : null;

        $supplier->update([
            'name'           => $validated['name'],
            'company'        => $validated['company'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
            'address'        => $validated['address'] ?? null,
            'city'           => $cityLabel,
            'city_id'        => $validated['city_id'] ?? null,
            'area_id'        => $validated['area_id'] ?? null,
            'ntn'            => $validated['ntn'] ?? null,
            'payment_terms'  => $validated['payment_terms'] ?? $supplier->payment_terms,
            'is_active'      => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $supplier->is_active,
            'notes'          => $validated['notes'] ?? null,
        ]);

        if ($party = $supplier->party()->first()) {
            $party->update([
                'name'        => $validated['name'],
                'company'     => $validated['company'] ?? $party->company,
                'phone'       => $validated['phone'] ?? $party->phone,
                'email'       => $validated['email'] ?? $party->email,
                'address'     => $validated['address'] ?? $party->address,
                'city'        => $cityLabel,
                'ntn'         => $validated['ntn'] ?? $party->ntn,
            ]);
        }

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
