# Screenshot verdict — fact-confirmed-by-any-source

Kitchen sink: `TreeViewDemoSeeder` (fresh `migrate:fresh`). Script: `shots.mjs <out> after`. 48 PNGs in `after/` (12 states x light/dark x 1280/390), 48 in `before/` (Phase 1 baseline, same states). Seeded sources have no files on disk, so thumbnails show the broken-image placeholder (seed limitation, same in `before/`).

Inspection: all states were opened in light 1280 and at least one more theme/width per state (dark 1280, light 390 or dark 390); the remaining theme/width variants of the same state were not each opened. Shots are viewport-sized (except `list`, full page), so anything below the fold of the modal is not seen, and is stated below where it matters.

Shot naming: `<theme>-<width>-<state>.png`. "All 4" = light/dark x 1280/390.

| State | Shots | Expected | Seen |
| --- | --- | --- | --- |
| List (Genowefa's row) | `*-list` (all 4) | Same ✓ after Genowefa's birth (akt) and death (gravestone); no kind on the list | As expected (light 1280 opened): "ur. 14.03.1932, Nowy Targ ✓ · zm. 02.11.2011, Kraków ✓"; Helena, Wiktor, Jan, Kacper also show ✓ with no kind text; Leon has none. |
| Przegląd: Jan | `*-overview-jan` (all 4) | Birth „Potwierdzone: akt" with two source links + kind; marriage „Potwierdzone: akt, nagrobek, inne" | As expected. Birth lists "akt-urodzenia-jan.jpg [akt]" and "akt-wspolny-jan-kacper.jpg [akt]"; marriage badge reads „Potwierdzone: akt, nagrobek, inne" in the card header next to the title at 1280 (light, dark); at 390 the birth badge fits beside the title. The marriage sources list is below the fold in the viewport shot, so it was NOT seen here (it is seen on the „Źródła" tab). |
| Przegląd: Genowefa | `*-overview-genowefa` (all 4) | Birth „Potwierdzone: akt"; death „Potwierdzone: nagrobek" | As expected (light 1280, dark 390 opened). Birth lists "akt-urodzenia-genowefa.jpg [akt]"; death badge „Potwierdzone: nagrobek". The death's source link is below the fold and was not seen. |
| Przegląd: Wiktor | `*-overview-wiktor` (all 4) | Birth „Bez źródła" + „Powiąż źródło"; death „Potwierdzone: akt, nagrobek" | As expected (light 1280 and 390 opened; the 390 death badge is below the fold, seen at 1280 only). Death badge „Potwierdzone: akt, nagrobek" at 1280. Its source links are below the fold, not seen. |
| Przegląd: Helena | `*-overview-helena` (all 4) | Death „Potwierdzone: inne" | As expected (light and dark 1280): „Potwierdzone: inne"; birth „Bez źródła" with „Powiąż źródło". Source link under the death is below the fold, not seen. |
| Przegląd: Leon | `*-overview-leon` (all 4) | Birth and death „Bez źródła" with „Powiąż źródło" | As expected (light 1280): both „Bez źródła"; birth trigger „Powiąż źródło", death trigger „Powiąż źródło" visible in the picker shot. |
| Źródła: Jan | `*-sources-jan` (all 4) | Group badges „Potwierdzone: akt" and „Potwierdzone: akt, nagrobek, inne"; each row with a kind badge; „Powiąż kolejne źródło" | As expected (light 1280 and 390). Marriage rows in link order akt, inne, nagrobek while the badge reads akt, nagrobek, inne. At 390 the badge sits under the group title and does not overflow. Fixed after the first shoot (kind badge moved to the „dodano” line, name on its own truncated line) and re-shot: at 390 the name reads „akt-uro…” / „Nagrob…” with the kind badge beneath it, Otwórz/Odepnij intact (light 390 opened). |
| Źródła: Genowefa | `*-sources-genowefa` (all 4) | Birth „Potwierdzone: akt"; death „Potwierdzone: nagrobek" with the long gravestone name truncated and „nagrobek" kept | As expected at 1280 ("Nagrobek Genowefy Pełnodanowski… [nagrobek]"). Fixed after the first shoot (kind badge moved to the „dodano” line, name on its own truncated line) and re-shot: at 390 the name reads „akt-uro…” / „Nagrob…” with the kind badge beneath it, Otwórz/Odepnij intact (light 390 opened). |
| Rodzina: Jan | `*-family-jan` (all 4) | Marriage row shows the longest badge without overflow; Ewa „Bez źródła" | As expected. At 1280 one line. At 390 (light) the badge wraps to two lines ("Potwierdzone: akt, nagrobek, / inne") inside the text column, no overflow, row intact. |
| Picker from Leon's death | `*-picker-leon-death` (all 4) | Title „Powiąż źródło", new description, every kind offered with a kind badge, newest first | As expected (light 1280 and 390). Title „Powiąż źródło", „Wybierz źródło, które potwierdza to wydarzenie."; first cards nekrolog-heleny [inne], nagrobek-jana-i-anny [nagrobek], zaproszenie-slubne [inne], then nagrobek-wiktora, Nagrobek Genowefy, Akt urodzenia z parafii. |
| Picker from Jan's birth (disabled card in view) | `*-picker-jan-birth` (all 4) | Linked source disabled and reading „Już powiązane"; kinds visible | As expected (light 1280, dark 390). "akt-wspolny-jan-kacper.jpg [akt] Już powiązane" is dimmed and disabled; cards show nagrobek/akt badges. |
| Edit mode: Genowefa's death (hint) | `*-edit-death-genowefa` (all 4) | „Potwierdzone: nagrobek" and the hint „Potwierdzone źródłem — upewnij się, że zmiana zgadza się ze źródłem." | As expected (light 1280 and dark 390): badge „Potwierdzone: nagrobek" and the new hint text; the other cards are dimmed (inert). |

