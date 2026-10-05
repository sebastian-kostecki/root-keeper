import type { FormComponentRef } from '@inertiajs/core';
import { Form } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { DeleteSourceDialog } from '@/components/sources/delete-source-dialog';
import { LegacyEntryNumber } from '@/components/sources/legacy-entry-number';
import { SourceDescriptionFields } from '@/components/sources/source-description-fields';
import { SourceKindField } from '@/components/sources/source-kind-field';
import { SourceViewer } from '@/components/sources/source-viewer';
import { SidePanel } from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { erasedRecordFields } from '@/lib/source-description';
import type { ErasedField } from '@/lib/source-description';
import { cn } from '@/lib/utils';
import type {
    LocalityOption,
    Parish,
    ReligionOption,
    Source,
    SourceConfirmation,
    SourceKind,
    SourceRecordType,
} from '@/types';

type Option<T extends string> = { value: T; label: string };

type Props = {
    /** The source to edit, or null for „Nowe źródło”. */
    source: Source | null;
    confirms: SourceConfirmation[];
    kinds: Option<SourceKind>[];
    recordTypes: Option<SourceRecordType>[];
    parishes: Parish[];
    archives: string[];
    localities: LocalityOption[];
    religions: ReligionOption[];
    onRequestClose: () => void;
    /** Reports whether the form holds unsaved changes. */
    onDirtyChange: (isDirty: boolean) => void;
};

type SourceFormData = {
    kind: SourceKind;
    name: string;
    record_type: string;
    year: string;
    parish_id: string;
    archive: string;
    url: string;
    note: string;
};

type FormHandle = FormComponentRef<SourceFormData>;

const FORM_ID = 'source-form';

/**
 * Two columns from 36rem of panel width (scan | 20rem form, the form scrolls on its
 * own); below it one column, a scan of fixed height above the form.
 */
const formLayout =
    'flex flex-col gap-6 @xl:grid @xl:h-full @xl:grid-cols-[minmax(0,1fr)_20rem] @xl:grid-rows-[minmax(0,1fr)]';
const formColumn = 'space-y-4 @xl:min-h-0 @xl:overflow-y-auto @xl:pr-1';

/** Bridges the `processing` flag of the `<Form>` out to the footer button. */
function ProcessingReporter({
    processing,
    onChange,
}: {
    processing: boolean;
    onChange: (processing: boolean) => void;
}) {
    useEffect(() => {
        onChange(processing);
    }, [processing, onChange]);

    useEffect(() => () => onChange(false), [onChange]);

    return null;
}

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

/** Scan beside the description form in the wide side panel; one component for „Nowe źródło” and editing. */
export function SourcePanel({
    source,
    confirms,
    kinds,
    recordTypes,
    parishes,
    archives,
    localities,
    religions,
    onRequestClose,
    onDirtyChange,
}: Props) {
    const [processing, setProcessing] = useState(false);
    const key = source?.id ?? 'new';

    return (
        <SidePanel
            variant="wide"
            title={source?.display_name ?? 'Nowe źródło'}
            contentKey={key}
            onRequestClose={onRequestClose}
            actions={
                source !== null && (
                    <DeleteSourceDialog
                        source={source}
                        confirms={confirms}
                        trigger={
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                            >
                                Usuń
                            </Button>
                        }
                    />
                )
            }
            footer={
                <Button type="submit" form={FORM_ID} disabled={processing}>
                    {processing && <Spinner />}
                    Zapisz
                </Button>
            }
        >
            <div className="@container h-full">
                {source === null ? (
                    <NewSourceForm
                        key={key}
                        kinds={kinds}
                        recordTypes={recordTypes}
                        parishes={parishes}
                        archives={archives}
                        localities={localities}
                        religions={religions}
                        onDirtyChange={onDirtyChange}
                        onProcessingChange={setProcessing}
                    />
                ) : (
                    <EditSourceForm
                        key={key}
                        source={source}
                        confirms={confirms}
                        kinds={kinds}
                        recordTypes={recordTypes}
                        parishes={parishes}
                        archives={archives}
                        localities={localities}
                        religions={religions}
                        onDirtyChange={onDirtyChange}
                        onProcessingChange={setProcessing}
                    />
                )}
            </div>
        </SidePanel>
    );
}

