import { useState } from 'react';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
import { PartialDateInput } from '@/components/persons/partial-date-input';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { PersonFormData, PersonFormEvent, Religion } from '@/types';

type Errors = Partial<Record<string, string>>;

/** Shown under a birth/death date when that event is backed by a linked source. */
const CONFIRMED_HINT =
    'Potwierdzone źródłem — upewnij się, że zmiana zgadza się ze źródłem.';

/** Radix Select and ToggleGroup do not allow an empty item value. */
const NONE = 'none';

type PersonalFieldsProps = {
    religions: Religion[];
    errors: Errors;
    person?: PersonFormData;
    /** Fixes the sex (value still submitted), e.g. by a tree slot. */
    lockedGender?: 'male' | 'female';
    /** Starting values for a new person. */
    defaults?: { last_name?: string | null };
};

const GENDER_OPTIONS = [
    { value: 'male', label: 'Mężczyzna' },
    { value: 'female', label: 'Kobieta' },
    { value: NONE, label: 'Nieznana' },
];

/** Imię, Nazwisko, Płeć, Wyznanie, Miejsce zamieszkania. */
export function PersonalFields({
    religions,
    errors,
    person,
    lockedGender,
    defaults,
}: PersonalFieldsProps) {
    const [selectedGender, setGender] = useState(person?.gender ?? '');
    const gender = lockedGender ?? selectedGender;
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
                        defaultValue={
                            (person ? person.last_name : defaults?.last_name) ??
                            undefined
                        }
                    />
                    <InputError message={errors.last_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="maiden_name">Nazwisko rodowe</Label>
                    <Input
                        id="maiden_name"
                        name="maiden_name"
                        maxLength={255}
                        defaultValue={person?.maiden_name ?? undefined}
                    />
                    <InputError message={errors.maiden_name} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label id="gender-label">Płeć</Label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    aria-labelledby="gender-label"
                    value={gender || NONE}
                    onValueChange={(value) => {
                        if (value !== '') {
                            setGender(value === NONE ? '' : value);
                        }
                    }}
                    className="w-full"
                >
                    {GENDER_OPTIONS.map((option) => (
                        <ToggleGroupItem
                            key={option.value}
                            value={option.value}
                            disabled={
                                lockedGender !== undefined &&
                                option.value !== lockedGender
                            }
                            className="flex-1"
                        >
                            {option.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <input type="hidden" name="gender" value={gender} />
                <InputError message={errors.gender} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="religion">Wyznanie</Label>
                <Select
                    value={religion || NONE}
                    onValueChange={(value) =>
                        setReligion(value === NONE ? '' : value)
                    }
                >
                    <SelectTrigger id="religion" className="w-full">
                        <SelectValue placeholder="Brak danych" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>Brak danych</SelectItem>
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
        </>
    );
}

/** Confirmed hint plus the birth date and place. */
export function BirthFields({
    errors,
    birth,
}: {
    errors: Errors;
    birth: PersonFormEvent | null;
}) {
    return (
        <div className="space-y-4">
            {birth?.is_confirmed && (
                <p className="text-muted-foreground text-sm">
                    {CONFIRMED_HINT}
                </p>
            )}
            <PartialDateInput
                prefix="birth"
                errors={errors}
                defaultValue={birth ?? undefined}
            />
        </div>
    );
}

/** "Osoba nie żyje" switch row; the death date, place and burial place appear when checked. */
export function DeathFields({
    errors,
    death,
    burial,
    isDeceased,
    burialSuggestion,
}: {
    errors: Errors;
    death: PersonFormEvent | null;
    burial: PersonFormData['burial'];
    /** Cemetery of the person's gravestone; preselects an empty burial place. */
    burialSuggestion?: PersonFormData['burial_suggestion'];
    /** Whether the checkbox starts checked. */
    isDeceased: boolean;
}) {
    const [checked, setChecked] = useState(isDeceased);
    const suggested = burial?.locality ? null : (burialSuggestion ?? null);

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-3">
                <Checkbox
                    id="is_deceased"
                    checked={checked}
                    onCheckedChange={(value) => setChecked(value === true)}
                />
                <Label htmlFor="is_deceased">Osoba nie żyje</Label>
                {checked && (
                    <input type="hidden" name="is_deceased" value="1" />
                )}
            </div>
            <InputError message={errors.is_deceased} />

            {checked && (
                <>
                    {death?.is_confirmed && (
                        <p className="text-muted-foreground text-sm">
                            {CONFIRMED_HINT}
                        </p>
                    )}
                    <PartialDateInput
                        prefix="death"
                        errors={errors}
                        defaultValue={death ?? undefined}
                    />
                    <LocalityPicker
                        id="burial-locality"
                        name="burial.locality_id"
                        label="Miejsce pochowania"
                        value={burial?.locality ?? suggested}
                        errors={errors}
                    />
                    {suggested && (
                        <p className="text-muted-foreground -mt-2 text-sm">
                            Podpowiedź z nagrobka
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

/** Marriage date and place. */
export function MarriageFields({
    errors,
    defaultValue,
}: {
    errors: Errors;
    defaultValue: NonNullable<
        React.ComponentProps<typeof PartialDateInput>['defaultValue']
    >;
}) {
    return (
        <PartialDateInput
            prefix="marriage"
            errors={errors}
            defaultValue={defaultValue}
        />
    );
}
