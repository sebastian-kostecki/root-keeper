import type { Cemetery } from '@/types';

const collator = new Intl.Collator('pl');

/** Cemeteries whose name, locality or label contains the search, ignoring case; a blank search matches all. */
export function filterCemeteries(
    cemeteries: Cemetery[],
    q: string,
): Cemetery[] {
    const needle = q.trim().toLocaleLowerCase('pl');

    if (needle === '') {
        return cemeteries;
    }

    return cemeteries.filter(
        (cemetery) =>
            (cemetery.name?.toLocaleLowerCase('pl').includes(needle) ??
                false) ||
            cemetery.locality_name.toLocaleLowerCase('pl').includes(needle) ||
            cemetery.label.toLocaleLowerCase('pl').includes(needle),
    );
}

/** Sort a copy by locality, then name in Polish order (no name first), ties by id. */
export function sortCemeteries(cemeteries: Cemetery[]): Cemetery[] {
    return [...cemeteries].sort(
        (a, b) =>
            collator.compare(a.locality_name, b.locality_name) ||
            collator.compare(a.name ?? '', b.name ?? '') ||
            a.id - b.id,
    );
}
