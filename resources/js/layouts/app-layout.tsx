import { usePage } from '@inertiajs/react';
import { MarriagePanel } from '@/components/marriages/marriage-panel';
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
    const { personPreview, marriagePanel } = usePage().props;

    const record = personPreview
        ? { key: 'person' as const, id: personPreview.person.id }
        : marriagePanel
          ? { key: 'marriage' as const, id: marriagePanel.marriage.id }
          : null;

    return (
        <PersonPreviewProvider>
            <SidePanelSlotProvider>
                <SectionEditorProvider record={record}>
                    <AppLayoutTemplate
                        breadcrumbs={breadcrumbs}
                        fillHeight={fillHeight}
                    >
                        {children}
                    </AppLayoutTemplate>
                    <PersonPanel />
                    <MarriagePanel />
                </SectionEditorProvider>
            </SidePanelSlotProvider>
        </PersonPreviewProvider>
    );
}
