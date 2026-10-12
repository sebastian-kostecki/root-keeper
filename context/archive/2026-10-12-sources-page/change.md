---
change_id: sources-page
title: Sources page
status: archived
created: 2026-10-12
updated: 2026-10-12
archived_at: 2026-10-12T06:22:33Z
---

## Notes

Widok: resources/js/pages/sources/index.tsx (+ resources/js/components/sources/sources-{filters,gallery,table,empty-state}.tsx, source-thumbnail.tsx). Źródło tokenów: resources/css/app.css (:root/.dark → @theme), komponenty: resources/js/components/ui + resources/js/components/{heading,search-field,segmented-toggle,empty-state,side-panel,table-parts}.tsx, stałe: lib/focus-ring.ts, lib/table-styles.ts; wariant kontraktu: istniejący system projektowy (rozszerzamy, bez drugiej palety ani shadcn init). Wzorzec: zarchiwizowana zmiana 2026-10-11-persons-index-ui (focusRing, persons-view-tokens.test.ts, bramka zrzutów w screenshots/). Poza zakresem: wnętrze panelu źródła (source-panel.tsx) — tylko jako punkt wejścia z listy; pozostałe listy (/parishes, /localities) z tym samym ręcznym pierścieniem fokusu.

Przed-audyt (/10x-ui, 2026-10-12) — wstępne zarzuty dla /10x-research:
1. Brakujący współdzielony styl: pierścień fokusu `focus-visible:ring-ring/50 … focus-visible:ring-[3px]` ręcznie w sources-gallery.tsx:45 i sources-table.tsx:145, zamiast `focusRing` z lib/focus-ring.ts (CLAUDE.md wymaga go poza components/ui).
2. Brakujący współdzielony komponent (hipoteza): karta galerii zbudowana z klas `bg-card rounded-lg border shadow-sm` (sources-gallery.tsx:45) obok ui/card.tsx — research rozstrzyga, czy Card pasuje do karty-linku, czy wystarczy wspólna stała.
3. Stan błędu: pola „Rok od/Rok do” (sources-filters.tsx:101–127) po cichu ignorują rok spoza 1–9999 (lib/sources-index.ts:168) i nie sygnalizują „od > do” — użytkownik widzi pustą listę albo nieprzefiltrowaną bez wyjaśnienia.
4. Układ/mobile (hipoteza do zrzutu): pasek wyszukiwania ma wyszukiwarkę, 2 przełączniki, 4 pola filtrów i „Wyczyść filtry” (index.tsx:330–436) — na 375 px wyniki mogą zaczynać się poniżej pierwszego ekranu; pojawiający się „Wyczyść filtry” może przesuwać układ.
5. Stany do weryfikacji: miniatura z has_thumbnail=true, która nie wczyta się (source-thumbnail.tsx:27, brak onError) pokazuje ikonę zepsutego obrazka; loading — filtrowanie jest po stronie klienta (only: ['filters']), sprawdzić, czy przełączenie widoku/sortu nie skacze; disabled — wyłączone segmenty rodzaju i selecty bez opcji: czytelność.
Skan zakodowanych wartości: 2 trafienia (oba `ring-[3px]`); 14 użyć klas tokenów, 14 importów ze współdzielonych komponentów. Inline `style=` tylko w source-panel.tsx:333 (szerokość paska postępu — wartość dynamiczna, poza zakresem). Brak .ai/rules i lessons.md; CLAUDE.md ma już blok „UI: tokens and shared components” — do rozszerzenia o /sources w kroku „Utrwal to” (test tokenów obejmuje dziś tylko /persons).
