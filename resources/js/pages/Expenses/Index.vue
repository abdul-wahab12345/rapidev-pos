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
    BarChart3, Calendar, CreditCard, Pencil, Plus, Receipt, Search, Trash2, Wallet, X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Finance', href: '/accounts' },
    { title: 'Expenses', href: '/expenses' },
];

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

let filterTimer: ReturnType<typeof setTimeout>;
watch([accountFilter, methodFilter, dateFrom, dateTo], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => applyFilters(), 400);
});

function applyFilters() {
    router.get('/expenses', {
        account_id:     accountFilter.value || undefined,
        payment_method: methodFilter.value || undefined,
        date_from:      dateFrom.value || undefined,
        date_to:        dateTo.value || undefined,
    }, { preserveState: true, replace: true });
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
        form.patch(`/expenses/${editTarget.value.id}`, {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    } else {
        form.post('/expenses', {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

function remove(e: Expense) {
    if (!confirm(`Delete expense ${e.expense_number}? This will reverse its journal entry.`)) return;
    router.delete(`/expenses/${e.id}`, { preserveScroll: true });
}

function fmtDate(d: string) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-PK', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

const hasFilters = () => accountFilter.value || methodFilter.value || dateFrom.value || dateTo.value;
</script>

<template>
    <Head title="Expenses" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Expenses</h1>
                    <p class="text-muted-foreground text-sm mt-1">Track and manage business expenses</p>
                </div>
                <Button @click="openCreate" class="gap-2">
                    <Plus :size="16" />
                    Add Expense
                </Button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard
                    label="This Month"
                    :value="formatMoney(stats.this_month)"
                    :icon="Calendar"
                    tone="warning"
                />
                <StatCard
                    label="This Year"
                    :value="formatMoney(stats.this_year)"
                    :icon="Wallet"
                />
                <StatCard
                    label="Entries (YTD)"
                    :value="String(stats.total_count)"
                    :icon="Receipt"
                    tone="info"
                />
                <StatCard
                    label="Top Category"
                    :value="stats.by_category[0]?.category ?? '—'"
                    :description="stats.by_category[0] ? formatMoney(stats.by_category[0].total) : undefined"
                    :icon="BarChart3"
                />
            </div>

            <!-- Category breakdown -->
            <div v-if="stats.by_category.length" class="rounded-xl border p-4">
                <h2 class="text-sm font-semibold mb-3 text-muted-foreground uppercase tracking-wide">
                    Top Categories (YTD)
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
                    <option value="">All Categories</option>
                    <option v-for="a in expense_accounts" :key="a.id" :value="a.id">
                        {{ a.label }}
                    </option>
                </select>

                <select
                    v-model="methodFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">All Methods</option>
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                </select>

                <div class="flex items-center gap-2">
                    <Input v-model="dateFrom" type="date" class="w-36 text-sm" placeholder="From" />
                    <span class="text-muted-foreground text-xs">to</span>
                    <Input v-model="dateTo"   type="date" class="w-36 text-sm" placeholder="To" />
                </div>

                <Button v-if="hasFilters()" variant="ghost" size="icon" @click="clearFilters">
                    <X :size="16" />
                </Button>
            </div>

            <!-- Table -->
            <div class="border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">#</th>
                            <th class="px-4 py-3 text-left font-medium">Date</th>
                            <th class="px-4 py-3 text-left font-medium">Category</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Description</th>
                            <th class="px-4 py-3 text-left font-medium hidden sm:table-cell">Method</th>
                            <th class="px-4 py-3 text-right font-medium">Amount</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="expenses.data.length === 0">
                            <td colspan="7" class="text-muted-foreground py-12 text-center">
                                No expenses found
                            </td>
                        </tr>
                        <tr
                            v-for="e in expenses.data"
                            :key="e.id"
                            class="hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                                {{ e.expense_number }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ fmtDate(e.expense_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ e.account_name ?? '—' }}</div>
                                <div v-if="e.account_code" class="text-muted-foreground text-xs font-mono">
                                    {{ e.account_code }}
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-muted-foreground">
                                {{ e.description ?? '—' }}
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <span :class="e.payment_method === 'bank'
                                    ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                    : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize">
                                    {{ e.payment_method }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-amber-600 dark:text-amber-400">
                                {{ formatMoney(e.amount) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" @click="openEdit(e)" title="Edit">
                                        <Pencil :size="15" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="text-destructive" @click="remove(e)" title="Delete">
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
                    Page {{ expenses.current_page }} of {{ expenses.last_page }} · {{ expenses.total }} total
                </span>
                <div class="flex gap-2">
                    <Button
                        v-if="expenses.current_page > 1"
                        variant="outline" size="sm"
                        @click="router.get('/expenses', { ...filters, page: expenses.current_page - 1 })"
                    >
                        Previous
                    </Button>
                    <Button
                        v-if="expenses.current_page < expenses.last_page"
                        variant="outline" size="sm"
                        @click="router.get('/expenses', { ...filters, page: expenses.current_page + 1 })"
                    >
                        Next
                    </Button>
                </div>
            </div>

        </div>
    </AppLayout>

    <!-- Add / Edit Modal -->
    <Dialog :open="showModal" @update:open="showModal = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editTarget ? 'Edit Expense' : 'Add Expense' }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="save" class="grid grid-cols-2 gap-4 mt-2">

                <!-- Category -->
                <div class="col-span-2">
                    <Label>Category *</Label>
                    <select
                        v-model="form.account_id"
                        required
                        class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm w-full mt-1"
                    >
                        <option value="" disabled>Select expense category…</option>
                        <option v-for="a in expense_accounts" :key="a.id" :value="a.id">
                            {{ a.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.account_id" class="text-destructive text-xs mt-1">{{ form.errors.account_id }}</p>
                </div>

                <!-- Date -->
                <div>
                    <Label>Date *</Label>
                    <Input v-model="form.expense_date" type="date" required class="mt-1" />
                    <p v-if="form.errors.expense_date" class="text-destructive text-xs mt-1">{{ form.errors.expense_date }}</p>
                </div>

                <!-- Amount -->
                <div>
                    <Label>Amount (Rs) *</Label>
                    <Input
                        v-model.number="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        class="mt-1"
                        placeholder="0.00"
                    />
                    <p v-if="form.errors.amount" class="text-destructive text-xs mt-1">{{ form.errors.amount }}</p>
                </div>

                <!-- Payment Method -->
                <div class="col-span-2">
                    <Label>Payment Method *</Label>
                    <div class="flex gap-3 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.payment_method" value="cash" class="accent-primary" />
                            <span class="text-sm">Cash</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.payment_method" value="bank" class="accent-primary" />
                            <span class="text-sm">Bank</span>
                        </label>
                    </div>
                </div>

                <!-- Description -->
                <div class="col-span-2">
                    <Label>Description</Label>
                    <Input v-model="form.description" class="mt-1" placeholder="Brief description of expense" />
                </div>

                <!-- Reference -->
                <div>
                    <Label>Reference / Receipt #</Label>
                    <Input v-model="form.reference" class="mt-1" placeholder="INV-001" />
                </div>

                <!-- Notes -->
                <div>
                    <Label>Notes</Label>
                    <Input v-model="form.notes" class="mt-1" />
                </div>

                <DialogFooter class="col-span-2 pt-2">
                    <Button type="button" variant="outline" @click="showModal = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ editTarget ? 'Save Changes' : 'Add Expense' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
