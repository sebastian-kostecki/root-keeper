import { usePage } from '@inertiajs/react';
import { PersonPanel } from '@/components/persons/person-panel';
import { PersonPreviewProvider } from '@/components/persons/person-preview-provider';
import { SectionEditorProvider } from '@/components/persons/section-editor';
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
    const { personPreview } = usePage().props;

    return (
        <PersonPreviewProvider>
            <SidePanelSlotProvider>
                <SectionEditorProvider
                    personId={personPreview?.person.id ?? null}
                >
                    <AppLayoutTemplate
                        breadcrumbs={breadcrumbs}
                        fillHeight={fillHeight}
                    >
                        {children}
                    </AppLayoutTemplate>
                    <PersonPanel />
                </SectionEditorProvider>
            </SidePanelSlotProvider>
        </PersonPreviewProvider>
    );
}
