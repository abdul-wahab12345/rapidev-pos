<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ class?: string }>();

const { t } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light' as const, Icon: Sun, labelKey: 'settings.appearanceLight' as const },
    { value: 'dark' as const, Icon: Moon, labelKey: 'settings.appearanceDark' as const },
    { value: 'system' as const, Icon: Monitor, labelKey: 'settings.appearanceSystem' as const },
];

const CurrentIcon = computed(() => options.find((o) => o.value === appearance.value)?.Icon ?? Monitor);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="icon"
                :class="cn('h-8 w-8 shrink-0 border-input bg-muted/40', props.class)"
                dir="ltr"
                :title="t('settings.appearanceTitle')"
                :aria-label="t('settings.appearanceTitle')"
            >
                <component :is="CurrentIcon" class="h-4 w-4" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem
                v-for="opt in options"
                :key="opt.value"
                class="gap-2"
                dir="ltr"
                @click="updateAppearance(opt.value)"
            >
                <component :is="opt.Icon" class="h-4 w-4 shrink-0 text-muted-foreground" />
                <span :class="appearance === opt.value ? 'font-semibold text-foreground' : ''">{{ t(opt.labelKey) }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
