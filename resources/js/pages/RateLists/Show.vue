<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, ChevronLeft, Save, Search, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

// Notification
type NotifType = 'success' | 'error';
const notif = ref<{ type: NotifType; message: string } | null>(null);
let notifTimer: ReturnType<typeof setTimeout> | null = null;

function showNotif(type: NotifType, message: string) {
    if (notifTimer) clearTimeout(notifTimer);
    notif.value = { type, message };
    notifTimer = setTimeout(() => { notif.value = null; }, 4000);
}

interface RateListInfo {
    id: string;
    name: string;
    name_ur: string | null;
    description: string | null;
    is_active: boolean;
}

interface ProductVariant {
    id: string;
    label: string;
    sku: string | null;
    default_price: number;
    rate_price: number | null;
}

interface ProductRow {
    id: string;
    name: string;
    name_ur: string | null;
    sku: string | null;
    has_variants: boolean;
    default_price?: number;
    rate_price?: number | null;
    variants?: ProductVariant[];
}

const props = defineProps<{
    rateList: RateListInfo;
    products: ProductRow[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.sales'), href: route('sales.index') },
    { title: t('rateList.pageTitle'), href: route('rate-lists.index') },
    { title: locale.value === 'ur' && props.rateList.name_ur ? props.rateList.name_ur : props.rateList.name, href: route('rate-lists.show', props.rateList.id) },
]);

const search = ref('');
const saving = ref(false);

// Local editable prices: key = productId_variantId ('' for no variant)
type PriceKey = string;
const localPrices = ref<Record<PriceKey, string>>({});

// Initialise from server
props.products.forEach((p) => {
    if (p.has_variants) {
        p.variants?.forEach((v) => {
            const key = `${p.id}_${v.id}`;
            localPrices.value[key] = v.rate_price !== null && v.rate_price !== undefined ? String(v.rate_price) : '';
        });
    } else {
        const key = `${p.id}_`;
        localPrices.value[key] = p.rate_price !== null && p.rate_price !== undefined ? String(p.rate_price) : '';
    }
});

const filteredProducts = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.products;
    return props.products.filter((p) => {
        const name = (p.name + ' ' + (p.name_ur ?? '') + ' ' + (p.sku ?? '')).toLowerCase();
        return name.includes(q);
    });
});

function priceKey(productId: string, variantId?: string) {
    return `${productId}_${variantId ?? ''}`;
}

function buildPayload() {
    const prices: { product_id: string; variant_id: string | null; price: string | null }[] = [];

    props.products.forEach((p) => {
        if (p.has_variants) {
            p.variants?.forEach((v) => {
                const key = priceKey(p.id, v.id);
                const val = localPrices.value[key];
                prices.push({
                    product_id: p.id,
                    variant_id: v.id,
                    price: val === '' ? null : val,
                });
            });
        } else {
            const key = priceKey(p.id);
            const val = localPrices.value[key];
            prices.push({
                product_id: p.id,
                variant_id: null,
                price: val === '' ? null : val,
            });
        }
    });

    return { prices };
}

function saveAll() {
    saving.value = true;

    router.post(route('rate-lists.save-prices', props.rateList.id), buildPayload(), {
        preserveScroll: true,
        onSuccess: () => {
            saving.value = false;
            showNotif('success', t('rateList.saveSuccess'));
        },
        onError: () => {
            saving.value = false;
            showNotif('error', t('rateList.saveFailed'));
        },
    });
}

function handleActivate() {
    router.post(route('rate-lists.activate', props.rateList.id), {}, {
        preserveScroll: true,
    });
}

function handleDeactivate() {
    router.post(route('rate-lists.deactivate', props.rateList.id), {}, {
        preserveScroll: true,
    });
}

