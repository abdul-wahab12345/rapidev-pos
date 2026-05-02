<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatDateTime, formatMoney } from '@/utils/format';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BadgeDollarSign,
    BarChart3,
    Landmark,
    Package,
    ReceiptText,
    RotateCcw,
    ScanLine,
    ShoppingBag,
    ShoppingCart,
    Store,
    Truck,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Stats {
    revenue_today: number;
    revenue_yesterday: number;
    revenue_week: number;
    revenue_month: number;
    sales_today: number;
    udhaar_outstanding: number;
    customers_udhaar: number;
    purchase_payable_due: number;
    purchase_orders_pending: number;
    stock_low_count: number;
    stock_out_count: number;
    expenses_month: number;
    returns_refund_month: number;
}

interface LowPeek {
    product_id: string;
    name: string;
    quantity: number;
    reorder_level: number;
}

interface RecentSaleRow {
    id: string;
    invoice_number: string;
    total: number;
    payment_method: string;
    customer: { id: string; name: string } | null;
    branch: { name: string } | null;
    created_at: string;
}

const props = defineProps<{
    stats: Stats;
    low_stock_peek: LowPeek[];
    recent_sales: RecentSaleRow[];
    business: { default_branch_name: string | null };
}>();

const { t } = useI18n();
const page = usePage();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('dashboard.pageTitle'), href: route('dashboard') },
]);

const userName = computed(() => page.props.auth?.user?.name ?? '');

const revenueDelta = computed(() => props.stats.revenue_today - props.stats.revenue_yesterday);

const revenueDeltaPct = computed(() => {
    const y = props.stats.revenue_yesterday;
    const d = revenueDelta.value;
    if (!y || y === 0) return null;
    return ((d / y) * 100).toFixed(1);
});

const deltaTrend = computed(() => {
    if (revenueDelta.value > 0) return 'up' as const;
    if (revenueDelta.value < 0) return 'down' as const;
    return 'flat' as const;
});

const fmt = formatMoney;

const quickLinks = computed(() => [
    { titleKey: 'dashboard.quick.products', href: route('inventory.products.index'), icon: Package },
    { titleKey: 'dashboard.quick.stock', href: route('inventory.stock.index'), icon: Truck },
    { titleKey: 'dashboard.quick.customers', href: route('customers.index'), icon: Users },
    { titleKey: 'dashboard.quick.purchasing', href: route('purchasing.orders.index'), icon: ShoppingCart },
    { titleKey: 'dashboard.quick.expenses', href: route('expenses.index'), icon: ReceiptText },
    { titleKey: 'dashboard.quick.accounts', href: route('accounts.index'), icon: Landmark },
]);

function stockLowHref(): string {
    return `${route('inventory.stock.index')}?stock=low`;
}

function stockOutHref(): string {
    return `${route('inventory.stock.index')}?stock=out`;
}

function udhaarCustomersHref(): string {
    return `${route('customers.index')}?balance=has_udhaar`;
}

function reorderLabel(level: number) {
    return `${t('inventory.reorderAt')}: ${level}`;
}
</script>

