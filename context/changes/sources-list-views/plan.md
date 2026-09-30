# Sources list — Gallery and Table views Implementation Plan

## Overview

`/sources` gets a toolbar and two views the user switches between: **A — Galeria** (card grid, for recognising a document by its image) and **B — Tabela** (sortable columns, for finding it by year, type and entry number). The design is the canvas https://claude.ai/artifact/McYg2Lo8Z2wZcKEHAoqpzU (boards `Main`, `Rejestr`, `Stany`, `Mobile`; board C „Lista z podglądem” parked). Named motif: „stykówka + rejestr”.

The toolbar holds a search by name, a kind filter with counters (Wszystkie / Akty / Nagrobki / Inne) and the Galeria | Tabela switcher. The list state lives in the URL as `q`, `kind`, `view` and `sort`. Each source shows „potwierdza N wydarzeń” or „bez powiązań”.

The work is split into the server contract and demo data first, then shared components moved out of the `/persons` files, then the view, then a screenshot gate. The change is outside the roadmap, like `persons-list-views`; it meets parked v2:FR-006 („wyszukać skan po nazwie”, `context/foundation/roadmap.md:421`) along the way.

## Current State Analysis

- **Controller.** `SourceController::index` returns the whole tree's sources, unpaginated, newest first, and reads no query parameter (`app/Http/Controllers/SourceController.php:31-40`). Props: `sources`, `kinds`, `recordTypes`.
- **Payload gap.** `SourceResource` has no link information (`app/Http/Resources/SourceResource.php:44-65`). It has three callers: the index (`SourceController.php:36`), the show page (`:69`) and the picker's `availableSources` (`app/Http/Middleware/HandleInertiaRequests.php:55-57`).
- **Current page** (`resources/js/pages/sources/index.tsx`):
  - a card grid with no base column, which breaks at 390 px (`:70`; research charge A1);
  - a one-line truncated name (`:83-88`) and up to four stacked meta lines (`:89-113`);
  - an `<h2>` page heading with a 32 px bottom margin (`:38-41`, `resources/js/components/heading.tsx:17-25`);
  - a card link without the app's focus ring (`:75-79`);
  - its own empty state, unlike the one on `/persons` (`:50-68`).
- **Pieces built for `/persons` but private to its files:**
  - the empty state (`resources/js/components/persons/persons-empty-state.tsx:5-33`);
  - the search field (`resources/js/pages/persons/index.tsx:147-170`);
  - the view switcher with its empty-value guard (`:172-194`);
  - the sortable column head and its helpers (`resources/js/components/persons/persons-table.tsx:51-114`).
- **Demo data.** `TreeViewDemoSeeder` creates 18 sources and none has a file on disk, so every thumbnail is the fallback icon (`database/factories/SourceFactory.php:29`).
- **Fuller detail:** `context/changes/sources-list-views/research.md` (15 charges, 12 decisions).

## Desired End State

**Server contract** — `GET /sources` accepts and echoes four parameters, and filters by none of them:

| Param | Accepted values | Missing or invalid |
|---|---|---|
| `q` | any string, trimmed, at most 100 characters | `''` |
| `kind` | `akty`, `nagrobki`, `inne` | `wszystkie` |
| `view` | `tabela` | `galeria` |
| `sort` | `nazwa`, `rodzaj`, `rok`, `potwierdza`, `dodano` and each with a leading `-` (only when `view=tabela`) | `-dodano`; also forced to `-dodano` whenever `view` is `galeria` |

- `filters` echoes all four: `{q, kind, view, sort}`.
- `sources` is always the whole tree in today's order (newest first), whatever the parameters say.
- Each source on the index carries `events_count`: the number of linked events. A marriage akt linked to one marriage counts 1.

**Toolbar**
- Search field: label „Szukaj źródła”, placeholder „Szukaj po nazwie”.
- Kind filter: four items with counters. Counters are set in the text colour at regular weight.
- View switcher: Galeria | Tabela. Below 640 px it shows icons only, next to the search field, and the kind filter takes a full-width row under them (board `Mobile`).
- All controls are 36 px high at every width.
- The toolbar is hidden only when the tree has no sources.

**View A (Galeria)**
- Grid: 2 columns by default, 3 from `lg`, 4 from `xl`.
- Each card is one link to the source page and holds:
  - a 4:3 thumbnail, cropped to fill; the fallback icon when there is no thumbnail;
  - the name, clamped to two lines, full text in `title`;
  - the summary line, e.g. „akt urodzenia · 1878 · nr 47”;
  - pochodzenie on one truncated line, when set;
  - „potwierdza 1 wydarzenie” / „potwierdza 2 wydarzenia” / „potwierdza 5 wydarzeń”, or muted „bez powiązań”, pinned to the bottom of the card.
- Order: always the server order (newest first).

