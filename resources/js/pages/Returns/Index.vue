<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowUpLeft, Calendar, Eye, RotateCcw, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.sales'), href: route('sales.index') },
    { title: t('returns.pageTitle'), href: route('returns.index') },
]);

interface ReturnRow {
    id: string;
    return_number: string;
    return_date: string;
    sale_id: string;
    invoice_number: string | null;
    refund_method: string;
    total_refund: number;
    reason: string | null;
    status: string;
    created_by: string | null;
}

const props = defineProps<{
    returns: { data: ReturnRow[]; current_page: number; last_page: number; total: number };
    stats: { this_month: number; this_year: number; total_count: number };
    filters: { search?: string; refund_method?: string; date_from?: string; date_to?: string };
}>();

const search       = ref(props.filters.search ?? '');
const methodFilter = ref(props.filters.refund_method ?? '');
const dateFrom     = ref(props.filters.date_from ?? '');
const dateTo       = ref(props.filters.date_to ?? '');

let filterTimer: ReturnType<typeof setTimeout>;
watch([search, methodFilter, dateFrom, dateTo], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => applyFilters(), 400);
});

function applyFilters(extra: Record<string, unknown> = {}) {
    router.get(route('returns.index'), {
        search: search.value || undefined,
        refund_method: methodFilter.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        ...extra,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    search.value = '';
    methodFilter.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
}

const hasFilters = () => search.value || methodFilter.value || dateFrom.value || dateTo.value;

function fmtDate(d: string) {
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(d + 'T00:00:00').toLocaleDateString(loc, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

const methodLabel = computed<Record<string, string>>(() => ({
    cash: t('common.cash'),
    bank: t('returns.bank'),
    store_credit: t('returns.storeCredit'),
}));

function paginationQuery(page: number) {
    applyFilters(page > 1 ? { page } : {});
}

const methodClass: Record<string, string> = {
    cash:         'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    bank:         'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    store_credit: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
};
</script>

<template>
    <Head :title="t('returns.pageTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('returns.pageTitle') }}</h1>
                    <p class="text-muted-foreground text-sm mt-1">{{ t('returns.pageDescription') }}</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <StatCard
                    :label="t('common.thisMonth')"
                    :value="formatMoney(stats.this_month)"
                    :icon="Calendar"
                    tone="danger"
                />
                <StatCard
                    :label="t('common.thisYear')"
                    :value="formatMoney(stats.this_year)"
                    :icon="ArrowUpLeft"
                />
                <StatCard
                    :label="t('returns.ytd')"
                    :value="String(stats.total_count)"
                    :icon="RotateCcw"
                    tone="warning"
                />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <Input v-model="search" :placeholder="t('returns.searchPlaceholder')" class="ps-3" />
                </div>
                <select
                    v-model="methodFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">{{ t('returns.allMethods') }}</option>
                    <option value="cash">{{ t('common.cash') }}</option>
                    <option value="bank">{{ t('returns.bank') }}</option>
                    <option value="store_credit">{{ t('returns.storeCredit') }}</option>
                </select>
                <div class="flex items-center gap-2">
                    <Input v-model="dateFrom" type="date" class="w-36 text-sm" />
                    <span class="text-muted-foreground text-xs">{{ t('common.to') }}</span>
                    <Input v-model="dateTo" type="date" class="w-36 text-sm" />
                </div>
                <Button v-if="hasFilters()" variant="ghost" size="icon" @click="clearFilters">
                    <X :size="16" />
                </Button>
            </div>

            <!-- Table -->
            <div class="border rounded-xl overflow-x-auto">
                <table class="w-full border-collapse text-sm min-w-[640px]">
                    <thead class="bg-muted/50">
                        <tr class="[&>th]:align-middle">
                            <th class="px-4 py-3 text-start font-medium">{{ t('returns.returnNumber') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('common.date') }}</th>
                            <th class="px-4 py-3 text-start font-medium hidden md:table-cell">{{ t('returns.originalSale') }}</th>
                            <th class="px-4 py-3 text-start font-medium hidden md:table-cell">{{ t('common.reason') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('common.method') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ t('returns.refund') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="returns.data.length === 0">
                            <td colspan="7" class="py-12 text-center text-muted-foreground">
                                {{ t('returns.noReturnsFound') }}
                            </td>
                        </tr>
                        <tr
                            v-for="r in returns.data"
                            :key="r.id"
                            class="hover:bg-muted/30 transition-colors [&>td]:align-middle"
                        >
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                                {{ r.return_number }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ fmtDate(r.return_date) }}
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <Link
                                    v-if="r.invoice_number"
                                    :href="route('sales.show', r.sale_id)"
                                    class="font-mono text-xs text-primary hover:underline"
                                >
                                    {{ r.invoice_number }}
                                </Link>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-muted-foreground text-xs">
                                {{ r.reason ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="methodClass[r.refund_method] ?? 'bg-muted text-muted-foreground'"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ methodLabel[r.refund_method] ?? r.refund_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums font-semibold text-red-600 dark:text-red-400">
                                −{{ formatMoney(r.total_refund) }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <div class="inline-flex justify-end gap-1 rtl:flex-row-reverse">
                                    <Button variant="ghost" size="icon" :as="'a'"
                                        :href="route('returns.show', r.id)" :title="t('returns.viewDetailsTitle')">
                                        <Eye :size="15" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="returns.last_page > 1" class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">
                    {{ t('returns.paginationSummary', { current: returns.current_page, last: returns.last_page, total: returns.total }) }}
                </span>
                <div class="flex gap-2">
                    <Button
                        v-if="returns.current_page > 1"
                        variant="outline" size="sm"
                        @click="paginationQuery(returns.current_page - 1)"
                    >{{ t('common.previous') }}</Button>
                    <Button
                        v-if="returns.current_page < returns.last_page"
                        variant="outline" size="sm"
                        @click="paginationQuery(returns.current_page + 1)"
                    >{{ t('common.next') }}</Button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
