import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export interface CartProductVariant {
    id: string;
    label: string;
    size: string | null;
    color: string | null;
    selling_price: number;
    cost_price: number;
    stock: number;
}

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
    variants: CartProductVariant[];
    // Material attributes (marble/tile/ceramic)
    material_type?: string | null;
    sq_m_per_box?: number | null;
    tile_width_in?: number | null;
    tile_height_in?: number | null;
    tiles_per_box?: number | null;
}

export interface CartItem {
    product_id: string;
    variant_id: string | null;
    name: string;
    name_ur: string | null;
    sku: string | null;
    variant_label: string | null;
    unit: string | null;
    unit_price: number;
    cost_price: number;
    quantity: number;
    /** PKR discounted off each unit; line discount = this × quantity */
    discount_per_unit: number;
    stock: number;
    tiles_per_box: number | null;
    sq_m_per_box: number | null;
    material_type: string | null;
    tile_width_in: number | null;
    tile_height_in: number | null;
}

export interface Customer {
    id: string;
    name: string;
    phone: string;
    balance: number;
    credit_limit: number;
    discount_percent: number;
}

export type PaymentMethod = 'cash' | 'jazzcash' | 'easypaisa' | 'bank' | 'udhaar' | 'mixed';

export const useCartStore = defineStore('cart', () => {
    // ── State ──────────────────────────────────────────────
    const items = ref<CartItem[]>([]);
    const selectedCustomer = ref<Customer | null>(null);
    const cartDiscount = ref(0); // PKR cart-level discount
    const deliveryFee = ref(0);  // PKR delivery / transport charge
    const paymentMethod = ref<PaymentMethod>('cash');
    const cashReceived = ref(0);
    const jazzcashAmount = ref(0);
    const easypaisaAmount = ref(0);
    const bankAmount = ref(0);

    // Rate list
    const selectedRateListId = ref<string | null>(null);
    const rateListPrices = ref<Record<string, number>>({}); // key: "productId_variantId"

    // UI state
    const showPaymentModal = ref(false);
    const showCustomerPanel = ref(false);
    const saleComplete = ref(false);
    const lastSale = ref<{ invoice_number: string; total: number; change: number } | null>(null);
    const isProcessing = ref(false);

    // ── Computed ───────────────────────────────────────────
    const itemCount = computed(() => items.value.reduce((s, i) => s + i.quantity, 0));

    function lineDiscountTotal(item: CartItem): number {
        return Math.round(item.discount_per_unit * item.quantity * 100) / 100;
    }

    const subtotal = computed(() =>
        items.value.reduce((s, i) => s + (i.unit_price * i.quantity) - lineDiscountTotal(i), 0),
    );

    const total = computed(() => Math.max(0, subtotal.value - cartDiscount.value + deliveryFee.value));

    const changeAmount = computed(() => {
        if (paymentMethod.value === 'cash') {
            return Math.max(0, cashReceived.value - total.value);
        }
        if (paymentMethod.value === 'mixed') {
            const nonCash = jazzcashAmount.value + easypaisaAmount.value + bankAmount.value;
            const udhaarPart = Math.max(0, total.value - cashReceived.value - nonCash);
            return Math.max(0, cashReceived.value - (total.value - nonCash - udhaarPart));
        }
        return 0;
    });

    const udhaarAmount = computed(() => {
        if (paymentMethod.value === 'udhaar') return total.value;
        if (paymentMethod.value === 'mixed') {
            return Math.max(
                0,
                total.value - cashReceived.value - jazzcashAmount.value - easypaisaAmount.value - bankAmount.value,
            );
        }
        return 0;
    });

    const canCharge = computed(() => {
        if (items.value.length === 0) return false;
        if (paymentMethod.value === 'udhaar' && !selectedCustomer.value) return false;
        if (paymentMethod.value === 'cash' && cashReceived.value < total.value) return false;
        if (paymentMethod.value === 'mixed') {
            const totalCovered =
                cashReceived.value + jazzcashAmount.value + easypaisaAmount.value + bankAmount.value + udhaarAmount.value;
            if (Math.abs(totalCovered - total.value) > 1) return false;
            if (udhaarAmount.value > 0 && !selectedCustomer.value) return false;
        }
        return true;
    });

    const chargeButtonLabel = computed(() => {
        if (items.value.length === 0) return 'Add items to start';
        if (paymentMethod.value === 'cash' && cashReceived.value < total.value)
            return `Need Rs ${(total.value - cashReceived.value).toLocaleString()} more`;
        if (paymentMethod.value === 'udhaar' && !selectedCustomer.value) return 'Select customer for Udhaar';
        return `Charge Rs ${Math.round(total.value).toLocaleString()}`;
    });

    // ── Actions ────────────────────────────────────────────
    function getRatePrice(productId: string, variantId: string | null): number | null {
        const key = `${productId}_${variantId ?? ''}`;
        return rateListPrices.value[key] ?? null;
    }

    function setRateList(id: string | null, prices: Record<string, number>) {
        selectedRateListId.value = id;
        rateListPrices.value = prices;
        // Re-price all current cart items under the new rate list
        items.value.forEach((item) => {
            const ratePrice = getRatePrice(item.product_id, item.variant_id);
            if (ratePrice !== null) {
                item.unit_price = ratePrice;
                if (item.discount_per_unit > item.unit_price) {
                    item.discount_per_unit = item.unit_price;
                }
            }
        });
    }

    function addItem(product: CartProduct, variantId: string | null = null, variantLabel: string | null = null) {
        saleComplete.value = false;
        const ratePrice = getRatePrice(product.id, variantId);

        // For variant products, get the variant's price
        let unitPrice: number;
        if (variantId && product.variants) {
            const variant = product.variants.find((v) => v.id === variantId);
            const variantRatePrice = getRatePrice(product.id, variantId);
            unitPrice = variantRatePrice !== null
                ? variantRatePrice
                : (variant?.selling_price ?? product.selling_price);
        } else {
            unitPrice = ratePrice !== null ? ratePrice : product.selling_price;
        }

        const existing = items.value.find(
            (i) => i.product_id === product.id && i.variant_id === variantId,
        );
        if (existing) {
            existing.quantity++;
        } else {
            const variantCostPrice = variantId
                ? (product.variants?.find((v) => v.id === variantId)?.cost_price ?? product.cost_price)
                : product.cost_price;

            items.value.push({
                product_id: product.id,
                variant_id: variantId,
                name: product.name,
                name_ur: product.name_ur ?? null,
                sku: product.sku ?? null,
                variant_label: variantLabel,
                unit: product.unit ?? null,
                unit_price: unitPrice,
                cost_price: variantCostPrice,
                quantity: 1,
                discount_per_unit: 0,
                stock: variantId
                    ? (product.variants?.find((v) => v.id === variantId)?.stock ?? product.stock)
                    : product.stock,
                tiles_per_box: product.tiles_per_box ?? null,
                sq_m_per_box: product.sq_m_per_box ?? null,
                material_type: product.material_type ?? null,
                tile_width_in: product.tile_width_in ?? null,
                tile_height_in: product.tile_height_in ?? null,
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

    /** PKR off each unit (capped at unit price so line total never goes negative). */
    function setItemDiscount(index: number, discountPerUnit: number) {
        const item = items.value[index];
        if (!item) return;
        const cap = Math.max(0, item.unit_price);
        item.discount_per_unit = Math.min(Math.max(0, discountPerUnit), cap);
    }

    function clearCart() {
        items.value = [];
        selectedCustomer.value = null;
        cartDiscount.value = 0;
        deliveryFee.value = 0;
        paymentMethod.value = 'cash';
        cashReceived.value = 0;
        jazzcashAmount.value = 0;
        easypaisaAmount.value = 0;
        bankAmount.value = 0;
        saleComplete.value = false;
        lastSale.value = null;
        showPaymentModal.value = false;
        // Keep rate list selection across sales
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
                discount: lineDiscountTotal(i),
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
                    : paymentMethod.value === 'mixed'
                      ? jazzcashAmount.value
                      : 0,
                easypaisa: paymentMethod.value === 'easypaisa'
                    ? total.value
                    : paymentMethod.value === 'mixed'
                      ? easypaisaAmount.value
                      : 0,
                bank: paymentMethod.value === 'bank'
                    ? total.value
                    : paymentMethod.value === 'mixed'
                      ? bankAmount.value
                      : 0,
                udhaar: udhaarAmount.value,
            },
            discount: cartDiscount.value,
            delivery_fee: deliveryFee.value,
            notes: notes,
            rate_list_id: selectedRateListId.value ?? null,
        };
    }

    return {
        items,
        selectedCustomer,
        cartDiscount,
        deliveryFee,
        paymentMethod,
        cashReceived,
        jazzcashAmount,
        easypaisaAmount,
        bankAmount,
        selectedRateListId,
        rateListPrices,
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
        lineDiscountTotal,
        clearCart,
        buildSalePayload,
        setRateList,
        getRatePrice,
    };
});
