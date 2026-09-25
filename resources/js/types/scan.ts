import type { PersonEvent } from './person';

export type Scan = {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    has_thumbnail: boolean;
    uploaded_at: string;
};

export type ScanConfirmation = {
    event_id: number;
    event_type: PersonEvent['type'];
    event_label: string;
    is_last_scan: boolean;
    person: {
        id: number;
        display_name: string;
    };
};
