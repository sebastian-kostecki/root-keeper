# Person Edit and Delete Implementation Plan

## Overview

Roadmap slice S-05 (FR-006, FR-007). A logged-in user can open an edit page for a person from the person preview, change every field captured at creation (names, residence, religion, birth date and place, deceased flag, death date and place) and save. From the same preview the user can delete the person after an explicit confirmation dialog that states what will be lost. There is no trash or undo (PRD Non-Goals), so the confirmation and the deletion scope must be unambiguous.

## Current State Analysis

- Persons expose only `index`, `create`, `store`, `show` (`routes/web.php:11`). `PersonPolicy` has only `view`, returning `denyAsNotFound()` for another tree (`app/Policies/PersonPolicy.php:14-19`).
- `CreatePerson` writes the person plus a birth event (always, even with empty fields) and a death event only when `is_deceased` is checked, each joined through `event_participants` with role `Subject` (`app/Actions/Persons/CreatePerson.php:40-75`). The date-input → event-column mapping and the checkbox coercion (`isChecked`) are private to that action.
- Validation lives in `PersonStoreRequest` (`app/Http/Requests/PersonStoreRequest.php:19-101`): name-required-without-other, partial-date dependencies, `prohibited_unless:is_deceased` on death fields, impossible-date check in an `after` hook, Polish attribute names. It has no `authorize()`.
- **Deletion trap:** `events` reference only `tree_id` (`database/migrations/2026_09_23_045723_create_events_table.php:16`). Deleting a `people` row cascades `event_participants` (`..._create_event_participants_table.php:17`) but leaves the birth/death `events` orphaned together with their `event_scan` links (`..._create_event_scan_table.php:16`). Deleting an `events` row cascades both pivots (proven by `tests/Feature/EventScansTest.php:173`).
- S-07 will model marriage as one event with two participants (`context/archive/2026-09-24-birth-death-confirmed-by-scan/plan.md:7`), so "delete all of the person's events" would later erase a spouse's marriage.
- `PersonResource` returns only a formatted date string (`app/Http/Resources/PersonResource.php:42-43`, `EventResource.php:34`); `PartialDateFields` has no default values (`resources/js/components/persons/partial-date-fields.tsx:11-74`). The edit form needs raw date parts and prefilled inputs.
- The person preview already loads `events.scans` (`app/Http/Controllers/PersonController.php:66`), so the delete dialog can count scan links client-side from `person.birth.scans` / `person.death.scans`.
- Confirmation dialog pattern: `resources/js/components/scans/unlink-scan-dialog.tsx` (shadcn `Dialog`, `router.delete`, destructive button, consequence sentence). Toasts via `Inertia::flash('toast', …)`.
- No `.ai/rules` directory and no `context/foundation/lessons.md` in this repo.

## Desired End State

- `/persons/{id}` shows "Edytuj" and "Usuń" actions next to the person's name.
- `/persons/{id}/edit` shows the same form as "Dodaj osobę", prefilled with the person's current data. An event already confirmed by a scan shows a muted hint under its heading: "Potwierdzone aktem — upewnij się, że zmiana zgadza się ze skanem." Saving redirects to the preview with the toast "Zapisano zmiany".
- Editing the birth or death date/place updates the existing event in place; its scan links survive.
- Checking "Zmarły/a" on a living person creates a death event. Unchecking it deletes the death event when it has no scans; when it has linked scans the save is rejected with the error "Najpierw odepnij skany od zgonu na podglądzie osoby." under the checkbox, and nothing changes.
- "Usuń" opens a dialog naming the person, stating that their data, birth and (if present) death will be removed together with N certificate links (correct Polish plural), that scans stay in the scan library, and that this cannot be undone. Confirming deletes the person and redirects to `/persons` with the toast "Usunięto osobę".
- Deletion removes the person, every event in which the person is the **only** participant (with its scan links), and detaches the person from events that have other participants. Scan rows and files are untouched.
- No route lets a user view the edit form of, update, or delete another tree's person (404), proven by feature tests.

### Key Discoveries:

