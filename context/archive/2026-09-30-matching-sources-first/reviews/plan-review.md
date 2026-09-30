<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Matching Sources First Implementation Plan

- **Plan**: context/changes/matching-sources-first/plan.md
- **Mode**: Deep (claims verified inline against the code; no sub-agent — the touched surface is seven files)
- **Date**: 2026-09-30
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | PASS (1 observation) |
| Blind Spots | PASS |
| Plan Completeness | PASS (1 observation) |

## Grounding

Grounding: 15/15 existing paths ✓ (new `source-matching.ts` correctly absent), 8/8 symbols ✓, brief↔plan ✓, Progress↔Phase ✓ (3 phases; 9 / 12 / 6 rows match the Success Criteria bullets).

Claims verified against the code:

- `SourcePickerDialog` has exactly two callers (`overview-tab.tsx:136`, `sources-tab.tsx:143`); both hold a `SourcedPersonEvent`, whose `type` is `'birth' | 'death' | 'marriage'` (`types/person.ts:15`) and is sent by `EventResource.php:39`.
- `EventType` values are a subset of `SourceRecordType` values (`app/Enums/`), so no mapping table is needed.
- `availableSources` is `latest()->orderByDesc('id')` over the user's tree through `SourceResource`, which carries `kind`, `record_type`, `record_type_label` and `year` (`HandleInertiaRequests.php:55-57`).
- `SourceFactory` defaults to an untyped akt; `described()` is birth / 1878. Tracing the 15 existing and 3 planned sources through the newest-first order reproduces every before/after expectation in the Phase 1 screenshot table (death akt last before and alone under „Pasujące akty" after; odpis second tile before and first, disabled, after; marriage picker with no match).
- Overriding `created_at` through the factory works; `PersonsTest.php:995` does the same.
- Nothing outside the seeder file references `TreeViewDemoSeeder`.
- Screenshot tooling is present today: `/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu`, Playwright in the npx cache, the app answers on port 8000, and the Vite dev server is active (`public/hot`), so shots reflect the current source.
- No `context/foundation/lessons.md`, `.ai/rules/` or `docs/reference/contract-surfaces.md` in the repo; those checks were skipped.

## Findings

### F1 — `migrate:fresh` in steps 1.6 and 3.1 wipes the local database for no benefit

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 1 Automated Verification (1.6), Phase 3 Automated Verification (3.1), brief "Open Risks"
- **Detail**: The seeder resets itself: `run()` deletes both demo users (`TreeViewDemoSeeder.php:52`), and trees, people, events and sources cascade from the user (`create_trees_table.php:16`, `create_scans_table.php:16`). This change has no migration, `migrate:status` shows nothing pending, and `shots.mjs` reads person ids by text. So `migrate:fresh` adds nothing to the gate, but it drops every other account (the local database holds one non-demo user today) and runs unattended under `/10x-implement`. It also works against the plan's own manual steps: the fresh account and uploaded akt from 2.11 are wiped by 3.1, and 3.6 then needs "an own akt" again. Re-seeding without the wipe was not executed in this review.
- **Fix**: Replace both commands with `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`, and drop the wipe note in 1.6 and the matching risk bullet in the brief.
- **Decision**: FIXED (Fix in plan)

### F2 — The open group-label charge has no home after archive

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Current State Analysis (charges table), What We're NOT Doing, Phase 3 verdict
- **Detail**: After this change the label class string lives in three files (`overview-tab.tsx:69`, `family-tab.tsx:149`, the picker). The plan leaves the charge open by decision, which is reasonable for a one-view slice, but the only record is a row in `verdict.md`, which is archived with the change. Nothing in the roadmap or on GitHub picks it up, so the charge is dropped in practice.
- **Fix**: Add a GitHub issue (or a roadmap note) naming the three locations, and reference it from the "open" row of the Phase 3 verdict contract.
- **Decision**: FIXED (Fix in plan)

### F3 — Seeder hook point is named wrong

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 3 (Kitchen sink data)
- **Detail**: "A new private method called last in `run()`, after `confirmationStates` (`:174`)" — line 174 is inside `seedTree()` (`:69`); `run()` (`:49`) only wraps the transaction. The new method also needs `$jan`, because it links the odpis to Jan's birth (as `sourceStates($jan, $kacper)` does).
- **Fix**: Reword to "called in `seedTree()` after `confirmationStates` (`:174`), taking `$jan`".
- **Decision**: FIXED (Fix in plan)
