import { Form } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import CemeteryController from '@/actions/App/Http/Controllers/CemeteryController';
import { DeleteCemeteryDialog } from '@/components/cemeteries/delete-cemetery-dialog';
import { MergeCemeteryDialog } from '@/components/cemeteries/merge-cemetery-dialog';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
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
import { Textarea } from '@/components/ui/textarea';
import type {
    Cemetery,
    CemeteryManagerOption,
    LocalityOption,
    ReligionOption,
} from '@/types';

type Props = {
    /** The record to edit, or null for a new one. */
    cemetery: Cemetery | null;
    /** The whole register, offered as the target of a merge. */
    cemeteries: Cemetery[];
    managers: CemeteryManagerOption[];
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

/** Name, locality, manager, religion and description form in the side panel; one component for „Nowy cmentarz” and editing. */
export function CemeteryPanel({
    cemetery,
    cemeteries,
    managers,
    religions,
    localities,
    onRequestClose,
    onDirtyChange,
    onLocalityCreated,
}: Props) {
    const formId = 'cemetery-form';
    const [mergeOpen, setMergeOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    // The dialogs act on the open record; a different record must not inherit an open one.
    const recordId = cemetery?.id ?? null;
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
        isDirty && cemetery !== null ? 'panel-actions-hint' : undefined;

    return (
        <SidePanel
            title={cemetery?.label ?? 'Nowy cmentarz'}
            contentKey={cemetery?.id ?? 'new'}
            onRequestClose={onRequestClose}
            actions={
                cemetery !== null && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="text-destructive-foreground hover:text-destructive-foreground"
                        disabled={isDirty}
                        aria-describedby={actionHintId}
                        onClick={() => setDeleteOpen(true)}
                    >
                        Usuń cmentarz
                    </Button>
                )
            }
            footer={
                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" form={formId} disabled={processing}>
                        {processing && <Spinner />}
                        Zapisz cmentarz
                    </Button>
                    {cemetery !== null && (
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
                    {isDirty && cemetery !== null && (
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
            <CemeteryForm
                key={cemetery?.id ?? 'new'}
                formId={formId}
                cemetery={cemetery}
                managers={managers}
                religions={religions}
                localities={localities}
                onDirtyChange={reportDirty}
                onProcessingChange={setProcessing}
                onLocalityCreated={onLocalityCreated}
            />

            {cemetery !== null && (
                <>
                    <MergeCemeteryDialog
                        cemetery={cemetery}
                        cemeteries={cemeteries}
                        open={mergeOpen}
                        onOpenChange={setMergeOpen}
                    />
                    <DeleteCemeteryDialog
                        cemetery={cemetery}
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

type CemeteryFormProps = {
    formId: string;
    cemetery: Cemetery | null;
    managers: CemeteryManagerOption[];
    religions: ReligionOption[];
    localities: LocalityOption[];
    onDirtyChange: (isDirty: boolean) => void;
    onProcessingChange: (processing: boolean) => void;
    onLocalityCreated?: (locality: LocalityOption) => void;
};

/** The fields and the form itself; the buttons live in the panel's header and footer. */
function CemeteryForm({
    formId,
    cemetery,
    managers,
    religions,
    localities,
    onDirtyChange,
    onProcessingChange,
    onLocalityCreated,
}: CemeteryFormProps) {
    const [religion, setReligion] = useState(cemetery?.religion ?? '');
    const [manager, setManager] = useState(cemetery?.manager ?? '');
    const locality =
        localities.find((option) => option.id === cemetery?.locality_id) ??
        null;

    return (
        <Form
            id={formId}
            {...(cemetery === null
                ? CemeteryController.store.form()
                : CemeteryController.update.form(cemetery.id))}
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
                        <Label htmlFor="cemetery-name">Nazwa</Label>
                        <Input
                            id="cemetery-name"
                            name="name"
                            defaultValue={cemetery?.name ?? ''}
                            maxLength={255}
                            aria-invalid={errors.name ? true : undefined}
                            aria-describedby="cemetery-name-hint"
                        />
                        <p
                            id="cemetery-name-hint"
                            className="text-muted-foreground text-sm"
                        >
                            {'Puste — „cmentarz {miejscowość}”'}
                        </p>
                        <InputError message={errors.name} />
                    </div>

                    <LocalityPicker
                        id="cemetery-locality"
                        name="locality_id"
                        label="Miejscowość"
                        value={locality}
                        options={localities}
                        errors={errors}
                        onCreated={onLocalityCreated}
                    />

                    <div className="space-y-2">
                        <Label htmlFor="cemetery-manager">Zarządca</Label>
                        <Select
                            value={manager || NONE}
                            onValueChange={(next) =>
                                setManager(next === NONE ? '' : next)
                            }
                        >
                            <SelectTrigger
                                id="cemetery-manager"
                                className="w-full"
                            >
                                <SelectValue placeholder="brak" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>brak</SelectItem>
                                {managers.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="manager" value={manager} />
                        <InputError message={errors.manager} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="cemetery-religion">Wyznanie</Label>
                        <Select
                            value={religion || NONE}
                            onValueChange={(next) =>
                                setReligion(next === NONE ? '' : next)
                            }
                        >
                            <SelectTrigger
                                id="cemetery-religion"
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

                    <div className="space-y-2">
                        <Label htmlFor="cemetery-description">Opis</Label>
                        <Textarea
                            id="cemetery-description"
                            name="description"
                            defaultValue={cemetery?.description ?? ''}
                            rows={6}
                            maxLength={5000}
                            aria-invalid={errors.description ? true : undefined}
                        />
                        <InputError message={errors.description} />
                    </div>
                </>
            )}
        </Form>
    );
}
