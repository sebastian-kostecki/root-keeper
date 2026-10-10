import { Form } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogFooter,
} from '@/components/form-dialog';
import {
    BirthFields,
    DeathFields,
    PersonalFields,
} from '@/components/persons/person-section-fields';
import { serialize } from '@/components/persons/section-card';
import {
    SectionEditorProvider,
    UnsavedChangesBar,
    useSectionEditor,
} from '@/components/persons/section-editor';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { genderCard } from '@/lib/gender-styles';
import { cn } from '@/lib/utils';
import type { Religion } from '@/types';

type Props = {
    religions: Religion[];
    trigger: React.ReactNode;
};

type Resolver = RefObject<((isSaved: boolean) => void) | null>;

/** "Dodaj osobę": the person modal shell in create mode, every card open. */
export function PersonCreateDialog(props: Props) {
    return (
        <SectionEditorProvider record={{ key: 'person', id: 0 }}>
            <PersonCreateDialogContent {...props} />
        </SectionEditorProvider>
    );
}

function Card({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section
            aria-label={title}
            className="bg-card space-y-4 rounded-lg border p-4"
        >
            <h4 className="font-medium">{title}</h4>
            {children}
        </section>
    );
}

function PersonCreateDialogContent({ religions, trigger }: Props) {
    const [open, setOpen] = useState(false);
    const { isDirty, hasPendingExit, requestExit } = useSectionEditor();
    const resolver: Resolver = useRef(null);

    const settle = (isSaved: boolean) => {
        resolver.current?.(isSaved);
        resolver.current = null;
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                if (isOpen) {
                    setOpen(true);

                    return;
                }

                requestExit(() => setOpen(false));
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <FormDialogContent
                className="sm:h-[90vh]"
                onEscapeKeyDown={(event) => {
                    if (isDirty) {
                        event.preventDefault();
                        requestExit(() => setOpen(false));
                    }
                }}
            >
                <div className="flex items-start gap-4 px-6 pt-6 pr-12 pb-4">
                    <div
                        aria-hidden="true"
                        className={cn(
                            'flex size-14 shrink-0 items-center justify-center rounded-full border text-lg font-semibold',
                            genderCard.unknown,
                        )}
                    >
                        ?
                    </div>
                    <div className="min-w-0 flex-1 space-y-1">
                        <DialogTitle className="text-xl leading-tight">
                            Nowa osoba
                        </DialogTitle>
                        <DialogDescription>
                            Po zapisaniu otworzy się panel tej osoby.
                        </DialogDescription>
                    </div>
                </div>

                <Form
                    {...PersonController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        settle(true);
                        setOpen(false);
                    }}
                    onError={() => settle(false)}
                    onFinish={() => settle(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <CreateFormBody
                            religions={religions}
                            errors={errors}
                            processing={processing}
                            resolver={resolver}
                            onCancel={() => requestExit(() => setOpen(false))}
                        />
                    )}
                </Form>

                {hasPendingExit && (
                    <div className="bg-background border-t px-6 py-4">
                        <UnsavedChangesBar />
                    </div>
                )}
            </FormDialogContent>
        </Dialog>
    );
}

type BodyProps = {
    religions: Religion[];
    errors: Partial<Record<string, string>>;
    processing: boolean;
    resolver: Resolver;
    onCancel: () => void;
};

function CreateFormBody({
    religions,
    errors,
    processing,
    resolver,
    onCancel,
}: BodyProps) {
    const { markDirty, registerSubmit } = useSectionEditor();
    const bodyRef = useRef<HTMLDivElement>(null);

    // Dirty = the serialised values differ from those at open. Hidden inputs
    // change without a DOM event, so their attributes are observed too.
    useEffect(() => {
        const form = bodyRef.current?.closest('form');

        if (!form) {
            return;
        }

        const initial = serialize(form);
        const check = () => markDirty(serialize(form) !== initial);
        const observer = new MutationObserver(check);

        markDirty(false);
        form.addEventListener('input', check);
        form.addEventListener('change', check);
        observer.observe(form, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['value'],
        });

        form.querySelector<HTMLElement>('input:not([type="hidden"])')?.focus();

        return () => {
            observer.disconnect();
            form.removeEventListener('input', check);
            form.removeEventListener('change', check);
            markDirty(false);
        };
    }, [markDirty]);

    // The guard bar's "Zapisz" submits this form and waits for the outcome.
    useEffect(() => {
        registerSubmit(
            () =>
                new Promise<boolean>((resolve) => {
                    const form = bodyRef.current?.closest('form');

                    if (!form || !form.reportValidity()) {
                        resolve(false);

                        return;
                    }

                    resolver.current = resolve;
                    form.requestSubmit();
                }),
        );

        return () => registerSubmit(null);
    }, [registerSubmit, resolver]);

    // Inertia updates `errors` after this component renders, so wait a frame.
    useEffect(() => {
        if (Object.keys(errors).length === 0) {
            return;
        }

        requestAnimationFrame(() => {
            bodyRef.current
                ?.querySelector('[data-slot="input-error"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    }, [errors]);

    return (
        <>
            <FormDialogBody ref={bodyRef}>
                <Card title="Dane osobowe">
                    <PersonalFields religions={religions} errors={errors} />
                </Card>
                <Card title="Urodzenie">
                    <BirthFields errors={errors} birth={null} />
                </Card>
                <Card title="Zgon">
                    <DeathFields
                        errors={errors}
                        death={null}
                        burial={null}
                        isDeceased={false}
                    />
                </Card>
            </FormDialogBody>

            <FormDialogFooter>
                <Button type="button" variant="secondary" onClick={onCancel}>
                    Anuluj
                </Button>
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="create-person-button"
                >
                    Dodaj osobę
                </Button>
            </FormDialogFooter>
        </>
    );
}
