import type { Auth } from '@/types/auth';
import type { PersonPreviewPayload, RelationCandidate } from '@/types/person';
import type { Scan } from '@/types/scan';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            personPreview: PersonPreviewPayload | null;
            availableScans?: Scan[];
            relationCandidates?: RelationCandidate[];
            [key: string]: unknown;
        };
    }
}
