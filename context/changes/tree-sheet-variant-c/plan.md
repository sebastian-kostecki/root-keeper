# Family sheet in variant C: gender-coloured cards and unions on the node — Implementation Plan

## Overview

Bring the desktop family sheet (`resources/js/pages/tree/show.tsx` + `resources/js/components/tree/*`) to variant C of the Design canvas (https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F, boards „C — karty w kolorze płci”, „C — stany”). Gender becomes the card's fill, and the tree lines become visible. The union choice moves from a chip row above the sheet into a dropdown on the couple node. The generation headings become screen-reader only, and „Dodaj dziecko” becomes the next cell of the children row. The work follows the charges C1–C4 of [`audit.md`](audit.md), with token values from [`tokens.md`](tokens.md).

## Current State Analysis

- **Tokens:** `resources/css/app.css:105-107` / `:144-146` define only the strip colours `--gender-male/female/unknown`, published at `:63-65`. There are no gender surfaces or borders, no line role and no meta-text role. Connectors use `--border` (`family-sheet.tsx:98,102,153,288,327`), about 1.2:1 on white.
- **Cards:** `sheet-person-card.tsx:17-21,57-58` draws a 4px `border-l-gender-*` strip. The Mars/Venus icons carry the only accessible gender (`:30,:37`). Text is left-aligned with `pr-9`, and the focus person has `ring-primary ring-2` (`:64`). `card-styles.ts:2,12-15` holds the geometry and the hover (`bg-accent`), which would erase a tinted fill.
- **Placeholders:** `unknown-person-card.tsx` has a static unknown and an actionable placeholder that differ only by a `+`. The labels are nouns („Nieznany ojciec”) even when the card is a button.
- **Unions:** `show.tsx:113-128` renders `UnionSwitch` (a `ToggleGroup`, `union-switch.tsx`) plus `AddSpouseDialog variant="switcher"` above the sheet. The chip text is the server-built `label` („Anna Wiśniewska · ślub 2001”, or „· ślub ?” for an undated marriage) and „Dzieci: N” (`union-switch.tsx:66-68`).
- **Payload:** `app/Actions/Trees/BuildFamilySheet.php:104-123` builds each union as `key, partner_id, label, is_marriage, marriage_year, children_count`. `label` is read only by `union-switch.tsx:66`. Pest asserts it at `tests/Feature/TreeTest.php:182,185,187,234`.
- **Dialog ownership:** `pick-or-create-person-dialog.tsx:67,121-122` owns `open` and needs a `DialogTrigger`. `handleOpenChange` (`:76-85`) resets the filter, view and error and calls `load()` only when *its* trigger opens it.
- **Layout:** `family-sheet.tsx:73-78` `RowLabel` is used 4× (`:220,257,284,338`). The „Dzieci” heading cuts the descent line. `AddChildDialog` sits in its own row (`:346-353`, fixed `col-start-4`). Children are 2 columns wide on an 8-column grid, centred via `CHILDREN_ROW_START` (`:22-27`), 4 per row.
- **Gate tooling:** there's no screenshot test in the repo. The last change used `context/archive/2026-09-28-tree-family-sheet-ui/screenshots/shots.mjs` (Playwright from the npx cache) on `database/seeders/TreeViewDemoSeeder.php`. Its `after/` set is this change's "before" set. The seeder already has Jan with 4 unions (dated, undated, co-parent, unknown), Kacper with 0 unions, Kuba with unknown gender and the Nowicki couple with 5 children.

## Desired End State

At 1440px, light and dark:

- Person cards are filled with the gender surface and outlined with the gender border. Text is centred, the corners are `rounded-xl`, and there's no gender icon: gender is `sr-only` text. Lifespans use `--tree-meta`. Hovering a card turns its border the gender colour, keyboard focus shows the detached `--ring` halo, and the focus person has `border-2 border-primary` plus a light shadow. Unknown gender gets the `bg-card` fill and a `--tree-line` border.
- The static unknown („Nieznany ojciec”) is a borderless `bg-muted` block. Every actionable placeholder has a dashed `--tree-line` border and a verb: „Dodaj ojca / Dodaj matkę / Dodaj rodzica / Dodaj dziecko / Dodaj małżonka”.
- All connectors, the couple line and the children bracket use `--tree-line`.
- There's no union chip row above the sheet. A union node sits on the couple line between the focus person and the partner:
  - With several unions, the trigger reads „N z M · <status>”.
  - With one union, the „N z M” part is dropped.
  - If both parts would be empty, the trigger reads „Związki”.
  - With zero unions there is no node.
