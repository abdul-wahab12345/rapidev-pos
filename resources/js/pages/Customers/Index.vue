<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatMoney } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowUpRight, BookOpen, Plus, Search, Users, Wallet, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface CustomerRow {
    id: string;
    name: string;
    phone: string | null;
    cnic: string | null;
    address: string | null;
    current_balance: number;
    credit_limit: number;
    total_spend: number;
    created_at: string;
}

const props = defineProps<{
    customers: {
        data: CustomerRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: { total: number; with_udhaar: number; total_udhaar: number; total_spend: number };
    filters: { search?: string; balance?: string };
}>();

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.customers'), href: route('customers.index') },
]);

const search  = ref(props.filters.search  ?? '');
const balance = ref(props.filters.balance ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters() {
    router.get(route('customers.index'), {
        search:  search.value  || undefined,
        balance: balance.value || undefined,
    }, { preserveScroll: true, replace: true });
}

watch([search], () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch([balance], applyFilters);

function clearFilters() {
    search.value = '';
    balance.value = '';
    applyFilters();
}

const fmt = formatMoney;
const hasFilters = () => !!(search.value || balance.value);
</script>

<template>
    <Head :title="t('customers.pageTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('customers.pageTitle') }}</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ t('customers.pageDescription') }}</p>
                </div>
                <Link
                    :href="route('customers.create')"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('customers.addCustomer') }}
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <Users class="h-4 w-4" />
                        <p class="text-xs font-medium">{{ t('customers.totalCustomers') }}</p>
                    </div>
                    <p class="mt-1.5 text-2xl font-black text-foreground">{{ stats.total }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <AlertTriangle class="h-4 w-4" />
                        <p class="text-xs font-medium">{{ t('customers.withUdhaar') }}</p>
                    </div>
                    <p class="mt-1.5 text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.with_udhaar }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                        <BookOpen class="h-4 w-4" />
                        <p class="text-xs font-medium">{{ t('customers.totalUdhaar') }}</p>
                    </div>
                    <p class="mt-1.5 text-lg font-black text-red-600 dark:text-red-400">{{ fmt(stats.total_udhaar) }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <Wallet class="h-4 w-4" />
                        <p class="text-xs font-medium">{{ t('customers.totalRevenue') }}</p>
                    </div>
                    <p class="mt-1.5 text-lg font-black text-green-600 dark:text-green-400">{{ fmt(stats.total_spend) }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-2">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('customers.searchPlaceholder')"
                        class="w-full rounded-lg border border-input bg-background py-2 ps-9 pe-4 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <select v-model="balance" class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">{{ t('customers.allCustomers') }}</option>
                    <option value="has_udhaar">{{ t('customers.hasUdhaar') }}</option>
                    <option value="clear">{{ t('customers.clearBalance') }}</option>
                </select>
                <button
                    v-if="hasFilters()"
                    @click="clearFilters"
                    class="flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground hover:border-foreground/30 hover:text-foreground transition-colors"
                >
                    <X class="h-3.5 w-3.5" /> {{ t('common.clear') }}
                </button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-border overflow-x-auto">
                <table class="w-full border-collapse text-sm min-w-[640px]">
                    <thead class="bg-muted/50">
                        <tr class="[&>th]:align-middle text-start text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3">{{ t('common.name') }}</th>
                            <th class="px-4 py-3">{{ t('common.phone') }}</th>
                            <th class="px-4 py-3 text-end">{{ t('customers.totalSpend') }}</th>
                            <th class="px-4 py-3 text-end">{{ t('customers.udhaarBalance') }}</th>
                            <th class="px-4 py-3 text-end">{{ t('customers.creditLimit') }}</th>
                            <th class="px-4 py-3 text-end w-28"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">

                        <tr v-if="customers.data.length === 0">
                            <td colspan="6" class="px-4 py-16 text-center">
                                <Users class="mx-auto mb-3 h-10 w-10 text-muted-foreground/30" />
                                <p class="text-sm text-muted-foreground">{{ t('customers.noCustomersFound') }}</p>
                            </td>
                        </tr>

                        <tr
                            v-for="c in customers.data"
                            :key="c.id"
                            class="hover:bg-muted/30 transition-colors [&>td]:align-middle"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ c.name }}</p>
                                <p v-if="c.cnic" class="text-xs text-muted-foreground">{{ t('customers.cnic') }}: {{ c.cnic }}</p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ c.phone || '—' }}</td>
                            <td class="px-4 py-3 text-end font-medium text-foreground tabular-nums">{{ fmt(c.total_spend) }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">
                                <span
                                    :class="c.current_balance > 0
                                        ? 'text-red-600 dark:text-red-400 font-semibold'
                                        : 'text-muted-foreground'"
                                >
                                    {{ c.current_balance > 0 ? fmt(c.current_balance) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end text-muted-foreground tabular-nums">
                                {{ c.credit_limit > 0 ? fmt(c.credit_limit) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-end align-middle">
                                <Link
                                    :href="route('customers.show', c.id)"
                                    class="inline-flex items-center justify-end gap-1 text-xs text-muted-foreground hover:text-primary transition-colors rtl:flex-row-reverse"
                                >
                                    <ArrowUpRight class="h-3.5 w-3.5" />
                                    {{ t('common.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="customers.last_page > 1" class="flex items-center justify-between text-sm text-muted-foreground">
                <p>{{ t('customers.paginationCustomersTotal', { count: customers.total }) }}</p>
                <div class="flex gap-1">
                    <template v-for="link in customers.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="['rounded-md border px-3 py-1.5 text-xs transition-colors', link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent']"
                            v-html="link.label"
                        />
                        <span v-else class="rounded-md border border-border px-3 py-1.5 text-xs opacity-40" v-html="link.label" />
                    </template>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
