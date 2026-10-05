<!-- PLAN-REVIEW-REPORT -->
# Plan Review: F-01 critical-flows-e2e-guard

- **Plan**: context/changes/critical-flows-e2e-guard/plan.md
- **Mode**: Deep (inline verification, no sub-agent)
- **Date**: 2026-10-05
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Paths 9/9 ✓ (e2e-server.sh, playwright.config.ts, tests/e2e/{auth.setup,seed.spec}.ts, portrait-small.jpg, Person/SourceController, side-panel.tsx, parent-slot/add-child/add-spouse dialogs), symbols ✓ (routes persons.*/sources.*/events.sources.*, PreventRequestForgery Sec-Fetch-Site + X-XSRF-TOKEN, persons.store redirect, `q` filter in persons/sources index), brief↔plan ✓, Progress↔Phase ✓ (1.1–4.3 match all criteria).

## Findings

### F1 — Frontend mutations are invisible to a running E2E server

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 3 — Success Criteria (celowe uszkodzenia, 3.4)
- **Detail**: `scripts/e2e-server.sh` runs `npm run build` once at start and serves a production build; `playwright.config.ts:40` has `reuseExistingServer: !CI`. The three planned mutations (`parent-slot-dialog.tsx`, `add-child-dialog.tsx`, `add-spouse-dialog.tsx`) are TSX, so editing them changes nothing until a rebuild. A mutated test would stay green (false proof of sensitivity) or, after revert, run on a stale mutated bundle. Only the Phase 2 mutation (PHP, bind-mounted) takes effect immediately.
- **Fix**: State in Phase 3 that each TSX mutation requires stopping the server on 8100 (so the script rebuilds), running the test red, reverting, and rebuilding again before the next green run; add "rebuild po cofnięciu" to 3.4.
- **Decision**: FIXED (Fix in plan)

### F2 — Suffix/cleanup contract under-specified

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 — `api.ts` (`uniqueSuffix`, `cleanupBySuffix`)
- **Detail**: Cleanup relies on `/persons?q=` (matches only first_name/last_name via `whereLike`, split on whitespace) and `/sources?q=` (matching field not verified). The plan does not say where the suffix goes in each record, so a suffix placed in e.g. `birth.place` would never be found and leak data. Suffix must also be a single token without spaces/`%`/`_`, and should include `repeatEachIndex`/retry so `--repeat-each` runs cannot collide.
- **Fix**: Specify: suffix is one token appended to `last_name` (persons) and to the source name; confirm `SourceController::index` `q` matches name; add repeatEachIndex/retry to `uniqueSuffix`.
- **Decision**: FIXED (Fix in plan; SourceController::index nie filtruje po q, więc sprzątanie filtruje listę źródeł po nazwie w teście)

### F3 — Inertia v3 embedded page object not yet resolved

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Critical Implementation Details — odczyt propsów
- **Detail**: Plan admits `data-page` vs `<script type="application/json">` is unknown. `personPreview` and cleanup both depend on it; it is a Phase 2 first-step risk (already listed in the brief).
- **Fix**: Settle it with one 2-minute `curl` of `/tree/{id}` before Phase 2 and write the answer into the plan.
- **Decision**: FIXED (Fix in plan; obiekt w <script data-page="app" type="application/json">)

### F4 — Stale-volume note and shared dev directory

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 / Migration Notes
- **Detail**: Host `storage/app/private/sources` already exists (owned by the dev user), so the nested mount point is safe. Remaining gap: if the `e2e` DB is ever recreated, the volume keeps orphan files; the comment in `e2e-server.sh` should say to remove the volume together with the DB.
- **Fix**: Add that sentence to the script comment and §6.6.
- **Decision**: FIXED (Fix in plan)
