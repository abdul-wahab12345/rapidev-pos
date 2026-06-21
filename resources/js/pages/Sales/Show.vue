<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';
import { useReceipt } from '@/composables/useReceipt';
import { formatMoney, formatDateTime, formatUnit, formatQty } from '@/utils/format';
import { paymentBadge, statusBadge } from '@/constants/badges';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ArrowUpLeft, Eye, Layers, Printer, RotateCcw } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { confirm } = useConfirm();
const { t, locale } = useI18n();

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
    unit: string | null;
    tile_width_in: number | null;
    tile_height_in: number | null;
    tiles_per_box: number | null;
    sq_m_per_box: number | null;
    material_type: string | null;
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
const returnQtys       = reactive<Record<string, number>>({});
const returnRestock    = reactive<Record<string, boolean>>({});
const returnBoxes      = reactive<Record<string, number | ''>>({});
const returnLooseTiles = reactive<Record<string, number | ''>>({});

function isTileProduct(item: SaleItem): boolean {
    return !!(item.tiles_per_box && item.tiles_per_box > 0 &&
              ['tile','ceramic','mosaic','border'].includes(item.material_type ?? ''));
}

// Nominal tile size label e.g. "12 × 24 in" (for display only)
function tileSizeLabel(item: SaleItem): string | null {
    if (!item.tile_width_in || !item.tile_height_in) return null;
    return `${item.tile_width_in} × ${item.tile_height_in} in`;
}

// Returns m² to return based on entered boxes + loose tiles
function computedQtyFromBoxes(item: SaleItem): number {
    if (!isTileProduct(item) || !item.sq_m_per_box || !item.tiles_per_box) return 0;
    const boxes = Number(returnBoxes[item.id] || 0);
    const loose = Number(returnLooseTiles[item.id] || 0);
    const sqmPerTile = item.sq_m_per_box / item.tiles_per_box;
    const totalTiles = boxes * item.tiles_per_box + loose;
    return Math.round(totalTiles * sqmPerTile * 100) / 100;
}

function hasTileEntry(item: SaleItem): boolean {
    return Number(returnBoxes[item.id] || 0) > 0 || Number(returnLooseTiles[item.id] || 0) > 0;
}

function openReturnModal() {
    props.sale.items.forEach(item => {
        returnQtys[item.id]       = item.quantity_returnable > 0 ? item.quantity_returnable : 0;
        returnRestock[item.id]    = true;
        returnBoxes[item.id]      = '';
        returnLooseTiles[item.id] = '';
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
    items:         [] as { sale_item_id: string; quantity_returned: number; restock: boolean; boxes_count?: number; loose_tiles_count?: number }[],
});

const returnableItems = computed(() =>
    props.sale.items.filter(i => i.quantity_returnable > 0)
);

const computedRefundTotal = computed(() =>
    props.sale.items.reduce((sum, item) => {
        let qty: number;
        if (isTileProduct(item) && hasTileEntry(item)) {
            qty = Math.min(computedQtyFromBoxes(item), item.quantity_returnable);
        } else {
            qty = Math.min(returnQtys[item.id] ?? 0, item.quantity_returnable);
        }
        return sum + item.unit_price * qty;
    }, 0)
);

function submitReturn() {
    const items = props.sale.items
        .map(item => {
            // For tile products with box/tile entry, derive m² quantity automatically
            let qty: number;
            if (isTileProduct(item) && hasTileEntry(item)) {
                qty = Math.min(computedQtyFromBoxes(item), item.quantity_returnable);
            } else {
                qty = Math.min(returnQtys[item.id] ?? 0, item.quantity_returnable);
            }

            const entry: { sale_item_id: string; quantity_returned: number; restock: boolean; boxes_count?: number; loose_tiles_count?: number } = {
                sale_item_id:      item.id,
                quantity_returned: qty,
                restock:           returnRestock[item.id] ?? true,
            };
            if (isTileProduct(item) && hasTileEntry(item)) {
                entry.boxes_count       = Number(returnBoxes[item.id] || 0);
                entry.loose_tiles_count = Number(returnLooseTiles[item.id] || 0);
            }
            return entry;
        })
        .filter(i => i.quantity_returned > 0);

    if (!items.length) {
        alert(t('sales.returnNeedItemsAlert'));
        return;
    }

    returnForm.items = items;
    returnForm.post(route('sales.returns.store', props.sale.id), {
        onSuccess: () => { showReturnModal.value = false; },
    });
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.sales'), href: route('sales.index') },
    { title: props.sale.invoice_number, href: '#' },
]);

