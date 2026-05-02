<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    Landmark,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.accounts'), href: route('accounts.index') },
    { title: t('accounts.reportsTitle'), href: route('accounts.reports') },
]);

interface LineItem { code: string; name: string; sub_type: string | null; amount: number }
interface TrialItem { code: string; name: string; type: string; debit: number; credit: number; balance: number }

const props = defineProps<{
    trial_balance: TrialItem[];
    pnl: {
        income: LineItem[];
        expenses: LineItem[];
        total_income: number;
        total_expenses: number;
        net_profit: number;
    };
    balance_sheet: {
        assets: LineItem[];
        liabilities: LineItem[];
        equity: LineItem[];
        total_assets: number;
        total_liabilities: number;
        total_equity: number;
    };
    filters: { from: string; to: string };
}>();

const tab  = ref<'pnl' | 'balance' | 'trial'>('pnl');
const from = ref(props.filters.from);
const to   = ref(props.filters.to);

let debounce: ReturnType<typeof setTimeout>;
watch([from, to], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('accounts.reports'), { from: from.value, to: to.value }, { preserveState: true, replace: true });
    }, 600);
});

const typeStyle: Record<string, { text: string }> = {
    asset:     { text: 'text-blue-600 dark:text-blue-400' },
    liability: { text: 'text-red-600 dark:text-red-400' },
    equity:    { text: 'text-purple-600 dark:text-purple-400' },
    income:    { text: 'text-emerald-600 dark:text-emerald-400' },
    expense:   { text: 'text-amber-600 dark:text-amber-400' },
};

function accountTypeLabel(type: string) {
    const keys: Record<string, string> = {
        asset: 'accounts.assetType',
        liability: 'accounts.liabilityType',
        equity: 'accounts.equityType',
        income: 'accounts.incomeType',
        expense: 'accounts.expenseType',
    };
    const path = keys[type];
    return path ? t(path as 'accounts.assetType') : type;
}
</script>

