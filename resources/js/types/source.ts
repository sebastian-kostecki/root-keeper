import type { PersonEvent } from './person';

export type SourceKind = 'metrical_record' | 'gravestone' | 'other';

export type SourceRecordType = 'birth' | 'marriage' | 'death' | 'other';

export type Source = {
    id: number;
    kind: SourceKind;
    kind_label: string;
    kind_short_label: string;
    name: string | null;
    display_name: string;
    record_type: SourceRecordType | null;
    record_type_label: string | null;
    entry_number: string | null;
    year: number | null;
    provenance: string | null;
    url: string | null;
    note: string | null;
    original_name: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    has_thumbnail: boolean;
    uploaded_at: string;
    events_count?: number;
};

export type IndexSource = Source & { events_count: number };

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
