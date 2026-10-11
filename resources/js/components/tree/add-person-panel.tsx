import { Form, router } from '@inertiajs/react';
import type { RefObject } from 'react';
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { PersonCandidateList } from '@/components/persons/person-candidate-list';
import { PersonFormFields } from '@/components/persons/person-form-fields';
import { serialize } from '@/components/persons/section-card';
import {
    SectionEditorProvider,
    UnsavedChangesBar,
    useSectionEditor,
} from '@/components/persons/section-editor';
import { addPersonConfig } from '@/components/tree/add-person-config';
import type {
    AddPersonConfig,
    PickOrCreateForm,
} from '@/components/tree/add-person-config';
import { SidePanel } from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useOptionalList } from '@/hooks/use-relation-candidates';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { resolveAddTarget } from '@/lib/add-person-target';
import type { FamilySheet, Gender, PersonCandidate, Religion } from '@/types';

type Props = {
    /** The `add` query value, e.g. `father:987`; null closes the panel. */
    value: string | null;
    sheet: FamilySheet | null;
    personCandidates: PersonCandidate[] | undefined;
    genders: Gender[];
    religions: Religion[];
};

type View = 'list' | 'form';

type Resolver = RefObject<((isSaved: boolean) => void) | null>;

/**
 * The "add a person" side panel of the tree: the people of the tree to pick
 * from, or a new-person form. Opened by `?add=<value>` from an empty slot; the
 * target is resolved from the sheet on screen, so a stale value renders nothing.
 * Its own section editor guards the form against leaving unsaved.
 */
export function AddPersonPanel({
    value,
    sheet,
    personCandidates,
    genders,
    religions,
}: Props) {
    const target = resolveAddTarget(sheet, value);

    if (value === null || target === null) {
        return null;
    }

    const config = addPersonConfig(target, { genders, religions });

    return (
        <SectionEditorProvider record={{ key: 'add', id: value }}>
            <AddPersonPanelBody
                value={value}
                config={config}
                personCandidates={personCandidates}
            />
        </SectionEditorProvider>
    );
}

type BodyProps = {
    value: string;
    config: AddPersonConfig;
    personCandidates: PersonCandidate[] | undefined;
};

