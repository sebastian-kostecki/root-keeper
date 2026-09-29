<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Preview without relation adding (S-29)

- **Plan**: context/changes/preview-relations-read-only/plan.md
- **Mode**: Deep
- **Date**: 2026-09-29
- **Verdict**: REVISE → SOUND after triage
- **Findings**: 0 critical · 3 warnings · 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | PASS |
| Plan Completeness | WARNING |

## Grounding
19/19 paths ✓, 12/12 symbols ✓, 21/21 roadmap+PRD line refs ✓, brief↔plan ✓

## Findings

### F1 — "dropped" status breaks roadmap lifecycle and the plan's own precedent

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 3 §1 Roadmap; criterion 3.3
- **Detail**: The plan cites the roadmap's pattern for superseded slices, then keeps S-28 with `Status: dropped`. S-10/S-11/S-21–S-23 have no row or block left, only a note (roadmap.md:29,31). `dropped` is not in the 10x-roadmap lifecycle (SKILL.md:595), and milestone-state.md:13 needs every S-NN to be `done` before M-1 can close.
- **Fix A ⭐ Recommended**: Remove the S-28 row, block and handoff row; add a „Porzucone (2026-09-29, decyzja użytkownika)” note next to lines 29/31; criterion 3.3 checks there is no `### S-28` and the note exists.
  - Strength: Matches the existing precedent; M-1 stays closable.
  - Tradeoff: The S-28 block is visible only in git history.
  - Confidence: HIGH — five earlier slices were handled this way.
  - Blind spot: None significant.
- **Fix B**: Keep `Status: dropped` as a terminal status.
  - Strength: History stays in place.
  - Tradeoff: A status the downstream skills don't know; M-1 closure needs an override.
  - Confidence: MED — depends on how downstream skills handle an unknown status.
  - Blind spot: /10x-status parsing of unknown statuses is unverified.
- **Decision**: FIXED (Fix A)

### F2 — Phase 3 misses stale S-28 / "removal on the tree" text

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 3 §1–§2; criteria 3.2–3.4
- **Detail**: Not in the edit lists: 11 roadmap „Parallel with:” lines naming S-28 (249–403); PRD:75, :107, :120, :130 and :153. The automated check greps only "S-25, S-26, S-27, S-28".
- **Fix**: Add the locations to the contracts, plus two sweep criteria (roadmap `S-28`, PRD `tylko do odczytu|usuwa.*z drzewa|usuwanie relacji`).
- **Decision**: FIXED

### F3 — Manual checks rely on seeder data that doesn't exist

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 Manual (1.5, 1.7); Testing Strategy steps 1 and 3
- **Detail**: In TreeViewDemoSeeder, Jan has 2 marriages (Anna, Ewa), not 4. Kacper has parents Jan + Anna. No seeded person has all three relation sections empty.
- **Fix**: 1.5 → 2 marriages; 1.7 → Kacper (Dzieci and Małżeństwa hints) and Marta (Rodzice hint).
- **Decision**: FIXED

### F4 — Middleware memo rationale is wrong; two docblocks will be stale

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1 (and Phase 1 §5)
- **Detail**: `availableScans` never calls `previewPerson()` (HandleInertiaRequests.php:55-57), so `personPreview` becomes its only caller. The PersonCandidateList docblock (person-candidate-list.tsx:36) names the preview pickers that are being removed.
- **Fix**: Correct the rationale and drop the "several shared props" docblock sentence; retarget the PersonCandidateList docblock to the tree's pickers.
- **Decision**: FIXED

### F5 — Edit-only MarriageFormDialog keeps a dead `person` prop

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §3 Contract
- **Detail**: `person` is read only by the create branch's hidden `person_id` input (marriage-form-dialog.tsx:124); `InputError` only showed `errors.spouse_id`.
- **Fix**: The contract becomes `{ marriage, trigger }`; the call site drops `mode=` and `person=`; remove the `InputError` import.
- **Decision**: FIXED
