<script setup lang="ts">
import CategoryBadge from '@/components/pos/CategoryBadge.vue';
import StatCard from '@/components/pos/StatCard.vue';
import StockBadge from '@/components/pos/StockBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpDown,
    Package,
    PackageCheck,
    PackageX,
    Pencil,
    Plus,
    Search,
    Shapes,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Category {
    id: string;
    name: string;
    color: string;
}

interface Product {
    id: string;
    name: string;
    name_ur: string | null;
    sku: string | null;
    barcode: string | null;
    unit: string;
    cost_price: number;
    selling_price: number;
    margin: number;
    has_variants: boolean;
    is_active: boolean;
    reorder_level: number;
    total_stock: number;
    category: Category | null;
}

interface Stats {
    total: number;
    active: number;
    with_variants: number;
    low_stock: number;
    out_of_stock: number;
}

interface Pagination {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    products: Pagination;
    categories: Category[];
    stats: Stats;
    filters: { search?: string; category?: string; status?: string; stock?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: '/inventory/products' },
    { title: 'Products', href: '/inventory/products' },
];

// Local filter state
const search = ref(props.filters.search ?? '');
const selectedCategory = ref(props.filters.category ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const selectedStock = ref(props.filters.stock ?? '');

const hasFilters = computed(
    () => !!search.value || !!selectedCategory.value || !!selectedStatus.value || !!selectedStock.value,
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    router.get(
        route('inventory.products.index'),
        {
            search: search.value || undefined,
            category: selectedCategory.value || undefined,
            status: selectedStatus.value || undefined,
            stock: selectedStock.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch([selectedCategory, selectedStatus, selectedStock], applyFilters);

function clearFilters() {
    search.value = '';
    selectedCategory.value = '';
    selectedStatus.value = '';
    selectedStock.value = '';
}

function deleteProduct(product: Product) {
    if (!confirm(`Delete "${product.name}"? This cannot be undone.`)) return;
    router.delete(route('inventory.products.destroy', product.id), {
        preserveScroll: true,
    });
}

function toggleStatus(product: Product) {
    router.patch(route('inventory.products.toggle-status', product.id), {}, { preserveScroll: true });
}

function formatPrice(price: number): string {
    return 'Rs ' + Math.round(price).toLocaleString('en-PK');
}
</script>

<template>
    <Head title="Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Products</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        Manage your product catalogue, pricing, and stock
                    </p>
                </div>
                <Button as-child>
                    <Link :href="route('inventory.products.create')">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Product
                    </Link>
                </Button>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <StatCard label="Total Products" :value="stats.total" :icon="Package" />
                <StatCard label="Active" :value="stats.active" tone="success" :icon="PackageCheck" />
                <StatCard label="With Variants" :value="stats.with_variants" tone="info" :icon="Shapes" />
                <StatCard label="Low Stock" :value="stats.low_stock" tone="warning" :icon="AlertTriangle" />
                <StatCard label="Out of Stock" :value="stats.out_of_stock" tone="danger" :icon="PackageX" />
            </div>

            <!-- Filters Bar -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Search -->
                <div class="relative flex-1 max-w-sm">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by name, SKU, barcode..."
                        class="w-full rounded-lg border border-input bg-background py-2 pl-9 pr-4 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <button v-if="search" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>

                <!-- Category filter -->
                <select
                    v-model="selectedCategory"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>

                <!-- Status filter -->
                <select
                    v-model="selectedStatus"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <!-- Stock filter -->
                <select
                    v-model="selectedStock"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                    <option value="">All Stock</option>
                    <option value="low">Low Stock</option>
                    <option value="out">Out of Stock</option>
                </select>

                <!-- Clear filters -->
                <button
                    v-if="hasFilters"
                    @click="clearFilters"
                    class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground hover:text-foreground hover:border-foreground/30 transition-colors"
                >
                    <X class="h-3.5 w-3.5" />
                    Clear
                </button>
            </div>

            <!-- Products Table -->
            <div class="rounded-xl border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/40">
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                    <button class="flex items-center gap-1 hover:text-foreground">
                                        Product <ArrowUpDown class="h-3.5 w-3.5" />
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-muted-foreground">Category</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Cost</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Price</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Margin</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">Stock</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <!-- Empty state -->
                            <tr v-if="products.data.length === 0">
                                <td colspan="8" class="px-4 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                            <Package class="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <p class="font-medium text-foreground">No products found</p>
                                            <p class="text-sm text-muted-foreground mt-0.5">
                                                {{ hasFilters ? 'Try adjusting your filters' : 'Add your first product to get started' }}
                                            </p>
                                        </div>
                                        <Button v-if="!hasFilters" as-child size="sm">
                                            <Link :href="route('inventory.products.create')">
                                                <Plus class="h-3.5 w-3.5 mr-1.5" /> Add Product
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Product rows -->
                            <tr
                                v-for="product in products.data"
                                :key="product.id"
                                class="hover:bg-muted/30 transition-colors group"
                            >
                                <!-- Name + SKU -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                            <Package class="h-4 w-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium truncate max-w-[200px]">{{ product.name }}</p>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span v-if="product.sku" class="text-xs text-muted-foreground">{{ product.sku }}</span>
                                                <span v-if="product.has_variants" class="text-[10px] font-medium text-indigo-600 dark:text-indigo-400 bg-indigo-500/10 px-1.5 py-0.5 rounded-full">Variants</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td class="px-4 py-3">
                                    <CategoryBadge
                                        v-if="product.category"
                                        :name="product.category.name"
                                        :color="product.category.color"
                                        size="sm"
                                    />
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <!-- Cost -->
                                <td class="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                    {{ formatPrice(product.cost_price) }}
                                </td>

                                <!-- Selling Price -->
                                <td class="px-4 py-3 text-right font-medium tabular-nums">
                                    {{ formatPrice(product.selling_price) }}
                                </td>

                                <!-- Margin -->
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <span
                                        :class="[
                                            'text-xs font-medium',
                                            product.margin >= 20 ? 'text-emerald-600 dark:text-emerald-400' :
                                            product.margin >= 10 ? 'text-amber-600 dark:text-amber-400' :
                                            'text-muted-foreground',
                                        ]"
                                    >
                                        {{ product.margin }}%
                                    </span>
                                </td>

                                <!-- Stock -->
                                <td class="px-4 py-3 text-center">
                                    <StockBadge :quantity="product.total_stock" :reorder-level="product.reorder_level" />
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3 text-center">
                                    <button
                                        @click="toggleStatus(product)"
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium border transition-colors cursor-pointer',
                                            product.is_active
                                                ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 hover:bg-emerald-500/20 dark:text-emerald-400'
                                                : 'bg-muted text-muted-foreground border-border hover:bg-muted/70',
                                        ]"
                                    >
                                        <span :class="['h-1.5 w-1.5 rounded-full', product.is_active ? 'bg-emerald-500' : 'bg-muted-foreground']"></span>
                                        {{ product.is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Link
                                            :href="route('inventory.products.edit', product.id)"
                                            class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </Link>
                                        <button
                                            @click="deleteProduct(product)"
                                            class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-red-500/10 hover:text-red-500 transition-colors"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="products.last_page > 1" class="flex items-center justify-between border-t border-border px-4 py-3">
                    <p class="text-sm text-muted-foreground">
                        Showing {{ (products.current_page - 1) * products.per_page + 1 }}–{{ Math.min(products.current_page * products.per_page, products.total) }} of {{ products.total }}
                    </p>
                    <div class="flex items-center gap-1">
                        <template v-for="link in products.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-sm transition-colors',
                                    link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-muted-foreground',
                                ]"
                                v-html="link.label"
                                preserve-scroll
                            />
                            <span
                                v-else
                                class="flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-sm text-muted-foreground/40"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
