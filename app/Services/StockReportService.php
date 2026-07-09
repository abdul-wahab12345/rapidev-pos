<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\SupplierReturnItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Stock reporting.
 *
 *  - snapshot()      : all-products stock-on-hand (current), with a flag for
 *                      products manually adjusted within a date range.
 *  - productLedger() : a single product's "stock card" — every movement
 *                      (purchase / sale / adjustment / return) merged
 *                      chronologically with a running balance.
 *
 * NOTE: stock movements are NOT all in one table. Sales only decrement
 * stock_levels (no stock_adjustment row), so the ledger unions the source
 * tables directly. Running balance is anchored to the live stock_levels
 * quantity and worked backwards, so the card always reconciles to reality.
 */
class StockReportService
{
    /** Manual (human) adjustment reasons — excludes automatic 'purchase'/'return'. */
    private const MANUAL_REASONS = ['damage', 'theft', 'correction', 'other'];

    public static function snapshot(?string $categoryId, ?string $from, ?string $to, ?string $search, ?string $tileSize = null): Collection
    {
        // Parse tile_size filter e.g. "12x24" → width=12, height=24
        $tileSizeW = null;
        $tileSizeH = null;
        if ($tileSize && preg_match('/^(\d+)x(\d+)$/i', $tileSize, $m)) {
            $tileSizeW = (int) $m[1];
            $tileSizeH = (int) $m[2];
        }

        $products = Product::with('category:id,name,color')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")))
            ->when($tileSizeW !== null, fn ($q) => $q
                ->whereRaw('CAST(tile_width_in AS UNSIGNED) = ?', [$tileSizeW])
                ->whereRaw('CAST(tile_height_in AS UNSIGNED) = ?', [$tileSizeH]))
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'category_id', 'reorder_level',
                   'tiles_per_box', 'sq_m_per_box', 'material_type',
                   'tile_width_in', 'tile_height_in']);


        $ids = $products->pluck('id');

        // Current stock-on-hand per product (summed across variants + branches).
        $qtyByProduct = StockLevel::whereIn('product_id', $ids)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->pluck('qty', 'product_id');

        // Manual adjustments inside the date range, per product.
        $adjByProduct = StockAdjustment::whereIn('product_id', $ids)
            ->whereIn('reason', self::MANUAL_REASONS)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) as cnt, SUM(quantity_change) as net')
            ->get()
            ->keyBy('product_id');

        return $products->map(function (Product $p) use ($qtyByProduct, $adjByProduct) {
            $adj = $adjByProduct->get($p->id);

            return [
                'id'                 => $p->id,
                'name'               => $p->name,
                'sku'                => $p->sku,
                'unit'               => $p->unit,
                'category'           => $p->category?->name,
                'quantity'           => (float) ($qtyByProduct[$p->id] ?? 0),
                'reorder_level'      => (float) $p->reorder_level,
                'tiles_per_box'      => $p->tiles_per_box,
                'sq_m_per_box'       => $p->sq_m_per_box ? (float) $p->sq_m_per_box : null,
                'material_type'      => $p->material_type,
                'tile_width_in'      => $p->tile_width_in ? (float) $p->tile_width_in : null,
                'tile_height_in'     => $p->tile_height_in ? (float) $p->tile_height_in : null,
                'manual_adjustments' => $adj ? (int) $adj->cnt : 0,
                'manual_net'         => $adj ? (float) $adj->net : 0.0,
                'flagged'            => (bool) $adj,
            ];
        })->values();
    }

    public static function productLedger(Product $product, ?string $from, ?string $to): array
    {
        $pid        = $product->id;
        $currentQty = (float) StockLevel::where('product_id', $pid)->sum('quantity');

        $moves = collect();

        // ── Sales (OUT) — exclude voided sales ──
        SaleItem::where('sale_items.product_id', $pid)
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', '!=', 'voided')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->get([
                'sales.id as ref_id',
                'sales.created_at as d',
                'sales.invoice_number as ref',
                'customers.name as party',
                'sale_items.quantity as qty',
                'sale_items.variant_label as vl',
            ])
            ->each(fn ($r) => $moves->push([
                'date' => Carbon::parse($r->d)->format('Y-m-d'), 'sort' => (string) $r->d,
                'type' => 'sale', 'reference' => $r->ref, 'ref_id' => $r->ref_id, 'party' => $r->party ?: 'Walk-in',
                'variant' => $r->vl, 'in' => 0.0, 'out' => (float) $r->qty, 'note' => null,
            ]));

        // ── Purchases (IN) — received PO lines ──
        PurchaseOrderItem::where('purchase_order_items.product_id', $pid)
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereIn('purchase_orders.status', ['received', 'partial'])
            ->where('purchase_order_items.quantity_received', '>', 0)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->get([
                'purchase_orders.id as ref_id',
                'purchase_orders.received_date as rd',
                'purchase_orders.order_date as od',
                'purchase_orders.po_number as ref',
                'suppliers.name as party',
                'purchase_order_items.quantity_received as qty',
                'purchase_order_items.variant_label as vl',
            ])
            ->each(function ($r) use ($moves) {
                $d = $r->rd ?: $r->od;
                $moves->push([
                    'date' => Carbon::parse($d)->format('Y-m-d'), 'sort' => (string) $d,
                    'type' => 'purchase', 'reference' => $r->ref, 'ref_id' => $r->ref_id, 'party' => $r->party ?: '—',
                    'variant' => $r->vl, 'in' => (float) $r->qty, 'out' => 0.0, 'note' => null,
                ]);
            });

        // ── Manual adjustments (IN or OUT) ──
        StockAdjustment::where('product_id', $pid)
            ->whereIn('reason', self::MANUAL_REASONS)
            ->with('user:id,name')
            ->get()
            ->each(function (StockAdjustment $a) use ($moves) {
                $chg = (float) $a->quantity_change;
                $moves->push([
                    'date' => $a->created_at->format('Y-m-d'), 'sort' => (string) $a->created_at,
                    'type' => 'adjustment', 'reference' => ucfirst($a->reason), 'ref_id' => null,
                    'party' => $a->user?->name ?? '—', 'variant' => null,
                    'in' => $chg > 0 ? $chg : 0.0, 'out' => $chg < 0 ? abs($chg) : 0.0,
                    'note' => $a->notes,
                ]);
            });

        // ── Customer returns (IN) — restocked only ──
        SaleReturnItem::where('sale_return_items.product_id', $pid)
            ->where('sale_return_items.restock', true)
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->leftJoin('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->get([
                'sale_returns.id as ref_id',
                'sale_returns.return_date as d',
                'sale_returns.return_number as ref',
                'customers.name as party',
                'sale_return_items.quantity_returned as qty',
                'sale_return_items.variant_label as vl',
            ])
            ->each(fn ($r) => $moves->push([
                'date' => Carbon::parse($r->d)->format('Y-m-d'), 'sort' => (string) $r->d,
                'type' => 'return_in', 'reference' => $r->ref, 'ref_id' => $r->ref_id, 'party' => $r->party ?: 'Walk-in',
                'variant' => $r->vl, 'in' => (float) $r->qty, 'out' => 0.0, 'note' => null,
            ]));

        // ── Supplier returns (OUT) — joined via PO item to resolve product ──
        SupplierReturnItem::join('purchase_order_items', 'purchase_order_items.id', '=', 'supplier_return_items.purchase_order_item_id')
            ->where('purchase_order_items.product_id', $pid)
            ->join('supplier_returns', 'supplier_returns.id', '=', 'supplier_return_items.supplier_return_id')
            ->get([
                'supplier_returns.created_at as d',
                'supplier_returns.return_number as ref',
                'supplier_return_items.quantity_returned as qty',
                'supplier_return_items.variant_label as vl',
            ])
            ->each(fn ($r) => $moves->push([
                'date' => Carbon::parse($r->d)->format('Y-m-d'), 'sort' => (string) $r->d,
                'type' => 'supplier_return', 'reference' => $r->ref, 'ref_id' => null, 'party' => '—',
                'variant' => $r->vl, 'in' => 0.0, 'out' => (float) $r->qty, 'note' => null,
            ]));

        $moves = $moves->sortBy('sort')->values();

        // Running balance anchored to the live quantity, worked backwards.
        $netOf  = fn (Collection $c) => (float) $c->sum(fn ($m) => $m['in'] - $m['out']);
        $afterTo = $to ? $moves->filter(fn ($m) => $m['date'] > $to) : collect();
        $closing = round($currentQty - $netOf($afterTo), 2);

        $inRange = $moves->filter(function ($m) use ($from, $to) {
            if ($from && $m['date'] < $from) return false;
            if ($to && $m['date'] > $to)     return false;
            return true;
        })->values();

        $opening = round($closing - $netOf($inRange), 2);

        $running = $opening;
        $rows = $inRange->map(function ($m) use (&$running) {
            $running = round($running + $m['in'] - $m['out'], 2);
            return array_merge($m, ['balance' => $running]);
        })->values();

        return [
            'opening'     => $opening,
            'closing'     => $closing,
            'current_qty' => $currentQty,
            'total_in'    => round((float) $inRange->sum('in'), 2),
            'total_out'   => round((float) $inRange->sum('out'), 2),
            'rows'        => $rows->all(),
        ];
    }
}
