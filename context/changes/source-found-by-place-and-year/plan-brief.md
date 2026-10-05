# Akt odnaleziony po miejscu i roku, numer wpisu przy powiązaniu (S-04) — Plan Brief

> Full plan: `context/changes/source-found-by-place-and-year/plan.md`
> Research: `context/changes/source-found-by-place-and-year/research.md`

## What & Why

Przy podpinaniu źródła do faktu autor zawęża picker po miejscu (miejscowość siedziby parafii aktu lub nazwa parafii) i roku, wybiera akt i podaje numer wpisu. Numer wpisu przechodzi ze źródła na powiązanie fakt–źródło, bo jedna strona księgi zawiera wiele wpisów. Domyka US-01 („Luboml”, 1908, wpis nr 95) i FR-003/FR-004, nie naruszając guardrailu FR-025.

## Starting Point

Picker nie ma filtra, a klik w kafel od razu podpina źródło; kafel pokazuje już „typ · rok · parafia”, pasujące akty są pierwsze. `event_source` nie ma danych powiązania i nie ma trasy edycji. „Numer wpisu” jest polem źródła, które `UpdateSource` zeruje przy każdym zapisie.

## Desired End State

Picker otwiera się z rokiem faktu, zawęża po „Miejsce” i „Rok”, mówi ile źródeł bez roku/miejsca ukrył i pozwala wyczyścić filtry. Klik zaznacza akt, autor wpisuje numer i „Powiąż”. „nr 95” widać przy fakcie (Przegląd, zakładka Źródła) i na karcie „Potwierdza” źródła; numer edytuje się w zakładce Źródła. Stary numer źródła jest widoczny w panelu tylko do odczytu, dopóki autor go nie usunie; istniejące powiązania prowadzą do tych samych źródeł.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Stary „Numer wpisu” źródła | Tekst tylko do odczytu w panelu + „Usuń”; znika z formularza, tabeli i galerii; kolumna zostaje w bazie | Wartość do ręcznego przeniesienia nie ginie (copy first, drop later) | Research |
| Przeniesienie numeru | Bez automatu: migracja tylko dodaje pustą kolumnę na `event_source` | Jedno źródło może potwierdzać kilka faktów; numer musi wskazać autor | Research |
| Kiedy podaje się numer | Przy podpinaniu (opcjonalnie) i edycja później | Istniejące powiązania też muszą dostać numer | Research |
| Kształt filtra | „Miejsce” = fragment nazwy miejscowości siedziby lub parafii; „Rok” dokładny, wstępnie rok faktu | Najprostszy predykat zgodny z `parishes-index.ts`; rok faktu oszczędza wpisywania | Research |
| Numer w pickerze | Klik zaznacza kafel, potem „Numer wpisu” + „Powiąż” (Enter też) | Numer trafia świadomie do konkretnego aktu | Plan |
| Edycja numeru | Mały modal w wierszu zakładki Źródła; na „Potwierdza” tylko wyświetlanie | Jedno miejsce edycji, wzorzec „Odepnij”, bez mieszania z formularzem źródła | Plan |
| Stary numer przy zapisie i zmianie rodzaju | Nietykalny; znika tylko przez „Usuń” z potwierdzeniem | Zmiana rodzaju nie gubi nieprzeniesionej wartości | Plan |
| Źródła bez roku/miejsca przy filtrze | Ukryte; licznik „Ukryto N … bez roku lub miejsca” + „Wyczyść filtry” | Lista krótka, a autor wie, że coś ukryto | Plan |
| Ponowne podpięcie istniejącej pary | No-op, numer bez zmian; zmiana tylko przez `PATCH` | Zachowuje idempotencję przypiętą testem | Plan |
| E2E | Nowy spec US-01 + cała suita | Krok numeru i filtr z rokiem faktu sprawdzone w przeglądarce | Plan |

## Scope

**In scope:**
- Kolumna `event_source.entry_number`, `withPivot`, `store` z numerem przez Action, `PATCH events/{event}/sources/{source}`
- Numer przy fakcie (Przegląd, zakładka Źródła z edycją) i na karcie „Potwierdza”
- Picker: „Miejsce”, „Rok” (rok faktu), licznik ukrytych, „Wyczyść filtry”, krok numeru
- `parish_name`, `locality_name`, `legacy_entry_number` w `SourceResource`
- Stary numer: odczyt + `DELETE sources/{source}/legacy-entry-number`; usunięcie z formularza, walidacji, `UpdateSource`, ostrzeżenia o zmianie rodzaju, tabeli, galerii
- Pest (migracja, powiązania, 404, `rowDiff`, backup), Vitest (filtr), E2E US-01

**Out of scope:**
- Miejscowość cmentarza nagrobka w filtrze (S-09); filtry listy `/sources` (S-10)
- Automatyczne przeniesienie lub podpowiadanie starego numeru; usunięcie kolumny `sources.entry_number`
- Edycja numeru z panelu źródła; wybór miejscowości ze spisu, zakres lat, zwijanie diakrytyków; filtr po stronie serwera

## Architecture / Approach

Numer to dana pivotu `event_source` czytana przez `withPivot` i serializowana w `EventResource` i `confirms()`. Zapis: Form Request → Action (`app/Actions/Events/`) dla podpięcia i edycji. Filtr to czysta funkcja klienta `filterSourcesByPlaceAndYear` nad jedną listą `availableSources` (ładowaną z `parish.locality`), przed istniejącym `splitSourcesForEvent`. Stary numer zostaje w `sources.entry_number`, wystawiony jako `legacy_entry_number`, z osobną trasą usuwania.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Numer wpisu na powiązaniu (backend) | Kolumna pivota, `store` z numerem, `PATCH`, serializacja, strażniki | `syncWithoutDetaching` z atrybutami nadpisałby numer istniejącej pary |
| 2. Numer przy fakcie i przy źródle + edycja | „nr …” w trzech miejscach, modal edycji | Numer w tekście linku przewróci E2E `fact-source-scan` |
| 3. Picker — filtr + krok numeru | Filtr z rokiem faktu, licznik ukrytych, zaznacz → numer → „Powiąż” | Wstępny rok ukrywa nieopisane źródła — licznik musi być czytelny |
| 4. Stary „Numer wpisu” tylko do odczytu | Pole znika ze źródła, odczyt + „Usuń” | `UpdateSource` zerujący numer przy pierwszym zapisie |
| 5. E2E US-01 | Nowy spec + cała suita zielona | Setup parafii z siedzibą przez API (brak trasy usuwania parafii) |

**Prerequisites:** S-03 i F-01 zakończone (są); Sail i baza `e2e` dostępne do Playwright.
**Estimated effort:** ~3–4 sesje w 5 fazach.

## Open Risks & Assumptions

- Liczba źródeł z niepustym `entry_number` w produkcji nieznana — wpływa tylko na nakład ręcznego przenoszenia.
- Akt bez parafii lub z parafią bez siedziby nie odnajdzie się po miejscu (świadomie od S-03); filtr po nazwie parafii częściowo to łagodzi.
- Cofnięcie migracji gubi numery wpisane po wdrożeniu (opisane w `down()`).

## Success Criteria (Summary)

- Autor szuka „Luboml” i 1908, widzi akt z rokiem i parafią, podaje wpis 95 i widzi „nr 95” przy urodzeniu i na karcie „Potwierdza”.
- Każde powiązanie sprzed wdrożenia prowadzi do tego samego źródła i skanu; stary numer jest widoczny, dopóki autor go nie usunie.
- Cała suita Pest, Vitest i E2E przechodzi.
