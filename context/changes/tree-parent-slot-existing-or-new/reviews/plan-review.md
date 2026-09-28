<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Existing or New Parent from an Empty Tree Slot

- **Plan**: `context/changes/tree-parent-slot-existing-or-new/plan.md`
- **Mode**: Deep (codebase verified locally; review subagent unavailable)
- **Date**: 2026-09-28
- **Verdict**: SOUND (after triage)
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
14/14 paths ✓, 5/5 symbols ✓ (Gender::options, Religion::options, Person 'view' policy, PersonFactory male()/female(), ParentChildGraph), brief↔plan ✓, Progress↔Phase ✓ (12+12 rows, 1:1). PersonFactory defaults `gender` to null, so the new same-sex rule cannot make existing relation tests flaky.

## Findings

### F1 — Existing TreeTest slot assertions will break

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 — §4 slot metadata, §10 tests
- **Detail**: `tests/Feature/TreeTest.php:276-279, 285, 293, 300, 316` compare parent placeholders with exact `toBe(['kind'=>'unknown','label'=>…])`; adding `parent_of` fails them. The plan listed only new tests.
- **Fix**: Phase 1 §10 lists updating these assertions to check kind/label + `parent_of` (id, role); the partner assertion (`:204`) stays exact.
- **Decision**: FIXED

### F2 — Sex rule can be bypassed by editing a person's sex

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 §1; brief Success Criteria; plan Desired End State
- **Detail**: The rule runs only on link creation; editing an unknown-sex parent to the other parent's sex still yields two fathers/mothers via `PersonController@update`. The brief claimed "no path" could.
- **Fix A ⭐ Recommended**: Narrow the claim to new links; list the edit path under "What We're NOT Doing".
  - Strength: No scope growth; sex changes are rare, deliberate edits.
  - Tradeoff: Same-sex parent pairs remain possible via one explicit edit.
  - Confidence: HIGH — consistent with "existing data left as is".
  - Blind spot: None significant.
- **Fix B**: Refuse such a sex change in `PersonUpdateRequest`.
  - Strength: Closes the rule on every path.
  - Tradeoff: Broadens the slice to the edit form, tests and messages.
  - Confidence: MED — needs graph + co-parent sexes at update time.
  - Blind spot: Interaction with confirmed-by-scan hints unverified.
- **Decision**: FIXED (Fix A)

### F3 — Hidden-count hint: what is counted, and Polish plurals

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §9
- **Detail**: Count basis (rule-excluded people? search text?) and plural forms were undefined.
- **Fix**: Count = unknown-sex people not in `excluded_ids`, independent of search; plural forms 1 osobę / 2–4 (not 12–14) osoby / otherwise osób.
- **Decision**: FIXED

### F4 — Preview blocked reasons: avoid a query per candidate

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §2
- **Detail**: New "Ma już ojca/matkę" reasons need candidates' parents' sexes; `ParentChildGraph` holds ids only.
- **Fix**: Read sexes from the already loaded candidate collection plus the person (id → gender map).
- **Decision**: FIXED
