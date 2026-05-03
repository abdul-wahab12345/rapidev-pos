<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import SearchableSelect, { type SearchableOption } from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
import {
    fetchAreaSearchOptionsForCity,
    fetchCitySearchOptions,
} from '@/composables/useLocationOptions';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    Eye,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Search,
    Trash2,
    Users,
    Wallet,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();
const { confirm } = useConfirm();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.purchasing'), href: route('purchasing.orders.index') },
    { title: t('purchasing.suppliersTitle'), href: route('purchasing.suppliers.index') },
]);

interface Supplier {
    id: string;
    name: string;
    company: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    area?: string | null;
    city_id?: number | null;
    area_id?: number | null;
    payment_terms: number;
    current_balance: number;
    ar_balance: number;
    net_payable: number;
    is_also_customer: boolean;
    is_active: boolean;
    customer_id: string | null;
}

const props = defineProps<{
    suppliers: { data: Supplier[]; current_page: number; last_page: number; total: number };
    stats: { total: number; active: number; total_payable: number };
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const showModal = ref(false);
const editTarget = ref<Supplier | null>(null);

const form = useForm({
    name: '',
    company: '',
    phone: '',
    email: '',
    address: '',
    city_id: null as number | null,
    area_id: null as number | null,
    ntn: '',
    payment_terms: 30,
    opening_balance: 0,
    notes: '',
});

const cityOptions = ref<SearchableOption[]>([]);
const areaOptions = ref<SearchableOption[]>([]);
const areasBusy = ref(false);

let searchTimer: ReturnType<typeof setTimeout>;

async function loadCityOptions() {
    try {
        cityOptions.value = await fetchCitySearchOptions(locale.value);
    } catch {
        cityOptions.value = [];
    }
}

onMounted(async () => {
    await loadCityOptions();
});

watch(locale, () => {
    void loadCityOptions();
});

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 400);
});
watch(statusFilter, () => applyFilters());

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

function applyFilters(extra: Record<string, unknown> = {}) {
    router.get(
        route('purchasing.suppliers.index'),
        {
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            ...extra,
        },
        { preserveState: true, replace: true },
    );
}

function paginationQuery(page: number) {
    applyFilters(page > 1 ? { page } : {});
}

function supplierCityLine(s: Supplier): string | null {
    const parts = [s.city, s.area].filter((x): x is string => Boolean(x?.trim()));
    return parts.length ? parts.join(' · ') : null;
}

async function hydrateAreasFromFormCity() {
    const cid = form.city_id;
    if (!cid) {
        areaOptions.value = [];
        return;
    }
    areasBusy.value = true;
    try {
        areaOptions.value = await fetchAreaSearchOptionsForCity(cid);
    } finally {
        areasBusy.value = false;
    }
}

async function openCreate() {
    editTarget.value = null;
    form.reset();
    form.city_id = null;
    form.area_id = null;
    form.payment_terms = 30;
    form.opening_balance = 0;
    form.clearErrors();
    await hydrateAreasFromFormCity();
    showModal.value = true;
}

async function openEdit(s: Supplier) {
    editTarget.value = s;
    form.name = s.name;
    form.company = s.company ?? '';
    form.phone = s.phone ?? '';
    form.email = s.email ?? '';
    form.address = '';
    form.city_id = s.city_id ?? null;
    form.area_id = s.area_id ?? null;
    form.ntn = '';
    form.payment_terms = s.payment_terms;
    form.opening_balance = 0;
    form.notes = '';
    form.clearErrors();
    await hydrateAreasFromFormCity();
    showModal.value = true;
}

