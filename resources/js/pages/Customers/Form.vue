<script setup lang="ts">
import SearchableSelect, { type SearchableOption } from '@/components/SearchableSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    fetchAreaSearchOptionsForCity,
    fetchCitySearchOptions,
} from '@/composables/useLocationOptions';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface CustomerData {
    id?: string;
    name: string;
    phone: string | null;
    cnic: string | null;
    address: string | null;
    notes: string | null;
    credit_limit: number;
    discount_percent: number;
    city_id?: number | null;
    area_id?: number | null;
}

const props = defineProps<{ customer: CustomerData | null }>();

const { t, locale } = useI18n();

const isEdit = !!props.customer?.id;

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.customers'), href: route('customers.index') },
    { title: isEdit ? t('customers.editCustomerTitle') : t('customers.addCustomerTitle'), href: '#' },
]);

const form = useForm({
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    cnic: props.customer?.cnic ?? '',
    address: props.customer?.address ?? '',
    notes: props.customer?.notes ?? '',
    credit_limit: props.customer?.credit_limit ?? '',
    discount_percent: props.customer?.discount_percent ?? '',
    opening_balance: '' as number | '',
    city_id: props.customer?.city_id ?? null,
    area_id: props.customer?.area_id ?? null,
});

const cityOptions = ref<SearchableOption[]>([]);
const areaOptions = ref<SearchableOption[]>([]);
const areasBusy = ref(false);

async function loadCityOptions() {
    try {
        cityOptions.value = await fetchCitySearchOptions(locale.value);
    } catch {
        cityOptions.value = [];
    }
}

onMounted(async () => {
    await loadCityOptions();

    const cid = form.city_id;
    if (cid) {
        areasBusy.value = true;
        try {
            areaOptions.value = await fetchAreaSearchOptionsForCity(cid);
        } finally {
            areasBusy.value = false;
        }
    }
});

watch(locale, () => {
    void loadCityOptions();
});

watch(
    () => form.city_id,
    async (cid, prev) => {
        if (cid !== prev) {
            form.area_id = null;
        }
        if (!cid) {
            areaOptions.value = [];
            return;
        }
        areasBusy.value = true;
        try {
            areaOptions.value = await fetchAreaSearchOptionsForCity(cid);
        } catch {
            areaOptions.value = [];
        } finally {
            areasBusy.value = false;
        }
    },
);

function submit() {
    if (isEdit && props.customer?.id) {
        form.put(route('customers.update', props.customer.id));
    } else {
        form.post(route('customers.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? t('customers.editCustomerTitle') : t('customers.addCustomerTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-2xl">
            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link
                    :href="isEdit ? route('customers.show', customer!.id) : route('customers.index')"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" /> {{ t('common.back') }}
                </Link>
                <h1 class="text-xl font-bold tracking-tight text-foreground">
                    {{ isEdit ? t('customers.editCustomerTitle') : t('customers.addCustomerTitle') }}
                </h1>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4 rounded-xl border border-border bg-card p-6">
                <!-- Name -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                        {{ t('common.name') }} <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :placeholder="t('customers.namePlaceholder')"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="form.errors.name ? 'border-destructive' : ''"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <!-- Phone -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                        {{ t('common.phone') }} <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="form.phone"
                        type="text"
                        :placeholder="t('customers.phonePlaceholder')"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="form.errors.phone ? 'border-destructive' : ''"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-destructive">{{ form.errors.phone }}</p>
                </div>

                <!-- CNIC -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">{{ t('customers.cnic') }}</label>
                    <input
                        v-model="form.cnic"
                        type="text"
                        :placeholder="t('customers.cnicPlaceholder')"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Address -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">{{ t('common.address') }}</label>
                    <input
                        v-model="form.address"
                        type="text"
                        :placeholder="t('customers.addressPlaceholder')"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- City / Area -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-foreground">{{ t('customers.cityPick') }}</label>
                        <SearchableSelect
                            button-id="cust-city-select"
                            v-model="form.city_id"
                            :options="cityOptions"
                            :placeholder="t('customers.cityPlaceholder')"
                            :search-placeholder="t('locations.searchCities')"
                            :empty-text="t('customers.noCityMatches')"
                            class="[&_[data-searchable-trigger]]:w-full"
                        />
                        <p class="text-xs text-muted-foreground">{{ t('customers.cityHelp') }}</p>
                        <p v-if="form.errors.city_id" class="text-xs text-destructive">{{ form.errors.city_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-foreground">{{ t('customers.areaPick') }}</label>
                        <SearchableSelect
                            button-id="cust-area-select"
                            v-model="form.area_id"
                            :disabled="!form.city_id || areasBusy"
                            :options="areaOptions"
                            :placeholder="
                                areasBusy
                                    ? t('locations.areasLoading')
                                    : form.city_id
                                      ? t('customers.areaPlaceholder')
                                      : t('customers.areaNeedsCity')
                            "
                            :search-placeholder="t('locations.searchAreas')"
                            :empty-text="t('customers.noAreasForCity')"
                            class="[&_[data-searchable-trigger]]:w-full"
                        />
                        <p class="text-xs text-muted-foreground">{{ t('customers.areaHelp') }}</p>
                        <p v-if="form.errors.area_id" class="text-xs text-destructive">{{ form.errors.area_id }}</p>
                    </div>
                </div>

                <!-- Credit limit + Discount % -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            {{ t('customers.creditLimitLabel') }}
                            <span class="ms-1 text-xs font-normal text-muted-foreground">{{ t('customers.noLimitHint') }}</span>
                        </label>
                        <input
                            v-model="form.credit_limit"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            {{ t('customers.autoDiscount') }}
                            <span class="ms-1 text-xs font-normal text-muted-foreground">{{ t('customers.discountPercentHint') }}</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.discount_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                placeholder="0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 pe-7 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="form.errors.discount_percent ? 'border-destructive' : ''"
                            />
                            <span class="pointer-events-none absolute end-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">
                                %
                            </span>
                        </div>
                        <p v-if="form.errors.discount_percent" class="mt-1 text-xs text-destructive">{{ form.errors.discount_percent }}</p>
                    </div>
                </div>

                <!-- Opening udhaar balance (create only) -->
                <div v-if="!isEdit">
                    <label class="mb-1.5 block text-sm font-medium text-foreground">{{ t('customers.openingBalance') }}</label>
                    <input
                        v-model.number="form.opening_balance"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('customers.openingBalanceHelp') }}</p>
                    <p v-if="form.errors.opening_balance" class="mt-1 text-xs text-destructive">{{ form.errors.opening_balance }}</p>
                </div>

                <!-- Notes -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">{{ t('common.notes') }}</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        :placeholder="t('customers.notesPlaceholder')"
                        class="w-full resize-none rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-2">
                    <Link
                        :href="isEdit ? route('customers.show', customer!.id) : route('customers.index')"
                        class="flex flex-1 items-center justify-center rounded-xl border border-border py-2.5 text-center text-sm text-muted-foreground transition-colors hover:bg-accent"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-bold text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60"
                    >
                        <Save class="h-4 w-4" />
                        {{ form.processing ? t('common.saving') : isEdit ? t('common.saveChanges') : t('customers.addCustomer') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
