---
change_id: tree-family-sheet-ui
title: Audit and improve the family sheet view (tree/show)
status: archived
created: 2026-09-28
updated: 2026-09-28
archived_at: 2026-09-28T21:37:44Z
---

## Notes

Visual change via `/10x-ui tree`.

- **One view:** `resources/js/pages/tree/show.tsx` (routes `tree.index` / `tree.show`) and the components it renders from `resources/js/components/tree/`.
- **Token source:** existing shadcn tokens in `resources/css/app.css` (`:root` / `.dark`, published through `@theme`), including the tree's own `--gender-*` tokens. Extend it; no second palette, no `shadcn init`.
- **Named motif:** PL-SOFT „Formularz rodzinny” (family sheet) + Ancestris dynamic-tree gender frames — already chosen in `context/archive/2026-09-28-graphical-tree-view/research.md` §1, §3.
- **Kitchen sink:** `database/seeders/TreeViewDemoSeeder.php` (users `tree-demo@example.com`, `tree-demo-empty@example.com`).
- Audit / charge list: `audit.md`. Before-screenshots: `screenshots/before/`.
