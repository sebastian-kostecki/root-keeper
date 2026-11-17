import { describe, expect, it } from 'vite-plus/test';
import type { IndexSource } from '@/types';
import {
    buildSourcesQuery,
    eventsLabel,
    filterOptionCounts,
    filterSources,
    formatYearsFilter,
    halfCenturyPresets,
    hiddenFilterCount,
    isFiltering,
    resolveFilters,
    resultCountText,
    sortSources,
    withSourcesQuery,
    yearFieldsError,
} from './sources-index';
import type { SourcesFilters } from './sources-index';

function source(id: number, overrides: Partial<IndexSource> = {}): IndexSource {
    const name = overrides.name === undefined ? `Źródło ${id}` : overrides.name;

    return {
        id,
        kind: 'other',
        kind_label: 'Inne',
        kind_short_label: 'inne',
        name,
        display_name: name ?? `plik-${id}.jpg`,
        record_type: null,
        record_type_label: null,
        year: null,
        archive: null,
        signature: null,
        parish_id: null,
        parish_label: null,
        parish_locality_id: null,
        locality_name: null,
        cemetery_id: null,
        cemetery_label: null,
        cemetery_locality_id: null,
        url: null,
        note: null,
        original_name: `plik-${id}.jpg`,
        mime_type: 'image/jpeg',
        size: 1,
        width: null,
        height: null,
        has_thumbnail: false,
        uploaded_at: '2026-09-24T12:00:00+00:00',
        events_count: 0,
        ...overrides,
    };
}

function filtersOf(overrides: Partial<SourcesFilters> = {}): SourcesFilters {
    return {
        q: '',
        kind: 'wszystkie',
        view: 'galeria',
        sort: '-dodano',
        place: '',
        year_from: null,
        year_to: null,
        archive: '',
        ...overrides,
    };
}

const ids = (sources: IndexSource[]) => sources.map((item) => item.id);

describe('buildSourcesQuery', () => {
    it('omits defaults and blanks', () => {
        expect(
            buildSourcesQuery({
                q: '  ',
                kind: 'wszystkie',
                view: 'galeria',
                sort: '-dodano',
            }),
        ).toEqual({});
    });

    it('keeps the search, kind, view and sort that differ from the defaults', () => {
        expect(
            buildSourcesQuery({
                q: ' lipnica ',
                kind: 'akty',
                view: 'tabela',
                sort: '-rok',
            }),
        ).toEqual({ q: 'lipnica', kind: 'akty', view: 'tabela', sort: '-rok' });
    });

    it('drops the default sort in the table', () => {
        expect(buildSourcesQuery({ view: 'tabela', sort: '-dodano' })).toEqual({
            view: 'tabela',
        });
    });

    it('drops sort on galeria', () => {
        expect(buildSourcesQuery({ view: 'galeria', sort: 'nazwa' })).toEqual(
            {},
        );
    });

    it('keeps place, years and archive, and omits blank or null ones', () => {
        expect(
            buildSourcesQuery({
                place: 'parafia-3',
                year_from: 1900,
                year_to: 1910,
                archive: ' AP Poznań ',
            }),
        ).toEqual({
            place: 'parafia-3',
            year_from: '1900',
            year_to: '1910',
            archive: 'AP Poznań',
        });
        expect(
            buildSourcesQuery({
                place: '',
                year_from: null,
                year_to: null,
                archive: '  ',
            }),
        ).toEqual({});
    });
});

describe('withSourcesQuery', () => {
    it('keeps the panel key, whether an id or new', () => {
        expect(withSourcesQuery('/sources?source=5', { q: 'lipnica' })).toBe(
            '/sources?source=5&q=lipnica',
        );
        expect(withSourcesQuery('/sources?source=new', { kind: 'akty' })).toBe(
            '/sources?source=new&kind=akty',
        );
    });

    it('removes sort when going back to the gallery', () => {
        expect(
            withSourcesQuery('/sources?view=tabela&sort=nazwa&source=5', {
                view: 'galeria',
                sort: 'nazwa',
            }),
        ).toBe('/sources?source=5');
    });

    it('removes a blank search', () => {
        expect(withSourcesQuery('/sources?q=abc&source=5', { q: '  ' })).toBe(
            '/sources?source=5',
        );
    });

    it('replaces and removes the place, year and archive keys', () => {
        expect(
            withSourcesQuery('/sources?place=parafia-1&year_to=1900&source=5', {
                place: '',
                year_to: 1900,
            }),
        ).toBe('/sources?year_to=1900&source=5');
        expect(
            withSourcesQuery('/sources?year_from=1800&archive=A&source=5', {
                year_from: null,
                archive: 'B',
            }),
        ).toBe('/sources?archive=B&source=5');
    });

    it('leaves other parameters and the hash untouched', () => {
        expect(
            withSourcesQuery('/sources?person=7&source=5#top', {
                q: 'x',
                kind: 'wszystkie',
            }),
        ).toBe('/sources?person=7&source=5&q=x#top');
    });
});

