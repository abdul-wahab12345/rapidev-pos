<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BookOpen,
    CircleDollarSign,
    Landmark,
    Layers,
    Minus,
    Plus,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Accounts', href: '/accounts' }];

interface AccountItem {
    id: string; code: string; name: string; type: string;
    sub_type: string | null; is_system: boolean; is_active: boolean;
}

interface JournalLine { account_code: string; account_name: string; debit: number; credit: number; description: string | null }
interface JournalEntryRow {
    id: string; entry_number: string; entry_date: string; description: string;
    reference_type: string | null; status: string; created_by: string | null;
    total_debit: number; lines: JournalLine[];
}

interface AccountListItem { id: string; code: string; name: string; type: string }

const props = defineProps<{
    accounts: Record<string, AccountItem[]>;
    entries: { data: JournalEntryRow[]; current_page: number; last_page: number; total: number };
    account_list: AccountListItem[];
    filters: { tab?: string };
}>();

const tab = ref<'chart' | 'journal'>(props.filters.tab === 'journal' ? 'journal' : 'chart');

// ── Stats ───────────────────────────────────────────────
const allAccounts  = computed(() => Object.values(props.accounts).flat());
const activeCount  = computed(() => allAccounts.value.filter(a => a.is_active).length);
const journalCount = computed(() => props.entries.total);

// ── Type styling ────────────────────────────────────────
const typeStyle: Record<string, { bg: string; text: string; label: string }> = {
    asset:     { bg: 'bg-blue-500/10',    text: 'text-blue-600 dark:text-blue-400',    label: 'Asset' },
    liability: { bg: 'bg-red-500/10',     text: 'text-red-600 dark:text-red-400',      label: 'Liability' },
    equity:    { bg: 'bg-purple-500/10',  text: 'text-purple-600 dark:text-purple-400',label: 'Equity' },
    income:    { bg: 'bg-emerald-500/10', text: 'text-emerald-600 dark:text-emerald-400',label: 'Income' },
    expense:   { bg: 'bg-amber-500/10',   text: 'text-amber-600 dark:text-amber-400',  label: 'Expense' },
};

const typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
const sortedTypes = computed(() => typeOrder.filter(t => props.accounts[t]?.length));

// ── Add Account Modal ────────────────────────────────────
const showAddAccount = ref(false);
const accountForm = useForm({
    code: '', name: '', type: 'asset' as string,
    sub_type: '', description: '',
});

function submitAccount() {
    accountForm.post(route('accounts.store-account'), {
        preserveScroll: true,
        onSuccess: () => { showAddAccount.value = false; accountForm.reset(); },
    });
}

// ── Journal Entry Modal ──────────────────────────────────
const showJournal = ref(false);
const expandedEntry = ref<string | null>(null);

interface LineRow { account_id: string; debit: number | ''; credit: number | ''; description: string }

const journalForm = useForm({
    entry_date:  new Date().toISOString().slice(0, 10),
    description: '',
    lines: [
        { account_id: '', debit: 0 as number | '', credit: '' as number | '', description: '' },
        { account_id: '', debit: '' as number | '', credit: 0 as number | '', description: '' },
    ] as LineRow[],
});

function addLine() {
    journalForm.lines.push({ account_id: '', debit: '', credit: '', description: '' });
}

function removeLine(i: number) {
    if (journalForm.lines.length > 2) journalForm.lines.splice(i, 1);
}

const totalDebit  = computed(() => journalForm.lines.reduce((s, l) => s + (Number(l.debit)  || 0), 0));
const totalCredit = computed(() => journalForm.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0));
const isBalanced  = computed(() => Math.abs(totalDebit.value - totalCredit.value) < 0.01);

function submitJournal() {
    journalForm.post(route('accounts.store-entry'), {
        preserveScroll: true,
        onSuccess: () => {
            showJournal.value = false;
            journalForm.reset();
            journalForm.lines = [
                { account_id: '', debit: 0, credit: '', description: '' },
                { account_id: '', debit: '', credit: 0, description: '' },
            ];
            journalForm.entry_date = new Date().toISOString().slice(0, 10);
        },
    });
}

