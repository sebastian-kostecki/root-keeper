# Matching Sources First Implementation Plan

## Overview

Roadmap S-19 (prd-v3 FR-009, absorbing prd-v2 FR-015; GitHub #27). Since S-18 the „Powiąż źródło" picker offers every source of the tree, newest upload first, with akts, gravestones and other sources mixed. This change makes the picker show the akty metrykalne whose type matches the event under „Pasujące akty", and everything else under „Pozostałe źródła". Every source stays selectable. Linking, validation and the „potwierdzone" rule do not change.

## Current State Analysis

**One picker, not told which event it serves.** `SourcePickerDialog` is used for birth, marriage and death alike. Its props are `eventId`, `linkedSourceIds`, `availableSources` and `trigger` (`resources/js/components/sources/source-picker-dialog.tsx:19-24`). It has two callers, both inside the person modal and both holding the event with its type: `FactView` on „Przegląd" for a fact without a source (`overview-tab.tsx:136-145`) and `SourceGroup` on the „Źródła" tab (`sources-tab.tsx:143-152`).

**One shared list.** The candidates come from the shared optional prop `availableSources` (`app/Http/Middleware/HandleInertiaRequests.php:55-57`): every source of the user's tree, `created_at` descending then `id` descending, no limit. It is loaded by a partial reload when the dialog opens and carries no event. `tests/Feature/PersonsTest.php:992-1018` pins that order.

**The order on screen.** The picker renders the list as it arrives (`source-picker-dialog.tsx:115`) in one grid that scrolls inside `max-h-96` (`:114`). A linked source stays at its position, disabled, reading „Już powiązane" (`:124-126`, `:144-145`).

**The tile.** Thumbnail, name, short kind badge, then „Już powiązane" or the upload date (`:130-149`). The payload already carries `record_type`, `record_type_label` and `year` (`resources/js/types/source.ts:14-17`), but the tile shows neither.

**The data.** `EventType` values are `birth`, `death`, `marriage`; `SourceRecordType` values are `birth`, `marriage`, `death`, `other`. Only an akt metrykalny carries a type, enforced in validation and in `UpdateSource`, not in the schema. The type is nullable and was never backfilled, so every scan migrated in S-16 is an akt without a type.

**Design-system charges (the contract for this change).**

| Category | Charge | Where | User impact |
| --- | --- | --- | --- |
| Accidental architecture | The picker is opened for one event but is not told which, so it cannot order by it | `source-picker-dialog.tsx:19-24`; callers `overview-tab.tsx:136`, `sources-tab.tsx:143` | The akt of this very event sits wherever its upload date puts it, among gravestones and other akts |
| Accidental architecture | The same source shows „typ · rok" on the „Źródła" card but not on the picker tile, although the payload carries both | `source-picker-dialog.tsx:134-149` against `pages/sources/index.tsx:72-105` | Two akts can be told apart only by name and upload date, and a matching akt does not say why it matches |
| Missing shared component | The modal's group label is a class string inlined in two files; the picker adds a third copy | `overview-tab.tsx:69`, `family-tab.tsx:149` | None visible. Recorded and left open: the other two copies are outside this view |
| Missing tokens | None. The headings use the existing muted label style and the tile keeps `Badge variant="secondary"` | — | — |

**Tests.** No JS component tests exist; `vp test` runs `resources/js/**/*.test.ts` only (`vite.config.ts:42-44`). Pure frontend logic lives in `resources/js/lib/*.ts` with a `.test.ts` next to it (`source-description.ts`, `persons-index.ts`).

**Screenshot gate.** No automated screenshot test. S-16 to S-18 used a per-change `shots.mjs` against `TreeViewDemoSeeder`. The seeder has one typed akt (birth) and creates everything in one instant, so whatever is appended last is the newest.

## Desired End State

- The picker opened for a birth shows, under the heading „Pasujące akty", every akt metrykalny typed „urodzenia"; for a marriage, those typed „małżeństwa"; for a death, those typed „zgonu".
- Every other source follows under the heading „Pozostałe źródła": akts without a type, akts typed „inny", akts of another event's type, gravestones and other sources.
- Inside each section the order is the server's: newest upload first.
- Every source in both sections can be chosen. A source already linked to the event stays at its place in its section, disabled, reading „Już powiązane".
- When no source matches, the picker shows no headings and looks as it does today. When every source matches, only „Pasujące akty" is shown.
- Each tile shows „typ · rok" under the kind badge when either is set („zgonu · 1932", „urodzenia", „1932"). The upload date appears only when neither is set.
- Linking any source to any event works exactly as before, and every fact keeps its state.

