# Marriage with Shared Source Implementation Plan

## Overview

Roadmap slice S-07 (FR-010, FR-012, FR-014, US-01): the user can join two people of their tree in marriage with a wedding date and place (a person may have several marriages), link a certificate scan to it once and see that scan as the source on both spouses' previews, correct the date and place, and remove the marriage after explicit confirmation. This is the PRD's hardest model rule — one event shared by two people — and it validates the event/participant/scan model from S-01/S-03.

## Current State Analysis

- Events already support several people: `event_participants` (event_id, person_id, role, unique pair) links people to an `Event`, and scans link to the event, not the person, via `event_scan` (`database/migrations/2026_09_23_045724_create_event_participants_table.php`, `database/migrations/2026_09_24_122757_create_event_scan_table.php`). Only `EventType::Birth|Death` and `ParticipantRole::Subject` exist (`app/Enums/EventType.php`, `app/Enums/ParticipantRole.php`). Both columns are plain strings, so new enum cases need no migration.
- Scan linking is event-generic: `EventScanController` store/destroy authorize via `EventPolicy::update` (tree ownership) and work for any event (`app/Http/Controllers/EventScanController.php:18-49`).
- The scan page lists what a scan confirms per event participant (`app/Http/Controllers/ScanController.php:51-70`); S-08's delete-impact dialog consumes the same `confirms` list. A marriage event with two participants will therefore appear twice ("Małżeństwo — A", "Małżeństwo — B") with no changes.
- `DeletePerson` deletes only events whose single participant is the person (`app/Actions/Persons/DeletePerson.php:21-26`); a two-spouse marriage would survive with one participant.
- S-06 established the relation patterns: a FormRequest validating tree ownership, an action that locks both people in id order inside a transaction (`app/Actions/Relations/CreateParentChildRelation.php:20-24`), `ListRelationCandidates` returning per-candidate blocked reasons through the optional `relationCandidates` prop (`app/Http/Controllers/PersonController.php:103-106`), a filterable picker dialog (`resources/js/components/persons/relative-picker-dialog.tsx`), a remove dialog, and a relation count in the delete-person dialog (`resources/js/components/persons/delete-person-dialog.tsx:51-66`).
- The preview renders `RelativesCard` ×2, then `EventCard` for birth and death (`resources/js/pages/persons/show.tsx:84-112`). `EventCard` owns the date/place/source-status/scan-link/unlink block (`resources/js/components/persons/event-card.tsx`).
- Partial-date validation (year range, required_with chains, `is_approximate`, calendar check) lives in `PersonStoreRequest` (`app/Http/Requests/PersonStoreRequest.php:19-104`), inherited by `PersonUpdateRequest`. `PartialDateFields` renders those inputs but its `prefix` prop is typed `'birth' | 'death'` (`resources/js/components/persons/partial-date-fields.tsx:7`). Validation-rule traits live in `app/Concerns/` (`PasswordValidationRules`, `ProfileValidationRules`).

## Desired End State

On a person's preview, between "Urodzenie" and "Zgon", a "Małżeństwa" section lists one card per marriage, ordered by wedding date (unknown dates last), titled "Małżeństwo z <spouse link>" and showing date, place, source status and linked scans exactly like birth/death, plus "Edytuj" and "Usuń małżeństwo". "Dodaj małżeństwo" opens one dialog: pick a spouse from a filterable list (people already married to this person are greyed out with "Już jest małżonkiem"), enter date and place, save. The marriage then appears on both spouses' previews; linking a scan on either preview confirms it on both, and the scan page lists both spouses. Editing keeps linked scans. Removing the marriage (after a dialog stating it disappears for both spouses and how many scan links go; scans stay) or deleting either spouse as a person removes the marriage. The server rejects self-marriage, a second marriage of the same pair, foreign-tree people, and non-marriage events on marriage endpoints.

Verify with `tests/Feature/MarriagesTest.php` plus manual walkthrough of both previews and the scan page.

### Key Discoveries:

