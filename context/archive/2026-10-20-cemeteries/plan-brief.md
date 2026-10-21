# Cmentarze jako rekord miejsca — Plan Brief

> Full plan: `context/changes/cemeteries/plan.md`
> Research: `context/changes/places-review/research.md`

## What & Why

Cmentarz nagrobka to dziś po prostu miejscowość, więc nie da się odróżnić dwóch cmentarzy w jednym mieście ani zapisać, kto nim zarządza i jakiego jest wyznania. Zmiana robi z cmentarza osobny rekord drzewa osadzony w miejscowości. Wskazują na niego nagrobek i miejsce pochowania osoby. To druga z trzech zmian wydzielonych z researchu `places-review` (po szczegółach miejscowości, przed parafią / urzędem).

## Starting Point

`sources.cemetery_locality_id` wskazuje miejscowość, a pole „Cmentarz” to picker miejscowości. Pochowanie (`events`, `type='burial'`) ma tylko `locality_id` i podpowiada się miejscowością cmentarza z nagrobka. Parafia jest gotowym wzorcem rekordu osadzonego w miejscowości: zakładka `/places`, panel, dodawanie w locie, scalanie, usuwanie tylko nieużywanych.

## Desired End State

Na `/places` są trzy zakładki: Miejscowości, Parafie, Cmentarze. Cmentarz ma panel z polami nazwa (opcjonalna), miejscowość, zarządca, wyznanie i opis. Nagrobek wybiera cmentarz albo dodaje nowy w oknie. W sekcji Zgon obok miejscowości pochowania jest opcjonalny cmentarz, podpowiadany z nagrobka. Panel osoby i oś czasu pokazują „Cmentarz parafialny, Luboml” albo „cmentarz Luboml”. Filtr „Miejsce” ma grupę Cmentarze. Istniejące nagrobki i pochowania mają cmentarze tam, gdzie wynika to z danych.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Cmentarz | Rekord osadzony w miejscowości: nazwa, miejscowość, zarządca, wyznanie, opis | Ten sam kształt co parafia, bez nowej architektury | Research |
| Zarządca | komunalny / wyznaniowy / wojenny / inny + osobne wyznanie (`Religion`) | Podział z ustawy, a „parafialny” to wyznaniowy rzymskokatolicki | Research |
| Kolejność zmian | Cmentarze przed parafią / urzędem | Wybór autora | Plan |
| Tożsamość | Nazwa opcjonalna, klucz drzewo + miejscowość + nazwa | Migracja niczego nie wymyśla, a etykieta „cmentarz Luboml” zostaje jak dziś | Plan |
| Migracja nagrobków | Jeden cmentarz bez nazwy na parę (drzewo, miejscowość) | Wierne przeniesienie dzisiejszego znaczenia | Plan |
| Pole pochowania | Miejscowość + opcjonalny cmentarz tej miejscowości | Działa, gdy wiadomo tylko gdzie, a nie na którym cmentarzu | Plan |
| Stare pochowania | Cmentarz tylko z nagrobka osoby, dokładnie jeden w miejscowości pochowania | Każde dopisanie ma dowód, a domysł nie udaje pewnego wpisu | Plan |
| Scalanie miejscowości | Cmentarze o tej samej nazwie scalane w tej samej transakcji | Scalenie dalej jednym kliknięciem, bez naruszenia unikalności | Plan |
| Filtr „Miejsce” | Grupa Cmentarze; miejscowość dalej trafia nagrobek przez miejscowość cmentarza | Rozróżnia cmentarze w jednym mieście i zachowuje FR-004 | Plan |
| Widoki osoby | Panel i oś czasu z etykietą cmentarza, karty drzewa z samą miejscowością | Szczegół tam, gdzie jest miejsce, a wąskie karty się nie rozjeżdżają | Plan |
| Panel cmentarza | Formularz jak parafia, liczniki Nagrobki / Pochowani w tabeli | Sprawdzony wzorzec, bez nowej sekcji list | Plan |
| Stara kolumna | `cemetery_locality_id` zostaje bez klucza obcego | Zasada „copy first, drop later”, a zdjęcie klucza nie blokuje scalania miejscowości | Plan |
| Testy | Pest + Vitest + aktualizacja speca `gravestone-cemetery-and-buried` | Przeglądarka pilnuje jedynego przepływu formularz → panel → wybór źródła | Plan |

