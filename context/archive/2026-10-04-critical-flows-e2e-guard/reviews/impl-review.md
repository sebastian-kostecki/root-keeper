<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: F-01 critical-flows-e2e-guard

- **Plan**: context/changes/critical-flows-e2e-guard/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-05
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 6 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Success criteria evidence (2026-10-05):
- `npx playwright test`: 6 passed (37.9s).
- `npx playwright test --repeat-each=2`: 11 passed (1.1m).
- `vendor/bin/sail npm run check`: formatting and lint pass on 159/161 files.
- After both runs the `e2e` DB has 0 rows in `people` and 0 in `sources` with `e2etest`, and the `root-keeper-e2e-sources` volume holds 0 files.

## Findings

### F1 — Sprzątanie w `finally` pomija timeout i może zasłonić prawdziwy błąd

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/support/api.ts:240-263; finally in fact-source-scan.spec.ts:79, tree-slot-parent.spec.ts:111, tree-slot-child.spec.ts:127, tree-slot-spouse.spec.ts:94
- **Detail**:
  - **Timeout.** On a test timeout Playwright abandons the test body and closes the context. The `finally` then runs against a closed context, so `page.request` throws and nothing is deleted.
    - There is no `actionTimeout`, so a slot locator renamed during S-05 waits until the test timeout. Every red run during the refactor would then leave data behind.
  - **Hidden assertion.** If cleanup throws inside `finally`, its error replaces the original assertion error. Locally there are no retries and no trace.
  - **Departs from the rule.** `e2e-quality-rules.md` says to clean up in `afterEach`.
  - **Weak spots in `cleanupBySuffix`:**
    - It has no guard on the suffix. An empty suffix gives `includes('')` = true and `/persons?q=` with no filter, so the whole E2E tree is deleted.
    - Persons are not filtered on the client.
    - Sources are matched only by `display_name`. If S-03 computes the display name, the sweep finds nothing and stays silent.
- **Fix A ⭐ Recommended**: Fixture `suffix` (stable per test) with a teardown that calls `cleanupBySuffix`. Add a guard `suffix.startsWith(`${TEST_PREFIX}-`)`, filter persons by `display_name.includes(suffix)`, and delete sources created through the API by id.
  - Strength: Playwright runs fixture teardown after a timeout as well, and reports body and teardown errors separately. Matches `e2e-quality-rules.md`.
  - Tradeoff: Changes 4 specs and `api.ts`. The suffix can no longer come from `Date.now()` on every call.
  - Confidence: HIGH — standard Playwright fixture mechanism; the 1.63 worker code was checked.
  - Blind spot: Not checked whether a fixture teardown still gets a live `page.request` after a timeout. Probably yes, because fixtures are torn down in reverse order.
- **Fix B**: Keep `finally`. Add the suffix guard and the person filter, and set `actionTimeout` (e.g. 10 s) in `playwright.config.ts`.
  - Strength: Smallest change. A renamed locator fails quickly and cleanup still runs before the test timeout.
  - Tradeoff: The cleanup error still masks the assertion. Departs from `afterEach` in the rules.
  - Confidence: MEDIUM — depends on the action timeout being shorter than the test.
  - Blind spot: `expect` without an explicit timeout still uses the global one.
- **Decision**: FIXED (Fix A)
  - New `tests/e2e/support/fixtures.ts` with the `suffix` fixture. Its teardown has its own 30 s timeout and goes to `about:blank` before cleanup, so an abandoned test body cannot create a record after the sweep (this was reproduced: a leftover `Nowe` before the change).
  - `api.ts`:
    - a guard on the `e2etest-` prefix;
    - a person filter by `display_name`;
    - sources created through the API are registered and deleted by id.
  - The 4 specs no longer use `try/finally`.
  - `test-plan.md` §6.6 is updated.
  - Verification:
    - suite 6/6;
    - `--repeat-each=2` 11/11;
    - forced timeouts 4/5/6/8 s on the child and parent specs (fact-source-scan passes within 4 s): the DB ends with 0 people and 0 sources;
    - `npm run check` passes.

