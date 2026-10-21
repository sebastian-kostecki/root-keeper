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
import { store as storeInlineParish } from '@/routes/parishes/inline';
import type {
    LocalityOption,
    Parish,
    ParishKindOption,
    ReligionOption,
} from '@/types';

type Props = {
    /** Text typed in the parish field; a locality of that name becomes the seat. */
    initialSeatName: string;
    religions: ReligionOption[];
    parishKinds: ParishKindOption[];
    /** Localities known to the form, including those added by earlier dialogs. */
    localities: LocalityOption[];
    onCreated: (parish: Parish) => void;
    onLocalityCreated: (locality: LocalityOption) => void;
    onClose: () => void;
};

type ParishPayload = {
    kind: string;
    dedication: string | null;
    religion: string | null;
    locality_id: number | null;
};

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

/** The locality named like the typed text (case and surrounding spaces ignored), if any. */
function seatNamed(
    localities: LocalityOption[],
    text: string,
): LocalityOption | null {
    const needle = text.trim().toLocaleLowerCase('pl');

    if (needle === '') {
        return null;
    }

    return (
        localities.find(
            (locality) =>
                locality.name.trim().toLocaleLowerCase('pl') === needle,
        ) ?? null
    );
}

/**
 * „Nowa parafia / urząd” over the source panel. It has no `<form>` and saves with plain HTTP: React
 * events cross the portal along the component tree, so a form here would submit the source
 * form, and an Inertia visit would reload the panel and reset what was typed.
 */
export function NewParishDialog({
    initialSeatName,
    religions,
    parishKinds,
    localities,
    onCreated,
    onLocalityCreated,
    onClose,
}: Props) {
    const [initialSeat] = useState(() =>
        seatNamed(localities, initialSeatName),
    );
    const [kind, setKind] = useState('parish');
    const [dedication, setDedication] = useState('');
    const [religion, setReligion] = useState('');
    const [localityId, setLocalityId] = useState<number | null>(
        initialSeat?.id ?? null,
    );
    const [saveError, setSaveError] = useState<string | undefined>();
    const parishHttp = useHttp<ParishPayload, { data: Parish }>({
        kind: 'parish',
        dedication: null,
        religion: null,
        locality_id: null,
    });

    const save = async () => {
        setSaveError(undefined);
        parishHttp.transform(() => ({
            kind,
            dedication: dedication.trim() === '' ? null : dedication,
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
            setSaveError('Nie udało się dodać parafii / urzędu.');
        }
    };

    const busy = parishHttp.processing;

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
                <DialogTitle>Nowa parafia / urząd</DialogTitle>
                <DialogDescription>
                    Parafia lub urząd trafi do spisu i zostanie wybrany w
                    źródle.
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="new-parish-kind">Rodzaj</Label>
                    <Select value={kind} onValueChange={setKind}>
                        <SelectTrigger id="new-parish-kind" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {parishKinds.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={parishHttp.errors.kind} />
                </div>

                <LocalityPicker
                    id="new-parish-locality"
                    name="locality_id"
                    label="Miejscowość siedziby"
                    value={initialSeat}
                    options={localities}
                    errors={parishHttp.errors}
                    onChange={(locality) => setLocalityId(locality?.id ?? null)}
                    onCreated={onLocalityCreated}
                />

                <div className="grid gap-2">
                    <Label htmlFor="new-parish-dedication">
                        Wezwanie / wyróżnik
                    </Label>
                    <Input
                        id="new-parish-dedication"
                        value={dedication}
                        maxLength={255}
                        onChange={(event) => setDedication(event.target.value)}
                        aria-describedby="new-parish-dedication-hint"
                        aria-invalid={
                            parishHttp.errors.dedication ? true : undefined
                        }
                    />
                    <p
                        id="new-parish-dedication-hint"
                        className="text-muted-foreground text-sm"
                    >
                        np. św. Bartłomieja, Fara, Cyrkuł I
                    </p>
                    <InputError message={parishHttp.errors.dedication} />
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
                        {parishHttp.processing && <Spinner />}
                        Dodaj
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
