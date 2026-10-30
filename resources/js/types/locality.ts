export type LocalityUsage = {
    facts: number;
    parishes: number;
    residences: number;
    cemeteries: number;
    total: number;
};

export type Locality = {
    id: number;
    name: string;
    kind: string | null;
    kind_label: string | null;
    latitude: number | null;
    longitude: number | null;
    historical_affiliation: string | null;
    description: string | null;
    is_defunct: boolean;
    defunct_note: string | null;
    /** Other spellings of the name; sorted, present when the register loads them. */
    aliases?: string[];
    /** Present on the list and the panel, which load the counts. */
    usage?: LocalityUsage;
};
