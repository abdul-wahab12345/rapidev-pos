<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    CreditCard,
    DollarSign,
    Gauge,
    LayoutGrid,
    ListOrdered,
    MapPin,
    Package,
    RotateCcw,
    ScrollText,
    Settings,
    ShoppingCart,
    Store,
    Tag,
    Truck,
    Users,
    Users2,
    Wallet,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { t } = useI18n();
const page = usePage();

/** Drawer edge is physical (`left`|`right`). Under `dir=rtl`, `right` is the logical start edge. Desktop dock uses logical `start` via Tailwind (`side=left`). */
const mobileSidebarEdge = computed(() =>
    page.props?.tenant?.settings?.language === 'ur' ? 'right' : 'left',
);

function isActive(href: string) {
    return page.url.startsWith(href);
}

const navGroups = [
    {
        labelKey: 'nav.main',
        items: [
            { titleKey: 'nav.dashboard', href: '/dashboard', icon: Gauge },
            { titleKey: 'nav.posCashier', href: '/pos', icon: ShoppingCart, soon: false },
        ],
    },
    {
        labelKey: 'nav.inventory',
        items: [
            { titleKey: 'nav.products', href: '/inventory/products', icon: Package },
            { titleKey: 'nav.categories', href: '/inventory/categories', icon: Tag, soon: true },
            { titleKey: 'nav.stockManagement', href: '/inventory/stock', icon: Store },
        ],
    },
    {
        labelKey: 'nav.sales',
        items: [
            { titleKey: 'nav.salesOrders', href: '/sales', icon: LayoutGrid, soon: false },
            { titleKey: 'nav.returnsRefunds', href: '/returns', icon: RotateCcw },
            { titleKey: 'nav.customers', href: '/customers', icon: Users, soon: false },
            { titleKey: 'nav.rateLists', href: '/rate-lists', icon: ListOrdered },
            { titleKey: 'nav.udhaarLedger', href: '/udhaar', icon: BookOpen, soon: true },
        ],
    },
    {
        labelKey: 'nav.purchasing',
        items: [
            { titleKey: 'nav.suppliers', href: '/purchasing/suppliers', icon: Truck },
            { titleKey: 'nav.purchaseOrders', href: '/purchasing/orders', icon: CreditCard },
        ],
    },
    {
        labelKey: 'nav.finance',
        items: [
            { titleKey: 'nav.accounts', href: '/accounts', icon: DollarSign },
            { titleKey: 'nav.generalLedger', href: '/accounts/ledger', icon: ScrollText },
            { titleKey: 'nav.receivablesPayables', href: '/accounts/receivables', icon: CreditCard },
            { titleKey: 'nav.reports', href: '/accounts/reports', icon: BarChart3 },
            { titleKey: 'nav.expenses', href: '/expenses', icon: Wallet },
        ],
    },
    {
        labelKey: 'nav.hr',
        items: [
            { titleKey: 'nav.employees', href: '/employees', icon: Users2, soon: true },
        ],
    },
    {
        labelKey: 'nav.system',
        items: [
            { titleKey: 'nav.businessSettings', href: '/business-settings', icon: Settings },
            { titleKey: 'nav.locations', href: '/locations', icon: MapPin },
            { titleKey: 'nav.profilePassword', href: '/settings/profile', icon: Settings },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" :sheet-side="mobileSidebarEdge">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup
                v-for="group in navGroups"
                :key="group.labelKey"
                class="px-2 py-0"
            >
                <SidebarGroupLabel>{{ t(group.labelKey) }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in group.items" :key="item.titleKey">
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item.href)"
                            :class="item.soon ? 'opacity-50 pointer-events-none' : ''"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span data-sidebar-nav-title class="line-clamp-3 break-words leading-snug">{{ t(item.titleKey) }}</span>
                                <span
                                    v-if="item.soon"
                                    data-slot="sidebar-badge"
                                    class="ms-auto text-[9px] font-medium uppercase tracking-wider text-muted-foreground bg-muted rounded px-1"
                                >
                                    {{ t('common.soon') }}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