### F2 — Pomocniki zapisu uznają 302 walidacji za sukces; DELETE idzie za przekierowaniem ze wspólnej sesji

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/support/api.ts:99-101 (expectRedirect), :226-232 (deleteRecord)
- **Detail**:
  - **Validation redirect accepted.** `expectRedirect` accepts any 301/302/303, and a Laravel validation failure is also a 302 `back()`. `page.request` sends no `Referer`, so `back()` goes to the session's `_previous.url`.
    - That session is shared by all parallel tests.
    - `createPerson` can then read `?person=X` from another test's page and work on someone else's person.
    - `linkSource` (`back()`) cannot tell success from failure at all.
    - This will bite when S-07 changes validation of `birth[place]`.
  - **Delete follows a shared-session redirect.** `deleteRecord` follows the redirect from `PersonController::urlAfterDelete`, which is also built from `url()->previous()`. `toBeOK` then checks another test's page, and if that page was just deleted the result is a 404 and the cleanup goes red.
    - The same redirect-following also hides an expired session: `/login` → 200.
- **Fix**: Send `Accept: application/json` on writes: validation returns 422, success stays a 302. Check the Location path (`/tree/\d+`, `/sources/\d+`). In `deleteRecord` use `maxRedirects: 0` and expect a 302 instead of following it.
  - Strength: Removes the dependency on the shared session; the create helpers already use `maxRedirects: 0`.
  - Tradeoff: Changes 5 helpers; confirm that `Accept: application/json` does not change the redirect from `persons.store`.
  - Confidence: HIGH — the validation behaviour with `expectsJson()` is standard Laravel.
  - Blind spot: Not checked whether the app's middleware (Inertia, PreventRequestForgery) treats JSON requests differently.
- **Decision**: FIXED
  - `writeHeaders` adds `Accept: application/json`.
  - `expectRedirect` requires a 302 plus a path check: `/tree/\d+` for person and spouse, `/sources/\d+` for source.
  - `deleteRecord` uses `maxRedirects: 0` and expects a 302.
  - Verification on the live server:
    - invalid person → 422 with the error message;
    - link to a missing event → 404;
    - no session → 419.
  - Suite 6/6; `--repeat-each=2` 11/11; DB clean.

### F3 — Lokatory slotów, węzła związku i linku źródła siedzą w specyfikacjach, nie w `support/`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: tree-slot-parent.spec.ts:38-43,64-66; tree-slot-child.spec.ts:55-61; tree-slot-spouse.spec.ts:40-54; fact-source-scan.spec.ts:58-68; header navigation.ts:1-3
- **Detail**:
  - The plan's Desired End State says the S-04/S-05/S-06 rebuild changes `tests/e2e/support/`. The `navigation.ts` header promises the same.
  - The specs, however, hold these locators directly: `Dodaj ojca|matkę|dziecko|małżonka: …` buttons, the union node `/^Związek \d z \d/` with `menuitemradio`/`menuitem`, and the link `Otwórz źródło …`. S-05 will reorganise exactly these.
  - They are not assertions, so the letter of the plan holds. But a rename in S-05 means editing 3 specs, and under F1 every red run leaves data behind.
- **Fix A ⭐ Recommended**: Move `clickSlot(page, label)`, `switchUnion(page, partnerName)`, `openUnionMenuItem(...)` and `openLinkedSource(region, name)` into `support/navigation.ts`.
  - Strength: Meets the end state literally; S-05 changes one file.
  - Tradeoff: More indirection in the specs; the path step is less readable on its own.
  - Confidence: HIGH — the pattern already exists (`openPerson`, `familySection`).
  - Blind spot: Not known yet which names S-05 will actually change.