<template>
    <Head :title="t('accounts.reportsTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <div>
                <h1 class="text-2xl font-bold text-foreground">{{ t('accounts.reportsTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('accounts.reportsDescription') }}</p>
            </div>
            <!-- Date range -->
            <div class="flex items-center gap-2 text-sm">
                <span class="text-muted-foreground">{{ t('common.from') }}</span>
                <Input v-model="from" type="date" class="w-36 h-9" />
                <span class="text-muted-foreground">{{ t('common.to') }}</span>
                <Input v-model="to" type="date" class="w-36 h-9" />
            </div>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:px-6">
            <StatCard :label="t('accounts.totalRevenue')"  :value="formatMoney(pnl.total_income)"   :icon="TrendingUp"   tone="success" />
            <StatCard :label="t('accounts.totalExpenses')" :value="formatMoney(pnl.total_expenses)" :icon="TrendingDown"  tone="warning" />
            <StatCard
                :label="t('accounts.netProfitLoss')"
                :value="formatMoney(Math.abs(pnl.net_profit))"
                :icon="BarChart3"
                :tone="pnl.net_profit >= 0 ? 'success' : 'danger'"
                :description="pnl.net_profit >= 0 ? t('accounts.profit') : t('accounts.loss')"
            />
            <StatCard :label="t('accounts.totalAssets')"   :value="formatMoney(balance_sheet.total_assets)" :icon="Landmark" tone="info" />
        </div>

        <!-- Tabs -->
        <div class="mt-4 border-b border-border px-4 sm:px-6">
            <div class="flex gap-1">
                <button v-for="tb in [
                    { id: 'pnl' as const,     label: t('accounts.plReport'),  icon: TrendingUp },
                    { id: 'balance' as const, label: t('accounts.balanceSheet'),  icon: Landmark },
                    { id: 'trial' as const,   label: t('accounts.trialBalance'),  icon: BookOpen },
                ]" :key="tb.id"
                    @click="tab = tb.id"
                    :class="[
                        'flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
                        tab === tb.id ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    <component :is="tb.icon" class="h-4 w-4" />
                    {{ tb.label }}
                </button>
            </div>
        </div>

        <!-- Profit & Loss -->
        <div v-if="tab === 'pnl'" class="px-4 py-4 sm:px-6 space-y-4">
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="bg-emerald-500/5 border-b border-border px-4 py-3">
                    <h3 class="font-semibold text-foreground">{{ t('accounts.income') }}</h3>
                </div>
                <table class="w-full border-collapse text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-if="pnl.income.length === 0">
                            <td colspan="2" class="px-4 py-6 text-center text-muted-foreground">{{ t('accounts.noIncomeEntries') }}</td>
                        </tr>
                        <tr v-for="item in pnl.income" :key="item.code" class="hover:bg-muted/20 [&>td]:align-middle">
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs text-muted-foreground mr-2">{{ item.code }}</span>
                                <span class="text-foreground">{{ item.name }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ formatMoney(item.amount) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-border bg-muted/30">
                        <tr class="[&>td]:align-middle">
                            <td class="px-4 py-3 font-bold text-foreground">{{ t('accounts.totalIncome') }}</td>
                            <td class="px-4 py-3 text-end font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ formatMoney(pnl.total_income) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="bg-amber-500/5 border-b border-border px-4 py-3">
                    <h3 class="font-semibold text-foreground">{{ t('accounts.expensesHeading') }}</h3>
                </div>
                <table class="w-full border-collapse text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-if="pnl.expenses.length === 0">
                            <td colspan="2" class="px-4 py-6 text-center text-muted-foreground">{{ t('accounts.noExpenseEntries') }}</td>
                        </tr>
                        <tr v-for="item in pnl.expenses" :key="item.code" class="hover:bg-muted/20 [&>td]:align-middle">
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs text-muted-foreground mr-2">{{ item.code }}</span>
                                <span class="text-foreground">{{ item.name }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums text-amber-600 dark:text-amber-400">
                                {{ formatMoney(item.amount) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-border bg-muted/30">
                        <tr class="[&>td]:align-middle">
                            <td class="px-4 py-3 font-bold text-foreground">{{ t('accounts.totalExpenses') }}</td>
                            <td class="px-4 py-3 text-end font-bold tabular-nums text-amber-600 dark:text-amber-400">
                                {{ formatMoney(pnl.total_expenses) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Net Profit/Loss -->
            <div :class="[
                'rounded-xl border-2 px-4 py-4 flex items-center justify-between',
                pnl.net_profit >= 0 ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-red-500/30 bg-red-500/5',
            ]">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">{{ pnl.net_profit >= 0 ? t('accounts.netProfit') : t('accounts.netLoss') }}</p>
                    <p class="text-xs text-muted-foreground">{{ filters.from }} {{ t('common.to') }} {{ filters.to }}</p>
                </div>
                <p :class="['text-2xl font-bold tabular-nums', pnl.net_profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500']">
                    {{ pnl.net_profit < 0 ? '− ' : '' }}{{ formatMoney(Math.abs(pnl.net_profit)) }}
                </p>
            </div>
        </div>

        <!-- Balance Sheet -->
        <div v-else-if="tab === 'balance'" class="px-4 py-4 sm:px-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <!-- Assets -->
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="bg-blue-500/5 border-b border-border px-4 py-3">
                    <h3 class="font-semibold text-foreground">{{ t('accounts.assets') }}</h3>
                </div>
                <table class="w-full border-collapse text-sm">
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in balance_sheet.assets" :key="item.code" class="hover:bg-muted/20 [&>td]:align-middle">
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs text-muted-foreground mr-2">{{ item.code }}</span>{{ item.name }}
                            </td>
                            <td class="px-4 py-2.5 text-end tabular-nums font-medium">{{ formatMoney(item.amount) }}</td>
                        </tr>
                        <tr v-if="balance_sheet.assets.length === 0">
                            <td colspan="2" class="px-4 py-6 text-center text-muted-foreground">{{ t('accounts.noAssetBalances') }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-border bg-muted/30">
                        <tr class="[&>td]:align-middle">
                            <td class="px-4 py-3 font-bold">{{ t('accounts.totalAssets') }}</td>
                            <td class="px-4 py-3 text-end font-bold tabular-nums text-blue-600 dark:text-blue-400">{{ formatMoney(balance_sheet.total_assets) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Liabilities + Equity -->
            <div class="space-y-4">
                <div class="rounded-xl border border-border bg-card overflow-hidden">
                    <div class="bg-red-500/5 border-b border-border px-4 py-3">
                        <h3 class="font-semibold text-foreground">{{ t('accounts.liabilities') }}</h3>
                    </div>
                    <table class="w-full border-collapse text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in balance_sheet.liabilities" :key="item.code" class="hover:bg-muted/20 [&>td]:align-middle">
                                <td class="px-4 py-2.5"><span class="font-mono text-xs text-muted-foreground mr-2">{{ item.code }}</span>{{ item.name }}</td>
                                <td class="px-4 py-2.5 text-end tabular-nums font-medium">{{ formatMoney(item.amount) }}</td>
                            </tr>
                            <tr v-if="balance_sheet.liabilities.length === 0">
                                <td colspan="2" class="px-4 py-4 text-center text-muted-foreground text-xs">{{ t('accounts.noLiabilities') }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-border bg-muted/30">
                            <tr class="[&>td]:align-middle">
                                <td class="px-4 py-3 font-bold">{{ t('accounts.totalLiabilities') }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-red-600 dark:text-red-400">{{ formatMoney(balance_sheet.total_liabilities) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="rounded-xl border border-border bg-card overflow-hidden">
                    <div class="bg-purple-500/5 border-b border-border px-4 py-3">
                        <h3 class="font-semibold text-foreground">{{ t('accounts.equity') }}</h3>
                    </div>
                    <table class="w-full border-collapse text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in balance_sheet.equity" :key="item.code" class="hover:bg-muted/20 [&>td]:align-middle">
                                <td class="px-4 py-2.5"><span class="font-mono text-xs text-muted-foreground mr-2">{{ item.code }}</span>{{ item.name }}</td>
                                <td class="px-4 py-2.5 text-end tabular-nums font-medium">{{ formatMoney(item.amount) }}</td>
                            </tr>
                            <tr v-if="balance_sheet.equity.length === 0">
                                <td colspan="2" class="px-4 py-4 text-center text-muted-foreground text-xs">{{ t('accounts.noEquityEntries') }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-border bg-muted/30">
                            <tr class="[&>td]:align-middle">
                                <td class="px-4 py-3 font-bold">{{ t('accounts.totalEquity') }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-purple-600 dark:text-purple-400">{{ formatMoney(balance_sheet.total_equity) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Trial Balance -->
        <div v-else class="px-4 py-4 sm:px-6">
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="border-b border-border bg-muted/30 px-4 py-3">
                    <h3 class="font-semibold text-foreground">{{ t('accounts.trialBalanceDateRange', { from: filters.from, to: filters.to }) }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-border [&>th]:align-middle">
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('accounts.code') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('accounts.account') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden sm:table-cell">{{ t('common.type') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('accounts.debit') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('accounts.credit') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('common.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="trial_balance.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">{{ t('accounts.noTransactionsInPeriod') }}</td>
                            </tr>
                            <tr v-for="item in trial_balance" :key="item.code" class="hover:bg-muted/20 [&>td]:align-middle">
                                <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">{{ item.code }}</td>
                                <td class="px-4 py-2.5 text-foreground">{{ item.name }}</td>
                                <td class="px-4 py-2.5 hidden sm:table-cell">
                                    <span :class="['text-xs capitalize font-medium', typeStyle[item.type]?.text]">{{ accountTypeLabel(item.type) }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-end tabular-nums">{{ item.debit > 0 ? formatMoney(item.debit) : '—' }}</td>
                                <td class="px-4 py-2.5 text-end tabular-nums">{{ item.credit > 0 ? formatMoney(item.credit) : '—' }}</td>
                                <td class="px-4 py-2.5 text-end tabular-nums font-semibold"
                                    :class="item.balance >= 0 ? 'text-foreground' : 'text-red-500'">
                                    {{ formatMoney(Math.abs(item.balance)) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="trial_balance.length > 0" class="border-t-2 border-border bg-muted/30">
                            <tr class="[&>td]:align-middle">
                                <td colspan="3" class="px-4 py-3 font-bold text-foreground">{{ t('common.totals') }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums">{{ formatMoney(trial_balance.reduce((s,i) => s + i.debit, 0)) }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums">{{ formatMoney(trial_balance.reduce((s,i) => s + i.credit, 0)) }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-muted-foreground">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
