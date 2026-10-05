import { describe, expect, it } from 'vite-plus/test';
import type { FamilySheet, SheetPerson, SheetSlot } from '@/types';
import { centreHints } from './centre-hints';

function person(id: number): SheetPerson {
    return {
        id,
        display_name: `Osoba ${id}`,
        gender: null,
        lifespan: null,
        is_deceased: false,
        age: null,
    };
}

const known = (id: number): SheetSlot => ({
    kind: 'person',
    person: person(id),
});
const unknown: SheetSlot = { kind: 'unknown', label: 'Nieznany' };
const empty: SheetSlot = { kind: 'empty' };

function sheet(overrides: Partial<FamilySheet> = {}): FamilySheet {
    return {
        focus: person(1),
        unions: [],
        selected_union: null,
        partner: empty,
        focus_side: 'left',
        parents: { focus: [empty, empty], partner: [empty, empty] },
        grandparents: {
            focus: [empty, empty, empty, empty],
            partner: [empty, empty, empty, empty],
        },
        children: [],
        add_child: {
            parents: [],
            default_last_name: null,
            excluded_ids: [],
            blocked_ids: [],
        },
        add_spouse: { excluded_ids: [] },
        unmarried_parents: { focus: false, partner: false },
        ...overrides,
    };
}

describe('centreHints', () => {
    it('reports the centre id', () => {
        expect(centreHints(sheet()).focusId).toBe(1);
    });

    it('pairs grandparents on both sides', () => {
        const hints = centreHints(
            sheet({
                grandparents: {
                    focus: [known(10), known(11), known(12), known(13)],
                    partner: [known(20), known(21), empty, empty],
                },
            }),
        );

        expect(hints.spouses).toMatchObject({
            10: '11',
            11: '10',
            12: '13',
            13: '12',
            20: '21',
            21: '20',
        });
    });

    it('pairs parents', () => {
        const hints = centreHints(
            sheet({
                parents: {
                    focus: [known(10), known(11)],
                    partner: [known(20), unknown],
                },
            }),
        );

        expect(hints.spouses).toEqual({ 10: '11', 11: '10', 20: 'unknown' });
    });

    it('points the partner at the centre', () => {
        const hints = centreHints(sheet({ partner: known(2) }));

        expect(hints.spouses).toEqual({ 2: '1' });
    });

    it('marks an unknown partner of a person as unknown', () => {
        const hints = centreHints(
            sheet({
                grandparents: {
                    focus: [known(10), unknown, empty, empty],
                    partner: [empty, empty, empty, empty],
                },
            }),
        );

        expect(hints.spouses).toEqual({ 10: 'unknown' });
    });

    it('gives no entry to a person without a pair', () => {
        const hints = centreHints(
            sheet({
                parents: { focus: [known(10), empty], partner: [empty, empty] },
            }),
        );

        expect(hints.spouses).toEqual({});
    });

    it('keeps the first occurrence of a person shown twice', () => {
        const hints = centreHints(
            sheet({
                grandparents: {
                    focus: [known(10), known(11), empty, empty],
                    partner: [empty, empty, empty, empty],
                },
                parents: {
                    focus: [known(10), known(12)],
                    partner: [empty, empty],
                },
                partner: known(10),
            }),
        );

        expect(hints.spouses[10]).toBe('11');
    });
});
