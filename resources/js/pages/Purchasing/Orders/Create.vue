<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatUnit } from '@/utils/format';
import { Head, useForm } from '@inertiajs/vue3';
import { Minus, Plus, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.purchasing'), href: route('purchasing.orders.index') },
    { title: t('purchasing.newPoTitle'), href: route('purchasing.orders.create') },
]);

interface Variant { id: string; label: string; cost_price: number }
interface Product {
    id: string; name: string; name_ur: string | null; sku: string | null; unit: string;
    cost_price: number; has_variants: boolean; variants: Variant[];
    tiles_per_box: number | null; sq_m_per_box: number | null; material_type: string | null;
}
interface Supplier { id: string; name: string; payment_terms: number }

const props = defineProps<{
    suppliers: Supplier[];
    products: Product[];
    today: string;
    supplier_id?: string;
}>();

const TILE_TYPES = ['tile', 'ceramic', 'mosaic', 'border'];

interface CartLine {
    product_id: string;
    variant_id: string | null;
    product_name: string;
    variant_label: string | null;
    unit: string;
    quantity_ordered: number;
    unit_cost: number;
    // tile product extras
    tiles_per_box: number | null;
    sq_m_per_box: number | null;
    material_type: string | null;
}

// Per-line entry mode: 'unit' = direct m², 'box' = boxes + loose tiles
const lineEntryMode = ref<Record<number, 'unit' | 'box'>>({});
const lineBoxes      = ref<Record<number, number>>({});
const lineLooseTiles = ref<Record<number, number>>({});

function isTileLine(i: number): boolean {
    const l = lines.value[i];
    return !!(l?.tiles_per_box && l.tiles_per_box > 0 &&
              TILE_TYPES.includes(l.material_type ?? ''));
}

function boxQty(i: number): number {
    const l = lines.value[i];
    if (!l?.tiles_per_box || !l.sq_m_per_box) return 0;
    const sqmPerTile = l.sq_m_per_box / l.tiles_per_box;
    const totalTiles = (lineBoxes.value[i] ?? 0) * l.tiles_per_box + (lineLooseTiles.value[i] ?? 0);
    return Math.round(totalTiles * sqmPerTile * 100) / 100;
}

function syncBoxQty(i: number) {
    if (lineEntryMode.value[i] === 'box') {
        lines.value[i].quantity_ordered = boxQty(i) || lines.value[i].quantity_ordered;
    }
}

const lines = ref<CartLine[]>([]);
const productSearch = ref('');

const form = useForm<{
    supplier_id: string;
    order_date: string;
    expected_date: string;
    payment_method: string;
    notes: string;
    mark_received: boolean;
    items: CartLine[];
}>({
    supplier_id:    props.supplier_id ?? '',
    order_date:     props.today,
    expected_date:  '',
    payment_method: 'credit',
    notes:          '',
    mark_received:  false,
    items:          [],
});

const filteredProducts = computed(() => {
    const q = productSearch.value.toLowerCase();
    if (!q) return props.products.slice(0, 30);
    return props.products.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.name_ur?.includes(productSearch.value)) ||
        (p.sku?.toLowerCase().includes(q))
    ).slice(0, 30);
});

function addProduct(p: Product, v?: Variant) {
    const pid = p.id;
    const vid = v?.id ?? null;
    const existing = lines.value.find(l => l.product_id === pid && l.variant_id === vid);
    if (existing) {
        existing.quantity_ordered++;
    } else {
        const idx = lines.value.length;
        const isTile = !!(p.tiles_per_box && p.tiles_per_box > 0 && TILE_TYPES.includes(p.material_type ?? ''));
        lines.value.push({
            product_id:       pid,
            variant_id:       vid,
            product_name:     p.name,
            variant_label:    v?.label ?? null,
            unit:             p.unit,
            quantity_ordered: isTile ? 0 : 1,
            unit_cost:        v?.cost_price ?? p.cost_price,
            tiles_per_box:    p.tiles_per_box,
            sq_m_per_box:     p.sq_m_per_box,
            material_type:    p.material_type,
        });
        // Default tile products to box-entry mode
        lineEntryMode.value[idx] = isTile ? 'box' : 'unit';
        lineBoxes.value[idx]      = 0;
        lineLooseTiles.value[idx] = 0;
    }
    productSearch.value = '';
}

function removeLine(i: number) {
    lines.value.splice(i, 1);
    delete lineEntryMode.value[i];
    delete lineBoxes.value[i];
    delete lineLooseTiles.value[i];
}

const subtotal = computed(() => lines.value.reduce((s, l) => s + l.unit_cost * l.quantity_ordered, 0));

function fmt(n: number) {
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}

function submit() {
    if (!lines.value.length) {
        alert(t('purchasing.addOneProductAlert'));
        return;
    }
    form.items = lines.value;
    form.post(route('purchasing.orders.store'));
}
</script>

