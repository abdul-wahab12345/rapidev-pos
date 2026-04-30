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
import { ref, watch } from 'vue';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales' },
    { title: 'All Sales', href: '/sales' },
];

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
        title: `Void ${sale.invoice_number}?`,
        message: 'This will restore stock for all items and reverse any udhaar balance. This cannot be undone.',
        confirmLabel: 'Yes, void sale',
        cancelLabel: 'Cancel',
        variant: 'danger',
    });
    if (!ok) return;
    router.post(route('sales.void', sale.id), {}, { preserveScroll: true });
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
</script>

<template>
    <Head title="Sales" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Page header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Sales</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">All transactions across your store</p>
                </div>
                <Link
                    :href="route('pos.cashier')"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    <ShoppingBag class="h-4 w-4" />
                    New Sale
                </Link>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Today's Sales</p>
                    <p class="mt-1 text-2xl font-black text-foreground">{{ stats.today_count }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Today's Revenue</p>
                    <p class="mt-1 text-lg font-black text-green-600 dark:text-green-400">{{ fmt(stats.today_revenue) }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Filtered Sales</p>
                    <p class="mt-1 text-2xl font-black text-foreground">{{ stats.total_sales }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Filtered Revenue</p>
                    <p class="mt-1 text-lg font-black text-foreground">{{ fmt(stats.total_revenue) }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Discounts Given</p>
                    <p class="mt-1 text-lg font-black text-amber-600 dark:text-amber-400">{{ fmt(stats.total_discount) }}</p>
                </div>
                <div class="col-span-1 rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Udhaar Pending</p>
                    <p class="mt-1 text-lg font-black text-red-600 dark:text-red-400">{{ fmt(stats.total_udhaar) }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-2">
                <!-- Search -->
                <div class="relative min-w-[200px] flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Invoice # or customer…"
                        class="w-full rounded-lg border border-input bg-background py-2 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Date from -->
                <div class="relative">
                    <Calendar class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="rounded-lg border border-input bg-background py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
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
                    <option value="">All Payments</option>
                    <option value="cash">Cash</option>
                    <option value="jazzcash">JazzCash</option>
                    <option value="easypaisa">Easypaisa</option>
                    <option value="udhaar">Udhaar</option>
                    <option value="mixed">Split</option>
                </select>

                <!-- Status -->
                <select v-model="status" class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="voided">Voided</option>
                </select>

                <!-- Clear -->
                <button
                    v-if="hasFilters()"
                    @click="clearFilters"
                    class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors"
                >
                    <X class="h-3.5 w-3.5" /> Clear
                </button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-border overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Date & Time</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Cashier</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <!-- Empty -->
                        <tr v-if="sales.data.length === 0">
                            <td colspan="8" class="px-4 py-16 text-center">
                                <ReceiptText class="mx-auto mb-3 h-10 w-10 text-muted-foreground/30" />
                                <p class="text-sm text-muted-foreground">No sales found</p>
                                <p class="mt-1 text-xs text-muted-foreground/60">Try adjusting filters or make a sale from the POS</p>
                            </td>
                        </tr>

                        <tr
                            v-for="sale in sales.data"
                            :key="sale.id"
                            :class="sale.status === 'voided' ? 'opacity-50' : 'hover:bg-muted/30'"
                            class="transition-colors"
                        >
                            <td class="px-4 py-3">
                                <Link :href="route('sales.show', sale.id)" class="font-mono text-xs font-semibold text-primary hover:underline">
                                    {{ sale.invoice_number }}
                                </Link>
                            </td>

                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ fmtDate(sale.created_at) }}
                            </td>

                            <td class="px-4 py-3">
                                <span v-if="sale.customer" class="text-sm font-medium text-foreground">{{ sale.customer.name }}</span>
                                <span v-else class="text-xs text-muted-foreground">Walk-in</span>
                            </td>

                            <td class="px-4 py-3 text-sm text-muted-foreground">
                                {{ sale.cashier?.name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    :class="paymentBadge[sale.payment_method] ?? 'bg-muted text-muted-foreground'"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize"
                                >
                                    {{ sale.payment_method }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right font-semibold text-foreground">
                                {{ fmt(sale.total) }}
                                <p v-if="sale.udhaar_amount > 0" class="text-[11px] font-normal text-amber-600 dark:text-amber-400">
                                    Udhaar: {{ fmt(sale.udhaar_amount) }}
                                </p>
                            </td>

                            <td class="px-4 py-3">
                                <span
                                    :class="statusBadge[sale.status] ?? 'bg-muted text-muted-foreground'"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize"
                                >
                                    {{ sale.status }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Print -->
                                    <button
                                        @click="printSale(sale)"
                                        :disabled="printingId === sale.id"
                                        class="flex items-center gap-1 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors disabled:opacity-40"
                                        title="Print receipt"
                                    >
                                        <Printer class="h-3 w-3" :class="printingId === sale.id ? 'animate-pulse' : ''" />
                                        {{ printingId === sale.id ? '…' : 'Print' }}
                                    </button>

                                    <!-- View -->
                                    <Link
                                        :href="route('sales.show', sale.id)"
                                        class="flex items-center gap-1 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors"
                                    >
                                        <ArrowUpRight class="h-3 w-3" />
                                        View
                                    </Link>

                                    <!-- Void -->
                                    <button
                                        v-if="sale.status === 'completed'"
                                        @click="voidSale(sale)"
                                        class="flex items-center gap-1 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground hover:border-destructive/50 hover:text-destructive transition-colors"
                                    >
                                        <AlertTriangle class="h-3 w-3" />
                                        Void
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="sales.last_page > 1" class="flex items-center justify-between text-sm text-muted-foreground">
                <p>{{ sales.total }} sales total</p>
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
