<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatUnit, formatQty } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { Layers, Printer, Trash2, Truck } from 'lucide-vue-next';

interface ChallanItem {
    id: string; product_id: string | null; product_name: string; product_unit: string;
    lot_number: string | null; quantity: number; notes: string | null; material_type: string | null;
    tile_width_in: number | null; tile_height_in: number | null;
    tiles_per_box: number | null; sq_m_per_box: number | null;
}

const TILE_TYPES = ['tile', 'ceramic', 'mosaic', 'border'];

function tileSizeLabel(item: ChallanItem): string | null {
    if (!item.tile_width_in || !item.tile_height_in) return null;
    return `${item.tile_width_in} × ${item.tile_height_in} in`;
}

// Box + loose-tile breakdown from the m² quantity — so the driver knows what to load
function tileBreakdown(item: ChallanItem): string | null {
    if (!item.tiles_per_box || !item.sq_m_per_box) return null;
    if (!TILE_TYPES.includes(item.material_type ?? '')) return null;
    const sqmPerTile = item.sq_m_per_box / item.tiles_per_box;
    if (!sqmPerTile) return null;
    const totalTiles = Math.round(item.quantity / sqmPerTile);
    const boxes = Math.floor(totalTiles / item.tiles_per_box);
    const loose = totalTiles % item.tiles_per_box;
    if (boxes === 0 && loose === 0) return null;
    return loose > 0 ? `${boxes} box + ${loose} tile` : `${boxes} box`;
}
interface Challan {
    id: string; challan_number: string; status: string;
    delivery_date: string | null; vehicle_number: string | null; driver_name: string | null;
    site_address: string | null; notes: string | null;
    created_at: string; created_by: string | null;
    customer: { id: string; name: string; phone: string } | null;
    sale: { id: string; invoice_number: string } | null;
    quotation: { id: string; quotation_number: string } | null;
    items: ChallanItem[];
}
interface Business {
    name: string; phone: string | null; address: string | null;
    city: string | null; logo: string | null; footer: string | null;
}

const props = defineProps<{ challan: Challan; business: Business }>();
const { confirm } = useConfirm();

const statusBadge: Record<string, string> = {
    pending:    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    dispatched: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    delivered:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
};

function updateStatus(status: string) {
    router.patch(route('challans.update-status', props.challan.id), { status }, { preserveScroll: true });
}

async function deleteChallan() {
    if (await confirm({ title: 'Delete Challan', message: `Delete ${props.challan.challan_number}?`, confirmText: 'Delete', variant: 'destructive' })) {
        router.delete(route('challans.destroy', props.challan.id));
    }
}

