import { formatAge } from '@/lib/age';
import type { TimelineEntry } from '@/types';

export type PersonTimelineMarker = 'own' | 'relative' | 'unknown';

export type PersonTimelineRow = {
    entry: TimelineEntry;
    marker: PersonTimelineMarker;
    /** Year column: "?" for an entry without a year, null for a burial. */
    year: string | null;
    approximate: boolean;
    age: string | null;
    /** Full date, only when it has a month (the year already sits in the column). */
    date: string | null;
    segmentBelow: 'solid' | 'dashed' | null;
};

function markerFor(entry: TimelineEntry): PersonTimelineMarker {
    if (entry.type === 'burial' || entry.year === null) {
        return 'unknown';
    }

    return entry.relative !== null && entry.type !== 'marriage'
        ? 'relative'
        : 'own';
}

function yearFor(entry: TimelineEntry): string | null {
    if (entry.type === 'burial') {
        return null;
    }

    return entry.year === null ? '?' : String(entry.year);
}

/** Turns the server-ordered timeline into the rows the Timeline component draws. */
export function personTimelineRows(
    entries: TimelineEntry[],
): PersonTimelineRow[] {
    const markers = entries.map(markerFor);

    return entries.map((entry, index) => {
        const isLast = index === entries.length - 1;
        const dashed =
            markers[index] === 'unknown' || markers[index + 1] === 'unknown';

        return {
            entry,
            marker: markers[index],
            year: yearFor(entry),
            approximate: entry.is_approximate,
            age: entry.age === null ? null : formatAge(entry.age),
            date: entry.date_has_month ? entry.date : null,
            segmentBelow: isLast ? null : dashed ? 'dashed' : 'solid',
        };
    });
}
