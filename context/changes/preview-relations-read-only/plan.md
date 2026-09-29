# Preview without relation adding (S-29) Implementation Plan

## Overview

The person preview modal stops offering relation **add** actions („Dodaj rodzica”, „Dodaj dziecko”, „Dodaj małżeństwo”). Adding a parent, child or spouse happens only on the tree, which already covers every add path (S-25 – S-27). **Removing** a parent–child relation or a marriage stays in the preview. So do editing a marriage's date and place and linking its scans. The header button becomes „Pokaż na drzewie”, and empty relation sections say that relatives are added on the tree.

This change also records a product decision made during planning: **S-28 `tree-relation-removal` is dropped**. The tree only views and adds; removal lives only in the person preview. The roadmap and PRD v4 are amended to match, and the stub change folder is deleted.

## Current State Analysis

- The `/persons/{id}` page is **already gone**. S-24 removed the route, `show()` and `pages/persons/show.tsx` in `73d4fa4`. `GET /persons/{id}` → 405 is asserted by `tests/Feature/PersonsTest.php:231`. The archived S-24 plan (`context/archive/2026-09-28-person-preview-in-modal/plan.md:237`) says S-29 should narrow to relations plus „Pokaż na drzewie”.
- The preview is a global modal (`resources/js/layouts/app-layout.tsx:14-19`) fed by the shared `personPreview` prop and driven by `?person=` (`app/Http/Middleware/HandleInertiaRequests.php:51-55`).
- Relation add actions in the preview:
  - `resources/js/components/persons/relatives-card.tsx:24-35`: „Dodaj rodzica”/„Dodaj dziecko” → `RelativePickerDialog` → `ParentChildRelationController.store`.
  - `resources/js/components/persons/marriages-section.tsx:25-34`: „Dodaj małżeństwo” → `MarriageFormDialog mode="create"` → `MarriageController.store`.
- Relation remove actions in the preview (they **stay**):
  - `relatives-card.tsx:71-89` → `RemoveRelationDialog`.
  - `marriages-section.tsx:99-113` → `RemoveMarriageDialog`.
- Marriage details (they **stay**): „Edytuj” → `MarriageFormDialog mode="edit"` → `MarriageController.update` (`marriages-section.tsx:85-98`). Scan linking and unlinking go through `EventCard` (`resources/js/components/persons/event-card.tsx`).
- The tree already adds every relation type:
  - parent: `tree/parent-slot-dialog.tsx`
  - child: `tree/add-child-dialog.tsx`
  - spouse: `tree/add-spouse-dialog.tsx`, also from the union dropdown in `tree/union-node.tsx`
  - „Połącz jako małżeństwo”: `tree/marry-couple-dialog.tsx`

  The tree has no removal UI, and after this change it never will.
- The candidate list for the preview pickers is the optional shared prop `relationCandidates` (`HandleInertiaRequests.php:59-63`), built by `app/Actions/Relations/ListRelationCandidates.php`. The tree uses a separate source: `personCandidates` via `app/Actions/Trees/ListPersonCandidates.php` (`app/Http/Controllers/TreeController.php:28,46`) and the generic `useOptionalList` (`tree/pick-or-create-person-dialog.tsx:83`).

## Desired End State

- Opening any person in the preview shows the Rodzice, Dzieci and Małżeństwa sections with no add buttons.
- Each parent and child keeps „Usuń relację”, and each marriage keeps „Edytuj” and „Usuń małżeństwo”, all behaving as today.
- An empty section reads e.g. „Brak znanych rodziców. Dodasz ich na drzewie.”
- The header button reads „Pokaż na drzewie” and lands on `tree/{person}`, centred on that person, with the modal closed.
- No code path computes or types `relationCandidates` any more.
- The roadmap shows S-28 as dropped and S-29 with its narrowed outcome and no dependency on S-28. PRD v4 FR-007/FR-008 describe removal in the preview and adding on the tree.

Verify with `npm run check`, `types:check` and `build`, the Pest files touched, and a manual pass through the preview on the demo seeder.

### Key Discoveries:

- `ParentChildRelationController::store` and `MarriageController::store` are still called by the tree (`tree/parent-slot-dialog.tsx:85`, `tree/marry-couple-dialog.tsx:37`). Both endpoints and their tests stay. Only the preview's callers go.
- `ParentChildRelationController::destroy`, `MarriageController::update` and `MarriageController::destroy` stay: the preview still calls them.
- `useOptionalList` lives in `resources/js/hooks/use-relation-candidates.ts` next to `useRelationCandidates` and is imported by the tree. Only the `useRelationCandidates` wrapper and its return type are removed. The file stays because renaming it is out of scope.
- `person-candidate-list.tsx` is shared with the tree, so it stays.
- `RelationCandidate` (`resources/js/types/person.ts:64`) is only referenced by the preview add path and `global.d.ts:19`.
- The only blocked-reason logic that goes away is `ListRelationCandidates`. It mirrors rules still enforced server-side by `CreateParentChildRelation` and the marriage requests, so no validation is lost.

## What We're NOT Doing

- Adding relation removal to the tree. S-28 is dropped, not deferred.
- Changing the marriage-removal confirmation. It keeps today's text: gone for both spouses, N scan links unpinned. The date/place warning that S-28 wanted was declined.
- Changing `/persons/{id}` behaviour. It was removed in S-24 and keeps returning 405.
- Renaming `use-relation-candidates.ts` or moving `useOptionalList`.
- Any visual restyle of the relation cards beyond removing buttons and the empty-state copy. There's no screenshot gate: this change only removes things, and the repo has no screenshot test.
- GitHub issues for S-28/S-29. The roadmap handoff table is updated; issue housekeeping stays with the user.

## Implementation Approach

Remove the frontend add path first (Phase 1). Nothing on the backend depends on the UI, so the app stays consistent after each phase. Then delete the backend prop, action and tests that only served that path (Phase 2). Finally, amend the foundation documents so the roadmap and PRD match the product decision (Phase 3).

## Phase 1: Preview without add actions

### Overview

Take the add triggers and the candidate plumbing out of the preview, keep every remove and marriage-detail action, relabel the tree link and add the empty-state hint.

### Changes Required:

#### 1. Parents and children card

**File**: `resources/js/components/persons/relatives-card.tsx`

**Intent**: Drop the „Dodaj rodzica”/„Dodaj dziecko” trigger and the `candidates` prop. Keep the list, the `PersonPreviewLink` per relative and `RemoveRelationDialog`. Add the tree hint to the empty-state sentence.

**Contract**: Props become `{ kind, person, relatives }`. Empty copy: „Brak znanych rodziców. Dodasz ich na drzewie.” / „Brak znanych dzieci. Dodasz je na drzewie.”

#### 2. Marriages section

**File**: `resources/js/components/persons/marriages-section.tsx`

**Intent**: Drop the „Dodaj małżeństwo” trigger and the `candidates` prop. Keep each marriage's `EventCard` with the spouse link, „Edytuj” and „Usuń małżeństwo”. Add the tree hint to the empty state.

**Contract**: Props become `{ person, availableScans }`. Empty copy: „Brak znanych małżeństw. Dodasz je na drzewie.”

#### 3. Marriage form dialog: edit only

**File**: `resources/js/components/persons/marriage-form-dialog.tsx`

**Intent**: Remove the `mode: 'create'` branch: the spouse picker, filter state, `selectedSpouse`, `person_id`/`spouse_id` hidden inputs, `useRelationCandidates`, and the `PersonCandidateList` and `InputError` imports (`InputError` only showed `errors.spouse_id`). What remains is an edit dialog for the marriage date and place.

**Contract**: Props become a single shape `{ marriage, trigger }`. `person` goes too, because only the create branch's hidden `person_id` input read it. The form always submits `MarriageController.update.form(marriage.id)`. The `mode` prop goes; the call site in `marriages-section.tsx` drops `mode=` and `person=`. Keep `PartialDateFields prefix="marriage"`.

#### 4. Preview and dialog prop threading

**Files**: `resources/js/components/persons/person-preview.tsx`, `resources/js/components/persons/person-preview-dialog.tsx`

**Intent**: Stop reading and passing `relationCandidates`. Relabel the header link to „Pokaż na drzewie”, keeping `treeShow(person.id)` as a plain `Link` so leaving drops `?person=` and closes the modal.

**Contract**: `PersonPreview` props become `{ preview, availableScans? }`. `person-preview-dialog.tsx` reads only `personPreview` and `availableScans` from `usePage().props`.

#### 5. Dead frontend code

**Files**: `resources/js/components/persons/relative-picker-dialog.tsx` (delete), `resources/js/hooks/use-relation-candidates.ts`, `resources/js/types/person.ts`, `resources/js/types/global.d.ts`

