import { formatMoney, formatDateTime } from '@/utils/format';
import { useI18n } from 'vue-i18n';

export interface ReceiptItem {
    name: string;
    name_ur?: string | null;
    variant_label?: string | null;
    quantity: number;
    unit_price: number;
    line_total: number;
    discount: number;
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
    language?: string | null;          // 'en' | 'ur'
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

function buildReceiptHtml(data: ReceiptData, t: TFn): string {
    const fm = (n: number) => formatMoney(n);

    const dateTime = data.created_at
        ? formatDateTime(data.created_at)
        : new Date().toLocaleString('en-PK', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });

    const isUrdu = data.language === 'ur';
    const dir = isUrdu ? 'rtl' : 'ltr';

    const itemRows = data.items.map(item => {
        const displayName = isUrdu
            ? (item.name_ur || item.name)
            : item.name;
        const varLabel = item.variant_label ? ` <span style="font-size:10px">(${item.variant_label})</span>` : '';
        return `
            <tr>
                <td>${displayName}${varLabel}</td>
                <td class="center">${item.quantity}</td>
                <td class="right">${fm(item.unit_price)}</td>
                <td class="right">${fm(item.line_total)}</td>
            </tr>
            ${item.discount > 0 ? `<tr class="disc-row"><td colspan="3">  ${t('receipt.disc')}</td><td class="right">−${fm(item.discount)}</td></tr>` : ''}
        `;
    }).join('');

    const cashierLine = [dateTime, data.cashier_name].filter(Boolean).join(' &nbsp;|&nbsp; ');
    const footer = data.receipt_footer || t('receipt.thankYou');
    const addressParts = [data.business_address, data.business_city].filter(Boolean).join(', ');

    const rtlStyles = isUrdu ? `
  body { direction: rtl; text-align: right; font-family:'Noto Nastaliq Urdu','Jameel Noori Nastaleeq','Arial Unicode MS',sans-serif; }
  th { text-align: right; }
  th.right { text-align: left; }
  td.right { text-align: left; }
  td.center { text-align: center; }
  .right { text-align: left; }
` : '';

    return `<!DOCTYPE html>
<html dir="${dir}" lang="${isUrdu ? 'ur' : 'en'}">
<head>
<meta charset="utf-8"/>
<title>Receipt – ${data.invoice_number}</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box;}
  body{font-family:'Courier New',monospace;font-size:12px;color:#000;width:80mm;margin:0 auto;padding:6mm 4mm;}
  .center{text-align:center;} .right{text-align:right;}
  h1{font-size:16px;font-weight:bold;text-align:center;margin-bottom:2px;}
  .sub{font-size:11px;text-align:center;color:#333;}
  hr{border:none;border-top:1px dashed #000;margin:6px 0;}
  table{width:100%;border-collapse:collapse;}
  th{font-size:11px;border-bottom:1px solid #000;padding:2px 0;text-align:left;}
  th.center{text-align:center;} th.right{text-align:right;}
  td{font-size:11px;padding:2px 0;vertical-align:top;}
  .disc-row td{font-size:10px;color:#555;}
  .totals td{padding:1px 0;font-size:12px;}
  .total-row td{font-size:14px;font-weight:bold;border-top:1px solid #000;padding-top:4px;}
  .badge{background:#eee;border:1px dashed #999;padding:3px 6px;margin:6px 0;font-size:11px;}
  .change{font-size:16px;font-weight:bold;text-align:center;margin:6px 0;}
  .footer{text-align:center;font-size:10px;color:#555;margin-top:4px;}
  @media print{body{width:80mm;}@page{margin:0;size:80mm auto;}}
  ${rtlStyles}
</style>
</head>
<body>
  ${data.logo_url ? `<div style="text-align:center;margin-bottom:4px"><img src="${data.logo_url}" style="max-height:48px;max-width:160px;object-fit:contain"/></div>` : ''}
  <h1>${data.business_name}</h1>
  ${data.business_phone ? `<p class="sub">${data.business_phone}</p>` : ''}
  ${addressParts ? `<p class="sub">${addressParts}</p>` : ''}
  ${data.branch_name ? `<p class="sub">${data.branch_name}</p>` : ''}
  ${data.receipt_header ? `<p class="sub" style="font-style:italic">${data.receipt_header}</p>` : ''}
  <hr/>
  <p class="sub">${t('receipt.invoice')} <strong>${data.invoice_number}</strong></p>
  <p class="sub">${cashierLine}</p>
  ${data.customer_name ? `<p class="sub">${t('receipt.customer')} ${data.customer_name}${data.customer_phone ? ' | ' + data.customer_phone : ''}</p>` : ''}
  <hr/>
  <table>
    <thead>
      <tr><th>${t('receipt.item')}</th><th class="center">${t('receipt.qty')}</th><th class="right">${t('receipt.price')}</th><th class="right">${t('receipt.total')}</th></tr>
    </thead>
    <tbody>${itemRows}</tbody>
  </table>
  <hr/>
  <table class="totals">
    <tr><td>${t('receipt.subtotal')}</td><td class="right">${fm(data.subtotal)}</td></tr>
    ${data.discount > 0 ? `<tr><td>${t('receipt.discount')}</td><td class="right">−${fm(data.discount)}</td></tr>` : ''}
    ${(data.tax ?? 0) > 0 ? `<tr><td>${t('receipt.tax')}</td><td class="right">${fm(data.tax!)}</td></tr>` : ''}
    <tr class="total-row"><td>${t('receipt.grandTotal')}</td><td class="right">${fm(data.total)}</td></tr>
  </table>
  <div class="badge">
    <strong>${t('receipt.payment')}</strong> ${data.payment_method.toUpperCase()}
    ${(data.cash_amount ?? 0) > 0 ? `<br>${t('receipt.cash')} ${fm(data.cash_amount!)}` : ''}
    ${(data.jazzcash_amount ?? 0) > 0 ? `<br>${t('receipt.jazzcash')} ${fm(data.jazzcash_amount!)}` : ''}
    ${(data.easypaisa_amount ?? 0) > 0 ? `<br>${t('receipt.easypaisa')} ${fm(data.easypaisa_amount!)}` : ''}
    ${(data.udhaar_amount ?? 0) > 0 ? `<br>${t('receipt.udhaar')} ${fm(data.udhaar_amount!)}` : ''}
  </div>
  ${(data.change_amount ?? 0) > 0 ? `<div class="change">${t('receipt.change')} ${fm(data.change_amount!)}</div>` : ''}
  <hr/>
  <p class="footer">${footer}</p>
  <p class="footer">— ${data.business_name} —</p>
<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
</body>
</html>`;
}

export function useReceipt() {
    const { t } = useI18n();

    function printReceipt(data: ReceiptData): void {
        const html = buildReceiptHtml(data, (key) => t(key));
        const win = window.open('', '_blank', 'width=400,height=600');
        if (win) {
            win.document.write(html);
            win.document.close();
        }
    }

    return { printReceipt };
}
