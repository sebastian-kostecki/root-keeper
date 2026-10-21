import { useState } from 'react';
import type { MouseEvent, ReactNode } from 'react';
import { CemeteryPicker } from '@/components/cemeteries/cemetery-picker';
import InputError from '@/components/input-error';
import { NewParishDialog } from '@/components/parishes/new-parish-dialog';
import { BuriedPeopleField } from '@/components/sources/buried-people-field';
import { SuggestField } from '@/components/suggest-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    CemeteryManagerOption,
    CemeteryOption,
    LocalityOption,
    Parish,
    ParishKindOption,
    ReligionOption,
    Source,
    SourceKind,
    SourceRecordType,
} from '@/types';

type Props = {
    kind: SourceKind | '';
    recordTypes: { value: SourceRecordType; label: string }[];
    parishes: Parish[];
    archives: string[];
    localities: LocalityOption[];
    cemeteries: CemeteryOption[];
    cemeteryManagers: CemeteryManagerOption[];
    religions: ReligionOption[];
    parishKinds: ParishKindOption[];
    errors: Partial<Record<string, string>>;
    defaults?: Source;
    /** File name shown as the „Nazwa” placeholder. */
    fileName?: string;
    /** Shown in place of the akt-only fields; those stay mounted (hidden) and still submit. */
    notice?: ReactNode;
    /** A click on a buried person's link; the page decides whether it may leave the form. */
    onPersonLinkClick?: (event: MouseEvent, personId: number) => void;
    /** Called with a locality added in the panel, so the caller can name it later. */
    onLocalityCreated?: (locality: LocalityOption) => void;
    /** Called with a cemetery added in the panel, so the caller can name it later. */
    onCemeteryCreated?: (cemetery: CemeteryOption) => void;
};

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

