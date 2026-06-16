<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit, Layers, Plus, Search, Tag, Trash2, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Category {
    id: string;
    name: string;
    slug: string;
    color: string;
    sort_order: number;
    is_active: boolean;
    products_count: number;
}

const props = defineProps<{
    categories: Category[];
    filters: { search?: string };
}>();

const { confirm } = useConfirm();

const search = ref(props.filters.search ?? '');
function applySearch() {
    router.get(route('inventory.categories.index'), { search: search.value }, { preserveState: true, replace: true });
}

// ── Modal state ─────────────────────────────────────────────────
const showModal = ref(false);
const editTarget = ref<Category | null>(null);

const PRESET_COLORS = [
    '#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316',
    '#eab308', '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6',
    '#64748b', '#78716c',
];

const form = useForm({
    name: '',
    color: '#6366f1',
    sort_order: 0,
    is_active: true,
});

function openCreate() {
    editTarget.value = null;
    form.reset();
    form.color = '#6366f1';
    form.is_active = true;
    showModal.value = true;
}

function openEdit(cat: Category) {
    editTarget.value = cat;
    form.name = cat.name;
    form.color = cat.color;
    form.sort_order = cat.sort_order;
    form.is_active = cat.is_active;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editTarget.value = null;
    form.reset();
}

function submit() {
    if (editTarget.value) {
        form.patch(route('inventory.categories.update', editTarget.value.id), {
            onSuccess: closeModal,
        });
    } else {
        form.post(route('inventory.categories.store'), {
            onSuccess: closeModal,
        });
    }
}

async function deleteCategory(cat: Category) {
    if (await confirm({
        title: 'Delete Category',
        message: cat.products_count > 0
            ? `"${cat.name}" has ${cat.products_count} product(s). You must reassign them before deleting.`
            : `Delete "${cat.name}"? This cannot be undone.`,
        confirmText: 'Delete',
        variant: 'destructive',
    })) {
        router.delete(route('inventory.categories.destroy', cat.id), { preserveScroll: true });
    }
}

const activeCount = computed(() => props.categories.filter(c => c.is_active).length);
const totalProducts = computed(() => props.categories.reduce((s, c) => s + c.products_count, 0));
</script>

<template>
    <Head title="Categories" />
    <AppLayout :breadcrumbs="[{ title: 'Inventory', href: '/inventory/products' }, { title: 'Categories' }]">
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Categories</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Organise products into groups for the POS and reports</p>
                </div>
                <button @click="openCreate"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors">
                    <Plus class="h-4 w-4" /> New Category
                </button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-xl border border-border bg-card p-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                        <Tag class="h-4 w-4 text-primary" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Total</p>
                        <p class="text-xl font-bold">{{ categories.length }}</p>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10">
                        <Tag class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Active</p>
                        <p class="text-xl font-bold">{{ activeCount }}</p>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10">
                        <Layers class="h-4 w-4 text-blue-500" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Products</p>
                        <p class="text-xl font-bold">{{ totalProducts }}</p>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="relative w-72">
                <Search class="absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input v-model="search" @keydown.enter="applySearch" type="text"
                    placeholder="Search categories..."
                    class="w-full rounded-lg border border-input bg-background ps-9 pe-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
            </div>

            <!-- Empty state -->
            <div v-if="categories.length === 0" class="rounded-xl border border-dashed border-border bg-card p-12 text-center">
                <Tag class="mx-auto h-10 w-10 text-muted-foreground/40 mb-3" />
                <p class="text-sm font-medium text-muted-foreground">No categories yet</p>
                <p class="text-xs text-muted-foreground mt-1">Create your first category to organise products</p>
                <button @click="openCreate"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors">
                    <Plus class="h-4 w-4" /> New Category
                </button>
            </div>

            <!-- Categories grid -->
            <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div v-for="cat in categories" :key="cat.id"
                    class="group relative rounded-xl border border-border bg-card p-4 hover:border-foreground/20 transition-colors">

                    <!-- Color bar -->
                    <div class="absolute inset-x-0 top-0 h-1 rounded-t-xl" :style="{ background: cat.color }"></div>

                    <div class="mt-1 flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <!-- Color swatch -->
                            <div class="h-8 w-8 shrink-0 rounded-lg border border-black/10"
                                :style="{ background: cat.color }"></div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm truncate">{{ cat.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ cat.products_count }} product{{ cat.products_count !== 1 ? 's' : '' }}</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                            <button @click="openEdit(cat)" title="Edit"
                                class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors">
                                <Edit class="h-3.5 w-3.5" />
                            </button>
                            <button @click="deleteCategory(cat)" title="Delete"
                                class="rounded-md p-1.5 text-muted-foreground hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 transition-colors">
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <span class="font-mono text-xs text-muted-foreground">{{ cat.slug }}</span>
                        <span :class="[
                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                            cat.is_active
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                        ]">{{ cat.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            @click.self="closeModal">
            <div class="w-full max-w-md rounded-2xl border border-border bg-card shadow-2xl">

                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h2 class="text-base font-bold">{{ editTarget ? 'Edit Category' : 'New Category' }}</h2>
                    <button @click="closeModal" class="text-muted-foreground hover:text-foreground transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-5">

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Name <span class="text-destructive">*</span></label>
                        <input v-model="form.name" type="text" placeholder="e.g. Marble, Ceramic, Border..."
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            :class="form.errors.name ? 'border-destructive' : ''" autofocus />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <!-- Color -->
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Color</label>
                        <!-- Preset swatches -->
                        <div class="flex flex-wrap gap-2 mb-2">
                            <button v-for="c in PRESET_COLORS" :key="c" type="button"
                                @click="form.color = c"
                                :style="{ background: c }"
                                :class="['h-7 w-7 rounded-full border-2 transition-all', form.color === c ? 'border-foreground scale-110' : 'border-transparent hover:scale-105']"
                                :title="c" />
                        </div>
                        <!-- Custom hex input -->
                        <div class="flex items-center gap-2">
                            <div class="h-8 w-8 shrink-0 rounded-lg border border-input" :style="{ background: form.color }"></div>
                            <input v-model="form.color" type="text" placeholder="#6366f1" maxlength="7"
                                class="w-32 rounded-lg border border-input bg-background px-3 py-1.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="form.errors.color ? 'border-destructive' : ''" />
                            <input v-model="form.color" type="color"
                                class="h-8 w-10 cursor-pointer rounded border border-input bg-transparent p-0.5" />
                        </div>
                        <p v-if="form.errors.color" class="mt-1 text-xs text-destructive">{{ form.errors.color }}</p>
                    </div>

                    <!-- Sort order + Active -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Sort Order</label>
                            <input v-model.number="form.sort_order" type="number" min="0" placeholder="0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                        </div>
                        <div class="flex flex-col justify-end">
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <div @click="form.is_active = !form.is_active"
                                    :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors cursor-pointer', form.is_active ? 'bg-primary' : 'bg-muted']">
                                    <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', form.is_active ? 'translate-x-6' : 'translate-x-1']" />
                                </div>
                                <span class="text-sm font-medium">Active</span>
                            </label>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 pt-1">
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 rounded-xl bg-primary py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60">
                            {{ form.processing ? 'Saving...' : (editTarget ? 'Save Changes' : 'Create Category') }}
                        </button>
                        <button type="button" @click="closeModal"
                            class="rounded-xl border border-input px-5 py-2.5 text-sm hover:bg-muted transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
