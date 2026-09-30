---
date: 2026-09-30T14:18:38+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: 27484728ddbe22f9c8785517e04bb117f3f1ad29
branch: feat/change-to-sources
repository: root-keeper
topic: "Sources list — switchable Gallery (A) and Table (B) views with ?view=tabela, search, kind filter and link state"
tags: [research, codebase, sources-index, source-thumbnail, persons-list-views, design-system, screenshot-gate]
status: complete
last_updated: 2026-09-30
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Follow-up 2026-09-30: the user answered all 12 open questions; see „Decisions”."
---

# Research: Sources list — switchable Gallery (A) and Table (B) views

**Date**: 2026-09-30T14:18:38+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: 27484728ddbe22f9c8785517e04bb117f3f1ad29 (working tree: untracked `context/changes/sources-list-views/`)
**Branch**: feat/change-to-sources
**Repository**: root-keeper

## Research Question

The user picked two views of `/sources` from the design canvas https://claude.ai/artifact/McYg2Lo8Z2wZcKEHAoqpzU (boards `Main` = A, `Rejestr` = B, `Stany`, `Mobile`; board C „Lista z podglądem” parked). Both views are offered through a Galeria | Tabela switcher, with the choice in the URL as `?view=tabela`.

- **Shared toolbar:** search, a kind filter with counters (Wszystkie / Akty / Nagrobki / Inne), the view switcher.
- **A — Galeria:** card grid; 4:3 thumbnail, name clamped to two lines, one line „akt urodzenia · 1878 · nr 47”, pochodzenie, and „potwierdza N wydarzeń” or „bez powiązań”.
- **B — Tabela:** columns Źródło (thumbnail + name) / Rodzaj / Rok / Nr wpisu / Pochodzenie / Potwierdza / Dodano / ⋯; Źródło, Rodzaj, Rok, Potwierdza and Dodano sortable.
- **States:** empty tree, no results, kind with counter 0, card and row edge cases; 390 px for both views.

What does this touch, reuse or conflict with in the current code, the backend contract and prior decisions? Output: a list of charges (file, line, user impact) in the three categories of `CLAUDE.md`, plus the facts `/10x-plan` needs.

## Summary

- **Named motif: „stykówka + rejestr” (contact sheet + register).** It is the repo's own two-view list pattern from `persons-list-views` (Lista | Tabela, `?view=tabela`), applied to sources: the gallery is for recognising a document by its image, the table for finding it by year, type and entry number. Reference: the canvas boards above; no outside values are needed.
- **The contract has one backend gap.** The index sends no link information: `SourceController::index` loads sources without a count (`app/Http/Controllers/SourceController.php:33`) and `SourceResource` has no such field (`app/Http/Resources/SourceResource.php:44-65`). „potwierdza N” / „bez powiązań” needs a count of linked events. `view`, `sort`, `q` and a kind parameter are not read by the index today (`SourceController.php:31-40`).
- **No missing colour token blocks the design, with one contrast exception.** The colours the canvas uses are existing roles (`resources/css/app.css:20-46`). The exception: a counter in `--muted-foreground` on the selected toggle's `--accent` fill computes to ≈ 4.35:1, below 4.5:1 (charge T1).
- **Most of the design is already built for `/persons` but locked inside persons files:** the empty state, the sortable table head, the search field and the view switcher (charges C1–C4).
- **The current view has a defect at 390 px that an earlier gate missed.** The grid has no base column, so a long name widens the single column past the viewport (charge A1).
- **The screenshot gate cannot show the gallery's main state today.** Seeded sources have no files on disk, so each thumbnail renders the `ImageOff` fallback (see „Kitchen sink and gate”).
- **Three canvas elements reverse or extend prior decisions** and need a product decision in the plan: row actions (⋯), search scope, and the meaning of N in „potwierdza N wydarzeń” (see Open Questions).

## Detailed Findings

### 1. Charges

Evidence for the current view: `resources/js/pages/sources/index.tsx` at HEAD and the archived gate shots `context/archive/2026-09-30-source-description-in-modal/screenshots/after/{light-1280,light-390}-list.png` (shot 2026-09-30, after the last commit that touched `index.tsx`, `a2c651a`).

#### Missing tokens

