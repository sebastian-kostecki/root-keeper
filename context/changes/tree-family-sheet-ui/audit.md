# Audit — family sheet view (`tree/show`)

Audit date: 2026-09-28. View: `resources/js/pages/tree/show.tsx` + `resources/js/components/tree/*`.
Evidence: `screenshots/before/` (kitchen sink = `TreeViewDemoSeeder`, 1440px + 390px, light + dark,
empty tree, logged out, foreign person, keyboard focus, hover).

## Design-system contract as found

| Half | Where | State |
| --- | --- | --- |
| Values | `resources/css/app.css` — `:root` / `.dark` hold values, `@theme` publishes `--color-*` | shadcn neutral. Tree adds `--gender-male/female/unknown` (both themes, with source comment → Ancestris). |
| Components | `resources/js/components/ui/*` (shadcn: Button, Badge, Card, ToggleGroup, Dialog…) | View uses them: Button, Badge, Card (empty state), ToggleGroup (union switch). |
| Views reading them | tree view | **Good**: no hex/rgb literals, colours by role (`bg-card`, `text-muted-foreground`, `border-l-gender-*`), dark mode works at the token layer (see `desktop-dark-proband.png`). |

Note (no charge): `app.css` publishes through `@theme`, not `@theme inline`. Works today because `.dark` is set on
`<html>` (`use-appearance.tsx`); it would break if `.dark` ever moved to a descendant.

Reference motif (already chosen, not re-researched): PL-SOFT „Formularz rodzinny” — children hang from the **couple**,
not from one parent — plus Ancestris gender frames
(`context/archive/2026-09-28-graphical-tree-view/research.md` §1, §3).

So this is **not** a "screens ignore their tokens" case. The debt is in layout architecture and in two state tokens.

## Charges (enter the plan)

### C1 — Accidental architecture / missing shared component: no page gutter on domain pages

- **Where:** `resources/js/layouts/app/app-sidebar-layout.tsx:14-16` renders `{children}` straight into `SidebarInset`;
  `resources/js/pages/tree/show.tsx:54,82` wrap in `space-y-6` with no padding. Same pattern in `persons/index.tsx:56`,
  `scans/index.tsx:26`, `scans/show.tsx:38`. Settings pages pad themselves (`layouts/settings/layout.tsx:35` `px-4 py-6`),
  the header uses `px-6 md:px-4` (`app-sidebar-header.tsx:11`).
- **User impact:** the name heading, union switch and outer cards sit flush against the panel edge (desktop) and the
  screen edge (390px), and the right-hand grandparent card runs into the panel's rounded corner. The page reads unfinished
  and misaligned with the breadcrumb bar above it.
- **Fix direction:** one page container (padding aligned with the header's inline padding) instead of per-page `p-*`
  copy-paste. **Decision for the plan:** layout-level (fixes tree + persons + scans in one line; slightly beyond "one view")
  vs tree-only. Recommendation: layout-level — it is the shared-component fix, and per-page padding would be the duplicate.

### C2 — Accidental architecture: children are not attached to the couple

- **Where:** `resources/js/components/tree/family-sheet.tsx:288-296` draws the couple's descent line at `left-1/2`
  (between the two blocks), while `ChildrenRows` (`:124-161`, `inset = 12.5`, children start at column 1) lays the children
  and their bracket out from the **left** edge, under the focus person's block. There is also no line joining the couple.
- **User impact:** in `desktop-light-proband.png` the bracket over Kacper/Zuzanna spans 12.5–37.5%, the couple line drops
  at 50% — they never meet. In `desktop-light-coparent.png` the only child (Oliwia) sits under Jan while the line ends in
  the „Połącz jako małżeństwo” button. On a genealogy sheet the line *is* the data: it currently says "children of Jan",
  not "children of Jan and Marta".
- **Fix direction:** centre the children rows under the couple axis (50%) so the bracket's midpoint meets the descent
  line; optionally a short horizontal couple line between focus and partner. Motif: PL-SOFT family sheet.

### C3 — Missing tokens (state): selected union and hover are invisible in light mode

- **Where:** `resources/js/components/tree/union-switch.tsx:50-53` relies on `ui/toggle.tsx:8`
  `data-[state=on]:bg-accent`; hover on cards `sheet-person-card.tsx:72` and `unknown-person-card.tsx:27`
  `hover:bg-accent/50`. `--accent` is `oklch(0.97 0 0)` on `--background` `oklch(1 0 0)` (`app.css:69,81`).
- **User impact:** in light mode the selected union („Anna Wiśniewska · ślub 2001”) is a barely-grey chip among white ones
  — the user cannot tell which union the sheet (and its children row) is showing; hover on a person card
  (`desktop-light-hover-card.png`) produces no visible change. Dark mode is fine (`accent` 0.269 vs 0.145).
- **Fix direction:** express "selected" with a role that already contrasts (e.g. `border-primary` / `ring-primary`
  on the selected ToggleGroupItem in `union-switch.tsx`, not by editing `components/ui/toggle.tsx`); hover via a stronger
  role (`bg-accent` full or `bg-muted`) — both themes checked. No new colour unless the plan proves a role is missing.

### C4 — Missing tokens (focus): `--ring` is too faint to be a focus indicator

- **Where:** `app.css:87` `--ring: oklch(0.87 0 0)` (light), `app.css:128` `oklch(0.439 0 0)` (dark). Tree cards use
  `focus-visible:ring-ring focus-visible:ring-[3px]` (`sheet-person-card.tsx:72`, `unknown-person-card.tsx:27`); shadcn
  Button/Toggle use `ring-ring/50` (half of that).
- **User impact:** a keyboard user tabbing through the sheet (~20 card links + eye buttons + dialog triggers) sees a
  light-grey halo of ~1.5:1 against white (dark theme: `0.439` on `0.145` ≈ 2.5:1 — also under 3:1) (`desktop-light-focus-card.png`); the focus person's static
  `ring-primary ring-2` (`sheet-person-card.tsx:62`) is a stronger signal than actual keyboard focus, so "current person"
  and "where my keyboard is" are easy to confuse.
- **Fix direction:** token layer only — raise `--ring` contrast in **both** themes to ≥3:1 against `--background` (global
  token change, allowed by scope). Candidate values to evaluate in the plan (deposit the chosen ones with source):
  shadcn neutral preset `--ring: oklch(0.708 0 0)` light / `oklch(0.556 0 0)` dark (source: ui.shadcn.com/docs/theming,
  neutral) — **~2.6:1 on white, likely still short**; `oklch(0.556 0 0)` light (= `--muted-foreground`, ~4.7:1).
  Keep the focus-visible classes in one place (see D3).

### C5 — Accidental architecture: desktop grid shipped unchanged to mobile

- **Where:** `resources/js/components/tree/family-sheet.tsx:193-194` — `overflow-x-auto` around
  `grid min-w-[72rem] grid-cols-8` (one-off width).
- **User impact:** at 390px (`mobile-light-proband-viewport.png`) the user sees about a third of the sheet, scrolled to the
  far left: focus person half off-screen, spouse and the spouse's whole ancestry invisible, and nothing (mobile hides
  scrollbars) says the sheet continues sideways. The union switch already wraps to 3 rows above it, pushing the sheet
  below the fold.
