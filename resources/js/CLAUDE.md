Zasady UI dla `resources/js/` (Claude Code ładuje ten plik przy pracy w tym katalogu). Toolchain Vite+, wersje pakietów i reguły backendu są w głównym `CLAUDE.md`.

## UI: tokens and shared components

- Tokens live in `resources/css/app.css` (`:root` / `.dark` mapped into `@theme`). Use role classes (`text-destructive-foreground`, `bg-accent`, `text-muted-foreground`). In views: no Tailwind palette classes (`text-red-600`), no hex, `rgb()` or `oklch()`, no arbitrary pixel values (`ring-[3px]`) and no inline `style=`.
- Before creating a component, check `resources/js/components/ui/` and the shared `components/{heading,search-field,segmented-toggle,empty-state,side-panel,table-parts,timeline}.tsx`. A missing shadcn component comes from `npx shadcn add <name>`; that is a new dependency (root `CLAUDE.md`, "Production dependencies").
- Oś czasu (`resources/js/components/timeline.tsx`, zakładka Wydarzenia w panelu osoby): wiek osoby w chwili wydarzenia liczy serwer (`PersonTimeline` + `Age::at`), front tylko go formatuje (`formatAge`). Linie osi składaj przez `clsx`, nie `cn` — tailwind-merge zjada kolor `border-tree-line` obok `border-line`.
- Focus ring for elements outside `components/ui/`: `focusRing` from `resources/js/lib/focus-ring.ts`. The open record on list cards is `selectedListItem` from `resources/js/lib/list-item-styles.ts` (focus wins over the selection ring). Sticky table columns come from `resources/js/lib/table-styles.ts`. List pages: `Heading variant="page"` and the search row with `role="search"`.
- Controls: `resources/js/lib/view-tokens.test.ts` scans the `/persons`, `/sources` and `/places` view files and the tree cards (incl. `ancestor-chart.tsx`, `card-avatar.tsx`) (runs in `npm run test:js`). Visual gate: screenshots in `context/changes/<id>/screenshots/`.
- Karta przodka to osobny wariant na wspólnym `CardBody` (`variant="ancestor"`, `CardAvatar size="ancestor"`): zmiana karty drzewa nie zmienia karty przodka bez decyzji w planie.
- Zoom ma tylko widok przodków (samo kółko, przyciski, szczypanie); drzewo (`family-sheet.tsx`) nie zoomuje. Zakres pokoleń przodków przychodzi z serwera (`available_generations`), nie z listy w kliencie.

## Side panels vs modals

- Person, source, marriage and locality open in a right-hand side panel, not a modal. Tree add/choose person (slots, spouse menu, empty tree) happens in the add panel `?add=` (reference: `resources/js/components/tree/add-person-panel.tsx`). Creating a person from `/persons` is the same add panel as `?add=person` (a bare form), not a modal. Modals remain for delete confirmations, `MarryCoupleDialog` and small pickers without a form (parish inline, buried pick-person-dialog) (`resources/js/components/ui/dialog.tsx`).
- An open panel must not cover the tree: the tree area narrows beside it. Do not use an overlay for these panels — `resources/js/components/ui/sheet.tsx` is an overlay (Radix Dialog) and does not meet this as-is.
- Build one shared panel shell and reuse it for all four; the source panel is wider (scan beside the form).
- Closing a panel returns the user to the same tree position; moving to a relative replaces the panel content.
