import { Form } from '@inertiajs/react';
import { useState } from 'react';
import LocalityController from '@/actions/App/Http/Controllers/LocalityController';
import { DeleteLocalityDialog } from '@/components/localities/delete-locality-dialog';
import { MergeLocalityDialog } from '@/components/localities/merge-locality-dialog';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
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

    return (
        <SidePanel
            title={locality?.name ?? 'Nowa miejscowość'}
            contentKey={locality?.id ?? 'new'}
            onRequestClose={onRequestClose}
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
                {({ errors, processing, isDirty }) => (
                    <>
                        <DirtyReporter
                            isDirty={isDirty}
                            onDirtyChange={onDirtyChange}
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

                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Zapisz
                            </Button>

                            {locality !== null && (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={isDirty}
                                        aria-describedby={
                                            isDirty
                                                ? 'panel-actions-hint'
                                                : undefined
                                        }
                                        onClick={() => setMergeOpen(true)}
                                    >
                                        Scal z…
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-destructive hover:text-destructive"
                                        disabled={isDirty}
                                        aria-describedby={
                                            isDirty
                                                ? 'panel-actions-hint'
                                                : undefined
                                        }
                                        onClick={() => setDeleteOpen(true)}
                                    >
                                        Usuń
                                    </Button>
                                </>
                            )}
                        </div>

                        {isDirty && (
                            <p
                                id="panel-actions-hint"
                                className="text-muted-foreground text-sm"
                            >
                                Zapisz zmiany, aby scalić lub usunąć.
                            </p>
                        )}
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
