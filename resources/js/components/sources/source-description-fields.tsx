import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { NewParishDialog } from '@/components/parishes/new-parish-dialog';
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
    LocalityOption,
    Parish,
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
    religions: ReligionOption[];
    errors: Partial<Record<string, string>>;
    defaults?: Source;
    /** File name shown as the „Nazwa” placeholder. */
    fileName?: string;
    /** Shown in place of the akt-only fields; those stay mounted (hidden) and still submit. */
    notice?: ReactNode;
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
    religions,
    errors,
    defaults,
    fileName,
    notice,
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
    /** Name typed in the parish field while „Nowa parafia” is open; null when it is closed. */
    const [newParishName, setNewParishName] = useState<string | null>(null);
    const isRecord = kind === 'metrical_record';

    // Parishes and localities added here live in state until the panel props are reloaded.
    const parishOptions = [
        ...parishes,
        ...createdParishes.filter(
            (created) => !parishes.some((known) => known.id === created.id),
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
                <Label htmlFor="parish_id">Parafia</Label>
                <SuggestField
                    mode="record"
                    id="parish_id"
                    name="parish_id"
                    options={parishOptions}
                    labelOf={(option) => option.label}
                    value={parishId}
                    onChange={setParishId}
                    onCreate={setNewParishName}
                    offerCreate="always"
                    createLabel={(query) => `Dodaj parafię „${query}”`}
                    placeholder="Wpisz nazwę parafii"
                    aria-invalid={errors.parish_id ? true : undefined}
                />
                <InputError message={errors.parish_id} />
            </div>

            <div className={cn('grid gap-2', !isRecord && 'hidden')}>
                <Label htmlFor="entry_number">Numer wpisu</Label>
                <Input
                    id="entry_number"
                    name="entry_number"
                    defaultValue={defaults?.entry_number ?? ''}
                />
                <InputError message={errors.entry_number} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="year">Rok</Label>
                <Input
                    id="year"
                    name="year"
                    inputMode="numeric"
                    defaultValue={defaults?.year ?? ''}
                />
                <InputError message={errors.year} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="archive">Archiwum</Label>
                <SuggestField
                    mode="text"
                    id="archive"
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

            {newParishName !== null && (
                <NewParishDialog
                    initialName={newParishName}
                    religions={religions}
                    localities={localityOptions}
                    onCreated={(parish) => {
                        setCreatedParishes((current) => [...current, parish]);
                        setParishId(parish.id);
                    }}
                    onLocalityCreated={(locality) =>
                        setCreatedLocalities((current) => [
                            ...current,
                            locality,
                        ])
                    }
                    onClose={() => setNewParishName(null)}
                />
            )}
        </>
    );
}
