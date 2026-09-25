# Parent–Child Relations Implementation Plan

## Overview

Roadmap slice S-06 (FR-011, FR-012, FR-008). A logged-in user can link two people of their tree as parent and child — from either person's preview ("Dodaj rodzica" / "Dodaj dziecko") — see parents and children on the person preview, and remove a relation after an explicit confirmation. A child has at most two known parents (one known parent is allowed), and no relation may create a cycle. Without this slice the success criterion of 3 generations cannot be entered and the graphical tree (S-09) has nothing to draw.

## Current State Analysis

- There is no relation between people. `Person` belongs to a `Tree` and reaches facts only through `events` via `event_participants` (`app/Models/Person.php:47-60`); `ParticipantRole` has a single case `Subject` (`app/Enums/ParticipantRole.php:5-8`).
- `Person` has no sex/gender field (`database/migrations/2026_09_23_045722_create_people_table.php:14-23`), so "mother/father" slots cannot exist — the rule is "0–2 parents of any kind".
- Ownership is enforced per model through `tree_id` checks returning `denyAsNotFound()` (`app/Policies/PersonPolicy.php:14-19`, `app/Policies/EventPolicy.php:14-18`). Foreign ids in a request body are rejected as validation errors via `Rule::exists(...)->where('tree_id', …)` (`app/Http/Requests/EventScanStoreRequest.php:26-30`, proven by `tests/Feature/EventScansTest.php:46`).
- Link/unlink precedent: `EventScanController` (`app/Http/Controllers/EventScanController.php:17-49`) — `back()` + `Inertia::flash('toast', …)`; picker loaded lazily through an `Inertia::optional` prop reloaded on dialog open (`app/Http/Controllers/PersonController.php:100-102`, `resources/js/components/scans/scan-picker-dialog.tsx:44-58`); destructive confirmation dialog (`resources/js/components/scans/unlink-scan-dialog.tsx`).
- `PersonResource` is shared by the index and the preview (`app/Http/Resources/PersonResource.php`); the index loads only `events` (`PersonController.php:29`). New preview-only keys must use `whenLoaded` so the index payload is unchanged.
- `DeletePerson` deletes sole-participant events then the person (`app/Actions/Persons/DeletePerson.php:18-27`); FK cascades on `people` do the rest. `DeletePersonDialog` states consequences with a Polish plural helper (`resources/js/components/persons/delete-person-dialog.tsx:21-38`).
- Database is PostgreSQL in dev and tests (`.env:24`, `phpunit.xml:26`), SQLite in `.env.example`.
- No `.ai/rules` directory and no `context/foundation/lessons.md` in this repo.

## Desired End State

