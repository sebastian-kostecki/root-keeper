# Sources list — Gallery and Table views — Plan Brief

> Full plan: `context/changes/sources-list-views/plan.md`
> Research: `context/changes/sources-list-views/research.md`

## What & Why

`/sources` gets a toolbar (search by name, kind filter with counters, Galeria | Tabela switcher) and two views of the same list. The gallery is for recognising a document by its image; the table is for finding it by year, type and entry number. Each source also says whether it confirms anything, so a source that was uploaded and never linked is visible without opening sources one by one.

## Starting Point

Today `/sources` is one card grid with a truncated one-line name, no search, no filter and no link information. At 390 px the grid breaks: a long name pushes the single column past the screen. Most of the needed controls already exist on `/persons`, but inside its own files.

## Desired End State

The user types part of a name and the list narrows at once; counters next to Wszystkie / Akty / Nagrobki / Inne show how many sources match. Cards show a 4:3 image, a two-line name, a line such as „akt urodzenia · 1878 · nr 47”, pochodzenie and „potwierdza 2 wydarzenia” or „bez powiązań”. The table shows the same sources in sortable columns with a ⋯ menu („Otwórz”, „Edytuj”). The view, search, kind and sort are in the URL, so a refresh or a shared link restores them. Both views work at 390 px.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Row actions | ⋯ menu with „Otwórz” and „Edytuj”; no delete from the list | Deleting needs the impact list that only the source page has. | Research |
| Where filtering happens | In the browser; `q`, `kind`, `view`, `sort` in the URL, echoed by the server | The whole list is already in the page, and counters need no extra query. | Research |
| Meaning of N | Linked events; a marriage akt counts 1 | The text says „wydarzeń”, and one akt confirms one marriage. | Research |
| Search scope | Name only (file name when there is no name) | This is what v2:FR-006 asks for. | Research |
| Search matching | Every word, any order, case ignored, Polish letters exact | The search box behaves the same as on `/persons`. | Plan |
| Chosen kind with no match | The kind stays; „Pokaż wszystkie rodzaje”, or „Wyczyść wyszukiwanie” when nothing matches anywhere | The typed text is kept and results are one click away. | Plan |
| Summary line (charge C5) | New line on the list only; source page and picker unchanged | Keeps the change to one view. | Plan |
| Demo images | Drawn in the seeder, three proportions, real thumbnail generator | No binary files or licences in the repo, and the 4:3 crop is still exercised. | Plan |
| Shared components | Empty state, search field, switcher and sortable head move out of `/persons` files; `/persons` unchanged visually | The second list reuses them instead of copying. | Research |
| Counter colour (charge T1) | Text colour, regular weight; no new token | The muted colour fails contrast on the selected fill. | Research |
| Default table sort | Upload date descending, with a visible Dodano column | Same order as the gallery and as today. | Research |
| Returning to the list | Breadcrumb and post-delete redirect go to the bare list | Accepted cost; browser Back is not affected. | Research |

## Scope

**In scope:**
- Server: `events_count` on the index, normalised and echoed `filters`, Pest tests.
- Demo seeder: drawn images and thumbnails for eight of the 18 sources; cleanup of old files.
- Shared components moved out of `/persons`; `SourceThumbnail` and `SourceEditDialog` adapted for a table row.
- The `/sources` page: toolbar, Galeria, Tabela, three empty states, `<h1>` heading, focus ring, 390 px layout.
- Screenshot gate with control shots of `/persons`.

**Out of scope:**
- Delete from the list; a menu on gallery cards.
- Server-side search, pagination, search by pochodzenie or note, diacritic folding.
- The summary line on the source page and in the picker; the `/persons` heading.
- A clear button inside the search field; board C „Lista z podglądem”.
- New tokens, packages, shadcn components, committed images, an in-repo screenshot test, a roadmap entry.

## Architecture / Approach

The server sends the whole tree's sources with a linked-event count and echoes the four URL parameters; it filters nothing. In the browser, pure functions filter by name and kind, count per kind and sort for the table; they are covered by Vitest. One query builder produces every `/sources` URL, and the visits reload only `filters`. The table and the gallery are two components over the same filtered list.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract and demo data | `events_count`, `filters`, Pest tests, seeded images | The new resource key leaking into the show page or picker payload. |
| 2. Shared components and list helpers | Four shared components, `/persons` switched to them, list helpers with tests, „before” shots | An unintended visual change on `/persons`. |
| 3. The view | Toolbar, Galeria, Tabela, empty states, 390 px layout | The edit dialog opened from a row menu; keeping the search input and the URL in step. |
| 4. Screenshot gate | „After” shots and `verdict.md` against the canvas | Drawn images hide how a real scan reads in a 4:3 crop. |

**Prerequisites:** Sail running; the demo tree seeded; Playwright available in the npx cache (as in earlier gates).
**Estimated effort:** about 4 sessions, one per phase.

## Open Risks & Assumptions

- The canvas boards were never rendered after publishing; the verdict compares against their markup and values, and differences found in the gate are fixed or explained there.
- Byte-identical control shots of `/persons` assume stable rendering between two runs; a difference is checked by eye before it is called a regression.
- The source list is assumed small enough to filter and sort in the browser (the PRD's stated scale).
- Three follow-ups stay open and are listed in the verdict: the summary line elsewhere, the `/persons` heading, list parameters on the way back.

## Success Criteria (Summary)

- A source can be found by typing part of its name, narrowed by kind, in either view, and the state survives a refresh.
- Every source shows whether it confirms an event, and unlinked sources are easy to spot and sort to the top.
- `/sources` works at 390 px in both views, and `/persons` looks exactly as before.
