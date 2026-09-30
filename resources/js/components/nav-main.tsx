import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { setOpenMobile } = useSidebar();

    return (
        <SidebarGroup className="px-2 py-0">
            <nav aria-label="Nawigacja główna">
                <SidebarMenu>
                    {items.map((item) => {
                        const isActive = isCurrentOrParentUrl(item.href);
                        const hasCount =
                            item.count !== undefined && item.count > 0;

                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive}
                                    tooltip={{
                                        children: hasCount
                                            ? `${item.title}\u00A0·\u00A0${item.count}`
                                            : item.title,
                                    }}
                                    className="[[data-mobile=true]_&]:h-11 [[data-mobile=true]_&]:text-base [[data-mobile=true]_&>svg]:size-5"
                                >
                                    <Link
                                        href={item.href}
                                        prefetch
                                        aria-current={
                                            isActive ? 'page' : undefined
                                        }
                                        onClick={() => setOpenMobile(false)}
                                    >
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                                {hasCount && (
                                    <SidebarMenuBadge className="font-normal [[data-mobile=true]_&]:top-3 [[data-mobile=true]_&]:text-sm">
                                        {item.count}
                                    </SidebarMenuBadge>
                                )}
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </nav>
        </SidebarGroup>
    );
}
