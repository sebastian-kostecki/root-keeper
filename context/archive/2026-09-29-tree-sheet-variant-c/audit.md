# Audit — family sheet (`tree/show`) against variant C

Audit date: 2026-09-29. View: `resources/js/pages/tree/show.tsx` + `resources/js/components/tree/*`.
Target: variant C from the Design canvas https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F
(boards „C — karty w kolorze płci”, „C — stany”). Mobile board „C — mobile” is **out of scope** (own change).

Evidence "before": the code has not changed since `tree-family-sheet-ui` closed (HEAD `36953b1`), so
`context/archive/2026-09-28-tree-family-sheet-ui/screenshots/after/` is this change's "before" set
(same seeder `TreeViewDemoSeeder`, same `shots.mjs`). Re-shoot into `screenshots/before/` here only if
something changes before phase 1.

## Design-system contract as found

| Half | Where | State |
| --- | --- | --- |
| Values | `resources/css/app.css` — `:root` / `.dark` values, `@theme` publishes `--color-*` | shadcn neutral + `--gender-male/female/unknown` (strip colours, both themes, sourced). No surface/border tints per gender, no connector-line role. |
| Components | `resources/js/components/ui/*` | `dropdown-menu.tsx` already present (needed for the union node). `toggle-group.tsx` used today by the union switch. |
| Tree primitives | `components/tree/card-styles.ts` (`cardSurface`, `cardInteractive`) | Shared by person + placeholder cards — the one place the variant-C card look goes. |
| Helpers | `resources/js/lib/plural.ts` (`pluralPhrase`) | Exists; the union switch does not use it. |

No design-system record in `.ai/rules` (directory absent); no `lessons.md`.

## Charges (enter the plan)

### C1 — Missing tokens: variant C needs gender surfaces, a readable meta text and a visible connector line

- **Where:** `resources/css/app.css:105-107` / `:144-146` define only the strip colours; cards take their
  look from `sheet-person-card.tsx:17-21,57-58` (`border-l-4` + `border-l-gender-*`). Connectors use the
  generic border role: `family-sheet.tsx:98` (`border-x border-b`), `:102` / `:288` / `:327` (`bg-border`),
  `:153` (`border-x border-t`). Lifespan uses `text-muted-foreground` (`sheet-person-card.tsx:46`).
- **User impact:** (a) with only a 4px strip, telling the two families' men and women apart needs reading
  each card edge — variant C makes gender the card's fill; (b) connector lines are `--border`
  (`oklch(0.922 0 0)` on white ≈ 1.2:1), so on the "after" shots the tree's lines — the actual
  relationship data — nearly vanish; (c) `--muted-foreground` on a tinted surface drops to ≈ 4.3:1,
  under 4.5:1 for 12px lifespans.
- **Fix direction:** add roles in `:root` + `.dark`, publish via `@theme`:
  `--gender-male-surface/-border`, `--gender-female-surface/-border`, `--tree-line`, `--tree-meta`.
  Values + sources: [`tokens.md`](tokens.md). Unknown gender keeps `bg-card` + `--tree-line` border (no new token).

### C2 — Missing shared component (states): person/placeholder cards must switch to the variant-C look in one place

- **Where:** `sheet-person-card.tsx:24-50` (Mars/Venus icons carry the only accessible gender text:
  `aria-label="mężczyzna"/"kobieta"` at `:30,:37`), `:57` (`pr-9` + left-aligned), `:64` focus person as
  `ring-primary ring-2`, `:81` eye button top-right; `card-styles.ts:2` (`cardSurface`), `:12-15`
  (`cardInteractive`: hover = `bg-accent` + darker top/right/bottom border — on a tinted card `bg-accent`
  would erase the gender fill). `unknown-person-card.tsx:17-23` vs `:26-38`: static unknown and
  actionable placeholder differ only by a `+` (audit D4 of the previous change).
- **User impact:** keyboard/screen-reader users lose the gender if icons are simply deleted; hover would
  wipe the colour that now carries meaning; „Nieznany ojciec +” still reads as disabled text, not as
  „add the father here”.
- **Fix direction:** in `card-styles.ts` — tinted surface per gender, centred text, radius `rounded-xl`,
  hover = border in the gender colour (`hover:border-gender-*`), keyboard focus unchanged (detached
  `--ring` halo). Focus person = `border-2 border-primary` + light shadow. Icons removed; gender kept as
  `sr-only` text. Placeholders: static unknown = `bg-muted`, no border; actionable = dashed `--tree-line`
  border with an explicit verb — „Dodaj ojca / Dodaj matkę / Dodaj rodzica” derived from
  `parent_of.role` (`types/tree.ts:9,15`), „Dodaj dziecko”, „Dodaj małżonka”.

### C3 — Accidental architecture: the union choice lives above the sheet, away from the couple it controls

- **Where:** `pages/tree/show.tsx:113-128` renders `UnionSwitch` (a `ToggleGroup`, `union-switch.tsx:47-78`)
  plus the `AddSpouseDialog variant="switcher"` button as a row of chips over the whole sheet;
  `union-switch.tsx:68` prints „Dzieci: N” (no plural forms although `lib/plural.ts` exists).
