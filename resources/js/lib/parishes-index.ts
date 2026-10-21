import type { Parish } from '@/types';

const collator = new Intl.Collator('pl');

/** Parishes whose label, former name or seat contains the search, ignoring case; a blank search matches all. */
export function filterParishes(parishes: Parish[], q: string): Parish[] {
    const needle = q.trim().toLocaleLowerCase('pl');

    if (needle === '') {
        return parishes;
    }

    return parishes.filter(
        (parish) =>
            parish.label.toLocaleLowerCase('pl').includes(needle) ||
            (parish.name?.toLocaleLowerCase('pl').includes(needle) ?? false) ||
            (parish.locality_name?.toLocaleLowerCase('pl').includes(needle) ??
                false),
    );
}

/** Sort a copy by label in Polish order, ties by id. */
export function sortParishes(parishes: Parish[]): Parish[] {
    return [...parishes].sort(
        (a, b) => collator.compare(a.label, b.label) || a.id - b.id,
    );
}
