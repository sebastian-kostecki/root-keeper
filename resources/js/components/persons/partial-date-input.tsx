import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    type DateQualifier,
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
        qualifier: DateQualifier;
        until_year: number | null;
        locality: LocalityOption | null;
    };
};

const NEUTRAL_HINT = 'np. 1948, 03.1948, ok. 1948, przed 1950, 1940–1945';
const TWO_DIGIT_YEAR_ERROR = '1848 czy 1948? wpisz rok 4 cyframi';
const RANGE_ORDER_ERROR =
    'Rok końca zakresu musi być późniejszy niż rok początku';
const INVALID_ERROR = 'Wpisz np. 1948, 03.1948, 12.03.1948 lub 1940–1945';

/**
 * One free-text date field (Polish genealogy notation) that posts the
 * `{prefix}.year|month|day|qualifier|until_year` parts through hidden inputs, plus a
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
        const short = formatPartialDate(parsed.parts, 'short');

        hint =
            parsed.precision === 'range'
                ? `${describePrecision('range')} ${short}`
                : `${short} ${describePrecision(parsed.precision)}`;
    } else if (parsed.kind === 'two-digit-year') {
        clientError = TWO_DIGIT_YEAR_ERROR;
    } else if (parsed.kind === 'range-order') {
        clientError = RANGE_ORDER_ERROR;
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
        errors[`${prefix}.qualifier`] ??
        errors[`${prefix}.until_year`];

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
                    name={`${prefix}.qualifier`}
                    value={parts?.qualifier ?? 'exact'}
                />
                <input
                    type="hidden"
                    name={`${prefix}.until_year`}
                    value={parts?.until_year ?? ''}
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
