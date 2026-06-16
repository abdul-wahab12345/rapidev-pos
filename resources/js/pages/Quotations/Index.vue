<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/utils/format';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle, Clock, Edit, FileText, Printer, Plus, Search, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Quotation {
    id: string;
    quotation_number: string;
    status: string;
    customer: { id: string; name: string } | null;
    site_address: string | null;
    total: number;
    advance_paid: number;
    balance_due: number;
    valid_until: string | null;
    created_at: string;
}

const props = defineProps<{
    quotations: { data: Quotation[]; links: any[]; meta: any };
    stats: { total: number; draft: number; approved: number; converted: number; total_value: number };
    filters: { search?: string; status?: string };
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const { confirm } = useConfirm();

function applyFilters() {
    router.get(route('quotations.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
}

async function cancelQuotation(e: Event, q: Quotation) {
    e.stopPropagation();
    if (await confirm({ title: 'Cancel Quotation', message: `Cancel ${q.quotation_number}? This cannot be undone.`, confirmText: 'Cancel Quotation', variant: 'destructive' })) {
        router.patch(route('quotations.update-status', q.id), { status: 'cancelled' }, { preserveScroll: true });
    }
}

function openPrint(e: Event, q: Quotation) {
    e.stopPropagation();
    window.open(route('quotations.show', q.id), '_blank');
}

const statusBadge: Record<string, string> = {
    draft:     'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    sent:      'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    approved:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    converted: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
    expired:   'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};
</script>

<template>
    <Head title="Quotations" />
    <AppLayout :breadcrumbs="[{ title: 'Sales', href: '/sales' }, { title: 'Quotations', href: '/quotations' }]">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Quotations</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Manage quotes and convert them to sales</p>
                </div>
                <Link :href="route('quotations.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors">
                    <Plus class="h-4 w-4" /> New Quotation
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                            <FileText class="h-4 w-4 text-primary" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Total Quotes</p>
                            <p class="text-xl font-bold">{{ stats.total }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10">
                            <Clock class="h-4 w-4 text-amber-500" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Draft / Sent</p>
                            <p class="text-xl font-bold">{{ stats.draft }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10">
                            <CheckCircle class="h-4 w-4 text-emerald-500" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Approved</p>
                            <p class="text-xl font-bold">{{ stats.approved }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-500/10">
                            <XCircle class="h-4 w-4 text-purple-500" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Converted</p>
                            <p class="text-xl font-bold">{{ stats.converted }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex gap-3 flex-wrap">
                <div class="relative">
                    <Search class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <input v-model="search" @keydown.enter="applyFilters" type="text"
                        placeholder="Search quotation number or customer..."
                        class="rounded-lg border border-input bg-background ps-9 pe-3 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-ring" />
                </div>
                <select v-model="statusFilter" @change="applyFilters"
                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="approved">Approved</option>
                    <option value="converted">Converted</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <!-- Table -->
            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Quotation #</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Customer</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden md:table-cell">Site</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground">Status</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">Total</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground hidden sm:table-cell">Balance Due</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden lg:table-cell">Valid Until</th>
                            <th class="px-4 py-3 text-start font-medium text-muted-foreground hidden lg:table-cell">Date</th>
                            <th class="px-4 py-3 text-end font-medium text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="quotations.data.length === 0">
                            <td colspan="9" class="px-4 py-12 text-center text-muted-foreground">No quotations found.</td>
                        </tr>
                        <tr v-for="q in quotations.data" :key="q.id" class="hover:bg-muted/30 cursor-pointer transition-colors"
                            @click="router.visit(route('quotations.show', q.id))">
                            <td class="px-4 py-3 font-mono font-medium text-primary">{{ q.quotation_number }}</td>
                            <td class="px-4 py-3">{{ q.customer?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground hidden md:table-cell max-w-[160px] truncate">{{ q.site_address ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize', statusBadge[q.status] ?? '']">
                                    {{ q.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end font-medium tabular-nums">{{ formatMoney(q.total) }}</td>
                            <td class="px-4 py-3 text-end tabular-nums hidden sm:table-cell" :class="q.balance_due > 0 ? 'text-amber-600 font-medium' : 'text-muted-foreground'">
                                {{ q.balance_due > 0 ? formatMoney(q.balance_due) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground hidden lg:table-cell">{{ q.valid_until ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground hidden lg:table-cell">{{ q.created_at }}</td>
                            <td class="px-4 py-3" @click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <!-- Print -->
                                    <button @click="openPrint($event, q)"
                                        title="Print"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                                        <Printer class="h-3.5 w-3.5" />
                                    </button>
                                    <!-- Edit (not for converted/cancelled) -->
                                    <Link v-if="!['converted', 'cancelled'].includes(q.status)"
                                        :href="route('quotations.edit', q.id)"
                                        title="Edit"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                        @click.stop>
                                        <Edit class="h-3.5 w-3.5" />
                                    </Link>
                                    <!-- Cancel (not for converted/cancelled) -->
                                    <button v-if="!['converted', 'cancelled'].includes(q.status)"
                                        @click="cancelQuotation($event, q)"
                                        title="Cancel"
                                        class="rounded-md p-1.5 text-muted-foreground hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 transition-colors">
                                        <XCircle class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="quotations.meta?.last_page > 1" class="flex items-center justify-between border-t border-border px-4 py-3">
                    <p class="text-sm text-muted-foreground">
                        Showing {{ quotations.meta.from }}–{{ quotations.meta.to }} of {{ quotations.meta.total }}
                    </p>
                    <div class="flex gap-1">
                        <Link v-for="link in quotations.links" :key="link.label" :href="link.url ?? '#'"
                            :class="['px-3 py-1.5 rounded-lg text-sm transition-colors',
                                link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-muted-foreground',
                                !link.url ? 'pointer-events-none opacity-40' : '']"
                            v-html="link.label" />
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
