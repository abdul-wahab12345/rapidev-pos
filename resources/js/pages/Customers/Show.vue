<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatMoney, formatDateTime } from '@/utils/format';
import { paymentBadge, ledgerTypeBadge } from '@/constants/badges';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, BookOpen, CreditCard, Edit, Phone,
    ShoppingBag, Trash2, Wallet,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface LedgerEntry {
    id: string;
    type: string;
    amount: number;
    running_balance: number;
    description: string | null;
    payment_method: string | null;
    created_at: string;
}

interface SaleRow {
    id: string;
    invoice_number: string;
    total: number;
    udhaar_amount: number;
    payment_method: string;
    status: string;
    created_at: string;
    branch: string | null;
}

interface Customer {
    id: string;
    name: string;
    phone: string | null;
    cnic: string | null;
    address: string | null;
    notes: string | null;
    current_balance: number;
    credit_limit: number;
    total_spend: number;
    created_at: string;
}

const props = defineProps<{
    customer: Customer;
    ledger: {
        data: LedgerEntry[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    recent_sales: SaleRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
    { title: props.customer.name, href: '#' },
];

const { confirm } = useConfirm();

// ── Record payment modal ──────────────────────────────────────
const showPaymentModal = ref(false);
const paymentForm = useForm({
    amount: '',
    method: 'cash',
    notes:  '',
});

function submitPayment() {
    paymentForm.post(route('customers.payment', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
        },
    });
}

// ── Delete ────────────────────────────────────────────────────
async function deleteCustomer() {
    const ok = await confirm({
        title: `Delete ${props.customer.name}?`,
        message: 'This will permanently remove this customer. Sales history is kept.',
        confirmLabel: 'Delete',
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(route('customers.destroy', props.customer.id));
}

// ── Helpers ───────────────────────────────────────────────────
const fmt = formatMoney;
const fmtDate = (dt: string) => formatDateTime(dt);
const typeLabel = ledgerTypeBadge;
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl">

            <!-- Header -->
            <div class="flex flex-wrap items-start gap-3">
                <Link href="/customers" class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mt-1">
                    <ArrowLeft class="h-4 w-4" /> Back
                </Link>

                <div class="flex-1">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ customer.name }}</h1>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span v-if="customer.phone" class="flex items-center gap-1">
                            <Phone class="h-3.5 w-3.5" />{{ customer.phone }}
                        </span>
                        <span v-if="customer.cnic">CNIC: {{ customer.cnic }}</span>
                        <span v-if="customer.address">{{ customer.address }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Record Payment -->
                    <button
                        v-if="customer.current_balance > 0"
                        @click="showPaymentModal = true"
                        class="flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 transition-colors"
                    >
                        <Wallet class="h-4 w-4" />
                        Record Payment
                    </button>
                    <Link
                        :href="route('customers.edit', customer.id)"
                        class="flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors"
                    >
                        <Edit class="h-4 w-4" /> Edit
                    </Link>
                    <button
                        @click="deleteCustomer"
                        class="flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-muted-foreground hover:border-destructive/50 hover:text-destructive transition-colors"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-xl border p-4" :class="customer.current_balance > 0 ? 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-950/20' : 'border-border bg-card'">
                    <p class="text-xs font-medium" :class="customer.current_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                        Outstanding Udhaar
                    </p>
                    <p class="mt-1.5 text-2xl font-black" :class="customer.current_balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                        {{ customer.current_balance > 0 ? fmt(customer.current_balance) : 'Clear' }}
                    </p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Total Spend</p>
                    <p class="mt-1.5 text-2xl font-black text-green-600 dark:text-green-400">{{ fmt(customer.total_spend) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs font-medium text-muted-foreground">Credit Limit</p>
                    <p class="mt-1.5 text-2xl font-black text-foreground">
                        {{ customer.credit_limit > 0 ? fmt(customer.credit_limit) : '—' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <!-- Ledger (2/3) -->
                <div class="lg:col-span-2">
                    <div class="mb-3 flex items-center gap-2">
                        <BookOpen class="h-4 w-4 text-muted-foreground" />
                        <h2 class="font-semibold text-foreground">Ledger History</h2>
                        <span class="ml-auto text-xs text-muted-foreground">{{ ledger.total }} entries</span>
                    </div>

                    <div class="rounded-xl border border-border overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="px-4 py-2.5">Date</th>
                                    <th class="px-4 py-2.5">Type</th>
                                    <th class="px-4 py-2.5">Description</th>
                                    <th class="px-4 py-2.5 text-right">Amount</th>
                                    <th class="px-4 py-2.5 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="ledger.data.length === 0">
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">No ledger entries yet</td>
                                </tr>
                                <tr v-for="entry in ledger.data" :key="entry.id" class="hover:bg-muted/20">
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground whitespace-nowrap">{{ fmtDate(entry.created_at) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span :class="typeLabel[entry.type]?.class ?? 'bg-muted text-muted-foreground'" class="rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize">
                                            {{ typeLabel[entry.type]?.label ?? entry.type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground">
                                        {{ entry.description || '—' }}
                                        <span v-if="entry.payment_method" class="ml-1 capitalize">({{ entry.payment_method }})</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-sm font-semibold" :class="entry.amount < 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                        {{ entry.amount < 0 ? '−' : '+' }}{{ fmt(Math.abs(entry.amount)) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-sm font-bold text-foreground">
                                        {{ fmt(entry.running_balance) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ledger pagination -->
                    <div v-if="ledger.last_page > 1" class="mt-3 flex justify-end gap-1">
                        <template v-for="link in ledger.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="['rounded-md border px-3 py-1 text-xs transition-colors', link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent']"
                                v-html="link.label"
                            />
                            <span v-else class="rounded-md border border-border px-3 py-1 text-xs opacity-40" v-html="link.label" />
                        </template>
                    </div>
                </div>

                <!-- Right: recent sales + notes -->
                <div class="space-y-4">

                    <!-- Recent sales -->
                    <div>
                        <div class="mb-3 flex items-center gap-2">
                            <ShoppingBag class="h-4 w-4 text-muted-foreground" />
                            <h2 class="font-semibold text-foreground">Recent Sales</h2>
                        </div>
                        <div class="space-y-2">
                            <div v-if="recent_sales.length === 0" class="rounded-xl border border-border bg-card p-4 text-center text-sm text-muted-foreground">
                                No sales yet
                            </div>
                            <Link
                                v-for="s in recent_sales"
                                :key="s.id"
                                :href="route('sales.show', s.id)"
                                class="block rounded-xl border border-border bg-card p-3 hover:bg-accent transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-semibold text-primary">{{ s.invoice_number }}</span>
                                    <span class="text-sm font-bold text-foreground">{{ fmt(s.total) }}</span>
                                </div>
                                <div class="mt-0.5 flex items-center justify-between">
                                    <span class="text-xs text-muted-foreground">{{ fmtDate(s.created_at) }}</span>
                                    <span v-if="s.udhaar_amount > 0" class="text-xs text-amber-600 dark:text-amber-400">
                                        Udhaar: {{ fmt(s.udhaar_amount) }}
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="customer.notes" class="rounded-xl border border-border bg-card p-4">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Notes</h3>
                        <p class="text-sm text-foreground">{{ customer.notes }}</p>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── Record Payment Modal ── -->
        <Teleport to="body">
            <div
                v-if="showPaymentModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                @click.self="showPaymentModal = false"
            >
                <div class="w-full max-w-sm rounded-2xl border border-border bg-card p-6 shadow-2xl">
                    <h2 class="mb-1 text-base font-bold text-foreground">Record Payment</h2>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Outstanding: <span class="font-semibold text-red-600 dark:text-red-400">{{ fmt(customer.current_balance) }}</span>
                    </p>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Amount (Rs) *</label>
                            <input
                                v-model="paymentForm.amount"
                                type="number"
                                min="1"
                                :max="customer.current_balance"
                                :placeholder="`Max: ${Math.round(customer.current_balance)}`"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <p v-if="paymentForm.errors.amount" class="mt-1 text-xs text-destructive">{{ paymentForm.errors.amount }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Payment Method</label>
                            <select v-model="paymentForm.method" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="cash">Cash</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Notes <span class="text-muted-foreground/60">(optional)</span></label>
                            <input
                                v-model="paymentForm.notes"
                                type="text"
                                placeholder="e.g. Partial payment"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <button
                            @click="showPaymentModal = false"
                            class="flex-1 rounded-xl border border-border py-2.5 text-sm text-muted-foreground hover:bg-accent transition-colors"
                        >Cancel</button>
                        <button
                            @click="submitPayment"
                            :disabled="paymentForm.processing"
                            class="flex-1 rounded-xl bg-green-600 py-2.5 text-sm font-bold text-white hover:bg-green-500 transition-colors disabled:opacity-60"
                        >
                            {{ paymentForm.processing ? 'Saving…' : 'Record Payment' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