- Events are shared-capable by design (`event_participants` with `unique(event_id, person_id)`), so "sole participant" is the correct, future-proof deletion boundary.
- `Person::birth()` / `death()` read the loaded `events` collection (`app/Models/Person.php:61-69`) — load `events` (or `events.scans`) before calling them in the new code paths.
- Form Request authorization pattern: `authorize(): Response` returning `Gate::inspect(...)` on the route model (`app/Http/Requests/EventScanStoreRequest.php:16-19`).
- Unchecked checkboxes are not submitted, and the death fields are not rendered when "Zmarły/a" is unchecked, so an update without `is_deceased` means "not deceased" and carries no death fields — consistent with the existing `prohibited_unless` rules.
- Wayfinder regenerates `@/actions/.../PersonController` with `edit`, `update`, `destroy` once the routes exist; `update.form()` spoofs the PUT method.

## What We're NOT Doing

- No trash, soft delete or undo for persons (PRD Non-Goals).
- No detection of disagreement between an edited fact and its linked scan, no "sprzeczny" state (PRD Business Logic / FR-006 accepted risk) — only the static hint.
- No deletion of scan files or scan rows when deleting a person (scan deletion is S-08).
- No delete action on the persons list — only on the preview.
- No relation handling beyond the generic sole-participant rule; parent–child relations (S-06) and marriage (S-07) do not exist yet and will add their own cleanup if they need it.
- No typed-name confirmation.
- No UI restyle or new design tokens; the new page and dialog reuse existing shadcn components.

## Implementation Approach

Two vertical phases, each shipping a clickable feature with its feature tests. Edit reuses the store validation by subclassing the request and adding one rule; the event-writing logic is shared between `CreatePerson` and a new `UpdatePerson` so date handling cannot drift. The create form's fields move into a shared component that accepts optional defaults, so create and edit render the same markup. Delete is a small transactional action with an explicit sole-participant rule instead of relying on FK cascades alone.

## Critical Implementation Details

- **Update in place, never recreate events.** `UpdatePerson` must update the existing birth/death `events` rows; deleting and recreating them would silently drop `event_scan` links, which is exactly the data loss this slice must avoid. Only an unchecked "Zmarły/a" with no linked scans may delete the death event.
- **Delete order.** Inside one transaction: delete the sole-participant events first (cascading their `event_scan` and `event_participants` rows), then delete the person (cascading remaining participant rows on shared events). Deleting the person first would drop the participant rows and make "sole participant" impossible to compute.

## Phase 1: Edit a person

### Overview

Add the edit page and update endpoint, with the deceased/scan guard and the confirmed-fact hint, and an "Edytuj" action on the preview.

### Changes Required:

#### 1. Routes and policy

**File**: `routes/web.php`, `app/Policies/PersonPolicy.php`

**Intent**: Expose the edit form and update endpoint behind the existing `auth` + `verified` group, and authorize them with the same tree check as `view`.

**Contract**: `Route::resource('persons', …)->only([... 'edit', 'update'])` → `GET persons/{person}/edit` (`persons.edit`), `PUT|PATCH persons/{person}` (`persons.update`). `PersonPolicy::update(User, Person): Response` — allow when `tree_id` matches the user's tree, otherwise `denyAsNotFound()`.

#### 2. Shared event writing

**File**: `app/Actions/Persons/CreatePerson.php` and a new shared helper in `app/Actions/Persons/`

**Intent**: Move the date-input → event-column mapping (`date_year`, `date_month`, `date_day`, `date_is_approximate`, `place`) and the checkbox coercion out of `CreatePerson` into one place that both `CreatePerson` and `UpdatePerson` use, so the two cannot diverge. `CreatePerson` behaviour stays identical (its existing tests must still pass unchanged).

**Contract**: One reusable mapping from the `birth`/`death` input shape (`array{year?, month?, day?, is_approximate?, place?}`) to the fillable event attributes, plus the bool coercion for checkbox values. Name and form (trait, invokable helper or static method) are the implementer's choice; follow the PHPDoc array-shape style of `CreatePerson`.

#### 3. Update request

**File**: `app/Http/Requests/PersonUpdateRequest.php` (new)

**Intent**: Reuse every store rule, attribute name and the impossible-date check, authorize against the route person, and reject unchecking "Zmarły/a" while the person's death event has linked scans.

**Contract**: `class PersonUpdateRequest extends PersonStoreRequest`. `authorize(): Response` returns `Gate::inspect('update', $this->route('person'))`. `withValidator()` calls the parent, then adds an `after` check: when `is_deceased` is not accepted and the person's death event has at least one linked scan, add the error `'Najpierw odepnij skany od zgonu na podglądzie osoby.'` on key `is_deceased` (translated via `__()` like the existing date message).

#### 4. Update action

**File**: `app/Actions/Persons/UpdatePerson.php` (new)

