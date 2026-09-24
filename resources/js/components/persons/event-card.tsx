import { Link } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { ScanPickerDialog } from '@/components/scans/scan-picker-dialog';
import { ScanThumbnail } from '@/components/scans/scan-thumbnail';
import { UnlinkScanDialog } from '@/components/scans/unlink-scan-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Scan, SourcedPersonEvent } from '@/types';

type Props = {
    title: string;
    event: SourcedPersonEvent;
    availableScans: Scan[] | undefined;
};

export function EventCard({ title, event, availableScans }: Props) {
    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                <CardTitle>{title}</CardTitle>
                <div className="flex items-center gap-2">
                    <EventSourceStatus isConfirmed={event.is_confirmed} />
                    <ScanPickerDialog
                        eventId={event.id}
                        linkedScanIds={event.scans.map((scan) => scan.id)}
                        availableScans={availableScans}
                        trigger={
                            <Button type="button" variant="outline" size="sm">
                                Powiąż skan
                            </Button>
                        }
                    />
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p className="text-muted-foreground text-sm">Data</p>
                        <p>{event.date ?? 'brak danych'}</p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-sm">Miejsce</p>
                        <p>{event.place ?? 'brak danych'}</p>
                    </div>
                </div>

                <div>
                    <p className="text-muted-foreground mb-2 text-sm">Źródła</p>
                    {event.scans.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Brak powiązanych skanów.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-3">
                            {event.scans.map((scan) => (
                                <div key={scan.id} className="w-20 space-y-1">
                                    <Link
                                        href={ScanController.show(scan.id)}
                                        aria-label={`Otwórz skan ${scan.original_name}`}
                                    >
                                        <ScanThumbnail
                                            scan={scan}
                                            className="rounded-md"
                                        />
                                    </Link>
                                    <UnlinkScanDialog
                                        eventId={event.id}
                                        scan={scan}
                                        isLastScan={event.scans.length === 1}
                                        eventLabel={title}
                                        trigger={
                                            <button
                                                type="button"
                                                aria-label={`Odepnij skan ${scan.original_name}`}
                                                className="text-muted-foreground hover:text-destructive w-full text-center text-xs underline-offset-4 hover:underline"
                                            >
                                                Odepnij
                                            </button>
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
