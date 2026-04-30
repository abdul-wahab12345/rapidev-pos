<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Minus, Plus, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'New Purchase Order', href: '/purchasing/orders/create' },
];

interface Variant { id: string; label: string; cost_price: number }
interface Product {
    id: string; name: string; name_ur: string | null; sku: string | null; unit: string;
    cost_price: number; has_variants: boolean; variants: Variant[];
}
interface Supplier { id: string; name: string; payment_terms: number }

const props = defineProps<{
    suppliers: Supplier[];
    products: Product[];
    today: string;
    supplier_id?: string;
}>();

interface CartLine {
    product_id: string;
    variant_id: string | null;
    product_name: string;
    variant_label: string | null;
    quantity_ordered: number;
    unit_cost: number;
}

const lines = ref<CartLine[]>([]);
const productSearch = ref('');
const selectedProductId = ref('');

const form = useForm<{
    supplier_id: string;
    order_date: string;
    expected_date: string;
    payment_method: string;
    notes: string;
    items: CartLine[];
}>({
    supplier_id:    props.supplier_id ?? '',
    order_date:     props.today,
    expected_date:  '',
    payment_method: 'credit',
    notes:          '',
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
        lines.value.push({
            product_id:      pid,
            variant_id:      vid,
            product_name:    p.name,
            variant_label:   v?.label ?? null,
            quantity_ordered: 1,
            unit_cost:       v?.cost_price ?? p.cost_price,
        });
    }
    productSearch.value = '';
}

function removeLine(i: number) { lines.value.splice(i, 1); }

const subtotal = computed(() => lines.value.reduce((s, l) => s + l.unit_cost * l.quantity_ordered, 0));

function fmt(n: number) {
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}

function submit() {
    if (!lines.value.length) {
        alert('Add at least one product.');
        return;
    }
    form.items = lines.value;
    form.post('/purchasing/orders');
}
</script>

<template>
    <Head title="New Purchase Order" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">

            <div>
                <h1 class="text-2xl font-bold tracking-tight">New Purchase Order</h1>
                <p class="text-muted-foreground text-sm mt-1">Select a supplier, add products, and submit</p>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- Left: PO Details -->
                <div class="lg:col-span-1 flex flex-col gap-4">
                    <div class="border rounded-xl p-4 flex flex-col gap-4">
                        <h2 class="font-semibold text-sm">Order Details</h2>

                        <div>
                            <Label>Supplier *</Label>
                            <select v-model="form.supplier_id" required
                                class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                <option value="">-- Select Supplier --</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="form.errors.supplier_id" class="text-destructive text-xs mt-1">{{ form.errors.supplier_id }}</p>
                        </div>

                        <div>
                            <Label>Order Date *</Label>
                            <Input v-model="form.order_date" type="date" required class="mt-1" />
                        </div>

                        <div>
                            <Label>Expected Delivery</Label>
                            <Input v-model="form.expected_date" type="date" class="mt-1" />
                        </div>

                        <div>
                            <Label>Payment Method</Label>
                            <select v-model="form.payment_method"
                                class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2 text-sm">
                                <option value="credit">Credit (AP)</option>
                                <option value="cash">Cash on Delivery</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>

                        <div>
                            <Label>Notes</Label>
                            <Input v-model="form.notes" class="mt-1" placeholder="Optional" />
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm">Summary</h2>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Items</span>
                            <span>{{ lines.length }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>{{ fmt(subtotal) }}</span>
                        </div>
                        <div class="border-t pt-2 flex justify-between font-bold">
                            <span>Total</span>
                            <span>{{ fmt(subtotal) }}</span>
                        </div>
                        <Button type="submit" :disabled="form.processing || !lines.length" class="w-full mt-1 gap-2">
                            <ShoppingCart :size="16" />
                            Create Purchase Order
                        </Button>
                    </div>
                </div>

                <!-- Right: Products -->
                <div class="lg:col-span-2 flex flex-col gap-4">

                    <!-- Product Search -->
                    <div class="border rounded-xl p-4 flex flex-col gap-3">
                        <h2 class="font-semibold text-sm">Add Products</h2>
                        <Input v-model="productSearch" placeholder="Search products by name or SKU…" />
                        <div v-if="productSearch" class="max-h-60 overflow-y-auto divide-y border rounded-lg">
                            <div v-if="filteredProducts.length === 0"
                                class="text-muted-foreground text-sm py-4 text-center">
                                No products found
                            </div>
                            <template v-for="p in filteredProducts" :key="p.id">
                                <!-- Product without variants -->
                                <div v-if="!p.has_variants"
                                    class="flex items-center justify-between px-3 py-2 hover:bg-muted/40 cursor-pointer"
                                    @click="addProduct(p)">
                                    <div>
                                        <div class="text-sm font-medium">{{ p.name }}</div>
                                        <div v-if="p.name_ur" class="text-xs" dir="rtl">{{ p.name_ur }}</div>
                                        <div v-if="p.sku" class="text-muted-foreground text-xs">{{ p.sku }}</div>
                                    </div>
                                    <span class="text-xs text-muted-foreground">{{ fmt(p.cost_price) }}</span>
                                </div>
                                <!-- Product with variants -->
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

                    <!-- Cart Lines -->
                    <div class="border rounded-xl overflow-hidden">
                        <div class="bg-muted/50 px-4 py-2 text-xs font-medium grid grid-cols-12 gap-2">
                            <div class="col-span-5">Product</div>
                            <div class="col-span-2 text-center">Qty</div>
                            <div class="col-span-2 text-right">Unit Cost</div>
                            <div class="col-span-2 text-right">Total</div>
                            <div class="col-span-1"></div>
                        </div>
                        <div v-if="lines.length === 0"
                            class="text-muted-foreground text-sm py-10 text-center">
                            No items added yet — search above to add products
                        </div>
                        <div v-for="(line, i) in lines" :key="i"
                            class="grid grid-cols-12 gap-2 px-4 py-2 items-center border-t text-sm">
                            <div class="col-span-5">
                                <div class="font-medium">{{ line.product_name }}</div>
                                <div v-if="line.variant_label" class="text-muted-foreground text-xs">
                                    {{ line.variant_label }}
                                </div>
                            </div>
                            <div class="col-span-2 flex items-center justify-center gap-1">
                                <button type="button" @click="line.quantity_ordered = Math.max(1, line.quantity_ordered - 1)"
                                    class="rounded hover:bg-muted p-0.5">
                                    <Minus :size="12" />
                                </button>
                                <input v-model.number="line.quantity_ordered" type="number" min="1"
                                    class="w-12 text-center border rounded px-1 py-0.5 text-xs bg-background" />
                                <button type="button" @click="line.quantity_ordered++"
                                    class="rounded hover:bg-muted p-0.5">
                                    <Plus :size="12" />
                                </button>
                            </div>
                            <div class="col-span-2 text-right">
                                <input v-model.number="line.unit_cost" type="number" min="0" step="0.01"
                                    class="w-24 text-right border rounded px-1 py-0.5 text-xs bg-background" />
                            </div>
                            <div class="col-span-2 text-right font-medium">
                                {{ fmt(line.unit_cost * line.quantity_ordered) }}
                            </div>
                            <div class="col-span-1 text-right">
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
