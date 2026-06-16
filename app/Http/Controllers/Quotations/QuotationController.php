<?php

namespace App\Http\Controllers\Quotations;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $service) {}

    public function index(Request $request): Response
    {
        $quotations = Quotation::with('customer')
            ->when($request->search, function ($q) use ($request) {
                $q->where('quotation_number', 'like', "%{$request->search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$request->search}%"));
            })
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $quotations->through(fn ($q) => [
            'id'               => $q->id,
            'quotation_number' => $q->quotation_number,
            'status'           => $q->status,
            'customer'         => $q->customer ? ['id' => $q->customer->id, 'name' => $q->customer->name] : null,
            'site_address'     => $q->site_address,
            'total'            => (float) $q->total,
            'advance_paid'     => (float) $q->advance_paid,
            'balance_due'      => $q->balance_due,
            'valid_until'      => $q->valid_until?->format('Y-m-d'),
            'created_at'       => $q->created_at->format('Y-m-d'),
        ]);

        $stats = [
            'total'     => Quotation::count(),
            'draft'     => Quotation::where('status', 'draft')->count(),
            'approved'  => Quotation::where('status', 'approved')->count(),
            'converted' => Quotation::where('status', 'converted')->count(),
            'total_value' => (float) Quotation::whereNotIn('status', ['cancelled'])->sum('total'),
        ];

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'stats'      => $stats,
            'filters'    => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Quotations/Create', [
            'customers' => $this->getCustomerOptions(),
            'products'  => $this->getProductOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'       => 'nullable|exists:customers,id',
            'site_address'      => 'nullable|string|max:500',
            'valid_until'       => 'nullable|date',
            'discount'          => 'nullable|numeric|min:0',
            'tax'               => 'nullable|numeric|min:0',
            'delivery_fee'      => 'nullable|numeric|min:0',
            'advance_paid'      => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.variant_id'   => 'nullable|exists:product_variants,id',
            'items.*.product_name' => 'required|string',
            'items.*.product_unit' => 'nullable|string',
            'items.*.quantity'     => 'required|numeric|min:0.001',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.notes'        => 'nullable|string',
        ]);

        $quotation = $this->service->create(
            $validated,
            auth()->id(),
            auth()->user()->tenant_id
        );

        return redirect()->route('quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->quotation_number} created.");
    }

    public function show(Quotation $quotation): Response
    {
        $quotation->load(['customer', 'items.product', 'user']);

        $tenant   = auth()->user()->tenant;
        $settings = $tenant?->settings ?? [];

        return Inertia::render('Quotations/Show', [
            'business' => [
                'name'    => data_get($settings, 'business_name', $tenant?->name ?? ''),
                'phone'   => data_get($settings, 'business_phone'),
                'address' => data_get($settings, 'business_address'),
                'city'    => data_get($settings, 'business_city'),
                'logo'    => data_get($settings, 'logo_url'),
                'footer'  => data_get($settings, 'receipt_footer', 'Thank you for your business!'),
            ],
            'quotation' => [
                'id'               => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'status'           => $quotation->status,
                'site_address'     => $quotation->site_address,
                'valid_until'      => $quotation->valid_until?->format('Y-m-d'),
                'subtotal'         => (float) $quotation->subtotal,
                'discount'         => (float) $quotation->discount,
                'tax'              => (float) $quotation->tax,
                'delivery_fee'     => (float) $quotation->delivery_fee,
                'total'            => (float) $quotation->total,
                'advance_paid'     => (float) $quotation->advance_paid,
                'balance_due'      => $quotation->balance_due,
                'notes'            => $quotation->notes,
                'converted_sale_id'=> $quotation->converted_sale_id,
                'created_at'       => $quotation->created_at->format('Y-m-d H:i'),
                'customer'         => $quotation->customer ? [
                    'id'    => $quotation->customer->id,
                    'name'  => $quotation->customer->name,
                    'phone' => $quotation->customer->phone,
                ] : null,
                'created_by'       => $quotation->user?->name,
                'items'            => $quotation->items->map(fn ($item) => [
                    'id'           => $item->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_unit' => $item->product_unit,
                    'quantity'     => (float) $item->quantity,
                    'unit_price'   => (float) $item->unit_price,
                    'discount'     => (float) $item->discount,
                    'line_total'   => (float) $item->line_total,
                    'notes'        => $item->notes,
                    // Tile info from related product
                    'sq_m_per_box' => $item->product ? (float) ($item->product->sq_m_per_box ?? 0) : 0,
                    'material_type'=> $item->product?->material_type,
                ]),
            ],
            'customers' => $this->getCustomerOptions(),
            'products'  => $this->getProductOptions(),
        ]);
    }

    public function edit(Quotation $quotation): Response
    {
        abort_if(in_array($quotation->status, ['converted', 'cancelled']), 403, 'Cannot edit this quotation.');

        $quotation->load(['customer', 'items']);

        return Inertia::render('Quotations/Edit', [
            'quotation' => [
                'id'               => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'status'           => $quotation->status,
                'customer_id'      => $quotation->customer_id,
                'site_address'     => $quotation->site_address,
                'valid_until'      => $quotation->valid_until?->format('Y-m-d'),
                'discount'         => (float) $quotation->discount,
                'tax'              => (float) $quotation->tax,
                'delivery_fee'     => (float) $quotation->delivery_fee,
                'advance_paid'     => (float) $quotation->advance_paid,
                'notes'            => $quotation->notes,
                'items'            => $quotation->items->map(fn ($item) => [
                    'id'           => $item->id,
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'product_name' => $item->product_name,
                    'product_unit' => $item->product_unit,
                    'quantity'     => (float) $item->quantity,
                    'unit_price'   => (float) $item->unit_price,
                    'discount'     => (float) $item->discount,
                    'notes'        => $item->notes,
                ]),
            ],
            'customers' => $this->getCustomerOptions(),
            'products'  => $this->getProductOptions(),
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_if(in_array($quotation->status, ['converted', 'cancelled']), 403);

        $validated = $request->validate([
            'customer_id'       => 'nullable|exists:customers,id',
            'site_address'      => 'nullable|string|max:500',
            'valid_until'       => 'nullable|date',
            'discount'          => 'nullable|numeric|min:0',
            'tax'               => 'nullable|numeric|min:0',
            'delivery_fee'      => 'nullable|numeric|min:0',
            'advance_paid'      => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.variant_id'   => 'nullable|exists:product_variants,id',
            'items.*.product_name' => 'required|string',
            'items.*.product_unit' => 'nullable|string',
            'items.*.quantity'     => 'required|numeric|min:0.001',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.notes'        => 'nullable|string',
        ]);

        $this->service->update($quotation, $validated);

        return redirect()->route('quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->quotation_number} updated.");
    }

    public function updateStatus(Request $request, Quotation $quotation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,approved,expired,cancelled',
        ]);

        abort_if($quotation->status === 'converted', 403, 'Cannot change status of a converted quotation.');

        $quotation->update(['status' => $validated['status']]);

        return back()->with('success', "Quotation status updated to {$validated['status']}.");
    }

    public function convert(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_if($quotation->status !== 'approved', 403, 'Only approved quotations can be converted to a sale.');

        $validated = $request->validate([
            'cash'       => 'nullable|numeric|min:0',
            'jazzcash'   => 'nullable|numeric|min:0',
            'easypaisa'  => 'nullable|numeric|min:0',
            'bank'       => 'nullable|numeric|min:0',
            'udhaar'     => 'nullable|numeric|min:0',
        ]);

        $sale = $this->service->convertToSale($quotation, $validated, auth()->id());

        return redirect()->route('sales.show', $sale)
            ->with('success', "Quotation converted to Sale {$sale->invoice_number}.");
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        abort_if($quotation->status === 'converted', 403, 'Cannot delete a converted quotation.');

        $number = $quotation->quotation_number;
        $quotation->delete();

        return redirect()->route('quotations.index')
            ->with('success', "Quotation {$number} deleted.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getCustomerOptions(): array
    {
        return Customer::orderBy('name')
            ->get(['id', 'name', 'phone', 'discount_percent'])
            ->map(fn ($c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'phone'            => $c->phone,
                'discount_percent' => (float) $c->discount_percent,
            ])
            ->toArray();
    }

    private function getProductOptions(): array
    {
        return Product::active()
            ->with(['variants', 'stockLevels'])
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'sku'           => $p->sku,
                'unit'          => $p->unit,
                'selling_price' => (float) $p->selling_price,
                'material_type' => $p->material_type,
                'sq_m_per_box'  => $p->sq_m_per_box ? (float) $p->sq_m_per_box : null,
                'tile_width_in' => $p->tile_width_in ? (float) $p->tile_width_in : null,
                'tile_height_in'=> $p->tile_height_in ? (float) $p->tile_height_in : null,
                'tiles_per_box' => $p->tiles_per_box,
                'stock'         => $p->stockLevels->sum('quantity'),
                'variants'      => $p->has_variants ? $p->variants->where('is_active', true)->map(fn ($v) => [
                    'id'            => $v->id,
                    'label'         => $v->label,
                    'selling_price' => (float) $v->selling_price,
                    'stock'         => $v->stockLevels->sum('quantity'),
                ])->values() : [],
            ])
            ->toArray();
    }
}
