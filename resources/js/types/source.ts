import type { LocalityOption, Parish, ReligionOption } from './parish';
import type { PersonEvent } from './person';

export type SourceKind = 'metrical_record' | 'gravestone' | 'other';

export type SourceRecordType = 'birth' | 'marriage' | 'death' | 'other';

export type BuriedPerson = { id: number; display_name: string };

export type Source = {
    id: number;
    kind: SourceKind;
    kind_label: string;
    kind_short_label: string;
    name: string | null;
    display_name: string;
    record_type: SourceRecordType | null;
    record_type_label: string | null;
    legacy_entry_number: string | null;
    year: number | null;
    archive: string | null;
    parish_id: number | null;
    parish_label: string | null;
    parish_name: string | null;
    parish_locality_id: number | null;
    locality_name: string | null;
    cemetery_locality_id: number | null;
    cemetery_name: string | null;
    buried_people?: BuriedPerson[];
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
    entry_number: string | null;
    person: {
        id: number;
        display_name: string;
    };
};

/** The `sourcePanel` prop of `/sources`: null when closed, `source: null` for „Nowe źródło”. */
export type SourcePanelProps = {
    source: Source | null;
    confirms: SourceConfirmation[];
    parishes: Parish[];
    localities: LocalityOption[];
    archives: string[];
    religions: ReligionOption[];
} | null;
