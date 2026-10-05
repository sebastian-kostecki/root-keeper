import { Form, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import ParishController from '@/actions/App/Http/Controllers/ParishController';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { SidePanel } from '@/components/side-panel';
import { SuggestField } from '@/components/suggest-field';
import { Button } from '@/components/ui/button';
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
import type { LocalityOption, Parish, ReligionOption } from '@/types';

type Props = {
    /** The record to edit, or null for a new one. */
    parish: Parish | null;
    religions: ReligionOption[];
    localities: LocalityOption[];
    onRequestClose: () => void;
    /** Reports whether the form holds unsaved changes. */
    onDirtyChange: (isDirty: boolean) => void;
};

/** Radix Select does not allow an empty item value. */
const NONE = 'none';

/** Name, religion and seat form in the side panel; one component for „Nowa parafia” and editing. */
export function ParishPanel({
    parish,
    religions,
    localities,
    onRequestClose,
    onDirtyChange,
}: Props) {
    return (
        <SidePanel
            title={parish?.name ?? 'Nowa parafia'}
            contentKey={parish?.id ?? 'new'}
            onRequestClose={onRequestClose}
        >
            <ParishForm
                key={parish?.id ?? 'new'}
                parish={parish}
                religions={religions}
                localities={localities}
                onDirtyChange={onDirtyChange}
            />
        </SidePanel>
    );
}

function ParishForm({
    parish,
    religions,
    localities,
    onDirtyChange,
}: Omit<Props, 'onRequestClose'>) {
    const [religion, setReligion] = useState(parish?.religion ?? '');
    const [localityId, setLocalityId] = useState<number | null>(
        parish?.locality_id ?? null,
    );
    const [createdLocalities, setCreatedLocalities] = useState<
        LocalityOption[]
    >([]);
    const [createError, setCreateError] = useState<string | undefined>();
    const http = useHttp<{ name: string }, LocalityOption>({ name: '' });

    const localityOptions = [
        ...localities,
        ...createdLocalities.filter(
            (created) => !localities.some((known) => known.id === created.id),
        ),
    ];

    // Plain HTTP, not a form or a visit: a nested form would submit this one, a visit would reload the panel.
    const createLocality = async (name: string) => {
        setCreateError(undefined);
        http.transform(() => ({ name }));

        try {
            const created = await http.post(storeInlineLocality.url());

            if (created === undefined) {
                return;
            }

            setCreatedLocalities((current) => [...current, created]);
            setLocalityId(created.id);
        } catch {
            setCreateError('Nie udało się dodać miejscowości.');
        }
    };

    return (
        <Form
            id="parish-form"
            {...(parish === null
                ? ParishController.store.form()
                : ParishController.update.form(parish.id))}
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
            className="space-y-4"
        >
            {({ errors, processing, isDirty }) => (
                <>
                    <DirtyReporter
                        isDirty={isDirty}
                        onDirtyChange={onDirtyChange}
                    />

                    <div className="space-y-2">
                        <Label htmlFor="parish-name">Nazwa</Label>
                        <Input
                            id="parish-name"
                            name="name"
                            defaultValue={parish?.name ?? ''}
                            maxLength={255}
                            required
                            aria-invalid={errors.name ? true : undefined}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="parish-religion">Wyznanie</Label>
                        <Select
                            value={religion || NONE}
                            onValueChange={(next) =>
                                setReligion(next === NONE ? '' : next)
                            }
                        >
                            <SelectTrigger
                                id="parish-religion"
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
                        <input type="hidden" name="religion" value={religion} />
                        <InputError message={errors.religion} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="parish-locality">
                            Miejscowość siedziby
                        </Label>
                        <SuggestField
                            mode="record"
                            id="parish-locality"
                            label="Miejscowość siedziby"
                            name="locality_id"
                            options={localityOptions}
                            labelOf={(option) => option.name}
                            value={localityId}
                            onChange={setLocalityId}
                            onCreate={createLocality}
                            createLabel={(query) =>
                                `Dodaj miejscowość „${query}”`
                            }
                            placeholder="Wpisz nazwę miejscowości"
                            disabled={http.processing}
                            aria-invalid={
                                errors.locality_id ||
                                http.errors.name ||
                                createError
                                    ? true
                                    : undefined
                            }
                        />
                        <InputError message={errors.locality_id} />
                        <InputError message={http.errors.name ?? createError} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Zapisz
                    </Button>
                </>
            )}
        </Form>
    );
}
