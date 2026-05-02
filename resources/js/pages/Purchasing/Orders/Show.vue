<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, Ban, Building2, Calendar, CreditCard,
    DollarSign, Package, Receipt, RotateCcw, Trash2, Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface OrderItem {
    id: string;
    product_name: string;
    variant_label: string | null;
    quantity_ordered: number;
    quantity_received: number;
    unit_cost: number;
    line_total: number;
}

interface Payment {
    id: string;
    amount: number;
    payment_method: string;
    notes: string | null;
    is_voided: boolean;
    created_at: string;
}

interface SupplierReturnItem {
    product_name: string;
    variant_label: string | null;
    quantity_returned: number;
    unit_cost: number;
    line_total: number;
}

interface SupplierReturn {
    id: string;
    return_number: string;
    total_amount: number;
    reason: string | null;
    notes: string | null;
    created_at: string;
    items: SupplierReturnItem[];
}

interface PurchaseOrder {
    id: string;
    po_number: string;
    order_date: string;
    expected_date: string | null;
    received_date: string | null;
    status: string;
    payment_method: string;
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
    paid_amount: number;
    amount_due: number;
    notes: string | null;
    created_by: string;
    supplier: { id: string; name: string; phone: string | null; city: string | null };
    items: OrderItem[];
    payments: Payment[];
    returns: SupplierReturn[];
}

const props = defineProps<{ order: PurchaseOrder }>();

const { confirm } = useConfirm();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: props.order.po_number, href: '#' },
];

