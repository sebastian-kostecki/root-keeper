import { formatAge } from '@/lib/age';
import type { TimelineEntry } from '@/types';

export type PersonTimelineMarker = 'own' | 'relative' | 'unknown';

export type PersonTimelineRow = {
    entry: TimelineEntry;
    marker: PersonTimelineMarker;
    /** Year column: "?" for an entry without a year, null for a burial, "1805–" for a range. */
    year: string | null;
    /** Second line of a range ("1811"), otherwise null. */
    yearEnd: string | null;
    /** Small word above the year; a range has none, its two lines say it. */
    qualifierLabel: 'ok.' | 'przed' | 'po' | null;
    /** The range read aloud ("między 1805 a 1811"), for screen readers. */
    spokenDate: string | null;
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

    if (entry.year === null) {
        return '?';
    }

    return isRange(entry) ? `${entry.year}\u2013` : String(entry.year);
}

function isRange(entry: TimelineEntry): boolean {
    return (
        entry.qualifier === 'range' &&
        entry.year !== null &&
        entry.until_year !== null
    );
}

function qualifierLabelFor(
    entry: TimelineEntry,
): PersonTimelineRow['qualifierLabel'] {
    switch (entry.qualifier) {
        case 'approximate':
            return 'ok.';
        case 'before':
            return 'przed';
        case 'after':
            return 'po';
        default:
            return null;
    }
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
            yearEnd: isRange(entry) ? String(entry.until_year) : null,
            qualifierLabel: qualifierLabelFor(entry),
            spokenDate: isRange(entry)
                ? `między ${entry.year} a ${entry.until_year}`
                : null,
            age: entry.age === null ? null : formatAge(entry.age),
            date: entry.date_has_month ? entry.date : null,
            segmentBelow: isLast ? null : dashed ? 'dashed' : 'solid',
        };
    });
}
