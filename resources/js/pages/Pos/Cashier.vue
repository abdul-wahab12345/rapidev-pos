<script setup lang="ts">
import PosLayout from '@/layouts/PosLayout.vue';
import { useCartStore, type CartProduct, type Customer } from '@/stores/cart';
import { Head } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
import { useReceipt } from '@/composables/useReceipt';
import { formatMoney, isUrdu } from '@/utils/format';
import posService from '@/services/posService';
import {
    AlertTriangle, Check, ChevronDown, Minus, Percent,
    Plus, Search, ShoppingCart, Tag, User, UserPlus, X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const { confirm } = useConfirm();

const props = defineProps<{
    categories: Array<{ id: string; name: string; color: string }>;
    initialProducts: CartProduct[];
    customers: Customer[];
    cashier: { id: number; name: string };
    branch: { id: string; name: string } | null;
    tenant: { name: string; settings: Record<string, any> | null };
    stats: { sales_today: number; revenue_today: number; low_stock: number };
}>();

// live stats — updated after each completed sale
const liveStats = ref({ ...props.stats });

async function refreshStats() {
    try {
        liveStats.value = await posService.getStats();
    } catch {
        // silently ignore
    }
}

const cart = useCartStore();

// ── Product search ──────────────────────────────────────────────
const search = ref('');
const selectedCategory = ref('');
const products = ref<CartProduct[]>(props.initialProducts);
const isSearching = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

async function fetchProducts() {
    isSearching.value = true;
    try {
        products.value = await posService.searchProducts({
            q: search.value,
            category: selectedCategory.value,
        });
    } finally {
        isSearching.value = false;
    }
}

watch([search, selectedCategory], () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchProducts, 300);
});

// ── Customer search ─────────────────────────────────────────────
const customerSearch = ref('');
const customerResults = ref<Customer[]>(props.customers);
let customerTimer: ReturnType<typeof setTimeout> | null = null;

watch(customerSearch, (val) => {
    if (customerTimer) clearTimeout(customerTimer);
    if (!val) { customerResults.value = props.customers; return; }
    customerTimer = setTimeout(async () => {
        customerResults.value = await posService.searchCustomers(val);
    }, 300);
});

function selectCustomer(c: Customer) {
    cart.selectedCustomer = c;
    cart.showCustomerPanel = false;
    customerSearch.value = '';
}

// ── Add new customer ────────────────────────────────────────────
const showAddCustomer = ref(false);
const newCustomer = ref({ name: '', phone: '', address: '' });
const addingCustomer = ref(false);
const addCustomerError = ref('');

function openAddCustomer() {
    newCustomer.value = { name: '', phone: '', address: '' };
    addCustomerError.value = '';
    showAddCustomer.value = true;
}

async function saveNewCustomer() {
    if (!newCustomer.value.name.trim()) { addCustomerError.value = 'Name is required'; return; }
    addingCustomer.value = true;
    addCustomerError.value = '';
    try {
        const customer = await posService.storeCustomer(newCustomer.value);
        customerResults.value = [customer, ...customerResults.value];
        selectCustomer(customer);
        showAddCustomer.value = false;
    } catch (e: any) {
        addCustomerError.value = e.response?.data?.message ?? 'Failed to create customer';
    } finally {
        addingCustomer.value = false;
    }
}

// ── Cart-level discount ─────────────────────────────────────────
const showDiscountRow = ref(false);
const discountType = ref<'amount' | 'percent'>('amount');
const discountInput = ref(0);

const discountLabel = computed(() => {
    if (!showDiscountRow.value || discountInput.value <= 0) return null;
    return discountType.value === 'percent' ? `${discountInput.value}%` : `Rs ${discountInput.value}`;
});

watch([discountInput, discountType], () => {
    cart.cartDiscount = discountType.value === 'percent'
        ? (cart.subtotal * Math.min(discountInput.value, 100)) / 100
        : Math.max(0, discountInput.value);
});

