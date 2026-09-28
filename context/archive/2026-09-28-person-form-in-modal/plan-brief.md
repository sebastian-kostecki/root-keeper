# Person Form in Modal — Plan Brief

> Full plan: `context/changes/person-form-in-modal/plan.md`

## What & Why

Roadmap slice S-20 (v3:FR-004): adding and correcting a person happens in a modal — from the person list and from the person preview — instead of on separate pages. It removes a page hop from the most frequent edit, and the same modal is the shared building block that S-21 – S-23 will open from the tree.

## Starting Point

`persons/create` and `persons/edit` are full pages that both render the shared `PersonFormFields`. `MarriageFormDialog` already shows the working form-in-dialog pattern, but the shadcn dialog has no max height or scrolling, so the long person form would overflow on a phone.

## Desired End State

"Dodaj osobę" on the list and "Edytuj" on the preview open one person modal. Saving closes it: after adding, the user stays on the list (same search filter); after editing, the preview shows the new data. On a phone the modal fills the screen with the title on top and "Anuluj"/"Zapisz" pinned at the bottom while the fields scroll; on desktop it is a centred, wider modal with the same layout. The old create/edit URLs return 404.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Where the user lands after adding | Stay on the list: `store` returns `back()` (fallback `persons.index`) | Fast for entering several people; `back()` also generalises to future entry points like the tree. |
| Active search filter after adding | Keep the same `?q=` and search text | Covers "search, not found, add" and matches Inertia's preserved list state; the toast confirms a save that the filter hides. |
| Old `/persons/create` and `/persons/{id}/edit` | Remove the routes, return 404 (with a numeric `{person}` pattern so `/persons/create` is not a PostgreSQL 500) | Exactly what the PRD says; no legacy code in a single-user app. |
| Empty relative/spouse picker link | Links to the person list | No nested dialogs; it only shows when the tree has one person. |
| Closing with typed data | Overlay click blocked; Esc, X, "Anuluj" close without asking | Guards against the common accidental click without dirty-form tracking. |
| Modal layout | Below 640px full screen, above it centred `max-w-2xl` up to ~90vh; header and footer pinned, body scrolls | "Zapisz" always visible, which addresses the roadmap's long-form-on-phone risk. |
| Shared component | New `resources/js/components/form-dialog.tsx`; shadcn `ui/dialog.tsx` untouched; no new tokens | Reusable by S-17's source modal and the tree modals without forking generated UI code. |
| Edit prefill data | `personForm` (`PersonFormResource`) + options as eager props on `show` | Relations already loaded, so no extra queries and no loading state in the modal. |
| Phasing | Add (with the wrapper) first, then edit | Each phase is clickable on its own; the wrapper is proven on the simpler case. |

## Scope

**In scope:**
- `FormDialogContent` / `Header` / `Body` / `Footer` shared wrapper
- `PersonFormDialog` (create + edit modes) on `persons/index` and `persons/show`
- `genders`/`religions` on index and show, `personForm` on show, `store` → `back()`
- Removing the create/edit routes, controller methods and pages; numeric `{person}` route pattern
- Updating `tests/Feature/PersonsTest.php`

**Out of scope:**
- Opening the modal from the tree, prefilled/locked fields (S-21 – S-23)
- "Discard changes?" confirmation; redirects from old URLs; nested modal in the picker
- Validation/action changes, the residence field (S-14), new tokens or dependencies, screenshot test infra

## Architecture / Approach

`PersonFormDialog` follows `MarriageFormDialog`: a controlled Radix dialog whose Inertia `<Form>` (`preserveScroll`, `onSuccess` closes, `onError` scrolls to the first error) wraps a scrolling `FormDialogBody` with the unchanged `PersonFormFields` and a pinned `FormDialogFooter`. Radix unmounts the content on close, so the uncontrolled fields reset and the edit mode always prefills from the latest `personForm` prop. The backend changes are only props, one redirect and route removal; `CreatePerson`, `UpdatePerson` and the validation are untouched.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Responsive form dialog and adding a person in a modal | Wrapper, add modal on the list, `store` back to the list, create page/route gone | Full-screen/scroll layout on phones (flex + `min-h-0`), and `/persons/create` hitting the `show` binding |
| 2. Editing a person in a modal on the preview | Edit modal on the preview, `personForm` prop, edit page/route gone | Tests formerly on the edit page must move to the `show` props without losing coverage |

**Prerequisites:** S-01 and S-05 done (they are); Sail running.
**Estimated effort:** ~1–2 sessions across 2 phases.

## Open Risks & Assumptions

- Assumes the Inertia `<Form>` keeps component state after a successful non-GET submit (checked in the installed `@inertiajs/react` code): the list's search text survives, which is why `store` must return to the same `?q=`.
- The visual gate is manual (screenshots at 375px and 1280px, light and dark); the repo has no screenshot tests.
- Bookmarks to the old pages will 404 (accepted).

## Success Criteria (Summary)

- A person can be added from the list and edited from the preview without leaving the page, with errors shown inside the modal.
- On a phone the whole form is usable: fields scroll and "Zapisz" is always reachable.
- No route, page or link to `persons/create` or `persons/edit` remains.
