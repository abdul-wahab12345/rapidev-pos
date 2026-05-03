<?php

namespace App\Support\Reports;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class OperationalReportQueries
{
    /**
     * @return array<string, float> keyed by supplier_id
     */
    public static function purchaseOrderDueBySupplierIds(Collection $supplierIds): array
    {
        if ($supplierIds->isEmpty()) {
            return [];
        }

        $rows = PurchaseOrder::query()
            ->whereIn('supplier_id', $supplierIds->all())
            ->whereIn('status', ['ordered', 'partial', 'received'])
            ->get(['supplier_id', 'total', 'paid_amount']);

        $out = [];
        foreach ($rows as $po) {
            $due = max(0, (float) $po->total - (float) $po->paid_amount);
            $out[$po->supplier_id] = ($out[$po->supplier_id] ?? 0) + $due;
        }

        return $out;
    }

    /** First udhaar date for aging (mirror Accounts receivable logic). */
    public static function oldestUdhaarSaleAt(string $customerId): ?Carbon
    {
        $at = Sale::query()
            ->where('customer_id', $customerId)
            ->where('udhaar_amount', '>', 0)
            ->whereIn('status', ['completed', 'partially_returned'])
            ->orderBy('created_at')
            ->value('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function udhaarCustomerRows(string $tenantId, ?int $cityId = null, ?int $areaId = null): Collection
    {
        $q = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('current_balance', '>', 0)
            ->with(['party.supplier']);

        if ($cityId !== null && $cityId !== 0) {
            $q->where('city_id', $cityId);
        }
        if ($areaId !== null && $areaId !== 0) {
            $q->where('area_id', $areaId);
        }

        $customers = $q->orderByDesc('current_balance')->get();

        $supplierIds = $customers
            ->map(fn (Customer $c) => $c->party?->supplier?->id)
            ->filter()
            ->unique()
            ->values();

        /** Pre-load PO dues for suppliers linked from customers — avoids repeated queries when many customers share vendors. */
        $poDue = self::purchaseOrderDueBySupplierIds($supplierIds);

        return $customers->map(function (Customer $c) use ($poDue) {
            $gross = (float) $c->current_balance;
            $supplier = $c->party?->supplier;
            $vendorLedger = $supplier ? (float) $supplier->current_balance : 0.0;
            $openPo = $supplier ? (float) ($poDue[$supplier->id] ?? 0.0) : 0.0;
            /** Net collectible when the same counterparty is also a vendor — AR minus AP ledger. */
            $netLedger = round($gross - $vendorLedger, 2);
            /** Alternate view: udhaar vs outstanding PO dues on that supplier. */
            $netVsPo = round($gross - $openPo, 2);

            $oldest = self::oldestUdhaarSaleAt($c->id);
            $ageDays = $oldest !== null ? (int) max(0, now()->startOfDay()->diffInDays($oldest->copy()->startOfDay())) : null;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'gross_udhaar' => $gross,
                'vendor_payable_ledger' => $vendorLedger,
                'open_po_due' => $openPo,
                'linked_supplier_name' => $supplier?->name,
                'net_after_vendor_ledger' => $netLedger,
                'net_after_open_po_due' => $netVsPo,
                'oldest_sale_date' => $oldest?->format('Y-m-d'),
                'age_days' => $ageDays,
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function payableVendorRows(string $tenantId, ?int $cityId = null, ?int $areaId = null): Collection
    {
        $q = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->where('current_balance', '>', 0)
            ->with('party.customer');

        if ($cityId !== null && $cityId !== 0) {
            $q->where('city_id', $cityId);
        }
        if ($areaId !== null && $areaId !== 0) {
            $q->where('area_id', $areaId);
        }

        $suppliers = $q->orderByDesc('current_balance')->get();

        $supplierIdList = collect($suppliers)->pluck('id')->values();

        $poDue = self::purchaseOrderDueBySupplierIds($supplierIdList);

        return $suppliers->map(function ($s) use ($poDue) {
            $oldestPo = PurchaseOrder::query()
                ->where('supplier_id', $s->id)
                ->whereIn('status', ['ordered', 'partial', 'received'])
                ->whereRaw('paid_amount < total')
                ->orderBy('order_date')
                ->value('order_date');

            $ageDays = $oldestPo !== null ? (int) max(0, now()->startOfDay()->diffInDays(Carbon::parse($oldestPo)->startOfDay())) : null;

            $ap = (float) $s->current_balance;
            $ar = (float) ($s->party?->customer?->current_balance ?? 0);
            $openPo = (float) ($poDue[$s->id] ?? 0);

            return [
                'id' => $s->id,
                'name' => $s->name,
                'company' => $s->company,
                'phone' => $s->phone,
                'vendor_ledger' => $ap,
                'open_po_due' => $openPo,
                'ar_balance' => $ar,
                'net_payable' => round(max(0, $ap - $ar), 2),
                'oldest_po_date' => $oldestPo !== null ? Carbon::parse($oldestPo)->format('Y-m-d') : null,
                'age_days' => $ageDays,
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function customersByLocationAggregate(string $tenantId, ?int $cityId = null, ?int $areaId = null): Collection
    {
        $q = Customer::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'city:id,name,province',
                'locality:id,name',
            ]);

        if ($cityId !== null && $cityId !== 0) {
            $q->where('city_id', $cityId);
        }
        if ($areaId !== null && $areaId !== 0) {
            $q->where('area_id', $areaId);
        }

        $customers = $q->get();

        return $customers
            ->groupBy(fn (Customer $c) => ($c->city_id ?? '').'|'.($c->area_id ?? ''))
            ->values()
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'city_name' => $first->city?->name ?? '—',
                    'province' => $first->city?->province ?? '—',
                    'area_name' => $first->locality?->name ?? '—',
                    'customer_count' => $group->count(),
                    'with_udhaar' => $group->where('current_balance', '>', 0)->count(),
                    'total_udhaar' => round((float) $group->sum('current_balance'), 2),
                    'total_spend' => round((float) $group->sum('total_spend'), 2),
                ];
            })
            ->sortByDesc('customer_count')
            ->values();
    }

    /**
     * @return array{count: int, gross_sales: float, net_sales: float, udhaar_in_period: float, refund_total: float, by_payment: array<string, array{count: int, total: float}>}
     */
    public static function salesSummary(string $tenantId, string $from, string $to, ?int $cityId = null, ?int $areaId = null): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $salesQuery = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'voided')
            ->whereBetween('created_at', [$start, $end]);

        if (($cityId !== null && $cityId !== 0) || ($areaId !== null && $areaId !== 0)) {
            $salesQuery->whereHas('customer', function ($cq) use ($cityId, $areaId): void {
                if ($cityId !== null && $cityId !== 0) {
                    $cq->where('city_id', $cityId);
                }
                if ($areaId !== null && $areaId !== 0) {
                    $cq->where('area_id', $areaId);
                }
            });
        }

        $grossSales = (float) (clone $salesQuery)->sum('total');
        $udhaarPeriod = (float) (clone $salesQuery)->sum('udhaar_amount');
        $count = (clone $salesQuery)->count();

        $refundTotalQuery = SaleReturn::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('return_date', [$start->toDateString(), $end->toDateString()]);

        if (($cityId !== null && $cityId !== 0) || ($areaId !== null && $areaId !== 0)) {
            $refundTotalQuery->whereHas('sale.customer', function ($cq) use ($cityId, $areaId): void {
                if ($cityId !== null && $cityId !== 0) {
                    $cq->where('city_id', $cityId);
                }
                if ($areaId !== null && $areaId !== 0) {
                    $cq->where('area_id', $areaId);
                }
            });
        }

        $refundTotal = (float) $refundTotalQuery->sum('total_refund');

        $byPayment = (clone $salesQuery)
            ->get(['payment_method', 'total'])
            ->groupBy('payment_method')
            ->map(fn (Collection $g) => ['count' => $g->count(), 'total' => round((float) $g->sum('total'), 2)])
            ->all();

        return [
            'count' => $count,
            'gross_sales' => round($grossSales, 2),
            'net_sales' => round($grossSales - $refundTotal, 2),
            'udhaar_in_period' => round($udhaarPeriod, 2),
            'refund_total' => round($refundTotal, 2),
            'by_payment' => $byPayment,
        ];
    }
}
