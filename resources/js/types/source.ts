import type { PersonEvent } from './person';

export type SourceKind = 'metrical_record' | 'gravestone' | 'other';

export type Source = {
    id: number;
    kind: SourceKind;
    kind_label: string;
    original_name: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    has_thumbnail: boolean;
    uploaded_at: string;
};

export type SourceConfirmation = {
    event_id: number;
    event_type: PersonEvent['type'];
    event_label: string;
    is_last_source: boolean;
    person: {
        id: number;
        display_name: string;
    };
};