**View B (Tabela)**
- Columns: Źródło (40 px thumbnail + name) / Rodzaj / Rok / Nr wpisu / Pochodzenie / Potwierdza / Dodano / ⋯.
- Header buttons sort Źródło, Rodzaj, Rok, Potwierdza and Dodano, with `aria-sort`.
- The ⋯ menu has „Otwórz” and „Edytuj”.
- On narrow screens the table scrolls sideways with the Źródło column sticky.

**States**

| State | When | Title | Description | Action |
|---|---|---|---|---|
| Empty tree | the tree has no sources | „Nie dodano jeszcze żadnego źródła” | „Zacznij od dodania aktu, zdjęcia nagrobka albo innego dokumentu.” | „Dodaj źródło” |
| No match | nothing matches the search in any kind | „Brak wyników” | „Nie znaleziono źródeł pasujących do „{q}”.” | „Wyczyść wyszukiwanie” (clears `q`; keeps kind, view, sort) |
| Empty kind | the chosen kind has no match, other kinds have | „Brak wyników” | with a search: „Wśród {aktów / nagrobków / innych źródeł} nic nie pasuje do „{q}”.”; without: „Nie masz jeszcze {aktów / nagrobków / innych źródeł}.” | „Pokaż wszystkie rodzaje” (sets kind to Wszystkie; keeps `q`, view, sort) |

**Verification.** Pest covers the contract, Vitest covers the helpers, and the screenshot gate `verdict.md` records light/dark shots at 1280 and 390 px of both views and every state, plus control shots of `/persons`.

### Key Discoveries:

- `pluralPhrase` already exists (`resources/js/lib/plural.ts`, six consumers). Research charge C7 needs no new helper.
- The seeder already creates an empty-tree user, `tree-demo-empty@example.com` (`database/seeders/TreeViewDemoSeeder.php:53`, `:62-65`). The gate needs no throwaway registration.
- `SourceEditDialog` opens only through its own `trigger` (`resources/js/components/sources/source-edit-dialog.tsx:117`). A dialog opened from a menu item must be controlled from outside the menu.
- `SourceController::update` returns `back()` (`SourceController.php:100`), so an edit made from the list lands on the same list URL with its query.
- The seeder deletes the demo users with a plain query (`TreeViewDemoSeeder.php:60`), which removes rows and leaves files on disk.
- Seeded sources of one run share a `created_at` second; the server breaks the tie with `id` descending (`SourceController.php:33`).
- Under the search „lipn” the seed gives akty 4, nagrobki 1, inne 0. That reaches the „counter 0” state without new rows.
- `SourcesTest.php:45-47` lists every resource key with `hasAll`; a new key must be added there.
- Class strings shared between components live in `resources/js/lib/` (`gender-styles.ts`), not in component files.

## What We're NOT Doing

- **No delete from the list** and no ⋯ menu on gallery cards. Deleting stays on the source page (decision 1).
- **No server-side search or filtering**, and no pagination (decision 2).
- **No search beyond the name:** not by pochodzenie, note, year or entry number (decision 4). A source with a name is not found by its file name.
- **No diacritic folding:** „swietego” does not find „Świętego”, as on `/persons`.
- **No carrying of list parameters** through the source page breadcrumb or the post-delete redirect; both go to the bare list (charge A7, accepted in decision 5).
- **No new token and no new size variant** (decisions 6 and 7).
- **No change to the source page header line or the picker tile.** Charge C5 is closed for the list only; the other two places are a follow-up.
- **No visual change to `/persons`.** Its heading (charge A5) is a separate small fix.
- **No custom clear button inside the search field.** The canvas draws one; adding it would change `/persons`. Clearing is offered in the „No match” state.
- No board C „Lista z podglądem”.
- No image files committed to the repo, no new seeded rows, no new npm or composer package, no new shadcn component.
- No in-repo screenshot test and no Playwright dependency; the gate stays a per-change script.
- No persistence of the chosen view beyond the URL.
- No roadmap entry (decision 12).

## Implementation Approach

**Contract before pixels.** Phase 1 fixes the server contract, its tests and the demo data, so the UI phases consume a stable payload and see real images.

**Extraction before the view.** Phase 2 moves four pieces out of the `/persons` files into shared components and switches `/persons` to them. Control shots taken before and after prove `/persons` did not change. The new view then has nothing to copy.

**The browser filters, the URL remembers.** The whole list is in the page. Search, kind filter and sort are pure functions over it, tested with Vitest. The server only normalises the four parameters and echoes them, so a refresh or a shared link restores the state.

**One query builder.** Every `/sources` visit the page makes goes through one helper, so no control drops another's parameter.

## Critical Implementation Details

