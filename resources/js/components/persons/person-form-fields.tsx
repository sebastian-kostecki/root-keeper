import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PartialDateFields } from '@/components/persons/partial-date-fields';
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
import { Separator } from '@/components/ui/separator';
import type { PersonFormData, Religion } from '@/types';

type Props = {
    religions: Religion[];
    errors: Partial<Record<string, string>>;
    person?: PersonFormData;
};

/** Radix Select does not allow an empty item value, so "no religion" uses a sentinel. */
const NO_RELIGION = 'none';

/** Shown under a birth/death heading when that event is backed by a linked scan. */
const CONFIRMED_HINT =
    'Potwierdzone aktem — upewnij się, że zmiana zgadza się ze skanem.';

export function PersonFormFields({ religions, errors, person }: Props) {
    const [isDeceased, setIsDeceased] = useState(person?.is_deceased ?? false);
    const [religion, setReligion] = useState(person?.religion ?? '');

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="first_name">Imię</Label>
                    <Input
                        id="first_name"
                        name="first_name"
                        maxLength={255}
                        autoFocus
                        defaultValue={person?.first_name ?? undefined}
                    />
                    <InputError message={errors.first_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="last_name">Nazwisko</Label>
                    <Input
                        id="last_name"
                        name="last_name"
                        maxLength={255}
                        defaultValue={person?.last_name ?? undefined}
                    />
                    <InputError message={errors.last_name} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="residence">Miejsce zamieszkania</Label>
                <Input
                    id="residence"
                    name="residence"
                    maxLength={255}
                    defaultValue={person?.residence ?? undefined}
                />
                <InputError message={errors.residence} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="religion">Wyznanie</Label>
                <Select
                    value={religion || NO_RELIGION}
                    onValueChange={(value) =>
                        setReligion(value === NO_RELIGION ? '' : value)
                    }
                >
                    <SelectTrigger id="religion" className="w-full">
                        <SelectValue placeholder="Brak danych" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_RELIGION}>Brak danych</SelectItem>
                        {religions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="religion" value={religion} />
                <InputError message={errors.religion} />
            </div>

            <Separator />

            <div className="space-y-4">
                <Heading variant="small" title="Urodzenie" />
                {person?.birth?.is_confirmed && (
                    <p className="text-muted-foreground text-sm">
                        {CONFIRMED_HINT}
                    </p>
                )}
                <PartialDateFields
                    prefix="birth"
                    errors={errors}
                    defaultValue={person?.birth ?? undefined}
                    defaultChecked={person?.birth?.is_approximate}
                />
            </div>

            <Separator />

            <div className="flex items-center space-x-3">
                <Checkbox
                    id="is_deceased"
                    name="is_deceased"
                    value="1"
                    checked={isDeceased}
                    onCheckedChange={(checked) =>
                        setIsDeceased(checked === true)
                    }
                />
                <Label htmlFor="is_deceased">Zmarły/a</Label>
            </div>
            <InputError message={errors.is_deceased} />

            {isDeceased && (
                <div className="space-y-4">
                    <Heading variant="small" title="Zgon" />
                    {person?.death?.is_confirmed && (
                        <p className="text-muted-foreground text-sm">
                            {CONFIRMED_HINT}
                        </p>
                    )}
                    <PartialDateFields
                        prefix="death"
                        errors={errors}
                        defaultValue={person?.death ?? undefined}
                        defaultChecked={person?.death?.is_approximate}
                    />
                </div>
            )}
        </>
    );
}
