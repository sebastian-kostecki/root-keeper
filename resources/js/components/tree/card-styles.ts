import { focusRingOffset } from '@/lib/focus-ring';

/**
 * Geometry of placeholder cards (variant C): rounded, centred column content,
 * side padding that leaves room for the eye button.
 */
export const cardSurface =
    'flex h-full min-h-24 flex-col items-center justify-center rounded-xl border px-7 py-3.5 text-center';

/**
 * Geometry of person cards: a row with a round avatar on the left, clipped to
 * the rounded corners. `@container` makes the card the size container for the
 * avatar's size (see `CardAvatar`).
 */
export const personCardSurface =
    '@container flex h-full min-h-24 flex-row items-stretch overflow-hidden rounded-xl border text-left';

/**
 * Geometry of the compact person card (one column, no avatar): its height
 * comes from the grid cell, not from a minimum, so it has no `min-h-*`.
 */
export const compactCardSurface =
    'flex h-full flex-col items-start justify-center overflow-hidden rounded-xl border px-2 py-1.5 text-left';

/**
 * Geometry of a placeholder in a one-column cell: tighter padding, no room
 * reserved for the eye button, height from the grid cell.
 */
export const compactPlaceholderSurface =
    'flex h-full flex-col items-center justify-center rounded-xl border px-1.5 py-1.5 text-center text-sm';

/**
 * Keyboard focus for a card that acts as a link or button.
 *
 * Hover never touches a person card's fill; it only darkens the border (see
 * `genderCard` in `lib/gender-styles`). Focus is a detached, offset halo, distinct from the focus
 * person's attached border.
 */
export const cardInteractive = focusRingOffset;

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

/** The trigger whose add panel is open: filled with the accent and a solid border. */
export const triggerActive = 'bg-accent text-foreground border-solid';
