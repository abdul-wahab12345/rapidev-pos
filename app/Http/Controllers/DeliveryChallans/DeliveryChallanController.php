<?php

namespace App\Http\Controllers\DeliveryChallans;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanItem;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryChallanController extends Controller
{
    public function index(Request $request): Response
    {
        $challans = DeliveryChallan::with('customer')
            ->when($request->search, function ($q) use ($request) {
                $q->where('challan_number', 'like', "%{$request->search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$request->search}%"));
            })
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $challans->through(fn ($c) => [
            'id'             => $c->id,
            'challan_number' => $c->challan_number,
            'status'         => $c->status,
            'customer'       => $c->customer ? ['id' => $c->customer->id, 'name' => $c->customer->name] : null,
            'site_address'   => $c->site_address,
            'delivery_date'  => $c->delivery_date?->format('Y-m-d'),
            'vehicle_number' => $c->vehicle_number,
            'driver_name'    => $c->driver_name,
            'created_at'     => $c->created_at->format('Y-m-d'),
        ]);

        $stats = [
            'total'      => DeliveryChallan::count(),
            'pending'    => DeliveryChallan::where('status', 'pending')->count(),
            'dispatched' => DeliveryChallan::where('status', 'dispatched')->count(),
            'delivered'  => DeliveryChallan::where('status', 'delivered')->count(),
        ];

