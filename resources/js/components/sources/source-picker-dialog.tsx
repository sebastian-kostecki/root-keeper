import { Link, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import EventSourceController from '@/actions/App/Http/Controllers/EventSourceController';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import InputError from '@/components/input-error';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { pluralPhrase } from '@/lib/plural';
import { formatSourceMeta } from '@/lib/source-description';
import { filterSourcesByPlaceAndYear } from '@/lib/source-place-year-filter';
import { splitSourcesForEvent } from '@/lib/source-matching';
import type { PersonEvent, Source } from '@/types';

const groupLabelClass =
    'text-muted-foreground text-xs font-semibold tracking-wide uppercase';

type Props = {
    eventId: number;
    eventType: PersonEvent['type'];
    eventYear: number | null;
    /** Prefills the „Miejsce” filter; the user can clear it. */
    eventPlace: string | null;
    linkedSourceIds: number[];
    availableSources: Source[] | undefined;
    trigger: React.ReactNode;
};

export function SourcePickerDialog({
    eventId,
    eventType,
    eventYear,
    eventPlace,
    linkedSourceIds,
    availableSources,
    trigger,
}: Props) {
    const matchingHeadingId = useId();
    const restHeadingId = useId();
    const placeId = useId();
    const entryNumberId = useId();
    const yearId = useId();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [place, setPlace] = useState('');
    const [year, setYear] = useState('');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [entryNumber, setEntryNumber] = useState('');
    const [error, setError] = useState<string | undefined>(undefined);
    const [entryNumberError, setEntryNumberError] = useState<
        string | undefined
    >(undefined);

    // The optional prop is dropped by any non-partial visit (e.g. the redirect
    // back after a validation error), so keep the last list that was loaded.
    const [sources, setSources] = useState(availableSources);

    if (availableSources !== undefined && availableSources !== sources) {
        setSources(availableSources);
    }

    const filtered = filterSourcesByPlaceAndYear(sources ?? [], {
        place,
        year,
    });
    const filtersActive = place.trim() !== '' || /^\d+$/.test(year.trim());
    const selectedSource = sources?.find((source) => source.id === selectedId);

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setError(undefined);
            setEntryNumberError(undefined);
            setPlace(eventPlace ?? '');
            setYear(eventYear === null ? '' : String(eventYear));
            setSelectedId(null);
            setEntryNumber('');
            setLoaded(false);
            setLoading(true);
            router.reload({
                only: ['availableSources'],
                onSuccess: () => setLoaded(true),
                onFinish: () => setLoading(false),
            });
        }
    };

    const handleLink = () => {
        if (selectedId === null) {
            return;
        }

        setProcessing(true);
        setError(undefined);
        setEntryNumberError(undefined);

        const trimmedNumber =
            selectedSource?.kind === 'gravestone' ? '' : entryNumber.trim();

        router.post(
            EventSourceController.store(eventId).url,
            {
                source_id: selectedId,
                entry_number: trimmedNumber === '' ? null : trimmedNumber,
            },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (errors) => {
                    setError(errors.source_id);
                    setEntryNumberError(errors.entry_number);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const clearFilters = () => {
        setPlace('');
        setYear('');
    };

    const renderTile = (source: Source) => {
        const isLinked = linkedSourceIds.includes(source.id);
        const meta = formatSourceMeta(source);

        return (
            <button
                key={source.id}
                type="button"
                disabled={isLinked || processing}
                aria-pressed={selectedId === source.id}
                onClick={() =>
                    setSelectedId(selectedId === source.id ? null : source.id)
                }
                className={`space-y-1 self-start rounded-md border-2 p-1 text-left disabled:cursor-not-allowed disabled:opacity-50 ${
                    selectedId === source.id
                        ? 'border-primary'
                        : 'border-transparent'
                }`}
            >
                <SourceThumbnail source={source} className="rounded-md" />
                <p
                    className="truncate text-xs font-medium"
                    title={source.display_name}
                >
                    {source.display_name}
                </p>
                <Badge variant="secondary">{source.kind_short_label}</Badge>
                {meta !== null && (
                    <p className="text-muted-foreground text-xs">{meta}</p>
                )}
                {(isLinked || meta === null) && (
                    <p className="text-muted-foreground text-xs">
                        {isLinked
                            ? 'Już powiązane'
                            : new Date(source.uploaded_at).toLocaleDateString(
                                  'pl-PL',
                              )}
                    </p>
                )}
            </button>
        );
    };

    const renderGrid = (list: Source[]) => (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {list.map(renderTile)}
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>Powiąż źródło</DialogTitle>
                <DialogDescription>
                    Wybierz źródło, które potwierdza to wydarzenie.
                </DialogDescription>

                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor={placeId}>Miejsce</Label>
                        <Input
                            id={placeId}
                            value={place}
                            onChange={(event) => setPlace(event.target.value)}
                            autoComplete="off"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={yearId}>Rok</Label>
                        <Input
                            id={yearId}
                            value={year}
                            inputMode="numeric"
                            onChange={(event) => setYear(event.target.value)}
                            autoComplete="off"
                        />
                    </div>
                </div>

                {loading ? (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {Array.from({ length: 6 }).map((_, index) => (
                            <Skeleton
                                key={index}
                                className="aspect-square w-full"
                            />
                        ))}
                    </div>
                ) : !loaded || sources === undefined ? (
                    <p className="text-muted-foreground text-sm">
                        Nie udało się wczytać źródeł. Zamknij okno i spróbuj
                        ponownie.
                    </p>
                ) : sources.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Nie masz jeszcze żadnych źródeł.{' '}
                        <Link
                            href={SourceController.index({
                                query: { source: 'new' },
                            })}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            Dodaj źródło
                        </Link>
                        .
                    </p>
                ) : (
                    <div className="max-h-96 space-y-4 overflow-y-auto">
                        {(() => {
                            const { visible } = filtered;
                            const { matching, rest } = splitSourcesForEvent(
                                visible,
                                eventType,
                            );

                            if (visible.length === 0) {
                                return (
                                    <p className="text-muted-foreground text-sm">
                                        Żadne źródło nie pasuje do filtrów.
                                    </p>
                                );
                            }

                            if (matching.length === 0) {
                                return renderGrid(rest);
                            }

                            return (
                                <>
                                    <section
                                        aria-labelledby={matchingHeadingId}
                                        className="space-y-2"
                                    >
                                        <h3
                                            id={matchingHeadingId}
                                            className={groupLabelClass}
                                        >
                                            Pasujące akty
                                        </h3>
                                        {renderGrid(matching)}
                                    </section>
                                    {rest.length > 0 && (
                                        <section
                                            aria-labelledby={restHeadingId}
                                            className="space-y-2"
                                        >
                                            <h3
                                                id={restHeadingId}
                                                className={groupLabelClass}
                                            >
                                                Pozostałe źródła
                                            </h3>
                                            {renderGrid(rest)}
                                        </section>
                                    )}
                                </>
                            );
                        })()}
                    </div>
                )}

                {loaded && sources !== undefined && sources.length > 0 && (
                    <>
                        {filtersActive && (
                            <div className="flex flex-wrap items-center gap-3 text-sm">
                                {filtered.hiddenUndescribedCount > 0 && (
                                    <p className="text-muted-foreground">
                                        Ukryto {filtered.hiddenUndescribedCount}{' '}
                                        {pluralPhrase(
                                            filtered.hiddenUndescribedCount,
                                            'źródło',
                                            'źródła',
                                            'źródeł',
                                        )}{' '}
                                        bez roku lub miejsca
                                    </p>
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={clearFilters}
                                >
                                    Wyczyść filtry
                                </Button>
                            </div>
                        )}
                    </>
                )}

                {selectedSource !== undefined && (
                    <div className="space-y-2 border-t pt-3">
                        <p className="text-sm font-medium">
                            Wybrane: {selectedSource.display_name}
                        </p>
                        <div className="flex items-end gap-3">
                            {selectedSource.kind !== 'gravestone' && (
                                <div className="grid flex-1 gap-1">
                                    <Label htmlFor={entryNumberId}>
                                        Numer wpisu
                                    </Label>
                                    <Input
                                        id={entryNumberId}
                                        value={entryNumber}
                                        onChange={(event) =>
                                            setEntryNumber(event.target.value)
                                        }
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                handleLink();
                                            }
                                        }}
                                        autoComplete="off"
                                        autoFocus
                                    />
                                </div>
                            )}
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={handleLink}
                                autoFocus={selectedSource.kind === 'gravestone'}
                            >
                                Powiąż
                            </Button>
                        </div>
                        {selectedSource.kind !== 'gravestone' && (
                            <InputError message={entryNumberError} />
                        )}
                    </div>
                )}

                <InputError message={error} />

                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Zamknij
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
