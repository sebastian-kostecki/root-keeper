type GenderKey = 'male' | 'female' | 'unknown';

/** Per-gender fill and border of a person surface, without any hover state. */
export const genderSurface: Record<GenderKey, string> = {
    male: 'bg-gender-male-surface border-gender-male-border',
    female: 'bg-gender-female-surface border-gender-female-border',
    unknown: 'bg-card border-tree-line',
};

/** Per-gender fill, border and hover border of a person card. */
export const genderCard: Record<GenderKey, string> = {
    male: `${genderSurface.male} hover:border-gender-male`,
    female: `${genderSurface.female} hover:border-gender-female`,
    unknown: `${genderSurface.unknown} hover:border-foreground/40`,
};
