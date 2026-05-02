<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, formatDateTime } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, ShoppingCart, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface LedgerEntry {
    id: string;
    type: string;
    amount: number;
    running_balance: number;
    description: string | null;
    created_at: string;
}

interface PurchaseOrderRow {
    id: string;
    order_number: string;
    total: number;
    status: string;
    created_at: string;
}

interface Party {
    id: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    is_customer: boolean;
    is_supplier: boolean;
}

interface ReceivableSide {
    customer_id: string;
    current_balance: number;
    total_spend: number;
    ledger: LedgerEntry[];
}

interface PayableSide {
    supplier_id: string;
    current_balance: number;
    purchase_orders: PurchaseOrderRow[];
}

const props = defineProps<{
    party: Party;
    receivable: ReceivableSide | null;
    payable: PayableSide | null;
    net_balance: number;
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [];
    if (props.receivable) {
        items.push({ title: t('customers.pageTitle'), href: route('customers.index') });
        items.push({
            title: props.party.name,
            href: route('customers.show', props.receivable.customer_id),
        });
    } else if (props.payable) {
        items.push({ title: t('purchasing.suppliersTitle'), href: route('purchasing.suppliers.index') });
        items.push({
            title: props.party.name,
            href: route('purchasing.suppliers.show', props.payable.supplier_id),
        });
    }
    items.push({ title: t('parties.netBalanceCrumb'), href: '#' });
    return items;
});

const pageTitle = computed(() => `${props.party.name} — ${t('parties.pageTitleSuffix')}`);

const backHref = computed(() => {
    if (props.receivable) return route('customers.show', props.receivable.customer_id);
    if (props.payable) return route('purchasing.suppliers.show', props.payable.supplier_id);
    return null;
});

const fmt = formatMoney;
const fmtDate = formatDateTime;

function poStatusLabel(code: string): string {
    const keys: Record<string, string> = {
        pending: 'purchasing.pending',
        received: 'purchasing.received',
        partial: 'purchasing.partial',
        cancelled: 'purchasing.cancelled',
        ordered: 'purchasing.ordered',
    };
    const k = keys[code];
    return k ? t(k) : code;
}