function printChallan() {
    const c = props.challan;
    const b = props.business;
    const addr = [b.address, b.city].filter(Boolean).join(', ');

    const itemRows = c.items.map((item, i) => `
        <tr>
            <td class="num">${i + 1}</td>
            <td>
                <strong>${item.product_name}</strong>
                ${tileSizeLabel(item) ? `<br><span style="font-size:11px;color:#666">${tileSizeLabel(item)}</span>` : ''}
                ${item.material_type ? `<br><span style="font-size:11px;color:#666;text-transform:capitalize">${item.material_type}</span>` : ''}
                ${item.notes ? `<br><span style="font-size:11px;color:#888;font-style:italic">${item.notes}</span>` : ''}
            </td>
            <td class="mono">${item.lot_number ?? '—'}</td>
            <td class="r fw">
                ${formatQty(item.quantity, item.product_unit)}
                ${tileBreakdown(item) ? `<br><span style="font-size:12px;font-weight:700;color:#065f46">${tileBreakdown(item)}</span>` : ''}
            </td>
            <td>${formatUnit(item.product_unit)}</td>
        </tr>`).join('');

    const html = `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/>
<title>Delivery Challan ${c.challan_number}</title><style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#111;background:#fff;padding:12mm 14mm;}
.r{text-align:right;}.c{text-align:center;}.fw{font-weight:600;}.mono{font-family:monospace;font-size:12px;}
.num{text-align:center;color:#888;width:32px;}

.hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8mm;}
.biz-name{font-size:22px;font-weight:700;margin-bottom:3px;}
.biz-sub{font-size:12px;color:#555;line-height:1.6;}
.dc-badge{background:#065f46;color:#fff;padding:6px 16px;border-radius:4px;font-size:18px;font-weight:700;letter-spacing:1px;}
.dc-meta{font-size:12px;color:#555;margin-top:5px;text-align:right;line-height:1.6;}

.divider{border:none;border-top:2px solid #111;margin:5mm 0 4mm;}

.info-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:5mm;margin-bottom:6mm;font-size:12px;}
.info-label{font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:3px;}
.info-val{font-weight:600;}

table{width:100%;border-collapse:collapse;margin-bottom:5mm;}
thead tr{background:#064e3b;color:#fff;}
thead th{padding:7px 8px;font-size:11px;text-align:left;}
thead th.r{text-align:right;}
tbody tr{border-bottom:1px solid #eee;}
tbody tr:last-child{border-bottom:none;}
td{padding:7px 8px;vertical-align:top;font-size:12px;}

.sig-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10mm;margin-top:14mm;}
.sig-block{border-top:1px solid #333;padding-top:4px;font-size:11px;color:#555;text-align:center;}

.footer{text-align:center;font-size:11px;color:#888;border-top:1px solid #eee;padding-top:4mm;margin-top:6mm;}

@media print{body{padding:8mm 10mm;}@page{margin:0;size:A4;}}
</style></head><body>

<div class="hdr">
  <div>
    ${b.logo ? `<img src="${b.logo}" style="max-height:56px;max-width:180px;object-fit:contain;margin-bottom:6px;display:block"/>` : ''}
    <div class="biz-name">${b.name}</div>
    <div class="biz-sub">${[b.phone, addr].filter(Boolean).join('<br>')}</div>
  </div>
  <div>
    <div class="dc-badge">DELIVERY CHALLAN</div>
    <div class="dc-meta">
      <strong>${c.challan_number}</strong><br>
      Date: ${c.delivery_date ?? c.created_at}
    </div>
  </div>
</div>

<hr class="divider"/>

<div class="info-grid">
  <div>
    <div class="info-label">Customer</div>
    ${c.customer
        ? `<div class="info-val">${c.customer.name}</div><div>${c.customer.phone ?? ''}</div>`
        : `<div class="info-val">—</div>`}
  </div>
  <div>
    <div class="info-label">Site / Delivery Address</div>
    <div>${c.site_address ?? '—'}</div>
  </div>
  <div>
    <div class="info-label">Transport</div>
    ${c.vehicle_number ? `<div>Vehicle: <strong>${c.vehicle_number}</strong></div>` : ''}
    ${c.driver_name ? `<div>Driver: ${c.driver_name}</div>` : ''}
    ${!c.vehicle_number && !c.driver_name ? '<div>—</div>' : ''}
  </div>
</div>

<table>
  <thead>
    <tr>
      <th class="c">#</th>
      <th>Product / Description</th>
      <th>Lot #</th>
      <th class="r">Quantity</th>
      <th>Unit</th>
    </tr>
  </thead>
  <tbody>${itemRows}</tbody>
</table>

${c.notes ? `<div style="border:1px solid #ddd;border-radius:4px;padding:6px 10px;font-size:12px;margin-bottom:5mm;"><div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:3px;">Notes</div><div>${c.notes}</div></div>` : ''}

<div class="sig-grid">
  <div class="sig-block">Dispatched By</div>
  <div class="sig-block">Received By &nbsp; ${c.customer?.name ?? '_______________'}</div>
  <div class="sig-block">Date</div>
</div>

<div class="footer"><p>${b.footer ?? 'Thank you for your business!'}</p></div>

<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
</body></html>`;

    const win = window.open('', '_blank', 'width=900,height=750');
    if (win) { win.document.write(html); win.document.close(); }
}
</script>

<template>
    <Head :title="`Challan ${challan.challan_number}`" />
    <AppLayout :breadcrumbs="[{ title: 'Delivery Challans', href: '/challans' }, { title: challan.challan_number }]">
        <div class="flex flex-col gap-6 p-6 max-w-3xl mx-auto">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                        <Layers class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">{{ challan.challan_number }}</h1>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusBadge[challan.status] ?? '']">
                                {{ challan.status }}
                            </span>
                            <span class="text-xs text-muted-foreground">{{ challan.created_at }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="challan.status === 'pending'" @click="updateStatus('dispatched')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        <Truck class="h-3.5 w-3.5" /> Mark Dispatched
                    </button>
                    <button v-if="challan.status === 'dispatched'" @click="updateStatus('delivered')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition-colors">
                        <Truck class="h-3.5 w-3.5" /> Mark Delivered
                    </button>
                    <button @click="printChallan"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-input px-3 py-2 text-sm hover:bg-muted transition-colors">
                        <Printer class="h-3.5 w-3.5" /> Print
                    </button>
                    <button v-if="challan.status !== 'delivered'" @click="deleteChallan"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 text-red-600 px-3 py-2 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- Related links -->
            <div v-if="challan.sale || challan.quotation" class="flex gap-3 flex-wrap">
                <Link v-if="challan.sale" :href="route('sales.show', challan.sale.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 px-3 py-1.5 text-sm font-medium hover:opacity-80 transition-opacity">
                    Sale: {{ challan.sale.invoice_number }}
                </Link>
                <Link v-if="challan.quotation" :href="route('quotations.show', challan.quotation.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 px-3 py-1.5 text-sm font-medium hover:opacity-80 transition-opacity">
                    Quote: {{ challan.quotation.quotation_number }}
                </Link>
            </div>

            <!-- Details -->
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                    <h3 class="font-semibold text-sm">Customer</h3>
                    <div v-if="challan.customer">
                        <p class="font-medium">{{ challan.customer.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ challan.customer.phone }}</p>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">No customer</div>
                    <div v-if="challan.site_address" class="mt-2">
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-1">Site Address</p>
                        <p class="text-sm">{{ challan.site_address }}</p>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-5 space-y-2 text-sm">
                    <h3 class="font-semibold">Transport</h3>
                    <div class="flex justify-between"><span class="text-muted-foreground">Delivery Date</span><span>{{ challan.delivery_date ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-muted-foreground">Vehicle #</span><span class="font-mono">{{ challan.vehicle_number ?? '—' }}</span></div>
                    <div class="flex justify-between"><span class="text-muted-foreground">Driver</span><span>{{ challan.driver_name ?? '—' }}</span></div>
                    <div v-if="challan.notes" class="pt-2 border-t border-border">
                        <p class="text-xs text-muted-foreground uppercase tracking-wide mb-1">Notes</p>
                        <p>{{ challan.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <h3 class="font-semibold">Items Dispatched</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">#</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Product</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Lot #</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">Quantity</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(item, i) in challan.items" :key="item.id">
                            <td class="px-4 py-3 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ item.product_name }}</p>
                                <p v-if="tileSizeLabel(item)" class="text-xs text-muted-foreground">{{ tileSizeLabel(item) }}</p>
                                <p v-if="item.material_type" class="text-xs text-muted-foreground capitalize">{{ item.material_type }}</p>
                                <p v-if="item.notes" class="text-xs text-muted-foreground italic mt-0.5">{{ item.notes }}</p>
                            </td>
                            <td class="px-4 py-3 font-mono text-sm text-muted-foreground">{{ item.lot_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-end tabular-nums font-medium">
                                {{ formatQty(item.quantity, item.product_unit) }}
                                <div v-if="tileBreakdown(item)" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ tileBreakdown(item) }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ formatUnit(item.product_unit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>

