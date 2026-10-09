import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SourcesPlaceOptions } from '@/lib/sources-index';

/** Radix Select rejects an empty value, so „Wszystkie” uses this sentinel and maps to ''. */
const ALL_VALUE = '__all__';

/** The fields of the search bar beside the search and the kind switcher; they wrap on narrow screens. */
export function SourcesFilterFields({
    place,
    placeOptions,
    archive,
    archiveOptions,
    yearFrom,
    yearTo,
    showClear,
    onPlaceChange,
    onArchiveChange,
    onYearFromChange,
    onYearToChange,
    onClear,
}: {
    place: string;
    placeOptions: SourcesPlaceOptions;
    archive: string;
    archiveOptions: string[];
    yearFrom: string;
    yearTo: string;
    showClear: boolean;
    onPlaceChange: (place: string) => void;
    onArchiveChange: (archive: string) => void;
    onYearFromChange: (year: string) => void;
    onYearToChange: (year: string) => void;
    onClear: () => void;
}) {
    const placeId = useId();
    const yearFromId = useId();
    const yearToId = useId();
    const archiveId = useId();
    const hasPlaces =
        placeOptions.localities.length > 0 || placeOptions.parishes.length > 0;

    return (
        <>
            <div className="order-4 flex w-full min-w-0 flex-col gap-1.5 sm:w-64">
                <Label htmlFor={placeId}>Parafia / miejsce</Label>
                <Select
                    value={place === '' ? ALL_VALUE : place}
                    onValueChange={(value) =>
                        onPlaceChange(value === ALL_VALUE ? '' : value)
                    }
                    disabled={!hasPlaces}
                >
                    <SelectTrigger id={placeId} className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_VALUE}>Wszystkie</SelectItem>
                        {placeOptions.localities.length > 0 && (
                            <SelectGroup>
                                <SelectLabel>Miejscowości</SelectLabel>
                                {placeOptions.localities.map((locality) => (
                                    <SelectItem
                                        key={`miejscowosc-${locality.id}`}
                                        value={`miejscowosc-${locality.id}`}
                                    >
                                        {locality.name}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        )}
                        {placeOptions.parishes.length > 0 && (
                            <SelectGroup>
                                <SelectLabel>Parafie</SelectLabel>
                                {placeOptions.parishes.map((parish) => (
                                    <SelectItem
                                        key={`parafia-${parish.id}`}
                                        value={`parafia-${parish.id}`}
                                    >
                                        {parish.label}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        )}
                    </SelectContent>
                </Select>
            </div>

            <div className="order-5 flex flex-col gap-1.5">
                <Label htmlFor={yearFromId}>Rok od</Label>
                <Input
                    id={yearFromId}
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={9999}
                    value={yearFrom}
                    onChange={(event) => onYearFromChange(event.target.value)}
                    className="w-28"
                />
            </div>

            <div className="order-6 flex flex-col gap-1.5">
                <Label htmlFor={yearToId}>Rok do</Label>
                <Input
                    id={yearToId}
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={9999}
                    value={yearTo}
                    onChange={(event) => onYearToChange(event.target.value)}
                    className="w-28"
                />
            </div>

            <div className="order-7 flex w-full min-w-0 flex-col gap-1.5 sm:w-56">
                <Label htmlFor={archiveId}>Archiwum</Label>
                <Select
                    value={archive === '' ? ALL_VALUE : archive}
                    onValueChange={(value) =>
                        onArchiveChange(value === ALL_VALUE ? '' : value)
                    }
                    disabled={archiveOptions.length === 0}
                >
                    <SelectTrigger id={archiveId} className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL_VALUE}>Wszystkie</SelectItem>
                        {archiveOptions.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {showClear && (
                <div className="order-8">
                    <Button variant="outline" onClick={onClear}>
                        Wyczyść filtry
                    </Button>
                </div>
            )}
        </>
    );
}
