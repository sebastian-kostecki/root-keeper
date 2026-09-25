# Marriage with Shared Source — Plan Brief

> Full plan: `context/changes/marriage-with-shared-source/plan.md`

## What & Why

Roadmap slice S-07 (FR-010, FR-012, FR-014, US-01): the user can join two people in marriage with a wedding date and place (a person may have several marriages), link the marriage certificate once and see it as the source on both spouses' previews, correct the date and place, and remove the marriage after explicit confirmation. The PRD calls a marriage "one event of both spouses" — this slice proves the S-01/S-03 event model holds for it, and S-09 (graphical tree) needs it.

## Starting Point

Events already link to people through `event_participants` (with a `role`) and to scans through `event_scan`, but only birth and death exist, each with one `subject`. Scan linking, the scan page's "Potwierdza" list and S-08's delete-impact list all work per event, and S-06 left a ready pattern for relations (locking action, candidate reasons, picker, remove dialog).

## Desired End State

The preview shows a "Małżeństwa" section between birth and death: one card per marriage ("Małżeństwo z <spouse>"), ordered by date, with the same date/place/source status/scans block as birth and death, plus "Edytuj" and "Usuń małżeństwo". "Dodaj małżeństwo" picks the spouse and takes date and place in one dialog. A scan linked on either spouse confirms the marriage on both. Removing the marriage or deleting a spouse removes it for both; scans always stay.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Data model | One `Event{type: marriage}` with two `spouse` participants; no new table | Scan linking, scan page and delete-impact work unchanged — literally the PRD rule. |
| Spouse deleted as person | Marriage is deleted too (dialog counts it); scans stay | No half-marriage state the UI and S-09 would have to handle. |
| Same pair twice | Rejected ("Ta para ma już małżeństwo"), greyed out in picker | Prevents duplicates that would split sources across identical events. |
| Editing | "Edytuj" dialog updates date/place in place | Correcting a typo must not drop the linked certificate. |
| Add flow | One dialog: spouse picker + date + place | Matches FR-010 wording in one step, reusing the S-06 candidate list. |
| Layout | Card per marriage reusing `EventCard`, between Urodzenie and Zgon | A marriage is a sourced event like birth/death; life order on the page. |
| Ordering | Wedding date (unknown last), then id | Same partial-date sort as relatives in S-06. |
| Blocked spouses | Only "Już jest małżonkiem"; self excluded; no plausibility checks | PRD non-goal of policing fact consistency, as in S-06. |
| Date validation | Extract into `app/Concerns/PartialDateValidationRules` shared with person requests | One source for partial/approximate date rules instead of copies. |
| Testing | Pest feature tests for every rule, cascade, cross-tree 404 and shared-source visibility; UI manual | Matches existing test style; covers everything that could corrupt the model. |

## Scope

**In scope:**
- `EventType::Marriage`, `ParticipantRole::Spouse`, shared date-validation trait
- `MarriageController` store/update/destroy with atomic pair rules; `DeletePerson` cascade
- `as_spouse_blocked_reason` in candidates; `marriages` on the preview payload
- Marriages section, create/edit dialog, remove dialog, delete-person dialog wording

**Out of scope:**
- Repeated marriages of one pair, unknown/deleted spouse, changing the spouse
- Divorce, marriage type, witnesses, bride/groom roles, plausibility checks
- Create-and-link a new person, persons index or edit form changes, S-09 tree, browser tests, undo

## Architecture / Approach

`MarriageStoreRequest` validates both people (own tree, not the same person) and `marriage.*` date fields → `CreateMarriage` locks both people, rejects an existing pair, creates the event and attaches both as spouses in one transaction. Update/destroy bind `marriages/{marriage}` to `Event` and 404 on non-marriage or foreign events. Scans keep linking through the existing `events/{event}/scans` endpoints. The preview loads `marriages.scans` + `marriages.participants` and renders each through `EventCard`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Marriage model and rules | Enums, validation trait, endpoints, actions, cascade, candidate reason, payload, tests | Duplicate pair under concurrent requests — no schema guard, mitigated by row locks in the action |
| 2. Marriages on the person view | Section, create/edit/remove dialogs, shared candidate list, delete-person wording | Refactoring `EventCard`/picker regresses birth/death or parent/child UI — covered by manual check |

**Prerequisites:** S-03 (done); S-06 patterns in place; Sail running.
**Estimated effort:** ~2 sessions across 2 phases.

## Open Risks & Assumptions

- Pair uniqueness relies on the action's locks, not a DB constraint; acceptable for a single-user tree.
- Deleting a spouse also loses the survivor's wedding date/place — accepted in exchange for no half-marriage state.
- Approximate wedding dates sort as exact.

## Success Criteria (Summary)

- The author records each marriage once, with date and place, and sees it on both spouses' previews.
- One linked certificate confirms the marriage on both spouses without being added twice.
- No duplicate or self-marriage, no cross-user leak, and every removal is explicitly confirmed.