| # | Charge | Where | User impact |
|---|---|---|---|
| T1 | No role for secondary text on a tinted fill. The selected toggle item is `data-[state=on]:bg-accent` (`resources/js/components/ui/toggle.tsx:8`); a counter in `text-muted-foreground` on it is `oklch(0.556 0 0)` on `oklch(0.97 0 0)` (`app.css:86-87`), computed ≈ 4.35:1 (≈ 4.74:1 on `--background`). The value that passes already exists as `--tree-meta` (`app.css:120`, `:165`), named for the tree and already used outside it (`resources/js/components/persons/family-tab.tsx:99`). | `resources/css/app.css:71`, `:120` | The counter of the active filter („Akty 6”) falls just under the AA text threshold at 12 px in light mode. Dark mode is not affected (`0.708` on `0.269`). |

No other missing token was found in the inspected scope (`app.css`, the five canvas boards). The thumbnail placeholders on the canvas are drawn stand-ins, not tokens.

#### Missing shared component

| # | Charge | Where | User impact |
|---|---|---|---|
| C1 | The empty state (dashed box, icon, title, hint, action) is a private function of the persons file. `/sources` draws a different one: a `Card` with a sentence and a link-styled `<button>`. The picker has a third. | `resources/js/components/persons/persons-empty-state.tsx:5-33`; `pages/sources/index.tsx:50-68`; `components/sources/source-picker-dialog.tsx:153-163` | An empty „Źródła” page looks unlike an empty „Osoby” page and has no heading or icon; a „Brak wyników” state for sources would be a fourth copy. |
| C2 | The sortable column head (button, arrow icons, `aria-sort`, sr-only state) and its `nextSort` / `columnOf` helpers are private to the persons table and typed to its columns. | `resources/js/components/persons/persons-table.tsx:54-67` | A second sortable table would copy the markup; the two tables' sort affordance and screen-reader text can drift. |
| C3 | The search field (icon, spinner, `Input` with left padding) is inlined in the persons page. | `resources/js/pages/persons/index.tsx:147-170` | Same control, second copy; focus and spacing fixes would have to be made twice. |
| C4 | The view switcher (`ToggleGroup` outline, icon + label, the guard against the empty value) is inlined in the persons page. | `resources/js/pages/persons/index.tsx:172-194` (guard `:177-183`) | Same as C3; forgetting the guard lets a click on the active item clear the view. |
| C5 | One source is described by three different one-line summaries: „typ · rok” (`formatSourceMeta`), the show page's „rodzaj · typ · rok · nr” built inline, and the canvas line „akt urodzenia · 1878 · nr 47” (short kind + type). The tile markup also exists twice. | `resources/js/lib/source-description.ts:6-13`; `pages/sources/show.tsx:37-44`; `pages/sources/index.tsx:75-116`; `source-picker-dialog.tsx:88-122` | The same akt reads differently on the list, in the picker and on its own page; the entry number, the fastest way to tell two akts of one year apart, is absent from the list. |
| C6 | `SourceThumbnail` fixes the fallback icon at `size-10` and the box at `aspect-square`; the class can be overridden, the icon cannot. | `resources/js/components/sources/source-thumbnail.tsx:15`, `:27`; consumers `pages/sources/index.tsx:81`, `source-picker-dialog.tsx:100`, `components/persons/sources-tab.tsx:68-71` | In a 40 px table thumbnail the 40 px icon fills the whole box; the „bez miniatury” row state of board `Stany` cannot be rendered with the component as is. |
| C7 | The Polish count noun is a persons-only helper (`peopleCount`). „1 wydarzenie / 2 wydarzenia / 5 wydarzeń” would be a second copy of the same rule. | `resources/js/lib/persons-index.ts:205-222` | None visible; a maintenance copy with its own test. |

#### Accidental architecture

