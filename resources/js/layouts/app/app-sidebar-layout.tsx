import { AppContent } from '@/components/app-content';
import { AppMobileHeader } from '@/components/app-mobile-header';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidePanelSlot, usePageContentRef } from '@/components/side-panel';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    fillHeight = false,
}: AppLayoutProps) {
    const contentRef = usePageContentRef();

    return (
        <AppShell>
            <AppSidebar />
            <AppContent
                ref={contentRef}
                className={cn(
                    'min-w-0 overflow-x-clip',
                    fillHeight &&
                        'h-svh min-h-0 overflow-y-clip md:peer-data-[variant=inset]:h-[calc(100svh-(--spacing(4)))]',
                )}
            >
                <AppMobileHeader />
                <div
                    className={cn(
                        'min-w-0 px-6 py-6 md:px-4',
                        fillHeight && 'flex min-h-0 flex-1 flex-col',
                    )}
                >
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                    {children}
                </div>
            </AppContent>
            <SidePanelSlot />
        </AppShell>
    );
}
