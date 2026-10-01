# Screenshot verdict — app-shell-sidebar

Kitchen sink: `TreeViewDemoSeeder` (demo tree `tree-demo@example.com`, empty tree `tree-demo-empty@example.com`). Script: `shots.mjs <out>` (two logins per run, one per user). 34 PNGs in `after/`: the 24 names of `before/` (all present, each has its twin) plus 10 new ones (per theme: collapsed `/tree`, source page, empty-tree `/persons` at 1280; `/tree` and the empty-tree sheet at 390). `before/` was not rewritten.

Comparison basis: the six A boards (https://claude.ai/artifact/EgWsEjc3AE64kvCcXpKAKN) were NOT compared. The Artifact `read` returned only the canvas type instructions and a file index (24 files of `.dc.html` markup), not rendered boards. Verdicts below are against the Desired End State of `plan.md`. The human still owes the board comparison (plan item 5.5).

Brand name: the shots print „Root Keeper”; local `.env` has `APP_NAME="Root Keeper"` (the shared `name` prop), so this says nothing about production.

Inspection: opened by eye — light 1280: persons, collapsed tree, source page, empty tree, focus on nav; dark 1280: collapsed tooltip, user menu; light 390: sheet open, `/tree`; dark 390: empty-tree sheet. Not opened one by one: the theme and width siblings of those states, `*-expanded-tree`, `*-expanded-sources`, `*-expanded-settings`, `*-collapsed-user-menu`, `*-sheet-user-menu`, `*-closed-persons`, dark source page and dark empty tree at 1280. They share code paths with the opened ones.

## Verdicts

| State | Shots | Verdict |
| --- | --- | --- |
| Expanded, list pages | `*-1280-expanded-persons`, `-sources`, `-tree` | Matches the End State (persons opened, light). No top bar; brand tile „RK” + „Root Keeper” + collapse button; Drzewo / Osoby 41 / Źródła 18, counters regular weight at the right; current item filled; account row with initials, name, e-mail, up/down chevrons; no footer links. Heading starts the card, 24 px down (the `Osoby` heading is about 38 px from the card top because of its own 16 px padding and page structure; pages are untouched by plan). |
| Expanded, settings | `*-1280-expanded-settings` | Not opened. Expected: no trail, settings stay English (deferred). |
| Parent trail on `/tree` with proband | `*-1280-collapsed-tree`, `*-390-closed-tree` | Shows „Drzewo” (link, no trailing chevron after the fix) above „Jan Kowalski” (opened, light). See difference 2. |
| Source page | `*-1280-expanded-source` | Shows „Źródła” (link) above the source name, „Źródła” current in the sidebar (opened, light). The viewer area with 64 px less chrome: the card is taller and the viewer runs below the 800 px fold as before; acceptable at this height, human to judge (5.7). The image itself is a broken-image placeholder, see difference 3. |
| Collapsed rail | `*-1280-collapsed-persons`, `-collapsed-tree` | Matches. Lettermark, expand button under it, three icons, avatar at the bottom, current item filled (opened `collapsed-tree` light). |
| Collapsed tooltip | `*-1280-collapsed-tooltip` | Matches. „Źródła · 18” beside the hovered icon (opened, dark). |
| User menu | `*-1280-expanded-user-menu`, `*-1280-collapsed-user-menu` | Expanded opens above the row: avatar, name, e-mail, „Ustawienia”, „Wyloguj” (opened, dark). Collapsed not opened; expected to open on the right. |
| Keyboard focus | `*-1280-expanded-focus-nav` | Matches. A ring around „Źródła” is clearly visible in light (opened); dark not opened. |
| Empty tree | `*-1280-expanded-empty-tree`, `*-390-sheet-empty-tree` | Counters absent in both (opened light 1280, dark 390). See difference 1 for the avatar. |
| Phone closed | `*-390-closed-persons`, `-closed-tree` | 56 px bar with menu button and brand, no sideways scroll (`closed-tree` opened, light). |
| Phone sheet | `*-390-sheet-open`, `-sheet-user-menu` | Brand, close button (X), 44 px rows with counters, account row at the bottom, page dimmed behind (opened, light). |
| Dark theme | all `dark-*` | Opened dark: tooltip, user menu, empty sheet. Tokens hold: current fill, ring and tile are visible. Counters and e-mail line read lighter grey on the dark sidebar; legible. |

## Differences from the boards / End State

Differences 1 and 2 were fixed during the gate (see their entries; the `use-initials.tsx` change is recorded in the plan addendum and covered by `use-initials.test.ts`); the rest are left for the human and `/10x-impl-review`:

1. **Initials of the empty-tree user read „D(”.** The demo name is „Demo drzewa (pusty)”; the initials take the first letter of the last word, which is „(”. The seeded name triggers it; a real name would not. Seen in `*-expanded-empty-tree` and `*-sheet-empty-tree`. **Fixed:** `use-initials.tsx` now skips words that do not start with a letter or digit, so the initials are „DD”; shots re-taken.
2. **Trail on `/tree` has a chevron after the single link** („Drzewo ›”). The plan says chevrons separate items when there is more than one; here one link is shown and the current page is not, so the chevron leads to nothing visible above the heading. **Fixed:** `breadcrumbs.tsx` renders a separator only between items; shots re-taken.
3. **Source page image is broken** (alt text in a grey box). The seeded file is not served in this environment; it is not a shell matter, recorded so it is not mistaken for one.
4. **Empty-tree sheet in dark 390 was captured mid-slide** (sheet at 278 px, rows start at the screen edge) while the light sheet is 288 px. The shot is taken 350 ms after the click; the sheet animation was not finished. A capture artifact, not a layout defect; re-shoot with a longer wait if a clean board comparison is needed.
5. **Account row e-mail is truncated** („tree-demo@examplec…”) in the expanded sidebar at 1280 and for the empty-tree user („tree-demo-empty@ex…”). The sheet at 390 shows the full address. Ellipsis in the row is what `truncate` does; the board may show a shorter e-mail.
6. The tooltip text renders tightly („Źródła·18”); the spaces around the dot are there but narrow at 12 px.

## Deferred (not in this change)

1. Settings pages and `layouts/settings/layout.tsx` stay in English.
2. Auth layouts (login, register, reset) still show the Laravel mark via `AppLogoIcon`.
3. Production `APP_NAME` must be set to „Root Keeper” by hand on the server; until then the sidebar prints whatever the environment says.

## Automated checks run with this phase

`vendor/bin/sail npm run check`, `types:check` and `tests/Feature/AppShellTest.php tests/Feature/Auth` (25 tests) all pass.
