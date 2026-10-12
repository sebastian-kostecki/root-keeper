/**
 * Selected state of an item in a list (the card of the open record). The element sets
 * `data-state="selected"` and `aria-current`; its focus ring comes from `focusRing`.
 * Keyboard focus wins over the selection ring: the selection ring shows only without
 * `focus-visible`. Without focus the look is the same as before the selection styles.
 */
export const selectedListItem =
    'data-[state=selected]:border-primary data-[state=selected]:bg-accent data-[state=selected]:not-focus-visible:ring-2 data-[state=selected]:not-focus-visible:ring-primary';
