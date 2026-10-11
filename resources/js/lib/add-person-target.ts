import type {
    AddChildTarget,
    AddSpouseTarget,
    FamilySheet,
    ParentSlotRole,
    ParentSlotTarget,
    SheetPerson,
    SheetSlot,
} from '@/types';

/** What the add-person panel adds, resolved from the family sheet on screen. */
export type AddTarget =
    | { kind: 'parent'; slot: ParentSlotTarget }
    | { kind: 'child'; target: AddChildTarget }
    | { kind: 'spouse'; focus: SheetPerson; target: AddSpouseTarget }
    | { kind: 'proband'; hasPeople: boolean };

/** `father:<childId>`, `mother:<childId>` or `parent:<childId>`. */
const PARENT_VALUE = /^(father|mother|parent):(\d+)$/;

/**
 * Resolves the value of the `add` query key to a target of the sheet. A value
 * that does not match the sheet (a parent slot filled since, an unknown key,
 * a malformed value) gives null, so no panel opens. `proband` is only a target
 * while the tree has no proband (no sheet); `hasPeople` tells the panel whether
 * it picks from the tree's people or only creates one.
 */
export function resolveAddTarget(
    sheet: FamilySheet | null,
    value: string | null,
    options: { hasPeople: boolean } = { hasPeople: false },
): AddTarget | null {
    if (value === null) {
        return null;
    }

    if (sheet === null) {
        return value === 'proband'
            ? { kind: 'proband', hasPeople: options.hasPeople }
            : null;
    }

    if (value === 'child') {
        return { kind: 'child', target: sheet.add_child };
    }

    if (value === 'spouse') {
        return {
            kind: 'spouse',
            focus: sheet.focus,
            target: sheet.add_spouse,
        };
    }

    const match = PARENT_VALUE.exec(value);

    if (match === null) {
        return null;
    }

    const role = match[1] as ParentSlotRole;
    const childId = Number(match[2]);
    const slots: SheetSlot[] = [
        ...sheet.parents.focus,
        ...sheet.parents.partner,
        ...sheet.grandparents.focus,
        ...sheet.grandparents.partner,
    ];

    for (const slot of slots) {
        const parentOf = slot.kind === 'unknown' ? slot.parent_of : undefined;

        if (
            parentOf !== undefined &&
            parentOf.id === childId &&
            parentOf.role === role
        ) {
            return { kind: 'parent', slot: parentOf };
        }
    }

    return null;
}

/** The `add` query value that opens the given target; the inverse of `resolveAddTarget`. */
export function addValueFor(target: AddTarget): string {
    if (target.kind === 'parent') {
        return `${target.slot.role}:${target.slot.id}`;
    }

    return target.kind;
}