**Intent**: Apply validated form data to a person in a transaction: overwrite person fields (absent → null, same as create), update the birth event in place (create it only if somehow missing), and for death: update in place or create when deceased; delete the existing death event when not deceased (validation guarantees it has no scans).

**Contract**: `handle(Person $person, array $data): Person` with the same `$data` array shape as `CreatePerson::handle`. Birth/death events are found through the person's `Subject` participation (`Person::birth()` / `death()` after loading `events`). Event ids of existing birth/death events are preserved.

#### 5. Form data resource

**File**: `app/Http/Resources/PersonFormResource.php` (new)

**Intent**: Give the edit page raw values to prefill the form, including whether each event is confirmed (for the hint), without changing the payloads of `index` and `show`.

**Contract**:
```
{
  id: int, display_name: string,
  first_name: string|null, last_name: string|null, residence: string|null,
  religion: string|null,            // enum value, not the {value,label} object
  is_deceased: bool,                // death event exists
  birth: FormEvent|null, death: FormEvent|null,
}
FormEvent = { year: int|null, month: int|null, day: int|null, is_approximate: bool, place: string|null, is_confirmed: bool }
```

#### 6. Controller

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: `edit` renders `persons/edit` with the form resource and `religions`; `update` delegates to `UpdatePerson`, flashes the toast `Zapisano zmiany` and redirects to `persons.show`.

**Contract**: `edit(Person $person): Response` — `Gate::authorize('update', $person)`, load `events.scans`, props `person` (`PersonFormResource`) and `religions` (`Religion::options()`). `update(PersonUpdateRequest $request, Person $person, UpdatePerson $updatePerson): RedirectResponse`.

#### 7. Shared form fields and date defaults

**Files**: `resources/js/components/persons/partial-date-fields.tsx`, `resources/js/components/persons/person-form-fields.tsx` (new), `resources/js/pages/persons/create.tsx`, `resources/js/types/person.ts`

**Intent**: Let `PartialDateFields` accept optional initial values (`defaultValue` / `defaultChecked`). Extract the field block of `create.tsx` (names, residence, religion select + hidden input, birth, "Zmarły/a" checkbox, conditional death) into `PersonFormFields`, which takes optional person defaults and shows the confirmed hint under "Urodzenie"/"Zgon" when the event is confirmed. `create.tsx` renders it without defaults and looks exactly as today.

**Contract**: New TS types `PersonFormEvent` and `PersonFormData` mirroring `PersonFormResource`. `PersonFormFields` props: `religions: Religion[]`, `errors`, `person?: PersonFormData`. Hint copy: `Potwierdzone aktem — upewnij się, że zmiana zgadza się ze skanem.` (muted small text). The `is_deceased` `InputError` stays directly under the checkbox so the scan-guard error is visible there.

#### 8. Edit page and preview action

**Files**: `resources/js/pages/persons/edit.tsx` (new), `resources/js/pages/persons/show.tsx`

**Intent**: New page "Edytuj osobę" using `<Form {...PersonController.update.form(person.id)}>` with `PersonFormFields` prefilled, "Zapisz" and "Anuluj" (back to the preview); breadcrumbs Osoby › {name} › Edytuj via `setLayoutProps` (as in `show.tsx`). The preview gets an "Edytuj" outline button next to the heading linking to `PersonController.edit(person.id)`.

**Contract**: Page component `persons/edit` with props `{ person: PersonFormData; religions: Religion[] }`. The heading row on `show.tsx` becomes a flex row (heading left, actions right) so phase 2 can add "Usuń" beside "Edytuj".

#### 9. Feature tests

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Cover the new behaviour and its failure modes (see Testing Strategy). Extend the existing guest-redirect test with `persons.edit` and `persons.update`.

### Success Criteria:

#### Automated Verification:

- Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventScansTest.php`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Code style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- Frontend types compile after Wayfinder regeneration: `vendor/bin/sail npm run types:check`
- Frontend lint/format check passes: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`

#### Manual Verification:

- "Edytuj" on a preview opens the form prefilled with every field, including partial dates ("sam rok", "rok+miesiąc") and the "około" checkbox
- Changing the birth date of a person with a linked birth scan saves, shows "Zapisano zmiany", and the preview still shows the scan and "Potwierdzone aktem"
- The confirmed hint appears only under events that have a scan
- Unchecking "Zmarły/a" on a person with a death scan shows the Polish error under the checkbox and keeps the death card; without a scan the death card disappears after saving
- "Dodaj osobę" looks and works exactly as before

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Delete a person

