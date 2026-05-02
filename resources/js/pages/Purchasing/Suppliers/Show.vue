<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft, Building2, ExternalLink,
    Mail, MapPin, Phone, ShoppingCart, TrendingDown, TrendingUp, User,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface SupplierData {
    id: string;
    name: string;
    company: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    ntn: string | null;
    payment_terms: number;
    current_balance: number;
    is_active: boolean;
    notes: string | null;
    party_id: string | null;
}

interface CustomerLink {
    id: string;
    current_balance: number;
}

interface PoRow {
    id: string;
    po_number: string;
    order_date: string;
    status: string;
    total: number;
    paid_amount: number;
    amount_due: number;
}

const props = defineProps<{
    supplier: SupplierData;
    customer_link: CustomerLink | null;
    net_payable: number;
    is_also_customer: boolean;
    purchase_orders: PoRow[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.purchasing'), href: route('purchasing.orders.index') },
    { title: t('purchasing.suppliersTitle'), href: route('purchasing.suppliers.index') },
    { title: props.supplier.name, href: '#' },
]);

const fmt = formatMoney;
const fmtDate = (d: string) => {
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(d).toLocaleDateString(loc, { day: '2-digit', month: 'short', year: 'numeric' });
};

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

const totalOrders = props.purchase_orders.length;
const totalSpend = props.purchase_orders.reduce((s, o) => s + o.total, 0);
const totalDue = props.purchase_orders.reduce((s, o) => s + o.amount_due, 0);
const ordersDueLine = computed(() =>
    t('purchasing.ordersDueLine', { count: totalOrders, due: fmt(totalDue) }),
);
</script>

