---
change_id: sources-filter-bar
title: Pasek filtrów z pigułkami i okienkami na /sources
status: implemented
created: 2026-10-12
updated: 2026-10-12
archived_at: null
---

## Notes

Wdróż pasek filtrów z design systemu Root Keeper na /sources: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU
Przeczytaj project/components/FilterBar/README.md i wiersze popover / sources-filters / pages/sources w project/implementation.md.
Makieta: https://claude.ai/artifact/Nvqqp3f43K3Kf22tNudBuS (artboardy Sources.dc.html i FiltersStates.dc.html).
Zgoda na nową zależność: npx shadcn add popover (@radix-ui/react-popover) — udzielona 2026-10-12.
Zakres: components/ui/popover.tsx (fokus jak reszta ui: pełny ring 3 px), sources-filters.tsx jako pasek pigułek Miejsce / Lata / Archiwum z okienkami,
liczby przy pozycjach z filterSources (bez nowego endpointu), liczba wyników „N z M źródeł” (role="status"), wersja poniżej sm za „Filtry (N)”,
przestawienie rzędów w pages/sources/index.tsx. Filtry mają działać jak dziś (te same parametry URL, ten sam yearError) — zmienia się sposób wyboru.
Zaktualizuj testy E2E szukające pól po etykietach „Parafia / miejsce”, „Rok od”, „Archiwum” na role przycisków pigułek i okienek; zachowania nie zmieniaj.