describe('resolveFilters', () => {
    const placeOptions = {
        localities: [{ id: 10, name: 'Lipnica' }],
        parishes: [{ id: 1, label: 'Lipnica (Lipnica)' }],
        cemeteries: [{ id: 100, label: 'cmentarz Lipnica' }],
    };

    it('keeps a place and an archive among the options', () => {
        const resolved = resolveFilters(
            filtersOf({ place: 'miejscowosc-10', archive: 'AP Poznań' }),
            placeOptions,
            ['AP Poznań'],
        );

        expect(resolved.place).toBe('miejscowosc-10');
        expect(resolved.archive).toBe('AP Poznań');
    });

    it('keeps a cemetery among the options', () => {
        expect(
            resolveFilters(
                filtersOf({ place: 'cmentarz-100' }),
                placeOptions,
                [],
            ).place,
        ).toBe('cmentarz-100');
        expect(
            resolveFilters(
                filtersOf({ place: 'cmentarz-101' }),
                placeOptions,
                [],
            ).place,
        ).toBe('');
    });

    it('blanks a place or an archive outside the options', () => {
        const resolved = resolveFilters(
            filtersOf({
                place: 'miejscowosc-99',
                archive: 'AP Gniezno',
                q: 'x',
            }),
            placeOptions,
            ['AP Poznań'],
        );

        expect(resolved.place).toBe('');
        expect(resolved.archive).toBe('');
        expect(resolved.q).toBe('x');
    });
});

describe('isFiltering', () => {
    it('is false for the defaults and the view or sort alone', () => {
        expect(isFiltering(filtersOf())).toBe(false);
        expect(isFiltering(filtersOf({ view: 'tabela', sort: 'nazwa' }))).toBe(
            false,
        );
    });

    it('is true for any filter', () => {
        expect(isFiltering(filtersOf({ q: 'x' }))).toBe(true);
        expect(isFiltering(filtersOf({ kind: 'akty' }))).toBe(true);
        expect(isFiltering(filtersOf({ place: 'parafia-1' }))).toBe(true);
        expect(isFiltering(filtersOf({ year_to: 1900 }))).toBe(true);
        expect(isFiltering(filtersOf({ archive: 'AP' }))).toBe(true);
    });
});

describe('yearFieldsError', () => {
    const rangeMessage = '„Rok od” nie może być późniejszy niż „Rok do”.';
    const yearMessage = 'Rok musi być liczbą od 1 do 9999.';

    it('flags a year outside 1 to 9999 on its own field', () => {
        expect(yearFieldsError('12345', '')).toEqual({
            from: true,
            to: false,
            message: yearMessage,
        });
        expect(yearFieldsError('0', '')).toEqual({
            from: true,
            to: false,
            message: yearMessage,
        });
        expect(yearFieldsError('', '-5')).toEqual({
            from: false,
            to: true,
            message: yearMessage,
        });
    });

    it('flags both fields when the range is reversed', () => {
        expect(yearFieldsError('1900', '1850')).toEqual({
            from: true,
            to: true,
            message: rangeMessage,
        });
    });

    it('accepts equal years and a valid range', () => {
        expect(yearFieldsError('1850', '1850')).toBeNull();
        expect(yearFieldsError('1850', '1900')).toBeNull();
    });

    it('treats blank fields as no error', () => {
        expect(yearFieldsError('', '')).toBeNull();
    });

    it('reports a field that does not parse before a reversed range', () => {
        expect(yearFieldsError('12345', '1850')).toEqual({
            from: true,
            to: false,
            message: yearMessage,
        });
    });
});

