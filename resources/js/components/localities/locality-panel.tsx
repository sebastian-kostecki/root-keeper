import { Form } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import LocalityController from '@/actions/App/Http/Controllers/LocalityController';
import { DeleteLocalityDialog } from '@/components/localities/delete-locality-dialog';
import { MergeLocalityDialog } from '@/components/localities/merge-locality-dialog';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { ProcessingReporter } from '@/components/processing-reporter';
import { SidePanel } from '@/components/side-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { focusRing } from '@/lib/focus-ring';
import { formatCoordinates, openStreetMapUrl } from '@/lib/localities-index';
import { cn } from '@/lib/utils';
import type { Locality, LocalityKindOption } from '@/types';

type Props = {
    /** The record to edit, or null for a new one. */
    locality: Locality | null;
    /** The whole register, offered as the target of a merge. */
    localities: Locality[];
    localityKinds: LocalityKindOption[];
    onRequestClose: () => void;
    /** Reports whether the form holds unsaved changes. */
    onDirtyChange: (isDirty: boolean) => void;
};

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

/** Name, kind, coordinates, affiliation, description and status form in the side panel; one component for „Nowa miejscowość” and editing. */
export function LocalityPanel({
    locality,
    localities,
    localityKinds,
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
            {locality?.is_defunct && (
                <div className="mb-4 space-y-1">
                    <Badge variant="outline">nie istnieje</Badge>
                    {locality.defunct_note && (
                        <p className="text-muted-foreground text-sm">
                            {locality.defunct_note}
                        </p>
                    )}
                </div>
            )}

            <Form
                key={`${locality?.id ?? 'new'}-${formatCoordinates(locality?.latitude ?? null, locality?.longitude ?? null)}`}
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

                        <LocalityFields
                            locality={locality}
                            localityKinds={localityKinds}
                            errors={errors}
                        />
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

type FieldsProps = {
    locality: Locality | null;
    localityKinds: LocalityKindOption[];
    errors: Partial<Record<string, string>>;
};

/** The form fields; remounted with the form, so the kind and the status start from the record. */
function LocalityFields({ locality, localityKinds, errors }: FieldsProps) {
    const [kind, setKind] = useState(locality?.kind ?? '');
    const [isDefunct, setIsDefunct] = useState(locality?.is_defunct ?? false);
    const hasCoordinates =
        locality?.latitude != null && locality.longitude != null;

    return (
        <>
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
                <Label htmlFor="locality-kind">Rodzaj</Label>
                <Select
                    value={kind || NONE}
                    onValueChange={(next) => setKind(next === NONE ? '' : next)}
                >
                    <SelectTrigger id="locality-kind" className="w-full">
                        <SelectValue placeholder="brak" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>brak</SelectItem>
                        {localityKinds.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="kind" value={kind} />
                <InputError message={errors.kind} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="locality-coordinates">Współrzędne</Label>
                <Input
                    id="locality-coordinates"
                    name="coordinates"
                    defaultValue={formatCoordinates(
                        locality?.latitude ?? null,
                        locality?.longitude ?? null,
                    )}
                    aria-describedby="locality-coordinates-hint"
                    aria-invalid={errors.coordinates ? true : undefined}
                />
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p
                        id="locality-coordinates-hint"
                        className="text-muted-foreground text-sm"
                    >
                        Wklej z mapy, np. 50.0614, 19.9366
                    </p>
                    {hasCoordinates && (
                        <a
                            href={openStreetMapUrl(
                                locality.latitude as number,
                                locality.longitude as number,
                            )}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(
                                focusRing,
                                'rounded-md text-sm underline',
                            )}
                        >
                            Pokaż na mapie
                        </a>
                    )}
                </div>
                <InputError message={errors.coordinates} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="locality-historical-affiliation">
                    Przynależność historyczna
                </Label>
                <Textarea
                    id="locality-historical-affiliation"
                    name="historical_affiliation"
                    defaultValue={locality?.historical_affiliation ?? ''}
                    rows={2}
                    maxLength={1000}
                    aria-describedby="locality-historical-affiliation-hint"
                    aria-invalid={
                        errors.historical_affiliation ? true : undefined
                    }
                />
                <p
                    id="locality-historical-affiliation-hint"
                    className="text-muted-foreground text-sm"
                >
                    Np. gm. Lipnica, pow. bocheński, Galicja
                </p>
                <InputError message={errors.historical_affiliation} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="locality-description">Opis</Label>
                <Textarea
                    id="locality-description"
                    name="description"
                    defaultValue={locality?.description ?? ''}
                    rows={6}
                    maxLength={5000}
                    aria-invalid={errors.description ? true : undefined}
                />
                <InputError message={errors.description} />
            </div>

            <div className="flex items-center gap-3">
                <Checkbox
                    id="locality-is-defunct"
                    checked={isDefunct}
                    onCheckedChange={(value) => setIsDefunct(value === true)}
                />
                <Label htmlFor="locality-is-defunct">Nie istnieje dziś</Label>
                {isDefunct && (
                    <input type="hidden" name="is_defunct" value="1" />
                )}
            </div>
            <InputError message={errors.is_defunct} />

            {isDefunct && (
                <div className="space-y-2">
                    <Label htmlFor="locality-defunct-note">Co się stało</Label>
                    <Input
                        id="locality-defunct-note"
                        name="defunct_note"
                        defaultValue={locality?.defunct_note ?? ''}
                        maxLength={255}
                        aria-describedby="locality-defunct-note-hint"
                        aria-invalid={errors.defunct_note ? true : undefined}
                    />
                    <p
                        id="locality-defunct-note-hint"
                        className="text-muted-foreground text-sm"
                    >
                        Np. zajęta pod zalew 1975, włączona do …
                    </p>
                    <InputError message={errors.defunct_note} />
                </div>
            )}
        </>
    );
}
