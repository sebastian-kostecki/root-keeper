export type Parish = {
    id: number;
    name: string | null;
    kind: string;
    kind_label: string;
    dedication: string | null;
    religion: string | null;
    religion_label: string | null;
    locality_id: number | null;
    locality_name: string | null;
    label: string;
    /** Present on the list and the panel, which count the sources. */
    sources_count?: number;
};

export type ReligionOption = {
    value: string;
    label: string;
};

export type ParishKindOption = {
    value: string;
    label: string;
};

/** A locality as offered in a suggestion list. */
export type LocalityOption = {
    id: number;
    name: string;
    is_defunct?: boolean;
};

/** A locality kind as offered in the kind select. */
export type LocalityKindOption = {
    value: string;
    label: string;
};
