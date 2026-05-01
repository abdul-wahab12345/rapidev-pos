<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { formatMoney, formatDateTime } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft, Building2, CreditCard, ExternalLink,
    Mail, MapPin, Phone, ShoppingCart, TrendingDown, TrendingUp, User,
} from 'lucide-vue-next';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'Suppliers', href: '/purchasing/suppliers' },
    { title: props.supplier.name, href: '#' },
];

const fmt = formatMoney;
const fmtDate = (d: string) => new Date(d).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });

const statusConfig: Record<string, { cls: string; label: string }> = {
    draft:     { cls: 'bg-muted text-muted-foreground', label: 'Draft' },
    ordered:   { cls: 'bg-blue-500/10 text-blue-600 dark:text-blue-400', label: 'Ordered' },
    partial:   { cls: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', label: 'Partial' },
    received:  { cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', label: 'Received' },
    cancelled: { cls: 'bg-red-500/10 text-red-500', label: 'Cancelled' },
};

const totalOrders  = props.purchase_orders.length;
const totalSpend   = props.purchase_orders.reduce((s, o) => s + o.total, 0);
const totalDue     = props.purchase_orders.reduce((s, o) => s + o.amount_due, 0);
</script>

<template>
    <Head :title="supplier.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl">

            <!-- Header -->
            <div class="flex flex-wrap items-start gap-3">
                <Link href="/purchasing/suppliers" class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mt-1">
                    <ArrowLeft class="h-4 w-4" /> Back
                </Link>

                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ supplier.name }}</h1>
                        <span v-if="supplier.company" class="text-sm text-muted-foreground">{{ supplier.company }}</span>
                        <span
                            :class="supplier.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground'"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >{{ supplier.is_active ? 'Active' : 'Inactive' }}</span>
                        <span v-if="is_also_customer" class="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400">
                            Also a Customer
                        </span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                        <span v-if="supplier.phone" class="flex items-center gap-1"><Phone class="h-3.5 w-3.5" />{{ supplier.phone }}</span>
                        <span v-if="supplier.email" class="flex items-center gap-1"><Mail class="h-3.5 w-3.5" />{{ supplier.email }}</span>
                        <span v-if="supplier.city" class="flex items-center gap-1"><MapPin class="h-3.5 w-3.5" />{{ supplier.city }}</span>
                        <span v-if="supplier.payment_terms">{{ supplier.payment_terms }}-day terms</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Link :href="route('purchasing.orders.create', { supplier: supplier.id })">
                        <Button variant="outline" class="gap-2"><ShoppingCart class="h-4 w-4" /> New PO</Button>
                    </Link>
                    <Link v-if="supplier.party_id && is_also_customer" :href="route('parties.show', supplier.party_id)">
                        <Button variant="outline" class="gap-2"><ExternalLink class="h-4 w-4" /> Net Balance</Button>
                    </Link>
                </div>
            </div>

            <!-- Balance cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <!-- AP (what you owe) -->
                <div class="rounded-xl border p-4"
                     :class="supplier.current_balance > 0 ? 'border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingDown class="h-4 w-4 text-amber-500" />
                        <p class="text-xs font-medium text-muted-foreground">You Owe (AP)</p>
                    </div>
                    <p class="text-xl font-black" :class="supplier.current_balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                        {{ supplier.current_balance > 0 ? fmt(supplier.current_balance) : 'Clear' }}
                    </p>
                </div>

                <!-- AR offset (if also a customer) -->
                <div v-if="is_also_customer" class="rounded-xl border p-4"
                     :class="customer_link && customer_link.current_balance > 0 ? 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-950/20' : 'border-border bg-card'">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingUp class="h-4 w-4 text-red-500" />
                        <p class="text-xs font-medium text-muted-foreground">They Owe You (AR)</p>
                    </div>
                    <p class="text-xl font-black" :class="customer_link && customer_link.current_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                        {{ customer_link && customer_link.current_balance > 0 ? fmt(customer_link.current_balance) : 'Clear' }}
                    </p>
                </div>

                <!-- Net payable -->
                <div class="rounded-xl border p-4"
                     :class="net_payable > 0 ? 'border-orange-200 bg-orange-50 dark:border-orange-800/50 dark:bg-orange-950/20' : 'border-border bg-card'">
                    <p class="text-xs font-medium text-muted-foreground mb-1">Net Payable</p>
                    <p class="text-xl font-black" :class="net_payable > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ net_payable > 0 ? fmt(net_payable) : 'Clear' }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">AP − AR offset</p>
                </div>

                <!-- Total spend -->
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground mb-1">Total Purchased</p>
                    <p class="text-xl font-black text-foreground">{{ fmt(totalSpend) }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ totalOrders }} orders</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <!-- Purchase order history (2/3) -->
                <div class="lg:col-span-2">
                    <div class="mb-3 flex items-center gap-2">
                        <ShoppingCart class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-foreground">Purchase Order History</h2>
                        <span class="ml-auto text-xs text-muted-foreground">{{ totalOrders }} orders · {{ fmt(totalDue) }} due</span>
                    </div>

                    <div class="rounded-xl border border-border overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="px-4 py-2.5">PO #</th>
                                    <th class="px-4 py-2.5">Date</th>
                                    <th class="px-4 py-2.5">Status</th>
                                    <th class="px-4 py-2.5 text-right">Total</th>
                                    <th class="px-4 py-2.5 text-right">Due</th>
                                    <th class="px-4 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="purchase_orders.length === 0">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-muted-foreground">No purchase orders yet</td>
                                </tr>
                                <tr v-for="po in purchase_orders" :key="po.id" class="hover:bg-muted/20">
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('purchasing.orders.show', po.id)" class="font-mono text-xs font-semibold text-primary hover:underline">
                                            {{ po.po_number }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ fmtDate(po.order_date) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span :class="statusConfig[po.status]?.cls ?? 'bg-muted text-muted-foreground'" class="rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                            {{ statusConfig[po.status]?.label ?? po.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-sm font-semibold text-foreground">{{ fmt(po.total) }}</td>
                                    <td class="px-4 py-2.5 text-right text-sm font-semibold"
                                        :class="po.amount_due > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">
                                        {{ po.amount_due > 0 ? fmt(po.amount_due) : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <Link :href="route('purchasing.orders.show', po.id)" class="text-xs text-primary hover:underline">View →</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right sidebar: supplier info + customer link -->
                <div class="space-y-4">

                    <!-- Contact info -->
                    <div class="rounded-xl border border-border bg-card p-4 space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2">
                            <Building2 class="h-3.5 w-3.5" /> Supplier Details
                        </h3>
                        <div class="space-y-1.5 text-sm">
                            <div v-if="supplier.ntn" class="flex justify-between">
                                <span class="text-muted-foreground">NTN</span>
                                <span class="font-mono text-xs">{{ supplier.ntn }}</span>
                            </div>
                            <div v-if="supplier.address" class="flex justify-between gap-2">
                                <span class="text-muted-foreground shrink-0">Address</span>
                                <span class="text-xs text-right">{{ supplier.address }}</span>
                            </div>
                            <div v-if="supplier.payment_terms" class="flex justify-between">
                                <span class="text-muted-foreground">Terms</span>
                                <span>{{ supplier.payment_terms }} days</span>
                            </div>
                        </div>
                        <div v-if="supplier.notes" class="border-t pt-2 text-xs text-muted-foreground">{{ supplier.notes }}</div>
                    </div>

                    <!-- Customer link card -->
                    <div v-if="is_also_customer && customer_link" class="rounded-xl border border-blue-200 bg-blue-50 dark:border-blue-800/50 dark:bg-blue-950/20 p-4 space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300 flex items-center gap-2">
                            <User class="h-3.5 w-3.5" /> Also a Customer
                        </h3>
                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">AR Balance</span>
                                <span :class="customer_link.current_balance > 0 ? 'text-red-600 font-semibold' : 'text-muted-foreground'">
                                    {{ customer_link.current_balance > 0 ? fmt(customer_link.current_balance) : 'Clear' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Net Position</span>
                                <span :class="net_payable > 0 ? 'text-orange-600 font-bold' : 'text-emerald-600 font-bold'">
                                    {{ fmt(net_payable) }}
                                    <span class="text-xs font-normal text-muted-foreground">{{ net_payable > 0 ? 'you owe net' : 'clear' }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <Link :href="route('customers.show', customer_link.id)" class="flex-1">
                                <Button variant="outline" size="sm" class="w-full text-xs gap-1">
                                    <User class="h-3 w-3" /> Customer Profile
                                </Button>
                            </Link>
                            <Link v-if="supplier.party_id" :href="route('parties.show', supplier.party_id)" class="flex-1">
                                <Button variant="outline" size="sm" class="w-full text-xs gap-1">
                                    <ExternalLink class="h-3 w-3" /> Net Balance
                                </Button>
                            </Link>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
