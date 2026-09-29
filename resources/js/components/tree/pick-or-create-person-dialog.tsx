import { Form, router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { useEffect, useRef, useState } from 'react';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogFooter,
    FormDialogHeader,
} from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PersonCandidateList } from '@/components/persons/person-candidate-list';
import { PersonFormFields } from '@/components/persons/person-form-fields';
import { Button } from '@/components/ui/button';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import { useOptionalList } from '@/hooks/use-relation-candidates';
import type { Gender, PersonCandidate, Religion } from '@/types';

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

type Props = {
    /** Rendered through `DialogTrigger asChild`; omit it for a controlled dialog. */
    trigger?: React.ReactNode;
    /** When given, the dialog is controlled by the caller. */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    /** Where focus goes on close, for a dialog without a trigger of its own. */
    onCloseAutoFocus?: (event: Event) => void;
    title: string;
    description: string;
    candidates: PersonCandidate[] | undefined;
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

/**
 * A dialog that lists the people of the tree to pick from or, when `form` is
 * given, switches to a new-person form. The client only hides what the caller
 * filters out; the server stays the authority.
 */
export function PickOrCreatePersonDialog({
    trigger,
    open: controlledOpen,
    onOpenChange,
    onCloseAutoFocus,
    title,
    description,
    candidates,
    select,
    pick,
    form,
}: Props) {
    const [internalOpen, setInternalOpen] = useState(false);
    const open = controlledOpen ?? internalOpen;
    const [wasOpen, setWasOpen] = useState(false);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>(undefined);
    const [filter, setFilter] = useState('');
    const [view, setView] = useState<'list' | 'form'>('list');
    const bodyRef = useRef<HTMLDivElement>(null);
    const { list, loading, loaded, load } = useOptionalList(
        'personCandidates',
        candidates,
    );

    const setOpen = (nextOpen: boolean) => {
        setInternalOpen(nextOpen);
        onOpenChange?.(nextOpen);
    };

    // Runs on every transition to open, whichever side opened the dialog.
    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setError(undefined);
            setFilter('');
            setView('list');
        }
    }

    useEffect(() => {
        if (open) {
            load();
        }
    }, [open]);

    const scrollFirstErrorIntoView = () => {
        requestAnimationFrame(() => {
            bodyRef.current
                ?.querySelector('[data-slot="input-error"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    };

    const { visible, hiddenCount, hiddenHint } = select(list ?? []);

    const handleSelect = (candidateId: number) => {
        setProcessingId(candidateId);
        setError(undefined);

        const { url, data } = pick(candidateId);

        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errors) =>
                setError(
                    errors.relation ??
                        errors.child_id ??
                        errors.parent_id ??
                        errors.other_parent_id ??
                        errors.spouse_id,
                ),
            onFinish: () => setProcessingId(null),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {trigger !== undefined && (
                <DialogTrigger asChild>{trigger}</DialogTrigger>
            )}
            <FormDialogContent onCloseAutoFocus={onCloseAutoFocus}>
                <FormDialogHeader title={title} description={description} />

                {view === 'list' || form === undefined ? (
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
                                            {hiddenHint}
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
                            {form !== undefined && (
                                <Button
                                    type="button"
                                    onClick={() => setView('form')}
                                >
                                    Nowa osoba
                                </Button>
                            )}
                        </FormDialogFooter>
                    </>
                ) : (
                    <Form
                        {...form.definition}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        onError={scrollFirstErrorIntoView}
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormDialogBody ref={bodyRef}>
                                    {form.hiddenInputs?.map((input) => (
                                        <input
                                            key={input.name}
                                            type="hidden"
                                            name={input.name}
                                            value={input.value}
                                        />
                                    ))}
                                    <PersonFormFields
                                        genders={form.genders}
                                        religions={form.religions}
                                        errors={errors}
                                        lockedGender={form.lockedGender}
                                        defaults={form.defaults}
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
                                        data-test={form.submitTestId}
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