- **User impact:** the control that decides which partner and which children the sheet shows is visually
  unrelated to the couple node; with 4+ unions it wraps into rows pushing the tree down; „Dzieci: 1”
  reads as a form field, not Polish.
- **Fix direction:** a `DropdownMenu` (`components/ui/dropdown-menu.tsx`) triggered from the couple node
  on the line between focus and partner: trigger „1 z 4 · ślub 2001” (`ślub, data nieznana` /
  `bez ślubu` / nothing for „Nieznany partner”), items = radio items „<partner> / <status> · N dzieci”,
  separator, „Dodaj małżonka”. Same `router.visit(... spouse=…)` + `preserveScroll` as today.
  With **one** union the node is still a dropdown (it is the only way to add a second spouse) but drops
  „1 z 1” — this corrects the canvas state „Jeden związek — sama etykieta”. With **zero** unions there is
  no node; the empty partner slot keeps `AddSpouseDialog variant="slot"`.
- **Sub-charge C3a — data shape:** `app/Actions/Trees/BuildFamilySheet.php:108-110` bakes the partner name
  and the status into one `label` string („Anna Wiśniewska · ślub 2001”). The menu needs the name on its
  own line. Add `partner_name` (nullable for the unknown union) to the union payload + `SheetUnion` type
  (`types/tree.ts:42-49`); derive the status line client-side from `is_marriage` / `marriage_year`.
  Existing Pest coverage of the sheet payload must be extended, not replaced.
- **Sub-charge C3b — dialog from a menu item:** `pick-or-create-person-dialog.tsx:67,121-122` owns its
  `open` state and needs a `DialogTrigger`. A `DropdownMenuItem` cannot be that trigger (closing the menu
  unmounts it). Needs optional controlled `open` / `onOpenChange` props, the menu item only sets
  `open = true`. `AddSpouseDialog` gains a third mode (or loses `switcher`).

### C4 — Accidental architecture: generation headings cut the descent line; „Dodaj dziecko” sits in its own row

- **Where:** `family-sheet.tsx:73-78` (`RowLabel`), used at `:220, :257, :284, :338` — the „Dzieci” heading
  sits between the couple's descent line (`:322-335`) and the children bracket (`:150-157`).
  `AddChildDialog` is a separate row under all children rows (`:346-352`, fixed `col-start-4`).
- **User impact:** the line that says "these are the children of this couple" is visibly interrupted by a
  label; the add-child card floats below the children instead of reading as "the next child here", and
  after 5+ children it is two rows away from the first child.
- **Fix direction:** keep the four headings **`sr-only`** (screen-reader structure stays, visual labels
  go — per the user's decision). Render „Dodaj dziecko” as the cell after the last child in the same
  row; the bracket spans real children only and stays centred on the couple axis; a full row (4)
  pushes the placeholder to a new centred row. No children: „Brak dzieci w tym związku.” + the placeholder
  centred under the node.

## Deferred (visible, not dropped)

| # | Category | Where | User impact | Why deferred |
| --- | --- | --- | --- | --- |
| D1 | Accidental architecture | mobile layout (`family-sheet.tsx:193-194` scroll grid; canvas „C — mobile”) | On 390px the 8-col grid still scrolls sideways; variant C's vertical layout is designed but not built. | Second layout to maintain — own change, as agreed. Phase gate still shoots 390px to confirm nothing regresses. |
| D2 | Colour-only encoding | whole sheet after icon removal | Gender is fill hue only (blue vs rose at equal lightness). Blue/rose stays distinguishable for red-green CVD, not for achromatopsia. | User decision („wystarczy sam kolor”). `sr-only` gender text keeps it for assistive tech. |
| D3 | Accidental architecture | `bootstrap/app.php:51-55` (bare 404 for foreign person) | Unchanged from previous audit D1. | App-wide, not this view. |
| D4 | Architecture | `show.tsx` meta line „ur. 1975 · 4 związki · 5 dzieci” from the canvas | Needs new aggregates in the payload. | Not part of the user's C selection list; header stays as is. |

## Entry points checked

| Entry | Expected after this change |
| --- | --- |
| No proband | Unchanged empty state (canvas „Pusty stan” not in scope). |
| Person with 0 unions | No node, empty partner slot „Dodaj małżonka”, placeholder „Dodaj dziecko” centred. |
| Person with 1 union | Node without „1 z 1”, menu has the union + „Dodaj małżonka”. |
| Person with 4 unions + `?spouse=` of a non-first one | Node „3 z 4 · bez ślubu”, menu radio on that union. |
| Unknown gender (`/tree/125`) | Neutral card, no tint. |
| Logged out / foreign id | Unchanged (redirect / 404). |

## States inventory for the gate

Card: default · hover (gender border) · keyboard focus (ring halo) · focus person · unknown gender · long name.
Placeholder: static unknown · actionable (default / hover / focus). Node: closed · open (menu) · keyboard
focus · one union. Connector: `Połącz jako małżeństwo`. Children: 0 · 2 · 5 (wrap). Themes: light + dark
for default, hover, open menu. Widths: 1440 + 390 (regression only). Loading: `personCandidates` optional
prop while a dialog opens — capture the dialog opened from the menu item. Disabled: `SetRootPersonButton`
processing — still uncaptured, out of scope.
