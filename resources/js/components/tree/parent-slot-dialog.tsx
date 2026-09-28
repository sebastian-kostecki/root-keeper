import { Form, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ParentChildRelationController from '@/actions/App/Http/Controllers/ParentChildRelationController';
import PersonParentController from '@/actions/App/Http/Controllers/PersonParentController';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogFooter,
    FormDialogHeader,
} from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PersonCandidateList } from '@/components/persons/person-candidate-list';
import { PersonFormFields } from '@/components/persons/person-form-fields';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { Button } from '@/components/ui/button';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import { useOptionalList } from '@/hooks/use-relation-candidates';
import type {
    Gender,
    ParentCandidate,
    ParentSlotRole,
    ParentSlotTarget,
    Religion,
} from '@/types';

type Props = {
    label: string;
    slot: ParentSlotTarget;
    candidates: ParentCandidate[] | undefined;
    genders: Gender[];
    religions: Religion[];
};

const ROLE_TITLE: Record<ParentSlotRole, string> = {
    father: 'Dodaj ojca',
    mother: 'Dodaj matkę',
    parent: 'Dodaj rodzica',
};

const ROLE_GENDER: Record<ParentSlotRole, 'male' | 'female' | null> = {
    father: 'male',
    mother: 'female',
    parent: null,
};

/** 1 → osobę, 2–4 (except 12–14) → osoby, otherwise osób. */
function pluralPeople(count: number): string {
    const lastTwo = count % 100;
    const last = count % 10;

    if (count === 1) {
        return 'osobę';
    }

    return last >= 2 && last <= 4 && !(lastTwo >= 12 && lastTwo <= 14)
        ? 'osoby'
        : 'osób';
}

/**
 * The empty parent slot and its dialog: pick an existing person of the tree
 * to link as the slot's parent. The client only hides people the rules and
 * the slot's sex exclude; the server stays the authority.
 */
export function ParentSlotDialog({
    label,
    slot,
    candidates,
    genders,
    religions,
}: Props) {
    const [open, setOpen] = useState(false);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>(undefined);
    const [filter, setFilter] = useState('');
    const [view, setView] = useState<'list' | 'form'>('list');
    const bodyRef = useRef<HTMLDivElement>(null);
    const { list, loading, loaded, load } = useOptionalList(
        'parentCandidates',
        candidates,
    );

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setError(undefined);
            setFilter('');
            setView('list');
            load();
        }
    };

    const scrollFirstErrorIntoView = () => {
        requestAnimationFrame(() => {
            bodyRef.current
                ?.querySelector('[data-slot="input-error"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    };

    const requiredGender = ROLE_GENDER[slot.role];
    const notExcluded = (list ?? []).filter(
        (candidate) => !slot.excluded_ids.includes(candidate.id),
    );
    const visible = notExcluded.filter(
        (candidate) =>
            requiredGender === null || candidate.gender === requiredGender,
    );
    const hiddenCount =
        requiredGender === null
            ? 0
            : notExcluded.filter((candidate) => candidate.gender === null)
                  .length;

    const handleSelect = (candidateId: number) => {
        setProcessingId(candidateId);
        setError(undefined);

        router.post(
            ParentChildRelationController.store().url,
            { parent_id: candidateId, child_id: slot.id, role: slot.role },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (errors) =>
                    setError(
                        errors.relation ?? errors.parent_id ?? errors.child_id,
                    ),
                onFinish: () => setProcessingId(null),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <UnknownPersonCard label={label} parentOf={slot} />
            </DialogTrigger>
            <FormDialogContent>
                <FormDialogHeader
                    title={`${ROLE_TITLE[slot.role]} — ${slot.display_name}`}
                    description="Wybierz osobę z drzewa albo dodaj nową."
                />

                {view === 'list' ? (
                    <>
                        <FormDialogBody>
                            <PersonCandidateList
                                loading={loading}
                                loaded={loaded}
                                list={visible}
                                filter={filter}
                                onFilterChange={setFilter}
                                onSelect={handleSelect}
                                processingId={processingId}
                                emptyState={
                                    <p className="text-muted-foreground text-sm">
                                        Brak pasujących osób w drzewie.
                                    </p>
                                }
                                footer={
                                    hiddenCount > 0 && (
                                        <p className="text-muted-foreground text-xs">
                                            {hiddenCount}{' '}
                                            {pluralPeople(hiddenCount)} bez
                                            podanej płci ukryto — uzupełnij
                                            płeć, aby je tu zobaczyć.
                                        </p>
                                    )
                                }
                            />

                            <InputError message={error} />
                        </FormDialogBody>

                        <FormDialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setOpen(false)}
                            >
                                Zamknij
                            </Button>
                            <Button
                                type="button"
                                onClick={() => setView('form')}
                            >
                                Nowa osoba
                            </Button>
                        </FormDialogFooter>
                    </>
                ) : (
                    <Form
                        {...PersonParentController.store.form(slot.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        onError={scrollFirstErrorIntoView}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormDialogBody ref={bodyRef}>
                                    <input
                                        type="hidden"
                                        name="role"
                                        value={slot.role}
                                    />
                                    <PersonFormFields
                                        genders={genders}
                                        religions={religions}
                                        errors={errors}
                                        lockedGender={
                                            requiredGender ?? undefined
                                        }
                                        defaults={
                                            slot.role === 'father'
                                                ? { last_name: slot.last_name }
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        message={errors.relation ?? errors.role}
                                    />
                                </FormDialogBody>

                                <FormDialogFooter>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setView('list')}
                                    >
                                        Wróć do listy
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="create-parent-button"
                                    >
                                        Zapisz
                                    </Button>
                                </FormDialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </FormDialogContent>
        </Dialog>
    );
}
