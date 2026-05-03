import type { SearchableOption } from '@/components/SearchableSelect.vue';
import { route } from 'ziggy-js';

type CityApiRow = {
    id: number;
    name?: string | null;
    name_ur?: string | null;
    province?: string | null;
};
type AreaApiRow = { id: number; name: string };

const cityOptionsCache = new Map<string, Promise<SearchableOption[]>>();

async function asJson(resp: Response) {
    if (!resp.ok) throw new Error(`Request failed: ${resp.status}`);
    return resp.json();
}

function cityLabel(row: CityApiRow, localeKey: string): string {
    const ur = typeof row.name_ur === 'string' ? row.name_ur.trim() : '';
    const en = typeof row.name === 'string' ? row.name.trim() : '';
    if (localeKey === 'ur' && ur) {
        return ur;
    }

    return en || ur || `City #${row.id}`;
}

/** Loads seeded Pakistan cities (cached per UI locale for correct Urdu labels). */
export function fetchCitySearchOptions(locale: string = 'en'): Promise<SearchableOption[]> {
    const localeKey = /^ur/i.test(String(locale ?? '').trim()) ? 'ur' : 'en';
    const cacheKey = `${localeKey}:kw`;
    let promise = cityOptionsCache.get(cacheKey);
    if (!promise) {
        promise = (async (): Promise<SearchableOption[]> => {
            try {
                const url = route('locations.cities.json');
                const data = (await asJson(
                    await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    }),
                )) as { data?: CityApiRow[] };
                const rows = data.data ?? [];
                return rows.map((c) => ({
                    value: c.id,
                    label: cityLabel(c, localeKey),
                    subtitle: typeof c.province === 'string' ? c.province : '',
                    keywords: [c.name ?? '', c.name_ur ?? '', c.province ?? ''].filter(Boolean).join(' '),
                }));
            } catch {
                cityOptionsCache.delete(cacheKey);
                throw new Error(`Failed to load cities (${cacheKey}).`);
            }
        })();
        cityOptionsCache.set(cacheKey, promise);
    }
    return promise;
}

/** Areas for one city — tenant-filtered via backend. */
export async function fetchAreaSearchOptionsForCity(cityId: number | string | null): Promise<SearchableOption[]> {
    if (cityId == null || cityId === '' || Number(cityId) <= 0) return [];

    const url = route('locations.cities.areas.index', { city: cityId });
    const data = (await asJson(
        await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }),
    )) as { data?: AreaApiRow[] };

    return (data.data ?? []).map((a) => ({
        value: a.id,
        label: a.name,
    }));
}
