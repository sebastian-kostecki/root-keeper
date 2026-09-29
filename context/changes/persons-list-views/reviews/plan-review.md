<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Persons list — List and Table views

- **Plan**: context/changes/persons-list-views/plan.md
- **Mode**: Deep
- **Date**: 2026-09-29
- **Verdict**: REVISE → SOUND after triage
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding
12/12 paths ✓ (`ui/table.tsx` correctly new), 7/7 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

What was verified and holds:
- `hrefFor` / `close()` keep every query param except `person`.
- `EventResource` and `PersonResource` each have only two callers (index and preview).
- The TS `Person` type has one consumer (`persons/index.tsx`).
- `Person::birth()` reads the loaded `events`, so `scans_exists` carries through.
- The seeder helpers match the plan.
- Node 24 in Sail supports `Intl.Collator('pl')`.

## Findings

### F1 — Table name sort and tie-breaks are undecided; server order isn't Polish

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details → Sort semantics; Phase 2 §3
- **Detail**:
  - The plan leaves undecided:
    - what `nazwisko` / `-nazwisko` mean;
    - where a null surname goes on `-nazwisko`;
    - whether `-urodzenie` also reverses its tie-break.
  - The live DB (en_US.utf8, postgres:18-alpine/musl) sorts bytewise: `Cyran, Lis, Zając, Ziel, Ćma, Łukasz, Żak`. Relying on server order would put Ł/Ś/Ż/Ć after Z in the table and within groups, while Lista puts them after L/S/Z.
- **Fix**: One `Intl.Collator('pl')` name comparator for every client sort and for the within-group order. Null surnames go last in both directions, `-` applies to the primary key only, and new Vitest cases cover this.
  - Strength: One comparator for both views; collator verified in Sail's Node 24.
  - Tradeoff: Client order diverges from the Pest-locked server order, which is intentionally kept.
  - Confidence: HIGH — collation measured in the running DB.
  - Blind spot: Whether to fix the server ordering later (out of scope).
- **Decision**: FIXED — plan and brief updated.

### F2 — `only: ['filters']` does not skip the people query

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Implementation Approach; Phase 1 §1
- **Detail**: `$people` is built eagerly (`PersonController.php:30-54`), so every partial reload runs the full query and resource resolve. This already happens on every preview open.
- **Fix**: Make `persons` a lazy closure prop, and add a Pest assertion that an `only: ['filters']` reload omits `persons`.
- **Decision**: FIXED — Phase 1 §1 and §4 and the Performance notes updated.

### F3 — `filters.sort` echo on Lista is undecided

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Desired End State (server contract); Phase 1 §4
- **Detail**: What `?view=lista&sort=-zgon` echoes was never decided.
- **Fix**: The server forces `sort=nazwisko` whenever `view` is `lista`, with a Pest case.
- **Decision**: FIXED — contract table and Phase 1 tests updated.

### F4 — Screenshot „before” capture and the fresh user have no step

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 §2
- **Detail**: `before/` had to be captured before Phase 3, but the step lived in Phase 4. The empty-tree shot had no user to log in as.
- **Fix**: New Phase 3 §0 writes `shots.mjs` and captures `before/`. The empty-tree shot registers a throwaway user via `/register`.
- **Decision**: FIXED — Phase 3 §0 added, Phase 4 §2 updated.
