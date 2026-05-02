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
    ReceiptText,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.accounts'), href: route('accounts.index') },
    { title: t('accounts.receivablesTitle'), href: route('accounts.receivables') },
]);

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
    ar_balance: number;
    net_payable: number;
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
    const label = d === 0 ? t('accounts.ageToday') : t('accounts.ageDaysShort', { days: d });
    if (d <= 30)  return { cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', label };
    if (d <= 60)  return { cls: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', label };
    if (d <= 90)  return { cls: 'bg-orange-500/10 text-orange-600 dark:text-orange-400', label };
    return { cls: 'bg-red-500/10 text-red-500', label: t('accounts.ageDaysOverdue', { days: d }) };
}

function fmtDate(d: string | null) {
    if (!d) return '—';
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(d).toLocaleDateString(loc, { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head :title="t('accounts.receivablesTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- Header -->
        <div class="px-4 py-4 sm:px-6">
            <h1 class="text-2xl font-bold text-foreground">{{ t('accounts.receivablesTitle') }}</h1>
            <p class="text-sm text-muted-foreground mt-0.5">{{ t('accounts.receivablesDescription') }}</p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:px-6">
            <StatCard
                :label="t('accounts.totalReceivable')"
                :value="formatMoney(total_receivable)"
                :icon="ReceiptText"
                tone="warning"
                :description="t('accounts.customersOwe')"
            />
            <StatCard
                :label="t('accounts.customersWithUdhaar')"
                :value="receivables.length"
                :icon="Users"
            />
            <StatCard
                :label="t('accounts.overdue')"
                :value="days31_60.length + days61_90.length + over90.length"
                :icon="Clock"
                :tone="(days31_60.length + days61_90.length + over90.length) > 0 ? 'danger' : 'default'"
            />
            <StatCard
                :label="t('accounts.totalPayable')"
                :value="total_payable > 0 ? formatMoney(total_payable) : formatMoney(0)"
                :icon="Wallet"
                :description="t('accounts.youOwe')"
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
                {{ t('accounts.viewArLedger') }}
                <ArrowRight class="h-3 w-3" />
            </a>
            <a
                v-if="ap_account_id"
                :href="route('accounts.ledger', { account: ap_account_id })"
                class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline rounded-lg border border-primary/20 bg-primary/5 px-3 py-1.5"
            >
                <Wallet class="h-3.5 w-3.5" />
                {{ t('accounts.viewApLedger') }}
                <ArrowRight class="h-3 w-3" />
            </a>
        </div>

        <!-- Aging buckets summary -->
        <div class="mt-4 grid grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:px-6">
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
                <p class="text-xs font-medium text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">{{ t('accounts.current030') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">
                    {{ formatMoney(current.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-emerald-600/70 dark:text-emerald-500">{{ t('accounts.customersCount', { count: current.length }) }}</p>
            </div>
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3">
                <p class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wide">{{ t('accounts.days3160') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-amber-700 dark:text-amber-400">
                    {{ formatMoney(days31_60.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-amber-600/70">{{ t('accounts.customersCount', { count: days31_60.length }) }}</p>
            </div>
            <div class="rounded-xl border border-orange-500/20 bg-orange-500/5 px-4 py-3">
                <p class="text-xs font-medium text-orange-700 dark:text-orange-400 uppercase tracking-wide">{{ t('accounts.days6190') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-orange-700 dark:text-orange-400">
                    {{ formatMoney(days61_90.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-orange-600/70">{{ t('accounts.customersCount', { count: days61_90.length }) }}</p>
            </div>
            <div class="rounded-xl border border-red-500/20 bg-red-500/5 px-4 py-3">
                <p class="text-xs font-medium text-red-700 dark:text-red-400 uppercase tracking-wide">{{ t('accounts.daysOver90') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-red-600 dark:text-red-400">
                    {{ formatMoney(over90.reduce((s, r) => s + r.balance, 0)) }}
                </p>
                <p class="text-xs text-red-500/70">{{ t('accounts.customersCount', { count: over90.length }) }}</p>
            </div>
        </div>

        <!-- Receivables table -->
        <div class="mt-4 px-4 pb-6 sm:px-6">
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="border-b border-border px-4 py-3 flex items-center gap-2">
                    <ReceiptText class="h-4 w-4 text-muted-foreground" />
                    <h3 class="font-semibold text-foreground">{{ t('accounts.arBreakdown') }}</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr class="[&>th]:align-middle">
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('accounts.customerColumn') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('common.phone') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('accounts.oldestUdhaar') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">{{ t('accounts.age') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('accounts.outstandingColumn') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="receivables.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    <CheckCircle2 class="mx-auto mb-2 h-8 w-8 text-emerald-500/50" />
                                    {{ t('accounts.allSettledUdhaar') }}
                                </td>
                            </tr>
                            <tr
                                v-for="r in receivables" :key="r.id"
                                class="hover:bg-muted/20 transition-colors [&>td]:align-middle"
                                :class="(r.age_days ?? 0) > 90 ? 'bg-red-500/3' : ''"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-foreground">{{ r.name }}</div>
                                    <div v-if="(r.age_days ?? 0) > 90" class="flex items-center gap-1 text-xs text-red-500 mt-0.5">
                                        <AlertTriangle class="h-3 w-3" /> {{ t('accounts.overdueBadge') }}
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
                                <td class="px-4 py-3 text-end tabular-nums font-bold text-foreground">
                                    {{ formatMoney(r.balance) }}
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <a
                                        :href="route('customers.show', r.id)"
                                        class="text-xs text-primary hover:underline"
                                    >{{ t('accounts.viewArrow') }}</a>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="receivables.length > 0" class="border-t-2 border-border bg-muted/30">
                            <tr class="[&>td]:align-middle">
                                <td colspan="4" class="px-4 py-3 font-bold text-foreground">{{ t('accounts.totalReceivable') }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-amber-600 dark:text-amber-400">
                                    {{ formatMoney(total_receivable) }}
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
                        <h3 class="font-semibold text-foreground">{{ t('accounts.apBreakdown') }}</h3>
                    </div>
                    <a :href="route('purchasing.suppliers.index')" class="text-xs text-primary hover:underline flex items-center gap-1">
                        {{ t('accounts.manageSuppliers') }} <ArrowRight class="h-3 w-3" />
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr class="[&>th]:align-middle">
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('accounts.supplierColumn') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden md:table-cell">{{ t('common.phone') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden lg:table-cell">{{ t('accounts.oldestPo') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground hidden lg:table-cell">{{ t('accounts.age') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-amber-700 dark:text-amber-400">{{ t('accounts.payableApColumn') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-blue-700 dark:text-blue-400 hidden sm:table-cell">{{ t('accounts.receivableArColumn') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-foreground">{{ t('common.net') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="payables.length === 0">
                                <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">
                                    <CheckCircle2 class="mx-auto mb-2 h-8 w-8 text-emerald-500/50" />
                                    {{ t('accounts.noOutstandingPayables') }}
                                </td>
                            </tr>
                            <tr
                                v-for="p in payables" :key="p.id"
                                class="hover:bg-muted/20 transition-colors [&>td]:align-middle"
                                :class="(p.age_days ?? 0) > 90 ? 'bg-red-500/3' : ''"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-foreground">{{ p.name }}</div>
                                    <div v-if="p.company" class="text-xs text-muted-foreground">{{ p.company }}</div>
                                    <div v-if="(p.age_days ?? 0) > 90" class="flex items-center gap-1 text-xs text-red-500 mt-0.5">
                                        <AlertTriangle class="h-3 w-3" /> {{ t('accounts.overdueBadge') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground hidden md:table-cell">{{ p.phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground text-xs hidden lg:table-cell">{{ fmtDate(p.oldest_po_date) }}</td>
                                <td class="px-4 py-3 text-center hidden lg:table-cell">
                                    <span v-if="p.age_days !== null"
                                        :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', ageBadge(p.age_days).cls]">
                                        {{ ageBadge(p.age_days).label }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <!-- AP actual -->
                                <td class="px-4 py-3 text-end tabular-nums font-semibold text-amber-600 dark:text-amber-400">
                                    {{ formatMoney(p.balance) }}
                                </td>
                                <!-- AR offset -->
                                <td class="px-4 py-3 text-end tabular-nums hidden sm:table-cell"
                                    :class="p.ar_balance > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-muted-foreground'">
                                    {{ p.ar_balance > 0 ? formatMoney(p.ar_balance) : '—' }}
                                </td>
                                <!-- Net -->
                                <td class="px-4 py-3 text-end tabular-nums font-bold"
                                    :class="p.net_payable > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-emerald-600 dark:text-emerald-400'">
                                    {{ formatMoney(p.net_payable) }}
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <a
                                        :href="route('purchasing.suppliers.show', p.id)"
                                        class="text-xs text-primary hover:underline"
                                    >{{ t('accounts.viewArrow') }}</a>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="payables.length > 0" class="border-t-2 border-border bg-muted/30">
                            <tr class="[&>td]:align-middle">
                                <td colspan="4" class="px-4 py-3 font-bold text-foreground">{{ t('common.total') }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-amber-600 dark:text-amber-400">
                                    {{ formatMoney(payables.reduce((s, p) => s + p.balance, 0)) }}
                                </td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-blue-600 dark:text-blue-400 hidden sm:table-cell">
                                    {{ formatMoney(payables.reduce((s, p) => s + p.ar_balance, 0)) }}
                                </td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-orange-600 dark:text-orange-400">
                                    {{ formatMoney(total_payable) }}
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
