<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle, Calendar, CheckCircle2, Clock, Eye, Package,
    Plus, Search, ShoppingCart, TrendingUp, Wallet, X,
} from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'Purchase Orders', href: '/purchasing/orders' },
];

interface OrderRow {
    id: string;
    po_number: string;
    supplier: { name: string };
    order_date: string;
    expected_date: string | null;
    status: string;
    total: number;
    paid_amount: number;
    amount_due: number;
}

const props = defineProps<{
    orders: { data: OrderRow[]; current_page: number; last_page: number; total: number };
    stats: { total: number; pending: number; total_due: number; this_month: number };
    filters: { search?: string; status?: string };
}>();

const search       = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 400); });
watch(statusFilter, applyFilters);

function applyFilters() {
    router.get('/purchasing/orders', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

const statusConfig: Record<string, { cls: string; label: string }> = {
    draft:     { cls: 'bg-muted text-muted-foreground', label: 'Draft' },
    ordered:   { cls: 'bg-blue-500/10 text-blue-600 dark:text-blue-400', label: 'Ordered' },
    partial:   { cls: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', label: 'Partial' },
    received:  { cls: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', label: 'Received' },
    cancelled: { cls: 'bg-red-500/10 text-red-500', label: 'Cancelled' },
};

function fmt(n: number) {
    if (n == null) return '—';
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}
</script>

<template>
    <Head title="Purchase Orders" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Purchase Orders</h1>
                    <p class="text-muted-foreground text-sm mt-1">Track stock purchases and supplier payables</p>
                </div>
                <div class="flex gap-2">
                    <Link href="/purchasing/suppliers">
                        <Button variant="outline" class="gap-2">
                            <Package :size="16" /> Suppliers
                        </Button>
                    </Link>
                    <Link href="/purchasing/orders/create">
                        <Button class="gap-2">
                            <Plus :size="16" /> New PO
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard title="Total Orders"   :value="String(stats.total)"         :icon="ShoppingCart"  color="blue" />
                <StatCard title="Pending"        :value="String(stats.pending)"        :icon="Clock"         color="orange" />
                <StatCard title="Total Due"      :value="fmt(stats.total_due)"         :icon="Wallet"        color="red" />
                <StatCard title="This Month"     :value="fmt(stats.this_month)"        :icon="TrendingUp"    color="green" />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <Search :size="16" class="text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2" />
                    <Input v-model="search" placeholder="Search PO number or supplier…" class="pl-9" />
                </div>
                <select v-model="statusFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="ordered">Ordered</option>
                    <option value="partial">Partial</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <Button v-if="search || statusFilter" variant="ghost" size="icon"
                    @click="search=''; statusFilter=''; applyFilters()">
                    <X :size="16" />
                </Button>
            </div>

            <!-- Table -->
            <div class="border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">PO #</th>
                            <th class="px-4 py-3 text-left font-medium">Supplier</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Order Date</th>
                            <th class="px-4 py-3 text-left font-medium hidden lg:table-cell">Expected</th>
                            <th class="px-4 py-3 text-center font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-right font-medium">Due</th>
                            <th class="px-4 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="orders.data.length === 0">
                            <td colspan="8" class="text-muted-foreground py-12 text-center">
                                No purchase orders found
                            </td>
                        </tr>
                        <tr v-for="o in orders.data" :key="o.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 font-mono font-medium">{{ o.po_number }}</td>
                            <td class="px-4 py-3">{{ o.supplier?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs hidden md:table-cell">{{ o.order_date }}</td>
                            <td class="px-4 py-3 text-xs hidden lg:table-cell">
                                {{ o.expected_date ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="statusConfig[o.status]?.cls"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium">
                                    {{ statusConfig[o.status]?.label ?? o.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">{{ fmt(o.total) }}</td>
                            <td class="px-4 py-3 text-right">
                                <span :class="o.amount_due > 0 ? 'text-orange-500 font-medium' : 'text-muted-foreground'">
                                    {{ o.amount_due > 0 ? fmt(o.amount_due) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/purchasing/orders/${o.id}`">
                                    <Button variant="ghost" size="sm" class="gap-1">
                                        <Eye :size="14" /> View
                                    </Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="orders.last_page > 1" class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">
                    Page {{ orders.current_page }} of {{ orders.last_page }} · {{ orders.total }} total
                </span>
                <div class="flex gap-2">
                    <Button v-if="orders.current_page > 1" variant="outline" size="sm"
                        @click="router.get('/purchasing/orders', { ...filters, page: orders.current_page - 1 })">
                        Previous
                    </Button>
                    <Button v-if="orders.current_page < orders.last_page" variant="outline" size="sm"
                        @click="router.get('/purchasing/orders', { ...filters, page: orders.current_page + 1 })">
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
