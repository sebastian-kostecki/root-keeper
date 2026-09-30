import type { FormComponentRef } from '@inertiajs/core';
import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogFooter,
    FormDialogHeader,
} from '@/components/form-dialog';
import { SourceDescriptionFields } from '@/components/sources/source-description-fields';
import { SourceKindField } from '@/components/sources/source-kind-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { erasedRecordFields } from '@/lib/source-description';
import type { ErasedField } from '@/lib/source-description';
import type { Source, SourceKind, SourceRecordType } from '@/types';

type Props = {
    source: Source;
    trigger: React.ReactNode;
    kinds: { value: SourceKind; label: string }[];
    recordTypes: { value: SourceRecordType; label: string }[];
};

type SourceFormData = {
    kind: SourceKind;
    name: string;
    record_type: string;
    entry_number: string;
    year: string;
    provenance: string;
    url: string;
    note: string;
};

type FormHandle = FormComponentRef<SourceFormData>;

function ErasedFieldList({ erased }: { erased: ErasedField[] }) {
    return (
        <ul className="list-disc space-y-1 pl-5 text-sm">
            {erased.map((field) => (
                <li key={field.field}>
                    {field.label}: {field.value}
                </li>
            ))}
        </ul>
    );
}

/** „Edytuj źródło”: correct kind and description; never erases typ or numer wpisu silently. */
export function SourceEditDialog({
    source,
    trigger,
    kinds,
    recordTypes,
}: Props) {
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState<SourceKind>(source.kind);
    const [erased, setErased] = useState<ErasedField[]>([]);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const formRef = useRef<FormHandle>(null);
    const discardRef = useRef<HTMLInputElement>(null);
    const bodyRef = useRef<HTMLDivElement>(null);

    const reset = () => {
        setKind(source.kind);
        setErased([]);
        setConfirmOpen(false);

        if (discardRef.current) {
            discardRef.current.value = '0';
        }
    };

    const computeErased = (selectedKind: SourceKind | '') =>
        erasedRecordFields(
            source,
            selectedKind,
            formRef.current?.getData() ?? {},
            recordTypes,
        );

    const scrollFirstErrorIntoView = () => {
        requestAnimationFrame(() => {
            bodyRef.current
                ?.querySelector('[data-slot="input-error"]')
                ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    };

    const confirmAndSubmit = () => {
        if (discardRef.current) {
            discardRef.current.value = '1';
        }

        setConfirmOpen(false);
        formRef.current?.submit();
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                reset();
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <FormDialogContent>
                <FormDialogHeader
                    title="Edytuj źródło"
                    description="Popraw rodzaj i opis źródła"
                />

                <Form<SourceFormData>
                    {...SourceController.update.form(source.id)}
                    ref={formRef}
                    options={{ preserveScroll: true }}
                    onBefore={() => {
                        const data = formRef.current?.getData();
                        const selectedKind = (data?.kind ?? '') as
                            | SourceKind
                            | '';
                        const list = computeErased(selectedKind);

                        setErased(list);

                        if (
                            list.length > 0 &&
                            discardRef.current?.value !== '1'
                        ) {
                            setConfirmOpen(true);

                            return false;
                        }
                    }}
                    onSuccess={() => {
                        setOpen(false);
                        reset();
                    }}
                    onError={scrollFirstErrorIntoView}
                    onFinish={() => {
                        if (discardRef.current) {
                            discardRef.current.value = '0';
                        }
                    }}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormDialogBody ref={bodyRef}>
                                <input
                                    ref={discardRef}
                                    type="hidden"
                                    name="discard_record_fields"
                                    defaultValue="0"
                                />

                                <SourceKindField
                                    kinds={kinds}
                                    defaultValue={source.kind}
                                    error={errors.kind}
                                    onValueChange={(next) => {
                                        setKind(next);
                                        setErased(computeErased(next));
                                    }}
                                />

                                <p className="text-sm font-medium">Opis</p>

                                <SourceDescriptionFields
                                    kind={kind}
                                    recordTypes={recordTypes}
                                    errors={errors}
                                    defaults={source}
                                    notice={
                                        erased.length > 0 ? (
                                            <div className="text-destructive space-y-1 text-sm">
                                                <p className="font-medium">
                                                    Te pola zostaną usunięte
                                                    przy zapisie:
                                                </p>
                                                <ErasedFieldList
                                                    erased={erased}
                                                />
                                            </div>
                                        ) : null
                                    }
                                />
                            </FormDialogBody>

                            <FormDialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Anuluj
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Zapisz
                                </Button>
                            </FormDialogFooter>
                        </>
                    )}
                </Form>

                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogContent>
                        <DialogTitle>Zmienić rodzaj źródła?</DialogTitle>
                        <DialogDescription>
                            Te pola zostaną usunięte przy zapisie:
                        </DialogDescription>
                        <div className="text-destructive">
                            <ErasedFieldList erased={erased} />
                        </div>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Anuluj
                                </Button>
                            </DialogClose>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={confirmAndSubmit}
                            >
                                Zmień rodzaj i zapisz
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </FormDialogContent>
        </Dialog>
    );
}
