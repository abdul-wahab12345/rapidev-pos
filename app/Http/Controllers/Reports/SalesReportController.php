<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    /** Sales report — one page, four views selected via ?view=. Returns are NOT netted. */
    public function sales(Request $request): Response
    {
        $data = $request->validate([
            'view'    => 'nullable|in:transactions,by_product,by_day,by_party',
            'from'    => 'nullable|date',
            'to'      => 'nullable|date',
            'payment' => 'nullable|string|max:20',
        ]);

        $view = $data['view'] ?? 'transactions';
        $from = $data['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $data['to']   ?? now()->format('Y-m-d');

        $payload = match ($view) {
            'by_product' => ['by_product' => SalesReportService::byProduct($from, $to)],
            'by_day'     => ['by_day'     => SalesReportService::byDay($from, $to)],
            'by_party'   => ['by_party'   => SalesReportService::byParty($from, $to)],
            default      => ['transactions' => SalesReportService::transactions($from, $to, $data['payment'] ?? null)],
        };

        return Inertia::render('Reports/SalesReport', array_merge([
            'view'    => $view,
            'summary' => SalesReportService::summary($from, $to),
            'filters' => ['from' => $from, 'to' => $to, 'payment' => $data['payment'] ?? null],
        ], $payload));
    }

    /** Returns report — by return date, linked to original invoices. */
    public function returns(Request $request): Response
    {
        $data = $request->validate([
            'from'   => 'nullable|date',
            'to'     => 'nullable|date',
            'method' => 'nullable|in:cash,bank,store_credit',
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $data['to']   ?? now()->format('Y-m-d');

        return Inertia::render('Reports/ReturnsReport', [
            'report'  => SalesReportService::returns($from, $to, $data['method'] ?? null),
            'filters' => ['from' => $from, 'to' => $to, 'method' => $data['method'] ?? null],
        ]);
    }
}
