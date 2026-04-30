<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Sale::with(['customer:id,name,phone', 'cashier:id,name', 'branch:id,name'])
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'ilike', "%{$s}%")
                  ->orWhereHas('customer', fn ($q2) => $q2->where('name', 'ilike', "%{$s}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('payment')) {
            $query->where('payment_method', $request->payment);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sales = $query->paginate(25)->withQueryString();

        // Summary stats — always exclude voided unless the user specifically filters for voided
        $filteringForVoided = $request->input('status') === 'voided';

        $statsQuery = Sale::when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('payment'),   fn ($q) => $q->where('payment_method', $request->payment))
            ->when($request->filled('status'),    fn ($q) => $q->where('status', $request->status))
            ->when(! $filteringForVoided,         fn ($q) => $q->where('status', '!=', 'voided'));

        $stats = [
            'total_sales'    => $statsQuery->count(),
            'total_revenue'  => (float) $statsQuery->sum('total'),
            'total_discount' => (float) $statsQuery->sum('discount'),
            'total_udhaar'   => (float) Sale::where('status', '!=', 'voided')
                                    ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
                                    ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
                                    ->sum('udhaar_amount'),
            'today_count'    => Sale::where('status', '!=', 'voided')->whereDate('created_at', today())->count(),
            'today_revenue'  => (float) Sale::where('status', '!=', 'voided')->whereDate('created_at', today())->sum('total'),
        ];

        return Inertia::render('Sales/Index', [
            'sales' => [
                'data' => collect($sales->items())->map(fn ($s) => [
                    'id'             => $s->id,
                    'invoice_number' => $s->invoice_number,
                    'status'         => $s->status,
                    'created_at'     => $s->created_at,
                    'total'          => (float) $s->total,
                    'discount'       => (float) $s->discount,
                    'udhaar_amount'  => (float) $s->udhaar_amount,
                    'payment_method' => $s->payment_method,
                    'customer'       => $s->customer ? ['id' => $s->customer->id, 'name' => $s->customer->name, 'phone' => $s->customer->phone] : null,
                    'cashier'        => $s->cashier ? ['id' => $s->cashier->id, 'name' => $s->cashier->name] : null,
                    'branch'         => $s->branch ? ['id' => $s->branch->id, 'name' => $s->branch->name] : null,
                ]),
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'total' => $sales->total(),
                'links' => $sales->linkCollection()->toArray(),
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'payment', 'status']),
        ]);
    }

    public function show(Sale $sale): Response
    {
        $sale->load([
            'items',
            'customer:id,name,phone,address',
            'cashier:id,name',
            'branch:id,name',
        ]);

        return Inertia::render('Sales/Show', [
            'sale' => [
                'id'             => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'status'         => $sale->status,
                'created_at'     => $sale->created_at,
                'subtotal'        => (float) $sale->subtotal,
                'discount'        => (float) $sale->discount,
                'tax'             => (float) $sale->tax,
                'total'           => (float) $sale->total,
                'paid'            => (float) $sale->paid,
                'change_amount'   => (float) $sale->change_amount,
                'cash_amount'     => (float) $sale->cash_amount,
                'jazzcash_amount' => (float) $sale->jazzcash_amount,
                'easypaisa_amount'=> (float) $sale->easypaisa_amount,
                'udhaar_amount'   => (float) $sale->udhaar_amount,
                'payment_method' => $sale->payment_method,
                'notes'          => $sale->notes,
                'customer'       => $sale->customer ? [
                    'id'    => $sale->customer->id,
                    'name'  => $sale->customer->name,
                    'phone' => $sale->customer->phone,
                ] : null,
                'cashier'  => ['id' => $sale->cashier?->id, 'name' => $sale->cashier?->name],
                'branch'   => ['id' => $sale->branch?->id,  'name' => $sale->branch?->name],
                'items'    => $sale->items->map(fn ($i) => [
                    'id'            => $i->id,
                    'product_name'  => $i->product_name,
                    'variant_label' => $i->variant_label,
                    'quantity'      => $i->quantity,
                    'unit_price'    => (float) $i->unit_price,
                    'cost_price'    => (float) $i->cost_price,
                    'discount'      => (float) $i->discount,
                    'line_total'    => (float) $i->line_total,
                ]),
            ],
        ]);
    }

    // AJAX: return full receipt data for inline printing from the list page
    public function receiptData(Sale $sale): JsonResponse
    {
        $sale->load(['items', 'customer:id,name,phone', 'cashier:id,name', 'branch:id,name']);

        $tenant   = auth()->user()->tenant;
        $settings = $tenant?->settings ?? [];

        return response()->json([
            'id'               => $sale->id,
            'invoice_number'   => $sale->invoice_number,
            'status'           => $sale->status,
            'created_at'       => $sale->created_at,
            'subtotal'         => (float) $sale->subtotal,
            'discount'         => (float) $sale->discount,
            'tax'              => (float) $sale->tax,
            'total'            => (float) $sale->total,
            'change_amount'    => (float) $sale->change_amount,
            'cash_amount'      => (float) $sale->cash_amount,
            'jazzcash_amount'  => (float) $sale->jazzcash_amount,
            'easypaisa_amount' => (float) $sale->easypaisa_amount,
            'udhaar_amount'    => (float) $sale->udhaar_amount,
            'payment_method'   => $sale->payment_method,
            'customer'         => $sale->customer ? ['name' => $sale->customer->name, 'phone' => $sale->customer->phone] : null,
            'cashier'          => ['name' => $sale->cashier?->name],
            'branch'           => ['name' => $sale->branch?->name],
            'items'            => $sale->items->map(fn ($i) => [
                'product_name'  => $i->product_name,
                'variant_label' => $i->variant_label,
                'quantity'      => $i->quantity,
                'unit_price'    => (float) $i->unit_price,
                'discount'      => (float) $i->discount,
                'line_total'    => (float) $i->line_total,
            ]),
            // tenant settings for receipt rendering
            'business_name'    => data_get($settings, 'business_name', $tenant?->name ?? ''),
            'business_phone'   => data_get($settings, 'business_phone'),
            'business_address' => data_get($settings, 'business_address'),
            'business_city'    => data_get($settings, 'business_city'),
            'logo_url'         => data_get($settings, 'logo_url'),
            'receipt_header'   => data_get($settings, 'receipt_header'),
            'receipt_footer'   => data_get($settings, 'receipt_footer', 'Thank you for your business!'),
            'currency_symbol'  => data_get($settings, 'currency_symbol', 'Rs'),
            'language'         => data_get($settings, 'language', 'en'),
        ]);
    }

    public function void(Sale $sale): RedirectResponse
    {
        if ($sale->status === 'voided') {
            return back()->with('error', 'Sale is already voided.');
        }

        DB::transaction(function () use ($sale) {
            // 1. Restore stock for each item
            foreach ($sale->items as $item) {
                StockLevel::where('product_id', $item->product_id)
                    ->when($item->variant_id, fn ($q) => $q->where('variant_id', $item->variant_id))
                    ->increment('quantity', $item->quantity);
            }

            // 2. Reverse customer balance + always add a void ledger entry for any linked customer
            if ($sale->customer_id) {
                $currentBalance = DB::table('customers')
                    ->where('id', $sale->customer_id)
                    ->value('current_balance') ?? 0;

                // Only udhaar changes the running balance
                $newBalance = (float) $currentBalance;
                if ($sale->udhaar_amount > 0) {
                    $newBalance = max(0, (float) $currentBalance - (float) $sale->udhaar_amount);
                }

                DB::table('customers')
                    ->where('id', $sale->customer_id)
                    ->update([
                        'current_balance' => $newBalance,
                        'total_spend'     => DB::raw("GREATEST(0, total_spend - {$sale->total})"),
                    ]);

                CustomerLedgerEntry::create([
                    'tenant_id'       => $sale->tenant_id,
                    'customer_id'     => $sale->customer_id,
                    'sale_id'         => $sale->id,
                    'type'            => 'void',
                    'amount'          => -(float) $sale->total,   // full sale reversed
                    'running_balance' => $newBalance,
                    'description'     => "Void: {$sale->invoice_number} ({$sale->payment_method})",
                ]);
            }

            // 3. Mark voided
            $sale->update(['status' => 'voided']);
        });

        // Reverse the accounting journal entry
        try {
            AccountingService::reverseSale($sale);
        } catch (\Throwable $e) {
            \Log::warning("AccountingService::reverseSale failed for sale {$sale->id}: " . $e->getMessage());
        }

        return back()->with('success', "Sale {$sale->invoice_number} voided successfully.");
    }
}
