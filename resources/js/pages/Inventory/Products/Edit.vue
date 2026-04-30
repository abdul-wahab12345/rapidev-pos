<script setup lang="ts">
import ProductForm from '@/components/pos/ProductForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

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
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: '/inventory/products' },
    { title: 'Products', href: '/inventory/products' },
    { title: props.product.name, href: `/inventory/products/${props.product.id}/edit` },
];
</script>

<template>
    <Head :title="`Edit: ${product.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ product.name }}</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        SKU: <span class="font-mono">{{ product.sku ?? '—' }}</span>
                        <span class="mx-2 text-border">·</span>
                        Stock: <span class="font-semibold text-foreground">{{ product.total_stock }}</span> units
                    </p>
                </div>
            </div>

            <ProductForm mode="edit" :product="product" :categories="categories" :units="units" />

        </div>
    </AppLayout>
</template>
