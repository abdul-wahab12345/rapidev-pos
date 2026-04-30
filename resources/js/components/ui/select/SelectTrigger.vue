<script setup lang="ts">
import { cn } from '@/lib/utils';
import { SelectIcon, SelectTrigger, type SelectTriggerProps, useForwardProps } from 'radix-vue';
import { ChevronDown } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';

const props = defineProps<SelectTriggerProps & { class?: HTMLAttributes['class'] }>();
const forwarded = useForwardProps(props);
</script>

<template>
    <SelectTrigger
        v-bind="forwarded"
        :class="cn(
            'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
            props.class,
        )"
    >
        <slot />
        <SelectIcon as-child>
            <ChevronDown class="h-4 w-4 opacity-50" />
        </SelectIcon>
    </SelectTrigger>
</template>
