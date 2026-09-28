# Person Form in Modal Implementation Plan

## Overview

Roadmap slice S-20 (v3:FR-004): the user adds a person from the person list and corrects a person from the person preview in a modal, without leaving the page. The `persons/create` and `persons/edit` pages and routes disappear. On a narrow screen the modal fills the screen and only the fields scroll; on desktop it is a regular centred modal. The modal is the shared building block that S-21 – S-23 will later open from the tree.

## Current State Analysis

- The form markup already lives in one component, `PersonFormFields` (`resources/js/components/persons/person-form-fields.tsx`), used by both `resources/js/pages/persons/create.tsx` and `resources/js/pages/persons/edit.tsx`. Its fields are uncontrolled (`defaultValue`), with local state only for gender, religion and the "Zmarły/a" toggle.
- `PersonController` (`app/Http/Controllers/PersonController.php`) renders `persons/create` (L72-78) and `persons/edit` (L114-125) with `genders` / `religions` from `Gender::options()` / `Religion::options()`; `edit` passes `PersonFormResource` (raw date parts + `is_confirmed`). `store` redirects to `persons.show` of the new person (L89); `update` redirects to `persons.show` (L136).
- The preview (`persons/show`) only has `PersonResource`, whose events carry formatted dates — not enough to prefill the form.
- A working form-in-dialog precedent exists: `MarriageFormDialog` (`resources/js/components/persons/marriage-form-dialog.tsx:108-116`) — `<Form … options={{ preserveScroll: true }} onSuccess={() => setOpen(false)}>`. The installed Inertia `<Form>` defaults `preserveState` to true for non-GET requests, so a validation redirect back keeps the dialog open with its errors (also relied upon in `resources/js/hooks/use-relation-candidates.ts`).
- `resources/js/components/ui/dialog.tsx` is stock shadcn: centred, `sm:max-w-lg`, no max height, no scrolling — a long form overflows on a phone. No existing dialog is full-screen or has a scrolling body (only inner lists with `max-h-96`).
- `resources/js/components/persons/person-candidate-list.tsx:63` links to `PersonController.create()` in the empty state of the relative/spouse picker.
- `tests/Feature/PersonsTest.php` covers the create/edit pages (L15, L18, L513-528, L552-580, L582-591) and asserts the store redirect to the preview (L294). No browser or screenshot tests exist.

## Desired End State

- On `/persons`, "Dodaj osobę" opens a modal with the person form. Saving closes it and keeps the user on the list, with the same search filter (`?q=`) and the "Dodano osobę" toast.
- On `/persons/{id}`, "Edytuj" opens the same modal prefilled (including the "Potwierdzone aktem…" hint on confirmed events). Saving closes it; the preview shows the updated data and the "Zapisano zmiany" toast.
- Validation errors appear inside the open modal; the first error is scrolled into view.
- Below 640px the modal fills the viewport: title and close button pinned at the top, "Anuluj" / "Zapisz" pinned at the bottom, only the fields scroll. From 640px up it is centred, `max-w-2xl`, at most ~90vh tall, with the same pinned header/footer and scrolling body.
- A click on the overlay does not close the modal; Esc, the X button and "Anuluj" close it and discard input.
- `GET /persons/create` and `GET /persons/{id}/edit` return 404; `create.tsx` and `edit.tsx` no longer exist.
- The empty relative/spouse picker's "Dodaj osobę" links to the person list.

### Key Discoveries:

- Form reuse: `resources/js/components/persons/person-form-fields.tsx:35-182` — render it unchanged inside the modal.
- Dialog pattern to follow: `resources/js/components/persons/marriage-form-dialog.tsx:59-211` (controlled `open`, `Form` inside `DialogContent`, `onSuccess` closes).
- Radix unmounts `DialogContent` when closed, so uncontrolled fields reset on every open and the edit modal always prefills from the latest `personForm` prop.
- `preserveState` stays true after a successful store, so the list's `search` state survives — the redirect must therefore return to the same `?q=` (via `back()`), otherwise `persons/index.tsx:28-46` would re-fire the old query.
- Prefill data: `app/Http/Resources/PersonFormResource.php` already produces exactly `PersonFormData` (`resources/js/types/person.ts:90-101`); `show` already eager-loads `events.scans` (`PersonController.php:99`).

