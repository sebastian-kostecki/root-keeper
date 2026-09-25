/**
 * Picks the Polish plural form for a count: `one` for 1, `few` for counts
 * ending in 2–4 (except 12–14), `many` for everything else, including 0.
 */
export function pluralPhrase(
    count: number,
    one: string,
    few: string,
    many: string,
): string {
    if (count === 1) {
        return one;
    }

    const lastDigit = count % 10;
    const lastTwoDigits = count % 100;

    if (
        lastDigit >= 2 &&
        lastDigit <= 4 &&
        (lastTwoDigits < 12 || lastTwoDigits > 14)
    ) {
        return few;
    }

    return many;
}