function saleStatusLabel(status: string) {
    if (status === 'completed')          return t('common.completed');
    if (status === 'voided')             return t('sales.voided');
    if (status === 'partially_returned') return t('sales.partiallyReturned');
    if (status === 'returned')           return t('sales.returned');
    if (status === 'pending')            return t('sales.pendingSale');
    return status;
}

function refundMethodDisplay(method: string) {
    if (method === 'cash') return t('common.cash');
    if (method === 'bank') return t('returns.bank');
    if (method === 'store_credit') return t('returns.storeCredit');
    return method.replace('_', ' ');
}

const fmt = formatMoney;
const fmtDate = (dt: string) => formatDateTime(dt, true, locale.value === 'ur' ? 'ur-PK' : 'en-PK');

async function voidSale() {
    const ok = await confirm({
        title: t('sales.voidConfirmTitle', { invoice: props.sale.invoice_number }),
        message: t('sales.voidConfirmMessage'),
        confirmLabel: t('sales.voidConfirmYes'),
        cancelLabel: t('common.cancel'),
        variant: 'danger',
    });
    if (!ok) return;
    router.post(route('sales.void', props.sale.id));
}

// ── Print ─────────────────────────────────────────────────────
const { printReceipt: openPrint } = useReceipt();
const printing = ref(false);

async function printReceipt() {
    printing.value = true;
    try {
        const response = await fetch(route('sales.receipt', props.sale.id));
        openPrint(await response.json());
    } catch (e) {
        console.error('Print failed:', e);
    } finally {
        printing.value = false;
    }
}
</script>

