import { useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { SourceKind } from '@/types';

type Props = {
    kinds: { value: SourceKind; label: string }[];
    defaultValue?: SourceKind;
    error?: string;
    onValueChange?: (kind: SourceKind) => void;
};

/** Required kind choice for a source; submits `kind` inside a native `<Form>`. */
export function SourceKindField({
    kinds,
    defaultValue,
    error,
    onValueChange,
}: Props) {
    const [kind, setKind] = useState<string>(defaultValue ?? '');

    return (
        <div className="grid gap-2">
            <Label id="kind-label">Rodzaj</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                aria-labelledby="kind-label"
                value={kind}
                onValueChange={(value) => {
                    if (value !== '') {
                        setKind(value);
                        onValueChange?.(value as SourceKind);
                    }
                }}
                className="w-full"
            >
                {kinds.map((option) => (
                    <ToggleGroupItem
                        key={option.value}
                        value={option.value}
                        className="flex-1"
                    >
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            <input type="hidden" name="kind" value={kind} />
            <InputError message={error} />
        </div>
    );
}
