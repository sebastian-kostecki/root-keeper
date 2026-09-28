# Existing or New Parent from an Empty Tree Slot Implementation Plan

## Overview

Roadmap slice S-25 (v4 north star; v4:FR-004, v4:FR-011, v4:US-01, v3:FR-001, v3:FR-012, v3:US-01). An empty parent slot on the tree — "Nieznany ojciec", "Nieznana matka", "Nieznany rodzic", at parent and grandparent level — becomes clickable and opens one dialog: first a searchable list of existing people from the tree (filtered by the slot's sex and by the relation rules), plus a "Nowa osoba" button that switches the same dialog to the person form with the sex set and locked by the slot. After saving, the parent is on the tree immediately and the tree keeps its centre and union. The server additionally refuses a second parent of the same known sex, everywhere.

## Current State Analysis

- The sheet is built by `BuildFamilySheet` (`app/Actions/Trees/BuildFamilySheet.php:44-152`). Parent slots come from `parentSlots()` (`:223-250`): no known parents → "Nieznany ojciec" + "Nieznana matka"; one male → "Nieznana matka"; one female → "Nieznany ojciec"; one of unknown sex → "Nieznany rodzic". Grandparent slots (`:257-268`) are `parentSlots()` of each known parent; above an unknown parent they are `empty`. An unknown slot carries only `{kind: 'unknown', label}` (`:281-284`) — the same shape as the "Nieznany partner" slot (`:211`), which is out of scope.
- The frontend renders unknown slots as a static dashed label (`resources/js/components/tree/unknown-person-card.tsx:1-7`, used in `family-sheet.tsx:24-26`). `slotSpouse()` (`family-sheet.tsx:9-17`) relies on `kind === 'unknown'` for union preselection, so the kind must stay.
- Relation rules live in `CreateParentChildRelation` (`app/Actions/Relations/CreateParentChildRelation.php:19-50`): duplicate, max two parents, cycle — all as `ValidationException` on key `relation`. There is no sex rule; two fathers are possible today. `ListRelationCandidates` (`app/Actions/Relations/ListRelationCandidates.php:24-67`) mirrors these rules as blocked reasons for the preview-modal picker.
- Candidates reach the frontend only through the shared optional prop `relationCandidates`, keyed on the preview's `?person=` (`app/Http/Middleware/HandleInertiaRequests.php:59-63`), loaded on demand by `useRelationCandidates` (`resources/js/hooks/use-relation-candidates.ts:16-41`). The tree has no way to ask for candidates for an arbitrary child.
- `ParentChildRelationController@store` (`app/Http/Controllers/ParentChildRelationController.php:17-29`) takes `parent_id`, `child_id` (`ParentChildRelationStoreRequest.php:16-22`) and redirects `back()` with "Dodano relację".
- `PersonController@store` redirects to `tree.show(new person)` (`PersonController.php:72-79`); `CreatePerson` (`app/Actions/Persons/CreatePerson.php:43-62`) runs in its own transaction. `PersonFormDialog` (`resources/js/components/persons/person-form-dialog.tsx:15-104`) owns its Dialog/trigger and cannot preset or lock sex; `PersonFormFields` (`person-form-fields.tsx:35-182`) keeps sex in local state with a hidden input.
- The tree page (`resources/js/pages/tree/show.tsx:14-17`, `TreeController.php:17-39`) receives only `sheet` and `rootPersonId` — no `genders`/`religions` for the form.
- `PersonCandidateList` (`resources/js/components/persons/person-candidate-list.tsx:30-120`) is typed to `RelationCandidate`, and its empty state links to the person list.
- No `.ai/rules` directory, no `context/foundation/lessons.md`, no screenshot test infrastructure.

## Desired End State

- On the tree, every "Nieznany ojciec / Nieznana matka / Nieznany rodzic" slot (focus's and partner's parents, and grandparents above known parents) is a button labelled with its text plus an add affordance; "Nieznany partner" stays a static label.
- Clicking it opens one dialog titled e.g. „Dodaj ojca — Jan Kowalski": a name search over existing people of the tree, excluding the child, the child's current parents and the child's descendants; a father slot lists only men, a mother slot only women, a generic parent slot everyone. When unknown-sex people are hidden, a line says how many (e.g. „3 osoby bez podanej płci ukryto — uzupełnij płeć, aby je tu zobaczyć"). Picking a person links them as parent; the dialog closes, the tree (same centre, same union) shows the new parent, toast „Dodano relację".
- The dialog's „Nowa osoba" switches to the person form (with a way back to the list): sex set and locked by the slot (father → mężczyzna, mother → kobieta; generic parent → free choice); in a father slot the surname starts as the child's surname (editable). Saving creates the person and the relation atomically; the tree shows the parent, toast „Dodano rodzica". Validation or rule errors keep the dialog open with messages and create nothing.
- When a parent link is created (from the tree or from the preview picker), the server refuses a second parent of the same known sex, and refuses a slot request whose person's sex does not match the slot role; the preview picker shows these as blocked reasons.

