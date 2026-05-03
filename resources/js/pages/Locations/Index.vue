<script setup lang="ts">
import SearchableSelect, { type SearchableOption } from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface CityLite {
    id: number;
    name: string;
    name_ur?: string | null;
    province: string;
}

interface AreaRow {
    id: number;
    name: string;
    city_id: number;
    city_name: string;
    city_name_ur?: string | null;
    province: string;
}

const props = defineProps<{
    cities: CityLite[];
    areas: AreaRow[];
    provinces: string[];
    filters: { province?: string; search?: string };
}>();

const { t, locale } = useI18n();
const { confirm } = useConfirm();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('locations.pageTitle'), href: route('locations.index') },
]);

const provinceFilter = ref(props.filters.province ?? '');
const search = ref(props.filters.search ?? '');
let filterTimer: ReturnType<typeof setTimeout>;

watch([provinceFilter, search], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(
            route('locations.index'),
            {
                province: provinceFilter.value || undefined,
                search: search.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 320);
});

const cityOptions = computed<SearchableOption[]>(() =>
    props.cities.map((c) => ({
        value: c.id,
        label: locale.value === 'ur' && c.name_ur ? c.name_ur : c.name,
        subtitle: c.province,
        keywords: [c.name, c.name_ur ?? '', c.province].filter(Boolean).join(' '),
    })),
);

function displayCityName(row: AreaRow): string {
    return locale.value === 'ur' && row.city_name_ur ? row.city_name_ur : row.city_name;
}

const modalOpen = ref(false);
const editTarget = ref<AreaRow | null>(null);

const areaForm = useForm({
    city_id: null as number | null,
    name: '',
});

const editAreaForm = useForm({
    name: '',
});

function openCreate() {
    editTarget.value = null;
    areaForm.reset();
    areaForm.city_id = null;
    areaForm.clearErrors();
    editAreaForm.reset();
    editAreaForm.clearErrors();
    modalOpen.value = true;
}

function openEdit(row: AreaRow) {
    editTarget.value = row;
    areaForm.city_id = row.city_id;
    areaForm.name = row.name;
    areaForm.clearErrors();
    editAreaForm.name = row.name;
    editAreaForm.clearErrors();
    modalOpen.value = true;
}

function submitArea() {
    if (editTarget.value) {
        editAreaForm.patch(route('locations.areas.update', editTarget.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                editAreaForm.reset();
                areaForm.reset();
                editTarget.value = null;
            },
        });
    } else {
        areaForm.post(route('locations.areas.store'), {
            preserveScroll: true,
            onSuccess: () => {
                modalOpen.value = false;
                areaForm.reset();
            },
        });
    }
}

async function removeArea(row: AreaRow) {
    const ok = await confirm({
        title: t('locations.deleteAreaTitle'),
        message: t('locations.deleteAreaMessage', { area: row.name }),
        confirmLabel: t('common.delete'),
        cancelLabel: t('common.cancel'),
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(route('locations.areas.destroy', row.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('locations.pageTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('locations.pageTitle') }}</h1>
                    <p class="text-sm text-muted-foreground mt-1 max-w-xl">{{ t('locations.pageSubtitle') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline" class="gap-2">
                        <Link :href="route('customers.create')">{{ t('locations.gotoCustomersHint') }}</Link>
                    </Button>
                    <Button class="gap-2" @click="openCreate">
                        <Plus class="size-4" /> {{ t('locations.addArea') }}
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="min-w-[10rem] space-y-1.5 flex-1 sm:max-w-[14rem]">
                    <Label class="text-xs uppercase tracking-wide text-muted-foreground">{{ t('locations.filterProvince') }}</Label>
                    <select
                        v-model="provinceFilter"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm"
                    >
                        <option value="">{{ t('locations.allProvinces') }}</option>
                        <option v-for="prov in provinces" :key="prov" :value="prov">{{ prov }}</option>
                    </select>
                </div>
                <div class="min-w-[12rem] space-y-1.5 flex-[2]">
                    <Label class="text-xs uppercase tracking-wide text-muted-foreground">{{ t('locations.searchAreas') }}</Label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" class="ps-9 h-11" :placeholder="t('locations.searchAreasPlaceholder')" />
                    </div>
                </div>
            </div>

            <!-- Areas table -->
            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="divide-y divide-border">
                    <p v-if="areas.length === 0" class="py-14 text-center text-sm text-muted-foreground px-6">
                        {{ t('locations.noAreasYet') }}
                    </p>
                    <div
                        v-for="row in areas"
                        :key="row.id"
                        class="flex flex-wrap items-start gap-3 px-4 py-3 hover:bg-muted/30 transition-colors"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <MapPin class="size-5" aria-hidden />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-foreground">{{ row.name }}</p>
                            <p class="text-xs text-muted-foreground mt-0.5">
                                {{ displayCityName(row) }} · <span class="tabular-nums">{{ row.province }}</span>
                            </p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <Button variant="ghost" size="icon" :title="t('common.edit')" @click="openEdit(row)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :title="t('common.delete')"
                                @click="removeArea(row)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <Dialog :open="modalOpen" @update:open="modalOpen = $event">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ editTarget ? t('locations.editArea') : t('locations.addArea') }}
                </DialogTitle>
            </DialogHeader>

            <form class="grid gap-4 pt-1" @submit.prevent="submitArea">
                <div class="space-y-2">
                    <Label for="modal-city">{{ t('locations.cityPick') }}</Label>
                    <SearchableSelect
                        button-id="locations-modal-city"
                        v-model="areaForm.city_id"
                        :options="cityOptions"
                        :placeholder="t('locations.cityPickPlaceholder')"
                        :search-placeholder="t('locations.searchCities')"
                        class="[&_[data-searchable-trigger]]:w-full"
                        :disabled="editTarget !== null"
                    />
                    <p class="text-xs text-muted-foreground">{{ t('locations.cityPickHint') }}</p>
                    <p v-if="areaForm.errors.city_id" class="text-xs text-destructive">{{ areaForm.errors.city_id }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="modal-area">{{ t('locations.areaName') }}</Label>
                    <Input
                        v-if="editTarget"
                        id="modal-area-edit"
                        v-model="editAreaForm.name"
                        :placeholder="t('locations.areaNamePlaceholder')"
                    />
                    <Input
                        v-else
                        id="modal-area-create"
                        v-model="areaForm.name"
                        :placeholder="t('locations.areaNamePlaceholder')"
                    />
                    <p v-if="editTarget && editAreaForm.errors.name" class="text-xs text-destructive">
                        {{ editAreaForm.errors.name }}
                    </p>
                    <p v-if="!editTarget && areaForm.errors.name" class="text-xs text-destructive">
                        {{ areaForm.errors.name }}
                    </p>
                </div>
                <DialogFooter class="gap-2 sm:justify-end pt-2">
                    <Button type="button" variant="outline" @click="modalOpen = false">{{ t('common.cancel') }}</Button>
                    <Button type="submit" :disabled="editTarget ? editAreaForm.processing : areaForm.processing">
                        {{ editTarget ? t('common.saveChanges') : t('locations.addArea') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
