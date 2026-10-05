import type { Parish } from '@/types';

const collator = new Intl.Collator('pl');

const BASE_URL = 'http://localhost';

/** The query of a `/parishes` visit: a blank search is omitted. */
export function buildParishesQuery(filters: {
    q?: string;
}): Record<string, string> {
    const q = filters.q?.trim() ?? '';

    return q === '' ? {} : { q };
}

/** The URL with only `q` replaced by the search (removed when blank); panel keys and other parameters stay. */
export function withParishesSearch(url: string, q: string): string {
    const parsed = new URL(url, BASE_URL);
    const search = buildParishesQuery({ q }).q;

    if (search === undefined) {
        parsed.searchParams.delete('q');
    } else {
        parsed.searchParams.set('q', search);
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}

/** Parishes whose name or seat contains the search, ignoring case; a blank search matches all. */
export function filterParishes(parishes: Parish[], q: string): Parish[] {
    const needle = q.trim().toLocaleLowerCase('pl');

    if (needle === '') {
        return parishes;
    }

    return parishes.filter(
        (parish) =>
            parish.name.toLocaleLowerCase('pl').includes(needle) ||
            (parish.locality_name?.toLocaleLowerCase('pl').includes(needle) ??
                false),
    );
}

/** Sort a copy by name in Polish order, ties by id. */
export function sortParishes(parishes: Parish[]): Parish[] {
    return [...parishes].sort(
        (a, b) => collator.compare(a.name, b.name) || a.id - b.id,
    );
}
