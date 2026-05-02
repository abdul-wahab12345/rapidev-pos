<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.accounts'), href: route('accounts.index') },
    { title: t('accounts.generalLedgerTitle'), href: route('accounts.ledger') },
]);

interface AccountOption { id: string; code: string; name: string; type: string }
interface LedgerLine {
    entry_number: string;
    entry_date: string;
    description: string;
    reference_type: string | null;
    debit: number;
    credit: number;
    balance: number;
}

const props = defineProps<{
    account_list: AccountOption[];
    selected_account: AccountOption | null;
    opening_balance: number;
    lines: LedgerLine[];
    filters: { account: string | null; from: string; to: string };
}>();

const selectedId = ref(props.filters.account ?? props.selected_account?.id ?? '');
const from       = ref(props.filters.from);
const to         = ref(props.filters.to);

let debounce: ReturnType<typeof setTimeout>;
function reload() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('accounts.ledger'), {
            account: selectedId.value || undefined,
            from: from.value,
            to:   to.value,
        }, { preserveState: true, replace: true });
    }, 400);
}

watch([selectedId, from, to], reload);

function fmtDate(d: string) {
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(d).toLocaleDateString(loc, { day: '2-digit', month: 'short', year: 'numeric' });
}

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

function ledgerRefLabel(referenceType: string | null | undefined) {
    const key = referenceType ?? 'manual';
    const map: Record<string, () => string> = {
        manual: () => t('accounts.refManual'),
        sale: () => t('badges.sale'),
        payment: () => t('badges.payment'),
        expense: () => t('accounts.refExpense'),
        void: () => t('badges.void'),
    };
    return map[key]?.() ?? key;
}

const typeStyle: Record<string, { bg: string; text: string }> = {
    asset:     { bg: 'bg-blue-500/10',    text: 'text-blue-600 dark:text-blue-400' },
    liability: { bg: 'bg-red-500/10',     text: 'text-red-600 dark:text-red-400' },
    equity:    { bg: 'bg-purple-500/10',  text: 'text-purple-600 dark:text-purple-400' },
    income:    { bg: 'bg-emerald-500/10', text: 'text-emerald-600 dark:text-emerald-400' },
    expense:   { bg: 'bg-amber-500/10',   text: 'text-amber-600 dark:text-amber-400' },
};

const refBadge: Record<string, string> = {
    sale:    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    void:    'bg-red-500/10 text-red-500',
    payment: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    manual:  'bg-muted text-muted-foreground',
    expense: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
};

const closingBalance = computed(() =>
    props.lines.length > 0
        ? props.lines[props.lines.length - 1].balance
        : props.opening_balance
);

const totalDebit  = computed(() => props.lines.reduce((s, l) => s + l.debit,  0));
const totalCredit = computed(() => props.lines.reduce((s, l) => s + l.credit, 0));
</script>

