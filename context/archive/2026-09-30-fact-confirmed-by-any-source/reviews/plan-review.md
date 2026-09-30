<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Fact Confirmed by Any Source Implementation Plan

- **Plan**: context/changes/fact-confirmed-by-any-source/plan.md
- **Mode**: Deep
- **Date**: 2026-09-30
- **Verdict**: REVISE
- **Verdict after triage**: SOUND — all 7 findings fixed in the plan (F1 via Fix A)
- **Findings**: 0 critical, 4 warnings, 3 observations

The approach is sound: the akt-only rule really lives in exactly the two places the plan names, no test outside the plan's list goes red, and the payload design holds. All seven findings are small plan edits; none changes the approach or the phase structure.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS (1 observation) |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

26/26 paths ✓, symbols ✓ (`SourceKind`, `withExists('sources')`, `useGuardedClick`, `CONFIRMED_HINT`, `ConfirmedMark`, `eventGenitive`, the test named at `SourcesTest.php:701`), brief↔plan ✓, Progress↔Phase ✓ (25 criteria ↔ 25 rows), seeder states ✓ (Genowefa, Wiktor, Jan–Anna, Helena, Leon and the two unlinked sources all exist as the plan describes).

Notes:

- Progress↔Phase: every Success Criteria bullet has a Progress row (Phase 1: 6 + 2, Phase 2: 5 + 6, Phase 3: 4 + 2), one `## Progress` heading, no checkboxes in phase blocks.
- Line drift only: the sources-index test starts at `tests/Feature/SourcesTest.php:32` and its `hasAll` is at `:46-48` (plan says `:31` and `:45-47`).
- Verified in deep mode: no third kind-based gate on linking or confirmation in `app/`, `routes/`, `database/` or `resources/js`; `SourcesTest.php:46-48` is the only scoped closure in `tests/`, so it is the only assertion that breaks on a new key; no TS literal constructs `Source`, `LinkedSource`, `SourcedPersonEvent` or `Marriage`; `BuildPersonPreview` loads `events.sources` and `marriages.sources` with all columns.
- Measured in the running demo app (Playwright, read-only DOM probe): the longest badge „Potwierdzone: akt, nagrobek, inne" is 239 px wide. It fits the „Przegląd" card header at 390 px, as the plan says.

## Findings

### F1 — Longest badge overflows the „Rodzina" row at 390 px

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Key Discoveries (section-card wrap), Phase 2 §2 Status badge, manual check 2.11
- **Detail**: The plan checks badge length only for the section-card header (`section-card.tsx:107` wraps). The „Rodzina" marriage row is different: the badge sits in a 224 px text column (`family-tab.tsx:94-104`), and `Badge` is `whitespace-nowrap shrink-0` with no max width (`ui/badge.tsx:8`), so it cannot wrap or shrink. Measured at 390 px on Jan–Anna's row: badge 239 px, column 224 px. It spills 15 px out of the column, 3 px past the row link's box (and its focus ring), and stops 1 px before the „…" menu button. No overlap and no horizontal scroll today, but the margin is one pixel. This is the seeded „all three kinds" state, and check 2.11 names only the card title.
- **Fix A ⭐ Recommended**: In `EventSourceStatus`, let the confirmed badge wrap instead of overflow (`h-auto max-w-full shrink whitespace-normal` on the `Badge`), and extend check 2.11 to name the „Rodzina" row at 390 px.
  - Strength: One place fixes all five call sites; still one `Badge` variant, no new token or component.
  - Tradeoff: At 390 px the three-kind badge on „Rodzina" becomes two lines, which looks different from the one-line pill elsewhere.
  - Confidence: MEDIUM — the overflow is measured; the wrapped look was not rendered.
  - Blind spot: Icon alignment in a two-line badge.
