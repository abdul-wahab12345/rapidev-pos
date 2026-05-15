<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';
import { useReceipt } from '@/composables/useReceipt';
import { formatMoney, formatDateTime } from '@/utils/format';
import { paymentBadge, statusBadge } from '@/constants/badges';
import salesService from '@/services/salesService';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle, ArrowUpRight,
    Calendar, Printer, ReceiptText, Search, ShoppingBag, X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { confirm } = useConfirm();

interface SaleRow {
    id: string;
    invoice_number: string;
    status: 'completed' | 'voided' | 'pending';
    created_at: string;
    total: number;
    discount: number;
    payment_method: string;
    udhaar_amount: number;
    order_type: string | null;
    delivery_fee: number;
    dining_table: { id: string; name: string } | null;
    customer: { id: string; name: string; phone: string } | null;
    cashier: { id: number; name: string } | null;
    branch: { id: string; name: string } | null;
}

interface Stats {
    total_sales: number;
    total_revenue: number;
    total_discount: number;
    total_udhaar: number;
    today_count: number;
    today_revenue: number;
}

interface Pagination {
    data: SaleRow[];
    current_page: number;
    last_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    sales: Pagination;
    stats: Stats;
    filters: { search?: string; date_from?: string; date_to?: string; payment?: string; status?: string };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.salesOrders'), href: '/sales' },
    { title: t('sales.breadcrumbSub'), href: '/sales' },
]);

// ── Filters ──────────────────────────────────────────────────
const search    = ref(props.filters.search    ?? '');
const dateFrom  = ref(props.filters.date_from ?? '');
const dateTo    = ref(props.filters.date_to   ?? '');
const payment   = ref(props.filters.payment   ?? '');
const status    = ref(props.filters.status    ?? '');

let filterTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    router.get(route('sales.index'), {
        search:    search.value    || undefined,
        date_from: dateFrom.value  || undefined,
        date_to:   dateTo.value    || undefined,
        payment:   payment.value   || undefined,
        status:    status.value    || undefined,
    }, { preserveScroll: true, replace: true });
}

watch([search], () => {
    if (filterTimer) clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilters, 400);
});

watch([dateFrom, dateTo, payment, status], applyFilters);

function clearFilters() {
    search.value = ''; dateFrom.value = ''; dateTo.value = '';
    payment.value = ''; status.value = '';
    applyFilters();
}

const hasFilters = () => !!(search.value || dateFrom.value || dateTo.value || payment.value || status.value);

// ── Void ─────────────────────────────────────────────────────
async function voidSale(sale: SaleRow) {
    const ok = await confirm({
        title: t('sales.voidConfirmTitle', { invoice: sale.invoice_number }),
        message: t('sales.voidConfirmMessage'),
        confirmLabel: t('sales.voidConfirmYes'),
        variant: 'danger',
    });
    if (!ok) return;
    router.post(route('sales.void', { sale: sale.id }), {}, { preserveScroll: true });
}

// ── Print receipt from list ───────────────────────────────────
const { printReceipt } = useReceipt();
const printingId = ref<string | null>(null);

async function printSale(sale: SaleRow) {
    printingId.value = sale.id;
    try {
        const receiptData = await salesService.getReceiptData(sale.id);
        printReceipt(receiptData);
    } finally {
        printingId.value = null;
    }
}

// ── Helpers ───────────────────────────────────────────────────
const fmt = formatMoney;
const fmtDate = (dt: string) => formatDateTime(dt);

const paymentMethodLabels: Record<string,
    | 'common.cash'
    | 'common.jazzcash'
    | 'common.easypaisa'
    | 'common.udhaar'
    | 'sales.split'> = {
    cash: 'common.cash',
    jazzcash: 'common.jazzcash',
    easypaisa: 'common.easypaisa',
    udhaar: 'common.udhaar',
    mixed: 'sales.split',
};

function paymentMethodLabel(method: string) {
    const key = paymentMethodLabels[method?.toLowerCase?.() ?? ''];
    return key ? t(key) : method;
}