- Marriage = `Event{type: marriage}` + two `event_participants{role: spouse}` rows; scan links stay on the event, so "one certificate, both spouses" falls out of the existing model (`app/Models/Event.php:61-77`).
- Route model binding by parameter name lets `marriages/{marriage}` bind `Event $marriage`; the type check (non-marriage → 404) must be explicit because `EventPolicy::update` only checks tree ownership (`app/Policies/EventPolicy.php:14-19`).
- `MapsEventInput` already maps `{year, month, day, is_approximate, place}` to event attributes (`app/Actions/Persons/MapsEventInput.php`) and can be reused by marriage actions.
- `Person::birth()/death()` use `events->firstWhere('type', …)` (`app/Models/Person.php:83-91`), so adding marriage events to a person's `events` does not disturb them; the persons index payload stays unchanged.

## What We're NOT Doing

- Two marriages of the same pair (civil + church, remarriage) — one per pair; a rare real case is covered by several scans on one event.
- A marriage with an unknown or deleted spouse — deleting a spouse removes the marriage.
- Genealogical plausibility checks (marrying a relative, wedding before birth, overlapping marriages) — PRD non-goal of policing fact consistency, as in S-06.
- Create-and-link a new spouse from the dialog — existing people only.
- Divorce, end date, marriage type, witnesses, bride/groom roles (no sex field).
- Marriages on the persons index or the person edit form.
- Changing the spouse of an existing marriage — remove and re-add.
- Graphical tree (S-09), browser tests, undo.

## Implementation Approach

Model a marriage purely as a shared event; add no table. The backend phase adds the enum cases, a shared partial-date validation trait, a `MarriageController` (store/update/destroy) backed by `CreateMarriage` / `UpdateMarriage` actions that enforce the pair rules atomically, the `DeletePerson` cascade, the spouse blocked reason in the existing candidates payload, and a `marriages` block on the preview payload — all covered by feature tests. The UI phase renders marriages through the existing `EventCard` source block and adds create/edit/remove dialogs, reusing the S-06 candidate list.

## Critical Implementation Details

- **Pair uniqueness has no schema guard.** Nothing in the database prevents two marriage events for the same pair, so `CreateMarriage` must lock both people (ascending id, as `CreateParentChildRelation` does) before checking for an existing marriage and inserting, all inside one transaction. The check must match the pair in either order.
- **Delete order in `DeletePerson`.** Marriage events must be deleted explicitly (they have two participants and so escape the existing "sole participant" rule) before the person row is deleted; otherwise the participant cascade leaves a one-spouse marriage behind.

## Phase 1: Marriage model and rules

### Overview

Everything server-side: enum cases, validation, endpoints, actions, cascade, candidate reason, preview payload, and feature tests. After this phase marriages can be created, edited, sourced and removed over HTTP, and the preview payload carries them.

### Changes Required:

#### 1. Enums

**File**: `app/Enums/EventType.php`, `app/Enums/ParticipantRole.php`

**Intent**: Introduce the marriage event type and the spouse participant role.

**Contract**: `EventType::Marriage = 'marriage'` with label `'Małżeństwo'`; `ParticipantRole::Spouse = 'spouse'`. No migration (string columns).

#### 2. Shared partial-date validation

**File**: `app/Concerns/PartialDateValidationRules.php` (new), `app/Http/Requests/PersonStoreRequest.php`

**Intent**: Move the partial-date rules and the calendar check out of `PersonStoreRequest` into a trait so the marriage requests reuse them instead of copying them; person validation behaviour stays identical.

**Contract**: Trait provides rules for a given prefix (year between 1000 and the current year, `required_with` chain month→year and day→month, year required when `is_approximate` is accepted, place max 255) and the impossible-date check that adds `"{prefix}.day"` → "Podana data jest nieprawidłowa.". `PersonStoreRequest` composes birth/death rules from it and appends its death-only `prohibited_unless:is_deceased` rules; error keys and messages unchanged.

#### 3. Requests

**File**: `app/Http/Requests/MarriageStoreRequest.php`, `app/Http/Requests/MarriageUpdateRequest.php` (new)

**Intent**: Validate the create and edit payloads with tree ownership and Polish attribute names.

**Contract**: Store — `person_id` and `spouse_id` required integers existing in `people` of the user's tree, `spouse_id` `different:person_id` ("Osoba nie może poślubić samej siebie."), plus `marriage.*` from the trait. Update — authorizes `update` on the route's event and validates `marriage.*` only. Attributes: `małżonek`, `rok ślubu`, `miesiąc ślubu`, `dzień ślubu`, `miejsce ślubu`, `przybliżona data ślubu`.