Verification: the unit tests named in Phase 1, the manual checks in Phase 2 and the screenshot verdict in Phase 3.

### Key Discoveries:

- Both callers already hold `event.type`, and the server sends it (`app/Http/Resources/EventResource.php:39`), so the split needs one new prop and no payload change.
- The three event type values equal three of the four record type values; no mapping table is needed.
- Nothing in the schema stops a record type on a non-akt, so the rule checks the kind as well as the type.
- `formatSourceMeta` builds the „typ · rok" line and is unit-tested (`resources/js/lib/source-description.ts:6-13`).
- `showsUploadDate` also hides the date when pochodzenie is set (`source-description.ts:16-21`). The tile does not show pochodzenie, so it cannot use that helper.
- The group label style of the modal is `text-muted-foreground text-xs font-semibold tracking-wide uppercase` (`overview-tab.tsx:69`).
- S-03's impl review removed a client re-sort because it repeated the server's ordering rule. This change does not re-sort: it splits the list and keeps the server's order inside both parts.
- `TreeViewDemoSeeder` is append-only (`database/seeders/TreeViewDemoSeeder.php:19-40`); its sources have no files on disk. Nothing else references the seeder.
- Lint runs with `denyWarnings`: an unused prop or import fails CI.
- The screenshot tooling of S-18 is present on this host: Chromium libraries under `/tmp/chromelibs`, Playwright in the npx cache, the app on port 8000.

## What We're NOT Doing

- No server change. `availableSources` stays one tree-wide list, newest first, with no event parameter, no per-source flag and no new endpoint.
- No rule about which source may confirm which event, and no warning when the type does not match. An akt urodzenia can still confirm a death.
- No change to the „potwierdzone" rule, the status badge or the toast.
- An akt without a type is not treated as matching, and there is no middle „may match" tier. No backfill and no guessing the type from a file name.
- An akt typed „inny" never matches.
- No reordering inside a section: no sort by year or name. Newest upload first stays, as decided in S-03.
- An already-linked source is not moved to the end and not hidden.
- No search, filter or pagination in the picker (parked, v2:FR-006).
- No prompt to fill in a missing type, and no editing or uploading a source from the picker.
- No pochodzenie on the tile. The „Źródła" card and its helpers (`showsUploadDate` included) are not touched.
- No „Powiąż źródło" button on „Przegląd" for a fact that already has a source (S-18 decision).
- No extraction of the group label into a shared component.
- No marriage-typed akt in the seeder: the marriage picker is the „nothing matches" state of the gate.
- No schema change, migration or Pest test change.
- No new design tokens, no new `components/ui` file, no second design system.
- No automated screenshot test and no blind baseline updates.

## Implementation Approach

Contract before pixels, in three phases.

1. **Contract.** The matching rule becomes a pure function with unit tests. The seeder gains the states the gate needs. The phase ends by shooting `before/`, so before and after share the same data.
2. **The view.** The picker learns the event type, renders the two sections and the new tile line.
3. **Gate.** `after/` screenshots, a written verdict, the full suite.

The rule lives in one client-side function because the list is shared by every picker and carries no event. The server keeps owning the order itself.

## Critical Implementation Details

**Baseline before pixels.** `before/` must be shot at the end of Phase 1, after the seeder gains its new sources and before any Phase 2 edit of the picker or its callers.

**The seeded death akt needs an old upload date.** Appended sources are the newest, so a matching akt appended without an explicit `created_at` would already be the first tile in `before/` and the gate would show no change. It is created with an upload date one year back, which puts it last before and first after.

**The date fallback is not `showsUploadDate`.** The tile shows the upload date exactly when `formatSourceMeta(source)` is null. Using `showsUploadDate` would leave an akt that has only pochodzenie with an empty line.

## Phase 1: Contract — the matching rule and the kitchen-sink data

### Overview

The rule that decides which sources match an event exists as a tested function, the demo tree holds every state of the picker, and the baseline is shot. Nothing visible changes.

