import { formatDateTime, formatMoney, formatUnit } from '@/utils/format';
import { useI18n } from 'vue-i18n';

export interface ReceiptItem {
    name: string;
    name_ur?: string | null;
    variant_label?: string | null;
    quantity: number;
    unit?: string | null;
    unit_price: number;
    line_total: number;
    discount: number;
    tiles_per_box?: number | null;
    sq_m_per_box?: number | null;
    material_type?: string | null;
    tile_width_in?: number | null;
    tile_height_in?: number | null;
}

const TILE_TYPES = ['tile', 'ceramic', 'mosaic', 'border'];

function tileBreakdown(item: ReceiptItem): string | null {
    if (!item.tiles_per_box || !item.sq_m_per_box) return null;
    if (!TILE_TYPES.includes(item.material_type ?? '')) return null;
    const sqmPerTile = item.sq_m_per_box / item.tiles_per_box;
    if (!sqmPerTile) return null;
    const totalTiles = Math.round(item.quantity / sqmPerTile);
    const boxes = Math.floor(totalTiles / item.tiles_per_box);
    const loose = totalTiles % item.tiles_per_box;
    if (boxes === 0 && loose === 0) return null;
    return loose > 0 ? `~${boxes} box + ${loose} tile` : `~${boxes} box`;
}

export interface ReceiptData {
    invoice_number: string;
    created_at?: string;
    business_name: string;
    business_phone?: string | null;
    business_address?: string | null;
    business_city?: string | null;
    logo_url?: string | null;
    receipt_header?: string | null;
    receipt_footer?: string | null;
    currency_symbol?: string | null;
    language?: string | null;
    invoice_template?: string | null;   // 'thermal' | 'a4'
    branch_name?: string | null;
    cashier_name?: string | null;
    customer_name?: string | null;
    customer_phone?: string | null;
    items: ReceiptItem[];
    subtotal: number;
    discount: number;
    tax?: number;
    total: number;
    payment_method: string;
    cash_amount?: number;
    jazzcash_amount?: number;
    easypaisa_amount?: number;
    udhaar_amount?: number;
    change_amount?: number;
}

type TFn = (key: string) => string;

// ── Shared helpers ────────────────────────────────────────────

