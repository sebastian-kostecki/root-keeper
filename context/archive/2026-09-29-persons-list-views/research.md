---
date: 2026-09-29T11:23:43+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: 89c667932ad42ee3a318668fab12bebb60ca8db6
branch: feat/sources
repository: root-keeper
topic: "Persons list — switchable List (A) and Table (B) views with ?view=tabela"
tags: [research, codebase, persons-index, person-search, person-preview, delete-person, gender-tokens, design-system]
status: complete
last_updated: 2026-09-29
last_updated_by: Claude (Opus 5.5)
---

# Research: Persons list — switchable List (A) and Table (B) views

**Date**: 2026-09-29T11:23:43+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: 89c667932ad42ee3a318668fab12bebb60ca8db6 (working tree: untracked `context/changes/persons-list-views/`, `context/changes/scans-become-sources/`)
**Branch**: feat/sources
**Repository**: root-keeper

## Research Question

The user picked two views of `/persons` from the design canvas https://claude.ai/artifact/LT3qzYRdcYfkxZchBJ7LEN (boards `Main` = A, `Tabela` = B, `Stany`, `Mobile`; board C „Oś życia” rejected). Both views are offered together through a Lista | Tabela switcher, and the choice lives in the URL as `?view=tabela` next to `?q=`.

- **A — Lista:** rows grouped by the first letter of the surname, with a „bez nazwiska” group last and jump links.
  - Each row has a gender-coloured initials avatar, the name, a „Probant” badge, and „ur. date, place · zm. date, place”.
  - A ✓ „potwierdzone aktem” sits next to each confirmed date, and a chevron closes the row.
- **B — Tabela:** columns Osoba / Urodzenie (date + place) / Zgon / Zamieszkanie / Wyznanie / ⋯.
  - Osoba, Urodzenie and Zgon are sortable.
  - The ⋯ menu has „Otwórz podgląd”, „Pokaż na drzewie” and „Usuń osobę”.
- **States:** empty tree, no results, search in progress.

What in the current code, backend contract and prior decisions does this touch, reuse, or conflict with?

## Summary

- **`view` is free on the server side but lost on the client today.**
  - `PersonController::index` reads only `q` (`app/Http/Controllers/PersonController.php:26-55`). An extra `view` param is ignored, not stripped.
  - The debounced search visit rebuilds the query as `trimmed === '' ? {} : { q: trimmed }` (`resources/js/pages/persons/index.tsx:33-51`, query at `:43`), so typing would drop `view=tabela`.
  - Opening the preview keeps both params, because `withPerson` edits the current URL (`resources/js/components/persons/person-preview-provider.tsx`, `withPerson`/`hrefFor`).
  - Deleting keeps both, because `urlAfterDelete` removes only `person` (`PersonController.php:112-138`; test `tests/Feature/PersonsTest.php:867-874`).
- **The ✓ needs a backend change and conflicts with a prior test.**
  - Index eager-loads only `events` (`PersonController.php:31`).
  - `EventResource` emits `is_confirmed` and `scans` only `whenLoaded('scans')` (`app/Http/Resources/EventResource.php:36-37`).
  - `PersonsTest.php:187-200` asserts both are *missing* on index.
  - S-03 excluded showing the status on the list: „Showing confirmation status on the persons list or in the tree view” (`context/archive/2026-09-24-birth-death-confirmed-by-scan/plan.md:42`). Showing ✓ is a new product decision to record, plus a deliberate test change.
- **Date sorting in the table has no data today.**
  - The index payload carries only the formatted `date` string (`EventResource.php:34`, `PartialDate::format()`), e.g. `12.03.1948`, `03.1948` or `ok. 1948`, which does not sort correctly as a string.
  - Raw columns exist on `events` (`date_year`, `date_month`, `date_day`, `date_is_approximate`, per the events migration) but are not sent.
  - A birth-date comparator already exists server-side: `PersonOrdering::relatives()` (`app/Support/PersonOrdering.php`). It orders known years first, then year/month/day, then name. There is no death-date comparator.
- **„Usuń osobę” from a row conflicts with a prior decision and cannot reuse the dialog as-is.**
  - S-05 decided „No delete action on the persons list — only on the preview” (`context/archive/2026-09-25-person-edit-and-delete/plan.md:42`).
  - `DeletePersonDialog` needs a full `PersonPreview` (scans, parents, children, marriages) plus `isRootPerson` (`resources/js/components/persons/delete-person-dialog.tsx`, props and impact calculation). The index payload has none of these.
  - CLAUDE.md requires showing the impact before deleting.
