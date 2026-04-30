import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export interface CartProduct {
    id: string;
    name: string;
    name_ur: string | null;
    sku: string | null;
    unit: string;
    selling_price: number;
    cost_price: number;
    has_variants: boolean;
    stock: number;
    category: { id: string; name: string; color: string } | null;
}

export interface CartItem {
    product_id: string;
    variant_id: string | null;
    name: string;
    name_ur: string | null;
    variant_label: string | null;
    unit_price: number;
    cost_price: number;
    quantity: number;
    discount: number; // item-level discount in PKR
    stock: number;
}

export interface Customer {
    id: string;
    name: string;
    phone: string;
    balance: number;
    credit_limit: number;
}

export type PaymentMethod = 'cash' | 'jazzcash' | 'easypaisa' | 'udhaar' | 'mixed';

export const useCartStore = defineStore('cart', () => {
    // ── State ──────────────────────────────────────────────
    const items = ref<CartItem[]>([]);
    const selectedCustomer = ref<Customer | null>(null);
    const cartDiscount = ref(0); // PKR cart-level discount
    const paymentMethod = ref<PaymentMethod>('cash');
    const cashReceived = ref(0);
    const jazzcashAmount = ref(0);
    const easypaisaAmount = ref(0);

    // UI state
    const showPaymentModal = ref(false);
    const showCustomerPanel = ref(false);
    const saleComplete = ref(false);
    const lastSale = ref<{ invoice_number: string; total: number; change: number } | null>(null);
    const isProcessing = ref(false);

    // ── Computed ───────────────────────────────────────────
    const itemCount = computed(() => items.value.reduce((s, i) => s + i.quantity, 0));

    const subtotal = computed(() =>
        items.value.reduce((s, i) => s + (i.unit_price * i.quantity) - i.discount, 0),
    );

    const total = computed(() => Math.max(0, subtotal.value - cartDiscount.value));

    const changeAmount = computed(() => {
        if (paymentMethod.value === 'cash') {
            return Math.max(0, cashReceived.value - total.value);
        }
        if (paymentMethod.value === 'mixed') {
            const cashPart = cashReceived.value;
            const nonCash = jazzcashAmount.value + easypaisaAmount.value;
            const udhaarPart = Math.max(0, total.value - cashPart - nonCash);
            return Math.max(0, cashPart - (total.value - nonCash - udhaarPart));
        }
        return 0;
    });

    const udhaarAmount = computed(() => {
        if (paymentMethod.value === 'udhaar') return total.value;
        if (paymentMethod.value === 'mixed') {
            return Math.max(0, total.value - cashReceived.value - jazzcashAmount.value - easypaisaAmount.value);
        }
        return 0;
    });

    const canCharge = computed(() => {
        if (items.value.length === 0) return false;
        if (paymentMethod.value === 'udhaar' && !selectedCustomer.value) return false;
        if (paymentMethod.value === 'cash' && cashReceived.value < total.value) return false;
        if (paymentMethod.value === 'mixed') {
            const totalCovered = cashReceived.value + jazzcashAmount.value + easypaisaAmount.value + udhaarAmount.value;
            if (Math.abs(totalCovered - total.value) > 1) return false;
            if (udhaarAmount.value > 0 && !selectedCustomer.value) return false;
        }
        return true;
    });

    const chargeButtonLabel = computed(() => {
        if (items.value.length === 0) return 'Add items to start';
        if (paymentMethod.value === 'cash' && cashReceived.value < total.value) return `Need Rs ${(total.value - cashReceived.value).toLocaleString()} more`;
        if (paymentMethod.value === 'udhaar' && !selectedCustomer.value) return 'Select customer for Udhaar';
        return `Charge Rs ${Math.round(total.value).toLocaleString()}`;
    });

    // ── Actions ────────────────────────────────────────────
    function addItem(product: CartProduct, variantId: string | null = null, variantLabel: string | null = null) {
        saleComplete.value = false;
        const existing = items.value.find(
            (i) => i.product_id === product.id && i.variant_id === variantId,
        );
        if (existing) {
            existing.quantity++;
        } else {
            items.value.push({
                product_id: product.id,
                variant_id: variantId,
                name: product.name,
                name_ur: product.name_ur ?? null,
                variant_label: variantLabel,
                unit_price: product.selling_price,
                cost_price: product.cost_price,
                quantity: 1,
                discount: 0,
                stock: product.stock,
            });
        }
    }

    function updateQuantity(index: number, qty: number) {
        if (qty <= 0) {
            items.value.splice(index, 1);
        } else {
            items.value[index].quantity = qty;
        }
    }

    function removeItem(index: number) {
        items.value.splice(index, 1);
    }

    function setItemDiscount(index: number, discount: number) {
        items.value[index].discount = discount;
    }

    function clearCart() {
        items.value = [];
        selectedCustomer.value = null;
        cartDiscount.value = 0;
        paymentMethod.value = 'cash';
        cashReceived.value = 0;
        jazzcashAmount.value = 0;
        easypaisaAmount.value = 0;
        saleComplete.value = false;
        lastSale.value = null;
        showPaymentModal.value = false;
    }

    function buildSalePayload(notes?: string) {
        return {
            customer_id: selectedCustomer.value?.id ?? null,
            items: items.value.map((i) => ({
                product_id: i.product_id,
                variant_id: i.variant_id,
                name: i.name,
                variant_label: i.variant_label,
                quantity: i.quantity,
                unit_price: i.unit_price,
                cost_price: i.cost_price,
                discount: i.discount,
            })),
            payment: {
                method: paymentMethod.value,
                cash: paymentMethod.value === 'cash'
                    ? total.value
                    : paymentMethod.value === 'mixed'
                      ? cashReceived.value
                      : 0,
                jazzcash: paymentMethod.value === 'jazzcash'
                    ? total.value
                    : jazzcashAmount.value,
                easypaisa: paymentMethod.value === 'easypaisa'
                    ? total.value
                    : easypaisaAmount.value,
                udhaar: udhaarAmount.value,
            },
            discount: cartDiscount.value,
            notes: notes,
        };
    }

    return {
        items,
        selectedCustomer,
        cartDiscount,
        paymentMethod,
        cashReceived,
        jazzcashAmount,
        easypaisaAmount,
        showPaymentModal,
        showCustomerPanel,
        saleComplete,
        lastSale,
        isProcessing,
        itemCount,
        subtotal,
        total,
        changeAmount,
        udhaarAmount,
        canCharge,
        chargeButtonLabel,
        addItem,
        updateQuantity,
        removeItem,
        setItemDiscount,
        clearCart,
        buildSalePayload,
    };
});
