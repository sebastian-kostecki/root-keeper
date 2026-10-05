export type Parish = {
    id: number;
    name: string;
    religion: string | null;
    religion_label: string | null;
    locality_id: number | null;
    locality_name: string | null;
    label: string;
};

export type ReligionOption = {
    value: string;
    label: string;
};

/** A locality as offered in a suggestion list. */
export type LocalityOption = {
    id: number;
    name: string;
};
