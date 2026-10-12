import { describe, expect, it } from 'vite-plus/test';
import type { Person } from '@/types';
import {
    buildPersonsQuery,
    formatEventMeta,
    groupBySurnameInitial,
    sortPersons,
    withPersonsQuery,
} from './persons-index';

function event(
    year: number | null,
    month: number | null = null,
    day: number | null = null,
    place: string | null = null,
): Person['birth'] {
    return {
        id: 1,
        type: 'birth',
        date: year === null ? null : String(year),
        place,
        locality_id: null,
        date_parts: { year, month, day, is_approximate: false },
        is_confirmed: false,
    };
}

function person(
    id: number,
    last_name: string | null,
    first_name: string | null,
    overrides: Partial<Person> = {},
): Person {
    return {
        id,
        first_name,
        last_name,
        maiden_name: null,
        display_name: [first_name, last_name].filter(Boolean).join(' '),
        gender: null,
        religion: null,
        burial_place: null,
        birth: null,
        death: null,
        age: null,
        photo_version: null,
        ...overrides,
    };
}

const ids = (persons: Person[]) => persons.map((p) => p.id);

describe('buildPersonsQuery', () => {
    it('omits defaults and blanks', () => {
        expect(
            buildPersonsQuery({ q: '  ', view: 'lista', sort: 'nazwisko' }),
        ).toEqual({});
        expect(
            buildPersonsQuery({ q: '', view: 'tabela', sort: 'nazwisko' }),
        ).toEqual({ view: 'tabela' });
    });

    it('keeps a trimmed search and the table sort', () => {
        expect(
            buildPersonsQuery({
                q: ' Kow ',
                view: 'tabela',
                sort: '-urodzenie',
            }),
        ).toEqual({ q: 'Kow', view: 'tabela', sort: '-urodzenie' });
    });

    it('drops sort on lista', () => {
        expect(
            buildPersonsQuery({ q: 'Kow', view: 'lista', sort: '-zgon' }),
        ).toEqual({ q: 'Kow' });
    });
});

describe('withPersonsQuery', () => {
    const lista = { q: '', view: 'lista', sort: 'nazwisko' } as const;

    it('keeps the panel keys and other parameters of the address', () => {
        expect(
            withPersonsQuery('/persons?add=person&q=Kow&view=lista', {
                ...lista,
                q: 'Kow',
            }),
        ).toBe('/persons?add=person&q=Kow');
        expect(
            withPersonsQuery('/persons?person=7&foo=bar#x', {
                ...lista,
                q: 'Kow',
            }),
        ).toBe('/persons?person=7&foo=bar&q=Kow#x');
    });

    it('removes an empty search and keeps the panel key', () => {
        expect(
            withPersonsQuery('/persons?q=Kow&add=person', {
                ...lista,
                q: '  ',
            }),
        ).toBe('/persons?add=person');
    });

    it('sets the view and sort only for the table', () => {
        expect(
            withPersonsQuery('/persons?add=person', {
                q: '',
                view: 'tabela',
                sort: '-zgon',
            }),
        ).toBe('/persons?add=person&view=tabela&sort=-zgon');
        expect(
            withPersonsQuery('/persons?view=tabela&sort=-zgon', {
                ...lista,
                view: 'lista',
                sort: '-zgon',
            }),
        ).toBe('/persons');
    });
});

describe('groupBySurnameInitial', () => {
    it('orders Ł, Ś and Ż after L, S and Z', () => {
        const groups = groupBySurnameInitial([
            person(1, 'Żak', 'A'),
            person(2, 'Zając', 'A'),
            person(3, 'Łukasz', 'A'),
            person(4, 'Lis', 'A'),
            person(5, 'Śliwa', 'A'),
            person(6, 'Sowa', 'A'),
        ]);

        expect(groups.map((g) => g.title)).toEqual([
            'L',
            'Ł',
            'S',
            'Ś',
            'Z',
            'Ż',
        ]);
    });

    it('puts people without a surname in a final group', () => {
        const groups = groupBySurnameInitial([
            person(1, null, 'Anna'),
            person(2, 'Zieliński', 'Jan'),
            person(3, 'Adamski', 'Piotr'),
        ]);

        expect(groups.map((g) => g.title)).toEqual(['A', 'Z', 'Bez nazwiska']);
        expect(ids(groups[2].persons)).toEqual([1]);
    });

    it('orders a group by surname, first name, id rather than server order', () => {
        const groups = groupBySurnameInitial([
            person(3, 'Kowalski', 'Jan'),
            person(2, 'Kowalski', 'Adam'),
            person(5, 'Kowalski', 'Jan'),
            person(1, 'Kowalczyk', 'Zofia'),
        ]);

        expect(ids(groups[0].persons)).toEqual([1, 2, 3, 5]);
    });
});

