<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { PrimitiveProps } from 'radix-vue';
import { Primitive } from 'radix-vue';
import type { HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<
        PrimitiveProps & {
            size?: 'sm' | 'md';
            isActive?: boolean;
            class?: HTMLAttributes['class'];
        }
    >(),
    {
        as: 'a',
        size: 'md',
    },
);
</script>

<template>
    <Primitive
        data-sidebar="menu-sub-button"
        :as="as"
        :as-child="asChild"
        :data-size="size"
        :data-active="isActive"
        :class="
            cn(
                'flex min-h-11 min-w-0 items-start gap-2 rounded-md px-2 py-2 text-sidebar-foreground ltr:-translate-x-px rtl:translate-x-px outline-none ring-sidebar-ring',
                'overflow-x-clip overflow-y-visible',
                'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground',
                'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
                '[&>svg]:size-4 [&>svg]:shrink-0 [&>svg]:self-center [&>svg]:text-sidebar-accent-foreground',
                '[&_[data-sidebar-nav-title]]:min-w-0 [&_[data-sidebar-nav-title]]:flex-1 [&_[data-sidebar-nav-title]]:leading-snug [&_[data-sidebar-nav-title]]:break-words',
                'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground',
                size === 'sm' && 'text-xs py-1.5 min-h-10',
                size === 'md' && 'text-sm',
                'group-data-[collapsible=icon]:hidden',
                props.class,
            )
        "
    >
        <slot />
    </Primitive>
</template>