## What We're NOT Doing

- Opening the person modal from the tree (S-21 – S-23), prefilled/locked fields, or creating a relation together with the person.
- A "discard changes?" confirmation or dirty-form tracking — only the overlay click is blocked.
- Redirects from the old `/persons/create` and `/persons/{id}/edit` URLs — they 404.
- A nested person modal inside the relative/spouse picker.
- Changing validation, `CreatePerson` / `UpdatePerson`, the residence field (S-14) or the form's fields and their order.
- Modifying the shadcn `resources/js/components/ui/dialog.tsx`, adding colour/spacing tokens, or adding a design-system dependency — the wrapper composes existing primitives and Tailwind utilities.
- Screenshot test infrastructure (the repo has none); the visual gate is manual.

## Implementation Approach

Vertical, two phases. Phase 1 builds the shared responsive dialog wrapper and exercises it on the simpler case (adding from the list), removing the create page and route. Phase 2 adds edit mode on the preview and removes the edit page and route. After phase 1 the app is fully working, with editing still on the old page.

The wrapper is a new shared component (`resources/js/components/form-dialog.tsx`) rather than an edit to shadcn's `ui/dialog.tsx` (which the lint config treats as generated). It owns only layout: full-screen vs centred sizing, pinned header/footer, scrolling body, and blocking the overlay click. `PersonFormDialog` owns person-specific wiring (mode, action, toasts, fields), following `MarriageFormDialog`.

## Critical Implementation Details

**User experience spec** — The `<Form>` element must wrap both the scrolling body and the pinned footer (the submit button lives in the footer), so the form itself is the flex column between header and footer; `min-h-0` on the body is required for `overflow-y-auto` to take effect inside the flex column. On validation error (`onError`), scroll the first rendered error message in the body into view — on a phone the pinned footer otherwise hides that anything failed.

**Timing & lifecycle** — Once the `create` route is removed, `GET /persons/create` matches `persons/{person}`; on PostgreSQL (`DB_CONNECTION=pgsql`) binding the non-numeric `create` throws an invalid-integer query error (500), not a 404. The numeric `{person}` pattern must land in the same phase as the route removal.

**State sequencing** — `store` must return with `back()` (fallback `persons.index`), not a fixed route: the preserved `search` state on the list only matches the server's `filters.q` if the same query string comes back.

## Phase 1: Responsive form dialog and adding a person in a modal

### Overview

Introduce the shared responsive dialog wrapper, `PersonFormDialog` in create mode on the person list, return `store` to the list, and remove the create page and route.

### Changes Required:

#### 1. Shared responsive form dialog

**File**: `resources/js/components/form-dialog.tsx` (new)

**Intent**: A reusable dialog layout for long forms: full-screen below `sm` (640px), centred `sm:max-w-2xl` with `sm:max-h-[90vh]` above, header and footer pinned, body scrolling; overlay clicks never close it. Reusable by S-17 (source modal) and S-21 – S-23.

**Contract**: Exports `FormDialogContent` (wraps `DialogContent` from `@/components/ui/dialog`; flex column, `p-0`, `gap-0`; mobile: inset-0 / `h-dvh` / no radius or border / no translate; desktop: centred sizes above; `onInteractOutside` → `preventDefault()`), `FormDialogHeader` (title + optional description, pinned, padded, leaves room for the built-in X), `FormDialogBody` (`flex-1 min-h-0 overflow-y-auto`, padded, vertical spacing), `FormDialogFooter` (pinned, top border, "Anuluj"/"Zapisz" row using the `DialogFooter` responsive button order). Uses only existing theme tokens (`bg-background`, `border`) and Tailwind utilities. Add a one-line doc comment naming it as the shared long-form dialog.

#### 2. Person form dialog

