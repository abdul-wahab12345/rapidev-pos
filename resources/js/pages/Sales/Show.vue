<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';
import { useReceipt } from '@/composables/useReceipt';
import { formatMoney, formatDateTime } from '@/utils/format';
import { paymentBadge } from '@/constants/badges';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ArrowUpLeft, Eye, Printer, RotateCcw } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

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
    quantity_returnable: number;
}

interface SaleReturnRow {
    id: string;
    return_number: string;
    return_date: string;
    refund_method: string;
    total_refund: number;
    reason: string | null;
    items_count: number;
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
    returns: SaleReturnRow[];
}

const props = defineProps<{ sale: Sale }>();

// ── Return modal ──────────────────────────────────────────────
const showReturnModal = ref(false);

// Per-item return quantities keyed by sale_item_id
const returnQtys    = reactive<Record<string, number>>({});
const returnRestock = reactive<Record<string, boolean>>({});

function openReturnModal() {
    props.sale.items.forEach(item => {
        returnQtys[item.id]    = item.quantity_returnable > 0 ? item.quantity_returnable : 0;
        returnRestock[item.id] = true;
    });
    returnForm.refund_method = 'cash';
    returnForm.reason        = '';
    returnForm.notes         = '';
    showReturnModal.value    = true;
}

const returnForm = useForm({
    refund_method: 'cash',
    reason:        '',
    notes:         '',
    items:         [] as { sale_item_id: string; quantity_returned: number; restock: boolean }[],
});

const returnableItems = computed(() =>
    props.sale.items.filter(i => i.quantity_returnable > 0)
);

const computedRefundTotal = computed(() =>
    props.sale.items.reduce((sum, item) => {
        const qty = Math.min(returnQtys[item.id] ?? 0, item.quantity_returnable);
        return sum + item.unit_price * qty;
    }, 0)
);

function submitReturn() {
    const items = props.sale.items
        .map(item => ({
            sale_item_id:      item.id,
            quantity_returned: Math.min(returnQtys[item.id] ?? 0, item.quantity_returnable),
            restock:           returnRestock[item.id] ?? true,
        }))
        .filter(i => i.quantity_returned > 0);

    if (!items.length) {
        alert('Select at least one item with quantity > 0 to return.');
        return;
    }

    returnForm.items = items;
    returnForm.post(route('sales.returns.store', props.sale.id), {
        onSuccess: () => { showReturnModal.value = false; },
    });
}

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
                        v-if="sale.status === 'completed' || sale.status === 'partially_returned'"
                        @click="openReturnModal"
                        class="flex items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-700 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
                    >
                        <RotateCcw class="h-4 w-4" />
                        Process Return
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

            <!-- Returns history -->
            <div v-if="sale.returns.length" class="rounded-xl border border-border overflow-hidden">
                <div class="bg-muted/50 px-4 py-3 flex items-center gap-2">
                    <ArrowUpLeft class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Returns</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/30">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-2">Return #</th>
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Method</th>
                            <th class="px-4 py-2 hidden sm:table-cell">Reason</th>
                            <th class="px-4 py-2 text-right">Refund</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="r in sale.returns" :key="r.id" class="hover:bg-muted/20">
                            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">{{ r.return_number }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ r.return_date }}</td>
                            <td class="px-4 py-2.5 text-xs capitalize">{{ r.refund_method.replace('_', ' ') }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground hidden sm:table-cell">{{ r.reason ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-red-600 dark:text-red-400 text-xs tabular-nums">
                                −{{ fmt(r.total_refund) }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <Link :href="route('returns.show', r.id)"
                                    class="text-xs text-primary hover:underline flex items-center justify-end gap-1">
                                    <Eye class="h-3 w-3" /> View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>

    <!-- Process Return Modal -->
    <Dialog :open="showReturnModal" @update:open="showReturnModal = $event">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Process Return — {{ sale.invoice_number }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 mt-2">

                <!-- Items table -->
                <div class="rounded-xl border overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2 text-center">Sold</th>
                                <th class="px-3 py-2 text-center">Returnable</th>
                                <th class="px-3 py-2 text-center w-24">Return Qty</th>
                                <th class="px-3 py-2 text-center">Restock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in sale.items" :key="item.id"
                                :class="item.quantity_returnable === 0 ? 'opacity-40' : ''"
                                class="hover:bg-muted/20">
                                <td class="px-3 py-2">
                                    <p class="font-medium text-xs">{{ item.product_name }}</p>
                                    <p v-if="item.variant_label" class="text-xs text-muted-foreground">{{ item.variant_label }}</p>
                                </td>
                                <td class="px-3 py-2 text-center text-muted-foreground text-xs">{{ item.quantity }}</td>
                                <td class="px-3 py-2 text-center text-xs"
                                    :class="item.quantity_returnable > 0 ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-muted-foreground'">
                                    {{ item.quantity_returnable }}
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        v-model.number="returnQtys[item.id]"
                                        type="number"
                                        :min="0"
                                        :max="item.quantity_returnable"
                                        :disabled="item.quantity_returnable === 0"
                                        class="w-full rounded border border-input bg-background px-2 py-1 text-center text-sm disabled:opacity-40"
                                    />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input
                                        type="checkbox"
                                        v-model="returnRestock[item.id]"
                                        :disabled="item.quantity_returnable === 0"
                                        class="rounded accent-primary disabled:opacity-40"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Refund method + reason -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label class="text-xs">Refund Method *</Label>
                        <div class="flex gap-3 mt-2">
                            <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" v-model="returnForm.refund_method" value="cash" class="accent-primary" /> Cash
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" v-model="returnForm.refund_method" value="bank" class="accent-primary" /> Bank
                            </label>
                            <label v-if="sale.customer" class="flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" v-model="returnForm.refund_method" value="store_credit" class="accent-primary" /> Store Credit
                            </label>
                        </div>
                        <p v-if="returnForm.refund_method === 'store_credit'" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Will reduce customer's udhaar balance.
                        </p>
                    </div>
                    <div>
                        <Label class="text-xs">Reason</Label>
                        <Input v-model="returnForm.reason" class="mt-1 text-sm" placeholder="Defective, wrong item…" />
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <Label class="text-xs">Internal Notes</Label>
                    <Input v-model="returnForm.notes" class="mt-1 text-sm" placeholder="Optional" />
                </div>

                <!-- Refund total preview -->
                <div class="rounded-lg bg-muted/50 px-4 py-3 flex justify-between items-center">
                    <span class="text-sm font-medium">Total Refund</span>
                    <span class="text-lg font-bold text-red-600 dark:text-red-400">
                        −{{ fmt(computedRefundTotal) }}
                    </span>
                </div>

            </div>

            <DialogFooter class="pt-2">
                <Button type="button" variant="outline" @click="showReturnModal = false">Cancel</Button>
                <Button
                    @click="submitReturn"
                    :disabled="returnForm.processing || computedRefundTotal === 0"
                    class="bg-amber-600 hover:bg-amber-700 text-white"
                >
                    Process Return
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
