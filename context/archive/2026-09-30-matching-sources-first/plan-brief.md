# Matching Sources First — Plan Brief

> Full plan: `context/changes/matching-sources-first/plan.md`

## What & Why

Roadmap S-19 (prd-v3 FR-009, absorbing prd-v2 FR-015; GitHub #27). When a source is linked to a birth, marriage or death, the picker shows the akty metrykalne typed for that event first, and every other source after them. Since S-18 the picker mixes akts, gravestones and other sources by upload date, so finding the right akt among many photos takes scrolling.

## Starting Point

One picker (`source-picker-dialog.tsx`) serves all three events from one shared, tree-wide list ordered newest upload first. It is not told which event it was opened for, and its tile shows the name, the kind and the upload date — not the akt's type or year, although both are already in the payload.

## Desired End State

The picker opened for a death shows akts typed „zgonu" under „Pasujące akty", then everything else under „Pozostałe źródła"; likewise for births and marriages. Every source stays selectable, and an already-linked one stays in place, disabled. Each tile shows „typ · rok" when set. With no matching akt the picker has no headings and looks as it does today.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| What matches | Only an akt metrykalny whose type equals the event type | The literal reading of FR-009; the first section always means „an akt of this event" |
| Akt without a type, typed „inny" or of another type | Goes to the rest, with gravestones and other sources | One rule, two groups; old scans start matching once their type is set |
| How the boundary shows | Two labelled sections: „Pasujące akty" and „Pozostałe źródła" | You see why the order is what it is and where the matches end |
| No matches | No headings; the list looks as today | A heading over nothing would only add noise |
| Tile content | Adds „typ · rok"; upload date only when neither is set | Tells two akts apart and explains why a tile matches |
| Already-linked source | Stays at its place in its section, disabled | No second ordering rule; a tile never moves because of linking |
| Order inside a section | Unchanged: newest upload first | Decided in S-03; this slice only adds the grouping |
| Where the rule lives | A pure client-side function in `resources/js/lib/` | The list is shared by every picker and carries no event; the server and its order test stay untouched |
| Date fallback predicate | „no typ · rok line", not the list card's `showsUploadDate` | That helper also depends on pochodzenie, which the tile does not show |
| Group label component | Reuse the existing label classes inline; no extraction | The other two copies are in other views, outside this slice |

## Scope

**In scope:**

- A tested split function: matching akts and the rest, server order kept in both
- The picker: event type prop, two sections, the „typ · rok" tile line
- The two callers passing the event type
- Three appended demo-seeder sources and a before/after screenshot gate with a written verdict

**Out of scope:**

- Any server change: no event parameter, no per-source flag, no endpoint, no migration, no Pest change
- Blocking or warning on a type mismatch; any change to „potwierdzone"
- Treating untyped akts as matching, a middle tier, or backfilling types
- Sorting inside a section; moving or hiding linked sources
- Search, filter, upload or edit inside the picker
- Pochodzenie on the tile; changes to the „Źródła" card
- A shared group-label component; new tokens or `components/ui` files
- An automated screenshot test

## Architecture / Approach

The server keeps sending one newest-first list. Both callers (`overview-tab.tsx`, `sources-tab.tsx`) pass `event.type` to the picker. The picker calls `splitSourcesForEvent(sources, eventType)`, which returns `{ matching, rest }` without reordering either part: a source matches when it is an akt metrykalny and its record type equals the event type. The picker renders each non-empty part as a section inside the existing scrolling box, using one tile for both.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract — the matching rule and the kitchen-sink data | The tested split function, three seeded akts, `before/` screenshots; nothing visible changes | The seeded death akt needs an old upload date, or the baseline shows no defect |
| 2. The view — sections and the tile | The picker with „Pasujące akty" / „Pozostałe źródła" and the „typ · rok" line | Headings take room in a box that shows two tiles at 390 px |
| 3. Screenshot gate | `after/` shots of four picker states, a written verdict, the full suite | States the seeded tree cannot show (only matches, empty, error) are checked by hand, not shot |

**Prerequisites:** S-17 and S-18 done (both are). Sail running with the app on port 8000; the screenshot tooling from S-18 on this host (`/tmp/chromelibs`, Playwright in the npx cache).
**Estimated effort:** about one session across three phases; roughly six files changed or added outside the screenshots.

## Open Risks & Assumptions

- Every scan migrated in S-16 is an akt without a type. Right after the deploy most pickers show no headings; the feature starts helping as types are filled in.
- The matching rule is tested in TypeScript, not in Pest. This is a step away from the „server owns the order" note of S-18; the newest-first order itself is still only on the server.
- There are no component tests, so the heading rule (which section appears when) is checked by the screenshots and by hand.
- The seeder has no marriage-typed akt on purpose; marriage matching is covered by a unit test and one manual step.

## Success Criteria (Summary)

- Linking a source to a birth, marriage or death shows the akts typed for that event first, under their own heading.
- Every other source is still there and can be linked; no fact changes its „potwierdzone" state.
- A tile tells you the akt's type and year, so the right one can be picked without opening it.
