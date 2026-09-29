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
};

export type LinkedScan = {
    id: number;
    original_name: string;
    has_thumbnail: boolean;
};

export type SourcedPersonEvent = PersonEvent & {
    is_confirmed: boolean;
    scans: LinkedScan[];
};

export type Marriage = SourcedPersonEvent & {
    date_parts: {
        year: number | null;
        month: number | null;
        day: number | null;
        is_approximate: boolean;
    };
    spouse: {
        id: number;
        display_name: string;
        birth_date: string | null;
        death_date: string | null;
    } | null;
};

export type Person = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    display_name: string;
    gender: Gender | null;
    residence: string | null;
    religion: Religion | null;
    birth: PersonEvent | null;
    death: PersonEvent | null;
};

export type Relative = {
    relation_id: number;
    id: number;
    display_name: string;
    birth_date: string | null;
    death_date: string | null;
};

export type PersonPreview = Omit<Person, 'birth' | 'death'> & {
    birth: SourcedPersonEvent | null;
    death: SourcedPersonEvent | null;
    parents: Relative[];
    children: Relative[];
    marriages: Marriage[];
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
    place: string | null;
    is_confirmed: boolean;
};

export type PersonFormData = {
    id: number;
    display_name: string;
    first_name: string | null;
    last_name: string | null;
    gender: string | null;
    residence: string | null;
    religion: string | null;
    is_deceased: boolean;
    birth: PersonFormEvent | null;
    death: PersonFormEvent | null;
};
