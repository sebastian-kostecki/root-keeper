# Graphical Tree View (Family Sheet) Implementation Plan

## Overview

Roadmap slice S-09 (FR-004, US-01): the user browses their tree graphically and moves from it to people.
We build a PL-SOFT-style **„Formularz rodzinny”**. It is a fixed grid of four rows, Dziadkowie (8) / Rodzice (4) /
Związek (focus person + one partner) / Dzieci, centred on one person:
- Clicking a card re-centres the sheet on that person.
- A separate „Podgląd” button on the card opens `persons.show`.
- The sheet opens on a stored **proband** (probant).
- Several unions are handled with a switch.
- Missing parents show as dashed „Nieznany ojciec / Nieznana matka / Nieznany rodzic” placeholders.
- Cards show name, years and gender (colour strip + Mars/Venus icon). This needs a new `people.gender` field.

## Current State Analysis

- **No tree surface exists.** There is no route, controller, page or nav item (`routes/web.php:12-25`,
  `resources/js/components/app-sidebar.tsx:20-31`). A tree has no root person: `trees` holds only `user_id`
  (`database/migrations/2026_09_23_045720_create_trees_table.php:16`, `app/Models/Tree.php`).
- **No gender anywhere.** The model, migrations and TS types have no gender (`app/Models/Person.php:25` fillable:
  `tree_id, first_name, last_name, residence, religion`).
- **Parent–child is per parent, not per couple.** `parent_child_relations` has `(parent_id, child_id)`, unique per
  pair, and the max-2-parents rule lives in app code only (`app/Actions/Relations/CreateParentChildRelation.php:33`).
  A child is therefore not attached to a marriage.
- **Marriages.** A marriage is an `Event` of type `Marriage` with two `spouse` participants and partial-date columns
  (`app/Models/Person.php:84`, `app/Actions/Relations/CreateMarriage.php:75`). There is no limit per person.
- **Graph helper.** `app/Support/ParentChildGraph.php` loads all of a tree's edges in one query and exposes
  `parentsOf`/`childrenOf` (ids only).
- **Ordering helpers.** Relative and marriage ordering (birth/marriage date with unknown last, then name/id) exists
  only as private methods in `app/Http/Resources/PersonResource.php:69-122`.
- **Dates.** `App\Support\PartialDate::format()` gives `dd.mm.yyyy` / `mm.yyyy` / `yyyy` with an „ok. ” prefix. There
  is no year-only formatter.
- **Enum pattern.** `Religion` is the pattern for a nullable enum person field end to end. See `app/Enums/Religion.php`
  (`label()`, `options()`), `app/Http/Requests/PersonStoreRequest.php:32` (`Rule::enum`),
  `app/Actions/Persons/CreatePerson.php:48`, `app/Http/Resources/PersonResource.php:43-46` (`{value,label}`), the form
  select with sentinel in `resources/js/components/persons/person-form-fields.tsx:24-94`, and options passed from
  `PersonController.php:74,119`.
- **Design system.** It is the stock shadcn neutral theme in `resources/css/app.css` (`@theme` 10-62, `:root` 64-98,
  `.dark` 100-133). There are no genealogy tokens, no screenshot tests (no Playwright, no `pest-plugin-browser`) and
  no kitchen-sink page.
- **Delete dialog.** Deleting a person lists affected items in `resources/js/components/persons/delete-person-dialog.tsx`.

## Desired End State

- **Gender on a person.** A person has an optional gender („Mężczyzna” / „Kobieta” / „Nieznana”):
  - set on create/edit;
  - shown on `persons/show`;
  - drawn on tree cards as a coloured left strip plus a Mars/Venus icon (unknown gender is a neutral strip and no icon).
- **Proband.** The user can mark one person as the proband:
  - „Ustaw jako probanta” on `persons/show` and in the tree toolbar;
  - a „Probant” badge once set;
  - deleting the proband clears it, and the delete dialog says so.
- **Sidebar entry.** „Drzewo” in the sidebar opens `/tree`:
  - the family sheet centred on the proband;
  - an empty state („Nie wybrano probanta”, with a link to the persons list) when none is set.
- **Re-centring.** `/tree/{person}` centres on any person of the user's tree:
  - clicking a card re-centres;
  - „Podgląd” opens `persons.show`;
  - `?spouse=<id|unknown>` selects the union and is kept in the URL, so Back restores it;
  - another user's person returns 404.
