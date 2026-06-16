<script setup lang="ts">
import ProductForm from '@/components/pos/ProductForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    product: {
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
        category: { id: string; name: string; color: string } | null;
        total_stock: number;
        // Material attributes
        material_type: string | null;
        finish: string | null;
        origin: string | null;
        thickness_mm: number | null;
        tile_width_in: number | null;
        tile_height_in: number | null;
        tiles_per_box: number | null;
        sq_m_per_box: number | null;
        variants: Array<{
            id: string;
            size: string | null;
            color: string | null;
            sku: string | null;
            cost_price: number;
            selling_price: number;
            is_active: boolean;
            stock: number;
        }>;
    };
    categories: Array<{ id: string; name: string; color: string }>;
    units: Array<{ value: string; label: string }>;
    materialTypes: Array<{ value: string; label: string }>;
    finishOptions: Array<{ value: string; label: string }>;
    originOptions: string[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.inventory'), href: '/inventory/products' },
    { title: t('nav.products'), href: '/inventory/products' },
    { title: props.product.name, href: `/inventory/products/${props.product.id}/edit` },
]);

const headTitle = computed(() => t('inventory.editProductHead', { name: props.product.name }));
</script>

<template>
    <Head :title="headTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ product.name }}</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        {{ t('inventory.skuLine', { sku: product.sku ?? '—' }) }}
                        <span class="mx-2 text-border">·</span>
                        {{ t('inventory.stockUnitsLine', { count: product.total_stock }) }}
                    </p>
                </div>
            </div>

            <ProductForm
                mode="edit"
                :product="product"
                :categories="categories"
                :units="units"
                :material-types="materialTypes"
                :finish-options="finishOptions"
                :origin-options="originOptions"
            />

        </div>
    </AppLayout>
</template>