- The node's menu has one radio item per union („<partner>” over „<status> · N dzieci/dziecko”), a separator and „Dodaj małżonka”, which opens the existing spouse dialog.
- Generation headings exist only as `sr-only`, so no label interrupts a line.
- The children row carries „Dodaj dziecko”:
  - After 1–2 children in the last row, it's the next cell in the same row.
  - With 3 or 4 children in the last row, it gets its own centred row below.
  - With 0 children, it's centred under the node below „Brak dzieci w tym związku.”
  - The bracket spans real children only and stays centred on the couple axis.
- The „Połącz jako małżeństwo” chip for an unmarried centre couple stays on the descent line below the node.
- 390px renders without regressions (the sheet still scrolls sideways; the vertical layout is a later change).

Verification: Pest (`TreeTest`), `npm run check`, `types:check`, `build`, and the screenshot gate in Phase 5 judged against `context/archive/2026-09-28-tree-family-sheet-ui/screenshots/after/`.

### Key Discoveries:

- `components/ui/dropdown-menu.tsx` already exports `DropdownMenuRadioGroup` / `DropdownMenuRadioItem` / `DropdownMenuSeparator` / `DropdownMenuLabel`, so no new shadcn component is needed.
- `lib/plural.ts` `pluralPhrase(count, one, few, many)` gives „1 dziecko / 2 dzieci / 5 dzieci”. Polish uses „dzieci” for both *few* and *many*, but go through the helper anyway.
- `ParentSlotDialog` already has the verbs in `ROLE_TITLE` (`parent-slot-dialog.tsx:19-23`). The visible placeholder label reuses them instead of the server's noun label, which stays as the static-unknown text.
- `useCentreInScroll` is used by both `family-sheet.tsx:206` and `union-switch.tsx:44`. It stays on the sheet and leaves with `UnionSwitch`.
- The canvas state „Jeden związek — sama etykieta” (a plain label) is superseded: with one union the node is still a dropdown, because it's the only way to add a second spouse (audit C3).

## What We're NOT Doing

- Mobile vertical layout (canvas „C — mobile”): a separate change (audit D1). 390px is shot for regressions only.
- The header meta line „ur. 1975 · 4 związki · 5 dzieci” (audit D4), the empty state („Pusty stan”), and the foreign-person 404 (D3).
- A second gender cue beyond colour for sighted users (D2, user decision). `sr-only` text keeps it for assistive tech.
- Moving „Połącz jako małżeństwo” into the union menu (it stays on the descent line).
- Keeping the server `label` field (replaced by `partner_name`).
- A screenshot test in the repo or baseline updates. The gate stays an out-of-repo script.
- Changes to `components/ui/*` (shadcn source stays untouched).

## Implementation Approach

Contract before pixels. Phase 1 lands the tokens and the payload field that everything else reads. Phase 2 changes the look in the one shared place (`card-styles.ts`) and the two card components, so each state is fixed once. Phase 3 replaces the union switcher with the node, the only new interaction. Phase 4 changes the sheet geometry once the node owns the couple line. Phase 5 shoots the audit's states inventory and judges each charge; a charge that fails the shots goes back to its phase.

## Critical Implementation Details

- **Dialog opened from a menu item:** Radix unmounts `DropdownMenuItem` content when the menu closes, so the item can't be a `DialogTrigger`. The item's `onSelect` only sets the dialog's controlled `open = true`. The reset and `load()` in `PickOrCreatePersonDialog` must run on *every* transition to open, not only inside its own `onOpenChange`: key it to the `open` value, e.g. by a shared "opened" handler both paths call. Otherwise the candidates list never loads when opened from the menu. Radix menu → dialog can leave `pointer-events: none` on `body` after the dialog closes. Verify that the page is clickable after closing. If it isn't, open the dialog after the menu's close (`onSelect` without `preventDefault`, open on the next frame) or set `modal={false}` on the menu. Return focus to the node trigger when the dialog closes (`onCloseAutoFocus`), since the dialog has no trigger of its own.
- **Hover must not touch the fill:** the tinted surface carries gender, so `cardInteractive` must drop `hover:bg-accent` for person cards. Only placeholders keep a background hover (canvas `.ph.hov`: `bg-accent`, solid border, `text-foreground`).

