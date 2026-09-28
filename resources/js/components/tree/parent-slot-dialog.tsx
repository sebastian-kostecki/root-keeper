import ParentChildRelationController from '@/actions/App/Http/Controllers/ParentChildRelationController';
import PersonParentController from '@/actions/App/Http/Controllers/PersonParentController';
import { PickOrCreatePersonDialog } from '@/components/tree/pick-or-create-person-dialog';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { pluralPhrase } from '@/lib/plural';
import type {
    Gender,
    ParentSlotRole,
    ParentSlotTarget,
    PersonCandidate,
    Religion,
} from '@/types';

type Props = {
    label: string;
    slot: ParentSlotTarget;
    candidates: PersonCandidate[] | undefined;
    genders: Gender[];
    religions: Religion[];
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

/**
 * The empty parent slot and its dialog: pick an existing person of the tree
 * to link as the slot's parent, or add a new one. The client only hides
 * people the rules and the slot's sex exclude; the server stays the authority.
 */
export function ParentSlotDialog({
    label,
    slot,
    candidates,
    genders,
    religions,
}: Props) {
    const requiredGender = ROLE_GENDER[slot.role];

    return (
        <PickOrCreatePersonDialog
            trigger={
                <UnknownPersonCard
                    label={label}
                    addLabel={`Dodaj ${ROLE_NOUN[slot.role]}: ${slot.display_name}`}
                />
            }
            title={`${ROLE_TITLE[slot.role]} — ${slot.display_name}`}
            description="Wybierz osobę z drzewa albo dodaj nową."
            candidates={candidates}
            select={(list) => {
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
            }}
            pick={(candidateId) => ({
                url: ParentChildRelationController.store().url,
                data: {
                    parent_id: candidateId,
                    child_id: slot.id,
                    role: slot.role,
                },
            })}
            form={{
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
            }}
        />
    );
}
