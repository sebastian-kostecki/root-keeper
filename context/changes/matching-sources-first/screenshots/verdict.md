# Screenshot verdict — matching-sources-first

Kitchen sink: `TreeViewDemoSeeder` (re-seeded before each shoot; the seeder deletes and recreates its own demo users). Script: `shots.mjs <out> before|after`. 16 PNGs in `before/` (Phase 1 baseline, before any picker edit), 16 in `after/` (4 states x light/dark x 1280/390). Seeded sources have no files on disk, so thumbnails show the broken-image placeholder (seed limitation, same in `before/`).

Inspection: all four states were opened in light 1280; `picker-jan-birth` also in dark 390. The other theme/width variants were not each opened. Shots are viewport-sized and the picker scrolls inside its box, so tiles below the fold of the box are not seen except in the `-end` shot.

Shot naming: `<theme>-<width>-<state>.png`. "All 4" = light/dark x 1280/390.

| State | Shots | Expected | Seen |
| --- | --- | --- | --- |
| Leon's death, as opened | `*-picker-leon-death` (all 4) | „Pasujące akty" with „Zgony Lipnica 1932 nr 14", then „Pozostałe źródła" | As expected (light 1280): heading „PASUJĄCE AKTY" over one tile „Zgony Lipnica 1932 n…" [akt] „zgonu · 1932"; heading „POZOSTAŁE ŹRÓDŁA" below it. |
| Leon's death, scrolled to the end | `*-picker-leon-death-end` (all 4) | Tail of „Pozostałe źródła"; the death akt is not there | As expected (light 1280): last tiles are akt-slubu-jan-anna and akt-urodzenia-jan, each with the upload date; no „Zgony Lipnica" tile. |
| Jan's birth | `*-picker-jan-birth` (all 4) | „Pasujące akty": linked odpis disabled, then the 1878 akt; „Pozostałe źródła" starts with „Księga ludności Lipnicy" | As expected (light 1280, dark 390): odpis first, dimmed, „urodzenia" + „Już powiązane"; then „Urodzenia Lipnica 187…" „urodzenia · 1878", selectable. „POZOSTAŁE ŹRÓDŁA" follows; its first row is below the fold of the box. At 390 the headings and tiles fit, no overflow. |
| Jan and Anna's marriage | `*-picker-jan-marriage` (all 4) | No headings, same order as before, tiles with „typ · rok" | As expected (light 1280): no headings; order Księga ludności, Odpis, nekrolog-heleny, … as in `before/`; „inny · 1890" under the Księga tile, „urodzenia" under the odpis, upload date under the untyped nekrolog. |

## Before -> after

- **Leon's death, as opened** (`before/light-1280-picker-leon-death.png` -> `after/light-1280-picker-leon-death.png`): before, one grid of mixed kinds, newest first, with no sign of the death akt; the tile line was the upload date only. After, the death akt is the only tile under „Pasujące akty", with its type and year, and everything else sits under „Pozostałe źródła".
- **Leon's death, end** (`before/…-death-end.png` -> `after/…-death-end.png`): before, the last tile was „Zgony Lipnica 1932 n…" (upload 2025). After, the last tile is akt-urodzenia-jan; the death akt has moved to the top.
- **Jan's birth** (`before/…-jan-birth.png` -> `after/…-jan-birth.png`): before, newest first with the linked odpis as the second tile among untyped and typed sources. After, the linked odpis and the 1878 akt lead under „Pasujące akty", the odpis still disabled and reading „Już powiązane".
- **Jan and Anna's marriage** (`before/…-jan-marriage.png` -> `after/…-jan-marriage.png`): same tile order and no headings; only the tile line changed from the upload date to „typ · rok" where either is set.

## Charges

| Category | Charge | Status | Evidence |
| --- | --- | --- | --- |
| Accidental architecture | The picker was not told which event it serves | closed | The death picker shows „zgonu" first, the birth picker „urodzenia" first, the marriage picker no match; both callers pass `eventType` |
| Accidental architecture | The picker tile did not show „typ · rok" | closed | „zgonu · 1932", „urodzenia · 1878", „inny · 1890", „urodzenia"; the upload date only on untyped tiles |
| Missing shared component | The modal's group label is a class string inlined in three places | open by decision | Copies at `overview-tab.tsx:69`, `family-tab.tsx:149` and the picker (`groupLabelClass`); follow-up GitHub issue: <link pending — to be created after the user's go-ahead> |
| Missing tokens | None | closed | Headings use the existing muted label style; tiles keep `Badge variant="secondary"` |

## Not shot

- **Only matches** (every source matches; only „Pasujące akty"): not in the seed data. Checked by hand on a fresh account (manual 2.11) and by the unit tests of the split.
- **Empty picker and load error**: not in the shot set, unchanged code paths. Checked by hand in Phase 2.
- **Type edited in the source modal** (2.9): interaction, not a still. Checked by hand in Phase 2.

## Open observations (not fixed in this change)

1. In `light-1280-picker-jan-birth.png` the 1878 tile sits about 10 px lower than the linked odpis tile beside it, because the odpis has one more line („Już powiązane") and the tile buttons centre their content vertically. Cosmetic, only visible when a linked tile and a shorter tile share a row. Not fixed: outside the plan's contract.
2. Not every theme/width variant was opened (see Inspection).
