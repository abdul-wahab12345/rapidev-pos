<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Package } from 'lucide-vue-next';

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Returns', href: '/returns' },
    { title: props.saleReturn.return_number, href: '#' },
];

const methodLabel: Record<string, string> = {
    cash: 'Cash', bank: 'Bank', store_credit: 'Store Credit',
};

function fmtDate(d: string) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-PK', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}
</script>

<template>
    <Head :title="`Return ${saleReturn.return_number}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-3xl">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-3">
                <Link href="/returns" class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors">
                    <ArrowLeft class="h-4 w-4" /> Back
                </Link>
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="font-mono text-2xl font-black tracking-tight">{{ saleReturn.return_number }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">
                            {{ saleReturn.status }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ fmtDate(saleReturn.return_date) }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <!-- Items table -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl border overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3 text-center">Qty</th>
                                    <th class="px-4 py-3 text-right">Unit</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3 text-center">Restocked</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in saleReturn.items" :key="item.id" class="hover:bg-muted/20">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ item.product_name }}</p>
                                        <p v-if="item.variant_label" class="text-xs text-muted-foreground">{{ item.variant_label }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center text-muted-foreground">{{ item.quantity_returned }}</td>
                                    <td class="px-4 py-3 text-right text-muted-foreground">{{ formatMoney(item.unit_price) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ formatMoney(item.line_total) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="item.restock ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                                            class="text-xs font-medium">
                                            {{ item.restock ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total -->
                    <div class="mt-3 rounded-xl border bg-card p-4 flex justify-between items-center">
                        <span class="text-sm font-semibold">Total Refund</span>
                        <span class="text-xl font-bold text-red-600 dark:text-red-400">
                            −{{ formatMoney(saleReturn.total_refund) }}
                        </span>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <!-- Return info -->
                    <div class="rounded-xl border bg-card p-4">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Return Info</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Original Sale</dt>
                                <dd>
                                    <Link :href="route('sales.show', saleReturn.sale.id)"
                                        class="font-mono text-xs text-primary hover:underline">
                                        {{ saleReturn.sale.invoice_number }}
                                    </Link>
                                </dd>
                            </div>
                            <div v-if="saleReturn.sale.customer" class="flex justify-between">
                                <dt class="text-muted-foreground">Customer</dt>
                                <dd class="font-medium">{{ saleReturn.sale.customer.name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Refund Method</dt>
                                <dd class="font-medium">{{ methodLabel[saleReturn.refund_method] ?? saleReturn.refund_method }}</dd>
                            </div>
                            <div v-if="saleReturn.branch" class="flex justify-between">
                                <dt class="text-muted-foreground">Branch</dt>
                                <dd class="font-medium">{{ saleReturn.branch }}</dd>
                            </div>
                            <div v-if="saleReturn.created_by" class="flex justify-between">
                                <dt class="text-muted-foreground">Processed By</dt>
                                <dd class="font-medium">{{ saleReturn.created_by }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Reason/Notes -->
                    <div v-if="saleReturn.reason || saleReturn.notes" class="rounded-xl border bg-card p-4">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Notes</h3>
                        <p v-if="saleReturn.reason" class="text-sm font-medium">{{ saleReturn.reason }}</p>
                        <p v-if="saleReturn.notes" class="text-sm text-muted-foreground mt-1">{{ saleReturn.notes }}</p>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
