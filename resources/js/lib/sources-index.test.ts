import { describe, expect, it } from 'vite-plus/test';
import type { IndexSource } from '@/types';
import {
    buildSourcesQuery,
    eventsLabel,
    filterSources,
    sortSources,
    withSourcesQuery,
} from './sources-index';

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
        entry_number: null,
        year: null,
        archive: null,
        parish_id: null,
        parish_label: null,
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

    it('leaves other parameters and the hash untouched', () => {
        expect(
            withSourcesQuery('/sources?person=7&source=5#top', {
                q: 'x',
                kind: 'wszystkie',
            }),
        ).toBe('/sources?person=7&source=5&q=x#top');
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
            ids(filterSources(list, '1878 lipnica', 'wszystkie').visible),
        ).toEqual([1]);
        expect(
            ids(filterSources(list, 'LIPNICA', 'wszystkie').visible),
        ).toEqual([1, 2]);
    });

    it('does not fold Polish diacritics', () => {
        expect(filterSources(list, 'swietego', 'wszystkie').visible).toEqual(
            [],
        );
        expect(
            ids(filterSources(list, 'świętego', 'wszystkie').visible),
        ).toEqual([2]);
    });

    it('matches a blank query against every source', () => {
        expect(filterSources(list, '   ', 'wszystkie').visible).toHaveLength(4);
    });

    it('does not find a named source by its file name, but an unnamed one', () => {
        expect(filterSources(list, 'plik-1', 'wszystkie').visible).toEqual([]);
        expect(ids(filterSources(list, 'plik-3', 'wszystkie').visible)).toEqual(
            [3],
        );
    });

    it('counts by the search and ignores the chosen kind', () => {
        const { visible, counts } = filterSources(list, 'lipnica', 'nagrobki');

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
