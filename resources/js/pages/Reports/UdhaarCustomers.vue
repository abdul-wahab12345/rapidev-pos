<script setup lang="ts">
import ReportsFilterSummary, {
    type ReportFilterLabels,
} from '@/components/reports/ReportsFilterSummary.vue';
import ReportsLocationFilters from '@/components/reports/ReportsLocationFilters.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ReceiptText } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('reports.moduleTitle'), href: route('reports.index') },
    { title: t('reports.udhaarTitle'), href: route('reports.udhaar-customers') },
]);

interface Row {
    id: string;
    name: string;
    phone: string | null;
    gross_udhaar: number;
    open_po_due: number;
    net_after_vendor_ledger: number;
    net_after_open_po_due: number;
    oldest_sale_date: string | null;
}

defineProps<{
    rows: Row[];
    totals: {
        gross_udhaar: number;
        vendor_payable: number;
        open_po: number;
        net_vendor_ledger: number;
        net_vs_po: number;
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
    <Head :title="t('reports.udhaarTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="reports-print-root mx-auto max-w-none space-y-4 p-4 sm:p-6 lg:max-w-[90rem]">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ t('reports.udhaarTitle') }}</h1>
                    <p class="print-hide mt-1 max-w-3xl text-sm text-muted-foreground">{{ t('reports.udhaarSubtitle') }}</p>
                </div>
                <Link :href="route('reports.index')" class="print-hide text-sm text-primary hover:underline">
                    ← {{ t('reports.moduleTitle') }}
                </Link>
            </div>

            <ReportsFilterSummary :filter-labels="filter_labels" />

            <ReportsLocationFilters report-route="reports.udhaar-customers" :filters="filters" />

            <div class="print-hide rounded-lg border border-primary/15 bg-primary/5 p-4 text-xs text-muted-foreground sm:text-sm">
                <p>{{ t('reports.udhaarHelpLedger') }}</p>
                <p class="mt-2">{{ t('reports.udhaarHelpPo') }}</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center gap-2 border-b border-border px-4 py-3">
                    <ReceiptText class="print-hide size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <h2 class="font-semibold">{{ t('reports.udhaarTitle') }}</h2>
                </div>

                <table class="w-full border-collapse text-sm">
                    <thead class="border-b border-border bg-muted/40 [&>tr>th]:align-middle">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('reports.colCustomer') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('common.phone') }}</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">{{ t('reports.oldestSale') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('reports.colGrossUdhaar') }}</th>
                            <th
                                class="hidden px-4 py-3 text-end font-medium text-muted-foreground print:table-cell xl:table-cell"
                            >
                                {{ t('reports.colOpenPo') }}
                            </th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">{{ t('reports.colNetLedger') }}</th>
                            <th
                                class="hidden px-4 py-3 text-end font-medium text-muted-foreground print:table-cell 2xl:table-cell"
                            >
                                {{ t('reports.colNetPo') }}
                            </th>
                            <th class="px-4 py-3 text-end text-muted-foreground" data-report-table-actions-cell>{{ t('common.view') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border [&>tr>td]:align-middle">
                        <tr v-if="rows.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                                {{ t('reports.noCustomersUdhaar') }}
                            </td>
                            <td class="w-0 min-w-[1px] p-0 lg:min-w-[4.5rem]" data-report-table-actions-cell aria-hidden="true"></td>
                        </tr>
                        <tr v-for="r in rows" :key="r.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3 font-medium text-foreground">{{ r.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ r.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ fmtDate(r.oldest_sale_date) }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ formatMoney(r.gross_udhaar) }}</td>
                            <td class="hidden px-4 py-3 text-end tabular-nums print:table-cell xl:table-cell">
                                {{ formatMoney(r.open_po_due) }}
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums font-semibold" :class="r.net_after_vendor_ledger < 0 ? 'text-orange-600' : ''">
                                {{ formatMoney(r.net_after_vendor_ledger) }}
                            </td>
                            <td
                                class="hidden px-4 py-3 text-end tabular-nums print:table-cell 2xl:table-cell"
                                :class="r.net_after_open_po_due < 0 ? 'text-orange-600' : ''"
                            >
                                {{ formatMoney(r.net_after_open_po_due) }}
                            </td>
                            <td class="px-4 py-3 text-end lg:min-w-[4.5rem]" data-report-table-actions-cell>
                                <Link :href="route('customers.show', r.id)" class="text-xs text-primary hover:underline">
                                    {{ t('common.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0" class="border-t-2 border-border bg-muted/30 [&>tr>td]:align-middle">
                        <tr>
                            <td colspan="3" class="px-4 py-3 font-bold text-foreground">{{ t('reports.totalsFooter') }}</td>
                            <td class="px-4 py-3 text-end tabular-nums font-bold">{{ formatMoney(totals.gross_udhaar) }}</td>
                            <td class="hidden px-4 py-3 text-end tabular-nums font-bold print:table-cell xl:table-cell">
                                {{ formatMoney(totals.open_po) }}
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums font-bold">{{ formatMoney(totals.net_vendor_ledger) }}</td>
                            <td class="hidden px-4 py-3 text-end tabular-nums font-bold print:table-cell 2xl:table-cell">
                                {{ formatMoney(totals.net_vs_po) }}
                            </td>
                            <td data-report-table-actions-cell aria-hidden="true"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
