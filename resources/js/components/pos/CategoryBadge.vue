<script setup lang="ts">
defineProps<{
    name: string;
    color?: string;
    size?: 'sm' | 'md';
}>();
</script>

<template>
    <span
        :style="{ backgroundColor: (color ?? '#6366f1') + '20', color: color ?? '#6366f1', borderColor: (color ?? '#6366f1') + '40' }"
        :class="[
            'inline-flex items-center rounded-full border font-medium',
            size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
        ]"
    >
        {{ name }}
    </span>
</template>
