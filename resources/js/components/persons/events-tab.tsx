import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { Timeline, TimelineItem } from '@/components/timeline';
import { cn } from '@/lib/utils';
import { focusRing } from '@/lib/focus-ring';
import {
    personTimelineRows,
    type PersonTimelineMarker,
    type PersonTimelineRow,
} from '@/lib/person-timeline';
import type { PersonPreview } from '@/types';

type Props = {
    person: PersonPreview;
};

const MARKERS = {
    own: 'primary',
    relative: 'muted',
    unknown: 'hollow',
} as const satisfies Record<PersonTimelineMarker, string>;

function YearColumn({ row }: { row: PersonTimelineRow }) {
    return (
        <>
            {row.approximate && (
                <span className="text-muted-foreground block text-xs leading-none">
                    ok.
                </span>
            )}
            {row.year !== null && (
                <span className="block text-sm leading-5 font-medium">
                    {row.year === '?' ? (
                        <>
                            <span aria-hidden>?</span>
                            <span className="sr-only">data nieznana</span>
                        </>
                    ) : (
                        row.year
                    )}
                </span>
            )}
            {row.age !== null && (
                <span className="text-muted-foreground block text-xs">
                    {row.age}
                </span>
            )}
        </>
    );
}

function EventRow({ row }: { row: PersonTimelineRow }) {
    const { entry } = row;
    const details = [row.date, entry.place].filter(
        (part): part is string => part !== null,
    );

    return (
        <TimelineItem
            marker={MARKERS[row.marker]}
            aside={<YearColumn row={row} />}
            segmentBelow={row.segmentBelow}
        >
            <p className="text-sm font-medium">
                {entry.label}
                {entry.relative !== null && (
                    <>
                        :{' '}
                        <PersonPreviewLink
                            personId={entry.relative.id}
                            className={cn(
                                'text-primary rounded-sm underline-offset-4 hover:underline',
                                focusRing,
                            )}
                        >
                            {entry.relative.display_name}
                        </PersonPreviewLink>
                    </>
                )}
            </p>
            {details.length > 0 && (
                <p className="text-muted-foreground text-sm">
                    {details.join(' · ')}
                </p>
            )}
        </TimelineItem>
    );
}

/** Tab of the person panel listing the timeline as the server orders it. */
export function EventsTab({ person }: Props) {
    if (person.timeline.length === 0) {
        return <p className="text-muted-foreground text-sm">Brak wydarzeń.</p>;
    }

    return (
        <Timeline>
            {personTimelineRows(person.timeline).map((row) => (
                <EventRow key={`${row.entry.type}-${row.entry.id}`} row={row} />
            ))}
        </Timeline>
    );
}
