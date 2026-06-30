<script setup lang="ts">
import SearchableSelect, { type SearchableOption } from '@/components/SearchableSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatQty, formatUnit, tileBreakdown } from '@/utils/format';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Printer } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface Move {
    date: string; type: string; reference: string | null; ref_id: string | null; party: string | null;
    variant: string | null; in: number; out: number; note: string | null; balance: number;
}
interface Ledger {
    opening: number; closing: number; current_qty: number;
    total_in: number; total_out: number; rows: Move[];
}
interface ProductLite { id: string; name: string; sku: string | null }

const props = defineProps<{
    product: {
        id: string; name: string; sku: string | null; unit: string | null;
        tiles_per_box: number | null; sq_m_per_box: number | null; material_type: string | null;
    };
    ledger: Ledger;
    products: ProductLite[];
    filters: { from: string; to: string };
}>();

/** tile box/tile breakdown for this product */
const bd = (n: number) => tileBreakdown(n, props.product);

/** Link a movement's reference to its source document, when one exists. */
const REF_ROUTE: Record<string, string> = {
    sale: 'sales.show',
    purchase: 'purchasing.orders.show',
    return_in: 'returns.show',
};
function refLink(m: Move): string | null {
    if (!m.ref_id || !REF_ROUTE[m.type]) return null;
    return route(REF_ROUTE[m.type], m.ref_id);
}

const form = reactive({ product: props.product.id, from: props.filters.from, to: props.filters.to });

const productOptions = computed<SearchableOption[]>(() =>
    props.products.map(p => ({ value: p.id, label: p.name, subtitle: p.sku ?? undefined, keywords: p.sku ?? undefined })),
);

function apply() {
    router.get(route('inventory.reports.stock-card', form.product), {
        from: form.from || undefined, to: form.to || undefined,
    }, { preserveState: false, preserveScroll: true });
}

const TYPE_LABEL: Record<string, string> = {
    sale: 'Sale', purchase: 'Purchase', adjustment: 'Adjustment',
    return_in: 'Customer return', supplier_return: 'Supplier return',
};
const TYPE_CLASS: Record<string, string> = {
    sale: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    purchase: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    adjustment: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    return_in: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    supplier_return: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
};

const u = (n: number) => `${formatQty(n, props.product.unit)} ${formatUnit(props.product.unit)}`;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: route('inventory.stock.index') },
    { title: 'Stock Report', href: route('inventory.reports.stock') },
    { title: props.product.name, href: '#' },
];

