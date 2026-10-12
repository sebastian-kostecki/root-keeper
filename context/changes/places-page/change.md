---
change_id: places-page
title: Places page
status: implemented
created: 2026-10-12
updated: 2026-10-12
archived_at: null
---

## Notes

Cel (użytkownik, /10x-ui): scalić zakładki „Miejscowości” i „Parafie” w jedną zakładkę — rozwiązanie do przemyślenia.

Widok: jedna strona „Miejsca” zastępująca resources/js/pages/localities/index.tsx i resources/js/pages/parishes/index.tsx (+ resources/js/components/{localities,parishes}/{*-table,*-empty-state}.tsx; wpis w resources/js/components/app-sidebar.tsx). Źródło tokenów: resources/css/app.css (:root/.dark → @theme), komponenty: resources/js/components/ui + resources/js/components/{heading,search-field,segmented-toggle,empty-state,side-panel,table-parts}.tsx, stałe: lib/focus-ring.ts, lib/table-styles.ts; wariant kontraktu: istniejący system projektowy (rozszerzamy, bez drugiej palety ani shadcn init). Wzorzec: zarchiwizowane 2026-10-12-sources-page i 2026-10-11-persons-index-ui. Poza zakresem: wnętrze paneli miejscowości i parafii (locality-panel.tsx, parish-panel.tsx) oraz dialogi scalania/usuwania — tylko jako punkty wejścia z listy.

Przed-audyt (/10x-ui, 2026-10-12) — wstępne zarzuty dla /10x-research:
1. Przypadkowa architektura: dwa wpisy w pasku bocznym (app-sidebar.tsx:47–58) dla jednej domeny. Parafia ma siedzibę w miejscowości (parish-panel.tsx:145), a „użycia” miejscowości liczą parafie (LocalityResource usage.parishes) — szukając „Luboml”, autor sprawdza dwie strony i nie widzi powiązania.
2. Brakujący współdzielony komponent: pages/localities/index.tsx i pages/parishes/index.tsx są niemal identyczne (debounce wyszukiwania, openFromList, statusText, ~200 linii) — scalenie bez wydzielenia da trzecią kopię.
3. Brakujący styl: pierścień fokusu `focus-visible:ring-ring/50 … focus-visible:ring-[3px]` ręcznie w localities-table.tsx:67 i parishes-table.tsx:68 zamiast `focusRing`.
4. Ta sama kolumna, inne znaczenie: „Użycia” to usage.total u miejscowości (localities-table.tsx:91), a sources_count u parafii (parishes-table.tsx:86) — na jednej stronie niespójność stanie się widoczna.
5. Punkty wejścia: redirecty po zapisie na localities.index/parishes.index (LocalityController.php:79, ParishController.php:83), deep linki ?locality=/?parish=, e2e places-merge-and-delete.spec.ts, liczniki navCounts (HandleInertiaRequests.php:57–58) — wymagają nowego adresu lub przekierowania. Stany: pusta jedna zakładka przy niepustej drugiej; 375 px.

Wzory do porównania w researchu (decyzja użytkownika na końcu researchu):
- A (rekomendacja): /places „Miejsca” z SegmentedToggle „Miejscowości (n) | Parafie (n)” w URL (?view=parishes), wspólna wyszukiwarka, „Dodaj” zależny od zakładki, panele bez zmian, redirecty ze starych tras.
- B: hierarchia — miejscowości z parafiami pod siedzibą + grupa „Bez siedziby”.
- C: jedna mieszana tabela z kolumną „Rodzaj” i filtrem.

Skan zakodowanych wartości: 2 trafienia (oba `ring-[3px]`); brak klas palety i `style=`. Współdzielone importy: tylko EmptyState w obu *-empty-state.tsx. resources/js/lib/view-tokens.test.ts nie obejmuje tych widoków — do rozszerzenia w kroku „Utrwal to”. Brak .ai/rules i lessons.md.
