# Person Preview in Modal Implementation Plan

## Overview

Roadmap slice S-24 (v4:FR-001, FR-002, FR-003, FR-010, FR-012, FR-013, v4:US-01): the person preview stops being a separate page and becomes a modal opened over the tree, the person list and the scan preview. Inside the modal the user moves to relatives and returns with "Wstecz"; closing leaves the page underneath exactly where it was. Adding a person from the list lands on the tree centred on the new person. The `/persons/{id}` page, route and controller method are removed in this slice (brought forward from S-29 by decision); relation editing stays inside the modal until S-29 makes it read-only.

## Current State Analysis

- The preview is `PersonController@show` (`app/Http/Controllers/PersonController.php:86-103`) rendering `resources/js/pages/persons/show.tsx`: props `person` (`PersonResource`), `personForm`, `genders`, `religions`, `isRootPerson`, and two `Inertia::optional` props — `availableScans` and `relationCandidates`.
- Seven entry points link to it, all via `PersonController.show(id)`: list rows `resources/js/pages/persons/index.tsx:97-99`, tree header "Podgląd" `resources/js/pages/tree/show.tsx:90-94`, the eye button on every tree card `resources/js/components/tree/sheet-person-card.tsx:79-92`, parent/child names `resources/js/components/persons/relatives-card.tsx:52-53`, spouse name `resources/js/components/persons/marriages-section.tsx:57-60`, the "Potwierdza" list `resources/js/pages/scans/show.tsx:82-85`, and the page's own breadcrumb `persons/show.tsx:46-49`.
- Every mutation reachable from the preview already redirects with `back()` — relations (`ParentChildRelationController.php:28,42`), marriages (`MarriageController.php:31,43,58`), event–scan links (`EventScanController.php:28,47`), proband (`TreeRootPersonController.php:25`), store (`PersonController.php:80`). Exceptions: `update` → `to_route('persons.show')` (`:114`), `destroy` → `to_route('persons.index')` (`:128`).
- The optional props are loaded by `router.reload({ only: [...] })` of the **current URL**: `resources/js/hooks/use-relation-candidates.ts:33` and `resources/js/components/scans/scan-picker-dialog.tsx:54-55`. Both cache the last list because non-partial visits drop optional props.
- `AppLayout` is the persistent default layout for every non-auth page (`resources/js/app.tsx:13-24`, `resources/js/layouts/app-layout.tsx`), so a provider mounted there survives Inertia visits.
- `resources/js/components/form-dialog.tsx` (S-20) gives full-screen-below-`sm`, centred-above, pinned header, scrolling body, overlay click blocked.
- Inertia 3.7.1 replaces (does not push) the history entry when a visit resolves to the same URL (`node_modules/@inertiajs/core/dist/index.js:1100`). inertia-laravel 3.3 resolves shared and page props in one resolver (`vendor/inertiajs/inertia-laravel/src/Response.php:190`), so `Inertia::optional` works in `share()`.
- Shared page props are typed in `resources/js/types/global.d.ts:11-15`.
- ~30 feature tests render `persons/show` directly (PersonsTest, MarriagesTest, ParentChildRelationsTest, TreeTest, EventScansTest); no browser/screenshot tests exist.

## Desired End State

