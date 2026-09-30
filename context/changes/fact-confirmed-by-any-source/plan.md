# Fact Confirmed by Any Source Implementation Plan

## Overview

Roadmap S-18 (prd-v3 FR-008, US-02). Since S-16 a source has a kind, but only an akt metrykalny can be linked to a fact, so a gravestone photo still confirms nothing. This change removes that gate: a source of any kind confirms a birth, marriage or death. The state stays one „potwierdzone", and the fact shows which kinds confirm it — „Potwierdzone: nagrobek", or „Potwierdzone: akt, nagrobek" when both are linked. Each linked source becomes openable from the fact itself.

## Current State Analysis

**The rule.** S-16 put the akt-only rule in exactly two places so that this change could remove it:

- the closure rule in `app/Http/Requests/EventSourceStoreRequest.php:37-46` (message „Do wydarzenia można na razie powiązać tylko akt metrykalny.");
- the picker's list filter in `app/Http/Middleware/HandleInertiaRequests.php:57`.

**The state.** „Potwierdzone" is never stored. It is derived from the link rows: `EventResource.php:46-51` reads the loaded `sources` relation in the person preview, or `sources_exists` on the persons list (`PersonController.php:39`), which loads no source data. `PersonFormResource.php:67` derives the same flag for the edit hint.

**The kind at the fact.** Each linked source already carries `kind` in the preview payload (`EventResource.php:54`), but nothing renders it. `EventSourceStatus` hard-codes „Potwierdzone aktem" (`resources/js/components/persons/event-source-status.tsx:13`) and is used on four surfaces: the „Przegląd" tab for birth, each marriage and death (`overview-tab.tsx:264`, `:306`, `:340`), the „Źródła" tab group header (`sources-tab.tsx:61`) and the „Rodzina" tab marriage row (`family-tab.tsx:215`).

**Opening a source.** Only the „Źródła" tab has „Otwórz", which goes to the full source page with the zoom viewer (`sources-tab.tsx:92-110`). On „Przegląd" the fact shows plain text — a count and the name of the first-linked source (`overview-tab.tsx:101-111`).

**Design-system charges (the contract for this change).**

| Category | Charge | Where | User impact |
| --- | --- | --- | --- |
| Accidental architecture | The status badge hard-codes the kind („aktem") although the kind is in the payload | `event-source-status.tsx:13`, four surfaces | A death confirmed by a gravestone would claim an akt |
| Accidental architecture | „Akt" is baked into the linking copy | picker title, description and empty state (`source-picker-dialog.tsx:82-84`, `:103`, `:141`), buttons (`overview-tab.tsx:119`, `sources-tab.tsx:148`), empty hint (`sources-tab.tsx:137`), edit hint (`person-section-fields.tsx:21`), list ✓ (`person-list-parts.tsx:12`), toast (`EventSourceController.php:26`) | The picker offers gravestones under the title „Powiąż akt" |
| Accidental architecture | The fact on „Przegląd" names only the first-linked source, as text | `overview-tab.tsx:110` | The evidence cannot be opened where the badge is read |
| Missing shared component | None. The kind next to a source name is the existing `Badge` (`variant="secondary"`, as on `pages/sources/index.tsx:89`) | — | — |
| Missing tokens | None. One state means one badge variant for every kind | — | — |

**Tests that assert the old rule.** `tests/Feature/EventSourcesTest.php:59-68` (non-akt is rejected), `:31` (toast „Powiązano akt"), `tests/Feature/PersonsTest.php:905-932` (picker list holds only akts).

**Screenshot gate.** No automated screenshot test. S-16 and S-17 used a per-change `shots.mjs` against `TreeViewDemoSeeder`. No seeded fact is confirmed by a non-akt source today.

## Desired End State

- In the picker, every source of the user's tree can be chosen for a birth, marriage or death, whatever its kind; each card shows its kind.
- A fact linked to at least one source is „potwierdzone". The badge names every distinct kind that confirms it, in the fixed order akt, nagrobek, inne: „Potwierdzone: nagrobek", „Potwierdzone: akt, nagrobek", „Potwierdzone: akt, nagrobek, inne". Two akts read „Potwierdzone: akt". The order never depends on which source was linked first.
- The badge has the same look for every kind. A fact without a source still reads „Bez źródła".
- On „Przegląd", a fact lists each linked source as a link (name and kind) that opens the full source page with the zoom viewer, the same target as „Otwórz" on the „Źródła" tab.
- On the persons list and table, the ✓ after a date is the same for any kind; the kind is visible only in the person modal.
- No copy in the linking flow says „akt" for something that may be a gravestone.
- Every fact that was confirmed before the deploy is confirmed after it, and every unconfirmed fact stays unconfirmed.

Verification: the Pest tests named in Phase 1, the copy check in Phase 2 and the screenshot verdict in Phase 3.

### Key Discoveries:

- The state is derived, so removing the gate needs no migration and cannot change an existing fact (`EventResource.php:46-51`).
- `Event::sources()` orders by link time (`app/Models/Event.php:69-75`). The badge must not inherit that order; it follows the enum's case order.
- Labels are server-owned: `SourceKind::label()` reaches the client as `kind_label` (`SourceResource.php:46`). The long labels („akt metrykalny", „zdjęcie nagrobka", „inne") are too long for the badge and for a picker card at 390 px.
- `MarriageResource.php:44` spreads `EventResource`, so a new event field reaches marriages with no extra code; its docblock still has to follow.
- `SourcesTest.php:45-47` is the resource-shape contract: its scoped `hasAll([...])` fails on an unexpected key.
- The section card header wraps (`section-card.tsx:107`), so a long badge drops under the title instead of overflowing.
- The „Rodzina" marriage row does not wrap: the badge is in a 224 px text column at 390 px (`family-tab.tsx:94-104`) and `Badge` is `whitespace-nowrap shrink-0` (`ui/badge.tsx:8`). The longest badge measures 239 px, so the badge itself has to wrap.
- Links out of the modal go through `useGuardedClick` so unsaved section edits are not lost (`section-editor.tsx:229-250`, used at `sources-tab.tsx:97-105`).
- `TreeViewDemoSeeder` is append-only: earlier scripts rely on creation order (`database/seeders/TreeViewDemoSeeder.php:23-33`). Its sources have no files on disk.
- Lint runs with `denyWarnings`: an unused prop or import fails CI.

## What We're NOT Doing

- No weaker or different state for a gravestone, and no different badge variant per kind (prd-v3 Non-Goals).
- No ranking of kinds and no „strongest kind only" badge.
- No kind on the persons list or table: the ✓ is the same for any kind, and the list query stays as it is.
- No zoom viewer on top of the person modal; a source opens on its own page.
- No ordering, grouping or filtering of the picker by kind or by matching type (S-19). It stays newest first, all kinds mixed.
- No rule about which kind may confirm which event: a gravestone may confirm a birth, an akt zgonu may confirm a marriage.
- No adding or uploading a source from the person modal or the picker. The empty picker still links to „Źródła".
- No „Powiąż źródło" button on „Przegląd" for a fact that already has a source; linking another stays on the „Źródła" tab.
- No change to the unlink and delete dialogs or to the „Potwierdza" card on the source page; their copy is already kind-neutral.
- No change to the long kind labels used in „Źródła".
- No schema change, migration or backfill.
- No new design tokens, no new `components/ui` file, no second design system.
- No automated screenshot test and no blind baseline updates.

## Implementation Approach

Contract before pixels, in three phases.

1. **Contract.** The gate goes in its two places, the payload gains the short kind label and the ordered confirming kinds, and the tests flip. The seeder gains the mixed-kind states. The phase ends by shooting `before/`, so before and after share the same data.
2. **The view.** The badge, the links on „Przegląd", the kind on rows and picker cards, and the copy.
3. **Gate.** `after/` screenshots, a written verdict, the full suite.

The server owns the order and the labels of the confirming kinds. The badge only joins them, so the „fixed order" decision is one Pest-tested place instead of logic repeated in four components.

## Critical Implementation Details

**Baseline before pixels.** `before/` must be shot at the end of Phase 1, after the seeder gains its new states and before any Phase 2 frontend edit. S-16 left its baseline empty and the gap could not be repaired afterwards. The baseline will show „Potwierdzone aktem" on a gravestone-only death — that is the defect this change removes, and it is worth having on record.

## Phase 1: Contract — any kind links, kinds in the payload

### Overview

A source of any kind can be linked; the person preview tells the client which kinds confirm each fact. The UI still shows the old copy in this phase.

### Changes Required:

#### 1. Short kind label

**File**: `app/Enums/SourceKind.php`

**Intent**: Give the badge and the narrow places a one-word name for each kind.

**Contract**: New `shortLabel(): string` returning `akt`, `nagrobek`, `inne` for `MetricalRecord`, `Gravestone`, `Other`. `label()` and `options()` are unchanged.

#### 2. Remove the akt-only rule

**File**: `app/Http/Requests/EventSourceStoreRequest.php`

**Intent**: Let a source of any kind confirm an event.

**Contract**: The closure rule at `:37-46` and its message go. `source_id` keeps `bail`, `required`, `integer` and the `exists` rule scoped to the user's tree, so a foreign source still fails with the generic message. Authorization is unchanged (foreign event → 404). Imports left unused are dropped.

#### 3. Picker list holds every kind

**File**: `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Offer every source of the tree in the picker.

**Contract**: The `where('kind', …)` filter at `:57` goes. The prop stays `Inertia::optional`, scoped to the user's tree, ordered newest first then by id descending.

#### 4. Toast

**File**: `app/Http/Controllers/EventSourceController.php`

**Intent**: The confirmation message must be true for a gravestone.

**Contract**: The flash message at `:26` becomes „Powiązano źródło".

#### 5. Resources

**File**: `app/Http/Resources/EventResource.php`, `app/Http/Resources/MarriageResource.php`, `app/Http/Resources/SourceResource.php`

**Intent**: Send the kinds that confirm a fact, in the agreed order, and a short kind label for every source shown in the person modal and the picker.

**Contract**:

- `EventResource` gains `confirmed_kinds: list<string>`, present under the same condition as `sources` (relation loaded). It holds the short label of each distinct kind among the linked sources, ordered by `SourceKind::cases()`, never by link order. No sources → an empty list.
- The inline source shape in `EventResource` (`:52-59`) gains `kind_short_label`.
- `SourceResource` gains `kind_short_label` next to `kind_label`.
- The array-shape docblocks of all three resources follow (Larastan level 7).

#### 6. Kitchen sink data

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Render every confirmation state from seeded data.

**Contract**: New links and sources appended after everything that exists today, so earlier creation order holds; the class docblock lists them. Each new source gets an explicit `original_name`.

| State | Fact | Sources after seeding | Badge after Phase 2 |
| --- | --- | --- | --- |
| Gravestone only (US-02) | Genowefa Pełnodanowska, death | new gravestone `nagrobek-genowefy.jpg`, named „Nagrobek Genowefy Pełnodanowskiej, Zakopane" | „Potwierdzone: nagrobek" |
| Akt and gravestone | Wiktor Skanowski, death | existing `akt-zgonu-wiktor.jpg` + new gravestone `nagrobek-wiktora.jpg` | „Potwierdzone: akt, nagrobek" |
| All three kinds, longest badge on the longest title | Jan and Anna, marriage | existing `akt-slubu-jan-anna.jpg`, then new other `zaproszenie-slubne.jpg`, then new gravestone `nagrobek-jana-i-anny.jpg` — linked in that order | „Potwierdzone: akt, nagrobek, inne" |
| Other only | Helena Kowalska, death | new other `nekrolog-heleny.jpg` | „Potwierdzone: inne" |

Jan and Anna's marriage is linked out of kind order on purpose: the badge must read „akt, nagrobek, inne" while the source list under it reads akt, inne, nagrobek (link order).

Left untouched: Leon Kowalski's death („Bez źródła"), and the unlinked `nagrobek-kowalskich.jpg` and `list-rodzinny.jpg`, which the short delete dialog depends on.

#### 7. Tests

**File**: `tests/Feature/EventSourcesTest.php`, `tests/Feature/PersonsTest.php`, `tests/Feature/SourcesTest.php`

**Intent**: Flip the tests of the old rule, pin the badge order, and show that nothing existing moved.

**Contract**:

Modified:

- `linking an own source to an own event …` (`EventSourcesTest.php:21`): the toast is „Powiązano źródło".
- `linking a non-metrical source fails with the akt message and creates nothing` (`:59`) is replaced by `linking a source of any kind creates the link`, over all three kinds.
- `linking another trees gravestone gives the generic message, not the akt one` (`:70`) keeps its assertions and is renamed `linking another trees gravestone fails validation and creates nothing`.
- `available sources load through a partial reload …` (`PersonsTest.php:905`): the list holds every own source of every kind, newest first, and none from another tree.
- `sources index shows only the authenticated user own sources, newest first` (`SourcesTest.php:31`): add `kind_short_label` to the `hasAll` list. Extend the list rather than add `->etc()`.

New, in `PersonsTest.php`:

- the person preview lists the kinds confirming an event in a fixed order regardless of link order — gravestone linked first, akt second → `['akt', 'nagrobek']`
- the person preview lists each confirming kind once — two akts → `['akt']`; one of each kind → `['akt', 'nagrobek', 'inne']`
- an event without sources has no confirming kinds
- the person preview exposes the short kind label of each linked source
- a marriage confirmed by a gravestone lists nagrobek among its confirming kinds
- the persons index marks a death backed only by a gravestone as confirmed

Kept as they are: the foreign-event 404 tests (`EventSourcesTest.php:81-110`) and `changing the kind of a linked akt keeps its event links and the events stay confirmed` (`SourcesTest.php`).

#### 8. Baseline screenshots

**File**: `context/changes/fact-confirmed-by-any-source/screenshots/shots.mjs` (new, copied from `context/archive/2026-09-30-source-description-in-modal/screenshots/shots.mjs`)

**Intent**: Shoot the current fact views from the seeded data before any frontend edit.

**Contract**: `node shots.mjs <out-dir> [before|after]`, light and dark × 1280×900 and 390×844, ids read from rendered lists by text. The script header keeps the note that Chromium needs `LD_LIBRARY_PATH=<nss/nspr/asound libs>` on this host (bare `node` fails on `libnspr4.so`). Both flavours cover the same states:

- the persons list with Genowefa's row
- „Przegląd" for Jan (birth, marriage with three kinds), Genowefa (birth akt, death gravestone), Wiktor (akt and gravestone), Helena (other only) and Leon („Bez źródła")
- „Źródła" tab for Jan and for Genowefa
- „Rodzina" tab for Jan (marriage row)
- the picker opened from Leon's death, and from Jan's birth with the first disabled card scrolled into view before the snap (`page.locator('[role=dialog] button:disabled').first().scrollIntoViewIfNeeded()`); the grid scrolls inside `max-h-96` and Jan's linked sources are at positions 9 and 15 of 15
- Genowefa's death section in edit mode (the hint)

Output of this phase goes to `screenshots/before/`.

### Success Criteria:

#### Automated Verification:

- Event-source, person and source tests pass: `vendor/bin/sail artisan test --compact tests/Feature/EventSourcesTest.php tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php`
- PHP style: `vendor/bin/sail bin pint --dirty --format agent`
- Static analysis: `vendor/bin/sail composer run types:check`
- Frontend still compiles and lints: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- Demo seeder runs: `vendor/bin/sail artisan migrate:fresh && vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Baseline screenshots produced: `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node context/changes/fact-confirmed-by-any-source/screenshots/shots.mjs context/changes/fact-confirmed-by-any-source/screenshots/before before` exits 0 and writes one PNG per state × theme × width

#### Manual Verification:

- A gravestone chosen in the picker links to a death and the fact becomes confirmed (the badge still reads „Potwierdzone aktem" until Phase 2)
- The `before/` PNGs were opened and show the real list, the three tabs, the picker and the edit hint in both themes and both widths — not a login page or a blank frame

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: The view — kind at the fact, links, picker and copy

### Overview

The fact shows which kinds confirm it, its sources open from „Przegląd", every source in the modal and the picker shows its kind, and the copy stops saying „akt".

### Changes Required:

#### 1. Types

**File**: `resources/js/types/person.ts`, `resources/js/types/source.ts`

**Intent**: Mirror the resource contract from Phase 1.

**Contract**: `LinkedSource` and `Source` gain `kind_short_label: string`; `SourcedPersonEvent` gains `confirmed_kinds: string[]`.

#### 2. Status badge

**File**: `resources/js/components/persons/event-source-status.tsx`, callers in `overview-tab.tsx`, `sources-tab.tsx`, `family-tab.tsx`

**Intent**: One component states the confirmation and its kinds on all four surfaces.

**Contract**: The only prop is `kinds: string[]`; the `isConfirmed` prop is removed. A non-empty list is confirmed and renders „Potwierdzone: " followed by the kinds joined with „, " in the order received; it does not sort or de-duplicate. An empty list is unconfirmed and renders „Bez źródła", unchanged. Same `Badge` variant and icon for every kind. All five call sites pass the event's `confirmed_kinds`. The confirmed badge wraps instead of overflowing (`h-auto max-w-full shrink whitespace-normal` on the `Badge`): on the „Rodzina" row it sits in a 224 px column and the longest badge is 239 px.

#### 3. Sources at the fact on „Przegląd"

**File**: `resources/js/components/persons/overview-tab.tsx`

**Intent**: Open the evidence where the badge is read.

**Contract**: In `FactView`, the count-and-first-name line (`:101-111`) becomes a list with one entry per linked source, in link order: the source's `display_name` as a link to `SourceController.show`, followed by its short kind in a `Badge variant="secondary"`. The link goes through `useGuardedClick`, as „Otwórz" does at `sources-tab.tsx:97-105`, and has the accessible name „Otwórz źródło {nazwa}". The guard is defensive: while a section is edited the other cards are `inert` (`section-card.tsx:60`, `:101`), so its confirm branch is not reachable from these links today. A long name truncates with the full text in `title`; the kind badge never truncates. With no source, the picker trigger stays and reads „Powiąż źródło". `pluralPhrase` is no longer imported here.

#### 4. „Źródła" tab

**File**: `resources/js/components/persons/sources-tab.tsx`

**Intent**: Show the kind of each linked source and drop the akt wording.

**Contract**: Each row shows the short kind in a `Badge variant="secondary"` next to the name. The empty text is „Brak źródła. Powiąż źródło, żeby potwierdzić zdarzenie." The trigger reads „Powiąż źródło", or „Powiąż kolejne źródło" when the fact has one. The `eventGenitive` prop and its three call-site values are removed.

#### 5. Picker

**File**: `resources/js/components/sources/source-picker-dialog.tsx`

**Intent**: The picker now offers every kind and must say so.

**Contract**: Title „Powiąż źródło"; description „Wybierz źródło, które potwierdza to wydarzenie."; empty state „Nie masz jeszcze żadnych źródeł." with the existing „Dodaj źródło" link; a linked card reads „Już powiązane". Each card shows the short kind in a `Badge variant="secondary"`. Order and grid are unchanged.

#### 6. Remaining copy

**File**: `resources/js/components/persons/person-section-fields.tsx`, `resources/js/components/persons/person-list-parts.tsx`

**Intent**: The edit hint and the list ✓ must be true for any kind.

**Contract**: `CONFIRMED_HINT` becomes „Potwierdzone źródłem — upewnij się, że zmiana zgadza się ze źródłem." The screen-reader text of `ConfirmedMark` becomes „potwierdzone źródłem". The ✓ itself and the list query do not change.

### Success Criteria:

#### Automated Verification:

- Event-source, person and source tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/EventSourcesTest.php tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php`
- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Types, lint and format: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- Production build: `vendor/bin/sail npm run build`
- No akt wording left in the linking flow: `grep -rniE "aktem|powiąż (kolejny )?akt|brak aktu|powiązano akt|wybierz akt|tylko akt metrykalny|aktów metrykalnych|już powiązany" app resources/js/pages resources/js/components` shows only the Link hint „Adres strony z aktem, …" in `source-description-fields.tsx`

#### Manual Verification:

- Genowefa's death reads „Potwierdzone: nagrobek", Wiktor's „Potwierdzone: akt, nagrobek", Helena's „Potwierdzone: inne", Leon's „Bez źródła"; Jan and Anna's marriage reads „Potwierdzone: akt, nagrobek, inne" in both spouses' modals and on the „Rodzina" tab
- Linking a gravestone first and an akt second to a fresh death still reads „Potwierdzone: akt, nagrobek"; unlinking the akt leaves „Potwierdzone: nagrobek"; unlinking the last source gives „Bez źródła"
- On „Przegląd" each source of a fact is a link with its kind; clicking it opens the source page; while a section is being edited, the source links of the other cards cannot be clicked or focused
- The picker lists akts, gravestones and other sources with their kinds, newest first; a linked source is disabled and reads „Już powiązane"; the toast reads „Powiązano źródło"
- The persons list shows the same ✓ after Genowefa's birth (akt) and death (gravestone)
- At 390 px the longest badge sits under the card title without overflowing and wraps inside its column on the „Rodzina" marriage row, long source names truncate and the kind stays readable; focus is visible on the new links; light and dark both read correctly

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Screenshot gate

### Overview

Every state is rendered from the kitchen-sink data, shot, compared with the Phase 1 baseline and judged in writing.

### Changes Required:

#### 1. After shots

**File**: `context/changes/fact-confirmed-by-any-source/screenshots/shots.mjs`

**Intent**: Shoot the same states the baseline has, after the view changed.

**Contract**: The `after` flavour covers the state list from Phase 1 and writes to `screenshots/after/`. Selectors that matched old copy („Powiąż akt") are updated for the new copy in the `after` flavour only; the `before` flavour stays as it ran.

#### 2. Verdict

**File**: `context/changes/fact-confirmed-by-any-source/screenshots/verdict.md` (new)

**Intent**: A written judgement per state instead of a folder of images.

**Contract**: A table with State, Shots, Expected, Seen; a before → after line for „Przegląd", the „Źródła" tab and the picker; a row per charge from Current State Analysis marked closed or open. No state is left „not inspected".

### Success Criteria:

#### Automated Verification:

- Demo seeder runs: `vendor/bin/sail artisan migrate:fresh && vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Screenshots produced: `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node context/changes/fact-confirmed-by-any-source/screenshots/shots.mjs context/changes/fact-confirmed-by-any-source/screenshots/after` exits 0 and writes one PNG per state × theme × width
- Full suite green: `vendor/bin/sail artisan test --compact`
- Types, lint, format and build: `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check` and `vendor/bin/sail npm run build`

#### Manual Verification:

- `verdict.md` reviewed: every state has Expected and Seen filled, „Przegląd", the „Źródła" tab and the picker are compared with `before/`, and every charge is marked closed
- One real gravestone photo uploaded, linked to a death and opened from the link on „Przegląd": the zoom works and the fact reads „Potwierdzone: nagrobek" (prd-v3 US-02 end to end; seeded sources have no files)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None new. The order and de-duplication of the confirming kinds live on the server and are covered by Pest; the badge only joins what it receives. The existing JS unit tests must stay green.

### Integration Tests:

- `tests/Feature/EventSourcesTest.php`: linking each of the three kinds, the new toast, a foreign source still rejected, a foreign event still 404.
- `tests/Feature/PersonsTest.php`: confirming kinds in fixed order regardless of link order, each kind once, empty without sources, the short label per linked source, a marriage confirmed by a gravestone, the list flag for a gravestone-only death, and the picker list holding every own kind.
- `tests/Feature/SourcesTest.php`: the resource shape with `kind_short_label`.

### Manual Testing Steps:

1. Upload a gravestone photo in „Źródła", open a person with a death and link it from the „Źródła" tab; confirm „Potwierdzone: nagrobek".
2. Link an akt zgonu to the same death; confirm „Potwierdzone: akt, nagrobek" on „Przegląd" and on the „Źródła" tab.
3. Open each source from „Przegląd"; confirm the zoom viewer and that „Potwierdza" on the source page lists the death.
4. Unlink the akt, then the gravestone; confirm the badge after each step.
5. Link a gravestone to a marriage; confirm the badge in both spouses' modals and on „Rodzina".
6. Check a person confirmed only by akts before this change: the badge reads „Potwierdzone: akt" and nothing else moved.
7. Repeat steps 1 and 2 at 390 px and in dark mode.

## Performance Considerations

The picker list now holds every source of the tree instead of only akts. It is the same query as the „Źródła" list and still loads only when the picker opens. The confirming kinds are computed from the already loaded `sources` relation; the persons list query is unchanged.

## Migration Notes

- No schema change and no data change. Deploy with `scripts/deploy.sh` as usual.
- Every existing fact keeps its state, because the state is derived from link rows that this change does not touch. A fact linked to a source whose kind was changed in S-17 now shows that kind instead of „aktem".
- Rollback is code-only: revert the merge on `main` and redeploy. Links to gravestones or other sources made in the meantime stay; those facts remain confirmed, read „Potwierdzone aktem" again, and can be unlinked but not re-linked until the change returns.

## References

- Roadmap slice: `context/foundation/roadmap.md:347-357` (S-18)
- Requirements: `context/foundation/prd-v3.md:83-89` (US-02), `:112-113` (FR-008), `:138-142` (business rule), `:157` (Non-Goal)
- Predecessors: `context/archive/2026-09-29-scans-become-sources/plan-brief.md` (akt-only linking „in one place"), `context/archive/2026-09-30-source-description-in-modal/plan.md` (open risk closed here)
- The rule to remove: `app/Http/Requests/EventSourceStoreRequest.php:37-46`, `app/Http/Middleware/HandleInertiaRequests.php:56-58`
- Derived state: `app/Http/Resources/EventResource.php:46-59`, `app/Http/Controllers/PersonController.php:39`
- Badge and its surfaces: `resources/js/components/persons/event-source-status.tsx`, `overview-tab.tsx:89-127`, `sources-tab.tsx:40-154`, `family-tab.tsx:200-216`
- Guarded link pattern: `resources/js/components/persons/sources-tab.tsx:92-110`
- Screenshot script and verdict to copy: `context/archive/2026-09-30-source-description-in-modal/screenshots/shots.mjs`, `verdict.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — any kind links, kinds in the payload

#### Automated

- [x] 1.1 Event-source, person and source tests pass
- [x] 1.2 PHP style
- [x] 1.3 Static analysis
- [x] 1.4 Frontend still compiles and lints
- [x] 1.5 Demo seeder runs
- [x] 1.6 Baseline screenshots produced

#### Manual

- [x] 1.7 A gravestone chosen in the picker links to a death and the fact becomes confirmed
- [x] 1.8 The `before/` PNGs were opened and show the real list, the three tabs, the picker and the edit hint in both themes and both widths

### Phase 2: The view — kind at the fact, links, picker and copy

#### Automated

- [ ] 2.1 Event-source, person and source tests still pass
- [ ] 2.2 JS unit tests pass
- [ ] 2.3 Types, lint and format
- [ ] 2.4 Production build
- [ ] 2.5 No akt wording left in the linking flow

#### Manual

- [ ] 2.6 The seeded facts read „Potwierdzone: nagrobek", „Potwierdzone: akt, nagrobek", „Potwierdzone: inne", „Bez źródła" and „Potwierdzone: akt, nagrobek, inne" on every surface
- [ ] 2.7 Link order does not change the badge, and unlinking updates it down to „Bez źródła"
- [ ] 2.8 On „Przegląd" each source of a fact is a link with its kind that opens the source page; while a section is edited the links of other cards are inert
- [ ] 2.9 The picker lists every kind with its kind shown, disables linked sources and the toast reads „Powiązano źródło"
- [ ] 2.10 The persons list shows the same ✓ for an akt-backed and a gravestone-backed date
- [ ] 2.11 At 390 px the longest badge and long source names fit; focus is visible; light and dark both read correctly

### Phase 3: Screenshot gate

#### Automated

- [ ] 3.1 Demo seeder runs
- [ ] 3.2 Screenshots produced
- [ ] 3.3 Full suite green
- [ ] 3.4 Types, lint, format and build

#### Manual

- [ ] 3.5 `verdict.md` reviewed: every state has Expected and Seen filled, the views are compared with `before/`, and every charge is marked closed
- [ ] 3.6 One real gravestone photo uploaded, linked to a death and opened from the link on „Przegląd"