<template>
    <Head :title="t('purchasing.newPoTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">

            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ t('purchasing.newPoTitle') }}</h1>
                <p class="text-muted-foreground text-sm mt-1">{{ t('purchasing.newPoDescription') }}</p>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <div class="lg:col-span-1 flex flex-col gap-4">
                    <div class="border rounded-xl p-4 flex flex-col gap-4">
                        <h2 class="font-semibold text-sm">{{ t('purchasing.orderDetails') }}</h2>

                        <div>
                            <Label>{{ t('purchasing.supplier') }} <span class="text-destructive">*</span></Label>
                            <select v-model="form.supplier_id" required
                                class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                <option value="">{{ t('purchasing.selectSupplier') }}</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="form.errors.supplier_id" class="text-destructive text-xs mt-1">{{ form.errors.supplier_id }}</p>
                        </div>

                        <div>
                            <Label>{{ t('purchasing.orderDate') }} <span class="text-destructive">*</span></Label>
                            <Input v-model="form.order_date" type="date" required class="mt-1" />
                        </div>

                        <div>
                            <Label>{{ t('purchasing.expectedDelivery') }}</Label>
                            <Input v-model="form.expected_date" type="date" class="mt-1" />
                        </div>

                        <div>
                            <Label>{{ t('common.paymentMethod') }}</Label>
                            <select v-model="form.payment_method"
                                class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                <option value="credit">{{ t('purchasing.creditAp') }}</option>
                                <option value="cash">{{ t('purchasing.cod') }}</option>
                                <option value="bank">{{ t('purchasing.bankTransfer') }}</option>
                            </select>
                        </div>

                        <div>
                            <Label>{{ t('common.notes') }}</Label>
                            <Input v-model="form.notes" class="mt-1" :placeholder="t('common.optionalHint')" />
                        </div>

                        <label class="flex items-start gap-3 cursor-pointer rounded-lg border p-3 transition-colors rtl:flex-row-reverse"
                               :class="form.mark_received ? 'border-emerald-500/50 bg-emerald-500/5' : 'border-border hover:bg-muted/30'">
                            <input type="checkbox" v-model="form.mark_received" class="mt-0.5 accent-emerald-600" />
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ t('purchasing.stockReceived') }}</p>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    {{ t('purchasing.stockReceivedHelpDetail') }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm">{{ t('common.summary') }}</h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('common.items') }}</span>
                            <span>{{ lines.length }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('common.subtotal') }}</span>
                            <span>{{ fmt(subtotal) }}</span>
                        </div>
                        <div class="border-t pt-2 flex justify-between font-bold">
                            <span>{{ t('common.total') }}</span>
                            <span>{{ fmt(subtotal) }}</span>
                        </div>
                        <Button
                            type="submit"
                            :disabled="form.processing || !lines.length"
                            class="w-full mt-1 gap-2 rtl:flex-row-reverse"
                            :class="form.mark_received ? 'bg-emerald-600 hover:bg-emerald-500' : ''"
                        >
                            <ShoppingCart :size="16" />
                            {{ form.mark_received ? t('purchasing.createReceived') : t('purchasing.createPo') }}
                        </Button>
                    </div>
                </div>

                <div class="lg:col-span-2 flex flex-col gap-4">

                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm">{{ t('purchasing.addProducts') }}</h2>
                        <Input v-model="productSearch" :placeholder="t('purchasing.searchProductsPoPlaceholder')" />
                        <div v-if="productSearch" class="max-h-60 overflow-y-auto divide-y border rounded-lg">
                            <div v-if="filteredProducts.length === 0"
                                class="text-muted-foreground text-sm py-4 text-center">
                                {{ t('purchasing.noProductsSearchResults') }}
                            </div>
                            <template v-for="p in filteredProducts" :key="p.id">
                                <div v-if="!p.has_variants"
                                    class="flex items-center justify-between px-3 py-2 hover:bg-muted/40 cursor-pointer rtl:flex-row-reverse"
                                    @click="addProduct(p)">
                                    <div>
                                        <div class="text-sm font-medium">{{ p.name }}</div>
                                        <div v-if="p.name_ur" class="text-xs" dir="rtl">{{ p.name_ur }}</div>
                                        <div v-if="p.sku" class="text-muted-foreground text-xs">{{ p.sku }}</div>
                                    </div>
                                    <span class="text-xs text-muted-foreground">{{ fmt(p.cost_price) }}</span>
                                </div>
                                <div v-else class="px-3 py-2">
                                    <div class="text-sm font-medium">{{ p.name }}</div>
                                    <div v-if="p.name_ur" class="text-xs mb-1" dir="rtl">{{ p.name_ur }}</div>
                                    <div class="flex flex-wrap gap-2">
                                        <button v-for="v in p.variants" :key="v.id"
                                            type="button"
                                            class="border rounded px-2 py-0.5 text-xs hover:bg-muted"
                                            @click="addProduct(p, v)">
                                            {{ v.label }} · {{ fmt(v.cost_price) }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="border rounded-xl overflow-hidden">
                        <div class="bg-muted/50 px-4 py-2 text-xs font-medium grid grid-cols-12 gap-2">
                            <div class="col-span-5">{{ t('inventory.product') }}</div>
                            <div class="col-span-2 text-center">{{ t('common.quantity') }}</div>
                            <div class="col-span-2 text-end">{{ t('purchasing.unitCost') }}</div>
                            <div class="col-span-2 text-end">{{ t('common.total') }}</div>
                            <div class="col-span-1"></div>
                        </div>
                        <div v-if="lines.length === 0"
                            class="text-muted-foreground text-sm py-10 text-center px-4">
                            {{ t('purchasing.cartEmptyCombined') }}
                        </div>
                        <div v-for="(line, i) in lines" :key="i"
                            class="grid grid-cols-12 gap-2 px-4 py-2 items-center border-t text-sm">
                            <div class="col-span-5">
                                <div class="font-medium">{{ line.product_name }}</div>
                                <div v-if="line.variant_label" class="text-muted-foreground text-xs">{{ line.variant_label }}</div>
                                <div v-if="line.unit" class="text-muted-foreground text-xs">{{ formatUnit(line.unit) }}</div>
                            </div>
                            <div class="col-span-2 flex flex-col items-center gap-1">
                                <!-- Mode toggle for tile products -->
                                <div v-if="isTileLine(i)" class="flex rounded border border-border text-[10px] overflow-hidden">
                                    <button type="button"
                                        :class="lineEntryMode[i] === 'box' ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted'"
                                        class="px-1.5 py-0.5 transition-colors"
                                        @click="lineEntryMode[i] = 'box'">Boxes</button>
                                    <button type="button"
                                        :class="lineEntryMode[i] === 'unit' ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:bg-muted'"
                                        class="px-1.5 py-0.5 transition-colors"
                                        @click="lineEntryMode[i] = 'unit'">m²</button>
                                </div>

                                <!-- Box entry mode -->
                                <template v-if="isTileLine(i) && lineEntryMode[i] === 'box'">
                                    <div class="flex gap-1 w-full">
                                        <div class="flex-1">
                                            <div class="text-[9px] text-muted-foreground text-center mb-0.5">Box</div>
                                            <input v-model.number="lineBoxes[i]" @input="syncBoxQty(i)"
                                                type="number" min="0" placeholder="0"
                                                class="w-full text-center border rounded px-1 py-0.5 text-xs bg-background" />
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-[9px] text-muted-foreground text-center mb-0.5">Tile</div>
                                            <input v-model.number="lineLooseTiles[i]" @input="syncBoxQty(i)"
                                                type="number" min="0" :max="(line.tiles_per_box ?? 1) - 1" placeholder="0"
                                                class="w-full text-center border rounded px-1 py-0.5 text-xs bg-background" />
                                        </div>
                                    </div>
                                    <span v-if="boxQty(i) > 0" class="text-[10px] text-primary font-medium">= {{ boxQty(i) }} m²</span>
                                </template>

                                <!-- Direct m² entry -->
                                <template v-else>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="line.quantity_ordered = Math.max(0.01, +(line.quantity_ordered - 1).toFixed(2))"
                                            class="rounded hover:bg-muted p-0.5">
                                            <Minus :size="12" />
                                        </button>
                                        <input v-model.number="line.quantity_ordered" type="number" min="0.01" step="0.01"
                                            class="w-14 text-center border rounded px-1 py-0.5 text-xs bg-background" />
                                        <button type="button" @click="line.quantity_ordered = +(line.quantity_ordered + 1).toFixed(2)"
                                            class="rounded hover:bg-muted p-0.5">
                                            <Plus :size="12" />
                                        </button>
                                    </div>
                                    <span v-if="line.unit" class="text-[10px] text-muted-foreground">{{ formatUnit(line.unit) }}</span>
                                </template>
                            </div>
                            <div class="col-span-2 text-end">
                                <input v-model.number="line.unit_cost" type="number" min="0" step="0.01"
                                    class="w-24 text-end border rounded px-1 py-0.5 text-xs bg-background" />
                            </div>
                            <div class="col-span-2 text-end font-medium">
                                {{ fmt(line.unit_cost * line.quantity_ordered) }}
                            </div>
                            <div class="col-span-1 text-end">
                                <button type="button" @click="removeLine(i)"
                                    class="text-destructive hover:bg-red-500/10 rounded p-1">
                                    <Trash2 :size="14" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