## Phase 1: Contract — variant C tokens and `partner_name`

### Overview

Add the six roles from `tokens.md` (C1) and replace the union `label` with `partner_name` (C3a), so later phases read the tokens and the payload they need.

### Changes Required:

#### 1. Tokens

**File**: `resources/css/app.css`

**Intent**: Add the gender surface/border roles and the tree line/meta roles in both themes, and publish them through `@theme` next to the existing `--color-gender-*` block.

**Contract**:
- New CSS variables in `:root` and `.dark`, with the exact values from `tokens.md`: `--gender-male-surface`, `--gender-male-border`, `--gender-female-surface`, `--gender-female-border`, `--tree-line`, `--tree-meta`.
- `@theme` gains `--color-gender-male-surface`, `--color-gender-male-border`, `--color-gender-female-surface`, `--color-gender-female-border`, `--color-tree-line` and `--color-tree-meta`.
- One source comment above the new `:root` group: `context/changes/tree-sheet-variant-c/tokens.md` plus the canvas URL.

#### 2. Union payload

**File**: `app/Actions/Trees/BuildFamilySheet.php`

**Intent**: The menu shows the partner's name on its own line and builds the status client-side, so the server stops baking both into `label`.

**Contract**: The union array drops `label` and gains `partner_name` (`string|null`): `$partner->displayName()` for a person union, `null` for the unknown union. `is_marriage`, `marriage_year` and `children_count` are unchanged. Update the PHPDoc array shape if the method declares one.

**File**: `resources/js/types/tree.ts`

**Intent**: Mirror the payload.

**Contract**: `SheetUnion` swaps `label: string` for `partner_name: string | null`.

**File**: `resources/js/components/tree/union-switch.tsx`

**Intent**: Keep the build green until Phase 3 deletes this file.

**Contract**: Render `partner_name ?? 'Nieznany partner'` where `label` was. It's temporary and needs no styling work.

#### 3. Tests

**File**: `tests/Feature/TreeTest.php`

**Intent**: Keep the union coverage and assert the new field instead of the removed one.

**Contract**:
- The asserts at `:182,185,187` become `partner_name` asserts (dated wife → her display name, co-parent → his/her name, unknown union → `null`) plus the existing `is_marriage` / `marriage_year` asserts. Add `marriage_year` `null` + `is_marriage` `true` for the undated marriage if the test's fixture has one.
- `:224-235` (repeat marriages) asserts `partner_name` and `marriage_year === '1880'`.
- Assert that `label` is absent from a union (`not->toHaveKey('label')`) once.

### Success Criteria:

#### Automated Verification:

- Sheet payload tests pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- Static analysis passes: `vendor/bin/sail composer run types:check`
- Types check: `vendor/bin/sail npm run types:check`
- Format and lint pass: `vendor/bin/sail npm run check`

#### Manual Verification:

- `/tree` still renders. Union chips show partner names (temporary) and the sheet looks unchanged, since the tokens aren't used yet.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Cards, placeholders and lines (C1, C2)

### Overview

Switch every person card and placeholder to the variant C look in the shared styles, and move all connectors to `--tree-line`.

### Changes Required:

#### 1. Shared card styles

**File**: `resources/js/components/tree/card-styles.ts`

**Intent**: This is the one place the variant C card look lives: geometry, per-gender surfaces and hover, focus halo and the focus-person border.

