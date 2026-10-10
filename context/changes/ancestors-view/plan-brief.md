# Widok przodków (S-15) — Plan Brief

> Full plan: `context/changes/ancestors-view/plan.md`
> Research: `context/changes/ancestors-view/research.md`

## What & Why

Autor chce wybrać osobę i zobaczyć ją oraz jej przodków w jednym widoku z wyborem liczby pokoleń (FR-018). Arkusz drzewa pokazuje tylko 3 pokolenia wokół jednej pary i służy do edycji. Nowy widok służy wyłącznie do oglądania linii w górę. Cudza osoba daje 404 (FR-029).

## Starting Point

Graf rodzic–dziecko (`ParentChildGraph`) zwraca przodków jako płaską listę bez pokoleń. Płótno `ZoomCanvas` dopasowuje treść do ekranu, ale celowo nie ma przybliżania. Panel osoby działa na każdej stronie przez `?person=`. Arkusz drzewa ma sztywną siatkę 8 kolumn z akcjami dodawania, więc nie nadaje się jako widok przodków.

## Desired End State

Z panelu osoby autor wybiera „Pokaż przodków” i trafia na `/tree/{osoba}/ancestors`. Widzi osobę na dole i przodków w górę, w 2–6 pokoleniach (domyślnie 4). Widok startuje z dopasowaną całością, a „Powiększ / Pomniejsz / Dopasuj”, Ctrl+kółko i szczypanie pozwalają czytać szczegóły. Kliknięcie karty otwiera panel osoby obok płótna, a z panelu przodka można przecentrować widok na niego. Drzewo działa jak dotąd.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Liczba pokoleń | Domyślnie 4, maks. 6 | Kompromis między zasięgiem a czytelnością. | Research |
| Liczenie pokoleń | Osoba = 1. pokolenie (4 = do pradziadków, 6 = rząd 32) | Konwencja Gramps; 6 pokoleń da się jeszcze dopasować. | Plan |
| Kierunek | Przodkowie w górę | Jak arkusz drzewa. | Research |
| Dopasowanie i zoom | Start z całością (niski `floor`) + przyciski | Rozstrzyga follow-up F1 z S-05. | Research |
| Gesty | Ctrl+kółko i szczypanie; samo kółko nic nie robi | Naturalne gesty bez przypadkowego przybliżania. | Plan |
| Kliknięcie karty | Otwiera panel osoby (cała karta) | Widok do oglądania szczegółów; inaczej niż na drzewie. | Research |
| Wejście / przecentrowanie | Tylko „Pokaż przodków” w menu „⋯” panelu osoby, pod „Pokaż na drzewie” | Jedno miejsce, działa na każdej stronie z panelem; nagłówek panelu nie traci tytułu. | Plan review F1 |
| Powtórzony przodek | Pokazany w każdym miejscu ze znacznikiem | Każda linia kompletna, autor widzi związek. | Plan |
| Brakujący rodzic | Szara karta „Nieznany ojciec/matka”, wyżej gałąź się kończy | Widać, kogo brakuje, bez pustych drabinek. | Plan |
| Rozmiar kart | Jednakowe karty jak na drzewie, czytelność przez zoom | Jedna karta; realne drzewa rzadko są pełne na 6. pokoleniu. | Plan |
| Wybór pokoleń | Przełącznik 2–6 w nagłówku, `?generations=` | Jedno kliknięcie, link zachowuje wybór. | Plan |
| Testy | Pest + Vitest + 1 E2E | Pokrywa dane, układ i jedyną ścieżkę wejścia. | Plan |

## Scope

**In scope:**
- Trasa `tree.ancestors`, akcja `BuildAncestorChart`, metoda grafu z limitem głębokości, wspólna kolejność rodziców.
- Strona `tree/ancestors`, czysta funkcja układu, karta przodka, linie w CSS.
- Tryb przybliżania w `ZoomCanvas`, włączany tylko w tym widoku.
- „Pokaż przodków” w panelu osoby; powrót po usunięciu osoby w centrum.
- `AncestorsDemoSeeder`, aktualizacja FR-018 i roadmapy S-15.

**Out of scope:**
- Dodawanie osób i relacji, przybliżanie na drzewie, wejście z nagłówka drzewa lub paska bocznego.
- Kompaktowe karty, pełna siatka 2ⁿ, karty-odsyłacze, znacznik „dalsi przodkowie”.
- Potomkowie, małżonkowie, rodzeństwo.

## Architecture / Approach

Serwer: jedno zapytanie grafu i jedno zapytanie osób z wydarzeniami. Z nich powstaje rekurencyjne drzewo `AncestorNode` (`person` z `repeated` i `parents` | `unknown` z `label`), z limitem pokoleń i kolejnością rodziców taką jak w arkuszu. Strona dostaje je jako prop `chart`. Klient: `layoutAncestorChart()` w `resources/js/lib/` przelicza drzewo na kolumny i rzędy siatki CSS; szerokość gałęzi wynika z liczby jej kart. Siatka trafia do `ZoomCanvas` z `zoomControls`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Dane przodków i trasa | Trasa z 404, drzewo węzłów, szkielet strony, testy Pest | Rozjazd kolejności rodziców z arkuszem po wydzieleniu reguły |
| 2. Przybliżanie w `ZoomCanvas` | Opcjonalne kontrolki i gesty, niski `floor` | Regresja drzewa (musi zostać bez zoomu) |
| 3. Układ i karty | Siatka pedigree z liniami, przełącznik pokoleń | Linie i wyrównanie przy asymetrycznych gałęziach |
| 4. Wejście z panelu + domknięcie | „Pokaż przodków”, seeder, dokumentacja | Ochrona niezapisanych zmian i zachowanie `generations` |
| 5. E2E | Test wejścia, zmiany pokoleń i przecentrowania | Stan panelu w URL między stronami |

**Prerequisites:** S-05 (płótno) i S-06 (panel osoby) są `done`; Sail i środowisko E2E działają.
**Estimated effort:** ~3–4 sesje w 5 fazach.

## Open Risks & Assumptions

- Przy 6 pełnych pokoleniach widok startowy (skala ok. 0,2) jest nieczytelny bez przybliżenia. To świadomy wybór: jednakowe karty zamiast kompaktowych.
- Wartość `ANCESTORS_FIT_FLOOR` (~0,1) to szacunek; ręczny przegląd w fazie 4 może ją skorygować.
- Dane z więcej niż dwoma rodzicami (omijające reguły) pokazują dwóch pierwszych, tak jak arkusz.

## Success Criteria (Summary)

- Z panelu dowolnej osoby autor jednym kliknięciem widzi jej przodków do 6 pokoleń i może przybliżyć dowolną część.
- Kliknięcie przodka otwiera jego panel, a z panelu można przecentrować widok; cudza osoba to 404.
- Drzewo zachowuje się bez zmian.
