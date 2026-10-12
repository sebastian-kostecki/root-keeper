export type PlacesKind = 'miejscowosci' | 'parafie';

const BASE_URL = 'http://localhost';

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