**Contract**:
- `cardSurface`: `rounded-xl`, `min-h-16`, centred column content, horizontal padding that leaves room for the eye button on both sides (canvas `padding: 10px 28px`).
- A per-gender map (e.g. `genderCard: Record<'male' | 'female' | 'unknown', string>`) with fill + border + hover border: male/female → `bg-gender-*-surface border-gender-*-border hover:border-gender-*`; unknown → `bg-card border-tree-line hover:border-foreground/40`.
- `cardInteractive`: keyboard focus only (the detached `--ring` halo, unchanged). The `bg-accent` hover and the per-side border hover are removed.
- `cardFocus`: `border-2 border-primary` + light shadow. The name is `font-semibold`.
- Placeholder styles: `placeholderStatic` (`bg-muted`, no border, `text-muted-foreground`) and `placeholderAction` (dashed `border-tree-line`, transparent, `text-muted-foreground`; hover `bg-accent`, solid border, `text-foreground`; plus `cardInteractive` focus).
- Update the module's doc comment: hover no longer protects a left strip.

#### 2. Person card

**File**: `resources/js/components/tree/sheet-person-card.tsx`

**Intent**: Apply the shared look, drop the icons and keep gender for assistive tech.

**Contract**:
- Remove the `strip` map, `border-l-4`, `pr-9` and the Mars/Venus imports.
- The name and lifespan are centred. Lifespan uses `text-tree-meta`, and so does the eye button.
- Gender is rendered as `<span className="sr-only">mężczyzna</span>` / `kobieta` (none for unknown) next to the name, inside the link.
- Focus person uses `cardFocus`.
- The eye button stays top-right (`PersonPreviewLink`, same `aria-label`).
- Long names wrap with `break-words` / `overflow-wrap:anywhere`.

#### 3. Placeholders with a verb

**File**: `resources/js/components/tree/unknown-person-card.tsx`

**Intent**: A static unknown reads as information and an actionable one reads as an action.

**Contract**:
- Without `addLabel`: a `placeholderStatic` block with `label`.
- With `addLabel`: a `placeholderAction` button with a centred `Plus` icon followed by `label`. The visible text is the verb the caller passes, and `aria-label` stays the caller's full phrase.

**File**: `resources/js/components/tree/parent-slot-dialog.tsx`

**Intent**: The visible label becomes the verb.

**Contract**: Pass `label={ROLE_TITLE[slot.role]}` („Dodaj ojca/matkę/rodzica”) to the trigger. The server `label` prop is then unused here; remove it from `Props` and from the call in `family-sheet.tsx` `Slot`. The static unknown still shows `slot.label`.

`add-child-dialog.tsx` („Dodaj dziecko”) and `add-spouse-dialog.tsx` slot mode („Dodaj małżonka”) already pass verbs and need no change.

#### 4. Lines

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Relationship lines are visible chart lines, not UI chrome.

**Contract**: Every connector uses `--tree-line`: `bg-tree-line` for the stems and the couple line, `border-tree-line` for the brackets. This covers `PairConnector` (`:98,:102`), the couple line (`:288`), the descent line (`:327`) and the children bracket (`:153`). `PairConnector`'s bracket gets rounded ends like the canvas `.br`.

### Success Criteria:

#### Automated Verification:

- Types check: `vendor/bin/sail npm run types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Build succeeds: `vendor/bin/sail npm run build`

#### Manual Verification:

- On `/tree` (light and dark), male/female cards are tinted with centred text and no icons, and unknown-gender Kuba (`/tree/<Kuba>`) is neutral.
- Hover changes only the border to the gender colour, and Tab shows the ring halo on cards and placeholders.
- A missing parent reads „Dodaj ojca/Dodaj matkę/Dodaj rodzica” with a dashed border. A static unknown is a grey block without a border.
- A screen reader (or the accessibility tree in devtools) still reads „mężczyzna” / „kobieta” on cards.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Union node with a dropdown (C3, C3b)

### Overview

Replace the chip row above the sheet with a dropdown on the couple line that switches unions and adds a spouse.

### Changes Required:

#### 1. Controlled dialog

**File**: `resources/js/components/tree/pick-or-create-person-dialog.tsx`

**Intent**: Let a menu item open the dialog without being its trigger.

**Contract**:
- `trigger` becomes optional, and new optional props `open?: boolean` and `onOpenChange?: (open: boolean) => void` are added. When `open` is given, the component is controlled.
- The reset + `load()` runs whenever the dialog becomes open, from either path (see Critical Implementation Details).
- Without `trigger`, no `DialogTrigger` is rendered.
- Existing callers are unchanged.

#### 2. Spouse dialog mode

**File**: `resources/js/components/tree/add-spouse-dialog.tsx`

**Intent**: The `switcher` button goes away with the chip row, and a menu-driven mode replaces it.

**Contract**: `variant: 'slot' | 'menu'`. `menu` takes `open` / `onOpenChange` and renders no trigger. `slot` is unchanged. Update the doc comment on `variant`.

#### 3. Union node

**File**: `resources/js/components/tree/union-node.tsx` (new)

**Intent**: This is the control that decides which partner and children the sheet shows, placed on the couple it controls.

**Contract**: `UnionNode({ focus, unions, selected, addSpouse, candidates, genders, religions })`. It renders nothing when `unions.length === 0`.
- **Status text** (shared helper, used by trigger and items):
  - `is_marriage` with a year → „ślub 2001”
  - `is_marriage` without a year → „ślub, data nieznana”
  - a co-parent (not a marriage) → „bez ślubu”
  - the unknown union (`partner_id === null`) → no status
- **Trigger:** a `DropdownMenuTrigger asChild` button styled like canvas `.sel`: `h-[34px]`, `rounded-lg`, `border`, `bg-background`, `shadow-xs`; hover `bg-accent`; `data-[state=open]:border-primary`; `cardInteractive` focus; a `ChevronsUpDown` icon. The text is „N z M” (muted, only when `unions.length > 1`) plus the status (semibold). If both are empty, it reads „Związki”. `aria-label`: „Związek N z M: <status or partner>. Zmień związek lub dodaj małżonka”.
- **Content:**
  - A `DropdownMenuLabel` „Związki — <focus.display_name>”.
  - A `DropdownMenuRadioGroup` with value = `selected`. Each `DropdownMenuRadioItem` shows `partner_name ?? 'Nieznany partner'` over a muted line: the status and `pluralPhrase(children_count, '1 dziecko', 'N dzieci', 'N dzieci')`, joined by „ · ” and skipping an empty status.
  - A `DropdownMenuSeparator`, then a `DropdownMenuItem` „Dodaj małżonka” (with a `Plus` icon) that sets the spouse dialog open.
- Choosing a radio item does the same as `UnionSwitch.choose` today: `router.visit(treeShow(focus.id, { query: { spouse } }), { preserveScroll: true })`, with `spouse` = `partner_id` or `'unknown'`.
- `AddSpouseDialog variant="menu"` is rendered as a sibling of the menu, with its open state owned by `UnionNode`.

#### 4. Place the node; remove the chip row

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: The node sits on the couple line, centred between the two couple cards.

**Contract**: Inside the couple row container (`:285`), the node is absolutely centred (`left-1/2 top-1/2 -translate-x/y-1/2`, `z-10`) over the couple line. `FamilySheet` receives what `UnionNode` needs from `sheet` (`unions`, `selected_union`, `add_spouse`, `focus`) and the existing props. The empty-partner slot (`AddSpouseDialog variant="slot"`) is unchanged.

**File**: `resources/js/pages/tree/show.tsx`

**Contract**: Remove the `UnionSwitch` block (`:113-127`) and the `AddSpouseDialog` / `UnionSwitch` imports.

**File**: `resources/js/components/tree/union-switch.tsx`

**Contract**: Delete the file. Its logic moves to `union-node.tsx`.

### Success Criteria:

#### Automated Verification:

- Types check: `vendor/bin/sail npm run types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Build succeeds: `vendor/bin/sail npm run build`
- No reference to the removed switcher remains: `grep -rn "UnionSwitch\|union-switch\|variant=\"switcher\"" resources/js` returns nothing
- Sheet tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`

#### Manual Verification:

- For Jan (4 unions), the node reads „1 z 4 · ślub 2001”. The menu lists 4 radio items with the right statuses and „N dziecko/dzieci”. Picking Marta navigates to `?spouse=` and the node then reads „3 z 4 · bez ślubu” with the radio on Marta. The scroll position is kept.
- Picking the unknown union shows „4 z 4” and the menu item „Nieznany partner / 1 dziecko”.
- Anna (one union) shows no „1 z 1”. Sasza (one union, unknown partner) shows „Związki”, and Kacper (0 unions) has no node.
- „Dodaj małżonka” in the menu opens the spouse dialog with the candidates loaded. Closing it returns focus to the node, and the page stays clickable afterwards.
- The keyboard works: Tab to the node, Enter opens it, arrows move, Esc closes and focus is back on the node.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Sheet geometry — headings off the lines, „Dodaj dziecko” in the row (C4)

### Overview

Nothing interrupts the descent line any more, and the add-child placeholder reads as "the next child here".

### Changes Required:

#### 1. Screen-reader-only generation headings

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Keep the heading structure for assistive tech and remove the visual labels.

**Contract**: `RowLabel` renders an `sr-only` `h3`, so the four headings stay in the DOM in the same order. Any spacing the visible labels gave is replaced by the rows' own gaps, and the connector heights are tuned against the canvas (descent line ≈30px, bracket ≈13px).

#### 2. Children rows with the placeholder

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: „Dodaj dziecko” follows the children while the children stay centred on the couple axis.

**Contract**: `ChildrenRows` receives the add-child element and applies these placement rules. `c` is the number of children in the last row (1–4 after chunking by `CHILDREN_PER_ROW = 4`):
- `c` of 1 or 2: the placeholder is the next 2-column cell in the same row, right after the last child. The children keep their `CHILDREN_ROW_START` start, e.g. 2 children at cols 3–6 put the placeholder at 7–8, and 1 child at 4–5 puts it at 6–7.
- `c` of 3 or 4: the placeholder gets its own row below, 2 columns wide at `col-start-4` (centred), with no bracket.
- 0 children: „Brak dzieci w tym związku.” (centred), then the placeholder centred at `col-start-4`.
- The bracket is drawn only for rows with >1 real child and is inset by the child count, not the child + placeholder count, so it stays centred on the couple axis.
- The separate add-child row (`:346-353`) is removed.

Boundary check: 5 children give row 1 = 4 children (bracket) and row 2 = 1 child at cols 4–5 + placeholder at 6–7. 3 children give cols 2–7 + the placeholder row at cols 4–5. 4 children give a full row + the placeholder row.

#### 3. Couple and descent line with the node

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: The descent line runs from the node down to the bracket, and the unmarried centre couple's „Połącz jako małżeństwo” chip stays on it.

**Contract**: The descent block (`:322-336`) keeps `marryCentreCouple` on the line below the node, in `bg-background` so the line doesn't cross the chip. The descent line is always drawn (the add-child placeholder is always present), so the placeholder hangs from the couple like a child. `hasChildren` no longer gates it.

#### 4. Kitchen sink: a union with 3 children

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: The "3 children → placeholder on its own row" boundary has no fixture. The one-union case (Anna) and the one-unknown-partner union (Sasza → „Związki”) already exist.

**Contract**: Append a married couple with 3 children after the Nowicki block, with a one-line comment naming the state it covers. The seeder stays append-only so the id offsets in `shots.mjs` still hold.

### Success Criteria:

#### Automated Verification:

- Types check: `vendor/bin/sail npm run types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Build succeeds: `vendor/bin/sail npm run build`
- Headings stay in the DOM: `grep -n "sr-only" resources/js/components/tree/family-sheet.tsx` shows the `RowLabel` class

