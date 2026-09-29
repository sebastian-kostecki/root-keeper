# Persons list — List and Table views — Plan Brief

> Full plan: `context/changes/persons-list-views/plan.md`
> Research: `context/changes/persons-list-views/research.md`

## What & Why

`/persons` gets two views the user switches between: **Lista**, grouped by the surname's first letter, and **Tabela**, with sortable columns. The chosen view lives in the URL (`?view=tabela`). The design is the canvas https://claude.ai/artifact/LT3qzYRdcYfkxZchBJ7LEN, boards Main, Tabela, Stany and Mobile. The goal is to make a growing tree easy to scan and compare, with places, residence, religion and source status shown next to each name.

## Starting Point

Today the page is an ungrouped list of cards showing name and lifespan. Search is server-side via `?q=`. The payload carries formatted dates only, and nothing tells the list whether a date is confirmed by a scan. The typed search rebuilds the URL from `q` alone, so any other param would be lost.

## Desired End State

- **Toolbar:** search + a Lista | Tabela switcher that keeps each other's state. Refresh, Back and closing the preview return to the same view, search and sort.
- **Lista:** avatars in gender colour, „Probant” badge, meta line „ur. date, place ✓ · zm. date, place”, grouped K…Ż with „Bez nazwiska” last.
- **Tabela:** sorts by Osoba / Urodzenie / Zgon, with unknown dates last. Its ⋯ menu opens the preview or the tree.
- **States:** empty, no results and searching all have their own states.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Views | Lista (A) + Tabela (B); Oś życia dropped | The user picked A and B on the canvas | Plan (user) |
| View in URL | `?view=tabela`; missing or invalid = lista; echoed in `filters.view` | Survives refresh and Back, and Pest can test it like `q` | Plan (user) |
| Confirmation ✓ | Shown; `withExists('scans')` → `is_confirmed: bool`, no scan bodies; `PersonsTest:187` changed deliberately | One light query; the rule stays server-side for „skany → źródła” | Plan (default) |
| Delete in row menu | Not offered; ⋯ = „Otwórz podgląd”, „Pokaż na drzewie” | S-05 and CLAUDE.md require the impact list, which the list payload lacks | Research → Plan (default) |
| Table sort | In-browser over the loaded list; `?sort=` (`nazwisko`, `urodzenie`, `zgon`, `-` = descending), table only | The list is complete and unpaginated; the URL keeps it across refresh and preview | Plan (default) |
| Date sort rule | Mirrors `PersonOrdering`; unknown dates last in both directions; „ok.” ignored | Matches the order the modal already uses | Research → Plan (default) |
| Date data | `date_parts` moves into `EventResource` for every event | Formatted strings („ok. 1948”, „03.1948”) don't sort | Research → Plan (default) |
| Meta line in Lista | „ur. X, place ✓ · zm. Y, place”; `formatLifespan` unchanged elsewhere | The user chose the canvas row, whose value is the places | Plan (default) |
| Visit shape | One query builder; switcher and sort reload only `filters` | No control can drop another's param, and no refetch of the list | Research → Plan (default) |
| Proband | `rootPersonId` index prop | Same pattern as `TreeController`, no extra query | Research → Plan (default) |
| Shared components | New `ui/table.tsx` (shadcn markup) and `PersonAvatar` extracted from the modal header | Missing component + second consumer; no new packages or tokens | Plan (default) |

## Scope

**In scope:**
- `filters.{view,sort}` + `rootPersonId` + `is_confirmed` + `date_parts`, with Pest tests.
- `ui/table`, `PersonAvatar` and `persons-index` helpers, with Vitest tests.
- The two views, the toolbar and the three states, responsive to 390 px.
- Seeder additions for the kitchen sink.
- Out-of-repo screenshot gate with `verdict.md`.

**Out of scope:**
- Delete from a row; Oś życia.
- Server-side reordering, pagination, extra filters, search by place or year.
- New tokens or packages; changes to `formatLifespan` callers.
- An in-repo screenshot test; remembering the view outside the URL.

## Architecture / Approach

The contract comes first: the controller normalises `view`/`sort`, and the resources add the confirmation flag and date parts. Next come the shared pieces: table, avatar, and pure helpers for query building, grouping and sorting. The page then composes a toolbar and one of two view components over the unchanged, surname-ordered payload. Every visit goes through `buildPersonsQuery`: search reloads fully, while view or sort changes reload only `filters`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract | `filters.view/sort`, `rootPersonId`, `is_confirmed`, `date_parts` + tests | `date_parts` move must not change the marriage payload |
| 2. Shared pieces | `ui/table`, `PersonAvatar`, tested helpers | Modal header avatar must not change visually |
| 3. The view | Toolbar, Lista, Tabela, states, 390 px | Visits dropping params; sticky column in dark mode |
| 4. Screenshot gate | Seeder cases, shots, `verdict.md` | Earlier gates' `/persons` selectors must still match |

**Prerequisites:** Sail running; demo seeder available; Playwright from the npx cache, as in the person-modal gate.
**Estimated effort:** ~2–3 sessions across 4 phases.

## Open Risks & Assumptions

- The tree size stays in the hundreds, so in-browser sort and grouping stay instant.
- The parallel `scans-become-sources` change may rename „akt” to „źródło”. Only the sr-only text and the tooltip would change, since the rule is server-side.
- Postgres collation (en_US.utf8, musl) sorts Polish surnames bytewise (verified: Ł/Ż/Ć after Z). All client ordering (groups, rows within a group, the table) therefore uses `Intl.Collator('pl')`, and the server order is left as is.

## Success Criteria (Summary)

- A user can switch Lista ↔ Tabela, search and sort without losing either setting, including after refresh and after closing a preview.
- Confirmed dates show a ✓ in both views, and the table sorts dates correctly with unknown dates last.
- Pest, Vitest, lint, types and build pass, and the screenshot verdict matches the canvas.
