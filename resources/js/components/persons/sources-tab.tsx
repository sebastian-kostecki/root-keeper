import { Link, router } from '@inertiajs/react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { useGuardedClick } from '@/components/persons/section-editor';
import { SourcePickerDialog } from '@/components/sources/source-picker-dialog';
import { SourceThumbnail } from '@/components/sources/source-thumbnail';
import { UnlinkSourceDialog } from '@/components/sources/unlink-source-dialog';
import { Button } from '@/components/ui/button';
import type { PersonPreview, Source, SourcedPersonEvent } from '@/types';

type Props = {
    person: PersonPreview;
    availableSources?: Source[];
};

type GroupProps = {
    label: string;
    /** Genitive form used in the empty-state hint, e.g. "urodzenia". */
    eventGenitive: string;
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

function SourceGroup({
    label,
    eventGenitive,
    event,
    availableSources,
}: GroupProps) {
    const summary = [event.date, event.place].filter(Boolean).join(', ');
    const hasSources = event.sources.length > 0;
    const guardClick = useGuardedClick();

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

            {hasSources ? (
                <ul className="space-y-2">
                    {event.sources.map((source) => {
                        const uploadedAt = formatUploadedAt(source.uploaded_at);

                        return (
                            <li
                                key={source.id}
                                className="flex items-center gap-3"
                            >
                                <SourceThumbnail
                                    source={source}
                                    className="size-14 shrink-0 rounded-md"
                                />
                                <div className="min-w-0 flex-1">
                                    <p
                                        className="truncate text-sm font-medium"
                                        title={source.display_name}
                                    >
                                        {source.display_name}
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
                                            href={SourceController.show(
                                                source.id,
                                            )}
                                            onClick={(event) =>
                                                guardClick(event, () =>
                                                    router.visit(
                                                        SourceController.show(
                                                            source.id,
                                                        ),
                                                    ),
                                                )
                                            }
                                            aria-label={`Otwórz źródło ${source.display_name}`}
                                        >
                                            Otwórz
                                        </Link>
                                    </Button>
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
                    Brak aktu. Powiąż akt {eventGenitive}, żeby potwierdzić
                    zdarzenie.
                </p>
            )}

            <SourcePickerDialog
                eventId={event.id}
                linkedSourceIds={event.sources.map((source) => source.id)}
                availableSources={availableSources}
                trigger={
                    <Button type="button" variant="outline" size="sm">
                        {hasSources ? 'Powiąż kolejny akt' : 'Powiąż akt'}
                    </Button>
                }
            />
        </section>
    );
}

export function SourcesTab({ person, availableSources }: Props) {
    return (
        <div className="space-y-4">
            {person.birth && (
                <SourceGroup
                    label="Urodzenie"
                    eventGenitive="urodzenia"
                    event={person.birth}
                    availableSources={availableSources}
                />
            )}

            {person.marriages.map((marriage) => (
                <SourceGroup
                    key={marriage.id}
                    label={`Małżeństwo z ${marriage.spouse?.display_name ?? 'nieznanym małżonkiem'}`}
                    eventGenitive="małżeństwa"
                    event={marriage}
                    availableSources={availableSources}
                />
            ))}

            {person.death && (
                <SourceGroup
                    label="Zgon"
                    eventGenitive="zgonu"
                    event={person.death}
                    availableSources={availableSources}
                />
            )}
        </div>
    );
}