**File**: `resources/js/components/persons/person-form-dialog.tsx` (new)

**Intent**: The person modal used by the list (and, in phase 2, the preview). Controlled `open`, renders `PersonFormFields` inside `FormDialogBody`, `Form` wrapping body + footer, closes on success, scrolls the first error into view on error.

**Contract**: Props — discriminated union `{ mode: 'create'; genders: Gender[]; religions: Religion[]; trigger: React.ReactNode }` (phase 2 adds `| { mode: 'edit'; person: PersonFormData; … }`). Create mode: `PersonController.store.form()`, title "Dodaj osobę", description "Podaj dane osoby, którą chcesz dodać do drzewa", submit keeps `data-test="create-person-button"`. `options={{ preserveScroll: true }}`, `onSuccess={() => setOpen(false)}`.

#### 3. Person list

**File**: `resources/js/pages/persons/index.tsx`

**Intent**: "Dodaj osobę" opens `PersonFormDialog` instead of linking to the create page.

**Contract**: New props `genders: Gender[]`, `religions: Religion[]`; the header button becomes the dialog trigger.

#### 4. Backend: list props, store redirect, route removal

**File**: `app/Http/Controllers/PersonController.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`

**Intent**: The list provides the form options; `store` returns to where the form was submitted from; the create page no longer exists.

**Contract**: `index` adds `'genders' => Gender::options()`, `'religions' => Religion::options()`. `store` returns `back()` with fallback `route('persons.index')` (keeps flash toast). `create()` method removed; `Route::resource('persons', …)->only([...])` drops `'create'`. Add a global numeric pattern for the `{person}` route parameter (`Route::pattern('person', '[0-9]+')` in `AppServiceProvider::boot()`; confirm the API with `search-docs`) so `/persons/create` is a 404 rather than reaching `show`.

#### 5. Remove the create page and fix the empty picker link

**File**: `resources/js/pages/persons/create.tsx` (delete), `resources/js/components/persons/person-candidate-list.tsx`

**Intent**: The page is replaced by the modal; the picker's empty-state link sends the user to the list, where "Dodaj osobę" opens the modal.

**Contract**: `person-candidate-list.tsx:63` → `href={personsIndex()}` (import from `@/routes/persons`); link text unchanged. Regenerate Wayfinder (`vendor/bin/sail artisan wayfinder:generate`) so `PersonController.create` disappears.

#### 6. Feature tests

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Tests follow the new contract.

**Contract**:
- Guest test (L11-21): drop the `persons.create` line.
- "create and edit pages pass gender options…" (L513): split — the create half becomes "persons index passes gender and religion options" on `persons/index`; the edit half stays until phase 2.
- "store creates a person…" (L284-294): assert redirect back to the list — post `->from(route('persons.index', ['q' => 'Kowal']))` and assert `assertRedirect(route('persons.index', ['q' => 'Kowal']))`.
- New: "store without a previous url redirects to the persons list".
- New: "the old create url returns not found" (`GET /persons/create` → 404).

### Success Criteria:

#### Automated Verification:

- Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- On `/persons`, "Dodaj osobę" opens the modal; saving a valid person closes it, shows "Dodano osobę" and the person appears on the list
- With the list filtered (e.g. "Kowal"), adding "Jan Kowalski" returns to the filtered list with the search text intact and the new person visible
- Submitting an empty form keeps the modal open with Polish errors, and the first error is scrolled into view
- At 375px width: modal fills the screen, title and X stay at the top, "Anuluj"/"Zapisz" stay at the bottom while the fields scroll (with "Zmarły/a" checked so the form is at its longest)
- At 1280px width: modal is centred and wide enough for the two-column name row; a short form has no inner scrollbar, a long one scrolls inside the modal
- Clicking the overlay does not close the modal; Esc, X and "Anuluj" do, and reopening shows an empty form
- Selecting gender/religion from the dropdowns inside the modal works and does not close it
- In a tree with one person, the relative picker's "Dodaj osobę" leads to the person list
- Dark mode: header/footer borders and background look correct

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Editing a person in a modal on the preview

