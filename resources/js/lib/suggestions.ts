const collator = new Intl.Collator('pl');

/**
 * Options whose label contains the query, ignoring case; a blank query matches all.
 * Labels that start with the query come first, then the rest; each group in Polish order.
 */
export function filterSuggestions<T>(
    options: T[],
    query: string,
    labelOf: (option: T) => string,
    limit?: number,
): T[] {
    const needle = query.trim().toLocaleLowerCase('pl');
    const labelled = options.map((option) => ({
        option,
        label: labelOf(option),
        lower: labelOf(option).toLocaleLowerCase('pl'),
    }));

    const matching =
        needle === ''
            ? labelled
            : labelled.filter((item) => item.lower.includes(needle));

    const startsWith = (item: { lower: string }) =>
        needle !== '' && item.lower.startsWith(needle);

    const sorted = matching.sort(
        (a, b) =>
            Number(startsWith(b)) - Number(startsWith(a)) ||
            collator.compare(a.label, b.label),
    );

    const result = sorted.map((item) => item.option);

    return limit === undefined ? result : result.slice(0, limit);
}

/** Whether „Dodaj …” makes sense: the trimmed query is not blank and no option is named exactly so (ignoring case). */
export function canOfferCreate<T>(
    options: T[],
    query: string,
    nameOf: (option: T) => string,
): boolean {
    const needle = query.trim().toLocaleLowerCase('pl');

    if (needle === '') {
        return false;
    }

    return !options.some(
        (option) => nameOf(option).toLocaleLowerCase('pl') === needle,
    );
}
