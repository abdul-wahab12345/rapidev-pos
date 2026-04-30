<script setup lang="ts">
import CategoryBadge from '@/components/pos/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import { router, useForm } from '@inertiajs/vue3';
import { Minus, Plus, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Category {
    id: string;
    name: string;
    color: string;
}

interface Unit {
    value: string;
    label: string;
}

interface VariantRow {
    size: string;
    color: string;
    sku: string;
    cost_price: number | string;
    selling_price: number | string;
}

const props = defineProps<{
    categories: Category[];
    units: Unit[];
    mode: 'create' | 'edit';
    product?: {
        id: string;
        name: string;
        name_ur: string | null;
        sku: string | null;
        barcode: string | null;
        description: string | null;
        unit: string;
        cost_price: number;
        selling_price: number;
        reorder_level: number;
        has_variants: boolean;
        is_active: boolean;
        category_id: string | null;
        total_stock?: number;
        variants?: VariantRow[];
    };
}>();

const form = useForm({
    name: props.product?.name ?? '',
    name_ur: props.product?.name_ur ?? '',
    category_id: props.product?.category_id ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    description: props.product?.description ?? '',
    unit: props.product?.unit ?? 'piece',
    cost_price: props.product?.cost_price ?? '',
    selling_price: props.product?.selling_price ?? '',
    reorder_level: props.product?.reorder_level ?? 5,
    has_variants: props.product?.has_variants ?? false,
    is_active: props.product?.is_active ?? true,
    initial_stock: 0,
    variants: (props.product?.variants ?? []) as VariantRow[],
});

// Auto-compute margin
const margin = ref(0);
watch([() => form.cost_price, () => form.selling_price], ([cost, sell]) => {
    const c = Number(cost);
    const s = Number(sell);
    margin.value = c > 0 ? Math.round(((s - c) / c) * 100 * 10) / 10 : 0;
});

// Variant management
function addVariant() {
    form.variants.push({ size: '', color: '', sku: '', cost_price: form.cost_price, selling_price: form.selling_price });
}
function removeVariant(i: number) {
    form.variants.splice(i, 1);
}

function submit() {
    if (props.mode === 'create') {
        form.post(route('inventory.products.store'));
    } else {
        form.put(route('inventory.products.update', props.product!.id));
    }
}

function cancel() {
    router.get(route('inventory.products.index'));
}
</script>

<template>
    <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- ── Left: Main Details (2/3) ── -->
        <div class="space-y-6 lg:col-span-2">

            <!-- Basic Information -->
            <div class="rounded-xl border border-border bg-card p-5">
                <h3 class="font-semibold mb-4">Basic Information</h3>
                <div class="space-y-4">

                    <!-- Name -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Product Name <span class="text-red-500">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Basmati Rice 5kg"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="{ 'border-red-500': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">
                                Urdu Name
                                <span class="text-xs text-muted-foreground font-normal ml-1">(optional)</span>
                            </label>
                            <input
                                v-model="form.name_ur"
                                type="text"
                                dir="rtl"
                                placeholder="اردو نام"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <!-- SKU + Barcode -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">SKU</label>
                            <input
                                v-model="form.sku"
                                type="text"
                                placeholder="Auto-generated if empty"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Barcode</label>
                            <input
                                v-model="form.barcode"
                                type="text"
                                placeholder="Scan or type barcode"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <!-- Category + Unit -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Category</label>
                            <select
                                v-model="form.category_id"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="">No category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                            <!-- Preview badge -->
                            <div v-if="form.category_id" class="mt-2">
                                <CategoryBadge
                                    :name="categories.find(c => c.id === form.category_id)?.name ?? ''"
                                    :color="categories.find(c => c.id === form.category_id)?.color"
                                    size="sm"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Unit of Measure <span class="text-red-500">*</span></label>
                            <select
                                v-model="form.unit"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option v-for="u in units" :key="u.value" :value="u.value">{{ u.label }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium mb-1.5">
                            Description <span class="text-xs text-muted-foreground font-normal ml-1">(optional)</span>
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Product description..."
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        ></textarea>
                    </div>

                </div>
            </div>

            <!-- Pricing -->
            <div class="rounded-xl border border-border bg-card p-5">
                <h3 class="font-semibold mb-4">Pricing</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Cost Price (PKR) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">Rs</span>
                            <input
                                v-model="form.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0"
                                class="w-full rounded-lg border border-input bg-background py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="{ 'border-red-500': form.errors.cost_price }"
                            />
                        </div>
                        <p v-if="form.errors.cost_price" class="mt-1 text-xs text-red-500">{{ form.errors.cost_price }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Selling Price (PKR) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">Rs</span>
                            <input
                                v-model="form.selling_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0"
                                class="w-full rounded-lg border border-input bg-background py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="{ 'border-red-500': form.errors.selling_price }"
                            />
                        </div>
                        <p v-if="form.errors.selling_price" class="mt-1 text-xs text-red-500">{{ form.errors.selling_price }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Profit Margin</label>
                        <div
                            :class="[
                                'flex h-9 items-center rounded-lg border px-3 text-sm font-semibold',
                                margin >= 20 ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
                                margin >= 10 ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' :
                                'border-border bg-muted text-muted-foreground',
                            ]"
                        >
                            {{ margin }}%
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variants Section -->
            <div class="rounded-xl border border-border bg-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold">Product Variants</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">For clothing/footwear with size or colour options</p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <span class="text-sm text-muted-foreground">Enable variants</span>
                        <button
                            type="button"
                            @click="form.has_variants = !form.has_variants"
                            :class="[
                                'relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors focus-visible:outline-none',
                                form.has_variants ? 'bg-primary' : 'bg-input',
                            ]"
                        >
                            <span
                                :class="[
                                    'pointer-events-none block h-4 w-4 rounded-full bg-white shadow-lg transition-transform',
                                    form.has_variants ? 'translate-x-4' : 'translate-x-0',
                                ]"
                            ></span>
                        </button>
                    </label>
                </div>

                <template v-if="form.has_variants">
                    <div class="space-y-2">
                        <!-- Header -->
                        <div class="grid grid-cols-12 gap-2 px-1 text-xs font-medium text-muted-foreground">
                            <span class="col-span-2">Size</span>
                            <span class="col-span-2">Colour</span>
                            <span class="col-span-2">SKU</span>
                            <span class="col-span-3">Cost (Rs)</span>
                            <span class="col-span-3">Price (Rs)</span>
                        </div>

                        <!-- Rows -->
                        <div
                            v-for="(variant, i) in form.variants"
                            :key="i"
                            class="grid grid-cols-12 gap-2 items-center"
                        >
                            <input v-model="variant.size" placeholder="e.g. M" class="col-span-2 rounded-md border border-input bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                            <input v-model="variant.color" placeholder="e.g. Red" class="col-span-2 rounded-md border border-input bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                            <input v-model="variant.sku" placeholder="SKU" class="col-span-2 rounded-md border border-input bg-background px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ring" />
                            <input v-model="variant.cost_price" type="number" placeholder="0" class="col-span-3 rounded-md border border-input bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                            <div class="col-span-3 flex gap-1">
                                <input v-model="variant.selling_price" type="number" placeholder="0" class="flex-1 rounded-md border border-input bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                                <button type="button" @click="removeVariant(i)" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-red-500/10 hover:text-red-500 transition-colors">
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="addVariant"
                            class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground mt-2 transition-colors"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Add variant row
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-sm text-muted-foreground">Variants are disabled. Enable to add size/colour options (e.g. clothing, footwear).</p>
                </template>
            </div>

        </div>

        <!-- ── Right: Settings (1/3) ── -->
        <div class="space-y-6">

            <!-- Status & Stock -->
            <div class="rounded-xl border border-border bg-card p-5 space-y-4">
                <h3 class="font-semibold">Settings</h3>

                <!-- Status Toggle -->
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium">Status</p>
                        <p class="text-xs text-muted-foreground">Visible on POS cashier screen</p>
                    </div>
                    <button
                        type="button"
                        @click="form.is_active = !form.is_active"
                        :class="[
                            'relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors',
                            form.is_active ? 'bg-primary' : 'bg-input',
                        ]"
                    >
                        <span
                            :class="[
                                'pointer-events-none block h-4 w-4 rounded-full bg-white shadow-lg transition-transform',
                                form.is_active ? 'translate-x-4' : 'translate-x-0',
                            ]"
                        ></span>
                    </button>
                </div>
                <p
                    :class="[
                        'text-xs font-medium px-2.5 py-1.5 rounded-lg',
                        form.is_active
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : 'bg-muted text-muted-foreground',
                    ]"
                >
                    {{ form.is_active ? 'Active — visible in POS' : 'Inactive — hidden from POS' }}
                </p>

                <hr class="border-border" />

                <!-- Reorder Level -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">Low Stock Alert (units)</label>
                    <input
                        v-model="form.reorder_level"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">Alert when stock drops to or below this number</p>
                </div>

                <!-- Initial stock (create only) -->
                <div v-if="mode === 'create' && !form.has_variants">
                    <label class="block text-sm font-medium mb-1.5">Opening Stock</label>
                    <input
                        v-model="form.initial_stock"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">Starting inventory quantity for main branch</p>
                </div>

                <!-- Current stock (edit only) -->
                <div v-if="mode === 'edit'">
                    <p class="text-sm font-medium">Current Stock</p>
                    <p class="text-2xl font-bold tabular-nums mt-1">{{ product?.total_stock ?? 0 }}</p>
                    <p class="text-xs text-muted-foreground">Adjust via Stock Management module</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Saving...</span>
                    <span v-else-if="mode === 'create'">Create Product</span>
                    <span v-else>Save Changes</span>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    @click="cancel"
                    :disabled="form.processing"
                >
                    Cancel
                </Button>
            </div>

        </div>
    </form>
</template>
