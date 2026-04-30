<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';
import { useReceipt } from '@/composables/useReceipt';
import { formatMoney, formatDateTime } from '@/utils/format';
import { paymentBadge } from '@/constants/badges';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Printer } from 'lucide-vue-next';

const { confirm } = useConfirm();

interface SaleItem {
    id: string;
    product_name: string;
    variant_label: string | null;
    quantity: number;
    unit_price: number;
    cost_price: number;
    discount: number;
    line_total: number;
}

interface Sale {
    id: string;
    invoice_number: string;
    status: string;
    created_at: string;
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
    paid: number;
    change_amount: number;
    cash_amount: number;
    jazzcash_amount: number;
    easypaisa_amount: number;
    udhaar_amount: number;
    payment_method: string;
    notes: string | null;
    customer: { id: string; name: string; phone: string } | null;
    cashier: { id: number; name: string } | null;
    branch: { id: string; name: string } | null;
    items: SaleItem[];
}

const props = defineProps<{ sale: Sale }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales' },
    { title: props.sale.invoice_number, href: '#' },
];

const fmt = formatMoney;
const fmtDate = (dt: string) => formatDateTime(dt, true);

async function voidSale() {
    const ok = await confirm({
        title: `Void ${props.sale.invoice_number}?`,
        message: 'This will restore stock for all items and reverse any udhaar balance. This cannot be undone.',
        confirmLabel: 'Yes, void sale',
        cancelLabel: 'Cancel',
        variant: 'danger',
    });
    if (!ok) return;
    router.post(route('sales.void', props.sale.id));
}

// ── Reprint receipt ───────────────────────────────────────────
const { printReceipt: openReceiptPrint } = useReceipt();

function printReceipt() {
    const sale = props.sale;
    openReceiptPrint({
        invoice_number:   sale.invoice_number,
        created_at:       sale.created_at,
        business_name:    sale.branch?.name || 'Bithouse POS',
        branch_name:      null,
        cashier_name:     sale.cashier?.name ?? null,
        customer_name:    sale.customer?.name ?? null,
        customer_phone:   sale.customer?.phone ?? null,
        items: sale.items.map(item => ({
            name:          item.product_name,
            variant_label: item.variant_label,
            quantity:      item.quantity,
            unit_price:    item.unit_price,
            line_total:    item.line_total,
            discount:      item.discount,
        })),
        subtotal:          sale.subtotal,
        discount:          sale.discount,
        tax:               sale.tax,
        total:             sale.total,
        payment_method:    sale.payment_method,
        cash_amount:       sale.cash_amount,
        jazzcash_amount:   sale.jazzcash_amount,
        easypaisa_amount:  sale.easypaisa_amount,
        udhaar_amount:     sale.udhaar_amount,
        change_amount:     sale.change_amount,
    });
}
</script>

<template>
    <Head :title="`Sale ${sale.invoice_number}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-4xl">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-3">
                <Link href="/sales" class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors">
                    <ArrowLeft class="h-4 w-4" /> Back
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="font-mono text-2xl font-black tracking-tight">{{ sale.invoice_number }}</h1>
                        <span
                            :class="sale.status === 'completed'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'"
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                        >{{ sale.status }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ fmtDate(sale.created_at) }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="printReceipt"
                        class="flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors"
                    >
                        <Printer class="h-4 w-4" />
                        Print Receipt
                    </button>
                    <button
                        v-if="sale.status === 'completed'"
                        @click="voidSale"
                        class="flex items-center gap-2 rounded-lg border border-destructive/30 px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors"
                    >
                        <AlertTriangle class="h-4 w-4" />
                        Void Sale
                    </button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <!-- Items table (2/3 width) -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl border border-border overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3 text-center">Qty</th>
                                    <th class="px-4 py-3 text-right">Unit Price</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="item in sale.items" :key="item.id" class="hover:bg-muted/20">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-foreground">{{ item.product_name }}</p>
                                        <p v-if="item.variant_label" class="text-xs text-muted-foreground">{{ item.variant_label }}</p>
                                        <p v-if="item.discount > 0" class="text-xs text-green-600 dark:text-green-400">Disc: −{{ fmt(item.discount) }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center text-muted-foreground">{{ item.quantity }}</td>
                                    <td class="px-4 py-3 text-right text-muted-foreground">{{ fmt(item.unit_price) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-foreground">{{ fmt(item.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="mt-4 rounded-xl border border-border bg-card p-4">
                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-muted-foreground">
                                <span>Subtotal</span><span>{{ fmt(sale.subtotal) }}</span>
                            </div>
                            <div v-if="sale.discount > 0" class="flex justify-between text-green-600 dark:text-green-400">
                                <span>Discount</span><span>−{{ fmt(sale.discount) }}</span>
                            </div>
                            <div v-if="sale.tax > 0" class="flex justify-between text-muted-foreground">
                                <span>Tax</span><span>{{ fmt(sale.tax) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-border pt-2 text-base font-bold text-foreground">
                                <span>Total</span>
                                <span class="text-primary">{{ fmt(sale.total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right sidebar: meta + payment -->
                <div class="space-y-4">

                    <!-- Sale info -->
                    <div class="rounded-xl border border-border bg-card p-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sale Info</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Branch</dt>
                                <dd class="font-medium text-foreground">{{ sale.branch?.name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Cashier</dt>
                                <dd class="font-medium text-foreground">{{ sale.cashier?.name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Customer</dt>
                                <dd class="font-medium text-foreground">{{ sale.customer?.name ?? 'Walk-in' }}</dd>
                            </div>
                            <div v-if="sale.customer?.phone" class="flex justify-between">
                                <dt class="text-muted-foreground">Phone</dt>
                                <dd class="font-medium text-foreground">{{ sale.customer.phone }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Payment breakdown -->
                    <div class="rounded-xl border border-border bg-card p-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Payment</h3>

                        <span
                            :class="paymentBadge[sale.payment_method] ?? 'bg-muted text-muted-foreground'"
                            class="mb-3 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                        >{{ sale.payment_method }}</span>

                        <dl class="mt-2 space-y-1.5 text-sm">
                            <div v-if="sale.cash_amount > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">Cash</dt>
                                <dd class="font-medium">{{ fmt(sale.cash_amount) }}</dd>
                            </div>
                            <div v-if="sale.jazzcash_amount > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">JazzCash</dt>
                                <dd class="font-medium">{{ fmt(sale.jazzcash_amount) }}</dd>
                            </div>
                            <div v-if="sale.easypaisa_amount > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">Easypaisa</dt>
                                <dd class="font-medium">{{ fmt(sale.easypaisa_amount) }}</dd>
                            </div>
                            <div v-if="sale.udhaar_amount > 0" class="flex justify-between text-amber-600 dark:text-amber-400">
                                <dt>Udhaar</dt>
                                <dd class="font-semibold">{{ fmt(sale.udhaar_amount) }}</dd>
                            </div>
                            <div v-if="sale.change_amount > 0" class="flex justify-between border-t border-border pt-1.5 text-green-600 dark:text-green-400">
                                <dt>Change Given</dt>
                                <dd class="font-semibold">{{ fmt(sale.change_amount) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Notes -->
                    <div v-if="sale.notes" class="rounded-xl border border-border bg-card p-4">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Notes</h3>
                        <p class="text-sm text-foreground">{{ sale.notes }}</p>
                    </div>

                </div>
            </div>

        </div>
    </AppLayout>
</template>