### Overview

`PersonFormDialog` gains edit mode, the preview opens it from "Edytuj", and the edit page, route and controller method are removed.

### Changes Required:

#### 1. Edit mode in the person dialog

**File**: `resources/js/components/persons/person-form-dialog.tsx`

**Intent**: Same modal, prefilled, submitting to update.

**Contract**: Union gains `{ mode: 'edit'; person: PersonFormData; genders; religions; trigger }`. Edit mode: `PersonController.update.form(person.id)`, title "Edytuj osobę", passes `person` to `PersonFormFields` (confirmed-fact hints come with it).

#### 2. Preview page

**File**: `resources/js/pages/persons/show.tsx`

**Intent**: "Edytuj" opens the modal instead of navigating.

**Contract**: New props `personForm: PersonFormData`, `genders: Gender[]`, `religions: Religion[]`; the "Edytuj" button (`variant="outline" size="sm"`) becomes the `PersonFormDialog` trigger.

#### 3. Backend: preview props and route removal

**File**: `app/Http/Controllers/PersonController.php`, `routes/web.php`, `app/Http/Resources/PersonFormResource.php`

**Intent**: The preview provides prefill data and options; the edit page no longer exists.

**Contract**: `show` adds `'personForm' => PersonFormResource::make($person)->resolve()`, `'genders'`, `'religions'` (eager, not optional — the needed relations are already loaded). `edit()` method removed; resource route drops `'edit'`. Update `PersonFormResource`'s doc comment (it now prefills the edit modal). `update` keeps redirecting to `persons.show`.

#### 4. Remove the edit page

**File**: `resources/js/pages/persons/edit.tsx` (delete)

**Intent**: Replaced by the modal.

**Contract**: Regenerate Wayfinder so `PersonController.edit` disappears; no remaining imports of it.

#### 5. Feature tests

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Move edit-page assertions to the preview props and prove the old URL is gone.

**Contract**:
- Guest test: drop the `persons.edit` line.
- The edit half of L513 becomes "person show passes gender and religion options and prefills personForm.gender".
- "edit page renders raw date parts…" (L552) → "person show exposes personForm with raw date parts, religion value, is_deceased and is_confirmed" on `persons/show`.
- "persons edit and update return not found…" (L582) → keep the update-404 assertion and the unchanged-name check, drop the edit GET.
- Extend the old-URL test: `GET /persons/{id}/edit` → 404.

### Success Criteria:

#### Automated Verification:

- Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Format and lint pass: `vendor/bin/sail npm run check`
- Type check passes: `vendor/bin/sail npm run types:check`
- Production build succeeds: `vendor/bin/sail npm run build`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- No references to the removed pages remain: `grep -rn "persons/create\|persons/edit\|PersonController.create\|PersonController.edit" resources/js app routes tests` returns nothing outside generated Wayfinder dirs

#### Manual Verification:

- On a person preview, "Edytuj" opens the modal prefilled with names, residence, gender, religion, birth and (for a deceased person) death
- Saving changes closes the modal, shows "Zapisano zmiany" and the preview shows the new values; reopening shows the saved values
- For a person whose birth is confirmed by a scan, the "Potwierdzone aktem…" hint shows in the modal
- Unchecking "Zmarły/a" on a death with a linked scan shows the validation error inside the modal
- At 375px and 1280px the edit modal behaves like the add modal (pinned header/footer, scrolling body)
- `/persons/create` and `/persons/{id}/edit` show the 404 page

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None — no new pure logic; behaviour is covered by feature tests.

### Integration Tests:

- `tests/Feature/PersonsTest.php`: list and preview props (`genders`, `religions`, `personForm`), store redirect back with the same `q` and its fallback, 404 for the old create/edit URLs, existing store/update/validation/other-user-404 tests unchanged.

### Manual Testing Steps:

1. Add a person from an unfiltered and a filtered list; check toast, list and search text.
2. Submit invalid data on a 375px viewport; confirm the modal stays open and scrolls to the first error.
3. Edit a person with a confirmed birth and a death; confirm prefill, hint and save.
4. Try closing by overlay click, Esc, X and "Anuluj".
5. Visual gate: capture the modal in each state — empty add, add with errors, edit prefilled with a confirmed hint, edit with death fields — at 375px and 1280px, light and dark. Attach to the review.

## Performance Considerations

`show` now also resolves `PersonFormResource` on every full visit; it uses relations already eager-loaded (`events.scans`), so there are no extra queries. Partial reloads (`availableScans`, `relationCandidates`) do not include it.

## Migration Notes

None — no schema or data changes. Removing the routes makes old bookmarks to the create/edit pages 404 (accepted).

## References

- Roadmap slice: `context/foundation/roadmap.md` — S-20 `person-form-in-modal`
- PRD: `context/foundation/prd-v3.md` — FR-004
- Prior plan (edit page, confirmed hint, validation reuse): `context/archive/2026-09-25-person-edit-and-delete/`
- Similar implementation: `resources/js/components/persons/marriage-form-dialog.tsx:59-211`
- Form fields: `resources/js/components/persons/person-form-fields.tsx:35-182`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Responsive form dialog and adding a person in a modal

#### Automated

- [x] 1.1 Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- [x] 1.2 Format and lint pass: `vendor/bin/sail npm run check`
- [x] 1.3 Type check passes: `vendor/bin/sail npm run types:check`
- [x] 1.4 Production build succeeds: `vendor/bin/sail npm run build`
- [x] 1.5 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual

- [x] 1.6 On `/persons`, "Dodaj osobę" opens the modal; saving a valid person closes it, shows "Dodano osobę" and the person appears on the list
- [x] 1.7 With the list filtered (e.g. "Kowal"), adding "Jan Kowalski" returns to the filtered list with the search text intact and the new person visible
- [x] 1.8 Submitting an empty form keeps the modal open with Polish errors, and the first error is scrolled into view
- [x] 1.9 At 375px width: modal fills the screen, title and X stay at the top, "Anuluj"/"Zapisz" stay at the bottom while the fields scroll (with "Zmarły/a" checked so the form is at its longest)
- [x] 1.10 At 1280px width: modal is centred and wide enough for the two-column name row; a short form has no inner scrollbar, a long one scrolls inside the modal
- [x] 1.11 Clicking the overlay does not close the modal; Esc, X and "Anuluj" do, and reopening shows an empty form
- [x] 1.12 Selecting gender/religion from the dropdowns inside the modal works and does not close it
- [x] 1.13 In a tree with one person, the relative picker's "Dodaj osobę" leads to the person list
- [x] 1.14 Dark mode: header/footer borders and background look correct

### Phase 2: Editing a person in a modal on the preview

#### Automated

- [ ] 2.1 Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- [ ] 2.2 Format and lint pass: `vendor/bin/sail npm run check`
- [ ] 2.3 Type check passes: `vendor/bin/sail npm run types:check`
- [ ] 2.4 Production build succeeds: `vendor/bin/sail npm run build`
- [ ] 2.5 PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- [ ] 2.6 No references to the removed pages remain: `grep -rn "persons/create\|persons/edit\|PersonController.create\|PersonController.edit" resources/js app routes tests` returns nothing outside generated Wayfinder dirs

#### Manual

- [ ] 2.7 On a person preview, "Edytuj" opens the modal prefilled with names, residence, gender, religion, birth and (for a deceased person) death
- [ ] 2.8 Saving changes closes the modal, shows "Zapisano zmiany" and the preview shows the new values; reopening shows the saved values
- [ ] 2.9 For a person whose birth is confirmed by a scan, the "Potwierdzone aktem…" hint shows in the modal
- [ ] 2.10 Unchecking "Zmarły/a" on a death with a linked scan shows the validation error inside the modal
- [ ] 2.11 At 375px and 1280px the edit modal behaves like the add modal (pinned header/footer, scrolling body)
- [ ] 2.12 `/persons/create` and `/persons/{id}/edit` show the 404 page
