import { Form } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import LocalityController from '@/actions/App/Http/Controllers/LocalityController';
import { DeleteLocalityDialog } from '@/components/localities/delete-locality-dialog';
import { MergeLocalityDialog } from '@/components/localities/merge-locality-dialog';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { ProcessingReporter } from '@/components/processing-reporter';
import { SidePanel } from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { Locality } from '@/types';

type Props = {
    /** The record to edit, or null for a new one. */
    locality: Locality | null;
    /** The whole register, offered as the target of a merge. */
    localities: Locality[];
    onRequestClose: () => void;
    /** Reports whether the form holds unsaved changes. */
    onDirtyChange: (isDirty: boolean) => void;
};

/** Name and description form in the side panel; one component for „Nowa miejscowość” and editing. */
export function LocalityPanel({
    locality,
    localities,
    onRequestClose,
    onDirtyChange,
}: Props) {
    const formId = 'locality-form';
    const [mergeOpen, setMergeOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    // The dialogs act on the open record; a different record must not inherit an open one.
    const recordId = locality?.id ?? null;
    const [dialogRecordId, setDialogRecordId] = useState(recordId);
    if (dialogRecordId !== recordId) {
        setDialogRecordId(recordId);
        setMergeOpen(false);
        setDeleteOpen(false);
    }
    const [isDirty, setIsDirty] = useState(false);
    const [processing, setProcessing] = useState(false);

    // Stable identity: DirtyReporter calls it again whenever its own identity changes.
    const reportDirty = useCallback(
        (next: boolean) => {
            setIsDirty(next);
            onDirtyChange(next);
        },
        [onDirtyChange],
    );

    const actionHintId =
        isDirty && locality !== null ? 'panel-actions-hint' : undefined;

    return (
        <SidePanel
            title={locality?.name ?? 'Nowa miejscowość'}
            contentKey={locality?.id ?? 'new'}
            onRequestClose={onRequestClose}
            actions={
                locality !== null && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="text-destructive-foreground hover:text-destructive-foreground"
                        disabled={isDirty}
                        aria-describedby={actionHintId}
                        onClick={() => setDeleteOpen(true)}
                    >
                        Usuń miejscowość
                    </Button>
                )
            }
            footer={
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" form={formId} disabled={processing}>
                        {processing && <Spinner />}
                        Zapisz miejscowość
                    </Button>
                    {locality !== null && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isDirty}
                            aria-describedby={actionHintId}
                            onClick={() => setMergeOpen(true)}
                        >
                            Scal z…
                        </Button>
                    )}
                    {isDirty && locality !== null && (
                        <p
                            id="panel-actions-hint"
                            className="text-muted-foreground text-sm"
                        >
                            Zapisz zmiany, aby scalić lub usunąć.
                        </p>
                    )}
                </div>
            }
        >
            <Form
                key={locality?.id ?? 'new'}
                id={formId}
                {...(locality === null
                    ? LocalityController.store.form()
                    : LocalityController.update.form(locality.id))}
                options={{ preserveScroll: true }}
                setDefaultsOnSuccess
                className="space-y-4"
            >
                {({
                    errors,
                    isDirty: formIsDirty,
                    processing: formProcessing,
                }) => (
                    <>
                        <DirtyReporter
                            isDirty={formIsDirty}
                            onDirtyChange={reportDirty}
                        />
                        <ProcessingReporter
                            processing={formProcessing}
                            onChange={setProcessing}
                        />

                        <div className="space-y-2">
                            <Label htmlFor="locality-name">Nazwa</Label>
                            <Input
                                id="locality-name"
                                name="name"
                                defaultValue={locality?.name ?? ''}
                                maxLength={255}
                                required
                                aria-invalid={errors.name ? true : undefined}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="locality-description">Opis</Label>
                            <Textarea
                                id="locality-description"
                                name="description"
                                defaultValue={locality?.description ?? ''}
                                rows={6}
                                maxLength={5000}
                                aria-invalid={
                                    errors.description ? true : undefined
                                }
                            />
                            <InputError message={errors.description} />
                        </div>
                    </>
                )}
            </Form>

            {locality !== null && (
                <>
                    <MergeLocalityDialog
                        locality={locality}
                        localities={localities}
                        open={mergeOpen}
                        onOpenChange={setMergeOpen}
                    />
                    <DeleteLocalityDialog
                        locality={locality}
                        open={deleteOpen}
                        onOpenChange={setDeleteOpen}
                        onMerge={() => {
                            setDeleteOpen(false);
                            setMergeOpen(true);
                        }}
                    />
                </>
            )}
        </SidePanel>
    );
}
