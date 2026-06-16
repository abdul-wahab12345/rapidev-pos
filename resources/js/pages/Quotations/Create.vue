<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Calculator, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Customer { id: string; name: string; phone: string; discount_percent: number }
interface Product {
    id: string; name: string; sku: string | null; unit: string; selling_price: number;
    material_type: string | null; sq_m_per_box: number | null;
    tile_width_cm: number | null; tile_height_cm: number | null; tiles_per_box: number | null;
    stock: number;
    variants: { id: string; label: string; selling_price: number; stock: number }[];
}
interface QuotationItemRow {
    product_id: string | null; variant_id: string | null;
    product_name: string; product_unit: string;
    quantity: number; unit_price: number; discount: number;
    notes: string;
    // UI-only
    _sq_m_per_box: number | null;
    _area_needed: number; // sq m wanted
}

const props = defineProps<{
    customers: Customer[];
    products: Product[];
}>();

const productSearch = ref('');
const showProductSearch = ref(false);

const filteredProducts = computed(() => {
    const q = productSearch.value.toLowerCase();
    if (!q) return props.products.slice(0, 30);
    return props.products.filter(p => p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q)).slice(0, 30);
});

const form = useForm({
    customer_id: '',
    site_address: '',
    valid_until: '',
    discount: 0,
    tax: 0,
    delivery_fee: 0,
    advance_paid: 0,
    notes: '',
    items: [] as QuotationItemRow[],
});

function addProduct(product: Product, variantId: string | null = null) {
    const variant = variantId ? product.variants.find(v => v.id === variantId) : null;
    const price = variant?.selling_price ?? product.selling_price;
    const name = variant ? `${product.name} — ${variant.label}` : product.name;
    form.items.push({
        product_id: product.id,
        variant_id: variantId,
        product_name: name,
        product_unit: product.unit,
        quantity: 1,
        unit_price: price,
        discount: 0,
        notes: '',
        _sq_m_per_box: product.sq_m_per_box,
        _area_needed: 0,
    });
    productSearch.value = '';
    showProductSearch.value = false;
}

function addFreeLineItem() {
    form.items.push({
        product_id: null, variant_id: null,
        product_name: '', product_unit: 'piece',
        quantity: 1, unit_price: 0, discount: 0, notes: '',
        _sq_m_per_box: null, _area_needed: 0,
    });
}

function removeItem(i: number) { form.items.splice(i, 1); }

function lineTotal(item: QuotationItemRow): number {
    return Math.max(0, item.quantity * item.unit_price - item.discount);
}

/** When user types area needed, auto-calc boxes */
function calcBoxes(item: QuotationItemRow) {
    if (item._sq_m_per_box && item._area_needed > 0) {
        // 10% wastage default
        const boxes = Math.ceil(item._area_needed * 1.10 / item._sq_m_per_box);
        item.quantity = boxes;
    }
}

const subtotal = computed(() => form.items.reduce((s, i) => s + lineTotal(i), 0));
const total = computed(() => Math.max(0, subtotal.value - form.discount + form.tax + form.delivery_fee));
const balanceDue = computed(() => Math.max(0, total.value - form.advance_paid));

function submit() {
    form.post(route('quotations.store'));
}
</script>

