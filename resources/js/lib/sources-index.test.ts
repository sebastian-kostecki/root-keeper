import { describe, expect, it } from 'vite-plus/test';
import type { IndexSource } from '@/types';
import {
    buildSourcesQuery,
    eventsLabel,
    filterSources,
    hiddenFilterCount,
    isFiltering,
    resolveFilters,
    sortSources,
    withSourcesQuery,
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
        legacy_entry_number: null,
        year: null,
        archive: null,
        parish_id: null,
        parish_label: null,
        parish_name: null,
        parish_locality_id: null,
        locality_name: null,
        cemetery_locality_id: null,
        cemetery_name: null,
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

describe('hiddenFilterCount', () => {
    it('counts the set fields among place, years and archive', () => {
        expect(
            hiddenFilterCount(filtersOf({ year_from: 1850, year_to: 1900 })),
        ).toBe(2);
    });

    it('is 0 for the defaults', () => {
        expect(hiddenFilterCount(filtersOf())).toBe(0);
    });

    it('is 4 when all four are set', () => {
        expect(
            hiddenFilterCount(
                filtersOf({
                    place: 'parafia-1',
                    year_from: 1850,
                    year_to: 1900,
                    archive: 'AP Poznań',
                }),
            ),
        ).toBe(4);
    });

    it('does not count the kind', () => {
        expect(hiddenFilterCount(filtersOf({ kind: 'akty' }))).toBe(0);
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
            source(2, { kind: 'gravestone', cemetery_locality_id: 10 }),
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

    it('matches a parish only by its own sources, not a gravestone in the same locality', () => {
        const sources = [
            source(1, {
                kind: 'metrical_record',
                parish_id: 1,
                parish_locality_id: 10,
            }),
            source(2, { kind: 'gravestone', cemetery_locality_id: 10 }),
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
            source(2, { kind: 'gravestone', cemetery_locality_id: 10 }),
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
            source(2, { kind: 'gravestone', cemetery_locality_id: 10 }),
            source(3, { kind: 'gravestone', cemetery_locality_id: 20 }),
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
