import { cva, type VariantProps } from 'class-variance-authority';

export { default as Sidebar } from './Sidebar.vue';
export { default as SidebarContent } from './SidebarContent.vue';
export { default as SidebarFooter } from './SidebarFooter.vue';
export { default as SidebarGroup } from './SidebarGroup.vue';
export { default as SidebarGroupAction } from './SidebarGroupAction.vue';
export { default as SidebarGroupContent } from './SidebarGroupContent.vue';
export { default as SidebarGroupLabel } from './SidebarGroupLabel.vue';
export { default as SidebarHeader } from './SidebarHeader.vue';
export { default as SidebarInput } from './SidebarInput.vue';
export { default as SidebarInset } from './SidebarInset.vue';
export { default as SidebarMenu } from './SidebarMenu.vue';
export { default as SidebarMenuAction } from './SidebarMenuAction.vue';
export { default as SidebarMenuBadge } from './SidebarMenuBadge.vue';
export { default as SidebarMenuButton } from './SidebarMenuButton.vue';
export { default as SidebarMenuItem } from './SidebarMenuItem.vue';
export { default as SidebarMenuSkeleton } from './SidebarMenuSkeleton.vue';
export { default as SidebarMenuSub } from './SidebarMenuSub.vue';
export { default as SidebarMenuSubButton } from './SidebarMenuSubButton.vue';
export { default as SidebarMenuSubItem } from './SidebarMenuSubItem.vue';
export { default as SidebarProvider } from './SidebarProvider.vue';
export { default as SidebarRail } from './SidebarRail.vue';
export { default as SidebarSeparator } from './SidebarSeparator.vue';
export { default as SidebarTrigger } from './SidebarTrigger.vue';

export { useSidebar } from './utils';

export const sidebarMenuButtonVariants = cva(
    [
        'peer/menu-button flex w-full min-h-0 items-start gap-2 rounded-md px-2 py-2.5 text-start text-sm outline-none ring-sidebar-ring transition-[width,height,padding]',
        'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground',
        'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
        'group-has-[[data-sidebar=menu-action]]/menu-item:pe-8',
        'data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground',
        'group-data-[collapsible=icon]:!size-8 group-data-[collapsible=icon]:!items-center group-data-[collapsible=icon]:!justify-center group-data-[collapsible=icon]:!p-2 group-data-[collapsible=icon]:!overflow-hidden',
        '[&>svg]:size-4 [&>svg]:shrink-0 [&>svg]:self-center',
        '[&_[data-sidebar-nav-title]]:min-w-0 [&_[data-sidebar-nav-title]]:flex-1 [&_[data-sidebar-nav-title]]:break-words [&_[data-sidebar-nav-title]]:leading-snug',
        '[&>[data-slot=sidebar-badge]]:shrink-0',
        /* Allow tall scripts (Urdu); overflow-x avoids horizontal bleed from rounded clipping */
        'overflow-x-clip overflow-y-visible',
    ].join(' '),
    {
        variants: {
            variant: {
                default: 'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                outline:
                    'bg-background shadow-[0_0_0_1px_hsl(var(--sidebar-border))] hover:bg-sidebar-accent hover:text-sidebar-accent-foreground hover:shadow-[0_0_0_1px_hsl(var(--sidebar-accent))]',
            },
            size: {
                default: 'min-h-11 text-sm',
                sm: 'min-h-10 text-xs py-2',
                lg: 'min-h-14 text-sm py-3.5 group-data-[collapsible=icon]:!p-0',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export type SidebarMenuButtonVariants = VariantProps<typeof sidebarMenuButtonVariants>;
