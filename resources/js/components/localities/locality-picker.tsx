import { usePage, useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { SuggestField } from '@/components/suggest-field';
import { Label } from '@/components/ui/label';
import { useOptionalList } from '@/hooks/use-relation-candidates';
import { localityHint } from '@/lib/localities-index';
import { store as storeInlineLocality } from '@/routes/localities/inline';
import type { LocalityOption } from '@/types';

type Props = {
    id: string;
    /** Name of the hidden input that carries the chosen locality id. */
    name: string;
    label: string;
    value: LocalityOption | null;
    /** Known localities; when omitted the shared `localityOptions` prop is loaded on mount. */
    options?: LocalityOption[];
    errors?: Partial<Record<string, string>>;
    onChange?: (locality: LocalityOption | null) => void;
    /** Called with a locality added in place (new or already in the register). */
    onCreated?: (locality: LocalityOption) => void;
};

/**
 * A locality chosen from the register, with „Dodaj miejscowość” in place. It has no
 * `<form>` and adds with plain HTTP: a nested form would submit the surrounding one, a
 * visit would reload the panel and reset what was typed.
 */
export function LocalityPicker({
    id,
    name,
    label,
    value,
    options,
    errors,
    onChange,
    onCreated,
}: Props) {
    const shared = usePage().props.localityOptions;
    const optional = useOptionalList<LocalityOption>('localityOptions', shared);
    const requested = useRef(false);
    const { list, load } = optional;
    const [selectedId, setSelectedId] = useState<number | null>(
        value?.id ?? null,
    );
    const [created, setCreated] = useState<LocalityOption[]>([]);
    const [createError, setCreateError] = useState<string | undefined>();
    const http = useHttp<{ name: string }, LocalityOption>({ name: '' });

    const needsLoad = options === undefined && list === undefined;

    useEffect(() => {
        if (needsLoad && !requested.current) {
            requested.current = true;
            load();
        }
    }, [needsLoad, load]);

    const merged: LocalityOption[] = [];

    for (const option of [
        ...(options ?? list ?? []),
        ...(value === null ? [] : [value]),
        ...created,
    ]) {
        if (!merged.some((known) => known.id === option.id)) {
            merged.push(option);
        }
    }

    const select = (nextId: number | null) => {
        setSelectedId(nextId);
        onChange?.(merged.find((option) => option.id === nextId) ?? null);
    };

    const createLocality = async (query: string) => {
        setCreateError(undefined);
        http.transform(() => ({ name: query }));

        try {
            const locality = await http.post(storeInlineLocality.url());

            if (locality === undefined) {
                return;
            }

            setCreated((current) => [...current, locality]);
            setSelectedId(locality.id);
            onCreated?.(locality);
            onChange?.(locality);
        } catch {
            setCreateError('Nie udało się dodać miejscowości.');
        }
    };

    const serverError = errors?.[name];

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <SuggestField
                mode="record"
                id={id}
                label={label}
                name={name}
                options={merged}
                labelOf={(option) => option.name}
                hintOf={localityHint}
                value={selectedId}
                onChange={select}
                onCreate={createLocality}
                offerCreate="unmatched"
                createLabel={(query) => `Dodaj miejscowość „${query}”`}
                placeholder="Wpisz nazwę miejscowości"
                disabled={http.processing}
                aria-invalid={
                    serverError || http.errors.name || createError
                        ? true
                        : undefined
                }
            />
            <InputError message={serverError} />
            <InputError message={http.errors.name ?? createError} />
        </div>
    );
}
