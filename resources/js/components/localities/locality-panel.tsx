import { Form } from '@inertiajs/react';
import LocalityController from '@/actions/App/Http/Controllers/LocalityController';
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
    onRequestClose: () => void;
    /** Reports whether the form holds unsaved changes. */
    onDirtyChange: (isDirty: boolean) => void;
};

/** Name and description form in the side panel; one component for „Nowa miejscowość” and editing. */
export function LocalityPanel({
    locality,
    onRequestClose,
    onDirtyChange,
}: Props) {
    const formId = 'locality-form';

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

                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Zapisz
                        </Button>
                    </>
                )}
            </Form>
        </SidePanel>
    );
}
