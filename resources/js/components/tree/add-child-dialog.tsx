import PersonChildController from '@/actions/App/Http/Controllers/PersonChildController';
import { PickOrCreatePersonDialog } from '@/components/tree/pick-or-create-person-dialog';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { pluralPhrase } from '@/lib/plural';
import type {
    AddChildTarget,
    Gender,
    PersonCandidate,
    Religion,
} from '@/types';

type Props = {
    target: AddChildTarget;
    candidates: PersonCandidate[] | undefined;
    genders: Gender[];
    religions: Religion[];
};

/**
 * The "Dodaj dziecko" card of the children row and its dialog: pick an
 * existing person of the tree to become the child of the shown pair, or add
 * a new one with the default surname. People the sheet marks as excluded or
 * blocked are not offered; the server stays the authority.
 */
export function AddChildDialog({
    target,
    candidates,
    genders,
    religions,
}: Props) {
    const names = target.parents
        .map((parent) => parent.display_name)
        .join(' i ');

    return (
        <PickOrCreatePersonDialog
            trigger={
                <UnknownPersonCard
                    label="Dodaj dziecko"
                    addLabel={`Dodaj dziecko: ${names}`}
                />
            }
            title={`Dodaj dziecko — ${names}`}
            description={
                target.parents.length > 1
                    ? `Rodzice: ${names}. Wybierz osobę z drzewa albo dodaj nową.`
                    : `Rodzic: ${names} (drugi nieznany). Wybierz osobę z drzewa albo dodaj nową.`
            }
            candidates={candidates}
            select={(list) => {
                const hiddenCount = list.filter((candidate) =>
                    target.blocked_ids.includes(candidate.id),
                ).length;

                return {
                    visible: list.filter(
                        (candidate) =>
                            !target.excluded_ids.includes(candidate.id) &&
                            !target.blocked_ids.includes(candidate.id),
                    ),
                    hiddenCount,
                    hiddenHint: pluralPhrase(
                        hiddenCount,
                        '1 osobę ukryto — ma już innych rodziców.',
                        `${hiddenCount} osoby ukryto — mają już innych rodziców.`,
                        `${hiddenCount} osób ukryto — mają już innych rodziców.`,
                    ),
                };
            }}
            pick={(candidateId) => ({
                url: PersonChildController.attach(target.parents[0].id).url,
                data: {
                    child_id: candidateId,
                    other_parent_id: target.parents[1]?.id ?? null,
                },
            })}
            form={{
                definition: PersonChildController.store.form(
                    target.parents[0].id,
                ),
                hiddenInputs: target.parents[1]
                    ? [{ name: 'other_parent_id', value: target.parents[1].id }]
                    : undefined,
                defaults: { last_name: target.default_last_name },
                genders,
                religions,
                submitTestId: 'create-child-button',
            }}
        />
    );
}
