<script setup lang="ts">
import CategoryBadge from '@/components/pos/CategoryBadge.vue';
import { Button } from '@/components/ui/button';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

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

const { t } = useI18n();

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
        form.put(route('inventory.products.update', { product: props.product!.id }));
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
                <h3 class="font-semibold mb-4">{{ t('inventory.sectionBasicInfo') }}</h3>
                <div class="space-y-4">

                    <!-- Name -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">{{ t('inventory.productNameLabel') }} <span class="text-red-500">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                :placeholder="t('inventory.namePlaceholderExample')"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="{ 'border-red-500': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">
                                {{ t('inventory.urduNameLabel') }}
                                <span class="text-xs text-muted-foreground font-normal ms-1">{{ t('inventory.optionalParen') }}</span>
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
                            <label class="block text-sm font-medium mb-1.5">{{ t('inventory.sku') }}</label>
                            <input
                                v-model="form.sku"
                                type="text"
                                :placeholder="t('inventory.skuAutoPlaceholder')"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">{{ t('inventory.barcodeLabel') }}</label>
                            <input
                                v-model="form.barcode"
                                type="text"
                                :placeholder="t('inventory.barcodePlaceholder')"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm font-mono placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <!-- Category + Unit -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">{{ t('inventory.category') }}</label>
                            <select
                                v-model="form.category_id"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="">{{ t('inventory.noCategory') }}</option>
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
                            <label class="block text-sm font-medium mb-1.5">{{ t('inventory.unitOfMeasure') }} <span class="text-red-500">*</span></label>
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
                            {{ t('common.description') }} <span class="text-xs text-muted-foreground font-normal ms-1">{{ t('inventory.optionalParen') }}</span>
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            :placeholder="t('inventory.descriptionPlaceholder')"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        ></textarea>
                    </div>

                </div>
            </div>

            <!-- Pricing -->
            <div class="rounded-xl border border-border bg-card p-5">
                <h3 class="font-semibold mb-4">{{ t('inventory.sectionPricing') }}</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">{{ t('inventory.costPricePkr') }} <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">Rs</span>
                            <input
                                v-model="form.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0"
                                class="w-full rounded-lg border border-input bg-background py-2 ps-9 pe-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="{ 'border-red-500': form.errors.cost_price }"
                            />
                        </div>
                        <p v-if="form.errors.cost_price" class="mt-1 text-xs text-red-500">{{ form.errors.cost_price }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">{{ t('inventory.sellingPricePkr') }} <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">Rs</span>
                            <input
                                v-model="form.selling_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0"
                                class="w-full rounded-lg border border-input bg-background py-2 ps-9 pe-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="{ 'border-red-500': form.errors.selling_price }"
                            />
                        </div>
                        <p v-if="form.errors.selling_price" class="mt-1 text-xs text-red-500">{{ form.errors.selling_price }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">{{ t('inventory.profitMargin') }}</label>
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
                        <h3 class="font-semibold">{{ t('inventory.sectionProductVariants') }}</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ t('inventory.variantsHelpText') }}</p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <span class="text-sm text-muted-foreground">{{ t('inventory.enableVariants') }}</span>
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
                                    form.has_variants ? 'ltr:translate-x-4 rtl:-translate-x-4' : 'translate-x-0',
                                ]"
                            ></span>
                        </button>
                    </label>
                </div>

                <template v-if="form.has_variants">
                    <div class="space-y-2">
                        <!-- Header -->
                        <div class="grid grid-cols-12 gap-2 px-1 text-xs font-medium text-muted-foreground">
                            <span class="col-span-2">{{ t('inventory.variantSize') }}</span>
                            <span class="col-span-2">{{ t('inventory.variantColour') }}</span>
                            <span class="col-span-2">{{ t('inventory.sku') }}</span>
                            <span class="col-span-3">{{ t('inventory.variantCostRs') }}</span>
                            <span class="col-span-3">{{ t('inventory.variantPriceRs') }}</span>
                        </div>

                        <!-- Rows -->
                        <div
                            v-for="(variant, i) in form.variants"
                            :key="i"
                            class="grid grid-cols-12 gap-2 items-center"
                        >
                            <input v-model="variant.size" :placeholder="t('inventory.variantSizePh')" class="col-span-2 rounded-md border border-input bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                            <input v-model="variant.color" :placeholder="t('inventory.variantColourPh')" class="col-span-2 rounded-md border border-input bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-ring" />
                            <input v-model="variant.sku" :placeholder="t('inventory.sku')" class="col-span-2 rounded-md border border-input bg-background px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ring" />
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
                            {{ t('inventory.addVariantRow') }}
                        </button>
                    </div>
                </template>
                <template v-else>
                    <p class="text-sm text-muted-foreground">{{ t('inventory.variantsDisabledHelp') }}</p>
                </template>
            </div>

        </div>

        <!-- ── Right: Settings (1/3) ── -->
        <div class="space-y-6">

            <!-- Status & Stock -->
            <div class="rounded-xl border border-border bg-card p-5 space-y-4">
                <h3 class="font-semibold">{{ t('inventory.sectionSettings') }}</h3>

                <!-- Status Toggle -->
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium">{{ t('common.status') }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('inventory.statusPosHint') }}</p>
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
                                form.is_active ? 'ltr:translate-x-4 rtl:-translate-x-4' : 'translate-x-0',
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
                    {{ form.is_active ? t('inventory.activePosLine') : t('inventory.inactivePosLine') }}
                </p>

                <hr class="border-border" />

                <!-- Reorder Level -->
                <div>
                    <label class="block text-sm font-medium mb-1.5">{{ t('inventory.lowStockAlert') }}</label>
                    <input
                        v-model="form.reorder_level"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('inventory.reorderHint') }}</p>
                </div>

                <!-- Initial stock (create only) -->
                <div v-if="mode === 'create' && !form.has_variants">
                    <label class="block text-sm font-medium mb-1.5">{{ t('inventory.openingStock') }}</label>
                    <input
                        v-model="form.initial_stock"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('inventory.openingStockHint') }}</p>
                </div>

                <!-- Current stock (edit only) -->
                <div v-if="mode === 'edit'">
                    <p class="text-sm font-medium">{{ t('inventory.currentStockForm') }}</p>
                    <p class="text-2xl font-bold tabular-nums mt-1">{{ props.product?.total_stock ?? 0 }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('inventory.adjustViaStock') }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">{{ t('common.saving') }}</span>
                    <span v-else-if="mode === 'create'">{{ t('inventory.createProductBtn') }}</span>
                    <span v-else>{{ t('common.saveChanges') }}</span>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    @click="cancel"
                    :disabled="form.processing"
                >
                    {{ t('common.cancel') }}
                </Button>
            </div>

        </div>
    </form>
</template>