### Key Discoveries:

- `ParentChildGraph` is already loaded inside `BuildFamilySheet` (`:46`), so each parent slot can carry its exclusion list (`[child] ∪ parentsOf(child) ∪ descendantsOf(child)`) without extra queries — the same set `CreateParentChildRelation` rejects for "as parent".
- The slot's role is derivable in `parentSlots()` from the label branch (`:239-249`) — derive it there, never from the label text on the client.
- `CreatePerson` already wraps itself in `DB::transaction`; nesting it with `CreateParentChildRelation` inside an outer transaction gives savepoints, so a rule failure rolls back the new person.
- The "optional prop + cache last list" pattern (`use-relation-candidates.ts:22-28`) handles validation-error redirects that drop optional props.

## What We're NOT Doing

- Child from the tree (S-26), spouse slot and „Połącz jako małżeństwo" (S-27), relation removal from the tree (S-28), read-only relations in the preview (S-29). The preview modal's add/remove parent stays as is (it only gains the sex rule and blocked reasons).
- Clickable "Nieznany partner" or `empty` slots (grandparents above an unknown parent); first create the parent, then the grandparent slots appear.
- Setting sex on an existing unknown-sex person when attaching (declined: they are hidden with a hint instead).
- Prefilling anything for the mother or generic-parent slot; prefilling other fields for the father.
- Cleaning up existing data that already has two parents of the same sex (the rule only blocks new links).
- Guarding sex changes in the person edit form: changing an unknown-sex parent to the sex of the child's other parent is still possible (a rare, deliberate edit); the same-sex rule applies only when a link is created.
- Duplicate merging, drag-and-drop, deep links (PRD v4 non-goals); new tokens, shadcn `ui/*` edits, or screenshot-test infrastructure.

## Implementation Approach

Two vertical phases. Phase 1 adds the server rule, slot metadata, a lazily loaded candidate list and the clickable slot with the dialog's list view — attaching an existing parent works end to end (the v4 US-01 flow). Phase 2 adds the "Nowa osoba" view and a dedicated endpoint that creates the person and the relation in one transaction.

Candidate source: the tree page gets an `Inertia::optional` prop `parentCandidates` — every person of the tree as `{id, display_name, birth_date, gender}` in the standard person order — loaded when a slot dialog opens. Filtering happens client-side from data the server computed: the slot's `excluded_ids` (rules) and `role` (sex). The server remains the authority (rules + role check), the client only hides.

Attaching reuses `parent-child-relations.store` with a new optional `role`; creating uses a new nested route `POST /persons/{person}/parents` (the `{person}` is the child). Both redirect `back()`, so the tree URL (with `spouse=`) is preserved.

## Critical Implementation Details

**State sequencing** — The same-sex check in `CreateParentChildRelation` must run inside the existing lock/transaction, after the two-parents check and before insert, reading genders of the child's current parents fresh from the database (not from the graph, which holds ids only). The role check (slot sex vs person sex) belongs to the request/controller layer, not to the action, because the preview picker has no role.

**User experience spec** — The dialog must reset to the list view and clear the search each time it opens; switching list → form → list must not lose the loaded candidates. The dialog uses `FormDialogContent` (full-screen below `sm`, overlay click blocked) so the long person form scrolls on a phone.

## Phase 1: Attach an existing parent from the slot

### Overview

Server-side sex rule, slot metadata, the `parentCandidates` prop and the clickable slot with the dialog's list view.

### Changes Required:

#### 1. Same-sex parent rule

**File**: `app/Actions/Relations/CreateParentChildRelation.php`

