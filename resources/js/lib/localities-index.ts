import type { Locality } from '@/types';

const collator = new Intl.Collator('pl');

/** Localities whose name contains the search, ignoring case; a blank search matches all. */
export function filterLocalities(
    localities: Locality[],
    q: string,
): Locality[] {
    const needle = q.trim().toLocaleLowerCase('pl');

    if (needle === '') {
        return localities;
    }

    return localities.filter((locality) =>
        locality.name.toLocaleLowerCase('pl').includes(needle),
    );
}

/** Sort a copy by name in Polish order, ties by id. */
export function sortLocalities(localities: Locality[]): Locality[] {
    return [...localities].sort(
        (a, b) => collator.compare(a.name, b.name) || a.id - b.id,
    );
}

/** The note after a locality's name in a suggestion list; only a defunct one has it. */
export function localityHint(option: {
    is_defunct?: boolean;
}): string | undefined {
    return option.is_defunct === true ? 'nie istnieje' : undefined;
}

/** „50.0614, 19.9366”, or an empty string when either coordinate is missing. */
export function formatCoordinates(
    latitude: number | null,
    longitude: number | null,
): string {
    if (latitude === null || longitude === null) {
        return '';
    }

    return `${latitude}, ${longitude}`;
}

/** OpenStreetMap centred on the point, with a marker. */
export function openStreetMapUrl(latitude: number, longitude: number): string {
    return `https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=14/${latitude}/${longitude}`;
}
