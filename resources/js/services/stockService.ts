import axios from 'axios';

export interface AdjustPayload {
    product_id: string;
    variant_id: string | null;
    type: 'add' | 'remove' | 'set';
    quantity: number;
    reason: string;
    notes?: string;
}

export const stockService = {
    async adjust(payload: AdjustPayload): Promise<void> {
        await axios.post(route('inventory.stock.adjust'), payload);
    },
};
