<?php

namespace App\Http\Controllers\Locations;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LocationsController extends Controller
{
    public function index(Request $request): Response
    {
        $province = $request->string('province')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $citiesQuery = City::query()->select(['id', 'name', 'name_ur', 'province'])->orderBy('province')->orderBy('name');
        if ($province !== '') {
            $citiesQuery->where('province', $province);
        }

        /** @var Collection<int,City> */
        $cities = $citiesQuery->get();

        $areaQuery = Area::query()
            ->with(['city:id,name,name_ur,province'])
            ->orderBy('city_id')
            ->orderBy('name');

        if ($province !== '') {
            $areaQuery->whereHas('city', fn ($q) => $q->where('province', $province));
        }

        if ($search !== '') {
            $lower = strtolower($search);
            $areaQuery->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]);
        }

        /** @var Collection<int,Area> */
        $areas = $areaQuery->get();

        return Inertia::render('Locations/Index', [
            'cities' => $cities,
            'areas' => $areas->map(fn (Area $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'city_id' => $a->city_id,
                'city_name' => $a->city->name ?? '',
                'city_name_ur' => $a->city->name_ur ?? null,
                'province' => $a->city->province ?? '',
            ]),
            'provinces' => City::query()->select('province')->distinct()->orderBy('province')->pluck('province'),
            'filters' => compact('province', 'search'),
        ]);
    }

    public function citiesJson(): JsonResponse
    {
        $rows = City::query()->select(['id', 'name', 'name_ur', 'province'])
            ->orderBy('province')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (City $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'name_ur' => $c->name_ur,
                'province' => $c->province,
                'subtitle' => $c->province,
            ]),
        ]);
    }

    /**
     * Public JSON for searchable area pickers (scoped to authenticated tenant via Area global scope).
     */
    public function areasForCity(Request $request, City $city): JsonResponse
    {
        $rows = Area::query()
            ->where('city_id', $city->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenant_id;

        $validated = $request->validate([
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            'name' => ['required', 'string', 'max:120'],
        ]);

        Area::create([
            'tenant_id' => $tenantId,
            'city_id' => (int) $validated['city_id'],
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Area added.');
    }

    public function updateArea(Request $request, Area $area): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $area->update([
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Area updated.');
    }

    public function destroyArea(Area $area): RedirectResponse
    {
        $area->delete();

        return back()->with('success', 'Area removed.');
    }

    public static function assertAreaMatchesCityTenant(?int $cityId, ?int $areaId, string $tenantId): void
    {
        if ($areaId === null || $areaId === 0) {
            return;
        }

        if ($cityId === null || $cityId === 0) {
            throw ValidationException::withMessages([
                'city_id' => 'Select a city before choosing an area.',
            ]);
        }

        $area = Area::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($areaId);

        if (! $area || (int) $area->city_id !== $cityId) {
            throw ValidationException::withMessages([
                'area_id' => 'The selected area is invalid for this city.',
            ]);
        }
    }
}
