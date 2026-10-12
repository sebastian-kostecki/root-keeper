/**
 * Focus ring for interactive elements outside `components/ui/*` (row links, sort
 * buttons, raw anchors). It matches the ring of the shadcn `Button`. Components in
 * `components/ui/*` have their own rings; do not add this constant to them.
 * Place-specific parts (e.g. `focus-visible:ring-inset`) are added with `cn()`.
 */
export const focusRing =
    'outline-none focus-visible:ring-ring focus-visible:ring-3';

/**
 * Focus ring with a gap between the ring and the element, for cards in a list that
 * can be selected (`selectedListItem`). Without the gap the focus ring sits against
 * the selection ring of the neighbouring card and the two read as one.
 */
export const focusRingOffset =
    'outline-none focus-visible:ring-3 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background';
