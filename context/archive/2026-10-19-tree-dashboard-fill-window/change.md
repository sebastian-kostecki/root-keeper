---
change_id: tree-dashboard-fill-window
title: Tree as the default dashboard, filling the window
status: archived
created: 2026-10-19
updated: 2026-10-19
archived_at: 2026-10-19T06:23:04Z
---

## Notes

Started from /10x-ui. Goal (user's words): zmiana wyglądu — bardziej jak drzewo genealogiczne program; ma być domyślny dashboard z widokiem osoby + małżonek (plus możliwe związki jak teraz) + dzieci + rodzice + dziadkowie; karty wyższe, cały widok (dziadkowie, rodzice, osoba, dzieci) możliwie zapełnia jak najwięcej okna.

- View: `/tree` and `/tree/{person}` — `resources/js/pages/tree/show.tsx` + `resources/js/components/tree/family-sheet.tsx` (cards: `sheet-person-card.tsx`, `unknown-person-card.tsx`, `card-styles.ts`; fit: `components/zoom-canvas.tsx`, `lib/fit-view.ts`).
- Contract variant: existing design system. Tokens: `resources/css/app.css` (`:root` / `.dark` → `@theme`); components: `resources/js/components/ui/`; DS + /tree mockups in the Root Keeper design-system artifact.
- Pre-audit (2026-10-17): hard-coded value scan 0 hits on 7 view files; 8 inline `style=` in family-sheet.tsx (computed rem geometry).