### Changes Required:

#### 1. The split

**File**: `resources/js/lib/source-matching.ts` (new)

**Intent**: One place that says which sources match an event and divides the picker list by it, so the rule is not written into the component.

**Contract**: `splitSourcesForEvent(sources, eventType)` takes the list as the server sent it and a `PersonEvent['type']`, and returns `{ matching, rest }`. A source matches when its `kind` is `metrical_record` and its `record_type` equals the event type; every other source goes to `rest`. Both arrays keep the order of the input. The function reads only `kind` and `record_type`, knows nothing about linked sources, and does not mutate its input.

#### 2. Unit tests of the split

**File**: `resources/js/lib/source-matching.test.ts` (new)

**Intent**: Pin every decision about what matches.

**Contract**: Same style as `source-description.test.ts` (`vite-plus/test`). Tests:

- an akt typed for the event matches — for a birth, a marriage and a death
- an akt without a type goes to the rest
- an akt typed „inny" goes to the rest
- an akt of another event's type goes to the rest — an akt zgonu for a birth
- a gravestone or other source never matches, even when it carries the event's record type
- both groups keep the order they came in
- when nothing matches, the rest is the whole list in its original order
- an empty list gives two empty groups

#### 3. Kitchen sink data

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Render every picker state from seeded data: a match that was far down the list, a match that is already linked, a typed akt that never matches, and an event with no match.

**Contract**: A new private method taking `$jan`, called in `seedTree()` after `confirmationStates` (`:174`), so earlier creation order and ids hold. The class docblock gains a „Matching states (appended last)" paragraph. Three akty metrykalne, created in the order listed (the screenshot expectations below depend on it):

| Source | Type, rok | Upload date | Linked to | State it provides |
| --- | --- | --- | --- | --- |
| `akt-zgonu-lipnica-1932.jpg`, name „Zgony Lipnica 1932 nr 14" | death, 1932 | one year before seeding (`created_at` and `updated_at`) | nothing | The only match for a death; the last tile today, the first after this change |
| `odpis-aktu-urodzenia-jana.jpg`, name „Odpis aktu urodzenia Jana Kowalskiego" | birth, no rok | default | Jan's birth, as its third source | A matching akt that is already linked; the tile line with a type only |
| `ksiega-ludnosci-lipnica.jpg`, name „Księga ludności Lipnicy" | other („inny"), 1890 | default | nothing | A typed akt that matches no event |

Already seeded and relied on: the akt typed birth with rok 1878 (`opisany-akt-lipnica-1878.jpg`), the gravestone with rok only (`nagrobek-opisany.jpg`), the akt with pochodzenie but no type or rok (`akt-z-dlugim-opisem.jpg`), untyped akts, gravestones and other sources. No akt is typed marriage.

Left untouched: Leon Kowalski's death („Bez źródła"), the unlinked `nagrobek-kowalskich.jpg` and `list-rodzinny.jpg`, and every existing link. Jan's birth keeps reading „Potwierdzone: akt".

#### 4. Baseline screenshots

**File**: `context/changes/matching-sources-first/screenshots/shots.mjs` (new, copied from `context/archive/2026-09-30-fact-confirmed-by-any-source/screenshots/shots.mjs`)

**Intent**: Shoot the picker as it is today from the seeded data, before any frontend edit.

**Contract**: `node shots.mjs <out-dir> [before|after]`, light and dark × 1280×900 and 390×844, person ids read from the rendered list by text. The header keeps the note that Chromium needs `LD_LIBRARY_PATH` on this host. Each shot waits until the skeleton is gone. Both flavours cover the same four states with the same selectors (the trigger copy does not change):

| State | How it is reached | Shows in `before/` | Shows in `after/` |
| --- | --- | --- | --- |
| `picker-leon-death` | „Przegląd" of Leon Kowalski, „Powiąż źródło" in „Zgon"; as opened | Newest first, kinds mixed; the death akt is not in view | „Pasujące akty" with „Zgony Lipnica 1932 nr 14", then „Pozostałe źródła" |
| `picker-leon-death-end` | Same picker, last tile scrolled into view | The last tile is the death akt | The tail of „Pozostałe źródła"; the death akt is not there |
| `picker-jan-birth` | „Źródła" tab of Jan Kowalski, „Powiąż kolejne źródło" in „Urodzenie"; as opened | Newest first; the linked odpis is the second tile | „Pasujące akty": the linked odpis, disabled, then the 1878 akt; „Pozostałe źródła" starts with „Księga ludności Lipnicy" |
| `picker-jan-marriage` | „Źródła" tab of Jan Kowalski, „Powiąż kolejne źródło" in the marriage with Anna; as opened | Newest first | The same order, no headings; tiles with „typ · rok" |

