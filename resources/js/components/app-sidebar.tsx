import { Link, usePage } from '@inertiajs/react';
import { Network, ScrollText, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { index as personsIndex } from '@/routes/persons';
import { index as sourcesIndex } from '@/routes/sources';
import { index as treeIndex } from '@/routes/tree';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { navCounts } = usePage().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Drzewo',
            href: treeIndex(),
            icon: Network,
        },
        {
            title: 'Osoby',
            href: personsIndex(),
            icon: Users,
            count: navCounts?.people,
        },
        {
            title: 'Źródła',
            href: sourcesIndex(),
            icon: ScrollText,
            count: navCounts?.sources,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center gap-1">
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={treeIndex()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        <SidebarTrigger className="shrink-0" />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
