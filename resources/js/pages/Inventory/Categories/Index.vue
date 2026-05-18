<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit, FolderOpen, Layers, Package, Plus, Search, Tag, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { confirm } = useConfirm();

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
    filters: { search?: string; status?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.inventory'), href: '/inventory/products' },
    { title: t('nav.categories'), href: '/inventory/categories' },
];

// ── Filters ───────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});
watch(statusFilter, () => applyFilters());

function applyFilters() {
    router.get(route('inventory.categories.index'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

// ── Stats ─────────────────────────────────────────────────────
const totalActive   = computed(() => props.categories.filter(c => c.is_active).length);
const totalInactive = computed(() => props.categories.filter(c => !c.is_active).length);
const totalProducts = computed(() => props.categories.reduce((s, c) => s + c.products_count, 0));

// ── Add / Edit modal ──────────────────────────────────────────
const showModal  = ref(false);
const editTarget = ref<Category | null>(null);

const PRESET_COLORS = [
    '#6366f1', '#8b5cf6', '#ec4899', '#ef4444',
    '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#06b6d4', '#3b82f6', '#64748b', '#1e293b',
];

const form = useForm({
    name:       '',
    color:      '#6366f1',
    sort_order: 0,
    is_active:  true,
});

function openCreate() {
    editTarget.value = null;
    form.reset();
    form.color = '#6366f1';
    form.sort_order = 0;
    form.is_active = true;
    showModal.value = true;
}

function openEdit(cat: Category) {
    editTarget.value = cat;
    form.name       = cat.name;
    form.color      = cat.color;
    form.sort_order = cat.sort_order;
    form.is_active  = cat.is_active;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    form.clearErrors();
}

function submit() {
    if (editTarget.value) {
        form.patch(route('inventory.categories.update', editTarget.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('inventory.categories.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

// ── Delete ────────────────────────────────────────────────────
async function deleteCategory(cat: Category) {
    const ok = await confirm({
        title:   t('categories.deleteTitle'),
        message: t('categories.deleteMessage', { name: cat.name }),
        confirmText: t('common.delete'),
        variant: 'destructive',
    });
    if (!ok) return;
    router.delete(route('inventory.categories.destroy', cat.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('nav.categories')" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- ── Header ─────────────────────────────────────────── -->
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ t('nav.categories') }}</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ t('categories.description') }}</p>
                </div>
                <Button @click="openCreate" class="gap-2">
                    <Plus class="h-4 w-4" />
                    {{ t('categories.add') }}
                </Button>
            </div>

            <!-- ── Stats cards ──────────────────────────────────── -->
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10">
                            <Layers class="h-4 w-4 text-primary" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">{{ t('categories.totalCategories') }}</p>
                            <p class="text-xl font-bold text-foreground">{{ categories.length }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-500/10">
                            <Tag class="h-4 w-4 text-green-600" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">{{ t('common.active') }}</p>
                            <p class="text-xl font-bold text-foreground">{{ totalActive }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10">
                            <Package class="h-4 w-4 text-blue-600" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">{{ t('categories.totalProducts') }}</p>
                            <p class="text-xl font-bold text-foreground">{{ totalProducts }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Filters ──────────────────────────────────────── -->
            <div class="flex items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" :placeholder="t('categories.searchPh')" class="pl-9" />
                </div>
                <select
                    v-model="statusFilter"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                >
                    <option value="">{{ t('common.allStatus') }}</option>
                    <option value="active">{{ t('common.active') }}</option>
                    <option value="inactive">{{ t('common.inactive') }}</option>
                </select>
            </div>

            <!-- ── Category grid ────────────────────────────────── -->
            <div v-if="categories.length > 0" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                <div
                    v-for="cat in categories"
                    :key="cat.id"
                    class="group relative flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-shadow hover:shadow-md"
                >
                    <!-- Color swatch + name -->
                    <div class="flex items-center gap-3">
                        <span
                            class="h-8 w-8 shrink-0 rounded-lg shadow-sm"
                            :style="{ backgroundColor: cat.color }"
                        />
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-foreground">{{ cat.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ cat.slug }}</p>
                        </div>
                    </div>

                    <!-- Product count + status -->
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1 text-muted-foreground">
                            <Package class="h-3.5 w-3.5" />
                            {{ cat.products_count }} {{ t('categories.products') }}
                        </span>
                        <span :class="[
                            'rounded-full px-2 py-0.5 font-medium',
                            cat.is_active
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-muted text-muted-foreground'
                        ]">
                            {{ cat.is_active ? t('common.active') : t('common.inactive') }}
                        </span>
                    </div>

                    <!-- Sort order badge -->
                    <div class="text-xs text-muted-foreground">
                        {{ t('categories.sortOrder') }}: {{ cat.sort_order }}
                    </div>

                    <!-- Hover actions -->
                    <div class="absolute inset-x-0 bottom-0 flex translate-y-1 items-center justify-end gap-1 rounded-b-xl bg-gradient-to-t from-card px-3 pb-3 pt-6 opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        <button
                            @click="openEdit(cat)"
                            class="flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background text-muted-foreground shadow-sm hover:text-foreground"
                            :title="t('common.edit')"
                        >
                            <Edit class="h-3.5 w-3.5" />
                        </button>
                        <button
                            @click="deleteCategory(cat)"
                            class="flex h-7 w-7 items-center justify-center rounded-md border border-destructive/40 bg-background text-destructive/60 shadow-sm hover:text-destructive"
                            :title="t('common.delete')"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Empty state ──────────────────────────────────── -->
            <div v-else class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border py-16 text-center">
                <FolderOpen class="h-10 w-10 text-muted-foreground/40" />
                <p class="mt-3 font-medium text-foreground">{{ t('categories.empty') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('categories.emptyHint') }}</p>
                <Button class="mt-4 gap-2" @click="openCreate">
                    <Plus class="h-4 w-4" />
                    {{ t('categories.add') }}
                </Button>
            </div>
        </div>

        <!-- ── Add / Edit Dialog ───────────────────────────────── -->
        <Dialog :open="showModal" @update:open="(v) => { if (!v) closeModal(); }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editTarget ? t('categories.editTitle') : t('categories.addTitle') }}
                    </DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submit" class="space-y-4 py-2">

                    <!-- Name -->
                    <div class="space-y-1.5">
                        <Label for="cat-name">{{ t('common.name') }} <span class="text-destructive">*</span></Label>
                        <Input
                            id="cat-name"
                            v-model="form.name"
                            :placeholder="t('categories.namePh')"
                            autofocus
                        />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <!-- Color -->
                    <div class="space-y-1.5">
                        <Label>{{ t('categories.color') }} <span class="text-destructive">*</span></Label>
                        <!-- Preset swatches -->
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="c in PRESET_COLORS"
                                :key="c"
                                type="button"
                                @click="form.color = c"
                                class="h-7 w-7 rounded-md shadow-sm ring-offset-background transition-all hover:scale-110 focus:outline-none"
                                :style="{ backgroundColor: c }"
                                :class="form.color === c ? 'ring-2 ring-ring ring-offset-2' : ''"
                            />
                        </div>
                        <!-- Custom hex input -->
                        <div class="flex items-center gap-2 mt-1">
                            <span
                                class="h-8 w-8 shrink-0 rounded-md border border-border shadow-sm"
                                :style="{ backgroundColor: form.color }"
                            />
                            <Input
                                v-model="form.color"
                                placeholder="#6366f1"
                                class="font-mono text-sm"
                                maxlength="7"
                            />
                        </div>
                        <p v-if="form.errors.color" class="text-xs text-destructive">{{ form.errors.color }}</p>
                    </div>

                    <!-- Sort order -->
                    <div class="space-y-1.5">
                        <Label for="cat-sort">{{ t('categories.sortOrder') }}</Label>
                        <Input
                            id="cat-sort"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            :placeholder="t('categories.sortOrderHint')"
                        />
                        <p class="text-xs text-muted-foreground">{{ t('categories.sortOrderHint') }}</p>
                    </div>

                    <!-- Active toggle -->
                    <div class="flex items-center gap-3 rounded-lg border border-border p-3">
                        <input
                            id="cat-active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-input accent-primary"
                        />
                        <div>
                            <Label for="cat-active" class="cursor-pointer font-medium">{{ t('categories.activeLabel') }}</Label>
                            <p class="text-xs text-muted-foreground">{{ t('categories.activeHint') }}</p>
                        </div>
                    </div>

                </form>

                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="closeModal">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button @click="submit" :disabled="form.processing">
                        {{ form.processing ? t('common.saving') : t('common.save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
