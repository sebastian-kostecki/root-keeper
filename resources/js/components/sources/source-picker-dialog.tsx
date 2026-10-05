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
import { Skeleton } from '@/components/ui/skeleton';
import { formatSourceMeta } from '@/lib/source-description';
import { splitSourcesForEvent } from '@/lib/source-matching';
import type { PersonEvent, Source } from '@/types';

const groupLabelClass =
    'text-muted-foreground text-xs font-semibold tracking-wide uppercase';

type Props = {
    eventId: number;
    eventType: PersonEvent['type'];
    linkedSourceIds: number[];
    availableSources: Source[] | undefined;
    trigger: React.ReactNode;
};

export function SourcePickerDialog({
    eventId,
    eventType,
    linkedSourceIds,
    availableSources,
    trigger,
}: Props) {
    const matchingHeadingId = useId();
    const restHeadingId = useId();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [processingSourceId, setProcessingSourceId] = useState<number | null>(
        null,
    );
    const [error, setError] = useState<string | undefined>(undefined);

    // The optional prop is dropped by any non-partial visit (e.g. the redirect
    // back after a validation error), so keep the last list that was loaded.
    const [sources, setSources] = useState(availableSources);

    if (availableSources !== undefined && availableSources !== sources) {
        setSources(availableSources);
    }

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setError(undefined);
            setLoaded(false);
            setLoading(true);
            router.reload({
                only: ['availableSources'],
                onSuccess: () => setLoaded(true),
                onFinish: () => setLoading(false),
            });
        }
    };

    const handleSelect = (sourceId: number) => {
        setProcessingSourceId(sourceId);
        setError(undefined);

        router.post(
            EventSourceController.store(eventId).url,
            { source_id: sourceId },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (errors) => setError(errors.source_id),
                onFinish: () => setProcessingSourceId(null),
            },
        );
    };

    const renderTile = (source: Source) => {
        const isLinked = linkedSourceIds.includes(source.id);
        const meta = formatSourceMeta(source);

        return (
            <button
                key={source.id}
                type="button"
                disabled={isLinked || processingSourceId !== null}
                onClick={() => handleSelect(source.id)}
                className="space-y-1 self-start text-left disabled:cursor-not-allowed disabled:opacity-50"
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
            <DialogContent>
                <DialogTitle>Powiąż źródło</DialogTitle>
                <DialogDescription>
                    Wybierz źródło, które potwierdza to wydarzenie.
                </DialogDescription>

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
                            const { matching, rest } = splitSourcesForEvent(
                                sources,
                                eventType,
                            );

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