const statusClass: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    ordered:   'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    received:  'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    partial:   'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    cancelled: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-7xl xl:max-w-[90rem]">

            <!-- Header -->
            <div class="flex flex-wrap items-start gap-3">
                <Link
                    v-if="backHref"
                    :href="backHref"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mt-1"
                >
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" /> {{ t('common.back') }}
                </Link>

                <div class="flex-1">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ party.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('parties.subtitleUnified') }}</p>
                </div>
            </div>

            <!-- Net balance hero -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <!-- AR -->
                <div class="rounded-xl border p-4"
                     :class="receivable && receivable.current_balance > 0 ? 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingUp class="h-4 w-4 text-red-500" />
                        <p class="text-xs font-medium text-muted-foreground">{{ t('parties.receivable') }}</p>
                    </div>
                    <p class="text-2xl font-black tabular-nums" :class="receivable && receivable.current_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                        {{ receivable ? (receivable.current_balance > 0 ? fmt(receivable.current_balance) : t('parties.clearBalance')) : t('parties.noValueEmDash') }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('parties.customerOwesYou') }}</p>
                </div>

                <!-- AP -->
                <div class="rounded-xl border p-4"
                     :class="payable && payable.current_balance > 0 ? 'border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingDown class="h-4 w-4 text-amber-500" />
                        <p class="text-xs font-medium text-muted-foreground">{{ t('parties.payable') }}</p>
                    </div>
                    <p class="text-2xl font-black tabular-nums" :class="payable && payable.current_balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                        {{ payable ? (payable.current_balance > 0 ? fmt(payable.current_balance) : t('parties.clearBalance')) : t('parties.noValueEmDash') }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('parties.youOweSupplier') }}</p>
                </div>

                <!-- Net -->
                <div class="rounded-xl border p-4"
                     :class="net_balance >= 0 ? 'border-green-200 bg-green-50 dark:border-green-800/50 dark:bg-green-950/20' : 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-950/20'">
                    <p class="text-xs font-medium text-muted-foreground mb-1">{{ t('common.netPosition') }}</p>
                    <p class="text-2xl font-black tabular-nums" :class="net_balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        {{ fmt(Math.abs(net_balance)) }}
                        <span class="text-sm font-normal ms-1">{{ net_balance >= 0 ? t('parties.inYourFavour') : t('parties.youOweNet') }}</span>
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('parties.arMinusApNote') }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">

                <!-- AR side: ledger -->
                <div v-if="receivable" class="min-w-0">
                    <div class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-foreground">{{ t('parties.arLedger') }}</h2>
                        <span class="ms-auto shrink-0 max-w-[12rem] text-end text-xs leading-tight text-muted-foreground">{{ t('parties.account1030Badge') }}</span>
                    </div>
                    <div class="rounded-xl border border-border overflow-x-auto">
                        <table class="w-full table-fixed border-collapse text-sm">
                            <colgroup>
                                <col class="w-[23%]" />
                                <col class="w-[50%]" />
                                <col class="w-[27%]" />
                            </colgroup>
                            <thead class="bg-muted/50">
                                <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                                    <th class="px-3 py-2.5">{{ t('common.date') }}</th>
                                    <th class="px-3 py-2.5 min-w-0">{{ t('common.description') }}</th>
                                    <th class="px-3 py-2.5 text-end">{{ t('common.balance') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="receivable.ledger.length === 0">
                                    <td colspan="3" class="px-3 py-8 text-center text-xs text-muted-foreground">{{ t('parties.emptyLedger') }}</td>
                                </tr>
                                <tr v-for="e in receivable.ledger" :key="e.id" class="hover:bg-muted/20 [&>td]:align-middle">
                                    <td class="px-3 py-2 align-top text-xs text-muted-foreground whitespace-nowrap">{{ fmtDate(e.created_at) }}</td>
                                    <td class="min-w-0 max-w-0 px-3 py-2 align-top text-xs text-muted-foreground break-words">{{ e.description || e.type }}</td>
                                    <td class="px-3 py-2 align-top text-end text-sm font-bold tabular-nums text-foreground whitespace-nowrap">{{ fmt(e.running_balance) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <Link :href="route('customers.show', receivable.customer_id)" class="text-xs text-primary hover:underline">
                            {{ t('parties.viewFullLedger') }} →
                        </Link>
                    </div>
                </div>

                <!-- AP side: purchase orders -->
                <div v-if="payable" class="min-w-0">
                    <div class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-foreground">{{ t('common.purchaseOrders') }}</h2>
                        <span class="ms-auto shrink-0 max-w-[12rem] text-end text-xs leading-tight text-muted-foreground">{{ t('parties.account2010Badge') }}</span>
                    </div>
                    <div class="rounded-xl border border-border overflow-x-auto">
                        <table class="w-full table-fixed border-collapse text-sm">
                            <colgroup>
                                <col class="w-[22%]" />
                                <col class="w-[32%]" />
                                <col class="w-[20%]" />
                                <col class="w-[26%]" />
                            </colgroup>
                            <thead class="bg-muted/50">
                                <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                                    <th class="px-3 py-2.5">{{ t('common.date') }}</th>
                                    <th class="px-3 py-2.5 min-w-0">{{ t('parties.colOrderHash') }}</th>
                                    <th class="px-3 py-2.5">{{ t('common.status') }}</th>
                                    <th class="px-3 py-2.5 text-end">{{ t('common.total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="payable.purchase_orders.length === 0">
                                    <td colspan="4" class="px-3 py-8 text-center text-xs text-muted-foreground">{{ t('parties.emptyPurchaseOrders') }}</td>
                                </tr>
                                <tr v-for="po in payable.purchase_orders" :key="po.id" class="hover:bg-muted/20 [&>td]:align-middle">
                                    <td class="px-3 py-2 align-top text-xs text-muted-foreground whitespace-nowrap">{{ fmtDate(po.created_at) }}</td>
                                    <td class="min-w-0 max-w-0 px-3 py-2 align-top">
                                        <Link :href="route('purchasing.orders.show', po.id)" class="font-mono text-xs font-semibold text-primary hover:underline break-all">
                                            {{ po.order_number }}
                                        </Link>
                                    </td>
                                    <td class="px-3 py-2 align-top whitespace-nowrap">
                                        <span :class="statusClass[po.status] ?? 'bg-muted text-muted-foreground'" class="inline-block max-w-full truncate rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                            {{ poStatusLabel(po.status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 align-top text-end text-sm font-bold tabular-nums text-foreground whitespace-nowrap">{{ fmt(po.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <Link :href="route('purchasing.suppliers.index')" class="text-xs text-primary hover:underline">
                            {{ t('parties.viewAllSuppliers') }} →
                        </Link>
                    </div>
                </div>

            </div>

            <!-- Accounting note -->
            <div class="rounded-xl border border-border bg-muted/30 px-4 py-3 text-xs leading-relaxed text-muted-foreground space-y-2">
                <p>
                    <span class="font-semibold text-foreground">{{ t('parties.accountingNoteTitle') }}</span>
                </p>
                <p>{{ t('parties.accountingNoteP1') }}</p>
                <p>{{ t('parties.accountingNoteP2') }}</p>
            </div>

        </div>
    </AppLayout>
</template>
