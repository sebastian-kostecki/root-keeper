export type Cemetery = {
    id: number;
    name: string | null;
    locality_id: number;
    locality_name: string;
    manager: string | null;
    manager_label: string | null;
    religion: string | null;
    religion_label: string | null;
    description: string | null;
    /** Built by the server: the name with the locality, or „cmentarz {miejscowość}”. */
    label: string;
    /** Present on the list and the panel, which count the references. */
    sources_count?: number;
    burials_count?: number;
};

/** A cemetery as offered in a suggestion list. */
export type CemeteryOption = {
    id: number;
    label: string;
    locality_id: number;
    locality_name: string;
};

/** A cemetery manager as offered in the manager select. */
export type CemeteryManagerOption = {
    value: string;
    label: string;
};
