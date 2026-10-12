import type { Person, PersonEvent } from '@/types';

export type PersonsView = 'lista' | 'tabela';

export type PersonsSort =
    | 'nazwisko'
    | '-nazwisko'
    | 'urodzenie'
    | '-urodzenie'
    | 'zgon'
    | '-zgon';

export type PersonsFilters = {
    q: string;
    view: PersonsView;
    sort: PersonsSort;
};

export type PersonGroup = {
    key: string;
    title: string;
    persons: Person[];
};

const BASE_URL = 'http://localhost';

/** The URL with only the list keys (`q`, `view`, `sort`) replaced, defaults removed; panel keys, other parameters and hash stay. */
export function withPersonsQuery(
    url: string,
    filters: Partial<PersonsFilters>,
): string {
    const parsed = new URL(url, BASE_URL);
    const query = buildPersonsQuery(filters);

    for (const key of ['q', 'view', 'sort']) {
        const value = query[key];

        if (value === undefined) {
            parsed.searchParams.delete(key);
        } else {
            parsed.searchParams.set(key, value);
        }
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}

const NO_SURNAME_KEY = 'bez-nazwiska';
const NO_SURNAME_TITLE = 'Bez nazwiska';

const collator = new Intl.Collator('pl');

/** The query of a `/persons` visit: defaults and blanks are omitted, `sort` only applies to the table. */
export function buildPersonsQuery(
    filters: Partial<PersonsFilters>,
): Record<string, string> {
    const query: Record<string, string> = {};
    const q = filters.q?.trim() ?? '';

    if (q !== '') {
        query.q = q;
    }

    if (filters.view === 'tabela') {
        query.view = 'tabela';

        if (filters.sort && filters.sort !== 'nazwisko') {
            query.sort = filters.sort;
        }
    }

    return query;
}

function surnameOf(person: Person): string | null {
    const surname = person.last_name?.trim() ?? '';

    return surname === '' ? null : surname;
}

/** Surname (collated), then first name (collated), then id: always ascending. */
function compareNameTail(a: Person, b: Person): number {
    return (
        collator.compare(a.last_name ?? '', b.last_name ?? '') ||
        collator.compare(a.first_name ?? '', b.first_name ?? '') ||
        a.id - b.id
    );
}

function compareByName(a: Person, b: Person): number {
    const aMissing = surnameOf(a) === null;
    const bMissing = surnameOf(b) === null;

    if (aMissing !== bMissing) {
        return aMissing ? 1 : -1;
    }

    return compareNameTail(a, b);
}

/** Uppercase first letter of the surname, or null for a person without one. */
function surnameInitial(person: Person): string | null {
    const surname = surnameOf(person);

    return surname === null
        ? null
        : (Array.from(surname)[0] ?? '').toLocaleUpperCase('pl');
}

/** Group people under the first letter of the surname; people without one go last. */
export function groupBySurnameInitial(persons: Person[]): PersonGroup[] {
    const byInitial = new Map<string, Person[]>();
    const withoutSurname: Person[] = [];

    for (const person of persons) {
        const initial = surnameInitial(person);

        if (initial === null) {
            withoutSurname.push(person);
        } else {
            const group = byInitial.get(initial);

            if (group) {
                group.push(person);
            } else {
                byInitial.set(initial, [person]);
            }
        }
    }

    const groups: PersonGroup[] = [...byInitial.entries()]
        .sort(([a], [b]) => collator.compare(a, b))
        .map(([initial, members]) => ({
            key: initial,
            title: initial,
            persons: [...members].sort(compareByName),
        }));

    if (withoutSurname.length > 0) {
        groups.push({
            key: NO_SURNAME_KEY,
            title: NO_SURNAME_TITLE,
            persons: [...withoutSurname].sort(compareByName),
        });
    }

    return groups;
}

type DateKey = readonly [year: number, month: number, day: number];

/** Year/month/day of an event, or null when the date is unknown (no event or no year). */
function dateKey(event: PersonEvent | null): DateKey | null {
    const parts = event?.date_parts;

    if (!parts || parts.year === null) {
        return null;
    }

    return [parts.year, parts.month ?? 0, parts.day ?? 0];
}

function compareDateKeys(a: DateKey, b: DateKey): number {
    return a[0] - b[0] || a[1] - b[1] || a[2] - b[2];
}

/** Sort a copy of the people for the table; the `-` variants reverse only the primary key. */
export function sortPersons(persons: Person[], sort: PersonsSort): Person[] {
    const descending = sort.startsWith('-');
    const direction = descending ? -1 : 1;
    const column = descending ? sort.slice(1) : sort;

    if (column === 'nazwisko') {
        return [...persons].sort((a, b) => {
            const aMissing = surnameOf(a) === null;
            const bMissing = surnameOf(b) === null;

            if (aMissing !== bMissing) {
                return aMissing ? 1 : -1;
            }

            const primary = collator.compare(
                a.last_name ?? '',
                b.last_name ?? '',
            );

            return (
                (aMissing ? 0 : direction * primary) ||
                collator.compare(a.first_name ?? '', b.first_name ?? '') ||
                a.id - b.id
            );
        });
    }

    const eventOf = (person: Person) =>
        column === 'zgon' ? person.death : person.birth;

    return [...persons].sort((a, b) => {
        const aKey = dateKey(eventOf(a));
        const bKey = dateKey(eventOf(b));

        if ((aKey === null) !== (bKey === null)) {
            return aKey === null ? 1 : -1;
        }

        const primary = aKey && bKey ? compareDateKeys(aKey, bKey) : 0;

        return direction * primary || compareNameTail(a, b);
    });
}

/** „ur. 12.03.1948, Kraków” for view A; null when there is nothing to show. */
export function formatEventMeta(
    prefix: 'ur.' | 'zm.',
    event: Pick<PersonEvent, 'date' | 'place'> | null,
): string | null {
    if (!event) {
        return null;
    }

    const date = event.date || (prefix === 'zm.' ? 'data nieznana' : null);
    const label = [date, event.place].filter(Boolean).join(', ');

    return label === '' ? null : `${prefix} ${label}`;
}

/** „1 osoba”, „3 osoby”, „6 osób”, „12 osób”, „22 osoby”. */
export function peopleCount(count: number): string {
    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;

    if (count === 1) {
        return '1 osoba';
    }

    if (
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        !(lastTwoDigits >= 12 && lastTwoDigits <= 14)
    ) {
        return `${count} osoby`;
    }

    return `${count} osób`;
}
