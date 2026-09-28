# Existing or New Spouse from the Tree — Plan Brief

> Full plan: `context/changes/tree-spouse-existing-or-new/plan.md`

## What & Why

Roadmap slice S-27 (v4:FR-006). The tree takes over adding marriages. The centre person gets a spouse from the tree (an existing person or a new one), and shown pairs without a marriage can be married with „Połącz jako małżeństwo". Once S-29 makes the preview read-only, the tree must be the only place to add a spouse, including a remarriage.

## Starting Point

A marriage is one `Marriage` event with two spouses. Today it is added only from the preview (`MarriageFormDialog` → `marriages.store`), whose only rules are no duplicate pair and not oneself. The tree already has the S-25/S-26 kit: the `PickOrCreatePersonDialog` shell, the dashed `UnknownPersonCard`, the `personCandidates` prop with sex, and the atomic create-and-link pattern. The partner slot is empty only when the centre has no union at all. The union switcher is hidden with ≤1 union.

## Desired End State

On the tree, „Dodaj małżonka" appears in the empty partner slot, or at the end of the union switcher when the centre already has a union. It opens one dialog: a list of possible spouses (same-sex and direct-line people hidden), or „Nowa osoba" with the opposite sex locked. After saving, the tree shows the new union with „Dodano małżonka". Unmarried shown parent pairs, and a „bez ślubu" centre couple, get „Połącz jako małżeństwo", which works after a confirmation. All marriages created from the tree have no date; the date, place and confirmation are filled in the preview.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Entry point | Card in the empty partner slot + „Dodaj małżonka" button ending the union switcher (switcher shown from one union) | Covers the first marriage and remarriage, which would otherwise be impossible after S-29. |
| After saving | Redirect to `tree.show(person)` with `?spouse=<new>` instead of `back()` | `back()` would keep the old union selected after a remarriage. |
| „Połącz" scope | Both parent pairs + the centre couple when „bez ślubu"; not grandparents | FR-006 plus the obvious co-parent case, without crowding narrow grandparent cards. |
| Filtering | Silently hide the centre, current spouses and the direct line; hide same known sex with a count hint; unknown sex hides nobody | Halves the list and matches the parent slot's sex handling. |
| New spouse sex | Locked to the opposite of the centre's known sex; free when unknown; no surname prefill | Consistent with the list filter; maiden names differ. |
| Server rules | Unchanged: sex and kinship hiding is UI-only | PRD v4: relation rules stay as they are. |
| Date & confirmation | No date/place from the tree; „Połącz" asks for confirmation; picking a spouse is itself the confirmation | Roadmap outcome; guards the one-click action against an unintended marriage. |
| „Połącz" endpoint | Reuse `marriages.store` unchanged | It already creates an undated marriage and redirects back with a toast. |

## Scope

**In scope:**
- `add_spouse.excluded_ids` and `unmarried_parents` on the family sheet.
- `PersonSpouseController` `attach` / `store` with a redirect to the new union; `CreateSpouseForPerson`.
- `AddSpouseDialog` (slot + switcher variants), `MarryCoupleDialog`, `UnionSwitch` action and single-union display.
- Feature tests in `MarriagesTest` and `TreeTest`; visual gate screenshots.

**Out of scope:**
- Server-side sex/kinship rules.
- A date step in the tree.
- A spouse slot for non-centre people.
- „Połącz" for grandparents.
- Replacing „Nieznany partner".
- Linking children to the new spouse.
- Preview changes (S-29) and relation removal (S-28).
- New tokens, `ui/*` edits, screenshot-test infrastructure.

## Architecture / Approach

The server computes what to hide (`excluded_ids`) and which parent pairs are unmarried from the graph and marriages the sheet already loads, plus one constant query. The client applies the sex filter from `gender` data it already has. Picking posts `persons/{person}/spouses/attach`, which calls `CreateMarriage` with no date. „Nowa osoba" posts `persons/{person}/spouses`, which runs `CreatePerson` + `CreateMarriage` in one transaction. Both redirect to the tree with the new union selected. „Połącz" posts the existing `marriages.store` and returns `back()`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Existing spouse | Exclusions, attach endpoint + redirect, dialog list view in the slot and the switcher | Switcher layout with one union |
| 2. New spouse | Atomic create-and-marry endpoint, „Nowa osoba" with locked sex | Person rolled back on a marriage failure |
| 3. „Połącz jako małżeństwo" | `unmarried_parents`, confirmation dialog, buttons on parent connectors and under a „bez ślubu" couple | Button placement on the thin connectors (visual gate) |

**Prerequisites:** S-24 (preview modal) and S-07 (marriages) are done; S-25/S-26 shell and card are merged.
**Estimated effort:** ~2–3 sessions across 3 phases.

## Open Risks & Assumptions

- A same-sex marriage cannot be recorded from the tree; the server would still accept one. This is accepted as UI-only filtering.
- The union chip reads „ślub ?" until the date is filled in the preview. Accepted as a visible reminder.
- S-29 must keep marriage date/place editing in the preview (FR-008), because the tree creates marriages without them.

## Success Criteria (Summary)

- From the tree alone, the user gives a person a first and a second spouse (existing or new) and immediately sees the new union.
- Shown unmarried parents and a „bez ślubu" couple are married after an explicit confirmation, and nothing is married by accident.
- Parent slots, the child card and the preview's marriage editing work as before.