<template>
    <Head :title="t('accounts.generalLedgerTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6">
            <div>
                <h1 class="text-2xl font-bold text-foreground">{{ t('accounts.generalLedgerTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('accounts.generalLedgerDescription') }}</p>
            </div>
            <Button variant="outline" as="a" :href="route('accounts.index')" class="gap-1.5 text-sm">
                <BookOpen class="h-4 w-4" /> {{ t('accounts.chartOfAccountsLink') }}
            </Button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2 px-4 pb-4 sm:px-6">
            <!-- Account picker -->
            <div class="w-72">
                <Select v-model="selectedId">
                    <SelectTrigger>
                        <SelectValue :placeholder="t('accounts.selectAccount')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="a in account_list" :key="a.id" :value="a.id">
                            {{ a.code }} – {{ a.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-muted-foreground">{{ t('common.from') }}</span>
                <Input v-model="from" type="date" class="w-36 h-9" />
                <span class="text-muted-foreground">{{ t('common.to') }}</span>
                <Input v-model="to"   type="date" class="w-36 h-9" />
            </div>
        </div>

        <!-- Account header card -->
        <div v-if="selected_account" class="mx-4 mb-4 sm:mx-6 rounded-xl border border-border bg-card px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div :class="['flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold', typeStyle[selected_account.type]?.bg, typeStyle[selected_account.type]?.text]">
                    {{ selected_account.code }}
                </div>
                <div>
                    <p class="font-semibold text-foreground">{{ selected_account.name }}</p>
                    <p class="text-xs text-muted-foreground capitalize">{{ accountTypeLabel(selected_account.type) }}</p>
                </div>
            </div>
            <div class="flex gap-6 text-right">
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('accounts.openingBalance') }}</p>
                    <p class="font-semibold tabular-nums" :class="opening_balance < 0 ? 'text-red-500' : 'text-foreground'">
                        {{ formatMoney(Math.abs(opening_balance)) }}
                        <span class="text-xs text-muted-foreground ml-1">{{ opening_balance < 0 ? t('accounts.cr') : t('accounts.dr') }}</span>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('accounts.closingBalance') }}</p>
                    <p class="font-semibold tabular-nums" :class="closingBalance < 0 ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ formatMoney(Math.abs(closingBalance)) }}
                        <span class="text-xs text-muted-foreground ml-1">{{ closingBalance < 0 ? t('accounts.cr') : t('accounts.dr') }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Ledger table -->
        <div class="px-4 pb-6 sm:px-6">
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr class="[&>th]:align-middle">
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground w-28">{{ t('common.date') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground w-28">{{ t('accounts.entryNumber') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('common.description') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground whitespace-nowrap min-w-[11rem] w-[11rem]">{{ t('accounts.referenceType') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground w-28">{{ t('accounts.debit') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground w-28">{{ t('accounts.credit') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground w-32">{{ t('common.balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">

                            <!-- Opening balance row -->
                            <tr v-if="selected_account" class="bg-muted/20 [&>td]:align-middle">
                                <td colspan="6" class="px-4 py-2.5 text-xs font-medium text-muted-foreground italic">
                                    {{ t('accounts.openingBalanceBeforeDate', { date: filters.from }) }}
                                </td>
                                <td class="px-4 py-2.5 text-end text-xs font-semibold tabular-nums text-muted-foreground">
                                    {{ formatMoney(Math.abs(opening_balance)) }}
                                </td>
                            </tr>

                            <tr v-if="lines.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                    {{ selected_account ? t('accounts.noTransactionsInPeriod') : t('accounts.selectAccountToView') }}
                                </td>
                            </tr>

                            <tr
                                v-for="(line, i) in lines" :key="i"
                                class="hover:bg-muted/20 transition-colors [&>td]:align-middle"
                            >
                                <td class="px-4 py-2.5 text-xs text-muted-foreground whitespace-nowrap">{{ fmtDate(line.entry_date) }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">{{ line.entry_number }}</td>
                                <td class="px-4 py-2.5 text-foreground">{{ line.description }}</td>
                                <td class="px-4 py-2.5 text-center whitespace-nowrap align-middle">
                                    <span :class="['inline-flex items-center justify-center whitespace-nowrap text-[10px] px-2 py-0.5 rounded-full font-medium', refBadge[line.reference_type ?? 'manual'] ?? refBadge.manual]">
                                        {{ ledgerRefLabel(line.reference_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-end tabular-nums text-emerald-600 dark:text-emerald-400 font-medium">
                                    {{ line.debit > 0 ? formatMoney(line.debit) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-end tabular-nums text-amber-600 dark:text-amber-400 font-medium">
                                    {{ line.credit > 0 ? formatMoney(line.credit) : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-end tabular-nums font-semibold"
                                    :class="line.balance < 0 ? 'text-red-500' : 'text-foreground'">
                                    {{ formatMoney(Math.abs(line.balance)) }}
                                    <span class="text-[10px] text-muted-foreground ml-0.5">{{ line.balance < 0 ? t('accounts.cr') : t('accounts.dr') }}</span>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Footer totals -->
                        <tfoot v-if="lines.length > 0" class="border-t-2 border-border bg-muted/30">
                            <tr class="[&>td]:align-middle">
                                <td colspan="4" class="px-4 py-3 font-bold text-foreground text-sm">{{ t('accounts.periodTotals') }}</td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                    {{ formatMoney(totalDebit) }}
                                </td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-amber-600 dark:text-amber-400">
                                    {{ formatMoney(totalCredit) }}
                                </td>
                                <td class="px-4 py-3 text-end font-bold tabular-nums text-foreground">
                                    {{ formatMoney(Math.abs(closingBalance)) }}
                                    <span class="text-xs text-muted-foreground ml-1">{{ closingBalance < 0 ? t('accounts.cr') : t('accounts.dr') }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