- **Everything else is reuse.**
  - Available pieces:
    - `genderCard` + `useInitials` for the avatar (`resources/js/lib/gender-styles.ts:2-6`, `resources/js/hooks/use-initials.tsx`, pattern in `person-modal-header.tsx`).
    - `toggle-group` / `tabs` for the switcher.
    - `dropdown-menu` for ⋯.
    - `treeShow(person.id)` from `@/routes/tree` for „Pokaż na drzewie”.
    - `PersonPreviewLink` for rows and „Otwórz podgląd”.
  - Missing: a shadcn `table` component (`resources/js/components/ui/table.tsx` does not exist; the directory holds 27 files). shadcn table is plain markup with no new package.
  - Also missing: the proband id. It is not a prop of the index page (`HandleInertiaRequests::share` has no root id; `TreeController` passes `rootPersonId` only to the tree pages).

## Detailed Findings

### 1. Backend: `PersonController::index`

- **Scoping:** `$request->user()->tree->people()` with `->with('events')` (`PersonController.php:30-31`).
- **Search** (`:28`, `:32-42`, normaliser `:60-67`):
  - A non-string `q` becomes `''`.
  - `q` is trimmed and cut to 100 characters.
  - It is split on whitespace. Each word is `whereLike` on `first_name` OR `last_name`, with `\ % _` escaped, and the words are ANDed.
- **Order** (`:43-46`): `last_name is null`, `last_name`, `first_name`, `id`, through `->get()` with no pagination. That order already suits grouping by the surname's first letter, with nameless people last.
- **Props** (`:49-54`): `persons` (`PersonResource::collection(...)->resolve()`), `filters: {q}`, `genders`, `religions`.
- There is no FormRequest for index. An unknown `view` value reaches nothing server-side.
- **`PersonResource` fields on index** (`app/Http/Resources/PersonResource.php`):
  - `id`, `first_name`, `last_name`, `display_name`, `gender {value,label}|null`, `residence`, `religion {value,label}|null`, `birth`, `death`.
  - `parents`, `children` and `marriages` are `whenLoaded` and absent.
  - So residence, religion, gender and the date + place strings for the table are already in the payload.
- **`Person::birth()`/`death()`** read the loaded `events` collection. With `events` eager-loaded there is no N+1 (backend worker, `app/Models/Person.php:92-100`).
- **`displayName()`** (`app/Models/Person.php:102-105`) substitutes `NN` for a null first or last name.
- **Adding the ✓:**
  - `Event::scans()` is a `belongsToMany` via the `event_scan` pivot (`app/Models/Event.php:69-75`).
  - `with('events.scans')` adds one query but hydrates full scans for every event, including marriages. `EventResource` would then also emit `scans` arrays.
  - A `withExists('scans')` on events is lighter but needs an `EventResource` branch.
  - `BuildPersonPreview` already loads `events.scans` for the modal (`app/Actions/Persons/BuildPersonPreview.php:27`).

### 2. Frontend: current page, preview, switcher

- **Current page** (`resources/js/pages/persons/index.tsx`):
  - The search input shows when `persons.length > 0 || filters.q !== ''` (`:70-79`).
  - Empty state texts:
    - Empty tree: „Nie dodano jeszcze żadnej osoby…” (`:81-86`).
    - No results: „Nie znaleziono osób pasujących do „{q}”.” with `break-words` (`:87-93`).
  - Rows are `PersonPreviewLink` › `Card` with `display_name` and `formatLifespan` (`resources/js/lib/lifespan.ts:5-22`).
- **Preview:**
  - `?person=<id>` is resolved in `HandleInertiaRequests::share` (`app/Http/Middleware/HandleInertiaRequests.php:50-54`).
  - Links use `hrefFor` → `withPerson(usePage().url, id)` with `only: ['personPreview']`, so opening does not refetch `persons`.
  - Closing uses `router.replace` without `person`.
  - The provider is mounted in `resources/js/layouts/app-layout.tsx`, so the table's „Otwórz podgląd” can be `PersonPreviewLink` inside `DropdownMenuItem asChild`.
- **Switcher primitives:**
  - `toggle-group` is used as the gender picker in `person-section-fields.tsx`.
  - Its `onValueChange` fires `''` when the active item is clicked again, and that code guards against it. The Lista/Tabela switcher needs the same guard.
  - `tabs` (`@radix-ui/react-tabs`) is installed as well.
- **Wayfinder:**
  - `query` is used only at `persons/index.tsx:42-45` and in `tree/sheet-person-card.tsx` (`spouse`). There is no `mergeQuery` usage anywhere.
  - Building `{ ...(q && {q}), ...(view === 'tabela' && {view}) }` keeps URLs clean.
- **Toggling view with `router.get`** would refetch `persons`. That is unnecessary if the view is read from the URL on the client, but harmless at this list size.

### 3. Avatar, gender, confirmation marker, tree link

- **Avatar:**
  - `genderCard[person.gender?.value ?? 'unknown']` + `getInitials(display_name)` is the modal header avatar (`person-modal-header.tsx`).
  - The `genderCard` strings include `hover:border-*`, which a static row avatar may not want.
