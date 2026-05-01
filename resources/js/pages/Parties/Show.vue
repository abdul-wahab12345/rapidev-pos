<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, formatDateTime } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Building2, ShoppingCart, TrendingDown, TrendingUp } from 'lucide-vue-next';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
    { title: props.party.name, href: `/customers/${props.receivable?.customer_id}` },
    { title: 'Net Balance', href: '#' },
];

const fmt = formatMoney;
const fmtDate = formatDateTime;

const statusClass: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    received:  'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    partial:   'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    cancelled: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <Head :title="`${party.name} — Net Balance`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl">

            <!-- Header -->
            <div class="flex flex-wrap items-start gap-3">
                <Link
                    v-if="receivable"
                    :href="route('customers.show', receivable.customer_id)"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mt-1"
                >
                    <ArrowLeft class="h-4 w-4" /> Back to Customer
                </Link>

                <div class="flex-1">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ party.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Unified contact — AR & AP overview</p>
                </div>
            </div>

            <!-- Net balance hero -->
            <div class="grid grid-cols-3 gap-4">
                <!-- AR -->
                <div class="rounded-xl border p-4"
                     :class="receivable && receivable.current_balance > 0 ? 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingUp class="h-4 w-4 text-red-500" />
                        <p class="text-xs font-medium text-muted-foreground">Receivable (AR 1030)</p>
                    </div>
                    <p class="text-2xl font-black" :class="receivable && receivable.current_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                        {{ receivable ? (receivable.current_balance > 0 ? fmt(receivable.current_balance) : 'Clear') : '—' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">Customer owes you</p>
                </div>

                <!-- AP -->
                <div class="rounded-xl border p-4"
                     :class="payable && payable.current_balance > 0 ? 'border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingDown class="h-4 w-4 text-amber-500" />
                        <p class="text-xs font-medium text-muted-foreground">Payable (AP 2010)</p>
                    </div>
                    <p class="text-2xl font-black" :class="payable && payable.current_balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                        {{ payable ? (payable.current_balance > 0 ? fmt(payable.current_balance) : 'Clear') : '—' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">You owe supplier</p>
                </div>

                <!-- Net -->
                <div class="rounded-xl border p-4"
                     :class="net_balance >= 0 ? 'border-green-200 bg-green-50 dark:border-green-800/50 dark:bg-green-950/20' : 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-950/20'">
                    <p class="text-xs font-medium text-muted-foreground mb-1">Net Position</p>
                    <p class="text-2xl font-black" :class="net_balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                        {{ fmt(Math.abs(net_balance)) }}
                        <span class="text-sm font-normal ml-1">{{ net_balance >= 0 ? 'in your favour' : 'you owe net' }}</span>
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">AR − AP (display only, not posted)</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">

                <!-- AR side: ledger -->
                <div v-if="receivable">
                    <div class="mb-3 flex items-center gap-2">
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-foreground">AR Ledger</h2>
                        <span class="ml-auto text-xs text-muted-foreground">Account 1030 — Asset</span>
                    </div>
                    <div class="rounded-xl border border-border overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="px-3 py-2.5">Date</th>
                                    <th class="px-3 py-2.5">Description</th>
                                    <th class="px-3 py-2.5 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="receivable.ledger.length === 0">
                                    <td colspan="3" class="px-3 py-8 text-center text-xs text-muted-foreground">No ledger entries</td>
                                </tr>
                                <tr v-for="e in receivable.ledger" :key="e.id" class="hover:bg-muted/20">
                                    <td class="px-3 py-2 text-xs text-muted-foreground whitespace-nowrap">{{ fmtDate(e.created_at) }}</td>
                                    <td class="px-3 py-2 text-xs text-muted-foreground">{{ e.description || e.type }}</td>
                                    <td class="px-3 py-2 text-right text-sm font-bold text-foreground">{{ fmt(e.running_balance) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <Link :href="route('customers.show', receivable.customer_id)" class="text-xs text-primary hover:underline">
                            View full customer ledger →
                        </Link>
                    </div>
                </div>

                <!-- AP side: purchase orders -->
                <div v-if="payable">
                    <div class="mb-3 flex items-center gap-2">
                        <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-foreground">Purchase Orders</h2>
                        <span class="ml-auto text-xs text-muted-foreground">Account 2010 — Liability</span>
                    </div>
                    <div class="rounded-xl border border-border overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="px-3 py-2.5">Date</th>
                                    <th class="px-3 py-2.5">Order #</th>
                                    <th class="px-3 py-2.5">Status</th>
                                    <th class="px-3 py-2.5 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="payable.purchase_orders.length === 0">
                                    <td colspan="4" class="px-3 py-8 text-center text-xs text-muted-foreground">No purchase orders</td>
                                </tr>
                                <tr v-for="po in payable.purchase_orders" :key="po.id" class="hover:bg-muted/20">
                                    <td class="px-3 py-2 text-xs text-muted-foreground whitespace-nowrap">{{ fmtDate(po.created_at) }}</td>
                                    <td class="px-3 py-2">
                                        <Link :href="route('purchasing.orders.show', po.id)" class="font-mono text-xs font-semibold text-primary hover:underline">
                                            {{ po.order_number }}
                                        </Link>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span :class="statusClass[po.status] ?? 'bg-muted text-muted-foreground'" class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize">
                                            {{ po.status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-sm font-bold text-foreground">{{ fmt(po.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">
                        <Link :href="route('purchasing.suppliers.index')" class="text-xs text-primary hover:underline">
                            View all suppliers →
                        </Link>
                    </div>
                </div>

            </div>

            <!-- Accounting note -->
            <div class="rounded-xl border border-border bg-muted/30 px-4 py-3 text-xs text-muted-foreground">
                <strong class="text-foreground">Accounting note:</strong>
                Customer receivables are tracked in <strong>Account 1030</strong> (Asset, debit-normal).
                Supplier payables are tracked in <strong>Account 2010</strong> (Liability, credit-normal).
                The net figure above is a management view only — it is never posted as a journal entry.
            </div>

        </div>
    </AppLayout>
</template>
