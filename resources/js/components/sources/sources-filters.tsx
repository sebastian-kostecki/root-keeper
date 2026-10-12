import {
    CalendarDays,
    CheckIcon,
    FilterIcon,
    Landmark,
    MapPin,
    Plus,
    XIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useId, useRef, useState } from 'react';
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { focusRing, focusRingOffset } from '@/lib/focus-ring';
import { pluralPhrase } from '@/lib/plural';
import {
    formatYearsFilter,
    type SourcesPlaceOptions,
    type YearFieldsError,
} from '@/lib/sources-index';
import { cn } from '@/lib/utils';

type ListOption = { value: string; label: string };

/** A group of the list; `heading` is null for a list without groups (Archiwum). */
type ListGroup = { heading: string | null; options: ListOption[] };

/** A list with more options than this (without „Wszystkie …”) gets a search field. */
const SEARCH_THRESHOLD = 7;

function sourcesCountText(count: number): string {
    return `${count} ${pluralPhrase(count, 'źródło', 'źródła', 'źródeł')}`;
}

/** Keyboard for the list: arrows and Home/End move between the enabled options, Enter and Space pick. */
function moveOptionFocus(event: KeyboardEvent<HTMLDivElement>) {
    const moves = ['ArrowDown', 'ArrowUp', 'Home', 'End'];

    if (!moves.includes(event.key)) {
        return;
    }

    const options = Array.from(
        event.currentTarget.querySelectorAll<HTMLElement>(
            '[role="option"]:not([aria-disabled="true"])',
        ),
    );

    if (options.length === 0) {
        return;
    }

    const current = options.indexOf(event.target as HTMLElement);
    const last = options.length - 1;
    const next =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? last
              : event.key === 'ArrowDown'
                ? (current + 1) % options.length
                : current <= 0
                  ? last
                  : current - 1;

    event.preventDefault();
    options[next].focus();
}

/** The body of the Miejsce and Archiwum popovers: a search field when the list is long, then the options. */
function FilterList({
    name,
    allLabel,
    searchLabel,
    groups,
    selected,
    counts,
    onSelect,
    onDone,
}: {
    name: string;
    allLabel: string;
    searchLabel: string;
    groups: ListGroup[];
    selected: string;
    counts: Record<string, number>;
    onSelect: (value: string) => void;
    onDone: () => void;
}) {
    const [query, setQuery] = useState('');
    const headingIdPrefix = useId();
    const optionTotal = groups.reduce(
        (total, group) => total + group.options.length,
        0,
    );
    const needle = query.trim().toLocaleLowerCase('pl');
    const shownGroups = groups
        .map((group) => ({
            ...group,
            options: group.options.filter((option) =>
                option.label.toLocaleLowerCase('pl').includes(needle),
            ),
        }))
        .filter((group) => group.options.length > 0);

    const renderOption = (option: ListOption) => {
        const count = counts[option.value] ?? 0;
        const isSelected = option.value === selected;
        const isDisabled = option.value !== '' && !isSelected && count === 0;

        return (
            <button
                key={option.value}
                type="button"
                role="option"
                aria-selected={isSelected}
                aria-disabled={isDisabled ? 'true' : undefined}
                onClick={() => {
                    if (!isDisabled) {
                        onSelect(option.value);
                        onDone();
                    }
                }}
                className={cn(
                    'hover:bg-accent focus:bg-accent flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm',
                    focusRing,
                    isSelected && 'bg-accent',
                    isDisabled &&
                        'text-muted-foreground pointer-events-none opacity-50',
                )}
            >
                {isSelected ? (
                    <CheckIcon className="text-primary size-4 shrink-0" />
                ) : (
                    <span className="size-4 shrink-0" />
                )}
                <span className="min-w-0 flex-1 truncate">{option.label}</span>
                <span
                    aria-hidden="true"
                    className="text-muted-foreground text-xs tabular-nums"
                >
                    {count}
                </span>
                <span className="sr-only">, {sourcesCountText(count)}</span>
            </button>
        );
    };

    return (
        <div className="flex flex-col gap-2" onKeyDown={moveOptionFocus}>
            {optionTotal > SEARCH_THRESHOLD && (
                <Input
                    type="search"
                    aria-label={searchLabel}
                    placeholder="Szukaj"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    className="h-8"
                />
            )}

            <div
                role="listbox"
                aria-label={name}
                className="flex max-h-72 flex-col gap-0.5 overflow-y-auto"
            >
                {renderOption({ value: '', label: allLabel })}

                {shownGroups.map((group, index) =>
                    group.heading === null ? (
                        group.options.map(renderOption)
                    ) : (
                        <div
                            key={group.heading}
                            role="group"
                            aria-labelledby={`${headingIdPrefix}-${index}`}
                            className="flex flex-col gap-0.5"
                        >
                            <div
                                id={`${headingIdPrefix}-${index}`}
                                className="text-muted-foreground px-2 pt-2 pb-1 text-xs"
                            >
                                {group.heading}
                            </div>
                            {group.options.map(renderOption)}
                        </div>
                    ),
                )}
            </div>
        </div>
    );
}

