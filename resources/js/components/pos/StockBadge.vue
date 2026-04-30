<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    quantity: number;
    reorderLevel?: number;
}>();

const level = computed(() => {
    if (props.quantity === 0) return 'out';
    if (props.quantity <= (props.reorderLevel ?? 5)) return 'low';
    return 'ok';
});

const classes = computed(() => ({
    'bg-red-500/10 text-red-500 border-red-500/20': level.value === 'out',
    'bg-amber-500/10 text-amber-500 border-amber-500/20': level.value === 'low',
    'bg-emerald-500/10 text-emerald-600 border-emerald-500/20': level.value === 'ok',
}));

const label = computed(() => {
    if (level.value === 'out') return 'Out of stock';
    if (level.value === 'low') return `Low (${props.quantity})`;
    return String(props.quantity);
});
</script>

<template>
    <span :class="['inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium', classes]">
        <span
            :class="[
                'h-1.5 w-1.5 rounded-full',
                level === 'out' ? 'bg-red-500' : level === 'low' ? 'bg-amber-500' : 'bg-emerald-500',
            ]"
        ></span>
        {{ label }}
    </span>
</template>
