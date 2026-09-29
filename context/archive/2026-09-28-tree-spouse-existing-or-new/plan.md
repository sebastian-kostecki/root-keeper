# Existing or New Spouse from the Tree Implementation Plan

## Overview

Roadmap slice S-27 (v4:FR-006, v4:FR-011, v3:FR-003). The centre person gets a spouse from the tree: a dashed „Dodaj małżonka" card in the empty partner slot, or a „Dodaj małżonka" button at the end of the union switcher when the centre already has a union. Both open one dialog. It lists existing people of the tree the centre could marry, plus a „Nowa osoba" view with the person form. The union switcher now also shows when the centre has a single union. Shown pairs without a marriage get „Połącz jako małżeństwo" after a confirmation: the parents of the centre, the parents of the partner, and the centre couple when their union is „bez ślubu". Every marriage created from the tree has no date and no place. The user fills in the date, place and confirmation in the person preview.

## Current State Analysis

- A marriage is one `Event` of type `Marriage` with two participants in the role `Spouse`. `CreateMarriage` (`app/Actions/Relations/CreateMarriage.php:24-54`) locks both people and rejects a duplicate pair on `spouse_id` („Ta para ma już małżeństwo."). `MarriageStoreRequest` (`app/Http/Requests/MarriageStoreRequest.php:20-40`) rejects marrying oneself and ids from another tree. The date and place are optional (`app/Concerns/PartialDateValidationRules.php:17-28`). There is no sex or kinship rule, and remarriage is allowed.
- `MarriageController@store` (`app/Http/Controllers/MarriageController.php:20-32`) takes `person_id`, `spouse_id` and an optional `marriage` date. It returns `back()` with the toast „Dodano małżeństwo". Today only the preview's `MarriageFormDialog` uses it (`resources/js/components/persons/marriages-section.tsx:25-34`). S-29 will make that preview read-only.
- `BuildFamilySheet` (`app/Actions/Trees/BuildFamilySheet.php:47-156`):
  - It collects marriage partners (`:54-66`) and co-parents (`:75-86`) into unions. A union without a marriage is labelled „X · bez ślubu" with `is_marriage: false` (`:100-109`).
  - The partner slot is `{kind: 'empty'}` only when there is no union at all (`:305-318`), and `Slot` renders nothing for it (`resources/js/components/tree/family-sheet.tsx:58`).
  - The sheet carries no data about marriages between the shown parents.
- `UnionSwitch` (`resources/js/components/tree/union-switch.tsx:12-15`) hides itself with ≤1 union. Its visits carry `?spouse=`. A `back()` redirect keeps the old `?spouse=`, so after a remarriage the new union would not be selected.
- S-25/S-26 left a reusable kit (`context/archive/2026-09-28-tree-child-existing-or-new/plan.md`):
  - `PickOrCreatePersonDialog` (`resources/js/components/tree/pick-or-create-person-dialog.tsx:118-284`), which offers list or form, `select` / `pick` / `form` props, and `lockedGender`.
  - `UnknownPersonCard` with `addLabel` (`resources/js/components/tree/unknown-person-card.tsx:14-32`).
  - The optional `personCandidates` prop with `gender` (`app/Actions/Trees/ListPersonCandidates.php:17-33`).
  - The atomic create-and-link pattern (`app/Actions/Relations/CreateChildForParents.php:24-33`).
- The shell's pick error reads only `relation`, `child_id`, `parent_id`, `other_parent_id` (`pick-or-create-person-dialog.tsx:168-174`). A duplicate-marriage error on `spouse_id` would be dropped.
- The confirmation pattern is a plain `Dialog` with an „Anuluj"/action footer (`resources/js/components/persons/remove-marriage-dialog.tsx:45-86`).
- No `.ai/rules` directory, no `context/foundation/lessons.md`, no screenshot-test infrastructure.

## Desired End State

- **Empty slot.** When the centre has no union at all, the partner slot shows a dashed „Dodaj małżonka" card. It is a button with a `Plus` icon and the aria-label „Dodaj małżonka: Jan Kowalski".
- **Switcher.** When the centre has at least one union, the union switcher is shown (also for a single union) and ends with a dashed „Dodaj małżonka" button with the same aria-label.
- **Dialog.** Both open the dialog „Dodaj małżonka — Jan Kowalski" with the description „Wybierz osobę z drzewa albo dodaj nową. Datę i miejsce ślubu uzupełnisz w podglądzie."
- **List.** The list shows existing people of the tree except these:
  - **Excluded (hidden, no hint):** the centre, the centre's current spouses, the centre's ancestors and descendants.
  - **Same sex (hidden, counted):** people whose known sex equals the centre's known sex. A hint independent of the search reads e.g. „3 osoby tej samej płci ukryto.". Unknown sex on either side hides nobody.
- **After picking or saving.**
  - Picking a person creates an undated marriage with them.
  - „Nowa osoba" shows the person form with the sex locked to the opposite of the centre's known sex; the sex is free when the centre's sex is unknown. Saving creates the person and the undated marriage atomically.
  - In both cases the dialog closes, the tree stays on the centre and selects the new union, and the toast says „Dodano małżonka".
  - A rule error (e.g. „Ta para ma już małżeństwo.") keeps the dialog open and creates nothing.
- **„Połącz jako małżeństwo".** When both parents of the centre, or both parents of the partner, are known people with no marriage between them, a button „Połącz jako małżeństwo" sits on that pair's connector. When the selected union is „bez ślubu", the same button sits under the couple.
- **Confirmation.** The button opens a confirmation: „Połączyć „Jan Kowalski" i „Anna Nowak" jako małżeństwo? Datę, miejsce i potwierdzenie ślubu uzupełnisz w podglądzie osoby." with „Anuluj" / „Połącz". Confirming creates an undated marriage. The tree keeps its centre and union and the button disappears. For the centre couple, the union label changes to „Anna Nowak · ślub ?". The toast says „Dodano małżeństwo".
- **Unchanged.** The preview's marriage dialogs, the S-25 parent slots and the S-26 child card behave as before.

### Key Discoveries:

- `ParentChildGraph` (`app/Support/ParentChildGraph.php:67-78`) already provides `descendantsOf` and `ancestorsOf`, and `$marriagePartnerIds` is already built in `BuildFamilySheet::handle` (`:54-66`). The spouse exclusions need no extra query.
- Marriages between the shown parents need one query over at most four parent ids (marriage events of the tree whose participants include them, with participants eager-loaded). This is constant, so `tests/Feature/TreeTest.php:490` (query count independent of tree size) keeps passing.
- `PersonCandidate.gender` and `sheet.focus.gender` are already on the client. The same-sex hiding and the locked sex are client-only, which matches the PRD statement that relation rules stay unchanged. The server keeps allowing any pair.
- `CreateMarriage` accepts an empty date array and opens its own transaction. Nesting it after `CreatePerson` inside an outer `DB::transaction` rolls the person back on failure, the same as `CreateChildForParents`.

## What We're NOT Doing

- A server-side sex or kinship rule for marriages (PRD v4: rules unchanged). Same-sex and direct-line hiding is UI-only, and a same-sex marriage cannot be recorded from the tree.
- A date, place or confirmation step when creating a marriage from the tree (declined: filled in the preview, which keeps editing them per FR-008).
- A spouse slot for anyone other than the centre. „Połącz jako małżeństwo" for grandparent pairs (declined: parents and the centre couple only).
- Replacing the static „Nieznany partner" card with an add card. With an unknown-partner union, the spouse is added from the switcher button.
- Inferring parentage: adding a spouse never links the centre's children to them.
- Prefilling the new spouse's surname (maiden names differ).
- Changing or removing the preview's „Dodaj małżeństwo" (S-29). Relation removal from the tree (S-28).
- New tokens, shadcn `ui/*` edits, screenshot-test infrastructure; duplicate merging, drag-and-drop, deep links (PRD v4 non-goals).

## Implementation Approach

Three vertical phases.

1. **Attach an existing spouse.**
   - The sheet gains `add_spouse.excluded_ids`.
   - A new `PersonSpouseController@attach` behind `POST persons/{person}/spouses/attach` reuses `CreateMarriage` with no date and redirects to the tree with the new union selected.
   - The new `AddSpouseDialog` wraps the S-26 shell and is rendered both in the empty partner slot and at the end of the union switcher.
2. **Create a new spouse.** A new `CreateSpouseForPerson` action behind `POST persons/{person}/spouses` creates the person and the marriage in one transaction. The dialog gains the „Nowa osoba" view with the locked opposite sex.
3. **„Połącz jako małżeństwo".** The sheet gains `unmarried_parents.{focus,partner}`. A new `MarryCoupleDialog` confirmation posts to the existing `marriages.store`, which already does exactly this and redirects `back()`. It is placed on the parent-pair connectors and under a „bez ślubu" centre couple.

The server stays the authority: the client only hides what the sheet excludes and what differs by sex.

## Critical Implementation Details

**User experience spec** — The spouse endpoints must not redirect `back()`. After a remarriage the referer still carries the old `?spouse=`, and the new union would stay hidden behind the switcher. They always redirect to `tree.show(person)` with `spouse`, including for the proband. `/tree/{probandId}` is the URL every sheet card and breadcrumb already links to (`resources/js/components/tree/sheet-person-card.tsx:66-69`, `resources/js/pages/tree/show.tsx:41`). „Połącz jako małżeństwo" keeps `back()`, because the shown union does not change.

## Phase 1: Attach an existing spouse from the tree

### Overview

Sheet exclusions for the spouse list, the attach endpoint with the redirect to the new union, the dialog with its list view, and its two triggers (empty partner slot, union switcher).

### Changes Required:

#### 1. Spouse exclusions on the sheet

**File**: `app/Actions/Trees/BuildFamilySheet.php`

**Intent**: The client knows whom the spouse list must never offer, derived from data the builder already has.

**Contract**: `handle()` returns a new key `add_spouse: {excluded_ids: list<int>}` (new phpstan type `AddSpouse`). `excluded_ids` = the focus ∪ `$marriagePartnerIds` ∪ `ancestorsOf(focus)` ∪ `descendantsOf(focus)`, unique, as a list. No new query.

#### 2. Attach request, controller, route

**File**: `app/Http/Requests/PersonSpouseAttachRequest.php` (new), `app/Http/Controllers/PersonSpouseController.php` (new), `routes/web.php`

**Intent**: The dialog posts the chosen person for a centre person from the user's own tree. The tree then shows the new union.

**Contract**:
- Route `POST persons/{person}/spouses/attach` → `PersonSpouseController@attach`, name `persons.spouses.attach`, next to the children routes.
- The request takes `spouse_id`: required integer, exists in the user's tree, `Rule::notIn([route person id])`. Message on `notIn`: „Osoba nie może poślubić samej siebie."; attribute „małżonek".
- The controller:
  - `Gate::authorize('view', $person)` (other user's centre → 404).
  - Loads the spouse via `$tree->people()->findOrFail`.
  - Calls `CreateMarriage::handle($tree, $person, $spouse, [])` and flashes the toast „Dodano małżonka".
  - Redirects to `tree.show` with `['person' => $person, 'spouse' => $spouse->id]`.
- Regenerate Wayfinder.

#### 3. Types

**File**: `resources/js/types/tree.ts`

**Intent**: Type the new sheet block.

**Contract**: New `AddSpouseTarget = { excluded_ids: number[] }`; `FamilySheet` gains `add_spouse: AddSpouseTarget`.

#### 4. Shell reports spouse errors

**File**: `resources/js/components/tree/pick-or-create-person-dialog.tsx`

**Intent**: A rejected pick (duplicate marriage, self, foreign id) shows its message in the dialog.

**Contract**: The pick `onError` chain also reads `errors.spouse_id` (after `other_parent_id`). Nothing else changes.

#### 5. Add-spouse dialog (list view)

**File**: `resources/js/components/tree/add-spouse-dialog.tsx` (new)

**Intent**: One dialog for picking the centre's spouse, reachable from two triggers.

**Contract**:
- `AddSpouseDialog({ focus: SheetPerson, target: AddSpouseTarget, candidates, genders, religions, variant: 'slot' | 'switcher' })` wraps `PickOrCreatePersonDialog`.
- Trigger:
  - `slot`: `UnknownPersonCard` with the label „Dodaj małżonka" and `addLabel` „Dodaj małżonka: {focus.display_name}".
  - `switcher`: an outline `Button` with a dashed border, a `Plus` icon and the text „Dodaj małżonka", sized to sit next to the union chips, with the same aria-label.
- Title „Dodaj małżonka — {focus.display_name}"; description „Wybierz osobę z drzewa albo dodaj nową. Datę i miejsce ślubu uzupełnisz w podglądzie."
- `select`:
  - `sameSex(c)` = `focus.gender !== null && c.gender === focus.gender`.
  - Visible = candidates not in `excluded_ids` and not `sameSex`.
  - The hidden count = candidates not in `excluded_ids` that are `sameSex`, independent of the search.
  - The hint = `pluralPhrase(n, '1 osobę tej samej płci ukryto.', 'n osoby tej samej płci ukryto.', 'n osób tej samej płci ukryto.')`.
- `pick` posts `PersonSpouseController.attach(focus.id)` with `{ spouse_id }`.
- No `form` in phase 1.

#### 6. Triggers on the sheet and the switcher

**File**: `resources/js/components/tree/family-sheet.tsx`, `resources/js/components/tree/union-switch.tsx`, `resources/js/pages/tree/show.tsx`

**Intent**: The empty partner slot adds a first spouse; the switcher adds another one.

**Contract**:
- `FamilySheet` renders `AddSpouseDialog variant="slot"` in the partner cell of the „Związek" row when `sheet.partner.kind === 'empty'`. Empty slots in the parent and grandparent rows stay blank.
- `UnionSwitch` gains an optional `action?: React.ReactNode`. It returns `null` only when there are no unions. With ≥1 union it renders the toggle group, a single chip included, followed by `action` in one wrapping flex row.
- `show.tsx` passes `<AddSpouseDialog variant="switcher" … />` as `action`, with `personCandidates`, `genders` and `religions`.

#### 7. Feature tests

**File**: `tests/Feature/MarriagesTest.php`, `tests/Feature/TreeTest.php`

**Intent**: Prove the exclusions, the attach endpoint's rules and redirect, and privacy.

**Contract**:
- Marriages (attach):
  - A centre F and an unrelated S → one undated marriage with both as spouses, redirect to `tree.show(F, spouse=S)`, toast „Dodano małżonka".
  - Remarriage: F already married to A, attach B → F has two marriages; the redirected sheet selects B's union.
  - An already married pair → „Ta para ma już małżeństwo." and still one marriage.
  - `spouse_id` = the route person → „Osoba nie może poślubić samej siebie.".
  - `spouse_id` from another user's tree → validation error, nothing created.
  - Other user's centre → 404; guest → login redirect.
- Tree:
  - `add_spouse.excluded_ids` contains F, F's spouse, F's parent and grandparent, F's child and grandchild.
  - It does not contain F's sibling, a co-parent without a marriage, or an unrelated person.
  - A person without any union → partner `empty`.

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- A tree centred on Jan with no union shows the „Dodaj małżonka" card in the partner slot. Picking Anna shows her in the partner slot and „Anna Nowak · ślub ?" in the switcher. Toast „Dodano małżonka". Jan's preview lists the undated marriage with Anna.
- Jan married to Anna → the switcher shows the single chip „Anna Nowak · ślub ?" and „Dodaj małżonka". Picking Maria selects Maria's union right away; switching back to Anna still works.
- The same flow started from `/tree` (the proband) lands on `/tree/{id}?spouse=…` with the new union selected.
- The list never shows Jan, his current spouses, his parents/grandparents or his children/grandchildren. Other men are hidden and counted in „… tej samej płci ukryto."; people of unknown sex are listed.
- Centre of unknown sex → nobody is hidden by sex and no hint is shown.
- S-25 parent slots and the S-26 „Dodaj dziecko" card behave exactly as before.
- Visual gate: the slot card and the switcher button at rest / hover / keyboard focus; the switcher with one union and with several (wrapping); the dialog with results, filter with no matches, the same-sex hint, loading skeleton and an error message. At 375px and 1280px, light and dark. Screenshots attached to the review.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: New spouse from the same dialog

### Overview

A dedicated endpoint creates a person and an undated marriage with the centre atomically; the dialog gains the „Nowa osoba" view with the opposite sex locked.

### Changes Required:

#### 1. Create-spouse action

**File**: `app/Actions/Relations/CreateSpouseForPerson.php` (new)

**Intent**: A new person and their marriage to the centre exist together or not at all.

**Contract**: `handle(Tree $tree, Person $person, array $data): Person`. Inside `DB::transaction`, run `CreatePerson::handle($tree, $data)`, then `CreateMarriage::handle($tree, $person, $spouse, [])`. Any `ValidationException` rolls back the person.

#### 2. Request, controller action, route

**File**: `app/Http/Controllers/PersonSpouseController.php`, `routes/web.php`

**Intent**: „Nowa osoba" posts the person form for a centre person from the user's own tree.

**Contract**:
- Route `POST persons/{person}/spouses` → `PersonSpouseController@store`, name `persons.spouses.store`.
- `store` type-hints the existing `PersonStoreRequest` (no new request class: there is no extra field); the sex is not enforced on the server.
- The controller:
  - `Gate::authorize('view', $person)`.
  - Calls the action with `$request->validated()` and flashes the toast „Dodano małżonka".
  - Redirects to the new union like `attach`.
- Regenerate Wayfinder.

#### 3. „Nowa osoba" view in the add-spouse dialog

**File**: `resources/js/components/tree/add-spouse-dialog.tsx`

**Intent**: One dialog: list first, then the form, and back.

**Contract**: Pass the shell's `form`:
- `PersonSpouseController.store.form(focus.id)`, with no hidden inputs.
- `lockedGender`: `'female'` for a male centre, `'male'` for a female centre, omitted when the centre's sex is unknown. No `defaults`.
- The submit button's `data-test` is `create-spouse-button`.

#### 4. Feature tests

**File**: `tests/Feature/MarriagesTest.php`

**Intent**: Prove atomic creation, the redirect and privacy.

**Contract**:
- Happy path: one new person, one undated marriage with the centre, redirect to `tree.show(F, spouse=new)`, toast „Dodano małżonka".
- An empty first name → no person, no marriage.
- Other user's centre → 404 and no person; guest → login.

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Jan (male, no union) → „Dodaj małżonka" → „Nowa osoba": sex locked to female, surname empty. Saving shows the new wife in the partner slot with „Dodano małżonka"; her preview lists the undated marriage.
- Jan already married → „Nowa osoba" from the switcher: the new union is selected after saving.
- Centre of unknown sex → the sex is freely selectable.
- „Wróć do listy" returns to the list with candidates still loaded; closing and reopening starts on the list.
- Submitting an empty form shows the name error inside the dialog and creates nothing.
- Visual gate: form view at 375px (full screen, scrolling, pinned header/footer) and 1280px, light and dark; screenshots attached to the review.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: „Połącz jako małżeństwo" for shown pairs

### Overview

The sheet marks which parent pairs are unmarried. A confirmation dialog marries a shown pair through the existing `marriages.store`. It is placed on the parent-pair connectors and under a „bez ślubu" centre couple.

### Changes Required:

#### 1. Unmarried parent pairs on the sheet

**File**: `app/Actions/Trees/BuildFamilySheet.php`

**Intent**: The client knows which shown parent pair can be married, without a per-pair request.

**Contract**:
- `handle()` returns a new key `unmarried_parents: {focus: bool, partner: bool}`.
- Each flag is `true` only when both parent slots of that block are `kind: 'person'` and no marriage event of the tree has both of them as participants. A block without a known partner is `false`.
- The marriages among the up to four shown parents come from one query.

#### 2. Types

**File**: `resources/js/types/tree.ts`

**Intent**: Type the new sheet block.

**Contract**: `FamilySheet` gains `unmarried_parents: { focus: boolean; partner: boolean }`.

#### 3. Confirmation dialog

**File**: `resources/js/components/tree/marry-couple-dialog.tsx` (new)

**Intent**: Marrying a shown pair is an explicit, confirmed action. This guards against the roadmap's risk of an unintended marriage.

**Contract**:
- `MarryCoupleDialog({ first: { id, display_name }, second: { id, display_name } })`.
- Trigger: an outline `Button` of size `sm` with the text „Połącz jako małżeństwo" and the aria-label „Połącz jako małżeństwo: {first} i {second}".
- A `Dialog` following `remove-marriage-dialog.tsx`:
  - Title „Połącz jako małżeństwo".
  - Description „Połączyć „{first}" i „{second}" jako małżeństwo? Datę, miejsce i potwierdzenie ślubu uzupełnisz w podglądzie osoby.".
  - `InputError` with `errors.spouse_id ?? errors.person_id`.
  - Footer „Anuluj" (closes) / „Połącz" (disabled while processing).
- Confirming posts `MarriageController.store()` with `{ person_id: first.id, spouse_id: second.id }` and `preserveScroll`. On success the dialog closes.

#### 4. Placement on the sheet

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: The action sits where the pair is visually joined.

**Contract**:
- `PairConnector` gains an optional `action?: React.ReactNode`, rendered centred on the bracket; the connector row grows to fit it. Only the decorative lines keep `aria-hidden`.
- The parents' connector of block `b` passes a `MarryCoupleDialog` for the two parent slots when `sheet.unmarried_parents[b]`.
- The gap between the „Związek" row and „Dzieci" renders a centred `MarryCoupleDialog` for focus and partner when the selected union (`sheet.unions` by `selected_union`) has `is_marriage === false` and `sheet.partner.kind === 'person'`. The vertical line to the children stays behind it. The gap's `aria-hidden` moves from the wrapper (`family-sheet.tsx:234`) onto the vertical line only, so the button stays visible to screen readers.

#### 5. Feature tests

**File**: `tests/Feature/TreeTest.php`, `tests/Feature/MarriagesTest.php`

**Intent**: Prove the flags and that the reused endpoint creates an undated marriage.

**Contract**:
- Tree:
  - Two known, unmarried parents of F → `unmarried_parents.focus` true.
  - A married pair → false. One parent unknown → false.
  - Partner block: true for the partner's unmarried parents; false without a partner.
  - The query-count test still passes.
- Marriages: `marriages.store` with only `person_id` and `spouse_id` creates one marriage with no date and no place.

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Jan's parents Piotr and Zofia without a marriage → „Połącz jako małżeństwo" on their connector. „Anuluj" creates nothing. „Połącz" closes the dialog, the button disappears, centre and union unchanged, toast „Dodano małżeństwo". Recentring on Piotr shows „Zofia · ślub ?".
- The partner's unmarried parents get the same button on the partner block; married or incomplete parent pairs never show it.
- Selected union „Anna Nowak · bez ślubu" → the button under the couple. After „Połącz" the chip reads „Anna Nowak · ślub ?" and the button is gone.
- Visual gate: the parents' connector with and without the button, the centre-couple button over the line to the children, the confirmation dialog, all at rest / hover / keyboard focus. At 375px (horizontal scroll of the sheet) and 1280px, light and dark. Screenshots attached to the review.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None: sheet metadata and rules are exercised through feature tests, matching the existing suite.

### Integration Tests:

- `MarriagesTest` (spouse attach):
  - Happy path; remarriage selects the new union.
  - Duplicate pair; self; foreign id; 404; guest.
- `MarriagesTest` (spouse store): atomic happy path; empty name → nothing; 404; guest.
- `MarriagesTest` (store): undated marriage without a `marriage` input.
- `TreeTest`: `add_spouse.excluded_ids`; `unmarried_parents` for both blocks; the query count stays constant.

### Manual Testing Steps:

1. On a real tree, give an unmarried person a spouse from the list, then a second spouse as „Nowa osoba" from the switcher, without leaving the tree.
2. Marry the shown parents with „Połącz jako małżeństwo", then fill the date in the preview and check that the chip shows the year.
3. Marry a „bez ślubu" centre couple from under the couple.
4. Re-check the S-25 parent slot and the S-26 child card end to end.
5. Visual gate screenshots as listed in each phase.

## Performance Considerations

`add_spouse.excluded_ids` uses the graph and the marriage partners the sheet already loads. `unmarried_parents` adds one constant query over at most four ids. `personCandidates` is unchanged: one query, evaluated only on a dialog's partial reload.

## Migration Notes

None: no schema changes and no data changes.

## References

- Roadmap slice: `context/foundation/roadmap.md`, S-27 `tree-spouse-existing-or-new`
- PRD: `context/foundation/prd.md` (v4), FR-006, FR-008, FR-011; `context/foundation/prd-v3.md`, FR-003
- Prior plans (shell, candidate prop, atomic create-and-link): `context/archive/2026-09-28-tree-child-existing-or-new/plan.md`, `context/archive/2026-09-28-tree-parent-slot-existing-or-new/plan.md`
- Marriage creation: `app/Actions/Relations/CreateMarriage.php:24-54`, `app/Http/Controllers/MarriageController.php:20-32`, `app/Http/Requests/MarriageStoreRequest.php:20-67`
- Sheet builder: `app/Actions/Trees/BuildFamilySheet.php:47-156, 305-356`
- Shell: `resources/js/components/tree/pick-or-create-person-dialog.tsx:118-284`; child dialog to mirror: `resources/js/components/tree/add-child-dialog.tsx`
- Sheet and switcher: `resources/js/components/tree/family-sheet.tsx:70-89, 214-238`; `resources/js/components/tree/union-switch.tsx:12-59`
- Confirmation pattern: `resources/js/components/persons/remove-marriage-dialog.tsx:45-86`
- Atomic pattern: `app/Actions/Relations/CreateChildForParents.php:24-33`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Attach an existing spouse from the tree

#### Automated

- [x] 1.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php` — 84baf5c
- [x] 1.2 Larastan passes: `vendor/bin/sail composer types:check` — 84baf5c
- [x] 1.3 Format and lint pass: `vendor/bin/sail npm run check` — 84baf5c
- [x] 1.4 Type check passes: `vendor/bin/sail npm run types:check` — 84baf5c
- [x] 1.5 Production build succeeds: `vendor/bin/sail npm run build` — 84baf5c
- [x] 1.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent` — 84baf5c

#### Manual

- [x] 1.7 A tree centred on Jan with no union shows the „Dodaj małżonka" card in the partner slot. Picking Anna shows her in the partner slot and „Anna Nowak · ślub ?" in the switcher. Toast „Dodano małżonka". Jan's preview lists the undated marriage with Anna. — 84baf5c
- [x] 1.8 Jan married to Anna → the switcher shows the single chip „Anna Nowak · ślub ?" and „Dodaj małżonka". Picking Maria selects Maria's union right away; switching back to Anna still works. — 84baf5c
- [x] 1.9 The same flow started from `/tree` (the proband) lands on `/tree/{id}?spouse=…` with the new union selected. — 84baf5c
- [x] 1.10 The list never shows Jan, his current spouses, his parents/grandparents or his children/grandchildren. Other men are hidden and counted in „… tej samej płci ukryto."; people of unknown sex are listed. — 84baf5c
- [x] 1.11 Centre of unknown sex → nobody is hidden by sex and no hint is shown. — 84baf5c
- [x] 1.12 S-25 parent slots and the S-26 „Dodaj dziecko" card behave exactly as before. — 84baf5c
- [x] 1.13 Visual gate: the slot card and the switcher button at rest / hover / keyboard focus; the switcher with one union and with several (wrapping); the dialog with results, filter with no matches, the same-sex hint, loading skeleton and an error message. At 375px and 1280px, light and dark. Screenshots attached to the review. — 84baf5c

### Phase 2: New spouse from the same dialog

#### Automated

- [x] 2.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php` — eecd845
- [x] 2.2 Larastan passes: `vendor/bin/sail composer types:check` — eecd845
- [x] 2.3 Format and lint pass: `vendor/bin/sail npm run check` — eecd845
- [x] 2.4 Type check passes: `vendor/bin/sail npm run types:check` — eecd845
- [x] 2.5 Production build succeeds: `vendor/bin/sail npm run build` — eecd845
- [x] 2.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent` — eecd845

#### Manual

- [x] 2.7 Jan (male, no union) → „Dodaj małżonka" → „Nowa osoba": sex locked to female, surname empty. Saving shows the new wife in the partner slot with „Dodano małżonka"; her preview lists the undated marriage. — eecd845
- [x] 2.8 Jan already married → „Nowa osoba" from the switcher: the new union is selected after saving. — eecd845
- [x] 2.9 Centre of unknown sex → the sex is freely selectable. — eecd845
- [x] 2.10 „Wróć do listy" returns to the list with candidates still loaded; closing and reopening starts on the list. — eecd845
- [x] 2.11 Submitting an empty form shows the name error inside the dialog and creates nothing. — eecd845
- [x] 2.12 Visual gate: form view at 375px (full screen, scrolling, pinned header/footer) and 1280px, light and dark; screenshots attached to the review. — eecd845

### Phase 3: „Połącz jako małżeństwo" for shown pairs

#### Automated

- [x] 3.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php` — f2dd6df
- [x] 3.2 Larastan passes: `vendor/bin/sail composer types:check` — f2dd6df
- [x] 3.3 Format and lint pass: `vendor/bin/sail npm run check` — f2dd6df
- [x] 3.4 Type check passes: `vendor/bin/sail npm run types:check` — f2dd6df
- [x] 3.5 Production build succeeds: `vendor/bin/sail npm run build` — f2dd6df
- [x] 3.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent` — f2dd6df

#### Manual

- [x] 3.7 Jan's parents Piotr and Zofia without a marriage → „Połącz jako małżeństwo" on their connector. „Anuluj" creates nothing. „Połącz" closes the dialog, the button disappears, centre and union unchanged, toast „Dodano małżeństwo". Recentring on Piotr shows „Zofia · ślub ?". — f2dd6df
- [x] 3.8 The partner's unmarried parents get the same button on the partner block; married or incomplete parent pairs never show it. — f2dd6df
- [x] 3.9 Selected union „Anna Nowak · bez ślubu" → the button under the couple. After „Połącz" the chip reads „Anna Nowak · ślub ?" and the button is gone. — f2dd6df
- [x] 3.10 Visual gate: the parents' connector with and without the button, the centre-couple button over the line to the children, the confirmation dialog, all at rest / hover / keyboard focus. At 375px (horizontal scroll of the sheet) and 1280px, light and dark. Screenshots attached to the review. — f2dd6df
