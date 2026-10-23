# Usunięcie starych kolumn po migracjach M-2 — Plan Brief

> Full plan: `context/changes/drop-legacy-columns/plan.md`

## What & Why

Migracje M-2 i późniejsze porządki przeniosły dane do nowych miejsc metodą „najpierw kopiuj, potem usuń”. Sześć starych kolumn wciąż jednak istnieje: `people.residence`, `people.maiden_name`, `events.place`, `sources.entry_number`, `sources.provenance` i `sources.cemetery_locality_id`. Dalej istnieje też UI „Stary numer wpisu”, który ręcznie czyści jedną z nich. Usuwamy je wszystkie, żeby schemat i model mówiły prawdę o danych (issue #35).

## Starting Point

Kontrola produkcji z 2026-10-22, zapytania tylko do odczytu:

- pięć kolumn ma pełne kopie w nowych miejscach;
- 4 „Pochodzenia” nagrobków są już w notatce;
- tylko 3 stare numery wpisu (akty 2, 3, 7) nie mają kopii, a każdy akt ma dokładnie jedno powiązanie bez numeru.

W kodzie aplikacji czyta i zapisuje jedynie UI „Stary numer wpisu”. Pozostałe kolumny występują już tylko w metadanych modeli i w testach. 8 testów migracji danych wstawia wiersze do starych kolumn.

## Desired End State

Sześciu kolumn nie ma, a trzy numery wpisu widać przy faktach jako numery powiązań. Panel źródła nie ma sekcji „Stary numer wpisu”. Migracja usuwająca nie pozwoli zgubić danych na żadnym środowisku: przerwie się, jeśli coś nie ma kopii. Stare testy migracji dalej działają.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Zakres | Wszystkie 6 kolumn, nie tylko 3 z issue | Kontrola produkcji pokazała, że każda ma kopię albo da się ją utworzyć bez utraty. |
| 3 stare numery wpisu | Osobna migracja kopiuje na jedyne puste powiązanie, w tym samym wydaniu kolejna usuwa kolumny | Jednoznaczne, bez ręcznej pracy i zgodne z zasadą „nie usuwaj w migracji, która kopiuje”. |
| Bezpiecznik | Migracja usuwająca liczy wartości bez kopii i przerywa przy > 0 | Nic nie ginie po cichu, także na lokalnej bazie z importem z 15.10. |
| Testy migracji danych (8) | Zamrożone: najpierw cofają migrację usuwającą | Stare migracje zostają pod ochroną na świeżych instalacjach (CI, E2E). |
| Testy usuwanej funkcji | Usunięte (zgoda użytkownika) | Sprawdzają zachowanie, które przestaje istnieć. |
| `down()` | Przywraca puste kolumny, z docblockiem o kopii przed `migrate` | Dane wracają tylko z `backup:run --only-db`. |

## Scope

**In scope:**
- usunięcie UI „Stary numer wpisu” (trasa, kontroler, akcja, pole w Resource i TS, komponent)
- migracja kopiująca z testem
- migracja usuwająca z bezpiecznikiem i testem
- metadane modeli
- zamrożenie 8 testów migracji, usunięcie testów starych kluczy
- odświeżenie lokalnej bazy, PR, kontrola po deployu

**Out of scope:**
- zmiana nazw wyliczanych pól API `place` i `cemetery_locality_id`
- pivot `event_source.entry_number`
- ratowanie lokalnych notatek z importu z 15.10
- sam deploy (robi go użytkownik)

## Architecture / Approach

Fazy w kolejności:

1. Znika funkcja, która czyta stary numer. Kolumny jeszcze są, więc aplikacja działa.
2. Dwie migracje w jednym wydaniu: kopiująca (`sources.entry_number` → `event_source.entry_number`, gdy źródło ma jedno puste powiązanie), a po niej usuwająca. Usuwająca najpierw sprawdza warunki z zapytania kontrolnego, potem robi `dropColumn`.
3. Lokalna baza z kopii produkcji, CI na PR, deploy użytkownika i kontrola.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Usunięcie „Stary numer wpisu” | Panel źródła bez starej sekcji, brak trasy i pola | Testy pivotu omyłkowo usunięte razem ze starymi |
| 2. Migracje i model | Kopia 3 numerów, usunięcie 6 kolumn z bezpiecznikiem, zamrożone testy | Zamrożenie 8 testów wymaga poprawnego `down()` na pełnym schemacie |
| 3. Lokalna baza i wydanie | Lokalna kopia produkcji, zielone CI, kontrola po deployu | Bezpiecznik na produkcji zatrzymuje deploy, jeśli dane zmieniły się od kontroli |

**Prerequisites:** S-04, S-07, S-08 i porządki z 20–22.10 wdrożone (tak); kontrola produkcji zrobiona 2026-10-22; archiwum kopii produkcji do lokalnego restore.
**Estimated effort:** ~1–2 sesje (faza 3 czeka na deploy użytkownika).

## Open Risks & Assumptions

- Jeśli między kontrolą a deployem ktoś doda stary numer wpisu albo ręcznie zmieni dane, bezpiecznik zatrzyma `migrate`. Aplikacja działa dalej, bo nie odwołuje się do kolumn, a dane trzeba przenieść i powtórzyć migrację.
- Usunięcie jest nieodwracalne co do danych. Jedyną drogą powrotu jest kopia przed `migrate` w `scripts/deploy.sh`.
- Lokalna baza traci notatki z importu z 15.10, bo jest zastępowana kopią produkcji. Plik źródłowy importu jest u użytkownika.

## Success Criteria (Summary)

- Na produkcji nie ma sześciu kolumn, a numery 38, 145 i 118 są przy faktach aktów 2, 3 i 7.
- Panel źródła nie ma „Stary numer wpisu”; wszystkie testy (Pest, Vitest, E2E) są zielone.
- Migracja usuwająca przerywa się na danych bez kopii, co udowadnia test.
