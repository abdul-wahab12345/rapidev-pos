<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useConfirm } from '@/composables/useConfirm';
import { AlertTriangle, Info, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const { open, options, accept, cancel } = useConfirm();

const variantConfig = computed(() => {
    switch (options.value.variant) {
        case 'danger':
            return {
                icon: ShieldAlert,
                iconClass: 'text-destructive',
                iconBg: 'bg-destructive/10',
                btnClass: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
            };
        case 'warning':
            return {
                icon: AlertTriangle,
                iconClass: 'text-amber-600 dark:text-amber-400',
                iconBg: 'bg-amber-100 dark:bg-amber-900/30',
                btnClass: 'bg-amber-600 text-white hover:bg-amber-500',
            };
        default:
            return {
                icon: Info,
                iconClass: 'text-primary',
                iconBg: 'bg-primary/10',
                btnClass: 'bg-primary text-primary-foreground hover:bg-primary/90',
            };
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(v) => { if (!v) cancel(); }">
        <DialogContent class="max-w-md" @interact-outside.prevent>
            <DialogHeader>
                <div class="flex items-start gap-4">
                    <!-- Icon -->
                    <div :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-full', variantConfig.iconBg]">
                        <component :is="variantConfig.icon" :class="['h-5 w-5', variantConfig.iconClass]" />
                    </div>

                    <div class="flex-1 pt-0.5">
                        <DialogTitle class="text-base font-semibold">
                            {{ options.title }}
                        </DialogTitle>
                        <DialogDescription class="mt-1 text-sm text-muted-foreground">
                            {{ options.message }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <DialogFooter class="mt-2 flex gap-2 sm:justify-end">
                <button
                    @click="cancel"
                    class="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-accent transition-colors sm:flex-none"
                >
                    {{ options.cancelLabel }}
                </button>
                <button
                    @click="accept"
                    :class="['flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors sm:flex-none', variantConfig.btnClass]"
                >
                    {{ options.confirmLabel }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
