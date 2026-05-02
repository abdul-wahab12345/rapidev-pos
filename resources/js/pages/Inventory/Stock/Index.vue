<script setup lang="ts">
import CategoryBadge from '@/components/pos/CategoryBadge.vue';
import StatCard from '@/components/pos/StatCard.vue';
import StockBadge from '@/components/pos/StockBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownCircle,
    ArrowUpCircle,
    ClipboardList,
    History,
    Layers,
    Package,
    PackageX,
    Search,
    Settings2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.inventory'), href: route('inventory.products.index') },
    { title: t('nav.stockManagement'), href: route('inventory.stock.index') },
]);

interface Category { name: string; color: string }
interface Variant   { id: string; label: string }
interface Product   { id: string; name: string; sku: string | null; unit: string; category: Category | null }

interface StockRow {
    id: string;
    product_id: string;
    variant_id: string | null;
    quantity: number;
    reorder_level: number;
    product: Product;
    variant: Variant | null;
}

interface Adjustment {
    id: string;
    product_name: string;
    variant_label: string | null;
    quantity_before: number;
    quantity_change: number;
    quantity_after: number;
    reason: string;
    notes: string | null;
    user: string;
    created_at: string;
}

interface Stats {
    total_products: number;
    low_stock: number;
    out_of_stock: number;
    total_items: number;
}

interface Pagination {
    data: StockRow[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    stock: Pagination;
    stats: Stats;
    categories: { id: string; name: string; color: string }[];
    recent_adjustments: Adjustment[];
    filters: { search?: string; category?: string; stock?: string };
}>();

// ── Filters ────────────────────────────────────────────
const search   = ref(props.filters.search   ?? '');
const category = ref(props.filters.category ?? '_all_');
const stockF   = ref(props.filters.stock    ?? '_all_');
const tab      = ref<'stock' | 'history'>('stock');

let debounce: ReturnType<typeof setTimeout>;
watch([search, category, stockF], () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});

