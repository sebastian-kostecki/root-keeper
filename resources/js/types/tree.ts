import type { PersonAge, PersonEvent } from './person';

export type SheetPersonEventDetails = {
    date_parts: PersonEvent['date_parts'];
    place: string | null;
};

export type SheetPersonDetails = {
    birth: SheetPersonEventDetails | null;
    death: SheetPersonEventDetails | null;
    burial_place: string | null;
};

export type SheetPerson = {
    id: number;
    display_name: string;
    card_name: string;
    card_given_name: string;
    card_surname: string;
    gender: 'male' | 'female' | null;
    lifespan: string | null;
    is_deceased: boolean;
    age: PersonAge | null;
    photo_version: string | null;
    details?: SheetPersonDetails;
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

export type PairMarriage = { year: string | null };

export type FamilySheet = {
    focus: SheetPerson;
    unions: SheetUnion[];
    selected_union: string | null;
    partner: SheetSlot;
    focus_side: 'left' | 'right';
    parents: { focus: SheetSlot[]; partner: SheetSlot[] };
    grandparents: { focus: SheetSlot[]; partner: SheetSlot[] };
    parent_marriages: {
        focus: PairMarriage | null;
        partner: PairMarriage | null;
    };
    grandparent_marriages: {
        focus: [PairMarriage | null, PairMarriage | null];
        partner: [PairMarriage | null, PairMarriage | null];
    };
    children: SheetPerson[];
    add_child: AddChildTarget;
    add_spouse: AddSpouseTarget;
    unmarried_parents: { focus: boolean; partner: boolean };
};

export type AncestorNode =
    | {
          kind: 'person';
          person: SheetPerson;
          repeated: boolean;
          parents: AncestorNode[];
      }
    | { kind: 'unknown'; label: string };

export type AncestorChart = {
    focus: SheetPerson;
    generations: number;
    min_generations: number;
    available_generations: number;
    root: AncestorNode;
};