- **Fix B**: Correct the `navigation.ts` header and §6.6 to say the specs hold the slot locators.
  - Strength: Zero code; honest documentation.
  - Tradeoff: The goal "rebuild changes support/" is only partly met.
  - Confidence: MEDIUM — acceptable if S-05 does not change slot names.
  - Blind spot: S-05 has no plan yet.
- **Decision**: FIXED (Fix A)
  - `navigation.ts` gains `treeSlot`, `slotDialog`, `openSlot`, `switchUnion`, `chooseUnionMenuItem` and `openLinkedSource`.
  - The specs no longer hold slot, union-node or source-link locators.
  - Verification through a temporary IPv4 config: suite 6/6, `--repeat-each=2` 11/11, DB clean.
  - Note from the verification: `localhost` resolves in about 10 s in the Playwright client on this host, because the IPv6 lookup goes to the WSL DNS. Through `127.0.0.1` everything is green. This is an environmental cause, not a code one; see the triage summary.

### F4 — Werdykt sondy `choose()` „NIEPOTWIERDZONE” to interpretacja obserwacji, która literalnie odpowiada „tak”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: context/foundation/test-plan.md (note „Dopisek z fazy 4 (2026-10-05)”)
- **Detail**:
  - The plan's question: does clicking `Dodaj dziecko` during a `?spouse=` visit target the previous pair?
  - Observation: after switching to union 2, a click during the 12 s delay saved the child to pair 1 (Jan + Anna).
  - The verdict is NIEPOTWIERDZONE, because the slot and the dialog title honestly showed pair 1. The data matches what the user saw, but not what the user chose in the menu a moment earlier.
  - Minor: the text mixes „P i Anna” and „Jan + Anna”.
- **Fix A ⭐ Recommended**: Accept the verdict and add to the note one sentence: „the click targets the previous pair, but the dialog states it; we treat it as a UX issue, not a data error”. Unify the person names.
  - Strength: Honestly describes the observation; the window needs a slow network and the dialog title is correct.
  - Tradeoff: A user who does not read the title may add a child to the wrong union.
  - Confidence: MEDIUM — the 12 s delay is artificial; the real window is short.
  - Blind spot: Not measured how long the visit takes on the production network.
- **Fix B**: Open a new change via `/10x-frame`, for example blocking the slots during a pending `?spouse=` visit.
  - Strength: Closes the window for good.
  - Tradeoff: Another change on the roadmap; could also be solved by the S-05 rebuild.
  - Confidence: MEDIUM.
  - Blind spot: S-05 may change the union switcher anyway.
- **Decision**: FIXED (Fix A)
  - The probe paragraph in `test-plan.md` says outright that the click targets the previous pair. The finding is classed as a UX issue, not a data error.
  - The names are unified as „Jan i Anna”.

### F5 — Odchylenia od planu udokumentowane w kodzie i `test-plan.md`, ale nie w planie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: plan.md (Phase 2/3 Contract); navigation.ts:9-12; test-plan.md §6.6 step 5
- **Detail**:
  - **Toasts replaced.** Instead of waiting for the `Dodano …` toasts, the specs wait for the dialog to close (`toBeHidden`) and, in the parent spec, for the slot to disappear. The reason (flash in the shared session) is sound.
    - Side effect: in the child spec, `expect(childSlot).toBeVisible()` (line 78) is not a fresh signal before the second click.
  - **`gotoTree` waits on any `h1`** instead of a slot or card.
  - **Deliberate damage changed:**
    - Child: `other_parent_id: null` instead of "the partner of the first union".
    - Spouse: `spouse_id: focus.id`, because the planned damage was symmetric and stayed green. That is a flaw in the plan, correctly caught.
- **Fix**: Add an "Odchylenia od planu" addendum at the end of `plan.md` with these four points and their reasons.
- **Decision**: FIXED — the "Odchylenia od planu" section was added to `plan.md` before `## Progress`. It includes the changes from F1–F3.

