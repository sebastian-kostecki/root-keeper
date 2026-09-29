import { ArrowLeft, PencilIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { FormDialogFooter } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { useSectionEditor } from '@/components/persons/section-editor';
import type { SectionId } from '@/components/persons/section-editor';
import { Button } from '@/components/ui/button';
import type { SectionValues } from '@/lib/person-payload';
import { cn } from '@/lib/utils';

type Props = {
    id: SectionId;
    title: string;
    badge?: ReactNode;
    /** Read-only content shown outside edit mode. */
    view: ReactNode;
    /** Form fields shown in edit mode. */
    edit: ReactNode;
    /** Resolves true when the server accepted the values. */
    onSubmit: (values: SectionValues) => Promise<boolean>;
    processing: boolean;
    /** Server error that belongs to no field of this section. */
    error?: string;
};

export function serialize(form: HTMLFormElement): string {
    return JSON.stringify(Array.from(new FormData(form).entries()));
}

function readValues(form: HTMLFormElement): SectionValues {
    const values: SectionValues = {};

    new FormData(form).forEach((value, key) => {
        if (typeof value === 'string') {
            values[key] = value;
        }
    });

    return values;
}

/**
 * Card that flips between a read-only view and an inline form. Only one card
 * of the modal edits at a time; below `sm` the editing card takes over the
 * whole modal body.
 */
export function SectionCard({
    id,
    title,
    badge,
    view,
    edit,
    onSubmit,
    processing,
    error,
}: Props) {
    const editor = useSectionEditor();
    const isEditing = editor.editing === id;
    const isDimmed = editor.editing !== null && !isEditing;
    const pencilRef = useRef<HTMLButtonElement>(null);
    const hasEdited = useRef(false);

    useEffect(() => {
        if (isEditing) {
            hasEdited.current = true;

            return;
        }

        if (!hasEdited.current) {
            return;
        }

        hasEdited.current = false;

        if (
            document.activeElement === null ||
            document.activeElement === document.body
        ) {
            pencilRef.current?.focus();
        }
    }, [isEditing]);

    if (isEditing) {
        return (
            <SectionCardEditor
                title={title}
                badge={badge}
                edit={edit}
                onSubmit={onSubmit}
                processing={processing}
                error={error}
            />
        );
    }

    return (
        <section
            aria-label={title}
            inert={isDimmed}
            className={cn(
                'bg-card space-y-3 rounded-lg border p-4 transition-opacity',
                isDimmed && 'opacity-50',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h4 className="font-medium">{title}</h4>
                <div className="flex items-center gap-1.5">
                    {badge}
                    <Button
                        ref={pencilRef}
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        aria-label={`Edytuj ${title}`}
                        title={`Edytuj ${title}`}
                        onClick={() => editor.start(id)}
                    >
                        <PencilIcon />
                    </Button>
                </div>
            </div>

            {view}
        </section>
    );
}

type EditorProps = Pick<
    Props,
    'title' | 'badge' | 'edit' | 'onSubmit' | 'processing' | 'error'
>;

function SectionCardEditor({
    title,
    badge,
    edit,
    onSubmit,
    processing,
    error,
}: EditorProps) {
    const { markDirty, registerSubmit, cancel, requestExit } =
        useSectionEditor();
    const formRef = useRef<HTMLFormElement>(null);
    const bodyRef = useRef<HTMLDivElement>(null);

    // Dirty = the serialised values differ from those at edit start. Hidden
    // inputs change without a DOM event, so their attributes are observed too.
    useEffect(() => {
        const form = formRef.current;

        if (form === null) {
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

        form.querySelector<HTMLElement>(
            'input:not([type="hidden"]), [role="checkbox"]',
        )?.focus();

        return () => {
            observer.disconnect();
            form.removeEventListener('input', check);
            form.removeEventListener('change', check);
            markDirty(false);
        };
    }, [markDirty]);

    const submit = async (): Promise<boolean> => {
        const form = formRef.current;

        if (form === null || !form.reportValidity()) {
            return false;
        }

        const isSaved = await onSubmit(readValues(form));

        if (isSaved) {
            cancel();
        } else {
            requestAnimationFrame(() => {
                bodyRef.current
                    ?.querySelector('[data-slot="input-error"]')
                    ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        }

        return isSaved;
    };

    // The guard bar saves through the latest submit (fresh `personForm`).
    useEffect(() => {
        registerSubmit(submit);

        return () => registerSubmit(null);
    });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        void submit();
    };

    return (
        <section
            aria-label={title}
            className={cn(
                'bg-card rounded-lg border',
                'max-sm:bg-background max-sm:fixed max-sm:inset-0 max-sm:z-20 max-sm:flex max-sm:flex-col max-sm:rounded-none max-sm:border-0',
            )}
        >
            <div className="flex items-center gap-2 border-b px-3 py-3 sm:hidden">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Wróć"
                    disabled={processing}
                    onClick={() => requestExit(cancel)}
                >
                    <ArrowLeft />
                </Button>
                <div>
                    <p className="font-medium">Edycja sekcji</p>
                    <p className="text-muted-foreground text-xs">{title}</p>
                </div>
            </div>

            <form
                ref={formRef}
                onSubmit={handleSubmit}
                className="flex min-h-0 flex-1 flex-col"
            >
                <div
                    ref={bodyRef}
                    className="space-y-4 p-4 max-sm:flex-1 max-sm:overflow-y-auto max-sm:px-6"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h4 className="font-medium max-sm:hidden">{title}</h4>
                        {badge}
                    </div>

                    {edit}

                    <InputError message={error} />
                </div>

                <FormDialogFooter className="sm:border-t-0 sm:px-4 sm:pt-0 sm:pb-4">
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={processing}
                        onClick={cancel}
                    >
                        Anuluj
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Zapisz
                    </Button>
                </FormDialogFooter>
            </form>
        </section>
    );
}
