# Family Sheet View (`tree/show`) — Visual Pass Implementation Plan

## Overview

Fix the five charges from `audit.md` on the family sheet view: the design-system contract first (focus-ring token, one page gutter, a page-level heading), then the sheet's geometry (children hang from the couple), then interaction states (selected union, hover, keyboard focus), then mobile. The last phase is a screenshot gate against `screenshots/before/`. The job is one view plus global tokens and layout, not an app rebrand.

## Current State Analysis

The view already reads its colours from tokens. `resources/css/app.css` holds shadcn neutral values in `:root` / `.dark` and publishes them through `@theme`, plus the tree's own `--gender-*` tokens. Components come from `resources/js/components/ui/*`. The debt is in layout architecture and in two state tokens (audit, "Design-system contract as found"):

- **C1 — no page gutter.** `app-sidebar-layout.tsx:14-16` renders `{children}` straight into `SidebarInset`, so every domain page (`tree/show`, `persons/index`, `scans/{index,create,show}`) is flush against the panel edge. Settings pages pad themselves in `layouts/settings/layout.tsx:35` (`px-4 py-6`) and render inside the same `AppLayout` (`app.tsx:18`).
- **C2 — children not attached to the couple.** `family-sheet.tsx:288-296` drops the descent line at `left-1/2`, but `ChildrenRows` (`:124-161`) starts the children and their bracket at column 1 (`inset = 12.5`). The bracket (12.5–37.5%) and the line (50%) never meet. There is no line between the couple either.
- **C3 — selected union and hover invisible in light mode.** `union-switch.tsx` relies on `ui/toggle.tsx:8` `data-[state=on]:bg-accent`. The cards use `hover:bg-accent/50` (`sheet-person-card.tsx:72`, `unknown-person-card.tsx:27`). In light mode `accent`, `muted` and `secondary` are all `oklch(0.97)` on a white card, so no background role alone makes hover visible.
- **C4 — focus ring too faint.** `--ring` is `oklch(0.87)` light (~1.5:1 on white) and `oklch(0.439)` dark (~2.5:1). The eye button is a ghost `Button` with no border, so it gets only `ring-ring/50`.
- **C5 — desktop grid shipped to mobile.** `family-sheet.tsx:193-194` puts `overflow-x-auto` around `min-w-[72rem] grid-cols-8`. At 390px the user sees the left third of the sheet with no hint that it continues. The union switch wraps to 3 rows above it.
- **D2 — title gap.** `Heading` (`components/heading.tsx:33`) is an `h2` with `mb-8` inside the flex title row, which leaves ~56px of dead space. The page has no `h1`.
- **D3 — duplicated card classes.** The card base plus hover/focus classes are copied in `sheet-person-card.tsx:55,72` and `unknown-person-card.tsx:16,27`.

The repo has no screenshot test (no Playwright, no Pest browser plugin). The visual gate is the out-of-repo script `context/changes/tree-family-sheet-ui/screenshots/shots.mjs` over the `TreeViewDemoSeeder` kitchen sink.

## Desired End State

- Every page in the sidebar layout (tree, persons, scans, settings) sits inside one content container whose inline padding lines up with the breadcrumb bar. Settings is not padded twice.
- The tree title is an `h1` with no dead gap before the union switch.
- On the sheet, a horizontal couple line joins focus and partner, and the descent line drops from its midpoint onto the centre of the children bracket. A single child sits directly under the couple axis. „Dodaj dziecko” sits centred under the children.
- In light mode the selected union is clearly marked, and hover on any person or placeholder card is visible. Keyboard focus is a ≥3:1 ring that reads differently from the focus person's static ring, on cards and on eye buttons alike, in both themes.
- At 390px the sheet opens scrolled so the focus person is centred, an edge fade shows where more sheet continues, and the union switch is one horizontally scrollable row with the selected union in view.
- At 1440px the whole sheet fits inside the new gutter with no horizontal scroll.
- `screenshots/after/` holds the same shots as `before/`, plus the new pages and states, and `audit.md` has a gate table mapping each charge to its before/after evidence.