- **Fix direction:** **decision for the plan** — (a) keep the grid but scroll the focus person into horizontal centre on
  load and show an edge fade/affordance; or (b) a stacked single-column layout under `md`. Recommendation: (a) — keeps the
  PL-SOFT shape one layout, smallest change; (b) is a second layout to maintain.

## Deferred (visible, not dropped)

| # | Category | Where | User impact | Why deferred |
| --- | --- | --- | --- | --- |
| D1 | Accidental architecture | `bootstrap/app.php:51-55` (no Inertia error page); reached via `/tree/{foreign-or-deleted-id}` | Laravel's bare „404 Nie Znaleziono” page, no app shell, no way back (`desktop-light-foreign-person.png`). Status is correct (policy `denyAsNotFound`). | App-wide error-page change, not this view. Own change (Inertia v3 custom exception handling). |
| D2 | Layout / hierarchy | `tree/show.tsx:84` `Heading` (`components/heading.tsx:11` `mb-8`) inside `space-y-6` | ~56px dead gap between the title row and union switch; title is an `h2`, the page has no `h1`. | Fold into C1 if the page container touches the header row; otherwise a follow-up. |
| D3 | Missing shared component (minor) | card base + focus/hover classes duplicated in `sheet-person-card.tsx:55,72` and `unknown-person-card.tsx:16,27` | None today; C3/C4 fixes would have to be made twice and can drift. | Consolidate only if C3/C4 touch those lines — do it then, not as its own phase. |
| D4 | Affordance | `unknown-person-card.tsx:15-31` | Actionable placeholders („Nieznany ojciec +”) and static ones look alike except the `+`; muted text reads as disabled. | Not blocking; revisit after C3 hover is visible. |
| D5 | Density | `family-sheet.tsx:199-200` grandparents `col-span-1` (~140px) with `pr-9` for the eye button | Every grandparent name wraps to 2 lines. | Acceptable at desktop; revisit with C5. |

## Entry points checked

| Entry | Result |
| --- | --- |
| Logged out → `/tree` | Redirect to `/login` (`logged-out.png`). OK. |
| No proband (`tree-demo-empty`) | Empty-state Card + „Przejdź do osób” (`desktop-light-empty.png`, `mobile-light-empty.png`). OK apart from C1 gutter. |
| Straight link to another tree's person | 404, bare framework page → D1. |
| Person with no unions / unknown gender (`/tree/125`) | Renders; union switch hidden, placeholders shown. OK. |

## States inventory for the gate

default ✓ · hover ✗ (C3) · focus ✗ (C4) · disabled — `SetRootPersonButton` while processing, not captured yet ·
error — n/a on the sheet itself (dialog forms own their errors) · empty ✓ (no proband; union without children
„Brak dzieci w tym związku.”) · loading — `personCandidates` is `Inertia::optional`, loaded when a dialog opens; not
captured yet. The kitchen sink for the gate = `TreeViewDemoSeeder` + the screenshot set above, re-shot as `after/`.