## Scope

**In scope:**
- tabela `cemeteries`, `sources.cemetery_id`, `events.cemetery_id`, enum `CemeteryManager`, pełen zestaw rekordu (polityka, walidacja, akcje, kontrolery, zasób)
- zakładka Cmentarze na `/places`, panel, okna usuwania i scalania, picker z oknem dodawania
- odwołania z miejscowości (blokada usuwania, scalanie z kolizjami), liczniki
- dwie migracje danych z testami, wypełnianie i podpowiedź pochowania z nagrobka
- etykiety, filtr „Miejsce”, wybór źródła do faktu, panel osoby, oś czasu
- PRD (FR-002, FR-004, FR-022, model danych) i reguły w `CLAUDE.md`

**Out of scope:**
- usunięcie `sources.cemetery_locality_id` (późniejsza zmiana)
- współrzędne, „nie istnieje” i nazwy alternatywne cmentarza
- listy osób i nagrobków w panelu cmentarza
- cmentarz na kartach drzewa
- cmentarz dla pochowań bez nagrobka
- szukanie osób po cmentarzu, data pogrzebu, parafia / urząd
- nowy spec E2E zakładki

## Architecture / Approach

Cmentarz powtarza wzorzec parafii plik w plik. Różnią go wymagana miejscowość w kluczu i opcjonalna nazwa. Etykietę buduje serwer (`Cemetery::label()`), front ją tylko pokazuje. Pochowanie z cmentarzem trzyma `locality_id` równe miejscowości cmentarza. Ten niezmiennik pilnują zapis osoby, wypełnianie z nagrobka, zmiana i scalanie cmentarza oraz scalanie miejscowości. Dzięki niemu karty drzewa, liczniki i wszystko, co czyta `events.locality_id`, działa bez zmian. Klient źródła dalej dostaje `cemetery_locality_id` (wyliczane z cmentarza), więc dopasowanie po miejscowości się nie zmienia.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Rekord cmentarza (backend) | Schemat, enum, CRUD, scalanie i usuwanie, `?kind=cmentarze`, odwołania z miejscowości | Kolejność przy scalaniu miejscowości łamie indeks unikalności |
| 2. Zakładka Cmentarze (frontend) | Tabela, panel, okna, trzy segmenty, licznik w menu, zrzuty | Logika strony `/places` napisana pod dwie zakładki |
| 3. Nagrobek wskazuje cmentarz | Migracja nagrobków, picker z oknem, etykiety, grupa w filtrze, spec E2E | Stara kolumna z kluczem obcym blokuje scalanie miejscowości |
| 4. Pochowanie na cmentarzu | Migracja pochowań, pole w sekcji Zgon, podpowiedź, widoki, PRD, `CLAUDE.md` | Niezmiennik miejscowości pochowania rozjedzie się w którymś miejscu zapisu |

**Prerequisites:** Sail uruchomiony, środowisko E2E (`scripts/e2e-server.sh`); `locality-details` już zarchiwizowane.
**Estimated effort:** ~4 sesje (po jednej na fazę), faza 3 największa.

## Open Risks & Assumptions

- Danych produkcyjnych nie sprawdzano. Zakładamy dziesiątki nagrobków, a liczba utworzonych cmentarzy równa się liczbie różnych miejscowości nagrobków.
- Po scaleniach miejscowości wartości w starej kolumnie mogą wskazywać usunięte id. Akceptowalne, bo kolumna jest tylko do odczytu, a wycofanie fazy 3 je zeruje.
- Wycofanie migracji pochowań jest świadomie puste: przypisanie jest addytywne, a kolumnę usuwa wycofanie fazy 1.

## Success Criteria (Summary)

- Autor dodaje, zmienia, scala i usuwa cmentarze na `/places`, a nagrobek i pochowanie wskazują wybrany cmentarz.
- Istniejące nagrobki wyglądają i wyszukują się jak przed zmianą, a pochowania z nagrobków mają cmentarz.
- Pest (z dwoma testami migracji), Vitest, lint, typy, build i cały zestaw E2E są zielone; zrzuty są w `screenshots/`.
