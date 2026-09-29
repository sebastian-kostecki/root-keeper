<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Persons list — List and Table views

- **Plan**: context/changes/persons-list-views/plan.md
- **Scope**: Full plan (4 of 4 phases)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-09-29
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 7 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS (one planned shot missing, see F9) |
| Scope Discipline | PASS (extras are benign: person-list-parts.tsx, PersonAvatar className, exported types) |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated checks re-run: PersonsTest 79/79, Marriages+ParentChild+EventScans 100/100, phpstan 0 errors, pint pass, types:check pass, `npm run check` pass, Vitest 70/70, build OK. Manual items 3.6–3.11 and 4.3 are ticked; evidence is the screenshots + verdict.md (desktop/390 px) — keyboard focus-visible was not captured (verdict admits this).

## Findings

### F1 — clearSearch triggers a duplicate search request

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/persons/index.tsx:82-115
- **Detail**: `clearSearch` calls `setSearch('')` then `visitSearch({q:''})`. Until the visit finishes, `filters.q` is still the old value, so the debounce effect schedules a second identical `router.get` 300 ms later (cancels the first if still in flight → spinner flicker). The effect also duplicates the visit options of `visitSearch`.
- **Fix**: Make the effect call the shared `visitSearch` helper, and have `clearSearch` only `setSearch('')` (let the effect visit), or guard the effect against a visit already in flight.
- **Decision**: FIXED via Fix now — effect calls visitSearch; lastVisitedQuery ref stops the duplicate visit after clearSearch

### F2 — Index eager-loads every event type

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PersonController.php:36
- **Detail**: `events` is loaded unconstrained with `withExists('scans')`, including marriage events; `Person::birth()/death()` use only birth/death. More rows and EXISTS subqueries than needed on large trees.
- **Fix**: Constrain to `whereIn('type', [EventType::Birth, EventType::Death])`. Confirm nothing on the index payload reads other events first.
- **Decision**: FIXED via Fix now — eager load constrained to Birth/Death

### F3 — O(n²) array copy in groupBySurnameInitial

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/persons-index.ts:98
- **Detail**: `byInitial.set(initial, [...(byInitial.get(initial) ?? []), person])` copies the group on every insert.
- **Fix**: Get-or-create the array and `push`.
- **Decision**: FIXED via Fix now — get-or-create + push

### F4 — isSearching can be cleared by an interrupted search

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/persons/index.tsx:48,61-62,103-104
- **Detail**: If a view/sort visit (`only:['filters']`, no callbacks) cancels an in-flight search, that search's `onFinish` clears the spinner/`aria-busy`; the effect re-issues the search, so it self-heals after the debounce gap. Overlapping searches: last `onFinish` wins (Inertia ordering not verified).
- **Fix**: Track the latest search visit with a counter/token and clear `isSearching` only from it.
  - Strength: Removes flicker and a wrong `aria-busy`.
  - Tradeoff: Small extra state; cosmetic in practice.
  - Confidence: MED — Inertia's cancelled-visit `onFinish` ordering unverified.
  - Blind spot: Could be reproduced only by switching view mid-search.
- **Decision**: FIXED via Fix now — latestSearchVisit token owns the spinner; onCancel of the latest visit lets the effect re-issue

### F5 — search input not resynced with filters.q on back/forward

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/persons/index.tsx:47
- **Detail**: `search` is initialised once from `filters.q`. After history navigation changing `q`, the input keeps old text and the effect re-issues the old search. Search uses `replace:true`, which limits exposure, but view toggles push history entries.
- **Fix**: Resync `search` from `filters.q` when it changes and differs from the last value the user typed (or key the input on `filters.q`).
  - Strength: Back/forward behave consistently.
  - Tradeoff: Must not fight typing mid-debounce.
  - Confidence: MED — not reproduced in browser.
  - Blind spot: Interaction with `preserveState`.
- **Decision**: FIXED via Fix now — effect resyncs the input from filters.q on history navigation

### F6 — visitDisplay drops `person` from URL while preview props stay

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/persons/index.tsx:68-79
- **Detail**: With `only:['filters']`, `personPreview` is untouched but the URL loses `?person=`. Likely unreachable because the modal overlay blocks the toolbar.
- **Fix**: Skip or accept; if desired, keep `person` in the URL for display visits.
- **Decision**: SKIPPED

### F7 — Avatar sr-only gender text precedes the name in link name

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/person-avatar.tsx (sr-only label)
- **Detail**: Accessible link name reads "Kobieta Jan Kowalski Probant".
- **Fix**: Place gender text after the name, or omit it inside rows where the name is present.
- **Decision**: FIXED via Fix now — PersonAvatar `announceGender` prop, off inside list/table links

### F8 — No live region for result count / no-results

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/persons/index.tsx:136-149
- **Detail**: Screen-reader users aren't told the outcome of a search; the spinner status exists only while searching.
- **Fix**: Add an sr-only `aria-live="polite"` count line.
- **Decision**: FIXED via Fix now — sr-only aria-live count; peopleCount moved to lib/persons-index.ts

### F9 — dark 390 px empty-tree shot missing from the gate

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/persons-list-views/screenshots/shots.mjs:119
- **Detail**: Plan asks for light/dark at desktop and 390 px; `after/` has no `dark-mobile-empty-tree.png`. verdict.md acknowledges "light/dark desktop, light mobile", so it is documented but not a plan-conformant gate.
- **Fix**: Add `emptyTree('dark', 390, …)` to shots.mjs and re-run into `after/` (only `after/` is written).
- **Decision**: FIXED via Fix now — dark 390 px empty-tree shot added, after/ re-run, verdict.md updated

### F10 — Hidden contract between EventResource and controller

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Resources/EventResource.php (~40)
- **Detail**: `is_confirmed` depends on the `scans_exists` attribute set by `withExists('scans')` in the controller. Tested, but undocumented.
- **Fix**: Add a PHPDoc line on the `is_confirmed` branch naming the `withExists('scans')` requirement.
- **Decision**: FIXED via Fix now — PHPDoc on EventResource::toArray
