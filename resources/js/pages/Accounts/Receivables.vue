<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Clock,
    Package,
    ReceiptText,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Accounts', href: '/accounts' },
    { title: 'Receivables & Payables', href: '/accounts/receivables' },
];

interface Receivable {
    id: string;
    name: string;
    phone: string | null;
    balance: number;
    credit_limit: number;
    oldest_sale_date: string | null;
    age_days: number | null;
}

interface Payable {
    id: string;
    name: string;
    company: string | null;
    phone: string | null;
    balance: number;
    oldest_po_date: string | null;
    age_days: number | null;
}

const props = defineProps<{
    receivables:      Receivable[];
    payables:         Payable[];
    total_receivable: number;
    total_payable:    number;
    ar_account_id:    string | null;
    ap_account_id:    string | null;
}>();

// Aging buckets
const current   = computed(() => props.receivables.filter(r => (r.age_days ?? 0) <= 30));
const days31_60 = computed(() => props.receivables.filter(r => (r.age_days ?? 0) > 30 && (r.age_days ?? 0) <= 60));
const days61_90 = computed(() => props.receivables.filter(r => (r.age_days ?? 0) > 60 && (r.age_days ?? 0) <= 90));
const over90    = computed(() => props.receivables.filter(r => (r.age_days ?? 0) > 90));