describe('hiddenFilterCount', () => {
    it('counts the years range as one pill', () => {
        expect(
            hiddenFilterCount(filtersOf({ year_from: 1850, year_to: 1900 })),
        ).toBe(1);
    });

    it('counts the years once with only one bound', () => {
        expect(hiddenFilterCount(filtersOf({ year_from: 1850 }))).toBe(1);
    });

    it('is 3 when place, years and archive are all set', () => {
        expect(
            hiddenFilterCount(
                filtersOf({
                    place: 'parafia-1',
                    year_from: 1850,
                    year_to: 1900,
                    archive: 'AP Poznań',
                }),
            ),
        ).toBe(3);
    });

    it('is 0 for the defaults', () => {
        expect(hiddenFilterCount(filtersOf())).toBe(0);
    });

    it('does not count the kind', () => {
        expect(hiddenFilterCount(filtersOf({ kind: 'akty' }))).toBe(0);
    });
});

describe('filterOptionCounts', () => {
    const sources = [
        source(1, {
            kind: 'metrical_record',
            name: 'Lipnica akt',
            year: 1880,
            archive: 'AP A',
            parish_id: 1,
            parish_locality_id: 10,
        }),
        source(2, {
            kind: 'gravestone',
            name: 'Lipnica grób',
            cemetery_id: 200,
            cemetery_locality_id: 20,
        }),
        source(3, {
            kind: 'gravestone',
            name: 'Inne',
            cemetery_id: 100,
            cemetery_locality_id: 10,
        }),
        source(4, {
            kind: 'metrical_record',
            name: 'Lipnica akt B',
            year: 1890,
            archive: 'AP B',
            parish_id: 2,
            parish_locality_id: 20,
        }),
    ];

    it('counts each archive with the search and years applied', () => {
        expect(
            filterOptionCounts(
                sources,
                filtersOf({ q: 'lipnica', year_from: 1850, year_to: 1900 }),
                'archive',
                ['', 'AP A', 'AP B'],
            ),
        ).toEqual({ '': 2, 'AP A': 1, 'AP B': 1 });
    });

    it('counts each place with the kind applied', () => {
        expect(
            filterOptionCounts(
                sources,
                filtersOf({ kind: 'nagrobki' }),
                'place',
                ['', 'miejscowosc-10', 'miejscowosc-20'],
            ),
        ).toEqual({ '': 2, 'miejscowosc-10': 1, 'miejscowosc-20': 1 });
    });

    it('counts the blank option without its own filter but with the other one', () => {
        const counts = filterOptionCounts(
            sources,
            filtersOf({ archive: 'AP A', year_from: 1850 }),
            'archive',
            [''],
        );

        expect(counts['']).toBe(
            filterSources(sources, filtersOf({ year_from: 1850 })).visible
                .length,
        );
    });

    it('is 0 for an option with no matching sources', () => {
        expect(
            filterOptionCounts(sources, filtersOf(), 'archive', ['AP Z']),
        ).toEqual({ 'AP Z': 0 });
    });
});

describe('formatYearsFilter', () => {
    it('joins both bounds with a spaced en dash', () => {
        expect(formatYearsFilter(1880, 1900)).toBe('1880 – 1900');
    });

    it('uses od for only a from bound', () => {
        expect(formatYearsFilter(1880, null)).toBe('od 1880');
    });

    it('uses do for only a to bound', () => {
        expect(formatYearsFilter(null, 1900)).toBe('do 1900');
    });

    it('is empty when neither bound is set', () => {
        expect(formatYearsFilter(null, null)).toBe('');
    });
});

describe('halfCenturyPresets', () => {
    it('spans the half-century of a year', () => {
        expect(halfCenturyPresets([source(1, { year: 1850 })])).toEqual([
            { from: 1850, to: 1899 },
        ]);
        expect(halfCenturyPresets([source(1, { year: 1849 })])).toEqual([
            { from: 1800, to: 1849 },
        ]);
    });

    it('merges duplicates and sorts ascending', () => {
        const sources = [
            source(1, { year: 1950 }),
            source(2, { year: 1850 }),
            source(3, { year: 1899 }),
            source(4, { year: 1860 }),
            source(5),
        ];

        expect(halfCenturyPresets(sources)).toEqual([
            { from: 1850, to: 1899 },
            { from: 1950, to: 1999 },
        ]);
    });

    it('is empty when no source has a year', () => {
        expect(halfCenturyPresets([source(1), source(2)])).toEqual([]);
    });
});

