import type { FamilySheet, SheetSlot } from '@/types';

/** Union to preselect after re-centring on a slot's person: the other slot of its pair. */
export function slotSpouse(
    pair: SheetSlot[],
    index: number,
): string | undefined {
    const other = pair[index === 0 ? 1 : 0];

    if (other?.kind === 'person') {
        return String(other.person.id);
    }

    return other?.kind === 'unknown' ? 'unknown' : undefined;
}

/**
 * Which union to select when the tree is re-centred on a person of the sheet:
 * a map of person id to `spouse`, plus the id of the current centre. A person
 * without a pair has no entry; one shown twice keeps the first occurrence in
 * the order grandparents, parents, partner.
 */
export function centreHints(sheet: FamilySheet): {
    focusId: number;
    spouses: Record<number, string>;
} {
    const spouses: Record<number, string> = {};

    const add = (slot: SheetSlot, spouse: string | undefined) => {
        if (
            slot.kind === 'person' &&
            spouse !== undefined &&
            spouses[slot.person.id] === undefined
        ) {
            spouses[slot.person.id] = spouse;
        }
    };

    for (const block of ['focus', 'partner'] as const) {
        const grandparents = sheet.grandparents[block];

        grandparents.forEach((slot, i) => {
            add(
                slot,
                slotSpouse(
                    grandparents.slice(i < 2 ? 0 : 2, i < 2 ? 2 : 4),
                    i % 2,
                ),
            );
        });
    }

    for (const block of ['focus', 'partner'] as const) {
        const parents = sheet.parents[block];

        parents.forEach((slot, i) => {
            add(slot, slotSpouse(parents, i));
        });
    }

    add(sheet.partner, String(sheet.focus.id));

    return { focusId: sheet.focus.id, spouses };
}
