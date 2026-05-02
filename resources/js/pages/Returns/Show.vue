<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface ReturnItem {
    id: string;
    product_name: string;
    variant_label: string | null;
    quantity_returned: number;
    unit_price: number;
    line_total: number;
    restock: boolean;
}

interface SaleReturn {
    id: string;
    return_number: string;
    return_date: string;
    refund_method: string;
    total_refund: number;
    reason: string | null;
    notes: string | null;
    status: string;
    created_by: string | null;
    branch: string | null;
    sale: { id: string; invoice_number: string; customer: { id: string; name: string } | null };
    items: ReturnItem[];
}

const props = defineProps<{ saleReturn: SaleReturn }>();

const { t, locale } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('returns.pageTitle'), href: route('returns.index') },
    { title: props.saleReturn.return_number, href: '#' },
]);

const methodLabel = computed<Record<string, string>>(() => ({
    cash: t('common.cash'),
    bank: t('returns.bank'),
    store_credit: t('returns.storeCredit'),
}));

function fmtDate(d: string) {
    const loc = locale.value === 'ur' ? 'ur-PK' : 'en-PK';
    return new Date(d + 'T00:00:00').toLocaleDateString(loc, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function returnStatusLabel(status: string) {
    const s = status.toLowerCase();
    if (s === 'completed' || s === 'processed') return t('common.completed');
    return status;
}
</script>

<template>
    <Head :title="t('returns.viewReturnTitle', { number: saleReturn.return_number })" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-3xl">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('returns.index')" class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors rtl:flex-row-reverse">
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" /> {{ t('common.back') }}
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="font-mono text-2xl font-black tracking-tight">{{ saleReturn.return_number }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">
                            {{ returnStatusLabel(saleReturn.status) }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ fmtDate(saleReturn.return_date) }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <!-- Items table -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl border overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground [&>th]:align-middle">
                                    <th class="px-4 py-3">{{ t('sales.item') }}</th>
                                    <th class="px-4 py-3 text-center">{{ t('common.quantity') }}</th>
                                    <th class="px-4 py-3 text-end">{{ t('common.unit') }}</th>
                                    <th class="px-4 py-3 text-end">{{ t('common.total') }}</th>
                                    <th class="px-4 py-3 text-center">{{ t('returns.restockedColumn') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in saleReturn.items" :key="item.id" class="hover:bg-muted/20 [&>td]:align-middle">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ item.product_name }}</p>
                                        <p v-if="item.variant_label" class="text-xs text-muted-foreground">{{ item.variant_label }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center text-muted-foreground">{{ item.quantity_returned }}</td>
                                    <td class="px-4 py-3 text-end text-muted-foreground">{{ formatMoney(item.unit_price) }}</td>
                                    <td class="px-4 py-3 text-end font-semibold">{{ formatMoney(item.line_total) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="item.restock ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                                            class="text-xs font-medium">
                                            {{ item.restock ? t('common.yes') : t('common.no') }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total -->
                    <div class="mt-3 rounded-xl border bg-card p-4 flex justify-between items-center">
                        <span class="text-sm font-semibold">{{ t('returns.totalRefund') }}</span>
                        <span class="text-xl font-bold text-red-600 dark:text-red-400">
                            −{{ formatMoney(saleReturn.total_refund) }}
                        </span>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <!-- Return info -->
                    <div class="rounded-xl border bg-card p-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('returns.returnInfo') }}</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('returns.originalSale') }}</dt>
                                <dd>
                                    <Link :href="route('sales.show', saleReturn.sale.id)"
                                        class="font-mono text-xs text-primary hover:underline">
                                        {{ saleReturn.sale.invoice_number }}
                                    </Link>
                                </dd>
                            </div>
                            <div v-if="saleReturn.sale.customer" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.customer') }}</dt>
                                <dd class="font-medium">{{ saleReturn.sale.customer.name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('returns.refundMethodLabel') }}</dt>
                                <dd class="font-medium">{{ methodLabel[saleReturn.refund_method] ?? saleReturn.refund_method }}</dd>
                            </div>
                            <div v-if="saleReturn.branch" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('common.branch') }}</dt>
                                <dd class="font-medium">{{ saleReturn.branch }}</dd>
                            </div>
                            <div v-if="saleReturn.created_by" class="flex justify-between">
                                <dt class="text-muted-foreground">{{ t('returns.processedBy') }}</dt>
                                <dd class="font-medium">{{ saleReturn.created_by }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Reason/Notes -->
                    <div v-if="saleReturn.reason || saleReturn.notes" class="rounded-xl border bg-card p-4">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('common.notes') }}</h3>
                        <p v-if="saleReturn.reason" class="text-sm font-medium">{{ saleReturn.reason }}</p>
                        <p v-if="saleReturn.notes" class="text-sm text-muted-foreground mt-1">{{ saleReturn.notes }}</p>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
