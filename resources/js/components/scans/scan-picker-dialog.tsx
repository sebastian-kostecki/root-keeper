import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import EventScanController from '@/actions/App/Http/Controllers/EventScanController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import InputError from '@/components/input-error';
import { ScanThumbnail } from '@/components/scans/scan-thumbnail';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import type { Scan } from '@/types';

type Props = {
    eventId: number;
    linkedScanIds: number[];
    availableScans: Scan[] | undefined;
    trigger: React.ReactNode;
};

export function ScanPickerDialog({
    eventId,
    linkedScanIds,
    availableScans,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [processingScanId, setProcessingScanId] = useState<number | null>(
        null,
    );
    const [error, setError] = useState<string | undefined>(undefined);

    // The optional prop is dropped by any non-partial visit (e.g. the redirect
    // back after a validation error), so keep the last list that was loaded.
    const [scans, setScans] = useState(availableScans);

    if (availableScans !== undefined && availableScans !== scans) {
        setScans(availableScans);
    }

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setError(undefined);
            setLoaded(false);
            setLoading(true);
            router.reload({
                only: ['availableScans'],
                onSuccess: () => setLoaded(true),
                onFinish: () => setLoading(false),
            });
        }
    };

    const handleSelect = (scanId: number) => {
        setProcessingScanId(scanId);
        setError(undefined);

        router.post(
            EventScanController.store(eventId).url,
            { scan_id: scanId },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (errors) => setError(errors.scan_id),
                onFinish: () => setProcessingScanId(null),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>Powiąż skan</DialogTitle>
                <DialogDescription>
                    Wybierz skan aktu, który potwierdza to wydarzenie.
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
                ) : !loaded || scans === undefined ? (
                    <p className="text-muted-foreground text-sm">
                        Nie udało się wczytać skanów. Zamknij okno i spróbuj
                        ponownie.
                    </p>
                ) : scans.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Nie masz jeszcze żadnych skanów.{' '}
                        <Link
                            href={ScanController.create()}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            Dodaj skan
                        </Link>
                        .
                    </p>
                ) : (
                    <div className="grid max-h-96 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                        {scans.map((scan) => {
                            const isLinked = linkedScanIds.includes(scan.id);

                            return (
                                <button
                                    key={scan.id}
                                    type="button"
                                    disabled={
                                        isLinked || processingScanId !== null
                                    }
                                    onClick={() => handleSelect(scan.id)}
                                    className="space-y-1 text-left disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <ScanThumbnail
                                        scan={scan}
                                        className="rounded-md"
                                    />
                                    <p
                                        className="truncate text-xs font-medium"
                                        title={scan.original_name}
                                    >
                                        {scan.original_name}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {isLinked
                                            ? 'Już powiązany'
                                            : new Date(
                                                  scan.uploaded_at,
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