- **Layout.** Parents are ordered male left, female right, unknown gender between, with birth date as the tie-break.
  Missing parents of any drawn known person (up to the grandparent row) are dashed placeholders. The sheet scrolls
  horizontally on narrow screens.
- **Visual gate.** A demo seeder builds a tree that shows every state. Screenshots of each state are stored in the
  change folder.

Verify with `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/PersonsTest.php tests/Unit/PartialDateTest.php`,
`vendor/bin/sail npm run check`, `types:check`, `build`, and the manual screenshot walk-through in Phase 5.

### Key Discoveries:

- `app/Support/ParentChildGraph.php:23` loads every edge in one query. The builder uses it and then batch-loads people,
  so the query count stays constant.
- `app/Http/Resources/PersonResource.php:69-122`: the relative and marriage sort keys the sheet must reuse (parents
  tie-break, children order, union order). They must be extracted, not copied.
- `app/Enums/Religion.php` and `person-form-fields.tsx:24` (the `NO_RELIGION` sentinel): the exact pattern for
  `Gender`.
- `app/Policies/PersonPolicy.php`: `view`/`update` return `denyAsNotFound()` for another tree's person. Route-bound
  `{person}` endpoints get the required 404 for free.
- `tests/Feature/ParentChildRelationsTest.php:12` (`attachBirth`) and `tests/Feature/MarriagesTest.php:13`
  (`attachMarriage`) are top-level functions local to their files. `tests/Pest.php` defines no helpers, and there is no
  autoload `files` entry. A run of `TreeTest.php` without those files fails with "undefined function". Phase 3 moves
  them into `tests/Pest.php`; do not redeclare them.
- `resources/js/components/scans/scan-viewer.tsx` pan/zoom is deliberately **not** used (the user chose horizontal
  scroll).

## What We're NOT Doing

- No pan/zoom tree, no generated ancestor/descendant/fan chart, no siblings side lines, no PDF/SVG export (PL-SOFT
  „Widok drzewa” is out of scope; the user chose the family sheet).
- No union-per-card layout algorithm and no graph library (no new npm dependency).
- No person modal; „Podgląd” navigates to the existing `persons.show`.
- No showing all spouses at once; one partner at a time, with a switch.
- No source/confirmation badges on cards (parked in `context/foundation/roadmap.md:340`, PRD Non-Goals).
- No places or full dates on cards (years only). No photos or avatars.
- No biological vs adoptive parent distinction (PRD Non-Goals, FR-011).
- No gender on `persons/index` rows or in relation-candidate pickers.
- No automatic proband (no backfill, no „first person” default).
- No screenshot test infrastructure (the repo has none; the gate is manual screenshots).

## Implementation Approach

Build backend-first, one testable contract per phase:
1. **Gender** is a plain enum field on the existing person write path.
2. **Proband** is a nullable FK on `trees` with one route-bound endpoint.
3. **The sheet** is assembled server-side into a fixed-shape payload (`BuildFamilySheet` Action), so the React page
   only renders slots and never infers relationships.
4. **The UI** is a CSS grid with 8 columns: grandparents 1 column each, parents 2, focus and partner 4 each, children
   spanning all 8 in their own row. Connectors are drawn with borders. There is no SVG and no layout library.

**Design-system contract.** New semantic tokens are only the gender colours, in `resources/css/app.css`. Everything
else maps to existing shadcn tokens:
- focus: `ring`/`primary`
- placeholder: `border` dashed + `muted-foreground`
- connectors: `border`

The new shared components live in `resources/js/components/tree/`.

## Critical Implementation Details

- **Union derivation (children are not attached to marriages).** The focus person's unions, in switch order:
  1. each **marriage**, ordered by the existing marriage sort key (date known first, then y/m/d, then id);
  2. each **co-parent** who shares a child with the focus person but has no marriage with them, ordered by the relative
     sort key;
  3. an **`unknown` union** if any child has the focus person as its only known parent.

  A marriage without a second `spouse` participant is skipped. The app never creates one (`DeletePerson` deletes whole
  marriages), but the DB does not prevent it, and `MarriageResource.php:41,51` guards the same case.
  A marriage partner who is also a co-parent is one union. Children of union `X` are exactly the children whose known
  parents are `{focus, X}`. Children of `unknown` are exactly those whose only known parent is the focus person.
  Every child of the focus person lands in exactly one union.
