# Jedno nazwisko rodowe — Plan Brief

> Full plan: `context/changes/single-birth-surname/plan.md`
> Research: `context/changes/marriage-form-and-other-surnames/research.md` (sekcje D–F; decyzja 4 skorygowana)

## What & Why

Osoba ma jedno nazwisko — zawsze rodowe (z urodzenia). Autor chce wpisywać tylko nazwisko rodowe, a dziś formularz ma dwa pola („Nazwisko” i „Nazwisko rodowe”), a nazwa pokazuje „z d.”.

## Starting Point

`people.last_name` + `people.maiden_name`, „z d.” w `Person::displayName()`. Karta drzewa bierze rodowe przed nazwiskiem, wyszukiwanie przeszukuje obie kolumny, a walidacja zabrania samego rodowego. W bazie deweloperskiej tylko 2 z 367 osób mają rodowe inne niż nazwisko.

## Desired End State

Jedno pole „Nazwisko rodowe” (`last_name`) w formularzu i widoku. Nazwa to „Imię Nazwisko” wszędzie: panel, lista, pickery, karta. Wyszukiwanie działa po imieniu i tym nazwisku. Osoby z dwoma nazwiskami mają rodowe jako nazwisko, a dawne nazwisko na końcu notatki („Nazwisko po mężu: Nowak”).

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Liczba nazwisk | Jedno, zawsze rodowe | Autor wpisuje tylko nazwisko z urodzenia. | Plan (korekta decyzji 4 z research) |
| Etykieta pola | „Nazwisko rodowe” | Znaczenie pola jednoznaczne w etykiecie. | Plan |
| Dawne nazwisko po ślubie | Dopisane do notatki osoby | Nic nie ginie, zgodnie z „reszta w notatce” z PRD. | Plan |
| Treść linii w notatce | Kobieta / nieznana płeć: „Nazwisko po mężu: X”; mężczyzna: „Wcześniej zapisane nazwisko: X” | Naturalne brzmienie bez błędu u mężczyzn. | Plan |
| Kobiety z jednym nazwiskiem (dev: 165, 97 zamężnych) | Bez zmian, poprawka ręczna | Bez zgadywania i bez szumu w notatkach. | Plan |
| Kolumna `maiden_name` | Zostaje w bazie, kod jej nie używa | Reguła „najpierw kopiuj, później usuwaj”. | Plan |
| Wycinanie „z d.” w wyszukiwaniu | Usunięte | Znacznik znika z aplikacji. | Plan |
| `down()` migracji | Nic nie robi, z komentarzem | Dawne nazwisko żyje w edytowalnej notatce; wycofanie z kopii przed `migrate`. | Plan |
| Osobna zmiana | `single-birth-surname`, nie faza zmiany formy ślubu | Forma ślubu jest już wdrożona i zamknięta. | Plan |

## Scope

**In scope:**
- Migracja danych + test migracji
- Request, akcje, model (`displayName`, `cardNameParts`), resources, wyszukiwanie
- Jedno pole w formularzu i widoku „Dane osobowe”, typy i payload
- Przepisanie testów Pest i Vitest utrwalających „z d.”, lokatory 10 specyfikacji E2E, FR-020 i non-goal w PRD

**Out of scope:**
- Lista wielu nazwisk, pole na nazwisko po ślubie, wyliczanie go z nazwiska męża
- Oznaczanie kobiet, które mają jedno nazwisko
- Usunięcie kolumny `maiden_name` (później)
- Nowe testy E2E, zmiana `use-initials`

## Architecture / Approach

`last_name` zmienia znaczenie na nazwisko rodowe; bez nowych kolumn i tras. Migracja kopiuje rodowe do `last_name` i dopisuje dawne nazwisko do `note`. Kod przestaje czytać i zapisywać `maiden_name`, a pickery dziedziczą nową nazwę przez `displayName()`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Migracja danych | Rodowe w `last_name`, dawne nazwisko w notatce, test migracji | Utrata dawnego nazwiska — chroni ją test i kopia przed `migrate` |
| 2. Jedno pole w aplikacji | Jedno pole, nazwa bez „z d.”, wyszukiwanie, testy, E2E, PRD | Spec E2E, który po zmianie lokatora przestaje chronić swoje ryzyko |

**Prerequisites:** Sail uruchomiony; środowisko E2E według `context/foundation/test-stack.md`.
**Estimated effort:** ~1–2 sesje, 2 fazy.

## Open Risks & Assumptions

- Część kobiet ma w polu „Nazwisko rodowe” nazwisko po mężu (dev: do 97 osób); świadomie zostaje do ręcznej poprawki.
- Zakładam, że produkcja ma podobnie mało osób z dwoma nazwiskami; migracja i tak działa partiami.

## Success Criteria (Summary)

- Autor wpisuje i widzi jedno nazwisko rodowe; nigdzie nie ma „z d.”.
- Osoby z dwoma nazwiskami po migracji mają rodowe jako nazwisko, a dawne nazwisko w notatce.
- Testy Pest (z testem migracji), Vitest, typy, lint, build i dotknięte E2E przechodzą.
