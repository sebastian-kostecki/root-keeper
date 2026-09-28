# Existing or New Child of the Shown Pair Implementation Plan

## Overview

Roadmap slice S-26 (v4:FR-005, v4:FR-011, v3:FR-002, v3:FR-012). The "Dzieci" row of the tree gets a „Dodaj dziecko" card. It opens one dialog naming the parents of the pair currently shown: the focus person plus the partner chosen in the union switcher, or the focus person alone when there is no partner. The dialog lists existing people of the tree the child could be (people whose link would break a rule are hidden, with a count and the reason), plus a „Nowa osoba" view with the person form. After saving, the child is linked to every parent of the pair in one transaction and appears on the tree without leaving it.

## Current State Analysis

- `BuildFamilySheet` (`app/Actions/Trees/BuildFamilySheet.php:45-153`) selects one union (`selectUnion()`, `:182-197`): a partner union `person-X` (marriage or co-parent), the `unknown` union ("Nieznany partner", children with only the focus as parent, `:110-119`), or none at all (no marriages and no children → `selected_union: null`, `partner: {kind: 'empty'}`, `children: []`). The shown children are `childrenByOtherParent[partner_id ?? 0]` (`:125`). The sheet has no metadata for adding a child.
- The frontend renders the children row in `ChildrenRows` (`resources/js/components/tree/family-sheet.tsx:90-128`) or „Brak dzieci w tym związku." (`:240-246`); there is no add affordance.
- Relation rules live in `CreateParentChildRelation` (`app/Actions/Relations/CreateParentChildRelation.php:20-80`): duplicate, max two parents, cycle, and (from S-25) no second parent of the same known sex — each a `ValidationException` on `relation`. It runs in its own transaction with row locks and rebuilds the graph on every call, so two calls inside an outer transaction see each other's insert.
- S-25 left a working pattern (`context/archive/2026-09-28-tree-parent-slot-existing-or-new/plan.md`):
  - `ParentSlotDialog` (`resources/js/components/tree/parent-slot-dialog.tsx:65-250`) with a list view (`PersonCandidateList` + hidden-count hint) and a form view (`Form` + `PersonFormFields` with `lockedGender`/`defaults`), resetting to the list on open.
  - The optional prop `parentCandidates` (every person of the tree with `gender`; `TreeController.php:28,46`, `app/Actions/Trees/ListParentCandidates.php`), loaded on open by `useOptionalList` (`resources/js/hooks/use-relation-candidates.ts:19-45`).
  - An atomic create-and-link endpoint (`PersonParentController`, `PersonParentStoreRequest`, `CreateParentForPerson`) under `persons/{person}/parents` (`routes/web.php:29`).
- `parent-slot-dialog.tsx:47-58` re-implements Polish plural forms (`pluralPeople`) although `resources/js/lib/plural.ts` already exports `pluralPhrase`.
- `UnknownPersonCard` (`resources/js/components/tree/unknown-person-card.tsx:21-40`) becomes a button only through `parentOf` and derives its aria-label from the parent role, so it cannot serve a child card.
- No `.ai/rules` directory, no `context/foundation/lessons.md`, no screenshot test infrastructure.

## Desired End State

- Whenever the tree shows a sheet, the "Dzieci" row ends with a dashed „Dodaj dziecko" card (a button with a `Plus` icon; aria-label „Dodaj dziecko: Jan Kowalski i Anna Nowak" or „Dodaj dziecko: Jan Kowalski"). It also appears next to „Brak dzieci w tym związku.".
- The card opens a dialog titled „Dodaj dziecko — Jan Kowalski i Anna Nowak" (or „— Jan Kowalski" when there is no partner), with the description „Rodzice: Jan Kowalski i Anna Nowak. Wybierz osobę z drzewa albo dodaj nową." (no partner: „Rodzic: Jan Kowalski (drugi nieznany). …").
- The list shows existing people of the tree except those that are excluded or blocked:
  - **Excluded (hidden, no hint):** the pair, the pair's ancestors, and people who already are children of every parent of the pair.
  - **Blocked (hidden, counted):** people who already have a parent outside the pair such that the link would break a rule. A hint says how many, independent of the search text, e.g. „2 osoby ukryto — mają już innych rodziców."
