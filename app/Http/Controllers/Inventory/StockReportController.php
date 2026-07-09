<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    /** All-products stock-on-hand snapshot + manual-adjustment flag in range. */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category'  => 'nullable|uuid|exists:categories,id',
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'search'    => 'nullable|string|max:100',
            'tile_size' => 'nullable|string|max:20',   // e.g. "24x48"
        ]);

        $from = $filters['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $filters['to']   ?? now()->format('Y-m-d');

        $rows = StockReportService::snapshot(
            $filters['category']  ?? null,
            $from, $to,
            $filters['search']    ?? null,
            $filters['tile_size'] ?? null,
        );

        // Unique tile sizes from tile-type products for the filter dropdown.
        // Format: "12x24" (width x height, both stored as inches).
        $tileSizes = Product::whereNotNull('tile_width_in')
            ->whereNotNull('tile_height_in')
            ->whereIn('material_type', ['tile', 'ceramic', 'mosaic', 'border'])
            ->selectRaw('CAST(tile_width_in AS UNSIGNED) as w, CAST(tile_height_in AS UNSIGNED) as h')
            ->groupBy('w', 'h')
            ->orderBy('w')->orderBy('h')
            ->get()
            ->map(fn ($r) => "{$r->w}x{$r->h}")
            ->values();

        return Inertia::render('Inventory/Reports/StockReport', [
            'rows'       => $rows,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'tile_sizes' => $tileSizes,
            'stats'      => [
                'total_products' => $rows->count(),
                'flagged'        => $rows->where('flagged', true)->count(),
            ],
            'filters'    => [
                'category'  => $filters['category']  ?? null,
                'from'      => $from,
                'to'        => $to,
                'search'    => $filters['search']    ?? null,
                'tile_size' => $filters['tile_size']  ?? null,
            ],
        ]);
    }

    /** Single-product stock card — full movement ledger with running balance. */
    public function show(Request $request, Product $product): Response
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $from = $filters['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $filters['to']   ?? now()->format('Y-m-d');

        $ledger = StockReportService::productLedger($product, $from, $to);

        return Inertia::render('Inventory/Reports/StockCard', [
            'product' => [
                'id'            => $product->id,
                'name'          => $product->name,
                'sku'           => $product->sku,
                'unit'          => $product->unit,
                'tiles_per_box' => $product->tiles_per_box,
                'sq_m_per_box'  => $product->sq_m_per_box ? (float) $product->sq_m_per_box : null,
                'material_type' => $product->material_type,
            ],
            'ledger'   => $ledger,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'filters'  => ['from' => $from, 'to' => $to],
        ]);
    }
}