| # | Charge | Where | User impact |
|---|---|---|---|
| A1 | The grid has no base column (`grid gap-4 sm:grid-cols-2 …`), so under 640 px the one implicit column is sized by content. A `truncate` name does not shrink it; the card grows wider than the viewport and is clipped by `overflow-x-clip` on the layout. | `resources/js/pages/sources/index.tsx:70`, `:83-88`; `resources/js/layouts/app/app-sidebar-layout.tsx:14` | In `light-390-list.png` the first card's square thumbnail is about 770 px tall, its right edge and the fallback icon are off-screen, the long name runs off without an ellipsis, and ten sources take 9042 px of scroll. |
| A2 | The index payload cannot say whether a source confirms anything (see Summary). | `SourceController.php:33`; `SourceResource.php:44-65` | A source uploaded but never linked can be found only by opening sources one by one. |
| A3 | The name is one truncated line. | `pages/sources/index.tsx:83-88` | In `light-1280-list.png` five of ten names end in „…”; akts whose names share a long prefix cannot be told apart. |
| A4 | A card carries up to four stacked lines of different kinds (badge, „typ · rok”, pochodzenie, date) and the date is a fallback for three of them. | `pages/sources/index.tsx:89-113`; `source-description.ts:16-21` | Cards in one row have different content heights; the kind is a badge on the list but plain text on the show page header. |
| A5 | The page's top heading is an `<h2>` with `mb-8`: `Heading` renders `<h1>` only for `variant="page"`, and the sources index uses the default. The 32 px margin sits inside an `items-center` row. | `resources/js/components/heading.tsx:17`, `:21`, `:25`; `pages/sources/index.tsx:37-41`; compare `pages/tree/show.tsx:53` | The page has no `<h1>`; „Dodaj źródło” sits lower than the title (visible in `light-1280-list.png`). `/persons` has the same construction (`pages/persons/index.tsx:134-137`). |
| A6 | The card link has no focus style of its own (`className="block"`), while list rows elsewhere use the ring token. | `pages/sources/index.tsx:75-79`; compare `components/persons/persons-list.tsx:59` | Keyboard focus on a source card is the browser default outline, not the app's focus ring. |
| A7 | List state is not in the URL and the ways back to the list drop it: the show breadcrumb links to bare `sourcesIndex()` and `destroy` redirects to `sources.index` without a query. | `pages/sources/show.tsx:29`; `SourceController.php:114` | Once `?view=tabela` exists, opening a source from the table and returning through the breadcrumb, or deleting it, lands on the gallery. Browser Back is not affected. |

### 2. Backend contract

