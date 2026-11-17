# Pierwszy import przodków do pustego drzewa — Plan Brief

> Full plan: `context/changes/first-import-into-empty-tree/plan.md`

## What & Why

S-01 nauczył komendę `import:research` czytać bloki danych z repo badawczego i raportować błędy, ale niczego nie zapisuje. S-02 to gwiazda przewodnia M-3: najmniejszy kompletny przebieg bloki → podsumowanie → zgoda → zapis → drzewo na prawdziwych plikach. Ma sprawdzić tor zapisu, zanim dojdzie dopasowanie do ręcznych osób (S-03) i ponowny import (S-04).

## Starting Point

Komenda czyta repo do typowanego `ResearchRepoReading` (osoby, małżeństwa, pominięcia, ostrzeżenia). Nie wykrywa jednak cykli rodzic–dziecko. Żadna tabela nie ma identyfikatora z repo. `events` zna tylko datę dokładną i „ok.”, a pozostałe rodzaje dochodzą w S-07. Nie ma też kolumn na miejsce bez spisu, poziom A–D, notatkę wydarzenia ani warianty nazwiska.

## Desired End State

Na koncie z drzewem bez osób komenda:
1. pokazuje raport i podsumowanie,
2. pyta o zgodę (domyślnie „nie”),
3. na produkcji robi kopię bazy,
4. w jednej transakcji zapisuje osoby, relacje, małżeństwa, urodzenia i zgony ze wszystkimi rodzajami daty — każdy rekord z `repo_id`.

Miejsca, pochowania, poziomy, notatki wydarzeń i warianty nazwiska trafiają tekstem do notatki osoby. Autor wybiera probanta w UI i widzi przodków w drzewie oraz w widoku przodków. Drzewo z osobami = odmowa bez zapisu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Drzewo z osobami | Odmowa bez zapisu, odesłanie do S-03/S-04 | Produkcyjne drzewo z ręcznymi osobami nie dostanie duplikatów przez pomyłkę | Plan |
| Probant | Import go nie ustawia; komenda podpowiada osobę z Ahnentafel 1 | Zostaje decyzja „bez automatycznego probanta” z widoku drzewa | Plan |
| Daty nieostre | S-07 robione osobno **przed** S-02; S-02 importuje wszystkie 5 rodzajów | Zakres widoczny i edytowalny od pierwszego importu, dwie średnie zmiany zamiast jednej dużej | Plan |
| Wartości bez kolumny | Tekst w notatce osoby, blok „Z repo badawczego (O-…):” | Nic z repo nie ginie do czasu S-05, S-08 i S-09 | Plan |
| Identyfikator repo | `repo_id` w `people`, `events`, `parent_child_relations`, unikalny w drzewie; klucze wydarzeń i relacji wyprowadzone z bloku | S-04 odróżni każdy zaimportowany rekord od dodanego ręcznie | Plan (CLAUDE.md, roadmapa) |
| Cykle i sprzeczna płeć | Pomija każdą relację na cyklu i relację ze sprzeczną płcią, z powodem; reszta idzie | Brak arbitralnego wyboru krawędzi, zgodnie z FR-004 | Plan (uwaga F3 z S-01) |
| Decyzja a zapis | `PlanFirstImport` (bez zapisu) i `ApplyFirstImport` (jedna transakcja, istniejące Actions) | Wzorzec z CLAUDE.md; niezmienniki aplikacji są ostatnią linią obrony | CLAUDE.md |
| Kopia na produkcji | `backup:run --only-db` po zgodzie, przed zapisem; błąd = nic nie zapisane | Wymóg CLAUDE.md, wzór `RestoreBackup` | CLAUDE.md |
| Podgląd przy pustym drzewie | Podsumowanie i jedno potwierdzenie | Bez dopasowań i konfliktów (FR-005); podgląd per zmiana dopiero w S-03/S-04 | PRD |

## Scope

