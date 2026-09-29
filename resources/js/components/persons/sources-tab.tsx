import { Link } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { ScanPickerDialog } from '@/components/scans/scan-picker-dialog';
import { ScanThumbnail } from '@/components/scans/scan-thumbnail';
import { UnlinkScanDialog } from '@/components/scans/unlink-scan-dialog';
import { Button } from '@/components/ui/button';
import type { PersonPreview, Scan, SourcedPersonEvent } from '@/types';

type Props = {
    person: PersonPreview;
    availableScans?: Scan[];
};

type GroupProps = {
    label: string;
    /** Genitive form used in the empty-state hint, e.g. "urodzenia". */
    eventGenitive: string;
    event: SourcedPersonEvent;
    availableScans: Scan[] | undefined;
};

function formatUploadedAt(uploadedAt: string | null): string | null {
    if (uploadedAt === null) {
        return null;
    }

    const date = new Date(uploadedAt);

    return Number.isNaN(date.getTime())
        ? null
        : date.toLocaleDateString('pl-PL', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          });
}

function SourceGroup({
    label,
    eventGenitive,
    event,
    availableScans,
}: GroupProps) {
    const summary = [event.date, event.place].filter(Boolean).join(', ');
    const hasScans = event.scans.length > 0;

    return (
        <section className="bg-card space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <h3 className="font-medium">{label}</h3>
                    {summary && (
                        <p className="text-muted-foreground text-sm">
                            {summary}
                        </p>
                    )}
                </div>
                <EventSourceStatus isConfirmed={event.is_confirmed} />
            </div>

            {hasScans ? (
                <ul className="space-y-2">
                    {event.scans.map((scan) => {
                        const uploadedAt = formatUploadedAt(scan.uploaded_at);

                        return (
                            <li
                                key={scan.id}
                                className="flex items-center gap-3"
                            >
                                <ScanThumbnail
                                    scan={scan}
                                    className="size-14 shrink-0 rounded-md"
                                />
                                <div className="min-w-0 flex-1">
                                    <p
                                        className="truncate text-sm font-medium"
                                        title={scan.original_name}
                                    >
                                        {scan.original_name}
                                    </p>
                                    {uploadedAt && (
                                        <p className="text-muted-foreground text-xs">
                                            dodano {uploadedAt}
                                        </p>
                                    )}
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={ScanController.show(scan.id)}
                                            aria-label={`Otwórz skan ${scan.original_name}`}
                                        >
                                            Otwórz
                                        </Link>
                                    </Button>
                                    <UnlinkScanDialog
                                        eventId={event.id}
                                        scan={scan}
                                        isLastScan={event.scans.length === 1}
                                        eventLabel={label}
                                        trigger={
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-muted-foreground hover:text-destructive"
                                                aria-label={`Odepnij skan ${scan.original_name}`}
                                            >
                                                Odepnij
                                            </Button>
                                        }
                                    />
                                </div>
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <p className="text-muted-foreground text-sm">
                    Brak skanu. Powiąż akt {eventGenitive}, żeby potwierdzić
                    zdarzenie.
                </p>
            )}

            <ScanPickerDialog
                eventId={event.id}
                linkedScanIds={event.scans.map((scan) => scan.id)}
                availableScans={availableScans}
                trigger={
                    <Button type="button" variant="outline" size="sm">
                        {hasScans ? 'Powiąż kolejny skan' : 'Powiąż skan'}
                    </Button>
                }
            />
        </section>
    );
}

export function SourcesTab({ person, availableScans }: Props) {
    return (
        <div className="space-y-4">
            {person.birth && (
                <SourceGroup
                    label="Urodzenie"
                    eventGenitive="urodzenia"
                    event={person.birth}
                    availableScans={availableScans}
                />
            )}

            {person.marriages.map((marriage) => (
                <SourceGroup
                    key={marriage.id}
                    label={`Małżeństwo z ${marriage.spouse?.display_name ?? 'nieznanym małżonkiem'}`}
                    eventGenitive="małżeństwa"
                    event={marriage}
                    availableScans={availableScans}
                />
            ))}

            {person.death && (
                <SourceGroup
                    label="Zgon"
                    eventGenitive="zgonu"
                    event={person.death}
                    availableScans={availableScans}
                />
            )}
        </div>
    );
}