/**
 * One filter pill: the value when active (with an ✕ to clear it), or a dashed „+ name” when not. It opens
 * `children`, a popover. Without data (`disabledReason`) it is disabled and the reason is a tooltip and
 * a description for screen readers.
 */
function FilterPill({
    name,
    value,
    activeIcon: ActiveIcon,
    ariaHasPopup,
    removeLabel,
    onClear,
    disabledReason,
    children,
}: {
    name: string;
    value: string | null;
    activeIcon: LucideIcon;
    ariaHasPopup: 'listbox' | 'dialog';
    removeLabel: string;
    onClear: () => void;
    disabledReason?: string;
    children?: (close: () => void) => ReactNode;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const reasonId = useId();
    const isActive = value !== null;
    const Icon = isActive ? ActiveIcon : Plus;

    const triggerClass = cn(
        'data-[state=open]:border-primary inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium whitespace-nowrap transition-colors disabled:pointer-events-none disabled:opacity-50 max-sm:h-11 max-sm:min-w-0 max-sm:flex-1',
        focusRingOffset,
        isActive
            ? 'border-primary/35 bg-accent text-accent-foreground border'
            : 'border-input text-muted-foreground hover:bg-accent hover:text-accent-foreground border border-dashed',
    );

    if (disabledReason !== undefined) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className="inline-flex max-sm:w-full">
                        <button
                            type="button"
                            disabled
                            aria-describedby={reasonId}
                            className={cn(triggerClass, 'max-sm:w-full')}
                        >
                            <Icon className="size-3.5 shrink-0" />
                            {name}
                        </button>
                        <span id={reasonId} className="sr-only">
                            {disabledReason}
                        </span>
                    </span>
                </TooltipTrigger>
                <TooltipContent>{disabledReason}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <div className="inline-flex items-center gap-1 max-sm:w-full">
                <PopoverTrigger
                    ref={triggerRef}
                    aria-haspopup={ariaHasPopup}
                    className={triggerClass}
                >
                    <Icon className="size-3.5 shrink-0" />
                    {isActive ? (
                        <span>
                            {`${name}: `}
                            <span className="font-semibold">{value}</span>
                        </span>
                    ) : (
                        name
                    )}
                </PopoverTrigger>

                {isActive && (
                    <button
                        type="button"
                        aria-label={`Usuń filtr: ${removeLabel}`}
                        onClick={() => {
                            onClear();
                            triggerRef.current?.focus();
                        }}
                        className={cn(
                            'border-primary/35 bg-accent text-accent-foreground inline-flex size-8 items-center justify-center rounded-md border max-sm:h-11 max-sm:w-11',
                            focusRing,
                        )}
                    >
                        <XIcon className="size-3.5" />
                    </button>
                )}
            </div>

            {children?.(() => setIsOpen(false))}
        </Popover>
    );
}

