<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, Ban, Building2, Calendar, CreditCard,
    DollarSign, Package, Receipt, RotateCcw, Trash2, Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

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
const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.purchasing'), href: route('purchasing.orders.index') },
    { title: props.order.po_number, href: '#' },
]);

const statusCls: Record<string, string> = {
    draft:     'bg-muted text-muted-foreground',
    ordered:   'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    partial:   'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    received:  'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    cancelled: 'bg-red-500/10 text-red-500',
};

function poStatusLabel(status: string) {
    const keys = ['draft', 'ordered', 'partial', 'received', 'cancelled'];
    return keys.includes(status) ? t(`purchasing.${status}` as 'purchasing.draft') : status;
}

function paymentMethodPo(m: string) {
    if (m === 'credit') return t('purchasing.creditAp');
    if (m === 'cash') return t('common.cash');
    if (m === 'bank') return t('purchasing.bankTransfer');
    return m.replace(/_/g, ' ');
}

// ── Receive ────────────────────────────────────────────────────
const showReceive = ref(false);
const receiveForm = useForm({ items: props.order.items.map(i => ({ id: i.id, quantity_received: i.quantity_ordered })) });
function submitReceive() {
    receiveForm.post(route('purchasing.orders.receive', props.order.id), {
        onSuccess: () => { showReceive.value = false; },
    });
}

const quickReceiveForm = useForm({ items: props.order.items.map(i => ({ id: i.id, quantity_received: i.quantity_ordered })) });
async function quickReceiveAll() {
    const ok = await confirm({
        title: t('purchasing.markAllReceivedConfirmTitle'),
        message: t('purchasing.markAllReceivedConfirmMessage', { po: props.order.po_number }),
        confirmLabel: t('purchasing.markAllReceived'),
        cancelLabel: t('common.cancel'),
        variant: 'default',
    });
    if (!ok) return;
    quickReceiveForm.post(route('purchasing.orders.receive', props.order.id));
}

// ── Pay ────────────────────────────────────────────────────────
const showPay = ref(false);
const payForm = useForm({ amount: props.order.amount_due, payment_method: 'cash', notes: '' });
function submitPay() {
    payForm.post(route('purchasing.orders.pay', props.order.id), {
        onSuccess: () => { showPay.value = false; },
    });
}

