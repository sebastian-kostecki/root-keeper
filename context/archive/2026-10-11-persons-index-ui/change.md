---
change_id: persons-index-ui
title: Persons index ui
status: archived
created: 2026-10-11
updated: 2026-10-12
archived_at: 2026-10-12T04:52:21Z
---

## Notes

Widok: resources/js/pages/persons/index.tsx (+ resources/js/components/persons/persons-{list,table,empty-state}.tsx). Źródło tokenów: resources/css/app.css (:root/.dark → @theme), komponenty: resources/js/components/ui + resources/js/components/{empty-state,search-field,segmented-toggle,side-panel}.tsx; wariant kontraktu: istniejący system projektowy (rozszerzamy, bez drugiej palety ani shadcn init). Poza zakresem: strona drzewa (/tree) — należy do zmiany tree-add-person-panel; jeśli zarzut 1 dotknie wspólnych PersonFormFields / lib/side-panel-url.ts, implementować po zamknięciu jej Fazy 2.

Przed-audyt (/10x-ui, 2026-10-11) — wstępne zarzuty dla /10x-research:
1. Architektura: „Dodaj osobę” otwiera modal — person-create-dialog.tsx:77–92 (FormDialogContent sm:h-[90vh]), wejścia index.tsx:138 i :180 — wbrew CLAUDE.md „Side panels vs modals” (osoba otwiera się w panelu bocznym).
2. Architektura/punkt wejścia: po logowaniu Fortify kieruje na /persons (config/fortify.php:76), a pierwsza pozycja sidebara i logo prowadzą do /tree (app-sidebar.tsx:32, :72) — niejasne, która strona jest „domem”.
3. Brakujący współdzielony komponent: link skoku do litery persons-list.tsx:108 odtwarza Button variant="outline" size="sm" z klas.
4. Brakujące tokeny/współdzielony styl: pierścień fokusu `focus-visible:ring-ring/50 focus-visible:ring-[3px]` skopiowany w persons-list.tsx:60, :108 i persons-table.tsx:141; drzewo ma wspólną stałą (tree/card-styles.ts:23).
5. Stany (hipoteza): wyszukiwanie tylko przygasza listę (index.tsx:190–196); loading/error z macierzy 7 stanów niezweryfikowane.
Skan zakodowanych wartości: 3 trafienia (wszystkie `ring-[3px]`); 16 użyć klas tokenów, 9 importów z ui/. Brak .ai/rules i lessons.md; CLAUDE.md nie ma bloku UI o tokenach — do dodania w kroku „Utrwal to”.