### Key Discoveries:

- `app.tsx:13-23`: settings pages use `[AppLayout, SettingsLayout]`, so a gutter in the sidebar layout reaches them too. Their own padding must go at the same time.
- `app-sidebar-header.tsx:11`: the header uses `px-6 md:px-4`. That is the inline padding to match.
- Couple cards are `col-span-4 mx-auto w-full max-w-72` (`family-sheet.tsx:263`), so their centres sit at 25% and 75% of the grid.
- Centring rule for a children row of `n` (1–4) cards in an 8-column grid of `col-span-2` cells: start at column `5 − n`. The bracket then runs from `(5 − n)·12.5%` to `(3 + n)·12.5%`, symmetric around 50%. The gap-x offsets are symmetric too, so the midpoint stays exactly 50%.
- `ui/button.tsx:52` merges `className` through `cn` (twMerge), so `focus-visible:ring-ring` passed to the eye `Button` replaces `ring-ring/50` without editing `ui/*`.
- Tailwind is 4.3.3 (`node_modules/tailwindcss`), so mask utilities (`mask-l-from-*`, `mask-r-from-*`) exist for the edge fade.
- `TreeViewDemoSeeder.php` has at most 2 children per union. The ">4 children → second row" state is not in the kitchen sink.
- `shots.mjs` hard-codes absolute person IDs (102 = Jan … 125 = Kuba). They are only valid for the current DB state, and any reseed shifts them.

## What We're NOT Doing

- No `shadcn init` and no second palette. No new colour tokens: hover and selected use existing roles (decided in planning).
- No edits to `resources/js/components/ui/*`. Selected-union and eye-button overrides go through `className` at the call site.
- `--sidebar-ring` stays unchanged: the sidebar is not in this view.
- The default `Heading` variant stays unchanged. persons/scans keep `h2 + mb-8` until they opt into `variant="page"`.
- No stacked mobile layout. One PL-SOFT grid at every width.
- Deferred audit items stay deferred: D1 (Inertia error page for 404), D4 (placeholder affordance), D5 (grandparent card density).
- No screenshot test is added to the repo, and no baselines exist to update. The gate stays the out-of-repo `shots.mjs`.
- No Pest tests: the change is styling and layout only, plus dev seed data.

## Implementation Approach

Fix the contract before the pixels:
- Phase 1 changes only shared things (one token, one layout container, one additive heading variant). Every later screenshot already carries them.
- Phase 2 reworks the geometry and extends the kitchen sink with the one state it lacks.
- Phase 3 moves the card interaction classes into one module (D3), then fixes hover, focus and the selected state there, so C3/C4 are fixed once.
- Phase 4 adds a single hook that centres an element in its scroll container, used for both the sheet and the union switch.
- Phase 5 re-shoots and judges each charge.

## Critical Implementation Details

**Tailwind class detection.** The children-row start column and any other computed grid position must come from a lookup of **literal** class names (e.g. `{1: 'col-start-4', 2: 'col-start-3', 3: 'col-start-2', 4: 'col-start-1'}`), never from template strings like `` `col-start-${x}` ``. Tailwind only generates classes it can see in source.

**Hover must not recolour the gender strip.** Tailwind emits `hover:` utilities after base utilities, so `hover:border-foreground/20` would override `border-l-gender-*` on the person card. The hover border must target the other sides only (top/right/bottom). The invariant is that the left strip keeps its gender colour in every state.

**Horizontal centring must not scroll the page.** Set the scroll container's `scrollLeft` directly (element offset + half its width − half the container's width). Do not use `Element.scrollIntoView`, which also scrolls vertical ancestors and would make the page jump on load and on every union switch (the union switch visits with `preserveScroll: true`).

**Seeder IDs.** Phase 2 reseeds, which shifts every person ID. Phase 5's `shots.mjs` must derive IDs relative to the proband's ID (read from the `/tree` page, e.g. the breadcrumb link to `/tree/{id}`) instead of hard-coded numbers. New seed data is appended after the existing people, so the relative offsets of the existing shots stay valid.