function deleteEntry(id: string) {
    router.delete(route('accounts.delete-entry', id), { preserveScroll: true });
}

function fmtDate(d: string) {
    return new Date(d).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
}

const refTypeBadge: Record<string, string> = {
    manual:  'bg-muted text-muted-foreground',
    sale:    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    payment: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    expense: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
};
</script>

<template>
    <Head title="Accounts" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-4 sm:px-6">
            <div>
                <h1 class="text-2xl font-bold text-foreground">Accounts</h1>
                <p class="text-sm text-muted-foreground">Chart of Accounts & Journal Entries</p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="tab = 'chart'; showAddAccount = true" class="gap-1.5 text-sm">
                    <Plus class="h-4 w-4" /> Add Account
                </Button>
                <Button @click="showJournal = true" class="gap-1.5 text-sm">
                    <BookOpen class="h-4 w-4" /> New Journal Entry
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:px-6">
            <StatCard label="Total Accounts"   :value="allAccounts.length"  :icon="Layers" />
            <StatCard label="Active Accounts"  :value="activeCount"         :icon="CircleDollarSign" tone="success" />
            <StatCard label="Journal Entries"  :value="journalCount"        :icon="BookOpen" />
            <StatCard label="Reports"          value="View →"               :icon="TrendingUp" tone="info"
                      description="P&L, Balance Sheet, Trial Balance" />
        </div>

        <!-- Tabs -->
        <div class="mt-4 border-b border-border px-4 sm:px-6">
            <div class="flex gap-1">
                <button v-for="t in [
                    { id: 'chart', label: 'Chart of Accounts', icon: Landmark },
                    { id: 'journal', label: 'Journal Entries', icon: BookOpen },
                ]" :key="t.id"
                    @click="tab = t.id as 'chart' | 'journal'"
                    :class="[
                        'flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
                        tab === t.id ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    <component :is="t.icon" class="h-4 w-4" />
                    {{ t.label }}
                </button>
            </div>
        </div>

        <!-- Chart of Accounts Tab -->
        <div v-if="tab === 'chart'" class="px-4 py-4 sm:px-6 space-y-4">
            <div v-for="type in sortedTypes" :key="type" class="rounded-xl border border-border bg-card overflow-hidden">
                <div :class="['flex items-center gap-3 px-4 py-3 border-b border-border', typeStyle[type]?.bg]">
                    <span :class="['text-sm font-semibold', typeStyle[type]?.text]">
                        {{ typeStyle[type]?.label }}
                    </span>
                    <span class="text-xs text-muted-foreground">({{ accounts[type]?.length }} accounts)</span>
                </div>
                <table class="w-full text-sm">
                        <thead class="bg-muted/30">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground w-24">Code</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground hidden sm:table-cell">Sub-type</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-muted-foreground">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-muted-foreground"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="acc in accounts[type]" :key="acc.id" class="hover:bg-muted/20 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">{{ acc.code }}</td>
                            <td class="px-4 py-2.5">
                                <span class="font-medium text-foreground">{{ acc.name }}</span>
                                <span v-if="acc.is_system" class="ml-2 text-[10px] bg-muted text-muted-foreground px-1.5 py-0.5 rounded-full">System</span>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground hidden sm:table-cell capitalize">
                                {{ acc.sub_type?.replace(/_/g, ' ') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        acc.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground',
                                    ]">
                                        {{ acc.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <a
                                        :href="route('accounts.ledger', { account: acc.id })"
                                        class="text-xs text-primary hover:underline"
                                    >Ledger →</a>
                                </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Journal Entries Tab -->
        <div v-else class="px-4 py-4 sm:px-6 space-y-3">
            <div v-if="entries.data.length === 0" class="rounded-xl border border-border bg-card px-4 py-12 text-center text-muted-foreground">
                No journal entries yet. Click "New Journal Entry" to post your first entry.
            </div>

            <div
                v-for="entry in entries.data" :key="entry.id"
                class="rounded-xl border border-border bg-card overflow-hidden"
            >
                <div
                    class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-muted/30 transition-colors"
                    @click="expandedEntry = expandedEntry === entry.id ? null : entry.id"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="font-mono text-sm font-semibold text-foreground shrink-0">{{ entry.entry_number }}</span>
                        <span class="text-sm text-muted-foreground shrink-0">{{ fmtDate(entry.entry_date) }}</span>
                        <span class="text-sm text-foreground truncate">{{ entry.description }}</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-2">
                        <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', refTypeBadge[entry.reference_type ?? 'manual'] ?? refTypeBadge.manual]">
                            {{ entry.reference_type ?? 'manual' }}
                        </span>
                        <span class="text-sm font-semibold tabular-nums text-foreground">{{ formatMoney(entry.total_debit) }}</span>
                        <Button
                            v-if="entry.reference_type === 'manual' || !entry.reference_type"
                            variant="ghost" size="sm"
                            class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                            @click.stop="deleteEntry(entry.id)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- Expanded lines -->
                <div v-if="expandedEntry === entry.id" class="border-t border-border">
                    <table class="w-full text-xs">
                        <thead class="bg-muted/30">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground">Account</th>
                                <th class="px-4 py-2 text-left font-medium text-muted-foreground hidden sm:table-cell">Notes</th>
                                <th class="px-4 py-2 text-right font-medium text-muted-foreground">Debit</th>
                                <th class="px-4 py-2 text-right font-medium text-muted-foreground">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(line, i) in entry.lines" :key="i">
                                <td class="px-4 py-2">
                                    <span class="font-mono text-muted-foreground">{{ line.account_code }}</span>
                                    <span class="ml-2 text-foreground">{{ line.account_name }}</span>
                                </td>
                                <td class="px-4 py-2 text-muted-foreground hidden sm:table-cell">{{ line.description ?? '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ line.debit > 0 ? formatMoney(line.debit) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ line.credit > 0 ? formatMoney(line.credit) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end gap-8 border-t border-border bg-muted/20 px-4 py-2 text-xs font-semibold">
                        <span>Total Dr: {{ formatMoney(entry.total_debit) }}</span>
                        <span>Total Cr: {{ formatMoney(entry.total_debit) }}</span>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="entries.last_page > 1" class="flex justify-end">
                <div class="flex gap-1">
                    <Button variant="outline" size="sm" :disabled="entries.current_page === 1"
                        @click="router.get(route('accounts.index'), { tab: 'journal', page: entries.current_page - 1 }, { preserveState: true })">
                        ‹ Prev
                    </Button>
                    <span class="flex items-center px-3 text-sm text-muted-foreground">
                        {{ entries.current_page }} / {{ entries.last_page }}
                    </span>
                    <Button variant="outline" size="sm" :disabled="entries.current_page === entries.last_page"
                        @click="router.get(route('accounts.index'), { tab: 'journal', page: entries.current_page + 1 }, { preserveState: true })">
                        Next ›
                    </Button>
                </div>
            </div>
        </div>

    </AppLayout>

    <!-- Add Account Modal -->
    <Dialog v-model:open="showAddAccount">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle class="flex items-center gap-2"><BadgeDollarSign class="h-5 w-5" /> Add Account</DialogTitle></DialogHeader>
            <div class="space-y-4 py-2">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label>Code <span class="text-destructive">*</span></Label>
                        <Input v-model="accountForm.code" placeholder="e.g. 5110" />
                        <p v-if="accountForm.errors.code" class="text-xs text-destructive">{{ accountForm.errors.code }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Type <span class="text-destructive">*</span></Label>
                        <Select v-model="accountForm.type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="asset">Asset</SelectItem>
                                <SelectItem value="liability">Liability</SelectItem>
                                <SelectItem value="equity">Equity</SelectItem>
                                <SelectItem value="income">Income</SelectItem>
                                <SelectItem value="expense">Expense</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label>Account Name <span class="text-destructive">*</span></Label>
                    <Input v-model="accountForm.name" placeholder="e.g. Packaging Materials" />
                    <p v-if="accountForm.errors.name" class="text-xs text-destructive">{{ accountForm.errors.name }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Description <span class="text-muted-foreground">(optional)</span></Label>
                    <Input v-model="accountForm.description" placeholder="Brief description" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showAddAccount = false">Cancel</Button>
                <Button @click="submitAccount" :disabled="accountForm.processing">
                    {{ accountForm.processing ? 'Saving…' : 'Create Account' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- New Journal Entry Modal -->
    <Dialog v-model:open="showJournal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <BookOpen class="h-5 w-5" /> New Journal Entry
                </DialogTitle>
            </DialogHeader>
            <div class="space-y-4 py-2 max-h-[70vh] overflow-y-auto pr-1">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label>Date <span class="text-destructive">*</span></Label>
                        <Input v-model="journalForm.entry_date" type="date" />
                    </div>
                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <Label>Description <span class="text-destructive">*</span></Label>
                        <Input v-model="journalForm.description" placeholder="e.g. Monthly rent payment" />
                        <p v-if="journalForm.errors.description" class="text-xs text-destructive">{{ journalForm.errors.description }}</p>
                    </div>
                </div>

                <!-- Lines -->
                <div class="space-y-2">
                    <div class="grid grid-cols-12 gap-1 text-xs font-medium text-muted-foreground px-1">
                        <span class="col-span-5">Account</span>
                        <span class="col-span-2 text-right">Debit</span>
                        <span class="col-span-2 text-right">Credit</span>
                        <span class="col-span-2">Notes</span>
                        <span class="col-span-1"></span>
                    </div>

                    <div v-for="(line, i) in journalForm.lines" :key="i" class="grid grid-cols-12 gap-1 items-center">
                        <div class="col-span-5">
                            <Select v-model="line.account_id">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select account" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="a in account_list" :key="a.id" :value="a.id">
                                        {{ a.code }} – {{ a.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="col-span-2">
                            <Input v-model.number="line.debit" type="number" min="0" step="0.01" placeholder="0.00" class="h-8 text-xs text-right" />
                        </div>
                        <div class="col-span-2">
                            <Input v-model.number="line.credit" type="number" min="0" step="0.01" placeholder="0.00" class="h-8 text-xs text-right" />
                        </div>
                        <div class="col-span-2">
                            <Input v-model="line.description" placeholder="Note" class="h-8 text-xs" />
                        </div>
                        <div class="col-span-1 flex justify-center">
                            <button @click="removeLine(i)" :disabled="journalForm.lines.length <= 2"
                                class="text-muted-foreground hover:text-destructive disabled:opacity-30 p-1">
                                <Minus class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <button @click="addLine" class="flex items-center gap-1.5 text-xs text-primary hover:underline mt-1 px-1">
                        <Plus class="h-3.5 w-3.5" /> Add line
                    </button>
                </div>

                <!-- Totals row -->
                <div :class="[
                    'flex items-center justify-end gap-6 rounded-lg border px-4 py-2.5 text-sm font-semibold',
                    isBalanced ? 'border-emerald-500/20 bg-emerald-500/5 text-emerald-700 dark:text-emerald-400' : 'border-amber-500/20 bg-amber-500/5 text-amber-700 dark:text-amber-400',
                ]">
                    <span>Dr: {{ formatMoney(totalDebit) }}</span>
                    <span>Cr: {{ formatMoney(totalCredit) }}</span>
                    <span>{{ isBalanced ? '✓ Balanced' : '⚠ Not balanced' }}</span>
                </div>

                <p v-if="journalForm.errors.lines" class="text-xs text-destructive">{{ journalForm.errors.lines }}</p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showJournal = false">Cancel</Button>
                <Button @click="submitJournal" :disabled="journalForm.processing || !isBalanced">
                    {{ journalForm.processing ? 'Posting…' : 'Post Entry' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