type FormProps = {
    kinds: Option<SourceKind>[];
    recordTypes: Option<SourceRecordType>[];
    parishes: Parish[];
    archives: string[];
    localities: LocalityOption[];
    religions: ReligionOption[];
    onDirtyChange: (isDirty: boolean) => void;
    onProcessingChange: (processing: boolean) => void;
};

/** Scrolls the first validation error of the panel into view. */
function scrollFirstErrorIntoView(root: HTMLElement | null) {
    requestAnimationFrame(() => {
        root?.querySelector('[data-slot="input-error"]')?.scrollIntoView({
            block: 'center',
            behavior: 'smooth',
        });
    });
}

function NewSourceForm({
    kinds,
    recordTypes,
    parishes,
    archives,
    localities,
    religions,
    onDirtyChange,
    onProcessingChange,
}: FormProps) {
    const [kind, setKind] = useState<SourceKind | ''>('');
    const [preview, setPreview] = useState<{
        url: string;
        name: string;
    } | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);
    const rootRef = useRef<HTMLDivElement>(null);

    // Releases the object URL when the file changes and when the panel unmounts.
    useEffect(
        () => () => {
            if (preview !== null) {
                URL.revokeObjectURL(preview.url);
            }
        },
        [preview],
    );

    return (
        <div ref={rootRef} className="h-full">
            <Form
                id={FORM_ID}
                {...SourceController.store.form()}
                setDefaultsOnSuccess
                options={{ preserveScroll: true }}
                onError={() => scrollFirstErrorIntoView(rootRef.current)}
                className={formLayout}
            >
                {({ errors, isDirty, processing, progress }) => (
                    <>
                        <DirtyReporter
                            isDirty={isDirty}
                            onDirtyChange={onDirtyChange}
                        />
                        <ProcessingReporter
                            processing={processing}
                            onChange={onProcessingChange}
                        />

                        <div
                            className={cn(
                                'flex min-h-0 flex-col gap-3 @xl:h-full',
                                preview !== null && 'h-96',
                            )}
                        >
                            {/* Stays mounted after a file is chosen: a chosen file cannot be put back into the form. */}
                            <div
                                className={cn(
                                    'grid gap-2',
                                    preview !== null && 'hidden',
                                )}
                            >
                                <Label htmlFor="file">Plik</Label>
                                <Input
                                    ref={fileRef}
                                    id="file"
                                    name="file"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];

                                        setPreview(
                                            file
                                                ? {
                                                      url: URL.createObjectURL(
                                                          file,
                                                      ),
                                                      name: file.name,
                                                  }
                                                : null,
                                        );
                                    }}
                                />
                                <p className="text-muted-foreground text-sm">
                                    JPG, PNG lub WebP, do 20 MB
                                </p>
                            </div>
                            <InputError message={errors.file} />

                            {preview !== null && (
                                <>
                                    <div className="min-h-0 flex-1">
                                        <SourceViewer
                                            src={preview.url}
                                            alt={preview.name}
                                        />
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                fileRef.current?.click()
                                            }
                                        >
                                            Zmień plik
                                        </Button>
                                        {progress && (
                                            <div className="flex min-w-0 flex-1 items-center gap-2">
                                                <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                                    <div
                                                        className="bg-primary h-full transition-all"
                                                        style={{
                                                            width: `${progress.percentage ?? 0}%`,
                                                        }}
                                                    />
                                                </div>
                                                <p className="text-muted-foreground text-sm">
                                                    {progress.percentage ?? 0}%
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                        </div>

                        <div className={formColumn}>
                            <SourceKindField
                                kinds={kinds}
                                error={errors.kind}
                                onValueChange={setKind}
                            />

                            <p className="text-sm font-medium">
                                Opis (opcjonalnie)
                            </p>

                            <SourceDescriptionFields
                                kind={kind}
                                recordTypes={recordTypes}
                                parishes={parishes}
                                archives={archives}
                                localities={localities}
                                religions={religions}
                                errors={errors}
                                fileName={preview?.name}
                            />
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}

function EditSourceForm({
    source,
    confirms,
    kinds,
    recordTypes,
    parishes,
    archives,
    localities,
    religions,
    onDirtyChange,
    onProcessingChange,
}: FormProps & { source: Source; confirms: SourceConfirmation[] }) {
    const [kind, setKind] = useState<SourceKind>(source.kind);
    const [erased, setErased] = useState<ErasedField[]>([]);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const formRef = useRef<FormHandle>(null);
    const discardRef = useRef<HTMLInputElement>(null);
    const rootRef = useRef<HTMLDivElement>(null);

    const computeErased = (selectedKind: SourceKind | '') =>
        erasedRecordFields(
            source,
            selectedKind,
            formRef.current?.getData() ?? {},
            recordTypes,
            parishes,
        );

    const confirmAndSubmit = () => {
        if (discardRef.current) {
            discardRef.current.value = '1';
        }

        setConfirmOpen(false);
        formRef.current?.submit();
    };

    return (
        <div ref={rootRef} className="h-full">
            <Form<SourceFormData>
                id={FORM_ID}
                {...SourceController.update.form(source.id)}
                ref={formRef}
                setDefaultsOnSuccess
                options={{ preserveScroll: true }}
                onBefore={() => {
                    const data = formRef.current?.getData();
                    const selectedKind = (data?.kind ?? '') as SourceKind | '';
                    const list = computeErased(selectedKind);

                    setErased(list);

                    if (list.length > 0 && discardRef.current?.value !== '1') {
                        setConfirmOpen(true);

                        return false;
                    }
                }}
                onSuccess={() => {
                    setErased([]);
                    setConfirmOpen(false);
                }}
                onError={() => scrollFirstErrorIntoView(rootRef.current)}
                onFinish={() => {
                    if (discardRef.current) {
                        discardRef.current.value = '0';
                    }
                }}
                className={formLayout}
            >
                {({ errors, isDirty, processing }) => (
                    <>
                        <DirtyReporter
                            isDirty={isDirty}
                            onDirtyChange={onDirtyChange}
                        />
                        <ProcessingReporter
                            processing={processing}
                            onChange={onProcessingChange}
                        />

                        <div className="h-96 min-h-0 @xl:h-full">
                            <SourceViewer
                                src={SourceController.file.url(source.id)}
                                alt={source.display_name}
                            />
                        </div>

                        <div className={formColumn}>
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
                                parishes={parishes}
                                archives={archives}
                                localities={localities}
                                religions={religions}
                                errors={errors}
                                defaults={source}
                                notice={
                                    erased.length > 0 ? (
                                        <div className="text-destructive space-y-1 text-sm">
                                            <p className="font-medium">
                                                Te pola zostaną usunięte przy
                                                zapisie:
                                            </p>
                                            <ErasedFieldList erased={erased} />
                                        </div>
                                    ) : null
                                }
                            />

                            <LegacyEntryNumber source={source} />

                            <Card>
                                <CardContent className="space-y-2">
                                    <p className="text-muted-foreground text-sm">
                                        Potwierdza
                                    </p>
                                    {confirms.length === 0 ? (
                                        <p className="text-sm">
                                            To źródło nie potwierdza jeszcze
                                            żadnego wydarzenia.
                                        </p>
                                    ) : (
                                        <ul className="space-y-1">
                                            {confirms.map((confirmation) => (
                                                <li
                                                    key={`${confirmation.event_id}-${confirmation.person.id}`}
                                                    className="text-sm"
                                                >
                                                    <PersonPreviewLink
                                                        personId={
                                                            confirmation.person
                                                                .id
                                                        }
                                                        className="text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {
                                                            confirmation.event_label
                                                        }{' '}
                                                        —{' '}
                                                        {
                                                            confirmation.person
                                                                .display_name
                                                        }
                                                    </PersonPreviewLink>
                                                    {confirmation.entry_number && (
                                                        <span className="text-muted-foreground">
                                                            {' · '}nr{' '}
                                                            {
                                                                confirmation.entry_number
                                                            }
                                                        </span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
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
        </div>
    );
}