Output of this phase goes to `screenshots/before/`: 16 PNGs named `<theme>-<width>-<state>.png`.

### Success Criteria:

#### Automated Verification:

- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Types, lint and format: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- PHP style: `vendor/bin/sail bin pint --dirty --format agent`
- Static analysis: `vendor/bin/sail composer run types:check`
- Picker, linking and source tests pass unchanged: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventSourcesTest.php tests/Feature/SourcesTest.php`
- Demo seeder runs: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder` (the seeder deletes and recreates its own demo users; no `migrate:fresh`, other local accounts stay)
- Baseline screenshots produced: `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node context/changes/matching-sources-first/screenshots/shots.mjs context/changes/matching-sources-first/screenshots/before before` exits 0 and writes 16 PNGs

#### Manual Verification:

- The `before/` PNGs were opened: they show the real picker with tiles in both themes and both widths — not a login page, a blank frame or the skeleton
- The baseline shows the defect: in `picker-leon-death-end` the last tile is „Zgony Lipnica 1932 nr 14", and it is not in view in `picker-leon-death`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Phase 2: The view — sections and the tile

### Overview

The picker is told which event it serves, shows matching akts first under their own heading, and each tile says what type and year the source has.

### Changes Required:

#### 1. Picker

**File**: `resources/js/components/sources/source-picker-dialog.tsx`

**Intent**: Show the matching akts first, make the boundary visible, and let a tile explain itself.

**Contract**:

- New required prop `eventType: PersonEvent['type']`. The loaded list is divided with `splitSourcesForEvent` at render; the partial reload, the cached list, the select handler and the loading, error and empty states do not change.
- With at least one match: a section headed „Pasujące akty" holding the matching tiles, and, when the rest is not empty, a section headed „Pozostałe źródła" holding the remaining tiles. Each section has its own grid with the columns used today; both sit inside the one scrolling box (`max-h-96`), so the headings scroll with the tiles.
- With no match: one grid and no heading, as today.
- The headings are `h3` with the modal's group label classes (`overview-tab.tsx:69`); each section is labelled by its heading.
- The tile markup exists once in the file and is used by both sections. Its lines, top to bottom: thumbnail; name (truncated, full text in `title`); short kind in `Badge variant="secondary"`; the `formatSourceMeta` line when it is not null; then „Już powiązane" when the source is linked, otherwise the upload date only when the `formatSourceMeta` line is absent.
- A linked source keeps its place in its section and stays disabled; while a link request runs, every tile is disabled, as today.
- Title, description, empty-state copy and the „Zamknij" button are unchanged.

#### 2. Callers

**File**: `resources/js/components/persons/overview-tab.tsx`, `resources/js/components/persons/sources-tab.tsx`

**Intent**: Tell the picker which event it is opened for.

**Contract**: `FactView` (`overview-tab.tsx:136-145`) and `SourceGroup` (`sources-tab.tsx:143-152`) pass `eventType={event.type}`. Nothing else in either file changes.

### Success Criteria:

#### Automated Verification:

- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Types, lint and format: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- Production build: `vendor/bin/sail npm run build`
- Picker, linking and source tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/EventSourcesTest.php tests/Feature/SourcesTest.php`

#### Manual Verification:

- Leon's death picker has both sections: „Pasujące akty" holds exactly „Zgony Lipnica 1932 nr 14" („zgonu · 1932"); „Pozostałe źródła" holds every other source newest first, including the akt urodzenia, the akt typed „inny", untyped akts and gravestones, all selectable
- Jan's birth picker keeps a linked match in place: the first tile of „Pasujące akty" is the odpis, disabled and reading „Już powiązane", followed by the selectable 1878 akt; the two untyped linked akts are disabled inside „Pozostałe źródła"
- Jan and Anna's marriage picker shows no headings and the same order as before
- A source from „Pozostałe źródła" still links: a gravestone and an akt urodzenia each link to a death, the toast reads „Powiązano źródło" and the fact reads „Potwierdzone: nagrobek" or „Potwierdzone: akt"
- Setting an untyped akt's type to „małżeństwa" in the source edit modal moves it under „Pasujące akty" in a marriage picker, and clearing the type moves it back
- The tile line is right: „typ · rok", type only or rok only when set; the upload date only when neither is set; a linked tile reads „Już powiązane"
- On a fresh account with a single akt urodzenia, the birth picker shows only „Pasujące akty" and the death picker shows the plain list without headings
- The picker behaves the same from „Przegląd" and from the „Źródła" tab; at 390 px and in dark mode the headings are readable, nothing overflows and focus is visible on the tiles

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Screenshot gate

### Overview

Every picker state is rendered from the kitchen-sink data, shot, compared with the Phase 1 baseline and judged in writing.

### Changes Required:

#### 1. After shots

**File**: `context/changes/matching-sources-first/screenshots/shots.mjs`

**Intent**: Shoot the same four states the baseline has, after the view changed.

**Contract**: The `after` flavour writes to `screenshots/after/`. The `before` flavour stays as it ran. If a selector has to change for `after`, it changes for that flavour only.

#### 2. Verdict

**File**: `context/changes/matching-sources-first/screenshots/verdict.md` (new)

**Intent**: A written judgement per state instead of a folder of images.

**Contract**: The shape of `context/archive/2026-09-30-fact-confirmed-by-any-source/screenshots/verdict.md`: a table with State, Shots, Expected, Seen for the four states; a before → after line for each; a row per charge from Current State Analysis marked closed or open, with the shared group label marked open by decision and linking a follow-up GitHub issue that names its three copies (`overview-tab.tsx:69`, `family-tab.tsx:149`, the picker). States that the shots cannot show — only matches, the empty picker, the load error — are listed as not shot, with where they were checked instead.

### Success Criteria:

#### Automated Verification:

- Demo seeder runs: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Screenshots produced: `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node context/changes/matching-sources-first/screenshots/shots.mjs context/changes/matching-sources-first/screenshots/after after` exits 0 and writes 16 PNGs
- Full suite green: `vendor/bin/sail artisan test --compact`
- JS tests, types, lint, format and build: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check` and `vendor/bin/sail npm run build`

#### Manual Verification:

