import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SuggestField } from '@/components/suggest-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store as storeInlineLocality } from '@/routes/localities/inline';
import { store as storeInlineParish } from '@/routes/parishes/inline';
import type { LocalityOption, Parish, ReligionOption } from '@/types';

type Props = {
    /** Name typed in the parish field; the dialog opens with it filled in. */
    initialName: string;
    religions: ReligionOption[];
    /** Localities known to the form, including those added by earlier dialogs. */
    localities: LocalityOption[];
    onCreated: (parish: Parish) => void;
    onLocalityCreated: (locality: LocalityOption) => void;
    onClose: () => void;
};

type ParishPayload = {
    name: string;
    religion: string | null;
    locality_id: number | null;
};

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

/**
 * „Nowa parafia” over the source panel. It has no `<form>` and saves with plain HTTP: React
 * events cross the portal along the component tree, so a form here would submit the source
 * form, and an Inertia visit would reload the panel and reset what was typed.
 */
export function NewParishDialog({
    initialName,
    religions,
    localities,
    onCreated,
    onLocalityCreated,
    onClose,
}: Props) {
    const [name, setName] = useState(initialName);
    const [religion, setReligion] = useState('');
    const [localityId, setLocalityId] = useState<number | null>(null);
    const [localityError, setLocalityError] = useState<string | undefined>();
    const [saveError, setSaveError] = useState<string | undefined>();
    const parishHttp = useHttp<ParishPayload, { data: Parish }>({
        name: '',
        religion: null,
        locality_id: null,
    });
    const localityHttp = useHttp<{ name: string }, LocalityOption>({
        name: '',
    });

    const createLocality = async (query: string) => {
        setLocalityError(undefined);
        localityHttp.transform(() => ({ name: query }));

        try {
            const created = await localityHttp.post(storeInlineLocality.url());

            if (created === undefined) {
                return;
            }

            onLocalityCreated(created);
            setLocalityId(created.id);
        } catch {
            setLocalityError('Nie udało się dodać miejscowości.');
        }
    };

    const save = async () => {
        setSaveError(undefined);
        parishHttp.transform(() => ({
            name,
            religion: religion === '' ? null : religion,
            locality_id: localityId,
        }));

        try {
            const response = await parishHttp.post(storeInlineParish.url());

            if (response === undefined) {
                return;
            }

            onCreated(response.data);
            onClose();
        } catch {
            setSaveError('Nie udało się dodać parafii.');
        }
    };

    const busy = parishHttp.processing || localityHttp.processing;

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent
                onEscapeKeyDown={(event) => {
                    // Escape in an open suggestion list closes only the list.
                    if (
                        event.target instanceof Element &&
                        event.target.getAttribute('aria-expanded') === 'true'
                    ) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogTitle>Nowa parafia</DialogTitle>
                <DialogDescription>
                    Parafia trafi do spisu parafii i zostanie wybrana w źródle.
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="new-parish-name">Nazwa</Label>
                    <Input
                        id="new-parish-name"
                        value={name}
                        maxLength={255}
                        onChange={(event) => setName(event.target.value)}
                        aria-invalid={parishHttp.errors.name ? true : undefined}
                    />
                    <InputError message={parishHttp.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="new-parish-religion">Wyznanie</Label>
                    <Select
                        value={religion || NONE}
                        onValueChange={(next) =>
                            setReligion(next === NONE ? '' : next)
                        }
                    >
                        <SelectTrigger
                            id="new-parish-religion"
                            className="w-full"
                        >
                            <SelectValue placeholder="brak" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>brak</SelectItem>
                            {religions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={parishHttp.errors.religion} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="new-parish-locality">
                        Miejscowość siedziby
                    </Label>
                    <SuggestField
                        mode="record"
                        id="new-parish-locality"
                        name="locality_id"
                        options={localities}
                        labelOf={(option) => option.name}
                        value={localityId}
                        onChange={setLocalityId}
                        onCreate={createLocality}
                        createLabel={(query) => `Dodaj miejscowość „${query}”`}
                        placeholder="Wpisz nazwę miejscowości"
                        disabled={busy}
                        aria-invalid={
                            parishHttp.errors.locality_id ||
                            localityHttp.errors.name ||
                            localityError
                                ? true
                                : undefined
                        }
                    />
                    <InputError message={parishHttp.errors.locality_id} />
                    <InputError
                        message={localityHttp.errors.name ?? localityError}
                    />
                </div>

                <InputError message={saveError} />

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Anuluj
                        </Button>
                    </DialogClose>
                    <Button type="button" disabled={busy} onClick={save}>
                        {parishHttp.processing && <Spinner />}
                        Dodaj parafię
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
