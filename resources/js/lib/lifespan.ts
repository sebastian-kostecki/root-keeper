/**
 * The board's lifespan format from the server's short dates: "A – B", "ur. A",
 * "zm. B", or null when neither is known.
 */
export function formatLifespan(
    birthDate: string | null | undefined,
    deathDate: string | null | undefined,
): string | null {
    if (birthDate && deathDate) {
        return `${birthDate} – ${deathDate}`;
    }

    if (birthDate) {
        return `ur. ${birthDate}`;
    }

    if (deathDate) {
        return `zm. ${deathDate}`;
    }

    return null;
}
