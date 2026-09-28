export type SheetPerson = {
    id: number;
    display_name: string;
    gender: 'male' | 'female' | null;
    lifespan: string | null;
    is_deceased: boolean;
};

export type ParentSlotRole = 'father' | 'mother' | 'parent';

export type ParentSlotTarget = {
    id: number;
    display_name: string;
    last_name: string | null;
    role: ParentSlotRole;
    excluded_ids: number[];
};

export type SheetSlot =
    | { kind: 'person'; person: SheetPerson }
    | { kind: 'unknown'; label: string; parent_of?: ParentSlotTarget }
    | { kind: 'empty' };

export type ParentCandidate = {
    id: number;
    display_name: string;
    birth_date: string | null;
    gender: 'male' | 'female' | null;
};

export type SheetUnion = {
    key: string;
    partner_id: number | null;
    label: string;
    is_marriage: boolean;
    marriage_year: string | null;
    children_count: number;
};

export type FamilySheet = {
    focus: SheetPerson;
    unions: SheetUnion[];
    selected_union: string | null;
    partner: SheetSlot;
    focus_side: 'left' | 'right';
    parents: { focus: SheetSlot[]; partner: SheetSlot[] };
    grandparents: { focus: SheetSlot[]; partner: SheetSlot[] };
    children: SheetPerson[];
};