- On the tree, the eye button on any card and the header "Podgląd" open the preview modal over the tree; clicking a card still re-centres the tree. On the person list, clicking a row opens the modal over the list (search text kept). On a scan preview, each "Potwierdza" entry opens the modal over the scan page.
- The modal shows everything today's page shows — basic data, parents, children, birth, marriages, death with linked scans — and the same header actions: "Probant" badge or "Ustaw jako probanta", "Pokaż w drzewie", "Edytuj" (person form modal on top), "Usuń". Relation and marriage add/remove, scan link/unlink keep working inside the modal, and the page underneath refreshes after each action (e.g. a new parent appears on the tree behind the modal).
- Clicking a parent, child or spouse inside the modal swaps its content to that person; a "Wstecz" button (visible only after such a swap) returns to the previously viewed person, repeatedly.
- Closing (X, Esc) leaves the underlying page in exactly the same state (same centre, same union, same list filter, same scroll). The browser back button behaves as today (leaves the page); in-modal history only via "Wstecz".
- Opening a scan from a fact navigates to the full scan page; browser back returns to the page with the modal reopened on that person.
- Deleting a person from the modal shows today's confirmation; afterwards the modal closes and the underlying page stays — except when the deleted person is the tree's centre, where the tree returns to the proband (deleting the proband → today's empty tree state).
- Below 640px the modal fills the screen with the title and "Wstecz"/X pinned at the top and the content scrolling.
- Adding a person from the list lands on `/tree/{new id}`.
- `GET /persons/{id}` no longer renders a page (405, since PUT/DELETE remain on that path); `persons/show.tsx` and `PersonController@show` no longer exist.

### Key Discoveries:

- Mechanism: the modal is driven by a `person` query parameter on the **current page URL**; a shared prop `personPreview` is non-null only when it is present. Since all preview mutations redirect `back()` to that same URL, both the modal and the page underneath refresh with zero per-form wiring, and the history entry is replaced, not pushed.
- `router.reload` in `use-relation-candidates.ts:33` / `scan-picker-dialog.tsx:54` reloads the current URL (which carries `person=`), so moving `availableScans` / `relationCandidates` into shared optional props keeps both hooks unchanged.
- Delete-impact text already lives in `resources/js/components/persons/delete-person-dialog.tsx:27-89` and only needs `PersonPreview` + `isRootPerson`, both present in the payload.
- Preview content to extract: `resources/js/pages/persons/show.tsx:57-174`.

## What We're NOT Doing

- Making relations read-only in the preview or adding "Pokaż na drzewie" per relation (S-29); relation editing on the tree (S-25 – S-28).
- Browser-history entries per viewed person or deep-link guarantees — `?person=` is an implementation mechanism, not a supported address; no redirects from old `/persons/{id}` URLs.
- Changing what clicking a tree card does (it keeps re-centring).
- Source kinds / source modals (S-17, S-18) — they will be built inside this modal later.
- Modifying shadcn `resources/js/components/ui/*`, adding tokens or a design-system dependency; screenshot test infrastructure (visual gate is manual, as in S-20).
- Updating the S-29 roadmap text for the page removal brought forward here — leave it to `/10x-roadmap` (noted in the brief).

## Implementation Approach

Two vertical phases. Phase 1 introduces the server-side preview props, the modal host in `AppLayout`, a single `PersonPreviewLink` used by every entry point, and the redirect changes for `update` and `destroy`; afterwards the whole app uses the modal and the old page is unreachable from the UI (still reachable by URL). Phase 2 applies FR-003 (store lands on the tree), deletes the old page/route/method, and migrates the remaining `persons/show` tests to the preview props.

Opening a preview is an Inertia partial visit to the current URL with `person=<id>`, `replace: true`, `preserveState`, `preserveScroll`, `only: ['personPreview']` — the underlying page's props are untouched. "Wstecz" is a client-side stack of person ids held by the provider: a `PersonPreviewLink` clicked while a preview is open pushes the current id; "Wstecz" pops and opens the popped id; the stack clears whenever `personPreview` becomes null (close, navigating elsewhere, deletion). Closing strips `person` from the URL client-side (no request) and clears the prop.

## Critical Implementation Details

**State sequencing** — The stack must clear when `personPreview` turns null, not on every visit: mutations inside the modal (`router.put`/`router.delete` default `preserveState: false`) remount the page but land back on the same `?person=` URL, and the stack must survive them. That is why the stack lives in the `AppLayout` provider, not in the dialog or page.

**Timing & lifecycle** — `destroy` must never redirect to a URL that still carries `person=<deleted id>` (the shared prop would 404) or to `tree/{deleted id}` (the tree 404s). Derive the target from the referring URL: parse it, drop `person`; if it matches route `tree.show` with the deleted id, use `tree.index` instead. A `spouse=<deleted id>` left on another person's tree URL must still render (verify `BuildFamilySheet::selectUnion` falls back; add a test).

**User experience spec** — Keep the `FormDialogContent` overlay-click block: nested dialogs (edit, relative picker, scan picker, confirmations) render in portals, and the block also guarantees they never dismiss the preview. Radix requires a description for each dialog; give the preview a `DialogDescription` (lifespan or a visually hidden "Podgląd osoby").

## Phase 1: Preview modal on every page

### Overview

Serve the preview as shared props keyed on `?person=`, render it in a modal hosted by `AppLayout`, switch all entry points to it, and make `update`/`destroy` return to the page the modal is open over.

### Changes Required:

#### 1. Shared preview props

**File**: `app/Http/Middleware/HandleInertiaRequests.php`, `app/Actions/Persons/BuildPersonPreview.php` (new)

**Intent**: Any page can show the preview of a person from the user's tree when its URL carries `person=<id>`; relation candidates and available scans become available the same way for the pickers inside the modal.

**Contract**: `share()` adds `personPreview` (closure → `null` when there is no authenticated user or `person` is absent/non-numeric; otherwise resolves the person through `$request->user()->tree->people()->findOrFail($id)` plus `Gate::authorize('view', …)` so a missing or other user's id is a 404, then returns `BuildPersonPreview::handle()`), `availableScans` (`Inertia::optional`, same query as `PersonController.php:98-100`) and `relationCandidates` (`Inertia::optional`, `ListRelationCandidates::handle($person)`; empty/absent when no `person`). `BuildPersonPreview::handle(Person $person, User $user): array` eager-loads the relations from `PersonController.php:90` and returns `{ person: PersonResource, personForm: PersonFormResource, genders, religions, isRootPerson }` (resources `->resolve()`d). `PersonController@show` delegates to the same action (and loses its two optional props) so page and modal cannot diverge until phase 2 removes it.

#### 2. Redirects for update and destroy

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: Editing from the modal refreshes the modal in place; deleting closes it and keeps the underlying page, returning the tree to the proband when the centre was deleted (FR-013).

**Contract**: `update` → `redirect()->back(fallback: route('persons.index'))`. `destroy` → referring URL with the `person` query key removed; if the referring URL matches route `tree.show` whose `person` parameter equals the deleted id → `route('tree.index')`; no/unmatchable referrer → `route('persons.index')`. Toasts unchanged. Keep the referrer-parsing in a small private method with a doc comment.

#### 3. Preview types

**File**: `resources/js/types/person.ts`, `resources/js/types/global.d.ts`

**Intent**: Type the new shared props.

**Contract**: `PersonPreviewPayload = { person: PersonPreview; personForm: PersonFormData; genders: Gender[]; religions: Religion[]; isRootPerson: boolean }`; `sharedPageProps` gains `personPreview: PersonPreviewPayload | null`, `availableScans?: Scan[]`, `relationCandidates?: RelationCandidate[]`.

#### 4. Preview content component

**File**: `resources/js/components/persons/person-preview.tsx` (new), `resources/js/pages/persons/show.tsx`

**Intent**: One component renders the preview's actions and cards, used by the modal (and by the old page until phase 2).

**Contract**: `PersonPreview({ preview: PersonPreviewPayload; availableScans?: Scan[]; relationCandidates?: RelationCandidate[] })` renders the actions row (Probant badge / `SetRootPersonButton`, "Pokaż w drzewie" → `treeShow(id)`, `PersonFormDialog` edit, `DeletePersonDialog`) and the cards from `persons/show.tsx:102-173` unchanged. `persons/show.tsx` keeps its `Head`/breadcrumbs/heading and renders this component.

#### 5. Preview host, link and dialog

**File**: `resources/js/components/persons/person-preview-provider.tsx` (new), `resources/js/components/persons/person-preview-link.tsx` (new), `resources/js/components/persons/person-preview-dialog.tsx` (new), `resources/js/layouts/app-layout.tsx`, `resources/js/components/form-dialog.tsx`

**Intent**: A single place owns opening, swapping, "Wstecz" and closing; every entry point uses the same link; the modal renders whenever `personPreview` is non-null.

**Contract**:
- Provider (context + `usePersonPreview()`): holds the back stack; `open(id)` = `router.visit(<current URL with person=id>, { replace: true, preserveState: true, preserveScroll: true, only: ['personPreview'] })`; `back()` pops and opens; `close()` strips `person` from the URL without a server request (client-side `router.replace` with `personPreview: null` — confirm the 3.7 signature in `node_modules/@inertiajs/core`) ; clears the stack whenever `usePage().props.personPreview` becomes null. Mounted in `AppLayout` around the children, together with `PersonPreviewDialog`.
- `PersonPreviewLink({ personId, className, children, … })`: an Inertia `Link` whose `href` is the current URL with `person=<id>` (so middle-click still works) and whose visit options match `open`; if a preview is open, clicking pushes the current person id first.
- `PersonPreviewDialog`: controlled by `personPreview !== null`; `FormDialogContent` + header with "Wstecz" (ghost button with arrow, only when the stack is non-empty), title = `display_name`, description (lifespan or visually hidden "Podgląd osoby"); `FormDialogBody` with `PersonPreview`, reading `availableScans`/`relationCandidates` from page props; `onOpenChange(false)` → `close()`. No footer.
- `FormDialogHeader`: optional leading slot for the "Wstecz" button (backward compatible for `PersonFormDialog`).

#### 6. Entry points

**File**: `resources/js/pages/persons/index.tsx`, `resources/js/pages/tree/show.tsx`, `resources/js/components/tree/sheet-person-card.tsx`, `resources/js/components/persons/relatives-card.tsx`, `resources/js/components/persons/marriages-section.tsx`, `resources/js/pages/scans/show.tsx`

**Intent**: Every place that led to the preview page opens the modal instead.

**Contract**: Replace each `Link href={PersonController.show(id)}` with `PersonPreviewLink personId={id}` keeping classes, labels and `aria-label`s (list row card, tree header "Podgląd", tree card eye button, relative name, spouse name, scan "Potwierdza" item). Tree card body link (re-centre) unchanged.

#### 7. Feature tests

**File**: `tests/Feature/PersonsTest.php`, `tests/Pest.php`

**Intent**: Prove the preview props, their privacy and the new redirects.

**Contract**: Pest helper `personPreviewUrl(Person $person, string $route = 'persons.index', array $parameters = []): string`. New tests: preview props present on `persons.index?person=`, `tree.show?person=` and `scans.show?person=` (component unchanged, `personPreview.person.id`, `isRootPerson`, `personForm`); `personPreview` null without the param and with a non-numeric one; other user's / non-existent id → 404; partial reload of `relationCandidates` and `availableScans` against a host page with `?person=` returns the expected lists; `update` from `persons.index?person=X` redirects back to it; `destroy` from `tree.show(X)?person=X` → `tree.index`, from `tree.show(Y)?person=X&spouse=X` → `tree.show(Y)` without `person` (and that page renders), from `persons.index?q=Kow&person=X` → `persons.index?q=Kow`, without referrer → `persons.index`. Existing update/destroy redirect assertions adjusted.

### Success Criteria:

#### Automated Verification:

- Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- On the tree, the eye button of a non-centre card opens the modal; the tree behind stays on the same centre and union; closing with X or Esc leaves it unchanged
- Clicking a card body still re-centres the tree; the header "Podgląd" opens the centre person's modal
- On `/persons?q=Kow`, clicking a row opens the modal; closing returns to the same filtered list with the search text intact
- On a scan preview, a "Potwierdza" entry opens the modal over the scan page
- In the modal, clicking parent → grandparent → uncle swaps the content; "Wstecz" returns uncle → grandparent → parent → start and then disappears
- Adding a parent in the modal (opened from the tree) shows the parent in the modal and on the tree behind it; "Wstecz" still works afterwards
- Linking and unlinking a scan on birth/marriage/death works inside the modal; clicking a scan thumbnail opens the full scan page, and browser back returns to the tree with the modal reopened
- "Edytuj" opens the person form over the preview; saving updates the modal and closes only the form
- "Ustaw jako probanta" in the modal swaps to the "Probant" badge
- Deleting a non-centre person from the tree modal keeps the tree on the same centre; deleting the centre person returns the tree to the proband; deleting from the list keeps the filtered list
- At 375px the modal fills the screen, title/"Wstecz"/X stay pinned and the content scrolls; at 1280px it is centred with an inner scroll for long content; dark mode looks correct

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Adding lands on the tree; remove the preview page

### Overview

`store` redirects to the new person's tree (FR-003); the `/persons/{id}` page, route, controller method and remaining direct `persons/show` tests are removed or migrated.

### Changes Required:

#### 1. Store lands on the tree

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: A new person has no relations yet; the tree is where they get connected (FR-003).

**Contract**: `store` → `to_route('tree.show', $person)` with the "Dodano osobę" toast. The now-unused `back()` rationale in `person-form-dialog.tsx` comments (if any) is removed.

#### 2. Remove the page, method and route

**File**: `app/Http/Controllers/PersonController.php`, `routes/web.php`, `resources/js/pages/persons/show.tsx` (delete)

**Intent**: The preview lives only in the modal.

**Contract**: `PersonController@show` removed; `Route::resource('persons', …)->only(['index', 'store', 'update', 'destroy'])`. Regenerate Wayfinder (`vendor/bin/sail artisan wayfinder:generate`); no imports of `PersonController.show` remain.

#### 3. Migrate remaining tests

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/MarriagesTest.php`, `tests/Feature/ParentChildRelationsTest.php`, `tests/Feature/TreeTest.php`, `tests/Feature/EventScansTest.php`

**Intent**: Keep every behavioural assertion (event confirmation, parents/children ordering, marriages on both spouses, proband flag, optional props, other-user 404) but read it from `personPreview` on a host page.

**Contract**: Replace `get(route('persons.show', …))` + `component('persons/show')` + `where('person…')` with `get(personPreviewUrl(…))` + `where('personPreview.person…')`; `from(route('persons.show', …))` becomes `from(personPreviewUrl(…))` with matching `assertRedirect`. Guest test drops the show line. Store test asserts redirect to `tree.show` of the created person. New: `GET /persons/{id}` → 405. Drop the tests about `availableScans`/`relationCandidates` on `persons/show` that phase 1 re-covers on the shared props (no behaviour lost).

### Success Criteria:

#### Automated Verification:

- Full affected suite passes: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- No references to the removed page remain: `grep -rn "persons/show\|PersonController.show\|persons.show" resources/js app routes tests` returns nothing outside generated Wayfinder dirs

#### Manual Verification:

- Adding "Jan Testowy" from the list lands on `/tree/{id}` centred on him with the "Dodano osobę" toast
- `/persons/{id}` typed in the address bar no longer shows a preview page
- No link anywhere (list, tree, scans, modal breadcrumbs) leads to the removed page

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None — no new pure logic; behaviour is covered by feature tests.

### Integration Tests:

- `tests/Feature/PersonsTest.php`: `personPreview` on three host pages, null/non-numeric param, other-user and missing id 404, optional props via partial reload with `?person=`, `update` back, `destroy` target derivation (centre → proband, other centre, list with filter, spouse param pointing at the deleted person, no referrer), `store` → tree, `GET /persons/{id}` → 405.
- Migrated preview assertions in Marriages / ParentChildRelations / Tree / EventScans tests.

### Manual Testing Steps:

1. From the tree, open a relative's preview, walk parent → grandparent, use "Wstecz", close — tree unchanged.
2. Add a parent inside the modal and watch the tree behind update; link a scan to birth; open the scan and come back with browser back.
3. Delete the centre person from its modal — tree returns to the proband.
4. Open previews from the filtered list and from a scan page.
5. Visual gate: capture the modal — person without relations/facts, full person with confirmed facts and marriages, "Wstecz" visible, proband badge, edit form on top, delete confirmation — at 375px and 1280px, light and dark. Attach to the review.

## Performance Considerations

`personPreview` is a closure evaluated only on full or explicitly-requested visits and returns immediately without `person=`; with it, the queries equal today's show page (`PersonController.php:90`). Opening/swapping is a partial visit returning only `personPreview`. Partial reloads for candidates/scans skip `personPreview`.

## Migration Notes

None — no schema or data changes. Old `/persons/{id}` bookmarks stop working (accepted by PRD v4 FR-009). Roadmap: S-29's "strona `/persons/{id}` znika" is delivered here; `/10x-roadmap` should narrow S-29 to read-only relations + "Pokaż na drzewie".

## References

- Roadmap slice: `context/foundation/roadmap.md` — S-24 `person-preview-in-modal`
- PRD: `context/foundation/prd.md` (v4) — FR-001 – FR-003, FR-009, FR-010, FR-012, FR-013, US-01
- Prior plan (modal layout, form dialog): `context/archive/2026-09-28-person-form-in-modal/plan.md`
- Preview page to extract: `resources/js/pages/persons/show.tsx:57-174`
- Modal layout: `resources/js/components/form-dialog.tsx:14-81`
- Optional-prop reload hooks: `resources/js/hooks/use-relation-candidates.ts:30-38`, `resources/js/components/scans/scan-picker-dialog.tsx:54-55`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Preview modal on every page

#### Automated

- [x] 1.1 Affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php` — 5e191be
- [x] 1.2 Larastan passes: `vendor/bin/sail composer types:check` — 5e191be
- [x] 1.3 Format and lint pass: `vendor/bin/sail npm run check` — 5e191be
- [x] 1.4 Type check passes: `vendor/bin/sail npm run types:check` — 5e191be
- [x] 1.5 Production build succeeds: `vendor/bin/sail npm run build` — 5e191be
- [x] 1.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent` — 5e191be

#### Manual

- [x] 1.7 On the tree, the eye button of a non-centre card opens the modal; the tree behind stays on the same centre and union; closing with X or Esc leaves it unchanged — 5e191be
- [x] 1.8 Clicking a card body still re-centres the tree; the header "Podgląd" opens the centre person's modal — 5e191be
- [x] 1.9 On `/persons?q=Kow`, clicking a row opens the modal; closing returns to the same filtered list with the search text intact — 5e191be
- [x] 1.10 On a scan preview, a "Potwierdza" entry opens the modal over the scan page — 5e191be
- [x] 1.11 In the modal, clicking parent → grandparent → uncle swaps the content; "Wstecz" returns uncle → grandparent → parent → start and then disappears — 5e191be
- [x] 1.12 Adding a parent in the modal (opened from the tree) shows the parent in the modal and on the tree behind it; "Wstecz" still works afterwards — 5e191be
- [x] 1.13 Linking and unlinking a scan on birth/marriage/death works inside the modal; clicking a scan thumbnail opens the full scan page, and browser back returns to the tree with the modal reopened — 5e191be
- [x] 1.14 "Edytuj" opens the person form over the preview; saving updates the modal and closes only the form — 5e191be
- [x] 1.15 "Ustaw jako probanta" in the modal swaps to the "Probant" badge — 5e191be
- [x] 1.16 Deleting a non-centre person from the tree modal keeps the tree on the same centre; deleting the centre person returns the tree to the proband; deleting from the list keeps the filtered list — 5e191be
- [x] 1.17 At 375px the modal fills the screen, title/"Wstecz"/X stay pinned and the content scrolls; at 1280px it is centred with an inner scroll for long content; dark mode looks correct — 5e191be

### Phase 2: Adding lands on the tree; remove the preview page

#### Automated

- [x] 2.1 Full affected suite passes: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php`
- [x] 2.2 Larastan passes: `vendor/bin/sail composer types:check`
- [x] 2.3 Format and lint pass: `vendor/bin/sail npm run check`
- [x] 2.4 Type check passes: `vendor/bin/sail npm run types:check`
- [x] 2.5 Production build succeeds: `vendor/bin/sail npm run build`
- [x] 2.6 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- [x] 2.7 No references to the removed page remain: `grep -rn "persons/show\|PersonController.show\|persons.show" resources/js app routes tests` returns nothing outside generated Wayfinder dirs

#### Manual

- [x] 2.8 Adding "Jan Testowy" from the list lands on `/tree/{id}` centred on him with the "Dodano osobę" toast
- [x] 2.9 `/persons/{id}` typed in the address bar no longer shows a preview page
- [x] 2.10 No link anywhere (list, tree, scans, modal breadcrumbs) leads to the removed page