- The person preview shows, right after the personal data card, a **"Rodzice"** card and a **"Dzieci"** card. Each lists relatives as links to their previews with their birth/death dates (muted, e.g. "ur. 1850 · zm. 1910"), ordered by birth date, and each row has a "Usuń relację" action. Empty states: "Brak znanych rodziców." / "Brak znanych dzieci.".
- "Dodaj rodzica" (hidden once the person has two parents) and "Dodaj dziecko" open a picker listing every other person of the tree with a name filter. People who cannot be linked are shown disabled with a reason; picking a linkable person creates the relation, closes the dialog and shows the toast "Dodano relację".
- "Usuń relację" opens a confirmation: "Czy na pewno chcesz usunąć relację: „{rodzic}” jest rodzicem „{dziecko}”? Obie osoby pozostaną w drzewie. Tej operacji nie można cofnąć." Confirming removes only the relation and shows "Usunięto relację".
- The server rejects, with a Polish message and without writing anything: a self-link, a duplicate pair, a third parent, a cycle (the parent is the child or one of the child's descendants), and any person from another tree. Removing another tree's relation returns 404.
- Deleting a person removes all their relations (as parent and as child); related people stay. The delete-person dialog states the number of relations lost, e.g. "…, a także 1 powiązanie ze skanami aktów i 2 relacje rodzinne. Skany pozostaną w bibliotece skanów. Powiązane osoby pozostaną w drzewie. Tej operacji nie można cofnąć."

### Key Discoveries:

- Picker "reason" labels and server validation must agree; both derive from one in-memory graph of the tree's `(parent_id, child_id)` pairs, which is cheap for a personal tree (hundreds of people) and database-agnostic.
- `Person::birth()` reads the loaded `events` collection (`app/Models/Person.php:61-64`) — eager-load `parents.events` / `children.events` before sorting or formatting relatives.
- The scan picker keeps its last loaded list because non-partial visits drop optional props (`scan-picker-dialog.tsx:38-43`); the person picker must do the same.
- Wayfinder regenerates `@/actions/App/Http/Controllers/ParentChildRelationController` from routes via the Vite plugin (`vite.config.ts:27`).

## What We're NOT Doing

- No biological vs adoptive distinction, no mother/father labels, no sex field (PRD Non-Goals, FR-011).
- No date plausibility check (parent born after child is accepted) — only structural rules. Consistent with the PRD non-goal of not policing fact consistency.
- No creating a new person from the picker — the user creates the person first, then links.
- No siblings section on the preview.
- No sources/scans for a parent–child relation, and no use of birth-event participants for parentage.
- No change to the persons index or its payload.
- No browser tests; UI states are verified manually.
- No trash/undo for relations (PRD Non-Goals) — explicit confirmation instead.

## Implementation Approach

A dedicated `parent_child_relations` table owned by the tree, with a plain `ParentChildRelation` model and two `belongsToMany` relations on `Person`. One flat endpoint pair (`POST /parent-child-relations` with `{parent_id, child_id}`, `DELETE /parent-child-relations/{id}`) serves both "Dodaj rodzica" and "Dodaj dziecko". All stateful rules (duplicate, max two parents, cycle) live in a single `CreateParentChildRelation` action that runs them inside a transaction after locking both people, so the rules and the write are atomic; the Form Request handles shape, tree ownership and self-link. A small `ParentChildGraph` support class answers ancestor/descendant/parent-count questions for both the action and the picker payload. Phase 1 lands and proves the backend; Phase 2 exposes it on the preview.

## Critical Implementation Details

- **Timing & lifecycle** — Rule checks must run inside the same transaction as the insert, after `lockForUpdate()` on both `people` rows taken in ascending id order. This serialises double-submits and two concurrent "add parent" requests for the same child (the third-parent race). The unique index on `(parent_id, child_id)` stays as the last safety net.
- **User experience spec** — The picker filters client-side over the loaded candidates and keeps the last loaded list across non-partial visits, exactly like `ScanPickerDialog`; a server validation error is shown inside the dialog, which stays open.

## Phase 1: Relation model and rules

### Overview

Persist parent–child relations, enforce every structural rule on the server, expose store/destroy endpoints scoped to the user's tree, and prove it with feature tests. No UI yet.

### Changes Required:

#### 1. Schema, model, factory

**File**: `database/migrations/<timestamp>_create_parent_child_relations_table.php`, `app/Models/ParentChildRelation.php`, `database/factories/ParentChildRelationFactory.php` (via `vendor/bin/sail artisan make:model ParentChildRelation -mf --no-interaction`)

**Intent**: Store one row per parent–child pair, owned by the tree, disappearing automatically when either person or the tree is deleted.

**Contract**: Table `parent_child_relations`: `id`, `tree_id` FK → trees cascadeOnDelete, `parent_id` FK → people cascadeOnDelete, `child_id` FK → people cascadeOnDelete, timestamps; `unique(parent_id, child_id)`, `index(child_id)`. Model: `#[Fillable(['tree_id', 'parent_id', 'child_id'])]`, `tree()`, `parent()`, `child()` BelongsTo `Person`, PHPDoc `@property` block like sibling models. Factory default: a tree plus two people of that same tree.

#### 2. Person relations

**File**: `app/Models/Person.php`

**Intent**: Let a person reach its parents and children directly, carrying the relation id needed for removal.

**Contract**: `parents(): BelongsToMany<Person, $this>` over `parent_child_relations` (`child_id` → `parent_id`) and `children(): BelongsToMany<Person, $this>` (`parent_id` → `child_id`), both `->withPivot('id')->withTimestamps()`.

#### 3. Graph helper

**File**: `app/Support/ParentChildGraph.php`

**Intent**: One in-memory view of a tree's relation pairs that answers the structural questions used by both the create action and the picker.

**Contract**: `final` class built by `static forTree(Tree $tree): self` (loads only `parent_id`, `child_id` for the tree). Methods: `parentsOf(int $personId): list<int>`, `childrenOf(int $personId): list<int>`, `descendantsOf(int $personId): list<int>`, `ancestorsOf(int $personId): list<int>` (transitive, excluding the person, cycle-safe traversal), `hasRelation(int $parentId, int $childId): bool`.

#### 4. Create action with rules

**File**: `app/Actions/Relations/CreateParentChildRelation.php`

**Intent**: The single place that decides whether a relation may exist and writes it atomically.

**Contract**: `handle(Tree $tree, Person $parent, Person $child): ParentChildRelation`. Inside `DB::transaction`: lock both people (`lockForUpdate`, ascending id), build `ParentChildGraph::forTree($tree)`, then check in this order and throw `ValidationException::withMessages(['relation' => …])`:
- pair exists → "Ta relacja już istnieje."
- child already has two parents → "Ta osoba ma już dwoje rodziców."
- parent is among `descendantsOf(child)` → "Ta relacja utworzyłaby cykl w drzewie."
Then create the row with `tree_id`. Boundary: A→B→C, adding A as parent of C is **allowed** (not a cycle).

#### 5. Store request

**File**: `app/Http/Requests/ParentChildRelationStoreRequest.php`

**Intent**: Validate shape and tree ownership before the action runs.

**Contract**: `parent_id`, `child_id`: `required`, `integer`, `Rule::exists('people', 'id')->where('tree_id', $this->user()->tree->id)`; `child_id` also `different:parent_id` with the message "Osoba nie może być własnym rodzicem.". `attributes()` → `rodzic`, `dziecko`. Foreign person ids fail validation (same as foreign scans).

#### 6. Controller, policy, routes

**File**: `app/Http/Controllers/ParentChildRelationController.php`, `app/Policies/ParentChildRelationPolicy.php`, `routes/web.php`

**Intent**: Wire add/remove endpoints inside the existing `auth`, `verified` group, following `EventScanController`.

**Contract**: `Route::resource('parent-child-relations', ParentChildRelationController::class)->only(['store', 'destroy'])` → `parent-child-relations.store`, `parent-child-relations.destroy`. `store(ParentChildRelationStoreRequest, CreateParentChildRelation)` resolves both people from the user's tree, calls the action, flashes toast `['type' => 'success', 'message' => __('Dodano relację')]`, returns `back()`. `destroy(ParentChildRelation)` → `Gate::authorize('delete', …)` (policy: `tree_id` match else `denyAsNotFound()`), deletes the row, flashes "Usunięto relację", returns `back()`.

#### 7. Delete-person docblock

**File**: `app/Actions/Persons/DeletePerson.php`

**Intent**: Record that relations disappear through FK cascade; no code change.

**Contract**: Docblock sentence only.

#### 8. Feature tests

**File**: `tests/Feature/ParentChildRelationsTest.php` (via `vendor/bin/sail artisan make:test --pest ParentChildRelationsTest`)

**Intent**: Prove every rule that could corrupt the tree or leak across trees.

**Contract**: Tests listed under Testing Strategy → Phase 1.

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back cleanly: `vendor/bin/sail artisan migrate` then `vendor/bin/sail artisan migrate:rollback --step=1` then `vendor/bin/sail artisan migrate`
- Relation feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php`
- Existing person and event-scan tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventScansTest.php`
- PHP formatting is clean: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: After completing this phase and all automated verification passes, pause for confirmation before proceeding to Phase 2. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes live in the `## Progress` section.

---

## Phase 2: Relations on the person view

### Overview

Show parents and children on the preview in birth order, add them through a picker that explains blocked people, remove them with confirmation, and make the delete-person dialog state the number of relations lost.

### Changes Required:

#### 1. Relatives in the preview payload

**File**: `app/Http/Controllers/PersonController.php` (`show`), `app/Http/Resources/PersonResource.php`, `app/Http/Resources/RelativeResource.php` (new)

**Intent**: The preview receives both relative lists already sorted; the index payload is untouched.

**Contract**: `show` loads `['events.scans', 'parents.events', 'children.events']`. `PersonResource` adds `parents` and `children` via `whenLoaded`, each a list of `RelativeResource`: `{relation_id: int (pivot id), id: int, display_name: string, birth_date: string|null, death_date: string|null}` (dates via `PartialDate::format()`). Sort order: known birth year before unknown; then year, month, day ascending with a missing month/day sorting before a known one in the same year ("1850" < "03.1850" < "15.03.1850"; "ok." ignored); then last name (null last), first name, id.

#### 2. Relation candidates prop

**File**: `app/Actions/Relations/ListRelationCandidates.php` (new), `app/Http/Controllers/PersonController.php` (`show`)

**Intent**: Give the picker every other person of the tree with the reason, per direction, why they cannot be linked — computed from the same graph as the server rules.

**Contract**: `show` adds `'relationCandidates' => Inertia::optional(fn (): array => $listRelationCandidates->handle($person))`. Each item: `{id, display_name, birth_date: string|null, as_parent_blocked_reason: string|null, as_child_blocked_reason: string|null}`, the current person excluded, ordered like the persons index (last name null last, first name, id). Reasons, first matching wins:
- as parent of the current person: already a parent → "Już jest rodzicem"; a descendant → "Jest potomkiem tej osoby".
- as child of the current person: already a child → "Już jest dzieckiem"; an ancestor → "Jest przodkiem tej osoby"; has two parents → "Ma już dwoje rodziców".

#### 3. Frontend types

**File**: `resources/js/types/person.ts`

**Intent**: Type the new payload.

**Contract**: `Relative`, `RelationCandidate` types matching the shapes above; `PersonPreview` gains `parents: Relative[]` and `children: Relative[]`.

#### 4. Relatives card, picker and removal dialog

**File**: `resources/js/components/persons/relatives-card.tsx`, `resources/js/components/persons/relative-picker-dialog.tsx`, `resources/js/components/persons/remove-relation-dialog.tsx` (new)

**Intent**: One card component used twice ("Rodzice", "Dzieci"), built from existing shadcn `Card`, `Dialog`, `Button`, `Input`, `Skeleton`, mirroring `EventCard`, `ScanPickerDialog` and `UnlinkScanDialog`.

**Contract**:
- `RelativesCard` props: `kind: 'parent' | 'child'`, `person: PersonPreview`, `relatives: Relative[]`, `candidates: RelationCandidate[] | undefined`. Rows link to `PersonController.show(id)`, show dates muted, and have a "Usuń relację" trigger. The add button ("Dodaj rodzica" / "Dodaj dziecko") is hidden for `kind === 'parent'` when `relatives.length >= 2`.
- `RelativePickerDialog`: on open `router.reload({ only: ['relationCandidates'] })` with skeleton, keeps the last loaded list, name filter input (case-insensitive, every word must match the display name), rows show name + birth date, disabled with the direction's reason text; select posts `ParentChildRelationController.store()` with `{parent_id, child_id}` assembled from `kind`; shows `errors.relation ?? errors.parent_id ?? errors.child_id`; empty tree message "Nie masz jeszcze innych osób." with a link to create one.
- `RemoveRelationDialog`: title "Usuń relację", text from Desired End State, destructive "Usuń", `router.delete(ParentChildRelationController.destroy(relationId).url, { preserveScroll: true })`.

#### 5. Preview page

**File**: `resources/js/pages/persons/show.tsx`

**Intent**: Place the two cards after the personal-data card and before the event cards (data → relations → facts with sources, FR-008 order).

**Contract**: `Props` gains `relationCandidates?: RelationCandidate[]`.

#### 6. Delete-person dialog

**File**: `resources/js/components/persons/delete-person-dialog.tsx`

**Intent**: State how many relations are lost and that related people stay.

**Contract**: relation count = `parents.length + children.length`. Generalise the local plural helper to `(count, one, few, many)`; phrases: "powiązanie/powiązania/powiązań ze skanami aktów" and "relacja rodzinna/relacje rodzinne/relacji rodzinnych". Extras joined with " i " after ", a także "; append " Powiązane osoby pozostaną w drzewie." only when count > 0. Example (1 scan link, 2 relations): "…urodzenie, a także 1 powiązanie ze skanami aktów i 2 relacje rodzinne. Skany pozostaną w bibliotece skanów. Powiązane osoby pozostaną w drzewie. Tej operacji nie można cofnąć." Example (0 links, 5 relations): "…urodzenie, a także 5 relacji rodzinnych. Skany pozostaną…".

#### 7. Feature tests

**File**: `tests/Feature/ParentChildRelationsTest.php`

**Intent**: Prove payload ordering and picker reasons.

**Contract**: Tests listed under Testing Strategy → Phase 2.

### Success Criteria:

#### Automated Verification:

- Relation feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php`
- Existing person tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- PHP formatting is clean: `vendor/bin/sail bin pint --dirty --format agent`
- TypeScript type check passes: `vendor/bin/sail npm run types:check`
- Lint and format check pass: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`

#### Manual Verification:

- Adding a parent from a child's preview and a child from a parent's preview shows the relation on both previews, with working links between them
- The picker shows blocked people disabled with the correct reason, the name filter narrows the list, and "Dodaj rodzica" disappears after two parents
- Removing a relation asks for confirmation; after confirming it disappears from both previews and both people remain
- Children on a preview appear in birth order, unknown birth dates last
- The delete-person dialog states the relation count with correct Polish plurals (1, 2, 5) and related people remain after deletion
- Cards, picker and dialogs render correctly in light and dark appearance

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation.

---

## Testing Strategy

### Feature Tests (Phase 1 — rules):

- Guests are redirected to login from both relation routes.
- Adding a relation creates the row with the tree id, redirects back and flashes "Dodano relację".
- A child may have two parents; a third is rejected on `relation` and nothing is created.
- Linking a person to themselves is rejected on `child_id`.
- Linking the same pair twice is rejected on `relation`; one row remains.
- Direct cycle: with A→B, adding B as parent of A is rejected.
- Deep cycle: with A→B→C, adding C as parent of A is rejected.
- Boundary: with A→B→C, adding A as parent of C is accepted.
- A `parent_id` or `child_id` from another tree fails validation and creates nothing.
- Removing a relation deletes only that row, keeps both people and flashes "Usunięto relację".
- Removing another tree's relation returns 404 and the row remains.
- Deleting a person removes the relations where they are parent and where they are child, and keeps the related people.

### Feature Tests (Phase 2 — preview):

- The preview returns `parents` and `children` with `relation_id`, children ordered: 1850, 03.1850, 1852, then unknown year; two children with the same date ordered by name.
- The preview does not include `relationCandidates` on a normal visit.
- A partial reload of `relationCandidates` excludes the current person and other trees' people, and returns the expected reason per direction: already parent, descendant, already child, ancestor, has two parents, and `null` for a linkable person.

### Manual Testing Steps:

1. Create three people; on the middle one add the oldest as parent and the youngest as child; open each preview and follow the links.
2. On the oldest person open "Dodaj rodzica" and check the youngest shows "Jest potomkiem tej osoby".
3. Give a person two parents; confirm "Dodaj rodzica" disappears and that another person's "Dodaj dziecko" picker shows them as "Ma już dwoje rodziców".
4. Remove one relation through the dialog; confirm both previews update.
5. Delete a person with relations; read the dialog text; confirm the relatives still exist.

## Performance Considerations

The picker payload loads every person of the tree with their events plus all relation pairs once per dialog open; for a personal tree (hundreds of people) this is a few small queries. The preview eager-loads `parents.events` and `children.events`, so there is no N+1.

## Migration Notes

Additive migration creating one table; no backfill. Rolling back drops the table and every relation entered since deploy. Production picks it up through the usual `scripts/deploy.sh` run after merge.

## References

- Roadmap slice: `context/foundation/roadmap.md` (S-06)
- PRD: `context/foundation/prd.md` (FR-008, FR-011, FR-012)
- Similar implementation: `app/Http/Controllers/EventScanController.php:17-49`, `resources/js/components/scans/scan-picker-dialog.tsx`, `resources/js/components/scans/unlink-scan-dialog.tsx`
- Prior plan conventions: `context/archive/2026-09-25-person-edit-and-delete/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Relation model and rules

#### Automated

- [x] 1.1 Migration applies and rolls back cleanly — bcf3ca8
- [x] 1.2 Relation feature tests pass — bcf3ca8
- [x] 1.3 Existing person and event-scan tests still pass — bcf3ca8
- [x] 1.4 PHP formatting is clean — bcf3ca8

### Phase 2: Relations on the person view

#### Automated

- [x] 2.1 Relation feature tests pass — 8a82d17
- [x] 2.2 Existing person tests still pass — 8a82d17
- [x] 2.3 PHP formatting is clean — 8a82d17
- [x] 2.4 TypeScript type check passes — 8a82d17
- [x] 2.5 Lint and format check pass — 8a82d17
- [x] 2.6 Production build succeeds — 8a82d17

#### Manual

- [x] 2.7 Adding a parent from a child's preview and a child from a parent's preview shows the relation on both previews — 8a82d17
- [x] 2.8 Picker shows blocked people with the correct reason, filter works, "Dodaj rodzica" hidden after two parents — 8a82d17
- [x] 2.9 Removing a relation asks for confirmation and both people remain — 8a82d17
- [x] 2.10 Children appear in birth order, unknown birth dates last — 8a82d17
- [x] 2.11 Delete-person dialog states the relation count with correct Polish plurals and relatives remain — 8a82d17
- [x] 2.12 Cards, picker and dialogs render correctly in light and dark appearance — 8a82d17