- **Search predicate.** The query is split on whitespace into words. A source matches when every word occurs in its `display_name` (the name, or the file name when it has none). Both sides are compared after `toLocaleLowerCase('pl')`. A blank query matches every source.
- **Counters.** Each counter is the number of sources that match the search, per kind; „Wszystkie” is their sum. A kind with counter 0 is disabled unless it is the chosen one. „Wszystkie” is never disabled.
- **Sort rule.**

  | `sort` | Primary key | Missing value |
  |---|---|---|
  | `nazwa` | `display_name`, `Intl.Collator('pl', { numeric: true })` | — |
  | `rodzaj` | kind in the filter's order (akt, nagrobek, inne); among akts the record type label, an akt without a type last | — |
  | `rok` | `year` | last in both directions |
  | `potwierdza` | `events_count` | 0 is a value, not a missing one |
  | `dodano` | `uploaded_at`, then `id`, as one key | — |

  - A leading `-` reverses the primary key only. Ties break by `display_name`, then `id`, always ascending.
  - `dodano` is the exception: `id` is part of its key, so `-dodano` returns exactly the server order, which is the gallery order.
  - A click on the active column flips the direction; a new column starts ascending.
  - `sort` is honoured only when `view=tabela`. Switching to Galeria drops `sort` from the URL.
- **Visits.** The search text is local state and filters the list at once. The URL follows after 300 ms with `replace: true`. Kind, view and sort visits add a history entry. All of them use `preserveState`, `preserveScroll` and `only: ['filters']`. History navigation that changes `q` updates the input, as on `/persons` (`pages/persons/index.tsx:99-105`).
- **Edit from a row.** The edit dialog is rendered once, outside the dropdown menu, and opened by state that holds the chosen source. A dialog nested in a menu item unmounts when the menu closes.
- **Seeder files.** File writes are not part of the database transaction. Before deleting the demo users, the seeder deletes each demo tree's `sources/{tree id}` directory on the `local` disk.

## Phase 1: Contract and demo data

### Overview

The server normalises and echoes the four list parameters and sends `events_count` on the index. Pest locks the contract. The demo seeder gives eight of its sources a drawn image and a real thumbnail.

### Changes Required:

#### 1. Index controller

**File**: `app/Http/Controllers/SourceController.php`

**Intent**: Normalise `q`, `kind`, `view` and `sort` like `PersonController` does (`app/Http/Controllers/PersonController.php:27-92`): an unknown or non-string value falls back to the default, without a validation error. Echo them in `filters`. Load the linked-event count. Do not filter or reorder the list.

**Contract**:
- `filters: {q: string, kind: 'wszystkie'|'akty'|'nagrobki'|'inne', view: 'galeria'|'tabela', sort: string}`; `sort` is one of the ten keys of the Desired End State table and is `-dodano` unless `view` is `tabela`.
- `sources` becomes a closure, so a partial reload with `only: ['filters']` runs no sources query.
- The query adds `withCount('events')`; scoping and ordering stay `tree->sources()->latest()->orderByDesc('id')`.
- Private normalisers in this controller. The four-line copy of `normalizeSearchQuery` is deliberate; no shared class.

#### 2. Source resource

**File**: `app/Http/Resources/SourceResource.php`

**Intent**: Emit the linked-event count only when it was loaded, so the show page and the picker payloads keep their shape.

**Contract**: `events_count?: int` through `whenCounted('events')`; PHPDoc array shape updated (Larastan level 7).

#### 3. Frontend types

**File**: `resources/js/types/source.ts`

**Intent**: Type the new field without claiming it on every source.

**Contract**: `Source` gains `events_count?: number`. New `IndexSource = Source & { events_count: number }`.

#### 4. Tests

**File**: `tests/Feature/SourcesTest.php`

**Intent**: Lock the new contract and extend the one test that pins the resource shape.

**Contract**:
- Extend the `hasAll([...])` list at `:45-47` with `events_count`; the order assertions of that test stay.
- New tests:
  - `events_count` is 0 for an unlinked source and 2 for a source linked to two events;
  - a source linked to one marriage event with two participants has `events_count` 1;
  - the show page `source` and the `availableSources` payload have no `events_count` key;
  - `filters` defaults are `{q: '', kind: 'wszystkie', view: 'galeria', sort: '-dodano'}`;
  - each valid `kind`, `view=tabela` and each of the ten `sort` values is echoed;
  - unknown, array and blank `kind`/`view`/`sort` fall back; `?sort=rok` without `view=tabela` echoes `-dodano`;
  - `q` is trimmed, cut to 100 characters and `''` for a non-string;
  - `?q=xyz&kind=nagrobki` still returns every source of the tree, in the same order;
  - a partial reload with `only: ['filters']` returns `filters` and omits `sources`.
- Existing tests that must stay green: the whole of `SourcesTest.php`, and `PersonsTest.php:1010` (the `availableSources` partial reload).