function save() {
    if (editTarget.value) {
        form.patch(route('purchasing.suppliers.update', editTarget.value.id), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route('purchasing.suppliers.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
}

async function remove(s: Supplier) {
    const ok = await confirm({
        title: t('purchasing.removeSupplierConfirmTitle', { name: s.name }),
        message: t('purchasing.removeSupplierConfirmMessage'),
        confirmLabel: t('common.delete'),
        cancelLabel: t('common.cancel'),
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(route('purchasing.suppliers.destroy', s.id), { preserveScroll: true });
}

function fmt(n: number) {
    if (n == null) return '—';
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}
</script>

<template>
    <Head :title="t('purchasing.suppliersTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('purchasing.suppliersTitle') }}</h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('purchasing.suppliersDescription') }}</p>
                </div>
                <Button class="gap-2" @click="openCreate">
                    <Plus :size="16" />
                    {{ t('purchasing.addSupplier') }}
                </Button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <StatCard :label="t('purchasing.totalSuppliers')" :value="String(stats.total)" :icon="Users" tone="info" />
                <StatCard :label="t('common.active')" :value="String(stats.active)" :icon="Building2" tone="success" />
                <StatCard :label="t('purchasing.totalPayable')" :value="fmt(stats.total_payable)" :icon="Wallet" tone="warning" />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <div class="relative min-w-[200px] flex-1">
                    <Search :size="16" class="absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" class="ps-9" :placeholder="t('purchasing.searchSuppliersPlaceholder')" />
                </div>
                <select
                    v-model="statusFilter"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground"
                >
                    <option value="">{{ t('purchasing.allSuppliers') }}</option>
                    <option value="active">{{ t('common.active') }}</option>
                    <option value="inactive">{{ t('common.inactive') }}</option>
                </select>
                <Button v-if="search || statusFilter" variant="ghost" size="icon" @click="(search = ''), (statusFilter = ''), applyFilters()">
                    <X :size="16" />
                </Button>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border">
                <table class="min-w-[800px] w-full border-collapse text-sm">
                    <thead class="bg-muted/50">
                        <tr class="[&>th]:align-middle">
                            <th class="px-4 py-3 text-start font-medium">{{ t('purchasing.supplier') }}</th>
                            <th class="hidden px-4 py-3 text-start font-medium md:table-cell">{{ t('common.contact') }}</th>
                            <th class="hidden px-4 py-3 text-start font-medium lg:table-cell">{{ t('purchasing.cityAreaCol') }}</th>
                            <th class="hidden px-4 py-3 text-start font-medium md:table-cell">{{ t('purchasing.terms') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-amber-700 dark:text-amber-400">{{ t('purchasing.ap') }}</th>
                            <th class="hidden px-4 py-3 text-end font-medium text-blue-700 dark:text-blue-400 sm:table-cell">{{ t('purchasing.ar') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ t('purchasing.netShort') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('common.status') }}</th>
                            <th class="px-4 py-3 text-end font-medium">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="suppliers.data.length === 0">
                            <td colspan="9" class="py-12 text-center text-muted-foreground">
                                {{ t('purchasing.noSuppliersFound') }}
                            </td>
                        </tr>
                        <tr v-for="s in suppliers.data" :key="s.id" class="transition-colors hover:bg-muted/30 [&>td]:align-middle">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ s.name }}</div>
                                <div v-if="s.company" class="text-xs text-muted-foreground">{{ s.company }}</div>
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">
                                <div v-if="s.phone" class="flex items-center gap-1 text-xs rtl:flex-row-reverse">
                                    <Phone :size="12" />{{ s.phone }}
                                </div>
                                <div v-if="s.email" class="flex items-center gap-1 text-xs text-muted-foreground rtl:flex-row-reverse">
                                    <Mail :size="12" />{{ s.email }}
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <div v-if="supplierCityLine(s)" class="flex items-start gap-1 text-xs text-muted-foreground rtl:flex-row-reverse">
                                    <MapPin class="mt-px shrink-0" :size="12" /><span>{{ supplierCityLine(s) }}</span>
                                </div>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="hidden px-4 py-3 text-xs md:table-cell">{{ t('purchasing.daysCount', { n: s.payment_terms }) }}</td>
                            <td class="px-4 py-3 text-end font-semibold tabular-nums text-amber-600 dark:text-amber-400">{{ fmt(s.current_balance) }}</td>
                            <td
                                class="hidden px-4 py-3 text-end tabular-nums sm:table-cell"
                                :class="s.ar_balance > 0 ? 'font-semibold text-blue-600 dark:text-blue-400' : 'text-muted-foreground'"
                            >
                                {{ s.ar_balance > 0 ? fmt(s.ar_balance) : '—' }}
                            </td>
                            <td
                                class="px-4 py-3 text-end font-bold tabular-nums"
                                :class="s.net_payable > 0 ? 'text-orange-500' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{ fmt(s.net_payable) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    :class="s.is_active
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ s.is_active ? t('common.active') : t('common.inactive') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <div class="inline-flex justify-end gap-1 rtl:flex-row-reverse">
                                    <Button variant="ghost" size="icon" :as="'a'" :href="route('purchasing.suppliers.show', s.id)" :title="t('returns.viewDetailsTitle')">
                                        <Eye :size="15" />
                                    </Button>
                                    <Button variant="ghost" size="icon" :title="t('common.edit')" @click="openEdit(s)">
                                        <Pencil :size="15" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="text-destructive" :title="t('common.delete')" @click="remove(s)">
                                        <Trash2 :size="15" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="suppliers.last_page > 1" class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">{{ t('returns.paginationSummary', { current: suppliers.current_page, last: suppliers.last_page, total: suppliers.total }) }}</span>
                <div class="flex gap-2">
                    <Button v-if="suppliers.current_page > 1" variant="outline" size="sm" @click="paginationQuery(suppliers.current_page - 1)">
                        {{ t('common.previous') }}
                    </Button>
                    <Button v-if="suppliers.current_page < suppliers.last_page" variant="outline" size="sm" @click="paginationQuery(suppliers.current_page + 1)">
                        {{ t('common.next') }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Add / Edit Modal -->
    <Dialog :open="showModal" @update:open="showModal = $event">
        <DialogContent class="max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ editTarget ? t('purchasing.editSupplierTitle') : t('purchasing.addSupplierTitle') }}</DialogTitle>
            </DialogHeader>
            <form class="mt-2 grid grid-cols-2 gap-4" @submit.prevent="save">
                <div class="col-span-2">
                    <Label>{{ t('common.name') }} <span class="text-destructive">*</span></Label>
                    <Input v-model="form.name" required class="mt-1" :placeholder="t('purchasing.supplierPlaceholderName')" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div>
                    <Label>{{ t('purchasing.company') }}</Label>
                    <Input v-model="form.company" class="mt-1" :placeholder="t('common.optionalHint')" />
                </div>
                <div />
                <div class="col-span-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label>{{ t('customers.cityPick') }}</Label>
                        <SearchableSelect
                            button-id="supp-city"
                            v-model="form.city_id"
                            class="[&_[data-searchable-trigger]]:w-full"
                            :options="cityOptions"
                            :placeholder="t('customers.cityPlaceholder')"
                            :search-placeholder="t('locations.searchCities')"
                        />
                        <p class="text-xs text-muted-foreground">{{ t('customers.cityHelpShort') }}</p>
                        <p v-if="form.errors.city_id" class="text-xs text-destructive">{{ form.errors.city_id }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label>{{ t('customers.areaPick') }}</Label>
                        <SearchableSelect
                            button-id="supp-area"
                            v-model="form.area_id"
                            class="[&_[data-searchable-trigger]]:w-full"
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
                        />
                        <Link
                            class="inline-block pt-1 text-xs text-primary underline"
                            :href="route('locations.index')"
                            @click="showModal = false"
                        >
                            {{ t('locations.manageAreasLinkShort') }}
                        </Link>
                        <p v-if="form.errors.area_id" class="text-xs text-destructive">{{ form.errors.area_id }}</p>
                    </div>
                </div>
                <div>
                    <Label>{{ t('common.phone') }}</Label>
                    <Input v-model="form.phone" class="mt-1" :placeholder="t('purchasing.phonePlaceholderPk')" />
                </div>
                <div>
                    <Label>{{ t('common.email') }}</Label>
                    <Input v-model="form.email" type="email" class="mt-1" />
                </div>
                <div>
                    <Label>{{ t('purchasing.ntn') }}</Label>
                    <Input v-model="form.ntn" class="mt-1" :placeholder="t('purchasing.ntnPlaceholderExample')" />
                </div>
                <div>
                    <Label>{{ t('purchasing.paymentTerms') }}</Label>
                    <Input v-model.number="form.payment_terms" type="number" min="0" class="mt-1" />
                </div>
                <div v-if="!editTarget" class="col-span-2">
                    <Label>{{ t('purchasing.openingBalance') }}</Label>
                    <Input v-model.number="form.opening_balance" type="number" min="0" step="0.01" class="mt-1" />
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('purchasing.openingBalanceHelp') }}</p>
                </div>
                <div class="col-span-2">
                    <Label>{{ t('common.address') }}</Label>
                    <Input v-model="form.address" class="mt-1" />
                </div>
                <div class="col-span-2">
                    <Label>{{ t('common.notes') }}</Label>
                    <Input v-model="form.notes" class="mt-1" />
                </div>
                <DialogFooter class="col-span-2 gap-2 pt-2">
                    <Button type="button" variant="outline" @click="showModal = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ editTarget ? t('common.saveChanges') : t('purchasing.addSupplier') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
