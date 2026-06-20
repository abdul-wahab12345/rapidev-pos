<?php

namespace App\Http\Controllers\Returns;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\AccountingService;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $query = SaleReturn::with(['sale:id,invoice_number', 'creator:id,name'])
            ->where('sale_returns.tenant_id', $tenantId);

        if ($request->filled('refund_method')) {
            $query->where('refund_method', $request->refund_method);
        }

        if ($request->filled('date_from')) {
            $query->where('return_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('return_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('return_number', 'like', "%{$s}%")
                  ->orWhereHas('sale', fn ($q2) => $q2->where('invoice_number', 'like', "%{$s}%"));
            });
        }

        $returns = $query->orderByDesc('return_date')
            ->orderByDesc('return_number')
            ->paginate(20)
            ->through(fn ($r) => [
                'id'            => $r->id,
                'return_number' => $r->return_number,
                'return_date'   => $r->return_date->format('Y-m-d'),
                'sale_id'       => $r->sale_id,
                'invoice_number' => $r->sale?->invoice_number,
                'refund_method' => $r->refund_method,
                'total_refund'  => $r->total_refund,
                'reason'        => $r->reason,
                'status'        => $r->status,
                'created_by'    => $r->creator?->name,
            ]);

        $base = SaleReturn::where('sale_returns.tenant_id', $tenantId);

        $stats = [
            'this_month'    => (int) (clone $base)->whereMonth('return_date', now()->month)->whereYear('return_date', now()->year)->sum('total_refund'),
            'this_year'     => (int) (clone $base)->whereYear('return_date', now()->year)->sum('total_refund'),
            'total_count'   => (clone $base)->whereYear('return_date', now()->year)->count(),
        ];

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
            'stats'   => $stats,
            'filters' => $request->only(['search', 'refund_method', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request, Sale $sale): RedirectResponse
    {
        if (in_array($sale->status, ['voided', 'returned'])) {
            return back()->with('error', "Sale {$sale->invoice_number} cannot be returned (status: {$sale->status}).");
        }

        $data = $request->validate([
            'refund_method'        => 'required|in:cash,bank,store_credit',
            'reason'               => 'nullable|string|max:255',
            'notes'                => 'nullable|string|max:1000',
            'items'                => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|uuid|exists:sale_items,id',
            'items.*.quantity_returned' => 'required|numeric|min:0.01',
            'items.*.restock'      => 'boolean',
        ]);

        // Rename key to match service expectation
        foreach ($data['items'] as &$item) {
            $item['quantity_returned'] = $item['quantity_returned'];
        }

        try {
            $return = ReturnService::process($sale, $data, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        try {
            $return->load('sale');
            AccountingService::postReturn($return);
        } catch (\Throwable $e) {
            \Log::warning("AccountingService::postReturn failed for return {$return->id}: " . $e->getMessage());
        }

        return back()->with('success', "Return {$return->return_number} processed successfully.");
    }

    public function show(SaleReturn $return): Response
    {
        $return->load(['sale.customer', 'items', 'creator', 'branch']);

        return Inertia::render('Returns/Show', [
            'saleReturn' => [
                'id'             => $return->id,
                'return_number'  => $return->return_number,
                'return_date'    => $return->return_date->format('Y-m-d'),
                'refund_method'  => $return->refund_method,
                'total_refund'   => $return->total_refund,
                'reason'         => $return->reason,
                'notes'          => $return->notes,
                'status'         => $return->status,
                'created_by'     => $return->creator?->name,
                'branch'         => $return->branch?->name,
                'sale' => [
                    'id'             => $return->sale?->id,
                    'invoice_number' => $return->sale?->invoice_number,
                    'customer'       => $return->sale?->customer ? [
                        'id'   => $return->sale->customer->id,
                        'name' => $return->sale->customer->name,
                    ] : null,
                ],
                'items' => $return->items->map(fn ($i) => [
                    'id'                => $i->id,
                    'product_name'      => $i->product_name,
                    'variant_label'     => $i->variant_label,
                    'quantity_returned' => $i->quantity_returned,
                    'unit_price'        => $i->unit_price,
                    'line_total'        => $i->line_total,
                    'restock'           => $i->restock,
                ]),
            ],
        ]);
    }
}