**Intent**: Delete the picker dialog. Remove the `useRelationCandidates` wrapper and `UseRelationCandidatesReturn`, but keep `useOptionalList` and `UseOptionalListReturn`. Remove the `RelationCandidate` type and the `relationCandidates?` page-prop entry. In `resources/js/components/persons/person-candidate-list.tsx:36`, the docblock says the list serves „the relative and marriage pickers”; change it to the tree's person pickers.

**Contract**: No remaining import of `RelativePickerDialog`, `useRelationCandidates` or `RelationCandidate` anywhere in `resources/js`. Lint with `typeAware` and `denyWarnings` must pass, with no unused exports or imports.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Build passes: `vendor/bin/sail npm run build`
- No references remain: `grep -rn "relationCandidates\|RelationCandidate\|RelativePickerDialog\|useRelationCandidates" resources/js --exclude-dir=actions --exclude-dir=routes --exclude-dir=wayfinder` returns nothing

#### Manual Verification:

- On the demo tree (`TreeViewDemoSeeder`), opening Jan's preview shows parents, children and 2 marriages (Anna, Ewa) with no „Dodaj …” buttons. Each relative still has „Usuń relację”, and each marriage has „Edytuj” and „Usuń małżeństwo”.
- „Edytuj” on a marriage changes its date and place. „Usuń relację” and „Usuń małżeństwo” still confirm and remove.
- Empty-state hints: Kacper (parents, no children, no marriages) shows the Dzieci and Małżeństwa hints; Marta (no parents) shows the Rodzice hint. No seeded person has all three sections empty.
- „Pokaż na drzewie” from a preview opened on the person list lands on the tree centred on that person, with the modal closed.
- The preview opened from the tree, the list and the scan preview still navigates between relatives and back with „Wstecz”.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Backend cleanup of relation candidates

### Overview

Remove the shared prop and action that only fed the deleted pickers, together with the tests that covered them.

### Changes Required:

#### 1. Shared prop

**File**: `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Remove the `relationCandidates` optional prop and the `ListRelationCandidates` import. Keep `personPreview`, `availableScans` and `previewPerson()`. `availableScans` never calls `previewPerson()`, so `personPreview` becomes its only caller. Keep the harmless memoisation, but drop the docblock sentence about several shared props repeating the lookup.

**Contract**: `share()` returns `name`, `auth`, `sidebarOpen`, `personPreview` and `availableScans`, plus the parent's props.

#### 2. Action

**File**: `app/Actions/Relations/ListRelationCandidates.php` (delete)

**Intent**: Remove the now-unused action. `ParentChildGraph` and the relation rules it mirrored remain in `CreateParentChildRelation` and the request classes.

**Contract**: No reference to `ListRelationCandidates` in `app/`, `routes/` or `tests/`.

#### 3. Tests

**Files**: `tests/Feature/ParentChildRelationsTest.php`, `tests/Feature/MarriagesTest.php`, `tests/Feature/PersonsTest.php`

**Intent**: The user approved deleting the tests that only cover `relationCandidates`. Keep the `availableScans` coverage in the shared test.

**Contract**:
- `ParentChildRelationsTest.php`: delete these tests:
  - „person show does not include relationCandidates on a normal visit”
  - „a partial reload of relationCandidates excludes the current person and other trees, and gives the expected reason per direction”
  - „the preview candidates block a second parent of the same sex in both directions”
- `MarriagesTest.php`: delete „relationCandidates marks the existing spouse and leaves other people unblocked”.
- `PersonsTest.php`: in „relation candidates and available scans load through a partial reload of a host page with a person parameter”:
  - Rename it to cover only available scans.
  - Request only `availableScans` in `X-Inertia-Partial-Data`.
  - Drop the `relationCandidates` assertions and the `$other` person if it becomes unused.
- No other test is removed.

### Success Criteria:

#### Automated Verification:

- Touched tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonsTest.php`
- PHP style: `vendor/bin/sail bin pint --dirty --format agent`
- Static analysis passes: `vendor/bin/sail bin phpstan analyse`
- No references remain: `grep -rn "relationCandidates\|ListRelationCandidates" app routes tests` returns nothing

#### Manual Verification:

- Opening a preview and linking a scan to a fact (which loads `availableScans` on demand) still works.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Record the S-28 drop in the foundation docs

### Overview

Make the roadmap and PRD v4 say what the code now does: the tree adds relations, the preview removes them, and S-28 no longer exists. Follow the pattern the roadmap already uses for superseded slices („Zastąpione przez prd-v4: …”).

### Changes Required:

#### 1. Roadmap

**File**: `context/foundation/roadmap.md`