function AddPersonPanelBody({ value, config, personCandidates }: BodyProps) {
    const { close } = useSidePanelNavigation('add');
    const { list, loading, loaded, load } = useOptionalList(
        'personCandidates',
        personCandidates,
    );
    const { hasPendingExit, requestExit } = useSectionEditor();
    const [view, setView] = useState<View>('list');
    const [filter, setFilter] = useState('');
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>(undefined);
    const [isSaving, setIsSaving] = useState(false);
    const [shownValue, setShownValue] = useState(value);
    const resolver: Resolver = useRef(null);
    const contentRef = useRef<HTMLDivElement>(null);
    const focusSearchOnList = useRef(false);
    const formId = useId();

    // A different slot starts again from the list, as a fresh panel would.
    if (shownValue !== value) {
        setShownValue(value);
        setView('list');
        setFilter('');
        setError(undefined);
        setProcessingId(null);
    }

    // Layout effect: the list starts loading before paint, so the "could not
    // load" state never flashes on open.
    useLayoutEffect(() => {
        load();
    }, []);

    useEffect(() => {
        if (view !== 'list' || !focusSearchOnList.current) {
            return;
        }

        focusSearchOnList.current = false;
        contentRef.current
            ?.querySelector<HTMLInputElement>('input[type="search"]')
            ?.focus();
    }, [view]);

    const settle = (isSaved: boolean) => {
        resolver.current?.(isSaved);
        resolver.current = null;
    };

    const scrollFirstErrorIntoView = () => {
        requestAnimationFrame(() => {
            contentRef.current
                ?.querySelector('[data-slot="input-error"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    };

    const showList = () => {
        focusSearchOnList.current = true;
        setView('list');
    };

    const { visible, hiddenCount, hiddenHint } = config.select(list ?? []);

    const handleSelect = (candidateId: number) => {
        setProcessingId(candidateId);
        setError(undefined);

        const { url, data } = config.pick(candidateId);

        // No onSuccess: the redirect of the server replaces this panel with the new person's.
        router.post(url, data, {
            preserveScroll: true,
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

    const { form } = config;

    const footer =
        view === 'list' ? (
            form !== undefined && (
                <div className="flex justify-end">
                    <Button type="button" onClick={() => setView('form')}>
                        Nowa osoba
                    </Button>
                </div>
            )
        ) : (
            <div className="space-y-3">
                {hasPendingExit && <UnsavedChangesBar />}
                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={isSaving}
                        onClick={() => requestExit(showList)}
                    >
                        Wróć do listy
                    </Button>
                    <Button
                        type="submit"
                        form={formId}
                        disabled={isSaving}
                        data-test={form?.submitTestId}
                    >
                        {isSaving && <Spinner />}
                        Zapisz
                    </Button>
                </div>
            </div>
        );

    return (
        <SidePanel
            variant="standard"
            title={config.title}
            description={config.description}
            contentKey={value}
            onRequestClose={() => requestExit(close)}
            footer={footer}
        >
            <div ref={contentRef} className="space-y-4">
                {view === 'list' || form === undefined ? (
                    <>
                        <PersonCandidateList
                            loading={loading}
                            loaded={loaded}
                            list={list === undefined ? undefined : visible}
                            filter={filter}
                            onFilterChange={setFilter}
                            onSelect={handleSelect}
                            onRetry={load}
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
                    </>
                ) : (
                    <Form
                        id={formId}
                        {...form.definition}
                        options={{ preserveScroll: true }}
                        onStart={() => setIsSaving(true)}
                        onSuccess={() => settle(true)}
                        onError={scrollFirstErrorIntoView}
                        onFinish={() => {
                            setIsSaving(false);
                            settle(false);
                        }}
                    >
                        {({ errors }) => (
                            <AddPersonFormBody
                                form={form}
                                errors={errors}
                                resolver={resolver}
                            />
                        )}
                    </Form>
                )}
            </div>
        </SidePanel>
    );
}

function AddPersonFormBody({
    form,
    errors,
    resolver,
}: {
    form: PickOrCreateForm;
    errors: Partial<Record<string, string>>;
    resolver: Resolver;
}) {
    const { markDirty, registerSubmit } = useSectionEditor();
    const bodyRef = useRef<HTMLDivElement>(null);

    // Dirty = the serialised values differ from those at open. Hidden inputs
    // change without a DOM event, so their attributes are observed too.
    useEffect(() => {
        const formElement = bodyRef.current?.closest('form');

        if (!formElement) {
            return;
        }

        const initial = serialize(formElement);
        const check = () => markDirty(serialize(formElement) !== initial);
        const observer = new MutationObserver(check);

        markDirty(false);
        formElement.addEventListener('input', check);
        formElement.addEventListener('change', check);
        observer.observe(formElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['value'],
        });

        formElement
            .querySelector<HTMLElement>('input:not([type="hidden"])')
            ?.focus();

        return () => {
            observer.disconnect();
            formElement.removeEventListener('input', check);
            formElement.removeEventListener('change', check);
            markDirty(false);
        };
    }, [markDirty]);

    // The guard bar's "Zapisz" submits this form and waits for the outcome.
    useEffect(() => {
        registerSubmit(
            () =>
                new Promise<boolean>((resolve) => {
                    const formElement = bodyRef.current?.closest('form');

                    if (!formElement || !formElement.reportValidity()) {
                        resolve(false);

                        return;
                    }

                    resolver.current = resolve;
                    formElement.requestSubmit();
                }),
        );

        return () => registerSubmit(null);
    }, [registerSubmit, resolver]);

    return (
        <div ref={bodyRef} className="space-y-6">
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
            <InputError message={errors.relation ?? errors.role} />
        </div>
    );
}
