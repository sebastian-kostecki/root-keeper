<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Linie drzewa i węzeł związku — plan implementacji

- **Plan**: context/changes/tree-lines-union-node/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-12
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated checks re-run during review: TreeTest (48 passed), Larastan (0 errors), Pint (pass), `types:check` (TS, no errors), `test:js` (315 passed), `npm run check` (pass); greps for `bg-tree-line|w-px|h-px` in family-sheet.tsx and `[1.5px]|[2px]` in components/tree return nothing. E2E (Playwright) not run.

## Findings

### F1 — Progress 3.14 ticked, but verdict.md says the user has not accepted the screenshots

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/tree-lines-union-node/plan.md:368, context/changes/tree-lines-union-node/screenshots/verdict.md:3
- **Detail**: Progress item 3.14 "zrzuty jasny/ciemny zaakceptowane przez użytkownika" is `[x]`, while verdict.md still states "Ocena wykonana przez agenta; akceptacja użytkownika jeszcze nie nastąpiła." The two disagree; the tick is a possible checkbox without evidence.
- **Fix**: Confirm the screenshots with the user and update the line in verdict.md (or untick 3.14 until they do).
- **Decision**: FIXED — verdict.md now records the user's acceptance of the screenshots (2026-10-12).

### F2 — `family-sheet.tsx` imports `clsx` directly instead of `cn`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/tree/family-sheet.tsx:3
- **Detail**: Every other view uses `cn` from `@/lib/utils`; this is the only file importing `clsx` directly. It may be deliberate (tailwind-merge could treat `border-line` / `border-tree-line` as conflicting), but there is no comment saying so.
- **Fix**: Try `cn`; if the line classes survive, switch to it, otherwise add a one-line comment explaining why `clsx` is used.
- **Decision**: FIXED — `clsx` kept on purpose: `twMerge` drops `border-tree-line` / `border-b-line` next to the custom width utilities (verified); added a comment above the import.

### F3 — Mixed-language identifier `isBezSlubu`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/tree/union-node.tsx:75
- **Detail**: The rest of the code base names identifiers in English; this one mixes Polish into it.
- **Fix**: Rename to `isUnmarried`.
- **Decision**: FIXED — renamed `isBezSlubu` to `isUnmarried` (union-node.tsx:75, :115).

### F4 — Marriage year of ancestor pairs is hidden from screen readers

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/tree/family-sheet.tsx:272
- **Detail**: `MarriageNode` is `aria-hidden` by plan (decorative, per the design system), so the year and the marriage fact for parent and grandparent pairs are available only visually. The same information is available in the marriage side panel for the focus person only.
- **Fix**: Leave as is (matches plan and DS) · Strength: no deviation from the approved plan · Tradeoff: screen-reader users miss the ancestors' marriage year · Confidence: MED — the plan and DS explicitly call it decorative · Blind spot: no screen-reader pass was done. Alternative: add an `sr-only` text ("ślub 1861") to the node and drop `aria-hidden`.
- **Decision**: ACCEPTED — node stays decorative (`aria-hidden`) as the plan and design system specify.
