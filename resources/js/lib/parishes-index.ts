import type { Parish } from '@/types';

const collator = new Intl.Collator('pl');

/** Parishes whose name or seat contains the search, ignoring case; a blank search matches all. */
export function filterParishes(parishes: Parish[], q: string): Parish[] {
    const needle = q.trim().toLocaleLowerCase('pl');

    if (needle === '') {
        return parishes;
    }

    return parishes.filter(
        (parish) =>
            parish.name.toLocaleLowerCase('pl').includes(needle) ||
            (parish.locality_name?.toLocaleLowerCase('pl').includes(needle) ??
                false),
    );
}

/** Sort a copy by name in Polish order, ties by id. */
export function sortParishes(parishes: Parish[]): Parish[] {
    return [...parishes].sort(
        (a, b) => collator.compare(a.name, b.name) || a.id - b.id,
    );
}
