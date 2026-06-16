<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Layers, Plus, Search, Truck } from 'lucide-vue-next';
import { ref } from 'vue';

interface Challan {
    id: string; challan_number: string; status: string;
    customer: { id: string; name: string } | null;
    site_address: string | null; delivery_date: string | null;
    vehicle_number: string | null; driver_name: string | null;
    created_at: string;
}

const props = defineProps<{
    challans: { data: Challan[]; links: any[]; meta: any };
    stats: { total: number; pending: number; dispatched: number; delivered: number };
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(route('challans.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
}

const statusBadge: Record<string, string> = {
    pending:    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    dispatched: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    delivered:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
};
</script>

<template>
    <Head title="Delivery Challans" />
    <AppLayout :breadcrumbs="[{ title: 'Sales', href: '/sales' }, { title: 'Delivery Challans' }]">
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Delivery Challans</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Track goods dispatched to customer sites</p>
                </div>
                <Link :href="route('challans.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors">
                    <Plus class="h-4 w-4" /> New Challan
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                        <Layers class="h-4 w-4 text-primary" />
                    </div>
                    <div><p class="text-xs text-muted-foreground">Total</p><p class="text-xl font-bold">{{ stats.total }}</p></div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10">
                        <Layers class="h-4 w-4 text-amber-500" />
                    </div>
                    <div><p class="text-xs text-muted-foreground">Pending</p><p class="text-xl font-bold">{{ stats.pending }}</p></div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10">
                        <Truck class="h-4 w-4 text-blue-500" />
                    </div>
                    <div><p class="text-xs text-muted-foreground">Dispatched</p><p class="text-xl font-bold">{{ stats.dispatched }}</p></div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10">
                        <Truck class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div><p class="text-xs text-muted-foreground">Delivered</p><p class="text-xl font-bold">{{ stats.delivered }}</p></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex gap-3 flex-wrap">
                <div class="relative">
                    <Search class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <input v-model="search" @keydown.enter="applyFilters" type="text" placeholder="Search challan or customer..."
                        class="rounded-lg border border-input bg-background ps-9 pe-3 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-ring" />
                </div>
                <select v-model="statusFilter" @change="applyFilters"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="delivered">Delivered</option>
                </select>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Challan #</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Customer</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden md:table-cell">Site</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Status</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden sm:table-cell">Delivery Date</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden lg:table-cell">Vehicle</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden lg:table-cell">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="challans.data.length === 0">
                            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">No challans found.</td>
                        </tr>
                        <tr v-for="c in challans.data" :key="c.id" class="hover:bg-muted/30 cursor-pointer transition-colors"
                            @click="router.visit(route('challans.show', c.id))">
                            <td class="px-4 py-3 font-mono font-medium text-primary">{{ c.challan_number }}</td>
                            <td class="px-4 py-3">{{ c.customer?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground hidden md:table-cell max-w-[160px] truncate">{{ c.site_address ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusBadge[c.status] ?? '']">
                                    {{ c.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground hidden sm:table-cell">{{ c.delivery_date ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground hidden lg:table-cell">{{ c.vehicle_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground hidden lg:table-cell">{{ c.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="challans.meta?.last_page > 1" class="flex items-center justify-between border-t border-border px-4 py-3">
                    <p class="text-sm text-muted-foreground">Showing {{ challans.meta.from }}–{{ challans.meta.to }} of {{ challans.meta.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in challans.links" :key="link.label" :href="link.url ?? '#'"
                            :class="['px-3 py-1.5 rounded-lg text-sm transition-colors', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-muted-foreground', !link.url ? 'pointer-events-none opacity-40' : '']"
                            v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