- **Selected union.** `?spouse=<partnerId>` or `?spouse=unknown`. A value that matches no union (including other users'
  ids and garbage) silently falls back to the first union. Cards build re-centre links that carry the pair context:
  - a card in a parent/grandparent pair links with `spouse=<other person of that pair>` when that person is known, and
    with `spouse=unknown` when the other slot is an `unknown` placeholder (the child below sits in that union);
  - the partner card links with `spouse=<focus id>`;
  - child cards link with no param.
- **Parent-slot order and labels.** Sort known parents by rank `male=0, null=1, female=2`, then the relative sort key.
  Placeholder labels:
  - 0 known parents: `[Nieznany ojciec, Nieznana matka]`;
  - 1 known male: `[person, Nieznana matka]`;
  - 1 known female: `[Nieznany ojciec, person]`;
  - 1 known with unknown gender: `[person, Nieznany rodzic]`.

  Placeholders are drawn only above a **known** person. Above a placeholder or empty slot, the grandparent slots are
  `empty` (nothing drawn).
- **Couple side.** The Związek row uses the same rank: the focus sits right (`focus_side = 'right'`) only when the
  selected partner is a known person with a strictly lower rank (e.g. a male partner of a female focus); otherwise, including
  ties, no partner and `unknown` partner, the focus sits left. The focus's parents and grandparents follow it to that
  side, so the sheet reads male-left / female-right after every re-centre.
- **Lifespan label.** The birth part is `PartialDate::formatYear()` of the birth event („1850” / „ok. 1850”) or „?”.
  - No death event: „<birth> –”, or `null` (line omitted) when the birth year is also unknown.
  - Death event: „<birth> – <death>” with „?” for a missing year.
- **Addendum (impl review 2026-09-28).** Two behaviours were added during implementation:
  - Repeat marriages to the same partner form one union. It is labelled with the earliest marriage by the marriage
    sort key (`BuildFamilySheet`, tested in `TreeTest`).
  - The tree toolbar has a „Podgląd” button that links to `persons.show` of the focus person.
- **Circular FK on delete.** `trees.root_person_id → people` with `nullOnDelete`, while `people.tree_id → trees` and
  `trees.user_id → users` cascade. Deleting an account with a proband set must still succeed on PostgreSQL 18. This
  needs an explicit test.

## Phase 1: Gender field on a person

### Overview

Add an optional gender to Person, following the `Religion` pattern end to end, so later phases can order parents and
style cards.

### Changes Required:

#### 1. Enum and schema

**File**: `app/Enums/Gender.php`, `database/migrations/<ts>_add_gender_to_people_table.php` (via `sail artisan make:migration`)

**Intent**: Store gender as a nullable string enum. `null` means unknown, so existing rows need no backfill.

**Contract**: `enum Gender: string { case Male = 'male'; case Female = 'female'; }` with `label()` („Mężczyzna”,
„Kobieta”) and `options()`, as in `Religion`. The migration adds nullable `people.gender` (string, after `last_name`),
and `down()` drops it.

#### 2. Model, factory

**File**: `app/Models/Person.php`, `database/factories/PersonFactory.php`

**Intent**: Make gender mass-assignable and cast it; give tests states to set it.

**Contract**:
- `gender` added to fillable, cast `Gender::class`, PHPDoc `@property Gender|null $gender`.
- Factory default `gender => null`, plus states `male()` and `female()`.

#### 3. Write path

**File**: `app/Http/Requests/PersonStoreRequest.php`, `app/Http/Requests/PersonUpdateRequest.php`, `app/Actions/Persons/CreatePerson.php`, `app/Actions/Persons/UpdatePerson.php`, `app/Http/Controllers/PersonController.php`

**Intent**: Accept, validate and persist gender on create and edit.

**Contract**:
- Rule `'gender' => ['nullable', Rule::enum(Gender::class)]`, attribute label `płeć`.
- Actions persist `$data['gender'] ?? null`, with the PHPDoc array shape extended.
- `create`/`edit` pass `genders => Gender::options()`.

#### 4. Read path

**File**: `app/Http/Resources/PersonResource.php`, `app/Http/Resources/PersonFormResource.php`, `resources/js/types/person.ts`

**Intent**: Expose gender to the preview and the edit form.

**Contract**:
- `PersonResource` adds `gender: {value, label}|null` (same shape as `religion`).
- `PersonFormResource` adds `gender: string|null`.
- TS: new `Gender = {value: 'male'|'female', label: string}`, `Person.gender: Gender | null`,
  `PersonFormData.gender: string | null`.

#### 5. UI