/**
 * The filter row of /sources: three pills (Miejsce, Lata, Archiwum), „Wyczyść filtry” and the result count.
 * Below `sm` the pills sit in a block behind „Filtry (N)”; from `sm` the block is `display: contents`.
 */
export function SourcesFilterBar({
    place,
    placeOptions,
    archive,
    archiveOptions,
    committedYears,
    yearFrom,
    yearTo,
    yearError,
    activeCount,
    showClear,
    placeCounts,
    archiveCounts,
    yearPresets,
    resultText,
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
    committedYears: { from: number | null; to: number | null };
    yearFrom: string;
    yearTo: string;
    yearError: YearFieldsError | null;
    activeCount: number;
    showClear: boolean;
    placeCounts: Record<string, number>;
    archiveCounts: Record<string, number>;
    yearPresets: { from: number; to: number }[];
    resultText: string;
    onPlaceChange: (place: string) => void;
    onArchiveChange: (archive: string) => void;
    onYearFromChange: (year: string) => void;
    onYearToChange: (year: string) => void;
    onClear: () => void;
}) {
    const [isMobileOpen, setIsMobileOpen] = useState(false);
    const yearFromId = useId();
    const yearToId = useId();
    const yearErrorId = useId();

    const placeGroups: ListGroup[] = [
        {
            heading: 'Miejscowości',
            options: placeOptions.localities.map((locality) => ({
                value: `miejscowosc-${locality.id}`,
                label: locality.name,
            })),
        },
        {
            heading: 'Parafie',
            options: placeOptions.parishes.map((parish) => ({
                value: `parafia-${parish.id}`,
                label: parish.label,
            })),
        },
    ].filter((group) => group.options.length > 0);
    const archiveGroups: ListGroup[] = [
        {
            heading: null,
            options: archiveOptions.map((option) => ({
                value: option,
                label: option,
            })),
        },
    ];
    const hasPlaces = placeGroups.length > 0;
    const hasArchives = archiveOptions.length > 0;
    const placeLabel =
        placeGroups
            .flatMap((group) => group.options)
            .find((option) => option.value === place)?.label ?? place;
    const yearsLabel = formatYearsFilter(
        committedYears.from,
        committedYears.to,
    );
    const hasYears = yearsLabel !== '';

    return (
        <div
            role="group"
            aria-label="Filtry"
            className="flex flex-wrap items-center gap-3"
        >
            <Collapsible
                open={isMobileOpen}
                onOpenChange={setIsMobileOpen}
                className="contents"
            >
                <CollapsibleTrigger asChild>
                    <Button
                        variant="outline"
                        className="order-1 h-11 sm:hidden"
                    >
                        <FilterIcon />
                        {activeCount > 0 ? `Filtry (${activeCount})` : 'Filtry'}
                    </Button>
                </CollapsibleTrigger>

                <CollapsibleContent
                    forceMount
                    className="bg-muted order-2 flex w-full flex-col gap-2 rounded-lg p-3 max-sm:data-[state=closed]:hidden sm:contents"
                >
                    <FilterPill
                        name="Miejsce"
                        value={place === '' ? null : placeLabel}
                        activeIcon={MapPin}
                        ariaHasPopup="listbox"
                        removeLabel={`miejsce ${placeLabel}`}
                        onClear={() => onPlaceChange('')}
                        disabledReason={
                            hasPlaces
                                ? undefined
                                : 'Żadne źródło nie ma jeszcze miejsca'
                        }
                    >
                        {(close) => (
                            <PopoverContent
                                aria-label="Miejsce"
                                align="start"
                                className="w-80 rounded-lg p-2"
                            >
                                <FilterList
                                    name="Miejsce"
                                    allLabel="Wszystkie miejsca"
                                    searchLabel="Szukaj miejsca"
                                    groups={placeGroups}
                                    selected={place}
                                    counts={placeCounts}
                                    onSelect={onPlaceChange}
                                    onDone={close}
                                />
                            </PopoverContent>
                        )}
                    </FilterPill>

                    <FilterPill
                        name="Lata"
                        value={hasYears ? yearsLabel : null}
                        activeIcon={CalendarDays}
                        ariaHasPopup="dialog"
                        removeLabel={`lata ${yearsLabel}`}
                        onClear={() => {
                            onYearFromChange('');
                            onYearToChange('');
                        }}
                    >
                        {() => (
                            <PopoverContent
                                aria-label="Lata"
                                align="start"
                                className="w-80 rounded-lg p-4"
                            >
                                <div className="flex flex-col gap-3">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor={yearFromId}>
                                                Rok od
                                            </Label>
                                            <Input
                                                id={yearFromId}
                                                type="text"
                                                inputMode="numeric"
                                                value={yearFrom}
                                                onChange={(event) =>
                                                    onYearFromChange(
                                                        event.target.value,
                                                    )
                                                }
                                                aria-invalid={
                                                    yearError?.from ?? false
                                                }
                                                aria-describedby={
                                                    yearError?.from
                                                        ? yearErrorId
                                                        : undefined
                                                }
                                            />
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor={yearToId}>
                                                Rok do
                                            </Label>
                                            <Input
                                                id={yearToId}
                                                type="text"
                                                inputMode="numeric"
                                                value={yearTo}
                                                onChange={(event) =>
                                                    onYearToChange(
                                                        event.target.value,
                                                    )
                                                }
                                                aria-invalid={
                                                    yearError?.to ?? false
                                                }
                                                aria-describedby={
                                                    yearError?.to
                                                        ? yearErrorId
                                                        : undefined
                                                }
                                            />
                                        </div>
                                    </div>

                                    <InputError
                                        id={yearErrorId}
                                        message={yearError?.message}
                                    />

                                    {yearPresets.length > 0 && (
                                        <div
                                            role="group"
                                            aria-label="Szybki wybór"
                                            className="flex flex-wrap gap-2"
                                        >
                                            {yearPresets.map((preset) => (
                                                <Button
                                                    key={preset.from}
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        onYearFromChange(
                                                            String(preset.from),
                                                        );
                                                        onYearToChange(
                                                            String(preset.to),
                                                        );
                                                    }}
                                                >
                                                    {`${preset.from} – ${preset.to}`}
                                                </Button>
                                            ))}
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between gap-2 border-t pt-3">
                                        <p className="text-muted-foreground text-xs">
                                            Lista odświeża się po wpisaniu roku
                                        </p>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                onYearFromChange('');
                                                onYearToChange('');
                                            }}
                                        >
                                            Wyczyść lata
                                        </Button>
                                    </div>
                                </div>
                            </PopoverContent>
                        )}
                    </FilterPill>

                    <FilterPill
                        name="Archiwum"
                        value={archive === '' ? null : archive}
                        activeIcon={Landmark}
                        ariaHasPopup="listbox"
                        removeLabel={`archiwum ${archive}`}
                        onClear={() => onArchiveChange('')}
                        disabledReason={
                            hasArchives
                                ? undefined
                                : 'Żadne źródło nie ma jeszcze archiwum'
                        }
                    >
                        {(close) => (
                            <PopoverContent
                                aria-label="Archiwum"
                                align="start"
                                className="w-80 rounded-lg p-2"
                            >
                                <FilterList
                                    name="Archiwum"
                                    allLabel="Wszystkie archiwa"
                                    searchLabel="Szukaj archiwum"
                                    groups={archiveGroups}
                                    selected={archive}
                                    counts={archiveCounts}
                                    onSelect={onArchiveChange}
                                    onDone={close}
                                />
                            </PopoverContent>
                        )}
                    </FilterPill>

                    {showClear && (
                        <Button variant="ghost" size="sm" onClick={onClear}>
                            Wyczyść filtry
                        </Button>
                    )}
                </CollapsibleContent>
            </Collapsible>

            <p
                role="status"
                aria-live="polite"
                className="text-muted-foreground order-1 ml-auto text-sm tabular-nums sm:order-last"
            >
                {resultText}
            </p>
        </div>
    );
}
