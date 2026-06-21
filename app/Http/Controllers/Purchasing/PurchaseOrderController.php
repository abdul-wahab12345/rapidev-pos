<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderPayment;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
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
            ->withSum('supplierReturns', 'total_amount')
            ->where('tenant_id', $tenant->id);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('po_number', 'like', "%{$s}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderByDesc('order_date')->orderByDesc('po_number')->paginate(20)->withQueryString();

        $stats = [
            'total' => PurchaseOrder::where('tenant_id', $tenant->id)->count(),
            'pending' => PurchaseOrder::where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'ordered', 'partial'])->count(),
            'total_due' => PurchaseOrder::openAmountDueAggregate($tenant->id),
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
                'data' => collect($orders->items())->map(fn ($o) => [
                    'id' => $o->id,
                    'po_number' => $o->po_number,
                    'supplier' => ['name' => $o->supplier?->name],
                    'order_date' => $o->order_date?->format('Y-m-d'),
                    'expected_date' => $o->expected_date?->format('Y-m-d'),
                    'status' => $o->status,
                    'total' => (float) $o->total,
                    'paid_amount' => (float) $o->paid_amount,
                    'amount_due' => $o->amountDue(),
                ]),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
            'stats' => $stats,
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status']),
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
            ->get(['id', 'name', 'name_ur', 'sku', 'unit', 'cost_price', 'has_variants', 'tiles_per_box', 'sq_m_per_box', 'material_type'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'name_ur' => $p->name_ur,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'cost_price' => (float) $p->cost_price,
                'has_variants' => $p->has_variants,
                'tiles_per_box' => $p->tiles_per_box,
                'sq_m_per_box'  => $p->sq_m_per_box ? (float) $p->sq_m_per_box : null,
                'material_type' => $p->material_type,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'label' => trim("{$v->size} {$v->color}"),
                    'cost_price' => (float) $v->cost_price,
                ]),
            ]);

        return Inertia::render('Purchasing/Orders/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'today' => now()->format('Y-m-d'),
            'supplier_id' => $request->get('supplier'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'payment_method' => 'required|in:cash,bank,credit',
            'notes' => 'nullable|string|max:500',
            'mark_received' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.product_name' => 'required|string',
            'items.*.variant_label' => 'nullable|string',
        ]);

        $tenant = auth()->user()->tenant;
        $branch = $tenant->defaultBranch();
        $markReceived = (bool) ($validated['mark_received'] ?? false);

        if (! $branch) {
            return back()->with('error', 'No branch configured.');
        }

        $po = DB::transaction(function () use ($validated, $tenant, $branch, $markReceived) {
            $subtotal = collect($validated['items'])->sum(fn ($i) => $i['unit_cost'] * $i['quantity_ordered']);

            $po = PurchaseOrder::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'supplier_id' => $validated['supplier_id'],
                'created_by' => auth()->id(),
                'po_number' => PurchaseOrder::nextNumber($tenant->id),
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'status' => $markReceived ? 'received' : 'ordered',
                'received_date' => $markReceived ? now()->toDateString() : null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = (float) $item['quantity_ordered'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'variant_label' => $item['variant_label'] ?? null,
                    'quantity_ordered' => $qty,
                    'quantity_received' => $markReceived ? $qty : 0,
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $item['unit_cost'] * $qty,
                ]);

                // If marking as received, update stock immediately
                if ($markReceived && $branch) {
                    $stock = StockLevel::firstOrCreate(
                        [
                            'product_id' => $item['product_id'],
                            'branch_id' => $branch->id,
                            'variant_id' => $item['variant_id'] ?? null,
                        ],
                        ['tenant_id' => $tenant->id, 'quantity' => 0]
                    );
                    $before = $stock->quantity;
                    $stock->increment('quantity', $qty);

                    StockAdjustment::create([
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'] ?? null,
                        'user_id' => auth()->id(),
                        'quantity_before' => $before,
                        'quantity_change' => $qty,
                        'quantity_after' => $before + $qty,
                        'reason' => 'purchase',
                        'notes' => "PO {$po->po_number} (created received)",
                    ]);

                    Product::where('id', $item['product_id'])
                        ->update(['cost_price' => $item['unit_cost']]);
                }
            }

            if (
                $markReceived
                && in_array($validated['payment_method'], ['cash', 'bank'], true)
            ) {
                $paidTotal = (float) $po->total;
                if ($paidTotal > 0 && (float) $po->paid_amount < $paidTotal) {
                    $po->paid_amount = $paidTotal;
                    $po->save();
                    PurchaseOrderPayment::create([
                        'tenant_id' => $tenant->id,
                        'purchase_order_id' => $po->id,
                        'amount' => $paidTotal,
                        'payment_method' => $validated['payment_method'],
                        'notes' => 'Paid with goods receipt (ledger: goods received entry only).',
                        'is_voided' => false,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            return $po->fresh();
        });

        // Post accounting entry if received on creation
        if ($markReceived) {
            try {
                AccountingService::postPurchaseReceived($po->load('items'));
            } catch (\Throwable $e) {
                \Log::warning('AccountingService::postPurchaseReceived on create failed: '.$e->getMessage());
            }
        }

        $msg = $markReceived
            ? "Purchase Order {$po->po_number} created and stock received."
            : "Purchase Order {$po->po_number} created.";

        return redirect()->route('purchasing.orders.show', $po->id)->with('success', $msg);
    }

    public function show(PurchaseOrder $order): Response
    {
        $order->load(['supplier', 'items.product', 'items.variant', 'creator']);
        $order->loadSum('supplierReturns', 'total_amount');

        $payments = PurchaseOrderPayment::where('purchase_order_id', $order->id)
            ->orderByDesc('created_at')
            ->get();

        $returns = SupplierReturn::where('purchase_order_id', $order->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Purchasing/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'expected_date' => $order->expected_date?->format('Y-m-d'),
                'received_date' => $order->received_date?->format('Y-m-d'),
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'tax' => (float) $order->tax,
                'total' => (float) $order->total,
                'paid_amount' => (float) $order->paid_amount,
                'amount_due' => $order->amountDue(),
                'notes' => $order->notes,
                'created_by' => $order->creator?->name,
                'supplier' => [
                    'id' => $order->supplier?->id,
                    'name' => $order->supplier?->name,
                    'phone' => $order->supplier?->phone,
                    'city' => $order->supplier?->city,
                ],
                'items' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'product_name' => $i->product_name,
                    'variant_label' => $i->variant_label,
                    'quantity_ordered' => $i->quantity_ordered,
                    'quantity_received' => $i->quantity_received,
                    'unit_cost' => (float) $i->unit_cost,
                    'line_total' => (float) $i->line_total,
                    'unit'          => $i->product?->unit,
                    'tile_width_in'  => $i->product?->tile_width_in ? (float) $i->product->tile_width_in : null,
                    'tile_height_in' => $i->product?->tile_height_in ? (float) $i->product->tile_height_in : null,
                    'tiles_per_box' => $i->product?->tiles_per_box,
                    'sq_m_per_box'  => $i->product?->sq_m_per_box ? (float) $i->product->sq_m_per_box : null,
                    'material_type' => $i->product?->material_type,
                ]),
                'payments' => $payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'payment_method' => $p->payment_method,
                    'notes' => $p->notes,
                    'is_voided' => $p->is_voided,
                    'created_at' => $p->created_at,
                ]),
                'returns' => $returns->map(fn ($r) => [
                    'id' => $r->id,
                    'return_number' => $r->return_number,
                    'total_amount' => (float) $r->total_amount,
                    'reason' => $r->reason,
                    'notes' => $r->notes,
                    'created_at' => $r->created_at,
                    'items' => $r->items->map(fn ($i) => [
                        'product_name' => $i->product_name,
                        'variant_label' => $i->variant_label,
                        'quantity_returned' => $i->quantity_returned,
                        'unit_cost' => (float) $i->unit_cost,
                        'line_total' => (float) $i->line_total,
                    ]),
                ]),
            ],
        ]);
    }

    // Mark the entire PO as received — increments stock, posts AP journal entry
    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        if (in_array($order->status, ['received', 'cancelled'])) {
            return back()->with('error', 'This order is already '.$order->status.'.');
        }

        $validated = $request->validate([
            'items'                          => 'required|array',
            'items.*.id'                     => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received'      => 'required|numeric|min:0',
            'items.*.boxes_count'            => 'nullable|integer|min:0',
            'items.*.loose_tiles_count'      => 'nullable|integer|min:0',
        ]);

        $tenant = auth()->user()->tenant;
        $branch = $tenant->defaultBranch();

        DB::transaction(function () use ($order, $validated, $branch) {
            $allReceived = true;
            $anyReceived = false;

            foreach ($validated['items'] as $lineInput) {
                $item = PurchaseOrderItem::find($lineInput['id']);
                if (! $item || $item->purchase_order_id !== $order->id) {
                    continue;
                }

                $qtyReceived = min((float) $lineInput['quantity_received'], (float) $item->quantity_ordered);
                $item->update(['quantity_received' => $qtyReceived]);

                if ($qtyReceived < $item->quantity_ordered) {
                    $allReceived = false;
                }
                if ($qtyReceived > 0) {
                    $anyReceived = true;
                }

                if ($qtyReceived > 0 && $branch) {
                    $boxesCount      = isset($lineInput['boxes_count']) ? (int) $lineInput['boxes_count'] : null;
                    $looseTilesCount = isset($lineInput['loose_tiles_count']) ? (int) $lineInput['loose_tiles_count'] : null;
                    $hasBoxData      = $boxesCount !== null || $looseTilesCount !== null;

                    // Increment stock
                    $stock = StockLevel::firstOrCreate(
                        ['product_id' => $item->product_id, 'branch_id' => $branch->id, 'variant_id' => $item->variant_id],
                        ['tenant_id' => $order->tenant_id, 'quantity' => 0]
                    );
                    $before = $stock->quantity;
                    $stock->increment('quantity', $qtyReceived);

                    if ($hasBoxData) {
                        $stock->update([
                            'boxes_count'       => $boxesCount ?? 0,
                            'loose_tiles_count' => $looseTilesCount ?? 0,
                            'box_count_at'      => now(),
                        ]);
                    }

                    // Log the stock adjustment
                    StockAdjustment::create([
                        'tenant_id'         => $order->tenant_id,
                        'branch_id'         => $branch->id,
                        'product_id'        => $item->product_id,
                        'variant_id'        => $item->variant_id,
                        'user_id'           => auth()->id(),
                        'quantity_before'   => $before,
                        'quantity_change'   => $qtyReceived,
                        'quantity_after'    => $before + $qtyReceived,
                        'reason'            => 'purchase',
                        'notes'             => "PO {$order->po_number}",
                        'boxes_count'       => $boxesCount,
                        'loose_tiles_count' => $looseTilesCount,
                    ]);

                    // Update product cost price to latest received cost
                    Product::where('id', $item->product_id)
                        ->update(['cost_price' => $item->unit_cost]);
                }
            }

            $order->update([
                'status' => $allReceived ? 'received' : ($anyReceived ? 'partial' : $order->status),
                'received_date' => $anyReceived ? now()->toDateString() : null,
            ]);

            if (
                $allReceived
                && in_array($order->payment_method, ['cash', 'bank'], true)
            ) {
                $paidTotal = (float) $order->total;
                if ($paidTotal > 0 && (float) $order->paid_amount < $paidTotal) {
                    $order->paid_amount = $paidTotal;
                    $order->save();
                    PurchaseOrderPayment::create([
                        'tenant_id' => $order->tenant_id,
                        'purchase_order_id' => $order->id,
                        'amount' => $paidTotal,
                        'payment_method' => $order->payment_method,
                        'notes' => 'Paid with goods receipt (ledger: goods received entry only).',
                        'is_voided' => false,
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        });

        // Post accounting entry: Dr Inventory (1040), Cr AP (2010) or Cash/Bank
        try {
            AccountingService::postPurchaseReceived($order);
        } catch (\Throwable $e) {
            \Log::warning('AccountingService::postPurchaseReceived failed: '.$e->getMessage());
        }

        return back()->with('success', 'Stock received and inventory updated.');
    }

    // Record a payment against this PO
    public function pay(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank',
            'notes' => 'nullable|string|max:300',
        ]);

        $amount = min((float) $validated['amount'], $order->amountDue());
        if ($amount <= 0) {
            return back()->with('error', 'No outstanding amount to pay.');
        }

        $payment = DB::transaction(function () use ($order, $amount, $validated) {
            $order->increment('paid_amount', $amount);
            $order->supplier()->update(['current_balance' => \DB::raw("GREATEST(0, current_balance - {$amount})")]);

            return PurchaseOrderPayment::create([
                'tenant_id' => $order->tenant_id,
                'purchase_order_id' => $order->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
                'is_voided' => false,
                'created_by' => auth()->id(),
            ]);
        });

        try {
            AccountingService::postPurchasePaymentRecord($payment, $order);
        } catch (\Throwable $e) {
            \Log::warning('AccountingService::postPurchasePaymentRecord failed: '.$e->getMessage());
        }

        return back()->with('success', 'Payment of Rs '.number_format($amount, 2).' recorded.');
    }

    // Void a previously recorded PO payment
    public function voidPayment(PurchaseOrder $order, PurchaseOrderPayment $payment): RedirectResponse
    {
        if ($payment->purchase_order_id !== $order->id) {
            return back()->with('error', 'Payment does not belong to this order.');
        }
        if ($payment->is_voided) {
            return back()->with('error', 'Payment is already voided.');
        }

        $amount = (float) $payment->amount;

        DB::transaction(function () use ($order, $payment, $amount) {
            $payment->update(['is_voided' => true]);
            $order->decrement('paid_amount', $amount);
            // pay() lowers supplier AP balance; receipt-only cash/bank never raised it via pay()
            if ($order->payment_method === 'credit') {
                $order->supplier()->update(['current_balance' => \DB::raw("current_balance + {$amount}")]);
            }
        });

        try {
            AccountingService::reversePurchasePayment($payment->load('purchaseOrder'));
        } catch (\Throwable $e) {
            \Log::warning('AccountingService::reversePurchasePayment failed: '.$e->getMessage());
        }

        return back()->with(
            'success',
            $order->payment_method === 'credit'
                ? 'Payment voided and payable balance restored.'
                : 'Payment voided.'
        );
    }

    // Record a supplier return (debit note) against a received PO
    public function storeReturn(Request $request, PurchaseOrder $order): RedirectResponse
    {
        if (! in_array($order->status, ['received', 'partial'])) {
            return back()->with('error', 'Can only return items from received orders.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:300',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_returned' => 'required|numeric|min:0.01',
        ]);

        $tenant = auth()->user()->tenant;
        $branch = $tenant->defaultBranch();

        $supplierReturn = DB::transaction(function () use ($order, $validated, $branch) {
            $total = 0;

            $return = SupplierReturn::create([
                'tenant_id' => $order->tenant_id,
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'return_number' => SupplierReturn::nextNumber($order->tenant_id),
                'total_amount' => 0, // updated below
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $line) {
                $item = PurchaseOrderItem::find($line['purchase_order_item_id']);
                if (! $item || $item->purchase_order_id !== $order->id) {
                    continue;
                }

                $qty = (float) $line['quantity_returned'];
                $lineTotal = (float) $item->unit_cost * $qty;
                $total += $lineTotal;

                SupplierReturnItem::create([
                    'supplier_return_id' => $return->id,
                    'purchase_order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_label' => $item->variant_label,
                    'quantity_returned' => $qty,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $lineTotal,
                ]);

                // Reduce stock
                if ($branch) {
                    $stock = StockLevel::where('product_id', $item->product_id)
                        ->where('branch_id', $branch->id)
                        ->where('variant_id', $item->variant_id)
                        ->first();

                    if ($stock && $stock->quantity >= $qty) {
                        $before = $stock->quantity;
                        $stock->decrement('quantity', $qty);

                        StockAdjustment::create([
                            'tenant_id' => $order->tenant_id,
                            'branch_id' => $branch->id,
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                            'user_id' => auth()->id(),
                            'quantity_before' => $before,
                            'quantity_change' => -$qty,
                            'quantity_after' => $before - $qty,
                            'reason' => 'return',
                            'notes' => "Supplier return {$return->return_number}",
                        ]);
                    }
                }
            }

            $return->update(['total_amount' => $total]);

            // Reduce supplier AP balance
            $order->supplier()->update(['current_balance' => \DB::raw("GREATEST(0, current_balance - {$total})")]);

            return $return;
        });

        try {
            AccountingService::postSupplierReturn($supplierReturn->load('purchaseOrder'));
        } catch (\Throwable $e) {
            \Log::warning('AccountingService::postSupplierReturn failed: '.$e->getMessage());
        }

        return back()->with('success', "Supplier return {$supplierReturn->return_number} recorded.");
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