#### 5. Demo seeder

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Give eight existing sources a file and a thumbnail so the gate can show the gallery's main state, and keep ten on the fallback. Images are drawn with GD in the seeder: a paper or stone tone with ruled lines, in three proportions. Thumbnails come from the real `GenerateSourceThumbnail`.

**Contract**:
- No new rows; ids and creation order stay. A last step in `seedTree()` updates `path`, `thumbnail_path`, `width`, `height` and `size` of the chosen sources, found by `original_name`.
- With an image: `akt-urodzenia-jan.jpg`, `opisany-akt-lipnica-1878.jpg`, `akt-zgonu-lipnica-1932.jpg` (portrait); `akt-slubu-jan-anna.jpg`, `akt-z-dlugim-opisem.jpg`, `zaproszenie-slubne.jpg` (landscape); `nagrobek-opisany.jpg` (portrait, stone); `nagrobek-genowefy.jpg` (very tall, stone). Each kind therefore has both an image and a fallback.
- `run()` deletes the `sources/{tree id}` directory of both demo users' trees before deleting the users.
- The class docblock gets a paragraph naming the image states.

### Success Criteria:

#### Automated Verification:

- Sources feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php`
- Other consumers of the source payload still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventSourcesTest.php`
- Static analysis passes: `vendor/bin/sail bin phpstan analyse`
- PHP formatting applied: `vendor/bin/sail bin pint --dirty --format agent`
- Types pass: `vendor/bin/sail npm run types:check`
- Seeder runs twice in a row without error, and the demo tree's source folder then holds the files of one run only: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`

#### Manual Verification:

- `/sources?view=foo&sort=bar&kind=baz` renders today's list with no error, and the Inertia page props show `filters.view = 'galeria'`, `filters.sort = '-dodano'`, `filters.kind = 'wszystkie'`
- As `tree-demo@example.com`, `/sources` in its current layout shows a real image on the eight chosen sources and the fallback icon on the other ten

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Shared components and list helpers

### Overview

Take the „before” shots. Move the empty state, the search field, the switcher and the sortable head out of the `/persons` files and switch `/persons` to them with no visual change. Make `SourceThumbnail` and `SourceEditDialog` fit a table row. Add the pure list helpers with Vitest coverage.

### Changes Required:

#### 0. Before shots (first, before touching any component)

**File**: `context/changes/sources-list-views/screenshots/shots.mjs`, `screenshots/before/`

**Intent**: Write the gate script now, following `context/archive/2026-09-30-matching-sources-first/screenshots/shots.mjs` (Playwright from the npx cache, Sail at `localhost:8000`, the demo users, the `LD_LIBRARY_PATH` note). Run it into `before/`.

**Contract**:
- Arguments: output directory, and a set: `persons`, `sources` or both.
- `/persons` control set, light and dark at 1280 and 390 px: Lista, Tabela, Tabela sorted by `-urodzenie`, no results, empty tree.
- `/sources` before set, light and dark at 1280 and 390 px: the current list.
- A compare step: byte comparison of same-named files in two directories, one result line per file.

#### 1. Empty state (C1)

**File**: `resources/js/components/empty-state.tsx`, `resources/js/components/persons/persons-empty-state.tsx`

**Intent**: Move the private `EmptyState` to a shared file. `PersonsEmptyTree` and `PersonsNoResults` keep their names and copy and import it.

**Contract**: `EmptyState({ icon, title, description, action })`, markup and classes unchanged.

#### 2. Search field (C3)

**File**: `resources/js/components/search-field.tsx`, `resources/js/pages/persons/index.tsx`

**Intent**: Move the icon, spinner and padded `Input` into one component. The persons page passes its own strings and its searching flag.

**Contract**: `SearchField({ value, onChange, placeholder, ariaLabel, busy?, busyLabel?, maxLength?, className? })`; renders the same markup as `pages/persons/index.tsx:147-170`. Without `busy` it always shows the search icon.

#### 3. Segmented toggle (C4)

**File**: `resources/js/components/segmented-toggle.tsx`, `resources/js/pages/persons/index.tsx`

**Intent**: One component owns the `ToggleGroup` outline markup and the guard against the empty value (`pages/persons/index.tsx:177-183`). It serves the persons view switcher, the sources view switcher and the sources kind filter.

**Contract**:
- `SegmentedToggle<T extends string>({ value, onValueChange, ariaLabel, items, iconOnlyBelowSm?, className? })`.
- `items: { value: T, label: string, icon?: ReactNode, count?: number, disabled?: boolean }[]`.
- `onValueChange` is never called with `''`.
- A `count` renders after the label at 12 px, regular weight, tabular numerals, in the text colour (closes charge T1 without a token).
- `iconOnlyBelowSm` keeps the label for screen readers and hides it visually below 640 px.
- With the persons items and no optional props, the output equals today's switcher.