## Phase 1: Contract — ring token, layout gutter, page heading

### Overview

Change only shared, global things, so that every later phase and screenshot already has them: the focus-ring value, one page container for the sidebar layout, and a page-level heading variant.

### Changes Required:

#### 1. Focus ring token (C4, token half)

**File**: `resources/css/app.css`

**Intent**: Raise `--ring` to at least 3:1 against `--background` in both themes, so every `ring-ring` consumer becomes a visible focus indicator. Leave a source comment next to it: this is a value taken from outside.

**Contract**: `:root --ring: oklch(0.556 0 0)` (≈4.7:1 on `oklch(1 0 0)`; Tailwind v4 neutral-500, same value as `--muted-foreground`). `.dark --ring: oklch(0.556 0 0)` (≈4:1 on `oklch(0.145 0 0)`; the shadcn neutral dark preset, ui.shadcn.com/docs/theming). The comment names the sources and the WCAG 2.2 SC 1.4.11 (non-text contrast, 3:1) target. `--sidebar-ring` is untouched.

#### 2. One page container for the sidebar layout (C1)

**File**: `resources/js/layouts/app/app-sidebar-layout.tsx`

**Intent**: Wrap `{children}` in a single content container, so pages stop sitting flush against the panel and each page does not need its own padding.

**Contract**: The container's inline padding matches the header (`px-6 md:px-4`) and it adds vertical padding `py-6` (the value settings already uses). It keeps `min-w-0` so inner horizontal scroll areas (the sheet) scroll inside it instead of widening the page.

**File**: `resources/js/layouts/settings/layout.tsx`

**Intent**: Remove the layout's own `px-4 py-6`, because the sidebar layout now provides the gutter.

**Contract**: The root `div` at `:35` loses its padding classes. Nothing else in the settings layout changes.

#### 3. Page heading variant (D2)

**File**: `resources/js/components/heading.tsx`

**Intent**: Add a third variant `page` for a view's main title: an `h1` with the default variant's type scale and no bottom margin. Spacing then comes from the page's `space-y-*`, and the page gets its `h1`.

**Contract**: `variant?: 'default' | 'small' | 'page'`. `default` and `small` render exactly as today.

**File**: `resources/js/pages/tree/show.tsx`

**Intent**: Use `Heading variant="page"` for both the empty-state title („Drzewo”) and the sheet title. Align the title row so the badge and buttons sit centred on the title line.

**Contract**: Props and the rest of the markup are unchanged.

### Success Criteria:

#### Automated Verification:

- Format + lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Build passes: `vendor/bin/sail npm run build`

#### Manual Verification:

- `/tree`, `/persons`, `/scans` and `/settings/profile` at 1440px and 390px: content has a gutter aligned with the breadcrumb bar, and settings is not double-padded
- `/tree` title is an `h1` with no dead gap before the union switch (DevTools)
- Tabbing through `/tree` in light and dark mode shows a clearly visible grey ring on cards (full check in Phase 3)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Sheet geometry — children hang from the couple (C2)

### Overview

Rework the couple and children rows of `family-sheet.tsx` so that the lines say "children of Jan and Anna". Make the grid fit inside the new gutter at 1440px, and add the one missing state to the kitchen sink.

### Changes Required:

#### 1. Couple line and descent

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Draw a horizontal couple line between the focus card and the partner card whenever the partner slot shows a person or an unknown placeholder, i.e. `sheet.partner.kind !== 'empty'` (not for the „Dodaj małżonka” slot). When there are children, the descent line starts at the couple line's midpoint (50%) and runs down through the gap row to the children bracket. The „Połącz jako małżeństwo” button stays centred on the descent line with a background, so it covers the line.

**Contract**: The couple row becomes a positioned wrapper spanning 8 columns. The line runs from 25% to 75% at the cards' vertical centre and sits behind the cards (the cards' `bg-card` hides its ends). The descent stays `bg-border w-px` at `left-1/2`. Lines stay `aria-hidden`. Whether the button renders is decided as today.