describe('resultCountText', () => {
    it('uses the plural forms when not filtering', () => {
        expect(resultCountText(1, 1, false)).toBe('1 źródło');
        expect(resultCountText(3, 3, false)).toBe('3 źródła');
        expect(resultCountText(5, 5, false)).toBe('5 źródeł');
    });

    it('shows the shown and total counts while filtering', () => {
        expect(resultCountText(12, 64, true)).toBe('12 z 64 źródeł');
        expect(resultCountText(1, 1, true)).toBe('1 z 1 źródła');
        expect(resultCountText(0, 64, true)).toBe('0 z 64 źródeł');
        expect(resultCountText(1, 2, true)).toBe('1 z 2 źródeł');
    });
});

describe('filterSources with a gravestone', () => {
    const list = [
        source(1, { kind: 'metrical_record', year: 1880, archive: 'AP A' }),
        source(2, { kind: 'metrical_record' }),
        source(3, { kind: 'gravestone', cemetery_id: 100 }),
    ];

    it('hides a gravestone when a year is set and does not count it as lacking a year', () => {
        const { visible, counts, hidden } = filterSources(
            list,
            filtersOf({ year_from: 1850, year_to: 1899 }),
        );

        expect(ids(visible)).toEqual([1]);
        expect(counts.nagrobki).toBe(0);
        expect(hidden).toEqual({ count: 1, missing: ['year'] });
    });

    it('hides a gravestone when an archive is set and does not count it as lacking an archive', () => {
        const { visible, hidden } = filterSources(
            list,
            filtersOf({ archive: 'AP A' }),
        );

        expect(ids(visible)).toEqual([1]);
        expect(hidden).toEqual({ count: 1, missing: ['archive'] });
    });

    it('shows no hidden gravestones on the nagrobki tab while a year is set', () => {
        const { visible, hidden } = filterSources(
            list,
            filtersOf({ kind: 'nagrobki', year_from: 1850 }),
        );

        expect(visible).toEqual([]);
        expect(hidden).toEqual({ count: 0, missing: [] });
    });

    it('still counts an akt without a year as lacking one', () => {
        expect(
            filterSources(list, filtersOf({ year_to: 1900, kind: 'akty' }))
                .hidden,
        ).toEqual({ count: 1, missing: ['year'] });
    });
});