#### 4. Table parts (C2)

**File**: `resources/js/components/table-parts.tsx`, `resources/js/lib/table-sort.ts` (+ `table-sort.test.ts`), `resources/js/lib/table-styles.ts`, `resources/js/components/persons/persons-table.tsx`

**Intent**: Move the sortable head, the muted dash and the sticky-first-column classes out of the persons table, generic over the column name. `PersonsTable` imports them.

**Contract**:
- `SortableHead<C extends string>({ column, label, sort, onSortChange, className? })`; same button, icons, `aria-sort` and sr-only text as `persons-table.tsx:67-114`.
- `EmptyCell()`: the muted „—”.
- `columnOf(sort)` and `nextSort(current, column)` in `table-sort.ts`, generic over the column.
- `stickyHeadCell` and `stickyCell` class strings in `table-styles.ts`.
- Vitest: `nextSort` flips the active column and starts a new one ascending.

#### 5. Source thumbnail (C6)

**File**: `resources/js/components/sources/source-thumbnail.tsx`

**Intent**: Let a consumer set the fallback icon size and mark the image as decorative when the name is printed next to it. The three existing consumers pass neither and render as today.

**Contract**: adds `iconClassName?: string` (default `size-10`) and `decorative?: boolean` (empty `alt`). The aspect ratio is already overridable through `className`.

#### 6. Edit dialog

**File**: `resources/js/components/sources/source-edit-dialog.tsx`

**Intent**: Allow opening the dialog from outside, for the row menu. The source page keeps using `trigger`.

**Contract**: `trigger` becomes optional; optional `open` and `onOpenChange` control the dialog when given. The reset on close stays.

#### 7. List helpers

**File**: `resources/js/lib/sources-index.ts` (+ `sources-index.test.ts`)

**Intent**: Pure functions for the page, following `resources/js/lib/persons-index.ts`.

**Contract**:
- Types `SourcesKindFilter`, `SourcesView`, `SourcesSort`, `SourcesFilters`.
- `buildSourcesQuery(filters): Record<string, string>` — omits defaults and blanks; drops `sort` when the view is `galeria`.
- `filterSources(sources, q, kind): { visible, counts }` — by the search predicate and counter rule in Critical Implementation Details.
- `sortSources(sources, sort): IndexSource[]` — by the sort rule.
- `eventsLabel(count): string | null` — „1 wydarzenie”, „2 wydarzenia”, „5 wydarzeń” through `pluralPhrase`; `null` for 0.
- Vitest cases:
  - defaults omitted, `sort` dropped on galeria;
  - „1878 lipnica” finds „Urodzenia Lipnica 1878 s. 12”; case is ignored; „swietego” does not find „Świętego”;
  - a named source is not found by its file name; an unnamed one is;
  - counters follow the search and ignore the chosen kind;
  - `-dodano` returns the input order for a server-ordered list with equal timestamps;
  - sources without a year are last for `rok` and `-rok`;
  - `potwierdza` ascending starts with 0;
  - `rodzaj` orders akt, nagrobek, inne, with an akt without a type last among akts;
  - ties break by name then id, ascending, in both directions;
  - the three plural forms and `null` for 0.

#### 8. Summary line (C5, list only)

**File**: `resources/js/lib/source-description.ts` (+ `source-description.test.ts`)

**Intent**: One helper for the kind text and the card line. `formatSourceMeta` and its callers (the picker, the old card) stay as they are.

**Contract**:
- `sourceKindText(source): string` — „akt urodzenia” (short kind + type label), „akt metrykalny” for an akt without a type, „nagrobek”, „inne”.
- `formatSourceSummary(source): string` — kind text, year, „nr {entry_number}”, joined with „ · ”; ends with „dodano {date}” when `showsUploadDate(source)` is true.
- Vitest: „akt urodzenia · 1878 · nr 47”, „nagrobek · 1932”, „akt metrykalny”, „inne · dodano 24.09.2026”, and no date when pochodzenie is set.

### Success Criteria:

#### Automated Verification:

- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Types pass: `vendor/bin/sail npm run types:check`
- Format + lint pass: `vendor/bin/sail npm run check`
- Build passes: `vendor/bin/sail npm run build`
- Persons and sources feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php`
- The script writes every `/persons` control shot to `screenshots/after/` and its compare step prints a result for each against `screenshots/before/`

#### Manual Verification:

- `/persons` looks and behaves as before in light and dark: search spinner, Lista | Tabela switcher, sort headers, both empty states; every control shot is identical or its difference is explained
- „Edytuj” on a source page still opens the dialog and saves
- Thumbnails in the source picker and in the person modal's „Źródła” tab look unchanged

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: The view — toolbar, Galeria, Tabela, states

### Overview

Rebuild `/sources` as the canvas boards: the toolbar, view A, view B and the three empty states, responsive down to 390 px. The page heading becomes an `<h1>`.

### Changes Required:

#### 1. Page

**File**: `resources/js/pages/sources/index.tsx`

**Intent**: Read `filters`, hold the search text as local state, and render the toolbar, the live status and one of the two views or an empty state. Every visit goes through `buildSourcesQuery`:

| Visit | History | Reloads |
|---|---|---|
| Search text (300 ms after typing) | `replace` | `only: ['filters']` |
| Kind filter, view switcher, sort header | new entry | `only: ['filters']` |
| „Wyczyść wyszukiwanie”, „Pokaż wszystkie rodzaje” | new entry | `only: ['filters']` |

**Contract**:
- Props: `sources: IndexSource[]`, `kinds`, `recordTypes`, `filters: SourcesFilters`.
- `Heading` uses `variant="page"` (an `<h1>`, no bottom margin); title, description and „Dodaj źródło” (`SourceCreateDialog`) stay.
- Toolbar: `SearchField` (no `busy`, `maxLength` 100), `SegmentedToggle` for the kind with counters from `filterSources`, `SegmentedToggle` for the view with `iconOnlyBelowSm`.
- A polite live region: empty when neither search nor kind is set; otherwise „Brak wyników” or „Znaleziono: {N źródło / źródła / źródeł}”.
- The list shown is `filterSources(...).visible`; the table sorts it with `sortSources`.

#### 2. View A

**File**: `resources/js/components/sources/sources-gallery.tsx`

**Intent**: Render the cards of the Desired End State. The grid has a base column count and the cards can shrink, which closes the 390 px defect (charge A1). The card link gets the app's focus ring (charge A6).

**Contract**:
- `SourcesGallery({ sources })`.
- Grid `grid-cols-2`, `lg:grid-cols-3`, `xl:grid-cols-4`; every card `min-w-0`.
- `SourceThumbnail` with a 4:3 aspect and `decorative`.
- Name: two-line clamp, long words may break, `title` with the full name.
- Lines: `formatSourceSummary`; pochodzenie truncated with `title`; `eventsLabel` with a link icon, or muted „bez powiązań”.

#### 3. View B

**File**: `resources/js/components/sources/sources-table.tsx`

**Intent**: Render `sortSources` rows in `ui/table` with the shared table parts.

| Column | Content | Empty |
|---|---|---|
| Źródło | 40 px thumbnail (small fallback icon) + name, a link to the source page; the name may wrap | — |
| Rodzaj | `sourceKindText` | — |
| Rok | year, tabular numerals | „—” |
| Nr wpisu | entry number | „—” |
| Pochodzenie | one truncated line with `title` | „—” |
| Potwierdza | `eventsLabel` | muted „bez powiązań” |
| Dodano | upload date, `pl-PL`, tabular numerals | — |
| ⋯ | menu „Otwórz” (link to the source page), „Edytuj” (opens `SourceEditDialog`) | — |

**Contract**:
- `SourcesTable({ sources, sort, onSortChange, kinds, recordTypes })`.
- `SortableHead` on Źródło, Rodzaj, Rok, Potwierdza, Dodano; a screen-reader caption naming the sort and the sortable headers.
- The ⋯ button has `aria-label` „Akcje: {name}”.
- One `SourceEditDialog`, controlled, rendered outside the menus.
- Sticky Źródło column on narrow screens through `stickyHeadCell` / `stickyCell`.

#### 4. States

**File**: `resources/js/components/sources/sources-empty-state.tsx`

**Intent**: The three states of the Desired End State table, built on the shared `EmptyState`.

**Contract**: `SourcesEmptyTree({ action })`, `SourcesNoResults({ query, onClear })`, `SourcesEmptyKind({ kind, query, onShowAll })`. The page picks „empty kind” when nothing is visible and the „Wszystkie” counter is above 0.

### Success Criteria:

#### Automated Verification:

- Format + lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Build passes: `vendor/bin/sail npm run build`
- Sources feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php`

#### Manual Verification:

- Typing filters the list at once, the URL gets `q` after a pause, and Back does not step through keystrokes
- Kind filter: counters follow the search, a kind with counter 0 is disabled, and the chosen kind survives a view switch and a refresh
- Switching Galeria ↔ Tabela keeps `q` and `kind`, the Galeria URL has no `sort`, and refresh restores the chosen view
- Table sort: Rok keeps sources without a year last in both directions, `Dodano ↓` equals the gallery order, and Potwierdza ↑ starts with „bez powiązań”
- Empty states: „Wyczyść wyszukiwanie” keeps the kind, „Pokaż wszystkie rodzaje” keeps the search text, and the empty tree offers „Dodaj źródło”
- ⋯ → „Edytuj” saves, and the list keeps its view, search, kind and sort and shows the new values; ⋯ → „Otwórz” lands on the source page
- Keyboard: Tab reaches the search, the kind filter, the switcher, cards or rows, sort headers and ⋯ menus, each with a visible focus ring
- At 390 px the gallery has two columns with no sideways page scroll, and the table scrolls sideways with the Źródło column sticky
- The page has one `<h1>` „Źródła” and „Dodaj źródło” is level with it

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Screenshot gate