### Overview

Add the destroy endpoint with the sole-participant deletion rule and a confirmation dialog on the preview.

### Changes Required:

#### 1. Route and policy

**File**: `routes/web.php`, `app/Policies/PersonPolicy.php`

**Intent**: Expose deletion behind the same group and tree check.

**Contract**: add `'destroy'` to the resource `only` list → `DELETE persons/{person}` (`persons.destroy`). `PersonPolicy::delete(User, Person): Response` with the same tree check / `denyAsNotFound()` as `view` and `update`.

#### 2. Delete action

**File**: `app/Actions/Persons/DeletePerson.php` (new)

**Intent**: In one transaction, delete every event whose only participant is this person (their `event_scan` and `event_participants` rows cascade), then delete the person (cascading participant rows on events shared with others, which survive). Scans are never touched.

**Contract**: `handle(Person $person): void`. "Sole participant" = the event has exactly one `event_participants` row. Ordering per Critical Implementation Details.

#### 3. Controller

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: `destroy` authorizes, delegates to `DeletePerson`, flashes `Usunięto osobę`, redirects to `persons.index`.

**Contract**: `destroy(Person $person, DeletePerson $deletePerson): RedirectResponse` with `Gate::authorize('delete', $person)`.

#### 4. Delete confirmation dialog

**Files**: `resources/js/components/persons/delete-person-dialog.tsx` (new), `resources/js/pages/persons/show.tsx`

**Intent**: A dialog modelled on `UnlinkScanDialog`: title "Usuń osobę", description naming the person and the consequences, "Anuluj" and a destructive "Usuń" button that calls `router.delete(PersonController.destroy(person.id).url)` and is disabled while processing. The preview shows a destructive-outline "Usuń" trigger next to "Edytuj".

**Contract**: Props `{ person: PersonPreview; trigger: React.ReactNode }`. Link count = `birth.scans.length + death.scans.length`. Description copy:
- base: `Czy na pewno chcesz usunąć osobę „{display_name}”? Usunięte zostaną jej dane i urodzenie` + (` oraz zgon` when `death` is present)
- when count > 0: `, a także {n} {powiązanie|powiązania|powiązań} ze skanami aktów` using Polish plural rules (n = 1 → powiązanie; last digit 2–4 and n mod 100 not in 12–14 → powiązania, e.g. 3, 22; otherwise → powiązań, e.g. 5, 12, 25)
- then: `. Skany pozostaną w bibliotece skanów. Tej operacji nie można cofnąć.`

Example: deceased person, 3 links → "…Usunięte zostaną jej dane i urodzenie oraz zgon, a także 3 powiązania ze skanami aktów. Skany pozostaną w bibliotece skanów. Tej operacji nie można cofnąć."

#### 5. Feature tests

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Cover deletion scope, isolation and scan preservation (see Testing Strategy). Extend the guest-redirect test with `persons.destroy`.

### Success Criteria:

#### Automated Verification:

- Person and scan feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php`
- Full test suite passes: `vendor/bin/sail artisan test --compact`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Code style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- Frontend types compile after Wayfinder regeneration: `vendor/bin/sail npm run types:check`
- Frontend lint/format check passes: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`

#### Manual Verification:

- `vendor/bin/sail artisan route:list --path=persons` shows `edit`, `update`, `destroy` behind `auth` and `verified`
- "Usuń" on a deceased person with linked scans shows the dialog with the name, "oraz zgon" and the correctly pluralised link count; "Anuluj" closes it without changes
- Confirming redirects to "Osoby" with "Usunięto osobę", the person is gone from the list, and the previously linked scan still opens from "Skany" with its "Potwierdza" list no longer mentioning that person
- Dialog and preview actions read correctly in light and dark themes

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Feature Tests (Phase 1 — edit):

- Edit page renders `persons/edit` with raw date parts, `religion` value, `is_deceased`, and `is_confirmed` true for a birth with a linked scan and false without.
- Edit page and update return 404 for a person from another tree; the other person is unchanged.
- Update changes names, residence, religion, birth date parts and place; redirects to `persons.show` with the `Zapisano zmiany` toast.
- Update of a birth with a linked scan keeps the same birth event id and the `event_scan` link.
- Checking "Zmarły/a" on a living person creates a death event with role `Subject` and the given date/place.
- Unchecking "Zmarły/a" when the death event has no scans deletes the death event.
- Unchecking "Zmarły/a" when the death event has a linked scan fails validation on `is_deceased` with the Polish message; death event and link remain.
- Update reuses store validation (one representative case, e.g. neither first nor last name).

