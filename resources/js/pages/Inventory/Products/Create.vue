<script setup lang="ts">
import ProductForm from '@/components/pos/ProductForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    categories: Array<{ id: string; name: string; color: string }>;
    units: Array<{ value: string; label: string }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: '/inventory/products' },
    { title: 'Products', href: '/inventory/products' },
    { title: 'New Product', href: '/inventory/products/create' },
];
</script>

<template>
    <Head title="Add Product" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <div>
                <h1 class="text-2xl font-bold tracking-tight">Add Product</h1>
                <p class="text-sm text-muted-foreground mt-0.5">Add a new product to your catalogue</p>
            </div>

            <ProductForm mode="create" :categories="categories" :units="units" />

        </div>
    </AppLayout>
</template>
