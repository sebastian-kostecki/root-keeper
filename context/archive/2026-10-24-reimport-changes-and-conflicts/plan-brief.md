# Kolejny import z podglądem zmian i konfliktów — Plan Brief

> Full plan: `context/changes/reimport-changes-and-conflicts/plan.md`

## What & Why

Autor co jakiś czas aktualizuje kopię repo badawczego i chce przenieść zmiany do drzewa bez dublowania i bez utraty ręcznych poprawek (US-02, FR-005, FR-006, FR-008, FR-014, FR-015). Dziś `import:research` po pierwszym imporcie odmawia pracy („Ponowny import (S-04) jeszcze nie działa”). Główne ryzyko według autora: ręczna poprawka znika po imporcie.

## Starting Point

S-02 i S-03 dały pierwszy import z dopasowaniem do ręcznych osób, podglądem w terminalu (tabela, „zatwierdź wszystkie / przejrzyj pojedynczo”, pytania „k/N”) i `repo_id` na osobach, wydarzeniach i relacjach. Nigdzie nie ma zapisu, co import wpisał ostatnio. `updated_at` się do tego nie nadaje, a historii zmian w aplikacji nie ma. Pominięty wpis odczytu nie niesie swojego id.

## Desired End State

Komenda na drzewie po imporcie pokazuje:
- nowe osoby (z dopasowaniem z S-03 do ręcznych),
- zmiany z repo, których nikt nie ruszał w aplikacji (zatwierdzane zbiorczo albo pojedynczo),
- konflikty z ręcznymi zmianami (obie wartości i poprzednia z repo),
- rekordy usunięte w repo (usuń z listą skutków albo odłącz),
- rekordy usunięte w aplikacji (zostaw usunięte albo przywróć).

Każda decyzja zapada raz. Drugi przebieg bez zmian kończy się „Nic do zapisania.”. Zapis jest atomowy, poprzedzony kopią bazy na produkcji, i tylko w drzewie konta.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| „Zmienione od ostatniego importu” | Pamięć wartości z repo per pole w tabeli `repo_snapshots`, reguła repo teraz / repo poprzednio / aplikacja | Pole daje najmniej fałszywych konfliktów, a tabela przetrwa usunięcie rekordu. |
| Zmiany z repo bez ręcznej edycji | Tabela, potem „Zatwierdź wszystkie / Przejrzyj pojedynczo” | Spełnia FR-006 bez dziesiątek pytań; wzór z S-03. |
| Odrzucona zmiana i „Zostaw z aplikacji” | Zapamiętane: pamięć dostaje wartość z repo, pytanie wraca tylko po kolejnej zmianie w repo | Każda decyzja zapada raz. |
| „Zostaw” przy usuniętym w repo | Odłącz: `repo_id = null`, rekord staje się rekordem tylko z aplikacji | Pytanie pada raz, a reguła „bez id repo = nietykalny” chroni rekord dalej. |
| Usunięte w aplikacji, repo nadal ma | Zapytaj raz: „Zostaw usunięte” (domyślne) / „Przywróć z repo”, zapamiętane | Ręczne usunięcie nie wraca po cichu, a pomyłkę da się cofnąć. |
| Blok „Z repo badawczego” w notatce | Pole jak inne: nietknięty → zmiana, edytowany → konflikt, reszta notatki nietknięta | Miejsca i poziomy z repo aktualizują się przed S-05/S-08 bez utraty dopisków. |
| Usunięcie osoby z danymi z aplikacji | Dozwolone po tabeli skutków (zdjęcie, źródła, zamieszkania, relacje, probant), domyślnie „Zostaw” | Wzór „najpierw pokaż skutki”; scalenie w repo da się posprzątać importem. |
| Rekordy z S-02/S-03 bez pamięci | Bez uzupełniania; każda różnica to konflikt (reguła S-03) | Wartość „z aplikacji” z S-03 mogła celowo różnić się od repo. |
| Urodzenie dopisane w aplikacji, repo dodało `urodzenie` | Oznaczenie istniejącego + uzupełnienie albo konflikt daty (jak S-03) | UI zawsze tworzy urodzenie, więc to częsty przypadek. |
| Urodzenie usunięte w repo | Tylko odłączenie, bez usuwania | Aplikacja zakłada urodzenie przy każdej osobie. |
| Plik z błędnym blokiem | Nie jest „usunięty”; wpis bez czytelnego id wyłącza pytania o usunięcie („Nie do oceny”) | Błąd w repo nie może skasować danych. |
| Architektura | Jeden uogólniony potok (`PlanImport` / `ApplyImport`), pierwszy import jako przypadek szczególny | Graf relacji, małżeństwa i notatka z S-03 zostają wspólne. |

