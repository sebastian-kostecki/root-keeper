import { PlusIcon, Trash2Icon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { LocalityPicker } from '@/components/localities/locality-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOptionalList } from '@/hooks/use-relation-candidates';
import type { LocalityOption, PersonResidence } from '@/types';
import { usePage } from '@inertiajs/react';

type Props = {
    residences: PersonResidence[];
    errors: Record<string, string>;
};

type Row = {
    key: string;
    locality: LocalityOption | null;
    yearFrom: number | null;
    yearTo: number | null;
};

const currentYear = new Date().getFullYear();

/**
 * Rows of a person's residences: locality, year from, year to. Rows are keyed by a
 * stable key (not the index) so a picker keeps its state when another row is removed;
 * field names carry the row's position, which is where the server reports errors.
 */
export function ResidencesFields({ residences, errors }: Props) {
    const shared = usePage().props.localityOptions;
    const { list, load } = useOptionalList<LocalityOption>(
        'localityOptions',
        shared,
    );
    const requested = useRef(false);
    const nextKey = useRef(0);
    const [created, setCreated] = useState<LocalityOption[]>([]);
    const [rows, setRows] = useState<Row[]>(() =>
        residences.map((residence) => ({
            key: `residence-${residence.id}`,
            locality: residence.locality,
            yearFrom: residence.year_from,
            yearTo: residence.year_to,
        })),
    );

    const needsLoad = list === undefined;

    useEffect(() => {
        if (needsLoad && !requested.current) {
            requested.current = true;
            load();
        }
    }, [needsLoad, load]);

    // Localities added in any row are offered in every row.
    const known = list ?? [];
    const options = [
        ...known,
        ...created.filter(
            (locality) => !known.some((option) => option.id === locality.id),
        ),
    ];

    const addRow = () => {
        nextKey.current += 1;
        setRows((current) => [
            ...current,
            {
                key: `new-${nextKey.current}`,
                locality: null,
                yearFrom: null,
                yearTo: null,
            },
        ]);
    };

    const removeRow = (key: string) =>
        setRows((current) => current.filter((row) => row.key !== key));

    return (
        <div className="space-y-4">
            {rows.length === 0 && (
                <p className="text-muted-foreground text-sm">
                    Brak zamieszkań.
                </p>
            )}

            {rows.map((row, index) => {
                const prefix = `residences.${index}`;

                return (
                    <div
                        key={row.key}
                        role="group"
                        aria-label={`Zamieszkanie ${index + 1}`}
                        className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_7rem_7rem_auto] sm:items-start"
                    >
                        <LocalityPicker
                            id={`${prefix}.locality_id`}
                            name={`${prefix}.locality_id`}
                            label="Miejscowość"
                            value={row.locality}
                            options={options}
                            errors={errors}
                            onCreated={(locality) =>
                                setCreated((current) => [...current, locality])
                            }
                        />
                        {(['year_from', 'year_to'] as const).map((field) => (
                            <div key={field} className="grid gap-2">
                                <Label htmlFor={`${prefix}.${field}`}>
                                    {field === 'year_from'
                                        ? 'Od roku'
                                        : 'Do roku'}
                                </Label>
                                <Input
                                    id={`${prefix}.${field}`}
                                    name={`${prefix}.${field}`}
                                    type="number"
                                    min={1000}
                                    max={currentYear}
                                    defaultValue={
                                        (field === 'year_from'
                                            ? row.yearFrom
                                            : row.yearTo) ?? undefined
                                    }
                                />
                                <InputError
                                    message={errors[`${prefix}.${field}`]}
                                />
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="sm:mt-6"
                            aria-label={`Usuń zamieszkanie ${index + 1}`}
                            title={`Usuń zamieszkanie ${index + 1}`}
                            onClick={() => removeRow(row.key)}
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                );
            })}

            <Button type="button" variant="outline" size="sm" onClick={addRow}>
                <PlusIcon />
                Dodaj zamieszkanie
            </Button>
        </div>
    );
}