### Overview

Run the gate script against the finished view and record a verdict against the canvas boards.

### Changes Required:

#### 1. After shots and verdict

**File**: `context/changes/sources-list-views/screenshots/shots.mjs`, `screenshots/after/`, `screenshots/verdict.md`

**Intent**: Extend the Phase 2 script with the states of the new view and write one verdict line per state against its board.

**Contract**:
- Light and dark at 1280 and 390 px, as `tree-demo@example.com`:

  | Shot | URL |
  |---|---|
  | Galeria | `/sources` |
  | Tabela | `/sources?view=tabela` |
  | Tabela by year | `/sources?view=tabela&sort=rok` |
  | Search with counters (Inne at 0) | `/sources?q=lipn` |
  | One kind | `/sources?kind=nagrobki` |
  | No match | `/sources?q=Kowalczyk` |
  | Empty kind | `/sources?q=lipn&kind=inne` |

- Empty tree: `/sources` as `tree-demo-empty@example.com`, same themes and widths.
- Light and dark at 1280 px: the ⋯ menu open, the edit dialog opened from a row, keyboard focus on a card and on a row link.
- `verdict.md` also lists the open follow-ups (the summary line on the source page and in the picker, the `/persons` heading, list parameters on the way back) and records v2:FR-006 as met.
- Baselines are never blind-updated: `before/` is from Phase 2 and is not rewritten.

### Success Criteria:

#### Automated Verification:

- Seeder runs cleanly: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- The screenshot script completes and writes every listed `/sources` shot to `screenshots/after/`

#### Manual Verification:

- `verdict.md` shows every state matching its canvas board (`Main`, `Rejestr`, `Stany`, `Mobile`), with each difference explained or fixed
- `verdict.md` lists the three open follow-ups and records v2:FR-006 as met

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Vitest `sources-index.test.ts`: query building, the search predicate, counters, each sort key with its missing-value and tie rules, the count noun.
- Vitest `source-description.test.ts`: the kind text and the summary line, including the date suffix.
- Vitest `table-sort.test.ts`: `nextSort` and `columnOf`.

### Integration Tests:

- Pest `SourcesTest.php`:
  - `events_count` values, including the marriage akt counted once;
  - the key is absent on the show page and in `availableSources`;
  - `filters` defaults, echo and fallbacks;
  - the parameters do not filter or reorder `sources`;
  - the partial reload omits `sources`.
- The existing sources, persons and event-sources tests stay green unchanged, except the `hasAll` list.

### Manual Testing Steps:

1. `/sources` → gallery with images and fallbacks, two-line names, „potwierdza …” or „bez powiązań”.
2. Type „lipn” → the list narrows at once; counters read 5 / 4 / 1 / 0 and „Inne” is disabled.
3. Choose „Nagrobki” → one card; type „xyz” → „Brak wyników” with „Wyczyść wyszukiwanie”.
4. Open `/sources?q=lipn&kind=inne` → „Pokaż wszystkie rodzaje” → five sources, the search text kept.
5. Switch to Tabela, sort Rok ↑ then ↓; sources without a year stay last.
6. ⋯ → „Edytuj”, change the year, save → the same table, sort and search, with the new year.
7. Refresh and Back on each of the above.
8. Dark mode and 390 px for both views.
9. `/persons` → both views, search and sort, unchanged.

## Performance Considerations

- `withCount('events')` adds one subquery column to the sources query over the indexed `event_source.source_id`; no query per source.
- The `sources` closure means search, kind, view and sort visits run no sources query.
- Filtering and sorting run in memory over the whole tree's sources, a small list by the PRD's stated scale (`context/foundation/prd-v3.md:25`). Thumbnails keep `loading="lazy"`.

## Migration Notes

None. No schema change. `events_count` is additive and appears only on the index. Demo data is refreshed by re-running `TreeViewDemoSeeder`.

## References

