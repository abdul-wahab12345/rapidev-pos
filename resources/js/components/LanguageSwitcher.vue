<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Languages } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ class?: string }>();
const { t } = useI18n();
const page = usePage<SharedData>();
const tenant = computed(() => page.props.tenant);
const current = computed(() => (tenant.value?.settings?.language === 'ur' ? 'ur' : 'en') as 'en' | 'ur');
const busy = ref(false);

function switchTo(code: 'en' | 'ur') {
    if (!tenant.value || busy.value || code === current.value) {
        return;
    }

    busy.value = true;

    router.post(
        route('business-settings.language'),
        { language: code },
        {
            preserveScroll: true,
            onSuccess: () => {
                window.location.reload();
            },
            onError: () => {
                busy.value = false;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
</script>

<template>
    <div v-if="tenant" role="toolbar" :aria-label="t('layout.language')" :class="cn('flex items-center gap-1.5', props.class)">
        <Languages class="hidden h-3.5 w-3.5 text-muted-foreground sm:block" aria-hidden="true" />
        <div class="inline-flex items-center rounded-md border border-input bg-muted/40 p-0.5 text-xs font-medium" dir="ltr">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="busy"
                :aria-pressed="current === 'en'"
                :aria-label="t('layout.languageEnglish')"
                :class="
                    cn(
                        'h-7 min-w-11 rounded-sm px-2 py-1 text-[11px] font-semibold',
                        current === 'en' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    )
                "
                @click="switchTo('en')"
            >
                EN
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="busy"
                :aria-pressed="current === 'ur'"
                :aria-label="t('layout.languageUrdu')"
                :class="
                    cn(
                        'h-7 min-w-11 rounded-sm px-2 py-1 text-[11px] font-semibold',
                        current === 'ur' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    )
                "
                @click="switchTo('ur')"
            >
                اردو
            </Button>
        </div>
    </div>
</template>
