import type { RequestPayload } from '@inertiajs/core';
import ParentChildRelationController from '@/actions/App/Http/Controllers/ParentChildRelationController';
import PersonChildController from '@/actions/App/Http/Controllers/PersonChildController';
import PersonParentController from '@/actions/App/Http/Controllers/PersonParentController';
import PersonSpouseController from '@/actions/App/Http/Controllers/PersonSpouseController';
import type { AddTarget } from '@/lib/add-person-target';
import { pluralPhrase } from '@/lib/plural';
import type {
    Gender,
    ParentSlotRole,
    PersonCandidate,
    Religion,
} from '@/types';

export type PickOrCreateSelection = {
    visible: PersonCandidate[];
    hiddenCount: number;
    hiddenHint: string;
};

export type PickOrCreateForm = {
    /** A Wayfinder form definition (`Controller.action.form(...)`). */
    definition: { action: string; method: 'get' | 'post' };
    /** Hidden inputs posted with the person form. */
    hiddenInputs?: { name: string; value: string | number }[];
    lockedGender?: 'male' | 'female';
    defaults?: { last_name?: string | null };
    genders: Gender[];
    religions: Religion[];
    submitTestId: string;
};

export type AddPersonConfig = {
    title: string;
    description: string;
    /** The caller's filtering of the loaded candidates. */
    select: (list: PersonCandidate[]) => PickOrCreateSelection;
    /** Where and what to post when a candidate is picked. */
    pick: (candidateId: number) => {
        url: string;
        data: RequestPayload;
    };
    /** Without it, the "Nowa osoba" view is not offered. */
    form?: PickOrCreateForm;
};

const ROLE_TITLE: Record<ParentSlotRole, string> = {
    father: 'Dodaj ojca',
    mother: 'Dodaj matkę',
    parent: 'Dodaj rodzica',
};

const ROLE_NOUN: Record<ParentSlotRole, string> = {
    father: 'ojca',
    mother: 'matkę',
    parent: 'rodzica',
};

const ROLE_GENDER: Record<ParentSlotRole, 'male' | 'female' | null> = {
    father: 'male',
    mother: 'female',
    parent: null,
};

/** Child names joined the way the trigger and the title show them. */
function parentNames(target: { parents: { display_name: string }[] }): string {
    return target.parents.map((parent) => parent.display_name).join(' i ');
}

/** Visible text and aria-label of the empty slot that opens the target. */
export function addTriggerLabels(target: AddTarget): {
    label: string;
    addLabel: string;
} {
    if (target.kind === 'parent') {
        return {
            label: ROLE_TITLE[target.slot.role],
            addLabel: `Dodaj ${ROLE_NOUN[target.slot.role]}: ${target.slot.display_name}`,
        };
    }

    if (target.kind === 'child') {
        return {
            label: 'Dodaj dziecko',
            addLabel: `Dodaj dziecko: ${parentNames(target.target)}`,
        };
    }

    return {
        label: 'Dodaj małżonka',
        addLabel: `Dodaj małżonka: ${target.focus.display_name}`,
    };
}

/**
 * Title, copy, candidate filtering, pick request and new-person form of one
 * add target. The client only hides what the rules exclude; the server stays
 * the authority.
 */
