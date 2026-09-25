# Person Search Implementation Plan

## Overview

Roadmap slice S-04 (FR-009, US-01). A logged-in user can type part of a name into a search box on the persons list, see the list narrowed to matching people from their own tree, and click through to a person's preview. The filter lives in the URL (`/persons?q=…`), so it survives a refresh and the Back button from the preview.

Matching rule (agreed): the query is split on whitespace; **every** word must be contained (anywhere, not only at the start) in `first_name` **or** `last_name`; comparison ignores case but **not** diacritics. So `kowalski jan` and `Jan Kowalski` both find Jan Kowalski, `owal` finds Kowalski, `JAN` finds Jan, `łukasz` finds Łukasz, but `lukasz` does not.

## Current State Analysis

- `PersonController::index` returns every person of the user's tree, unpaginated, eager-loading `events`, ordered by `last_name is null`, `last_name`, `first_name`, `id` (`app/Http/Controllers/PersonController.php:26-38`). Tree scoping comes from `$request->user()->tree->people()`.
- `resources/js/pages/persons/index.tsx` renders a heading with "Dodaj osobę", an empty-tree `Card` when `persons` is empty, otherwise a grid of `Card` links to `PersonController.show(person.id)`.
- Names are two nullable columns (`first_name`, `last_name`); `displayName()` substitutes `NN` for a missing part, but `NN` is not stored (`app/Models/Person.php:71-74`).
- Database is Postgres 18 in dev, tests (`phpunit.xml:26` → `testing` DB) and production (`docker-compose.prod.yml:38`). Both local databases use libc locale `en_US.utf8`; verified: `'ŁUKASZ Żółkiewski' ILIKE '%łukasz żółk%'` is true, `'Łukasz' ILIKE 'lukasz'` is false, and `\_` / `\%` escape wildcards.
- Laravel 13.32 `whereLike()` / `orWhereLike()` compile to `ilike` on Postgres (`vendor/laravel/framework/src/Illuminate/Database/Query/Grammars/PostgresGrammar.php:93-100`) and do **not** escape `%`, `_` or `\` in the value; the framework has no LIKE-escape helper.
- Wayfinder's `PersonController.index()` accepts `{ query: {...} }` (`resources/js/actions/App/Http/Controllers/PersonController.ts:23`).
- Shared `Input` component exists (`resources/js/components/ui/input.tsx`); no debounce library in `package.json`; no JS test runner — Pest (`tests/Feature/PersonsTest.php`) is the only automated test surface.
- PRD Non-Goals exclude searching spelling variants of names (`context/foundation/prd.md:149`). No `.ai/rules` directory and no `context/foundation/lessons.md`.

## Desired End State

- `/persons` shows a search box ("Szukaj po imieniu lub nazwisku") above the list whenever the tree has at least one person or a search is active.
- Typing updates the URL to `/persons?q=<text>` after a short pause (~300 ms) and the list shows only matching people, in the same order as today. The input keeps focus and its text while results update; no new browser history entry is created per keystroke.
- Clearing the box (or entering only spaces) removes `q` from the URL and shows everyone.
- With an active search and no matches, the page shows "Nie znaleziono osób pasujących do „<q>”." instead of the empty-tree card.
- Clicking a result opens the person's preview; Back returns to `/persons?q=<text>` with the box filled and the same results.
- Wildcard characters are literal: `%` or `_` in the query match only those characters.
- Another user's people are never returned, whatever the query.

### Key Discoveries:

- Tree scoping is already in the base query (`PersonController.php:27`); the search only adds conditions to it, so the existing isolation test pattern (`tests/Feature/PersonsTest.php:23-43`) extends naturally.
- `NULL ILIKE '%x%'` is `NULL` (not true), so a missing first or last name simply doesn't match that column — no special case needed.
- Index tests assert via `assertInertia(... ->has('persons', n)->where('persons.i.id', ...))` (`tests/Feature/PersonsTest.php:36-42`); new tests follow that style.

## What We're NOT Doing

- No diacritic folding (`lukasz` does not find `Łukasz`), no `unaccent` extension, no spelling-variant matching (PRD Non-Goals).
- No matching on birth/death year, place, residence or religion — names only.
- No prefix-only mode, relevance ranking or result highlighting; order stays last name → first name → id.
- No pagination or result limit.
- No search on other pages (scans, scan picker) and no global/header search.
- No new frontend dependency (debounce is a local timer).
- No UI restyle or new design tokens; the box reuses the existing `Input`.

## Implementation Approach

Server-side filtering keyed by a `q` query parameter: the controller normalizes `q`, turns each word into an escaped `%word%` pattern and adds one `(first_name ILIKE p OR last_name ILIKE p)` group per word. The page receives `filters.q` back so the input can be restored. The frontend owns only the input state and a debounced Inertia GET that preserves component state. Phase 1 is fully provable by Pest before any UI exists; Phase 2 adds the box and the no-match state.

## Critical Implementation Details

- **LIKE escaping:** each word must have `\` escaped first, then `%` and `_`, before being wrapped in `%…%`; otherwise `%` matches everyone and `_` any character. Postgres' default LIKE escape character is `\`, and the value is a bound parameter, so no `ESCAPE` clause is needed.
- **Visit loop / focus:** the debounced effect must skip the request when the trimmed input equals `filters.q` (initial mount and Back navigation would otherwise re-issue a visit), and the visit must use `preserveState: true` so the input is not remounted and does not lose focus or keystrokes typed during the request.

## Phase 1: Server-side name filter

### Overview

`GET /persons?q=…` returns only the user's people whose names match every word of `q`, and echoes the normalized query as `filters.q`.

### Changes Required:

#### 1. Person index filtering

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: Read `q` from the query string, normalize it, and constrain the existing tree-scoped query so each word matches `first_name` or `last_name` case-insensitively as a substring. Keep eager loading and ordering unchanged. Pass the normalized query to the page so the input can be restored.

**Contract**:
- Input: `q` query parameter. A non-string value (e.g. `q[]=x`) is treated as empty. The string is trimmed and truncated to 100 characters; words are split on runs of whitespace (`preg_split('/\s+/u', …)`), empty words dropped.
- Per word: one grouped `where(fn ($query) => $query->whereLike('first_name', $pattern)->orWhereLike('last_name', $pattern))`, groups combined with AND.
- Props: `persons` (unchanged shape) plus `filters: { q: string }`, where `q` is the trimmed/truncated string (`''` when absent).
- Escaping (the one non-obvious bit):

```php
$pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';
```

Keep the parsing and pattern building readable inside `index` or a small private method on the controller; no new class is needed for this size.

#### 2. Feature tests

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Prove the agreed matching rules and the failure modes that would leak data or crash, following the existing `assertInertia` index test style.

**Contract**: New tests (names indicative):
- `persons index filters by every query word against first or last name in any order` — tree with Jan Kowalski, Anna Kowalski, Jan Nowak: `q=kowalski jan` → only Jan Kowalski; `q=JAN` → Jan Kowalski and Jan Nowak in list order (proves case-insensitivity and ordering).
- `persons index matches a query word anywhere inside a name` — `q=owal` finds Kowalski.
- `persons index ignores case but not polish diacritics` — person Łukasz Żółkiewski: `q=ŁUKASZ żółk` finds him, `q=lukasz` returns none.
- `persons index treats percent and underscore in the query literally` — `q=%` and `q=_` return none when no name contains them.
- `persons index search never returns people from another users tree` — other tree has a matching name; only own match returned.
- `persons index with an empty, blank or non-string query returns everyone and echoes filters.q` — `q=`, `q=%20%20`, `q[]=x` → all people, `filters.q` is `''`; `q=%20Jan%20` echoes `Jan`.

### Success Criteria:

#### Automated Verification:

- Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Code style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Opening `/persons?q=<part of a real surname>` in the browser returns only matching people (list still renders without the search box)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Search box on the persons list

### Overview

The persons list gets a debounced search box bound to `?q=`, restored from `filters.q`, with a distinct no-match state.

### Changes Required:

#### 1. Persons index page

**File**: `resources/js/pages/persons/index.tsx`

**Intent**: Add a search `Input` between the heading row and the list, hold its value in local state initialized from `filters.q`, and after ~300 ms without typing issue an Inertia GET to the index with the trimmed value (omitting `q` when empty). Split the empty state: empty tree vs. no matches for an active query.

**Contract**:
- `Props` gains `filters: { q: string }`.
- Input: `type="search"`, `placeholder="Szukaj po imieniu lub nazwisku"`, `aria-label="Szukaj osoby"`, `maxLength={100}`, shown when `persons.length > 0 || filters.q !== ''`.
- Visit: `router.get(PersonController.index({ query: … }), {}, { preserveState: true, preserveScroll: true, replace: true })` via Wayfinder; skipped when the trimmed value equals `filters.q`. Clear the pending timer on unmount/change.
- Empty states: `persons.length === 0 && filters.q === ''` → existing card copy unchanged; `persons.length === 0 && filters.q !== ''` → muted card "Nie znaleziono osób pasujących do „{filters.q}”."
- No new files and no new dependency; the local `setTimeout` lives in the page (extract to `resources/js/hooks/` only if it ends up non-trivial).

### Success Criteria:

#### Automated Verification:

- Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Frontend types compile: `vendor/bin/sail npm run types:check`
- Frontend lint/format check passes: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`