<template>
    <Head :title="t('sales.salePageTitle', { invoice: sale.invoice_number })" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-4xl">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('sales.index')" class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors rtl:flex-row-reverse">
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" /> {{ t('common.back') }}
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="font-mono text-2xl font-black tracking-tight">{{ sale.invoice_number }}</h1>
                        <span
                            :class="statusBadge[sale.status] ?? 'bg-muted text-muted-foreground'"
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        >{{ saleStatusLabel(sale.status) }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ fmtDate(sale.created_at) }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('challans.create') + '?sale_id=' + sale.id"
                        class="flex items-center gap-2 rounded-lg border border-input px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
                    >
                        <Layers class="h-4 w-4" />
                        Delivery Challan
                    </Link>
                    <button
                        @click="printReceipt"
                        :disabled="printing"
                        class="flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors disabled:opacity-50"
                    >
                        <Printer class="h-4 w-4" :class="printing ? 'animate-pulse' : ''" />
                        {{ t('sales.printReceipt') }}
                    </button>
                    <button
                        v-if="sale.status === 'completed' || sale.status === 'partially_returned'"
                        @click="openReturnModal"
                        class="flex items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-700 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
                    >
                        <RotateCcw class="h-4 w-4" />
                        {{ t('sales.processReturn') }}
                    </button>
                    <button
                        v-if="sale.status === 'completed'"
                        @click="voidSale"
                        class="flex items-center gap-2 rounded-lg border border-destructive/30 px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors"
                    >
                        <AlertTriangle class="h-4 w-4" />
                        {{ t('sales.voidSale') }}
                    </button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <!-- Items table (2/3 width) -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl border border-border overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                                    <th class="px-4 py-3">{{ t('sales.item') }}</th>
                                    <th class="px-4 py-3 text-center">{{ t('common.quantity') }}</th>
                                    <th class="px-4 py-3 text-end">{{ t('sales.unitPrice') }}</th>
                                    <th class="px-4 py-3 text-end">{{ t('common.total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="item in sale.items" :key="item.id" class="hover:bg-muted/20 [&>td]:align-middle">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-foreground">{{ item.product_name }}</p>
                                        <p v-if="item.variant_label" class="text-xs text-muted-foreground">{{ item.variant_label }}</p>
                                        <p v-if="tileSizeLabel(item)" class="text-xs text-muted-foreground">{{ tileSizeLabel(item) }}</p>
                                        <p v-if="item.discount > 0" class="text-xs text-green-600 dark:text-green-400">{{ t('sales.discountLine', { amount: fmt(item.discount) }) }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center text-muted-foreground">
                                        {{ formatQty(item.quantity, item.unit) }}<span v-if="item.unit" class="text-xs"> {{ formatUnit(item.unit) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-end text-muted-foreground">{{ fmt(item.unit_price) }}</td>
                                    <td class="px-4 py-3 text-end font-semibold text-foreground">{{ fmt(item.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="mt-4 rounded-xl border border-border bg-card p-4">
                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-muted-foreground">
                                <span>{{ t('common.subtotal') }}</span><span>{{ fmt(sale.subtotal) }}</span>
                            </div>
                            <div v-if="sale.discount > 0" class="flex justify-between text-green-600 dark:text-green-400">
                                <span>{{ t('common.discount') }}</span><span>−{{ fmt(sale.discount) }}</span>
                            </div>
                            <div v-if="sale.tax > 0" class="flex justify-between text-muted-foreground">
                                <span>{{ t('common.tax') }}</span><span>{{ fmt(sale.tax) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-border pt-2 text-base font-bold text-foreground">
                                <span>{{ t('common.total') }}</span>
                                <span class="text-primary">{{ fmt(sale.total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right sidebar: meta + payment -->
                <div class="space-y-4">

                    <!-- Sale info -->
                    <div class="rounded-xl border border-border bg-card p-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('sales.saleInfo') }}</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.branch') }}</dt>
                                <dd class="font-medium text-foreground">{{ sale.branch?.name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('sales.cashier') }}</dt>
                                <dd class="font-medium text-foreground">{{ sale.cashier?.name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.customer') }}</dt>
                                <dd class="font-medium text-foreground">{{ sale.customer?.name ?? t('sales.walkInLabel') }}</dd>
                            </div>
                            <div v-if="sale.customer?.phone" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.phone') }}</dt>
                                <dd class="font-medium text-foreground">{{ sale.customer.phone }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Payment breakdown -->
                    <div class="rounded-xl border border-border bg-card p-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('common.payment') }}</h3>

                        <span
                            :class="paymentBadge[sale.payment_method] ?? 'bg-muted text-muted-foreground'"
                            class="mb-3 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                        >{{ sale.payment_method }}</span>

                        <dl class="mt-2 space-y-1.5 text-sm">
                            <div v-if="sale.cash_amount > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.cash') }}</dt>
                                <dd class="font-medium">{{ fmt(sale.cash_amount) }}</dd>
                            </div>
                            <div v-if="sale.jazzcash_amount > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.jazzcash') }}</dt>
                                <dd class="font-medium">{{ fmt(sale.jazzcash_amount) }}</dd>
                            </div>
                            <div v-if="sale.easypaisa_amount > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.easypaisa') }}</dt>
                                <dd class="font-medium">{{ fmt(sale.easypaisa_amount) }}</dd>
                            </div>
                            <div v-if="sale.udhaar_amount > 0" class="flex justify-between text-amber-600 dark:text-amber-400">
                                <dt>{{ t('common.udhaar') }}</dt>
                                <dd class="font-semibold">{{ fmt(sale.udhaar_amount) }}</dd>
                            </div>
                            <div v-if="sale.change_amount > 0" class="flex justify-between border-t border-border pt-1.5 text-green-600 dark:text-green-400">
                                <dt>{{ t('sales.changeGiven') }}</dt>
                                <dd class="font-semibold">{{ fmt(sale.change_amount) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Notes -->
                    <div v-if="sale.notes" class="rounded-xl border border-border bg-card p-4">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('common.notes') }}</h3>
                        <p class="text-sm text-foreground">{{ sale.notes }}</p>
                    </div>

                </div>
            </div>

            <!-- Returns history -->
            <div v-if="sale.returns.length" class="rounded-xl border border-border overflow-x-auto">
                <div class="bg-muted/50 px-4 py-3 flex items-center gap-2">
                    <ArrowUpLeft class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">{{ t('sales.returnsSectionTitle') }}</h3>
                </div>
                <table class="w-full border-collapse text-sm min-w-[520px]">
                    <thead class="bg-muted/30">
                        <tr class="text-start text-xs font-medium uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                            <th class="px-4 py-2">{{ t('returns.returnNumber') }}</th>
                            <th class="px-4 py-2">{{ t('common.date') }}</th>
                            <th class="px-4 py-2">{{ t('common.method') }}</th>
                            <th class="px-4 py-2 hidden sm:table-cell">{{ t('common.reason') }}</th>
                            <th class="px-4 py-2 text-end">{{ t('returns.refund') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="r in sale.returns" :key="r.id" class="hover:bg-muted/20 [&>td]:align-middle">
                            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">{{ r.return_number }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ r.return_date }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ refundMethodDisplay(r.refund_method) }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground hidden sm:table-cell">{{ r.reason ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold text-red-600 dark:text-red-400 text-xs tabular-nums">
                                −{{ fmt(r.total_refund) }}
                            </td>
                            <td class="px-4 py-2.5 text-end">
                                <Link :href="route('returns.show', r.id)"
                                    class="inline-flex items-center justify-end gap-1 text-xs text-primary hover:underline rtl:flex-row-reverse">
                                    <Eye class="h-3 w-3" /> {{ t('common.view') }}
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
                <DialogTitle>{{ t('sales.processReturnModalTitle', { invoice: sale.invoice_number }) }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 mt-2">

                <!-- Items table -->
                <div class="rounded-xl border overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead class="bg-muted/50">
                            <tr class="text-start text-xs font-medium uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                                <th class="px-3 py-2">{{ t('sales.item') }}</th>
                                <th class="px-3 py-2 text-center">{{ t('sales.sold') }}</th>
                                <th class="px-3 py-2 text-center">{{ t('sales.returnable') }}</th>
                                <th class="px-3 py-2 text-center w-24">{{ t('returns.returnQty') }}</th>
                                <th class="px-3 py-2 text-center">Boxes</th>
                                <th class="px-3 py-2 text-center">Tiles</th>
                                <th class="px-3 py-2 text-center">{{ t('inventory.restock') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in sale.items" :key="item.id"
                                :class="item.quantity_returnable === 0 ? 'opacity-40' : ''"
                                class="hover:bg-muted/20 [&>td]:align-middle">
                                <td class="px-3 py-2">
                                    <p class="font-medium text-xs">{{ item.product_name }}</p>
                                    <p v-if="item.variant_label" class="text-xs text-muted-foreground">{{ item.variant_label }}</p>
                                    <p v-if="item.tiles_per_box" class="text-[10px] text-blue-500">{{ item.tiles_per_box }} tiles/box</p>
                                </td>
                                <td class="px-3 py-2 text-center text-muted-foreground text-xs">{{ item.quantity }}</td>
                                <td class="px-3 py-2 text-center text-xs"
                                    :class="item.quantity_returnable > 0 ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-muted-foreground'">
                                    {{ item.quantity_returnable }}
                                </td>
                                <td class="px-3 py-2">
                                    <!-- Tile product: qty auto-computed from boxes/tiles -->
                                    <div v-if="isTileProduct(item) && hasTileEntry(item)"
                                        class="w-full rounded border border-primary/40 bg-primary/5 px-2 py-1 text-center text-sm font-medium text-primary">
                                        {{ Math.min(computedQtyFromBoxes(item), item.quantity_returnable) }} m²
                                    </div>
                                    <input v-else
                                        v-model.number="returnQtys[item.id]"
                                        type="number"
                                        :min="0"
                                        :max="item.quantity_returnable"
                                        :disabled="item.quantity_returnable === 0"
                                        class="w-full rounded border border-input bg-background px-2 py-1 text-center text-sm disabled:opacity-40"
                                    />
                                </td>
                                <!-- Boxes (tile products only) -->
                                <td class="px-3 py-2">
                                    <input v-if="isTileProduct(item)"
                                        v-model.number="returnBoxes[item.id]"
                                        type="number" min="0"
                                        :disabled="item.quantity_returnable === 0"
                                        placeholder="0"
                                        class="w-14 rounded border border-input bg-background px-2 py-1 text-center text-xs disabled:opacity-40"
                                    />
                                    <span v-else class="block text-center text-muted-foreground text-xs">—</span>
                                </td>
                                <!-- Loose tiles (tile products only) -->
                                <td class="px-3 py-2">
                                    <input v-if="isTileProduct(item)"
                                        v-model.number="returnLooseTiles[item.id]"
                                        type="number" min="0" :max="(item.tiles_per_box ?? 1) - 1"
                                        :disabled="item.quantity_returnable === 0"
                                        placeholder="0"
                                        class="w-14 rounded border border-input bg-background px-2 py-1 text-center text-xs disabled:opacity-40"
                                    />
                                    <span v-else class="block text-center text-muted-foreground text-xs">—</span>
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
                        <Label class="text-xs">{{ t('sales.refundMethodRequired') }}</Label>
                        <div class="flex gap-3 mt-2 flex-wrap">
                            <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" v-model="returnForm.refund_method" value="cash" class="accent-primary" /> {{ t('common.cash') }}
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" v-model="returnForm.refund_method" value="bank" class="accent-primary" /> {{ t('returns.bank') }}
                            </label>
                            <label v-if="sale.customer" class="flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" v-model="returnForm.refund_method" value="store_credit" class="accent-primary" /> {{ t('returns.storeCredit') }}
                            </label>
                        </div>
                        <p v-if="returnForm.refund_method === 'store_credit'" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            {{ t('returns.udhaarReductionNote') }}
                        </p>
                    </div>
                    <div>
                        <Label class="text-xs">{{ t('common.reason') }}</Label>
                        <Input v-model="returnForm.reason" class="mt-1 text-sm" :placeholder="t('sales.returnReasonPlaceholder')" />
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <Label class="text-xs">{{ t('returns.internalNotes') }}</Label>
                    <Input v-model="returnForm.notes" class="mt-1 text-sm" :placeholder="t('common.optionalHint')" />
                </div>

                <!-- Refund total preview -->
                <div class="rounded-lg bg-muted/50 px-4 py-3 flex justify-between items-center">
                    <span class="text-sm font-medium">{{ t('returns.totalRefund') }}</span>
                    <span class="text-lg font-bold text-red-600 dark:text-red-400">
                        −{{ fmt(computedRefundTotal) }}
                    </span>
                </div>

            </div>

            <!-- Validation error summary -->
            <div v-if="Object.keys(returnForm.errors).length" class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <p class="font-semibold mb-1">{{ t('common.pleaseFixErrors') }}</p>
                <ul class="list-disc ms-5 space-y-0.5">
                    <li v-for="(msg, key) in returnForm.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>

            <DialogFooter class="pt-2">
                <Button type="button" variant="outline" @click="showReturnModal = false">{{ t('common.cancel') }}</Button>
                <Button
                    @click="submitReturn"
                    :disabled="returnForm.processing || computedRefundTotal === 0"
                    class="bg-amber-600 hover:bg-amber-700 text-white"
                >
                    {{ t('sales.processReturn') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