- **Fix B**: Keep the badge as it is and record the measured spill as accepted in Key Discoveries and in `verdict.md`; extend check 2.11 to „does not touch the menu button on the Rodzina row".
  - Strength: No code; the badge is identical on every surface.
  - Tradeoff: One pixel of margin — a longer short label (for example a rename of „inne") or a font change makes it overlap the menu button.
  - Confidence: HIGH — measured.
  - Blind spot: Browser zoom and other fonts not measured.
- **Decision**: FIXED — Fixed via Fix A

### F2 — Screenshot commands fail as written on this host

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 criterion 1.6, Phase 3 criterion 3.2, brief Prerequisites
- **Detail**: Both criteria say `node …/shots.mjs …` „exits 0". Run bare, Playwright's Chromium does not start here: `chrome-headless-shell: error while loading shared libraries: libnspr4.so` (reproduced during this review). It starts with `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu`. Earlier scripts carried the note „LD_LIBRARY_PATH=<nss/nspr/asound libs>" (`context/archive/2026-09-29-person-modal/screenshots/shots.mjs:3`); the S-17 script this plan copies dropped it. The libraries live under `/tmp`, so they can be gone after a restart. The plan's own rule is that the baseline must be shot at the end of Phase 1 and cannot be repaired later, so a launch failure at that moment is the worst time to find this out.
- **Fix**: Prefix both commands with `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu`, add the library directory to the brief's Prerequisites, and keep the note in the new script's header.
- **Decision**: FIXED — Fix in plan

### F3 — The „linked card disabled" picker state will not be in the shots

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §8 Baseline screenshots („the picker opened … from Jan's birth (a linked card disabled)")
- **Detail**: The picker grid scrolls inside `max-h-96` (`source-picker-dialog.tsx:113`). Measured today: 6 cards fully visible at 1280 px, 2 at 390 px. Once Phase 1 removes the filter and the seeder appends five sources, the list is 15 sources newest first and Jan's two linked sources sit at positions 9 and 15. Neither is in view at either width, in `before` or in `after`. The shot would show no disabled card, and a missing `before` state cannot be re-shot after Phase 2.
- **Fix**: In both flavours, scroll the first disabled card into view before the snap (`page.locator('[role=dialog] button:disabled').first().scrollIntoViewIfNeeded()`), or open the picker from Jan–Anna's marriage on the „Źródła" tab, whose two new sources are among the five newest.
- **Decision**: FIXED — Fix in plan

### F4 — Manual check 2.8 asks for a guard that cannot fire

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §3 Sources at the fact, manual check 2.8, Progress row 2.8
- **Detail**: Check 2.8 says „with an unsaved section edit the click asks before leaving". That state is unreachable from the new links. While a section is edited, its own card renders the form without `view` (`section-card.tsx:85-96`), and every other card is `inert` (`section-card.tsx:60`, `:101`). `isDirty` is set only by a mounted editor (`section-card.tsx:159-182`). So whenever there is an unsaved edit, the `FactView` links are either unmounted or not clickable. `useGuardedClick` works there (the provider is in scope) but its guard branch never runs. The human cannot confirm the step as written, and Progress titles become immutable after this review.
- **Fix**: Reword the last clause of 2.8 (bullet and Progress title) to „while a section is being edited, the source links of the other cards cannot be clicked or focused". Keep `useGuardedClick` on the link for parity with „Otwórz" on the „Źródła" tab, and say in §3 that it is defensive.
- **Decision**: FIXED — Fix in plan

### F5 — Badge props allow „confirmed with no kinds"

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 §2 Status badge
- **Detail**: The new props are `isConfirmed: boolean` and `kinds: string[]`. In every call site both come from the same loaded `sources` relation, so `isConfirmed` is always `kinds.length > 0`. Two props can disagree, and the plan does not say what `isConfirmed = true` with `kinds = []` renders („Potwierdzone: " with a trailing colon).
- **Fix**: Make `kinds` the only prop; confirmed means `kinds.length > 0`. All five call sites pass `confirmed_kinds`.
- **Decision**: FIXED — Fix in plan

### F6 — No seeded state has link order different from kind order

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §6 Kitchen sink data
- **Detail**: Every multi-kind state is attached akt → nagrobek → inne, which is also the enum order. The screenshots therefore cannot show the rule „the order never depends on which source was linked first"; only Pest and manual check 2.7 cover it. The same data also hides what the fact card looks like when the badge (kind order) and the source list below it (link order) disagree.
- **Fix**: For Jan and Anna's marriage, attach `zaproszenie-slubne.jpg` before `nagrobek-jana-i-anny.jpg`. The badge must still read „akt, nagrobek, inne" while the list reads akt, inne, nagrobek.
- **Decision**: FIXED — Fix in plan

### F7 — The copy check misses one old string

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 automated criterion 2.5
- **Detail**: The grep pattern has no alternative for „Wybierz akt metrykalny, który potwierdza to wydarzenie." (`source-picker-dialog.tsx:84`). The line is in the change list, but the automated check would pass if it were left behind. Every other current „akt" string in the linking flow is matched.
- **Fix**: Add `wybierz akt` to the pattern.
- **Decision**: FIXED — Fix in plan
