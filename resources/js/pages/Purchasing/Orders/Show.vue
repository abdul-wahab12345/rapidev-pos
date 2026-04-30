<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, Ban, Building2, Calendar, CreditCard,
    DollarSign, Package, Printer, Receipt, Truck,
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
}

const props = defineProps<{ order: PurchaseOrder }>();

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

// Receive items
const showReceive = ref(false);
const receiveForm = useForm({ items: props.order.items.map(i => ({ id: i.id, quantity_received: i.quantity_ordered })) });
function submitReceive() {
    receiveForm.post(`/purchasing/orders/${props.order.id}/receive`, {
        onSuccess: () => { showReceive.value = false; },
    });
}

// Pay
const showPay    = ref(false);
const payForm    = useForm({ amount: props.order.amount_due, payment_method: 'cash', notes: '' });
function submitPay() {
    payForm.post(`/purchasing/orders/${props.order.id}/pay`, {
        onSuccess: () => { showPay.value = false; },
    });
}

// Cancel
function cancelOrder() {
    if (!confirm('Cancel this purchase order?')) return;
    useForm({}).post(`/purchasing/orders/${props.order.id}/cancel`);
}

const canReceive = computed(() => ['ordered', 'partial'].includes(props.order.status));
const canPay     = computed(() => props.order.amount_due > 0 && props.order.status !== 'cancelled');
const canCancel  = computed(() => !['received', 'cancelled'].includes(props.order.status));

function fmt(n: number) {
    if (n == null) return '—';
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
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
                    <Button v-if="canReceive" @click="showReceive = true" class="gap-2">
                        <Truck :size="15" /> Receive Stock
                    </Button>
                    <Button v-if="canPay" variant="outline" @click="showPay = true" class="gap-2">
                        <CreditCard :size="15" /> Record Payment
                    </Button>
                    <Button v-if="canCancel" variant="ghost" class="gap-2 text-destructive" @click="cancelOrder">
                        <Ban :size="15" /> Cancel
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- Supplier & Summary -->
                <div class="flex flex-col gap-4 lg:col-span-1">
                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm flex items-center gap-2">
                            <Building2 :size="15" /> Supplier
                        </h2>
                        <div>
                            <div class="font-medium">{{ order.supplier.name }}</div>
                            <div v-if="order.supplier.phone" class="text-muted-foreground text-xs mt-0.5">
                                {{ order.supplier.phone }}
                            </div>
                            <div v-if="order.supplier.city" class="text-muted-foreground text-xs">
                                {{ order.supplier.city }}
                            </div>
                        </div>
                        <Link :href="`/purchasing/orders/create?supplier=${order.supplier.id}`">
                            <Button variant="outline" size="sm" class="w-full gap-1">
                                <Package :size="13" /> New PO for this Supplier
                            </Button>
                        </Link>
                    </div>

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

                <!-- Items -->
                <div class="lg:col-span-2">
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
                                <div v-if="item.variant_label" class="text-muted-foreground text-xs">
                                    {{ item.variant_label }}
                                </div>
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
            <p class="text-muted-foreground text-sm">
                Enter the quantity actually received for each item.
            </p>
            <form @submit.prevent="submitReceive" class="mt-2 flex flex-col gap-3">
                <div v-for="(line, i) in receiveForm.items" :key="line.id"
                    class="flex items-center gap-4 border rounded-lg px-3 py-2 text-sm">
                    <div class="flex-1">
                        <div class="font-medium">{{ order.items[i]?.product_name }}</div>
                        <div v-if="order.items[i]?.variant_label" class="text-muted-foreground text-xs">
                            {{ order.items[i]?.variant_label }}
                        </div>
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
</template>
