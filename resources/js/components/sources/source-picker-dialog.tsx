import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import EventSourceController from '@/actions/App/Http/Controllers/EventSourceController';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import InputError from '@/components/input-error';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import type { Source } from '@/types';

type Props = {
    eventId: number;
    linkedSourceIds: number[];
    availableSources: Source[] | undefined;
    trigger: React.ReactNode;
};

export function SourcePickerDialog({
    eventId,
    linkedSourceIds,
    availableSources,
    trigger,
}: Props) {
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

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Powiąż akt</DialogTitle>
                <DialogDescription>
                    Wybierz akt metrykalny, który potwierdza to wydarzenie.
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
                        Nie masz jeszcze żadnych aktów metrykalnych.{' '}
                        <Link
                            href={SourceController.create()}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            Dodaj źródło
                        </Link>
                        .
                    </p>
                ) : (
                    <div className="grid max-h-96 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                        {sources.map((source) => {
                            const isLinked = linkedSourceIds.includes(
                                source.id,
                            );

                            return (
                                <button
                                    key={source.id}
                                    type="button"
                                    disabled={
                                        isLinked || processingSourceId !== null
                                    }
                                    onClick={() => handleSelect(source.id)}
                                    className="space-y-1 text-left disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <SourceThumbnail
                                        source={source}
                                        className="rounded-md"
                                    />
                                    <p
                                        className="truncate text-xs font-medium"
                                        title={source.original_name}
                                    >
                                        {source.original_name}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {isLinked
                                            ? 'Już powiązany'
                                            : new Date(
                                                  source.uploaded_at,
                                              ).toLocaleDateString('pl-PL')}
                                    </p>
                                </button>
                            );
                        })}
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