#### 4. Actions

**File**: `app/Actions/Relations/CreateMarriage.php`, `app/Actions/Relations/UpdateMarriage.php` (new)

**Intent**: Create a marriage as one event with both people as spouses, rejecting a duplicate pair atomically; update date and place in place so linked scans are kept.

**Contract**: `CreateMarriage::handle(Tree $tree, Person $person, Person $spouse, array $date): Event` — transaction, lock both people in ascending id order, throw `ValidationException` on key `spouse_id` with "Ta para ma już małżeństwo." when a marriage event with both people exists (either order), else create the event (`type` Marriage, attributes via `MapsEventInput`) and attach both with role Spouse. `UpdateMarriage::handle(Event $marriage, array $date): Event` — updates date/place via `MapsEventInput`, never touches participants or scans.

#### 5. Controller and routes

**File**: `app/Http/Controllers/MarriageController.php` (new), `routes/web.php`

**Intent**: Expose create, update and delete for marriages, following `ParentChildRelationController`.

**Contract**: `Route::resource('marriages', MarriageController::class)->only(['store', 'update', 'destroy'])` inside the auth group; `update`/`destroy` bind `Event $marriage` and respond 404 when the event is not a marriage or belongs to another tree (`EventPolicy::update` for ownership). `store` resolves both people through `$tree->people()->findOrFail`. `destroy` deletes the event (participants and `event_scan` rows cascade; scans untouched). Each redirects `back()` with a toast: "Dodano małżeństwo" / "Zapisano zmiany" / "Usunięto małżeństwo".

#### 6. Person model and delete cascade

**File**: `app/Models/Person.php`, `app/Actions/Persons/DeletePerson.php`

**Intent**: Give the person a marriages relation for eager loading, and make person deletion remove every marriage the person was in.

**Contract**: `Person::marriages(): BelongsToMany` = `events()` constrained to `type = marriage`. `DeletePerson` deletes the person's marriage events before the existing sole-participant cleanup; docblock updated. Scans are never deleted.

#### 7. Spouse candidates

**File**: `app/Actions/Relations/ListRelationCandidates.php`

**Intent**: Let the picker grey out people already married to this person, sharing the existing `relationCandidates` prop.

**Contract**: Each candidate gains `as_spouse_blocked_reason: string|null` — `'Już jest małżonkiem'` when a marriage with the person exists, otherwise `null`. Spouse ids are computed once per call, not per candidate.

#### 8. Preview payload

**File**: `app/Http/Resources/MarriageResource.php` (new), `app/Http/Resources/PersonResource.php`, `app/Http/Controllers/PersonController.php`

**Intent**: Send the person's marriages, each with its sourced event data, raw date parts for the edit form and the other spouse, only on the preview.

**Contract**: `show` eager-loads `marriages.scans` and `marriages.participants`. `PersonResource` adds `marriages` via `whenLoaded('marriages')`, sorted by date (known year first; year, month, day ascending with missing month/day first within a year) then id. Each item: the `EventResource` fields (`id`, `type`, `date`, `place`, `is_confirmed`, `scans`) plus `date_parts: {year, month, day, is_approximate}` and `spouse: {id, display_name, birth_date, death_date}` (the participant other than the viewed person). Persons index payload unchanged.

#### 9. Tests

**File**: `tests/Feature/MarriagesTest.php` (new, `vendor/bin/sail artisan make:test --pest MarriagesTest`)

**Intent**: Cover every rule that could corrupt the shared-event model or leak data, following `ParentChildRelationsTest` style.

**Contract**: See Testing Strategy; `PersonsTest`, `EventScansTest`, `ScansTest`, `ParentChildRelationsTest` must pass unchanged.

### Success Criteria:

#### Automated Verification:

- Marriage feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php`
- Existing person, event-scan, scan and relation tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php tests/Feature/ParentChildRelationsTest.php`
- PHP formatting is clean: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: After completing this phase and all automated verification passes, pause for confirmation before proceeding to Phase 2. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes live in the `## Progress` section.

---

## Phase 2: Marriages on the person view

### Overview

Render marriages on the preview as sourced event cards and add the create, edit and remove dialogs; include marriages in the delete-person confirmation.

