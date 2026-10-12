---
change_id: tree-lines-union-node
title: Linie drzewa i węzeł związku z design systemu Root Keeper
status: implementing
created: 2026-10-12
updated: 2026-10-12
archived_at: null
---

## Notes

Wdróż linie drzewa i węzeł związku z design systemu Root Keeper: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU
Tokeny kolorów i font są już w kodzie (zmiana design-tokens).
Przeczytaj project/components/UnionNode/README.md, project/components/PersonCard/README.md, project/tokens.json (layout: tree-line-width, lineage-line-width, tree-corner-radius) i wiersze components/tree/* w project/implementation.md.
Makieta docelowa: https://claude.ai/artifact/Nvqqp3f43K3Kf22tNudBuS (artboardy Main.dc.html i Union.dc.html).
Zacznij od /10x-research: czy FamilySheetData ma rok ślubu par rodziców i dziadków; jeśli nie — co dodać w Resource (+ test Pest z przypadkiem 404 dla cudzego drzewa).
Zakres:
- nowe zmienne w app.css i @theme: --tree-line-width 1.5px, --lineage-line-width 2px (tych jeszcze nie ma);
- marriage-rings-icon.tsx (nowy);
- union-node.tsx: pigułka na linii; stany ślub / data nieznana / bez ślubu / „1 z 2” / brak partnera;
- linie w family-sheet.tsx: zaokrąglone narożniki, przerywane do zaślepek, linia przodków osoby w centrum w primary, węzeł rodziców z obrączkami i rokiem;
- listwa płci pod zdjęciem w sheet-person-card.tsx (img: inset-x-0 top-0 bottom-1).
Bez wartości [1.5px] w widokach — grubości z tokenów; view-tokens.test.ts obejmuje już components/tree.
