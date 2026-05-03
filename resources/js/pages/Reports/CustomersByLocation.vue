<script setup lang="ts">
import ReportsFilterSummary, {
    type ReportFilterLabels,
} from '@/components/reports/ReportsFilterSummary.vue';
import ReportsLocationFilters from '@/components/reports/ReportsLocationFilters.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { MapPin } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('reports.moduleTitle'), href: route('reports.index') },
    { title: t('reports.byLocationTitle'), href: route('reports.customers-by-location') },
]);

interface Row {
    city_name: string;
    province: string;
    area_name: string;
    customer_count: number;
    with_udhaar: number;
    total_udhaar: number;
    total_spend: number;
}

const props = defineProps<{
    rows: Row[];
    filters: { q?: string | null; city_id?: number | null; area_id?: number | null };
    filter_labels: ReportFilterLabels;
}>();

const q = ref(props.filters.q ?? '');

watch(
    () => props.filters.q,
    (v) => {
        q.value = v ?? '';
    },
);

/** City & area picker only — text search merges via ReportsLocationFilters. */
const pickerFilters = computed(() => ({
    city_id: props.filters.city_id,
    area_id: props.filters.area_id,
}));

const mergeQuery = computed(() => ({
    q: q.value.trim() || undefined,
}));

/** Search text on print reflects the field (navigation may lag). */
const printFilterLabels = computed<ReportFilterLabels>(() => ({
    ...props.filter_labels,
    q: q.value.trim() || null,
}));
</script>

<template>
    <Head :title="t('reports.byLocationTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="reports-print-root mx-auto max-w-6xl space-y-4 p-4 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ t('reports.byLocationTitle') }}</h1>
                    <p class="print-hide mt-1 max-w-2xl text-sm text-muted-foreground">{{ t('reports.byLocationSubtitle') }}</p>
                </div>
                <Link :href="route('reports.index')" class="print-hide text-sm text-primary hover:underline">
                    ← {{ t('reports.moduleTitle') }}
                </Link>
            </div>

            <ReportsFilterSummary :filter-labels="printFilterLabels" />

            <ReportsLocationFilters
                report-route="reports.customers-by-location"
                :filters="pickerFilters"
                :merge-query="mergeQuery"
            />

            <div class="print-hide max-w-md">
                <Input v-model="q" type="search" class="h-10" :placeholder="t('reports.searchLocations')" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center gap-2 border-b border-border px-4 py-3">
                    <MapPin class="print-hide size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <h2 class="font-semibold">{{ t('reports.byLocationTitle') }}</h2>
                </div>

                <table class="w-full border-collapse text-sm">
                    <thead class="border-b border-border bg-muted/40 [&>tr>th]:align-middle">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('reports.colCity') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('reports.colProvince') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('reports.colArea') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('reports.colCustomerCount') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('reports.colWithUdhaar') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('reports.colTotalUdhaar') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('reports.colTotalSpend') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border [&>tr>td]:align-middle">
                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                {{ t('common.noResults') }}
                            </td>
                        </tr>
                        <tr v-for="(r, idx) in rows" :key="idx" class="hover:bg-muted/20">
                            <td class="px-4 py-3 font-medium text-foreground">{{ r.city_name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ r.province }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ r.area_name }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ r.customer_count }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ r.with_udhaar }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ formatMoney(r.total_udhaar) }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ formatMoney(r.total_spend) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
