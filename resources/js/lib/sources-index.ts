import { pluralPhrase } from '@/lib/plural';
import type { SortKey } from '@/lib/table-sort';
import type { IndexSource, SourceKind } from '@/types';

export type SourcesKindFilter = 'wszystkie' | 'akty' | 'nagrobki' | 'inne';

export type SourcesView = 'galeria' | 'tabela';

export type SourcesSortColumn =
    | 'nazwa'
    | 'rodzaj'
    | 'rok'
    | 'potwierdza'
    | 'dodano';

export type SourcesSort = SortKey<SourcesSortColumn>;

export type SourcesFilters = {
    q: string;
    kind: SourcesKindFilter;
    view: SourcesView;
    sort: SourcesSort;
};

export type SourcesCounts = Record<SourcesKindFilter, number>;

const DEFAULT_SORT: SourcesSort = '-dodano';

const collator = new Intl.Collator('pl', { numeric: true });

const kindFilterOf: Record<
    SourceKind,
    Exclude<SourcesKindFilter, 'wszystkie'>
> = {
    metrical_record: 'akty',
    gravestone: 'nagrobki',
    other: 'inne',
};

const kindRank: Record<SourceKind, number> = {
    metrical_record: 0,
    gravestone: 1,
    other: 2,
};

/** The query of a `/sources` visit: defaults and blanks are omitted, `sort` only applies to the table. */
export function buildSourcesQuery(
    filters: Partial<SourcesFilters>,
): Record<string, string> {
    const query: Record<string, string> = {};
    const q = filters.q?.trim() ?? '';

    if (q !== '') {
        query.q = q;
    }

    if (filters.kind && filters.kind !== 'wszystkie') {
        query.kind = filters.kind;
    }

    if (filters.view === 'tabela') {
        query.view = 'tabela';

        if (filters.sort && filters.sort !== DEFAULT_SORT) {
            query.sort = filters.sort;
        }
    }

    return query;
}

function matchesSearch(source: IndexSource, words: string[]): boolean {
    const name = source.display_name.toLocaleLowerCase('pl');

    return words.every((word) => name.includes(word));
}

/**
 * The sources shown for a search and a kind, and the counters of the kind switcher.
 * Counters follow the search and ignore the chosen kind.
 */
export function filterSources(
    sources: IndexSource[],
    q: string,
    kind: SourcesKindFilter,
): { visible: IndexSource[]; counts: SourcesCounts } {
    const words = q
        .toLocaleLowerCase('pl')
        .split(/\s+/)
        .filter((word) => word !== '');
    const counts: SourcesCounts = {
        wszystkie: 0,
        akty: 0,
        nagrobki: 0,
        inne: 0,
    };
    const visible: IndexSource[] = [];

    for (const source of sources) {
        if (!matchesSearch(source, words)) {
            continue;
        }

        const sourceKind = kindFilterOf[source.kind];

        counts.wszystkie++;
        counts[sourceKind]++;

        if (kind === 'wszystkie' || kind === sourceKind) {
            visible.push(source);
        }
    }

    return { visible, counts };
}

function compareTail(a: IndexSource, b: IndexSource): number {
    return collator.compare(a.display_name, b.display_name) || a.id - b.id;
}

function timestampOf(source: IndexSource): number {
    const time = Date.parse(source.uploaded_at);

    return Number.isNaN(time) ? 0 : time;
}

/** Kind in the filter's order; among akts the record type label, an akt without a type last. */
function compareKind(a: IndexSource, b: IndexSource): number {
    const byKind = kindRank[a.kind] - kindRank[b.kind];

    if (byKind !== 0 || a.kind !== 'metrical_record') {
        return byKind;
    }

    const aLabel = a.record_type_label ?? '';
    const bLabel = b.record_type_label ?? '';

    if ((aLabel === '') !== (bLabel === '')) {
        return aLabel === '' ? 1 : -1;
    }

    return collator.compare(aLabel, bLabel);
}

/** Sort a copy of the sources for the table; the `-` variants reverse only the primary key. */
export function sortSources(
    sources: IndexSource[],
    sort: SourcesSort,
): IndexSource[] {
    const descending = sort.startsWith('-');
    const direction = descending ? -1 : 1;
    const column = (descending ? sort.slice(1) : sort) as SourcesSortColumn;

    return [...sources].sort((a, b) => {
        switch (column) {
            case 'nazwa':
                return (
                    direction *
                        collator.compare(a.display_name, b.display_name) ||
                    a.id - b.id
                );
            case 'rodzaj':
                return direction * compareKind(a, b) || compareTail(a, b);
            case 'rok': {
                if ((a.year === null) !== (b.year === null)) {
                    return a.year === null ? 1 : -1;
                }

                const primary =
                    a.year !== null && b.year !== null ? a.year - b.year : 0;

                return direction * primary || compareTail(a, b);
            }
            case 'potwierdza':
                return (
                    direction * (a.events_count - b.events_count) ||
                    compareTail(a, b)
                );
            case 'dodano':
                return (
                    direction * (timestampOf(a) - timestampOf(b) || a.id - b.id)
                );
        }
    });
}

/** „1 wydarzenie”, „2 wydarzenia”, „5 wydarzeń”; null for a source that confirms nothing. */
export function eventsLabel(count: number): string | null {
    if (count === 0) {
        return null;
    }

    return `${count} ${pluralPhrase(count, 'wydarzenie', 'wydarzenia', 'wydarzeń')}`;
}
