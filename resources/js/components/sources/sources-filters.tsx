import { FilterIcon } from 'lucide-react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import type { SourcesPlaceOptions, YearFieldsError } from '@/lib/sources-index';

/** Radix Select rejects an empty value, so „Wszystkie” uses this sentinel and maps to ''. */
const ALL_VALUE = '__all__';

/**
 * The fields of the search bar beside the search and the kind switcher. Below `sm` they sit in a block
 * collapsed behind „Filtry (N)”; from `sm` the block is `display: contents` and the fields stand in the bar.
 */
export function SourcesFilterFields({
    place,
    placeOptions,
    archive,
    archiveOptions,
    yearFrom,
    yearTo,
    activeCount,
    showClear,
    yearError,
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
    activeCount: number;
    showClear: boolean;
    yearError: YearFieldsError | null;
    onPlaceChange: (place: string) => void;
    onArchiveChange: (archive: string) => void;
    onYearFromChange: (year: string) => void;
    onYearToChange: (year: string) => void;
    onClear: () => void;
}) {
    const placeId = useId();
    const yearFromId = useId();
    const yearToId = useId();
    const yearErrorId = useId();
    const archiveId = useId();
    const hasPlaces =
        placeOptions.localities.length > 0 || placeOptions.parishes.length > 0;
    const hasArchives = archiveOptions.length > 0;
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Collapsible
            open={isOpen}
            onOpenChange={setIsOpen}
            className="contents"
        >
            <CollapsibleTrigger asChild>
                <Button variant="outline" className="order-4 sm:hidden">
                    <FilterIcon />
                    {activeCount > 0 ? `Filtry (${activeCount})` : 'Filtry'}
                </Button>
            </CollapsibleTrigger>

            {showClear && (
                <Button
                    variant="outline"
                    onClick={onClear}
                    className="order-4 sm:order-8"
                >
                    Wyczyść filtry
                </Button>
            )}

            <CollapsibleContent
                forceMount
                className="order-5 flex w-full flex-wrap gap-3 max-sm:data-[state=closed]:hidden sm:contents"
            >
                <div
                    className="group order-4 flex w-full min-w-0 flex-col gap-1.5 sm:w-64"
                    data-disabled={String(!hasPlaces)}
                >
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
                        onChange={(event) =>
                            onYearFromChange(event.target.value)
                        }
                        aria-invalid={yearError?.from ?? false}
                        aria-describedby={
                            yearError?.from ? yearErrorId : undefined
                        }
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
                        aria-invalid={yearError?.to ?? false}
                        aria-describedby={
                            yearError?.to ? yearErrorId : undefined
                        }
                        className="w-28"
                    />
                </div>

                <InputError
                    id={yearErrorId}
                    message={yearError?.message}
                    className="order-6 basis-full sm:order-9"
                />

                <div
                    className="group order-7 flex w-full min-w-0 flex-col gap-1.5 sm:w-56"
                    data-disabled={String(!hasArchives)}
                >
                    <Label htmlFor={archiveId}>Archiwum</Label>
                    <Select
                        value={archive === '' ? ALL_VALUE : archive}
                        onValueChange={(value) =>
                            onArchiveChange(value === ALL_VALUE ? '' : value)
                        }
                        disabled={!hasArchives}
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
            </CollapsibleContent>
        </Collapsible>
    );
}
