import axios from 'axios';
import type { ReceiptData } from '@/composables/useReceipt';

const salesService = {
    async getReceiptData(saleId: string): Promise<ReceiptData> {
        const { data } = await axios.get(route('sales.receipt', saleId));

        // Normalize the server response into the flat ReceiptData shape that useReceipt expects.
        return {
            invoice_number:   data.invoice_number,
            created_at:       data.created_at,
            // business info from tenant settings
            business_name:    data.business_name || data.branch?.name || 'Bithouse POS',
            business_phone:   data.business_phone   ?? null,
            business_address: data.business_address ?? null,
            business_city:    data.business_city    ?? null,
            logo_url:         data.logo_url         ?? null,
            receipt_header:   data.receipt_header   ?? null,
            receipt_footer:   data.receipt_footer   ?? null,
            currency_symbol:  data.currency_symbol  ?? 'Rs',
            language:         data.language         ?? 'en',
            branch_name:      data.branch?.name     ?? null,
            cashier_name:     data.cashier?.name    ?? null,
            customer_name:    data.customer?.name   ?? null,
            customer_phone:   data.customer?.phone  ?? null,
            items: (data.items ?? []).map((item: any) => ({
                name:          item.product_name,
                name_ur:       item.name_ur       ?? null,
                variant_label: item.variant_label ?? null,
                quantity:      item.quantity,
                unit_price:    item.unit_price,
                line_total:    item.line_total,
                discount:      item.discount,
            })),
            subtotal:         data.subtotal,
            discount:         data.discount,
            tax:              data.tax,
            total:            data.total,
            payment_method:   data.payment_method,
            cash_amount:      data.cash_amount,
            jazzcash_amount:  data.jazzcash_amount,
            easypaisa_amount: data.easypaisa_amount,
            udhaar_amount:    data.udhaar_amount,
            change_amount:    data.change_amount,
        };
    },
};

export default salesService;