function applyFilters() {
    router.get(route('inventory.stock.index'), {
        search:   search.value                             || undefined,
        category: category.value !== '_all_' ? category.value : undefined,
        stock:    stockF.value   !== '_all_' ? stockF.value   : undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    search.value = '';
    category.value = '_all_';
    stockF.value   = '_all_';
    applyFilters();
}

const hasFilters = computed(() => search.value || category.value !== '_all_' || stockF.value !== '_all_');

// ── Adjust Modal ────────────────────────────────────────
const showAdjust = ref(false);
const adjustRow  = ref<StockRow | null>(null);

const form = useForm({
    product_id: '',
    variant_id: null as string | null,
    type:       'add' as 'add' | 'remove' | 'set',
    quantity:   1,
    reason:     'purchase' as string,
    notes:      '',
});

function openAdjust(row: StockRow) {
    adjustRow.value = row;
    form.reset();
    form.product_id = row.product_id;
    form.variant_id = row.variant_id;
    form.type       = 'add';
    form.quantity   = 1;
    form.reason     = 'purchase';
    form.notes      = '';
    showAdjust.value = true;
}

function submitAdjust() {
    form.post(route('inventory.stock.adjust'), {
        preserveScroll: true,
        onSuccess: () => {
            showAdjust.value = false;
            adjustRow.value  = null;
        },
    });
}

const previewQty = computed(() => {
    if (!adjustRow.value) return null;
    const cur = adjustRow.value.quantity;
    if (form.type === 'add')    return cur + form.quantity;
    if (form.type === 'remove') return Math.max(0, cur - form.quantity);
    return form.quantity;
});

// ── Formatting ──────────────────────────────────────────
function fmtDate(d: string) {
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(d).toLocaleString(loc, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const reasonLabel = computed<Record<string, string>>(() => ({
    purchase: t('inventory.adjustReasonPurchase'),
    damage: t('inventory.adjustReasonDamage'),
    theft: t('inventory.adjustReasonTheft'),
    correction: t('inventory.adjustReasonCorrection'),
    return: t('inventory.adjustReasonReturn'),
    other: t('inventory.adjustReasonOther'),
}));

const stockHistoryTabs = computed(() => [
    { id: 'stock' as const, label: t('inventory.stockLevels'), icon: ClipboardList },
    { id: 'history' as const, label: t('inventory.recentAdjustments'), icon: History },
]);

const adjustTypeButtons = computed(() => [
    { id: 'add' as const, label: t('inventory.addQty'), icon: ArrowUpCircle },
    { id: 'remove' as const, label: t('inventory.removeQty'), icon: ArrowDownCircle },
    { id: 'set' as const, label: t('inventory.setTo'), icon: Package },
]);

const qtyAdjustPlaceholder = computed(() => {
    if (form.type === 'set') return t('inventory.placeholderNewStockLevel');
    if (form.type === 'add') return t('inventory.placeholderQtyToAdd');
    return t('inventory.placeholderQtyToRemove');
});
</script>

<template>
    <Head :title="t('inventory.stockManagementTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-4 sm:px-6">
            <div>
                <h1 class="text-2xl font-bold text-foreground">{{ t('inventory.stockManagementTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('inventory.stockManagementDescription') }}</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:px-6">
            <StatCard :label="t('inventory.totalProducts')" :value="stats.total_products" :icon="Package" />
            <StatCard :label="t('inventory.totalItems')" :value="stats.total_items.toLocaleString()" :icon="Layers" />
            <StatCard :label="t('inventory.lowStock')" :value="stats.low_stock" :icon="AlertTriangle" tone="warning" />
            <StatCard :label="t('inventory.outOfStock')" :value="stats.out_of_stock" :icon="PackageX" tone="danger" />
        </div>

        <!-- Tabs -->
        <div class="mt-4 border-b border-border px-4 sm:px-6">
            <div class="flex gap-1">
                <button
                    v-for="tabItem in stockHistoryTabs"
                    :key="tabItem.id"
                    @click="tab = tabItem.id"
                    :class="[
                        'flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
                        tab === tabItem.id
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    <component :is="tabItem.icon" class="h-4 w-4" />
                    {{ tabItem.label }}
                </button>
            </div>
        </div>

        <!-- Stock Levels Tab -->
        <div v-if="tab === 'stock'" class="px-4 py-4 sm:px-6 space-y-4">

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-48">
                    <Search class="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" :placeholder="t('inventory.stockSearchPlaceholder')" class="ps-9" />
                </div>

                <Select v-model="category">
                    <SelectTrigger class="w-44">
                        <SelectValue :placeholder="t('inventory.allCategories')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="_all_">{{ t('inventory.allCategories') }}</SelectItem>
                        <SelectItem v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="stockF">
                    <SelectTrigger class="w-40">
                        <SelectValue :placeholder="t('inventory.allStock')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="_all_">{{ t('inventory.allStock') }}</SelectItem>
                        <SelectItem value="low">{{ t('inventory.lowStock') }}</SelectItem>
                        <SelectItem value="out">{{ t('inventory.outOfStock') }}</SelectItem>
                    </SelectContent>
                </Select>

                <Button v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters" class="gap-1">
                    <X class="h-3.5 w-3.5" /> {{ t('common.clear') }}
                </Button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm min-w-[720px]">
                        <thead class="border-b border-border bg-muted/40">
                            <tr class="[&>th]:align-middle">
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('inventory.product') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('inventory.category') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('inventory.sku') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">{{ t('inventory.stock') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">{{ t('inventory.reorderAt') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">{{ t('common.status') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="stock.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                    {{ t('inventory.stockEmptyExtended') }}
                                </td>
                            </tr>
                            <tr
                                v-for="row in stock.data"
                                :key="row.id"
                                class="hover:bg-muted/30 transition-colors [&>td]:align-middle"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-foreground">{{ row.product.name }}</div>
                                    <div v-if="row.variant" class="text-xs text-muted-foreground">{{ row.variant.label }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <CategoryBadge
                                        v-if="row.product.category"
                                        :name="row.product.category.name"
                                        :color="row.product.category.color"
                                        size="sm"
                                    />
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground font-mono text-xs">{{ row.product.sku ?? '—' }}</td>
                                <td class="px-4 py-3 text-center font-bold tabular-nums text-foreground">{{ row.quantity }}</td>
                                <td class="px-4 py-3 text-center text-muted-foreground">{{ row.reorder_level }}</td>
                                <td class="px-4 py-3 text-center">
                                    <StockBadge :quantity="row.quantity" :reorder-level="row.reorder_level" />
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openAdjust(row)"
                                        class="gap-1.5 text-xs rtl:flex-row-reverse"
                                    >
                                        <Settings2 class="h-3.5 w-3.5" />
                                        {{ t('inventory.adjust') }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="stock.last_page > 1" class="flex items-center justify-between border-t border-border px-4 py-3">
                    <p class="text-sm text-muted-foreground">
                        {{ t('inventory.paginationRecordsPartial', { shown: stock.data.length, total: stock.total }) }}
                    </p>
                    <div class="flex gap-1">
                        <Button
                            v-for="link in stock.links"
                            :key="link.label"
                            variant="outline"
                            size="sm"
                            :disabled="!link.url"
                            :class="link.active ? 'border-primary text-primary' : ''"
                            @click="link.url && router.get(link.url, {}, { preserveState: true })"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div v-else class="px-4 py-4 sm:px-6">
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead class="border-b border-border bg-muted/40">
                            <tr class="[&>th]:align-middle">
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('inventory.product') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">{{ t('inventory.before') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">{{ t('inventory.change') }}</th>
                                <th class="px-4 py-3 text-center font-medium text-muted-foreground">{{ t('inventory.after') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('common.reason') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('common.by') }}</th>
                                <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('common.date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="recent_adjustments.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                    {{ t('inventory.noAdjustmentsYet') }}
                                </td>
                            </tr>
                            <tr
                                v-for="adj in recent_adjustments"
                                :key="adj.id"
                                class="hover:bg-muted/30 transition-colors [&>td]:align-middle"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium text-foreground">{{ adj.product_name }}</div>
                                    <div v-if="adj.variant_label" class="text-xs text-muted-foreground">{{ adj.variant_label }}</div>
                                    <div v-if="adj.notes" class="text-xs text-muted-foreground italic mt-0.5">{{ adj.notes }}</div>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums text-muted-foreground">{{ adj.quantity_before }}</td>
                                <td class="px-4 py-3 text-center tabular-nums font-semibold">
                                    <span :class="adj.quantity_change >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'">
                                        {{ adj.quantity_change >= 0 ? '+' : '' }}{{ adj.quantity_change }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums font-bold text-foreground">{{ adj.quantity_after }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
                                        {{ reasonLabel[adj.reason] ?? adj.reason }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground text-xs">{{ adj.user }}</td>
                                <td class="px-4 py-3 text-muted-foreground text-xs whitespace-nowrap">{{ fmtDate(adj.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </AppLayout>

    <!-- Adjust Stock Modal -->
    <Dialog v-model:open="showAdjust">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Settings2 class="h-5 w-5" />
                    {{ t('inventory.adjustStock') }}
                </DialogTitle>
            </DialogHeader>

            <div v-if="adjustRow" class="space-y-4 py-2">

                <!-- Product info -->
                <div class="rounded-lg bg-muted/50 px-4 py-3">
                    <p class="font-semibold text-foreground">{{ adjustRow.product.name }}</p>
                    <p v-if="adjustRow.variant" class="text-xs text-muted-foreground">{{ adjustRow.variant.label }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t('inventory.currentStock') }}: <strong class="text-foreground">{{ adjustRow.quantity }}</strong>
                        {{ adjustRow.product.unit }}
                    </p>
                </div>

                <!-- Type -->
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="btn in adjustTypeButtons"
                        :key="btn.id"
                        @click="form.type = btn.id"
                        :class="[
                            'flex flex-col items-center gap-1 rounded-lg border py-3 text-xs font-medium transition-colors',
                            form.type === btn.id
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border text-muted-foreground hover:bg-muted',
                        ]"
                    >
                        <component :is="btn.icon" class="h-4 w-4" />
                        {{ btn.label }}
                    </button>
                </div>

                <!-- Quantity -->
                <div class="space-y-1.5">
                    <Label>{{ t('common.quantity') }}</Label>
                    <Input
                        v-model.number="form.quantity"
                        type="number"
                        min="0"
                        :placeholder="qtyAdjustPlaceholder"
                    />
                    <p v-if="previewQty !== null" class="text-xs text-muted-foreground">
                        {{ t('inventory.stockAfterAdjustment') }}: <strong class="text-foreground">{{ previewQty }}</strong>
                    </p>
                    <p v-if="form.errors.quantity" class="text-xs text-destructive">{{ form.errors.quantity }}</p>
                </div>

                <!-- Reason -->
                <div class="space-y-1.5">
                    <Label>{{ t('common.reason') }}</Label>
                    <Select v-model="form.reason">
                        <SelectTrigger>
                            <SelectValue :placeholder="t('inventory.selectReasonPlaceholder')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="(label, key) in reasonLabel" :key="key" :value="key">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.reason" class="text-xs text-destructive">{{ form.errors.reason }}</p>
                </div>

                <!-- Notes -->
                <div class="space-y-1.5">
                    <Label>{{ t('common.notesOptional') }}</Label>
                    <Input v-model="form.notes" :placeholder="t('inventory.anyAdditionalDetails')" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showAdjust = false">{{ t('common.cancel') }}</Button>
                <Button @click="submitAdjust" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('inventory.saveAdjustment') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