function saleStatusLabel(st: SaleRow['status']) {
    if (st === 'completed')          return t('common.completed');
    if (st === 'voided')             return t('sales.voided');
    if (st === 'partially_returned') return t('sales.partiallyReturned');
    if (st === 'returned')           return t('sales.returned');
    if (st === 'pending')            return t('sales.pendingSale');
    return st;
}
</script>

<template>
    <Head :title="t('sales.pageTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Page header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('sales.pageTitle') }}</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ t('sales.pageDescription') }}</p>
                </div>
                <Link
                    :href="route('pos.cashier')"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    <ShoppingBag class="h-4 w-4" />
                    {{ t('sales.newSale') }}
                </Link>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">{{ t('sales.todaysSales') }}</p>
                    <p class="mt-1 text-2xl font-black text-foreground">{{ stats.today_count }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">{{ t('sales.todaysRevenue') }}</p>
                    <p class="mt-1 text-lg font-black text-green-600 dark:text-green-400">{{ fmt(stats.today_revenue) }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">{{ t('sales.filteredSales') }}</p>
                    <p class="mt-1 text-2xl font-black text-foreground">{{ stats.total_sales }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">{{ t('sales.filteredRevenue') }}</p>
                    <p class="mt-1 text-lg font-black text-foreground">{{ fmt(stats.total_revenue) }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">{{ t('sales.discountsGiven') }}</p>
                    <p class="mt-1 text-lg font-black text-amber-600 dark:text-amber-400">{{ fmt(stats.total_discount) }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">{{ t('sales.udhaarPending') }}</p>
                    <p class="mt-1 text-lg font-black text-red-600 dark:text-red-400">{{ fmt(stats.total_udhaar) }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-2">
                <!-- Search -->
                <div class="relative min-w-[200px] flex-1">
                    <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('sales.searchPlaceholder')"
                        class="w-full rounded-lg border border-input bg-background py-2 ps-9 pe-4 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Date from -->
                <div class="relative">
                    <Calendar class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="rounded-lg border border-input bg-background py-2 ps-9 pe-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Date to -->
                <input
                    v-model="dateTo"
                    type="date"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                />

                <!-- Payment method -->
                <select v-model="payment" class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">{{ t('sales.allPayments') }}</option>
                    <option value="cash">{{ t('common.cash') }}</option>
                    <option value="jazzcash">{{ t('common.jazzcash') }}</option>
                    <option value="easypaisa">{{ t('common.easypaisa') }}</option>
                    <option value="udhaar">{{ t('common.udhaar') }}</option>
                    <option value="mixed">{{ t('sales.split') }}</option>
                </select>

                <!-- Status -->
                <select v-model="status" class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">{{ t('sales.allStatus') }}</option>
                    <option value="completed">{{ t('common.completed') }}</option>
                    <option value="voided">{{ t('sales.voided') }}</option>
                </select>

                <!-- Clear -->
                <button
                    v-if="hasFilters()"
                    @click="clearFilters"
                    class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors"
                >
                    <X class="h-3.5 w-3.5" /> {{ t('common.clear') }}
                </button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-muted/50">
                        <tr class="[&>th]:align-middle text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3">{{ t('sales.invoice') }}</th>
                            <th class="px-4 py-3">{{ t('common.dateTime') }}</th>
                            <th class="px-4 py-3">{{ t('pos.customerWord') }}</th>
                            <th class="px-4 py-3">{{ t('sales.cashier') }}</th>
                            <th class="px-4 py-3">{{ t('common.payment') }}</th>
                            <th class="px-4 py-3 text-end">{{ t('common.total') }}</th>
                            <th class="px-4 py-3 text-start">{{ t('common.status') }}</th>
                            <th class="px-4 py-3 text-end w-48 lg:w-[22rem]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <!-- Empty -->
                        <tr v-if="sales.data.length === 0">
                            <td colspan="8" class="px-4 py-16 text-center">
                                <ReceiptText class="mx-auto mb-3 h-10 w-10 text-muted-foreground/30" />
                                <p class="text-sm text-muted-foreground">{{ t('sales.noSalesFound') }}</p>
                                <p class="mt-1 text-xs text-muted-foreground/60">{{ t('sales.noSalesHint') }}</p>
                            </td>
                        </tr>

                        <tr
                            v-for="sale in sales.data"
                            :key="sale.id"
                            :class="sale.status === 'voided' ? 'opacity-50' : 'hover:bg-muted/30'"
                            class="[&>td]:align-middle transition-colors"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('sales.show', { sale: sale.id })" class="font-mono text-xs font-semibold text-primary hover:underline">
                                    {{ sale.invoice_number }}
                                </Link>
                            </td>

                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ fmtDate(sale.created_at) }}
                            </td>

                            <td class="px-4 py-3">
                                <span v-if="sale.customer" class="text-sm font-medium text-foreground">{{ sale.customer.name }}</span>
                                <span v-else class="text-xs text-muted-foreground">{{ t('sales.walkInLabel') }}</span>
                                <div v-if="sale.order_type" class="mt-0.5 flex items-center gap-1">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-1.5 py-0 text-[10px] font-medium',
                                        sale.order_type === 'dine_in'
                                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                            : sale.order_type === 'delivery'
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                    ]">
                                        {{ sale.order_type === 'dine_in' ? t('pos.dineIn') : sale.order_type === 'delivery' ? t('pos.delivery') : t('pos.takeaway') }}
                                    </span>
                                    <span v-if="sale.dining_table" class="text-[10px] text-muted-foreground">
                                        · {{ sale.dining_table.name }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm text-muted-foreground">
                                {{ sale.cashier?.name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    :class="paymentBadge[sale.payment_method] ?? 'bg-muted text-muted-foreground'"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize"
                                >
                                    {{ paymentMethodLabel(sale.payment_method) }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-end font-semibold text-foreground">
                                {{ fmt(sale.total) }}
                                <p v-if="sale.udhaar_amount > 0" class="text-[11px] font-normal text-amber-600 dark:text-amber-400">
                                    {{ t('sales.udhaarLine', { amount: fmt(sale.udhaar_amount) }) }}
                                </p>
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    :class="statusBadge[sale.status] ?? 'bg-muted text-muted-foreground'"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                >
                                    {{ saleStatusLabel(sale.status) }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-end align-middle">
                                <div class="inline-flex justify-end rtl:flex-row-reverse">
                                    <!-- Print -->
                                    <button
                                        @click="printSale(sale)"
                                        :disabled="printingId !== null"
                                        class="flex items-center gap-1 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors disabled:opacity-40"
                                        :title="t('sales.printReceiptTitle')"
                                    >
                                        <Printer class="h-3 w-3" :class="printingId === sale.id ? 'animate-pulse' : ''" />
                                        {{ printingId === sale.id ? t('common.saving') : t('common.print') }}
                                    </button>

                                    <!-- View -->
                                    <Link
                                        :href="route('sales.show', { sale: sale.id })"
                                        class="flex items-center gap-1 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors"
                                    >
                                        <ArrowUpRight class="h-3 w-3" />
                                        {{ t('sales.viewSale') }}
                                    </Link>

                                    <!-- Void -->
                                    <button
                                        v-if="sale.status === 'completed'"
                                        @click="voidSale(sale)"
                                        class="flex items-center gap-1 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-destructive/50 hover:text-destructive transition-colors"
                                    >
                                        <AlertTriangle class="h-3 w-3" />
                                        {{ t('sales.voidSale') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="sales.last_page > 1" class="flex items-center justify-between text-sm text-muted-foreground">
                <p>{{ t('sales.paginationSalesTotal', { count: sales.total }) }}</p>
                <div class="flex gap-1">
                    <template v-for="link in sales.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="[
                                'rounded-md border px-3 py-1.5 text-xs transition-colors',
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border hover:bg-accent',
                            ]"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded-md border border-border px-3 py-1.5 text-xs opacity-40"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
