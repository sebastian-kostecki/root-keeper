import type { Locality } from '@/types';

const collator = new Intl.Collator('pl');

/** The query of a `/localities` visit: a blank search is omitted. */
export function buildLocalitiesQuery(filters: {
    q?: string;
}): Record<string, string> {
    const q = filters.q?.trim() ?? '';

    return q === '' ? {} : { q };
}

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
