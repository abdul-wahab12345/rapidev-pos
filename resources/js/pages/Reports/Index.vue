<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BarChart3, CreditCard, MapPin, ReceiptText, ShoppingCart } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('reports.moduleTitle'), href: route('reports.index') },
]);

const cards = computed(() => [
    {
        to: route('reports.udhaar-customers'),
        icon: ReceiptText,
        title: t('reports.udhaarTitle'),
        desc: t('reports.udhaarSubtitle'),
    },
    {
        to: route('reports.payable-vendors'),
        icon: ShoppingCart,
        title: t('reports.vendorPayTitle'),
        desc: t('reports.vendorPaySubtitle'),
    },
    {
        to: route('reports.customers-by-location'),
        icon: MapPin,
        title: t('reports.byLocationTitle'),
        desc: t('reports.byLocationSubtitle'),
    },
    {
        to: route('reports.sales-summary'),
        icon: BarChart3,
        title: t('reports.salesSummaryTitle'),
        desc: t('reports.salesSummarySubtitle'),
    },
]);
</script>

<template>
    <Head :title="t('reports.moduleTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="reports-print-root mx-auto flex max-w-4xl flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ t('reports.moduleTitle') }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">{{ t('reports.moduleSubtitle') }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('accounts.reports')"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/50"
                >
                    <BarChart3 class="size-4 text-muted-foreground" />
                    {{ t('reports.linkFinancialReports') }}
                    <ArrowRight class="size-3.5 text-muted-foreground" />
                </Link>
                <Link
                    :href="route('accounts.receivables')"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/50"
                >
                    <CreditCard class="size-4 text-muted-foreground" />
                    {{ t('reports.linkReceivablesCombined') }}
                    <ArrowRight class="size-3.5 text-muted-foreground" />
                </Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <Link
                    v-for="card in cards"
                    :key="card.to"
                    :href="card.to"
                    class="group rounded-xl border border-border bg-card p-4 shadow-sm transition-colors hover:bg-muted/30"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="card.icon" class="size-5" aria-hidden />
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{ t('reports.openSection') }}
                            </span>
                            <h2 class="mt-0.5 font-semibold text-foreground">{{ card.title }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ card.desc }}</p>
                            <span
                                class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary group-hover:underline"
                            >
                                {{ t('common.view') }}
                                <ArrowRight class="size-3.5 rtl:rotate-180" aria-hidden />
                            </span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