- Research: `context/changes/sources-list-views/research.md`
- Design canvas: https://claude.ai/artifact/McYg2Lo8Z2wZcKEHAoqpzU
- Similar implementation: `context/archive/2026-09-29-persons-list-views/plan.md`; `app/Http/Controllers/PersonController.php:27-92`; `resources/js/lib/persons-index.ts:31-50`, `:145-187`
- Screenshot gate precedent: `context/archive/2026-09-30-matching-sources-first/screenshots/shots.mjs`, `verdict.md`
- Plural helper: `resources/js/lib/plural.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract and demo data

#### Automated

- [x] 1.1 Sources feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php` — e6c3093
- [x] 1.2 Other consumers of the source payload still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventSourcesTest.php` — e6c3093
- [x] 1.3 Static analysis passes: `vendor/bin/sail bin phpstan analyse` — e6c3093
- [x] 1.4 PHP formatting applied: `vendor/bin/sail bin pint --dirty --format agent` — e6c3093
- [x] 1.5 Types pass: `vendor/bin/sail npm run types:check` — e6c3093
- [x] 1.6 Seeder runs twice in a row without error, and the demo tree's source folder then holds the files of one run only: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder` — e6c3093

#### Manual

- [x] 1.7 `/sources?view=foo&sort=bar&kind=baz` renders today's list with no error, and the Inertia page props show `filters.view = 'galeria'`, `filters.sort = '-dodano'`, `filters.kind = 'wszystkie'` — e6c3093
- [x] 1.8 As `tree-demo@example.com`, `/sources` in its current layout shows a real image on the eight chosen sources and the fallback icon on the other ten — e6c3093

### Phase 2: Shared components and list helpers

#### Automated

- [x] 2.1 JS unit tests pass: `vendor/bin/sail npm run test:js` — 934c1fb
- [x] 2.2 Types pass: `vendor/bin/sail npm run types:check` — 934c1fb
- [x] 2.3 Format + lint pass: `vendor/bin/sail npm run check` — 934c1fb
- [x] 2.4 Build passes: `vendor/bin/sail npm run build` — 934c1fb
- [x] 2.5 Persons and sources feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php` — 934c1fb
- [x] 2.6 The script writes every `/persons` control shot to `screenshots/after/` and its compare step prints a result for each against `screenshots/before/` — 934c1fb

#### Manual

- [x] 2.7 `/persons` looks and behaves as before in light and dark: search spinner, Lista | Tabela switcher, sort headers, both empty states; every control shot is identical or its difference is explained — 934c1fb
- [x] 2.8 „Edytuj” on a source page still opens the dialog and saves — 934c1fb
- [x] 2.9 Thumbnails in the source picker and in the person modal's „Źródła” tab look unchanged — 934c1fb

### Phase 3: The view — toolbar, Galeria, Tabela, states

#### Automated

- [x] 3.1 Format + lint pass: `vendor/bin/sail npm run check` — f6ab8b3
- [x] 3.2 Types pass: `vendor/bin/sail npm run types:check` — f6ab8b3
- [x] 3.3 JS unit tests pass: `vendor/bin/sail npm run test:js` — f6ab8b3
- [x] 3.4 Build passes: `vendor/bin/sail npm run build` — f6ab8b3
- [x] 3.5 Sources feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php` — f6ab8b3

#### Manual

- [x] 3.6 Typing filters the list at once, the URL gets `q` after a pause, and Back does not step through keystrokes — f6ab8b3
- [x] 3.7 Kind filter: counters follow the search, a kind with counter 0 is disabled, and the chosen kind survives a view switch and a refresh — f6ab8b3
- [x] 3.8 Switching Galeria ↔ Tabela keeps `q` and `kind`, the Galeria URL has no `sort`, and refresh restores the chosen view — f6ab8b3
- [x] 3.9 Table sort: Rok keeps sources without a year last in both directions, `Dodano ↓` equals the gallery order, and Potwierdza ↑ starts with „bez powiązań” — f6ab8b3
- [x] 3.10 Empty states: „Wyczyść wyszukiwanie” keeps the kind, „Pokaż wszystkie rodzaje” keeps the search text, and the empty tree offers „Dodaj źródło” — f6ab8b3
- [x] 3.11 ⋯ → „Edytuj” saves, and the list keeps its view, search, kind and sort and shows the new values; ⋯ → „Otwórz” lands on the source page — f6ab8b3
- [x] 3.12 Keyboard: Tab reaches the search, the kind filter, the switcher, cards or rows, sort headers and ⋯ menus, each with a visible focus ring — f6ab8b3
- [x] 3.13 At 390 px the gallery has two columns with no sideways page scroll, and the table scrolls sideways with the Źródło column sticky — f6ab8b3
- [x] 3.14 The page has one `<h1>` „Źródła” and „Dodaj źródło” is level with it — f6ab8b3

### Phase 4: Screenshot gate

#### Automated

- [x] 4.1 Seeder runs cleanly: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder` — 4140008
- [x] 4.2 The screenshot script completes and writes every listed `/sources` shot to `screenshots/after/` — 4140008

#### Manual

- [x] 4.3 `verdict.md` shows every state matching its canvas board (`Main`, `Rejestr`, `Stany`, `Mobile`), with each difference explained or fixed — 4140008
- [x] 4.4 `verdict.md` lists the three open follow-ups and records v2:FR-006 as met — 4140008