**Intent**: Drop S-28 and narrow S-29, keeping the rest of the document intact. Write the text in Polish and bump `updated:` to the implementation date.

**Contract**: Edits by location:
- **Line 30:** the prd-v4 extension note says the tree adds relations and the preview removes them.
- **After lines 29/31:** add „Porzucone (2026-09-29, decyzja użytkownika): S-28 `tree-relation-removal` — usuwanie relacji zostaje w podglądzie osoby.”, in the style of the „Zastąpione przez …” lines. This follows the precedent of S-10/S-11/S-21 – S-23, which have no row or block left. `dropped` is not a roadmap status, and M-1 closes only when every S-NN is `done`.
- **Line 44:** the vision recap says the tree is where relations are added and the preview is where they are removed.
- **At-a-glance rows 85-86:**
  - The S-28 row is removed.
  - S-29: the outcome says „widzieć relacje w podglądzie bez dodawania (usuwanie zostaje) z przyciskiem „Pokaż na drzewie””, the prerequisites drop S-28, and the PRD refs lose `v4:FR-009`, which S-24 delivered.
- **Stream D, row 97:** drop S-28 from the chain and the guardrail wording.
- **`### S-28` block (410-420):** removed. The reason lives in the „Porzucone” note.
- **`### S-29` block (422-432):**
  - Title and outcome match this plan.
  - `Prerequisites: S-25, S-26, S-27`.
  - The risk no longer refers to S-28 or to the `/persons/{id}` page.
- **Backlog handoff rows 463-464:**
  - The S-28 row is removed.
  - The S-29 title matches.
- **Line 477 (replanning history):** keep it as history, and mark S-28 in it as „porzucone 2026-09-29”.
- **Line 501 (Parked):** the reference to S-25 – S-28 becomes S-25 – S-27.
- **„Parallel with:” lines 249, 262, 275, 289, 327, 340, 353, 365, 377, 390, 403:** drop S-28 from each list.

Leave `Status:` of S-29 to the lifecycle: `/10x-plan` sets `planning` now and `/10x-implement` moves it on.

#### 2. PRD v4

**File**: `context/foundation/prd.md`

**Intent**: Amend the requirements to the decision without rewriting the document's history. Mark the changes as a dated amendment (2026-09-29, after S-27), the same way the file annotates earlier changes.

**Contract**: Edits by location:
- **FR-007 (line 106):** removal of a parent–child relation or a marriage happens in the person preview, after confirmation. The preview's confirmation stays as it is today; the lost-data list is dropped.
- **FR-008 (line 93):** the preview shows relations without add actions and keeps removal, marriage date, place and confirmation. The header button „Pokaż na drzewie” centres the tree on the person.
- **Line 40 (Zmiana):** the tree takes over adding relations and the preview keeps removing them.
- **Line 54 (Etap 2):** say the same for stage 2.
- **Line 57 (Primary):** the flow removes a relation in the preview, not on the tree.
- **Line 82 (acceptance criterion):** reword to „W modalu podglądu nie ma akcji dodawania rodzica”.
- **Line 99 (tree scope):** drop „usuwanie relacji osoby w centrum”.
- **Line 128 (Etapowanie):** the guardrail says adding and removing each have exactly one home.
- **Line 75 (acceptance scenario):** Józef shows in „Rodzice” without an add action (not „tylko do odczytu”, since removal stays).
- **Line 107 (Socrates note under FR-007):** mark the lost-data resolution as withdrawn by the 2026-09-29 amendment.
- **Lines 120 and 130 (v3:FR-001 – FR-003, S-21 – S-23):** drop „usuwanie relacji” from what the tree absorbs.
- **Line 153 (Open question 1):** drop „usuwanie relacji” from what the tree absorbs, or annotate it with the amendment.

#### 3. Change folders

**Files**: `context/changes/tree-relation-removal/` (delete), `context/changes/preview-relations-read-only/change.md`

**Intent**: Remove the untracked stub for the dropped slice. It holds only `change.md` with status `new`. Keep this change's id and retitle it to what it now delivers.

**Contract**:
- `tree-relation-removal/` no longer exists.
- This change's `change.md` gets `title: Preview without relation adding; S-28 dropped` and a bumped `updated:`. The id and `status` are left to the lifecycle skills.
- The local branch `feat/tree-relation-removal` has no commits of its own and can be deleted or renamed by the user. It's not touched here.

### Success Criteria:

#### Automated Verification:

- The stub is gone: `test ! -d context/changes/tree-relation-removal`
- No stale S-28 prerequisite: `grep -n "S-25, S-26, S-27, S-28" context/foundation/roadmap.md` returns nothing
- S-28 is removed and noted as dropped: `! grep -q "^### S-28" context/foundation/roadmap.md && grep -q "Porzucone (2026-09-29" context/foundation/roadmap.md`
- No other S-28 mention in the roadmap: `grep -n "S-28" context/foundation/roadmap.md` lists only the „Porzucone” note and line 477
- No stale tree-removal text in the PRD: `grep -n "tylko do odczytu\|usuwa.*z drzewa\|usuwanie relacji" context/foundation/prd.md` lists only lines marked as the 2026-09-29 amendment or as history („Było: …”)

#### Manual Verification:

- Reading roadmap S-28/S-29 and PRD FR-007/FR-008 tells a newcomer the same thing this plan does: add on the tree, remove in the preview, no removal on the tree.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before closing the change.

---

## Testing Strategy

### Unit Tests:

- None added. This change removes behaviour. The deleted tests covered only the removed prop.

### Integration Tests:

- The existing Pest feature tests for `ParentChildRelationController` (store/destroy), `MarriageController` (store/update/destroy), preview payloads and `availableScans` remain and must stay green. They prove the kept actions and the tree's endpoints still work.

### Manual Testing Steps:

1. Seed the demo tree and open Jan's preview from the tree. Check: no add buttons, remove and edit present.
2. Remove a parent–child relation and a marriage from the preview. Confirm the dialogs and the tree reflect the change.
3. Open Kacper's preview (Dzieci and Małżeństwa hints) and Marta's (Rodzice hint).
4. From the person list, open a preview and click „Pokaż na drzewie”. The tree is centred on that person and the modal is closed.
5. Add a parent, a child and a spouse from the tree to confirm the only add path works.

## Performance Considerations

Removing `relationCandidates` drops the `ListRelationCandidates` graph computation on every picker open. There are no other effects.

## Migration Notes

None. No schema or data changes.

## References

- Roadmap: `context/foundation/roadmap.md` (S-28 lines 410-420, S-29 lines 422-432)
- PRD v4: `context/foundation/prd.md` (FR-007 line 106, FR-008 line 93)
- S-24 archive: `context/archive/2026-09-28-person-preview-in-modal/plan.md:237`
- Tree add paths: `context/archive/2026-09-28-tree-parent-slot-existing-or-new/`, `…-tree-child-existing-or-new/`, `…-tree-spouse-existing-or-new/`
- Demo data: `database/seeders/TreeViewDemoSeeder.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Preview without add actions

#### Automated

- [x] 1.1 Format and lint pass: `vendor/bin/sail npm run check`
- [x] 1.2 Types pass: `vendor/bin/sail npm run types:check`
- [x] 1.3 Build passes: `vendor/bin/sail npm run build`
- [x] 1.4 No references remain to relationCandidates, RelationCandidate, RelativePickerDialog or useRelationCandidates in resources/js

#### Manual

- [x] 1.5 Jan's preview shows relations with no add buttons, removal and marriage edit kept
- [x] 1.6 Marriage edit, relation removal and marriage removal still work from the preview
- [x] 1.7 Kacper's and Marta's previews show the empty-state hints
- [x] 1.8 „Pokaż na drzewie” from the list lands on the centred tree with the modal closed
- [x] 1.9 Preview from tree, list and scan still navigates relatives and back

### Phase 2: Backend cleanup of relation candidates

#### Automated

- [ ] 2.1 Touched tests pass: ParentChildRelationsTest, MarriagesTest, PersonsTest
- [ ] 2.2 PHP style: `vendor/bin/sail bin pint --dirty --format agent`
- [ ] 2.3 Static analysis passes: `vendor/bin/sail bin phpstan analyse`
- [ ] 2.4 No references remain to relationCandidates or ListRelationCandidates in app, routes, tests

#### Manual

- [ ] 2.5 Linking a scan to a fact from the preview still works

### Phase 3: Record the S-28 drop in the foundation docs

#### Automated

- [ ] 3.1 The tree-relation-removal stub folder is gone
- [ ] 3.2 No stale S-28 prerequisite in the roadmap
- [ ] 3.3 S-28 is removed and noted as dropped
- [ ] 3.4 No other S-28 mention in the roadmap
- [ ] 3.5 No stale tree-removal text in the PRD

#### Manual

- [ ] 3.6 Roadmap S-28/S-29 and PRD FR-007/FR-008 match this plan
