<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const FALLBACK_BRAND = 'RapiDev POS';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage();

const brandLabel = computed(
    () => page.props.tenant?.name?.trim() || FALLBACK_BRAND,
);
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ms-1 grid flex-1 text-start text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ brandLabel }}</span>
    </div>
</template>
