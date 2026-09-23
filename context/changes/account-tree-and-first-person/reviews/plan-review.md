<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Konto z gotowym drzewem i pierwsza osoba

- **Plan**: context/changes/account-tree-and-first-person/plan.md
- **Mode**: Deep
- **Date**: 2026-09-23
- **Verdict**: REVISE → SOUND (after triage)
- **Findings**: 1 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
15/15 paths ✓, 5/5 symbols ✓ (dashboard refs, reset refs, types:check/check scripts, setLayoutProps, Radix ui/*), brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Phase 3 grep can never return empty

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3, Success Criteria 3.2
- **Detail**: The pattern `routes/password` matches `resources/js/pages/auth/confirm-password.tsx:7` (`@/routes/password/confirm`), which stays in use, so the gate always fails.
- **Fix**: Narrow the pattern to `'@/routes/password'` (with the quotes).
- **Decision**: FIXED

### F2 — Radix Checkbox sends "on", which the boolean rule rejects

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §4 (PersonStoreRequest) + §7 (create.tsx)
- **Detail**: `@radix-ui/react-checkbox` submits `value = "on"` by default; Laravel `boolean` accepts only true/false/0/1/"0"/"1" (`ValidatesAttributes.php:538`). Tests posting `true` would pass while the real form fails.
- **Fix**: Checkbox `value="1"`, conditional rules keyed on "1" (`prohibited_unless:is_deceased,1`, `required_if_accepted`), tests post "1".
- **Decision**: FIXED

### F3 — The backfill test has no user without a tree

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §6 + §8
- **Detail**: `UserFactory` afterCreating always creates a tree; the migration's date prefix may differ, yet the test requires the file by path.
- **Fix**: `withoutTree()` state + load the migration via `glob(database_path('migrations/*_create_trees_for_existing_users.php'))`.
- **Decision**: FIXED

### F4 — No phase runs the full test suite

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Success Criteria, Phases 1–3
- **Detail**: The factory, locale and Dashboard changes affect every test, but Settings/*, ExampleTest and BackupScheduleTest ran in no gate.
- **Fix**: Added "Full suite passes" as criteria 2.7 and 3.6 (manual items renumbered to 2.8–2.12, 3.7–3.8).
- **Decision**: FIXED

### F5 — passkey-verify.tsx has a literal '/dashboard' that tsc won't catch

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §8
- **Detail**: `passkey-verify.tsx:34` has `router.visit(response.redirect ?? '/dashboard')`, a string that tsc won't flag after the route is removed.
- **Fix**: §8 now says explicitly to replace it with `persons.index().url`.
- **Decision**: FIXED