describe('filterSources', () => {
    const list = [
        source(1, {
            kind: 'metrical_record',
            name: 'Urodzenia Lipnica 1878 s. 12',
        }),
        source(2, { kind: 'gravestone', name: 'Świętego Jana Lipnica' }),
        source(3, { name: null }),
        source(4, { name: 'Coś innego' }),
    ];

    it('finds every word in any order, ignoring case', () => {
        expect(
            ids(filterSources(list, filtersOf({ q: '1878 lipnica' })).visible),
        ).toEqual([1]);
        expect(
            ids(filterSources(list, filtersOf({ q: 'LIPNICA' })).visible),
        ).toEqual([1, 2]);
    });

    it('does not fold Polish diacritics', () => {
        expect(
            filterSources(list, filtersOf({ q: 'swietego' })).visible,
        ).toEqual([]);
        expect(
            ids(filterSources(list, filtersOf({ q: 'świętego' })).visible),
        ).toEqual([2]);
    });

    it('matches a blank query against every source', () => {
        expect(
            filterSources(list, filtersOf({ q: '   ' })).visible,
        ).toHaveLength(4);
    });

    it('does not find a named source by its file name, but an unnamed one', () => {
        expect(filterSources(list, filtersOf({ q: 'plik-1' })).visible).toEqual(
            [],
        );
        expect(
            ids(filterSources(list, filtersOf({ q: 'plik-3' })).visible),
        ).toEqual([3]);
    });

    it('counts by the search and ignores the chosen kind', () => {
        const { visible, counts } = filterSources(
            list,
            filtersOf({ q: 'lipnica', kind: 'nagrobki' }),
        );

        expect(ids(visible)).toEqual([2]);
        expect(counts).toEqual({ wszystkie: 2, akty: 1, nagrobki: 1, inne: 0 });
    });

    it('matches a locality by a cemetery or a parish seated there, not a parish seated elsewhere', () => {
        const sources = [
            source(1, {
                kind: 'metrical_record',
                parish_id: 1,
                parish_locality_id: 10,
            }),
            source(2, {
                kind: 'gravestone',
                cemetery_id: 100,
                cemetery_locality_id: 10,
            }),
            source(3, {
                kind: 'metrical_record',
                parish_id: 2,
                parish_locality_id: 20,
            }),
        ];

        expect(
            ids(
                filterSources(sources, filtersOf({ place: 'miejscowosc-10' }))
                    .visible,
            ),
        ).toEqual([1, 2]);
    });

    it('matches a cemetery only by the gravestones that point at it', () => {
        const sources = [
            source(1, {
                kind: 'gravestone',
                cemetery_id: 100,
                cemetery_locality_id: 10,
            }),
            source(2, {
                kind: 'gravestone',
                cemetery_id: 101,
                cemetery_locality_id: 10,
            }),
            source(3, {
                kind: 'metrical_record',
                parish_id: 1,
                parish_locality_id: 10,
            }),
        ];

        expect(
            ids(
                filterSources(sources, filtersOf({ place: 'cmentarz-100' }))
                    .visible,
            ),
        ).toEqual([1]);
        expect(
            ids(
                filterSources(sources, filtersOf({ place: 'miejscowosc-10' }))
                    .visible,
            ),
        ).toEqual([1, 2, 3]);
    });

    it('matches a parish only by its own sources, not a gravestone in the same locality', () => {
        const sources = [
            source(1, {
                kind: 'metrical_record',
                parish_id: 1,
                parish_locality_id: 10,
            }),
            source(2, {
                kind: 'gravestone',
                cemetery_id: 100,
                cemetery_locality_id: 10,
            }),
            source(3, {
                kind: 'metrical_record',
                parish_id: 2,
                parish_locality_id: 10,
            }),
        ];

        expect(
            ids(
                filterSources(sources, filtersOf({ place: 'parafia-1' }))
                    .visible,
            ),
        ).toEqual([1]);
    });

    it('includes both ends of the year range and nothing outside it', () => {
        const sources = [1890, 1900, 1910, 1920].map((year, index) =>
            source(index + 1, { year }),
        );

        expect(
            ids(
                filterSources(
                    sources,
                    filtersOf({ year_from: 1900, year_to: 1910 }),
                ).visible,
            ),
        ).toEqual([2, 3]);
        expect(
            ids(filterSources(sources, filtersOf({ year_from: 1910 })).visible),
        ).toEqual([3, 4]);
        expect(
            ids(filterSources(sources, filtersOf({ year_to: 1900 })).visible),
        ).toEqual([1, 2]);
    });

    it('hides and counts a source without a year under an active range', () => {
        const sources = [source(1, { year: 1900 }), source(2)];
        const result = filterSources(sources, filtersOf({ year_from: 1890 }));

        expect(ids(result.visible)).toEqual([1]);
        expect(result.hidden).toEqual({ count: 1, missing: ['year'] });
    });

    it('does not count a missing year when no range is active', () => {
        const sources = [source(1), source(2, { year: 1900 })];
        const result = filterSources(sources, filtersOf());

        expect(ids(result.visible)).toEqual([1, 2]);
        expect(result.hidden).toEqual({ count: 0, missing: [] });
    });

    it('matches nothing for a reversed range', () => {
        const sources = [source(1, { year: 1900 }), source(2, { year: 1905 })];
        const result = filterSources(
            sources,
            filtersOf({ year_from: 1910, year_to: 1900 }),
        );

        expect(result.visible).toEqual([]);
        expect(result.hidden).toEqual({ count: 0, missing: [] });
    });

    it('counts "brak miejsca" only for a source with no parish and no cemetery', () => {
        const sources = [
            source(1, { kind: 'other' }),
            source(2, {
                kind: 'gravestone',
                cemetery_id: 100,
                cemetery_locality_id: 10,
            }),
            source(3, { kind: 'metrical_record', parish_id: 2 }),
        ];

        const parishResult = filterSources(
            sources,
            filtersOf({ place: 'parafia-1' }),
        );
        expect(parishResult.visible).toEqual([]);
        expect(parishResult.hidden).toEqual({ count: 1, missing: ['place'] });

        const localityResult = filterSources(
            sources,
            filtersOf({ place: 'miejscowosc-10' }),
        );
        expect(ids(localityResult.visible)).toEqual([2]);
        expect(localityResult.hidden).toEqual({ count: 1, missing: ['place'] });
    });

    it('matches the archive exactly', () => {
        const sources = [
            source(1, { archive: 'AP Poznań' }),
            source(2, { archive: 'AP Poznań, oddz. Gniezno' }),
        ];

        expect(
            ids(
                filterSources(sources, filtersOf({ archive: 'AP Poznań' }))
                    .visible,
            ),
        ).toEqual([1]);
    });

    it('lists only the active criteria a hidden source lacks, in fixed order', () => {
        const sources = [source(1, { archive: null })];

        expect(
            filterSources(sources, filtersOf({ archive: 'AP Poznań' })).hidden,
        ).toEqual({ count: 1, missing: ['archive'] });
        expect(
            filterSources(
                sources,
                filtersOf({ archive: 'AP Poznań', year_from: 1900 }),
            ).hidden,
        ).toEqual({ count: 1, missing: ['year', 'archive'] });
    });

    it('does not count a source that is hidden by a mismatch, not by missing data', () => {
        const sources = [source(1, { year: 1850 })];
        const result = filterSources(sources, filtersOf({ year_from: 1900 }));

        expect(result.visible).toEqual([]);
        expect(result.hidden).toEqual({ count: 0, missing: [] });
    });

    it('counts places after the search and the years, ignoring the chosen kind', () => {
        const sources = [
            source(1, {
                kind: 'metrical_record',
                parish_id: 1,
                parish_locality_id: 10,
            }),
            source(2, {
                kind: 'gravestone',
                cemetery_id: 100,
                cemetery_locality_id: 10,
            }),
            source(3, {
                kind: 'gravestone',
                cemetery_id: 200,
                cemetery_locality_id: 20,
            }),
        ];
        const { visible, counts } = filterSources(
            sources,
            filtersOf({ place: 'miejscowosc-10', kind: 'nagrobki' }),
        );

        expect(ids(visible)).toEqual([2]);
        expect(counts).toEqual({ wszystkie: 2, akty: 1, nagrobki: 1, inne: 0 });
    });
});