<template>
    <Head :title="supplier.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl">

            <!-- Header -->
            <div class="flex flex-wrap items-start gap-3">
                <Link :href="route('purchasing.suppliers.index')" class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mt-1 rtl:flex-row-reverse">
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" /> {{ t('common.back') }}
                </Link>

                <div class="flex-1">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ supplier.name }}</h1>
                        <span v-if="supplier.company" class="text-sm text-muted-foreground">{{ supplier.company }}</span>
                        <span
                            :class="supplier.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground'"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >{{ supplier.is_active ? t('common.active') : t('common.inactive') }}</span>
                        <span v-if="is_also_customer" class="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400">
                            {{ t('purchasing.alsoCustomer') }}
                        </span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                        <span v-if="supplier.phone" class="flex items-center gap-1 rtl:flex-row-reverse"><Phone class="h-3.5 w-3.5 shrink-0" />{{ supplier.phone }}</span>
                        <span v-if="supplier.email" class="flex items-center gap-1 rtl:flex-row-reverse"><Mail class="h-3.5 w-3.5 shrink-0" />{{ supplier.email }}</span>
                        <span v-if="supplier.city" class="flex items-center gap-1 rtl:flex-row-reverse"><MapPin class="h-3.5 w-3.5 shrink-0" />{{ supplier.city }}</span>
                        <span v-if="supplier.payment_terms">{{ t('purchasing.termDaysSuffix', { days: supplier.payment_terms }) }}</span>
                    </div>
                </div>

                <div class="flex gap-2 flex-wrap">
                    <Link :href="route('purchasing.orders.create', { supplier: supplier.id })">
                        <Button variant="outline" class="gap-2 rtl:flex-row-reverse"><ShoppingCart class="h-4 w-4 shrink-0" /> {{ t('purchasing.newPo') }}</Button>
                    </Link>
                    <Link v-if="supplier.party_id && is_also_customer" :href="route('parties.show', supplier.party_id)">
                        <Button variant="outline" class="gap-2 rtl:flex-row-reverse"><ExternalLink class="h-4 w-4 shrink-0" /> {{ t('purchasing.netBalanceLink') }}</Button>
                    </Link>
                </div>
            </div>

            <!-- Balance cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-xl border p-4"
                     :class="supplier.current_balance > 0 ? 'border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1 rtl:flex-row-reverse">
                        <TrendingDown class="h-4 w-4 text-amber-500 shrink-0" />
                        <p class="text-xs font-medium text-muted-foreground">{{ t('purchasing.youOwe') }}</p>
                    </div>
                    <p class="text-xl font-black" :class="supplier.current_balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                        {{ supplier.current_balance > 0 ? fmt(supplier.current_balance) : t('customers.balanceClearLabel') }}
                    </p>
                </div>

                <div v-if="is_also_customer" class="rounded-xl border p-4"
                     :class="customer_link && customer_link.current_balance > 0 ? 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1 rtl:flex-row-reverse">
                        <TrendingUp class="h-4 w-4 text-red-500 shrink-0" />
                        <p class="text-xs font-medium text-muted-foreground">{{ t('purchasing.theyOwe') }}</p>
                    </div>
                    <p class="text-xl font-black" :class="customer_link && customer_link.current_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                        {{ customer_link && customer_link.current_balance > 0 ? fmt(customer_link.current_balance) : t('customers.balanceClearLabel') }}
                    </p>
                </div>

                <div class="rounded-xl border p-4"
                     :class="net_payable > 0 ? 'border-orange-200 bg-orange-50 dark:border-orange-800/50 dark:bg-orange-950/20' : 'border-border bg-card'">
                    <p class="text-xs font-medium text-muted-foreground mb-1">{{ t('purchasing.netPayable') }}</p>
                    <p class="text-xl font-black" :class="net_payable > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ net_payable > 0 ? fmt(net_payable) : t('customers.balanceClearLabel') }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('purchasing.apMinusArHint') }}</p>
                </div>

                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground mb-1">{{ t('purchasing.totalPurchased') }}</p>
                    <p class="text-xl font-black text-foreground">{{ fmt(totalSpend) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('purchasing.ordersCountMini', { count: totalOrders }) }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <div class="lg:col-span-2">
                    <div class="mb-3 flex items-center gap-2 flex-wrap">
                        <ShoppingCart class="h-4 w-4 text-muted-foreground shrink-0" />
                        <h2 class="font-semibold text-foreground">{{ t('purchasing.poHistory') }}</h2>
                        <span class="ms-auto text-xs text-muted-foreground">{{ ordersDueLine }}</span>
                    </div>

                    <div class="rounded-xl border border-border overflow-x-auto">
                        <table class="w-full border-collapse text-sm min-w-[640px]">
                            <thead class="bg-muted/50">
                                <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                                    <th class="px-4 py-2.5">{{ t('purchasing.poNumber') }}</th>
                                    <th class="px-4 py-2.5">{{ t('common.date') }}</th>
                                    <th class="px-4 py-2.5">{{ t('common.status') }}</th>
                                    <th class="px-4 py-2.5 text-end">{{ t('common.total') }}</th>
                                    <th class="px-4 py-2.5 text-end">{{ t('purchasing.due') }}</th>
                                    <th class="px-4 py-2.5 w-24"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="purchase_orders.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-muted-foreground">{{ t('purchasing.noPurchaseOrdersYet') }}</td>
                                </tr>
                                <tr v-for="po in purchase_orders" :key="po.id" class="hover:bg-muted/20 [&>td]:align-middle">
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('purchasing.orders.show', po.id)" class="font-mono text-xs font-semibold text-primary hover:underline">
                                            {{ po.po_number }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ fmtDate(po.order_date) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span :class="statusCls[po.status] ?? 'bg-muted text-muted-foreground'" class="rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                            {{ poStatusLabel(po.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-end text-sm font-semibold text-foreground">{{ fmt(po.total) }}</td>
                                    <td class="px-4 py-2.5 text-end text-sm font-semibold"
                                        :class="po.amount_due > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                                        {{ po.amount_due > 0 ? fmt(po.amount_due) : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-end">
                                        <Link :href="route('purchasing.orders.show', po.id)" class="text-xs text-primary hover:underline">
                                            {{ t('common.view') }} →
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-4">

                    <div class="rounded-xl border border-border bg-card p-4 space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2 rtl:flex-row-reverse">
                            <Building2 class="h-3.5 w-3.5 shrink-0" /> {{ t('purchasing.supplierDetails') }}
                        </h3>
                        <div class="space-y-1.5 text-sm">
                            <div v-if="supplier.ntn" class="flex justify-between gap-2">
                                <span class="text-muted-foreground">{{ t('purchasing.ntn') }}</span>
                                <span class="font-mono text-xs">{{ supplier.ntn }}</span>
                            </div>
                            <div v-if="supplier.address" class="flex justify-between gap-2">
                                <span class="text-muted-foreground shrink-0">{{ t('common.address') }}</span>
                                <span class="text-xs text-end">{{ supplier.address }}</span>
                            </div>
                            <div v-if="supplier.payment_terms" class="flex justify-between gap-2">
                                <span class="text-muted-foreground">{{ t('purchasing.terms') }}</span>
                                <span>{{ t('purchasing.daysCount', { n: supplier.payment_terms }) }}</span>
                            </div>
                        </div>
                        <div v-if="supplier.notes" class="border-t pt-2 text-xs text-muted-foreground">{{ supplier.notes }}</div>
                    </div>

                    <div v-if="is_also_customer && customer_link" class="rounded-xl border border-blue-200 bg-blue-50 dark:border-blue-800/50 dark:bg-blue-950/20 p-4 space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300 flex items-center gap-2 rtl:flex-row-reverse">
                            <User class="h-3.5 w-3.5 shrink-0" /> {{ t('purchasing.alsoCustomer') }}
                        </h3>
                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between gap-2">
                                <span class="text-muted-foreground">{{ t('purchasing.arBalance') }}</span>
                                <span :class="customer_link.current_balance > 0 ? 'text-red-600 font-semibold' : 'text-muted-foreground'">
                                    {{ customer_link.current_balance > 0 ? fmt(customer_link.current_balance) : t('customers.balanceClearLabel') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2 flex-wrap">
                                <span class="text-muted-foreground">{{ t('common.netPosition') }}</span>
                                <span :class="net_payable > 0 ? 'text-orange-600 font-bold' : 'text-emerald-600 font-bold'">
                                    {{ fmt(net_payable) }}
                                    <span class="text-xs font-normal text-muted-foreground">
                                        {{ net_payable > 0 ? ` (${t('purchasing.netHintYouOwe')})` : ` (${t('purchasing.netHintClear')})` }}
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1 flex-wrap">
                            <Link :href="route('customers.show', customer_link.id)" class="flex-1 min-w-[8rem]">
                                <Button variant="outline" size="sm" class="w-full text-xs gap-1 rtl:flex-row-reverse">
                                    <User class="h-3 w-3 shrink-0" /> {{ t('customers.customerProfile') }}
                                </Button>
                            </Link>
                            <Link v-if="supplier.party_id" :href="route('parties.show', supplier.party_id)" class="flex-1 min-w-[8rem]">
                                <Button variant="outline" size="sm" class="w-full text-xs gap-1 rtl:flex-row-reverse">
                                    <ExternalLink class="h-3 w-3 shrink-0" /> {{ t('purchasing.netBalanceLink') }}
                                </Button>
                            </Link>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