<template>
    <Head :title="t('dashboard.pageTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-8 p-4 sm:p-6 max-w-[1400px] mx-auto">
            <!-- Header + POS CTA -->
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2 max-w-2xl">
                    <p v-if="userName" class="text-sm font-medium text-muted-foreground truncate">
                        {{ userName }}
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                        {{ t('dashboard.pageTitle') }}
                    </h1>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        {{ t('dashboard.subtitle') }}
                    </p>
                    <p
                        v-if="business.default_branch_name"
                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/40 px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        <Store class="h-3.5 w-3.5 shrink-0" aria-hidden />
                        {{ t('dashboard.branchScope', { branch: business.default_branch_name }) }}
                    </p>
                </div>

                <div class="flex flex-col gap-1 lg:items-end lg:text-end shrink-0">
                    <Button as-child size="lg" class="gap-2 h-11 px-6 shadow-sm w-full sm:w-auto rtl:flex-row-reverse">
                        <Link :href="route('pos.cashier')">
                            <ScanLine class="h-5 w-5 shrink-0" aria-hidden />
                            {{ t('dashboard.openPOS') }}
                            <ArrowRight class="h-4 w-4 opacity-80 rtl:hidden" aria-hidden />
                            <ArrowRight class="h-4 w-4 opacity-80 hidden rtl:inline rotate-180" aria-hidden />
                        </Link>
                    </Button>
                    <span class="text-xs text-muted-foreground px-1">{{ t('dashboard.openPOSHelp') }}</span>
                </div>
            </div>

            <!-- Primary KPIs -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    :label="t('dashboard.revenueToday')"
                    :value="fmt(stats.revenue_today)"
                    tone="success"
                    :icon="Wallet"
                />

                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                                {{ t('dashboard.salesTodayCount') }}
                            </p>
                            <p class="mt-1 text-2xl font-bold tabular-nums text-foreground">{{ stats.sales_today }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t('dashboard.ordersTodayHint') }}
                            </p>
                            <div
                                class="mt-3 flex flex-wrap items-center gap-1 text-xs tabular-nums"
                                :class="[
                                    deltaTrend === 'up' ? 'text-emerald-600 dark:text-emerald-400' : '',
                                    deltaTrend === 'down' ? 'text-red-600 dark:text-red-400' : '',
                                    deltaTrend === 'flat' ? 'text-muted-foreground' : '',
                                ]"
                            >
                                <TrendingUp v-if="deltaTrend === 'up'" class="h-3.5 w-3.5 shrink-0" aria-hidden />
                                <TrendingDown v-else-if="deltaTrend === 'down'" class="h-3.5 w-3.5 shrink-0" aria-hidden />
                                <span v-else class="inline-block h-1.5 w-1.5 rounded-full bg-muted-foreground shrink-0" aria-hidden />
                                <span>{{ t('dashboard.vsYesterday', { amount: fmt(stats.revenue_yesterday) }) }}</span>
                                <span v-if="revenueDeltaPct !== null" class="ms-1">({{ revenueDelta > 0 ? '+' : '' }}{{ revenueDeltaPct }}%)</span>
                            </div>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <ShoppingBag class="h-4 w-4" aria-hidden />
                        </div>
                    </div>
                </div>

                <StatCard
                    :label="t('dashboard.revenueThisWeek')"
                    :value="fmt(stats.revenue_week)"
                    tone="info"
                    :icon="BarChart3"
                />
                <StatCard
                    :label="t('dashboard.revenueThisMonth')"
                    :value="fmt(stats.revenue_month)"
                    tone="default"
                    :icon="BadgeDollarSign"
                />
            </div>

            <!-- Secondary KPIs -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <StatCard
                    :label="t('dashboard.outstandingUdhaar')"
                    :value="fmt(stats.udhaar_outstanding)"
                    tone="danger"
                    :icon="ReceiptText"
                    :description="t('dashboard.customersOwingHint', { count: stats.customers_udhaar })"
                />
                <StatCard
                    :label="t('dashboard.supplierPayableDue')"
                    :value="fmt(stats.purchase_payable_due)"
                    tone="warning"
                    :icon="Truck"
                    :description="t('dashboard.pendingPOsHint', { count: stats.purchase_orders_pending })"
                />
                <StatCard
                    :label="t('dashboard.stockLow')"
                    :value="String(stats.stock_low_count)"
                    tone="warning"
                    :icon="AlertTriangle"
                />
                <StatCard
                    :label="t('dashboard.stockOut')"
                    :value="String(stats.stock_out_count)"
                    :tone="stats.stock_out_count > 0 ? 'danger' : 'default'"
                    :icon="Package"
                />
                <StatCard
                    :label="t('dashboard.expensesMonth')"
                    :value="fmt(stats.expenses_month)"
                    tone="default"
                    :icon="BadgeDollarSign"
                />
                <StatCard
                    :label="t('dashboard.refundsMonth')"
                    :value="fmt(stats.returns_refund_month)"
                    tone="info"
                    :icon="RotateCcw"
                />
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Recent sales -->
                <div class="lg:col-span-3 rounded-xl border border-border bg-card overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-border bg-muted/30 px-4 py-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <ReceiptText class="h-4 w-4 text-muted-foreground shrink-0" aria-hidden />
                            <h2 class="font-semibold text-sm text-foreground">{{ t('dashboard.sectionRecentSales') }}</h2>
                        </div>
                        <Link
                            :href="route('sales.index')"
                            class="text-xs font-medium text-primary hover:underline inline-flex items-center gap-1 rtl:flex-row-reverse"
                        >
                            {{ t('dashboard.viewSalesList') }}
                            <ArrowRight class="h-3 w-3 rtl:rotate-180" aria-hidden />
                        </Link>
                    </div>

                    <div class="divide-y divide-border">
                        <p v-if="recent_sales.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                            {{ t('dashboard.noSalesYet') }}
                        </p>
                        <Link
                            v-for="sale in recent_sales"
                            :key="sale.id"
                            :href="route('sales.show', sale.id)"
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-muted/40 transition-colors"
                        >
                            <div class="min-w-0 flex-1 basis-[12rem]">
                                <p class="font-mono text-xs font-semibold text-primary truncate">{{ sale.invoice_number }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">
                                    {{ sale.customer ? sale.customer.name : t('sales.walkInLabel') }}
                                    <template v-if="sale.branch?.name"><span class="tabular-nums"> · {{ sale.branch.name }}</span></template>
                                </p>
                            </div>
                            <span class="text-xs capitalize text-muted-foreground whitespace-nowrap">{{ sale.payment_method }}</span>
                            <span class="text-sm font-bold tabular-nums text-foreground sm:ms-auto">{{ fmt(sale.total) }}</span>
                            <span class="text-[11px] text-muted-foreground whitespace-nowrap w-full sm:w-auto sm:text-end tabular-nums">{{ formatDateTime(sale.created_at) }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Alerts + low stock -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-xl border border-border bg-card overflow-hidden shadow-sm">
                        <div class="flex items-center gap-2 border-b border-border bg-muted/30 px-4 py-3">
                            <AlertTriangle class="h-4 w-4 text-amber-500 shrink-0" aria-hidden />
                            <h2 class="font-semibold text-sm">{{ t('dashboard.sectionAttention') }}</h2>
                        </div>
                        <ul class="divide-y divide-border text-sm px-0">
                            <li v-if="stats.stock_out_count > 0">
                                <Link :href="stockOutHref()" class="flex items-center gap-3 px-4 py-3 hover:bg-muted/30 transition-colors">
                                    <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-semibold text-red-600 dark:text-red-400">{{ stats.stock_out_count }}</span>
                                    <span class="text-foreground flex-1 min-w-0">{{ t('dashboard.stockOut') }}</span>
                                    <ArrowRight class="h-3.5 w-3.5 text-muted-foreground rtl:rotate-180 shrink-0" aria-hidden />
                                </Link>
                            </li>
                            <li v-if="stats.stock_low_count > 0">
                                <Link :href="stockLowHref()" class="flex items-center gap-3 px-4 py-3 hover:bg-muted/30 transition-colors">
                                    <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400">{{ stats.stock_low_count }}</span>
                                    <span class="text-foreground flex-1 min-w-0">{{ t('dashboard.stockLow') }}</span>
                                    <ArrowRight class="h-3.5 w-3.5 text-muted-foreground rtl:rotate-180 shrink-0" aria-hidden />
                                </Link>
                            </li>
                            <li v-if="stats.purchase_payable_due > 0.01">
                                <Link :href="route('purchasing.orders.index')" class="flex items-center gap-3 px-4 py-3 hover:bg-muted/30 transition-colors">
                                    <span class="rounded-full bg-orange-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-orange-600">{{ fmt(stats.purchase_payable_due) }}</span>
                                    <span class="text-foreground flex-1 min-w-0 truncate">{{ t('dashboard.supplierPayableDue') }}</span>
                                    <ArrowRight class="h-3.5 w-3.5 text-muted-foreground rtl:rotate-180 shrink-0" aria-hidden />
                                </Link>
                            </li>
                            <li v-if="stats.udhaar_outstanding > 0.01">
                                <Link :href="udhaarCustomersHref()" class="flex items-center gap-3 px-4 py-3 hover:bg-muted/30 transition-colors">
                                    <span class="rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-red-600 dark:text-red-400">{{ fmt(stats.udhaar_outstanding) }}</span>
                                    <span class="text-foreground flex-1 min-w-0 truncate">{{ t('dashboard.outstandingUdhaar') }}</span>
                                    <ArrowRight class="h-3.5 w-3.5 text-muted-foreground rtl:rotate-180 shrink-0" aria-hidden />
                                </Link>
                            </li>
                            <li v-if="stats.stock_out_count === 0 && stats.stock_low_count === 0 && stats.purchase_payable_due <= 0.01 && stats.udhaar_outstanding <= 0.01">
                                <p class="py-10 px-4 text-center text-muted-foreground text-sm">{{ t('dashboard.noLowStockPeek') }}</p>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-border bg-card overflow-hidden shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-border bg-muted/30 px-4 py-3">
                            <h2 class="font-semibold text-sm">{{ t('dashboard.stockLowPeek') }}</h2>
                            <Link :href="stockLowHref()" class="text-xs font-medium text-primary hover:underline">
                                {{ t('dashboard.gotoStockFilter') }}
                            </Link>
                        </div>
                        <div class="divide-y divide-border">
                            <p v-if="low_stock_peek.length === 0" class="py-8 px-4 text-center text-sm text-muted-foreground">
                                {{ t('dashboard.noLowStockPeek') }}
                            </p>
                            <div
                                v-for="row in low_stock_peek"
                                :key="row.product_id"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-muted/20"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate text-foreground">{{ row.name }}</p>
                                    <p class="text-[11px] text-muted-foreground tabular-nums">
                                        {{ t('dashboard.unitLeft', { qty: row.quantity }) }} · {{ reorderLabel(row.reorder_level) }}
                                    </p>
                                </div>
                                <Link
                                    :href="route('inventory.products.edit', { product: row.product_id })"
                                    class="text-xs font-medium text-primary hover:underline shrink-0"
                                >
                                    {{ t('dashboard.editProduct') }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick actions -->
            <div>
                <h2 class="mb-3 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                    {{ t('dashboard.quickActionsTitle') }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <Link
                        v-for="item in quickLinks"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 hover:bg-muted/50 hover:border-muted-foreground/15 transition-colors group"
                    >
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                            <component :is="item.icon" class="h-5 w-5" aria-hidden />
                        </div>
                        <span class="text-sm font-medium text-foreground leading-tight text-start">{{ t(item.titleKey) }}</span>
                        <ArrowRight class="ms-auto h-4 w-4 text-muted-foreground rtl:rotate-180 opacity-70 group-hover:opacity-100 shrink-0" aria-hidden />
                    </Link>
                    <Link
                        :href="route('sales.index')"
                        class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 hover:bg-muted/50 hover:border-muted-foreground/15 transition-colors group"
                    >
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                            <ReceiptText class="h-5 w-5" aria-hidden />
                        </div>
                        <span class="text-sm font-medium text-foreground text-start">{{ t('nav.salesOrders') }}</span>
                        <ArrowRight class="ms-auto h-4 w-4 text-muted-foreground rtl:rotate-180 opacity-70 shrink-0" aria-hidden />
                    </Link>
                    <Link
                        :href="route('accounts.reports')"
                        class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 hover:bg-muted/50 hover:border-muted-foreground/15 transition-colors group xl:col-span-1"
                    >
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                            <BarChart3 class="h-5 w-5" aria-hidden />
                        </div>
                        <span class="text-sm font-medium text-foreground text-start">{{ t('nav.reports') }}</span>
                        <ArrowRight class="ms-auto h-4 w-4 text-muted-foreground rtl:rotate-180 opacity-70 shrink-0" aria-hidden />
                    </Link>
                    <Link
                        :href="route('returns.index')"
                        class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3 hover:bg-muted/50 hover:border-muted-foreground/15 transition-colors group"
                    >
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors shrink-0">
                            <RotateCcw class="h-5 w-5" aria-hidden />
                        </div>
                        <span class="text-sm font-medium text-foreground text-start">{{ t('nav.returnsRefunds') }}</span>
                        <ArrowRight class="ms-auto h-4 w-4 text-muted-foreground rtl:rotate-180 opacity-70 shrink-0" aria-hidden />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
