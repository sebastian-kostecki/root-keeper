import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { NewCemeteryDialog } from '@/components/cemeteries/new-cemetery-dialog';
import InputError from '@/components/input-error';
import { SuggestField } from '@/components/suggest-field';
import { Label } from '@/components/ui/label';
import { useOptionalList } from '@/hooks/use-relation-candidates';
import type {
    CemeteryManagerOption,
    CemeteryOption,
    LocalityOption,
    ReligionOption,
} from '@/types';

type Props = {
    id: string;
    /** Name of the hidden input that carries the chosen cemetery id. */
    name: string;
    label: string;
    value: CemeteryOption | null;
    /** Known cemeteries; when omitted the shared `cemeteryOptions` prop is loaded on mount. */
    options?: CemeteryOption[];
    /** Narrows the list to the cemeteries of this locality (the chosen one stays visible). */
    localityId?: number | null;
    managers: CemeteryManagerOption[];
    religions: ReligionOption[];
    /** Localities known to the form, offered in the „Nowy cmentarz” dialog. */
    localities?: LocalityOption[];
    errors?: Partial<Record<string, string>>;
    onChange?: (cemetery: CemeteryOption | null) => void;
    /** Called with a cemetery added in the dialog. */
    onCreated?: (cemetery: CemeteryOption) => void;
    /** Called with a locality added in the dialog's locality field. */
    onLocalityCreated?: (locality: LocalityOption) => void;
};

/**
 * A cemetery chosen from the register, with „Dodaj cmentarz” opening a dialog that saves it in
 * place. The dialog has no `<form>` and saves with plain HTTP, so the surrounding form is not
 * submitted and the panel keeps what was typed.
 */
export function CemeteryPicker({
    id,
    name,
    label,
    value,
    options,
    localityId,
    managers,
    religions,
    localities,
    errors,
    onChange,
    onCreated,
    onLocalityCreated,
}: Props) {
    const shared = usePage().props.cemeteryOptions;
    const optional = useOptionalList<CemeteryOption>('cemeteryOptions', shared);
    const requested = useRef(false);
    const { list, load } = optional;
    const [selectedId, setSelectedId] = useState<number | null>(
        value?.id ?? null,
    );
    const [created, setCreated] = useState<CemeteryOption[]>([]);
    /** Name typed in the field while the dialog is open; null when it is closed. */
    const [newName, setNewName] = useState<string | null>(null);

    const needsLoad = options === undefined && list === undefined;

    useEffect(() => {
        if (needsLoad && !requested.current) {
            requested.current = true;
            load();
        }
    }, [needsLoad, load]);

    const merged: CemeteryOption[] = [];

    for (const option of [
        ...(options ?? list ?? []),
        ...(value === null ? [] : [value]),
        ...created,
    ]) {
        if (!merged.some((known) => known.id === option.id)) {
            merged.push(option);
        }
    }

    const visible = merged.filter(
        (option) =>
            localityId === undefined ||
            localityId === null ||
            option.locality_id === localityId ||
            option.id === selectedId,
    );

    const select = (nextId: number | null) => {
        setSelectedId(nextId);
        onChange?.(merged.find((option) => option.id === nextId) ?? null);
    };

    const handleCreated = (cemetery: CemeteryOption) => {
        setCreated((current) => [...current, cemetery]);
        setSelectedId(cemetery.id);
        onCreated?.(cemetery);
        onChange?.(cemetery);
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
                options={visible}
                labelOf={(option) => option.label}
                value={selectedId}
                onChange={select}
                onCreate={setNewName}
                offerCreate="always"
                createLabel={(query) => `Dodaj cmentarz „${query}”`}
                placeholder="Wpisz nazwę cmentarza"
                aria-invalid={serverError ? true : undefined}
            />
            <InputError message={serverError} />

            {newName !== null && (
                <NewCemeteryDialog
                    initialName={newName}
                    localityId={localityId ?? null}
                    managers={managers}
                    religions={religions}
                    localities={localities}
                    onCreated={handleCreated}
                    onLocalityCreated={onLocalityCreated}
                    onClose={() => setNewName(null)}
                />
            )}
        </div>
    );
}
