import type { LocalityOption } from './parish';
import type { SourceKind } from './source';

export type Religion = {
    value: string;
    label: string;
};

export type Gender = {
    value: 'male' | 'female';
    label: string;
};

export type PersonEvent = {
    id: number;
    type: 'birth' | 'death' | 'marriage';
    date: string | null;
    place: string | null;
    locality_id: number | null;
    date_parts: {
        year: number | null;
        month: number | null;
        day: number | null;
        is_approximate: boolean;
    };
};

export type IndexPersonEvent = PersonEvent & {
    is_confirmed: boolean;
};

export type LinkedSource = {
    id: number;
    kind: SourceKind;
    kind_short_label: string;
    original_name: string;
    display_name: string;
    has_thumbnail: boolean;
    uploaded_at: string | null;
    entry_number: string | null;
};

export type SourcedPersonEvent = PersonEvent & {
    is_confirmed: boolean;
    confirmed_kinds: string[];
    sources: LinkedSource[];
};

export type Marriage = SourcedPersonEvent & {
    spouse: {
        id: number;
        display_name: string;
        gender: 'male' | 'female' | null;
        birth_date: string | null;
        death_date: string | null;
    } | null;
};

/** A spouse as the marriage carries it: `Marriage['spouse']` once the null case is handled. */
export type PersonSummary = NonNullable<Marriage['spouse']>;

/** Payload of the marriage side panel (`marriagePanel` shared prop, `BuildMarriagePanel`). */
export type MarriagePanelPayload = {
    marriage: SourcedPersonEvent;
    /** Both spouses, in the fixed order of `PersonOrdering::spouses`. */
    spouses: PersonSummary[];
    /** Children both spouses share. */
    children: PersonSummary[];
};

export type PersonAge = {
    unit: 'years' | 'months' | 'days' | 'under_year';
    value: number | null;
};

export type PersonResidence = {
    id: number;
    locality: LocalityOption;
    year_from: number | null;
    year_to: number | null;
};

export type PersonGravestone = {
    id: number;
    display_name: string;
    cemetery_name: string | null;
};

export type Person = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    maiden_name: string | null;
    display_name: string;
    gender: Gender | null;
    residences?: PersonResidence[];
    gravestones?: PersonGravestone[];
    religion: Religion | null;
    burial_place: string | null;
    birth: IndexPersonEvent | null;
    death: IndexPersonEvent | null;
    age: PersonAge | null;
    photo_version: string | null;
};

export type Relative = {
    relation_id: number;
    id: number;
    display_name: string;
    gender: 'male' | 'female' | null;
    birth_date: string | null;
    death_date: string | null;
};

/** One row of the person's timeline, already ordered by the server. */
export type TimelineEntry = {
    id: number;
    type: 'birth' | 'death' | 'marriage' | 'burial';
    label: string;
    date: string | null;
    place: string | null;
    relative: {
        id: number;
        display_name: string;
    } | null;
};

export type PersonPreview = Omit<Person, 'birth' | 'death'> & {
    birth: SourcedPersonEvent | null;
    death: SourcedPersonEvent | null;
    parents: Relative[];
    children: Relative[];
    marriages: Marriage[];
    note: string | null;
    timeline: TimelineEntry[];
};

export type PersonPreviewPayload = {
    person: PersonPreview;
    personForm: PersonFormData;
    genders: Gender[];
    religions: Religion[];
    isRootPerson: boolean;
};

export type PersonFormEvent = {
    year: number | null;
    month: number | null;
    day: number | null;
    is_approximate: boolean;
    locality: LocalityOption | null;
    is_confirmed: boolean;
};

export type PersonFormData = {
    id: number;
    display_name: string;
    first_name: string | null;
    last_name: string | null;
    maiden_name: string | null;
    gender: string | null;
    residences: PersonResidence[];
    religion: string | null;
    is_deceased: boolean;
    birth: PersonFormEvent | null;
    death: PersonFormEvent | null;
    burial: { locality: LocalityOption | null } | null;
    burial_suggestion?: LocalityOption | null;
};
