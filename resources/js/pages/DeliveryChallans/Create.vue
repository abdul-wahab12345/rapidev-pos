<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Customer { id: string; name: string; phone: string }
interface Product { id: string; name: string; unit: string; material_type: string | null; stock: number }
interface ChallanItemRow { product_id: string | null; product_name: string; product_unit: string; lot_number: string; quantity: number; notes: string }
interface Prefill {
    source: 'quotation' | 'sale';
    quotation_id?: string; sale_id?: string;
    customer_id?: string; site_address?: string;
    items: { product_id: string | null; product_name: string; product_unit: string; quantity: number }[];
}

const props = defineProps<{
    customers: Customer[];
    products: Product[];
    prefill: Prefill | null;
}>();

const productSearch = ref('');
const showProductSearch = ref(false);
const filteredProducts = computed(() => {
    const q = productSearch.value.toLowerCase();
    if (!q) return props.products.slice(0, 30);
    return props.products.filter(p => p.name.toLowerCase().includes(q)).slice(0, 30);
});

const form = useForm({
    customer_id: props.prefill?.customer_id ?? '',
    sale_id: props.prefill?.sale_id ?? '',
    quotation_id: props.prefill?.quotation_id ?? '',
    delivery_date: '',
    vehicle_number: '',
    driver_name: '',
    site_address: props.prefill?.site_address ?? '',
    notes: '',
    items: (props.prefill?.items ?? []).map(i => ({
        product_id: i.product_id,
        product_name: i.product_name,
        product_unit: i.product_unit,
        lot_number: '',
        quantity: i.quantity,
        notes: '',
    })) as ChallanItemRow[],
});

function addProduct(product: Product) {
    form.items.push({ product_id: product.id, product_name: product.name, product_unit: product.unit, lot_number: '', quantity: 1, notes: '' });
    productSearch.value = ''; showProductSearch.value = false;
}
function addFreeItem() {
    form.items.push({ product_id: null, product_name: '', product_unit: 'piece', lot_number: '', quantity: 1, notes: '' });
}
function removeItem(i: number) { form.items.splice(i, 1); }

function submit() { form.post(route('challans.store')); }
</script>

<template>
    <Head title="New Delivery Challan" />
    <AppLayout :breadcrumbs="[{ title: 'Delivery Challans', href: '/challans' }, { title: 'New Challan' }]">
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">New Delivery Challan</h1>
                <p class="text-sm text-muted-foreground mt-0.5">Record goods dispatched to a customer site</p>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Items -->
                <div class="lg:col-span-2 space-y-5">
                    <div class="rounded-xl border border-border bg-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold">Items Dispatched</h3>
                            <div class="flex gap-2">
                                <button type="button" @click="showProductSearch = !showProductSearch"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-input bg-background px-3 py-1.5 text-sm hover:bg-muted transition-colors">
                                    <Search class="h-3.5 w-3.5" /> Add Product
                                </button>
                                <button type="button" @click="addFreeItem"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-input px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted transition-colors">
                                    <Plus class="h-3.5 w-3.5" /> Free line
                                </button>
                            </div>
                        </div>

                        <div v-if="showProductSearch" class="mb-4 relative">
                            <div class="relative">
                                <Search class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <input v-model="productSearch" autofocus type="text" placeholder="Search product..."
                                    class="w-full rounded-lg border border-input bg-background ps-9 pe-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>
                            <div class="absolute z-50 mt-1 w-full rounded-lg border border-border bg-card shadow-lg max-h-60 overflow-y-auto">
                                <div v-if="filteredProducts.length === 0" class="px-4 py-3 text-sm text-muted-foreground">No products</div>
                                <button v-for="p in filteredProducts" :key="p.id" type="button" @click="addProduct(p)"
                                    class="flex w-full items-center justify-between px-4 py-2.5 text-start text-sm hover:bg-muted transition-colors">
                                    <div>
                                        <p class="font-medium">{{ p.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ p.unit }}<span v-if="p.material_type" class="capitalize"> · {{ p.material_type }}</span></p>
                                    </div>
                                    <span class="text-xs text-muted-foreground">{{ p.stock }} in stock</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="form.items.length === 0" class="py-8 text-center text-sm text-muted-foreground border-2 border-dashed border-border rounded-lg">Add items to dispatch</div>
                        <div v-else class="space-y-3">
                            <div v-for="(item, i) in form.items" :key="i" class="rounded-lg border border-border p-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <input v-model="item.product_name" type="text" placeholder="Product name"
                                        class="flex-1 rounded-md border border-input bg-background px-2.5 py-1.5 text-sm font-medium focus:outline-none focus:ring-1 focus:ring-ring" />
                                    <button type="button" @click="removeItem(i)" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-red-500/10 hover:text-red-500 transition-colors">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-4 gap-2">
                                    <div>
                                        <label class="block text-xs text-muted-foreground mb-1">Qty</label>
                                        <input v-model.number="item.quantity" type="number" step="0.001" min="0.001" class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-muted-foreground mb-1">Unit</label>
                                        <input v-model="item.product_unit" type="text" class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs text-muted-foreground mb-1">Lot / Batch # <span class="text-muted-foreground">(Optional)</span></label>
                                        <input v-model="item.lot_number" type="text" placeholder="e.g. LOT-IT-047"
                                            class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-ring" />
                                    </div>
                                </div>
                                <input v-model="item.notes" type="text" placeholder="Notes (optional)"
                                    class="w-full rounded-md border border-dashed border-input bg-background px-2.5 py-1.5 text-xs text-muted-foreground focus:outline-none" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Details -->
                <div class="space-y-5">
                    <div class="rounded-xl border border-border bg-card p-5 space-y-4">
                        <h3 class="font-semibold">Dispatch Details</h3>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Customer</label>
                            <select v-model="form.customer_id" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="">— Select —</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Delivery Date</label>
                            <input v-model="form.delivery_date" type="date" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1.5">Vehicle #</label>
                                <input v-model="form.vehicle_number" type="text" placeholder="e.g. LHR-1234"
                                    class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5">Driver</label>
                                <input v-model="form.driver_name" type="text" placeholder="Driver name"
                                    class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Site Address</label>
                            <textarea v-model="form.site_address" rows="2" placeholder="Delivery address"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Notes</label>
                            <textarea v-model="form.notes" rows="2" placeholder="Internal notes"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"></textarea>
                        </div>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                        <button type="submit" :disabled="form.processing || form.items.length === 0"
                            class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Create Challan' }}
                        </button>
                        <button type="button" @click="router.visit(route('challans.index'))"
                            class="w-full rounded-lg border border-input px-4 py-2.5 text-sm font-medium hover:bg-muted transition-colors">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
