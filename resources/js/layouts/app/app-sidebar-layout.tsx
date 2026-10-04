import { AppContent } from '@/components/app-content';
import { AppMobileHeader } from '@/components/app-mobile-header';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidePanelSlot } from '@/components/side-panel';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell>
            <AppSidebar />
            <AppContent className="min-w-0 overflow-x-clip">
                <AppMobileHeader />
                <div className="min-w-0 px-6 py-6 md:px-4">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                    {children}
                </div>
            </AppContent>
            <SidePanelSlot />
        </AppShell>
    );
}
