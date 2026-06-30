<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney, formatQty, formatUnit, tileBreakdown } from '@/utils/format';
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, Boxes, Printer, Receipt, Users } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface Txn { id: string; invoice_number: string; date: string; customer: string; cashier: string; items: number; discount: number; total: number; paid: number; udhaar: number; payment_method: string; status: string }
interface ProductRow { product_id: string | null; product_name: string; unit: string | null; tiles_per_box: number | null; sq_m_per_box: number | null; material_type: string | null; qty: number; revenue: number; invoices: number }
interface DayRow { day: string; count: number; amount: number; udhaar: number }
interface PartyRow { name: string; count: number; amount: number; udhaar?: number }
interface PayBucket { method: string; count: number; amount: number }
interface Summary { count: number; gross_sales: number; total_discount: number; total_udhaar: number; returns_in_period: number; by_payment: PayBucket[] }

const props = defineProps<{
    view: string;
    summary: Summary;
    filters: { from: string; to: string; payment: string | null };
    transactions?: Txn[];
    by_product?: ProductRow[];
    by_day?: DayRow[];
    by_party?: { customers: PartyRow[]; cashiers: PartyRow[] };
}>();

const form = reactive({ from: props.filters.from, to: props.filters.to });

const TABS = [
    { id: 'transactions', label: 'Transactions', icon: Receipt },
    { id: 'by_product', label: 'By Product', icon: Boxes },
    { id: 'by_day', label: 'By Day', icon: CalendarDays },
    { id: 'by_party', label: 'By Customer / Cashier', icon: Users },
];

function go(view = props.view) {
    router.get(route('reports.sales'), { view, from: form.from || undefined, to: form.to || undefined },
        { preserveState: true, preserveScroll: true, replace: true });
}

const fmt = formatMoney;
const statusBadge: Record<string, string> = {
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    partially_returned: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    returned: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};
const statusLabel: Record<string, string> = { completed: 'Completed', partially_returned: 'Part. returned', returned: 'Returned' };

const dayMax = computed(() => Math.max(1, ...(props.by_day ?? []).map(d => d.amount)));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '#' }, { title: 'Sales Report', href: '#' }];

