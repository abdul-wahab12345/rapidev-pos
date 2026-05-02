<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BarChart3, Calendar, Pencil, Plus, Receipt, Trash2, Wallet, X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from '@/composables/useConfirm';

const { t, locale } = useI18n();
const { confirm } = useConfirm();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.finance'), href: route('accounts.index') },
    { title: t('nav.expenses'), href: route('expenses.index') },
]);

interface ExpenseAccount {
    id: string;
    code: string;
    name: string;
    sub_type: string | null;
    label: string;
}

interface Expense {
    id: string;
    expense_number: string;
    expense_date: string;
    account_id: string;
    account_name: string | null;
    account_code: string | null;
    amount: number;
    payment_method: string;
    description: string | null;
    notes: string | null;
    reference: string | null;
}

interface CategoryStat {
    category: string;
    code: string;
    total: number;
}

const props = defineProps<{
    expenses: { data: Expense[]; current_page: number; last_page: number; total: number };
    stats: { this_month: number; this_year: number; total_count: number; by_category: CategoryStat[] };
    expense_accounts: ExpenseAccount[];
    filters: { account_id?: string; payment_method?: string; date_from?: string; date_to?: string };
}>();

const accountFilter    = ref(props.filters.account_id ?? '');
const methodFilter     = ref(props.filters.payment_method ?? '');
const dateFrom         = ref(props.filters.date_from ?? '');
const dateTo           = ref(props.filters.date_to ?? '');
const showModal        = ref(false);
const editTarget       = ref<Expense | null>(null);

const form = useForm({
    account_id:     '',
    expense_date:   new Date().toISOString().split('T')[0],
    amount:         '' as number | string,
    payment_method: 'cash',
    description:    '',
    notes:          '',
    reference:      '',
});

function filterQuery(extra: Record<string, unknown> = {}) {
    return {
        account_id:     accountFilter.value || undefined,
        payment_method: methodFilter.value || undefined,
        date_from:      dateFrom.value || undefined,
        date_to:        dateTo.value || undefined,
        ...extra,
    };
}

let filterTimer: ReturnType<typeof setTimeout>;
watch([accountFilter, methodFilter, dateFrom, dateTo], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => applyFilters(), 400);
});

function applyFilters(extra?: Record<string, unknown>) {
    router.get(route('expenses.index'), filterQuery(extra ?? {}), { preserveState: true, replace: true });
}

function clearFilters() {
    accountFilter.value = '';
    methodFilter.value  = '';
    dateFrom.value      = '';
    dateTo.value        = '';
    applyFilters();
}

function openCreate() {
    editTarget.value = null;
    form.reset();
    form.expense_date = new Date().toISOString().split('T')[0];
    form.payment_method = 'cash';
    showModal.value = true;
}

function openEdit(e: Expense) {
    editTarget.value     = e;
    form.account_id      = e.account_id;
    form.expense_date    = e.expense_date;
    form.amount          = e.amount;
    form.payment_method  = e.payment_method;
    form.description     = e.description ?? '';
    form.notes           = e.notes ?? '';
    form.reference       = e.reference ?? '';
    showModal.value      = true;
}

