<script setup lang="ts">
import ReportsFilterSummary, {
    type ReportFilterLabels,
} from '@/components/reports/ReportsFilterSummary.vue';
import ReportsLocationFilters from '@/components/reports/ReportsLocationFilters.vue';
import StatCard from '@/components/pos/StatCard.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ReceiptText, TrendingDown, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('reports.moduleTitle'), href: route('reports.index') },
    { title: t('reports.salesSummaryTitle'), href: route('reports.sales-summary') },
]);

const props = defineProps<{
    summary: {
        count: number;
        gross_sales: number;
        net_sales: number;
        udhaar_in_period: number;
        refund_total: number;
        by_payment: Record<string, { count: number; total: number }>;
    };
    filters: { from: string; to: string; city_id?: number | null; area_id?: number | null };
    filter_labels: ReportFilterLabels;
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

watch(
    () => [props.filters.from, props.filters.to],
    ([f, tval]) => {
        from.value = f;
        to.value = tval;
    },
);

const pickerFilters = computed(() => ({
    city_id: props.filters.city_id,
    area_id: props.filters.area_id,
}));

const mergeQuery = computed(() => ({
    from: from.value,
    to: to.value,
}));

/** Period line + print reflects current inputs (navigation may lag due to debounce). */
const printFilterLabels = computed<ReportFilterLabels>(() => ({
    ...props.filter_labels,
    from: from.value || props.filters.from,
    to: to.value || props.filters.to,
}));

function paymentLabel(method: string) {
    const m = method?.replace(/_/g, ' ') ?? '—';
    return m.charAt(0).toUpperCase() + m.slice(1);
}
</script>

<template>
    <Head :title="t('reports.salesSummaryTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="reports-print-root mx-auto max-w-5xl space-y-4 p-4 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ t('reports.salesSummaryTitle') }}</h1>
                    <p class="print-hide mt-1 text-sm text-muted-foreground">{{ t('reports.salesSummarySubtitle') }}</p>
                </div>
                <Link :href="route('reports.index')" class="print-hide text-sm text-primary hover:underline">
                    ← {{ t('reports.moduleTitle') }}
                </Link>
            </div>

            <ReportsFilterSummary :filter-labels="printFilterLabels" />

            <div class="print-hide flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted-foreground">{{ t('common.from') }}</span>
                <Input v-model="from" type="date" class="h-9 w-40" />
                <span class="text-muted-foreground">{{ t('common.to') }}</span>
                <Input v-model="to" type="date" class="h-9 w-40" />
            </div>

            <ReportsLocationFilters report-route="reports.sales-summary" :filters="pickerFilters" :merge-query="mergeQuery" />

            <p class="print-hide text-xs text-muted-foreground">{{ t('reports.salesLocationNote') }}</p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatCard
                    :label="t('reports.periodInvoices')"
                    :value="String(summary.count)"
                    :icon="ReceiptText"
                    tone="default"
                />
                <StatCard
                    :label="t('reports.grossSales')"
                    :value="formatMoney(summary.gross_sales)"
                    :icon="TrendingUp"
                    tone="success"
                />
                <StatCard
                    :label="t('reports.refundsInPeriod')"
                    :value="formatMoney(summary.refund_total)"
                    :icon="TrendingDown"
                    tone="warning"
                />
                <StatCard
                    :label="t('reports.netSales')"
                    :value="formatMoney(summary.net_sales)"
                    :icon="Wallet"
                    tone="info"
                />
            </div>

            <div class="rounded-xl border border-border bg-card px-4 py-3 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('reports.udhaarInPeriod') }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-400">
                    {{ formatMoney(summary.udhaar_in_period) }}
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border px-4 py-3 font-semibold text-foreground">{{ t('reports.byPayment') }}</div>
                <div class="divide-y divide-border">
                    <div v-if="Object.keys(summary.by_payment).length === 0" class="px-4 py-10 text-center text-muted-foreground text-sm">
                        {{ t('common.noResults') }}
                    </div>
                    <div
                        v-for="(bucket, method) in summary.by_payment"
                        :key="method"
                        class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm"
                    >
                        <div>
                            <span class="font-medium text-foreground">{{ paymentLabel(String(method)) }}</span>
                            <span class="ms-2 text-xs text-muted-foreground">{{ t('reports.count', { count: bucket.count }) }}</span>
                        </div>
                        <span class="tabular-nums font-semibold text-foreground">{{ formatMoney(bucket.total) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
