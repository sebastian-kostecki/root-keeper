import type { Source } from '@/types';

type FilterFields = Pick<
    Source,
    'kind' | 'year' | 'parish_label' | 'locality_name'
> &
    Partial<Pick<Source, 'cemetery_label'>>;

/**
 * Narrows a source list by place (parish label, locality name or cemetery label
 * contains the phrase, ignoring case) and exact year. A gravestone has no year, so it
 * passes the year criterion and is found by the place of its cemetery. Blank or non-integer input leaves that
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
        const cemeteryLabel = source.cemetery_label ?? null;
        const hasPlace =
            source.parish_label !== null ||
            source.locality_name !== null ||
            cemeteryLabel !== null;
        const placeMatches =
            needle === '' ||
            (source.parish_label?.toLocaleLowerCase('pl').includes(needle) ??
                false) ||
            (source.locality_name?.toLocaleLowerCase('pl').includes(needle) ??
                false) ||
            (cemeteryLabel?.toLocaleLowerCase('pl').includes(needle) ?? false);
        const yearMatches =
            year === null ||
            source.kind === 'gravestone' ||
            source.year === year;

        if (placeMatches && yearMatches) {
            visible.push(source);
        } else if (
            (year !== null &&
                source.kind !== 'gravestone' &&
                source.year === null) ||
            (needle !== '' && !hasPlace)
        ) {
            hiddenUndescribedCount += 1;
        }
    }

    return { visible, hiddenUndescribedCount };
}
