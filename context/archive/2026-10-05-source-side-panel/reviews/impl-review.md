<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Źródło w szerokim panelu bocznym (S-02)

- **Plan**: context/changes/source-side-panel/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-10-05
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated verification (run during this review):
- Pest: Localities, Sources, DeleteImpact, Marriages, Persons, EventSources, OwnershipBoundaries, BackupRestore. 311 passed, 2169 assertions.
- `npm run check`: pass. `types:check`: pass. Vitest: 151/151 passed. `npm run build`: pass. Larastan: 0 errors. Pint: pass.
- Phase 4 grep for leftover references: none found.
- E2E: `source-panel-edit` and `fact-source-scan` with `--repeat-each=2` (5 passed, including setup). Full suite: 7 passed.

## Findings

### F1 — Debounced search visit can cancel the panel visit

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/sources/index.tsx:100-118 (same pattern in resources/js/pages/localities/index.tsx:84)
- **Detail**: Clicking a card shows nothing on screen, because the panel does not open, when the user clicks within 300 ms of the last keystroke in the search box. The steps:
  1. The click does not clear the debounce timer. The effect's dependencies (`search`, `filters.*`) do not change until the panel visit returns.
  2. The timer then fires `router.get(... only: ['filters'])`. That address is built from `window.location`, which does not have `?source=` yet.
  3. This second sync Inertia visit cancels the panel visit still in flight.

  The E2E test does not hit this. `openSourceFromList` waits for the status text, which follows `filters.q` from the server, so the click always comes after the debounce has landed. The defect was inherited from the localities page; this change did not introduce it.
- **Fix**: Before a visit that opens a panel, flush the pending search. Cancel the timer, then carry the typed `q` into the panel address, for example `withSourcesQuery(hrefFor(id), { q: trimmedSearch })`. Do the same in the localities page.
  - Strength: Closes the race at its source, with no change to how the filters themselves behave.
  - Tradeoff: Touches both lists and needs the timer in a ref. The shared `guardLink` path changes.
  - Confidence: MED. The cancel-on-new-sync-visit behaviour follows from Inertia; it was not reproduced in a browser.
  - Blind spot: Not reproduced manually. Not checked how prefetching on `Link` interacts with this.
- **Decision**: FIXED. Both lists got `openFromList`: when a search is still waiting for its debounce, a click on a list link cancels the timer and sends one visit with the panel and `q` (`only` plus `filters`). Reproduced with a temporary Playwright spec that delays the panel response by 800 ms: before the fix the panel did not appear, after it the panel opens and the address has `source` and `q`. The spec was then removed. check, types, Vitest 151/151 and E2E 7/7 pass.

### F2 — `listQuery()` does not keep the plan's "no Referer, no filters" promise

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Http/Controllers/SourceController.php:78
- **Detail**: The plan, under Critical Implementation Details, says: "Gdy poprzedni adres … go brak (np. `page.request` w E2E), filtrów nie ma." The code does not do that:
  - `url()->previous()` falls back to `_previous.url` in the session when there is no Referer (`UrlGenerator::previous`). A `store` or `destroy` call without a Referer, from `page.request` or a Pest test with no `from()`, can pick up filters left by an earlier full GET of `/sources?kind=…` in the same session.
  - The E2E helper uses `/^\/sources$/` instead of the planned `/^\/sources\?source=\d+$/` (`tests/e2e/support/api.ts:181`). That change was forced, because `expectRedirect` compares only the pathname, but it means the "no filters in the redirect" guarantee is no longer asserted anywhere.
  - Separately, the path `'/sources'` is hardcoded and the host is not checked, unlike `PersonController::urlAfterDelete:139-164`. Every value still goes through the existing `normalize*` methods, so this is not an open redirect.
- **Fix**: Read `request()->headers->get('referer')` instead of `url()->previous()`. Compare the path with `parse_url(route('sources.index'), PHP_URL_PATH)` and check the host as `urlAfterDelete` does. Add a Pest case: GET `/sources?kind=akty`, then `store` without `from()`, and expect only `?source={id}`.
- **Decision**: FIXED.
  - `listQuery(Request $request)` reads only the `referer` header and compares the path with `route('sources.index')`. A Referer that names a host is accepted only when the host matches `$request->getHost()`.
  - `destroy` takes `Request`.
  - Two new Pest tests were red before the fix: "storing without a referer…" (a GET of `/sources?kind=akty&view=tabela` first, then `store`) and "…on another host". Both pass after it.
  - Sources, DeleteImpact, OwnershipBoundaries and EventSources: 167/167. Larastan and Pint are clean.
  - The E2E helper is unchanged; the guarantee now lives in Pest.

### F3 — Guarded action uses the address from an older render

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/hooks/use-unsaved-changes-guard.ts:21, resources/js/hooks/use-side-panel-navigation.ts:21-35
- **Detail**: `guard` stores `() => open(id)` or `close`. Both build the address from `usePage().url` as it was when the user clicked. If a debounced `q` visit finishes while "Odrzucić niezapisane zmiany?" is open, choosing "Odrzuć" goes to the old address. The `q` filter goes back and the typed search is lost. Form data is not affected.
- **Fix**: In `useSidePanelNavigation`, build the address from `window.location` when the action runs, the way `visit()` in `sources/index.tsx` already does.
- **Decision**: FIXED. `open` and `close` in `useSidePanelNavigation` now build the address with `liveUrl()` from `window.location` when they run. `hrefFor`, used for link hrefs, still reads `usePage().url`. check, types, Vitest 151/151 and E2E 7/7 pass.

### F4 — Demo page `/dev/side-panel` changed its dialog wording

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/dev/side-panel.tsx:33-54
- **Detail**: The plan said both consumers would be rewired with no visible change. On the demo page the dialog changed from "Porzucić zmiany?" / "Porzuć zmiany" to the shared "Odrzucić niezapisane zmiany?" / "Odrzuć". The dirty checkbox also moved into `UnsavedChangesToggle`. This is the natural result of sharing the dialog, and no test refers to the old wording.
- **Fix**: Accept, and add one line to the plan as an addendum.
- **Decision**: FIXED. Accepted; an addendum was added to plan.md under phase 1, step 3 ("Przepięcie konsumentów").

### F5 — New source can open while its card is hidden by the filter

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/SourceController.php:186
- **Detail**: After `store`, the `kind` and `q` filters are kept, as the plan requires. Adding a gravestone while the filter is `kind=akty` therefore opens the new source's panel, but its card is not on the list, so the open-card highlight is not visible. This is a consequence of the plan's own decision, not a drift.
- **Fix**: Accept as intended. If not, drop `kind` and `q` from the redirect after `store` when the new source does not match them.
- **Decision**: FIXED.
  - `SourceController::filtersShowing()` drops `kind` and `q` from the redirect after `store` when they would hide the new source. It uses the same rule as `filterSources`.
  - New Pest test (red before the fix): a gravestone "Nagrobek Nowak" stored from `?view=tabela&kind=akty&q=Kraków&sort=nazwa` redirects with `view` and `sort` only.
  - In the "keeps the list filters" test, the stored source is now named `Akt Kraków 1890`, so `q` still applies. Its assertion is unchanged.
  - Plan addendum added. Pest: 312/312, and Larastan is clean.