#### 2. Centred children rows and bracket

**File**: `resources/js/components/tree/family-sheet.tsx` (`ChildrenRows`)

**Intent**: Centre every row of up to `CHILDREN_PER_ROW` children under the couple axis, so the bracket's midpoint meets the descent line. A single child sits directly under the line with no bracket.

**Contract**: A row of `n` children starts at column `5 − n` (literal class lookup, see Critical Implementation Details). The bracket's left and right margins are both `(5 − n) · 12.5%`. Rows after the first keep their own bracket, centred the same way (existing behaviour, now symmetric).

#### 3. „Dodaj dziecko” placement

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Move the `AddChildDialog` cell from column 1 to a centred `col-span-2` under the children (or under „Brak dzieci w tym związku.”), so the add action belongs to the couple, not to the focus person.

**Contract**: Same `AddChildDialog` props, only the grid position changes.

#### 4. Grid width fits the gutter

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Lower the one-off `min-w-[72rem]`, which overflows by a few pixels at 1440px once the gutter exists, so the desktop sheet fits without a horizontal scrollbar while grandparent cards stay legible.

**Contract**: `min-w-[68rem]` (≈129px per grandparent column). Above that width the grid stays `w-full`.

#### 5. Kitchen-sink state: more than 4 children

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Add a married couple with 5 children (unrelated to Jan, so the proband's union list and existing shots don't change). The gate can then see a two-row children layout.

**Contract**: Created with the existing `person()` / `marry()` / `parents()` helpers, **after** Kuba and before `SetTreeRootPerson`, so the IDs of the existing people keep their offsets from Jan. Polish names, e.g. the Nowicki family.

### Success Criteria:

#### Automated Verification:

