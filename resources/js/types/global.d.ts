import type { Auth } from '@/types/auth';
import type { CemeteryManagerOption, CemeteryOption } from '@/types/cemetery';
import type { LocalityOption } from '@/types/parish';
import type {
    MarriageFormOption,
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
                cemeteries: number;
            } | null;
            personPreview: PersonPreviewPayload | null;
            marriagePanel: MarriagePanelPayload | null;
            availableSources?: Source[];
            localityOptions?: LocalityOption[];
            cemeteryOptions?: CemeteryOption[];
            cemeteryManagers: CemeteryManagerOption[];
            marriageForms: MarriageFormOption[];
            [key: string]: unknown;
        };
    }
}