        return Inertia::render('DeliveryChallans/Index', [
            'challans' => $challans,
            'stats'    => $stats,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        $quotationId = $request->get('quotation_id');
        $saleId      = $request->get('sale_id');

        $prefill = null;
        if ($quotationId) {
            $q = Quotation::with(['customer', 'items'])->find($quotationId);
            if ($q) {
                $prefill = [
                    'source'       => 'quotation',
                    'quotation_id' => $q->id,
                    'customer_id'  => $q->customer_id,
                    'site_address' => $q->site_address,
                    'items'        => $q->items->map(fn ($i) => [
                        'product_id'   => $i->product_id,
                        'product_name' => $i->product_name,
                        'product_unit' => $i->product_unit,
                        'quantity'     => (float) $i->quantity,
                    ])->toArray(),
                ];
            }
        } elseif ($saleId) {
            $s = Sale::with(['customer', 'items'])->find($saleId);
            if ($s) {
                $prefill = [
                    'source'      => 'sale',
                    'sale_id'     => $s->id,
                    'customer_id' => $s->customer_id,
                    'items'       => $s->items->map(fn ($i) => [
                        'product_id'   => $i->product_id,
                        'product_name' => $i->product_name,
                        'product_unit' => 'piece',
                        'quantity'     => $i->quantity,
                    ])->toArray(),
                ];
            }
        }

        return Inertia::render('DeliveryChallans/Create', [
            'customers' => $this->getCustomerOptions(),
            'products'  => $this->getProductOptions(),
            'prefill'   => $prefill,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'       => 'nullable|exists:customers,id',
            'sale_id'           => 'nullable|exists:sales,id',
            'quotation_id'      => 'nullable|exists:quotations,id',
            'delivery_date'     => 'nullable|date',
            'vehicle_number'    => 'nullable|string|max:50',
            'driver_name'       => 'nullable|string|max:100',
            'site_address'      => 'nullable|string|max:500',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.product_unit' => 'nullable|string',
            'items.*.quantity'     => 'required|numeric|min:0.001',
            'items.*.lot_number'   => 'nullable|string',
            'items.*.notes'        => 'nullable|string',
        ]);

        $challan = DB::transaction(function () use ($validated) {
            $tenantId = auth()->user()->tenant_id;

            $challan = DeliveryChallan::create([
                'tenant_id'      => $tenantId,
                'customer_id'    => $validated['customer_id'] ?? null,
                'sale_id'        => $validated['sale_id'] ?? null,
                'quotation_id'   => $validated['quotation_id'] ?? null,
                'user_id'        => auth()->id(),
                'challan_number' => DeliveryChallan::generateNumber($tenantId),
                'status'         => 'pending',
                'delivery_date'  => $validated['delivery_date'] ?? null,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'driver_name'    => $validated['driver_name'] ?? null,
                'site_address'   => $validated['site_address'] ?? null,
                'notes'          => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                DeliveryChallanItem::create([
                    'challan_id'   => $challan->id,
                    'product_id'   => $itemData['product_id'] ?? null,
                    'product_name' => $itemData['product_name'],
                    'product_unit' => $itemData['product_unit'] ?? 'piece',
                    'lot_number'   => $itemData['lot_number'] ?? null,
                    'quantity'     => $itemData['quantity'],
                    'notes'        => $itemData['notes'] ?? null,
                ]);
            }

            return $challan;
        });

        return redirect()->route('challans.show', $challan)
            ->with('success', "Delivery Challan {$challan->challan_number} created.");
    }

    public function show(DeliveryChallan $challan): Response
    {
        $challan->load(['customer', 'items.product', 'user', 'sale', 'quotation']);

        $tenant   = auth()->user()->tenant;
        $settings = $tenant?->settings ?? [];

        return Inertia::render('DeliveryChallans/Show', [
            'business' => [
                'name'    => data_get($settings, 'business_name', $tenant?->name ?? ''),
                'phone'   => data_get($settings, 'business_phone'),
                'address' => data_get($settings, 'business_address'),
                'city'    => data_get($settings, 'business_city'),
                'logo'    => data_get($settings, 'logo_url'),
                'footer'  => data_get($settings, 'receipt_footer', 'Thank you for your business!'),
            ],
            'challan' => [
                'id'             => $challan->id,
                'challan_number' => $challan->challan_number,
                'status'         => $challan->status,
                'delivery_date'  => $challan->delivery_date?->format('Y-m-d'),
                'vehicle_number' => $challan->vehicle_number,
                'driver_name'    => $challan->driver_name,
                'site_address'   => $challan->site_address,
                'notes'          => $challan->notes,
                'created_at'     => $challan->created_at->format('Y-m-d H:i'),
                'created_by'     => $challan->user?->name,
                'customer'       => $challan->customer ? [
                    'id'    => $challan->customer->id,
                    'name'  => $challan->customer->name,
                    'phone' => $challan->customer->phone,
                ] : null,
                'sale'           => $challan->sale ? [
                    'id'             => $challan->sale->id,
                    'invoice_number' => $challan->sale->invoice_number,
                ] : null,
                'quotation'      => $challan->quotation ? [
                    'id'               => $challan->quotation->id,
                    'quotation_number' => $challan->quotation->quotation_number,
                ] : null,
                'items'          => $challan->items->map(fn ($item) => [
                    'id'           => $item->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_unit' => $item->product_unit,
                    'lot_number'   => $item->lot_number,
                    'quantity'     => (float) $item->quantity,
                    'notes'        => $item->notes,
                    'material_type'=> $item->product?->material_type,
                ]),
            ],
        ]);
    }

    public function updateStatus(Request $request, DeliveryChallan $challan): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,dispatched,delivered',
        ]);

        $challan->update(['status' => $validated['status']]);

        return back()->with('success', "Challan status updated to {$validated['status']}.");
    }

    public function destroy(DeliveryChallan $challan): RedirectResponse
    {
        abort_if($challan->status === 'delivered', 403, 'Cannot delete a delivered challan.');

        $number = $challan->challan_number;
        $challan->delete();

        return redirect()->route('challans.index')
            ->with('success', "Challan {$number} deleted.");
    }

    private function getCustomerOptions(): array
    {
        return Customer::orderBy('name')->get(['id', 'name', 'phone'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])
            ->toArray();
    }

    private function getProductOptions(): array
    {
        return Product::active()->with('stockLevels')->orderBy('name')->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'unit'          => $p->unit,
                'material_type' => $p->material_type,
                'stock'         => $p->stockLevels->sum('quantity'),
            ])
            ->toArray();
    }
}
