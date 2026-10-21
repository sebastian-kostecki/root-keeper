import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
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
import { store as storeInlineCemetery } from '@/routes/cemeteries/inline';
import type {
    Cemetery,
    CemeteryManagerOption,
    CemeteryOption,
    LocalityOption,
    ReligionOption,
} from '@/types';

type Props = {
    /** Name typed in the cemetery field; the dialog opens with it filled in. */
    initialName: string;
    /** The locality the dialog starts with, when it is among `localities`. */
    localityId?: number | null;
    managers: CemeteryManagerOption[];
    religions: ReligionOption[];
    /** Localities known to the form; when omitted the shared `localityOptions` prop is loaded. */
    localities?: LocalityOption[];
    onCreated: (cemetery: CemeteryOption) => void;
    onLocalityCreated?: (locality: LocalityOption) => void;
    onClose: () => void;
};

type CemeteryPayload = {
    name: string;
    locality_id: number | null;
    manager: string | null;
    religion: string | null;
};

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

/**
 * „Nowy cmentarz” over a form. It has no `<form>` and saves with plain HTTP: React events cross
 * the portal along the component tree, so a form here would submit the surrounding one, and an
 * Inertia visit would reload the panel and reset what was typed.
 */
export function NewCemeteryDialog({
    initialName,
    localityId = null,
    managers,
    religions,
    localities,
    onCreated,
    onLocalityCreated,
    onClose,
}: Props) {
    const initialLocality =
        localityId === null
            ? null
            : (localities?.find((locality) => locality.id === localityId) ??
              null);
    const [name, setName] = useState(initialName);
    const [manager, setManager] = useState('');
    const [religion, setReligion] = useState('');
    const [chosenLocalityId, setChosenLocalityId] = useState<number | null>(
        initialLocality?.id ?? null,
    );
    const [saveError, setSaveError] = useState<string | undefined>();
    const http = useHttp<CemeteryPayload, { data: Cemetery }>({
        name: '',
        locality_id: null,
        manager: null,
        religion: null,
    });

    const save = async () => {
        setSaveError(undefined);
        http.transform(() => ({
            name,
            locality_id: chosenLocalityId,
            manager: manager === '' ? null : manager,
            religion: religion === '' ? null : religion,
        }));

        try {
            const response = await http.post(storeInlineCemetery.url());

            if (response === undefined) {
                return;
            }

            const cemetery = response.data;

            onCreated({
                id: cemetery.id,
                label: cemetery.label,
                locality_id: cemetery.locality_id,
                locality_name: cemetery.locality_name,
            });
            onClose();
        } catch {
            setSaveError('Nie udało się dodać cmentarza.');
        }
    };

    const busy = http.processing;

    return (
        <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
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
                <DialogTitle>Nowy cmentarz</DialogTitle>
                <DialogDescription>
                    Cmentarz trafi do spisu cmentarzy i zostanie wybrany.
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="new-cemetery-name">Nazwa</Label>
                    <Input
                        id="new-cemetery-name"
                        value={name}
                        maxLength={255}
                        onChange={(event) => setName(event.target.value)}
                        aria-invalid={http.errors.name ? true : undefined}
                        aria-describedby="new-cemetery-name-hint"
                    />
                    <p
                        id="new-cemetery-name-hint"
                        className="text-muted-foreground text-sm"
                    >
                        {'Puste — „cmentarz {miejscowość}”'}
                    </p>
                    <InputError message={http.errors.name} />
                </div>

                <LocalityPicker
                    id="new-cemetery-locality"
                    name="locality_id"
                    label="Miejscowość"
                    value={initialLocality}
                    options={localities}
                    errors={http.errors}
                    onChange={(locality) =>
                        setChosenLocalityId(locality?.id ?? null)
                    }
                    onCreated={onLocalityCreated}
                />

                <div className="grid gap-2">
                    <Label htmlFor="new-cemetery-manager">Zarządca</Label>
                    <Select
                        value={manager || NONE}
                        onValueChange={(next) =>
                            setManager(next === NONE ? '' : next)
                        }
                    >
                        <SelectTrigger
                            id="new-cemetery-manager"
                            className="w-full"
                        >
                            <SelectValue placeholder="brak" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>brak</SelectItem>
                            {managers.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={http.errors.manager} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="new-cemetery-religion">Wyznanie</Label>
                    <Select
                        value={religion || NONE}
                        onValueChange={(next) =>
                            setReligion(next === NONE ? '' : next)
                        }
                    >
                        <SelectTrigger
                            id="new-cemetery-religion"
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
                    <InputError message={http.errors.religion} />
                </div>

                <InputError message={saveError} />

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={busy}
                        >
                            Anuluj
                        </Button>
                    </DialogClose>
                    <Button type="button" disabled={busy} onClick={save}>
                        {http.processing && <Spinner />}
                        Dodaj cmentarz
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