## Before -> after

- **Przegląd** (`before/light-1280-overview-genowefa.png` -> `after/light-1280-overview-genowefa.png`): before, both facts read „Potwierdzone aktem", including the gravestone-backed death (the defect), and the source was plain grey text "1 źródło · akt-urodzenia-genowefa.jpg" naming only the first source. After, the birth reads „Potwierdzone: akt", the death „Potwierdzone: nagrobek", and each source is a line "name [kind]" (link). The link/click behaviour cannot be seen in a still.
- **Źródła tab** (`before/light-1280-sources-jan.png` -> `after/light-1280-sources-jan.png`): before, „Potwierdzone aktem" on both groups, rows with name only, buttons „Powiąż kolejny akt". After, „Potwierdzone: akt" and „Potwierdzone: akt, nagrobek, inne", a kind badge on each row, buttons „Powiąż kolejne źródło". At 390 the row name truncates legibly with the kind badge under it (see fix).
- **Picker** (`before/light-1280-picker-leon-death.png` -> `after/light-1280-picker-leon-death.png`): before, title „Powiąż akt", description „Wybierz akt metrykalny, który potwierdza to wydarzenie.", cards without kind. After, title „Powiąż źródło", description „Wybierz źródło, które potwierdza to wydarzenie.", each card with a kind badge; the grid scrolls inside its box as before.

## Charges

| Category | Charge | Status | Evidence |
| --- | --- | --- | --- |
| Accidental architecture | Status badge hard-codes the kind („aktem"), four surfaces | closed | „Potwierdzone: nagrobek" / „: inne" / „: akt, nagrobek" / „: akt, nagrobek, inne" on Przegląd, Źródła and Rodzina; no „aktem" anywhere in `after/` |
| Accidental architecture | „Akt" baked into the linking copy | closed for the shots covered | „Powiąż źródło", „Powiąż kolejne źródło", picker title/description, „Już powiązane", edit hint „Potwierdzone źródłem …" all seen; the empty-picker state, the empty hint on „Źródła", the toast and the list ✓ screen-reader text are not in the shot set and were not visually verified |
| Accidental architecture | The fact on Przegląd names only the first source, as text | closed for the shots covered | Each source is its own line with name and kind (Jan birth, Genowefa birth); the marriage/death lists are below the fold in the viewport shots and the link target itself is not visible in a still |
| Missing shared component | None (kind next to a name is the existing `Badge`) | closed | Same secondary badge on Przegląd rows, Źródła rows and picker cards |
| Missing tokens | None (one state, one variant) | closed | Confirmed badge is the same dark/white pill for every kind in light and dark |

## Open observations (not fixed in this phase)

1. **Źródła tab at 390 px**: was open (name column collapsed to one character); closed by moving the kind badge under the name in `sources-tab.tsx` and re-shooting `after/`.
2. Below-the-fold content in the viewport shots was not seen: source lists under Jan's marriage, Genowefa's, Wiktor's and Helena's deaths on Przegląd.
3. Not each of the 48 files was opened; the unopened ones are the other theme/width variants of states seen in at least two variants.