const displayName = computed(() =>
    locale.value === 'ur' && props.rateList.name_ur ? props.rateList.name_ur : props.rateList.name
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="displayName" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <a :href="route('rate-lists.index')" class="text-muted-foreground hover:text-foreground">
                        <ChevronLeft class="h-5 w-5" />
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold">{{ displayName }}</h1>
                            <span v-if="rateList.is_active" class="rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground">{{ t('common.active') }}</span>
                        </div>
                        <p v-if="rateList.description" class="text-muted-foreground text-sm">{{ rateList.description }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button v-if="!rateList.is_active" size="sm" variant="outline" @click="handleActivate">
                        <CheckCircle2 class="mr-1 h-4 w-4" />
                        {{ t('rateList.setActive') }}
                    </Button>
                    <Button v-else size="sm" variant="secondary" @click="handleDeactivate">
                        {{ t('rateList.deactivate') }}
                    </Button>

                    <Button @click="saveAll" :disabled="saving">
                        <Save class="mr-2 h-4 w-4" />
                        {{ saving ? t('common.saving') : t('rateList.savePrices') }}
                    </Button>
                </div>
            </div>

            <!-- Info banner -->
            <div class="bg-muted rounded-md px-4 py-2 text-sm">
                {{ t('rateList.pricesHint') }}
            </div>

            <!-- Search -->
            <div class="relative max-w-sm">
                <Search class="text-muted-foreground absolute top-2.5 left-3 h-4 w-4" />
                <Input
                    v-model="search"
                    :placeholder="t('rateList.searchProducts')"
                    class="pl-9"
                />
            </div>


            <!-- Products table -->
            <div class="rounded-lg border">
                <table class="w-full table-fixed text-sm">
                    <colgroup>
                        <col class="w-[35%]" />
                        <col class="w-[20%]" />
                        <col class="w-[20%]" />
                        <col class="w-[25%]" />
                    </colgroup>
                    <thead>
                        <tr class="bg-muted/50 border-b">
                            <th class="px-4 py-2 font-medium text-start">{{ t('rateList.colProduct') }}</th>
                            <th class="px-4 py-2 font-medium text-start">{{ t('rateList.colVariant') }}</th>
                            <th class="px-4 py-2 font-medium text-end">{{ t('rateList.colDefaultPrice') }}</th>
                            <th class="px-4 py-2 font-medium text-end">{{ t('rateList.colRatePrice') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="p in filteredProducts" :key="p.id">
                            <!-- No-variant product -->
                            <tr v-if="!p.has_variants" class="border-b last:border-0 hover:bg-muted/30">
                                <td class="px-4 py-2 align-top">
                                    <div class="font-medium">
                                        {{ locale === 'ur' && p.name_ur ? p.name_ur : p.name }}
                                    </div>
                                    <div v-if="p.sku" class="text-muted-foreground text-xs">{{ p.sku }}</div>
                                </td>
                                <td class="text-muted-foreground px-4 py-2 align-top">—</td>
                                <td class="px-4 py-2 text-end align-top">{{ formatMoney(p.default_price ?? 0) }}</td>
                                <td class="px-4 py-2 text-end align-top">
                                    <Input
                                        v-model="localPrices[priceKey(p.id)]"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :placeholder="t('rateList.leaveBlankForDefault')"
                                        class="h-8 w-32 text-sm text-right"
                                    />
                                </td>
                            </tr>

                            <!-- Variant rows -->
                            <template v-if="p.has_variants" v-for="(v, vi) in p.variants" :key="v.id">
                                <tr class="border-b last:border-0 hover:bg-muted/30">
                                    <td class="px-4 py-2 align-top">
                                        <template v-if="vi === 0">
                                            <div class="font-medium">
                                                {{ locale === 'ur' && p.name_ur ? p.name_ur : p.name }}
                                            </div>
                                            <div v-if="p.sku" class="text-muted-foreground text-xs">{{ p.sku }}</div>
                                        </template>
                                    </td>
                                    <td class="text-muted-foreground px-4 py-2 align-top">
                                        {{ v.label }}<span v-if="v.sku" class="ml-1 text-xs">({{ v.sku }})</span>
                                    </td>
                                    <td class="px-4 py-2 text-end align-top">{{ formatMoney(v.default_price) }}</td>
                                    <td class="px-4 py-2 text-end align-top">
                                        <Input
                                            v-model="localPrices[priceKey(p.id, v.id)]"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :placeholder="t('rateList.leaveBlankForDefault')"
                                            class="h-8 w-32 text-sm text-right"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </template>

                        <tr v-if="filteredProducts.length === 0">
                            <td colspan="4" class="text-muted-foreground py-10 text-center">
                                {{ t('common.noResults') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom save button for long lists -->
            <div class="flex justify-end">
                <Button @click="saveAll" :disabled="saving">
                    <Save class="mr-2 h-4 w-4" />
                    {{ saving ? t('common.saving') : t('rateList.savePrices') }}
                </Button>
            </div>
        </div>

        <!-- Floating notification -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div
                v-if="notif"
                class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 flex items-center gap-3 rounded-xl px-5 py-3 shadow-lg text-sm font-medium"
                :class="notif.type === 'success'
                    ? 'bg-green-600 text-white'
                    : 'bg-destructive text-destructive-foreground'"
            >
                <CheckCircle2 v-if="notif.type === 'success'" class="h-4 w-4 shrink-0" />
                <XCircle v-else class="h-4 w-4 shrink-0" />
                {{ notif.message }}
            </div>
        </Transition>
    </AppLayout>
</template>