**Intent**: A child cannot get a second parent of the same known sex (the prd-v3 guardrail "nie da się dodać drugiego ojca"), wherever the link is made.

**Contract**: When the new parent's `gender` is not null and any current parent of the child has the same gender, throw `ValidationException` on `relation`: male → „Ta osoba ma już ojca.", female → „Ta osoba ma już matkę.". Unknown sex on either side never conflicts. Existing checks and their order (duplicate, two parents, cycle) unchanged.

#### 2. Blocked reasons in the preview picker

**File**: `app/Actions/Relations/ListRelationCandidates.php`

**Intent**: The preview-modal picker must not offer links the server will now refuse.

**Contract**: `as_parent_blocked_reason` gains „Ma już ojca" / „Ma już matkę" when the candidate's known sex equals that of an existing parent of the person; `as_child_blocked_reason` gains „Ma już ojca" / „Ma już matkę" when the candidate already has a parent of the person's known sex. Existing reasons keep precedence. Sexes come from the already loaded candidate collection plus the person (an id → gender map) — no query per candidate.

#### 3. Role on the attach request

**File**: `app/Http/Requests/ParentChildRelationStoreRequest.php`

**Intent**: A link made from a tree slot is checked against the slot's sex.

**Contract**: Optional `role` ∈ `father|mother|parent`. In `withValidator()->after()`: `father` requires the parent's gender `male`, `mother` requires `female` (error on `relation`: „Wybrana osoba nie pasuje do tego miejsca na drzewie."); `parent` and absent role add nothing. `ParentChildRelationController` is unchanged — it still reads only `parent_id`/`child_id`.

#### 4. Parent slot metadata on the sheet

**File**: `app/Actions/Trees/BuildFamilySheet.php`

**Intent**: The client knows which person a slot adds a parent to, in which role, and whom the rules exclude.

**Contract**: Unknown slots produced by `parentSlots()` become `{kind: 'unknown', label, parent_of: {id, display_name, last_name, role: 'father'|'mother'|'parent', excluded_ids: list<int>}}` where `id` is the person above whom the slot sits, `role` follows the branch (`Nieznany ojciec` → father, `Nieznana matka` → mother, `Nieznany rodzic` → parent) and `excluded_ids` = the person, their current parents and descendants (from the in-memory graph). The partner slot keeps `{kind: 'unknown', label}`. Update the `Slot` phpstan type.

#### 5. Tree page props

**File**: `app/Http/Controllers/TreeController.php`

**Intent**: The dialog gets its candidate list lazily; phase 2's form gets its options.

