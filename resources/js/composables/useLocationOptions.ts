import type { SearchableOption } from '@/components/SearchableSelect.vue';
import { route } from 'ziggy-js';

type CityApiRow = { id: number; name: string; province: string };
type AreaApiRow = { id: number; name: string };

let citiesPromise: Promise<SearchableOption[]> | null = null;

async function asJson(resp: Response) {
    if (!resp.ok) throw new Error(`Request failed: ${resp.status}`);
    return resp.json();
}

/** Loads seeded Pakistan cities once (cached) for searchable dropdowns. */
export function fetchCitySearchOptions(): Promise<SearchableOption[]> {
    citiesPromise ??= (async (): Promise<SearchableOption[]> => {
        const url = route('locations.cities.json');
        const data = await asJson(
            await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            }),
        ) as { data?: CityApiRow[] };
        const rows = data.data ?? [];
        return rows.map((c) => ({
            value: c.id,
            label: c.name,
            subtitle: c.province,
        }));
    })();
    return citiesPromise;
}

/** Areas for one city — tenant-filtered via backend. */
export async function fetchAreaSearchOptionsForCity(cityId: number | string | null): Promise<SearchableOption[]> {
    if (cityId == null || cityId === '' || Number(cityId) <= 0) return [];

    const url = route('locations.cities.areas.index', { city: cityId });
    const data = await asJson(
        await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }),
    ) as { data?: AreaApiRow[] };

    return (data.data ?? []).map((a) => ({
        value: a.id,
        label: a.name,
    }));
}
