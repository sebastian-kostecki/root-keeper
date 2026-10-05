import type { Locality } from '@/types';

const collator = new Intl.Collator('pl');

const BASE_URL = 'http://localhost';

/** The query of a `/localities` visit: a blank search is omitted. */
export function buildLocalitiesQuery(filters: {
    q?: string;
}): Record<string, string> {
    const q = filters.q?.trim() ?? '';

    return q === '' ? {} : { q };
}

/** The URL with only `q` replaced by the search (removed when blank); panel keys and other parameters stay. */
export function withLocalitiesSearch(url: string, q: string): string {
    const parsed = new URL(url, BASE_URL);
    const search = buildLocalitiesQuery({ q }).q;

    if (search === undefined) {
        parsed.searchParams.delete('q');
    } else {
        parsed.searchParams.set('q', search);
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
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
