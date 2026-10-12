import { describe, expect, it } from 'vite-plus/test';
import {
    addValueFor,
    resolveAddTarget,
    resolvePersonsAddTarget,
} from './add-person-target';
import type { AddTarget } from './add-person-target';
import type {
    FamilySheet,
    ParentSlotTarget,
    SheetPerson,
    SheetSlot,
} from '@/types';

const focus: SheetPerson = {
    id: 1,
    display_name: 'Jan Kowalski',
    gender: 'male',
    lifespan: null,
    is_deceased: false,
    age: null,
    photo_version: null,
};

function parentSlot(
    childId: number,
    role: ParentSlotTarget['role'],
): SheetSlot {
    return {
        kind: 'unknown',
        label: 'Dodaj',
        parent_of: {
            id: childId,
            display_name: 'Dziecko',
            last_name: 'Kowalski',
            role,
            excluded_ids: [],
        },
    };
}

function sheet(overrides: Partial<FamilySheet> = {}): FamilySheet {
    return {
        focus,
        unions: [],
        selected_union: null,
        partner: { kind: 'empty' },
        focus_side: 'left',
        parents: {
            focus: [parentSlot(1, 'father'), parentSlot(1, 'mother')],
            partner: [{ kind: 'empty' }, { kind: 'empty' }],
        },
        grandparents: {
            focus: [
                { kind: 'unknown', label: 'Dziadek' },
                { kind: 'empty' },
                { kind: 'empty' },
                { kind: 'empty' },
            ],
            partner: [
                { kind: 'empty' },
                { kind: 'empty' },
                { kind: 'empty' },
                { kind: 'empty' },
            ],
        },
        children: [],
        add_child: {
            parents: [{ id: 1, display_name: 'Jan Kowalski' }],
            default_last_name: 'Kowalski',
            excluded_ids: [],
            blocked_ids: [],
        },
        add_spouse: { excluded_ids: [] },
        parent_marriages: { focus: null, partner: null },
        grandparent_marriages: { focus: [null, null], partner: [null, null] },
        unmarried_parents: { focus: false, partner: false },
        ...overrides,
    };
}

describe('resolveAddTarget', () => {
    it('resolves a parent slot of the sheet', () => {
        const target = resolveAddTarget(sheet(), 'father:1');

        expect(target).toEqual({
            kind: 'parent',
            slot: {
                id: 1,
                display_name: 'Dziecko',
                last_name: 'Kowalski',
                role: 'father',
                excluded_ids: [],
            },
        });
    });

    it('resolves a parent slot of a grandparent row', () => {
        const grandparents = sheet({
            grandparents: {
                focus: [
                    parentSlot(2, 'parent'),
                    { kind: 'empty' },
                    { kind: 'empty' },
                    { kind: 'empty' },
                ],
                partner: [
                    { kind: 'empty' },
                    { kind: 'empty' },
                    { kind: 'empty' },
                    { kind: 'empty' },
                ],
            },
        });

        expect(resolveAddTarget(grandparents, 'parent:2')?.kind).toBe('parent');
    });

    it('resolves the child target', () => {
        expect(resolveAddTarget(sheet(), 'child')).toEqual({
            kind: 'child',
            target: sheet().add_child,
        });
    });

    it('resolves the spouse target with the centre person as focus', () => {
        expect(resolveAddTarget(sheet(), 'spouse')).toEqual({
            kind: 'spouse',
            focus,
            target: { excluded_ids: [] },
        });
    });

    it('gives null for a parent slot that is already filled', () => {
        const filled = sheet({
            parents: {
                focus: [
                    { kind: 'person', person: { ...focus, id: 9 } },
                    parentSlot(1, 'mother'),
                ],
                partner: [{ kind: 'empty' }, { kind: 'empty' }],
            },
        });

        expect(resolveAddTarget(filled, 'father:1')).toBeNull();
    });

    it('gives null for a parent value of another child', () => {
        expect(resolveAddTarget(sheet(), 'father:99')).toBeNull();
    });

    it('gives null for a role the slot does not have', () => {
        expect(resolveAddTarget(sheet(), 'parent:1')).toBeNull();
    });

    it('gives null without a sheet or a value', () => {
        expect(resolveAddTarget(null, 'child')).toBeNull();
        expect(resolveAddTarget(sheet(), null)).toBeNull();
    });

    it('resolves the proband target only without a sheet', () => {
        expect(resolveAddTarget(null, 'proband', { hasPeople: false })).toEqual(
            { kind: 'proband', hasPeople: false },
        );
        expect(resolveAddTarget(null, 'proband', { hasPeople: true })).toEqual({
            kind: 'proband',
            hasPeople: true,
        });
        expect(
            resolveAddTarget(sheet(), 'proband', { hasPeople: true }),
        ).toBeNull();
    });

    it('gives null for the sheet targets without a sheet', () => {
        for (const value of ['child', 'spouse', 'father:1']) {
            expect(
                resolveAddTarget(null, value, { hasPeople: true }),
            ).toBeNull();
        }
    });

    it('gives null for an unknown or malformed value', () => {
        for (const value of [
            '',
            'proband',
            'Child',
            'father',
            'father:',
            'father:abc',
            'father:1x',
            'father:1:2',
            'uncle:1',
            ' child',
        ]) {
            expect(resolveAddTarget(sheet(), value)).toBeNull();
        }
    });
});

describe('resolvePersonsAddTarget', () => {
    it('resolves the person target on /persons', () => {
        expect(resolvePersonsAddTarget('person')).toEqual({ kind: 'person' });
    });

    it('gives null for any other value on /persons', () => {
        for (const value of [null, '', 'proband', 'child', 'Person']) {
            expect(resolvePersonsAddTarget(value)).toBeNull();
        }
    });
});

describe('the person value on the tree', () => {
    it('gives null with and without a sheet', () => {
        expect(resolveAddTarget(sheet(), 'person')).toBeNull();
        expect(
            resolveAddTarget(null, 'person', { hasPeople: true }),
        ).toBeNull();
        expect(
            resolveAddTarget(null, 'person', { hasPeople: false }),
        ).toBeNull();
    });
});

describe('addValueFor', () => {
    it('names the person target', () => {
        expect(addValueFor({ kind: 'person' })).toBe('person');
    });

    it('is the inverse of resolveAddTarget for every kind', () => {
        for (const value of ['father:1', 'mother:1', 'child', 'spouse']) {
            const target = resolveAddTarget(sheet(), value) as AddTarget;

            expect(addValueFor(target)).toBe(value);
        }
    });

    it('names the proband target', () => {
        const target = resolveAddTarget(null, 'proband', { hasPeople: true });

        expect(addValueFor(target as AddTarget)).toBe('proband');
    });

    it('names the parent role and the child id', () => {
        const target: AddTarget = {
            kind: 'parent',
            slot: {
                id: 4,
                display_name: 'Dziecko',
                last_name: null,
                role: 'parent',
                excluded_ids: [],
            },
        };

        expect(addValueFor(target)).toBe('parent:4');
    });
});
