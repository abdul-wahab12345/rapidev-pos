import axios from 'axios';

export interface ProductSearchParams {
    q?: string;
    category?: string;
}

export interface StockCheckItem {
    product_id: string;
    variant_id: string | null;
    quantity: number;
}

export interface StockCheckResult {
    ok: boolean;
    errors: Array<{ name: string; available: number }>;
}

export interface NewCustomerPayload {
    name: string;
    phone: string;
    address: string;
}

const posService = {
    async searchProducts(params: ProductSearchParams) {
        const { data } = await axios.get(route('pos.products.search'), { params });
        return data;
    },

    async searchCustomers(q: string) {
        const { data } = await axios.get(route('pos.customers.search'), { params: { q } });
        return data;
    },

    async storeCustomer(payload: NewCustomerPayload) {
        const { data } = await axios.post(route('pos.customers.store'), payload);
        return data;
    },

    async checkStock(items: StockCheckItem[]): Promise<StockCheckResult> {
        const { data } = await axios.post(route('pos.check-stock'), { items });
        return data;
    },

    async storeSale(payload: unknown) {
        const { data } = await axios.post(route('pos.sales.store'), payload);
        return data;
    },

    async getStats() {
        const { data } = await axios.get(route('pos.stats'));
        return data;
    },
};

export default posService;