### Feature Tests (Phase 2 — delete):

- Destroy deletes the person, their birth and death events and those events' `event_scan` rows; `scans` rows remain; redirects to `persons.index` with the `Usunięto osobę` toast.
- Destroy of a person who shares an event with another person (built with factories) deletes the person but keeps the shared event and the other participant's row.
- Destroy of another tree's person returns 404 and deletes nothing.

### Manual Testing Steps:

1. Create a person with partial birth date "około 1890", open "Edytuj", verify prefill, change the place, save.
2. Link a scan to the birth, edit the birth year, confirm the scan is still linked and the hint showed.
3. Mark a person deceased via edit, link a death scan, try to uncheck "Zmarły/a" — see the error; unlink the scan, uncheck again — death disappears.
4. Delete that person via the dialog, check the list and the scan's "Potwierdza" section.

## Performance Considerations

None beyond the existing per-person queries; the edit page loads the same `events.scans` relation as the preview.

## Migration Notes

No schema changes. Orphaned events cannot pre-exist because no delete path existed before this slice.

## References

- Roadmap slice: `context/foundation/roadmap.md` (S-05)
- PRD: `context/foundation/prd.md` (FR-006, FR-007, Non-Goals)
- Prior plans: `context/archive/2026-09-23-account-tree-and-first-person/plan.md`, `context/archive/2026-09-24-birth-death-confirmed-by-scan/plan.md`
- Patterns: `app/Actions/Persons/CreatePerson.php:40-75`, `app/Http/Requests/EventScanStoreRequest.php:16-19`, `resources/js/components/scans/unlink-scan-dialog.tsx`, `resources/js/pages/persons/create.tsx:48-174`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Edit a person

#### Automated

- [x] 1.1 Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventScansTest.php` — ce099c1
- [x] 1.2 Static analysis passes: `vendor/bin/sail composer types:check` — ce099c1
- [x] 1.3 Code style is clean: `vendor/bin/sail bin pint --dirty --format agent` — ce099c1
- [x] 1.4 Frontend types compile after Wayfinder regeneration: `vendor/bin/sail npm run types:check` — ce099c1
- [x] 1.5 Frontend lint/format check passes: `vendor/bin/sail npm run check` — ce099c1
- [x] 1.6 Production build succeeds: `vendor/bin/sail npm run build` — ce099c1

#### Manual

- [x] 1.7 "Edytuj" on a preview opens the form prefilled with every field, including partial dates ("sam rok", "rok+miesiąc") and the "około" checkbox — ce099c1
- [x] 1.8 Changing the birth date of a person with a linked birth scan saves, shows "Zapisano zmiany", and the preview still shows the scan and "Potwierdzone aktem" — ce099c1
- [x] 1.9 The confirmed hint appears only under events that have a scan — ce099c1
- [x] 1.10 Unchecking "Zmarły/a" on a person with a death scan shows the Polish error under the checkbox and keeps the death card; without a scan the death card disappears after saving — ce099c1
- [x] 1.11 "Dodaj osobę" looks and works exactly as before — ce099c1

### Phase 2: Delete a person

#### Automated

- [x] 2.1 Person and scan feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php`
- [x] 2.2 Full test suite passes: `vendor/bin/sail artisan test --compact`
- [x] 2.3 Static analysis passes: `vendor/bin/sail composer types:check`
- [x] 2.4 Code style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- [x] 2.5 Frontend types compile after Wayfinder regeneration: `vendor/bin/sail npm run types:check`
- [x] 2.6 Frontend lint/format check passes: `vendor/bin/sail npm run check`
- [x] 2.7 Production build succeeds: `vendor/bin/sail npm run build`

#### Manual

- [x] 2.8 `vendor/bin/sail artisan route:list --path=persons` shows `edit`, `update`, `destroy` behind `auth` and `verified`
- [x] 2.9 "Usuń" on a deceased person with linked scans shows the dialog with the name, "oraz zgon" and the correctly pluralised link count; "Anuluj" closes it without changes
- [x] 2.10 Confirming redirects to "Osoby" with "Usunięto osobę", the person is gone from the list, and the previously linked scan still opens from "Skany" with its "Potwierdza" list no longer mentioning that person
- [x] 2.11 Dialog and preview actions read correctly in light and dark themes