export function addPersonConfig(
    target: AddTarget,
    options: { genders: Gender[]; religions: Religion[] },
): AddPersonConfig {
    const { genders, religions } = options;

    if (target.kind === 'parent') {
        const { slot } = target;
        const requiredGender = ROLE_GENDER[slot.role];

        return {
            title: `${ROLE_TITLE[slot.role]} — ${slot.display_name}`,
            description: 'Wybierz osobę z drzewa albo dodaj nową.',
            select: (list) => {
                const notExcluded = list.filter(
                    (candidate) => !slot.excluded_ids.includes(candidate.id),
                );
                const hiddenCount =
                    requiredGender === null
                        ? 0
                        : notExcluded.filter(
                              (candidate) => candidate.gender === null,
                          ).length;

                return {
                    visible: notExcluded.filter(
                        (candidate) =>
                            requiredGender === null ||
                            candidate.gender === requiredGender,
                    ),
                    hiddenCount,
                    hiddenHint: `${hiddenCount} ${pluralPhrase(hiddenCount, 'osobę', 'osoby', 'osób')} bez podanej płci ukryto — uzupełnij płeć, aby je tu zobaczyć.`,
                };
            },
            pick: (candidateId) => ({
                url: ParentChildRelationController.store().url,
                data: {
                    parent_id: candidateId,
                    child_id: slot.id,
                    role: slot.role,
                },
            }),
            form: {
                definition: PersonParentController.store.form(slot.id),
                hiddenInputs: [{ name: 'role', value: slot.role }],
                lockedGender: requiredGender ?? undefined,
                defaults:
                    slot.role === 'father'
                        ? { last_name: slot.last_name }
                        : undefined,
                genders,
                religions,
                submitTestId: 'create-parent-button',
            },
        };
    }

    if (target.kind === 'child') {
        const { target: child } = target;
        const names = parentNames(child);

        return {
            title: `Dodaj dziecko — ${names}`,
            description:
                child.parents.length > 1
                    ? `Rodzice: ${names}. Wybierz osobę z drzewa albo dodaj nową.`
                    : `Rodzic: ${names} (drugi nieznany). Wybierz osobę z drzewa albo dodaj nową.`,
            select: (list) => {
                const hiddenCount = list.filter((candidate) =>
                    child.blocked_ids.includes(candidate.id),
                ).length;

                return {
                    visible: list.filter(
                        (candidate) =>
                            !child.excluded_ids.includes(candidate.id) &&
                            !child.blocked_ids.includes(candidate.id),
                    ),
                    hiddenCount,
                    hiddenHint: pluralPhrase(
                        hiddenCount,
                        '1 osobę ukryto — ma już innych rodziców.',
                        `${hiddenCount} osoby ukryto — mają już innych rodziców.`,
                        `${hiddenCount} osób ukryto — mają już innych rodziców.`,
                    ),
                };
            },
            pick: (candidateId) => ({
                url: PersonChildController.attach(child.parents[0].id).url,
                data: {
                    child_id: candidateId,
                    other_parent_id: child.parents[1]?.id ?? null,
                },
            }),
            form: {
                definition: PersonChildController.store.form(
                    child.parents[0].id,
                ),
                hiddenInputs: child.parents[1]
                    ? [{ name: 'other_parent_id', value: child.parents[1].id }]
                    : undefined,
                defaults: { last_name: child.default_last_name },
                genders,
                religions,
                submitTestId: 'create-child-button',
            },
        };
    }

    const { focus, target: spouse } = target;
    const isSameSex = (candidate: PersonCandidate) =>
        focus.gender !== null && candidate.gender === focus.gender;

    return {
        title: `Dodaj małżonka — ${focus.display_name}`,
        description:
            'Wybierz osobę z drzewa albo dodaj nową. Datę i miejsce ślubu uzupełnisz w podglądzie.',
        select: (list) => {
            const allowed = list.filter(
                (candidate) => !spouse.excluded_ids.includes(candidate.id),
            );
            const hiddenCount = allowed.filter(isSameSex).length;

            return {
                visible: allowed.filter((candidate) => !isSameSex(candidate)),
                hiddenCount,
                hiddenHint: pluralPhrase(
                    hiddenCount,
                    '1 osobę tej samej płci ukryto.',
                    `${hiddenCount} osoby tej samej płci ukryto.`,
                    `${hiddenCount} osób tej samej płci ukryto.`,
                ),
            };
        },
        pick: (candidateId) => ({
            url: PersonSpouseController.attach(focus.id).url,
            data: { spouse_id: candidateId },
        }),
        form: {
            definition: PersonSpouseController.store.form(focus.id),
            lockedGender:
                focus.gender === 'male'
                    ? 'female'
                    : focus.gender === 'female'
                      ? 'male'
                      : undefined,
            genders,
            religions,
            submitTestId: 'create-spouse-button',
        },
    };
}