const statusConfig: Record<string, { cls: string; label: string }> = {
    draft:     { cls: 'bg-muted text-muted-foreground', label: 'Draft' },
    ordered:   { cls: 'bg-blue-500/10 text-blue-600 dark:text-blue-400', label: 'Ordered' },
    partial:   { cls: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', label: 'Partial' },
    received:  { cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', label: 'Received' },
    cancelled: { cls: 'bg-red-500/10 text-red-500', label: 'Cancelled' },
};

// ── Receive ────────────────────────────────────────────────────
const showReceive = ref(false);
const receiveForm = useForm({ items: props.order.items.map(i => ({ id: i.id, quantity_received: i.quantity_ordered })) });
function submitReceive() {
    receiveForm.post(`/purchasing/orders/${props.order.id}/receive`, {
        onSuccess: () => { showReceive.value = false; },
    });
}

const quickReceiveForm = useForm({ items: props.order.items.map(i => ({ id: i.id, quantity_received: i.quantity_ordered })) });
function quickReceiveAll() {
    if (!confirm(`Mark all items in ${props.order.po_number} as fully received?`)) return;
    quickReceiveForm.post(`/purchasing/orders/${props.order.id}/receive`);
}

// ── Pay ────────────────────────────────────────────────────────
const showPay = ref(false);
const payForm = useForm({ amount: props.order.amount_due, payment_method: 'cash', notes: '' });
function submitPay() {
    payForm.post(`/purchasing/orders/${props.order.id}/pay`, {
        onSuccess: () => { showPay.value = false; },
    });
}

// ── Void payment ───────────────────────────────────────────────
async function voidPayment(payment: Payment) {
    const ok = await confirm({
        title: `Void Payment of ${fmt(payment.amount)}?`,
        message: 'This will restore the AP balance for this supplier.',
        confirmLabel: 'Void Payment',
        variant: 'danger',
    });
    if (!ok) return;
    useForm({}).post(route('purchasing.orders.payments.void', { order: props.order.id, payment: payment.id }));
}

// ── Supplier return ────────────────────────────────────────────
const showReturn = ref(false);
const returnQtys = ref<Record<string, number>>({});

function openReturn() {
    returnQtys.value = Object.fromEntries(props.order.items.map(i => [i.id, i.quantity_received]));
    showReturn.value = true;
}

const returnForm = useForm({ reason: '', notes: '', items: [] as { purchase_order_item_id: string; quantity_returned: number }[] });

function submitReturn() {
    const items = props.order.items
        .map(i => ({ purchase_order_item_id: i.id, quantity_returned: returnQtys.value[i.id] ?? 0 }))
        .filter(i => i.quantity_returned > 0);

    if (!items.length) {
        alert('Enter at least one item quantity to return.');
        return;
    }

    returnForm.items = items;
    returnForm.post(route('purchasing.orders.returns.store', props.order.id), {
        onSuccess: () => { showReturn.value = false; },
    });
}

// ── Cancel ─────────────────────────────────────────────────────
function cancelOrder() {
    if (!confirm('Cancel this purchase order?')) return;
    useForm({}).post(`/purchasing/orders/${props.order.id}/cancel`);
}

const canReceive = computed(() => ['ordered', 'partial'].includes(props.order.status));
const canPay     = computed(() => props.order.amount_due > 0 && props.order.status !== 'cancelled');
const canReturn  = computed(() => ['received', 'partial'].includes(props.order.status));
const canCancel  = computed(() => !['received', 'cancelled'].includes(props.order.status));

const activePayments = computed(() => props.order.payments.filter(p => !p.is_voided));

function fmt(n: number) {
    if (n == null) return '—';
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}

function fmtDate(dt: string) {
    return new Date(dt).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head :title="order.po_number" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link href="/purchasing/orders">
                        <Button variant="outline" size="icon"><ArrowLeft :size="16" /></Button>
                    </Link>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-xl font-bold">{{ order.po_number }}</h1>
                            <span :class="statusConfig[order.status]?.cls"
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                                {{ statusConfig[order.status]?.label }}
                            </span>
                        </div>
                        <p class="text-muted-foreground text-sm mt-0.5">
                            Ordered {{ order.order_date }}
                            <span v-if="order.expected_date"> · Expected {{ order.expected_date }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex gap-2 flex-wrap justify-end">
                    <Button v-if="canReceive" @click="quickReceiveAll" :disabled="quickReceiveForm.processing"
                        variant="default" class="gap-2 bg-emerald-600 hover:bg-emerald-500">
                        <Truck :size="15" />
                        {{ quickReceiveForm.processing ? 'Saving…' : 'Mark All Received' }}
                    </Button>
                    <Button v-if="canReceive" variant="outline" @click="showReceive = true" class="gap-2">
                        <Truck :size="15" /> Partial Receive
                    </Button>
                    <Button v-if="canPay" variant="outline" @click="showPay = true" class="gap-2">
                        <CreditCard :size="15" /> Record Payment
                    </Button>
                    <Button v-if="canReturn" variant="outline" @click="openReturn" class="gap-2 text-amber-600 border-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                        <RotateCcw :size="15" /> Return Items
                    </Button>
                    <Button v-if="canCancel" variant="ghost" class="gap-2 text-destructive" @click="cancelOrder">
                        <Ban :size="15" /> Cancel
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- Left column -->
                <div class="flex flex-col gap-4 lg:col-span-1">

                    <!-- Supplier -->
                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm flex items-center gap-2">
                            <Building2 :size="15" /> Supplier
                        </h2>
                        <div>
                            <div class="font-medium">{{ order.supplier.name }}</div>
                            <div v-if="order.supplier.phone" class="text-muted-foreground text-xs mt-0.5">{{ order.supplier.phone }}</div>
                            <div v-if="order.supplier.city" class="text-muted-foreground text-xs">{{ order.supplier.city }}</div>
                        </div>
                        <Link :href="`/purchasing/orders/create?supplier=${order.supplier.id}`">
                            <Button variant="outline" size="sm" class="w-full gap-1">
                                <Package :size="13" /> New PO for this Supplier
                            </Button>
                        </Link>
                    </div>

                    <!-- Financials -->
                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm flex items-center gap-2">
                            <Receipt :size="15" /> Financials
                        </h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>{{ fmt(order.subtotal) }}</span>
                        </div>
                        <div v-if="order.discount > 0" class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Discount</span>
                            <span class="text-emerald-600">-{{ fmt(order.discount) }}</span>
                        </div>
                        <div v-if="order.tax > 0" class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Tax</span>
                            <span>{{ fmt(order.tax) }}</span>
                        </div>
                        <div class="border-t pt-2 flex justify-between font-bold">
                            <span>Total</span>
                            <span>{{ fmt(order.total) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Paid</span>
                            <span class="text-emerald-600">{{ fmt(order.paid_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold"
                            :class="order.amount_due > 0 ? 'text-orange-500' : 'text-emerald-600'">
                            <span>Amount Due</span>
                            <span>{{ fmt(order.amount_due) }}</span>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="border rounded-xl p-4 flex flex-col gap-2 text-sm">
                        <h2 class="font-semibold flex items-center gap-2">
                            <Calendar :size="15" /> Details
                        </h2>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Payment</span>
                            <span class="capitalize">{{ order.payment_method }}</span>
                        </div>
                        <div v-if="order.received_date" class="flex justify-between">
                            <span class="text-muted-foreground">Received</span>
                            <span>{{ order.received_date }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Created by</span>
                            <span>{{ order.created_by }}</span>
                        </div>
                        <div v-if="order.notes" class="text-muted-foreground text-xs mt-1 border-t pt-2">
                            {{ order.notes }}
                        </div>
                    </div>
                </div>

                <!-- Right column: items + payment history + returns -->
                <div class="lg:col-span-2 flex flex-col gap-6">

                    <!-- Items -->
                    <div class="border rounded-xl overflow-hidden">
                        <div class="bg-muted/50 px-4 py-2 text-xs font-medium flex gap-4">
                            <span class="flex-1">Product</span>
                            <span class="w-20 text-center">Ordered</span>
                            <span class="w-20 text-center">Received</span>
                            <span class="w-24 text-right">Unit Cost</span>
                            <span class="w-24 text-right">Total</span>
                        </div>
                        <div v-for="item in order.items" :key="item.id"
                            class="flex items-center gap-4 px-4 py-3 border-t text-sm">
                            <div class="flex-1">
                                <div class="font-medium">{{ item.product_name }}</div>
                                <div v-if="item.variant_label" class="text-muted-foreground text-xs">{{ item.variant_label }}</div>
                            </div>
                            <div class="w-20 text-center">{{ item.quantity_ordered }}</div>
                            <div class="w-20 text-center">
                                <span :class="item.quantity_received >= item.quantity_ordered
                                    ? 'text-emerald-600'
                                    : item.quantity_received > 0 ? 'text-amber-600' : 'text-muted-foreground'">
                                    {{ item.quantity_received }}
                                </span>
                            </div>
                            <div class="w-24 text-right">{{ fmt(item.unit_cost) }}</div>
                            <div class="w-24 text-right font-medium">{{ fmt(item.line_total) }}</div>
                        </div>
                    </div>

                    <!-- Payment History -->
                    <div v-if="order.payments.length" class="border rounded-xl overflow-hidden">
                        <div class="bg-muted/50 px-4 py-2.5 flex items-center gap-2">
                            <CreditCard :size="14" class="text-muted-foreground" />
                            <h3 class="text-sm font-semibold">Payment History</h3>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="bg-muted/30">
                                <tr class="text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Method</th>
                                    <th class="px-4 py-2">Notes</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="p in order.payments" :key="p.id"
                                    :class="p.is_voided ? 'opacity-50' : ''"
                                    class="hover:bg-muted/20">
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ fmtDate(p.created_at) }}</td>
                                    <td class="px-4 py-2.5 text-xs capitalize">{{ p.payment_method }}</td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ p.notes || '—' }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-xs"
                                        :class="p.is_voided ? 'line-through text-muted-foreground' : 'text-emerald-600'">
                                        {{ fmt(p.amount) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span v-if="p.is_voided" class="text-xs text-muted-foreground italic">Voided</span>
                                        <button
                                            v-else
                                            @click="voidPayment(p)"
                                            class="text-xs text-destructive hover:underline flex items-center gap-1 ml-auto"
                                            title="Void this payment"
                                        >
                                            <Trash2 :size="12" /> Void
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Supplier Returns History -->
                    <div v-if="order.returns.length" class="border rounded-xl overflow-hidden">
                        <div class="bg-muted/50 px-4 py-2.5 flex items-center gap-2">
                            <RotateCcw :size="14" class="text-muted-foreground" />
                            <h3 class="text-sm font-semibold">Supplier Returns</h3>
                        </div>
                        <div v-for="r in order.returns" :key="r.id" class="border-t px-4 py-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-xs font-semibold text-amber-600">{{ r.return_number }}</span>
                                <span class="font-bold text-red-600 dark:text-red-400">−{{ fmt(r.total_amount) }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-muted-foreground">
                                {{ fmtDate(r.created_at) }}
                                <span v-if="r.reason"> · {{ r.reason }}</span>
                            </div>
                            <div class="mt-2 space-y-0.5">
                                <div v-for="(item, i) in r.items" :key="i" class="flex justify-between text-xs text-muted-foreground">
                                    <span>{{ item.product_name }}<span v-if="item.variant_label"> ({{ item.variant_label }})</span> × {{ item.quantity_returned }}</span>
                                    <span>{{ fmt(item.line_total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Receive Modal -->
    <Dialog :open="showReceive" @update:open="showReceive = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Receive Stock — {{ order.po_number }}</DialogTitle>
            </DialogHeader>
            <p class="text-muted-foreground text-sm">Enter the quantity actually received for each item.</p>
            <form @submit.prevent="submitReceive" class="mt-2 flex flex-col gap-3">
                <div v-for="(line, i) in receiveForm.items" :key="line.id"
                    class="flex items-center gap-4 border rounded-lg px-3 py-2 text-sm">
                    <div class="flex-1">
                        <div class="font-medium">{{ order.items[i]?.product_name }}</div>
                        <div v-if="order.items[i]?.variant_label" class="text-muted-foreground text-xs">{{ order.items[i]?.variant_label }}</div>
                        <div class="text-muted-foreground text-xs">Ordered: {{ order.items[i]?.quantity_ordered }}</div>
                    </div>
                    <div class="w-24">
                        <Label class="text-xs">Received</Label>
                        <Input v-model.number="line.quantity_received" type="number"
                            :max="order.items[i]?.quantity_ordered" min="0" class="mt-0.5 text-center" />
                    </div>
                </div>
                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="showReceive = false">Cancel</Button>
                    <Button type="submit" :disabled="receiveForm.processing" class="gap-2">
                        <Truck :size="15" /> Confirm Receipt
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Pay Modal -->
    <Dialog :open="showPay" @update:open="showPay = $event">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Record Payment — {{ order.po_number }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitPay" class="flex flex-col gap-4 mt-2">
                <div>
                    <Label>Amount (Rs)</Label>
                    <Input v-model.number="payForm.amount" type="number" min="0.01" step="0.01"
                        :max="order.amount_due" required class="mt-1" />
                    <p class="text-muted-foreground text-xs mt-1">Outstanding: {{ fmt(order.amount_due) }}</p>
                </div>
                <div>
                    <Label>Payment Method</Label>
                    <select v-model="payForm.payment_method"
                        class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2 text-sm">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <Label>Notes</Label>
                    <Input v-model="payForm.notes" class="mt-1" placeholder="Optional" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showPay = false">Cancel</Button>
                    <Button type="submit" :disabled="payForm.processing" class="gap-2">
                        <DollarSign :size="15" /> Confirm Payment
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Supplier Return Modal -->
    <Dialog :open="showReturn" @update:open="showReturn = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Return Items — {{ order.po_number }}</DialogTitle>
            </DialogHeader>
            <p class="text-muted-foreground text-sm">Enter the quantity to return for each item. Stock will be reduced and AP balance adjusted.</p>
            <form @submit.prevent="submitReturn" class="mt-2 flex flex-col gap-4">
                <div class="space-y-2">
                    <div v-for="item in order.items" :key="item.id"
                        class="flex items-center gap-4 border rounded-lg px-3 py-2 text-sm">
                        <div class="flex-1">
                            <div class="font-medium">{{ item.product_name }}</div>
                            <div v-if="item.variant_label" class="text-muted-foreground text-xs">{{ item.variant_label }}</div>
                            <div class="text-muted-foreground text-xs">Received: {{ item.quantity_received }} · {{ fmt(item.unit_cost) }} each</div>
                        </div>
                        <div class="w-24">
                            <Label class="text-xs">Return Qty</Label>
                            <Input v-model.number="returnQtys[item.id]" type="number"
                                :max="item.quantity_received" min="0" class="mt-0.5 text-center" />
                        </div>
                    </div>
                </div>
                <div>
                    <Label>Reason</Label>
                    <Input v-model="returnForm.reason" class="mt-1" placeholder="Defective, wrong item…" />
                </div>
                <div>
                    <Label>Notes</Label>
                    <Input v-model="returnForm.notes" class="mt-1" placeholder="Optional" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showReturn = false">Cancel</Button>
                    <Button type="submit" :disabled="returnForm.processing" class="gap-2 bg-amber-600 hover:bg-amber-700 text-white">
                        <RotateCcw :size="15" /> Confirm Return
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
