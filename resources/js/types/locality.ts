export type LocalityUsage = {
    facts: number;
    parishes: number;
    residences: number;
    gravestones: number;
    total: number;
};

export type Locality = {
    id: number;
    name: string;
    description: string | null;
    /** Present on the list and the panel, which load the counts. */
    usage?: LocalityUsage;
};
