# Screenshot verdict — source-description-in-modal

Kitchen sink: `TreeViewDemoSeeder` (fresh `migrate:fresh`). Script: `shots.mjs <out> after`. 48 PNGs in `after/` (12 states x light/dark x 1280/390), every one opened. Seeded sources have no files on disk, so thumbnails and the viewer show the broken-image placeholder; this is a seed limitation, not a regression (the baseline shows the same).

Shot naming: `<theme>-<width>-<state>.png`. "All 4" = light/dark x 1280/390.

| State | Shots | Expected | Seen |
| --- | --- | --- | --- |
| List: described, undescribed, long-valued cards | `*-list` (all 4) | Name (or file name) as title, kind badge, „typ · rok" and pochodzenie when set, upload date only when none set; long values truncate | As expected. Long-name card "Akt urodzenia z parafii …" and its pochodzenie truncate with ellipsis; gravestone shows "1932" only; akt shows "urodzenia · 1878" + pochodzenie; undescribed cards show file name + date. DEFECT (minor): at 1280 the fully described card (3 meta lines) is taller than its row neighbours, so its bottom edge sticks out ~18 px (light and dark). At 390 the list is one column (full-page shot, 9042 px tall) and truncates fine. |
| Upload modal: empty | `*-upload-empty` (all 4) | Plik, Rodzaj, Nazwa, Rok, Pochodzenie, Link, Notatka; no akt fields | As expected. Pinned header/footer, body scrolls (Notatka is cut by the footer, scrollable). Full-screen at 390 with stacked buttons. |
| Upload modal: akt selected | `*-upload-akt` (all 4) | „Typ aktu" (default "brak") and „Numer wpisu" appear | As expected. |
| Upload modal: zdjęcie nagrobka selected | `*-upload-gravestone` (all 4) | Akt fields absent | As expected. |
| Upload modal: validation errors | `*-upload-errors` (all 4) | Inline Polish errors, no native bubbles | As expected: "Pole plik jest wymagane.", "Pole rodzaj jest wymagane.", "Pole rok musi być liczbą całkowitą." in the destructive colour. The Link value "nie-link" is typed but its server error is below the fold of the body (not scrolled into view, it is not the first error), so it was not seen in this shot. |
| Edit modal: prefilled akt | `*-edit-akt` (all 4) | Kind akt selected, all fields prefilled | As expected: name, typ "urodzenia", numer 47, rok 1878, pochodzenie, link, notatka (multi-line, shown in Textarea). |
| Edit modal: kind switched, erase notice | `*-edit-kind-switched` (all 4) | Akt fields replaced by a notice listing what will be erased | As expected: "Te pola zostaną usunięte przy zapisie: Typ aktu: urodzenia, Numer wpisu: 47" in destructive colour. |
| Edit modal: confirmation dialog | `*-edit-confirm` (all 4) | Nested dialog "Zmienić rodzaj źródła?" with the fields and destructive confirm | As expected: list of both fields, „Anuluj" + red „Zmień rodzaj i zapisz"; Zapisz buttons re-enabled state not visible (not cancelled here). Observation: in dark the confirm button is dark red with white text, readable but low-key; the two overlays stack so the edit modal behind is heavily dimmed. |
| Preview: fully described akt | `*-preview-described` (all 4) | Name as title, meta line, description panel beside the image (wide) with typ, numer, rok, pochodzenie, clickable link, notatka with paragraphs | As expected at 1280: title "Urodzenia Lipnica 1878 s. 12", subtitle "akt metrykalny · urodzenia · 1878 · nr 47", panel with all fields, two-paragraph note, file block (Nazwa pliku is the original name). At 390 the panel sits under the viewer (below the fold in the shot; only the header, Edytuj/Usuń and the viewer are visible). Link text is displayed as "www.szukajwarchiwach.gov.pl"; its new-tab behaviour is not visible in a still. |
| Preview: undescribed source | `*-preview-undescribed` (all 4) | File name as title, kind as subtitle, "To źródło nie ma jeszcze opisu" with „Dodaj opis" | As expected (1280 and 390 header). |
| Preview: linked akt | `*-preview-linked` (all 4) | As undescribed, links kept; „Potwierdza" card | Header, description empty-state and viewer as expected. The „Potwierdza" card is below the fold in the viewport shot, so it was NOT seen in this shot (also not in the baseline viewport). |
| Person modal „Źródła" tab | `*-person-sources-tab` (all 4) | Source names (file names for undescribed) in the cards, badges unchanged | As expected: "akt-urodzenia-jan.jpg", "akt-wspolny-jan-kacper.jpg", "akt-slubu-jan-anna.jpg", "Potwierdzone aktem", "Bez źródła". At 390 file names truncate ("akt-uro…"), same as before. The seeded described sources are not linked to Jan, so a named source in the tab is not exercised by the seed. |

## Before -> after

- **List** (`before/light-1280-list.png` -> `after/light-1280-list.png`): before, every card was thumbnail + file name + kind badge + upload date, two of the demo cards were the file names "akt-z-dlugim-opisem.jpg" / "opisany-akt-lipnica-1878.jpg" with no other data. After, those cards show their names (Akt urodzenia z parafii …, Urodzenia Lipnica 1878 …), the „typ · rok" line and pochodzenie, the date disappears when a description exists, and undescribed cards are unchanged. Side effect: the described card is taller than its neighbours (see defect above). The order is now different only because the after baseline was reseeded (same append order as before).
- **Preview** (`before/light-1280-preview-akt.png` -> `after/light-1280-preview-linked.png` and `after/light-1280-preview-described.png`): before, a full-width viewer under a title with date/size/dimensions and a kind badge, and only „Usuń". After, a two-column layout: viewer left (about 640 px), description or "nie ma jeszcze opisu" panel plus file block right, „Edytuj" next to „Usuń", kind moved to the subtitle/panel. The viewer is narrower than before at 1280. Note: the page uses a different font rendering in the after set (fallback sans) than the before set (Instrument Sans was loaded then); this is a headless font-loading difference, not a change in CSS.
- **Upload**: before a page at /sources/create (no shot of it remains in `after/`); after a modal.

## Charges

| Category | Charge | Status | Evidence |
| --- | --- | --- | --- |
| Missing shared component | No `Textarea` in `components/ui/` | closed | Notatka is a styled multi-line control in `*-upload-*` (scrolled) and `*-edit-*` shots |
| Missing shared component | Description fields group needed by both modals | closed | Identical field set/order in `*-upload-akt` and `*-edit-akt` (`source-description-fields.tsx`) |
| Accidental architecture | Display label read as `original_name` in ~10 places | closed for the shots covered | Name shown in list, preview title/breadcrumb, person „Źródła" tab; the picker and the two source dialogs (unlink/delete) are not part of the shot set and were not visually verified here |
| Missing tokens | None needed | closed | The erase notice and confirm button use the existing destructive colour in light and dark; no new token introduced |

## Open observations (not fixed in this phase)

1. List cards: a card with three meta lines is taller than its row neighbours at 1280.
2. Linked-akt preview: „Potwierdza" card and, at 390, the description panel are below the viewport shot; not visually inspected.
3. Upload errors shot does not show the Link error (below the fold of the modal body).