#### Manual Verification:

- No visible „Dziadkowie / Rodzice / Związek / Dzieci” labels. The line from the couple to the children bracket is continuous.
- Jan + Anna: children plus „Dodaj dziecko” in one row, with the bracket over the children only.
- Nowicki (5 children): 4 children with a bracket, then 1 child + the placeholder next to it.
- Kacper (0 unions): „Brak dzieci w tym związku.” with the placeholder centred.
- The new 3-child couple shows the placeholder on its own centred row.
- Jan + Marta (bez ślubu): „Połącz jako małżeństwo” is on the descent line below the node, not overlapping it.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Screenshot gate

### Overview

Shoot the audit's states inventory and judge each charge (C1–C4) against the "before" set. Visual findings aren't cosmetic: a charge that doesn't hold goes back to its phase.

### Changes Required:

#### 1. Gate script

**File**: `context/changes/tree-sheet-variant-c/screenshots/shots.mjs` (copied from `context/archive/2026-09-28-tree-family-sheet-ui/screenshots/shots.mjs`)

**Intent**: Reuse the existing out-of-repo Playwright script, which derives person ids from the proband, and extend it to the variant C states.

**Contract**: Keep all existing shots, and add these:
- Node closed, keyboard focus and open menu (light + dark)
- Placeholder hover and focus („Dodaj ojca” / „Dodaj dziecko”)
- The spouse dialog opened from the menu item
- Jan + Marta (the marry chip on the descent line)
- Jan with the unknown union selected

