import { Link, router } from '@inertiajs/react';
import { useId } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { useGuardedClick } from '@/components/persons/section-editor';
import { EntryNumberDialog } from '@/components/sources/entry-number-dialog';
import { SourcePickerDialog } from '@/components/sources/source-picker-dialog';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { UnlinkSourceDialog } from '@/components/sources/unlink-source-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { SourcedPersonEvent, Source } from '@/types';

export type SourceGroupProps = {
    label: string;
    event: SourcedPersonEvent;
    availableSources: Source[] | undefined;
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

export function SourceGroup({
    label,
    event,
    availableSources,
}: SourceGroupProps) {
    const summary = [event.date, event.place].filter(Boolean).join(', ');
    const hasSources = event.sources.length > 0;
    const guardClick = useGuardedClick();
    const headingId = useId();

    return (
        <section
            aria-labelledby={headingId}
            className="bg-card space-y-3 rounded-lg border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <h3 id={headingId} className="font-medium">
                        {label}
                    </h3>
                    {summary && (
                        <p className="text-muted-foreground text-sm">
                            {summary}
                        </p>
                    )}
                </div>
                <EventSourceStatus kinds={event.confirmed_kinds} />
            </div>

            {hasSources ? (
                <ul className="space-y-2">
                    {event.sources.map((source) => {
                        const uploadedAt = formatUploadedAt(source.uploaded_at);

                        return (
                            <li
                                key={source.id}
                                className="flex flex-wrap items-center gap-x-3 gap-y-2"
                            >
                                <SourceThumbnail
                                    source={source}
                                    className="size-14 shrink-0 rounded-md"
                                />
                                <div className="min-w-40 flex-1">
                                    <p
                                        className="truncate text-sm font-medium"
                                        title={source.display_name}
                                    >
                                        {source.display_name}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <Badge
                                            variant="secondary"
                                            className="shrink-0"
                                        >
                                            {source.kind_short_label}
                                        </Badge>
                                        {source.entry_number && (
                                            <p className="text-muted-foreground max-w-full truncate text-xs">
                                                nr {source.entry_number}
                                            </p>
                                        )}
                                        {uploadedAt && (
                                            <p className="text-muted-foreground text-xs">
                                                dodano {uploadedAt}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={SourceController.index({
                                                query: { source: source.id },
                                            })}
                                            onClick={(event) =>
                                                guardClick(event, () =>
                                                    router.visit(
                                                        SourceController.index({
                                                            query: {
                                                                source: source.id,
                                                            },
                                                        }),
                                                    ),
                                                )
                                            }
                                            aria-label={`Otwórz źródło ${source.display_name}`}
                                        >
                                            Otwórz
                                        </Link>
                                    </Button>
                                    <EntryNumberDialog
                                        eventId={event.id}
                                        source={source}
                                        trigger={
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-muted-foreground"
                                                aria-label={`Numer wpisu źródła ${source.display_name}`}
                                            >
                                                Numer wpisu
                                            </Button>
                                        }
                                    />
                                    <UnlinkSourceDialog
                                        eventId={event.id}
                                        source={source}
                                        isLastSource={
                                            event.sources.length === 1
                                        }
                                        eventLabel={label}
                                        trigger={
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-muted-foreground hover:text-destructive"
                                                aria-label={`Odepnij źródło ${source.display_name}`}
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
                    Brak źródła. Powiąż źródło, żeby potwierdzić zdarzenie.
                </p>
            )}

            <SourcePickerDialog
                eventId={event.id}
                eventType={event.type}
                eventYear={event.date_parts.year}
                eventPlace={event.place}
                linkedSourceIds={event.sources.map((source) => source.id)}
                availableSources={availableSources}
                trigger={
                    <Button type="button" variant="outline" size="sm">
                        {hasSources ? 'Powiąż kolejne źródło' : 'Powiąż źródło'}
                    </Button>
                }
            />
        </section>
    );
}
