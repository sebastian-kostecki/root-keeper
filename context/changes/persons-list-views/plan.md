# Persons list — List and Table views Implementation Plan

## Overview

`/persons` gets two views the user switches between: **A — Lista** (rows grouped by the first letter of the surname) and **B — Tabela** (sortable columns). The design comes from the canvas https://claude.ai/artifact/LT3qzYRdcYfkxZchBJ7LEN (boards `Main`, `Tabela`, `Stany`, `Mobile`; board C „Oś życia” rejected).

The view lives in the URL as `?view=tabela`, next to `?q=` and a new `?sort=`. The work is split into a contract change first (backend payload + `filters`), then shared components, then the view itself, then a screenshot gate.

## Current State Analysis

- **Controller.** `PersonController::index` returns the whole tree, unpaginated, ordered by surname (`app/Http/Controllers/PersonController.php:26-55`).
  - It eager-loads only `events`.
  - Props: `persons`, `filters: {q}`, `genders`, `religions`.
- **Current page** (`resources/js/pages/persons/index.tsx`):
  - One ungrouped list of `Card`s showing `display_name` + `formatLifespan`.
  - The debounced search visit builds its query from `q` alone (`:41-47`), so any other query param is dropped.
- **Payload gaps.** `EventResource` sends a formatted `date` string only.
  - `is_confirmed`/`scans` appear only when `scans` is loaded (`app/Http/Resources/EventResource.php:34-37`).
  - Raw date parts are sent only by `MarriageResource` (`:45-50`).
  - `PersonsTest.php:187-200` asserts that `is_confirmed` and `scans` are **missing** on index.
- **Reusable pieces:**
  - avatar: `genderCard` (`resources/js/lib/gender-styles.ts:2-6`) + `useInitials`, inlined in `person-modal-header.tsx:50-58`;
  - `PersonPreviewLink` (keeps `q`/`view` when opening the preview);
  - `dropdown-menu`, `toggle-group`, `tabs`;
  - `treeShow` from `@/routes/tree`.
- **Missing pieces:**
  - `ui/table.tsx` does not exist;
  - the proband id is not an index prop.
- **Fuller detail:** `context/changes/persons-list-views/research.md`.

## Desired End State

**Server contract** — `GET /persons` accepts:

| Param | Accepted values | Missing or invalid |
|---|---|---|
| `q` | unchanged | unchanged |
| `view` | `tabela` | `lista` |
| `sort` | `nazwisko`, `-nazwisko`, `urodzenie`, `-urodzenie`, `zgon`, `-zgon` (only when `view=tabela`) | `nazwisko`; also forced to `nazwisko` whenever `view` is `lista` |

- `filters` echoes all three: `{q, view, sort}`.
- The payload adds `rootPersonId`.
- Each event gets `is_confirmed: bool` (no scan bodies) and `date_parts`.

**View A (Lista)**
- Grouping:
  - Grouped under the uppercase first letter of `last_name`.
  - Groups are ordered with a Polish collator.
  - A final group „Bez nazwiska” holds people with no surname.
  - Jump links sit above the groups.
- Each row:
  - gender-coloured initials avatar;
  - name + „Probant” badge;
  - meta line „ur. 12.03.1948, Kraków ✓ · zm. ok. 2015, Tarnów” (✓ has sr-only text „potwierdzone aktem”);
  - chevron.
- Every row is a `PersonPreviewLink`.

**View B (Tabela)**
- Columns: Osoba / Urodzenie (date + place, ✓) / Zgon / Zamieszkanie / Wyznanie / ⋯.
- Header buttons sort Osoba, Urodzenie and Zgon, with `aria-sort`.
- The ⋯ menu has „Otwórz podgląd” and „Pokaż na drzewie”.
- On narrow screens the table scrolls horizontally, with the Osoba column sticky.

**Toolbar and states**
- The toolbar holds search + a Lista | Tabela switcher.
- The search visit, switcher and sort headers all carry the params they don't own.
- The page states are:
  - empty tree;
  - no results with „Wyczyść wyszukiwanie”;
  - search in flight (spinner in the input, list dimmed).

**Verification.** Pest covers the contract, Vitest covers the helpers, and the screenshot gate `verdict.md` records light/dark/390 px shots of both views and all states.

### Key Discoveries:

