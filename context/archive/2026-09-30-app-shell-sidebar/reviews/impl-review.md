<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: App shell — variant A („bez górnej belki”)

- **Plan**: context/changes/app-shell-sidebar/plan.md
- **Scope**: Full plan (completed phases)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-09-30
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated checks re-run: `npm run check` pass, `types:check` pass, `test:js` 120/120, Pest `AppShellTest` + `Auth` 25/25, phpstan 0 errors, all three grep gates print nothing, all 24 `before/` shots have an `after/` twin (34 files).

## Findings

### F1 — Shared `useInitials` hook changed during the screenshot gate

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Scope Discipline
- **Location**: resources/js/hooks/use-initials.tsx:11-14 (commit 9e78058)
- **Detail**: Plan Phase 5 is a gate (no shell code changes) and lists no edit to this hook. The change was made to make the seeded name „Demo drzewa (pusty)” show „DD” instead of „D(”. The hook is also used by `persons/person-avatar.tsx` and `persons/family-tab.tsx`, so initials change for every person avatar whose name has a word not starting with a letter/digit. No test covers it. `verdict.md` also says „Nothing was fixed in this phase” while listing differences 1 and 2 as fixed.
- **Fix A ⭐ Recommended**: Keep the change, add a small Vitest case for the hook, add a plan addendum, and correct the sentence in `verdict.md`.
  - Strength: Behaviour is arguably more correct for real names with parentheses or dashes; the test pins it.
  - Tradeoff: Person avatars are touched by a shell change.
  - Confidence: MED — only the two other callers were grepped, not visually checked.
  - Blind spot: Person names in real data that start with a symbol.
- **Fix B**: Revert the hook and rename the demo user in `TreeViewDemoSeeder`.
  - Strength: Shell change stays inside its scope.
  - Tradeoff: The „D(” quirk stays for real names like that; shots must be re-taken.
  - Confidence: MED — depends on whether the seeder name is used elsewhere.
  - Blind spot: Seeder references to the name.
- **Decision**: FIXED via Fix A

### F2 — Board comparison and manual gate items still open

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: plan.md Progress 5.5–5.9
- **Detail**: Items 5.5–5.9 are unchecked. `verdict.md` states the six A boards were not compared (artifact returned markup, not renders) and lists about a dozen shots not opened by eye (dark focus, collapsed user menu, settings, dark source page). The Progress SHA stamps for phases 1–5 are an uncommitted edit of plan.md.
- **Fix**: Human does 5.5–5.8 against the canvas, then checks the boxes; commit the plan.md stamps.
- **Decision**: ACCEPTED — manual steps 5.5–5.8 left to the human

### F3 — E-mail truncated in the expanded account row

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/user-info.tsx:41-50
- **Detail**: At 1280 px the e-mail reads „tree-demo@examplec…”; the plan shows the e-mail under the name. The full address is only visible in the account menu, and the row has no `title`.
- **Fix**: Add `title={user.email}` on the e-mail span (or accept; a narrower real address fits).
- **Decision**: FIXED — `title` on the e-mail span

### F4 — Tooltip separator looks tight

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/nav-main.tsx:38-40
- **Detail**: Tooltip text is „Źródła · 18” in code (matches plan) but renders as „Źródła·18” at 12 px per the verdict.
- **Fix**: Accept, or use non-breaking spaces around the dot if the board shows more air.
- **Decision**: FIXED — non-breaking spaces around the dot
