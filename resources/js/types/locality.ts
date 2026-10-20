export type LocalityUsage = {
    facts: number;
    parishes: number;
    residences: number;
    cemeteries: number;
    gravestones: number;
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
    /** Present on the list and the panel, which load the counts. */
    usage?: LocalityUsage;
};
