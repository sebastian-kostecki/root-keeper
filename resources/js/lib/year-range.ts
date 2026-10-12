/** Years of a stay in the view: „1900–1910”, „od 1900”, „do 1910”, or empty. */
export function formatYearRange(
    from: number | null,
    to: number | null,
): string {
    if (from !== null && to !== null) {
        return `${from}–${to}`;
    }

    if (from !== null) {
        return `od ${from}`;
    }

    if (to !== null) {
        return `do ${to}`;
    }

    return '';
}
