# Screenshot gate verdict — scans-become-sources (first draft)

Run: `LD_LIBRARY_PATH=<nss/nspr/asound libs> node context/changes/scans-become-sources/screenshots/shots.mjs context/changes/scans-become-sources/screenshots/after`
(32 shots, exit 0; seeded with `TreeViewDemoSeeder` after `migrate:fresh`, dev server at http://localhost:8000).
Naming: `<light|dark>-<1280|390>-<state>.png`. Baseline: `before/` is shot on `main` with `shots.mjs <dir> before` (not run yet, folder is empty).
No screenshot test exists in the repo, so no baseline was updated. Source files are factory rows without files: thumbnails and previews show the broken-image placeholder (seed artefact, not an app defect).

Kitchen sink: 7 akt rows (4 linked from before, plus `akt-wspolny-jan-kacper.jpg` linked to Jan's birth and Kacper's birth), `nagrobek-kowalskich.jpg` (gravestone, unlinked), `list-rodzinny.jpg` (other, unlinked).

| State | Shots | Expected | Seen |
| --- | --- | --- | --- |
| list, three kinds | `*-list` | „Źródła” heading, „Dodaj źródło”, each card with a kind badge (akt metrykalny / zdjęcie nagrobka / inne) | light-1280 inspected: matches, three kinds present, sidebar item „Źródła”. Other 3 variants not inspected |
| upload with kind error | `*-upload-kind-error` | „Dodaj źródło”, Rodzaj toggle with „Pole rodzaj jest wymagane.” under it, „Plik” label | light-1280 and light-390 inspected: matches (also shows „Pole plik jest wymagane.”); at 390 the „akt metrykalny” label wraps to two lines. Dark not inspected |
| preview of a gravestone | `*-preview-gravestone` | „zdjęcie nagrobka” badge next to the heading, „Usuń” | light-1280 (behind the short dialog) inspected: badge present. Other variants not inspected |
| preview of an „inne” source | `*-preview-other` | „inne” badge next to the heading | not inspected |
| short delete dialog | `*-delete-short` | „Usunąć źródło?”, one-line description, Anuluj / Usuń, no list | light-1280 inspected: matches. Others not inspected |
| linked delete dialog, both suffixes | `*-delete-linked-both-suffixes` | „Usuń źródło”, „Te fakty stracą to źródło:”, Jan's birth „pozostanie potwierdzone innym źródłem”, Kacper's birth „stanie się niepotwierdzone” (red) | dark-1280 and dark-390 inspected: matches. Light not inspected |
| akt-only picker | `*-picker-akt-only` | „Powiąż akt”, only akts; gravestone and other absent; already linked ones greyed „Już powiązany” | light-1280 inspected: 5 akts, nagrobek and list absent, two greyed as linked. Others not inspected |
| person modal „Źródła” tab | `*-person-sources-tab` | Jan's Urodzenie with two sources, „Powiąż kolejny akt”, second marriage „Brak aktu. Powiąż akt…” with „Powiąż akt” | dark-390 inspected: matches. Others not inspected |

Minor observations (no action taken): at 390 px the kind badge on the preview page drops below the title, and the „Usuń” button sits beside the title.

## Pending

- `before/` baseline from `main` (script supports `before` flavour; not run), and the before→after comparison of thumbnails, zoom and layout.
- Remaining shots marked "not inspected" above.
