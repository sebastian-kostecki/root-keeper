/**
 * Geometry shared by person cards and placeholder cards (variant C): rounded,
 * centred column content, side padding that leaves room for the eye button.
 */
export const cardSurface =
    'flex h-full min-h-16 flex-col items-center justify-center rounded-xl border px-7 py-2.5 text-center';

/** Per-gender fill, border and hover border of a person card. */
export const genderCard: Record<'male' | 'female' | 'unknown', string> = {
    male: 'bg-gender-male-surface border-gender-male-border hover:border-gender-male',
    female: 'bg-gender-female-surface border-gender-female-border hover:border-gender-female',
    unknown: 'bg-card border-tree-line hover:border-foreground/40',
};

/**
 * Keyboard focus for a card that acts as a link or button.
 *
 * Hover never touches a person card's fill; it only darkens the border (see
 * `genderCard`). Focus is a detached, offset halo, distinct from the focus
 * person's attached border.
 */
export const cardInteractive =
    'focus-visible:ring-ring focus-visible:ring-offset-background focus-visible:ring-[3px] focus-visible:ring-offset-2 focus-visible:outline-none';

/** The focus person: a thick primary border and a light shadow. */
export const cardFocus = 'border-2 border-primary shadow-sm';

/** A placeholder for a person the tree does not know, with no action. */
export const placeholderStatic =
    'bg-muted text-muted-foreground border-transparent';

/** A placeholder that opens a dialog to add the missing person. */
export const placeholderAction = [
    'border-tree-line text-muted-foreground border-dashed bg-transparent',
    'hover:bg-accent hover:text-foreground hover:border-solid',
    cardInteractive,
].join(' ');
