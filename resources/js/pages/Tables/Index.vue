<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from '@/composables/useConfirm';
import { Plus, Pencil, Trash2, UtensilsCrossed, X, Check } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';

interface DiningTable {
    id: string;
    name: string;
    capacity: number;
    section: string | null;
}

const props = defineProps<{ tables: DiningTable[] }>();
const { t } = useI18n();
const { confirm } = useConfirm();

// ── Add / Edit ──────────────────────────────────────────────────
const showModal = ref(false);
const editingTable = ref<DiningTable | null>(null);

const form = useForm({ name: '', capacity: 4, section: '' });

function openAdd() {
    editingTable.value = null;
    form.reset();
    showModal.value = true;
}

function openEdit(table: DiningTable) {
    editingTable.value = table;
    form.name     = table.name;
    form.capacity = table.capacity;
    form.section  = table.section ?? '';
    showModal.value = true;
}

function submitForm() {
    if (editingTable.value) {
        form.patch(route('tables.update', editingTable.value.id), {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    } else {
        form.post(route('tables.store'), {
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

async function deleteTable(table: DiningTable) {
    const ok = await confirm({
        title: t('tables.deleteConfirmTitle'),
        message: t('tables.deleteConfirmMessage', { name: table.name }),
        confirmLabel: t('common.delete'),
        variant: 'destructive',
    });
    if (!ok) return;
    router.delete(route('tables.destroy', table.id));
}

// ── Group by section ────────────────────────────────────────────
const grouped = computed(() => {
    const map = new Map<string, DiningTable[]>();
    const noSection = t('tables.noSection');
    props.tables.forEach((tbl) => {
        const key = tbl.section ?? noSection;
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(tbl);
    });
    return map;
});
</script>

<template>
    <Head :title="t('tables.pageTitle')" />
    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold text-foreground">
                        <UtensilsCrossed class="h-6 w-6 text-primary" />
                        {{ t('tables.pageTitle') }}
                    </h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ t('tables.pageDescription') }}</p>
                </div>
                <Button @click="openAdd" class="gap-2">
                    <Plus :size="16" /> {{ t('tables.addTable') }}
                </Button>
            </div>

            <!-- Empty state -->
            <div v-if="tables.length === 0" class="rounded-2xl border border-dashed border-border p-16 text-center">
                <UtensilsCrossed class="mx-auto mb-3 h-10 w-10 text-muted-foreground/40" />
                <p class="font-medium text-foreground">{{ t('tables.empty') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('tables.emptyHint') }}</p>
                <Button @click="openAdd" class="mt-4 gap-2" variant="outline">
                    <Plus :size="15" /> {{ t('tables.addTable') }}
                </Button>
            </div>

            <!-- Grouped tables -->
            <template v-for="[section, sectionTables] in grouped" :key="section">
                <div>
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ section }}
                    </h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                        <div
                            v-for="table in sectionTables"
                            :key="table.id"
                            class="group relative flex flex-col items-center justify-center gap-1 rounded-2xl border border-border bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
                        >
                            <UtensilsCrossed class="h-8 w-8 text-primary/70" />
                            <span class="mt-1 text-base font-bold text-foreground">{{ table.name }}</span>
                            <span class="text-xs text-muted-foreground">{{ t('tables.seatsLabel', { n: table.capacity }) }}</span>

                            <!-- Action buttons (show on hover) -->
                            <div class="absolute end-2 top-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                <button
                                    @click="openEdit(table)"
                                    class="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                >
                                    <Pencil :size="13" />
                                </button>
                                <button
                                    @click="deleteTable(table)"
                                    class="rounded-md p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                >
                                    <Trash2 :size="13" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>

    <!-- Add / Edit Modal -->
    <Dialog :open="showModal" @update:open="showModal = $event">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ editingTable ? t('tables.editTable') : t('tables.addTable') }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitForm" class="flex flex-col gap-4 mt-2">
                <div>
                    <Label>{{ t('tables.tableName') }} <span class="text-destructive">*</span></Label>
                    <Input v-model="form.name" class="mt-1" :placeholder="t('tables.tableNamePh')" required />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div>
                    <Label>{{ t('tables.capacity') }}</Label>
                    <Input v-model.number="form.capacity" type="number" min="1" max="100" class="mt-1" />
                </div>
                <div>
                    <Label>{{ t('tables.section') }}</Label>
                    <Input v-model="form.section" class="mt-1" :placeholder="t('tables.sectionPh')" />
                    <p class="mt-1 text-xs text-muted-foreground">{{ t('tables.sectionHint') }}</p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showModal = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing" class="gap-2">
                        <Check :size="15" />
                        {{ editingTable ? t('common.save') : t('tables.addTable') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
