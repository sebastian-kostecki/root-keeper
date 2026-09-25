# Person Edit and Delete — Plan Brief

> Full plan: `context/changes/person-edit-and-delete/plan.md`

## What & Why

Roadmap slice S-05 (FR-006, FR-007): the user can correct a person's data and delete a person after explicit confirmation. The app has no trash or undo (PRD Non-Goals), so an edit must never silently drop a certificate link, and a deletion must say exactly what goes away.

## Starting Point

Persons can only be listed, created and previewed. Birth and death are `events` rows joined to people through `event_participants`, with scans linked per event (`event_scan`). Deleting a `people` row today would cascade only the participant rows and leave birth/death events and their scan links orphaned. The create form cannot be prefilled, and `PersonResource` only exposes formatted dates.

## Desired End State

The preview has "Edytuj" and "Usuń". Edit opens the familiar person form prefilled, with a hint under events already confirmed by a certificate; saving updates events in place so scan links survive. "Usuń" opens a dialog naming the person and the number of certificate links that will go (scans stay in the library); confirming removes the person and returns to the list. No other tree's person can be edited or deleted.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Deletion scope | Delete events where the person is the sole participant (with their scan links); detach from shared events; never touch scans | No orphans today, and it stays correct once S-07 adds one marriage event shared by two spouses. |
| Unchecking "Zmarły/a" with a death scan | Reject with "Najpierw odepnij skany od zgonu na podglądzie osoby."; without scans delete the death event | A form save never silently removes a source link — same principle as FR-015. |
| Delete confirmation | Dialog with consequences (name, birth/death, pluralised link count, scans stay, irreversible) | Matches the existing `UnlinkScanDialog` pattern and makes the scope explicit. |
| Editing a confirmed fact | Static muted hint "Potwierdzone aktem — upewnij się, że zmiana zgadza się ze skanem." | Reminds the user of the responsibility the PRD assigns them (FR-006) at minimal cost. |
| Validation reuse | `PersonUpdateRequest extends PersonStoreRequest` plus the scan guard | One set of partial-date rules and Polish messages. |
| Event writing | Shared date → event mapping used by `CreatePerson` and new `UpdatePerson`; events updated in place | Recreating events would drop scan links; one mapping prevents drift. |
| Form UI | Extract `PersonFormFields` from the create page, add defaults to `PartialDateFields` | Create and edit render identical markup. |
| Phasing | Vertical: edit first, then delete | Each phase is clickable and verifiable on its own. |

## Scope

**In scope:**
- `edit` / `update` / `destroy` routes, `PersonPolicy::update` / `delete` (404 for other trees)
- `UpdatePerson`, `DeletePerson` actions, `PersonUpdateRequest`, `PersonFormResource`
- `persons/edit` page, shared form fields, "Edytuj" / "Usuń" on the preview, `DeletePersonDialog`
- Feature tests in `tests/Feature/PersonsTest.php`

**Out of scope:**
- Trash, soft delete, undo; fact-vs-scan disagreement detection
- Deleting scans (S-08); relation-specific cleanup (S-06/S-07)
- Delete from the persons list; typed-name confirmation; any restyle or new tokens

## Architecture / Approach

Controller → Form Request (auth + validation) → action in a DB transaction, as in `CreatePerson` / `EventScanController`. Update touches existing birth/death rows by id; only an unchecked, scan-less death is deleted. Delete computes sole-participant events **before** removing the person, deletes them (FK cascades clear `event_scan` and `event_participants`), then deletes the person. The frontend reuses shadcn `Dialog`, `Form` with Wayfinder `update.form()`, and `router.delete`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Edit a person | Prefilled edit page, update with scan guard and hint, "Edytuj" on preview | Recreating events instead of updating them would drop scan links |
| 2. Delete a person | Confirmation dialog, sole-participant deletion, redirect with toast | Wrong delete order makes shared events undetectable |

**Prerequisites:** S-01 and S-03 merged (done); Sail running.
**Estimated effort:** ~1–2 sessions across 2 phases.

## Open Risks & Assumptions

- A scan linked to the death event between validation and save (another tab) would be removed with the death event; accepted as negligible for a single-user MVP.
- The sole-participant rule assumes S-06/S-07 keep modelling shared facts as events with several participants; if parent–child relations use a separate table, S-06 must define its own deletion cleanup.

## Success Criteria (Summary)

- A user fixes any field of a person, including partial dates, without losing any certificate link.
- A user deletes a person after reading exactly what will be lost, and their scans remain available.
- Feature tests prove tree isolation and the deletion boundary.
