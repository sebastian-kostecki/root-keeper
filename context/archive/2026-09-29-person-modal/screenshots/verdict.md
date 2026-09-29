# Screenshot gate verdict — person-modal

Run: `LD_LIBRARY_PATH=<nss/nspr/asound libs> node context/changes/person-modal/screenshots/shots.mjs context/changes/person-modal/screenshots/after`
(41 shots, no script errors; seeded with `TreeViewDemoSeeder`, dev server at http://localhost:8000). Shots are in `screenshots/after/`.
Naming: `light-*` / `dark-*` = 1280px, `mobile-*` = 390px.

Judged against: the plan's "Desired End State" text. The design canvas (https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F) was
not reachable from here, so no pixel comparison against the four boards was made. There is no screenshot test in the repo: no baseline exists and none was updated.

Kitchen-sink data (seeder appended after the tree-sheet persons; ids read from `/persons` by name):
Jan (proband) has a birth scan and a marriage scan (Anna); Leon Kowalski has an approximate death without a scan; Nina Rzadka is sparse (name and gender, one relative);
Wiktor Skanowski has a death scan. The scan files are factory rows without files, so thumbnails show the broken-image placeholder (seed artefact, not an app defect).

## Charges

| Charge | Shots | Verdict | Notes |
| --- | --- | --- | --- |
| Partial-date input | light-edit-birth-hint, light-edit-birth-ambiguous, light-edit-death-error, light-create-filled, dark-edit-death-error, mobile-section-edit | PASS | `12.3.1948` reads „12.03.1948 dokładna data”; `marzec 1948` reads „03.1948 miesiąc i rok”; `zima 2015` shows the red „Wpisz np. 1948, 03.1948 lub 12.03.1948”. Note: after „Zapisz” the browser's native validation bubble (same text) covers the red line until dismissed; the line is readable once it goes away. The `12.3.48` shot (`*-edit-birth-ambiguous`) was not inspected for the „1848 czy 1948?” wording. |
| Section card | light-overview-full, light-edit-personal, dark-overview-full | PASS | Pencil `Edytuj <title>` on each card, inline form with Anuluj/Zapisz, other cards dimmed while one edits. Minor: every card has an uppercase caption („DANE OSOBOWE”) above a card that repeats the same title. |
| Tabs | light-family, light-sources, dark-family, dark-sources, light-focus-tab | PASS | Przegląd / Rodzina n / Źródła n; counters match the plan (Jan 9 and 2, Leon 1 and none, Wiktor none and 1); focus ring visible on the tab. |
| Lifespan helper | light-menu-non-proband (Leon), light-overview-full | PASS | `1940 – ok. 2015` with an approximate death, `ur. 1975` for one date, nothing for no dates (Nina). |
| `genderCard` move | light-family, dark-family, light-create, dark-overview-full | PASS | Gender colours on the header avatar, family rows and the create avatar (unknown gender neutral) in both themes. Tree-sheet cards were not re-shot here (covered by the variant C gate). |
| Layered-edit removal | light-edit-personal, light-edit-birth-hint | PASS | Editing is only the inline card form; no second edit dialog opens from the modal. |

## Board states

| State | Shots | Verdict | Notes |
| --- | --- | --- | --- |
| Przegląd full | light-overview-full, dark-overview-full, light-overview-approx-death | PASS | Cards Dane osobowe, Urodzenie (1 skan · file name), Małżeństwo z Anna Wiśniewska, Zgon; „około 2015” for Leon. |
| Przegląd sparse (empty CTAs) | light-overview-sparse, dark-overview-sparse | PASS | „Nie znamy daty ani miejsca urodzenia.” + „Uzupełnij urodzenie”; „Osoba żyje albo nie wiemy, że zmarła.” + „Oznacz zgon”. |
| Editing „Dane osobowe” | light-edit-personal, dark-edit-personal | PASS | |
| Urodzenie edit with a valid parse hint | light-edit-birth-hint, dark-edit-birth-hint | PASS | „Potwierdzone aktem” hint kept above the field. |
| Zgon edit with a date error | light-edit-death-error, dark-edit-death-error | PASS | See the native-bubble note above. |
| Zgon: „Najpierw odepnij skany od zgonu…” | light-edit-death-scan-error, dark-edit-death-scan-error | PASS | Server error shown under the unchecked „Osoba nie żyje”. |
| Guard bar (keyboard exit, Esc) | light-guard-bar, dark-guard-bar | PASS | „Masz niezapisane zmiany w danych osobowych.” with Porzuć / Zapisz. |
| Guard bar (pointer click on a tab) | light-guard-tab-click, dark-guard-tab-click | PASS (after fix) | Initial run failed: blur-triggered `change` called `markDirty(true)`, which cleared the pending exit. Fixed in `section-editor.tsx` (`markDirty` now clears `pending` only when the form becomes clean); re-shot, bar shows after a real click. |
| „⋯” menu, non-proband | light-menu-non-proband, dark-menu-non-proband | PASS | Pokaż na drzewie / Ustaw jako probanta / Usuń osobę…. |
| „⋯” menu, proband | light-menu-proband, dark-menu-proband | PASS | „Ustaw jako probanta” absent. |
| Rodzina | light-family, light-family-row-menu, dark-family, dark-family-row-menu | PASS | Rodzice / Małżeństwa (date, place, source status) / Dzieci; gender-coloured rows; row „⋯” holds „Usuń relację”; footer hint about adding relatives on the tree. Minor: the open row menu overlaps the footer hint text. |
| Źródła | light-sources, dark-sources | PASS | One group per event, file name + „dodano DD.MM.YYYY”, Otwórz / Odepnij, „Powiąż kolejny skan” / „Powiąż skan”, „Bez źródła” group for the second marriage. Zgon group (Wiktor) not shot. |
| Create | light-create, light-create-filled, dark-create, dark-create-filled | PASS | „Nowa osoba” with Dane osobowe, Urodzenie, Zgon cards open, footer „Anuluj / Dodaj osobę”. |
| Keyboard focus: tab and pencil | light-focus-tab, light-focus-pencil, dark-focus-tab, dark-focus-pencil | PASS | Visible focus ring on both. |
| 390px Przegląd | mobile-overview | PASS | Full-screen modal, tabs span the width. |
| 390px full-screen section edit | mobile-section-edit | PASS | „Edycja sekcji” header with back arrow, tabs hidden, Zapisz / Anuluj pinned in the footer (Zapisz stacked above Anuluj). |
| 390px create | mobile-create | PASS | Full-screen, footer pinned. |

## Pending (needs the human)

- Side-by-side comparison with the four design-canvas boards (canvas not reachable): pending.
- Real-device / touch check of the 390px edit and create flows: pending.
- Confirmation in a normal (non-Playwright) browser that click-away exits (tab, X, „Wstecz”, relative link, „⋯” items) show the guard bar: pending.
