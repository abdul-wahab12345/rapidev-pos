<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\CustomerLedgerEntry;
use App\Models\Product;
use App\Models\RateList;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(): Response
    {
        $categories = Category::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        // Load initial products (first page, active only)
        $products = Product::with(['category', 'stockLevels', 'variants.stockLevels'])
            ->active()
            ->limit(48)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $this->formatProductForPos($p));

        // Load customers for udhaar selector
        $customers = Customer::orderBy('name')
            ->get(['id', 'name', 'phone', 'current_balance', 'credit_limit', 'discount_percent']);

        // Load rate lists for the selector
        $rateLists = RateList::orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'name_ur', 'is_active']);
        $activeRateList = $rateLists->firstWhere('is_active', true);

        // Pre-load active rate list prices keyed by "productId_variantId"
        $rateListPrices = [];
        if ($activeRateList) {
            $activeRateList->load('items');
            foreach ($activeRateList->items as $item) {
                $key = $item->product_id . '_' . ($item->variant_id ?? '');
                $rateListPrices[$key] = (float) $item->price;
            }
        }

        // Today's stats — exclude voided sales
        $todaySales   = Sale::where('status', '!=', 'voided')->whereDate('created_at', today())->count();
        $todayRevenue = Sale::where('status', '!=', 'voided')->whereDate('created_at', today())->sum('total');
        $lowStockCount = StockLevel::whereColumn('quantity', '<=', DB::raw(
            'COALESCE((SELECT reorder_level FROM products WHERE products.id = stock_levels.product_id LIMIT 1), 5)'
        ))->where('quantity', '>', 0)->count();

        return Inertia::render('Pos/Cashier', [
            'categories' => $categories,
            'initialProducts' => $products,
            'customers' => $customers->map(fn ($c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'phone'            => $c->phone,
                'balance'          => (float) $c->current_balance,
                'credit_limit'     => (float) $c->credit_limit,
                'discount_percent' => (float) $c->discount_percent,
            ]),
            'cashier' => [
                'id'   => auth()->id(),
                'name' => auth()->user()->name,
            ],
            'branch'  => auth()->user()->tenant?->defaultBranch()?->only('id', 'name'),
            'tenant'  => [
                'name'     => auth()->user()->tenant?->getBusinessName(),
                'settings' => auth()->user()->tenant?->settings,
            ],
            'stats' => [
                'sales_today'   => $todaySales,
                'revenue_today' => (float) $todayRevenue,
                'low_stock'     => $lowStockCount,
            ],
            'rateLists' => $rateLists->map(fn ($rl) => [
                'id'        => $rl->id,
                'name'      => $rl->name,
                'name_ur'   => $rl->name_ur,
                'is_active' => $rl->is_active,
            ]),
            'activeRateListId'     => $activeRateList?->id,
            'activeRateListPrices' => $rateListPrices,
            'diningTables' => DiningTable::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_active', true)
                ->orderBy('section')->orderBy('name')
                ->get(['id', 'name', 'capacity', 'section']),
        ]);
    }

    // AJAX: search products by name / barcode / SKU
    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $categoryId = $request->get('category', '');

        $products = Product::with(['category', 'stockLevels', 'variants.stockLevels'])
            ->active()
            ->when($query, fn ($q) => $q->search($query))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->limit(48)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $this->formatProductForPos($p));

        return response()->json($products);
    }

    // AJAX: validate stock before charging
    public function checkStock(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ])['items'];

        $branch = auth()->user()->tenant?->defaultBranch();
        $errors = [];

        foreach ($items as $item) {
            $stock = StockLevel::where('product_id', $item['product_id'])
                ->where('branch_id', $branch?->id)
                ->when($item['variant_id'] ?? null, fn ($q) => $q->where('variant_id', $item['variant_id']))
                ->first();

            $available = $stock?->quantity ?? 0;
            if ($available < $item['quantity']) {
                $product = Product::find($item['product_id']);
                $errors[] = [
                    'product_id' => $item['product_id'],
                    'requested' => $item['quantity'],
                    'available' => $available,
                    'name' => $product?->name,
                ];
            }
        }

        return response()->json([
            'ok' => empty($errors),
            'errors' => $errors,
        ]);
    }

    // POST: complete the sale — wrapped in DB transaction
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cost_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.name' => 'required|string',
            'items.*.variant_label' => 'nullable|string',
            'payment.method' => 'required|in:cash,jazzcash,easypaisa,udhaar,mixed',
            'payment.cash' => 'nullable|numeric|min:0',
            'payment.jazzcash' => 'nullable|numeric|min:0',
            'payment.easypaisa' => 'nullable|numeric|min:0',
            'payment.udhaar' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'rate_list_id' => 'nullable|exists:rate_lists,id',
            'dining_table_id' => 'nullable|exists:dining_tables,id',
            'order_type'      => 'nullable|in:dine_in,takeaway,delivery',
            'delivery_fee'    => 'nullable|numeric|min:0',
        ]);

        $user = auth()->user();
        $tenant = $user->tenant;
        $branch = $tenant?->defaultBranch();

        if (!$branch) {
            return response()->json(['error' => 'No branch configured'], 422);
        }

        try {
            $sale = DB::transaction(function () use ($validated, $user, $tenant, $branch) {
                // Calculate totals — values come in as decimal PKR, stored as decimal PKR
                $subtotal     = 0.0;
                $cartDiscount = round((float) ($validated['discount'] ?? 0), 2);
                $deliveryFee  = round((float) ($validated['delivery_fee'] ?? 0), 2);

                foreach ($validated['items'] as $item) {
                    $itemDiscount = round((float) ($item['discount'] ?? 0), 2);
                    $unitPrice    = round((float) $item['unit_price'], 2);
                    $lineTotal    = ($unitPrice * $item['quantity']) - $itemDiscount;
                    $subtotal    += $lineTotal;
                }

                $total = round($subtotal - $cartDiscount + $deliveryFee, 2);

                // Payment breakdown
                $cash      = round((float) ($validated['payment']['cash'] ?? 0), 2);
                $jazzcash  = round((float) ($validated['payment']['jazzcash'] ?? 0), 2);
                $easypaisa = round((float) ($validated['payment']['easypaisa'] ?? 0), 2);
                $udhaar    = round((float) ($validated['payment']['udhaar'] ?? 0), 2);
                $paid      = $cash + $jazzcash + $easypaisa + $udhaar;
                $change    = max(0, $cash - ($total - $jazzcash - $easypaisa - $udhaar));

                // Create sale record
                $sale = Sale::create([
                    'tenant_id'       => $tenant->id,
                    'branch_id'       => $branch->id,
                    'user_id'         => $user->id,
                    'customer_id'     => $validated['customer_id'] ?? null,
                    'invoice_number'  => Sale::generateInvoiceNumber($tenant->id),
                    'status'          => 'completed',
                    'subtotal'        => $subtotal,
                    'discount'        => $cartDiscount,
                    'tax'             => 0,
                    'total'           => $total,
                    'paid'            => $paid,
                    'change_amount'   => $change,
                    'cash_amount'     => $cash,
                    'jazzcash_amount' => $jazzcash,
                    'easypaisa_amount'=> $easypaisa,
                    'udhaar_amount'   => $udhaar,
                    'payment_method'  => $validated['payment']['method'],
                    'notes'           => $validated['notes'] ?? null,
                    'rate_list_id'    => $validated['rate_list_id'] ?? null,
                    'dining_table_id' => $validated['dining_table_id'] ?? null,
                    'order_type'      => $validated['order_type'] ?? 'takeaway',
                    'delivery_fee'    => $deliveryFee,
                ]);

                // Create sale items + decrement stock
                foreach ($validated['items'] as $item) {
                    $itemDiscount = round((float) ($item['discount'] ?? 0), 2);
                    $unitPrice    = round((float) $item['unit_price'], 2);
                    $costPrice    = round((float) $item['cost_price'], 2);
                    $lineTotal    = ($unitPrice * $item['quantity']) - $itemDiscount;

                    SaleItem::create([
                        'sale_id'       => $sale->id,
                        'product_id'    => $item['product_id'],
                        'variant_id'    => $item['variant_id'] ?? null,
                        'product_name'  => $item['name'],
                        'variant_label' => $item['variant_label'] ?? null,
                        'quantity'      => $item['quantity'],
                        'unit_price'    => $unitPrice,
                        'cost_price'    => $costPrice,
                        'discount'      => $itemDiscount,
                        'line_total'    => $lineTotal,
                    ]);

                    StockLevel::where('product_id', $item['product_id'])
                        ->where('branch_id', $branch->id)
                        ->when($item['variant_id'] ?? null, fn ($q) => $q->where('variant_id', $item['variant_id']))
                        ->lockForUpdate()
                        ->decrement('quantity', $item['quantity']);
                }

                // Update customer balance + always record a ledger entry for any linked customer
                if ($validated['customer_id']) {
                    $customer   = Customer::find($validated['customer_id']);
                    $newBalance = (float) $customer->current_balance + $udhaar; // only udhaar changes balance

                    if ($udhaar > 0) {
                        $customer->increment('current_balance', $udhaar);
                    }
                    $customer->increment('total_spend', $total);

                    CustomerLedgerEntry::create([
                        'tenant_id'       => $tenant->id,
                        'customer_id'     => $customer->id,
                        'sale_id'         => $sale->id,
                        'type'            => 'sale',
                        'amount'          => $total,           // full sale amount for history
                        'running_balance' => $newBalance,      // balance only moves on udhaar
                        'description'     => "Sale #{$sale->invoice_number} ({$sale->payment_method})",
                    ]);
                }

                return $sale;
            });

            // Auto-post to accounts ledger (outside the main transaction so it never blocks the sale)
            try {
                AccountingService::postSale($sale);
            } catch (\Throwable $e) {
                // Log but don't fail the sale
                \Log::warning("AccountingService::postSale failed for sale {$sale->id}: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'sale' => [
                    'id'             => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'total'          => (float) $sale->total,
                    'paid'           => (float) $sale->paid,
                    'change'         => (float) $sale->change_amount,
                    'payment_method' => $sale->payment_method,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Sale failed: ' . $e->getMessage()], 500);
        }
    }

    // AJAX: get rate list prices (called when user switches rate list in POS)
    public function rateListPrices(string $rateListId): JsonResponse
    {
        $rateList = RateList::with('items')->findOrFail($rateListId);

        $prices = [];
        foreach ($rateList->items as $item) {
            $key = $item->product_id . '_' . ($item->variant_id ?? '');
            $prices[$key] = (float) $item->price;
        }

        return response()->json($prices);
    }

    // AJAX: live stats for the bottom bar (called after each sale)
    public function stats(): JsonResponse
    {
        // Exclude voided sales from all counts
        $todaySales   = Sale::where('status', '!=', 'voided')->whereDate('created_at', today())->count();
        $todayRevenue = Sale::where('status', '!=', 'voided')->whereDate('created_at', today())->sum('total');
        $lowStock     = StockLevel::whereColumn('quantity', '<=', DB::raw(
            'COALESCE((SELECT reorder_level FROM products WHERE products.id = stock_levels.product_id LIMIT 1), 5)'
        ))->where('quantity', '>', 0)->count();

        return response()->json([
            'sales_today'   => $todaySales,
            'revenue_today' => (float) $todayRevenue,
            'low_stock'     => $lowStock,
        ]);
    }

    // AJAX: quick customer search for the customer selector
    public function searchCustomers(Request $request): JsonResponse
    {
        $customers = Customer::search($request->get('q', ''))
            ->limit(8)
            ->get(['id', 'name', 'phone', 'current_balance', 'credit_limit', 'discount_percent'])
            ->map(fn ($c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'phone'            => $c->phone,
                'balance'          => (float) $c->current_balance,
                'credit_limit'     => (float) $c->credit_limit,
                'discount_percent' => (float) $c->discount_percent,
            ]);

        return response()->json($customers);
    }

    // AJAX: quick create customer from POS screen
    public function storeCustomer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $customer = Customer::create([
            'name'            => $validated['name'],
            'phone'           => $validated['phone'] ?? null,
            'address'         => $validated['address'] ?? null,
            'current_balance' => 0,
            'credit_limit'    => 0,
            'total_spend'     => 0,
        ]);

        return response()->json([
            'id'           => $customer->id,
            'name'         => $customer->name,
            'phone'        => $customer->phone,
            'balance'      => 0,
            'credit_limit' => 0,
        ], 201);
    }

    private function formatProductForPos(Product $product): array
    {
        // For variant products, stock = sum across all variants
        $stock = $product->has_variants
            ? $product->variants->flatMap->stockLevels->sum('quantity')
            : $product->stockLevels->sum('quantity');

        return [
            'id'            => $product->id,
            'name'          => $product->name,
            'name_ur'       => $product->name_ur,
            'sku'           => $product->sku,
            'barcode'       => $product->barcode,
            'unit'          => $product->unit,
            'selling_price' => (float) $product->selling_price,
            'cost_price'    => (float) $product->cost_price,
            'has_variants'  => $product->has_variants,
            'stock'         => $stock,
            'category_id'   => $product->category_id,
            'category'      => $product->category ? [
                'id'    => $product->category->id,
                'name'  => $product->category->name,
                'color' => $product->category->color,
            ] : null,
            'variants' => $product->has_variants
                ? $product->variants->where('is_active', true)->map(fn ($v) => [
                    'id'            => $v->id,
                    'label'         => $v->label,
                    'size'          => $v->size,
                    'color'         => $v->color,
                    'selling_price' => (float) $v->selling_price,
                    'cost_price'    => (float) $v->cost_price,
                    'stock'         => $v->stockLevels->sum('quantity'),
                ])->values()
                : [],
        ];
    }
}