function printReport() {
    const f = props.filters;
    let title = 'Sales Report', cols = '', rows = '';

    if (props.view === 'transactions') {
        title = 'Sales — Transactions';
        cols = '<th>Date</th><th>Invoice</th><th>Customer</th><th>Cashier</th><th class="c">Items</th><th class="r">Discount</th><th class="r">Total</th><th class="r">Udhaar</th><th>Payment</th>';
        rows = (props.transactions ?? []).map(t => `<tr><td>${t.date}</td><td>${t.invoice_number}</td><td>${t.customer}</td><td>${t.cashier}</td><td class="c">${t.items}</td><td class="r">${fmt(t.discount)}</td><td class="r fw">${fmt(t.total)}</td><td class="r">${t.udhaar ? fmt(t.udhaar) : '—'}</td><td>${t.payment_method}</td></tr>`).join('');
    } else if (props.view === 'by_product') {
        title = 'Sales — By Product';
        cols = '<th>Product</th><th class="r">Qty Sold</th><th class="c">Invoices</th><th class="r">Revenue</th>';
        rows = (props.by_product ?? []).map(p => {
            const tb = tileBreakdown(p.qty, p);
            return `<tr><td>${p.product_name}</td><td class="r">${formatQty(p.qty, p.unit)} ${formatUnit(p.unit)}${tb ? `<br><span style="color:#888;font-size:12px">${tb}</span>` : ''}</td><td class="c">${p.invoices}</td><td class="r fw">${fmt(p.revenue)}</td></tr>`;
        }).join('');
    } else if (props.view === 'by_day') {
        title = 'Sales — By Day';
        cols = '<th>Date</th><th class="c">Sales</th><th class="r">Amount</th><th class="r">Udhaar</th>';
        rows = (props.by_day ?? []).map(d => `<tr><td>${d.day}</td><td class="c">${d.count}</td><td class="r fw">${fmt(d.amount)}</td><td class="r">${d.udhaar ? fmt(d.udhaar) : '—'}</td></tr>`).join('');
    } else {
        title = 'Sales — By Customer / Cashier';
        cols = '<th>Customer</th><th class="c">Sales</th><th class="r">Amount</th><th class="r">Udhaar</th>';
        rows = (props.by_party?.customers ?? []).map(c => `<tr><td>${c.name}</td><td class="c">${c.count}</td><td class="r fw">${fmt(c.amount)}</td><td class="r">${c.udhaar ? fmt(c.udhaar) : '—'}</td></tr>`).join('')
            + `<tr><td colspan="4" style="padding-top:14px;font-weight:700;border:0">By Cashier</td></tr>`
            + (props.by_party?.cashiers ?? []).map(c => `<tr><td>${c.name}</td><td class="c">${c.count}</td><td class="r fw">${fmt(c.amount)}</td><td class="r">—</td></tr>`).join('');
    }

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"/><title>${title}</title><style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#111;padding:14mm;}
        h1{font-size:23px;} .meta{color:#444;font-size:15px;margin:4px 0 10px;}
        .sum{display:flex;gap:26px;margin-bottom:12px;font-size:14px;} .sum b{display:block;font-size:19px;}
        table{width:100%;border-collapse:collapse;} th,td{padding:7px 9px;border-bottom:1px solid #ddd;text-align:left;}
        th{background:#111;color:#fff;font-size:12px;text-transform:uppercase;}
        .r{text-align:right;} .c{text-align:center;} .fw{font-weight:600;}
        .memo{margin-top:12px;color:#b45309;font-size:13px;}
        @media print{@page{size:A4 landscape;margin:10mm;}}
    </style></head><body>
        <h1>${title}</h1>
        <div class="meta">${f.from} → ${f.to} · Printed ${new Date().toLocaleString()}</div>
        <div class="sum">
            <div>Invoices<b>${props.summary.count}</b></div>
            <div>Gross Sales<b>${fmt(props.summary.gross_sales)}</b></div>
            <div>Discount<b>${fmt(props.summary.total_discount)}</b></div>
            <div>Udhaar<b>${fmt(props.summary.total_udhaar)}</b></div>
        </div>
        <table><thead><tr>${cols}</tr></thead><tbody>${rows}</tbody></table>
        <div class="memo">Returns processed in this period: ${fmt(props.summary.returns_in_period)} (see Returns Report — not deducted above)</div>
    </body></html>`;
    const w = window.open('', '_blank'); if (!w) return;
    w.document.write(html); w.document.close(); w.focus(); setTimeout(() => w.print(), 250);
}
</script>

<template>
    <Head title="Sales Report" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Sales Report</h1>
                    <p class="text-sm text-muted-foreground">Gross sales by sale date. Returns are reported separately.</p>
                </div>
                <button @click="printReport" class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors">
                    <Printer class="h-4 w-4" /> Print
                </button>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Invoices" :value="String(summary.count)" tone="default" :icon="Receipt" />
                <StatCard label="Gross Sales" :value="fmt(summary.gross_sales)" tone="success" :icon="Boxes" />
                <StatCard label="Discount" :value="fmt(summary.total_discount)" tone="info" :icon="CalendarDays" />
                <StatCard label="Udhaar" :value="fmt(summary.total_udhaar)" tone="warning" :icon="Users" />
            </div>

            <!-- Date filter -->
            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">From</label>
                    <input v-model="form.from" @change="go()" type="date" class="rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">To</label>
                    <input v-model="form.to" @change="go()" type="date" class="rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-1 border-b border-border">
                <button v-for="tab in TABS" :key="tab.id" @click="go(tab.id)"
                    :class="view === tab.id ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    class="flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors -mb-px">
                    <component :is="tab.icon" class="h-4 w-4" /> {{ tab.label }}
                </button>
            </div>

            <!-- Transactions -->
            <div v-if="view === 'transactions'" class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-muted/50"><tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <th class="px-3 py-3 text-start">Date</th><th class="px-3 py-3 text-start">Invoice</th><th class="px-3 py-3 text-start">Customer</th>
                        <th class="px-3 py-3 text-start">Cashier</th><th class="px-3 py-3 text-center">Items</th><th class="px-3 py-3 text-end">Discount</th>
                        <th class="px-3 py-3 text-end">Total</th><th class="px-3 py-3 text-end">Udhaar</th><th class="px-3 py-3 text-start">Payment</th><th class="px-3 py-3 text-center">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="t in transactions" :key="t.id" class="hover:bg-muted/20">
                            <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">{{ t.date }}</td>
                            <td class="px-3 py-2.5"><a :href="route('sales.show', t.id)" target="_blank" class="font-mono text-xs text-primary hover:underline">{{ t.invoice_number }}</a></td>
                            <td class="px-3 py-2.5">{{ t.customer }}</td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ t.cashier }}</td>
                            <td class="px-3 py-2.5 text-center">{{ t.items }}</td>
                            <td class="px-3 py-2.5 text-end text-muted-foreground">{{ t.discount ? fmt(t.discount) : '—' }}</td>
                            <td class="px-3 py-2.5 text-end font-semibold">{{ fmt(t.total) }}</td>
                            <td class="px-3 py-2.5 text-end" :class="t.udhaar ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'">{{ t.udhaar ? fmt(t.udhaar) : '—' }}</td>
                            <td class="px-3 py-2.5 capitalize text-muted-foreground">{{ t.payment_method }}</td>
                            <td class="px-3 py-2.5 text-center"><span :class="statusBadge[t.status] ?? 'bg-muted text-muted-foreground'" class="rounded-full px-2 py-0.5 text-[11px] font-semibold">{{ statusLabel[t.status] ?? t.status }}</span></td>
                        </tr>
                        <tr v-if="!transactions?.length"><td colspan="10" class="px-4 py-10 text-center text-muted-foreground">No sales in this period.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- By Product -->
            <div v-else-if="view === 'by_product'" class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-muted/50"><tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 text-start">Product</th><th class="px-4 py-3 text-end">Qty Sold</th><th class="px-4 py-3 text-center">Invoices</th><th class="px-4 py-3 text-end">Revenue</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="p in by_product" :key="p.product_id ?? p.product_name" class="hover:bg-muted/20">
                            <td class="px-4 py-2.5 font-medium">{{ p.product_name }}</td>
                            <td class="px-4 py-2.5 text-end tabular-nums">
                                {{ formatQty(p.qty, p.unit) }} <span class="text-xs font-normal text-muted-foreground">{{ formatUnit(p.unit) }}</span>
                                <div v-if="tileBreakdown(p.qty, p)" class="text-xs font-normal text-muted-foreground">{{ tileBreakdown(p.qty, p) }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-center text-muted-foreground">{{ p.invoices }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ fmt(p.revenue) }}</td>
                        </tr>
                        <tr v-if="!by_product?.length"><td colspan="4" class="px-4 py-10 text-center text-muted-foreground">No sales in this period.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- By Day -->
            <div v-else-if="view === 'by_day'" class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-muted/50"><tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 text-start">Date</th><th class="px-4 py-3 text-center">Sales</th><th class="px-4 py-3 text-start w-1/2">Amount</th><th class="px-4 py-3 text-end">Udhaar</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="d in by_day" :key="d.day" class="hover:bg-muted/20">
                            <td class="px-4 py-2.5 whitespace-nowrap">{{ d.day }}</td>
                            <td class="px-4 py-2.5 text-center text-muted-foreground">{{ d.count }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 rounded bg-primary/70" :style="{ width: `${Math.max(4, (d.amount / dayMax) * 100)}%` }"></div>
                                    <span class="font-semibold tabular-nums">{{ fmt(d.amount) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-end text-muted-foreground">{{ d.udhaar ? fmt(d.udhaar) : '—' }}</td>
                        </tr>
                        <tr v-if="!by_day?.length"><td colspan="4" class="px-4 py-10 text-center text-muted-foreground">No sales in this period.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- By Customer / Cashier -->
            <div v-else class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-border overflow-hidden">
                    <div class="bg-muted/50 px-4 py-2.5 text-sm font-semibold">Top Customers</div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="c in by_party?.customers" :key="c.name" class="hover:bg-muted/20">
                                <td class="px-4 py-2.5">{{ c.name }}</td>
                                <td class="px-4 py-2.5 text-center text-muted-foreground">{{ c.count }}</td>
                                <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ fmt(c.amount) }}</td>
                            </tr>
                            <tr v-if="!by_party?.customers?.length"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">No data.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="rounded-xl border border-border overflow-hidden">
                    <div class="bg-muted/50 px-4 py-2.5 text-sm font-semibold">By Cashier</div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-border">
                            <tr v-for="c in by_party?.cashiers" :key="c.name" class="hover:bg-muted/20">
                                <td class="px-4 py-2.5">{{ c.name }}</td>
                                <td class="px-4 py-2.5 text-center text-muted-foreground">{{ c.count }}</td>
                                <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ fmt(c.amount) }}</td>
                            </tr>
                            <tr v-if="!by_party?.cashiers?.length"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">No data.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Returns memo -->
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/40 dark:bg-amber-950/20 dark:text-amber-300">
                Returns processed in this period: <strong>{{ fmt(summary.returns_in_period) }}</strong>
                <span class="text-amber-700/80 dark:text-amber-400/80">— not deducted above.</span>
                <a :href="route('reports.returns', { from: filters.from, to: filters.to })" class="ms-1 font-medium underline">View Returns Report</a>
            </div>
        </div>
    </AppLayout>
</template>
