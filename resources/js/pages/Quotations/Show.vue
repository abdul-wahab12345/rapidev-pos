<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatMoney } from '@/utils/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle, ChevronDown, Edit, FileText, Layers, Printer, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface QuotationItem {
    id: string; product_id: string | null; product_name: string; product_unit: string;
    quantity: number; unit_price: number; discount: number; line_total: number; notes: string | null;
    sq_m_per_box: number; material_type: string | null;
}
interface Quotation {
    id: string; quotation_number: string; status: string;
    site_address: string | null; valid_until: string | null;
    subtotal: number; discount: number; tax: number; delivery_fee: number; total: number;
    advance_paid: number; balance_due: number;
    notes: string | null; converted_sale_id: string | null;
    created_at: string; created_by: string | null;
    customer: { id: string; name: string; phone: string } | null;
    items: QuotationItem[];
}

interface Business {
    name: string; phone: string | null; address: string | null;
    city: string | null; logo: string | null; footer: string | null;
}

const props = defineProps<{ quotation: Quotation; business: Business; customers: any[]; products: any[] }>();
const { confirm } = useConfirm();

const showStatusMenu = ref(false);
const showConvertModal = ref(false);

const statusBadge: Record<string, string> = {
    draft:     'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    sent:      'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    approved:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    converted: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    expired:   'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

function updateStatus(status: string) {
    router.patch(route('quotations.update-status', props.quotation.id), { status }, { preserveScroll: true });
    showStatusMenu.value = false;
}

async function deleteQuotation() {
    if (await confirm({ title: 'Delete Quotation', message: `Delete ${props.quotation.quotation_number}? This cannot be undone.`, confirmText: 'Delete', variant: 'destructive' })) {
        router.delete(route('quotations.destroy', props.quotation.id));
    }
}

// Convert to sale
const convertForm = useForm({ cash: 0, bank: 0, jazzcash: 0, easypaisa: 0, udhaar: 0 });
const convertTotal = ref(props.quotation.balance_due);

function submitConvert() {
    convertForm.post(route('quotations.convert', props.quotation.id));
}

function printQuotation() {
    const q = props.quotation;
    const b = props.business;
    const fm = (n: number) => formatMoney(n);
    const addr = [b.address, b.city].filter(Boolean).join(', ');

    const itemRows = q.items.map((item, i) => `
        <tr>
            <td class="num">${i + 1}</td>
            <td>
                <strong>${item.product_name}</strong>
                ${item.product_unit ? `<br><span style="font-size:11px;color:#666">${item.product_unit}${item.material_type ? ' · ' + item.material_type : ''}</span>` : ''}
                ${item.notes ? `<br><span style="font-size:11px;color:#888;font-style:italic">${item.notes}</span>` : ''}
            </td>
            <td class="r">${item.quantity}</td>
            <td class="r">${fm(item.unit_price)}</td>
            <td class="r">${item.discount > 0 ? '−' + fm(item.discount) : '—'}</td>
            <td class="r fw">${fm(item.line_total)}</td>
        </tr>`).join('');

    const html = `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"/>
<title>Quotation ${q.quotation_number}</title><style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#111;background:#fff;padding:12mm 14mm;}
.r{text-align:right;}.c{text-align:center;}.fw{font-weight:600;}
.num{text-align:center;color:#888;width:32px;}

.hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8mm;}
.biz-name{font-size:22px;font-weight:700;margin-bottom:3px;}
.biz-sub{font-size:12px;color:#555;line-height:1.6;}
.quo-badge{background:#1e40af;color:#fff;padding:6px 16px;border-radius:4px;font-size:18px;font-weight:700;letter-spacing:1px;}
.quo-meta{font-size:12px;color:#555;margin-top:5px;text-align:right;line-height:1.6;}

.divider{border:none;border-top:2px solid #111;margin:5mm 0 4mm;}
.divider-sm{border:none;border-top:1px solid #ddd;margin:3mm 0;}

.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:6mm;margin-bottom:6mm;font-size:12px;}
.info-label{font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:3px;}
.info-val{font-weight:600;}

table{width:100%;border-collapse:collapse;margin-bottom:5mm;}
thead tr{background:#1e3a5f;color:#fff;}
thead th{padding:7px 8px;font-size:11px;text-align:left;}
thead th.r{text-align:right;}
tbody tr{border-bottom:1px solid #eee;}
tbody tr:last-child{border-bottom:none;}
td{padding:6px 8px;vertical-align:top;font-size:12px;}

.totals-wrap{display:flex;justify-content:flex-end;margin-bottom:6mm;}
.totals{width:240px;}
.totals tr td{padding:3px 6px;font-size:13px;}
.totals tr td:last-child{text-align:right;}
.tot-grand td{font-size:16px;font-weight:700;border-top:2px solid #111;padding-top:5px;}
.tot-balance td{font-size:14px;font-weight:700;color:#b45309;border-top:1px dashed #ddd;padding-top:4px;}
.tot-advance td{color:#059669;font-weight:600;}

.notes-box{border:1px solid #ddd;border-radius:4px;padding:6px 10px;font-size:12px;margin-bottom:6mm;}
.notes-label{font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:3px;}

.sig-grid{display:grid;grid-template-columns:1fr 1fr;gap:10mm;margin-top:10mm;}
.sig-block{border-top:1px solid #333;padding-top:4px;font-size:11px;color:#555;}

.footer{text-align:center;font-size:11px;color:#888;border-top:1px solid #eee;padding-top:4mm;margin-top:4mm;}

.validity-badge{display:inline-block;background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:2px 8px;font-size:11px;color:#92400e;font-weight:600;}

@media print{body{padding:8mm 10mm;}@page{margin:0;size:A4;}}
</style></head><body>

<!-- Header -->
<div class="hdr">
  <div>
    ${b.logo ? `<img src="${b.logo}" style="max-height:56px;max-width:180px;object-fit:contain;margin-bottom:6px;display:block"/>` : ''}
    <div class="biz-name">${b.name}</div>
    <div class="biz-sub">
      ${[b.phone, addr].filter(Boolean).join('<br>')}
    </div>
  </div>
  <div>
    <div class="quo-badge">QUOTATION</div>
    <div class="quo-meta">
      <strong>${q.quotation_number}</strong><br>
      Date: ${q.created_at}<br>
      ${q.valid_until ? `<span class="validity-badge">Valid Until: ${q.valid_until}</span>` : ''}
    </div>
  </div>
</div>

<hr class="divider"/>

<!-- Customer / Site info -->
<div class="info-grid">
  <div>
    <div class="info-label">Quotation For</div>
    ${q.customer
        ? `<div class="info-val">${q.customer.name}</div><div>${q.customer.phone ?? ''}</div>`
        : `<div class="info-val">Walk-in Customer</div>`}
  </div>
  ${q.site_address ? `
  <div>
    <div class="info-label">Site / Delivery Address</div>
    <div>${q.site_address}</div>
  </div>` : ''}
</div>

<!-- Items -->
<table>
  <thead>
    <tr>
      <th class="c">#</th>
      <th>Description</th>
      <th class="r">Qty</th>
      <th class="r">Unit Price</th>
      <th class="r">Discount</th>
      <th class="r">Amount</th>
    </tr>
  </thead>
  <tbody>${itemRows}</tbody>
</table>

<!-- Totals -->
<div class="totals-wrap">
  <table class="totals">
    <tr><td>Subtotal</td><td>${fm(q.subtotal)}</td></tr>
    ${q.discount > 0 ? `<tr><td>Discount</td><td>−${fm(q.discount)}</td></tr>` : ''}
    ${q.tax > 0 ? `<tr><td>Tax</td><td>${fm(q.tax)}</td></tr>` : ''}
    ${q.delivery_fee > 0 ? `<tr><td>Delivery Fee</td><td>${fm(q.delivery_fee)}</td></tr>` : ''}
    <tr class="tot-grand"><td>Total</td><td>${fm(q.total)}</td></tr>
    ${q.advance_paid > 0 ? `<tr class="tot-advance"><td>Advance Paid</td><td>−${fm(q.advance_paid)}</td></tr>` : ''}
    ${q.balance_due > 0 ? `<tr class="tot-balance"><td>Balance Due</td><td>${fm(q.balance_due)}</td></tr>` : ''}
  </table>
</div>

${q.notes ? `
<div class="notes-box">
  <div class="notes-label">Notes / Terms</div>
  <div>${q.notes}</div>
</div>` : ''}

<!-- Signature block -->
<div class="sig-grid">
  <div class="sig-block">Authorized Signature &nbsp;&nbsp; ${b.name}</div>
  <div class="sig-block">Customer Acceptance &nbsp;&nbsp; ${q.customer?.name ?? '_______________'}</div>
</div>

<!-- Footer -->
<div class="footer">
  <p>${b.footer ?? 'Thank you for your business!'}</p>
</div>

<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
</body></html>`;

    const win = window.open('', '_blank', 'width=900,height=750');
    if (win) { win.document.write(html); win.document.close(); }
}
</script>

<template>
    <Head :title="`Quotation ${quotation.quotation_number}`" />
    <AppLayout :breadcrumbs="[{ title: 'Quotations', href: '/quotations' }, { title: quotation.quotation_number }]">
        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                        <FileText class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">{{ quotation.quotation_number }}</h1>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusBadge[quotation.status] ?? '']">
                                {{ quotation.status }}
                            </span>
                            <span class="text-xs text-muted-foreground">{{ quotation.created_at }}</span>
                            <span v-if="quotation.created_by" class="text-xs text-muted-foreground">by {{ quotation.created_by }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <!-- Print -->
                    <button @click="printQuotation"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-input px-3 py-2 text-sm hover:bg-muted transition-colors">
                        <Printer class="h-3.5 w-3.5" /> Print
                    </button>

                    <!-- Status change (not for converted/cancelled) -->
                    <div v-if="!['converted', 'cancelled'].includes(quotation.status)" class="relative">
                        <button @click="showStatusMenu = !showStatusMenu"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-input px-3 py-2 text-sm hover:bg-muted transition-colors">
                            Change Status <ChevronDown class="h-3.5 w-3.5" />
                        </button>
                        <div v-if="showStatusMenu" class="absolute end-0 z-10 mt-1 w-40 rounded-lg border border-border bg-card shadow-lg py-1">
                            <button v-for="s in ['draft','sent','approved','expired','cancelled']" :key="s" @click="updateStatus(s)"
                                class="w-full px-3 py-2 text-start text-sm capitalize hover:bg-muted transition-colors"
                                :class="quotation.status === s ? 'font-semibold text-primary' : ''">
                                {{ s }}
                            </button>
                        </div>
                    </div>

                    <Link v-if="!['converted', 'cancelled'].includes(quotation.status)"
                        :href="route('quotations.edit', quotation.id)"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-input px-3 py-2 text-sm hover:bg-muted transition-colors">
                        <Edit class="h-3.5 w-3.5" /> Edit
                    </Link>

                    <!-- Create Challan -->
                    <Link :href="route('challans.create') + '?quotation_id=' + quotation.id"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-input px-3 py-2 text-sm hover:bg-muted transition-colors">
                        <Layers class="h-3.5 w-3.5" /> Delivery Challan
                    </Link>

                    <!-- Convert to Sale -->
                    <button v-if="quotation.status === 'approved'" @click="showConvertModal = true"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition-colors">
                        <ShoppingCart class="h-3.5 w-3.5" /> Convert to Sale
                    </button>

                    <button @click="deleteQuotation"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 text-red-600 px-3 py-2 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- Converted banner -->
            <div v-if="quotation.status === 'converted' && quotation.converted_sale_id" class="rounded-xl bg-purple-500/10 border border-purple-500/20 p-4 flex items-center gap-3">
                <CheckCircle class="h-5 w-5 text-purple-600 shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-purple-700 dark:text-purple-300">Converted to Sale</p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        This quotation has been converted. <Link :href="route('sales.show', quotation.converted_sale_id)" class="underline">View Sale →</Link>
                    </p>
                </div>
            </div>

            <!-- Details grid -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Customer info -->
                <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                    <h3 class="font-semibold text-sm">Customer</h3>
                    <div v-if="quotation.customer">
                        <p class="font-medium">{{ quotation.customer.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ quotation.customer.phone }}</p>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">Walk-in customer</div>
                    <div v-if="quotation.site_address" class="mt-2">
                        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide mb-1">Site Address</p>
                        <p class="text-sm">{{ quotation.site_address }}</p>
                    </div>
                </div>
                <!-- Quotation meta -->
                <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                    <h3 class="font-semibold text-sm">Details</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Quotation #</span>
                            <span class="font-mono font-medium">{{ quotation.quotation_number }}</span>
                        </div>
                        <div v-if="quotation.valid_until" class="flex justify-between">
                            <span class="text-muted-foreground">Valid Until</span>
                            <span>{{ quotation.valid_until }}</span>
                        </div>
                        <div v-if="quotation.notes" class="pt-2 border-t border-border">
                            <span class="text-xs text-muted-foreground uppercase tracking-wide">Notes</span>
                            <p class="text-sm mt-1">{{ quotation.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <h3 class="font-semibold">Items</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Product</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">Qty</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">Unit Price</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground hidden sm:table-cell">Discount</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in quotation.items" :key="item.id">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ item.product_name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.product_unit }}
                                    <span v-if="item.material_type" class="capitalize"> · {{ item.material_type }}</span>
                                    <span v-if="item.sq_m_per_box > 0" class="text-blue-500"> · {{ item.sq_m_per_box }}m²/box</span>
                                </p>
                                <p v-if="item.notes" class="text-xs text-muted-foreground italic mt-0.5">{{ item.notes }}</p>
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ item.quantity }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ formatMoney(item.unit_price) }}</td>
                            <td class="px-4 py-3 text-end tabular-nums hidden sm:table-cell text-muted-foreground">
                                {{ item.discount > 0 ? '- ' + formatMoney(item.discount) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums font-medium">{{ formatMoney(item.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="px-5 py-4 border-t border-border">
                    <div class="ms-auto max-w-xs space-y-2 text-sm">
                        <div class="flex justify-between text-muted-foreground">
                            <span>Subtotal</span><span class="tabular-nums">{{ formatMoney(quotation.subtotal) }}</span>
                        </div>
                        <div v-if="quotation.discount > 0" class="flex justify-between text-muted-foreground">
                            <span>Discount</span><span class="tabular-nums text-red-500">- {{ formatMoney(quotation.discount) }}</span>
                        </div>
                        <div v-if="quotation.tax > 0" class="flex justify-between text-muted-foreground">
                            <span>Tax</span><span class="tabular-nums">{{ formatMoney(quotation.tax) }}</span>
                        </div>
                        <div v-if="quotation.delivery_fee > 0" class="flex justify-between text-muted-foreground">
                            <span>Delivery Fee</span><span class="tabular-nums">{{ formatMoney(quotation.delivery_fee) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-base pt-2 border-t border-border">
                            <span>Total</span><span class="tabular-nums">{{ formatMoney(quotation.total) }}</span>
                        </div>
                        <div v-if="quotation.advance_paid > 0" class="flex justify-between text-emerald-600 font-medium">
                            <span>Advance Paid</span><span class="tabular-nums">- {{ formatMoney(quotation.advance_paid) }}</span>
                        </div>
                        <div v-if="quotation.balance_due > 0" class="flex justify-between font-bold text-amber-600">
                            <span>Balance Due</span><span class="tabular-nums">{{ formatMoney(quotation.balance_due) }}</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>

    <!-- Convert to Sale Modal -->
    <Teleport to="body">
        <div v-if="showConvertModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-card border border-border shadow-xl p-6 space-y-5">
                <div>
                    <h2 class="text-lg font-bold">Convert to Sale</h2>
                    <p class="text-sm text-muted-foreground mt-1">
                        Total: <strong>{{ formatMoney(quotation.total) }}</strong>
                        <template v-if="quotation.advance_paid > 0">
                            · Advance: {{ formatMoney(quotation.advance_paid) }}
                            · <strong>Balance Due: {{ formatMoney(quotation.balance_due) }}</strong>
                        </template>
                    </p>
                </div>

                <form @submit.prevent="submitConvert" class="space-y-3">
                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Payment for Balance Due</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1">Cash (Rs)</label>
                            <input v-model.number="convertForm.cash" type="number" min="0" placeholder="0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Bank Transfer (Rs)</label>
                            <input v-model.number="convertForm.bank" type="number" min="0" placeholder="0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">JazzCash (Rs)</label>
                            <input v-model.number="convertForm.jazzcash" type="number" min="0" placeholder="0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">Udhaar (Rs)</label>
                            <input v-model.number="convertForm.udhaar" type="number" min="0" placeholder="0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="convertForm.processing"
                            class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 transition-colors disabled:opacity-50">
                            <ShoppingCart class="inline h-3.5 w-3.5 me-1" />
                            {{ convertForm.processing ? 'Converting...' : 'Convert to Sale' }}
                        </button>
                        <button type="button" @click="showConvertModal = false"
                            class="rounded-lg border border-input px-4 py-2.5 text-sm hover:bg-muted transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
