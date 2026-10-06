import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ParishController from '@/actions/App/Http/Controllers/ParishController';
import { DirtyReporter } from '@/components/dirty-reporter';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
import { SidePanel } from '@/components/side-panel';
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
    const seat =
        localities.find((locality) => locality.id === parish?.locality_id) ??
        null;

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

                    <LocalityPicker
                        id="parish-locality"
                        name="locality_id"
                        label="Miejscowość siedziby"
                        value={seat}
                        options={localities}
                        errors={errors}
                    />

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Zapisz
                    </Button>
                </>
            )}
        </Form>
    );
}
