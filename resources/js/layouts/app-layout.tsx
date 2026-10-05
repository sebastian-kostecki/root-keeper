import { PersonPreviewDialog } from '@/components/persons/person-preview-dialog';
import { PersonPreviewProvider } from '@/components/persons/person-preview-provider';
import { SidePanelSlotProvider } from '@/components/side-panel';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    fillHeight = false,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    fillHeight?: boolean;
    children: React.ReactNode;
}) {
    return (
        <PersonPreviewProvider>
            <SidePanelSlotProvider>
                <AppLayoutTemplate
                    breadcrumbs={breadcrumbs}
                    fillHeight={fillHeight}
                >
                    {children}
                </AppLayoutTemplate>
                <PersonPreviewDialog />
            </SidePanelSlotProvider>
        </PersonPreviewProvider>
    );
}
