<script setup lang="ts">
import SearchableSelect, { type SearchableOption } from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { fetchAreaSearchOptionsForCity, fetchCitySearchOptions } from '@/composables/useLocationOptions';
import { router } from '@inertiajs/vue3';
import { FilterX } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        /** Ziggy route name, e.g. `reports.udhaar-customers` */
        reportRoute: string;
        filters?: { city_id?: number | null; area_id?: number | null };
        /** Extra query params preserved on each navigation (e.g. `{ from, to }` or `{ q }`). */
        mergeQuery?: Record<string, string | number | boolean | undefined | null>;
    }>(),
    { filters: () => ({}), mergeQuery: () => ({}) },
);

const { t, locale } = useI18n();

const cityId = ref<number | null>(props.filters?.city_id ?? null);
const areaId = ref<number | null>(props.filters?.area_id ?? null);
const cityOptions = ref<SearchableOption[]>([]);
const areaOptions = ref<SearchableOption[]>([]);
const areasBusy = ref(false);

const canNavigate = ref(false);

async function loadCities() {
    try {
        cityOptions.value = await fetchCitySearchOptions(locale.value);
    } catch {
        cityOptions.value = [];
    }
}

async function loadAreasForCity(id: number | null) {
    if (!id) {
        areaOptions.value = [];
        return;
    }
    areasBusy.value = true;
    try {
        areaOptions.value = await fetchAreaSearchOptionsForCity(id);
    } catch {
        areaOptions.value = [];
    } finally {
        areasBusy.value = false;
    }
}

watch(
    () => [props.filters?.city_id, props.filters?.area_id],
    ([c, a]) => {
        cityId.value = c ?? null;
        areaId.value = a ?? null;
    },
);

watch(locale, () => {
    void loadCities();
});

watch(
    () => cityId.value,
    async (cid, prev) => {
        if (cid == null || cid === 0) {
            areaId.value = null;
            areaOptions.value = [];
            return;
        }
        if (prev !== undefined && cid !== prev) {
            areaId.value = null;
        }
        await loadAreasForCity(cid ?? null);
    },
);

watch(
    () => props.mergeQuery,
    () => {
        scheduleNavigate();
    },
    { deep: true },
);

watch([cityId, areaId], () => {
    scheduleNavigate();
});

onMounted(async () => {
    await loadCities();
    if (cityId.value) {
        await loadAreasForCity(cityId.value);
    }
    await nextTick();
    canNavigate.value = true;
});

let debounceTimer: ReturnType<typeof setTimeout>;

function pruneParams(raw: Record<string, unknown>): Record<string, string | number> {
    const out: Record<string, string | number> = {};
    for (const [k, v] of Object.entries(raw)) {
        if (v === undefined || v === null || v === '') {
            continue;
        }
        if (typeof v === 'boolean') {
            out[k] = v ? '1' : '0';
        } else {
            out[k] = v as string | number;
        }
    }
    return out;
}

function scheduleNavigate() {
    if (!canNavigate.value) {
        return;
    }
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const extra = pruneParams({ ...(props.mergeQuery ?? {}) });
        if (cityId.value != null && cityId.value > 0) {
            extra.city_id = cityId.value;
        }
        if (areaId.value != null && areaId.value > 0) {
            extra.area_id = areaId.value;
        }
        router.get(route(props.reportRoute as never), extra, { preserveState: true, replace: true });
    }, 420);
}

function clearLocation() {
    cityId.value = null;
    areaId.value = null;
    areaOptions.value = [];
}
</script>

<template>
    <div
        class="print-hide flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end"
    >
        <div class="flex min-w-[12rem] flex-1 flex-col gap-1.5 sm:max-w-[18rem]">
            <Label class="text-xs uppercase tracking-wide text-muted-foreground">{{ t('customers.cityPick') }}</Label>
            <SearchableSelect
                v-model="cityId"
                :options="cityOptions"
                button-id="reports-filter-city"
                :placeholder="t('customers.cityPlaceholder')"
                :search-placeholder="t('locations.searchCities')"
                clearable
                class="[&_[data-searchable-trigger]]:w-full"
            />
        </div>
        <div class="flex min-w-[12rem] flex-1 flex-col gap-1.5 sm:max-w-[18rem]">
            <Label class="text-xs uppercase tracking-wide text-muted-foreground">{{ t('customers.areaPick') }}</Label>
            <SearchableSelect
                v-model="areaId"
                :options="areaOptions"
                :disabled="!cityId || areasBusy"
                button-id="reports-filter-area"
                :placeholder="
                    areasBusy ? t('locations.areasLoading') : cityId ? t('customers.areaPlaceholder') : t('customers.areaNeedsCity')
                "
                :search-placeholder="t('locations.searchAreas')"
                clearable
                class="[&_[data-searchable-trigger]]:w-full"
            />
        </div>
        <Button type="button" variant="outline" class="gap-2 self-start sm:self-end" @click="clearLocation">
            <FilterX class="size-4" aria-hidden /> {{ t('reports.clearLocationFilters') }}
        </Button>
        <p class="w-full text-xs text-muted-foreground sm:flex-[100%]">{{ t('reports.locationFilterHint') }}</p>
    </div>
</template>
