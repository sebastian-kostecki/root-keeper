import { filterLocalities } from '@/lib/localities-index';
import { filterParishes } from '@/lib/parishes-index';
import type { Locality, Parish } from '@/types';

export type PlacesKind = 'miejscowosci' | 'parafie';

export type PlacesCounts = Record<PlacesKind, number>;

const BASE_URL = 'http://localhost';

/** Hits per list for `q`; a blank `q` gives the size of each list. */
export function placesCounts(
    localities: Locality[],
    parishes: Parish[],
    q: string,
): PlacesCounts {
    return {
        miejscowosci: filterLocalities(localities, q).length,
        parafie: filterParishes(parishes, q).length,
    };
}

/**
 * The other list, when the search has no hits here but some there; `null` otherwise, including a blank search.
 */
export function otherKindHint(
    kind: PlacesKind,
    counts: PlacesCounts,
    q: string,
): { kind: PlacesKind; count: number } | null {
    if (q.trim() === '') {
        return null;
    }

    const other: PlacesKind =
        kind === 'miejscowosci' ? 'parafie' : 'miejscowosci';

    if (counts[kind] !== 0 || counts[other] < 1) {
        return null;
    }

    return { kind: other, count: counts[other] };
}

/**
 * The URL with `q` and/or `kind` replaced: a blank search and the default kind (miejscowosci) are removed.
 * Only the given keys change; panel keys, other parameters and the hash stay.
 */
export function withPlacesQuery(
    url: string,
    filters: { q?: string; kind?: PlacesKind },
): string {
    const parsed = new URL(url, BASE_URL);

    if (filters.q !== undefined) {
        const q = filters.q.trim();

        if (q === '') {
            parsed.searchParams.delete('q');
        } else {
            parsed.searchParams.set('q', q);
        }
    }

    if (filters.kind !== undefined) {
        if (filters.kind === 'miejscowosci') {
            parsed.searchParams.delete('kind');
        } else {
            parsed.searchParams.set('kind', filters.kind);
        }
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}