function fmtDate(dt?: string | null): string {
    return dt
        ? formatDateTime(dt)
        : new Date().toLocaleString('en-PK', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
}

function itemName(item: ReceiptItem, isUrdu: boolean): string {
    return isUrdu ? (item.name_ur || item.name) : item.name;
}

function openPrintWindow(html: string, width = 420, height = 700): void {
    const win = window.open('', '_blank', `width=${width},height=${height}`);
    if (win) {
        win.document.write(html);
        win.document.close();
    }
}

// ── Thermal receipt (80 mm) ───────────────────────────────────

function buildThermalHtml(data: ReceiptData, t: TFn): string {
    const fm   = (n: number) => formatMoney(n);
    const isUr = data.language === 'ur';
    const dir  = isUr ? 'rtl' : 'ltr';
    const dt   = fmtDate(data.created_at);
    const addr = [data.business_address, data.business_city].filter(Boolean).join(', ');
    const footer = data.receipt_footer || t('receipt.thankYou');

    const itemRows = data.items.map(item => {
        const name = itemName(item, isUr);
        const vl   = item.variant_label ? ` <span style="font-size:10px">(${item.variant_label})</span>` : '';
        const qtyStr = item.unit ? `${item.quantity} ${formatUnit(item.unit)}` : String(item.quantity);
        const bdStr  = tileBreakdown(item);
        return `<tr>
            <td>${name}${vl}${bdStr ? `<br><span style="font-size:10px;color:#666">${bdStr}</span>` : ''}</td>
            <td class="c">${qtyStr}</td>
            <td class="r">${fm(item.unit_price)}</td>
            <td class="r">${fm(item.line_total)}</td>
        </tr>${item.discount > 0 ? `<tr class="dr"><td colspan="3">&nbsp;${t('receipt.disc')}</td><td class="r">−${fm(item.discount)}</td></tr>` : ''}`;
    }).join('');

    const rtl = isUr ? `body{direction:rtl;text-align:right;font-family:'Noto Nastaliq Urdu','Arial Unicode MS',sans-serif;}th{text-align:right;}th.r{text-align:left;}td.r{text-align:left;}.r{text-align:left;}` : '';

    return `<!DOCTYPE html><html dir="${dir}" lang="${isUr?'ur':'en'}"><head><meta charset="utf-8"/>
<title>${data.invoice_number}</title><style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Courier New',monospace;font-size:12px;color:#000;width:80mm;margin:0 auto;padding:6mm 4mm;}
.c{text-align:center;}.r{text-align:right;}
h1{font-size:16px;font-weight:bold;text-align:center;margin-bottom:2px;}
.sub{font-size:11px;text-align:center;color:#333;}
hr{border:none;border-top:1px dashed #000;margin:6px 0;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;border-bottom:1px solid #000;padding:2px 0;text-align:left;}
th.c{text-align:center;}th.r{text-align:right;}
td{font-size:11px;padding:2px 0;vertical-align:top;}
.dr td{font-size:10px;color:#555;}
.tot td{padding:1px 0;font-size:12px;}
.tot-r td{font-size:14px;font-weight:bold;border-top:1px solid #000;padding-top:4px;}
.badge{background:#eee;border:1px dashed #999;padding:3px 6px;margin:6px 0;font-size:11px;}
.chg{font-size:16px;font-weight:bold;text-align:center;margin:6px 0;}
.ft{text-align:center;font-size:10px;color:#555;margin-top:4px;}
@media print{body{width:80mm;}@page{margin:0;size:80mm auto;}}
${rtl}
</style></head><body>
${data.logo_url?`<div style="text-align:center;margin-bottom:4px"><img src="${data.logo_url}" style="max-height:48px;max-width:160px;object-fit:contain"/></div>`:''}
<h1>${data.business_name}</h1>
${data.business_phone?`<p class="sub">${data.business_phone}</p>`:''}
${addr?`<p class="sub">${addr}</p>`:''}
${data.branch_name?`<p class="sub">${data.branch_name}</p>`:''}
${data.receipt_header?`<p class="sub" style="font-style:italic">${data.receipt_header}</p>`:''}
<hr/>
<p class="sub">${t('receipt.invoice')} <strong>${data.invoice_number}</strong></p>
<p class="sub">${[dt, data.cashier_name].filter(Boolean).join(' &nbsp;|&nbsp; ')}</p>
${data.customer_name?`<p class="sub">${t('receipt.customer')} ${data.customer_name}${data.customer_phone?' | '+data.customer_phone:''}</p>`:''}
<hr/>
<table><thead><tr>
  <th>${t('receipt.item')}</th><th class="c">${t('receipt.qty')}</th>
  <th class="r">${t('receipt.price')}</th><th class="r">${t('receipt.total')}</th>
</tr></thead><tbody>${itemRows}</tbody></table>
<hr/>
<table class="tot">
  <tr><td>${t('receipt.subtotal')}</td><td class="r">${fm(data.subtotal)}</td></tr>
  ${data.discount>0?`<tr><td>${t('receipt.discount')}</td><td class="r">−${fm(data.discount)}</td></tr>`:''}
  ${(data.tax??0)>0?`<tr><td>${t('receipt.tax')}</td><td class="r">${fm(data.tax!)}</td></tr>`:''}
  <tr class="tot-r"><td>${t('receipt.grandTotal')}</td><td class="r">${fm(data.total)}</td></tr>
</table>
<div class="badge"><strong>${t('receipt.payment')}</strong> ${data.payment_method.toUpperCase()}
  ${(data.cash_amount??0)>0?`<br>${t('receipt.cash')} ${fm(data.cash_amount!)}`:''}
  ${(data.jazzcash_amount??0)>0?`<br>${t('receipt.jazzcash')} ${fm(data.jazzcash_amount!)}`:''}
  ${(data.easypaisa_amount??0)>0?`<br>${t('receipt.easypaisa')} ${fm(data.easypaisa_amount!)}`:''}
  ${(data.udhaar_amount??0)>0?`<br>${t('receipt.udhaar')} ${fm(data.udhaar_amount!)}`:''}
</div>
${(data.change_amount??0)>0?`<div class="chg">${t('receipt.change')} ${fm(data.change_amount!)}</div>`:''}
<hr/>
<p class="ft">${footer}</p>
<p class="ft">— ${data.business_name} —</p>
<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
</body></html>`;
}

// ── A4 invoice ────────────────────────────────────────────────

function buildA4Html(data: ReceiptData, t: TFn): string {
    const fm   = (n: number) => formatMoney(n);
    const isUr = data.language === 'ur';
    const dir  = isUr ? 'rtl' : 'ltr';
    const dt   = fmtDate(data.created_at);
    const addr = [data.business_address, data.business_city].filter(Boolean).join(', ');
    const footer = data.receipt_footer || t('receipt.thankYou');

    const itemRows = data.items.map((item, i) => {
        const name = itemName(item, isUr);
        const vl   = item.variant_label ? `<br><span style="font-size:11px;color:#666">${item.variant_label}</span>` : '';
        const qtyStr = item.unit ? `${item.quantity} ${formatUnit(item.unit)}` : String(item.quantity);
        const bdStr  = tileBreakdown(item);
        const sizeStr = (TILE_TYPES.includes(item.material_type ?? '') && item.tile_width_in && item.tile_height_in) 
            ? `<br><span style="font-size:11px;color:#666">Size: ${item.tile_width_in}" x ${item.tile_height_in}"</span>` 
            : '';
        return `<tr>
            <td class="num">${i + 1}</td>
            <td>${name}${vl}${sizeStr}</td>
            <td class="r">${qtyStr}${bdStr ? `<br><span style="font-size:11px;color:#666">${bdStr}</span>` : ''}</td>
            <td class="r">${fm(item.unit_price)}</td>
            <td class="r">${item.discount > 0 ? `−${fm(item.discount)}` : '—'}</td>
            <td class="r fw">${fm(item.line_total)}</td>
        </tr>`;
    }).join('');

    const payLines = [
        (data.cash_amount ?? 0) > 0      ? `${t('receipt.cash')}: ${fm(data.cash_amount!)}` : '',
        (data.jazzcash_amount ?? 0) > 0  ? `${t('receipt.jazzcash')}: ${fm(data.jazzcash_amount!)}` : '',
        (data.easypaisa_amount ?? 0) > 0 ? `${t('receipt.easypaisa')}: ${fm(data.easypaisa_amount!)}` : '',
        (data.udhaar_amount ?? 0) > 0    ? `${t('receipt.udhaar')}: ${fm(data.udhaar_amount!)}` : '',
    ].filter(Boolean).join(' &nbsp;·&nbsp; ');

    const font = isUr ? `'Noto Nastaliq Urdu','Jameel Noori Nastaleeq','Arial Unicode MS',sans-serif` : `'Segoe UI','Helvetica Neue',Arial,sans-serif`;

    return `<!DOCTYPE html><html dir="${dir}" lang="${isUr?'ur':'en'}"><head><meta charset="utf-8"/>
<title>${t('receipt.invoice')} ${data.invoice_number}</title><style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:${font};font-size:13px;color:#111;background:#fff;padding:12mm 14mm;}
.r{text-align:${isUr?'left':'right'};}
.c{text-align:center;}
.fw{font-weight:600;}
.num{text-align:center;color:#888;width:32px;}

/* Header */
.hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10mm;}
.biz-name{font-size:22px;font-weight:700;margin-bottom:3px;}
.biz-sub{font-size:12px;color:#555;line-height:1.5;}
.inv-badge{background:#111;color:#fff;padding:6px 16px;border-radius:4px;font-size:18px;font-weight:700;letter-spacing:1px;white-space:nowrap;}
.inv-meta{font-size:12px;color:#555;margin-top:5px;text-align:${isUr?'left':'right'};}

/* Divider */
.divider{border:none;border-top:2px solid #111;margin:6mm 0 4mm;}
.divider-sm{border:none;border-top:1px solid #ddd;margin:3mm 0;}

/* Bill-to */
.bill-section{display:flex;justify-content:space-between;margin-bottom:6mm;font-size:12px;}
.bill-label{font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:3px;}
.bill-val{font-weight:600;}

/* Items table */
table{width:100%;border-collapse:collapse;margin-bottom:5mm;}
thead tr{background:#111;color:#fff;}
thead th{padding:6px 8px;font-size:11px;text-align:${isUr?'right':'left'};}
thead th.r{text-align:${isUr?'left':'right'};}
tbody tr{border-bottom:1px solid #eee;}
tbody tr:last-child{border-bottom:none;}
td{padding:6px 8px;vertical-align:top;font-size:12px;}

/* Totals */
.totals-wrap{display:flex;justify-content:flex-end;margin-bottom:6mm;}
.totals{width:220px;}
.totals tr td{padding:3px 6px;font-size:13px;}
.totals tr td:last-child{text-align:${isUr?'left':'right'};}
.tot-grand td{font-size:16px;font-weight:700;border-top:2px solid #111;padding-top:5px;}

/* Payment */
.pay-box{border:1px solid #ddd;border-radius:4px;padding:6px 10px;font-size:12px;margin-bottom:5mm;}
.pay-method{font-weight:600;text-transform:uppercase;margin-bottom:2px;}

/* Footer */
.footer{text-align:center;font-size:11px;color:#888;border-top:1px solid #eee;padding-top:4mm;margin-top:4mm;}

@media print{body{padding:8mm 10mm;}@page{margin:0;size:A4;}}
</style>
<style>
body::before{
  content:"${data.business_name.replace(/"/g, '\\"')}";
  position:fixed;top:50%;left:50%;
  transform:translate(-50%,-50%) rotate(-45deg);
  font-size:72px;font-weight:900;letter-spacing:6px;
  color:rgba(0,0,0,0.04);white-space:nowrap;
  pointer-events:none;z-index:0;text-transform:uppercase;
}
</style>
</head><body>

<!-- Header -->
<div class="hdr">
  <div>
    ${data.logo_url?`<img src="${data.logo_url}" style="max-height:56px;max-width:180px;object-fit:contain;margin-bottom:6px;display:block"/>`:''}
    <div class="biz-name">${data.business_name}</div>
    <div class="biz-sub">
      ${[data.business_phone, addr, data.branch_name].filter(Boolean).join('<br>')}
    </div>
  </div>
  <div style="text-align:${isUr?'left':'right'}">
    <div class="inv-badge">${t('receipt.invoice')}</div>
    <div class="inv-meta">
      <strong>${data.invoice_number}</strong><br>
      ${dt}<br>
      ${data.cashier_name ? `${t('a4.cashier')}: ${data.cashier_name}` : ''}
    </div>
  </div>
</div>

<hr class="divider"/>

<!-- Bill to / Payment method summary -->
<div class="bill-section">
  <div>
    <div class="bill-label">${t('a4.billTo')}</div>
    ${data.customer_name
      ? `<div class="bill-val">${data.customer_name}</div><div>${data.customer_phone ?? ''}</div>`
      : `<div class="bill-val">—</div>`}
  </div>
  <div style="text-align:${isUr?'left':'right'}">
    <div class="bill-label">${t('receipt.payment')}</div>
    <div class="bill-val">${data.payment_method.toUpperCase()}</div>
    ${payLines ? `<div style="font-size:11px;color:#555">${payLines}</div>` : ''}
  </div>
</div>

<!-- Items -->
<table>
  <thead>
    <tr>
      <th class="c">#</th>
      <th>${t('receipt.item')}</th>
      <th class="r">${t('receipt.qty')}</th>
      <th class="r">${t('receipt.price')}</th>
      <th class="r">${t('receipt.discount')}</th>
      <th class="r">${t('receipt.total')}</th>
    </tr>
  </thead>
  <tbody>${itemRows}</tbody>
</table>

<!-- Totals -->
<div class="totals-wrap">
  <table class="totals">
    <tr><td>${t('receipt.subtotal')}</td><td>${fm(data.subtotal)}</td></tr>
    ${data.discount>0?`<tr><td>${t('receipt.discount')}</td><td>−${fm(data.discount)}</td></tr>`:''}
    ${(data.tax??0)>0?`<tr><td>${t('receipt.tax')}</td><td>${fm(data.tax!)}</td></tr>`:''}
    ${(data.change_amount??0)>0?`<tr><td>${t('receipt.change')}</td><td>${fm(data.change_amount!)}</td></tr>`:''}
    <tr class="tot-grand"><td>${t('receipt.grandTotal')}</td><td>${fm(data.total)}</td></tr>
  </table>
</div>

<!-- Footer -->
<div class="footer">
  ${data.receipt_header ? `<p style="margin-bottom:3px;font-style:italic">${data.receipt_header}</p>` : ''}
  <p>${footer}</p>
</div>

<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
</body></html>`;
}

// ── Composable ────────────────────────────────────────────────

export function useReceipt() {
    const { t } = useI18n();
    const tf = (key: string) => t(key);

    function printThermal(data: ReceiptData): void {
        openPrintWindow(buildThermalHtml(data, tf), 420, 700);
    }

    function printA4(data: ReceiptData): void {
        openPrintWindow(buildA4Html(data, tf), 900, 750);
    }

    function printReceipt(data: ReceiptData): void {
        if (data.invoice_template === 'a4') printA4(data);
        else printThermal(data);
    }

    return { printReceipt, printThermal, printA4 };
}