watch(() => cart.items.length, (len) => {
    if (len === 0) { discountInput.value = 0; cart.cartDiscount = 0; showDiscountRow.value = false; }
});

// ── Item-level discount ─────────────────────────────────────────
const expandedItemIdx = ref<number | null>(null);
const itemDiscountInput = ref(0);

function toggleExpandItem(idx: number) {
    if (expandedItemIdx.value === idx) { expandedItemIdx.value = null; return; }
    expandedItemIdx.value = idx;
    itemDiscountInput.value = cart.items[idx]?.discount ?? 0;
}

function applyItemDiscount(idx: number) {
    cart.setItemDiscount(idx, Math.max(0, itemDiscountInput.value));
    expandedItemIdx.value = null;
}

// ── Payment / sale ──────────────────────────────────────────────
const stockError = ref('');

async function submitSale() {
    if (!cart.canCharge || cart.isProcessing) return;
    cart.isProcessing = true;
    stockError.value = '';

    try {
        const stockCheck = await posService.checkStock(
            cart.items.map(i => ({ product_id: i.product_id, variant_id: i.variant_id, quantity: i.quantity })),
        );

        if (!stockCheck.ok) {
            stockError.value = stockCheck.errors.map(e => `${e.name}: only ${e.available} left`).join('\n');
            cart.isProcessing = false;
            return;
        }

        const result = await posService.storeSale(cart.buildSalePayload());
        if (result.success) {
            cart.lastSale = { invoice_number: result.sale.invoice_number, total: result.sale.total, change: result.sale.change };
            cart.showPaymentModal = false;
            cart.saleComplete = true;
            discountInput.value = 0;
            fetchProducts();
            refreshStats();
        }
    } catch (e: any) {
        stockError.value = e.response?.data?.error ?? 'Sale failed. Please try again.';
    } finally {
        cart.isProcessing = false;
    }
}

function newSale() {
    cart.clearCart();
    discountInput.value = 0;
    expandedItemIdx.value = null;
    stockError.value = '';
}

// ── Receipt printing ────────────────────────────────────────────
const { printReceipt: openReceiptPrint } = useReceipt();

function printReceipt() {
    const sale = cart.lastSale;
    if (!sale) return;

    const s = props.tenant?.settings ?? {};
    openReceiptPrint({
        invoice_number:   sale.invoice_number,
        business_name:    s.business_name    || props.tenant?.name || 'Bithouse POS',
        business_phone:   s.business_phone   ?? null,
        business_address: s.business_address ?? null,
        business_city:    s.business_city    ?? null,
        logo_url:         s.logo_url         ?? null,
        receipt_header:   s.receipt_header   ?? null,
        receipt_footer:   s.receipt_footer   ?? null,
        currency_symbol:  s.currency_symbol  ?? 'Rs',
        language:         s.language         ?? 'en',
        branch_name:      props.branch?.name ?? null,
        cashier_name:     props.cashier?.name ?? null,
        customer_name:    cart.selectedCustomer?.name ?? null,
        customer_phone:   cart.selectedCustomer?.phone ?? null,
        items: cart.items.map(item => ({
            name:          item.name,
            name_ur:       item.name_ur,
            variant_label: item.variant_label,
            quantity:      item.quantity,
            unit_price:    item.unit_price,
            line_total:    (item.unit_price * item.quantity) - item.discount,
            discount:      item.discount,
        })),
        subtotal:         cart.subtotal,
        discount:         cart.cartDiscount,
        total:            sale.total,
        payment_method:   cart.paymentMethod,
        cash_amount:      cart.paymentMethod === 'cash' ? cart.cashReceived : (cart.paymentMethod === 'mixed' ? cart.cashReceived : 0),
        jazzcash_amount:  cart.paymentMethod === 'jazzcash' ? sale.total : cart.jazzcashAmount,
        easypaisa_amount: cart.paymentMethod === 'easypaisa' ? sale.total : cart.easypaisaAmount,
        udhaar_amount:    cart.udhaarAmount,
        change_amount:    sale.change,
    });
}

