/** Geometry shared by person cards and placeholder cards. */
export const cardSurface = 'h-full min-h-16 rounded-md border p-2';

/**
 * Hover and keyboard focus for a card that acts as a link or button.
 *
 * Hover colours only the top, right and bottom borders, so a card's left
 * border (the gender strip) keeps its colour in every state. Focus is a
 * detached, offset halo, distinct from the focus person's attached
 * `ring-primary ring-2`.
 */
export const cardInteractive = [
    'hover:bg-accent hover:border-t-foreground/20 hover:border-r-foreground/20 hover:border-b-foreground/20',
    'focus-visible:ring-ring focus-visible:ring-offset-background focus-visible:ring-[3px] focus-visible:ring-offset-2 focus-visible:outline-none',
].join(' ');
