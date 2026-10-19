---
change_id: ancestors-view-polish
title: Ancestors view polish
status: impl_reviewed
created: 2026-10-19
updated: 2026-10-19
archived_at: null
---

## Notes

UI change driven by `/10x-ui`.

- **View:** `/tree/{person}/ancestors` — `resources/js/pages/tree/ancestors.tsx` (chart: `resources/js/components/tree/ancestor-chart.tsx`, card: `ancestor-card.tsx` → `CardBody` in `sheet-person-card.tsx`, avatar: `card-avatar.tsx`, zoom: `resources/js/components/zoom-canvas.tsx`).
- **Token source:** `resources/css/app.css` (`:root` / `.dark` → `@theme`); components from `resources/js/components/ui/` and the shared `heading`, `segmented-toggle`. Contract variant: existing design system — extend it, no second palette.

User wishes (verbatim):

- widok drzewa danej osoby
    - brak zmniejszania/powiększania scrollem
    - sama opcja bardziej widoczna (moze button z widoku drzewa)
    - możliwosć wyświetlania wszystkich możliwych pokoleń
    - inny wygląda zdjęcia (najlepiej kółko, avatar)
    - bez wieku na widoku drzewa

Pre-audit (2026-10-19):

- Hard-coded value scan on the 7 view files: 0 hits. 7× inline `style=` in `ancestor-chart.tsx` (runtime rem geometry).
- No `.ai/rules`; UI rules live in `CLAUDE.md`.
- Candidate charges: entry only in the person panel "⋯" menu (`person-modal-header.tsx:146`); wheel zoom needs Ctrl (`zoom-canvas.tsx:594-597`); generations capped at 6 (`TreeAncestorsController.php:19`, `ancestors.tsx:17`, `fit-view.ts:8-14`); age shown via shared `CardBody` (`sheet-person-card.tsx:76-80`); avatar already round since `f641432` (`card-avatar.tsx:66-72`) — confirm on a screenshot what the user sees.
- Assumptions to confirm: "scrollem" = plain wheel zooms without Ctrl; "bez wieku" = ancestors view only, not the main tree.
