import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ConfidenceLevel } from '@/types';

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

type Props = {
    /** Form field name, e.g. `birth.confidence`; also the hidden input's name. */
    name: string;
    defaultValue: ConfidenceLevel | null;
    label?: string;
    disabled?: boolean;
    error?: string;
};

/** „Poziom pewności” select: A–D or „nieokreślony”; the real value rides in a hidden input. */
export function ConfidenceField({
    name,
    defaultValue,
    label = 'Poziom pewności',
    disabled = false,
    error,
}: Props) {
    const { confidenceLevels } = usePage().props;
    const [level, setLevel] = useState<string>(defaultValue ?? '');
    const shown = disabled ? '' : level;
    const id = `${name.replace(/\./g, '-')}-field`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select
                value={shown || NONE}
                onValueChange={(value) => setLevel(value === NONE ? '' : value)}
                disabled={disabled}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder="nieokreślony" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>nieokreślony</SelectItem>
                    {confidenceLevels.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.value} — {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <input type="hidden" name={name} value={shown} />
            <InputError message={error} />
        </div>
    );
}
