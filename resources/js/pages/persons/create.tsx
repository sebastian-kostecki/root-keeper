import { useState } from 'react';
import { Form, Head, Link } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import { PartialDateFields } from '@/components/persons/partial-date-fields';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { index as personsIndex } from '@/routes/persons';
import type { BreadcrumbItem, Religion } from '@/types';

type Props = {
    religions: Religion[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Osoby', href: personsIndex() },
    { title: 'Dodaj osobę', href: PersonController.create() },
];

/** Radix Select does not allow an empty item value, so "no religion" uses a sentinel. */
const NO_RELIGION = 'none';

export default function Create({ religions }: Props) {
    const [isDeceased, setIsDeceased] = useState(false);
    const [religion, setReligion] = useState('');

    return (
        <>
            <Head title="Dodaj osobę" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Dodaj osobę"
                    description="Podaj dane osoby, którą chcesz dodać do drzewa"
                />

                <Form {...PersonController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">Imię</Label>
                                    <Input
                                        id="first_name"
                                        name="first_name"
                                        maxLength={255}
                                        autoFocus
                                    />
                                    <InputError message={errors.first_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">Nazwisko</Label>
                                    <Input
                                        id="last_name"
                                        name="last_name"
                                        maxLength={255}
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="residence">
                                    Miejsce zamieszkania
                                </Label>
                                <Input
                                    id="residence"
                                    name="residence"
                                    maxLength={255}
                                />
                                <InputError message={errors.residence} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="religion">Wyznanie</Label>
                                <Select
                                    value={religion || NO_RELIGION}
                                    onValueChange={(value) =>
                                        setReligion(
                                            value === NO_RELIGION ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="religion"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Brak danych" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_RELIGION}>
                                            Brak danych
                                        </SelectItem>
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
                                <input
                                    type="hidden"
                                    name="religion"
                                    value={religion}
                                />
                                <InputError message={errors.religion} />
                            </div>

                            <Separator />

                            <div className="space-y-4">
                                <Heading variant="small" title="Urodzenie" />
                                <PartialDateFields
                                    prefix="birth"
                                    errors={errors}
                                />
                            </div>

                            <Separator />

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="is_deceased"
                                    name="is_deceased"
                                    value="1"
                                    checked={isDeceased}
                                    onCheckedChange={(checked) =>
                                        setIsDeceased(checked === true)
                                    }
                                />
                                <Label htmlFor="is_deceased">Zmarły/a</Label>
                            </div>
                            <InputError message={errors.is_deceased} />

                            {isDeceased && (
                                <div className="space-y-4">
                                    <Heading variant="small" title="Zgon" />
                                    <PartialDateFields
                                        prefix="death"
                                        errors={errors}
                                    />
                                </div>
                            )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="create-person-button"
                                >
                                    Zapisz
                                </Button>

                                <Button variant="outline" asChild>
                                    <Link href={personsIndex()}>Anuluj</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Create.layout = {
    breadcrumbs,
};