- Picking a person creates only the missing links (e.g. a child of Jan in the „Nieznany partner" union gets only Anna added). The dialog closes and the tree keeps its centre and union. The toast says „Dodano dziecko".
- With no partner, a person who already has another parent of the opposite (or unknown) sex can still be attached. They keep that parent and appear under the resulting union: shown directly when it becomes the only union, otherwise selectable in the switcher.
- „Nowa osoba" switches the same dialog to the person form (with „Wróć do listy"). Sex is free. The surname starts as the surname of the pair's man, or the focus person's surname when the pair has no man. Saving creates the person and all the pair's links atomically. The toast says „Dodano dziecko". Validation or rule errors keep the dialog open and create nothing.
- The S-25 parent slot behaves exactly as before, now built on the same shared dialog shell.

### Key Discoveries:

- `ParentChildGraph` (`app/Support/ParentChildGraph.php:41-74`) exposes `parentsOf`, `childrenOf`, `ancestorsOf`, `hasRelation`, and it is already loaded in `BuildFamilySheet` (`:47`). Excluded and blocked sets can be computed per sheet from the graph without per-candidate queries.
- In pair mode no candidate property can trigger the same-sex rule without also exceeding two parents, so "blocked" there is purely `|parentsOf(C) ∪ pair| > 2`. In single-parent mode, a candidate with exactly one other parent is blocked only when that parent has the same known sex as the focus person. That needs an id → gender map for the other parents (one query).
- Nesting `CreatePerson` and several `CreateParentChildRelation` calls inside one outer `DB::transaction` rolls everything back on a rule failure (established in S-25's `CreateParentForPerson`).
- `PersonFormFields` already accepts `defaults.last_name` (`resources/js/components/persons/person-form-fields.tsx:26,74`); no form changes are needed.
- `back()` redirects keep `tree.show(X)?spouse=Y`, so the sheet re-renders the same union with the new child.

## What We're NOT Doing

- Adding a child of the focus person alone while a partner is shown. The pair is always the one chosen in the switcher (the roadmap risk: a child landing in the wrong pair). A child with an unknown other parent is added from the „Nieznany partner" union when it exists, or from the preview modal.
- Checking on the server that the two parents actually form a union on the sheet. The relation rules protect data integrity; the pair is a UI concept.
- A child card anywhere other than the "Dzieci" row of the centre's shown union (e.g. no "add sibling" on parent cards). Spouse slot / „Połącz jako małżeństwo" (S-27), relation removal from the tree (S-28), read-only preview relations (S-29).
- Showing blocked people disabled with a reason in this dialog (declined: hidden with a count hint instead). The preview-modal picker is unchanged.
- Restricting single-parent mode to people with no parents (declined: rule-valid people with another parent are allowed).
- Locking or prefilling the new child's sex, or prefilling anything but the surname.
- New tokens, shadcn `ui/*` edits, screenshot-test infrastructure; duplicate merging, drag-and-drop, deep links (PRD v4 non-goals).

## Implementation Approach

Two vertical phases, like S-25.

**Phase 1 — attach an existing person as the pair's child.**
- The sheet gains an `add_child` block (parents, excluded ids, blocked ids, default surname) computed server-side from the in-memory graph.
- The candidate prop is renamed to the neutral `personCandidates`, because it now serves parent and child dialogs.
- A new `AttachChildToParents` action behind `POST persons/{person}/children/attach` creates only the missing links in one transaction.
- The list/form dialog logic is extracted from `ParentSlotDialog` into a shared shell (S-27 will reuse it), and the new `AddChildDialog` is built on it.

**Phase 2 — create a new child.** A new `CreateChildForParents` action behind `POST persons/{person}/children` creates the person and then attaches them, and the dialog gains the „Nowa osoba" view.

Both endpoints take the focus person as `{person}` and the partner as optional `other_parent_id`. Both redirect `back()` with the toast „Dodano dziecko". The server stays the authority: the client only hides what the sheet marked as excluded or blocked.

## Critical Implementation Details

**State sequencing** — `AttachChildToParents` decides which links are missing from a graph read inside its own transaction, then calls `CreateParentChildRelation` once per missing link. When a candidate has an outside parent, the first link may succeed and the second fail („Ta osoba ma już dwoje rodziców."). The outer transaction must roll back the first link, and a test must prove it.

**User experience spec** — The shared shell must keep S-25's behaviour exactly:
- Opening the dialog resets it to the list view, clears the search and the error, and reloads the candidates.
- Switching list → form → list keeps the loaded candidates.
- The dialog uses `FormDialogContent`.
- A form error scrolls the first `[data-slot="input-error"]` into view.

## Phase 1: Attach an existing child to the shown pair

### Overview

Sheet metadata for adding a child, the renamed candidate prop, the attach endpoint, the shared dialog shell, and the „Dodaj dziecko" card with its list view.

### Changes Required:

#### 1. Add-child metadata on the sheet

**File**: `app/Actions/Trees/BuildFamilySheet.php`, `app/Support/ParentChildGraph.php`

**Intent**: The client knows whose child the card adds, whom to hide, whom to count as blocked, and which surname to prefill, all derived from the selected union.

**Contract**: `handle()` returns a new key `add_child: {parents: list<{id: int, display_name: string}>, default_last_name: string|null, excluded_ids: list<int>, blocked_ids: list<int>}` (new phpstan type `AddChild`).
- `parents` = focus first, then the selected union's partner when it is a known person; otherwise the focus alone (for the `unknown` union and for no union).
- `excluded_ids` = the parents ∪ `ancestorsOf` each parent ∪ people whose `parentsOf` contains every parent id.
- `blocked_ids` = people not excluded where `|parentsOf(C) ∪ parent ids| > 2`, or, in single-parent mode, where C has exactly one other parent whose known gender equals the focus person's known gender. Only people who have parents can be blocked, so the candidates for `blocked_ids` are exactly `ParentChildGraph::childIds()` (new public method on `app/Support/ParentChildGraph.php` returning the keys of the private `$parentsByChild` as `list<int>`); one query builds the id → gender map for those other parents (`pluck('gender', 'id')` returns `Gender|null` enums).
- `default_last_name` = `last_name` of the first male parent, else the focus person's `last_name`.

#### 2. Neutral candidate prop

**File**: `app/Actions/Trees/ListParentCandidates.php` → `app/Actions/Trees/ListPersonCandidates.php`, `app/Http/Controllers/TreeController.php`, `resources/js/types/tree.ts`, `resources/js/pages/tree/show.tsx`, `resources/js/components/tree/family-sheet.tsx`, `resources/js/components/tree/parent-slot-dialog.tsx`, `tests/Feature/TreeTest.php`

**Intent**: The prop listing every person of the tree now feeds both parent and child dialogs, so its name should not say "parent".

**Contract**: The class is renamed `ListPersonCandidates` (same query and shape; docblock updated), the prop `parentCandidates` → `personCandidates`, and the TS type `ParentCandidate` → `PersonCandidate`. Behaviour is unchanged; existing tree tests are updated to the new prop name.

#### 3. Attach action

**File**: `app/Actions/Relations/AttachChildToParents.php` (new)

**Intent**: An existing person becomes the child of every parent of the pair, or nothing changes.

**Contract**: `handle(Tree $tree, Person $child, list<Person> $parents): void`, inside `DB::transaction`:
- Determine the parents not yet linked to the child (fresh `ParentChildGraph::forTree`).
- If none are missing, throw `ValidationException` on `relation` with „Ta relacja już istnieje.".
- Otherwise call `CreateParentChildRelation::handle($tree, $parent, $child)` for each missing parent in the given order. Any rule failure rolls back all links.

#### 4. Attach request, controller, route

**File**: `app/Http/Requests/PersonChildAttachRequest.php` (new), `app/Http/Controllers/PersonChildController.php` (new), `routes/web.php`

**Intent**: The dialog posts the chosen person and the pair's partner for a focus person from the user's own tree.

**Contract**:
- Route `POST persons/{person}/children/attach` → `PersonChildController@attach`, name `persons.children.attach`.
- The request takes:
  - `child_id`: required integer, exists in the user's tree, not the route person, different from `other_parent_id`.
  - `other_parent_id`: nullable integer, exists in the user's tree, not the route person.
  - Messages: child = person → „Osoba nie może być własnym rodzicem."; attributes „dziecko", „drugi rodzic".
- The controller:
  - `Gate::authorize('view', $person)` (other user's focus → 404).
  - Loads the child and the optional other parent via `$tree->people()->findOrFail`.
  - Calls the action with `[$person, $otherParent?]`, flashes the toast „Dodano dziecko" and returns `back()`.
- Regenerate Wayfinder.

#### 5. Types

**File**: `resources/js/types/tree.ts`

**Intent**: Type the new sheet block.

**Contract**: New `AddChildTarget = { parents: { id: number; display_name: string }[]; default_last_name: string | null; excluded_ids: number[]; blocked_ids: number[] }`; `FamilySheet` gains `add_child: AddChildTarget`.

#### 6. Clickable dashed card without role coupling

**File**: `resources/js/components/tree/unknown-person-card.tsx`, `resources/js/components/tree/parent-slot-dialog.tsx`

**Intent**: The same dashed "add" card serves the parent slot and the child card.

**Contract**: `UnknownPersonCard` replaces `parentOf?: ParentSlotTarget` with `addLabel?: string`. When set, it renders the existing button (same classes, `Plus` icon) with `aria-label={addLabel}`; otherwise it renders the static label. `ParentSlotDialog` passes `addLabel` „Dodaj ojca/matkę/rodzica: {display_name}" (same text as today).

#### 7. Shared pick-or-create dialog shell

**File**: `resources/js/components/tree/pick-or-create-person-dialog.tsx` (new), `resources/js/components/tree/parent-slot-dialog.tsx`

**Intent**: One implementation of "list of tree people, or a new-person form" for the parent slot, the child card and later S-27.

**Contract**: The shell owns:
- `Dialog` open state and the trigger (passed as a node, rendered through `DialogTrigger asChild`).
- The list/form view, the search text, the pick error and the in-flight id.
- `useOptionalList('personCandidates', candidates)` and the reset-on-open behaviour.

Props:
- `title` and `description`.
- `candidates`.
- `select(list) → { visible, hiddenCount, hiddenHint }`, the caller's filtering.
- `pick(candidateId) → { url, data }`, posted with `preserveScroll`. On success the dialog closes. On error it shows the first of `relation`, `child_id`, `parent_id`, `other_parent_id`.
- Optional `form`: a Wayfinder form definition, hidden inputs, and `PersonFormFields` props (`lockedGender`, `defaults`), plus `genders`/`religions`. Without `form`, the „Nowa osoba" button is not rendered.

The form view keeps S-25's footer („Wróć do listy" / „Zapisz"), the `relation`/`role` error line and scroll-to-first-error. `ParentSlotDialog` becomes a thin wrapper with identical visible behaviour, and its `pluralPeople` is replaced by `pluralPhrase` from `@/lib/plural`.

#### 8. Add-child dialog (list view) and the card in the children row

**File**: `resources/js/components/tree/add-child-dialog.tsx` (new), `resources/js/components/tree/family-sheet.tsx`

**Intent**: Clicking „Dodaj dziecko" opens the dialog; picking a person makes them the pair's child.

**Contract**:
- `AddChildDialog({ target: AddChildTarget, candidates, genders, religions })` wraps the shell:
  - Trigger: `UnknownPersonCard` labelled „Dodaj dziecko", `addLabel` „Dodaj dziecko: {names}", where names are joined with „ i ".
  - Title „Dodaj dziecko — {names}".
  - Description „Rodzice: {names}. Wybierz osobę z drzewa albo dodaj nową.", or with one parent „Rodzic: {name} (drugi nieznany). Wybierz osobę z drzewa albo dodaj nową.".
- The visible list is the candidates minus `excluded_ids` and `blocked_ids`. The hidden count is the candidates in `blocked_ids`, independent of the search. The hint reads `pluralPhrase(n, '1 osobę ukryto — ma już innych rodziców.', 'n osoby ukryto — mają już innych rodziców.', 'n osób ukryto — mają już innych rodziców.')`. The empty state reads „Brak pasujących osób w drzewie.".
- Picking posts `PersonChildController.attach(target.parents[0].id)` with `{child_id, other_parent_id: target.parents[1]?.id ?? null}`.
- No `form` in phase 1.
- `FamilySheet` renders the dialog as a `col-span-2` cell after the last children row, or after the „Brak dzieci w tym związku." line. It sits outside `ChildrenRows`, so the connector brackets are unaffected.
- `show.tsx` passes `personCandidates` through.

#### 9. Feature tests

**File**: `tests/Feature/ParentChildRelationsTest.php`, `tests/Feature/TreeTest.php`

**Intent**: Prove the metadata, the attach endpoint's rules and atomicity, and privacy.

**Contract**:
- Relations (attach):
  - Pair + parentless person → two relations, redirect back to `tree.show(F)?spouse=P`, toast „Dodano dziecko".
  - Child of F only → only the P link is added.
  - Already a child of both → „Ta relacja już istnieje.".
  - Pair + person with an outside parent → „Ta osoba ma już dwoje rodziców." and no relation left (the first link was rolled back).
  - No `other_parent_id` + person with a mother, F male → linked, the mother kept.
  - No `other_parent_id` + person with a father, F male → „Ta osoba ma już ojca.".
  - F's own parent as child → cycle error.
  - `child_id` = the route person → validation error.
  - `child_id` or `other_parent_id` from another user's tree → validation error, nothing created.
  - Other user's focus → 404; guest → login redirect.
- Tree:
  - Partner union → `add_child.parents` [F, P].
  - `excluded_ids` contains F, P, F's parent, and a child of both.
  - `blocked_ids` contains a child of F with an outside parent; a child of F only appears in neither.
  - `unknown` union and a person with no union → `parents` [F]; F's children are excluded.
  - Single-parent same-sex blocking; opposite-sex other parent not blocked.
  - `default_last_name` = the male partner's surname; a pair without a man → the focus's surname.
  - `personCandidates` absent on a normal visit and present on a partial reload (renamed existing test).

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Pair: tree centred on Jan with union „Anna · ślub 1950", unrelated parentless Piotr exists → „Dodaj dziecko", pick Piotr → Piotr appears among the children; centre and union unchanged; toast „Dodano dziecko"; Piotr's preview lists Jan and Anna under „Rodzice"
- A child of Jan from the „Nieznany partner" union is offered in Anna's union and, after picking, moves to it (the „Nieznany partner" union disappears when it had only that child)
- The list never shows Jan, Anna, their parents/grandparents, or children already of both; a person with a parent outside the pair is hidden and counted in „… ukryto — mają już innych rodziców."
- No partner (a person without any union): the dialog says „Rodzic: … (drugi nieznany)"; picking Anna's son (mother Anna) makes Jan his father; the tree now shows Jan's union with Anna, with the son among the children
- Parent slot regression (S-25): father/mother/parent slots list and attach exactly as before, including the unknown-sex hint text
- Visual gate: „Dodaj dziecko" card at rest / hover / keyboard focus, next to „Brak dzieci…" and after a full row of four children; dialog with results, with filter and no matches, with the hidden hint, loading skeleton, error message — at 375px and 1280px, light and dark; screenshots attached to the review

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: New child from the same dialog

### Overview

A dedicated endpoint creates a person and links them to every parent of the pair atomically; the add-child dialog gains the „Nowa osoba" view with the default surname.

### Changes Required:

#### 1. Create-child action

**File**: `app/Actions/Relations/CreateChildForParents.php` (new)

**Intent**: A new person and all their parent links exist together or not at all.

**Contract**: `handle(Tree $tree, list<Person> $parents, array $data): Person`. Inside `DB::transaction`, run `CreatePerson::handle($tree, $data)`, then `AttachChildToParents::handle($tree, $person, $parents)`. Any `ValidationException` rolls back the person.

#### 2. Request, controller action, route

**File**: `app/Http/Requests/PersonChildStoreRequest.php` (new), `app/Http/Controllers/PersonChildController.php`, `routes/web.php`

**Intent**: „Nowa osoba" posts the person form plus the pair's partner for a focus person from the user's own tree.

**Contract**:
- Route `POST persons/{person}/children` → `PersonChildController@store`, name `persons.children.store`.
- The request extends `PersonStoreRequest` rules with `other_parent_id` (nullable integer, exists in the user's tree, not the route person).
- The controller:
  - `Gate::authorize('view', $person)`.
  - Loads the optional other parent.
  - Calls the action with `$request->safe()->except('other_parent_id')`, flashes the toast „Dodano dziecko" and returns `back()`.
- Regenerate Wayfinder.

#### 3. „Nowa osoba" view in the add-child dialog

**File**: `resources/js/components/tree/add-child-dialog.tsx`

**Intent**: One dialog: list first, then the form, and back.

**Contract**: Pass the shell's `form`:
- `PersonChildController.store.form(target.parents[0].id)`.
- A hidden `other_parent_id` input, only when there is a second parent.
- `PersonFormFields` defaults `{ last_name: target.default_last_name }`, with no `lockedGender`.
- The submit button's `data-test` is `create-child-button`.

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Pair Jan Kowalski + Anna Nowak → „Nowa osoba": surname starts as „Kowalski", sex freely selectable; saving shows the new child on the tree with „Dodano dziecko", centre and union unchanged; the child's preview lists both parents
- Pair where the partner is the man (tree centred on Anna) → surname starts as Jan's surname; a single mother without a partner → her own surname
- „Nieznany partner" union → „Nowa osoba" → the child appears in that union with only the focus as parent
- „Wróć do listy" returns to the list with candidates still loaded; closing and reopening starts on the list
- Submitting an empty form shows the name error inside the dialog and creates nothing
- Visual gate: form view at 375px (full screen, scrolling, pinned header/footer) and 1280px, light and dark; screenshots attached to the review

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None: the sheet metadata and the rules are exercised through feature tests, matching the existing suite.

### Integration Tests:

- `ParentChildRelationsTest` (attach):
  - Pair and single-parent linking; only-missing-link behaviour.
  - Duplicate; three-parent rollback; single-parent same-sex; cycle.
  - Foreign ids; 404; guest.
- `ParentChildRelationsTest` (create):
  - Happy path for a pair (person + birth event + two relations) and for a single parent (one relation).
  - An empty first name → no person.
  - `other_parent_id` from another tree → validation error, no person.
  - A pair of the same known sex (e.g. two men as co-parents in legacy data) → „Ta osoba ma już ojca." and no person or relation left.
  - Other user's focus → 404; guest → login.
- `TreeTest`: `add_child` for a partner union, the `unknown` union and no union; exclusion/blocking sets; default surname; renamed `personCandidates`.

### Manual Testing Steps:

1. On a real tree, attach an existing child to a married pair, then create a new child for the same pair, without leaving the tree.
2. Move a child from „Nieznany partner" into a known union by picking them in that union's dialog.
3. On a person with no union, attach a child who already has a mother; check that the tree now shows the new union with the child.
4. Re-check the S-25 parent slot dialog end to end (list, hint, „Nowa osoba").
5. Visual gate screenshots as listed in each phase.

## Performance Considerations

`excluded_ids`/`blocked_ids` come from the graph already built for the sheet. Single-parent mode needs one additional query: the id → gender map for the other parents of candidates that have one. `personCandidates` is unchanged: one query, evaluated only on the dialog's partial reload.

## Migration Notes

None: no schema changes. The prop rename is internal to the tree page.

## References

- Roadmap slice: `context/foundation/roadmap.md`, S-26 `tree-child-existing-or-new`
- PRD: `context/foundation/prd.md` (v4), FR-005, FR-011, FR-012; `context/foundation/prd-v3.md`, FR-002
- Prior plan (slot dialog, candidate prop, atomic create-and-link): `context/archive/2026-09-28-tree-parent-slot-existing-or-new/plan.md`
- Sheet builder: `app/Actions/Trees/BuildFamilySheet.php:45-153, 182-216`
- Rules: `app/Actions/Relations/CreateParentChildRelation.php:20-80`; atomic pattern: `app/Actions/Relations/CreateParentForPerson.php:23-32`
- Dialog to extract from: `resources/js/components/tree/parent-slot-dialog.tsx:65-250`; card: `resources/js/components/tree/unknown-person-card.tsx:21-40`
- Children row: `resources/js/components/tree/family-sheet.tsx:90-128, 239-246`
- Plural helper: `resources/js/lib/plural.ts:5-27`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Attach an existing child to the shown pair

#### Automated

- [x] 1.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php` — 3d1c35c
- [x] 1.2 Larastan passes: `vendor/bin/sail composer types:check` — 3d1c35c
- [x] 1.3 Format and lint pass: `vendor/bin/sail npm run check` — 3d1c35c
- [x] 1.4 Type check passes: `vendor/bin/sail npm run types:check` — 3d1c35c
- [x] 1.5 Production build succeeds: `vendor/bin/sail npm run build` — 3d1c35c
- [x] 1.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent` — 3d1c35c

#### Manual

- [x] 1.7 Pair: tree centred on Jan with union „Anna · ślub 1950", unrelated parentless Piotr exists → „Dodaj dziecko", pick Piotr → Piotr appears among the children; centre and union unchanged; toast „Dodano dziecko"; Piotr's preview lists Jan and Anna under „Rodzice" — 3d1c35c
- [x] 1.8 A child of Jan from the „Nieznany partner" union is offered in Anna's union and, after picking, moves to it (the „Nieznany partner" union disappears when it had only that child) — 3d1c35c
- [x] 1.9 The list never shows Jan, Anna, their parents/grandparents, or children already of both; a person with a parent outside the pair is hidden and counted in „… ukryto — mają już innych rodziców." — 3d1c35c
- [x] 1.10 No partner (a person without any union): the dialog says „Rodzic: … (drugi nieznany)"; picking Anna's son (mother Anna) makes Jan his father; the tree now shows Jan's union with Anna, with the son among the children — 3d1c35c
- [x] 1.11 Parent slot regression (S-25): father/mother/parent slots list and attach exactly as before, including the unknown-sex hint text — 3d1c35c
- [x] 1.12 Visual gate: „Dodaj dziecko" card at rest / hover / keyboard focus, next to „Brak dzieci…" and after a full row of four children; dialog with results, with filter and no matches, with the hidden hint, loading skeleton, error message — at 375px and 1280px, light and dark; screenshots attached to the review — 3d1c35c

### Phase 2: New child from the same dialog

#### Automated

- [x] 2.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php` — f63e96f
- [x] 2.2 Larastan passes: `vendor/bin/sail composer types:check` — f63e96f
- [x] 2.3 Format and lint pass: `vendor/bin/sail npm run check` — f63e96f
- [x] 2.4 Type check passes: `vendor/bin/sail npm run types:check` — f63e96f
- [x] 2.5 Production build succeeds: `vendor/bin/sail npm run build` — f63e96f
- [x] 2.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent` — f63e96f

#### Manual

- [x] 2.7 Pair Jan Kowalski + Anna Nowak → „Nowa osoba": surname starts as „Kowalski", sex freely selectable; saving shows the new child on the tree with „Dodano dziecko", centre and union unchanged; the child's preview lists both parents — f63e96f
- [x] 2.8 Pair where the partner is the man (tree centred on Anna) → surname starts as Jan's surname; a single mother without a partner → her own surname — f63e96f
- [x] 2.9 „Nieznany partner" union → „Nowa osoba" → the child appears in that union with only the focus as parent — f63e96f
- [x] 2.10 „Wróć do listy" returns to the list with candidates still loaded; closing and reopening starts on the list — f63e96f
- [x] 2.11 Submitting an empty form shows the name error inside the dialog and creates nothing — f63e96f
- [x] 2.12 Visual gate: form view at 375px (full screen, scrolling, pinned header/footer) and 1280px, light and dark; screenshots attached to the review — f63e96f
