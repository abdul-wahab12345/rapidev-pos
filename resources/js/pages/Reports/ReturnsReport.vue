<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Printer, RotateCcw, Undo2 } from 'lucide-vue-next';
import { reactive } from 'vue';

interface ReturnRow {
    id: string; return_number: string; return_date: string;
    sale_id: string | null; invoice_number: string; sale_date: string | null;
    customer: string; refund_method: string; items: number; total_refund: number;
    reason: string | null; prior_period: boolean;
}

const props = defineProps<{
    report: { rows: ReturnRow[]; total: number; count: number };
    filters: { from: string; to: string; method: string | null };
}>();

const form = reactive({ from: props.filters.from, to: props.filters.to, method: props.filters.method ?? '' });

function go() {
    router.get(route('reports.returns'), {
        from: form.from || undefined, to: form.to || undefined, method: form.method || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

const fmt = formatMoney;
const methodLabel: Record<string, string> = { cash: 'Cash', bank: 'Bank', store_credit: 'Store credit' };
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '#' }, { title: 'Returns Report', href: '#' }];

function printReport() {
    const f = props.filters;
    const rows = props.report.rows.map(r => `<tr class="${r.prior_period ? 'flagged' : ''}">
        <td>${r.return_date}</td><td>${r.return_number}</td><td>${r.invoice_number}</td>
        <td>${r.sale_date ?? '—'}${r.prior_period ? ' ⚑' : ''}</td><td>${r.customer}</td>
        <td>${methodLabel[r.refund_method] ?? r.refund_method}</td><td class="c">${r.items}</td>
        <td class="r fw">${fmt(r.total_refund)}</td></tr>`).join('');

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"/><title>Returns Report</title><style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#111;padding:14mm;}
        h1{font-size:23px;} .meta{color:#444;font-size:15px;margin:4px 0 10px;}
        .sum{display:flex;gap:26px;margin-bottom:12px;font-size:14px;} .sum b{display:block;font-size:19px;}
        table{width:100%;border-collapse:collapse;} th,td{padding:7px 9px;border-bottom:1px solid #ddd;text-align:left;}
        th{background:#111;color:#fff;font-size:12px;text-transform:uppercase;}
        .r{text-align:right;} .c{text-align:center;} .fw{font-weight:600;}
        tr.flagged td{background:#fff7ed;} tr.flagged td:first-child{border-left:3px solid #f59e0b;}
        .foot{margin-top:10px;color:#555;font-size:13px;}
        @media print{@page{size:A4 landscape;margin:10mm;} tr.flagged td{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
    </style></head><body>
        <h1>Returns Report</h1>
        <div class="meta">${f.from} → ${f.to} · by return date · Printed ${new Date().toLocaleString()}</div>
        <div class="sum"><div>Returns<b>${props.report.count}</b></div><div>Total Refunded<b>${fmt(props.report.total)}</b></div></div>
        <table><thead><tr><th>Return Date</th><th>Return #</th><th>Invoice</th><th>Sale Date</th><th>Customer</th><th>Method</th><th class="c">Items</th><th class="r">Refund</th></tr></thead>
        <tbody>${rows}</tbody></table>
        <div class="foot">⚑ = original sale is from before this report period.</div>
    </body></html>`;
    const w = window.open('', '_blank'); if (!w) return;
    w.document.write(html); w.document.close(); w.focus(); setTimeout(() => w.print(), 250);
}
</script>

<template>
    <Head title="Returns Report" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Returns Report</h1>
                    <p class="text-sm text-muted-foreground">Returns by the date they were processed, linked to the original invoice.</p>
                </div>
                <button @click="printReport" class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors">
                    <Printer class="h-4 w-4" /> Print
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:max-w-md">
                <StatCard label="Returns" :value="String(report.count)" tone="default" :icon="RotateCcw" />
                <StatCard label="Total Refunded" :value="fmt(report.total)" tone="danger" :icon="Undo2" />
            </div>

            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">From</label>
                    <input v-model="form.from" @change="go" type="date" class="rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">To</label>
                    <input v-model="form.to" @change="go" type="date" class="rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Refund method</label>
                    <select v-model="form.method" @change="go" class="rounded-lg border border-input bg-background px-3 py-2 text-sm">
                        <option value="">All</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                        <option value="store_credit">Store credit</option>
                    </select>
                </div>
            </div>

            <div class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-muted/50"><tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <th class="px-3 py-3 text-start">Return Date</th><th class="px-3 py-3 text-start">Return #</th><th class="px-3 py-3 text-start">Invoice</th>
                        <th class="px-3 py-3 text-start">Sale Date</th><th class="px-3 py-3 text-start">Customer</th><th class="px-3 py-3 text-start">Method</th>
                        <th class="px-3 py-3 text-center">Items</th><th class="px-3 py-3 text-end">Refund</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="r in report.rows" :key="r.id" :class="r.prior_period ? 'bg-amber-50/60 dark:bg-amber-950/10' : 'hover:bg-muted/20'">
                            <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">{{ r.return_date }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs">
                                <a :href="route('returns.show', r.id)" target="_blank" class="text-primary hover:underline">{{ r.return_number }}</a>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs">
                                <a v-if="r.sale_id" :href="route('sales.show', r.sale_id)" target="_blank" class="text-primary hover:underline">{{ r.invoice_number }}</a>
                                <span v-else>{{ r.invoice_number }}</span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">
                                {{ r.sale_date ?? '—' }}
                                <AlertTriangle v-if="r.prior_period" class="inline h-3.5 w-3.5 text-amber-500" />
                            </td>
                            <td class="px-3 py-2.5">{{ r.customer }}</td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ methodLabel[r.refund_method] ?? r.refund_method }}</td>
                            <td class="px-3 py-2.5 text-center">{{ r.items }}</td>
                            <td class="px-3 py-2.5 text-end font-semibold text-red-600 dark:text-red-400">{{ fmt(r.total_refund) }}</td>
                        </tr>
                        <tr v-if="!report.rows.length"><td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No returns in this period.</td></tr>
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-muted-foreground">
                <AlertTriangle class="inline h-3.5 w-3.5 text-amber-500" /> = the original sale is from before this report period.
            </p>
        </div>
    </AppLayout>
</template>
