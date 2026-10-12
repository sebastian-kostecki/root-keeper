import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ParishController from '@/actions/App/Http/Controllers/ParishController';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
import { DeleteParishDialog } from '@/components/parishes/delete-parish-dialog';
import { MergeParishDialog } from '@/components/parishes/merge-parish-dialog';
import { SidePanel } from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { LocalityOption, Parish, ReligionOption } from '@/types';

type Props = {
    /** The record to edit, or null for a new one. */
    parish: Parish | null;
    /** The whole register, offered as the target of a merge. */
    parishes: Parish[];
    religions: ReligionOption[];
    localities: LocalityOption[];
    onRequestClose: () => void;
    /** Reports whether the form holds unsaved changes. */
    onDirtyChange: (isDirty: boolean) => void;
    /** A locality added in the seat picker, so the caller can refresh its list. */
    onLocalityCreated?: (locality: LocalityOption) => void;
};

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

/** Name, religion and seat form in the side panel; one component for „Nowa parafia” and editing. */
export function ParishPanel({
    parish,
    parishes,
    religions,
    localities,
    onRequestClose,
    onDirtyChange,
    onLocalityCreated,
}: Props) {
    return (
        <SidePanel
            title={parish?.name ?? 'Nowa parafia'}
            contentKey={parish?.id ?? 'new'}
            onRequestClose={onRequestClose}
        >
            <ParishForm
                key={parish?.id ?? 'new'}
                parish={parish}
                parishes={parishes}
                religions={religions}
                localities={localities}
                onDirtyChange={onDirtyChange}
                onLocalityCreated={onLocalityCreated}
            />
        </SidePanel>
    );
}

function ParishForm({
    parish,
    parishes,
    religions,
    localities,
    onDirtyChange,
    onLocalityCreated,
}: Omit<Props, 'onRequestClose'>) {
    const [religion, setReligion] = useState(parish?.religion ?? '');
    const [mergeOpen, setMergeOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const seat =
        localities.find((locality) => locality.id === parish?.locality_id) ??
        null;

    return (
        <>
            <Form
                id="parish-form"
                {...(parish === null
                    ? ParishController.store.form()
                    : ParishController.update.form(parish.id))}
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
                            <Label htmlFor="parish-name">Nazwa</Label>
                            <Input
                                id="parish-name"
                                name="name"
                                defaultValue={parish?.name ?? ''}
                                maxLength={255}
                                required
                                aria-invalid={errors.name ? true : undefined}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="parish-religion">Wyznanie</Label>
                            <Select
                                value={religion || NONE}
                                onValueChange={(next) =>
                                    setReligion(next === NONE ? '' : next)
                                }
                            >
                                <SelectTrigger
                                    id="parish-religion"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="brak" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>brak</SelectItem>
                                    {religions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="religion"
                                value={religion}
                            />
                            <InputError message={errors.religion} />
                        </div>

                        <LocalityPicker
                            id="parish-locality"
                            name="locality_id"
                            label="Miejscowość siedziby"
                            value={seat}
                            options={localities}
                            errors={errors}
                            onCreated={onLocalityCreated}
                        />

                        <div className="flex flex-wrap items-center gap-2">
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Zapisz
                            </Button>

                            {parish !== null && (
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

            {parish !== null && (
                <>
                    <MergeParishDialog
                        parish={parish}
                        parishes={parishes}
                        open={mergeOpen}
                        onOpenChange={setMergeOpen}
                    />
                    <DeleteParishDialog
                        parish={parish}
                        open={deleteOpen}
                        onOpenChange={setDeleteOpen}
                        onMerge={() => {
                            setDeleteOpen(false);
                            setMergeOpen(true);
                        }}
                    />
                </>
            )}
        </>
    );
}
