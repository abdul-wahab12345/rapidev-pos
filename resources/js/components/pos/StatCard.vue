<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string | number;
    tone?: 'default' | 'success' | 'warning' | 'danger' | 'info';
    icon?: Component;
    description?: string;
}>();
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-4">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">{{ label }}</p>
                <p
                    :class="[
                        'mt-1 text-2xl font-bold tabular-nums',
                        tone === 'success' ? 'text-emerald-600 dark:text-emerald-400' :
                        tone === 'warning' ? 'text-amber-600 dark:text-amber-400' :
                        tone === 'danger'  ? 'text-red-600 dark:text-red-400' :
                        tone === 'info'    ? 'text-blue-600 dark:text-blue-400' :
                        'text-foreground',
                    ]"
                >
                    {{ value }}
                </p>
                <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">{{ description }}</p>
            </div>
            <div
                v-if="icon"
                :class="[
                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                    tone === 'success' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
                    tone === 'warning' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' :
                    tone === 'danger'  ? 'bg-red-500/10 text-red-600 dark:text-red-400' :
                    tone === 'info'    ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' :
                    'bg-muted text-muted-foreground',
                ]"
            >
                <component :is="icon" class="h-4.5 w-4.5" />
            </div>
        </div>
    </div>
</template>
