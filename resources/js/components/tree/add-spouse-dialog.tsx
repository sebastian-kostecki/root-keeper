import PersonSpouseController from '@/actions/App/Http/Controllers/PersonSpouseController';
import { PickOrCreatePersonDialog } from '@/components/tree/pick-or-create-person-dialog';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { pluralPhrase } from '@/lib/plural';
import type {
    AddSpouseTarget,
    Gender,
    PersonCandidate,
    Religion,
    SheetPerson,
} from '@/types';

type Props = {
    focus: SheetPerson;
    target: AddSpouseTarget;
    candidates: PersonCandidate[] | undefined;
    genders: Gender[];
    religions: Religion[];
    /**
     * `slot`: the card of the empty partner cell, with its own trigger;
     * `menu`: no trigger, opened by the union node's menu through `open`.
     */
    variant: 'slot' | 'menu';
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    onCloseAutoFocus?: (event: Event) => void;
};

/**
 * The "Dodaj małżonka" trigger and its dialog: pick an existing person of the
 * tree to marry the centre person, or add a new one with the opposite sex. People the sheet marks as excluded and
 * people of the centre's known sex are not offered; the server stays the
 * authority.
 */
export function AddSpouseDialog({
    focus,
    target,
    candidates,
    genders,
    religions,
    variant,
    open,
    onOpenChange,
    onCloseAutoFocus,
}: Props) {
    const addLabel = `Dodaj małżonka: ${focus.display_name}`;
    const isSameSex = (candidate: PersonCandidate) =>
        focus.gender !== null && candidate.gender === focus.gender;

    return (
        <PickOrCreatePersonDialog
            trigger={
                variant === 'slot' ? (
                    <UnknownPersonCard
                        label="Dodaj małżonka"
                        addLabel={addLabel}
                    />
                ) : undefined
            }
            open={variant === 'menu' ? open : undefined}
            onOpenChange={onOpenChange}
            onCloseAutoFocus={onCloseAutoFocus}
            title={`Dodaj małżonka — ${focus.display_name}`}
            description="Wybierz osobę z drzewa albo dodaj nową. Datę i miejsce ślubu uzupełnisz w podglądzie."
            candidates={candidates}
            select={(list) => {
                const allowed = list.filter(
                    (candidate) => !target.excluded_ids.includes(candidate.id),
                );
                const hiddenCount = allowed.filter(isSameSex).length;

                return {
                    visible: allowed.filter(
                        (candidate) => !isSameSex(candidate),
                    ),
                    hiddenCount,
                    hiddenHint: pluralPhrase(
                        hiddenCount,
                        '1 osobę tej samej płci ukryto.',
                        `${hiddenCount} osoby tej samej płci ukryto.`,
                        `${hiddenCount} osób tej samej płci ukryto.`,
                    ),
                };
            }}
            pick={(candidateId) => ({
                url: PersonSpouseController.attach(focus.id).url,
                data: { spouse_id: candidateId },
            })}
            form={{
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
            }}
        />
    );
}
