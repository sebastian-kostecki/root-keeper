<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Parent–Child Relations

- **Plan**: context/changes/parent-child-relations/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-25
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Notes:
- Automated checks run during review: relation + persons + event-scan feature tests (70 passed, 497 assertions), `pint --test`, `types:check`, `npm run check`, `npm run build` — all pass. Migration round-trip not re-run: dev DB holds 3 relation rows; `down()` is a plain `dropIfExists` and was read instead.
- Unplanned `app/Models/Tree.php` `parentChildRelations()` HasMany is the mechanism behind the planned "create the row with `tree_id`" contract — benign, not scope creep.
- Security verified: both people are tree-scoped twice (Form Request `exists` + controller `findOrFail` on `$tree->people()`), `tree_id` never comes from input, destroy guarded by `denyAsNotFound`, `relationCandidates` behind `Gate::authorize('view')`.

## Findings

### F1 — Relative rows omit death date and the "ur./zm." format

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/persons/relatives-card.tsx:57
- **Detail**: Desired End State asks for birth/death dates, muted, e.g. "ur. 1850 · zm. 1910". The card renders only the bare `birth_date`; `death_date` is in the payload but never shown. The persons index already renders the planned format (`resources/js/pages/persons/index.tsx:103-110`).
- **Fix**: Render `[birth_date && \`ur. ${birth_date}\`, death_date && \`zm. ${death_date}\`].filter(Boolean).join(' · ')` like the index.
- **Decision**: FIXED — relatives-card rows use the index "ur. … · zm. …" format

### F2 — Empty-state copy differs from plan

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/persons/relatives-card.tsx:42
- **Detail**: Plan: "Brak znanych rodziców." / "Brak znanych dzieci." Code: "Brak dodanych rodziców." / "Brak dodanych dzieci." "znanych" matches the genealogy framing (unknown vs not entered) used elsewhere in the plan.
- **Fix**: Change the two strings to "Brak znanych rodziców." / "Brak znanych dzieci." (or amend the plan if "dodanych" was a deliberate choice).
- **Decision**: FIXED — "Brak znanych rodziców." / "Brak znanych dzieci."

### F3 — Birth-order test does not prove the sort

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ParentChildRelationsTest.php:195
- **Detail**: Children and relations are created in exactly the expected order (1850, 03.1850, 1852, none), so ids and insertion order already match the assertion — the test would likely pass with `sortRelatives` removed. It also never asserts `person.parents`, which the plan lists ("returns `parents` and `children` with `relation_id`").
- **Fix**: Create the children out of order (e.g. no-birth, 1852, 03.1850, 1850) and add one `person.parents.0.relation_id` assertion.
- **Decision**: FIXED — fixtures created out of order, parents asserted; verified the test fails with the sort removed

### F4 — Relation factory creates a tree eagerly

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: database/factories/ParentChildRelationFactory.php:22
- **Detail**: `Tree::factory()->create()` runs inside `definition()`, so every `create()` inserts an extra Tree + User even when all columns are overridden; overriding only `tree_id` (the "removing another tree's relation" test) yields a relation whose people live in a different tree. Sibling factories (`PersonFactory`, `EventFactory`) use lazy `'tree_id' => Tree::factory()`.
- **Fix**: `'tree_id' => Tree::factory()`, and `parent_id` / `child_id` as closures `fn (array $attributes) => Person::factory()->create(['tree_id' => $attributes['tree_id']])->id`.
- **Decision**: FIXED — lazy `Tree::factory()`, people created in `$attributes['tree_id']`

