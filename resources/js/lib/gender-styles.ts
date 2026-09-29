/** Per-gender fill, border and hover border of a person card. */
export const genderCard: Record<'male' | 'female' | 'unknown', string> = {
    male: 'bg-gender-male-surface border-gender-male-border hover:border-gender-male',
    female: 'bg-gender-female-surface border-gender-female-border hover:border-gender-female',
    unknown: 'bg-card border-tree-line hover:border-foreground/40',
};
