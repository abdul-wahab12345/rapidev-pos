<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Locations\LocationsController;
use App\Models\Area;
use App\Models\City;
use App\Support\Reports\OperationalReportQueries;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationalReportsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Reports/Index');
    }

    public function udhaarCustomers(Request $request): Response
    {
        $tenantId = auth()->user()->tenant->id;
        $loc = $this->validatedLocationFilters($request);
        $rows = OperationalReportQueries::udhaarCustomerRows($tenantId, $loc['city_id'], $loc['area_id']);

        return Inertia::render('Reports/UdhaarCustomers', [
            'rows' => $rows->values()->all(),
            'filter_labels' => $this->filterLabelsForLocation($request, $loc),
            'totals' => [
                'gross_udhaar' => round((float) $rows->sum('gross_udhaar'), 2),
                'vendor_payable' => round((float) $rows->sum('vendor_payable_ledger'), 2),
                'open_po' => round((float) $rows->sum('open_po_due'), 2),
                'net_vendor_ledger' => round((float) $rows->sum('net_after_vendor_ledger'), 2),
                'net_vs_po' => round((float) $rows->sum('net_after_open_po_due'), 2),
            ],
            'filters' => $loc,
        ]);
    }

    public function payableVendors(Request $request): Response
    {
        $tenantId = auth()->user()->tenant->id;
        $loc = $this->validatedLocationFilters($request);
        $rows = OperationalReportQueries::payableVendorRows($tenantId, $loc['city_id'], $loc['area_id']);

        return Inertia::render('Reports/PayableVendors', [
            'rows' => $rows->values()->all(),
            'filter_labels' => $this->filterLabelsForLocation($request, $loc),
            'totals' => [
                'vendor_ledger' => round((float) $rows->sum('vendor_ledger'), 2),
                'open_po' => round((float) $rows->sum('open_po_due'), 2),
                'ar_balance' => round((float) $rows->sum('ar_balance'), 2),
                'net_payable' => round((float) $rows->sum('net_payable'), 2),
            ],
            'filters' => $loc,
        ]);
    }

    public function customersByLocation(Request $request): Response
    {
        $tenantId = auth()->user()->tenant->id;
        $loc = $this->validatedLocationFilters($request);

        $qRaw = trim((string) $request->get('q', ''));
        $q = strtolower($qRaw);
        $rows = OperationalReportQueries::customersByLocationAggregate($tenantId, $loc['city_id'], $loc['area_id']);

        if ($q !== '') {
            $rows = $rows->filter(function (array $r) use ($q) {
                return str_contains(strtolower((string) $r['city_name']), $q)
                    || str_contains(strtolower((string) $r['area_name']), $q)
                    || str_contains(strtolower((string) $r['province']), $q);
            })->values();
        }

        return Inertia::render('Reports/CustomersByLocation', [
            'rows' => $rows->all(),
            'filters' => array_merge(['q' => $qRaw !== '' ? $qRaw : null], $loc),
            'filter_labels' => $this->filterLabelsForLocation($request, $loc, ['q' => $qRaw !== '' ? $qRaw : null]),
        ]);
    }

    public function salesSummary(Request $request): Response
    {
        $tenantId = auth()->user()->tenant->id;
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));
        $loc = $this->validatedLocationFilters($request);
        $summary = OperationalReportQueries::salesSummary($tenantId, $from, $to, $loc['city_id'], $loc['area_id']);

        return Inertia::render('Reports/SalesSummary', [
            'summary' => $summary,
            'filters' => array_merge([
                'from' => $from,
                'to' => $to,
            ], $loc),
            'filter_labels' => $this->filterLabelsForLocation($request, $loc, [
                'from' => $from,
                'to' => $to,
            ]),
        ]);
    }

    /**
     * @return array{city_id: int|null, area_id: int|null}
     */
    private function validatedLocationFilters(Request $request): array
    {
        $tenantId = auth()->user()->tenant->id;

        $data = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'area_id' => ['nullable', 'integer'],
        ]);

        $cityId = isset($data['city_id']) && (int) $data['city_id'] > 0 ? (int) $data['city_id'] : null;
        $areaId = isset($data['area_id']) && (int) $data['area_id'] > 0 ? (int) $data['area_id'] : null;

        LocationsController::assertAreaMatchesCityTenant($cityId, $areaId, $tenantId);

        return [
            'city_id' => $cityId,
            'area_id' => $areaId,
        ];
    }

    /**
     * Localized human-readable filters for on-screen summaries and printing (tenant language).
     *
     * @param  array<string, mixed>  $extra
     * @return array{city?: string|null, area?: string|null, from?: string|null, to?: string|null, q?: string|null}
     */
    private function filterLabelsForLocation(Request $request, array $loc, array $extra = []): array
    {
        $lang = $request->user()->tenant?->getLanguage() ?? 'en';

        $out = [];

        $cityId = $loc['city_id'] ?? null;
        $areaId = $loc['area_id'] ?? null;

        $out['city'] = null;
        if ($cityId) {
            $city = City::query()->find($cityId);
            $out['city'] = $city ? $city->localizedName($lang) : null;
        }

        $out['area'] = null;
        if ($areaId) {
            $area = Area::query()->find($areaId);
            $out['area'] = $area ? (string) $area->name : null;
        }

        foreach (['from', 'to', 'q'] as $key) {
            if (! array_key_exists($key, $extra)) {
                continue;
            }
            $value = $extra[$key];
            $out[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return $out;
    }
}