describe('sortSources', () => {
    it('returns the server order for -dodano, ties with equal timestamps included', () => {
        const serverOrder = [
            source(5, { uploaded_at: '2026-09-25T10:00:00+00:00' }),
            source(4),
            source(3),
            source(2, { uploaded_at: '2026-09-23T10:00:00+00:00' }),
        ];

        expect(ids(sortSources(serverOrder, '-dodano'))).toEqual([5, 4, 3, 2]);
        expect(ids(sortSources(serverOrder, 'dodano'))).toEqual([2, 3, 4, 5]);
    });

    it('puts sources without a year last in both directions', () => {
        const list = [
            source(1),
            source(2, { year: 1932 }),
            source(3, { year: 1878 }),
        ];

        expect(ids(sortSources(list, 'rok'))).toEqual([3, 2, 1]);
        expect(ids(sortSources(list, '-rok'))).toEqual([2, 3, 1]);
    });

    it('starts potwierdza ascending with 0', () => {
        const list = [
            source(1, { events_count: 2 }),
            source(2, { events_count: 0 }),
            source(3, { events_count: 1 }),
        ];

        expect(ids(sortSources(list, 'potwierdza'))).toEqual([2, 3, 1]);
    });

    it('orders rodzaj akt, nagrobek, inne, an akt without a type last among akts', () => {
        const list = [
            source(1),
            source(2, { kind: 'gravestone' }),
            source(3, { kind: 'metrical_record' }),
            source(4, {
                kind: 'metrical_record',
                record_type: 'birth',
                record_type_label: 'urodzenia',
            }),
        ];

        expect(ids(sortSources(list, 'rodzaj'))).toEqual([4, 3, 2, 1]);
    });

    it('breaks ties by name then id, ascending in both directions', () => {
        const list = [
            source(3, { year: 1878, name: 'B' }),
            source(2, { year: 1878, name: 'A' }),
            source(1, { year: 1878, name: 'A' }),
        ];

        expect(ids(sortSources(list, 'rok'))).toEqual([1, 2, 3]);
        expect(ids(sortSources(list, '-rok'))).toEqual([1, 2, 3]);
    });

    it('sorts nazwa with numbers as numbers and reverses only the name', () => {
        const list = [
            source(1, { name: 'Akt 10' }),
            source(2, { name: 'Akt 9' }),
        ];

        expect(ids(sortSources(list, 'nazwa'))).toEqual([2, 1]);
        expect(ids(sortSources(list, '-nazwa'))).toEqual([1, 2]);
    });
});

describe('eventsLabel', () => {
    it('uses the three plural forms', () => {
        expect(eventsLabel(1)).toBe('1 wydarzenie');
        expect(eventsLabel(2)).toBe('2 wydarzenia');
        expect(eventsLabel(5)).toBe('5 wydarzeń');
    });

    it('is null for 0', () => {
        expect(eventsLabel(0)).toBeNull();
    });
});