- **Gender and colour contrast:**
  - Gender is `Gender = {value: 'male'|'female'; label}` or `null` (`resources/js/types/person.ts:6-9`, `:51`). The backend enum has no unknown case.
  - The gender cue is colour only, plus sr-only text (tree-sheet-variant-c audit D2).
  - Secondary text on tinted surfaces should use `--tree-meta` (5.9:1 light / 7.2:1 dark) rather than `--muted-foreground` (~4.3:1). Source: `context/archive/2026-09-29-tree-sheet-variant-c/audit.md:34`.
- **Confirmation marker:**
  - `EventSourceStatus({isConfirmed})` renders the full „Potwierdzone aktem” / „Bez źródła” badge (`resources/js/components/persons/event-source-status.tsx`), which is too heavy for a row.
  - A bare `CheckIcon` with sr-only text follows the S-03 rule that status must not rely on colour alone.
- **„Pokaż na drzewie”** is `treeShow(person.id)` from `@/routes/tree`, as used in `person-modal-header.tsx`.
  - The header ⋯ menu already has „Pokaż na drzewie”, „Ustaw jako probanta” and „Usuń osobę…”.
  - A row menu should reuse those labels and the `DropdownMenu` pattern: `onSelect` sets state, and the dialog sits outside the menu.

### 4. Delete from a row

- `DeletePersonDialog` builds its impact text from `person.birth/death.scans`, `marriages[].scans`, `parents` and `children`. It also takes `isRootPerson`. Its only caller is `person-modal-header.tsx`.
- The index `Person` type lacks all of those, so passing it would fail type-checking and throw at runtime on `marriages.reduce`.
- **Options:**
  - (a) The row item opens the preview, and the user deletes from its header, where the flow is already implemented.
  - (b) Open the preview with the delete dialog pre-opened. This needs a new provider affordance.
  - (c) Load the preview on demand and render the dialog from `personPreview`.
  - (d) Eager-load relations and scans for the whole list. This is heavy.
- The redirect after delete already preserves `q` and `view`.

### 5. Tests and verification

- **Index tests** in `tests/Feature/PersonsTest.php`:
  - `:21-40` order with unnamed last
  - `:43-185` search rules and `filters.q`
  - `:187-200` no `is_confirmed`/`scans` on index
  - `:470` gender and religion options
  - `:742-802` preview props on `persons/index`
  - `:867-874` delete keeps `q`
- **What changes affect:**
  - Adding a `filters.view` key or a `rootPersonId` prop breaks none of them, because they use `where('filters.q', …)`, not whole-object equality.
  - Changing the default server order would break `:21-40`.
  - Adding ✓ breaks `:187-200` by design.
- **Vitest** exists (`package.json:13` `"test:js": "vp test"`, e.g. `resources/js/lib/partial-date.test.ts`). Pure helpers can get JS tests, e.g. grouping by first letter or a client date comparator.
- **Screenshot gate:**
  - It is an out-of-repo Playwright `shots.mjs` per change, run against Sail with the `TreeViewDemoSeeder` user (`context/archive/2026-09-29-person-modal/screenshots/shots.mjs`, `context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs`). There is no in-repo screenshot test.
  - Those scripts locate people by scraping `/persons` for `a[href*="person="]`. Both new views must keep real preview `href`s on the name.

## Code References

- `app/Http/Controllers/PersonController.php:26-55` - index query, order, props
- `app/Http/Controllers/PersonController.php:60-67` - `q` normalisation
- `app/Http/Controllers/PersonController.php:112-138` - `urlAfterDelete` keeps every query key except `person`
- `app/Http/Resources/EventResource.php:34-37` - formatted `date`; `is_confirmed`/`scans` only when `scans` loaded
- `app/Support/PersonOrdering.php` - `relatives()` birth-date comparator; `marriages()`
- `app/Models/Person.php:92-105` - `birth()`/`death()` from loaded events; `displayName()` with `NN`
- `app/Http/Middleware/HandleInertiaRequests.php:41-58` - shared props; `?person=` → `personPreview`; no root person id
- `resources/js/pages/persons/index.tsx:33-51` - debounced search visit that drops other params
- `resources/js/pages/persons/index.tsx:70-116` - search visibility, empty states, rows
- `resources/js/components/persons/person-preview-provider.tsx` - `withPerson`, `hrefFor`, `previewVisitOptions`, `close`
- `resources/js/components/persons/delete-person-dialog.tsx` - needs `PersonPreview` + `isRootPerson`
- `resources/js/components/persons/person-modal-header.tsx` - initials avatar, ⋯ menu, `treeShow`
- `resources/js/components/persons/event-source-status.tsx` - confirmed / bez źródła badge
- `resources/js/lib/gender-styles.ts:2-6` - `genderCard`
- `resources/js/lib/lifespan.ts:5-22` - `formatLifespan`
- `resources/js/types/person.ts:6-56` - `Gender`, `PersonEvent`, `SourcedPersonEvent`, `Person`
- `resources/css/app.css` - `--gender-*` and `--tree-meta` tokens (light and dark)
- `tests/Feature/PersonsTest.php:21-200, 470, 742-802, 867-874` - index contract tests

