# Linie drzewa i węzeł związku — Plan Brief

> Full plan: `context/changes/tree-lines-union-node/plan.md`
> Research: `context/changes/tree-lines-union-node/research.md`

## What & Why

Wdrażamy linie arkusza rodziny i węzeł związku z design systemu Root Keeper, żeby rodowód był czytelny od pierwszego spojrzenia: kto z kim, od kogo pochodzi dziecko, czego brakuje. Do tego potrzebny jest rok ślubu par rodziców i dziadków, którego arkusz dziś nie ma.

## Starting Point

Linie mają 1 px, są jednolite i w `tree-line`, rodzice łączą się nawiasem, nic nie jest przerywane i nie ma linii przodków. Pigułka związku jest prostokątna i znika przy braku związków. Arkusz (`BuildFamilySheet`) zna rok ślubu tylko dla związków osoby w centrum, a dla par rodziców ma jedynie flagę `unmarried_parents`.

## Desired End State

Linie mają 1,5 px, są łamane z narożnikami 12 px. Od dziadków do osoby w centrum biegnie linia 2 px w `primary`. Każda linia do zaślepki jest przerywana. Nad parą rodziców lub dziadków po ślubie stoi węzeł z obrączkami i rokiem („1861”, „ok. 1828”; bez daty same obrączki). Pigułka związku jest okrągła, z obrączkami przy ślubie i przerywana przy „bez ślubu”; pojawia się też przy braku związków. Karta ze zdjęciem ma pod nim listwę w kolorze płci.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Pigułka przy braku związków | Renderuje się („Związki”, menu z „Dodaj małżonka”), połówka do slotu przerywana | Stan DS „brak partnera” obejmuje też arkusz bez związków | Research (D1) |
| Linia do statycznej zaślepki „Nieznany partner” | Przerywana jak do każdej zaślepki | Jedna reguła: przerywana linia = brakująca osoba | Research (D2) |
| Kształt danych | Nowe `parent_marriages` + `grandparent_marriages` (`{year} \| null`), `unmarried_parents` bez zmian | Nie zmienia istniejącego kontraktu i testów | Research (D3) |
| Strażnik tokenów | `family-sheet.tsx` w `VIEW_FILES` z wyjątkiem `style={`; reguła px łapie ułamki | Zakaz `[1.5px]` egzekwowany testem, nie przeglądem | Research (D4) |
| Utility grubości | `@theme --border-width-line` / `--border-width-lineage` → `border-l-line`, `border-lineage` … | Sprawdzone w Tailwind 4.3.3; nie koliduje z kolorem `border-tree-line` | Plan |
| Pień przy 0 dzieciach | Przerywany | Prowadzi tylko do zaślepki „Dziecko” — reguła DS | Plan (D5) |
| Slot „Dziecko” pod rzędem (≥4 dzieci) | Bez linii, jak dziś | Rzadki układ, DS go nie projektuje; bez nowej geometrii przy karcie | Plan (D6) |
| Rok przy kilku ślubach pary | Najwcześniejszy (`PersonOrdering::marriages`) | Spójne z etykietą związków w centrum | Research |
| Linie jako ramki | Wszystkie linie to `border-*` na elementach 0 px | Tylko ramka może być przerywana | Plan |

## Scope

**In scope:**
- Pola par w `BuildFamilySheet` + typy PHPStan/TS + testy Pest (8 przypadków, 404 `tree.show`)
- Tokeny `--tree-line-width` / `--lineage-line-width` i utility `border-line` / `border-lineage`
- `MarriageRingsIcon`, pigułka `UnionNode` we wszystkich stanach, listwa płci pod zdjęciem
- Linie `family-sheet.tsx`: ramiona, węzeł rodziców, linia przodków, linia pary, pień, belka dzieci
- `view-tokens.test.ts`: ułamki px, `family-sheet.tsx` i nowa ikona w `VIEW_FILES`
- Małżeństwa przodków w `TreeViewDemoSeeder` i zrzuty jasny/ciemny

**Out of scope:**
- Zmiany w `unmarried_parents` / „Połącz jako małżeństwo”
- Klikalny węzeł rodziców, `ancestor-chart.tsx`, regresje pikselowe, nowe zależności

## Architecture / Approach

Od danych do pikseli. `BuildFamilySheet` liczy pary z już załadowanych `events` (część wspólna zdarzeń `Marriage`, bez nowych zapytań) i zwraca je wyrównane do slotów łączników. `PairConnector` dostaje `marriage` i `lineage`, a styl każdego ramienia i pnia wynika z tego, czy slot jest osobą. Linia pary to dwie połówki, a strona partnera wynika z `focus_side`. Belka dzieci wybiera dla każdej komórki narożnik, pełną belkę albo pion.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Dane par w arkuszu | `parent_marriages` / `grandparent_marriages`, testy, 404 | Wzrost liczby zapytań (pilnuje test licznika) |
| 2. Tokeny, obrączki, pigułka, listwa | Utility grubości, ikona, pigułka DS, listwa płci, strażnik ułamków | `<img>` w pozycji absolutnej ignoruje `top/bottom` (wymaga wrappera) |
| 3. Linie arkusza | Nowa geometria linii, węzeł rodziców, strażnik dla arkusza, zrzuty | Styk pnia z belką przy 1 dziecku; strona partnera przy `focus_side: right` |

**Prerequisites:** zmiana `design-tokens` (zarchiwizowana: kolory i font już w `app.css`); Sail i serwer dev z danymi demo (`tree-demo@example.com`) do zrzutów.
**Estimated effort:** ~2–3 sesje w 3 fazach.

## Open Risks & Assumptions

- Dokładne wysokości (łącznik 44 px, odgałęzienia 16 px) pochodzą z makiety. Ostateczny styk linii z kartami ocenia się na zrzutach.
- Dodanie małżeństw przodków do seedera demo zmienia arkusz probanda w danych demo (rodzice Jana tracą przycisk „Połącz”; rodzice Anny go zachowują). Żaden test go nie używa.

## Success Criteria (Summary)

- Na `/tree` linia przodków osoby w centrum jest widoczna od razu, a brakujące osoby są rozpoznawalne po linii przerywanej
- Rok ślubu rodziców i dziadków jest widoczny na węzłach, bez nowych zapytań do bazy
- `view-tokens.test.ts` pilnuje arkusza i węzła; `check`, `types:check`, Pest zielone; zrzuty zaakceptowane
