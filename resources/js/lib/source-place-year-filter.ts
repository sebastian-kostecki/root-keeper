import type { Source } from '@/types';

type FilterFields = Pick<Source, 'year' | 'parish_name' | 'locality_name'>;

/**
 * Narrows a source list by place (parish name or locality name contains the
 * phrase, ignoring case) and exact year. Blank or non-integer input leaves that
 * criterion off. `hiddenUndescribedCount` counts hidden sources that lack the
 * data an active criterion needs (no year / no place), so the UI can say so.
 */
export function filterSourcesByPlaceAndYear<T extends FilterFields>(
    sources: readonly T[],
    filter: { place: string; year: string },
): { visible: T[]; hiddenUndescribedCount: number } {
    const needle = filter.place.trim().toLocaleLowerCase('pl');
    const yearText = filter.year.trim();
    const year = /^\d+$/.test(yearText) ? Number(yearText) : null;

    const visible: T[] = [];
    let hiddenUndescribedCount = 0;

    for (const source of sources) {
        const hasPlace =
            source.parish_name !== null || source.locality_name !== null;
        const placeMatches =
            needle === '' ||
            (source.parish_name?.toLocaleLowerCase('pl').includes(needle) ??
                false) ||
            (source.locality_name?.toLocaleLowerCase('pl').includes(needle) ??
                false);
        const yearMatches = year === null || source.year === year;

        if (placeMatches && yearMatches) {
            visible.push(source);
        } else if (
            (year !== null && source.year === null) ||
            (needle !== '' && !hasPlace)
        ) {
            hiddenUndescribedCount += 1;
        }
    }

    return { visible, hiddenUndescribedCount };
}