## Architecture Insights

- The list is a server-filtered, unpaginated, whole-tree payload. At the stated scale (hundreds of people, `person-search/plan.md:173`) client-side grouping and sorting over it is viable. The repo's precedent for URL state is server-side (`q`), chosen so that it „survives Back/refresh and is provable by Pest” (`person-search/plan-brief.md:33`).
- The URL is the state carrier for the list (`q`), the modal (`person`) and the post-delete redirect. `view` fits that model, with one condition: every visit the page makes must carry the params it doesn't own.
- The design contract is shadcn + Tailwind v4 `@theme` tokens.
  - Every token the canvas uses already exists in `app.css`: gender surface and border, `tree-line`, `tree-meta`, `muted`, `accent`.
  - The only missing shared component is `ui/table`.
  - Row avatar markup is currently inlined in the modal header. A second consumer, the list rows, makes it a candidate for a shared `PersonAvatar`.

## Historical Context (from prior changes)

- `context/archive/2026-09-25-person-search/plan.md:41`, `plan-brief.md:33` — no pagination; server-side `?q=` with 300 ms debounce and `replace: true`; the empty states. Supported, and still the current code.
- `context/archive/2026-09-24-birth-death-confirmed-by-scan/plan.md:42` — confirmation status not shown on the persons list. This was scope exclusion for S-03, not a standing prohibition. The new design reverses it.
- `context/archive/2026-09-25-person-edit-and-delete/plan.md:42` — no delete action on the persons list. It predates the modal (S-24). The table's ⋯ „Usuń osobę” reverses it unless it routes through the preview.
- `context/archive/2026-09-29-person-modal/plan-brief.md:97` — the list lifespan changed from `ur. X · zm. Y` to `X – Y` via `formatLifespan`. List view A goes back to „ur. …, place · zm. …, place”, which conflicts with this decision.
- `context/foundation/prd.md` (v4) — the list is one of the modal's entry points, and `?person=` is not a supported deep link. FR-003: after „Dodaj osobę” the user lands on the tree with the preview open, and the create button stays.
- `context/foundation/roadmap.md` — no slice covers a list or table redesign. S-14 (proposed) will turn `residence` into a list of places, and S-13/S-15 will turn event places into gazetteer places. Render both as opaque text now.
- `context/changes/scans-become-sources/change.md` — new, empty. Per roadmap S-16/S-18, „confirmed” may come to mean any kind of source. Keeping `is_confirmed` computed server-side lets the ✓ survive that change.

## Related Research

- `context/archive/2026-09-29-person-modal/research.md` — the modal these rows open; the source of `genderCard` moving to `lib/`.
- `context/archive/2026-09-25-person-search/` — the search contract the switcher must preserve.

## Open Questions

1. **✓ on the list:** confirm reversing the S-03 exclusion. Which payload: `events.scans` (heavier, emits scan bodies) or `withExists('scans')` → boolean only? Then update `PersonsTest.php:187-200` to assert a boolean and no scan bodies.
2. **Row „Usuń osobę”:** pick (a) open the preview and delete there, (b) preview with the dialog pre-opened, (c) on-demand preview load, or drop the item. A delete without the impact list is ruled out by CLAUDE.md.
3. **Table sort:** client-side over the loaded list or server-side `?sort=`? Should sort be in the URL? For date columns: send raw `date_year/month/day/is_approximate` (or a sort key) with events, or sort server-side reusing a `PersonOrdering`-style comparator. Death-date ordering does not exist yet.
4. **Meta line in view A:** keep `formatLifespan` (`X – Y`, person-modal decision) or use the canvas format with places (`ur. X, Kraków · zm. Y, Tarnów`)?
5. **`view` contract:** absent means lista, `tabela` means table, and an invalid value falls back to lista. Does the server echo it as `filters.view`, validated like `q`, or does the client read it from the URL? And does toggling fire a visit (`router.get`, refetch) or a client-side `replace`?
6. **Proband badge:** add `rootPersonId` (`$tree->root_person_id`) to index props, as `TreeController` does.
7. **Mobile:** no PRD requirement for the list. The canvas shows grouped rows and a horizontally scrolling table with a sticky Osoba column. Confirm that as the target.
8. **Shared components:** add `ui/table.tsx` (shadcn markup, no new package) and extract a `PersonAvatar` from the modal header?
