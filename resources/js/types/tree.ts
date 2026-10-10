import type { PersonAge } from './person';

export type SheetPerson = {
    id: number;
    display_name: string;
    gender: 'male' | 'female' | null;
    lifespan: string | null;
    is_deceased: boolean;
    age: PersonAge | null;
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

export type PersonCandidate = {
    id: number;
    display_name: string;
    birth_date: string | null;
    gender: 'male' | 'female' | null;
};

export type AddChildTarget = {
    parents: { id: number; display_name: string }[];
    default_last_name: string | null;
    excluded_ids: number[];
    blocked_ids: number[];
};

export type AddSpouseTarget = {
    excluded_ids: number[];
};

export type SheetUnion = {
    key: string;
    partner_id: number | null;
    partner_name: string | null;
    is_marriage: boolean;
    marriage_id: number | null;
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
    add_child: AddChildTarget;
    add_spouse: AddSpouseTarget;
    unmarried_parents: { focus: boolean; partner: boolean };
};