function printCard() {
    const unit = formatUnit(props.product.unit);
    const balBd = (n: number) => { const b = bd(n); return b ? `<br><span class="sub">${b}</span>` : ''; };
    const rowsHtml = props.ledger.rows.map(m => {
        const flag = m.type === 'adjustment';
        return `
        <tr class="${flag ? 'flagged' : ''}">
            <td>${m.date}</td>
            <td>${flag ? '⚑ ' : ''}${TYPE_LABEL[m.type] ?? m.type}</td>
            <td>${m.reference ?? '—'}${m.variant ? `<br><span class="sub">${m.variant}</span>` : ''}</td>
            <td>${m.party ?? '—'}${m.note ? `<br><span class="sub">${m.note}</span>` : ''}</td>
            <td class="r">${m.in ? formatQty(m.in, props.product.unit) + ' ' + unit + balBd(m.in) : ''}</td>
            <td class="r">${m.out ? formatQty(m.out, props.product.unit) + ' ' + unit + balBd(m.out) : ''}</td>
            <td class="r fw">${formatQty(m.balance, props.product.unit)} ${unit}${balBd(m.balance)}</td>
        </tr>`;
    }).join('');

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"/><title>Stock Card – ${props.product.name}</title><style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#111;padding:14mm;}
        h1{font-size:24px;} .meta{color:#555;font-size:14px;margin:4px 0 12px;}
        table{width:100%;border-collapse:collapse;} th,td{padding:8px 10px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top;}
        th{background:#111;color:#fff;font-size:12px;text-transform:uppercase;}
        .r{text-align:right;} .fw{font-weight:600;} .sub{color:#888;font-size:12px;}
        .summary{display:flex;gap:28px;margin:10px 0 14px;font-size:14px;}
        .summary b{display:block;font-size:20px;margin-top:2px;}
        tr.flagged td{background:#faf5ff;} tr.flagged td:first-child{border-left:3px solid #a855f7;}
        @media print{@page{size:A4 landscape;margin:10mm;} tr.flagged td{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
    </style></head><body>
        <h1>Stock Card — ${props.product.name}</h1>
        <div class="meta">${props.product.sku ?? ''} · ${props.filters.from} → ${props.filters.to} · unit: ${formatUnit(props.product.unit)}</div>
        <div class="summary">
            <div>Opening<b>${formatQty(props.ledger.opening, props.product.unit)} ${unit}${balBd(props.ledger.opening)}</b></div>
            <div>Total In<b>${formatQty(props.ledger.total_in, props.product.unit)} ${unit}${balBd(props.ledger.total_in)}</b></div>
            <div>Total Out<b>${formatQty(props.ledger.total_out, props.product.unit)} ${unit}${balBd(props.ledger.total_out)}</b></div>
            <div>Closing<b>${formatQty(props.ledger.closing, props.product.unit)} ${unit}${balBd(props.ledger.closing)}</b></div>
        </div>
        <table><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Party</th><th class="r">In</th><th class="r">Out</th><th class="r">Balance</th></tr></thead>
        <tbody>
            <tr><td colspan="6"><i>Opening balance</i></td><td class="r fw">${formatQty(props.ledger.opening, props.product.unit)} ${unit}${balBd(props.ledger.opening)}</td></tr>
            ${rowsHtml}
        </tbody></table>
    </body></html>`;

    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 250);
}
</script>

<template>
    <Head :title="`Stock Card – ${product.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ product.name }}</h1>
                    <p class="text-sm text-muted-foreground">Stock card — every movement with running balance.</p>
                </div>
                <div class="flex gap-2">
                    <a :href="route('inventory.reports.stock')"
                        class="flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm text-muted-foreground hover:bg-accent transition-colors">
                        <ArrowLeft class="h-4 w-4" /> Back
                    </a>
                    <button @click="printCard"
                        class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors">
                        <Printer class="h-4 w-4" /> Print
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 rounded-xl border border-border bg-card p-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Product</label>
                    <SearchableSelect
                        v-model="form.product"
                        :options="productOptions"
                        search-placeholder="Search name or SKU…"
                        :clearable="false"
                        @update:model-value="apply"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">From</label>
                    <input v-model="form.from" @change="apply" type="date"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">To</label>
                    <input v-model="form.to" @change="apply" type="date"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Opening</p>
                    <p class="text-lg font-bold tabular-nums">{{ u(ledger.opening) }}</p>
                    <p v-if="bd(ledger.opening)" class="text-xs text-muted-foreground">{{ bd(ledger.opening) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Total In</p>
                    <p class="text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400">+{{ u(ledger.total_in) }}</p>
                    <p v-if="bd(ledger.total_in)" class="text-xs text-muted-foreground">{{ bd(ledger.total_in) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Total Out</p>
                    <p class="text-lg font-bold tabular-nums text-red-600 dark:text-red-400">−{{ u(ledger.total_out) }}</p>
                    <p v-if="bd(ledger.total_out)" class="text-xs text-muted-foreground">{{ bd(ledger.total_out) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <p class="text-xs text-muted-foreground">Closing</p>
                    <p class="text-lg font-bold tabular-nums">{{ u(ledger.closing) }}</p>
                    <p v-if="bd(ledger.closing)" class="text-xs text-muted-foreground">{{ bd(ledger.closing) }}</p>
                </div>
            </div>

            <!-- Ledger -->
            <div class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-muted/50">
                        <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3 text-start">Date</th>
                            <th class="px-4 py-3 text-start">Type</th>
                            <th class="px-4 py-3 text-start">Reference</th>
                            <th class="px-4 py-3 text-start">Party</th>
                            <th class="px-4 py-3 text-end">In</th>
                            <th class="px-4 py-3 text-end">Out</th>
                            <th class="px-4 py-3 text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr class="bg-muted/30">
                            <td colspan="6" class="px-4 py-2.5 text-xs font-medium text-muted-foreground italic">Opening balance</td>
                            <td class="px-4 py-2.5 text-end font-bold tabular-nums">
                                {{ u(ledger.opening) }}
                                <div v-if="bd(ledger.opening)" class="text-xs font-normal text-muted-foreground">{{ bd(ledger.opening) }}</div>
                            </td>
                        </tr>
                        <tr v-for="(m, i) in ledger.rows" :key="i"
                            :class="m.type === 'adjustment' ? 'bg-purple-50/60 dark:bg-purple-950/20' : 'hover:bg-muted/20'">
                            <td class="px-4 py-3 whitespace-nowrap text-muted-foreground">{{ m.date }}</td>
                            <td class="px-4 py-3">
                                <span :class="TYPE_CLASS[m.type] ?? 'bg-muted text-muted-foreground'"
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold">
                                    <AlertTriangle v-if="m.type === 'adjustment'" class="h-3 w-3" />{{ TYPE_LABEL[m.type] ?? m.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <a v-if="refLink(m)" :href="refLink(m)!" target="_blank"
                                    class="font-mono text-xs text-primary hover:underline">{{ m.reference }}</a>
                                <span v-else class="font-mono text-xs">{{ m.reference ?? '—' }}</span>
                                <span v-if="m.variant" class="block text-xs text-muted-foreground">{{ m.variant }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ m.party ?? '—' }}
                                <span v-if="m.note" class="block text-xs text-muted-foreground italic">{{ m.note }}</span>
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums text-emerald-600 dark:text-emerald-400">{{ m.in ? u(m.in) : '' }}</td>
                            <td class="px-4 py-3 text-end tabular-nums text-red-600 dark:text-red-400">{{ m.out ? u(m.out) : '' }}</td>
                            <td class="px-4 py-3 text-end font-semibold tabular-nums">
                                {{ u(m.balance) }}
                                <div v-if="bd(m.balance)" class="text-xs font-normal text-muted-foreground">{{ bd(m.balance) }}</div>
                            </td>
                        </tr>
                        <tr v-if="ledger.rows.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No movements in this date range.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
