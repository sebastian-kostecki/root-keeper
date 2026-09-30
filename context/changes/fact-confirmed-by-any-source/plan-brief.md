# Fact Confirmed by Any Source — Plan Brief

> Full plan: `context/changes/fact-confirmed-by-any-source/plan.md`

## What & Why

Roadmap S-18 (prd-v3 FR-008, US-02). A source has had a kind since S-16, but only an akt metrykalny can be linked to a fact, so the gravestone photos the author brings from the cemetery still confirm nothing. This change lets a source of any kind confirm a birth, marriage or death, and shows at the fact which kinds confirm it, so the weight of the evidence is the author's own call.

## Starting Point

The akt-only rule sits in two places by design: a validation rule on linking and a filter on the picker list. „Potwierdzone" is not stored; it is derived from the link rows. The badge says „Potwierdzone aktem" on four surfaces, and on „Przegląd" a fact names its first source as plain text that cannot be opened.

## Desired End State

- The picker offers every source of the tree, each with its kind.
- A fact with any linked source is „potwierdzone", and the badge names the kinds: „Potwierdzone: nagrobek", „Potwierdzone: akt, nagrobek".
- On „Przegląd" each linked source is a link that opens its page with the zoom viewer.
- Every fact confirmed before the deploy is confirmed after it.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| State | One „potwierdzone" for every kind, same badge look | The author decides the weight of the evidence by seeing the kind | PRD v3 FR-008, Non-Goals |
| Which kind confirms what | Any kind confirms any of the three events | The PRD sets no pairing rule, and S-19 must not block linking | PRD v3 |
| Badge with several kinds | Every distinct kind, fixed order akt, nagrobek, inne; two akts read „akt" | Shows all the evidence without ranking it or depending on link order | Plan (user) |
| Persons list ✓ | Same ✓ for any kind; kind only in the person modal | One state, and the list query loads no source data today | Plan (user) |
| Opening a source from the fact | Links on „Przegląd" to the full source page | Evidence opens where the badge is read, with no modal on a modal | Plan (user) |
| Where order and labels live | Server sends the ordered short labels; the badge only joins them | The „fixed order" rule is one tested place, not logic in four components | Plan |
| Short labels | akt, nagrobek, inne | The long labels do not fit the badge or a picker card at 390 px | Plan |
| Picker order | Unchanged: newest first, all kinds mixed, kind shown on each card | Ordering by matching type is S-19 | Roadmap |
| Copy | „Powiąż źródło", „Powiązano źródło", „Potwierdzone źródłem — …" | The linking flow may no longer say „akt" for a gravestone | Plan (approved with the outline) |
| Design system | No new tokens or components; existing `Badge` variants | The kind is a label on one state, not a new visual concept | Plan |

## Scope

**In scope:**

- Removing the akt-only rule on linking and the picker filter.
- Short kind labels and the ordered confirming kinds in the person preview.
- The badge on „Przegląd", „Źródła" and „Rodzina"; source links on „Przegląd".
- The kind on „Źródła" rows and picker cards; the linking copy, edit hint, toast and screen-reader text.
- Flipped and new Pest tests.
- Kitchen-sink states, `before/` and `after/` screenshots, a written verdict.

**Out of scope:**

- A weaker state, a different mark or a ranking by kind.
- The kind on the persons list or table.
- A zoom viewer over the person modal.
- Picker ordering, grouping or filtering (S-19).
- Adding a source from the person modal or the picker.
- Schema changes, new tokens, an automated screenshot test.

## Architecture / Approach

Contract before pixels. Phase 1 removes the gate in its two places and extends the payload: each source carries a short kind label, and each event carries the list of kinds that confirm it, ordered by the enum and not by link time. The baseline is shot then, on the new seeded states. Phase 2 changes one badge component used on four surfaces, turns the fact's source text into links, and replaces the akt wording. Phase 3 shoots the same states again and judges them in writing.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract — any kind links, kinds in the payload | Gate removed, short labels, `confirmed_kinds`, Pest tests, seeder states, `before/` shots | The baseline is skipped or shot after a frontend edit and cannot be recovered |
| 2. The view — kind at the fact, links, picker and copy | Badge with kinds on four surfaces, links on „Przegląd", kind on rows and picker cards, new copy | A leftover „akt" string, or the longest badge breaking a card at 390 px |
| 3. Screenshot gate | `after/` shots in both themes and widths, `verdict.md`, full suite | Seeded sources have no files, so zoom needs one real upload |

**Prerequisites:** S-16 and S-24 are done; S-17 is done, so a gravestone can be added in a modal. Sail is running. Playwright is available in the npx cache for the screenshot script, and the Chromium libraries are present in `/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu` (passed as `LD_LIBRARY_PATH`; the directory is under `/tmp`, so check it after a restart).
**Estimated effort:** about 1–2 sessions across 3 phases. Phase 2 is the largest; Phase 1 is small but must end with the baseline.

## Open Risks & Assumptions

- „Potwierdzone: inne" is the least natural label; it was accepted with the outline and is easy to change in one enum method.
- Until S-19 the picker mixes akts, gravestones and other sources newest first, so finding the right akt among many photos takes longer.
- On the persons list a gravestone-backed date looks the same as an akt-backed one. Accepted.
- After a code rollback, links made to non-akt sources stay and read „Potwierdzone aktem" again.

## Success Criteria (Summary)

- A gravestone photo links to a death, the death reads „Potwierdzone: nagrobek", and the photo opens enlarged from the fact.
- A fact with an akt and a gravestone reads „Potwierdzone: akt, nagrobek" whichever was linked first.
- No fact changes its confirmed state because of the deploy.