#### Manual Verification:

- Typing part of a name narrows the list after a short pause, the URL shows `?q=…`, and the input keeps focus and every typed character
- `kowalski jan` and `Jan Kowalski` give the same result; clearing the box shows everyone and removes `q` from the URL
- A query with no matches shows "Nie znaleziono osób pasujących do „…”."; an empty tree still shows the original empty-tree card without a search box
- Clicking a result opens the preview; Back returns to the filtered list with the box filled, and typing does not add one history entry per keystroke

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- None; matching is database behavior and is covered by feature tests against the real Postgres test DB.

### Integration Tests:

- The six Phase 1 feature tests in `tests/Feature/PersonsTest.php` cover multi-word AND matching in any order, substring, case-insensitivity including Polish letters, diacritic sensitivity, literal wildcards, tree isolation, and blank/non-string `q`.
- Existing index tests (sorting, payload shape) must keep passing unchanged.

### Manual Testing Steps:

1. On `/persons` with several people, type a fragment of a surname and confirm the list narrows and the URL updates.
2. Type two words in reverse order (surname first) and confirm the same result.
3. Type `lukasz` with a person named Łukasz and confirm no match (agreed behavior), then `Łukasz` and confirm the match.
4. Open a result, press Back, confirm the filter and results are restored.
5. Clear the box, confirm everyone is back and `q` is gone from the URL.

