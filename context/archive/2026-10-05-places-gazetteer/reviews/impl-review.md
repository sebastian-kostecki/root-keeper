<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spis miejscowości z istniejących miejsc (S-01)

- **Plan**: context/changes/places-gazetteer/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-05
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated verification (2026-10-05):
- Pest: 66/66 tests of the change; `BackupRestoreTest` 2/2.
- Larastan: 0 errors. Pint: clean.
- Vitest: 145/145.
- `vp check`: clean. `tsc`: clean. Build: passed.
- Both migrations ran locally.

Manual Progress rows are ticked with commit SHAs; the diff cannot prove them, and nothing contradicts them as worded.

## Findings

### F1 — Searching drops `?locality=` from the URL

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/localities/index.tsx:57-69
- **Detail**: `visit()` builds the URL from `LocalityController.index({ query: { q } })` alone, with `only: ['filters']`. While a panel is open, the address loses `locality=<id>` but the stale `localityPanel` prop keeps the panel on screen. As a result:
  - A refresh or a shared link loses the panel.
  - After an edit, "Zapisz" → `back()` uses a Referer without the key, so the full reload closes the panel.
  - "Wyczyść wyszukiwanie" does the same.

  This breaks the plan's promise that saving refreshes the panel (Desired End State, manual 3.8) as soon as the author searches first. The page is the first in the repo to combine search with a panel, so no existing page handles this.
- **Fix**: Build the search URL from the current page URL (`usePage().url`): set or remove only `q` and keep the panel keys.
  - Strength: restores the plan's invariant that the panel state lives in the URL; `withPanel` already uses the same "change one parameter" approach.
  - Tradeoff: a small helper, e.g. `withQuery(url, 'q', value)` beside `withPanel`, needs a Vitest case.
  - Confidence: HIGH — confirmed in code at `index.tsx:60-68`.
  - Blind spot: the Pest suite cannot cover the browser path; check it manually (search → edit → save → panel stays open).
- **Decision**: FIXED — search URL built from the live address via `withLocalitiesSearch` (`resources/js/lib/localities-index.ts`), Vitest cases added

### F2 — "Odrzuć" on the same record leaves the form dirty and turns the guard off

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/localities/index.tsx:104-126; resources/js/components/localities/locality-panel.tsx:28-32,52
- **Detail**: The failure shows when the author, with a dirty form, clicks the row of the locality that is already open, or "Dodaj miejscowość" while the "new" panel is dirty:
  1. `discard()` sets `isDirty=false` and runs `open(sameId)`.
  2. The `<Form>` key does not change, so the form keeps the edited values and its own `isDirty` stays `true`.
  3. `DirtyReporter` only fires when `isDirty` changes, so the page stays at `false`.
  4. The next close, Escape or row click drops the edits with no prompt, and "Odrzuć" visibly did not discard anything.
- **Fix A ⭐ Recommended**: A click on the already-open id, or "Dodaj" while the "new" panel is open, does nothing: `preventDefault` and no guard.
  - Strength: removes the trigger; there is nothing to "open" anyway, and the change is a few lines in `guardLink` and the "Dodaj" handler.
  - Tradeoff: a "discard" through a click on the same row is no longer possible; the user still has the close button.
  - Confidence: HIGH — the only path that leaves the key unchanged is the same id.
  - Blind spot: none significant.
- **Fix B**: On discard, reset the form, e.g. with a reset counter in the `<Form>` key, instead of forcing `isDirty` from outside.
  - Strength: "Odrzuć" always really discards, whatever the path.
  - Tradeoff: extra state passed between the page and the panel; the form remounts and focus is lost.
  - Confidence: MED — depends on wiring the counter into `key`/`contentKey`.
  - Blind spot: not checked whether the `SidePanel` remount moves focus well.
- **Decision**: FIXED — via Fix A: a click on the already-open record (or "Dodaj" while the new panel is open) is a no-op in `guardLink`

