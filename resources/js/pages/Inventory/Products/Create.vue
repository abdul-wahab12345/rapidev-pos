<script setup lang="ts">
import ProductForm from '@/components/pos/ProductForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    categories: Array<{ id: string; name: string; color: string }>;
    units: Array<{ value: string; label: string }>;
    materialTypes: Array<{ value: string; label: string }>;
    finishOptions: Array<{ value: string; label: string }>;
    originOptions: string[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.inventory'), href: '/inventory/products' },
    { title: t('nav.products'), href: '/inventory/products' },
    { title: t('inventory.newProductBreadcrumb'), href: '/inventory/products/create' },
]);
</script>

<template>
    <Head :title="t('inventory.addProductTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ t('inventory.addProductTitle') }}</h1>
                <p class="text-sm text-muted-foreground mt-0.5">{{ t('inventory.addProductSubtitle') }}</p>
            </div>

            <ProductForm
                mode="create"
                :categories="categories"
                :units="units"
                :material-types="materialTypes"
                :finish-options="finishOptions"
                :origin-options="originOptions"
            />

        </div>
    </AppLayout>
</template>
