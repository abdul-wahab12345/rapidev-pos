<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;

        $query = PurchaseOrder::with('supplier:id,name')
            ->where('tenant_id', $tenant->id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('po_number', 'ilike', "%{$s}%")
                  ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'ilike', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderByDesc('order_date')->orderByDesc('po_number')->paginate(20)->withQueryString();

        $stats = [
            'total'      => PurchaseOrder::where('tenant_id', $tenant->id)->count(),
            'pending'    => PurchaseOrder::where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'ordered', 'partial'])->count(),
            'total_due'  => (float) PurchaseOrder::where('tenant_id', $tenant->id)
                                ->whereIn('status', ['received', 'partial', 'ordered'])
                                ->selectRaw('SUM(total - paid_amount) as due')
                                ->value('due'),
            'this_month' => (float) PurchaseOrder::where('tenant_id', $tenant->id)
                                ->whereMonth('order_date', now()->month)
                                ->sum('total'),
        ];

        $suppliers = Supplier::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms']);

        return Inertia::render('Purchasing/Orders/Index', [
            'orders' => [
                'data'         => collect($orders->items())->map(fn ($o) => [
                    'id'           => $o->id,
                    'po_number'    => $o->po_number,
                    'supplier'     => ['name' => $o->supplier?->name],
                    'order_date'   => $o->order_date?->format('Y-m-d'),
                    'expected_date'=> $o->expected_date?->format('Y-m-d'),
                    'status'       => $o->status,
                    'total'        => (float) $o->total,
                    'paid_amount'  => (float) $o->paid_amount,
                    'amount_due'   => $o->amountDue(),
                ]),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
            'stats'     => $stats,
            'suppliers' => $suppliers,
            'filters'   => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = auth()->user()->tenant;

        $suppliers = Supplier::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms']);

        $products = Product::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('variants:id,product_id,size,color,cost_price')
            ->orderBy('name')
            ->get(['id', 'name', 'name_ur', 'sku', 'unit', 'cost_price', 'has_variants'])
            ->map(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'name_ur'      => $p->name_ur,
                'sku'          => $p->sku,
                'unit'         => $p->unit,
                'cost_price'   => (float) $p->cost_price,
                'has_variants' => $p->has_variants,
                'variants'     => $p->variants->map(fn ($v) => [
                    'id'         => $v->id,
                    'label'      => trim("{$v->size} {$v->color}"),
                    'cost_price' => (float) $v->cost_price,
                ]),
            ]);

        return Inertia::render('Purchasing/Orders/Create', [
            'suppliers'    => $suppliers,
            'products'     => $products,
            'today'        => now()->format('Y-m-d'),
            'supplier_id'  => $request->get('supplier'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'    => 'required|exists:suppliers,id',
            'order_date'     => 'required|date',
            'expected_date'  => 'nullable|date|after_or_equal:order_date',
            'payment_method' => 'required|in:cash,bank,credit',
            'notes'          => 'nullable|string|max:500',
            'items'          => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.variant_id'      => 'nullable|exists:product_variants,id',
            'items.*.quantity_ordered'=> 'required|integer|min:1',
            'items.*.unit_cost'       => 'required|numeric|min:0',
            'items.*.product_name'    => 'required|string',
            'items.*.variant_label'   => 'nullable|string',
        ]);

        $tenant = auth()->user()->tenant;
        $branch = $tenant->defaultBranch();

        if (!$branch) return back()->with('error', 'No branch configured.');

        $po = DB::transaction(function () use ($validated, $tenant, $branch) {
            $subtotal = collect($validated['items'])->sum(fn ($i) => $i['unit_cost'] * $i['quantity_ordered']);

            $po = PurchaseOrder::create([
                'tenant_id'      => $tenant->id,
                'branch_id'      => $branch->id,
                'supplier_id'    => $validated['supplier_id'],
                'created_by'     => auth()->id(),
                'po_number'      => PurchaseOrder::nextNumber($tenant->id),
                'order_date'     => $validated['order_date'],
                'expected_date'  => $validated['expected_date'] ?? null,
                'status'         => 'ordered',
                'subtotal'       => $subtotal,
                'total'          => $subtotal,
                'payment_method' => $validated['payment_method'],
                'notes'          => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'variant_id'        => $item['variant_id'] ?? null,
                    'product_name'      => $item['product_name'],
                    'variant_label'     => $item['variant_label'] ?? null,
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost'         => $item['unit_cost'],
                    'line_total'        => $item['unit_cost'] * $item['quantity_ordered'],
                ]);
            }

            return $po;
        });

        return redirect()->route('purchasing.orders.show', $po->id)
            ->with('success', "Purchase Order {$po->po_number} created.");
    }

    public function show(PurchaseOrder $order): Response
    {
        $order->load(['supplier', 'items.product', 'items.variant', 'creator']);

        return Inertia::render('Purchasing/Orders/Show', [
            'order' => [
                'id'             => $order->id,
                'po_number'      => $order->po_number,
                'order_date'     => $order->order_date?->format('Y-m-d'),
                'expected_date'  => $order->expected_date?->format('Y-m-d'),
                'received_date'  => $order->received_date?->format('Y-m-d'),
                'status'         => $order->status,
                'payment_method' => $order->payment_method,
                'subtotal'       => (float) $order->subtotal,
                'discount'       => (float) $order->discount,
                'tax'            => (float) $order->tax,
                'total'          => (float) $order->total,
                'paid_amount'    => (float) $order->paid_amount,
                'amount_due'     => $order->amountDue(),
                'notes'          => $order->notes,
                'created_by'     => $order->creator?->name,
                'supplier'       => [
                    'id'    => $order->supplier?->id,
                    'name'  => $order->supplier?->name,
                    'phone' => $order->supplier?->phone,
                    'city'  => $order->supplier?->city,
                ],
                'items' => $order->items->map(fn ($i) => [
                    'id'                => $i->id,
                    'product_id'        => $i->product_id,
                    'product_name'      => $i->product_name,
                    'variant_label'     => $i->variant_label,
                    'quantity_ordered'  => $i->quantity_ordered,
                    'quantity_received' => $i->quantity_received,
                    'unit_cost'         => (float) $i->unit_cost,
                    'line_total'        => (float) $i->line_total,
                ]),
            ],
        ]);
    }

    // Mark the entire PO as received — increments stock, posts AP journal entry
    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        if (in_array($order->status, ['received', 'cancelled'])) {
            return back()->with('error', 'This order is already ' . $order->status . '.');
        }

        $validated = $request->validate([
            'items'                     => 'required|array',
            'items.*.id'                => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|integer|min:0',
        ]);

        $tenant = auth()->user()->tenant;
        $branch = $tenant->defaultBranch();

        DB::transaction(function () use ($order, $validated, $branch) {
            $allReceived = true;
            $anyReceived = false;

            foreach ($validated['items'] as $lineInput) {
                $item = PurchaseOrderItem::find($lineInput['id']);
                if (!$item || $item->purchase_order_id !== $order->id) continue;

                $qtyReceived = min((int) $lineInput['quantity_received'], $item->quantity_ordered);
                $item->update(['quantity_received' => $qtyReceived]);

                if ($qtyReceived < $item->quantity_ordered) $allReceived = false;
                if ($qtyReceived > 0) $anyReceived = true;

                if ($qtyReceived > 0 && $branch) {
                    // Increment stock
                    $stock = StockLevel::firstOrCreate(
                        ['product_id' => $item->product_id, 'branch_id' => $branch->id, 'variant_id' => $item->variant_id],
                        ['tenant_id' => $order->tenant_id, 'quantity' => 0]
                    );
                    $before = $stock->quantity;
                    $stock->increment('quantity', $qtyReceived);

                    // Log the stock adjustment
                    StockAdjustment::create([
                        'tenant_id'       => $order->tenant_id,
                        'branch_id'       => $branch->id,
                        'product_id'      => $item->product_id,
                        'variant_id'      => $item->variant_id,
                        'user_id'         => auth()->id(),
                        'quantity_before' => $before,
                        'quantity_change' => $qtyReceived,
                        'quantity_after'  => $before + $qtyReceived,
                        'reason'          => 'purchase',
                        'notes'           => "PO {$order->po_number}",
                    ]);

                    // Update product cost price to latest received cost
                    Product::where('id', $item->product_id)
                        ->update(['cost_price' => $item->unit_cost]);
                }
            }

            $order->update([
                'status'        => $allReceived ? 'received' : ($anyReceived ? 'partial' : $order->status),
                'received_date' => $anyReceived ? now()->toDateString() : null,
            ]);
        });

        // Post accounting entry: Dr Inventory (1040), Cr AP (2010) or Cash/Bank
        try {
            AccountingService::postPurchaseReceived($order);
        } catch (\Throwable $e) {
            \Log::warning("AccountingService::postPurchaseReceived failed: " . $e->getMessage());
        }

        return back()->with('success', 'Stock received and inventory updated.');
    }

    // Record a payment against this PO
    public function pay(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank',
            'notes'          => 'nullable|string|max:300',
        ]);

        $amount = min((float) $validated['amount'], $order->amountDue());
        if ($amount <= 0) {
            return back()->with('error', 'No outstanding amount to pay.');
        }

        DB::transaction(function () use ($order, $amount) {
            $order->increment('paid_amount', $amount);
            $order->supplier()->update(['current_balance' => \DB::raw("GREATEST(0, current_balance - {$amount})")]);
        });

        try {
            AccountingService::postPurchasePayment($order, $amount, $validated['payment_method']);
        } catch (\Throwable $e) {
            \Log::warning("AccountingService::postPurchasePayment failed: " . $e->getMessage());
        }

        return back()->with('success', 'Payment of Rs ' . number_format($amount, 2) . ' recorded.');
    }

    public function cancel(PurchaseOrder $order): RedirectResponse
    {
        if ($order->status === 'received') {
            return back()->with('error', 'Cannot cancel a fully received order.');
        }
        $order->update(['status' => 'cancelled']);
        return back()->with('success', 'Purchase order cancelled.');
    }
}
