import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
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
import type { Source, SourceKind, SourceRecordType } from '@/types';

type Props = {
    kind: SourceKind | '';
    recordTypes: { value: SourceRecordType; label: string }[];
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
    errors,
    defaults,
    fileName,
    notice,
}: Props) {
    const [recordType, setRecordType] = useState<string>(
        defaults?.record_type ?? '',
    );
    const isRecord = kind === 'metrical_record';

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
                <Label htmlFor="provenance">Pochodzenie</Label>
                <Input
                    id="provenance"
                    name="provenance"
                    defaultValue={defaults?.provenance ?? ''}
                />
                <p className="text-muted-foreground text-sm">
                    Parafia, archiwum, sygnatura
                </p>
                <InputError message={errors.provenance} />
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
        </>
    );
}