### F5 — Pair lock leaves a 4-person cycle race; plan.md overstates atomicity

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Relations/CreateParentChildRelation.php:23
- **Detail**: Locks cover only the two people of the request. With existing B→C and D→A, concurrent A→B (locks A,B) and C→D (locks C,D) both pass the cycle check and commit A→B→C→D→A. Double-submit, third-parent and 3-node cycle races are correctly serialized. `plan-brief.md:62` accepts this risk for a single-user tree, but `plan.md:50` says the lock makes "the rules and the write atomic".
- **Fix**: Lock the tree row at the start of the transaction (`Tree::whereKey($tree->id)->lockForUpdate()->first()`) instead of the two people.
  - Strength: Serialises every relation write per tree, closing all cycle and parent-count races with one line; contention is nil for a single-user tree.
  - Tradeoff: Coarser lock than the plan designed; the person locks become redundant.
  - Confidence: HIGH — Postgres READ COMMITTED re-reads committed rows after the lock is acquired, which is what the current design already relies on.
  - Blind spot: Not verified whether anything else will later lock the tree row (deadlock ordering).
  - Alternative: keep as-is and just correct `plan.md:50` to match the accepted risk.
- **Decision**: FIXED via Fix B — code unchanged; plan.md Critical Implementation Details now states the accepted 4-person cycle race

### F6 — Rare races surface as 500 instead of a validation error

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Relations/CreateParentChildRelation.php:23
- **Detail**: Lock results are ignored: if a person is deleted between the controller's `findOrFail` and the lock, the insert hits an FK violation (500). The unique index is described as "the last safety net", but a `UniqueConstraintViolationException` would also be a 500 — sibling `EventScanController.php:20-24` catches it.
- **Fix**: Check the lock returns both rows (`abort_if(... === null, 404)`) and catch `UniqueConstraintViolationException` → `ValidationException(['relation' => 'Ta relacja już istnieje.'])`.
- **Decision**: FIXED — locks use `firstOrFail()` (404), `UniqueConstraintViolationException` → `relation` "Ta relacja już istnieje."

### F7 — No index on `parent_child_relations.tree_id`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_09_25_061809_create_parent_child_relations_table.php:16
- **Detail**: Postgres does not index FK columns automatically; `ParentChildGraph::forTree` filters by `tree_id` on every create and every picker open. `people` and `scans` both lead a composite index with `tree_id`. Negligible at personal-tree scale.
- **Fix**: Add `$table->index('tree_id')` (edit the migration if not yet deployed, otherwise a new migration).
- **Decision**: FIXED — `index(tree_id)` added to the unpushed migration; dev DB rolled back and re-migrated (dev relation rows dropped, as agreed)

### F8 — "Usuń relację" buttons lack an accessible name

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/relatives-card.tsx:81
- **Detail**: Every row's trigger reads just "Usuń relację"; screen-reader users can't tell rows apart. The sibling `event-card.tsx:75` uses `aria-label={\`Odepnij skan ${scan.original_name}\`}`.
- **Fix**: Add `aria-label={\`Usuń relację z ${relative.display_name}\`}` to the trigger.
- **Decision**: FIXED — `aria-label="Usuń relację z {display_name}"` on each trigger

### F9 — PersonResource docblock summary dropped; relatives tie-break by byte order

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Resources/PersonResource.php:17
- **Detail**: The diff removed "Transform the resource into an array." — every other resource (incl. new `RelativeResource`) keeps it. Separately, `sortRelatives` breaks same-date ties with `<=>`, so "Łukasz" sorts after "Zofia", unlike the collation-ordered picker; only affects relatives sharing an identical birth date.
- **Fix**: Restore the docblock line; optionally compare names with `Collator('pl_PL')` or `strcoll`.
- **Decision**: FIXED — docblock summary restored; byte-order name tie-break left as is

### F10 — Person-deletion test bypasses DeletePerson

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ParentChildRelationsTest.php:188
- **Detail**: The test calls `$middle->delete()` directly. It proves the FK cascade but not the user path (`DELETE persons.destroy` → `DeletePerson`).
- **Fix**: Replace with `$this->actingAs($user)->delete(route('persons.destroy', $middle))`.
- **Decision**: FIXED — test deletes through `persons.destroy` and asserts the person is gone

## Triage Summary

- Fixed: F1, F2, F3, F4, F6, F7, F8, F9, F10 (9)
- Fixed via Fix B (plan wording): F5 (1)
- Post-fix verification: relation + persons + event-scan tests 70/70, pint, types:check, npm run check pass; migration rollback/migrate round-trip clean; ordering test proven to fail with the sort removed.
