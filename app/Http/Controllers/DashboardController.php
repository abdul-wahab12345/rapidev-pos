<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockLevel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $tenant = Auth::user()->tenant;
        $tenantId = $tenant->id;
        $branch = $tenant->defaultBranch();

        $netRevenue = static function (Carbon $fromInclusive, Carbon $toInclusive) use ($tenantId): float {
            $gross = (float) Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'voided')
                ->whereBetween('created_at', [$fromInclusive->copy()->startOfDay(), $toInclusive->copy()->endOfDay()])
                ->sum('total');

            $refunds = (float) SaleReturn::query()
                ->where('tenant_id', $tenantId)
                ->whereBetween('return_date', [$fromInclusive->toDateString(), $toInclusive->toDateString()])
                ->sum('total_refund');

            return $gross - $refunds;
        };

        $today = today();
        $yesterday = $today->copy()->subDay();
        $startWeek = today()->copy()->startOfWeek();
        $startMonth = today()->copy()->startOfMonth();

        $salesTodayCount = Sale::where('tenant_id', $tenantId)
            ->where('status', '!=', 'voided')
            ->whereDate('created_at', $today)
            ->count();

        $purchaseDue = PurchaseOrder::openAmountDueAggregate($tenantId);

        $pendingPoCount = PurchaseOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'ordered', 'partial'])
            ->count();

        $lowStockCount = StockLevel::query()
            ->where('stock_levels.tenant_id', $tenantId)
            ->when($branch, fn ($q) => $q->where('stock_levels.branch_id', $branch->id))
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->where('products.is_active', true)
            ->whereColumn('stock_levels.quantity', '<=', 'products.reorder_level')
            ->where('stock_levels.quantity', '>', 0)
            ->count();

        $outOfStockCount = StockLevel::query()
            ->where('stock_levels.tenant_id', $tenantId)
            ->when($branch, fn ($q) => $q->where('stock_levels.branch_id', $branch->id))
            ->where('stock_levels.quantity', '<=', 0)
            ->count();

        $lowStockPeek = DB::table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->where('stock_levels.tenant_id', $tenantId)
            ->when($branch, fn ($q) => $q->where('stock_levels.branch_id', $branch->id))
            ->where('products.is_active', true)
            ->whereColumn('stock_levels.quantity', '<=', 'products.reorder_level')
            ->where('stock_levels.quantity', '>', 0)
            ->orderBy('stock_levels.quantity')
            ->limit(8)
            ->get(['products.id as product_id', 'products.name', 'stock_levels.quantity', 'products.reorder_level']);

        $udhaarOutstanding = (float) Customer::where('tenant_id', $tenantId)
            ->where('current_balance', '>', 0)
            ->sum('current_balance');

        $customersWithUdhaar = Customer::where('tenant_id', $tenantId)
            ->where('current_balance', '>', 0)
            ->count();

        $expensesThisMonth = (float) Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startMonth->toDateString(), $today->toDateString()])
            ->sum('amount');

        $returnsThisMonthRefund = (float) SaleReturn::where('tenant_id', $tenantId)
            ->whereBetween('return_date', [$startMonth->toDateString(), $today->toDateString()])
            ->sum('total_refund');

        $recentSales = Sale::with(['customer:id,name', 'branch:id,name'])
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'voided')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'invoice_number' => $s->invoice_number,
                'total' => (float) $s->total,
                'payment_method' => $s->payment_method,
                'customer' => $s->customer ? ['id' => $s->customer->id, 'name' => $s->customer->name] : null,
                'branch' => $s->branch ? ['name' => $s->branch->name] : null,
                'created_at' => $s->created_at,
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'revenue_today' => $netRevenue($today, $today),
                'revenue_yesterday' => $netRevenue($yesterday, $yesterday),
                'revenue_week' => $netRevenue($startWeek, $today),
                'revenue_month' => $netRevenue($startMonth, $today),
                'sales_today' => $salesTodayCount,
                'udhaar_outstanding' => $udhaarOutstanding,
                'customers_udhaar' => $customersWithUdhaar,
                'purchase_payable_due' => max(0, $purchaseDue),
                'purchase_orders_pending' => $pendingPoCount,
                'stock_low_count' => $lowStockCount,
                'stock_out_count' => $outOfStockCount,
                'expenses_month' => $expensesThisMonth,
                'returns_refund_month' => $returnsThisMonthRefund,
            ],
            'low_stock_peek' => collect($lowStockPeek)->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'reorder_level' => (int) $row->reorder_level,
            ]),
            'recent_sales' => $recentSales,
            'business' => [
                'default_branch_name' => $branch?->name,
            ],
        ]);
    }
}