// ── Keyboard shortcuts ──────────────────────────────────────────
function handleKey(e: KeyboardEvent) {
    if (e.key === 'F1') { e.preventDefault(); (document.querySelector('input[placeholder*="barcode"]') as HTMLInputElement)?.focus(); }
    if (e.key === 'Escape') { cart.showPaymentModal = false; cart.showCustomerPanel = false; showAddCustomer.value = false; expandedItemIdx.value = null; stockError.value = ''; }
    if (e.key === 'F12') { e.preventDefault(); if (cart.items.length > 0) cart.showPaymentModal = true; }
}
onMounted(() => window.addEventListener('keydown', handleKey));
onUnmounted(() => window.removeEventListener('keydown', handleKey));

const fmt = formatMoney;

const paymentMethods = [
    { id: 'cash',      label: 'Cash' },
    { id: 'jazzcash',  label: 'JazzCash' },
    { id: 'easypaisa', label: 'Easypaisa' },
    { id: 'udhaar',    label: 'Udhaar' },
    { id: 'mixed',     label: 'Split' },
];
</script>

<template>
    <Head title="POS Cashier" />

    <PosLayout>

        <!-- ════════════════════════════════════════
             LEFT — Product catalogue
        ════════════════════════════════════════ -->
        <div class="flex flex-1 flex-col overflow-hidden border-r border-border bg-background">

            <!-- Search -->
            <div class="shrink-0 px-4 pt-3 pb-2">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search product or scan barcode… (F1)"
                        :dir="isUrdu(search) ? 'rtl' : 'ltr'"
                        class="w-full rounded-lg border border-input bg-background py-2.5 pl-9 pr-9 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <button v-if="search" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <!-- Category pills -->
            <div class="shrink-0 flex flex-nowrap gap-1.5 overflow-x-auto px-4 pb-2.5">
                <button
                    @click="selectedCategory = ''"
                    :class="selectedCategory === ''
                        ? 'bg-primary text-primary-foreground border-primary'
                        : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground'"
                    class="shrink-0 rounded-full border px-3.5 py-1 text-xs font-medium transition-all"
                >All</button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="selectedCategory = selectedCategory === cat.id ? '' : cat.id"
                    :style="selectedCategory === cat.id ? { background: cat.color, borderColor: cat.color, color: '#fff' } : {}"
                    :class="selectedCategory !== cat.id ? 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground' : ''"
                    class="shrink-0 rounded-full border px-3.5 py-1 text-xs font-medium transition-all"
                >{{ cat.name }}</button>
            </div>

            <!-- Product grid -->
            <div class="flex-1 overflow-y-auto p-3">
                <!-- Shimmer -->
                <div v-if="isSearching" class="grid grid-cols-3 gap-2.5 xl:grid-cols-4">
                    <div v-for="i in 12" :key="i" class="h-[88px] animate-pulse rounded-xl bg-muted"></div>
                </div>

                <!-- Empty -->
                <div v-else-if="products.length === 0" class="flex h-44 flex-col items-center justify-center text-muted-foreground">
                    <ShoppingCart class="mb-2 h-8 w-8 opacity-30" />
                    <p class="text-sm">No products found</p>
                </div>

                <!-- Grid -->
                <div v-else class="grid grid-cols-3 gap-2.5 xl:grid-cols-4">
                    <button
                        v-for="product in products"
                        :key="product.id"
                        @click="cart.addItem(product)"
                        :disabled="product.stock === 0"
                        :class="[
                            'group relative flex min-h-[88px] cursor-pointer flex-col justify-between rounded-xl border p-3 text-left transition-all',
                            cart.items.find(i => i.product_id === product.id)
                                ? 'border-primary/60 bg-primary/10 ring-1 ring-primary/20'
                                : product.stock === 0
                                  ? 'cursor-not-allowed border-border bg-muted/30 opacity-50'
                                  : 'border-border bg-card hover:border-foreground/20 hover:bg-accent',
                        ]"
                    >
                        <!-- In-cart qty badge -->
                        <span
                            v-if="cart.items.find(i => i.product_id === product.id)"
                            class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                        >{{ cart.items.find(i => i.product_id === product.id)?.quantity }}</span>

                        <!-- Out of stock -->
                        <span v-if="product.stock === 0" class="absolute inset-0 flex items-center justify-center rounded-xl text-xs font-semibold text-muted-foreground">
                            Out of stock
                        </span>

                        <p class="pr-6 text-[13px] font-medium leading-snug text-foreground line-clamp-2">{{ product.name }}</p>
                        <p v-if="product.name_ur" class="text-[11px] text-muted-foreground leading-tight mt-0.5" dir="rtl">{{ product.name_ur }}</p>

                        <div class="mt-1.5 flex items-end justify-between gap-1">
                            <span class="text-sm font-bold text-primary">{{ fmt(product.selling_price) }}</span>
                            <span class="text-[11px] text-muted-foreground">{{ product.stock > 0 ? `×${product.stock}` : '' }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Bottom stats bar -->
            <div class="shrink-0 border-t border-border bg-card px-4 py-2">
                <div class="grid grid-cols-4 divide-x divide-border text-center text-[11px]">
                    <div>
                        <p class="font-bold text-foreground">{{ products.length }}</p>
                        <p class="text-muted-foreground">Showing</p>
                    </div>
                    <div>
                        <p class="font-bold text-green-600 dark:text-green-400">{{ liveStats.sales_today }}</p>
                        <p class="text-muted-foreground">Sales Today</p>
                    </div>
                    <div>
                        <p class="font-bold text-foreground">{{ fmt(liveStats.revenue_today) }}</p>
                        <p class="text-muted-foreground">Revenue</p>
                    </div>
                    <div>
                        <p :class="liveStats.low_stock > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'" class="font-bold">
                            {{ liveStats.low_stock }}
                        </p>
                        <p class="text-muted-foreground">Low Stock</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             RIGHT — Cart
        ════════════════════════════════════════ -->
        <div class="flex w-[340px] shrink-0 flex-col bg-card xl:w-[380px]">

            <!-- Cart header -->
            <div class="shrink-0 border-b border-border px-4 py-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-foreground">Current Sale</span>
                    <span v-if="cart.itemCount > 0" class="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-bold text-primary-foreground">
                        {{ cart.itemCount }}
                    </span>

                    <div class="ml-auto flex items-center gap-2">
                        <!-- ★ Customer button — always visible ★ -->
                        <button
                            @click="cart.showCustomerPanel = !cart.showCustomerPanel; customerSearch = ''"
                            :class="[
                                'flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors',
                                cart.selectedCustomer
                                    ? 'border-primary/60 bg-primary/10 text-primary'
                                    : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                            ]"
                        >
                            <User class="h-3.5 w-3.5" />
                            <span class="max-w-[90px] truncate">{{ cart.selectedCustomer ? cart.selectedCustomer.name : 'Customer' }}</span>
                            <ChevronDown class="h-3 w-3 opacity-50" />
                        </button>

                        <button
                            v-if="cart.items.length > 0"
                            @click="async () => {
                                const ok = await confirm({ title: 'Clear cart?', message: 'All items will be removed from the current sale.', confirmLabel: 'Clear', variant: 'warning' });
                                if (ok) { cart.clearCart(); discountInput = 0; }
                            }"
                            class="text-xs text-muted-foreground hover:text-destructive transition-colors"
                        >Clear</button>
                    </div>
                </div>
            </div>

            <!-- ── Customer panel ── -->
            <div v-if="cart.showCustomerPanel" class="shrink-0 border-b border-border bg-muted/40 px-4 py-3">
                <!-- Header with "+ New Customer" button -->
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Select Customer</p>
                    <button
                        @click="openAddCustomer"
                        class="flex items-center gap-1 rounded-md border border-dashed border-primary/50 px-2.5 py-1 text-xs font-medium text-primary hover:border-primary hover:bg-primary/10 transition-colors"
                    >
                        <UserPlus class="h-3 w-3" />
                        Add New
                    </button>
                </div>

                <input
                    v-model="customerSearch"
                    type="text"
                    placeholder="Search by name or phone…"
                    class="mb-2 w-full rounded-lg border border-input bg-background px-3 py-1.5 text-xs placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring"
                />

                <div class="max-h-48 space-y-1 overflow-y-auto">
                    <button
                        v-for="c in customerResults"
                        :key="c.id"
                        @click="selectCustomer(c)"
                        :class="[
                            'w-full flex items-center justify-between rounded-lg border px-3 py-2 text-left text-xs transition-all',
                            cart.selectedCustomer?.id === c.id
                                ? 'border-primary/60 bg-primary/10'
                                : 'border-border hover:bg-accent',
                        ]"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-foreground truncate">{{ c.name }}</p>
                            <p class="text-muted-foreground">{{ c.phone || '—' }}</p>
                        </div>
                        <span :class="c.balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'" class="ml-2 shrink-0 text-[11px]">
                            {{ c.balance > 0 ? fmt(c.balance) : 'Clear' }}
                        </span>
                    </button>

                    <button
                        @click="cart.selectedCustomer = null; cart.showCustomerPanel = false"
                        class="w-full py-2 text-center text-xs text-muted-foreground hover:text-foreground transition-colors"
                    >Walk-in / no customer</button>
                </div>
            </div>

            <!-- ── Cart items ── -->
            <div class="flex-1 overflow-y-auto">

                <!-- Sale complete -->
                <div v-if="cart.saleComplete && cart.lastSale" class="p-4">
                    <div class="rounded-2xl border border-border bg-card p-5 text-center shadow-sm">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40">
                            <Check class="h-6 w-6 text-green-600 dark:text-green-400" />
                        </div>
                        <p class="text-base font-bold text-green-600 dark:text-green-400">Sale Complete!</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ cart.lastSale.invoice_number }}</p>

                        <div v-if="cart.lastSale.change > 0" class="mt-3 rounded-xl bg-muted px-4 py-3">
                            <p class="text-xs text-muted-foreground">Change due</p>
                            <p class="text-2xl font-black text-foreground">{{ fmt(cart.lastSale.change) }}</p>
                        </div>

                        <div class="mt-4 flex justify-center gap-2">
                            <button
                                @click="printReceipt"
                                class="flex items-center gap-1.5 rounded-lg border border-border bg-background px-4 py-2 text-xs font-medium text-foreground hover:bg-accent transition-colors"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Print Receipt
                            </button>
                        </div>
                        <button @click="newSale()" class="mt-3 text-xs text-primary hover:text-primary/80 transition-colors font-medium">
                            + Start new sale
                        </button>
                    </div>
                </div>

                <!-- Empty cart -->
                <div v-else-if="cart.items.length === 0" class="flex h-full flex-col items-center justify-center px-6 text-muted-foreground">
                    <ShoppingCart class="mb-3 h-10 w-10 opacity-20" />
                    <p class="text-sm font-medium">Cart is empty</p>
                    <p class="mt-1 text-xs">Tap a product or scan barcode</p>
                </div>

                <!-- Items -->
                <div v-else class="px-3 py-2">
                    <div
                        v-for="(item, idx) in cart.items"
                        :key="`${item.product_id}-${item.variant_id}`"
                        class="mb-1.5 rounded-xl border border-border bg-background px-3 py-2.5"
                    >
                        <!-- Main row -->
                        <div class="flex items-center gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-medium text-foreground">{{ item.name }}</p>
                                <p v-if="item.name_ur" class="truncate text-[12px] text-muted-foreground leading-tight" dir="rtl">{{ item.name_ur }}</p>
                                <p v-if="item.variant_label" class="text-xs text-muted-foreground">{{ item.variant_label }}</p>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground">{{ fmt(item.unit_price) }}</span>
                                    <span v-if="item.discount > 0" class="flex items-center gap-0.5 text-[11px] text-green-600 dark:text-green-400">
                                        <Tag class="h-2.5 w-2.5" /> −{{ fmt(item.discount) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Qty controls -->
                            <div class="flex items-center gap-1">
                                <button
                                    @click="cart.updateQuantity(idx, item.quantity - 1)"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-border bg-background text-foreground hover:bg-accent transition-colors"
                                >
                                    <Minus class="h-3.5 w-3.5" />
                                </button>
                                <span class="w-7 text-center text-[13px] font-bold text-foreground">{{ item.quantity }}</span>
                                <button
                                    @click="cart.updateQuantity(idx, item.quantity + 1)"
                                    :disabled="item.quantity >= item.stock"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-border bg-background text-foreground hover:bg-accent transition-colors disabled:opacity-30"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Line total + disc toggle -->
                            <div class="flex w-20 shrink-0 flex-col items-end">
                                <span class="text-[13px] font-semibold text-foreground">
                                    {{ fmt((item.unit_price * item.quantity) - item.discount) }}
                                </span>
                                <!-- ★ Item discount toggle button ★ -->
                                <button
                                    @click="toggleExpandItem(idx)"
                                    :class="[
                                        'mt-0.5 flex items-center gap-0.5 text-[11px] transition-colors',
                                        item.discount > 0 ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground hover:text-primary',
                                    ]"
                                >
                                    <Tag class="h-2.5 w-2.5" />
                                    {{ item.discount > 0 ? 'Edit' : 'Disc' }}
                                </button>
                            </div>
                        </div>

                        <!-- Item discount input (expanded) -->
                        <div v-if="expandedItemIdx === idx" class="mt-2 flex items-center gap-2 border-t border-border pt-2">
                            <span class="text-xs text-muted-foreground">Discount (Rs):</span>
                            <input
                                v-model.number="itemDiscountInput"
                                type="number"
                                min="0"
                                :max="item.unit_price * item.quantity"
                                class="w-24 rounded-lg border border-input bg-background px-2 py-1 text-right text-xs focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring"
                                @keydown.enter="applyItemDiscount(idx)"
                            />
                            <button @click="applyItemDiscount(idx)" class="rounded-lg bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground hover:bg-primary/90 transition-colors">Apply</button>
                            <button @click="cart.setItemDiscount(idx, 0); itemDiscountInput = 0; expandedItemIdx = null" class="text-xs text-muted-foreground hover:text-destructive transition-colors">Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Totals + Charge ── -->
            <div v-if="!cart.saleComplete" class="shrink-0 border-t border-border bg-card px-4 pb-4 pt-3">

                <!-- Totals -->
                <div class="space-y-1 text-[13px]">
                    <div class="flex justify-between text-muted-foreground">
                        <span>Subtotal</span>
                        <span class="text-foreground">{{ fmt(cart.subtotal) }}</span>
                    </div>

                    <!-- ★ Discount toggle row ★ -->
                    <div class="flex items-center justify-between">
                        <button
                            @click="showDiscountRow = !showDiscountRow"
                            :class="[
                                'flex items-center gap-1 text-[13px] font-medium transition-colors',
                                cart.cartDiscount > 0 ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground hover:text-foreground',
                            ]"
                        >
                            <Percent class="h-3.5 w-3.5" />
                            Discount
                            <span v-if="discountLabel" class="ml-1 text-xs">({{ discountLabel }})</span>
                        </button>
                        <span :class="cart.cartDiscount > 0 ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'">
                            {{ cart.cartDiscount > 0 ? `−${fmt(cart.cartDiscount)}` : '—' }}
                        </span>
                    </div>

                    <!-- Discount input panel -->
                    <div v-if="showDiscountRow && cart.items.length > 0" class="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2">
                        <div class="flex overflow-hidden rounded-md border border-input text-xs">
                            <button
                                @click="discountType = 'amount'; discountInput = 0; cart.cartDiscount = 0"
                                :class="discountType === 'amount' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                                class="px-2.5 py-1 transition-colors"
                            >Rs</button>
                            <button
                                @click="discountType = 'percent'; discountInput = 0; cart.cartDiscount = 0"
                                :class="discountType === 'percent' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                                class="px-2.5 py-1 transition-colors"
                            >%</button>
                        </div>
                        <input
                            v-model.number="discountInput"
                            type="number"
                            min="0"
                            :max="discountType === 'percent' ? 100 : cart.subtotal"
                            :placeholder="discountType === 'percent' ? '0–100' : 'Amount'"
                            class="flex-1 bg-transparent text-right text-sm text-foreground focus:outline-none"
                        />
                        <button @click="discountInput = 0; cart.cartDiscount = 0" class="text-muted-foreground hover:text-destructive transition-colors">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Total -->
                    <div class="flex items-baseline justify-between border-t border-border pt-2">
                        <span class="font-bold text-foreground">Total</span>
                        <span class="text-2xl font-black text-primary">{{ fmt(cart.total) }}</span>
                    </div>
                </div>

                <!-- Payment method -->
                <div class="mt-3">
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Payment method</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="m in paymentMethods"
                            :key="m.id"
                            @click="cart.paymentMethod = m.id as any; cart.cashReceived = 0"
                            :class="[
                                'rounded-full border px-3 py-1.5 text-xs font-medium transition-all',
                                cart.paymentMethod === m.id
                                    ? m.id === 'udhaar'
                                        ? 'border-amber-500 bg-amber-500/20 text-amber-700 dark:text-amber-300'
                                        : 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                            ]"
                        >{{ m.label }}</button>
                    </div>

                    <!-- Cash received -->
                    <div v-if="cart.paymentMethod === 'cash' && cart.items.length > 0" class="mt-2 flex items-center gap-2">
                        <span class="text-xs text-muted-foreground">Received:</span>
                        <input
                            v-model.number="cart.cashReceived"
                            type="number"
                            :placeholder="`${Math.round(cart.total)}`"
                            class="w-28 rounded-lg border border-input bg-background px-2 py-1.5 text-right text-sm focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                        <span v-if="cart.cashReceived >= cart.total" class="text-xs font-bold text-green-600 dark:text-green-400">
                            ← {{ fmt(cart.changeAmount) }}
                        </span>
                    </div>

                    <p v-if="cart.paymentMethod === 'udhaar' && !cart.selectedCustomer" class="mt-2 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                        <AlertTriangle class="h-3.5 w-3.5" />
                        Select a customer for credit sale
                    </p>
                    <p v-if="cart.paymentMethod === 'udhaar' && cart.selectedCustomer" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                        Added to <span class="font-semibold">{{ cart.selectedCustomer.name }}</span>'s udhaar
                    </p>
                </div>

                <!-- Charge button -->
                <button
                    @click="cart.showPaymentModal = true"
                    :disabled="!cart.canCharge || cart.isProcessing"
                    :class="[
                        'mt-3 w-full rounded-xl py-3.5 text-sm font-bold tracking-wide transition-all',
                        cart.canCharge && !cart.isProcessing
                            ? 'bg-primary text-primary-foreground hover:bg-primary/90 active:scale-[0.98]'
                            : 'cursor-not-allowed bg-muted text-muted-foreground',
                    ]"
                >
                    {{ cart.chargeButtonLabel }}
                    <span v-if="cart.items.length > 0" class="ml-2 text-xs opacity-50">(F12)</span>
                </button>
            </div>
        </div>

        <!-- ════ Add New Customer Modal ════ -->
        <Teleport to="body">
            <div
                v-if="showAddCustomer"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                @click.self="showAddCustomer = false"
            >
                <div class="w-full max-w-sm rounded-2xl border border-border bg-card p-6 shadow-2xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="flex items-center gap-2 text-base font-bold text-foreground">
                            <UserPlus class="h-4 w-4 text-primary" />
                            Add New Customer
                        </h2>
                        <button @click="showAddCustomer = false" class="text-muted-foreground hover:text-foreground transition-colors">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div v-if="addCustomerError" class="mb-3 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                        {{ addCustomerError }}
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Name <span class="text-destructive">*</span></label>
                            <input
                                v-model="newCustomer.name"
                                type="text"
                                placeholder="Customer name"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                                @keydown.enter="saveNewCustomer"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Phone</label>
                            <input
                                v-model="newCustomer.phone"
                                type="text"
                                placeholder="03xx-xxxxxxx"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-muted-foreground">Address <span class="text-muted-foreground/60 text-[11px]">(optional)</span></label>
                            <input
                                v-model="newCustomer.address"
                                type="text"
                                placeholder="Street / area"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <button
                            @click="showAddCustomer = false"
                            class="flex-1 rounded-xl border border-border py-2.5 text-sm text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors"
                        >Cancel</button>
                        <button
                            @click="saveNewCustomer"
                            :disabled="addingCustomer"
                            class="flex-1 rounded-xl bg-primary py-2.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60"
                        >
                            {{ addingCustomer ? 'Saving…' : 'Save & Select' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ════ Payment Confirmation Modal ════ -->
        <Teleport to="body">
            <div
                v-if="cart.showPaymentModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                @click.self="cart.showPaymentModal = false"
            >
                <div class="w-full max-w-sm rounded-2xl border border-border bg-card p-6 shadow-2xl">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-foreground">Confirm Payment</h2>
                        <button @click="cart.showPaymentModal = false" class="text-muted-foreground hover:text-foreground">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div v-if="stockError" class="mb-4 flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
                        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                        <pre class="whitespace-pre-wrap font-sans">{{ stockError }}</pre>
                    </div>

                    <div class="max-h-40 space-y-1.5 overflow-y-auto text-sm">
                        <div v-for="item in cart.items" :key="item.product_id" class="flex justify-between text-muted-foreground">
                            <span class="mr-4 truncate">{{ item.name }} × {{ item.quantity }}</span>
                            <span class="shrink-0 font-medium text-foreground">{{ fmt((item.unit_price * item.quantity) - item.discount) }}</span>
                        </div>
                    </div>

                    <div class="mt-3 border-t border-border pt-3 text-sm">
                        <div v-if="cart.cartDiscount > 0" class="flex justify-between text-green-600 dark:text-green-400">
                            <span>Discount</span><span>−{{ fmt(cart.cartDiscount) }}</span>
                        </div>
                        <div class="mt-1 flex justify-between">
                            <span class="font-bold text-foreground">Total</span>
                            <span class="text-2xl font-black text-primary">{{ fmt(cart.total) }}</span>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl bg-muted/60 px-4 py-3 text-sm">
                        <div class="flex justify-between text-muted-foreground">
                            <span>Method</span>
                            <span class="capitalize font-medium text-foreground">{{ cart.paymentMethod }}</span>
                        </div>
                        <div v-if="cart.paymentMethod === 'cash'" class="mt-1 flex justify-between text-muted-foreground">
                            <span>Change</span>
                            <span class="font-bold text-green-600 dark:text-green-400">{{ fmt(cart.changeAmount) }}</span>
                        </div>
                        <div v-if="cart.udhaarAmount > 0" class="mt-1 flex justify-between text-muted-foreground">
                            <span>Udhaar</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400">{{ fmt(cart.udhaarAmount) }}</span>
                        </div>
                        <div v-if="cart.selectedCustomer" class="mt-1 flex justify-between text-muted-foreground">
                            <span>Customer</span>
                            <span class="font-medium text-foreground">{{ cart.selectedCustomer.name }}</span>
                        </div>
                    </div>

                    <button
                        @click="submitSale"
                        :disabled="cart.isProcessing"
                        class="mt-5 w-full rounded-xl bg-primary py-3.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 active:scale-[0.98] transition-all disabled:opacity-60"
                    >
                        {{ cart.isProcessing ? 'Processing…' : `Confirm & Charge ${fmt(cart.total)}` }}
                    </button>
                </div>
            </div>
        </Teleport>

    </PosLayout>
</template>