/** Optional description fields shared by „Dodaj źródło” and „Edytuj źródło”. */
export function SourceDescriptionFields({
    kind,
    recordTypes,
    parishes,
    archives,
    localities,
    cemeteries,
    cemeteryManagers,
    religions,
    parishKinds,
    errors,
    defaults,
    fileName,
    notice,
    onPersonLinkClick,
    onLocalityCreated,
    onCemeteryCreated,
}: Props) {
    const [recordType, setRecordType] = useState<string>(
        defaults?.record_type ?? '',
    );
    const [parishId, setParishId] = useState<number | null>(
        defaults?.parish_id ?? null,
    );
    const [createdParishes, setCreatedParishes] = useState<Parish[]>([]);
    const [createdLocalities, setCreatedLocalities] = useState<
        LocalityOption[]
    >([]);
    const [createdCemeteries, setCreatedCemeteries] = useState<
        CemeteryOption[]
    >([]);
    /** Text typed in the parish field while „Nowa parafia / urząd” is open; null when it is closed. */
    const [newParishText, setNewParishText] = useState<string | null>(null);
    const isRecord = kind === 'metrical_record';
    const isGravestone = kind === 'gravestone';

    const cemeteryId = defaults?.cemetery_id ?? null;
    const cemetery: CemeteryOption | null =
        cemeteryId === null
            ? null
            : {
                  id: cemeteryId,
                  label: defaults?.cemetery_label ?? '',
                  locality_id: defaults?.cemetery_locality_id ?? 0,
                  locality_name: defaults?.locality_name ?? '',
              };

    const addCreatedCemetery = (created: CemeteryOption) => {
        setCreatedCemeteries((current) => [...current, created]);
        onCemeteryCreated?.(created);
    };

    const addCreatedLocality = (locality: LocalityOption) => {
        setCreatedLocalities((current) => [...current, locality]);
        onLocalityCreated?.(locality);
    };

    // Parishes and localities added here live in state until the panel props are reloaded.
    const parishOptions = [
        ...parishes,
        ...createdParishes.filter(
            (created) => !parishes.some((known) => known.id === created.id),
        ),
    ];
    const cemeteryOptions = [
        ...cemeteries,
        ...createdCemeteries.filter(
            (created) => !cemeteries.some((known) => known.id === created.id),
        ),
    ];
    const localityOptions = [
        ...localities,
        ...createdLocalities.filter(
            (created) => !localities.some((known) => known.id === created.id),
        ),
    ];

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Nazwa</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={defaults?.name ?? ''}
                    placeholder={fileName ?? defaults?.original_name}
                />
                <InputError message={errors.name} />
            </div>

            {!isRecord && notice}

            <div className={cn('grid gap-2', !isRecord && 'hidden')}>
                <Label htmlFor="record_type">Typ aktu</Label>
                <Select
                    value={recordType || NONE}
                    onValueChange={(value) =>
                        setRecordType(value === NONE ? '' : value)
                    }
                >
                    <SelectTrigger id="record_type" className="w-full">
                        <SelectValue placeholder="brak" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>brak</SelectItem>
                        {recordTypes.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="record_type" value={recordType} />
                <InputError message={errors.record_type} />
            </div>

            <div className={cn('grid gap-2', !isRecord && 'hidden')}>
                <Label htmlFor="parish_id">Parafia / urząd</Label>
                <SuggestField
                    mode="record"
                    id="parish_id"
                    label="Parafia / urząd"
                    name="parish_id"
                    options={parishOptions}
                    labelOf={(option) => option.label}
                    value={parishId}
                    onChange={setParishId}
                    onCreate={setNewParishText}
                    offerCreate="always"
                    createLabel={(query) => `Dodaj parafię / urząd „${query}”`}
                    placeholder="Wpisz miejscowość lub wezwanie"
                    aria-invalid={errors.parish_id ? true : undefined}
                />
                <InputError message={errors.parish_id} />
            </div>

            <div className={cn(!isGravestone && 'hidden')}>
                <CemeteryPicker
                    id="cemetery_id"
                    name="cemetery_id"
                    label="Cmentarz"
                    value={cemetery}
                    options={cemeteryOptions}
                    managers={cemeteryManagers}
                    religions={religions}
                    localities={localityOptions}
                    errors={errors}
                    onCreated={addCreatedCemetery}
                    onLocalityCreated={addCreatedLocality}
                />
            </div>

            <div className={cn(!isGravestone && 'hidden')}>
                <BuriedPeopleField
                    defaults={defaults?.buried_people}
                    errors={errors}
                    onPersonLinkClick={onPersonLinkClick}
                />
            </div>

            <div className={cn('grid gap-2', isGravestone && 'hidden')}>
                <Label htmlFor="year">Rok</Label>
                <Input
                    id="year"
                    name="year"
                    inputMode="numeric"
                    defaultValue={defaults?.year ?? ''}
                />
                <InputError message={errors.year} />
            </div>

            <div className={cn('grid gap-2', isGravestone && 'hidden')}>
                <Label htmlFor="archive">Archiwum</Label>
                <SuggestField
                    mode="text"
                    id="archive"
                    label="Archiwum"
                    name="archive"
                    options={archives}
                    labelOf={(option) => option}
                    defaultValue={defaults?.archive ?? ''}
                    aria-invalid={errors.archive ? true : undefined}
                />
                <p className="text-muted-foreground text-sm">
                    Np. Archiwum Diecezjalne w Tarnowie, sygnatura
                </p>
                <InputError message={errors.archive} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="url">Link</Label>
                <Input
                    id="url"
                    name="url"
                    inputMode="url"
                    defaultValue={defaults?.url ?? ''}
                />
                <p className="text-muted-foreground text-sm">
                    Adres strony z aktem, np. w archiwum internetowym
                </p>
                <InputError message={errors.url} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="note">Notatka</Label>
                <Textarea
                    id="note"
                    name="note"
                    defaultValue={defaults?.note ?? ''}
                />
                <p className="text-muted-foreground text-sm">
                    Kto, kiedy, co i jak zrobił
                </p>
                <InputError message={errors.note} />
            </div>

            {newParishText !== null && (
                <NewParishDialog
                    initialSeatName={newParishText}
                    religions={religions}
                    parishKinds={parishKinds}
                    localities={localityOptions}
                    onCreated={(parish) => {
                        setCreatedParishes((current) => [...current, parish]);
                        setParishId(parish.id);
                    }}
                    onLocalityCreated={addCreatedLocality}
                    onClose={() => setNewParishText(null)}
                />
            )}
        </>
    );
}