## Scope

**In scope:**
- tabela pamięci i reguła trzech wartości;
- klasyfikacja kluczy (znane, nowe, usunięte w aplikacji, usunięte w repo, nie do oceny);
- dopasowanie nowych osób do ręcznych;
- zmiany i konflikty pól osoby, dat urodzenia, zgonu, ślubu i bloku notatki;
- usuwanie, odłączanie i przywracanie osób, zgonów, relacji i ślubów;
- komenda z podglądem;
- `DeletePerson` bezpieczny w transakcji importu.

**Out of scope:**
- miejsca, poziomy A–D, warianty jako pola (S-05, S-08, S-09) i źródła (S-06);
- zmiany w UI;
- uzupełnianie pamięci starych importów;
- ponowne pytania o odrzucone wartości;
- automatyczne podłączanie odłączonego rekordu;
- usuwanie urodzenia;
- tryb nieinteraktywny z pytaniami;
- zmiana kontraktu bloku.

## Architecture / Approach

Potok importu:
1. Odczyt repo.
2. `ClassifyRepoRecords`: klucze z repo, rekordy z `repo_id` i `repo_snapshots`, skutki usunięcia.
3. `MatchResearchPersons`: tylko nowe osoby, kandydaci bez `repo_id`.
4. Decyzje sprzed planu: tożsamość, usunięte w repo, usunięte w aplikacji.
5. `PlanImport` (`ThreeWayValue`, `NoteBlock`, graf relacji z S-03 bez usuwanych krawędzi).
6. Zatwierdzenia zmian i rozstrzygnięcia konfliktów.
7. Zgoda, kopia bazy, `ApplyImport`.

`ApplyImport` działa w jednej transakcji, w kolejności: sprawdzenia pod blokadą → usunięcia i odłączenia → osoby → zmiany → relacje i małżeństwa → pamięć. Pamięć zawsze dostaje wartości z repo, nigdy z aplikacji.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Pamięć ostatniego importu | Tabela `repo_snapshots`, `RepoKeys`, nazwy `PlanImport` / `ApplyImport`, pamięć przy pierwszym imporcie, `DeletePerson` z `afterCommit` | Zmiana nazw dotyka wielu testów; mechaniczna, ale szeroka |
| 2. Klasyfikacja i reguła trzech wartości | `ThreeWayValue`, `NoteBlock`, id pominiętego wpisu, `ClassifyRepoRecords` ze skutkami usunięcia, dopasowanie tylko nowych | Błędna klasyfikacja „usunięte w repo” przy pominiętych plikach |
| 3. Plan ponownego importu | Zmiany, konflikty, usunięcia, przywrócenia, relacje po usunięciach, wpisy pamięci | Wspólny graf po usunięciach i grupowanie kluczy osoby |
| 4. Zapis ponownego importu | Atomowy zapis z kontrolą pod blokadą i pamięcią = repo | Kolejność usunięć przed tworzeniem; pliki zdjęć przy wycofaniu |
| 5. Komenda | Podgląd w terminalu, testy komendy, ręczny przebieg na kopii `test-pack` | Czytelność tabel i liczba pytań na prawdziwych danych |

**Prerequisites:** S-02 i S-03 zrobione (są). Sail działa. Do ręcznego przebiegu potrzebna jest kopia `test-pack`, zmieniana przez autora.
**Estimated effort:** ~5–6 sesji, po jednej na fazę (Faza 3 i 4 najcięższe).

## Open Risks & Assumptions

- Na produkcji importu jeszcze nie było (czeka na S-05), więc brak pamięci dotyczy tylko kont dev i testowych. Tam każda różnica wyjdzie jako konflikt.
- `PlanImport` (dziś 882 linie) rośnie. Plan wydziela reguły (`ThreeWayValue`, `NoteBlock`, `ClassifyRepoRecords`, `RepoKeys`), ale planer pozostanie duży.
- Usunięcie osoby przez import jest nieodwracalne poza kopią bazy (PRD Non-Goals). Siatką są domyślne „Zostaw” i tabela skutków.
- S-05 – S-09 rozszerzą regułę trzech wartości o swoje pola (miejsce, poziom, warianty) i zmniejszą blok notatki.

## Success Criteria (Summary)

- Wartość zmieniona ręcznie w aplikacji nigdy nie znika po imporcie bez pytania z obiema wartościami.
- Poprawka w repo do nietkniętej wartości wchodzi po jednym zbiorczym zatwierdzeniu. Drugi przebieg bez zmian nie zadaje pytań.
- Rekord usunięty w repo albo w aplikacji wymaga decyzji autora raz. Rekordy bez id repo i cudze drzewa są nietknięte.
