/**
 * Focus ring for interactive elements outside `components/ui/*` (row links, sort
 * buttons, raw anchors). It matches the ring of the shadcn `Button`. Components in
 * `components/ui/*` have their own rings; do not add this constant to them.
 * Place-specific parts (e.g. `focus-visible:ring-inset`) are added with `cn()`.
 */
export const focusRing =
    'outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px]';