- `urlAfterDelete` keeps every query key except `person` (`PersonController.php:112-138`). So `view`/`sort` survive a delete from the preview without backend work.
- `PersonOrdering::relativeSortKey` (`app/Support/PersonOrdering.php`) is the house rule for birth-date order: known years first, then year/month/day, then surname/first name/id. The client comparator mirrors it.
- `ToggleGroup` fires `''` when the active item is clicked again. `person-section-fields.tsx:90-94` guards this, and the switcher must do the same.
- The screenshot scripts of earlier changes find people via `a[href*="person="]` on `/persons`. Names in both views must stay real preview links.
- `TreeViewDemoSeeder` is append-only (id offsets used by earlier `shots.mjs`). Its `person()` helper takes a non-null surname and writes no places.

## What We're NOT Doing

- **No „Usuń osobę” in the row menu.** Deletion stays in the preview modal. This follows S-05 („No delete action on the persons list”, `context/archive/2026-09-25-person-edit-and-delete/plan.md:42`) and the CLAUDE.md impact-first rule. It diverges from canvas board B.
- No „Oś życia” view (rejected on the canvas).
- No server-side re-ordering: the server order stays surname/first name/id, and `PersonsTest.php:21-40` stays green. Table sorting happens in the browser.
- No pagination, filters by gender or date, or search by place/year.
- No new design tokens and no new npm/composer packages. `ui/table.tsx` is shadcn markup only.
- No change to `formatLifespan` or its other callers (modal header, tree cards).
- No in-repo screenshot test. The gate stays an out-of-repo script, as in earlier changes.
- No persistence of the chosen view beyond the URL (no localStorage, no user setting).

## Implementation Approach

**Contract before pixels.** Phase 1 fixes the server contract and its tests, so the UI phases consume a stable payload.

**One query builder.** All `/persons` visits the page makes go through one helper, so no control can drop another's param:
- the search debounce;
- the view switcher;
- the sort headers;
- „Wyczyść wyszukiwanie”.

**Switching view or sort** reloads only `filters` (`only: ['filters']`). The person list is already complete in the page.

**Sorting and grouping** are pure functions over the loaded list, tested with Vitest.

## Critical Implementation Details

- **Event payload.**
  - Load events with `withExists('scans')`, not `events.scans`. `EventResource` then emits `is_confirmed` from the `scans_exists` attribute when present, and keeps the existing `whenLoaded('scans')` path for the preview.
  - `date_parts` moves from `MarriageResource` into `EventResource` (same shape). `MarriageResource` stops adding it itself.
- **Sort semantics** — the decided predicate:
  - Known dates first. Among them, order by year, then month, then day.
    - A missing month or day counts as 0, so it comes first within its year.
    - `is_approximate` is ignored.
    - Ties break by surname, first name, id.
  - Unknown dates (no event, or event without a year) go last in **both** directions.
  - `-urodzenie` reverses only the known part.
  - **Names use one comparator, never server order.** The live DB (en_US.utf8 on postgres:18-alpine, musl) sorts bytewise (`Zając, Ziel, Ćma, Łukasz, Żak`). So every client sort and the within-group order compare surname, then first name, with `Intl.Collator('pl')`, then id.
    - `nazwisko` sorts by that key; `-nazwisko` reverses it.
    - People without a surname go last in **both** directions.
    - The `-` applies to the primary key only. Tie-breaks (surname, first name, id) always stay ascending.
  - `sort` is honoured only when `view=tabela`. Switching to Lista drops `sort` from the URL.
- **Search visit.**
  - Keep `preserveState`, `preserveScroll` and `replace: true` (person-search decision).
  - The equality guard `trimmed === filters.q` stays, so a switcher visit does not re-trigger search.

## Phase 1: Contract — filters, proband, confirmation and date parts

### Overview

The server normalises and echoes `view` and `sort`, sends `rootPersonId`, and gives each index event `is_confirmed: bool` and `date_parts`. The TS types follow, and Pest locks the contract in.

### Changes Required:

#### 1. Index controller

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: Normalise `view` and `sort` like `q`: an unknown or non-string value falls back to the default, without a validation error. Echo them in `filters`, add `rootPersonId`, and load the scan-existence flag on events.