### F3 — A unique-index violation (race) becomes a 500

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Localities/StoreLocality.php:15; app/Actions/Localities/UpdateLocality.php:14
- **Detail**: Uniqueness is checked only by the validation closure. Two concurrent requests (two tabs, a double click) pass validation and hit `localities_tree_id_lower_name_unique`. The resulting `UniqueConstraintViolationException` is not handled. The repo already handles this case in `app/Actions/Relations/CreateParentChildRelation.php:44-51`.
- **Fix**: In both actions, catch `UniqueConstraintViolationException` → `ValidationException::withMessages(['name' => 'Miejscowość o tej nazwie jest już w spisie.'])`.
- **Decision**: FIXED — both actions catch `UniqueConstraintViolationException` inside `DB::transaction` and throw a `name` validation error; test in `LocalitiesTest`

### F4 — An array `name` reaches the DB query and gives a 500

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Concerns/LocalityValidationRules.php:19-33
- **Detail**: The `name` rules have no `bail`, so after `string` fails the closure still runs with the array. `whereRaw('lower(name) = lower(?)', [$value])` with `name[]=a&name[]=b` then throws `SQLSTATE[HY093]`, which becomes a 500. The value is bound, so this is not an injection, just an error page.
- **Fix**: Add `'bail'` at the start of the `name` rules (or `if (! is_string($value)) return;` in the closure), plus an "array name" case in the validation dataset of `LocalitiesTest`.
- **Decision**: FIXED — `'bail'` added to the `name` rules; "array name" case in the validation dataset

### F5 — Adding a locality clears the active search

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/LocalityController.php:76
- **Detail**: `to_route('localities.index', ['locality' => $id])` drops `q`, so after "Zapisz" for a new locality the search box empties (effect at `index.tsx:73-78`).
- **Fix**: Pass the current `q` from the Referer or the form into `to_route`, or accept it as intended.
- **Decision**: SKIPPED

### F6 — `LocalitiesTest` has no tests for `normalizeSearchQuery` or the partial reload

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/LocalitiesTest.php
- **Detail**: The search normalization was copied from `SourceController`, but its tests were not:
  - trimming, the 100-character limit and a non-string `q` → `''` (`SourcesTest.php:801`);
  - a partial reload of `filters` running no list query (`SourcesTest.php:821`).
- **Fix**: Copy both cases into `LocalitiesTest` for `localities.index`.
- **Decision**: FIXED — `LocalitiesTest` covers `q` normalization (trimming, 100 characters, array) and a partial reload of `filters` with an open panel and no localities query

### F7 — Actions take positional arguments instead of the planned `array $attributes`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Localities/StoreLocality.php:13; app/Actions/Localities/UpdateLocality.php:12
- **Detail**: The plan expected `handle(Tree, array $attributes)` with a PHPDoc array shape. The code has `handle(Tree $tree, string $name, ?string $description)`. The plan's intent is kept: the tree comes from the user, and an empty description becomes NULL. Another harmless drift: the factory uses a list of Polish names plus `numerify` instead of `fake()->unique()->city()`.
- **Fix**: Accept it and note it as an addendum in the plan; the signature is clearer for two fields.
- **Decision**: FIXED — addendum in the plan (Phase 1 §2, Phase 2 §4)

### F8 — The S-01 → `in-progress` change in the roadmap is not committed

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:47,133
- **Detail**: The working tree has S-01 with `Status: in-progress` (the table and the item body), but none of the commits b7d0b50, 2a1c747, 3103f6b or ed6fbd1 includes it. The `/10x-plan` convention is that the roadmap change lands with phase 1.
- **Fix**: Commit `roadmap.md` together with the review fixes, or let `/10x-archive` switch it to `done`.
- **Decision**: ACCEPTED — `roadmap.md` goes into the commit with the review fixes

## Triage Summary

- **Fixed**:
  - F1: search keeps the panel keys.
  - F2: Fix A — a click on the open record is a no-op.
  - F3: unique-index race → `name` validation error.
  - F4: `bail` on `name`.
  - F6: tests for `q` and the partial reload.
  - F7: plan addendum.
- **Skipped**: F5.
- **Accepted**: F8.

Verification after the fixes:
- `LocalitiesTest`: 21/21.
- Vitest for `localities-index`: 7/7.
- Larastan: 0 errors. Pint: clean.
- `vp check` and `tsc`: clean on the changed files.