## Performance Considerations

`%word%` ILIKE cannot use a B-tree index, so each search is a sequential scan of the user's `people` rows. At the PRD's scale (one author, a tree of hundreds of people) this is negligible; no index or trigram extension is added. The debounce keeps request count to roughly one per typing pause.

## Migration Notes

None — no schema change. Assumption to confirm on production: its database was initialized by the same `postgres:18-alpine` image without a locale override, so `ILIKE` folds Polish letters as it does locally (check with `SHOW lc_ctype;` → `en_US.utf8`).

## References

- Roadmap item: `context/foundation/roadmap.md` (S-04 `person-search`)
- Requirement: `context/foundation/prd.md:80` (FR-009), `context/foundation/prd.md:46-50` (US-01), `context/foundation/prd.md:149` (no spelling variants)
- Current list: `app/Http/Controllers/PersonController.php:26-38`, `resources/js/pages/persons/index.tsx`
- Test style: `tests/Feature/PersonsTest.php:23-43`
- Previous slice plan: `context/archive/2026-09-25-person-edit-and-delete/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Server-side name filter

#### Automated

- [x] 1.1 Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php` — eb2f540
- [x] 1.2 Static analysis passes: `vendor/bin/sail composer types:check` — eb2f540
- [x] 1.3 Code style is clean: `vendor/bin/sail bin pint --dirty --format agent` — eb2f540

#### Manual

- [x] 1.4 Opening `/persons?q=<part of a real surname>` in the browser returns only matching people (list still renders without the search box) — eb2f540

### Phase 2: Search box on the persons list

#### Automated

- [x] 2.1 Person feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php` — 37453fe
- [x] 2.2 Frontend types compile: `vendor/bin/sail npm run types:check` — 37453fe
- [x] 2.3 Frontend lint/format check passes: `vendor/bin/sail npm run check` — 37453fe
- [x] 2.4 Production build succeeds: `vendor/bin/sail npm run build` — 37453fe

#### Manual

- [x] 2.5 Typing part of a name narrows the list after a short pause, the URL shows `?q=…`, and the input keeps focus and every typed character — 37453fe
- [x] 2.6 `kowalski jan` and `Jan Kowalski` give the same result; clearing the box shows everyone and removes `q` from the URL — 37453fe
- [x] 2.7 A query with no matches shows "Nie znaleziono osób pasujących do „…”."; an empty tree still shows the original empty-tree card without a search box — 37453fe
- [x] 2.8 Clicking a result opens the preview; Back returns to the filtered list with the box filled, and typing does not add one history entry per keystroke — 37453fe
