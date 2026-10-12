import { Link, usePage } from '@inertiajs/react';
import { MapPin, Network, ScrollText, Users, X } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { index as placesIndex } from '@/routes/places';
import { index as personsIndex } from '@/routes/persons';
import { index as sourcesIndex } from '@/routes/sources';
import { index as treeIndex } from '@/routes/tree';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { navCounts, name } = usePage().props;
    const { isMobile, setOpenMobile } = useSidebar();

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
        {
            title: 'Miejsca',
            href: placesIndex(),
            icon: MapPin,
            count: navCounts
                ? navCounts.localities + navCounts.parishes
                : undefined,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center gap-1 group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:gap-2">
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="min-w-0 flex-1 group-data-[collapsible=icon]:flex-none"
                        >
                            <Link
                                href={treeIndex()}
                                prefetch
                                aria-label={name}
                                onClick={() => setOpenMobile(false)}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        {isMobile ? (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-sidebar-ring size-11 shrink-0 focus-visible:ring-3"
                                onClick={() => setOpenMobile(false)}
                            >
                                <X />
                                <span className="sr-only">Zamknij menu</span>
                            </Button>
                        ) : (
                            <SidebarTrigger className="hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-sidebar-ring shrink-0 group-data-[collapsible=icon]:size-8 focus-visible:ring-3" />
                        )}
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