### Changes Required:

#### 1. Types and Wayfinder

**File**: `resources/js/types/person.ts`

**Intent**: Type the new payload.

**Contract**: `PersonEvent['type']` gains `'marriage'`; new `Marriage = SourcedPersonEvent & { date_parts: {year, month, day, is_approximate}; spouse: {id, display_name, birth_date, death_date} }`; `PersonPreview.marriages: Marriage[]`; `RelationCandidate.as_spouse_blocked_reason: string | null`. Regenerate Wayfinder actions for `MarriageController`.

#### 2. Reusable pieces

**File**: `resources/js/components/persons/partial-date-fields.tsx`, `resources/js/components/persons/event-card.tsx`, `resources/js/components/persons/relative-picker-dialog.tsx`, `resources/js/components/persons/person-candidate-list.tsx` (new)

**Intent**: Let the marriage UI reuse the date fields, the event source block and the S-06 candidate list without copying them.

**Contract**: `PartialDateFields` accepts `prefix: 'birth' | 'death' | 'marriage'` and an `is_approximate` default. `EventCard` accepts an optional header node (for "Małżeństwo z <link>") and optional extra header actions while keeping the `title` string as the unlink-dialog label; birth/death rendering unchanged. The filterable candidate list (load on open, skeleton, empty state with "Dodaj osobę", name filter, disabled rows with reason) moves into `PersonCandidateList`, used by `RelativePickerDialog` (click = submit, as today) and the marriage dialog (click = select).

#### 3. Marriage dialogs

**File**: `resources/js/components/persons/marriage-form-dialog.tsx`, `resources/js/components/persons/remove-marriage-dialog.tsx` (new)

**Intent**: One dialog creates a marriage (spouse + date + place) or edits one (spouse fixed, date/place prefilled); a confirmation dialog removes it.

**Contract**: Create posts `person_id`, `spouse_id`, `marriage.*` to `MarriageController.store`; the selected spouse is shown with a "Zmień" option back to the list, rows use `as_spouse_blocked_reason`; save is disabled until a spouse is chosen. Edit sends `marriage.*` to `MarriageController.update`. Server errors show under the fields (`spouse_id`, `marriage.*`). Remove text: "Czy na pewno chcesz usunąć małżeństwo „<person>” i „<spouse>”? Zniknie ono u obojga małżonków[; N powiązań ze skanami zostanie odpiętych — skany pozostaną]. Tej operacji nie można cofnąć."

#### 4. Preview section

**File**: `resources/js/components/persons/marriages-section.tsx` (new), `resources/js/pages/persons/show.tsx`

**Intent**: Show the marriages between birth and death.

**Contract**: Heading "Małżeństwa" with "Dodaj małżeństwo"; empty state "Brak znanych małżeństw."; one `EventCard` per marriage (in payload order) titled "Małżeństwo z <spouse link with ur./zm. dates>", with "Edytuj" and "Usuń małżeństwo" actions and the unlink label "Małżeństwo z <spouse>". Order on the page: details, Rodzice, Dzieci, Urodzenie, Małżeństwa, Zgon.

#### 5. Delete-person dialog

**File**: `resources/js/components/persons/delete-person-dialog.tsx`

**Intent**: Keep the destructive confirmation honest about marriages.

**Contract**: `linkCount` also sums marriage scan links; a new phrase "N małżeństwo / małżeństwa / małżeństw" (via `pluralPhrase`) is listed, stating it is removed for the spouse too.

### Success Criteria:

#### Automated Verification:

- Marriage and person tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php`
- TypeScript type check passes: `vendor/bin/sail npm run types:check`
- Lint and format check pass: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`

#### Manual Verification:

- Adding a marriage from one spouse's preview (spouse, partial or "około" date, place) shows it on both previews with a working link to the other spouse; an existing spouse is greyed out with "Już jest małżonkiem"
- Linking one scan on one spouse's preview shows "Potwierdzone aktem" and the thumbnail on both previews, and the scan page lists "Małżeństwo — <each spouse>"
- A person with two marriages shows them ordered by wedding date; editing a date keeps the linked scan
- Removing a marriage (dialog states both spouses and scan links) removes it from both previews while the scan remains; deleting a spouse shows the marriage in the delete dialog and removes it from the other spouse
- Parents/children picker and birth/death cards behave exactly as before

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes live in the `## Progress` section.

