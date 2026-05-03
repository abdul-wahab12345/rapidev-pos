<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ChevronRight, ListOrdered, Pencil, Plus, Tag, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useConfirm } from '@/composables/useConfirm';

const { t, locale } = useI18n();
const { confirm } = useConfirm();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('nav.sales'), href: route('sales.index') },
    { title: t('rateList.pageTitle'), href: route('rate-lists.index') },
]);

interface RateList {
    id: string;
    name: string;
    name_ur: string | null;
    description: string | null;
    is_active: boolean;
    items_count: number;
}

const props = defineProps<{
    rateLists: RateList[];
}>();

const showModal = ref(false);
const editTarget = ref<RateList | null>(null);

const form = useForm({
    name: '',
    name_ur: '',
    description: '',
});

function openCreate() {
    editTarget.value = null;
    form.reset();
    showModal.value = true;
}

function openEdit(rl: RateList) {
    editTarget.value = rl;
    form.name = rl.name;
    form.name_ur = rl.name_ur ?? '';
    form.description = rl.description ?? '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editTarget.value = null;
    form.reset();
}

function submit() {
    if (editTarget.value) {
        form.patch(route('rate-lists.update', editTarget.value.id), {
            onSuccess: closeModal,
        });
    } else {
        form.post(route('rate-lists.store'), {
            onSuccess: closeModal,
        });
    }
}

async function handleDelete(rl: RateList) {
    const displayName = locale.value === 'ur' && rl.name_ur ? rl.name_ur : rl.name;
    const ok = await confirm(t('rateList.deleteConfirmTitle', { name: displayName }), t('rateList.deleteConfirmMessage'));
    if (!ok) return;
    router.delete(route('rate-lists.destroy', rl.id));
}

function handleActivate(rl: RateList) {
    router.post(route('rate-lists.activate', rl.id));
}

function handleDeactivate(rl: RateList) {
    router.post(route('rate-lists.deactivate', rl.id));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('rateList.pageTitle')" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ t('rateList.pageTitle') }}</h1>
                    <p class="text-muted-foreground text-sm">{{ t('rateList.pageDescription') }}</p>
                </div>
                <Button @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ t('rateList.addRateList') }}
                </Button>
            </div>

            <!-- Empty state -->
            <div v-if="rateLists.length === 0" class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                <ListOrdered class="text-muted-foreground mb-3 h-10 w-10" />
                <p class="text-muted-foreground text-sm">{{ t('rateList.noRateListsYet') }}</p>
                <Button class="mt-4" size="sm" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ t('rateList.addRateList') }}
                </Button>
            </div>

            <!-- Rate list cards -->
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="rl in rateLists"
                    :key="rl.id"
                    class="bg-card flex flex-col rounded-lg border p-4 shadow-sm"
                    :class="{ 'border-primary ring-primary/20 ring-2': rl.is_active }"
                >
                    <!-- Name + active badge -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-semibold">
                                    {{ locale === 'ur' && rl.name_ur ? rl.name_ur : rl.name }}
                                </span>
                                <span v-if="rl.is_active" class="shrink-0 rounded-full bg-primary px-2 py-0.5 text-[10px] font-semibold text-primary-foreground">
                                    {{ t('common.active') }}
                                </span>
                            </div>
                            <p v-if="locale === 'ur' && rl.name_ur" class="text-muted-foreground mt-0.5 truncate text-xs">
                                {{ rl.name }}
                            </p>
                            <p v-if="rl.description" class="text-muted-foreground mt-1 text-sm">{{ rl.description }}</p>
                        </div>
                    </div>

                    <!-- Items count -->
                    <div class="text-muted-foreground mt-2 flex items-center gap-1 text-xs">
                        <Tag class="h-3.5 w-3.5" />
                        {{ t('rateList.itemsCount', { count: rl.items_count }) }}
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <!-- Activate / Deactivate toggle -->
                        <Button
                            v-if="!rl.is_active"
                            size="sm"
                            variant="outline"
                            @click="handleActivate(rl)"
                        >
                            <CheckCircle2 class="mr-1 h-3.5 w-3.5" />
                            {{ t('rateList.setActive') }}
                        </Button>
                        <Button
                            v-else
                            size="sm"
                            variant="secondary"
                            @click="handleDeactivate(rl)"
                        >
                            {{ t('rateList.deactivate') }}
                        </Button>

                        <!-- Manage prices -->
                        <Link :href="route('rate-lists.show', rl.id)" as="button">
                            <Button size="sm" variant="outline">
                                {{ t('rateList.managePrices') }}
                                <ChevronRight class="ml-1 h-3.5 w-3.5" />
                            </Button>
                        </Link>

                        <!-- Edit -->
                        <Button size="sm" variant="ghost" @click="openEdit(rl)">
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>

                        <!-- Delete -->
                        <Button size="sm" variant="ghost" class="text-destructive hover:text-destructive" @click="handleDelete(rl)">
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit modal -->
        <Dialog :open="showModal" @update:open="(v) => { if (!v) closeModal(); }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editTarget ? t('rateList.editTitle') : t('rateList.createTitle') }}
                    </DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-1">
                        <Label for="rl-name">{{ t('rateList.nameLabel') }} *</Label>
                        <Input
                            id="rl-name"
                            v-model="form.name"
                            :placeholder="t('rateList.namePlaceholder')"
                            required
                        />
                        <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-1">
                        <Label for="rl-name-ur">{{ t('rateList.nameUrLabel') }}</Label>
                        <Input
                            id="rl-name-ur"
                            v-model="form.name_ur"
                            :placeholder="t('rateList.nameUrPlaceholder')"
                            dir="rtl"
                        />
                    </div>

                    <div class="space-y-1">
                        <Label for="rl-desc">{{ t('common.descriptionOptional') }}</Label>
                        <Input
                            id="rl-desc"
                            v-model="form.description"
                            :placeholder="t('rateList.descriptionPlaceholder')"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeModal">{{ t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? t('common.saving') : t('common.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
