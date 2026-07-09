<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatQty, formatUnit, tileBreakdown } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Boxes, FileText, Printer, Search } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

interface Row {
    id: string; name: string; sku: string | null; unit: string | null; category: string | null;
    quantity: number; reorder_level: number;
    tiles_per_box: number | null; sq_m_per_box: number | null; material_type: string | null;
    tile_width_in: number | null; tile_height_in: number | null;
    manual_adjustments: number; manual_net: number; flagged: boolean;
}
interface Category { id: string; name: string }

const props = defineProps<{
    rows: Row[];
    categories: Category[];
    tile_sizes: string[];   // e.g. ['12x24', '24x48']
    stats: { total_products: number; flagged: number };
    filters: { category: string | null; from: string; to: string; search: string | null; tile_size: string | null };
}>();

const form = reactive({
    category:  props.filters.category  ?? '',
    tile_size: props.filters.tile_size ?? '',
    from:      props.filters.from,
    to:        props.filters.to,
    search:    props.filters.search ?? '',
});

function applyFilters() {
    router.get(route('inventory.reports.stock'), {
        category:  form.category  || undefined,
        tile_size: form.tile_size || undefined,
        from:      form.from      || undefined,
        to:        form.to        || undefined,
        search:    form.search    || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

// Live, debounced search (name / SKU / barcode)
let searchTimer: ReturnType<typeof setTimeout>;
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory', href: route('inventory.stock.index') },
    { title: 'Stock Report', href: '#' },
];

const TILE_MATERIALS = ['tile', 'ceramic', 'mosaic', 'border'];

// ── Tile totals (shown when any tile rows are visible) ─────────
const tileTotals = computed(() => {
    const tileRows = props.rows.filter(r => TILE_MATERIALS.includes(r.material_type ?? ''));
    if (tileRows.length === 0) return null;

    let totalSqm = 0;
    let totalBoxes = 0;
    let totalLoose = 0;
    let totalPieces = 0;

    for (const r of tileRows) {
        totalSqm += r.quantity;
        if (r.tiles_per_box && r.sq_m_per_box) {
            const sqmPerTile = r.sq_m_per_box / r.tiles_per_box;
            if (sqmPerTile > 0) {
                const pieces = Math.round(r.quantity / sqmPerTile);
                totalPieces += pieces;
                totalBoxes  += Math.floor(pieces / r.tiles_per_box);
                totalLoose  += pieces % r.tiles_per_box;
            }
        }
    }

    // Carry over excess loose tiles into boxes
    const firstWithBox = tileRows.find(r => r.tiles_per_box);
    const commonTpb = firstWithBox?.tiles_per_box ?? null;
    const mixedBoxSizes = tileRows.some(r => r.tiles_per_box !== commonTpb);
    if (!mixedBoxSizes && commonTpb && totalLoose >= commonTpb) {
        totalBoxes += Math.floor(totalLoose / commonTpb);
        totalLoose  = totalLoose % commonTpb;
    }

    return {
        sqm: Math.round(totalSqm * 100) / 100,
        pieces: totalPieces,
        boxes: totalBoxes,
        loose: totalLoose,
        tileCount: tileRows.length,
        mixedBoxSizes,
    };
});

function printReport() {
    const catLabel = props.categories.find(c => c.id === form.category)?.name ?? 'All categories';
    const rowsHtml = props.rows.map((r, i) => {
        const bd = tileBreakdown(r.quantity, r);
        const adjBd = tileBreakdown(r.manual_net, r);
        const sizeStr = (TILE_MATERIALS.includes(r.material_type ?? '') && r.tile_width_in && r.tile_height_in)
            ? `<br><span class="sub">${r.tile_width_in}" × ${r.tile_height_in}"</span>`
            : '';
        const adjCell = r.flagged
            ? `⚑ ${r.manual_adjustments}× (${formatQty(r.manual_net, r.unit)} ${formatUnit(r.unit)})${adjBd ? `<br><span class="sub">${adjBd}</span>` : ''}`
            : '—';
        return `
        <tr class="${r.flagged ? 'flagged' : ''}">
            <td class="num">${i + 1}</td>
            <td>${r.name}${r.sku ? `<br><span class="sub">${r.sku}</span>` : ''}${sizeStr}</td>
            <td>${r.category ?? '—'}</td>
            <td class="r fw">${formatQty(r.quantity, r.unit)} ${formatUnit(r.unit)}${bd ? `<br><span class="sub">${bd}</span>` : ''}</td>
            <td class="c">${adjCell}</td>
        </tr>`;
    }).join('');

    // Tile totals footer row for print
    const tt = tileTotals.value;
    const tfootHtml = tt ? `
        <tfoot>
            <tr class="totals-row">
                <td colspan="2" class="r"><strong>Total (${tt.tileCount} tile product${tt.tileCount !== 1 ? 's' : ''})</strong></td>
                <td class="r fw">
                    ${tt.sqm} m²<br>
                    <span class="sub">≈ ${tt.boxes} box${tt.loose ? ` + ${tt.loose} tile` : ''}</span><br>
                    <span class="sub">${tt.pieces.toLocaleString()} pcs total</span>
                </td>
                <td></td><td></td>
            </tr>
        </tfoot>` : '';

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"/><title>Stock Report</title><style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;font-size:14px;color:#111;padding:14mm;}
        h1{font-size:23px;margin-bottom:3px;} .meta{color:#444;font-size:16px;margin-bottom:14px;}
        table{width:100%;border-collapse:collapse;} th,td{padding:8px 10px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top;}
        th{background:#111;color:#fff;font-size:12px;text-transform:uppercase;letter-spacing:.5px;}
        .r{text-align:right;} .c{text-align:center;} .fw{font-weight:600;} .num{color:#888;width:34px;text-align:center;}
        .sub{color:#888;font-size:12px;} .foot{margin-top:12px;color:#555;font-size:13px;}
        tfoot tr.totals-row td{background:#f0f9ff;border-top:2px solid #0c4a6e;font-size:13px;}
        tr.flagged td{background:#fff7ed;} tr.flagged td:first-child{border-left:3px solid #f59e0b;}
        @media print{@page{size:A4;margin:10mm;} tr.flagged td,tfoot tr.totals-row td{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
    </style></head><body>
        <h1>Stock Report</h1>
        <div class="meta">${catLabel} · Adjustments flagged for ${form.from} → ${form.to} · Printed ${new Date().toLocaleString()}</div>
        <table><thead><tr><th>#</th><th>Product</th><th>Category</th><th class="r">Stock</th><th class="c">Manual Adj</th></tr></thead>
        <tbody>${rowsHtml}</tbody>${tfootHtml}</table>
        <div class="foot">${props.rows.length} products · ${props.stats.flagged} manually adjusted in period · ⚑ = adjusted (damage/theft/correction/other)</div>
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
    <Head title="Stock Report" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Stock Report</h1>
                    <p class="text-sm text-muted-foreground">Current stock-on-hand. Products manually adjusted in the date range are flagged.</p>
                </div>
                <button @click="printReport"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors">
                    <Printer class="h-4 w-4" /> Print
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:max-w-md">
                <StatCard label="Products" :value="String(stats.total_products)" tone="default" :icon="Boxes" />
                <StatCard label="Manually adjusted" :value="String(stats.flagged)" tone="warning" :icon="AlertTriangle" />
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5 rounded-xl border border-border bg-card p-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Category</label>
                    <select v-model="form.category" @change="applyFilters"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                        <option value="">All categories</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Tile Size</label>
                    <select v-model="form.tile_size" @change="applyFilters"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                        <option value="">All sizes</option>
                        <option v-for="s in tile_sizes" :key="s" :value="s">
                            {{ s.replace('x', '" × ') + '"' }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Adjustments from</label>
                    <input v-model="form.from" @change="applyFilters" type="date"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">to</label>
                    <input v-model="form.to" @change="applyFilters" type="date"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Search</label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="form.search" @input="onSearchInput" type="text" placeholder="Name, SKU or barcode"
                            class="w-full rounded-lg border border-input bg-background ps-8 pe-3 py-2 text-sm" />
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-muted/50">
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3 text-start">Product</th>
                            <th class="px-4 py-3 text-start">Category</th>
                            <th class="px-4 py-3 text-end">Stock</th>
                            <th class="px-4 py-3 text-center">Manual Adj (period)</th>
                            <th class="px-4 py-3 text-end"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in rows" :key="row.id" class="hover:bg-muted/20"
                            :class="row.flagged ? 'bg-amber-50/50 dark:bg-amber-950/10' : ''">
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ row.name }}</p>
                                <p v-if="row.sku" class="font-mono text-xs text-muted-foreground">{{ row.sku }}</p>
                                <p v-if="TILE_MATERIALS.includes(row.material_type ?? '') && row.tile_width_in && row.tile_height_in"
                                   class="text-xs text-muted-foreground">
                                    {{ row.tile_width_in }}" × {{ row.tile_height_in }}"
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.category ?? '—' }}</td>
                            <td class="px-4 py-3 text-end font-semibold tabular-nums">
                                {{ formatQty(row.quantity, row.unit) }} <span class="text-xs font-normal text-muted-foreground">{{ formatUnit(row.unit) }}</span>
                                <div v-if="tileBreakdown(row.quantity, row)" class="text-xs font-normal text-muted-foreground">{{ tileBreakdown(row.quantity, row) }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <template v-if="row.flagged">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                        <AlertTriangle class="h-3 w-3" /> {{ row.manual_adjustments }}× ({{ formatQty(row.manual_net, row.unit) }} {{ formatUnit(row.unit) }})
                                    </span>
                                    <div v-if="tileBreakdown(row.manual_net, row)" class="mt-0.5 text-xs text-muted-foreground">{{ tileBreakdown(row.manual_net, row) }}</div>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <Link :href="route('inventory.reports.stock-card', row.id)"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                                    <FileText class="h-3.5 w-3.5" /> Stock card
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No products found.</td>
                        </tr>
                    </tbody>
                    <!-- Tile totals footer row -->
                    <tfoot v-if="tileTotals">
                        <tr class="border-t-2 border-sky-300 bg-sky-50/80 dark:border-sky-700 dark:bg-sky-950/40 text-sky-900 dark:text-sky-200">
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                Total ({{ tileTotals.tileCount }} tile product{{ tileTotals.tileCount !== 1 ? 's' : '' }})
                            </td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-end tabular-nums">
                                <div class="font-bold">{{ tileTotals.sqm }} <span class="text-xs font-normal">m²</span></div>
                                <div class="text-xs font-semibold text-sky-700 dark:text-sky-300">
                                    ≈ {{ tileTotals.boxes }} box{{ tileTotals.loose ? ` + ${tileTotals.loose} tile` : '' }}
                                </div>
                                <div class="text-xs font-normal text-muted-foreground">
                                    {{ tileTotals.pieces.toLocaleString() }} pcs total
                                </div>
                            </td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
