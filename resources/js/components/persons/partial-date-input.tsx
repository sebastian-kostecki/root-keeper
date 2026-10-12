import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    describePrecision,
    formatPartialDate,
    parsePartialDate,
} from '@/lib/partial-date';
import type { LocalityOption } from '@/types';

type Props = {
    prefix: 'birth' | 'death' | 'marriage';
    errors: Partial<Record<string, string>>;
    defaultValue?: {
        year: number | null;
        month: number | null;
        day: number | null;
        is_approximate: boolean;
        locality: LocalityOption | null;
    };
};

const NEUTRAL_HINT = 'np. 1948, 03.1948, ok. 1948';
const TWO_DIGIT_YEAR_ERROR = '1848 czy 1948? wpisz rok 4 cyframi';
const INVALID_ERROR = 'Wpisz np. 1948, 03.1948 lub 12.03.1948';

/**
 * One free-text date field (Polish genealogy notation) that posts the
 * `{prefix}.year|month|day|is_approximate` parts through hidden inputs, plus a
 * place field. Unparseable text blocks native form submit via `setCustomValidity`.
 */
export function PartialDateInput({ prefix, errors, defaultValue }: Props) {
    const [text, setText] = useState(() =>
        defaultValue ? formatPartialDate(defaultValue, 'short') : '',
    );
    const inputRef = useRef<HTMLInputElement>(null);

    const parsed = parsePartialDate(text);
    const parts = parsed.kind === 'ok' ? parsed.parts : null;

    let hint = NEUTRAL_HINT;
    let clientError: string | null = null;

    if (parsed.kind === 'ok') {
        hint = `${formatPartialDate(parsed.parts, 'short')} ${describePrecision(parsed.precision)}`;
    } else if (parsed.kind === 'two-digit-year') {
        clientError = TWO_DIGIT_YEAR_ERROR;
    } else if (parsed.kind === 'invalid') {
        clientError = INVALID_ERROR;
    }

    useEffect(() => {
        inputRef.current?.setCustomValidity(clientError ?? '');
    }, [clientError]);

    const serverError =
        errors[`${prefix}.year`] ??
        errors[`${prefix}.month`] ??
        errors[`${prefix}.day`] ??
        errors[`${prefix}.is_approximate`];

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-date`}>Data</Label>
                <Input
                    ref={inputRef}
                    id={`${prefix}-date`}
                    type="text"
                    autoComplete="off"
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    aria-invalid={clientError !== null || Boolean(serverError)}
                    aria-describedby={`${prefix}-date-hint`}
                />
                <input
                    type="hidden"
                    name={`${prefix}.year`}
                    value={parts?.year ?? ''}
                />
                <input
                    type="hidden"
                    name={`${prefix}.month`}
                    value={parts?.month ?? ''}
                />
                <input
                    type="hidden"
                    name={`${prefix}.day`}
                    value={parts?.day ?? ''}
                />
                <input
                    type="hidden"
                    name={`${prefix}.is_approximate`}
                    value={parts?.is_approximate ? '1' : '0'}
                />
                <p
                    id={`${prefix}-date-hint`}
                    className={
                        clientError
                            ? 'text-destructive-foreground text-sm'
                            : 'text-muted-foreground text-sm'
                    }
                >
                    {clientError ?? hint}
                </p>
                <InputError message={serverError} />
            </div>

            <LocalityPicker
                id={`${prefix}-locality`}
                name={`${prefix}.locality_id`}
                label="Miejsce"
                value={defaultValue?.locality ?? null}
                errors={errors}
            />
        </div>
    );
}