// ── Void payment ───────────────────────────────────────────────
async function voidPayment(payment: Payment) {
    const ok = await confirm({
        title: t('purchasing.voidSupplierPaymentTitle', { amount: formatMoney(Math.abs(payment.amount)) }),
        message: t('purchasing.voidSupplierPaymentMessage'),
        confirmLabel: t('purchasing.voidPaymentConfirmLabel'),
        cancelLabel: t('common.cancel'),
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
        alert(t('purchasing.returnNeedQtyAlert'));
        return;
    }

    returnForm.items = items;
    returnForm.post(route('purchasing.orders.returns.store', props.order.id), {
        onSuccess: () => { showReturn.value = false; },
    });
}

// ── Cancel ─────────────────────────────────────────────────────
async function cancelOrder() {
    const ok = await confirm({
        title: t('purchasing.cancelPoConfirmTitle'),
        message: t('purchasing.cancelPoConfirmMessage'),
        confirmLabel: t('purchasing.cancelPoConfirmAction'),
        cancelLabel: t('common.cancel'),
        variant: 'danger',
    });
    if (!ok) return;
    useForm({}).post(route('purchasing.orders.cancel', props.order.id));
}

const canReceive = computed(() => ['ordered', 'partial'].includes(props.order.status));
const canPay     = computed(() => props.order.amount_due > 0 && props.order.status !== 'cancelled');
const canReturn  = computed(() => ['received', 'partial'].includes(props.order.status));
const canCancel  = computed(() => !['received', 'cancelled'].includes(props.order.status));

function fmt(n: number) {
    if (n == null) return '—';
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}

function fmtDate(dt: string) {
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(dt).toLocaleDateString(loc, { day: '2-digit', month: 'short', year: 'numeric' });
}

const subtitleLine = computed(() => {
    let s = t('purchasing.orderedShort', { date: props.order.order_date });
    if (props.order.expected_date) s += t('purchasing.expectedShort', { date: props.order.expected_date });
    return s;
});
</script>

<template>
    <Head :title="order.po_number" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <Link :href="route('purchasing.orders.index')">
                        <Button variant="outline" size="icon"><ArrowLeft :size="16" class="rtl:rotate-180" /></Button>
                    </Link>
                    <div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-xl font-bold">{{ order.po_number }}</h1>
                            <span :class="statusCls[order.status] ?? statusCls.draft"
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                                {{ poStatusLabel(order.status) }}
                            </span>
                        </div>
                        <p class="text-muted-foreground text-sm mt-0.5">
                            {{ subtitleLine }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-2 flex-wrap justify-end">
                    <Button v-if="canReceive" @click="quickReceiveAll" :disabled="quickReceiveForm.processing"
                        variant="default" class="gap-2 bg-emerald-600 hover:bg-emerald-500 rtl:flex-row-reverse">
                        <Truck :size="15" />
                        {{ quickReceiveForm.processing ? t('common.saving') : t('purchasing.markAllReceived') }}
                    </Button>
                    <Button v-if="canReceive" variant="outline" @click="showReceive = true" class="gap-2 rtl:flex-row-reverse">
                        <Truck :size="15" /> {{ t('purchasing.partialReceive') }}
                    </Button>
                    <Button v-if="canPay" variant="outline" @click="showPay = true" class="gap-2 rtl:flex-row-reverse">
                        <CreditCard :size="15" /> {{ t('purchasing.recordPayment') }}
                    </Button>
                    <Button v-if="canReturn" variant="outline" @click="openReturn" class="gap-2 text-amber-600 border-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/20 rtl:flex-row-reverse">
                        <RotateCcw :size="15" /> {{ t('purchasing.returnItems') }}
                    </Button>
                    <Button v-if="canCancel" variant="ghost" class="gap-2 text-destructive rtl:flex-row-reverse" @click="cancelOrder">
                        <Ban :size="15" /> {{ t('common.cancel') }}
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <div class="flex flex-col gap-4 lg:col-span-1">

                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm flex items-center gap-2 rtl:flex-row-reverse">
                            <Building2 :size="15" /> {{ t('purchasing.supplier') }}
                        </h2>
                        <div>
                            <div class="font-medium">{{ order.supplier.name }}</div>
                            <div v-if="order.supplier.phone" class="text-muted-foreground text-xs mt-0.5">{{ order.supplier.phone }}</div>
                            <div v-if="order.supplier.city" class="text-muted-foreground text-xs">{{ order.supplier.city }}</div>
                        </div>
                        <Link :href="route('purchasing.orders.create', { supplier: order.supplier.id })">
                            <Button variant="outline" size="sm" class="w-full gap-1 rtl:flex-row-reverse">
                                <Package :size="13" /> {{ t('purchasing.newPoForThisSupplier') }}
                            </Button>
                        </Link>
                    </div>

                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm flex items-center gap-2 rtl:flex-row-reverse">
                            <Receipt :size="15" /> {{ t('common.financials') }}
                        </h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('common.subtotal') }}</span>
                            <span>{{ fmt(order.subtotal) }}</span>
                        </div>
                        <div v-if="order.discount > 0" class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('common.discount') }}</span>
                            <span class="text-emerald-600">-{{ fmt(order.discount) }}</span>
                        </div>
                        <div v-if="order.tax > 0" class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('common.tax') }}</span>
                            <span>{{ fmt(order.tax) }}</span>
                        </div>
                        <div class="border-t pt-2 flex justify-between font-bold">
                            <span>{{ t('common.total') }}</span>
                            <span>{{ fmt(order.total) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('purchasing.paid') }}</span>
                            <span class="text-emerald-600">{{ fmt(order.paid_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold"
                            :class="order.amount_due > 0 ? 'text-orange-500' : 'text-emerald-600'">
                            <span>{{ t('purchasing.amountDue') }}</span>
                            <span>{{ fmt(order.amount_due) }}</span>
                        </div>
                    </div>

                    <div class="border rounded-xl p-4 flex flex-col gap-2 text-sm">
                        <h2 class="font-semibold flex items-center gap-2 rtl:flex-row-reverse">
                            <Calendar :size="15" /> {{ t('common.details') }}
                        </h2>
                        <div class="flex justify-between gap-2">
                            <span class="text-muted-foreground">{{ t('purchasing.paymentLabel') }}</span>
                            <span>{{ paymentMethodPo(order.payment_method) }}</span>
                        </div>
                        <div v-if="order.received_date" class="flex justify-between gap-2">
                            <span class="text-muted-foreground">{{ t('purchasing.receivedLabelShort') }}</span>
                            <span>{{ order.received_date }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-muted-foreground">{{ t('common.createdBy') }}</span>
                            <span>{{ order.created_by }}</span>
                        </div>
                        <div v-if="order.notes" class="text-muted-foreground text-xs mt-1 border-t pt-2">
                            {{ order.notes }}
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 flex flex-col gap-6">

                    <div class="border rounded-xl overflow-hidden">
                        <div class="bg-muted/50 px-4 py-2 text-xs font-medium flex gap-4">
                            <span class="flex-1">{{ t('inventory.product') }}</span>
                            <span class="w-20 text-center">{{ t('purchasing.orderedQty') }}</span>
                            <span class="w-20 text-center">{{ t('purchasing.receivedQty') }}</span>
                            <span class="w-24 text-end">{{ t('purchasing.unitCost') }}</span>
                            <span class="w-24 text-end">{{ t('common.total') }}</span>
                        </div>
                        <div v-for="item in order.items" :key="item.id"
                            class="flex items-center gap-4 px-4 py-3 border-t text-sm rtl:flex-row-reverse">
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
                            <div class="w-24 text-end">{{ fmt(item.unit_cost) }}</div>
                            <div class="w-24 text-end font-medium">{{ fmt(item.line_total) }}</div>
                        </div>
                    </div>

                    <div v-if="order.payments.length" class="border rounded-xl overflow-x-auto">
                        <div class="bg-muted/50 px-4 py-2.5 flex items-center gap-2 rtl:flex-row-reverse">
                            <CreditCard :size="14" class="text-muted-foreground shrink-0" />
                            <h3 class="text-sm font-semibold">{{ t('purchasing.paymentHistory') }}</h3>
                        </div>
                        <table class="w-full border-collapse text-sm">
                            <thead class="bg-muted/30">
                                <tr class="text-start text-xs font-medium uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                                    <th class="px-4 py-2">{{ t('common.date') }}</th>
                                    <th class="px-4 py-2">{{ t('common.method') }}</th>
                                    <th class="px-4 py-2">{{ t('common.notes') }}</th>
                                    <th class="px-4 py-2 text-end">{{ t('common.amount') }}</th>
                                    <th class="px-4 py-2 w-28"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="p in order.payments" :key="p.id"
                                    :class="p.is_voided ? 'opacity-50' : ''"
                                    class="hover:bg-muted/20 [&>td]:align-middle">
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ fmtDate(p.created_at) }}</td>
                                    <td class="px-4 py-2.5 text-xs">{{ paymentMethodPo(p.payment_method) }}</td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground">{{ p.notes || '—' }}</td>
                                    <td class="px-4 py-2.5 text-end font-semibold text-xs"
                                        :class="p.is_voided ? 'line-through text-muted-foreground' : 'text-emerald-600'">
                                        {{ fmt(p.amount) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-end">
                                        <span v-if="p.is_voided" class="text-xs text-muted-foreground italic">{{ t('purchasing.voidedPayment') }}</span>
                                        <button
                                            v-else
                                            @click="voidPayment(p)"
                                            class="text-xs text-destructive hover:underline flex items-center gap-1 ms-auto rtl:flex-row-reverse"
                                            :title="t('purchasing.voidPaymentTooltip')"
                                        >
                                            <Trash2 :size="12" /> {{ t('purchasing.void') }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="order.returns.length" class="border rounded-xl overflow-hidden">
                        <div class="bg-muted/50 px-4 py-2.5 flex items-center gap-2 rtl:flex-row-reverse">
                            <RotateCcw :size="14" class="text-muted-foreground shrink-0" />
                            <h3 class="text-sm font-semibold">{{ t('purchasing.supplierReturns') }}</h3>
                        </div>
                        <div v-for="r in order.returns" :key="r.id" class="border-t px-4 py-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-xs font-semibold text-amber-600">{{ r.return_number }}</span>
                                <span class="font-bold text-red-600 dark:text-red-400">−{{ fmt(r.total_amount) }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-muted-foreground">
                                {{ fmtDate(r.created_at) }}
                                <span v-if="r.reason"> · {{ r.reason }}</span>
                            </div>
                            <div class="mt-2 space-y-0.5">
                                <div v-for="(ritem, i) in r.items" :key="i" class="flex justify-between gap-2 text-xs text-muted-foreground">
                                    <span>{{ ritem.product_name }}<span v-if="ritem.variant_label"> ({{ ritem.variant_label }})</span> × {{ ritem.quantity_returned }}</span>
                                    <span>{{ fmt(ritem.line_total) }}</span>
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
                <DialogTitle>{{ t('purchasing.receiveStockModalTitle', { po: order.po_number }) }}</DialogTitle>
            </DialogHeader>
            <p class="text-muted-foreground text-sm">{{ t('purchasing.receiveModalIntro') }}</p>
            <form @submit.prevent="submitReceive" class="mt-2 flex flex-col gap-3">
                <div v-for="(line, i) in receiveForm.items" :key="line.id"
                    class="flex items-center gap-4 border rounded-lg px-3 py-2 text-sm rtl:flex-row-reverse">
                    <div class="flex-1">
                        <div class="font-medium">{{ order.items[i]?.product_name }}</div>
                        <div v-if="order.items[i]?.variant_label" class="text-muted-foreground text-xs">{{ order.items[i]?.variant_label }}</div>
                        <div class="text-muted-foreground text-xs">{{ t('purchasing.receiveOrderedLabel', { qty: order.items[i]?.quantity_ordered }) }}</div>
                    </div>
                    <div class="w-24">
                        <Label class="text-xs">{{ t('purchasing.receivedQty') }}</Label>
                        <Input v-model.number="line.quantity_received" type="number"
                            :max="order.items[i]?.quantity_ordered" min="0" class="mt-0.5 text-center" />
                    </div>
                </div>
                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="showReceive = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="receiveForm.processing" class="gap-2 rtl:flex-row-reverse">
                        <Truck :size="15" /> {{ t('purchasing.confirmReceipt') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Pay Modal -->
    <Dialog :open="showPay" @update:open="showPay = $event">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ t('purchasing.recordPaymentModalTitle', { po: order.po_number }) }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitPay" class="flex flex-col gap-4 mt-2">
                <div>
                    <Label>{{ t('purchasing.amountRsLabelShort') }}</Label>
                    <Input v-model.number="payForm.amount" type="number" min="0.01" step="0.01"
                        :max="order.amount_due" required class="mt-1" />
                    <p class="text-muted-foreground text-xs mt-1">{{ t('customers.outstandingLabel') }}: {{ fmt(order.amount_due) }}</p>
                </div>
                <div>
                    <Label>{{ t('common.paymentMethod') }}</Label>
                    <select v-model="payForm.payment_method"
                        class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2 text-sm">
                        <option value="cash">{{ t('common.cash') }}</option>
                        <option value="bank">{{ t('purchasing.bankTransfer') }}</option>
                    </select>
                </div>
                <div>
                    <Label>{{ t('common.notes') }}</Label>
                    <Input v-model="payForm.notes" class="mt-1" :placeholder="t('common.optionalHint')" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showPay = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="payForm.processing" class="gap-2 rtl:flex-row-reverse">
                        <DollarSign :size="15" /> {{ t('purchasing.confirmPayment') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Supplier Return Modal -->
    <Dialog :open="showReturn" @update:open="showReturn = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('purchasing.returnItemsTitle') }} — {{ order.po_number }}</DialogTitle>
            </DialogHeader>
            <p class="text-muted-foreground text-sm">{{ t('purchasing.returnModalIntro') }}</p>
            <form @submit.prevent="submitReturn" class="mt-2 flex flex-col gap-4">
                <div class="space-y-2">
                    <div v-for="item in order.items" :key="item.id"
                        class="flex items-center gap-4 border rounded-lg px-3 py-2 text-sm rtl:flex-row-reverse">
                        <div class="flex-1">
                            <div class="font-medium">{{ item.product_name }}</div>
                            <div v-if="item.variant_label" class="text-muted-foreground text-xs">{{ item.variant_label }}</div>
                            <div class="text-muted-foreground text-xs">
                                {{ t('purchasing.receivedLineMini', { qty: item.quantity_received, cost: fmt(item.unit_cost) }) }}
                            </div>
                        </div>
                        <div class="w-24">
                            <Label class="text-xs">{{ t('returns.returnQty') }}</Label>
                            <Input v-model.number="returnQtys[item.id]" type="number"
                                :max="item.quantity_received" min="0" class="mt-0.5 text-center" />
                        </div>
                    </div>
                </div>
                <div>
                    <Label>{{ t('common.reason') }}</Label>
                    <Input v-model="returnForm.reason" class="mt-1" :placeholder="t('sales.returnReasonPlaceholder')" />
                </div>
                <div>
                    <Label>{{ t('common.notes') }}</Label>
                    <Input v-model="returnForm.notes" class="mt-1" :placeholder="t('common.optionalHint')" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showReturn = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="returnForm.processing" class="gap-2 bg-amber-600 hover:bg-amber-700 text-white rtl:flex-row-reverse">
                        <RotateCcw :size="15" /> {{ t('purchasing.confirmReturn') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