<template>
    <Head title="New Quotation" />
    <AppLayout :breadcrumbs="[{ title: 'Quotations', href: '/quotations' }, { title: 'New Quotation' }]">
        <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto">

            <div>
                <h1 class="text-2xl font-bold tracking-tight">New Quotation</h1>
                <p class="text-sm text-muted-foreground mt-0.5">Create a quote for a customer — can be converted to a sale later</p>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <!-- Left: Items (2/3) -->
                <div class="lg:col-span-2 space-y-5">

                    <!-- Items table -->
                    <div class="rounded-xl border border-border bg-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">Items</h3>
                            <div class="flex gap-2">
                                <button type="button" @click="showProductSearch = !showProductSearch"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-input bg-background px-3 py-1.5 text-sm hover:bg-muted transition-colors">
                                    <Search class="h-3.5 w-3.5" /> Add Product
                                </button>
                                <button type="button" @click="addFreeLineItem"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-input px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted transition-colors">
                                    <Plus class="h-3.5 w-3.5" /> Free line
                                </button>
                            </div>
                        </div>

                        <!-- Product search dropdown -->
                        <div v-if="showProductSearch" class="mb-4 relative">
                            <div class="relative">
                                <Search class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <input v-model="productSearch" autofocus type="text" placeholder="Search product name or SKU..."
                                    class="w-full rounded-lg border border-input bg-background ps-9 pe-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>
                            <div class="absolute z-50 mt-1 w-full rounded-lg border border-border bg-card shadow-lg max-h-64 overflow-y-auto">
                                <div v-if="filteredProducts.length === 0" class="px-4 py-3 text-sm text-muted-foreground">No products found</div>
                                <template v-for="p in filteredProducts" :key="p.id">
                                    <!-- If no variants, direct add -->
                                    <button v-if="p.variants.length === 0" type="button" @click="addProduct(p)"
                                        class="flex w-full items-center justify-between px-4 py-2.5 text-start text-sm hover:bg-muted transition-colors">
                                        <div>
                                            <p class="font-medium">{{ p.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ p.sku }} · {{ p.unit }}
                                                <span v-if="p.material_type" class="capitalize"> · {{ p.material_type }}</span>
                                                <span v-if="p.sq_m_per_box" class="text-blue-500"> · {{ p.sq_m_per_box }}m²/box</span>
                                            </p>
                                        </div>
                                        <span class="text-xs font-medium tabular-nums">{{ formatMoney(p.selling_price) }}</span>
                                    </button>
                                    <!-- Variants -->
                                    <template v-else>
                                        <div class="px-4 py-1.5 text-xs font-semibold text-muted-foreground bg-muted/50">{{ p.name }}</div>
                                        <button v-for="v in p.variants" :key="v.id" type="button" @click="addProduct(p, v.id)"
                                            class="flex w-full items-center justify-between px-6 py-2 text-start text-sm hover:bg-muted transition-colors">
                                            <span>{{ v.label }}</span>
                                            <span class="text-xs font-medium tabular-nums">{{ formatMoney(v.selling_price) }}</span>
                                        </button>
                                    </template>
                                </template>
                            </div>
                        </div>

                        <!-- Items list -->
                        <div v-if="form.items.length === 0" class="py-8 text-center text-sm text-muted-foreground border-2 border-dashed border-border rounded-lg">
                            Add products or free line items above
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="(item, i) in form.items" :key="i" class="rounded-lg border border-border p-3 space-y-2">
                                <div class="flex items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <input v-model="item.product_name" type="text" placeholder="Product / service name"
                                            class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                    <button type="button" @click="removeItem(i)" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-red-500/10 hover:text-red-500 transition-colors">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- Area calculator for tiles -->
                                <div v-if="item._sq_m_per_box" class="rounded-md bg-blue-500/10 border border-blue-500/20 p-2.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Calculator class="h-3.5 w-3.5 text-blue-600 shrink-0" />
                                        <span class="text-xs font-medium text-blue-700 dark:text-blue-300">Area Calculator</span>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs text-muted-foreground">Area (m²):</span>
                                            <input v-model.number="item._area_needed" @input="calcBoxes(item)" type="number" step="0.1" min="0" placeholder="0"
                                                class="w-20 rounded border border-blue-300 bg-white dark:bg-blue-950 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500" />
                                        </div>
                                        <span v-if="item._area_needed > 0 && item._sq_m_per_box" class="text-xs text-blue-600 dark:text-blue-300">
                                            → {{ item.quantity }} boxes (10% wastage incl.)
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-4 gap-2">
                                    <div>
                                        <label class="block text-xs text-muted-foreground mb-1">Qty / {{ item.product_unit }}</label>
                                        <input v-model.number="item.quantity" type="number" step="0.001" min="0.001"
                                            class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-muted-foreground mb-1">Unit Price (Rs)</label>
                                        <input v-model.number="item.unit_price" type="number" step="0.01" min="0"
                                            class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-muted-foreground mb-1">Discount (Rs)</label>
                                        <input v-model.number="item.discount" type="number" step="0.01" min="0"
                                            class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-muted-foreground mb-1">Line Total</label>
                                        <div class="flex h-9 items-center rounded-md border border-border bg-muted/50 px-2.5 text-sm font-semibold tabular-nums">
                                            {{ formatMoney(lineTotal(item)) }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <input v-model="item.notes" type="text" placeholder="Line notes (optional)"
                                        class="w-full rounded-md border border-dashed border-input bg-background px-2.5 py-1.5 text-xs text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div v-if="form.items.length > 0" class="mt-4 border-t border-border pt-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Subtotal</span>
                                <span class="tabular-nums font-medium">{{ formatMoney(subtotal) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Discount (Rs)</span>
                                <input v-model.number="form.discount" type="number" min="0" step="0.01"
                                    class="w-28 rounded-md border border-input bg-background px-2 py-1 text-sm text-end tabular-nums focus:outline-none focus:ring-1 focus:ring-ring" />
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Tax (Rs)</span>
                                <input v-model.number="form.tax" type="number" min="0" step="0.01"
                                    class="w-28 rounded-md border border-input bg-background px-2 py-1 text-sm text-end tabular-nums focus:outline-none focus:ring-1 focus:ring-ring" />
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-1.5 text-muted-foreground">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                                    Delivery Fee (Rs)
                                </span>
                                <input v-model.number="form.delivery_fee" type="number" min="0" step="50"
                                    class="w-28 rounded-md border border-input bg-background px-2 py-1 text-sm text-end tabular-nums focus:outline-none focus:ring-1 focus:ring-ring" />
                            </div>
                            <div class="flex justify-between text-base font-bold border-t border-border pt-2 mt-2">
                                <span>Total</span>
                                <span class="tabular-nums">{{ formatMoney(total) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm text-emerald-600">
                                <span class="font-medium">Advance Received (Rs)</span>
                                <input v-model.number="form.advance_paid" type="number" min="0" step="0.01"
                                    class="w-28 rounded-md border border-emerald-300 bg-background px-2 py-1 text-sm text-end tabular-nums focus:outline-none focus:ring-1 focus:ring-emerald-500" />
                            </div>
                            <div v-if="form.advance_paid > 0" class="flex justify-between text-sm font-semibold text-amber-600">
                                <span>Balance Due</span>
                                <span class="tabular-nums">{{ formatMoney(balanceDue) }}</span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right: Customer + Meta (1/3) -->
                <div class="space-y-5">

                    <!-- Customer -->
                    <div class="rounded-xl border border-border bg-card p-5 space-y-4">
                        <h3 class="font-semibold">Customer</h3>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Select Customer <span class="text-xs text-muted-foreground">(Optional)</span></label>
                            <select v-model="form.customer_id" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="">— Walk-in Customer —</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Site Address <span class="text-xs text-muted-foreground">(Optional)</span></label>
                            <textarea v-model="form.site_address" rows="2" placeholder="Installation site / delivery address"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Valid Until <span class="text-xs text-muted-foreground">(Optional)</span></label>
                            <input v-model="form.valid_until" type="date"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring appearance-auto [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-80 dark:[&::-webkit-calendar-picker-indicator]:invert" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Notes <span class="text-xs text-muted-foreground">(Optional)</span></label>
                            <textarea v-model="form.notes" rows="2" placeholder="Internal notes or conditions"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                        <div class="text-xs text-muted-foreground mb-2">
                            Quotation will be saved as <strong>Draft</strong>. You can approve and convert it to a sale later.
                        </div>
                        <button type="submit" :disabled="form.processing || form.items.length === 0"
                            class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Create Quotation' }}
                        </button>
                        <button type="button" @click="router.visit(route('quotations.index'))"
                            class="w-full rounded-lg border border-input px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">
                            Cancel
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </AppLayout>
</template>
