<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const { t } = useI18n();

const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: t('settings.profileLink'),
        href: route('profile.edit'),
    },
    {
        title: t('settings.passwordLink'),
        href: route('password.edit'),
    },
    {
        title: t('settings.appearanceLink'),
        href: route('appearance'),
    },
]);

const currentPath = window.location.pathname;
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('settings.pageTitle')" :description="t('settings.pageDescription')" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full lg:w-52 lg:shrink-0">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="min-w-0 flex-1 md:max-w-4xl xl:max-w-5xl">
                <section class="w-full max-w-3xl xl:max-w-4xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