function ageBadge(days: number | null) {
    const d = days ?? 0;
    const label = d === 0 ? 'Today' : `${d}d`;
    if (d <= 30)  return { cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', label };
    if (d <= 60)  return { cls: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', label };
    if (d <= 90)  return { cls: 'bg-orange-500/10 text-orange-600 dark:text-orange-400', label };
    return { cls: 'bg-red-500/10 text-red-500', label: `${d}d overdue` };
}

function fmtDate(d: string | null) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Receivables & Payables" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- Header -->
        <div class="px-4 py-4 sm:px-6">
            <h1 class="text-2xl font-bold text-foreground">Receivables & Payables</h1>
            <p class="text-sm text-muted-foreground mt-0.5">Who owes you money, and who you owe</p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:px-6">
            <StatCard
                label="Total Receivable"
                :value="'Rs ' + formatMoney(total_receivable)"
                :icon="ReceiptText"
                tone="warning"
                description="Customers owe you this"
            />
            <StatCard
                label="Customers with Udhaar"
                :value="receivables.length"
                :icon="Users"
            />
            <StatCard
                label="Overdue (>30 days)"
                :value="days31_60.length + days61_90.length + over90.length"
                :icon="Clock"
                :tone="(days31_60.length + days61_90.length + over90.length) > 0 ? 'danger' : 'default'"
            />
            <StatCard
                label="Total Payable"
                :value="total_payable > 0 ? 'Rs ' + formatMoney(total_payable) : 'Rs 0'"
                :icon="Wallet"
                description="You owe this (via journal entries)"
            />
        </div>

        <!-- AR / AP links -->
        <div class="flex flex-wrap gap-2 px-4 pt-4 sm:px-6">
            <a
                v-if="ar_account_id"
                :href="route('accounts.ledger', { account: ar_account_id })"
                class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline rounded-lg border border-primary/20 bg-primary/5 px-3 py-1.5"
            >
                <ReceiptText class="h-3.5 w-3.5" />
                View AR General Ledger
                <ArrowRight class="h-3 w-3" />
            </a>
            <a
                v-if="ap_account_id"
                :href="route('accounts.ledger', { account: ap_account_id })"
                class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline rounded-lg border border-primary/20 bg-primary/5 px-3 py-1.5"
            >
                <Wallet class="h-3.5 w-3.5" />
                View AP General Ledger
                <ArrowRight class="h-3 w-3" />
            </a>
        </div>

        <!-- Aging buckets summary -->
        <div class="mt-4 grid grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:px-6">
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
                <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">Current (0–30 days)</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">
                    Rs {{ formatMoney(current.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-emerald-600/70 dark:text-emerald-500">{{ current.length }} customer(s)</p>
            </div>
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">31–60 days</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-amber-700 dark:text-amber-400">
                    Rs {{ formatMoney(days31_60.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-amber-600/70">{{ days31_60.length }} customer(s)</p>
            </div>
            <div class="rounded-xl border border-orange-500/20 bg-orange-500/5 px-4 py-3">
                <p class="text-xs font-medium text-orange-700 dark:text-orange-400 uppercase tracking-wide">61–90 days</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-orange-700 dark:text-orange-400">
                    Rs {{ formatMoney(days61_90.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-orange-600/70">{{ days61_90.length }} customer(s)</p>
            </div>
            <div class="rounded-xl border border-red-500/20 bg-red-500/5 px-4 py-3">
                <p class="text-xs font-medium text-red-700 dark:text-red-400 uppercase tracking-wide">Over 90 days</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-red-600 dark:text-red-400">
                    Rs {{ formatMoney(over90.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-red-500/70">{{ over90.length }} customer(s)</p>
            </div>
        </div>

        <!-- Receivables table -->
        <div class="mt-4 px-4 pb-6 sm:px-6">
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="border-b border-border px-4 py-3 flex items-center gap-2">
                    <ReceiptText class="h-4 w-4 text-muted-foreground" />
                    <h3 class="font-semibold text-foreground">Accounts Receivable — Customer Breakdown</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Customer</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Phone</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Oldest Udhaar</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">Age</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Outstanding</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="receivables.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    <CheckCircle2 class="mx-auto mb-2 h-8 w-8 text-emerald-500/50" />
                                    All customers are settled — no outstanding udhaar!
                                </td>
                            </tr>
                            <tr
                                v-for="r in receivables" :key="r.id"
                                class="hover:bg-muted/20 transition-colors"
                                :class="(r.age_days ?? 0) > 90 ? 'bg-red-500/3' : ''"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-foreground">{{ r.name }}</div>
                                    <div v-if="(r.age_days ?? 0) > 90" class="flex items-center gap-1 text-xs text-red-500 mt-0.5">
                                        <AlertTriangle class="h-3 w-3" /> Overdue
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ r.phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground text-xs">{{ fmtDate(r.oldest_sale_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="r.age_days !== null"
                                        :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', ageBadge(r.age_days).cls]">
                                        {{ ageBadge(r.age_days).label }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-bold text-foreground">
                                    Rs {{ formatMoney(r.balance) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        :href="route('customers.show', r.id)"
                                        class="text-xs text-primary hover:underline"
                                    >View →</a>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="receivables.length > 0" class="border-t-2 border-border bg-muted/30">
                            <tr>
                                <td colspan="4" class="px-4 py-3 font-bold text-foreground">Total Receivable</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums text-amber-600 dark:text-amber-400">
                                    Rs {{ formatMoney(total_receivable) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payables table -->
            <div class="mt-4 rounded-xl border border-border bg-card overflow-hidden">
                <div class="border-b border-border px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Wallet class="h-4 w-4 text-muted-foreground" />
                        <h3 class="font-semibold text-foreground">Accounts Payable — Supplier Breakdown</h3>
                    </div>
                    <a href="/purchasing/suppliers" class="text-xs text-primary hover:underline flex items-center gap-1">
                        Manage Suppliers <ArrowRight class="h-3 w-3" />
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Supplier</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Phone</th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Oldest PO</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">Age</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Outstanding</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="payables.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    <CheckCircle2 class="mx-auto mb-2 h-8 w-8 text-emerald-500/50" />
                                    No outstanding supplier payables.
                                </td>
                            </tr>
                            <tr
                                v-for="p in payables" :key="p.id"
                                class="hover:bg-muted/20 transition-colors"
                                :class="(p.age_days ?? 0) > 90 ? 'bg-red-500/3' : ''"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-foreground">{{ p.name }}</div>
                                    <div v-if="p.company" class="text-xs text-muted-foreground">{{ p.company }}</div>
                                    <div v-if="(p.age_days ?? 0) > 90" class="flex items-center gap-1 text-xs text-red-500 mt-0.5">
                                        <AlertTriangle class="h-3 w-3" /> Overdue
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ p.phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground text-xs">{{ fmtDate(p.oldest_po_date) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="p.age_days !== null"
                                        :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', ageBadge(p.age_days).cls]">
                                        {{ ageBadge(p.age_days).label }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-bold text-foreground">
                                    Rs {{ formatMoney(p.balance) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        :href="`/purchasing/suppliers`"
                                        class="text-xs text-primary hover:underline"
                                    >View →</a>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="payables.length > 0" class="border-t-2 border-border bg-muted/30">
                            <tr>
                                <td colspan="4" class="px-4 py-3 font-bold text-foreground">Total Payable</td>
                                <td class="px-4 py-3 text-right font-bold tabular-nums text-orange-600 dark:text-orange-400">
                                    Rs {{ formatMoney(total_payable) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