**File**: `resources/js/components/persons/person-form-fields.tsx`, `resources/js/pages/persons/create.tsx`, `resources/js/pages/persons/edit.tsx`, `resources/js/pages/persons/show.tsx`

**Intent**: Let the user pick gender and see it on the preview.

**Contract**:
- „Płeć” `Select` next to „Wyznanie”, with a sentinel item „Nieznana” mapping to `''` (same technique as
  `NO_RELIGION`) and a hidden input `name="gender"`.
- `genders: Gender[]` prop on the create/edit pages and `PersonFormFields`.
- `show.tsx` displays the gender label in the details card when set (a Badge like religion).

#### 6. Tests

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Cover the new field's behaviour.

**Contract**:
- Store and update persist `male`/`female`/null.
- An invalid value fails validation on `gender`.
- The preview prop `person.gender` is `{value,label}` or null.
- The edit form prefills `person.gender`.

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back: `vendor/bin/sail artisan migrate` then `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Person tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Static analysis passes: `vendor/bin/sail composer run types:check` (Larastan level 7)
- PHP style: `vendor/bin/sail bin pint --dirty --format agent`
- Frontend: `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Creating and editing a person with each of Mężczyzna / Kobieta / Nieznana saves and prefills correctly; the preview shows the label

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Proband (tree root person)

### Overview

Store one proband per tree, let the user set it, and keep deletion safe and explained.

### Changes Required:

#### 1. Schema and model

**File**: `database/migrations/<ts>_add_root_person_id_to_trees_table.php`, `app/Models/Tree.php`

**Intent**: Point a tree at its proband. Deleting that person clears the pointer.

**Contract**:
- Nullable `trees.root_person_id` foreignId constrained to `people` with `nullOnDelete()`; `down()` drops the FK and
  column.
- `Tree::rootPerson(): BelongsTo<Person, $this>`, fillable plus the PHPDoc property.

#### 2. Endpoint and action

**File**: `routes/web.php`, `app/Http/Controllers/TreeRootPersonController.php`, `app/Actions/Trees/SetTreeRootPerson.php`

**Intent**: Set the proband via a route-bound person, so another user's person is a 404 through `PersonPolicy`. There
is no request body, so no Form Request is needed (like the existing `destroy` actions).

**Contract**:
- `Route::put('tree/root-person/{person}', [TreeRootPersonController::class, 'update'])->name('tree.root-person.update')`.
- The controller calls `Gate::authorize('update', $person)`, then `SetTreeRootPerson::handle(Tree $tree, Person $person): void`,
  then `Inertia::flash('toast', ['type' => 'success', 'message' => __('Ustawiono probanta')])`, then `back()`.
- Regenerate Wayfinder.

#### 3. Preview integration

**File**: `app/Http/Controllers/PersonController.php` (`show`), `resources/js/pages/persons/show.tsx`, `resources/js/components/persons/delete-person-dialog.tsx`

**Intent**: Show and set proband status on the preview, and warn before deleting the proband.

**Contract**:
- `persons/show` gets a page prop `isRootPerson: boolean` (no change to `PersonResource`).
- Header: when not the proband, a „Ustaw jako probanta” button (`router.put` to the Wayfinder action); when it is the
  proband, a „Probant” Badge.
- `DeletePersonDialog` takes `isRootPerson` and appends „Osoba jest probantem drzewa — widok drzewa pokaże pusty stan,
  dopóki nie wybierzesz nowego probanta.” when true.

#### 4. Tests

**File**: `tests/Feature/TreeTest.php` (new, `sail artisan make:test --pest TreeTest`)

**Intent**: Cover the proband endpoint and its lifecycle.

**Contract**:
- Setting the proband stores `root_person_id` and flashes the toast.
- Another user's person returns 404 and nothing changes.
- Setting a new proband replaces the old one.
- Deleting the proband person nulls `root_person_id`.
- Deleting the user account (`profile.destroy`) with a proband set succeeds.
- `persons.show` exposes `isRootPerson` true/false.

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back: `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Tree tests pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`
- Account deletion still passes: `vendor/bin/sail artisan test --compact tests/Feature/Settings/ProfileUpdateTest.php`
- PHP style and static analysis: `vendor/bin/sail bin pint --dirty --format agent` and `vendor/bin/sail composer run types:check`
- Frontend: `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`

#### Manual Verification:

- On a person preview, „Ustaw jako probanta” shows a toast and turns into the „Probant” badge; the delete dialog of the proband shows the extra sentence

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Family sheet backend

