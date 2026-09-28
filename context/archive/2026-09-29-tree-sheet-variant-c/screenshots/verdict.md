# Screenshot gate verdict — tree-sheet-variant-c

Run: `LD_LIBRARY_PATH=<nss/nspr/asound libs> node context/changes/tree-sheet-variant-c/screenshots/shots.mjs context/changes/tree-sheet-variant-c/screenshots/after`
(43 shots, no errors, after reseeding `TreeViewDemoSeeder` with the appended Konieczny couple).
Before set: `context/archive/2026-09-28-tree-family-sheet-ui/screenshots/after/`. Shots below are in `screenshots/after/`.

| Charge | Shots | Verdict | Notes |
| --- | --- | --- | --- |
| C1 tokens | desktop-{light,dark}-proband, desktop-light-unknown-gender | PASS | Gender surface + border on all cards, unknown gender neutral (`bg-card`, `--tree-line` border). Connectors visibly darker than before. |
| C2 cards / placeholders | desktop-light-proband, -hover-card, -focus-card, -placeholder-{father,child}-{hover,focus}, desktop-dark-* | PASS | Centred text, no gender icons, focus person `border-2 border-primary`; placeholders dashed with verbs („Dodaj ojca/matkę/dziecko"); static „Nieznany partner" is a borderless grey block. |
| C3 union node | desktop-{light,dark}-node-closed / -node-focus / -node-menu, desktop-light-one-union, -unions-label, -no-unions | PASS | „1 z 4 · ślub 2001", „3 z 4 · bez ślubu", „ślub 1996" (one union, no „1 z 1"), „Związki" (Sasza), no node for Kacper. |
| C3a `partner_name` | desktop-dark-node-menu, desktop-light-unknown-union | PASS | Menu items show partner name over „status · N dzieci"; unknown union reads „Nieznany partner". |
| C3b add spouse from menu | desktop-light-spouse-dialog | PASS | Dialog opened from „Dodaj małżonka" with candidates loaded. Focus return / clickability confirmed manually in Phase 3. |
| C4 geometry | desktop-light-proband, -five-children, -three-children, -no-unions, -marry-chip; desktop-dark-three-children | PASS | No visible generation labels; descent line continuous; placeholder in-row for 1–2 children in the last row (Jan, Nowicki), own centred row for 3 (Konieczny); bracket spans real children only; „Połącz jako małżeństwo" sits on the descent line below the node (Jan + Marta). |

## Measured contrast (WCAG, OKLCH → sRGB)

| Pair | Light | Dark | Audit estimate |
| --- | --- | --- | --- |
| `--tree-meta` on male surface | 5.91:1 | 7.18:1 | ≈5.9 / ≈7 — confirmed |
| `--tree-meta` on female surface | 5.85:1 | 7.29:1 | confirmed |
| `--tree-line` vs page background | 1.87:1 | 2.34:1 | ≈1.9 / ≈2.4 — confirmed |

## 390px regression

`mobile-light-proband`, `mobile-light-four-unions`: same sideways-scroll layout as before (D1); the node scrolls with the sheet. No regression beyond the known one.

## Still deferred

D1 mobile vertical layout, D2 second gender cue, D3 foreign-person 404 page, D4 header meta line.
