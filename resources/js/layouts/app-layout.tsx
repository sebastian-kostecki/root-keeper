import { PersonPreviewDialog } from '@/components/persons/person-preview-dialog';
import { PersonPreviewProvider } from '@/components/persons/person-preview-provider';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <PersonPreviewProvider>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
            <PersonPreviewDialog />
        </PersonPreviewProvider>
    );
}