**Contract**: Both `index` and `show` add `parentCandidates` (`Inertia::optional`, every person of the user's tree ordered like `ListRelationCandidates` — last name nulls last, last name, first name, id — as `{id, display_name, birth_date, gender}`) plus `genders` (`Gender::options()`) and `religions` (`Religion::options()`). Extract the candidate query into a small action (`app/Actions/Trees/ListParentCandidates.php`) rather than inlining it twice.

#### 6. Types

**File**: `resources/js/types/tree.ts` (and the barrel if needed)

**Intent**: Type the new slot shape and candidate row.

**Contract**: `SheetSlot` unknown variant gets optional `parent_of?: { id: number; display_name: string; last_name: string | null; role: 'father' | 'mother' | 'parent'; excluded_ids: number[] }`; new `ParentCandidate = { id: number; display_name: string; birth_date: string | null; gender: 'male' | 'female' | null }`.

#### 7. On-demand prop hook

**File**: `resources/js/hooks/use-relation-candidates.ts` (or a sibling generic hook)

**Intent**: Reuse the load-on-open + keep-last-list behaviour for `parentCandidates`.

**Contract**: A generic hook parameterised by prop name and item type (`only: [name]`), with `useRelationCandidates` delegating to it so existing callers are unchanged.

#### 8. Candidate list reuse

**File**: `resources/js/components/persons/person-candidate-list.tsx`

**Intent**: The same list component serves the tree dialog, where blocked people are hidden rather than disabled and the empty state differs.

**Contract**: Generic over rows with `{id, display_name, birth_date}`; `getBlockedReason` becomes optional; an optional `emptyState` node replaces the default "Nie masz jeszcze innych osób…" text; an optional `footer` node renders under the list (for the hidden-count hint). Existing callers unchanged.

#### 9. Clickable slot and dialog (list view)

**File**: `resources/js/components/tree/unknown-person-card.tsx`, `resources/js/components/tree/parent-slot-dialog.tsx` (new), `resources/js/components/tree/family-sheet.tsx`, `resources/js/pages/tree/show.tsx`

**Intent**: Clicking an empty parent slot opens the dialog; picking a person links them.

**Contract**:
- `UnknownPersonCard` with `parent_of` renders as the dialog trigger: same dashed card, a `Plus` icon, hover/focus-visible states like `SheetPersonCard`, `aria-label` e.g. „Dodaj ojca: Jan Kowalski". Without `parent_of` it stays a label.
- `ParentSlotDialog({ slot: parent_of, label, candidates })` on `FormDialogContent`: title „Dodaj ojca / matkę / rodzica — {display_name}", description „Wybierz osobę z drzewa albo dodaj nową."; list view = `PersonCandidateList` over `parentCandidates` minus `excluded_ids`, filtered by role (father → `male`, mother → `female`, parent → all); footer hint when > 0 (father/mother only) counting unknown-sex people not in `excluded_ids`, independent of the search text, with Polish plural forms („1 osobę bez podanej płci ukryto", „3 osoby … ukryto", „5 osób … ukryto" — 1 / 2–4 except 12–14 / otherwise); empty state „Brak pasujących osób w drzewie."; selecting posts `ParentChildRelationController.store` with `{parent_id, child_id: parent_of.id, role}`, `preserveScroll`, closes on success, shows `errors.relation ?? errors.parent_id ?? errors.child_id` under the list on error. A disabled-until-phase-2 „Nowa osoba" button is not rendered in phase 1.
- `FamilySheet`/tree page pass `parentCandidates` down (read from page props).

#### 10. Feature tests

**File**: `tests/Feature/ParentChildRelationsTest.php`, `tests/Feature/TreeTest.php`

**Intent**: Prove the rule, the role check, the slot metadata and the prop.

**Contract**:
- Relations: second father refused with „Ta osoba ma już ojca."; second mother refused; unknown-sex parent + male allowed, male parent + unknown-sex allowed; `role=father` with a woman refused, `role=mother` with a woman accepted, `role=parent` with anyone accepted; redirect back to `tree.show(X)?spouse=Y` preserved; preview candidates show „Ma już ojca" (as parent) and „Ma już ojca" (as child).
- Tree, existing tests: the exact-array placeholder assertions in `tests/Feature/TreeTest.php:276-279, 285, 293, 300, 316` now check `kind`/`label` plus the expected `parent_of` (`id`, `role`); the "Nieznany partner" assertion (`:204`) stays exact.
- Tree, new tests: focus without parents → two slots with `parent_of.role` father/mother and `excluded_ids` containing focus, and a descendant; one male parent → mother slot; one unknown-sex parent → `role=parent`; grandparent slot above a known parent carries that parent's id; partner "Nieznany partner" slot has no `parent_of`; `parentCandidates` absent on a normal visit and present on a partial reload (ordering + gender); `genders`/`religions` present; other user's person → 404 (existing test still passes).

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- US-01: tree centred on Jan with „Nieznany ojciec", Józef (male, unrelated) exists → click the slot, pick Józef → Józef appears as Jan's father without leaving the tree; centre and union unchanged; the preview modal of Jan lists Józef under „Rodzice"
- The father slot lists only men, the mother slot only women, a „Nieznany rodzic" slot everyone; Jan himself, his parents and his descendants never appear; the hidden-count hint appears when unknown-sex people exist and disappears when there are none
- A grandparent slot (above Józef) opens „Dodaj ojca — Józef …" and attaches to Józef
- „Nieznany partner" is not clickable
- In the preview modal, adding a second father is blocked with „Ma już ojca"
- Visual gate: slot at rest / hover / keyboard focus, dialog with results, with filter and no matches, with hint, loading skeleton, error message — at 375px and 1280px, light and dark; screenshots attached to the review

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: New parent from the same dialog

### Overview

A dedicated endpoint creates a person and links them as the slot's parent atomically; the dialog's „Nowa osoba" view hosts the person form with slot-locked sex and the father's surname prefilled.

### Changes Required:

#### 1. Create-parent action

**File**: `app/Actions/Relations/CreateParentForPerson.php` (new)

**Intent**: A new person and their parent link exist together or not at all.

**Contract**: `handle(Tree $tree, Person $child, array $data): Person` — inside `DB::transaction`, `CreatePerson::handle($tree, $data)` then `CreateParentChildRelation::handle($tree, $person, $child)`; any `ValidationException` from the relation rolls back the person.

#### 2. Request, controller, route

**File**: `app/Http/Requests/PersonParentStoreRequest.php` (new), `app/Http/Controllers/PersonParentController.php` (new), `routes/web.php`

**Intent**: The slot's "Nowa osoba" posts the person form plus the slot role for a given child from the user's own tree.

**Contract**: Route `POST persons/{person}/parents` → `PersonParentController@store`, name `persons.parents.store`. The request extends `PersonStoreRequest` rules with `role` required ∈ `father|mother|parent` and requires `gender` = `male` for father, `female` for mother (error on `gender`: „Płeć musi pasować do miejsca na drzewie."). Controller: `Gate::authorize('view', $person)` (other user's child → 404 via the existing policy), calls the action, flashes toast „Dodano rodzica", returns `back()`. Regenerate Wayfinder.

#### 3. Locked sex and default surname in the form fields

**File**: `resources/js/components/persons/person-form-fields.tsx`

**Intent**: The slot dictates the sex of a new father/mother; a new father starts with the child's surname.

**Contract**: Optional `lockedGender?: 'male' | 'female'` — the select shows that value, is disabled, and the hidden input submits it; optional `defaults?: { last_name?: string | null }` used when no `person` is given. Existing callers unchanged.

#### 4. „Nowa osoba" view in the slot dialog

**File**: `resources/js/components/tree/parent-slot-dialog.tsx`, optionally a shared form body extracted from `resources/js/components/persons/person-form-dialog.tsx`

**Intent**: One dialog: list first, then the form, and back.

**Contract**: The list view gets a „Nowa osoba" button; the form view renders a `Form` for `PersonParentController.store.form(parent_of.id)` with hidden `role`, `PersonFormFields` with `lockedGender` (father/mother only), `defaults.last_name = parent_of.last_name` (father only), footer „Wróć do listy" + „Zapisz"; `onSuccess` closes the dialog, `onError` scrolls to the first error (same behaviour as `PersonFormDialog`); a `relation` error shows above the footer. Reopening the dialog starts in the list view. If the form body is extracted, `PersonFormDialog` must behave exactly as before.

#### 5. Feature tests

**File**: `tests/Feature/ParentChildRelationsTest.php`

**Intent**: Prove creation, atomicity, privacy and the role/sex check.

**Contract**: Happy path (father slot, male) creates the person with birth event and the relation, redirects back to `tree.show(X)?spouse=Y`, toast; `role=parent` with no gender accepted; `role=father` with `gender=female` → error on `gender`, no person created; child already with two parents → `relation` error and person count unchanged; child already with a father + `role=parent` male → „Ta osoba ma już ojca.", no person created; other user's child → 404; guest → login redirect.

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- „Nieznany ojciec" → „Nowa osoba": sex shows „Mężczyzna" and cannot be changed, surname starts as the child's surname; saving shows the new father on the tree with „Dodano rodzica", centre and union unchanged
- „Nieznana matka" → „Nowa osoba": sex locked to „Kobieta", surname empty
- „Nieznany rodzic" → „Nowa osoba": sex freely selectable, surname empty
- „Wróć do listy" returns to the list with candidates still loaded; closing and reopening starts on the list
- Submitting an empty form shows the name error inside the dialog and creates nothing
- Visual gate: form view at 375px (full screen, scrolling, pinned header/footer) and 1280px, light and dark, locked select state; screenshots attached to the review

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None — rules and slot metadata are exercised through feature tests, matching the existing suite.

### Integration Tests:

- `ParentChildRelationsTest`: same-sex rule (both sexes, unknown sex on each side), role check on attach, preview blocked reasons, create-parent endpoint (happy path, role/sex mismatch, rollback on rule failure, 404, guest).
- `TreeTest`: `parent_of` on focus/partner/grandparent slots for all three roles, none on the partner slot, `excluded_ids` content, `parentCandidates` optional behaviour, `genders`/`religions`.

### Manual Testing Steps:

1. On a real tree, attach an existing father to the centre person, then a grandfather above him — both from slots, without leaving the tree.
2. Create a new mother from a slot; try a father slot for someone who already has a father via the preview modal (blocked).
3. Check a person with an unknown-sex parent: the other slot says „Nieznany rodzic" and lists everyone.
4. Visual gate screenshots as listed in each phase.

## Performance Considerations

`excluded_ids` reuses the in-memory graph already built for the sheet (no extra query). `parentCandidates` is one ordered query over the tree's people with birth events, evaluated only on the dialog's partial reload — the same cost as today's `relationCandidates`.

## Migration Notes

None — no schema changes. Existing data with two same-sex parents (if any) is left as is; only new links are refused.

## References

- Roadmap slice: `context/foundation/roadmap.md` — S-25 `tree-parent-slot-existing-or-new`
- PRD: `context/foundation/prd.md` (v4) — FR-004, FR-011, US-01; `context/foundation/prd-v3.md` — FR-001, FR-012, Guardrails
- Prior plan (preview modal, optional props, redirects): `context/archive/2026-09-28-person-preview-in-modal/plan.md`
- Slot builder: `app/Actions/Trees/BuildFamilySheet.php:223-284`
- Rules: `app/Actions/Relations/CreateParentChildRelation.php:19-50`, `app/Actions/Relations/ListRelationCandidates.php:24-67`
- Picker pattern: `resources/js/components/persons/relative-picker-dialog.tsx:24-106`, `resources/js/hooks/use-relation-candidates.ts:16-41`
- Modal layout: `resources/js/components/form-dialog.tsx`, `resources/js/components/persons/person-form-dialog.tsx:30-104`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Attach an existing parent from the slot

#### Automated

- [x] 1.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- [x] 1.2 Larastan passes: `vendor/bin/sail composer types:check`
- [x] 1.3 Format and lint pass: `vendor/bin/sail npm run check`
- [x] 1.4 Type check passes: `vendor/bin/sail npm run types:check`
- [x] 1.5 Production build succeeds: `vendor/bin/sail npm run build`
- [x] 1.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual

- [ ] 1.7 US-01: tree centred on Jan with „Nieznany ojciec", Józef (male, unrelated) exists → click the slot, pick Józef → Józef appears as Jan's father without leaving the tree; centre and union unchanged; the preview modal of Jan lists Józef under „Rodzice"
- [ ] 1.8 The father slot lists only men, the mother slot only women, a „Nieznany rodzic" slot everyone; Jan himself, his parents and his descendants never appear; the hidden-count hint appears when unknown-sex people exist and disappears when there are none
- [ ] 1.9 A grandparent slot (above Józef) opens „Dodaj ojca — Józef …" and attaches to Józef
- [ ] 1.10 „Nieznany partner" is not clickable
- [ ] 1.11 In the preview modal, adding a second father is blocked with „Ma już ojca"
- [ ] 1.12 Visual gate: slot at rest / hover / keyboard focus, dialog with results, with filter and no matches, with hint, loading skeleton, error message — at 375px and 1280px, light and dark; screenshots attached to the review

### Phase 2: New parent from the same dialog

#### Automated

- [x] 2.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- [x] 2.2 Larastan passes: `vendor/bin/sail composer types:check`
- [x] 2.3 Format and lint pass: `vendor/bin/sail npm run check`
- [x] 2.4 Type check passes: `vendor/bin/sail npm run types:check`
- [x] 2.5 Production build succeeds: `vendor/bin/sail npm run build`
- [x] 2.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual

- [x] 2.7 „Nieznany ojciec" → „Nowa osoba": sex shows „Mężczyzna" and cannot be changed, surname starts as the child's surname; saving shows the new father on the tree with „Dodano rodzica", centre and union unchanged
- [x] 2.8 „Nieznana matka" → „Nowa osoba": sex locked to „Kobieta", surname empty
- [x] 2.9 „Nieznany rodzic" → „Nowa osoba": sex freely selectable, surname empty
- [x] 2.10 „Wróć do listy" returns to the list with candidates still loaded; closing and reopening starts on the list
- [x] 2.11 Submitting an empty form shows the name error inside the dialog and creates nothing
- [x] 2.12 Visual gate: form view at 375px (full screen, scrolling, pinned header/footer) and 1280px, light and dark, locked select state; screenshots attached to the review