### F6 — Przy działającym `npm run dev` aplikacja E2E ładuje zasoby z Vite dev, nie z buildu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: scripts/e2e-server.sh:13 (build); test-plan.md §6.6 „Run locally” and step 5
- **Detail**:
  - `public/hot` (`http://localhost:5173`) is visible to the E2E container through the bind mount.
  - `curl :8100/login` returns `src="http://localhost:5173/@vite/client"`.
  - §6.6 and the plan say "zmiany w TSX wymagają restartu serwera E2E" (TSX changes need an E2E server restart). With the dev server running that is not true: the E2E app sees the working tree live. If the dev server crashes and `public/hot` stays behind, the E2E pages fail to load.
- **Fix**: Add one sentence to the `e2e-server.sh` comment and to §6.6: "z działającym `npm run dev` serwer E2E używa zasobów Vite dev (zmiany TSX widoczne od razu); restart jest potrzebny tylko bez dev serwera" (with `npm run dev` running, the E2E server uses Vite dev assets and TSX changes show at once; a restart is needed only without the dev server).
- **Decision**: FIXED
  - A comment was added before `npm run build` in `scripts/e2e-server.sh`.
  - §6.6 "Run locally" and step 5 in `test-plan.md` now describe both modes (dev server running or not) and the stale `public/hot` trap.

### F7 — Wyszukiwanie osób utworzonych w UI tylko po imieniu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tree-slot-parent.spec.ts:83 (`'Matka'`), tree-slot-child.spec.ts:89 (`'Nowe'`), tree-slot-spouse.spec.ts:66 (`'Nowa'`)
- **Detail**: Each search returns every Matka/Nowe/Nowa in the shared tree, from all parallel tests and leftovers. Only the exact `display_name` filter keeps it correct, and `/persons` has no pagination today.
- **Fix**: `findPersons(page, fullName)`: the server ANDs the words, so only this test's record comes back.
- **Decision**: FIXED — `findPersons(page, motherName | newChildName | newSpouseName)`. Suite 6/6.

### F8 — sha256 liczony z ustalonego URL zamiast z `src` wyświetlonego skanu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/fact-source-scan.spec.ts:68-73
- **Detail**: The test asserts the exact `src` `/sources/${id}/file` and hashes a separately fetched URL. That pins it to the file route's shape, which the S-02 viewer could change (query string, absolute URL).
- **Fix**: `const src = await scan.getAttribute('src')`, then fetch and hash that URL. This ties the displayed scan to the uploaded bytes without pinning the URL form.
- **Decision**: FIXED — `src` must contain `/sources/{id}/`, and the hash is taken from the `src` of the displayed image. The spec passes.

### F9 — `familySection` to lokator strukturalny (`section` + `h3`)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/support/navigation.ts:38-40
- **Detail**: CLAUDE.md forbids DOM structure in locators. The exception is documented (§6.6 Pułapki), kept in one helper, and forced by the ban on production code in F-01.
- **Fix**: In S-06 (when the panel changes anyway), add `aria-labelledby` to the family `Section` and switch to `getByRole('region', { name })`.
- **Decision**: FIXED (queued) — the S-06 task is in `follow-ups/review-fixes.md`. No production code was changed in F-01.

## Triage summary (2026-10-05)

- Fixed: F1 (Fix A), F2, F3 (Fix A), F4 (Fix A), F5, F6, F7, F8 (8)
- Fixed (queued for S-06): F9 (1)
- Final check:
  - `npx playwright test --repeat-each=2` through `127.0.0.1`: 11/11.
  - `e2e` DB: 0 people, 0 sources.
  - `npm run check`: passes.
- Outside the findings: on this host the Playwright client resolves `localhost` in about 10 s (the IPv6 lookup goes to the WSL DNS `10.255.255.254`). With `baseURL` `http://localhost:8100`, every request then exceeds the timeouts. Decision left to the user: set `baseURL` to `127.0.0.1` in `playwright.config.ts`, or add `::1 localhost` to `/etc/hosts`.