- `verdict.md` reviewed: every state has Expected and Seen filled, each is compared with `before/`, and every charge is marked closed or open
- One real akt checked end to end: an own akt is given its type in „Źródła", and the picker of a matching fact shows it first under „Pasujące akty" with its thumbnail (seeded sources have no files)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/source-matching.test.ts`: the eight tests listed in Phase 1 — a match for each of the three events, an untyped akt, an akt typed „inny", an akt of another type, a non-akt carrying a record type, order kept in both groups, nothing matching, an empty list.
- The existing `source-description.test.ts` stays green; `formatSourceMeta` is reused unchanged.

### Integration Tests:

- None new and none modified. The server contract does not change: `tests/Feature/PersonsTest.php:992` keeps pinning the newest-first list, and `tests/Feature/EventSourcesTest.php` keeps covering linking any kind, the foreign source and the foreign event.

### Manual Testing Steps:

1. Seed the demo tree, open Leon Kowalski and link a source to his death: „Zgony Lipnica 1932 nr 14" is alone under „Pasujące akty".
2. Open Jan Kowalski's „Źródła" tab and „Powiąż kolejne źródło" for his birth: the linked odpis is first and disabled, the 1878 akt is next.
3. Open the picker for Jan and Anna's marriage: no headings.
4. Edit an untyped akt, set its type to „małżeństwa", reopen the marriage picker: the akt is under „Pasujące akty". Clear the type: it is back in the plain list.
5. Link a gravestone from „Pozostałe źródła" to Leon's death and check „Potwierdzone: nagrobek"; unlink it.
6. Register a fresh account, add a person with a birth and a death, upload one akt typed „urodzenia": the birth picker shows only „Pasujące akty", the death picker no headings.
7. Repeat steps 1 and 2 at 390 px and in dark mode.

## Performance Considerations

The split is one pass over a list that is already in memory; the request and the query are unchanged. The list is still the whole tree's sources, loaded only when the picker opens (up to about 500, per S-03).

## Migration Notes

- No schema change and no data change. Deploy with `scripts/deploy.sh` as usual.
- Existing akts have no type, so right after the deploy most pickers show no headings. A source starts matching once its type is set in the source edit modal.
- Rollback is code-only: revert the merge on `main` and redeploy. No data is written by this change, so nothing is left behind.

## References

- Roadmap slice: `context/foundation/roadmap.md:303-313` (S-19), index row `:79`; GitHub issue #27
- Requirements: `context/foundation/prd-v3.md:116-117` (FR-009), `context/foundation/prd-v2.md:130-131` (FR-015), `:122-123` (FR-012), `:163` („typ nie blokuje wiązania")
- Deferred to this slice: `context/archive/2026-09-30-fact-confirmed-by-any-source/plan.md:65`, `plan-brief.md:73`
- Record type, made for this slice: `context/archive/2026-09-30-source-description-in-modal/plan.md:113`
- The picker and its callers: `resources/js/components/sources/source-picker-dialog.tsx`, `resources/js/components/persons/overview-tab.tsx:136-145`, `resources/js/components/persons/sources-tab.tsx:143-152`
- The shared list and its test: `app/Http/Middleware/HandleInertiaRequests.php:55-57`, `tests/Feature/PersonsTest.php:992-1018`
- Pure helper pattern: `resources/js/lib/source-description.ts`, `resources/js/lib/source-description.test.ts`
- Tile line on the „Źródła" card: `resources/js/pages/sources/index.tsx:72-105`
- Group label style: `resources/js/components/persons/overview-tab.tsx:69`
- Earlier ordering precedent: `context/archive/2026-09-24-birth-death-confirmed-by-scan/reviews/impl-review.md:121-124`
- Screenshot script and verdict to copy: `context/archive/2026-09-30-fact-confirmed-by-any-source/screenshots/shots.mjs`, `verdict.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — the matching rule and the kitchen-sink data

#### Automated

- [x] 1.1 JS unit tests pass — 65a16e6
- [x] 1.2 Types, lint and format — 65a16e6
- [x] 1.3 PHP style — 65a16e6
- [x] 1.4 Static analysis — 65a16e6
- [x] 1.5 Picker, linking and source tests pass unchanged — 65a16e6
- [x] 1.6 Demo seeder runs — 65a16e6
- [x] 1.7 Baseline screenshots produced — 65a16e6

#### Manual

- [x] 1.8 The `before/` PNGs were opened — 65a16e6
- [x] 1.9 The baseline shows the defect — 65a16e6

### Phase 2: The view — sections and the tile

#### Automated

- [x] 2.1 JS unit tests pass — 63d7d90
- [x] 2.2 Types, lint and format — 63d7d90
- [x] 2.3 Production build — 63d7d90
- [x] 2.4 Picker, linking and source tests still pass — 63d7d90

#### Manual

- [x] 2.5 Leon's death picker has both sections — 63d7d90
- [x] 2.6 Jan's birth picker keeps a linked match in place — 63d7d90
- [x] 2.7 Jan and Anna's marriage picker shows no headings and the same order as before — 63d7d90
- [x] 2.8 A source from „Pozostałe źródła" still links — 63d7d90
- [x] 2.9 Setting an untyped akt's type to „małżeństwa" moves it under „Pasujące akty" in a marriage picker, and clearing the type moves it back — 63d7d90
- [x] 2.10 The tile line is right — 63d7d90
- [x] 2.11 On a fresh account with a single akt urodzenia, the birth picker shows only „Pasujące akty" and the death picker shows the plain list without headings — 63d7d90
- [x] 2.12 The picker behaves the same from „Przegląd" and from the „Źródła" tab; 390 px, dark mode and focus are fine — 63d7d90

### Phase 3: Screenshot gate

#### Automated

- [x] 3.1 Demo seeder runs — 421964f
- [x] 3.2 Screenshots produced — 421964f
- [x] 3.3 Full suite green — 421964f
- [x] 3.4 JS tests, types, lint, format and build — 421964f

#### Manual

- [x] 3.5 `verdict.md` reviewed — 421964f
- [x] 3.6 One real akt checked end to end — 421964f