---

## Testing Strategy

### Feature Tests (`tests/Feature/MarriagesTest.php`):

- Store creates exactly one marriage event with both people as `spouse` participants and the given partial/approximate date and place; both previews expose it with the other person as `spouse`
- Invalid dates are rejected with the shared messages (impossible calendar date; month without year)
- Self-marriage, a duplicate pair (in both argument orders) and a spouse or person from another tree are rejected; nothing is created
- A person can have several marriages; the preview orders them by date with unknown dates last
- A scan linked to the marriage through `events.scans.store` makes it confirmed on both previews, and `scans.show` `confirms` lists both spouses
- Update changes date and place and keeps linked scans; update/destroy of a birth event via marriage routes and of another tree's marriage return 404
- Destroy removes the event, participants and scan links; the scan still exists; both previews no longer show it
- Deleting one spouse removes the marriage; the other spouse and the scan remain
- `relationCandidates` marks the existing spouse with `Już jest małżonkiem` and leaves others `null`
- Guests are redirected to login on all marriage routes

### Manual Testing Steps:

1. Create A and B; on A's preview add a marriage with B, date "ok. 1880", place "Kraków"; open B's preview and check the card.
2. Link one scan on B's preview; confirm the badge and thumbnail on A's preview and both entries on the scan page.
3. Add a second marriage for A with C (earlier date) and check ordering; try adding B again and see it greyed out.
4. Edit the A–B date; confirm the scan is still linked. Remove A–C via the dialog.
5. Delete B; check the delete dialog wording and that A no longer shows the A–B marriage while the scan still exists.

## Performance Considerations

The preview adds one eager-loaded relation (marriages with participants and scans); candidate reasons reuse the tree-wide person list already loaded by S-06. Personal-scale tree assumed.

## Migration Notes

No schema change. Existing events keep their `type`/`role` values; rollback is a code revert.

## References

- Roadmap slice: `context/foundation/roadmap.md` (S-07)
- PRD: `context/foundation/prd.md` (FR-010, FR-012, FR-014, US-01, Business Logic)
- Similar implementation: `context/archive/2026-09-25-parent-child-relations/plan.md`, `app/Actions/Relations/CreateParentChildRelation.php:18-50`
- Scan linking: `context/archive/2026-09-24-birth-death-confirmed-by-scan/plan.md`, `app/Http/Controllers/EventScanController.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Marriage model and rules

#### Automated

- [x] 1.1 Marriage feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php` — 8ed7607
- [x] 1.2 Existing person, event-scan, scan and relation tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventScansTest.php tests/Feature/ScansTest.php tests/Feature/ParentChildRelationsTest.php` — 8ed7607
- [x] 1.3 PHP formatting is clean: `vendor/bin/sail bin pint --dirty --format agent` — 8ed7607

### Phase 2: Marriages on the person view

#### Automated

- [x] 2.1 Marriage and person tests pass: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php` — 1be7efe
- [x] 2.2 TypeScript type check passes: `vendor/bin/sail npm run types:check` — 1be7efe
- [x] 2.3 Lint and format check pass: `vendor/bin/sail npm run check` — 1be7efe
- [x] 2.4 Production build succeeds: `vendor/bin/sail npm run build` — 1be7efe

#### Manual

- [x] 2.5 Adding a marriage from one spouse's preview (spouse, partial or "około" date, place) shows it on both previews with a working link to the other spouse; an existing spouse is greyed out with "Już jest małżonkiem" — 1be7efe
- [x] 2.6 Linking one scan on one spouse's preview shows "Potwierdzone aktem" and the thumbnail on both previews, and the scan page lists "Małżeństwo — <each spouse>" — 1be7efe
- [x] 2.7 A person with two marriages shows them ordered by wedding date; editing a date keeps the linked scan — 1be7efe
- [x] 2.8 Removing a marriage (dialog states both spouses and scan links) removes it from both previews while the scan remains; deleting a spouse shows the marriage in the delete dialog and removes it from the other spouse — 1be7efe
- [x] 2.9 Parents/children picker and birth/death cards behave exactly as before — 1be7efe
