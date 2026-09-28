# Person Preview in Modal — Plan Brief

> Full plan: `context/changes/person-preview-in-modal/plan.md`

## What & Why

Roadmap S-24 (PRD v4 stage 1): viewing a person should no longer pull the author off the tree onto a separate page. The person preview becomes a modal opened from the tree, the person list and the scan preview; inside it the author walks to relatives and returns with "Wstecz", and closing leaves the page underneath exactly as it was. This also makes the preview consistent with add/edit, which moved into a modal in S-20.

## Starting Point

The preview is the page `/persons/{id}` (`PersonController@show` → `persons/show.tsx`), reached from 7 links. Every action on it (relations, marriages, scan links, proband) already redirects with `back()`; only `update` and `destroy` use fixed routes. The relation-candidate and scan pickers load optional props by reloading the current URL.

## Desired End State

The tree's eye button and "Podgląd", every list row and every "Potwierdza" entry on a scan page open the preview modal over the current page. The modal has everything the page had (facts with scans, relations with add/remove, proband, edit, delete), swaps to relatives with "Wstecz", is full-screen on phones, and the page behind refreshes after each action. Deleting the tree's centre returns the tree to the proband. Adding a person lands on the tree centred on them. `/persons/{id}` is gone.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Where modal state/data lives | `?person=ID` on the current page URL + shared Inertia prop `personPreview` (candidates and scans as shared optional props) | All existing forms already redirect `back()`, so the modal and the page underneath refresh with no per-form wiring and the picker hooks stay unchanged. |
| Browser back button | Leaves the page as today; open/swap/close replace the history entry; "Wstecz" uses a client-side stack | No stale history entries after mutations and simple closing; returning from a scan page reopens the modal. |
| Clicks on the tree | Card body still re-centres; eye button and header "Podgląd" open the modal | Keeps the FR-012 "centring works as today" guardrail literally. |
| `/persons/{id}` page | Removed in this slice (route, method, page) | No dead page to maintain through S-25 – S-28; brings that part of FR-009 forward from S-29. |
| Redirects | `update` → back; `destroy` → referrer without `person`, or `tree.index` if the centre was deleted; `store` → `tree.show(new)` | Implements FR-013 and FR-003 while keeping the underlying page. |
| Old URL | `GET /persons/{id}` → 405 (PUT/DELETE stay on that path), no redirect | PRD v4 accepts that old addresses stop working. |

## Scope

**In scope:**
- Shared preview props with tree-scoped 404, `BuildPersonPreview` action
- `PersonPreviewDialog` + provider in `AppLayout`, `PersonPreviewLink` in all 6 remaining entry points, preview content extracted into a component
- `update`/`destroy`/`store` redirects; removal of the show page/route/method
- Feature tests moved from `persons/show` to `personPreview` on host pages

**Out of scope:**
- Read-only relations and "Pokaż na drzewie" per relation (S-29); relation editing on the tree (S-25 – S-28)
- Deep-link guarantees or per-person history entries; redirects from old URLs
- Changing the tree card click; source modals (S-17/S-18); shadcn `ui/*` edits, tokens, screenshot test infra

## Architecture / Approach

Opening = Inertia partial visit to the current URL with `person=<id>` (`replace`, `preserveState`, `preserveScroll`, `only: ['personPreview']`). `HandleInertiaRequests` resolves `personPreview` only when the param is present (person via the user's tree, else 404). The provider in the persistent `AppLayout` renders the dialog when the prop is non-null, keeps the "Wstecz" stack (clears when the prop becomes null) and closes by stripping the param client-side. Mutations inside the modal redirect back to the same URL, which re-renders both the modal and the page.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Preview modal on every page | Modal from tree/list/scan, "Wstecz", actions inside, update/destroy redirects | Stack lost on `preserveState: false` remounts; `destroy` redirecting to a URL with the deleted id |
| 2. Adding lands on the tree; remove the preview page | FR-003, page/route/method removed, ~30 tests migrated | Missing a behavioural assertion while migrating tests |

**Prerequisites:** S-20 and S-09 done (they are); no schema changes.
**Estimated effort:** ~2 sessions across 2 phases (phase 1 is the larger).

## Open Risks & Assumptions

- `?person=` makes the preview technically addressable although the PRD says it has none; treated as an implementation detail, not a supported link.
- After returning from a scan page via browser back, the reopened modal shows history-restored data (same as the rest of the page today).
- S-29's "the page disappears" is delivered here; the roadmap text for S-29 should be narrowed by `/10x-roadmap`.
- The tree must still render when `spouse=` names the deleted person (to be verified by a test in phase 1).

## Success Criteria (Summary)

- From the tree, the author opens a person, walks to a parent and back with "Wstecz", closes the modal and is on the same centre and union.
- Every action from the old page works inside the modal and the page behind reflects it; deleting the centre person returns the tree to the proband.
- Adding a person lands on the tree centred on them; `/persons/{id}` no longer exists.