- **Index today** (`SourceController.php:31-40`): `$request->user()->tree->sources()->latest()->orderByDesc('id')->get()`, unpaginated, props `sources`, `kinds`, `recordTypes`. No FormRequest; no query parameter is read.
- **Count of linked events.** `Source::events()` is a `belongsToMany` over `event_source` (`app/Models/Source.php:78-84`). The pivot has `unique(event_id, scan_id)` and `index(scan_id)`, and the column was renamed to `source_id` (`database/migrations/2026_09_24_122757_create_event_scan_table.php:20-21`, `database/migrations/2026_09_29_100000_rename_scans_to_sources.php:17-21`). The same controller already counts across this pivot in the other direction (`withCount('sources')` on events, `SourceController.php:66`).
- **`SourceResource` has three callers** (`SourceController.php:36`, `:69`; `app/Http/Middleware/HandleInertiaRequests.php:55-57` for the picker's `availableSources`). A count emitted only when loaded leaves the other two payloads unchanged.
- **What „N” would count.** The show page lists one row per event participant (`SourceController.php:72-83`), so a marriage akt shows two „Potwierdza” rows for one event; the delete dialog words these rows as „fakty” (`resources/js/components/sources/delete-source-dialog.tsx:59`). A count of `events` gives 1 for that akt. See Open Question 3.
- **Precedent for list parameters** (`app/Http/Controllers/PersonController.php:27-62`): `q`, `view`, `sort` are normalised without validation errors (`normalizeView` `:67-70`), echoed as `filters`, and `sort` is forced to the default unless `view=tabela` (`:31`). The server order never changes with `sort`; sorting is done in the browser (`resources/js/lib/persons-index.ts:145-187`). Search on `/persons` is server-side (`PersonController.php:40-50`).
- **Tests that pin the current contract** (`tests/Feature/SourcesTest.php`): order and resource shape `:32-51` (its `hasAll([...])` list at `:45-47` is the resource-shape contract and must be extended for a new key, per `context/archive/2026-09-30-source-description-in-modal/plan.md:229`); options `:750-760`. No test reads a query parameter on the index.

### 3. Frontend reuse

- **Present and sufficient:** `ui/table.tsx` (scroll container `:7-10`, row hover `:58`), `ui/toggle-group.tsx`, `ui/input.tsx`, `ui/dropdown-menu.tsx`, `ui/badge.tsx`, `ui/card.tsx`, `SourceCreateDialog`, `SourceEditDialog` (its props `source`, `kinds`, `recordTypes` are all on the index page, `pages/sources/index.tsx:18-22`).
- **Sticky first column on narrow screens** exists as a class string in the persons table (`persons-table.tsx:51-52`, applied at `:208`), the pattern board `Mobile` B shows.
- **One query builder per page** is the persons rule: every visit goes through `buildPersonsQuery` so no control drops another's parameter (`persons-index.ts:31-50`; `pages/persons/index.tsx:53-97`). Switching view or sort reloads `only: ['filters']` (`:85-97`).
- **Control heights.** `Input` and default `Button`/`Toggle` are `h-9` (36 px); the largest existing size is `lg` = `h-10` (`ui/input.tsx:11`, `ui/button.tsx:24-27`, `ui/toggle.tsx:17-19`). Board `Mobile` draws 44 px controls; no existing size produces that.
- **Thumbnails are about 400 px wide with the original aspect ratio** (`app/Actions/Sources/GenerateSourceThumbnail.php:10`, `:49-53`). A 4:3 `object-cover` crop in a roughly 270 px card needs no new thumbnail size at 1× density.
- **`DeleteSourceDialog` needs `confirms`** (`delete-source-dialog.tsx:16-20`), which only the show page computes. The index has no such data, the same situation persons had with its delete dialog.
- **JS unit tests exist** for list helpers (`package.json:13` `"test:js": "vp test"`; `vite.config.ts` `test.include: resources/js/**/*.test.ts`; four test files under `resources/js/lib/`). Pure helpers for filter, sort, count noun and the one-line summary fit there.

### 4. Kitchen sink and gate

- **No in-repo screenshot test.** `git ls-files` shows `shots.mjs` only under `context/archive/**/screenshots/`, and `package.json` has no Playwright dependency. Per `CLAUDE.md` the gate therefore stays a per-change `shots.mjs` + `verdict.md`. The latest script (`context/archive/2026-09-30-matching-sources-first/screenshots/shots.mjs:1-17`) runs Playwright from the npx cache against `http://localhost:8000` as `tree-demo@example.com`, and notes the `LD_LIBRARY_PATH` Chromium needs on this host.
- **Data:** `database/seeders/TreeViewDemoSeeder.php` creates 18 sources at HEAD (4 through `attachSource` at `:167`, `:168`, `:177`, `:209`; 6 in `sourceStates` `:259-287`; 5 in `confirmationStates` `:292-311`; 3 in `matchingStates` `:316-342`). It covers undescribed, described, long name + pochodzenie, unlinked, one akt linked to two events, all three kinds and an old upload date. The seeder is append-only (`:23-24`).
- **Gap:** seeded sources have no files (`thumbnail_path` is null in the factory default, `database/factories/SourceFactory.php:29`; `withThumbnail()` sets a path without a file, `:40-45`). The archived list shots show the fallback icon on each of the ten cards. The gallery's main state, a real 4:3 image, is not reachable from the seed. `tests/Fixtures/` holds one image (`rotated-exif.jpg`, 37 KB).
- **States the seed cannot reach directly:** a kind with counter 0 (reachable through a search that excludes a kind) and the empty tree (needs a user without sources, as `persons-list-views` did).

## Code References

- `resources/js/pages/sources/index.tsx:37-41` - heading row; `:50-68` empty state; `:70` grid classes; `:75-116` card
- `resources/js/pages/sources/show.tsx:29` - breadcrumb to the bare list; `:37-44` inline header line
- `resources/js/components/sources/source-thumbnail.tsx:15`, `:27` - fixed aspect and icon size
- `resources/js/lib/source-description.ts:6-13`, `:16-21`, `:96` - `formatSourceMeta`, `showsUploadDate`, `isUndescribed`
- `resources/js/components/sources/source-picker-dialog.tsx:88-122` - picker tile
- `resources/js/components/sources/delete-source-dialog.tsx:16-20` - needs `confirms`
- `resources/js/pages/persons/index.tsx:53-97`, `:145-196` - visits and toolbar to mirror
- `resources/js/components/persons/persons-empty-state.tsx:5-33` - private `EmptyState`
- `resources/js/components/persons/persons-table.tsx:51-67` - sticky cell, sort helpers, `SortableHead`
- `resources/js/lib/persons-index.ts:31-50`, `:145-187`, `:205-222` - query builder, client sort, count noun
- `resources/js/components/heading.tsx:17-25` - `<h2>` + `mb-8` unless `variant="page"`
- `resources/js/components/ui/toggle.tsx:8`, `:17-19` - selected fill and sizes
- `resources/css/app.css:20-46`, `:86-87`, `:120` - colour roles, the muted/accent pair, `--tree-meta`
- `app/Http/Controllers/SourceController.php:31-40`, `:66`, `:72-83`, `:114` - index, pivot count precedent, confirms, delete redirect
- `app/Http/Resources/SourceResource.php:44-65` - payload fields
- `app/Http/Middleware/HandleInertiaRequests.php:55-57` - second list consumer of the resource
- `app/Http/Controllers/PersonController.php:27-70` - parameter normalisation precedent
- `tests/Feature/SourcesTest.php:32-51`, `:750-760` - index contract tests
- `database/seeders/TreeViewDemoSeeder.php:259-342` - source states

## Architecture Insights

- The list is a whole-tree, unpaginated payload, and the PRDs state a small scale (`context/foundation/prd-v3.md:25`). Filtering by kind, counting per kind and sorting over the loaded list in the browser is the persons precedent for sort; for search the persons precedent is the opposite (server-side `q`). The canvas counters (board `Stany`: 9 / 6 / 0 / 3 under the query „lipn”) imply counts taken after search and before the kind filter, which a browser-side filter gives without extra queries.
- The URL is the state carrier for lists in this app. For sources that rule has a cost persons does not have: a person opens in a modal over the list, a source opens on its own page, so list parameters are lost on the way back unless something carries them (charge A7).
- The design system contract is met by extraction, not addition: C1–C4 move existing persons code to a shared place; no new package and no new shadcn component is needed for either view.

## Historical Context (from prior changes)

- `context/archive/2026-09-29-persons-list-views/plan.md` („What We're NOT Doing”, „Implementation Approach”) — view in the URL, server echo in `filters`, one query builder, browser-side sort with `Intl.Collator('pl')` because the database sorts names bytewise, no row delete, no view persistence beyond the URL, gate as an out-of-repo script. Supported by the current code (`PersonController.php:27-70`, `persons-index.ts`).
- `context/archive/2026-09-30-source-description-in-modal/plan.md:36`, `:59`, `:60`, `:65` — the card content decided then (badge, „typ · rok”, pochodzenie, date fallback); „No search by name”; „No edit or delete actions on list cards”. These were scope limits of S-17; the canvas changes the first and reverses the second, and the ⋯ column would reverse the third for table rows.
- `context/archive/2026-09-30-source-description-in-modal/screenshots/verdict.md:9` — „At 390 the list is one column … and truncates fine”. Contradicted by its own shot `after/light-390-list.png` (charge A1). The same row's 1280 px defect (a described card taller than its neighbours) is supported as fixed: `h-full` was added in `a2c651a` (`pages/sources/index.tsx:80`).
- `context/archive/2026-09-30-matching-sources-first/plan.md:53`, `:68` — seeded sources have no files; the „Źródła” card and `showsUploadDate` were left untouched on purpose. Supported.
- `context/archive/2026-09-30-matching-sources-first/screenshots/verdict.md:29` — an open shared-component charge (group label, three copies) tracked as GitHub issue #28. Not part of this view.
- `context/archive/2026-09-29-tree-sheet-variant-c/audit.md:34` — `--muted-foreground` on a tinted surface drops to ≈ 4.3:1; the origin of `--tree-meta`. Supported by the calculation in T1.
- `context/foundation/prd-v2.md:105`, `:179` — FR-006 „wyszukać skan po nazwie”, nice-to-have; search by note content is a non-goal. `context/foundation/roadmap.md:36` marks v2:FR-006 as parked, and `:287` leaves „does the list show kind, type and year” to planning. A grep of `roadmap.md` for „lista źródeł” / „liście źródeł” returns only `:287`, so no slice title in that file names a sources-list redesign (the slice table itself was not read row by row).

## Related Research

- `context/archive/2026-09-29-persons-list-views/research.md` — the same question for `/persons`; source of the contract and gate patterns above.

## Decisions (follow-up 2026-09-30, answered by the user)

Numbers match the Open Questions below; each of the 12 is settled.

1. **Row actions:** the ⋯ menu has „Otwórz” and „Edytuj”. No delete from the list; deleting stays on the source page.
2. **Filtering:** search and the kind filter run in the browser over the loaded list. The search text, kind, `view` and `sort` are URL parameters.
3. **Meaning of N:** linked events. A marriage akt counts 1; the text is „potwierdza N wydarzeń”.
4. **Search scope:** name only (the source's name, or the file name when it has none), as v2:FR-006 says.
5. **Returning to the list:** unchanged. The breadcrumb and the post-delete redirect go to the bare list; charge A7 is accepted, not fixed.
6. **T1:** the counter is set in `--foreground` at regular weight. No new token; T1 is closed by not using the muted colour on the fill.
7. **Mobile control height:** 36 px, the existing sizes. No new size variant.
8. **Kitchen sink:** some seeded sources get a real file and thumbnail; the rest keep the fallback, so the gate shows both states.
9. **Shared extraction:** C1–C4 are moved to shared components and `/persons` switches to them with no visual change; the gate takes control shots of `/persons`.
10. **Heading (A5):** fixed on `/sources` only. `/persons` is left as is and noted as a separate small fix.
11. **Default table sort:** upload date descending with a visible Dodano column, the same order as the gallery and as today.
12. **Roadmap:** the change stays outside the roadmap, like `persons-list-views`; v2:FR-006 is noted as met along the way.

Canvas boards `Main`, `Rejestr`, `Stany` and `Mobile` were updated to decisions 4, 6 and 7 (name-only search placeholder, counter in the text colour, 36 px mobile controls). Board `Rejestr` draws the ⋯ button but not its menu; decision 1 defines the menu.

## Open Questions

Answered in „Decisions” above; kept as asked.

1. **Row actions in the table (⋯).** Drop the column, or offer „Otwórz” and „Edytuj” (both possible with today's index props), and what about „Usuń”, which needs the impact list the index does not have? Prior decision: no edit or delete on list cards.
2. **Where filtering happens and what is in the URL.** Browser-side search and kind filter over the loaded list (canvas assumption), or server-side `q` like `/persons`? Which of `q`, kind, `view`, `sort` are URL parameters echoed in `filters`?
3. **Meaning of N.** Linked events (a marriage akt counts 1) or fact rows as on the show page and in the delete dialog (the same akt counts 2)? The noun follows: „wydarzeń” or „faktów”.
4. **Search scope.** Name only, as FR-006 says, or name and pochodzenie, as the desktop boards' placeholder says? Board `Mobile` says name only.
5. **Returning to the list.** Accept that the breadcrumb and the post-delete redirect reset the view (A7), or carry the list parameters?
6. **Secondary text on a tinted fill (T1).** Add a neutrally named role, reuse `--tree-meta` as is, or set the counter in `--foreground`?
7. **Mobile control height.** Keep the existing 36 px controls or introduce 44 px for this view, which no current size provides?
8. **Thumbnails in the kitchen sink.** Seed real files and thumbnails for some sources so the gate can show the gallery, or accept fallback-only shots as earlier gates did?
9. **Scope of shared extraction.** C1–C4 touch `/persons` files. `CLAUDE.md` limits a UI change to one view plus global tokens; is moving persons code to shared components, without changing how `/persons` looks, inside that limit?
10. **Heading (A5).** Fix on `/sources` only, or on `/persons` as well?
11. **Default table sort and the Dodano column.** Keep upload date descending (today's order, test `SourcesTest.php:32-51`) with a visible Dodano column, or sort by Rok?
12. **Roadmap.** No slice covers this change; record it as outside the roadmap, as `persons-list-views` was, or attach it to parked v2:FR-006?
