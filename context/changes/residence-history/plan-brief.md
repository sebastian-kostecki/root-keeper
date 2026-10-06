# Kilka zamieszkań z latami (S-08) — Plan Brief

> Full plan: `context/changes/residence-history/plan.md`
> Research: `context/changes/residence-history/research.md`

## What & Why

Autor ustawia osobie kilka miejscowości zamieszkania ze spisu z opcjonalnymi latami od–do (FR-011). Dziś zamieszkanie to jedno pole tekstowe, które nie odwołuje się do spisu miejscowości i nie mieści historii przeprowadzek. Dotychczasowy tekst staje się pierwszą pozycją listy, bez lat.

## Starting Point

`people.residence` to wolny tekst zapisywany z „Danych osobowych”, dialogu „Dodaj osobę” i slotów drzewa, pokazywany w panelu i w tabeli osób. Spis miejscowości, picker z dopisaniem w locie i wzorzec migracji tekst → spis są gotowe po S-01 i S-07.

## Desired End State

W panelu osoby jest karta „Zamieszkania” pod „Danymi osobowymi”. Pokazuje listę miejscowości z latami posortowaną po roku, a edycja pozwala dodawać, zmieniać i usuwać wiersze (picker + rok od + rok do). Każda osoba z dawnym tekstem ma po migracji jedno zamieszkanie bez lat. Stare pole i kolumna w tabeli osób znikają.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Gdzie edytować | Osobna karta „Zamieszkania” w panelu, zapis przez `PUT persons/{id}` | Jedna ścieżka zapisu osoby, bez nowych tras. | Research |
| Kolejność | Rok „od”, a bez niego „do”, rosnąco; bez lat na końcu | Chronologia bez ręcznego układania, wzorzec `PersonOrdering`. | Research |
| Remis w kolejności | Kolejność dodania (`id`) | Wpis z migracji zostaje pierwszy wśród niedatowanych, nowe dopisują się pod nim. | Plan |
| Lata | Oba opcjonalne, 1000 – bieżący rok, od ≤ do | Jak daty faktów; niepełna wiedza jest normą. | Research |
| Niepełny wiersz | Całkiem pusty pomijany; z latami bez miejscowości → „Wybierz miejscowość.” | Nie gubi wpisanych lat i nie karze za przypadkowe kliknięcie. | Plan |
| Formularze tworzenia | Zamieszkania tylko w panelu; pole znika z dialogów | Mniejsze dialogi, lista potrzebuje miejsca. | Research |
| Duplikaty | Dozwolone (powrót do miejscowości = nowy wiersz) | Odpowiada rzeczywistym przeprowadzkom; S-11 nie rozwiązuje kolizji. | Research |
| Tabela osób | Kolumna „Zamieszkanie” znika | Jedna wartość nie oddaje listy. | Research |
| Stary tekst | Zostaje w `people.residence`, nieczytany i niezapisywany | CLAUDE.md „copy first, drop later”, jak `events.place` w S-07. | Plan |
| Zapis listy | Usuń i wstaw w kolejności payloadu; nic nie rób, gdy lista się nie zmieniła | Prosto zachowuje kolejność dodania i nie przepisuje wierszy przy zapisie innych sekcji. | Plan |
| E2E | Jeden test ryzyka przez `/10x-e2e` | Edytor wielu pickerów i pełne zastąpienie są widoczne tylko w przeglądarce. | Plan |

## Scope

**In scope:**
- Tabela `residences`, migracja z dosianiem i kopią, model z relacjami (`Locality::residences()` dla S-11)
- Walidacja i zapis listy w edycji osoby, posortowany odczyt w panelu
- Karta „Zamieszkania” z edytorem wierszy, usunięcie starego pola i kolumny
- Pest, Vitest, jeden E2E

**Out of scope:**
- Usunięcie kolumny `people.residence`; zamieszkania w dialogach tworzenia
- Źródła, daty dzienne, ręczna kolejność, unikalność
- Zamieszkania na drzewie, w rodzinie, w wyszukiwaniu; scalanie miejscowości (S-11)

## Architecture / Approach

`residences` (tree_id, person_id → kaskada; locality_id → NO ACTION; year_from, year_to). `PersonUpdateRequest` przyjmuje `residences.*` (puste wiersze usuwane z zachowaniem kluczy, żeby błąd trafił pod właściwy wiersz). `UpdatePerson` zastępuje listę w transakcji osoby. `PersonOrdering::residences()` sortuje widok, formularz i porównanie przy zapisie. Na froncie `ResidencesFields` dzieli jedną listę miejscowości i dopisane miejscowości między wszystkie wiersze, a `personFormToPayload` zawsze niesie listę, więc zapis innej sekcji jej nie kasuje.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Migracja i model | Tabela, dosianie, kopia tekstów, test migracji | Miejscowości przemianowane przed S-08 wrócą jako nowe (przyjęte w S-01) |
| 2. Backend | Walidacja, zapis listy, posortowany odczyt, testy różnicowe | Brak klucza w payloadzie czyści listę — front musi go zawsze nieść |
| 3. Frontend | Karta „Zamieszkania”, payload, usunięcie pola i kolumny | Stan pickera przy usuwaniu wierszy; wiele pickerów w jednej karcie |
| 4. E2E | Test ryzyka S-08 w panelu | — |

**Prerequisites:** S-01 (spis) i S-07 (picker, wzorzec migracji) — ukończone. Fazy 1–3 wdrażać razem.
**Estimated effort:** ~3–4 sesje w 4 fazach.

## Open Risks & Assumptions

- Nie policzono na produkcji, ile tekstów zamieszkania nie ma dziś miejscowości — runbook z licznikami przed/po.
- Pozycja z migracji (bez lat) spada na koniec listy, gdy pojawią się datowane — zgodne z decyzją o kolejności.
- Usunięcie `people.residence` i `events.place` nie ma właściciela w roadmapie.

## Success Criteria (Summary)

- Autor dodaje w panelu kilka zamieszkań z latami (także nową miejscowość w locie) i widzi je w kolejności chronologicznej.
- Każde dawne „Zamieszkanie” jest pierwszą pozycją listy, bez utraty żadnej wartości.
- Zapis innych sekcji osoby nie zmienia listy zamieszkań.