**In scope:**
- migracja `repo_id`,
- `PlanFirstImport` i `ApplyFirstImport` z klasami wartości,
- `ResearchDate::label()`,
- podsumowanie, zgoda, kopia i zapis w `import:research`,
- testy z drugim użytkownikiem,
- aktualizacja reguły w kontrakcie bloku.

**Out of scope:**
- import do drzewa z osobami (S-03, S-04),
- ustawianie probanta,
- miejsca ze spisu i wydarzenie pochowania (S-05),
- kolumny poziomu i wariantów (S-08, S-09),
- schemat i UI rodzaju daty (S-07),
- źródła i skany (S-06),
- import „od zera” (S-10),
- wycofanie importu.

## Architecture / Approach

`ReadResearchRepo` (S-01) zwraca odczyt. `PlanFirstImport` sprawdza pustość drzewa, wycina relacje z cykli (silnie spójne składowe) i ze sprzeczną płcią, mapuje daty na kontrakt S-07 i układa notatki. Komenda drukuje plan i pyta o zgodę, a na produkcji robi kopię.

`ApplyFirstImport` w `DB::transaction` blokuje drzewo i ponownie sprawdza pustość. Potem tworzy osoby, wydarzenia urodzenia i zgonu, relacje przez `CreateParentChildRelation` i małżeństwa przez `CreateMarriage`, z `repo_id` na każdym rekordzie. Każdy wyjątek cofa całość.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Identyfikator repo w schemacie | `repo_id` + unikalny indeks w 3 tabelach, modele | Niski — tylko kolumny nullable |
| 2. Planowanie importu | `PlanFirstImport`, plan, format notatki, testy na fixture i repo z cyklem | Kontrakt dat z S-07 inny niż założony — zmienia się tylko mapowanie |
| 3. Zapis importu | `ApplyFirstImport` w jednej transakcji, testy granicy drzewa i wycofania | Wyjątek w zagnieżdżonej transakcji Actions musi cofnąć całość |
| 4. Komenda | Podsumowanie, zgoda, kopia na produkcji, zapis, podpowiedź probanta; reguła w kontrakcie | Podsumowanie nieczytelne przy ok. 190 osobach |

**Prerequisites:** S-01 (done) i **S-07 (`fuzzy-event-dates`) zaimplementowane** — rodzaj daty w `events`. Do ręcznej weryfikacji potrzebne są nowe konto testowe i świeża kopia repo ze skonwertowanymi plikami przodków.
**Estimated effort:** ~3 sesje po godzinach w 4 fazach (po S-07).

## Open Risks & Assumptions

- Kontrakt S-07 jest znany (S-07 zaimplementowane): `MapsEventInput` przyjmuje `qualifier` i `until_year`, kolumny `date_qualifier` i `date_until_year`; `ResearchDate` mapuje się na nie 1:1.
- Notatka osoby niesie dane z repo do czasu S-05, S-08 i S-09. Te wycinki (i S-04) muszą rozpoznać blok „Z repo badawczego (…)” i go sprzątać. Ręczna edycja tego bloku stanie się w S-04 konfliktem.
- Pierwszy import na produkcji czeka na S-03 i S-05. S-02 dowodzi toru wyłącznie na koncie testowym.
- Osoba bez klucza `zgon` w bloku wygląda w drzewie na żyjącą („1850 –”). To kwestia kompletności bloków w repo, nie importu.

## Success Criteria (Summary)

- Autor importuje przodków z prawdziwego repo do nowego konta testowego jednym poleceniem z podsumowaniem i zgodą, a po wyborze probanta widzi ich w drzewie i w widoku przodków z relacjami i datami (także nieostrymi).
- Bez zgody, bez udanej kopii na produkcji, na drzewie z osobami i przy błędzie w trakcie nic nie jest zapisywane; testy z drugim użytkownikiem dowodzą, że cudze drzewo jest nietknięte.
- Testy, analiza statyczna i formatowanie przechodzą.
