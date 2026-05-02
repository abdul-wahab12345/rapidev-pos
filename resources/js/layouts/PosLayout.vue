<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage<any>();
const tenant = page.props.tenant as { name: string } | null;
const auth = page.props.auth as { user: { name: string } };

const now = ref(new Date());
let clockInterval: ReturnType<typeof setInterval> | null = null;

const timeStr = () => now.value.toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' });
const dateStr = () => now.value.toLocaleDateString('en-PK', { weekday: 'short', day: 'numeric', month: 'short' });

onMounted(() => {
    clockInterval = setInterval(() => { now.value = new Date(); }, 30000);
});
onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
});
</script>

<template>
    <!-- Full-screen POS — no sidebar, uses the app's CSS variable theme -->
    <div class="flex h-screen flex-col overflow-hidden bg-background text-foreground subpixel-antialiased">

        <!-- Top Bar -->
        <header class="flex h-12 shrink-0 items-center gap-4 border-b border-border bg-card px-5">
            <div class="flex items-center gap-2.5">
                <div class="rounded-md bg-primary px-2.5 py-1">
                    <span class="text-xs font-bold tracking-widest text-primary-foreground">{{ t('layout.posBadge') }}</span>
                </div>
                <div class="h-4 w-px bg-border"></div>
                <span class="text-sm font-semibold text-foreground">{{ tenant?.name ?? 'Bithouse POS' }}</span>
            </div>

            <div class="ms-auto flex items-center gap-3">
                <LanguageSwitcher />
                <slot name="notifications" />
                <div class="h-4 w-px bg-border"></div>

                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                        {{ auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <span class="text-sm text-muted-foreground">{{ auth.user.name }}</span>
                </div>

                <div class="h-4 w-px bg-border"></div>
                <span class="text-xs text-muted-foreground">{{ dateStr() }} — {{ timeStr() }}</span>

                <a
                    href="/dashboard"
                    class="ms-2 flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground"
                >
                    <svg class="h-3.5 w-3.5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                    </svg>
                    {{ t('nav.dashboard') }}
                </a>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <slot />
        </div>
    </div>

    <ConfirmDialog />
</template>