describe('sortPersons: nazwisko', () => {
    const people = [
        person(1, 'Żak', 'A'),
        person(2, null, 'B'),
        person(3, 'Zając', 'A'),
        person(4, 'Łukasz', 'A'),
        person(5, 'Lis', 'A'),
    ];

    it('puts Ł after L and Ż after Z, and no surname last', () => {
        expect(ids(sortPersons(people, 'nazwisko'))).toEqual([5, 4, 3, 1, 2]);
    });

    it('reverses the surname but keeps no surname last', () => {
        expect(ids(sortPersons(people, '-nazwisko'))).toEqual([1, 3, 4, 5, 2]);
    });

    it('keeps first name and id ascending on a reversed surname', () => {
        const same = [
            person(1, 'Nowak', 'Jan'),
            person(2, 'Nowak', 'Adam'),
            person(3, 'Nowak', 'Adam'),
        ];

        expect(ids(sortPersons(same, '-nazwisko'))).toEqual([2, 3, 1]);
    });
});

describe('sortPersons: dates', () => {
    const people = [
        person(1, 'Nowak', 'A', { birth: event(1950, 5, 1) }),
        person(2, 'Adam', 'A'),
        person(3, 'Kowal', 'A', { birth: event(1950, null, null) }),
        person(4, 'Bąk', 'A', { birth: event(null) }),
        person(5, 'Lis', 'A', { birth: event(1940, 12, 31) }),
        person(6, 'Abacki', 'A', { birth: event(1950, 5, 1) }),
    ];

    it('puts a missing month before a known month in the same year', () => {
        expect(ids(sortPersons(people, 'urodzenie')).slice(0, 4)).toEqual([
            5, 3, 6, 1,
        ]);
    });

    it('puts unknown dates last in both directions', () => {
        expect(ids(sortPersons(people, 'urodzenie')).slice(4)).toEqual([2, 4]);
        expect(ids(sortPersons(people, '-urodzenie')).slice(4)).toEqual([2, 4]);
    });

    it('reverses only the known part and breaks ties by name ascending', () => {
        expect(ids(sortPersons(people, '-urodzenie'))).toEqual([
            6, 1, 3, 5, 2, 4,
        ]);
    });

    it('sorts by death date', () => {
        const dead = [
            person(1, 'A', 'A', { death: event(2000) }),
            person(2, 'B', 'A'),
            person(3, 'C', 'A', { death: event(1990) }),
        ];

        expect(ids(sortPersons(dead, 'zgon'))).toEqual([3, 1, 2]);
        expect(ids(sortPersons(dead, '-zgon'))).toEqual([1, 3, 2]);
    });

    it('does not mutate the input', () => {
        const copy = [...people];
        sortPersons(people, 'urodzenie');

        expect(people).toEqual(copy);
    });
});

describe('formatEventMeta', () => {
    it('returns null without an event', () => {
        expect(formatEventMeta('ur.', null)).toBeNull();
    });

    it('joins date and place', () => {
        expect(
            formatEventMeta('ur.', { date: '12.03.1948', place: 'Kraków' }),
        ).toBe('ur. 12.03.1948, Kraków');
        expect(formatEventMeta('zm.', { date: 'ok. 2015', place: null })).toBe(
            'zm. ok. 2015',
        );
    });

    it('marks a death without a date', () => {
        expect(formatEventMeta('zm.', { date: null, place: null })).toBe(
            'zm. data nieznana',
        );
        expect(formatEventMeta('zm.', { date: null, place: 'Tarnów' })).toBe(
            'zm. data nieznana, Tarnów',
        );
    });

    it('shows a birth without a date by its place only', () => {
        expect(formatEventMeta('ur.', { date: null, place: 'Kraków' })).toBe(
            'ur. Kraków',
        );
        expect(formatEventMeta('ur.', { date: null, place: null })).toBeNull();
    });
});