**Contract**:
- `filters: {q: string, view: 'lista'|'tabela', sort: 'nazwisko'|'-nazwisko'|'urodzenie'|'-urodzenie'|'zgon'|'-zgon'}`
- `rootPersonId: int|null` (`$tree->root_person_id`)
- Eager-load `events` with `withExists('scans')`.
- Two private normalisers next to `normalizeSearchQuery`.
- Query scoping and ordering stay unchanged.
- The query and resolve move into a closure: `'persons' => fn (): array => PersonResource::collection(...)->resolve()`. Partial reloads (`only: ['filters']`, and today's `only: ['personPreview']` on preview open) then skip the people query entirely. Today `$people` is built eagerly, so only the serialisation would be skipped.

#### 2. Event resource and marriage resource

**File**: `app/Http/Resources/EventResource.php`, `app/Http/Resources/MarriageResource.php`

**Intent**: Emit `is_confirmed` when either the `scans` relation is loaded (preview, unchanged) or the `scans_exists` attribute is present (index), and never emit `scans` from the attribute path. Emit `date_parts` for every event, and remove the duplicate from `MarriageResource`.

**Contract**:
- `is_confirmed?: bool`
- `scans?: list<...>` — only when the relation is loaded
- `date_parts: array{year: int|null, month: int|null, day: int|null, is_approximate: bool}`
- Update the PHPDoc array shapes (Larastan level 7).

#### 3. Frontend types

**File**: `resources/js/types/person.ts`

**Intent**: Move `date_parts` onto `PersonEvent`, and make the index event carry `is_confirmed`.

**Contract**:
- `PersonEvent` gains `date_parts`.
- A new `IndexPersonEvent = PersonEvent & { is_confirmed: boolean }`, with `Person.birth`/`death` typed as `IndexPersonEvent | null`.
- `Marriage` loses its own `date_parts` field; it inherits it.
- `types:check` must stay green for every consumer of `Person`.

#### 4. Tests

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Lock the new contract and change the one test that asserted the old one.

**Contract**:
- Change `:187-200` deliberately. It now asserts:
  - `persons.0.birth.is_confirmed` is `false`, and `true` after a scan is attached;
  - `scans` is still missing.
- New tests:
  - `filters.view` and `filters.sort` defaults;
  - `tabela` and each sort value echoed;
  - unknown, array and blank `view`/`sort` fall back;
  - `?sort=-zgon` without `view=tabela` (or with `view=lista`) echoes `sort: 'nazwisko'`;
  - `rootPersonId` equals the tree root, and is `null` when unset;
  - `date_parts` present on birth/death;
  - a partial reload with `only: ['filters']` (Inertia partial headers) returns `filters` and omits `persons`.
- Existing tests that must stay green:
  - `PersonsTest.php:21-185`, `:470`, `:742-802`, `:867-874`;
  - `MarriagesTest` (marriage `date_parts` unchanged in shape).
- Add a delete test: from `persons.index?q=Kow&view=tabela&sort=-urodzenie&person=<id>`, the redirect keeps `q`, `view` and `sort`.

### Success Criteria:

#### Automated Verification:

- Persons feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Marriage and preview tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventScansTest.php`
- Static analysis passes: `vendor/bin/sail bin phpstan analyse`
- PHP formatting applied: `vendor/bin/sail bin pint --dirty --format agent`
- Types pass: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- `/persons?view=foo&sort=bar` renders the list with no error, and the Inertia page props show `filters.view = 'lista'`, `filters.sort = 'nazwisko'`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Shared pieces — table, person avatar, list helpers

### Overview

Add the missing shared component and the second-consumer extraction, plus the pure helpers the views need, each with Vitest coverage.

### Changes Required:

#### 1. shadcn table

**File**: `resources/js/components/ui/table.tsx`

**Intent**: Add the standard shadcn `Table` markup components (Table, TableHeader, TableBody, TableRow, TableHead, TableCell, TableCaption), styled with existing tokens. This is marked in the PR as a new shared component, not a dependency.

**Contract**: shadcn table API; no Radix package. The file lives in the lint-ignored `components/ui/*`.

#### 2. Person avatar

**File**: `resources/js/components/persons/person-avatar.tsx`, `resources/js/lib/gender-styles.ts`, `resources/js/components/persons/person-modal-header.tsx`

**Intent**: Extract the initials avatar from the modal header into `PersonAvatar` with a size variant. Split `genderCard` into a hover-free `genderSurface` (reused by the avatar) and `genderCard` (surface + hover, unchanged for cards). The modal header switches to `PersonAvatar` with no visual change.

**Contract**:
- `PersonAvatar({ name: string, gender: 'male'|'female'|null, size?: 'sm'|'md'|'lg' })`
- `aria-hidden` initials, plus an sr-only gender label when gender is known.
- `genderCard` output is unchanged for existing callers.

#### 3. Index helpers

**File**: `resources/js/lib/persons-index.ts` (+ `resources/js/lib/persons-index.test.ts`)

**Intent**: Pure functions for the page:
- **Build the `/persons` query** from `{q, view, sort}`:
  - omit defaults and blanks;
  - drop `sort` when the view is `lista`.
- **Group persons by surname initial:**
  - uppercase with the `pl` locale;
  - groups ordered with `Intl.Collator('pl')`;
  - within a group, ordered by the name comparator from Critical Implementation Details (not server order);
  - the „Bez nazwiska” group comes last.
- **Sort persons for the table** by the rule in Critical Implementation Details.
- **Format an event meta string** for view A: „ur. {date}, {place}”, „zm. {date}, {place}”, and „zm. (data nieznana)” for a death with no date.

**Contract**:
- `buildPersonsQuery(filters): Record<string, string>`
- `groupBySurnameInitial(persons): {key, title, persons}[]`
- `sortPersons(persons, sort): Person[]`
- `formatEventMeta(prefix, event): string | null`
- Vitest cases:
  - defaults omitted;
  - `sort` dropped on lista;
  - Ł/Ś/Ż groups ordered after L/S/Z;
  - null surname last;
  - unknown dates last in both directions;
  - a missing month comes before a known month in the same year;
  - `-urodzenie` reversal, with ascending name tie-break;
  - `nazwisko` puts Ł after L and Ż after Z; `-nazwisko` keeps a null surname last;
  - death without a date.

### Success Criteria:

#### Automated Verification:

- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Types pass: `vendor/bin/sail npm run types:check`
- Format + lint pass: `vendor/bin/sail npm run check`

#### Manual Verification:

- The person modal header avatar looks unchanged in light and dark mode (open any person from `/tree`)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: The view — toolbar, Lista, Tabela, states

### Overview

Rebuild `/persons` as the canvas boards: a toolbar with search and a Lista | Tabela switcher, view A, view B, and the empty, no-results and searching states, responsive down to 390 px.

### Changes Required:

#### 0. Before shots (first, before touching the page)

**File**: `context/changes/persons-list-views/screenshots/shots.mjs`, `screenshots/before/`

**Intent**: Write the Phase 4 script now, following the person-modal `shots.mjs` pattern, and run it against the current `/persons` into `before/`. Phase 4 re-runs it into `after/`.

#### 1. Page

**File**: `resources/js/pages/persons/index.tsx`

**Intent**: Read `filters.{q,view,sort}` and `rootPersonId`. Every visit goes through `buildPersonsQuery`:

| Visit | Options | Reloads |
|---|---|---|
| Search debounce (300 ms, `replace`) | `preserveState`, `preserveScroll` | full |
| Switcher | `preserveState`, `preserveScroll` | `only: ['filters']` |
| Sort header | `preserveState`, `preserveScroll` | `only: ['filters']` |

- Track in-flight search visits for the searching state.
- Keep the heading, description and „Dodaj osobę” (`PersonCreateDialog`).
- Keep the existing visibility rule for search and switcher (`persons.length > 0 || q !== ''`).

**Contract**:
- Props gain `rootPersonId: number | null`.
- Filters gain `view`/`sort`.
- The switcher is a `ToggleGroup type="single"` with „Lista”/„Tabela” and icons, and ignores `''`.

#### 2. View A

**File**: `resources/js/components/persons/persons-list.tsx`

**Intent**: Render `groupBySurnameInitial` groups as sections with a heading and count („6 osób”, „1 osoba”, „3 osoby”), plus jump links. Each row is a `PersonPreviewLink` with:
- `PersonAvatar`;
- the name, and the „Probant” badge when `id === rootPersonId`;
- the meta line from `formatEventMeta`, with a ✓ (`CheckIcon`, sr-only „potwierdzone aktem”) after a confirmed date;
- a chevron.

Rows are divided inside one bordered container per group, with hover and focus-visible states.

**Contract**: `PersonsList({ persons, rootPersonId })`. The row `href` is the preview link, as today.

#### 3. View B

**File**: `resources/js/components/persons/persons-table.tsx`

**Intent**: Render `sortPersons` rows in `ui/table`:
- **Osoba:** avatar + name `PersonPreviewLink` + „Probant”.
- **Urodzenie/Zgon:** date (tabular numerals) + ✓, with the place on a second muted line. „—” when absent, „data nieznana” for a dated-less death.
- **Zamieszkanie**, **Wyznanie** (label): „—” when empty.
- **⋯:** `DropdownMenu` with „Otwórz podgląd” (`PersonPreviewLink` via `asChild`) and „Pokaż na drzewie” (`Link` to `treeShow(id)`). The icon button has `aria-label` „Akcje: {name}”.

Sortable headers are buttons with an arrow icon, `aria-sort` on `th`, and a caption for screen readers. The wrapper uses `overflow-x-auto`, with a sticky first column on narrow screens.

**Contract**: `PersonsTable({ persons, rootPersonId, sort, onSortChange })`. Clicking the active column toggles direction; a new column starts ascending.

#### 4. States

**File**: `resources/js/pages/persons/index.tsx` (or a small `persons-empty-state.tsx`)

**Intent**: Replace the plain `Card` messages with the canvas empty states. The copy stays as today:

| State | Content |
|---|---|
| Empty tree | icon, „Nie dodano jeszcze żadnej osoby”, „Zacznij od dodania pierwszej osoby do drzewa.”, „Dodaj osobę” |
| No results | icon, „Brak wyników”, „Nie znaleziono osób pasujących do „{q}”.” (`break-words`), „Wyczyść wyszukiwanie” (clears `q`, keeps view) |
| Search in flight | spinner inside the input, current results at reduced opacity with `aria-busy` |

**Contract**: No new props. „Wyczyść wyszukiwanie” resets the local search state and visits through `buildPersonsQuery`.

### Success Criteria:

#### Automated Verification:

- Format + lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Build passes: `vendor/bin/sail npm run build`
- Persons feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`

#### Manual Verification:

- Typing in search on `?view=tabela&sort=-urodzenie` keeps the table and the sort, and Back does not step through keystrokes
- Switching Lista ↔ Tabela keeps `q`, the Lista URL has no `sort`, and refresh restores the chosen view
- Opening a person from either view and closing the modal returns to the same view, search and sort
- Table sort by Urodzenie/Zgon puts people without dates last in both directions
- Keyboard: Tab reaches the switcher, sort headers, row links and ⋯ menus, with visible focus
- At 390 px the list reads well and the table scrolls sideways with the Osoba column sticky

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Kitchen sink and screenshot gate

### Overview

Make the demo tree exercise every row state, then run the out-of-repo screenshot gate against the canvas.

### Changes Required:

#### 1. Demo seeder

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Append (at the end, so existing id offsets hold) people covering the states the list shows:
- one person with full data: day-precise birth with place and a linked scan, death with place, residence, religion;
- one person with no surname and no dates.

**Contract**: Append-only; reuse the existing `attachScan` helper. Create via factories directly where the `person()` helper cannot express the case (null surname, places).

#### 2. Screenshot gate

**File**: `context/changes/persons-list-views/screenshots/shots.mjs`, `screenshots/{before,after}/`, `screenshots/verdict.md`

**Intent**: Follow the person-modal gate: Playwright from the npx cache, Sail at `localhost:8000`, the demo user. Capture:
- Lista and Tabela in light and dark at desktop and 390 px;
- table sorted by `-urodzenie`;
- the searching state;
- the no-results state;
- the empty tree: the script registers a throwaway user through `/register` (unique e-mail per run), whose tree starts empty.

`verdict.md` records one line per state against the canvas board. Earlier gates' `/persons` selectors are re-run once to confirm they still find people.

**Contract**: Baselines are never blind-updated. `before/` comes from Phase 3 §0; this phase writes only `after/`.

### Success Criteria:

#### Automated Verification:

- Seeder runs cleanly: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Screenshot script completes and writes every listed shot to `screenshots/after/`

#### Manual Verification:

- `verdict.md` shows every state matching the canvas (A, B, Stany, Mobile), with differences explained or fixed
- The person-modal gate's `/persons` person lookup still resolves ids

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Vitest `persons-index.test.ts` covers:
  - query building (defaults omitted, `sort` only in the table);
  - surname grouping (Polish letters, null surname);
  - table sorting (unknown dates last both ways, partial dates, ties);
  - meta formatting.

### Integration Tests:

- Pest `PersonsTest.php`:
  - `filters.view`/`filters.sort` normalisation and echo;
  - `rootPersonId`;
  - `is_confirmed` boolean without `scans`;
  - `date_parts`;
  - delete redirect keeps `q`/`view`/`sort`.
- The existing search, isolation and preview tests stay green unchanged.

### Manual Testing Steps:

1. `/persons` → Lista grouped K…Z, „Bez nazwiska” last; jump link scrolls to a group.
2. Switch to Tabela, sort Urodzenie ↓/↑; people without dates stay last.
3. Type „Kow” → spinner and dimmed results → filtered table; Back leaves the page, not keystroke by keystroke.
4. ⋯ → „Otwórz podgląd” opens the modal; close → same table, sort and search.
5. ⋯ → „Pokaż na drzewie” lands on `/tree/{id}`.
6. Search „Kowalczyk” → „Brak wyników” → „Wyczyść wyszukiwanie” → full list in the same view.
7. Dark mode and 390 px for both views.

## Performance Considerations

- `withExists('scans')` adds one subquery column to the events query, not a query per person. The payload grows by a boolean and four small ints per event.
- The lazy `persons` closure means view/sort switches and preview opens run no people query.
- Grouping and sorting run in memory over the whole tree, which is expected to be hundreds of people (`person-search/plan.md:173`). Memoise them on `persons` + `sort`.

## Migration Notes

None. No schema change. `date_parts` and `is_confirmed` are additive for existing consumers. The marriage payload shape is unchanged.

## References

- Research: `context/changes/persons-list-views/research.md`
- Design canvas: https://claude.ai/artifact/LT3qzYRdcYfkxZchBJ7LEN
- Search contract: `context/archive/2026-09-25-person-search/plan.md`
- Birth-date ordering rule: `app/Support/PersonOrdering.php`
- Avatar source: `resources/js/components/persons/person-modal-header.tsx:50-58`
- Screenshot gate precedent: `context/archive/2026-09-29-person-modal/screenshots/shots.mjs`, `verdict.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — filters, proband, confirmation and date parts

#### Automated

- [x] 1.1 Persons feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php` — 69cbefa
- [x] 1.2 Marriage and preview tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventScansTest.php` — 69cbefa
- [x] 1.3 Static analysis passes: `vendor/bin/sail bin phpstan analyse` — 69cbefa
- [x] 1.4 PHP formatting applied: `vendor/bin/sail bin pint --dirty --format agent` — 69cbefa
- [x] 1.5 Types pass: `vendor/bin/sail npm run types:check` — 69cbefa

#### Manual

- [x] 1.6 `/persons?view=foo&sort=bar` renders the list with no error, and the Inertia page props show `filters.view = 'lista'`, `filters.sort = 'nazwisko'` — 69cbefa

### Phase 2: Shared pieces — table, person avatar, list helpers

#### Automated

- [x] 2.1 JS unit tests pass: `vendor/bin/sail npm run test:js`
- [x] 2.2 Types pass: `vendor/bin/sail npm run types:check`
- [x] 2.3 Format + lint pass: `vendor/bin/sail npm run check`

#### Manual

- [x] 2.4 The person modal header avatar looks unchanged in light and dark mode (open any person from `/tree`)

### Phase 3: The view — toolbar, Lista, Tabela, states

#### Automated

- [ ] 3.1 Format + lint pass: `vendor/bin/sail npm run check`
- [ ] 3.2 Types pass: `vendor/bin/sail npm run types:check`
- [ ] 3.3 JS unit tests pass: `vendor/bin/sail npm run test:js`
- [ ] 3.4 Build passes: `vendor/bin/sail npm run build`
- [ ] 3.5 Persons feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`

#### Manual

- [ ] 3.6 Typing in search on `?view=tabela&sort=-urodzenie` keeps the table and the sort, and Back does not step through keystrokes
- [ ] 3.7 Switching Lista ↔ Tabela keeps `q`, the Lista URL has no `sort`, and refresh restores the chosen view
- [ ] 3.8 Opening a person from either view and closing the modal returns to the same view, search and sort
- [ ] 3.9 Table sort by Urodzenie/Zgon puts people without dates last in both directions
- [ ] 3.10 Keyboard: Tab reaches the switcher, sort headers, row links and ⋯ menus, with visible focus
- [ ] 3.11 At 390 px the list reads well and the table scrolls sideways with the Osoba column sticky

### Phase 4: Kitchen sink and screenshot gate

#### Automated

- [ ] 4.1 Seeder runs cleanly: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- [ ] 4.2 Screenshot script completes and writes every listed shot to `screenshots/after/`

#### Manual

- [ ] 4.3 `verdict.md` shows every state matching the canvas (A, B, Stany, Mobile), with differences explained or fixed
- [ ] 4.4 The person-modal gate's `/persons` person lookup still resolves ids
