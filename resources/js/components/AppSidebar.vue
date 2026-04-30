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
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    CreditCard,
    DollarSign,
    Gauge,
    LayoutGrid,
    Package,
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
import AppLogo from './AppLogo.vue';

const page = usePage();

function isActive(href: string) {
    return page.url.startsWith(href);
}

const navGroups = [
    {
        label: 'Main',
        items: [
            { title: 'Dashboard', href: '/dashboard', icon: Gauge },
            { title: 'POS Cashier', href: '/pos', icon: ShoppingCart, soon: false },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { title: 'Products', href: '/inventory/products', icon: Package },
            { title: 'Categories', href: '/inventory/categories', icon: Tag, soon: true },
            { title: 'Stock Management', href: '/inventory/stock', icon: Store },
        ],
    },
    {
        label: 'Sales',
        items: [
            { title: 'Sales Orders', href: '/sales', icon: LayoutGrid, soon: false },
            { title: 'Customers', href: '/customers', icon: Users, soon: false },
            { title: 'Udhaar Ledger', href: '/udhaar', icon: BookOpen, soon: true },
        ],
    },
    {
        label: 'Purchasing',
        items: [
            { title: 'Suppliers', href: '/purchasing/suppliers', icon: Truck },
            { title: 'Purchase Orders', href: '/purchasing/orders', icon: CreditCard },
        ],
    },
    {
        label: 'Finance',
        items: [
            { title: 'Accounts', href: '/accounts', icon: DollarSign },
            { title: 'General Ledger',       href: '/accounts/ledger',       icon: ScrollText },
            { title: 'Receivables & Payables', href: '/accounts/receivables', icon: CreditCard },
            { title: 'Reports',              href: '/accounts/reports',       icon: BarChart3 },
            { title: 'Expenses', href: '/expenses', icon: Wallet, soon: true },
            { title: 'Reports', href: '/reports', icon: BarChart3, soon: true },
        ],
    },
    {
        label: 'HR',
        items: [
            { title: 'Employees', href: '/employees', icon: Users2, soon: true },
        ],
    },
    {
        label: 'System',
        items: [
            { title: 'Business Settings', href: '/business-settings', icon: Settings },
            { title: 'Profile & Password', href: '/settings/profile', icon: Settings },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
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
                :key="group.label"
                class="px-2 py-0"
            >
                <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in group.items" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item.href)"
                            :class="item.soon ? 'opacity-50 pointer-events-none' : ''"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <span
                                    v-if="item.soon"
                                    class="ml-auto text-[9px] font-medium uppercase tracking-wider text-muted-foreground bg-muted rounded px-1"
                                >
                                    Soon
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
