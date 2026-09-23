export type Religion = {
    value: string;
    label: string;
};

export type PersonEvent = {
    type: 'birth' | 'death';
    date: string | null;
    place: string | null;
};

export type Person = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    display_name: string;
    residence: string | null;
    religion: Religion | null;
    birth: PersonEvent | null;
    death: PersonEvent | null;
};