function save() {
    if (editTarget.value) {
        form.patch(route('expenses.update', editTarget.value.id), {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    } else {
        form.post(route('expenses.store'), {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

async function remove(e: Expense) {
    const ok = await confirm({
        title: t('expenses.deleteConfirmTitle'),
        message: t('expenses.deleteConfirmMessage', { number: e.expense_number }),
        confirmLabel: t('expenses.deleteConfirmAction'),
        cancelLabel: t('common.cancel'),
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(route('expenses.destroy', e.id), { preserveScroll: true });
}

function expensePaymentLabel(method: string) {
    if (method === 'bank') return t('expenses.bank');
    if (method === 'cash') return t('common.cash');
    return method;
}

function fmtDate(d: string) {
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(d + 'T00:00:00').toLocaleDateString(loc, {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

const hasFilters = () => accountFilter.value || methodFilter.value || dateFrom.value || dateTo.value;
</script>

<template>
    <Head :title="t('expenses.pageTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('expenses.pageTitle') }}</h1>
                    <p class="text-muted-foreground text-sm mt-1">{{ t('expenses.pageDescription') }}</p>
                </div>
                <Button @click="openCreate" class="gap-2">
                    <Plus :size="16" />
                    {{ t('expenses.addExpense') }}
                </Button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard
                    :label="t('common.thisMonth')"
                    :value="formatMoney(stats.this_month)"
                    :icon="Calendar"
                    tone="warning"
                />
                <StatCard
                    :label="t('common.thisYear')"
                    :value="formatMoney(stats.this_year)"
                    :icon="Wallet"
                />
                <StatCard
                    :label="t('expenses.entriesYtd')"
                    :value="String(stats.total_count)"
                    :icon="Receipt"
                    tone="info"
                />
                <StatCard
                    :label="t('expenses.topCategory')"
                    :value="stats.by_category[0]?.category ?? '—'"
                    :description="stats.by_category[0] ? formatMoney(stats.by_category[0].total) : undefined"
                    :icon="BarChart3"
                />
            </div>

            <!-- Category breakdown -->
            <div v-if="stats.by_category.length" class="rounded-xl border p-4">
                <h2 class="text-sm font-semibold mb-3 text-muted-foreground uppercase tracking-wide">
                    {{ t('expenses.topCategories') }}
                </h2>
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="cat in stats.by_category"
                        :key="cat.code"
                        class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2 text-sm"
                    >
                        <span class="text-muted-foreground text-xs font-mono">{{ cat.code }}</span>
                        <span class="font-medium">{{ cat.category }}</span>
                        <span class="tabular-nums font-semibold text-amber-600 dark:text-amber-400">
                            {{ formatMoney(cat.total) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 items-center">
                <select
                    v-model="accountFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm min-w-[180px]"
                >
                    <option value="">{{ t('expenses.allCategories') }}</option>
                    <option v-for="a in expense_accounts" :key="a.id" :value="a.id">
                        {{ a.label }}
                    </option>
                </select>

                <select
                    v-model="methodFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">{{ t('expenses.allMethods') }}</option>
                    <option value="cash">{{ t('common.cash') }}</option>
                    <option value="bank">{{ t('expenses.bank') }}</option>
                </select>

                <div class="flex items-center gap-2">
                    <Input v-model="dateFrom" type="date" class="w-36 text-sm" :placeholder="t('common.from')" />
                    <span class="text-muted-foreground text-xs">{{ t('common.to') }}</span>
                    <Input v-model="dateTo"   type="date" class="w-36 text-sm" :placeholder="t('common.to')" />
                </div>

                <Button v-if="hasFilters()" variant="ghost" size="icon" @click="clearFilters">
                    <X :size="16" />
                </Button>
            </div>

            <!-- Table -->
            <div class="border rounded-xl overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead class="bg-muted/50">
                        <tr class="[&>th]:align-middle">
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-start font-medium text-muted-foreground">
                                {{ t('common.number') }}
                            </th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-start font-medium text-muted-foreground">
                                {{ t('common.date') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-start font-medium text-muted-foreground">
                                {{ t('expenses.category') }}
                            </th>
                            <th
                                scope="col"
                                class="hidden px-4 py-3 text-start font-medium text-muted-foreground md:table-cell md:min-w-[8rem]"
                            >
                                {{ t('common.description') }}
                            </th>
                            <th
                                scope="col"
                                class="hidden w-36 px-4 py-3 text-start font-medium text-muted-foreground sm:table-cell"
                            >
                                {{ t('common.paymentMethod') }}
                            </th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-end font-medium text-muted-foreground">
                                {{ t('common.amount') }}
                            </th>
                            <th scope="col" class="w-24 whitespace-nowrap px-4 py-3 text-end font-medium text-muted-foreground lg:w-[5.75rem]">
                                {{ t('common.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="expenses.data.length === 0">
                            <td colspan="7" class="text-muted-foreground py-12 text-center">
                                {{ t('expenses.noExpensesFound') }}
                            </td>
                        </tr>
                        <tr
                            v-for="e in expenses.data"
                            :key="e.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-start font-mono text-xs text-muted-foreground">
                                {{ e.expense_number }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-start">
                                {{ fmtDate(e.expense_date) }}
                            </td>
                            <td class="px-4 py-3 align-middle text-start">
                                <div class="font-medium">{{ e.account_name ?? '—' }}</div>
                                <div v-if="e.account_code" class="text-muted-foreground text-xs font-mono">
                                    {{ e.account_code }}
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 align-middle text-start text-muted-foreground md:table-cell">
                                {{ e.description ?? '—' }}
                            </td>
                            <td class="hidden px-4 py-3 align-middle text-start sm:table-cell">
                                <span
                                    :class="e.payment_method === 'bank'
                                        ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                        : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'"
                                    class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ expensePaymentLabel(e.payment_method) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-end tabular-nums font-semibold text-amber-600 dark:text-amber-400">
                                {{ formatMoney(e.amount) }}
                            </td>
                            <td class="px-4 py-3 align-middle text-end">
                                <div class="inline-flex justify-end gap-1 rtl:flex-row-reverse">
                                    <Button variant="ghost" size="icon" @click="openEdit(e)" :title="t('common.edit')">
                                        <Pencil :size="15" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="text-destructive" @click="remove(e)" :title="t('common.delete')">
                                        <Trash2 :size="15" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="expenses.last_page > 1" class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">
                    {{ t('expenses.paginationSummary', { current: expenses.current_page, last: expenses.last_page, total: expenses.total }) }}
                </span>
                <div class="flex gap-2">
                    <Button
                        v-if="expenses.current_page > 1"
                        variant="outline" size="sm"
                        @click="router.get(route('expenses.index'), filterQuery({ page: expenses.current_page - 1 }), { preserveState: true })"
                    >
                        {{ t('common.previous') }}
                    </Button>
                    <Button
                        v-if="expenses.current_page < expenses.last_page"
                        variant="outline" size="sm"
                        @click="router.get(route('expenses.index'), filterQuery({ page: expenses.current_page + 1 }), { preserveState: true })"
                    >
                        {{ t('common.next') }}
                    </Button>
                </div>
            </div>

        </div>
    </AppLayout>

    <!-- Add / Edit Modal -->
    <Dialog :open="showModal" @update:open="showModal = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editTarget ? t('expenses.editExpenseTitle') : t('expenses.addExpenseTitle') }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="save" class="grid grid-cols-2 gap-4 mt-2">

                <!-- Category -->
                <div class="col-span-2">
                    <Label>{{ t('expenses.categoryLabelRequired') }}</Label>
                    <select
                        v-model="form.account_id"
                        required
                        class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm w-full mt-1"
                    >
                        <option value="" disabled>{{ t('expenses.placeholderSelectCategory') }}</option>
                        <option v-for="a in expense_accounts" :key="a.id" :value="a.id">
                            {{ a.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.account_id" class="text-destructive text-xs mt-1">{{ form.errors.account_id }}</p>
                </div>

                <!-- Date -->
                <div>
                    <Label>{{ t('common.date') }} *</Label>
                    <Input v-model="form.expense_date" type="date" required class="mt-1" />
                    <p v-if="form.errors.expense_date" class="text-destructive text-xs mt-1">{{ form.errors.expense_date }}</p>
                </div>

                <!-- Amount -->
                <div>
                    <Label>{{ t('expenses.amountRsRequired') }}</Label>
                    <Input
                        v-model.number="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        class="mt-1"
                        :placeholder="t('expenses.placeholderAmount')"
                    />
                    <p v-if="form.errors.amount" class="text-destructive text-xs mt-1">{{ form.errors.amount }}</p>
                </div>

                <!-- Payment Method -->
                <div class="col-span-2">
                    <Label>{{ t('expenses.paymentMethodRequired') }}</Label>
                    <div
                        dir="ltr"
                        class="mt-2 inline-flex rounded-md border border-input bg-muted/40 p-0.5"
                        role="group"
                        :aria-label="t('expenses.paymentMethodRequired')"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :class="
                                form.payment_method === 'cash'
                                    ? 'bg-background shadow-sm hover:bg-background'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            class="h-8 flex-1 rounded-sm px-4 text-xs font-medium sm:flex-none sm:min-w-[6.5rem]"
                            @click="form.payment_method = 'cash'"
                        >
                            {{ t('common.cash') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :class="
                                form.payment_method === 'bank'
                                    ? 'bg-background shadow-sm hover:bg-background'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            class="h-8 flex-1 rounded-sm px-4 text-xs font-medium sm:flex-none sm:min-w-[6.5rem]"
                            @click="form.payment_method = 'bank'"
                        >
                            {{ t('expenses.bank') }}
                        </Button>
                    </div>
                </div>

                <!-- Description -->
                <div class="col-span-2">
                    <Label>{{ t('common.description') }}</Label>
                    <Input v-model="form.description" class="mt-1" :placeholder="t('expenses.placeholderDescription')" />
                </div>

                <!-- Reference -->
                <div>
                    <Label>{{ t('expenses.reference') }}</Label>
                    <Input v-model="form.reference" class="mt-1" :placeholder="t('expenses.placeholderReference')" />
                </div>

                <!-- Notes -->
                <div>
                    <Label>{{ t('common.notes') }}</Label>
                    <Input v-model="form.notes" class="mt-1" />
                </div>

                <DialogFooter class="col-span-2 pt-2">
                    <Button type="button" variant="outline" @click="showModal = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ editTarget ? t('common.saveChanges') : t('expenses.addExpense') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