### Overview

Build the fixed-shape family sheet payload for a focus person and selected union, and serve it on `/tree` and
`/tree/{person}`.

### Changes Required:

#### 1. Shared ordering

**File**: `app/Support/PersonOrdering.php` (new), `app/Http/Resources/PersonResource.php`

**Intent**: Move the relative and marriage sort keys out of `PersonResource`, so the sheet and the preview order
people and marriages identically.

**Contract**:
- `PersonOrdering::relatives(Collection<int, Person>): Collection<int, Person>` and
  `PersonOrdering::marriages(Collection<int, Event>): Collection<int, Event>`, with the same keys as today.
- `PersonResource` delegates to them with no behaviour change; the existing preview ordering tests stay green.

#### 2. Year formatting

**File**: `app/Support/PartialDate.php`, `tests/Unit/PartialDateTest.php`

**Intent**: A year-only label for cards.

**Contract**: `formatYear(): ?string` returns „1850”, „ok. 1850” when approximate, or `null` without a year.

#### 3. Card resource

**File**: `app/Http/Resources/SheetPersonResource.php`

**Intent**: The per-card payload, following the rule that all page data goes through API Resources.

**Contract**:
- `{id, display_name, gender: 'male'|'female'|null, lifespan: string|null, is_deceased: bool}`.
- `lifespan` follows the rule in Critical Implementation Details.
- Requires `events` loaded.

#### 4. Sheet builder

**File**: `app/Actions/Trees/BuildFamilySheet.php`

**Intent**: Derive unions, select one, and fill every slot of the four rows with a constant number of queries
(`ParentChildGraph` + marriages with participants + one batched `people()->with('events')->whereIn(...)`).

**Contract**: `handle(Person $focus, ?string $spouse): array`. It returns the shape below, which Phase 4 depends on.
The batched `with('events')` load includes **every** rendered person: the focus itself (route-bound, so its events are
not loaded), all union partners (their names label the switch), parents, grandparents and children.
`Person::birth()`/`death()` read the `events` relation, and the app has no `preventLazyLoading`, so a missed id is a
silent N+1 that only the query-count test catches.
All persons are rendered through `SheetPersonResource::make(...)->resolve()`.

```php
/**
 * @return array{
 *   focus: SheetPerson,
 *   unions: list<array{key: string, partner_id: int|null, label: string, is_marriage: bool, marriage_year: string|null, children_count: int}>,
 *   selected_union: string|null,            // 'person-<id>' | 'unknown' | null when no unions
 *   partner: Slot,                          // person | unknown('Nieznany partner') | empty (no unions)
 *   focus_side: 'left'|'right',             // see "Couple side" in Critical Implementation Details
 *   parents: array{focus: array{Slot, Slot}, partner: array{Slot, Slot}},
 *   grandparents: array{focus: array{Slot, Slot, Slot, Slot}, partner: array{Slot, Slot, Slot, Slot}},
 *   children: list<SheetPerson>,
 * }
 * Slot = array{kind: 'person', person: SheetPerson} | array{kind: 'unknown', label: string} | array{kind: 'empty'}
 */
```

Union labels:
- marriage: „<partner> · ślub <formatYear or '?'>”;
- co-parent: „<partner> · bez ślubu”;
- unknown union: „Nieznany partner”.

#### 5. Controller and routes

**File**: `app/Http/Controllers/TreeController.php`, `routes/web.php`

**Intent**: Serve the proband sheet and any person's sheet.

**Contract**:
- `GET tree` → `TreeController@index`, name `tree.index`. It renders `tree/show` with the proband's sheet, or with
  `sheet: null` when there is no proband.
- `GET tree/{person}` → `TreeController@show`, name `tree.show`. It calls `Gate::authorize('view', $person)` and
  renders `tree/show`.
- Props: `sheet: FamilySheet|null`, `rootPersonId: int|null`.
- The `spouse` query param is read as a string and passed to the builder.
- Regenerate Wayfinder.

#### 6. Tests

**File**: `tests/Feature/TreeTest.php`, `tests/Pest.php`, `tests/Feature/ParentChildRelationsTest.php`, `tests/Feature/MarriagesTest.php`

**Intent**: Cover the sheet contract, including its edge cases.

**Contract**:
- First, move `attachBirth` and `attachMarriage` verbatim from their test files into `tests/Pest.php` (with their
  `use` imports), so any subset of test files can use them.
