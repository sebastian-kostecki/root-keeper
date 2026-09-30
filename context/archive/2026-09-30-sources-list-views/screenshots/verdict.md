# Screenshot verdict — sources-list-views

Kitchen sink: `TreeViewDemoSeeder` (re-seeded before the shoot). Script: `shots.mjs <out> sources-after` (also part of `all`). 40 PNGs of `/sources` in `after/`: 7 states + empty tree, each light/dark x 1280/390 (32), plus at 1280 light/dark: menu open, edit dialog, focus on a card, focus on a row link (8). `before/` (Phase 2) was not rewritten; the `/persons` control shots in `after/` are untouched. The seeder (Phase 1) draws an image and a real thumbnail for eight sources; the other ten keep the fallback icon.

Comparison basis: the canvas boards (`Main`, `Rejestr`, `Stany`, `Mobile`, https://claude.ai/artifact/McYg2Lo8Z2wZcKEHAoqpzU) were NOT compared pixel by pixel. The Artifact read returned only the canvas type instructions and file index, not the board contents. Verdicts below are against the Desired End State text of `plan.md`. The human still owes the board comparison.

Inspection: opened light 1280 of Galeria, Tabela, menu, edit dialog, both focus shots, empty kind; dark 1280 of Tabela by year, Galeria focus, menu; light 390 of Galeria, Tabela, search, one kind, no match; dark 390 of search and empty tree. The remaining variants share code paths with those and were not opened one by one (theme/width siblings of opened states).

## Fixed during the gate

- **Kind filter overflowed at 390 px.** First shoot: the four-item filter measured 398 px right edge inside a 366 px gutter, so „Inne” was clipped (page itself did not scroll sideways, `scrollWidth` = 390). Fix: `resources/js/components/segmented-toggle.tsx`, items with a counter get `max-sm:px-1.5`. Re-shot and re-measured: right edge 350 px, all four items visible, controls still 36 px.

## Verdicts

| State | Shots | Verdict |
| --- | --- | --- |
| Galeria | `*-sources-galeria` (4) | Matches. Light 1280: 4 columns, 4:3 thumbnails with fallback icon, name clamped to 2 lines, summary „akt urodzenia · 1878 · nr 47”, pochodzenie truncated, „potwierdza N wydarzenie/wydarzenia” or muted „bez powiązań” pinned to the card bottom. Toolbar controls 36 px, counters in text colour, regular weight. 390: 2 columns, no sideways page scroll; the view switcher is icon-only beside the search field, kind filter on its own row. Dark 390 opened only through the search state (same structure). |
| Tabela | `*-sources-tabela` (4) | Matches. 1280: columns Źródło / Rodzaj / Rok / Nr wpisu / Pochodzenie / Potwierdza / Dodano / ⋯, 40 px thumbnails, „—” for empty cells, Dodano sorted descending by default with an arrow. 390: table scrolls sideways inside its card, Źródło column stays sticky, header row cut at „Rok” as expected. |
| Tabela by year | `*-sources-tabela-rok` (4) | Matches. Rok header shows the ascending arrow (dark 1280), rows ordered 1878, 1890, 1932, 1932, then rows without a year. |
| Search with counters (Inne 0) | `*-sources-search-counters` (4) | Matches. „lipn”: Wszystkie 5, Akty 4, Nagrobki 1, Inne 0; the Inne item is dimmed and disabled (a kind with counter 0 is disabled unless chosen); the empty-kind state is reached with `?q=lipn&kind=inne`. Gallery shows the five matches. |
| One kind | `*-sources-one-kind` (4) | Matches. Nagrobki selected, counters still show the whole tree (18/10/5/3), five nagrobek cards. |
| No match | `*-sources-no-match` (4) | Matches. „Brak wyników”, „Nie znaleziono źródeł pasujących do „Kowalczyk”.”, button „Wyczyść wyszukiwanie”; all counters 0; toolbar stays. |
| Empty kind | `*-sources-empty-kind` (4) | Matches. „Brak wyników”, „Wśród innych źródeł nic nie pasuje do „lipn”.”, button „Pokaż wszystkie rodzaje”; Inne selected with 0. |
| Empty tree | `*-sources-empty-tree` (4) | Matches. „Nie dodano jeszcze żadnego źródła”, description, „Dodaj źródło”; the toolbar is hidden. |
| ⋯ menu | `*-sources-menu-open` (light, dark 1280) | Matches. Menu with „Otwórz” and „Edytuj” opens under the first row's ⋯ button in both themes. |
| Edit dialog from a row | `*-sources-edit-dialog` (light, dark 1280) | Matches (light opened). Dialog „Edytuj źródło” with the row's data („Księga ludności Lipnicy”, rok 1890), opened from the menu item. Viewport-sized shot; the fields below „Notatka” are not visible. |
| Focus on a card | `*-sources-focus-card` (light, dark 1280) | Matches. The first card shows the 3 px ring after Tab (visible in light and dark, but a low-contrast grey in dark). |
| Focus on a row link | `*-sources-focus-row-link` (light, dark 1280) | Matches (light opened). The ring wraps thumbnail plus name of the first row. |

## Differences and observations (not fixed)

1. At 390 px the kind filter is narrower than the full-width row (ends about 16 px short of the right gutter) because the items shrink to content, not to the row. Board `Mobile` says full-width row; visually it reads as left-aligned. Left as is: the plan asks for a full-width row and the fix would stretch items (`flex-1`), a design choice.
2. Dark theme focus ring on cards is a dim grey (`ring-ring/50`), visible but weak. Same token as the rest of the app; not changed.
3. Card summary line wraps to two lines for sources without type/year („akt metrykalny · dodano 30.09.2026”) at 1280 and always at 390; heights stay equal because the events line is pinned to the bottom.
4. The menu-open shot places the popover over the second row; that is Radix positioning, not a defect.

## Open follow-ups (not in this change)

1. The summary line („typ · rok · nr”) on the source page and in the source picker, named in the plan as follow-up work.
2. The `/persons` heading, named in the plan as follow-up work.
3. List parameters on the way back, named in the plan as follow-up work (the list parameters are not carried back from the source page).

## Requirements

- **v2:FR-006** („wyszukać skan po nazwie”, `context/foundation/roadmap.md:421`): met. The search field „Szukaj po nazwie” filters the list by name, with per-kind counters and the no-match state (`light-1280-sources-search-counters.png`, `light-390-sources-no-match.png`).
