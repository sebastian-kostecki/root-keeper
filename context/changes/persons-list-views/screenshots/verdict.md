# Verdict: persons-list-views (Phase 4 screenshot gate)

Shots: `after/` (27 PNGs) from `shots.mjs after`, demo tree seeded with `TreeViewDemoSeeder` (now including Genowefa Pełnodanowska = full data, "Bezimienna" = no surname/no dates). `before/` untouched.
Canvas: https://claude.ai/artifact/LT3qzYRdcYfkxZchBJ7LEN (boards Main, Tabela, Stany, Mobile). Canvas boards were compared from their source; Mobile.dc.html was not read, mobile is judged against the same tokens and the Main/Tabela structure.

## A. Main (Lista)

- Lista light/dark, desktop: match. Grouped by surname initial with count ("16 osób", "1 osoba"), bordered list per group, avatar + name + meta + chevron, "Probant" badge, ✓ after confirmed date.
- Full-data row (Genowefa): match. "ur. 14.03.1932, Nowy Targ ✓ · zm. 02.11.2011, Kraków" on one muted line.
- Long name (Zuzanna ...): match. Wraps to 3 lines on 390 px, chevron stays aligned. The canvas truncates it with an ellipsis; wrapping is acceptable (readable).
- Difference: jump links have no "Nazwiska:" prefix label and have a bordered chip look (canvas: plain text links). Cosmetic, kept.
- Difference: the "Bez nazwiska" group is present and last (as in canvas). Persons without any dates (Nina, Bezimienna) show no meta line; the canvas shows "brak dat". Listed, not fixed (copy decision, plan does not specify it).
- Difference: the "Probant" badge has no star icon (canvas has one). Cosmetic.
- Difference: the canvas subtitle "13 osób w Twoim ..." is a mock; the app keeps the existing copy on purpose.

## B. Tabela

- Columns Osoba / Urodzenie / Zgon / Zamieszkanie / Wyznanie / ⋯, avatar + name + Probant, tabular dates, ✓, place on a second muted line, "—" for empty, "data nieznana" for a dated-less death (Józef): match.
- Sort headers with arrows, `-urodzenie` sorted newest first with the arrow down on Urodzenie, unknown dates last: match.
- Row menu (light/dark, desktop and 390): "Otwórz podgląd" and "Pokaż na drzewie" open under the ⋯ button. Canvas also shows "Usuń osobę", which is out of scope for this change. Difference: explained.
- Difference: canvas footer "13 osób · sortowanie: ..." is not built (not in the plan).

## Stany

- Empty tree (light/dark, desktop and 390 px): match. Dashed container, icon, title, hint, "Dodaj osobę".
- No results: match. Icon, "Brak wyników", `Nie znaleziono osób pasujących do „zzzzzz”.`, "Wyczyść wyszukiwanie" (outline button), view kept.
- Searching: match. Focus ring on the input, spinner in the field, previous results dimmed. The canvas shows a clear (x) button in the field and the app shows one too (blue x while the query is set).
- Row states (hover, focus-visible, probant, unknown gender, long name): hover/probant/long name seen and match; keyboard focus was not captured by the script.

## Mobile (390 px)

- Lista: match. Search, then the switcher, then jump chips wrapping onto two lines, then groups. Rows stay legible.
- Tabela: match. The table scrolls horizontally inside its container and the first column ("Osoba") sticks; after scrolling right only the ⋯ column and the trailing header remain visible beside it. The header text of the scrolled column peeks out next to the sticky one ("e"), which is normal for overflow-x tables and only visible in the scrolled screenshot.
- Dark: matches light structurally, no contrast defects seen.

## Earlier gate lookup

`a[href*="person="]` on `/persons`: 41 links found (the original person-modal `idOf()` also filters by link text, which still works because names are link text in Lista).

## Defects

No real defects found; no app code changed.