Also add a 390px regression shot of the proband and Jan with 4 unions. Output goes to `screenshots/after/`, and the "before" set is `context/archive/2026-09-28-tree-family-sheet-ui/screenshots/after/`.

Shots also cover the Phase 4 three-children couple, Anna (one union) and Sasza („Związki”); their ids are offsets like the rest.

#### 2. Verdict

**File**: `context/changes/tree-sheet-variant-c/screenshots/verdict.md`

**Intent**: Record per-charge pass/fail with the shot names, and note the measured contrast of `--tree-meta` on the tinted surfaces and of `--tree-line` (audit's estimates to confirm).

**Contract**: One row per charge C1, C2, C3, C3a, C3b and C4: shots, verdict, notes. Deferred D1–D4 are listed as still deferred.

### Success Criteria:

#### Automated Verification:

- Gate script runs without errors: `node context/changes/tree-sheet-variant-c/screenshots/shots.mjs context/changes/tree-sheet-variant-c/screenshots/after` (with the `LD_LIBRARY_PATH` noted in the script)
- Full checks pass: `vendor/bin/sail npm run check && vendor/bin/sail npm run types:check && vendor/bin/sail npm run build`
- Sheet tests pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`

#### Manual Verification:

- Every state in the audit's inventory has a shot, in light and dark where the inventory asks for it.
- `verdict.md` shows C1–C4 passing against the before set. Any failed charge was sent back and re-shot.
- The 390px shots show no regression beyond the known sideways scroll (D1).
- The user asks for the full suite: `vendor/bin/sail artisan test --compact`.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None on the JS side (the repo has no JS test runner). Status text and pluralisation are checked in the gate.

### Integration Tests:

- `tests/Feature/TreeTest.php`: `partner_name` for person/co-parent/unknown unions, `label` absent, `marriage_year` of the repeat-marriage union. The other-user 404 coverage is unchanged (no new endpoint).

### Manual Testing Steps:

1. `/tree` as the demo user: tinted cards, visible lines, node „1 z 4 · ślub 2001”.
2. Open the node and pick Marta: the URL gets `?spouse=`, and the node reads „3 z 4 · bez ślubu”. The marry chip is below it.
3. Menu → „Dodaj małżonka”: the dialog loads candidates. Close it with Esc: focus is back on the node, and the page is still clickable.
4. Nowicki: 5 children wrap, and the placeholder is next to the fifth child.
5. Kuba (unknown gender): neutral card. Kacper: no node, the placeholder is centred.
6. Toggle dark mode and repeat 1–2.

## Performance Considerations

None. The menu renders at most a handful of unions, and the payload shrinks slightly (`label` is gone).

## Migration Notes

No data migration. `label` is removed from the Inertia payload in the same deploy as the only reader, so there's no compatibility window.

## References

- Audit (charges C1–C4, states inventory): `context/changes/tree-sheet-variant-c/audit.md`
- Token values and sources: `context/changes/tree-sheet-variant-c/tokens.md`
- Design canvas: https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F (boards „C — karty w kolorze płci”, „C — stany”)
- Previous change (layout, gate script, before set): `context/archive/2026-09-28-tree-family-sheet-ui/`
- Union payload: `app/Actions/Trees/BuildFamilySheet.php:93-123`
- Union tests: `tests/Feature/TreeTest.php:151-235`
- Dialog to control: `resources/js/components/tree/pick-or-create-person-dialog.tsx:67-122`
- Kitchen sink: `database/seeders/TreeViewDemoSeeder.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — variant C tokens and `partner_name`

#### Automated

- [x] 1.1 Sheet payload tests pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php` — 418a3ff
- [x] 1.2 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent` — 418a3ff
- [x] 1.3 Static analysis passes: `vendor/bin/sail composer run types:check` — 418a3ff
- [x] 1.4 Types check: `vendor/bin/sail npm run types:check` — 418a3ff
- [x] 1.5 Format and lint pass: `vendor/bin/sail npm run check` — 418a3ff

#### Manual

- [x] 1.6 `/tree` still renders; union chips show partner names (temporary); sheet looks unchanged — 418a3ff

### Phase 2: Cards, placeholders and lines (C1, C2)

#### Automated

- [x] 2.1 Types check: `vendor/bin/sail npm run types:check` — ac8f2c0
- [x] 2.2 Format and lint pass: `vendor/bin/sail npm run check` — ac8f2c0
- [x] 2.3 Build succeeds: `vendor/bin/sail npm run build` — ac8f2c0

#### Manual

- [x] 2.4 Tinted male/female cards, centred text, no icons; unknown-gender card neutral (light and dark) — ac8f2c0
- [x] 2.5 Hover changes only the border to the gender colour; Tab shows the ring halo on cards and placeholders — ac8f2c0
- [x] 2.6 Missing parent reads „Dodaj ojca/matkę/rodzica” dashed; static unknown is a borderless grey block — ac8f2c0
- [x] 2.7 Screen reader still reads „mężczyzna” / „kobieta” on cards — ac8f2c0

### Phase 3: Union node with a dropdown (C3, C3b)

#### Automated

- [x] 3.1 Types check: `vendor/bin/sail npm run types:check`
- [x] 3.2 Format and lint pass: `vendor/bin/sail npm run check`
- [x] 3.3 Build succeeds: `vendor/bin/sail npm run build`
- [x] 3.4 No reference to the removed switcher remains
- [x] 3.5 Sheet tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`

#### Manual

- [x] 3.6 Jan's node reads „1 z 4 · ślub 2001”; menu lists 4 unions; picking Marta gives „3 z 4 · bez ślubu” with scroll kept
- [x] 3.7 Unknown union shows „4 z 4” and „Nieznany partner / 1 dziecko”
- [x] 3.8 Anna: no „1 z 1”; Sasza shows „Związki”; Kacper: no node
- [x] 3.9 „Dodaj małżonka” from the menu opens the dialog with candidates; focus returns to the node; page stays clickable
- [x] 3.10 Keyboard: Tab, Enter, arrows, Esc work and focus returns to the node

### Phase 4: Sheet geometry — headings off the lines, „Dodaj dziecko” in the row (C4)

#### Automated

- [ ] 4.1 Types check: `vendor/bin/sail npm run types:check`
- [ ] 4.2 Format and lint pass: `vendor/bin/sail npm run check`
- [ ] 4.3 Build succeeds: `vendor/bin/sail npm run build`
- [ ] 4.4 Headings stay in the DOM as `sr-only`

#### Manual

- [ ] 4.5 No visible generation labels; the couple-to-children line is continuous
- [ ] 4.6 Jan + Anna: children and „Dodaj dziecko” in one row, bracket over the children only
- [ ] 4.7 Nowicki: 4 children, then 1 child + placeholder next to it
- [ ] 4.8 Kacper: „Brak dzieci w tym związku.” with the placeholder centred
- [ ] 4.9 New 3-child couple shows the placeholder on its own centred row
- [ ] 4.10 Jan + Marta: marry chip on the descent line below the node, not overlapping

### Phase 5: Screenshot gate

#### Automated

- [ ] 5.1 Gate script runs without errors into `screenshots/after`
- [ ] 5.2 Full checks pass: check, types:check, build
- [ ] 5.3 Sheet tests pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`

#### Manual

- [ ] 5.4 Every state in the audit's inventory has a shot (light/dark where asked)
- [ ] 5.5 `verdict.md` shows C1–C4 passing against the before set
- [ ] 5.6 390px shots show no regression beyond the known sideways scroll
- [ ] 5.7 User runs the full suite: `vendor/bin/sail artisan test --compact`