- `tree.index`: no proband gives `sheet: null`; with a proband, focus is the proband.
- `tree.show`: another user's person returns 404.
- Two marriages plus a co-parent plus a single-parent child give unions in the order marriage-by-date → co-parent →
  unknown, each with the right `children` when selected by `?spouse=`.
- An invalid or foreign `?spouse=` falls back to the first union.
- A focus with no unions gives `partner.kind = empty` and empty partner columns.
- Parent order and labels:
  - male+female in either DB order puts the male in slot 0;
  - one known female gives `[Nieznany ojciec, person]`;
  - one unknown-gender gives `[person, Nieznany rodzic]`;
  - zero parents give `[Nieznany ojciec, Nieznana matka]`.
- Grandparents above a placeholder are `empty`.
- `focus_side`: a female focus with a male partner is `right`; a male focus, no partner, or an `unknown` partner is `left`.
- `lifespan` covers „1850 –”, „ok. 1850 – 1912”, „? – 1912” and null.
- The query count is equal for a small and a large tree.

### Success Criteria:

#### Automated Verification:

- Tree and preview tests pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php`
- Unit tests pass: `vendor/bin/sail artisan test --compact tests/Unit/PartialDateTest.php`
- PHP style and static analysis: `vendor/bin/sail bin pint --dirty --format agent` and `vendor/bin/sail composer run types:check`
- Wayfinder regenerated and types compile: `vendor/bin/sail artisan wayfinder:generate && vendor/bin/sail npm run types:check`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Family sheet UI

### Overview

Render the sheet: the grid, cards, placeholders, union switch, toolbar, empty state and navigation entry points.

### Changes Required:

#### 1. Tokens

**File**: `resources/css/app.css`

**Intent**: Add semantic gender colours (the only new tokens), with a comment naming the source convention: blue/rose
gender frames as in Ancestris' dynamic tree (`context/changes/graphical-tree-view/research.md` §3).

**Contract**:
- `--gender-male`, `--gender-female`, `--gender-unknown` in `:root` and `.dark` (oklch, AA contrast against `card`).
- Mapped in `@theme` as `--color-gender-male/female/unknown`, used as `border-l-gender-*`.

#### 2. Types and components

**File**: `resources/js/types/tree.ts` (re-exported from `resources/js/types/index.ts`), `resources/js/components/tree/sheet-person-card.tsx`, `resources/js/components/tree/unknown-person-card.tsx`, `resources/js/components/tree/union-switch.tsx`, `resources/js/components/tree/family-sheet.tsx`

**Intent**: Shared, importable pieces of the sheet. The page itself stays thin.

**Contract**:
- TS types mirror the Phase 3 array shape (`FamilySheet`, `SheetPerson`, `SheetSlot`, `SheetUnion`).
- `SheetPersonCard`:
  - the whole card is a Wayfinder `Link` to `tree.show(person.id, {query: {spouse}})` that re-centres;
  - a separate „Podgląd” icon button links to `PersonController.show(id)`, with `aria-label` „Podgląd: <name>”;
  - a left strip in the gender colour and a lucide `Mars`/`Venus` icon (`aria-label` „mężczyzna”/„kobieta”, none
    when unknown);
  - name, and `lifespan` when present;
  - the focus card is highlighted (`ring-primary`) and not a link.
- `UnknownPersonCard`: dashed `border`, `text-muted-foreground`, label, not interactive.
- `UnionSwitch`: hidden when there are ≤1 unions. Otherwise a single-select `ToggleGroup` that wraps onto more lines
  when needed (no `Select` variant), listing the union labels plus the children count. Choosing one visits `tree.show(focus, {query: {spouse}})` with
  `preserveScroll`.
- `FamilySheet`: an 8-column grid inside `overflow-x-auto` with a fixed `min-w`. Rows are labelled „Dziadkowie”,
  „Rodzice”, „Związek”, „Dzieci”. The focus column block (focus, its parents, its grandparents) and the partner block swap halves
  when `focus_side = 'right'`. Connectors between a pair and its child are drawn with borders. `empty` slots keep
  their grid cell. The children row shows „Brak dzieci w tym związku.” when empty.

#### 3. Page

**File**: `resources/js/pages/tree/show.tsx`

**Intent**: The tree page for both routes.

**Contract**:
- Props `{sheet: FamilySheet | null; rootPersonId: number | null}`.
- Breadcrumbs „Drzewo”, plus the focus name, via `setLayoutProps`.
- Toolbar:
  - when the focus is not the proband: a „Ustaw jako probanta” button and, if a proband exists, a „Wróć do probanta”
    link to `tree.index`;
  - when the focus is the proband: a „Probant” badge.
- `sheet === null`: an inline Card empty state „Nie wybrano probanta. Otwórz osobę i wybierz »Ustaw jako probanta«.”
  with a link to the persons list (same pattern as `persons/index.tsx:77-89`).

#### 4. Entry points

**File**: `resources/js/components/app-sidebar.tsx`, `resources/js/pages/persons/show.tsx`

**Intent**: Reach the tree from the sidebar and from any person.

**Contract**:
- `mainNavItems` gets `{title: 'Drzewo', href: treeIndex(), icon: Network}` as the first item.
- The `persons/show` header gets a „Pokaż w drzewie” button linking to `tree.show(person.id)`.

### Success Criteria:

#### Automated Verification:

- Format and lint (denyWarnings): `vendor/bin/sail npm run check`
- Types: `vendor/bin/sail npm run types:check`
- Production build: `vendor/bin/sail npm run build`
- Backend still green: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`

