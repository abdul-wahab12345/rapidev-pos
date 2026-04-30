<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        $branch = $tenant?->defaultBranch();

        // Stock levels joined with products
        $query = StockLevel::with(['product.category', 'variant'])
            ->where('stock_levels.tenant_id', $tenant->id)
            ->when($branch, fn ($q) => $q->where('stock_levels.branch_id', $branch->id))
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->where('products.is_active', true)
            ->select('stock_levels.*');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('products.name', 'ilike', "%{$s}%")
                  ->orWhere('products.sku', 'ilike', "%{$s}%")
                  ->orWhere('products.barcode', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('products.category_id', $request->category);
        }

        if ($request->stock === 'low') {
            $query->whereColumn('stock_levels.quantity', '<=', 'products.reorder_level')
                  ->where('stock_levels.quantity', '>', 0);
        } elseif ($request->stock === 'out') {
            $query->where('stock_levels.quantity', '<=', 0);
        }

        $query->orderBy('products.name');

        $stock = $query->paginate(30)->withQueryString();

        // Stats
        $stats = [
            'total_products'  => Product::where('is_active', true)->count(),
            'low_stock'       => StockLevel::where('stock_levels.tenant_id', $tenant->id)
                                    ->when($branch, fn ($q) => $q->where('stock_levels.branch_id', $branch->id))
                                    ->join('products', 'products.id', '=', 'stock_levels.product_id')
                                    ->whereColumn('stock_levels.quantity', '<=', 'products.reorder_level')
                                    ->where('stock_levels.quantity', '>', 0)
                                    ->count(),
            'out_of_stock'    => StockLevel::where('stock_levels.tenant_id', $tenant->id)
                                    ->when($branch, fn ($q) => $q->where('stock_levels.branch_id', $branch->id))
                                    ->where('stock_levels.quantity', '<=', 0)
                                    ->count(),
            'total_items'     => StockLevel::where('stock_levels.tenant_id', $tenant->id)
                                    ->when($branch, fn ($q) => $q->where('stock_levels.branch_id', $branch->id))
                                    ->sum('stock_levels.quantity'),
        ];

        $categories = Category::active()->orderBy('name')->get(['id', 'name', 'color']);

        // Recent adjustments (last 10)
        $recentAdjustments = StockAdjustment::with(['product:id,name', 'variant:id,size,color', 'user:id,name'])
            ->where('tenant_id', $tenant->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id'              => $a->id,
                'product_name'    => $a->product?->name,
                'variant_label'   => $a->variant ? trim("{$a->variant->size} {$a->variant->color}") : null,
                'quantity_before' => $a->quantity_before,
                'quantity_change' => $a->quantity_change,
                'quantity_after'  => $a->quantity_after,
                'reason'          => $a->reason,
                'notes'           => $a->notes,
                'user'            => $a->user?->name,
                'created_at'      => $a->created_at,
            ]);

        return Inertia::render('Inventory/Stock/Index', [
            'stock'             => [
                'data'         => collect($stock->items())->map(fn ($s) => [
                    'id'            => $s->id,
                    'product_id'    => $s->product_id,
                    'variant_id'    => $s->variant_id,
                    'quantity'      => $s->quantity,
                    'reorder_level' => $s->product?->reorder_level ?? 5,
                    'product'       => [
                        'id'       => $s->product?->id,
                        'name'     => $s->product?->name,
                        'sku'      => $s->product?->sku,
                        'unit'     => $s->product?->unit,
                        'category' => $s->product?->category ? [
                            'name'  => $s->product->category->name,
                            'color' => $s->product->category->color,
                        ] : null,
                    ],
                    'variant'       => $s->variant ? [
                        'id'    => $s->variant->id,
                        'label' => trim("{$s->variant->size} {$s->variant->color}"),
                    ] : null,
                ]),
                'current_page' => $stock->currentPage(),
                'last_page'    => $stock->lastPage(),
                'total'        => $stock->total(),
                'links'        => $stock->linkCollection()->toArray(),
            ],
            'stats'             => $stats,
            'categories'        => $categories,
            'recent_adjustments'=> $recentAdjustments,
            'filters'           => $request->only(['search', 'category', 'stock']),
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'type'       => 'required|in:add,remove,set',
            'quantity'   => 'required|integer|min:1',
            'reason'     => 'required|in:purchase,damage,theft,correction,return,other',
            'notes'      => 'nullable|string|max:500',
        ]);

        $tenant = auth()->user()->tenant;
        $branch = $tenant?->defaultBranch();

        if (!$branch) {
            return back()->with('error', 'No branch configured.');
        }

        DB::transaction(function () use ($validated, $tenant, $branch) {
            $stockLevel = StockLevel::where('product_id', $validated['product_id'])
                ->where('branch_id', $branch->id)
                ->when($validated['variant_id'] ?? null, fn ($q) => $q->where('variant_id', $validated['variant_id']))
                ->lockForUpdate()
                ->first();

            if (!$stockLevel) {
                $stockLevel = StockLevel::create([
                    'tenant_id'  => $tenant->id,
                    'branch_id'  => $branch->id,
                    'product_id' => $validated['product_id'],
                    'variant_id' => $validated['variant_id'] ?? null,
                    'quantity'   => 0,
                ]);
            }

            $before = $stockLevel->quantity;

            $change = match ($validated['type']) {
                'add'    => $validated['quantity'],
                'remove' => -$validated['quantity'],
                'set'    => $validated['quantity'] - $before,
            };

            $after = max(0, $before + $change);
            $stockLevel->update(['quantity' => $after]);

            StockAdjustment::create([
                'tenant_id'       => $tenant->id,
                'branch_id'       => $branch->id,
                'product_id'      => $validated['product_id'],
                'variant_id'      => $validated['variant_id'] ?? null,
                'user_id'         => auth()->id(),
                'quantity_before' => $before,
                'quantity_change' => $after - $before,
                'quantity_after'  => $after,
                'reason'          => $validated['reason'],
                'notes'           => $validated['notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Stock adjusted successfully.');
    }
}
