<script setup lang="ts">
import StatCard from '@/components/pos/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Building2, Eye, Mail, MapPin, Pencil, Phone, Plus, Search,
    ShoppingCart, Trash2, Users, Wallet, X,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchasing', href: '/purchasing/orders' },
    { title: 'Suppliers', href: '/purchasing/suppliers' },
];

interface Supplier {
    id: string;
    name: string;
    company: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
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

const search     = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const showModal  = ref(false);
const editTarget = ref<Supplier | null>(null);

const form = useForm({
    name: '', company: '', phone: '', email: '',
    address: '', city: '', ntn: '', payment_terms: 30,
    opening_balance: 0, notes: '',
});

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 400);
});
watch(statusFilter, () => applyFilters());

function applyFilters() {
    router.get('/purchasing/suppliers', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function openCreate() {
    editTarget.value = null;
    form.reset();
    showModal.value = true;
}

function openEdit(s: Supplier) {
    editTarget.value = s;
    form.name          = s.name;
    form.company       = s.company ?? '';
    form.phone         = s.phone ?? '';
    form.email         = s.email ?? '';
    form.address       = '';
    form.city          = s.city ?? '';
    form.ntn           = '';
    form.payment_terms = s.payment_terms;
    form.opening_balance = 0;
    form.notes = '';
    showModal.value = true;
}

function save() {
    if (editTarget.value) {
        form.patch(`/purchasing/suppliers/${editTarget.value.id}`, {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    } else {
        form.post('/purchasing/suppliers', {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

function remove(s: Supplier) {
    if (!confirm(`Remove ${s.name}?`)) return;
    router.delete(`/purchasing/suppliers/${s.id}`, { preserveScroll: true });
}

function fmt(n: number) {
    if (n == null) return '—';
    const parts = n.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'Rs ' + parts.join('.');
}
</script>

<template>
    <Head title="Suppliers" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Suppliers</h1>
                    <p class="text-muted-foreground text-sm mt-1">Manage your vendor contacts and payables</p>
                </div>
                <Button @click="openCreate" class="gap-2">
                    <Plus :size="16" />
                    Add Supplier
                </Button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <StatCard label="Total Suppliers" :value="String(stats.total)"      :icon="Users"       tone="info" />
                <StatCard label="Active"          :value="String(stats.active)"     :icon="Building2"   tone="success" />
                <StatCard label="Total Payable"   :value="fmt(stats.total_payable)" :icon="Wallet"      tone="warning" />
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <Search :size="16" class="text-muted-foreground absolute left-3 top-1/2 -translate-y-1/2" />
                    <Input v-model="search" placeholder="Search by name, company, phone…" class="pl-9" />
                </div>
                <select v-model="statusFilter"
                    class="border-input bg-background text-foreground rounded-md border px-3 py-2 text-sm">
                    <option value="">All Suppliers</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
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
                            <th class="px-4 py-3 text-left font-medium">Supplier</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Contact</th>
                            <th class="px-4 py-3 text-left font-medium hidden lg:table-cell">City</th>
                            <th class="px-4 py-3 text-left font-medium hidden md:table-cell">Terms</th>
                            <th class="px-4 py-3 text-right font-medium text-amber-700 dark:text-amber-400">AP</th>
                            <th class="px-4 py-3 text-right font-medium text-blue-700 dark:text-blue-400 hidden sm:table-cell">AR</th>
                            <th class="px-4 py-3 text-right font-medium">Net</th>
                            <th class="px-4 py-3 text-center font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="suppliers.data.length === 0">
                            <td colspan="9" class="text-muted-foreground py-12 text-center">
                                No suppliers found
                            </td>
                        </tr>
                        <tr v-for="s in suppliers.data" :key="s.id"
                            class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ s.name }}</div>
                                <div v-if="s.company" class="text-muted-foreground text-xs">{{ s.company }}</div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <div v-if="s.phone" class="flex items-center gap-1 text-xs">
                                    <Phone :size="12" />{{ s.phone }}
                                </div>
                                <div v-if="s.email" class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <Mail :size="12" />{{ s.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <div v-if="s.city" class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <MapPin :size="12" />{{ s.city }}
                                </div>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-xs hidden md:table-cell">
                                {{ s.payment_terms }} days
                            </td>
                            <!-- AP actual -->
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-amber-600 dark:text-amber-400">
                                {{ fmt(s.current_balance) }}
                            </td>
                            <!-- AR receivable -->
                            <td class="px-4 py-3 text-right tabular-nums hidden sm:table-cell"
                                :class="s.ar_balance > 0 ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-muted-foreground'">
                                {{ s.ar_balance > 0 ? fmt(s.ar_balance) : '—' }}
                            </td>
                            <!-- Net -->
                            <td class="px-4 py-3 text-right tabular-nums font-bold"
                                :class="s.net_payable > 0 ? 'text-orange-500' : 'text-emerald-600 dark:text-emerald-400'">
                                {{ fmt(s.net_payable) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="s.is_active
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground'"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium">
                                    {{ s.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" :as="'a'" :href="route('purchasing.suppliers.show', s.id)" title="View details">
                                        <Eye :size="15" />
                                    </Button>
                                    <Button variant="ghost" size="icon" @click="openEdit(s)">
                                        <Pencil :size="15" />
                                    </Button>
                                    <Button variant="ghost" size="icon" class="text-destructive" @click="remove(s)">
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
                <span class="text-muted-foreground">
                    Page {{ suppliers.current_page }} of {{ suppliers.last_page }} · {{ suppliers.total }} total
                </span>
                <div class="flex gap-2">
                    <Button v-if="suppliers.current_page > 1" variant="outline" size="sm"
                        @click="router.get('/purchasing/suppliers', { ...filters, page: suppliers.current_page - 1 })">
                        Previous
                    </Button>
                    <Button v-if="suppliers.current_page < suppliers.last_page" variant="outline" size="sm"
                        @click="router.get('/purchasing/suppliers', { ...filters, page: suppliers.current_page + 1 })">
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Add / Edit Modal -->
    <Dialog :open="showModal" @update:open="showModal = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editTarget ? 'Edit Supplier' : 'Add Supplier' }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="save" class="grid grid-cols-2 gap-4 mt-2">
                <div class="col-span-2">
                    <Label>Name *</Label>
                    <Input v-model="form.name" required class="mt-1" placeholder="e.g. Ali Traders" />
                    <p v-if="form.errors.name" class="text-destructive text-xs mt-1">{{ form.errors.name }}</p>
                </div>
                <div>
                    <Label>Company</Label>
                    <Input v-model="form.company" class="mt-1" placeholder="Optional" />
                </div>
                <div>
                    <Label>City</Label>
                    <Input v-model="form.city" class="mt-1" placeholder="Lahore" />
                </div>
                <div>
                    <Label>Phone</Label>
                    <Input v-model="form.phone" class="mt-1" placeholder="0300-1234567" />
                </div>
                <div>
                    <Label>Email</Label>
                    <Input v-model="form.email" type="email" class="mt-1" />
                </div>
                <div>
                    <Label>NTN</Label>
                    <Input v-model="form.ntn" class="mt-1" placeholder="Tax number" />
                </div>
                <div>
                    <Label>Payment Terms (days)</Label>
                    <Input v-model.number="form.payment_terms" type="number" min="0" class="mt-1" />
                </div>
                <div v-if="!editTarget" class="col-span-2">
                    <Label>Opening Balance (Rs)</Label>
                    <Input v-model.number="form.opening_balance" type="number" min="0" step="0.01" class="mt-1" />
                    <p class="text-muted-foreground text-xs mt-1">Amount already owed to this supplier before today</p>
                </div>
                <div class="col-span-2">
                    <Label>Address</Label>
                    <Input v-model="form.address" class="mt-1" />
                </div>
                <div class="col-span-2">
                    <Label>Notes</Label>
                    <Input v-model="form.notes" class="mt-1" />
                </div>
                <DialogFooter class="col-span-2 pt-2">
                    <Button type="button" variant="outline" @click="showModal = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ editTarget ? 'Save Changes' : 'Add Supplier' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
