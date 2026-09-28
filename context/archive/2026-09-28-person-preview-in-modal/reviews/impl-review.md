<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Person Preview in Modal

- **Plan**: context/changes/person-preview-in-modal/plan.md
- **Scope**: Full plan (Phases 1–2 of 2)
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-28
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated criteria re-run at review time: 165 feature tests, Larastan, `npm run check`, `tsc` all green. Manual rows 1.7–1.17 and 2.8–2.10 confirmed by the user.

## Findings

### F1 — Stale "Wstecz" stack when a preview visit does not land

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/person-preview-link.tsx (~34), person-preview-provider.tsx (~84, `pushCurrent`)
- **Detail**: The current person id is pushed onto the back stack on click, before the visit succeeds. If the visit fails/404s/is cancelled, the stack keeps an entry while `currentId` is unchanged, so "Wstecz" reopens the same person. Clicking a link to the person already shown also pushes a duplicate.
- **Fix**: Skip the push when `personId === currentId`, and push only after a successful visit (`onSuccess`) instead of on click.
  - Strength: Removes both the duplicate and the failed-visit stale entry; small change in link/provider.
  - Tradeoff: The link may need `onSuccess` handling rather than a bare `<Link>` click hook.
  - Confidence: MED — `Link` accepts visit callbacks, but not exercised here.
  - Blind spot: No browser check of a failing visit.
- **Decision**: FIXED — push "Wstecz" entry in Link onSuccess, skip when personId === currentId

### F2 — Redirect target built with `url($path)` after destroy

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PersonController.php (`urlAfterDelete`, ~111-135)
- **Detail**: The host is compared but not the scheme; `url()` returns paths starting with `//` unchanged, so a crafted Referer path like `//evil.com/persons` could yield a protocol-relative redirect. Exploitation is unlikely (Referer comes from the user's own same-origin XHR; Laravel trims the path), so this is hardening.
- **Fix**: Reject a path that does not start with a single `/`, and build the result as `$request->getSchemeAndHttpHost().$path`.
- **Decision**: FIXED — reject `//`/non-rooted referrer paths, redirect built from request origin

### F3 — Person lookup + policy runs twice per request

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Middleware/HandleInertiaRequests.php (~51-63)
- **Detail**: `personPreview` and `relationCandidates` each call `previewPerson()` when both evaluate on a request.
- **Fix**: Memoize the resolved person per request (e.g. `$request->attributes`).
- **Decision**: FIXED — resolved person memoized on the request

### F4 — No exit animation on close

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/person-preview-dialog.tsx (~19)
- **Detail**: The dialog returns null as soon as `personPreview` is null, so the modal disappears without the usual shadcn close animation. Behaviour is correct; cosmetic only.
- **Fix**: Accept, or keep the last payload mounted until the close transition ends.
- **Decision**: SKIPPED — cosmetic

## Notes

- Dropped an unverified subagent claim (stale `relationCandidates` after swapping people): the picker hooks reload on open, but this was not confirmed in code.
- Drift review: every planned item MATCH; only benign extras (`FormDialogHeader` `leading`/`descriptionClassName`, exported `previewVisitOptions`, host check in `urlAfterDelete`). No "Not Doing" violations.
