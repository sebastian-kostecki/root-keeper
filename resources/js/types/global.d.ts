import type { Auth } from '@/types/auth';
import type { LocalityOption } from '@/types/parish';
import type {
    MarriagePanelPayload,
    PersonPreviewPayload,
} from '@/types/person';
import type { Source } from '@/types/source';

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
            navCounts: {
                people: number;
                sources: number;
                localities: number;
                parishes: number;
            } | null;
            personPreview: PersonPreviewPayload | null;
            marriagePanel: MarriagePanelPayload | null;
            availableSources?: Source[];
            localityOptions?: LocalityOption[];
            [key: string]: unknown;
        };
    }
}