- PHP formatting applied: `vendor/bin/sail bin pint --dirty --format agent`
- Seeder runs: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder --no-interaction`
- Format + lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Build passes: `vendor/bin/sail npm run build`

#### Manual Verification:

- Proband sheet (Jan + Anna, 2 children): the couple line joins both cards, the descent meets the bracket's midpoint, and the children are centred under the couple
- Co-parent (Jan + Marta, 1 child): Oliwia sits directly under the couple axis, and the line passes behind the „Połącz jako małżeństwo” button into her card
- Unknown partner and a union with no children: the couple line is shown and there is no descent; „Brak dzieci w tym związku.” and „Dodaj dziecko” are centred
- Person without unions (Kuba): no couple line next to „Dodaj małżonka”
- 5-children couple: two centred rows, each with a symmetric bracket
- 1440px: no horizontal scrollbar on the sheet, in light and dark

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Interaction states — selected, hover, focus (C3, C4, D3)

### Overview

Move the card surface and interaction classes into one module, then fix hover and keyboard focus there once. Mark the selected union with a role that already has contrast.

### Changes Required:

#### 1. Shared card classes (D3)

**File**: `resources/js/components/tree/card-styles.ts` (new)

**Intent**: Keep the card surface and the interactive-card classes (hover + focus-visible) in one place, so person cards and placeholder cards cannot drift apart.

**Contract**: Exports class strings (or a small `cva`, matching the `ui/*` idiom), e.g. a surface part (rounded, border, min height, padding) and an interactive part. The interactive part includes:
- hover: `bg-accent` (full strength, not `/50`) plus a `foreground/20` border on the top, right and bottom sides only
- focus-visible: `ring-ring`, 3px, with `ring-offset-2 ring-offset-background`, and no outline

The offset makes keyboard focus a detached halo, distinct from the focus person's attached `ring-primary ring-2`.

#### 2. Adopt in cards

**File**: `resources/js/components/tree/sheet-person-card.tsx`

**Intent**: Build the card from the shared classes and keep the gender strip and the focus person's static ring. Give the eye `Button` a full-strength focus ring through `className`, because the ghost button has no border and `ring-ring/50` stays under 3:1.

**Contract**: The left border keeps `border-l-gender-*` in every state. The eye button className adds `focus-visible:ring-ring` (twMerge replaces the `/50` variant). The rendered structure and links are unchanged.

**File**: `resources/js/components/tree/unknown-person-card.tsx`

**Intent**: Build the dashed placeholder from the same shared classes. The interactive (button) form gets the same hover and focus; on hover it also darkens its left border, since it has no gender strip.

**Contract**: Props and the static/button split are unchanged. The dashed border stays.

#### 3. Selected union (C3)

**File**: `resources/js/components/tree/union-switch.tsx`

**Intent**: Mark the selected union with a role that has contrast in both themes, so the user can tell which union the sheet shows. Do this through the item's `className`; `ui/toggle.tsx` is not edited.

**Contract**: `ToggleGroupItem` className adds `data-[state=on]:border-primary data-[state=on]:ring-1 data-[state=on]:ring-primary` (keeping `bg-accent`). Unselected items are unchanged.

### Success Criteria:

#### Automated Verification:

- Format + lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Build passes: `vendor/bin/sail npm run build`

#### Manual Verification:

- Light mode: the selected union is obvious among the chips; switching unions moves the mark
- Light and dark: hover on a person card and on a „Nieznany ojciec +” placeholder is visible, and the gender strip keeps its colour on hover
- Keyboard: tabbing through the sheet shows a detached ≥3:1 ring on cards, placeholders and eye buttons, visually distinct from the focus person's static ring
- Dark mode: selected, hover and focus remain visible and not glaring

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Mobile — scroll to focus, edge fade, one-row union switch (C5)

### Overview

Keep the single PL-SOFT grid, but open it on phones where the user needs it: focus person centred, a visible cue that the sheet continues, and a union switch that no longer pushes the sheet below the fold.

### Changes Required:

#### 1. Centre-in-scroll hook

**File**: `resources/js/hooks/use-centre-in-scroll.ts` (new)

**Intent**: One hook that horizontally centres a target element inside its scroll container when a key changes. It also reports whether hidden content exists to the left or right, for the fade.

**Contract**:
- Takes a container ref, a target ref and a key (e.g. focus id + selected union), and returns `{ canScrollLeft, canScrollRight }`.
- On layout and whenever the key changes, it sets the container's `scrollLeft` directly (see Critical Implementation Details); it does nothing when the container doesn't overflow.
- It updates the flags on scroll and on resize.

Named like the sibling hooks in `resources/js/hooks/`.

#### 2. Sheet uses it, with an edge fade

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Attach the hook to the sheet's scroll container, with the focus card as the target. Apply a Tailwind mask fade only on the edge(s) that hide content, so a mobile user sees that the sheet continues sideways.

**Contract**:
- The key is focus id + `selected_union`.
- The fade uses the Tailwind v4 mask utilities (`mask-l-from-*` / `mask-r-from-*`), driven by the hook's flags.
- There is no fade and no forced scroll when the grid fits (desktop).

#### 3. One-row union switch below `md`

**File**: `resources/js/components/tree/union-switch.tsx`

**Intent**: Below `md`, lay the union chips and the „Dodaj małżonka” action in one horizontally scrollable row, with the selected chip centred through the same hook, so the sheet moves up above the fold.

**Contract**: `max-md:` variants turn wrapping off and horizontal overflow on; chips don't shrink. At `md` and up the layout is unchanged. The hook's key is `selected`.

### Success Criteria:

#### Automated Verification:

- Format + lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Build passes: `vendor/bin/sail npm run build`

#### Manual Verification:

- 390px `/tree`: first viewport shows the focus person centred, a right/left edge fade, and the union switch as one row with the selected union visible
- 390px: switching union and re-centring on another person re-centres the sheet without the page jumping vertically
- 1440px: no fade, no forced scroll, union switch wraps as before

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Screenshot gate

### Overview

Re-shoot the kitchen sink and judge each charge against `before/`. Visual findings are not "cosmetic". A charge that doesn't hold in the after-shots goes back to its phase.

### Changes Required:

#### 1. Extend the shot script

**File**: `context/changes/tree-family-sheet-ui/screenshots/shots.mjs`

**Intent**: Make the script survive the reseed, and cover what this change touched beyond the tree view.

**Contract**:
- IDs are derived from the proband's ID read on `/tree`, not hard-coded.
- The script produces every `before/` filename (1:1 pairs) plus:
  - `/persons`, `/scans` and `/settings/profile` at 1440px and 390px
  - dark hover-card and dark focus-card
  - the 5-children couple at 1440px
  - 390px union switch viewport
- The out-dir argument selects `after/`.

#### 2. Gate record

**File**: `context/changes/tree-family-sheet-ui/audit.md`

**Intent**: Add a "Gate" section with one row per charge (C1–C5, D2, D3): the before file, the after file, and a verdict (holds / regressed + note). Also add a line listing the states covered (default, hover, focus, empty, >4 children, mobile) and the states still not captured (the disabled `SetRootPersonButton` while processing, and the loading dialog candidates).

**Contract**: Append only; existing audit content is unchanged.

### Success Criteria:

#### Automated Verification:

- Script runs and writes every expected file: `node context/changes/tree-family-sheet-ui/screenshots/shots.mjs context/changes/tree-family-sheet-ui/screenshots/after`
- Every `before/*.png` has a same-named `after/*.png`: `comm -23 <(ls context/changes/tree-family-sheet-ui/screenshots/before) <(ls context/changes/tree-family-sheet-ui/screenshots/after)` prints nothing

#### Manual Verification:

- Each gate-table verdict in `audit.md` is confirmed by looking at the before/after pair
- No regression on `/persons`, `/scans`, `/settings/*` (gutter present, nothing double-padded, nothing clipped)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None. The change is styling, layout and dev seed data; there is no behaviour or backend change (Test Enforcement: layout-only changes need no tests).

### Integration Tests:

- Existing `tests/Feature/TreeTest.php` is unaffected (props unchanged). Run it once at the end as a sanity check: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`.

### Manual Testing Steps:

1. Reseed with `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder --no-interaction`, then log in as `tree-demo@example.com` / `password`.
2. Walk `/tree`, each union, the co-parent, Kuba, Zuzanna, and the 5-children couple at 1440px light and dark.
3. Tab through the sheet with the keyboard, then hover cards and placeholders.
4. Repeat `/tree` at 390px: check the initial scroll position, the fade and the union row.
5. Open `/persons`, `/scans` and `/settings/profile` to confirm the gutter.

## Performance Considerations

The hook listens to `scroll` and `resize` on one or two elements. It only sets two booleans, and only when they change, so there are no measurable costs.

## Migration Notes

None. There is no schema or data change. The seeder is dev-only and idempotent: it deletes and recreates the demo users.

## References

- Audit / charge list: `context/changes/tree-family-sheet-ui/audit.md`
- Before-shots: `context/changes/tree-family-sheet-ui/screenshots/before/`
- Motif: PL-SOFT „Formularz rodzinny” + Ancestris gender frames — `context/archive/2026-09-28-graphical-tree-view/research.md` §1, §3
- Kitchen sink: `database/seeders/TreeViewDemoSeeder.php`
- Settled layout pattern for settings: `resources/js/layouts/settings/layout.tsx:35`
- Existing bracket connector: `resources/js/components/tree/family-sheet.tsx:72-104`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — ring token, layout gutter, page heading

#### Automated

- [x] 1.1 Format + lint pass: `vendor/bin/sail npm run check` — 685383d
- [x] 1.2 Types pass: `vendor/bin/sail npm run types:check` — 685383d
- [x] 1.3 Build passes: `vendor/bin/sail npm run build` — 685383d

#### Manual

- [x] 1.4 `/tree`, `/persons`, `/scans` and `/settings/profile` at 1440px and 390px: content has a gutter aligned with the breadcrumb bar, and settings is not double-padded — 685383d
- [x] 1.5 `/tree` title is an `h1` with no dead gap before the union switch (DevTools) — 685383d
- [x] 1.6 Tabbing through `/tree` in light and dark mode shows a clearly visible grey ring on cards (full check in Phase 3) — 685383d

### Phase 2: Sheet geometry — children hang from the couple (C2)

#### Automated

- [x] 2.1 PHP formatting applied: `vendor/bin/sail bin pint --dirty --format agent` — e1bf02b
- [x] 2.2 Seeder runs: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder --no-interaction` — e1bf02b
- [x] 2.3 Format + lint pass: `vendor/bin/sail npm run check` — e1bf02b
- [x] 2.4 Types pass: `vendor/bin/sail npm run types:check` — e1bf02b
- [x] 2.5 Build passes: `vendor/bin/sail npm run build` — e1bf02b

#### Manual

- [x] 2.6 Proband sheet (Jan + Anna, 2 children): the couple line joins both cards, the descent meets the bracket's midpoint, and the children are centred under the couple — e1bf02b
- [x] 2.7 Co-parent (Jan + Marta, 1 child): Oliwia sits directly under the couple axis, and the line passes behind the „Połącz jako małżeństwo” button into her card — e1bf02b
- [x] 2.8 Unknown partner and a union with no children: the couple line is shown and there is no descent; „Brak dzieci w tym związku.” and „Dodaj dziecko” are centred — e1bf02b
- [x] 2.9 Person without unions (Kuba): no couple line next to „Dodaj małżonka” — e1bf02b
- [x] 2.10 5-children couple: two centred rows, each with a symmetric bracket — e1bf02b
- [x] 2.11 1440px: no horizontal scrollbar on the sheet, in light and dark — e1bf02b

### Phase 3: Interaction states — selected, hover, focus (C3, C4, D3)

#### Automated

- [x] 3.1 Format + lint pass: `vendor/bin/sail npm run check` — 03db005
- [x] 3.2 Types pass: `vendor/bin/sail npm run types:check` — 03db005
- [x] 3.3 Build passes: `vendor/bin/sail npm run build` — 03db005

#### Manual

- [x] 3.4 Light mode: the selected union is obvious among the chips; switching unions moves the mark — 03db005
- [x] 3.5 Light and dark: hover on a person card and on a „Nieznany ojciec +” placeholder is visible, and the gender strip keeps its colour on hover — 03db005
- [x] 3.6 Keyboard: tabbing through the sheet shows a detached ≥3:1 ring on cards, placeholders and eye buttons, visually distinct from the focus person's static ring — 03db005
- [x] 3.7 Dark mode: selected, hover and focus remain visible and not glaring — 03db005

### Phase 4: Mobile — scroll to focus, edge fade, one-row union switch (C5)

#### Automated

- [x] 4.1 Format + lint pass: `vendor/bin/sail npm run check` — 5e8a3a1
- [x] 4.2 Types pass: `vendor/bin/sail npm run types:check` — 5e8a3a1
- [x] 4.3 Build passes: `vendor/bin/sail npm run build` — 5e8a3a1

#### Manual

- [x] 4.4 390px `/tree`: first viewport shows the focus person centred, a right/left edge fade, and the union switch as one row with the selected union visible — 5e8a3a1
- [x] 4.5 390px: switching union and re-centring on another person re-centres the sheet without the page jumping vertically — 5e8a3a1
- [x] 4.6 1440px: no fade, no forced scroll, union switch wraps as before — 5e8a3a1

### Phase 5: Screenshot gate

#### Automated

- [x] 5.1 Script runs and writes every expected file: `node context/changes/tree-family-sheet-ui/screenshots/shots.mjs context/changes/tree-family-sheet-ui/screenshots/after` — 9ab054c
- [x] 5.2 Every `before/*.png` has a same-named `after/*.png`: `comm -23 <(ls context/changes/tree-family-sheet-ui/screenshots/before) <(ls context/changes/tree-family-sheet-ui/screenshots/after)` prints nothing — 9ab054c

#### Manual

- [x] 5.3 Each gate-table verdict in `audit.md` is confirmed by looking at the before/after pair — 9ab054c
- [x] 5.4 No regression on `/persons`, `/scans`, `/settings/*` (gutter present, nothing double-padded, nothing clipped) — 9ab054c
