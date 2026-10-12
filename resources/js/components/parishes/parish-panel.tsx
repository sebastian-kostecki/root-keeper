import { Form } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import ParishController from '@/actions/App/Http/Controllers/ParishController';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
import { DeleteParishDialog } from '@/components/parishes/delete-parish-dialog';
import { MergeParishDialog } from '@/components/parishes/merge-parish-dialog';
import { ProcessingReporter } from '@/components/processing-reporter';
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
    const formId = 'parish-form';
    const [mergeOpen, setMergeOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
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
        isDirty && parish !== null ? 'panel-actions-hint' : undefined;

    return (
        <SidePanel
            title={parish?.name ?? 'Nowa parafia'}
            contentKey={parish?.id ?? 'new'}
            onRequestClose={onRequestClose}
            actions={
                parish !== null && (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={isDirty}
                            aria-describedby={actionHintId}
                            onClick={() => setMergeOpen(true)}
                        >
                            Scal z…
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="text-destructive-foreground hover:text-destructive-foreground"
                            disabled={isDirty}
                            aria-describedby={actionHintId}
                            onClick={() => setDeleteOpen(true)}
                        >
                            Usuń parafię
                        </Button>
                    </>
                )
            }
            footer={
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" form={formId} disabled={processing}>
                        {processing && <Spinner />}
                        Zapisz parafię
                    </Button>
                    {isDirty && parish !== null && (
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
            <ParishForm
                key={parish?.id ?? 'new'}
                parish={parish}
                religions={religions}
                localities={localities}
                onDirtyChange={reportDirty}
                onProcessingChange={setProcessing}
                onLocalityCreated={onLocalityCreated}
            />

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
        </SidePanel>
    );
}

type ParishFormProps = {
    parish: Parish | null;
    religions: ReligionOption[];
    localities: LocalityOption[];
    onDirtyChange: (isDirty: boolean) => void;
    onProcessingChange: (processing: boolean) => void;
    onLocalityCreated?: (locality: LocalityOption) => void;
};

/** The fields and the form itself; the buttons live in the panel's header and footer. */
function ParishForm({
    parish,
    religions,
    localities,
    onDirtyChange,
    onProcessingChange,
    onLocalityCreated,
}: ParishFormProps) {
    const [religion, setReligion] = useState(parish?.religion ?? '');
    const seat =
        localities.find((locality) => locality.id === parish?.locality_id) ??
        null;

    return (
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
                    <ProcessingReporter
                        processing={processing}
                        onChange={onProcessingChange}
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
                        <input type="hidden" name="religion" value={religion} />
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
                </>
            )}
        </Form>
    );
}
