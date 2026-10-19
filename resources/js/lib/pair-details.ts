import { formatPartialDate } from '@/lib/partial-date';
import type { SheetPersonDetails, SheetPersonEventDetails } from '@/types';

/** "{label} {date}, {place}" with whichever parts are known, or null when neither is. */
function eventLine(
    label: string,
    event: SheetPersonEventDetails | null,
): string | null {
    if (event === null) {
        return null;
    }

    const text = [formatPartialDate(event.date_parts, 'long'), event.place]
        .filter((part): part is string => part !== null && part !== '')
        .join(', ');

    return text === '' ? null : `${label} ${text}`;
}

/**
 * Detail lines of the pair card: "ur. …", "zm. …" (only for a deceased
 * person) and "poch. …" (only with a burial place). Unknown parts are left out.
 */
export function pairDetailLines(
    details: SheetPersonDetails,
    isDeceased: boolean,
): string[] {
    const burial =
        details.burial_place === null || details.burial_place === ''
            ? null
            : `poch. ${details.burial_place}`;

    return [
        eventLine('ur.', details.birth),
        isDeceased ? eventLine('zm.', details.death) : null,
        burial,
    ].filter((line): line is string => line !== null);
}