#### Manual Verification:

- Clicking a parent, partner, child or grandparent card re-centres, with the expected union preselected; „Podgląd” opens the preview; Back restores the previous sheet
- The union switch changes the partner, the partner's ancestors and the children
- Keyboard: every card and „Podgląd” button is reachable with Tab and has a visible focus ring

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Visual gate (kitchen-sink tree and screenshots)

### Overview

Render every state from real data and screenshot it, then close the slice.

### Changes Required:

#### 1. Demo seeder

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: A kitchen-sink tree for the visual gate and future UI work. It is run explicitly and not added to
`DatabaseSeeder`.

**Contract**: `sail artisan db:seed --class=TreeViewDemoSeeder` creates the user `tree-demo@example.com` (password
`password`) whose tree contains:
- a proband with all 4 grandparents known, and a first (dated) spouse with all 4 grandparents known, so the default
  sheet fills the whole Dziadkowie row (8 cards);
- 2 marriages (one dated, one undated), plus a co-parent without a marriage, plus a child with only the proband as a
  known parent;
- a partner with one known parent (female → „Nieznany ojciec”);
- a parent of unknown gender with no partner (→ „Nieznany rodzic”);
- people with an approximate birth year, a deceased person with no death year, a living person, and a very long name;
- a person with no unions at all.

It also creates a second user, `tree-demo-empty@example.com`, with no proband (the empty state).

The seeder is idempotent: it first deletes any existing users with these two emails (DB cascades remove their trees),
so it can be re-run on the dev database without `migrate:fresh`.

#### 2. Screenshots

**File**: `context/changes/graphical-tree-view/screenshots/*.png`

**Intent**: Evidence for `/10x-impl-review`. Screenshots are not wired into any test (the repo has no screenshot
tests).

**Contract**: One screenshot per state:
- empty state;
- proband sheet;
- each union selected;
- placeholders;
- no-union person;
- dark mode;
- a 390px-wide viewport, scrolled.

Capture method: if the implementing agent has a browser tool connected, it takes them. Otherwise the user takes them in
Chrome as `tree-demo@example.com`: full-size captures via DevTools „Capture screenshot”, 390px via device mode
(Responsive, width 390), and dark mode via `/settings/appearance` („Dark”). Files are named `<nn>-<state>.png`.

#### 3. Roadmap

**File**: `context/foundation/roadmap.md`

**Intent**: Record the resolution of S-09's unknown (one partner with a switch; dashed placeholder), so the roadmap
reflects the decision. Status transitions stay with `/10x-implement` and `/10x-archive`.

**Contract**: The S-09 `Unknowns` bullet gets its resolution appended. The Status field is not touched.

### Success Criteria:

#### Automated Verification:

- Seeder runs and re-runs without wiping the dev DB: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder && vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder` (never `migrate:fresh`: `.env` points at the user's dev data)
- Full check suite: `vendor/bin/sail npm run check && vendor/bin/sail npm run types:check && vendor/bin/sail npm run build`

#### Manual Verification:

- Screenshots of every listed state are saved in `context/changes/graphical-tree-view/screenshots/`, and each matches the decisions (father left, dashed placeholders, strip + icon, focus highlight)
- Dark mode: gender strips and placeholders are legible
- At 390px the sheet scrolls horizontally without breaking row alignment
- The user runs the full suite: `vendor/bin/sail artisan test --compact`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `PartialDate::formatYear()`: exact, approximate, no year.

### Integration Tests:

- `PersonsTest`: gender store, update, validation, preview and form props.
- `TreeTest`:
  - proband: set, other-user 404, replace, delete-clears, account-delete, `isRootPerson`;
  - sheet: empty state, 404, union derivation and order, `?spouse=` selection and fallback, parent order and
    placeholder labels, empty grandparents, lifespan variants, constant query count.
- Existing `ParentChildRelationsTest` / `MarriagesTest` preview ordering stays green after the `PersonOrdering`
  extraction.

### Manual Testing Steps:

1. Seed `TreeViewDemoSeeder`, log in as `tree-demo@example.com`, and open „Drzewo”: the proband sheet appears.
2. Switch across all unions and check the children and the partner's ancestors change.
3. Click a grandparent: the sheet re-centres with that grandparent's partner preselected. Press Back.
4. Click „Podgląd” on any card: `persons.show` opens.
5. Log in as the second demo user: the empty state appears. Set a proband from a preview and reopen „Drzewo”.
6. Delete the proband: the dialog warns, and afterwards „Drzewo” shows the empty state.

## Performance Considerations

The sheet uses a constant number of queries: edges of the whole tree (one query, fine for a personal tree of
hundreds of people), the focus person's marriages with participants, and one batched people-with-events load. A test
asserts the query count does not grow with tree size.

## Migration Notes

Both migrations only add nullable columns. There is no backfill: existing people have unknown gender and existing
trees have no proband. Both are reversible with `migrate:rollback`.

## References

- Research: `context/changes/graphical-tree-view/research.md`
- Roadmap slice: `context/foundation/roadmap.md` (S-09), PRD FR-004, FR-011, US-01
- Enum field pattern: `app/Enums/Religion.php`, `resources/js/components/persons/person-form-fields.tsx:24-94`
- Edge loading: `app/Support/ParentChildGraph.php:23`
- Ordering keys to extract: `app/Http/Resources/PersonResource.php:69-122`
- Delete-warning pattern: `resources/js/components/persons/delete-person-dialog.tsx`
- Empty-state pattern: `resources/js/pages/persons/index.tsx:77-89`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Gender field on a person

#### Automated

- [x] 1.1 Migration applies and rolls back — ba65515
- [x] 1.2 Person tests pass — ba65515
- [x] 1.3 Static analysis passes — ba65515
- [x] 1.4 PHP style — ba65515
- [x] 1.5 Frontend check and types — ba65515

#### Manual

- [x] 1.6 Create/edit with each gender saves, prefills and shows on preview — ba65515

### Phase 2: Proband (tree root person)

#### Automated

- [x] 2.1 Migration applies and rolls back — e5f69fa
- [x] 2.2 Tree tests pass — e5f69fa
- [x] 2.3 Account deletion still passes — e5f69fa
- [x] 2.4 PHP style and static analysis — e5f69fa
- [x] 2.5 Frontend check and types — e5f69fa

#### Manual

- [x] 2.6 Set proband from preview shows toast and badge; delete dialog warns — e5f69fa

### Phase 3: Family sheet backend

#### Automated

- [x] 3.1 Tree and preview tests pass — 241a5e6
- [x] 3.2 Unit tests pass — 241a5e6
- [x] 3.3 PHP style and static analysis — 241a5e6
- [x] 3.4 Wayfinder regenerated and types compile — 241a5e6

### Phase 4: Family sheet UI

#### Automated

- [x] 4.1 Format and lint — 22143d1
- [x] 4.2 Types — 22143d1
- [x] 4.3 Production build — 22143d1
- [x] 4.4 Backend still green — 22143d1

#### Manual

- [x] 4.5 Card click recentres with expected union, Podgląd opens preview, Back restores — 22143d1
- [x] 4.6 Union switch changes partner, partner ancestors and children — 22143d1
- [x] 4.7 Keyboard reachability and visible focus — 22143d1

### Phase 5: Visual gate (kitchen-sink tree and screenshots)

#### Automated

- [x] 5.1 Seeder runs and re-runs without wiping the dev DB — dbe1b1e
- [x] 5.2 Full check suite — dbe1b1e

#### Manual

- [ ] 5.3 Screenshots of every state saved and matching decisions
- [x] 5.4 Dark mode legible — dbe1b1e
- [x] 5.5 390px horizontal scroll keeps alignment — dbe1b1e
- [x] 5.6 User runs the full suite — dbe1b1e
