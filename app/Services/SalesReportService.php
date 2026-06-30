<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Collection;

/**
 * Sales reporting — PURE gross sales, attributed by sale date.
 * Returns are intentionally NOT netted here; they live in their own report
 * (by return date). summary() exposes the period's returns total only as an
 * FYI memo so the two never get tangled across periods.
 */
class SalesReportService
{
    /** Base query: a tenant's non-voided sales within [from, to] by sale date.
     *  Columns are table-qualified so the query stays valid when joined (byParty). */
    private static function base(string $from, string $to)
    {
        return Sale::where('sales.status', '!=', 'voided')
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to);
    }

    /** Headline figures + returns-in-period memo (memo is NOT subtracted from gross). */
    public static function summary(string $from, string $to): array
    {
        $q = self::base($from, $to);

        $gross    = (float) (clone $q)->sum('total');
        $count    = (clone $q)->count();
        $discount = (float) (clone $q)->sum('discount');
        $udhaar   = (float) (clone $q)->sum('udhaar_amount');

        // Returns processed in this period (by return date) — FYI only.
        $returns = (float) SaleReturn::whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->sum('total_refund');

        $byPayment = (clone $q)
            ->selectRaw('payment_method, COUNT(*) as cnt, SUM(total) as amount')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($r) => ['method' => $r->payment_method, 'count' => (int) $r->cnt, 'amount' => (float) $r->amount])
            ->values();

        return [
            'count'           => $count,
            'gross_sales'     => $gross,
            'total_discount'  => $discount,
            'total_udhaar'    => $udhaar,
            'returns_in_period' => $returns,   // memo line
            'by_payment'      => $byPayment,
        ];
    }

    /** Invoice-level transaction list. */
    public static function transactions(string $from, string $to, ?string $payment): Collection
    {
        return self::base($from, $to)
            ->when($payment, fn ($q) => $q->where('payment_method', $payment))
            ->with(['customer:id,name', 'cashier:id,name'])
            ->withCount('items')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Sale $s) => [
                'id'             => $s->id,
                'invoice_number' => $s->invoice_number,
                'date'           => $s->created_at->format('Y-m-d'),
                'customer'       => $s->customer?->name ?? 'Walk-in',
                'cashier'        => $s->cashier?->name ?? '—',
                'items'          => $s->items_count,
                'discount'       => (float) $s->discount,
                'total'          => (float) $s->total,
                'paid'           => (float) $s->paid,
                'udhaar'         => (float) $s->udhaar_amount,
                'payment_method' => $s->payment_method,
                'status'         => $s->status,
            ])
            ->values();
    }

    /** Sales aggregated by product (gross qty + revenue). Based on Sale so the
     *  tenant global scope applies (sale_items has no tenant_id of its own). */
    public static function byProduct(string $from, string $to): Collection
    {
        return self::base($from, $to)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('sale_items.product_name, sale_items.product_id,
                         products.unit, products.tiles_per_box, products.sq_m_per_box, products.material_type,
                         SUM(sale_items.quantity) as qty,
                         SUM(sale_items.line_total) as revenue,
                         COUNT(DISTINCT sale_items.sale_id) as invoices')
            ->groupBy('sale_items.product_id', 'sale_items.product_name',
                      'products.unit', 'products.tiles_per_box', 'products.sq_m_per_box', 'products.material_type')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'product_id'    => $r->product_id,
                'product_name'  => $r->product_name,
                'unit'          => $r->unit,
                'tiles_per_box' => $r->tiles_per_box !== null ? (int) $r->tiles_per_box : null,
                'sq_m_per_box'  => $r->sq_m_per_box !== null ? (float) $r->sq_m_per_box : null,
                'material_type' => $r->material_type,
                'qty'           => (float) $r->qty,
                'revenue'       => (float) $r->revenue,
                'invoices'      => (int) $r->invoices,
            ])
            ->values();
    }

    /** Daily totals. */
    public static function byDay(string $from, string $to): Collection
    {
        return self::base($from, $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt, SUM(total) as amount, SUM(udhaar_amount) as udhaar')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day'    => (string) $r->day,
                'count'  => (int) $r->cnt,
                'amount' => (float) $r->amount,
                'udhaar' => (float) $r->udhaar,
            ])
            ->values();
    }

    /** Top customers and per-cashier totals. */
    public static function byParty(string $from, string $to): array
    {
        $customers = self::base($from, $to)
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->selectRaw("COALESCE(customers.name, 'Walk-in') as name, COUNT(*) as cnt, SUM(sales.total) as amount, SUM(sales.udhaar_amount) as udhaar")
            ->groupBy('name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->cnt, 'amount' => (float) $r->amount, 'udhaar' => (float) $r->udhaar])
            ->values();

        $cashiers = self::base($from, $to)
            ->leftJoin('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw("COALESCE(users.name, '—') as name, COUNT(*) as cnt, SUM(sales.total) as amount")
            ->groupBy('name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->cnt, 'amount' => (float) $r->amount])
            ->values();

        return ['customers' => $customers, 'cashiers' => $cashiers];
    }

    /** Returns report — by return date, linked to the original invoice. */
    public static function returns(string $from, string $to, ?string $method): array
    {
        $rows = SaleReturn::query()
            ->whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->when($method, fn ($q) => $q->where('refund_method', $method))
            ->with(['sale:id,invoice_number,created_at,customer_id', 'sale.customer:id,name'])
            ->withCount('items')
            ->orderBy('return_date')
            ->get()
            ->map(function (SaleReturn $r) use ($from) {
                $saleDate = $r->sale?->created_at?->format('Y-m-d');
                return [
                    'id'              => $r->id,
                    'return_number'   => $r->return_number,
                    'return_date'     => $r->return_date->format('Y-m-d'),
                    'sale_id'         => $r->sale?->id,
                    'invoice_number'  => $r->sale?->invoice_number ?? '—',
                    'sale_date'       => $saleDate,
                    'customer'        => $r->sale?->customer?->name ?? 'Walk-in',
                    'refund_method'   => $r->refund_method,
                    'items'           => $r->items_count,
                    'total_refund'    => (float) $r->total_refund,
                    'reason'          => $r->reason,
                    // ⚑ the original sale is from before this report's window
                    'prior_period'    => $saleDate !== null && $saleDate < $from,
                ];
            })
            ->values();

        return [
            'rows'  => $rows,
            'total' => round($rows->sum('total_refund'), 2),
            'count' => $rows->count(),
        ];
    }
}
