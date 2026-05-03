<script setup lang="ts">
import ReportsFilterSummary, {
    type ReportFilterLabels,
} from '@/components/reports/ReportsFilterSummary.vue';
import ReportsLocationFilters from '@/components/reports/ReportsLocationFilters.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('reports.moduleTitle'), href: route('reports.index') },
    { title: t('reports.vendorPayTitle'), href: route('reports.payable-vendors') },
]);

interface Row {
    id: string;
    name: string;
    company: string | null;
    phone: string | null;
    vendor_ledger: number;
    open_po_due: number;
    ar_balance: number;
    net_payable: number;
    oldest_po_date: string | null;
    age_days: number | null;
}

const props = defineProps<{
    rows: Row[];
    totals: {
        vendor_ledger: number;
        open_po: number;
        ar_balance: number;
        net_payable: number;
    };
    filters: { city_id?: number | null; area_id?: number | null };
    filter_labels: ReportFilterLabels;
}>();

function fmtDate(d: string | null) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head :title="t('reports.vendorPayTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="reports-print-root mx-auto max-w-none space-y-4 p-4 sm:p-6 lg:max-w-[90rem]">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ t('reports.vendorPayTitle') }}</h1>
                    <p class="print-hide mt-1 max-w-3xl text-sm text-muted-foreground">{{ t('reports.vendorPaySubtitle') }}</p>
                </div>
                <Link :href="route('reports.index')" class="print-hide text-sm text-primary hover:underline">
                    ← {{ t('reports.moduleTitle') }}
                </Link>
            </div>

            <ReportsFilterSummary :filter-labels="filter_labels" />

            <ReportsLocationFilters report-route="reports.payable-vendors" :filters="filters" />

            <div class="print-hide rounded-lg border border-amber-500/20 bg-amber-500/5 p-4 text-xs text-muted-foreground sm:text-sm">
                {{ t('reports.vendorHelpPo') }}
            </div>

            <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center gap-2 border-b border-border px-4 py-3">
                    <Wallet class="print-hide size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <h2 class="font-semibold">{{ t('reports.vendorPayTitle') }}</h2>
                </div>

                <table class="w-full border-collapse text-sm">
                    <thead class="border-b border-border bg-muted/40 [&>tr>th]:align-middle">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('reports.colVendor') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('common.phone') }}</th>
                            <th class="hidden px-4 py-3 text-start font-medium text-muted-foreground print:table-cell lg:table-cell">
                                {{ t('reports.oldestPo') }}
                            </th>
                            <th
                                class="hidden px-4 py-3 text-center font-medium text-muted-foreground print:table-cell lg:table-cell"
                            >
                                {{ t('reports.colAge') }}
                            </th>
                            <th class="px-4 py-3 text-end font-medium text-amber-700 dark:text-amber-400">{{ t('reports.colPayableLedger') }}</th>
                            <th class="hidden px-4 py-3 text-end font-medium text-muted-foreground print:table-cell md:table-cell">
                                {{ t('reports.colOpenPo') }}
                            </th>
                            <th
                                class="hidden px-4 py-3 text-end font-medium text-blue-700 dark:text-blue-400 print:table-cell sm:table-cell"
                            >
                                {{ t('reports.colArOffset') }}
                            </th>
                            <th class="px-4 py-3 text-end font-medium text-foreground">{{ t('reports.colNetPayable') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground" data-report-table-actions-cell>
                                {{ t('common.view') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border [&>tr>td]:align-middle">
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center text-muted-foreground">{{ t('reports.noPayables') }}</td>
                            <td class="w-0 min-w-[1px] p-0 lg:min-w-[4.5rem]" data-report-table-actions-cell aria-hidden="true"></td>
                        </tr>
                        <tr v-for="r in rows" :key="r.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3">
                                <div class="font-medium text-foreground">{{ r.name }}</div>
                                <div v-if="r.company" class="text-xs text-muted-foreground">{{ r.company }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ r.phone ?? '—' }}</td>
                            <td class="hidden px-4 py-3 text-xs text-muted-foreground print:table-cell lg:table-cell">
                                {{ fmtDate(r.oldest_po_date) }}
                            </td>
                            <td class="hidden px-4 py-3 text-center tabular-nums text-muted-foreground print:table-cell lg:table-cell">
                                {{ r.age_days ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums font-semibold text-amber-600">{{ formatMoney(r.vendor_ledger) }}</td>
                            <td class="hidden px-4 py-3 text-end tabular-nums print:table-cell md:table-cell">{{ formatMoney(r.open_po_due) }}</td>
                            <td class="hidden px-4 py-3 text-end tabular-nums print:table-cell sm:table-cell">{{ r.ar_balance > 0 ? formatMoney(r.ar_balance) : '—' }}</td>
                            <td class="px-4 py-3 text-end tabular-nums font-bold text-orange-600 dark:text-orange-400">
                                {{ formatMoney(r.net_payable) }}
                            </td>
                            <td class="px-4 py-3 text-end lg:min-w-[4.5rem]" data-report-table-actions-cell>
                                <Link :href="route('purchasing.suppliers.show', r.id)" class="text-xs text-primary hover:underline">
                                    {{ t('common.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0" class="border-t-2 border-border bg-muted/30 [&>tr>td]:align-middle">
                        <tr>
                            <td colspan="4" class="px-4 py-3 font-bold text-foreground">{{ t('reports.totalsFooter') }}</td>
                            <td class="px-4 py-3 text-end tabular-nums font-bold text-amber-600">{{ formatMoney(totals.vendor_ledger) }}</td>
                            <td class="hidden px-4 py-3 text-end tabular-nums font-bold print:table-cell md:table-cell">
                                {{ formatMoney(totals.open_po) }}
                            </td>
                            <td class="hidden px-4 py-3 text-end tabular-nums font-bold print:table-cell text-blue-600 sm:table-cell">
                                {{ formatMoney(totals.ar_balance) }}
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums font-bold text-orange-600">{{ formatMoney(totals.net_payable) }}</td>
                            <td data-report-table-actions-cell aria-hidden="true"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
