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
    place: string;
    year_from: number | null;
    year_to: number | null;
    archive: string;
};

export type SourcesPlaceOptions = {
    localities: { id: number; name: string }[];
    parishes: { id: number; label: string }[];
};

export type SourcesCounts = Record<SourcesKindFilter, number>;

export type SourcesMissingCriterion = 'year' | 'place' | 'archive';

export type SourcesResult = {
    visible: IndexSource[];
    counts: SourcesCounts;
    hidden: { count: number; missing: SourcesMissingCriterion[] };
};

const BASE_URL = 'http://localhost';

const DEFAULT_SORT: SourcesSort = '-dodano';

const PLACE_PATTERN = /^(miejscowosc|parafia)-(\d+)$/;

const CRITERIA_ORDER: SourcesMissingCriterion[] = ['year', 'place', 'archive'];

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

    const place = filters.place?.trim() ?? '';

    if (place !== '') {
        query.place = place;
    }

    if (typeof filters.year_from === 'number') {
        query.year_from = String(filters.year_from);
    }

    if (typeof filters.year_to === 'number') {
        query.year_to = String(filters.year_to);
    }

    const archive = filters.archive?.trim() ?? '';

    if (archive !== '') {
        query.archive = archive;
    }

    return query;
}

/** The URL with only the filter keys replaced (defaults removed); panel keys, other parameters and hash stay. */
export function withSourcesQuery(
    url: string,
    filters: Partial<SourcesFilters>,
): string {
    const parsed = new URL(url, BASE_URL);
    const query = buildSourcesQuery(filters);

    for (const key of [
        'q',
        'kind',
        'view',
        'sort',
        'place',
        'year_from',
        'year_to',
        'archive',
    ]) {
        const value = query[key];

        if (value === undefined) {
            parsed.searchParams.delete(key);
        } else {
            parsed.searchParams.set(key, value);
        }
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}

/** The filters the page can actually show: a place or archive not among the options is blank. */
export function resolveFilters(
    filters: SourcesFilters,
    placeOptions: SourcesPlaceOptions,
    archiveOptions: string[],
): SourcesFilters {
    const places = new Set([
        ...placeOptions.localities.map(
            (locality) => `miejscowosc-${locality.id}`,
        ),
        ...placeOptions.parishes.map((parish) => `parafia-${parish.id}`),
    ]);

    return {
        ...filters,
        place: places.has(filters.place) ? filters.place : '',
        archive: archiveOptions.includes(filters.archive)
            ? filters.archive
            : '',
    };
}

/** A year typed into a field: a whole number from 1 to 9999, otherwise null (as the server normalizes it). */
export function parseYearInput(value: string): number | null {
    const trimmed = value.trim();

    if (!/^\d+$/.test(trimmed)) {
        return null;
    }

    const year = Number(trimmed);

    return year >= 1 && year <= 9999 ? year : null;
}

export type YearFieldsError = { from: boolean; to: boolean; message: string };

/**
 * Which year field is wrong and why, by the same rules as `parseYearInput`. A blank field is not an error;
 * a field that does not parse wins over a reversed range.
 */
export function yearFieldsError(
    fromText: string,
    toText: string,
): YearFieldsError | null {
    const fromBlank = fromText.trim() === '';
    const toBlank = toText.trim() === '';
    const fromYear = parseYearInput(fromText);
    const toYear = parseYearInput(toText);
    const fromInvalid = !fromBlank && fromYear === null;
    const toInvalid = !toBlank && toYear === null;

    if (fromInvalid || toInvalid) {
        return {
            from: fromInvalid,
            to: toInvalid,
            message: 'Rok musi być liczbą od 1 do 9999.',
        };
    }

    if (fromYear !== null && toYear !== null && fromYear > toYear) {
        return {
            from: true,
            to: true,
            message: '„Rok od” nie może być późniejszy niż „Rok do”.',
        };
    }

    return null;
}

/** Whether anything beyond the view and the sort differs from the defaults. */
export function isFiltering(filters: SourcesFilters): boolean {
    return (
        filters.q.trim() !== '' ||
        filters.kind !== 'wszystkie' ||
        filters.place !== '' ||
        filters.year_from !== null ||
        filters.year_to !== null ||
        filters.archive !== ''
    );
}

/** How many of the fields hidden below `sm` (place, years, archive) are set: the count on „Filtry (N)”. */
export function hiddenFilterCount(filters: SourcesFilters): number {
    return [
        filters.place !== '',
        filters.year_from !== null,
        filters.year_to !== null,
        filters.archive !== '',
    ].filter(Boolean).length;
}

function matchesSearch(source: IndexSource, words: string[]): boolean {
    const name = source.display_name.toLocaleLowerCase('pl');

    return words.every((word) => name.includes(word));
}

function parsePlace(
    place: string,
): { prefix: 'miejscowosc' | 'parafia'; id: number } | null {
    const match = PLACE_PATTERN.exec(place);

    if (match === null) {
        return null;
    }

    return {
        prefix: match[1] === 'parafia' ? 'parafia' : 'miejscowosc',
        id: Number(match[2]),
    };
}

function matchesPlace(
    source: IndexSource,
    place: ReturnType<typeof parsePlace>,
): boolean {
    if (place === null) {
        return false;
    }

    if (place.prefix === 'parafia') {
        return source.parish_id === place.id;
    }

    return (
        (source.kind === 'gravestone' &&
            source.cemetery_locality_id === place.id) ||
        (source.kind === 'metrical_record' &&
            source.parish_locality_id === place.id)
    );
}

function matchesYears(source: IndexSource, filters: SourcesFilters): boolean {
    if (filters.year_from === null && filters.year_to === null) {
        return true;
    }

    if (source.year === null) {
        return false;
    }

    if (filters.year_from !== null && source.year < filters.year_from) {
        return false;
    }

    return filters.year_to === null || source.year <= filters.year_to;
}

/** The active criteria the source fails, in the fixed order year, place, archive. */
function failedCriteria(
    source: IndexSource,
    filters: SourcesFilters,
    place: ReturnType<typeof parsePlace>,
): SourcesMissingCriterion[] {
    const failed: SourcesMissingCriterion[] = [];

    if (!matchesYears(source, filters)) {
        failed.push('year');
    }

    if (filters.place !== '' && !matchesPlace(source, place)) {
        failed.push('place');
    }

    if (filters.archive !== '' && source.archive !== filters.archive) {
        failed.push('archive');
    }

    return failed;
}

/** Whether the source has no data that the criterion needs. */
function lacksData(
    source: IndexSource,
    criterion: SourcesMissingCriterion,
): boolean {
    switch (criterion) {
        case 'year':
            return source.year === null;
        case 'place':
            return (
                source.parish_id === null &&
                source.cemetery_locality_id === null
            );
        case 'archive':
            return (source.archive ?? '').trim() === '';
    }
}

/**
 * The sources shown for the filters, the counters of the kind switcher and the sources hidden
 * only because they lack data an active filter needs. Filters must already be resolved.
 * Counters follow the search and all filters except the kind.
 */
export function filterSources(
    sources: IndexSource[],
    filters: SourcesFilters,
): SourcesResult {
    const words = filters.q
        .toLocaleLowerCase('pl')
        .split(/\s+/)
        .filter((word) => word !== '');
    const place = parsePlace(filters.place);
    const counts: SourcesCounts = {
        wszystkie: 0,
        akty: 0,
        nagrobki: 0,
        inne: 0,
    };
    const visible: IndexSource[] = [];
    const hiddenMissing = new Set<SourcesMissingCriterion>();
    let hiddenCount = 0;

    for (const source of sources) {
        if (!matchesSearch(source, words)) {
            continue;
        }

        const sourceKind = kindFilterOf[source.kind];
        const failed = failedCriteria(source, filters, place);
        const matchesKind =
            filters.kind === 'wszystkie' || filters.kind === sourceKind;

        if (failed.length === 0) {
            counts.wszystkie++;
            counts[sourceKind]++;

            if (matchesKind) {
                visible.push(source);
            }

            continue;
        }

        if (
            matchesKind &&
            failed.every((criterion) => lacksData(source, criterion))
        ) {
            hiddenCount++;
            failed.forEach((criterion) => hiddenMissing.add(criterion));
        }
    }

    return {
        visible,
        counts,
        hidden: {
            count: hiddenCount,
            missing: CRITERIA_ORDER.filter((criterion) =>
                hiddenMissing.has(criterion),
            ),
        },
    };
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
