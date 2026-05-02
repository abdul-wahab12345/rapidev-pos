<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Clock, Eye, Package,
    Plus, Search, ShoppingCart, TrendingUp, Wallet, X,
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.purchasing'), href: route('purchasing.orders.index') },
    { title: t('purchasing.pageTitle'), href: route('purchasing.orders.index') },
]);

interface OrderRow {
    id: string;
    po_number: string;
    supplier: { name: string };
    order_date: string;
    expected_date: string | null;
    status: string;
    total: number;
    paid_amount: number;
    amount_due: number;
}

const props = defineProps<{
    orders: { data: OrderRow[]; current_page: number; last_page: number; total: number };
    stats: { total: number; pending: number; total_due: number; this_month: number };
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 400); });
watch(statusFilter, applyFilters);

function applyFilters(extra: Record<string, unknown> = {}) {
    router.get(route('purchasing.orders.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        ...extra,
    }, { preserveState: true, replace: true });
}

function paginationQuery(page: number) {
    applyFilters(page > 1 ? { page } : {});
}

const statusCls: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    ordered: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    partial: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    received: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    cancelled: 'bg-red-500/10 text-red-500',
};

function poStatusLabel(status: string) {
    const keys = ['draft', 'ordered', 'partial', 'received', 'cancelled'];
    return keys.includes(status) ? t(`purchasing.${status}` as 'purchasing.draft') : status;
}

function fmt(n: number) {
    if (n == null) return '—';
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}
</script>

<template>
    <Head :title="t('purchasing.pageTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('purchasing.pageTitle') }}</h1>
                    <p class="text-muted-foreground text-sm mt-1">{{ t('purchasing.pageDescription') }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('purchasing.suppliers.index')">
                        <Button variant="outline" class="gap-2 rtl:flex-row-reverse">
                            <Package :size="16" /> {{ t('purchasing.suppliersLink') }}
                        </Button>
                    </Link>
                    <Link :href="route('purchasing.orders.create')">
                        <Button class="gap-2 rtl:flex-row-reverse">
                            <Plus :size="16" /> {{ t('purchasing.newPo') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard :label="t('purchasing.totalOrders')" :value="String(stats.total)" :icon="ShoppingCart" tone="info" />
                <StatCard :label="t('purchasing.pending')" :value="String(stats.pending)" :icon="Clock" tone="warning" />
                <StatCard :label="t('purchasing.totalDue')" :value="fmt(stats.total_due)" :icon="Wallet" tone="danger" />
                <StatCard :label="t('common.thisMonth')" :value="fmt(stats.this_month)" :icon="TrendingUp" tone="success" />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <Search :size="16" class="text-muted-foreground absolute start-3 top-1/2 -translate-y-1/2" />
                    <Input v-model="search" :placeholder="t('purchasing.searchPoPlaceholder')" class="ps-9" />
                </div>
                <select v-model="statusFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm">
                    <option value="">{{ t('purchasing.allStatuses') }}</option>
                    <option value="draft">{{ t('purchasing.draft') }}</option>
                    <option value="ordered">{{ t('purchasing.ordered') }}</option>
                    <option value="partial">{{ t('purchasing.partial') }}</option>
                    <option value="received">{{ t('purchasing.received') }}</option>
                    <option value="cancelled">{{ t('purchasing.cancelled') }}</option>
                </select>
                <Button v-if="search || statusFilter" variant="ghost" size="icon"
                    @click="search=''; statusFilter=''; applyFilters()">
                    <X :size="16" />
                </Button>
            </div>

            <!-- Table -->
            <div class="border rounded-xl overflow-x-auto">
                <table class="w-full border-collapse text-sm min-w-[640px]">
                    <thead class="bg-muted/50">
                        <tr class="[&>th]:align-middle">
                            <th class="px-4 py-3 text-start font-medium">{{ t('purchasing.poNumber') }}</th>
                            <th class="px-4 py-3 text-start font-medium">{{ t('purchasing.supplier') }}</th>
                            <th class="px-4 py-3 text-start font-medium hidden md:table-cell">{{ t('purchasing.orderDate') }}</th>
                            <th class="px-4 py-3 text-start font-medium hidden lg:table-cell">{{ t('purchasing.expected') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('common.status') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ t('common.total') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ t('purchasing.due') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ t('common.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="orders.data.length === 0">
                            <td colspan="8" class="text-muted-foreground py-12 text-center">
                                {{ t('purchasing.noPurchaseOrdersFound') }}
                            </td>
                        </tr>
                        <tr v-for="o in orders.data" :key="o.id" class="hover:bg-muted/30 transition-colors [&>td]:align-middle">
                            <td class="px-4 py-3 font-mono font-medium">{{ o.po_number }}</td>
                            <td class="px-4 py-3">{{ o.supplier?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs hidden md:table-cell">{{ o.order_date }}</td>
                            <td class="px-4 py-3 text-xs hidden lg:table-cell">
                                {{ o.expected_date ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="statusCls[o.status] ?? statusCls.draft"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium">
                                    {{ poStatusLabel(o.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end">{{ fmt(o.total) }}</td>
                            <td class="px-4 py-3 text-end">
                                <span :class="o.amount_due > 0 ? 'text-orange-500 font-medium' : 'text-muted-foreground'">
                                    {{ o.amount_due > 0 ? fmt(o.amount_due) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <Link :href="route('purchasing.orders.show', o.id)">
                                    <Button variant="ghost" size="sm" class="gap-1 rtl:flex-row-reverse">
                                        <Eye :size="14" /> {{ t('common.view') }}
                                    </Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="orders.last_page > 1" class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">
                    {{ t('returns.paginationSummary', { current: orders.current_page, last: orders.last_page, total: orders.total }) }}
                </span>
                <div class="flex gap-2">
                    <Button v-if="orders.current_page > 1" variant="outline" size="sm"
                        @click="paginationQuery(orders.current_page - 1)">
                        {{ t('common.previous') }}
                    </Button>
                    <Button v-if="orders.current_page < orders.last_page" variant="outline" size="sm"
                        @click="paginationQuery(orders.current_page + 1)">
                        {{ t('common.next') }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
