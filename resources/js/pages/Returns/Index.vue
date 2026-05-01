<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowUpLeft, Calendar, Eye, PackageX, RotateCcw, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales' },
    { title: 'Returns & Refunds', href: '/returns' },
];

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

function applyFilters() {
    router.get('/returns', {
        search:         search.value || undefined,
        refund_method:  methodFilter.value || undefined,
        date_from:      dateFrom.value || undefined,
        date_to:        dateTo.value || undefined,
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
    return new Date(d + 'T00:00:00').toLocaleDateString('en-PK', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

const methodLabel: Record<string, string> = {
    cash: 'Cash',
    bank: 'Bank',
    store_credit: 'Store Credit',
};

const methodClass: Record<string, string> = {
    cash:         'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    bank:         'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    store_credit: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
};
</script>

<template>
    <Head title="Returns & Refunds" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Returns & Refunds</h1>
                    <p class="text-muted-foreground text-sm mt-1">Track all processed sale returns</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <StatCard
                    label="This Month"
                    :value="formatMoney(stats.this_month)"
                    :icon="Calendar"
                    tone="danger"
                />
                <StatCard
                    label="This Year"
                    :value="formatMoney(stats.this_year)"
                    :icon="ArrowUpLeft"
                />
                <StatCard
                    label="Returns (YTD)"
                    :value="String(stats.total_count)"
                    :icon="RotateCcw"
                    tone="warning"
                />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <Input v-model="search" placeholder="Search by return # or invoice #…" class="pl-3" />
                </div>
                <select
                    v-model="methodFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">All Methods</option>
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                    <option value="store_credit">Store Credit</option>
                </select>
                <div class="flex items-center gap-2">
                    <Input v-model="dateFrom" type="date" class="w-36 text-sm" />
                    <span class="text-muted-foreground text-xs">to</span>
                    <Input v-model="dateTo" type="date" class="w-36 text-sm" />
                </div>
                <Button v-if="hasFilters()" variant="ghost" size="icon" @click="clearFilters">
                    <X :size="16" />
                </Button>
            </div>

            <!-- Table -->
            <div class="border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Return #</th>
                            <th class="px-4 py-3 text-left font-medium">Date</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Original Sale</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Reason</th>
                            <th class="px-4 py-3 text-left font-medium">Method</th>
                            <th class="px-4 py-3 text-right font-medium">Refund</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="returns.data.length === 0">
                            <td colspan="7" class="py-12 text-center text-muted-foreground">
                                No returns found
                            </td>
                        </tr>
                        <tr
                            v-for="r in returns.data"
                            :key="r.id"
                            class="hover:bg-muted/30 transition-colors"
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
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-red-600 dark:text-red-400">
                                −{{ formatMoney(r.total_refund) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" :as="'a'"
                                        :href="route('returns.show', r.id)" title="View details">
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
                    Page {{ returns.current_page }} of {{ returns.last_page }} · {{ returns.total }} total
                </span>
                <div class="flex gap-2">
                    <Button
                        v-if="returns.current_page > 1"
                        variant="outline" size="sm"
                        @click="router.get('/returns', { ...filters, page: returns.current_page - 1 })"
                    >Previous</Button>
                    <Button
                        v-if="returns.current_page < returns.last_page"
                        variant="outline" size="sm"
                        @click="router.get('/returns', { ...filters, page: returns.current_page + 1 })"
                    >Next</Button>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
