# Wyszukiwanie osób po wariantach nazwiska — Plan Brief

> Full plan: `context/changes/surname-variant-search/plan.md`

## What & Why

Import ma przenosić warianty zapisu nazwiska osoby (`warianty_nazwiska` z bloku), a wyszukiwarka osób ma znajdować osobę po każdym wariancie (US-03: Misiuk po „Mysiuk”). Bez wyszukiwania warianty są tylko tekstem w notatce (FR-019, roadmapa S-09).

## Starting Point

Import czyta warianty, ale zapisuje je tylko jako linię `Warianty nazwiska: A, B` w bloku „Z repo badawczego” w notatce osoby. Osoba nie ma pola na warianty. Wyszukiwarka `/persons` sprawdza każde słowo tylko w imieniu i nazwisku rodowym (bez wielkości liter, z polskimi znakami).

## Desired End State

Osoba ma listę wariantów w tabeli `person_surname_variants`, którą wypełnia i aktualizuje tylko import. Linii wariantów w notatce już nie ma. Wpisanie „Mysiuk” pokazuje Jana Misiuka z wyciszonym dopiskiem „zapis: Mysiuk”. Panel osoby pokazuje w „Dane osobowe” pole „Warianty nazwiska” tylko do odczytu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Kto pisze warianty | Tylko import; w panelu tylko do odczytu | Mały wycinek zgodny z roadmapą, bez ryzyka skasowania przez pełny payload formularza i bez konfliktów z ręczną zmianą. | Plan (autor) |
| Trafienie po wariancie na liście | Dopisek „zapis: …” tylko przy aktywnym wyszukiwaniu, dla słów niepasujących do imienia i nazwiska | Wynik nie wygląda na błąd, a lista bez wyszukiwania się nie zmienia. | Plan (autor) |
| Pickery osób | Bez zmian, tylko `/persons` | Zakres FR-019; pickery filtrują w przeglądarce po nazwie. | Plan (autor) |
| Zmiana wariantów w repo | Zwykła zmiana do zatwierdzenia (reguła trzech wartości); pierwszy raz uzupełnienie | Zgodne z zasadą „nic bez zgody” i z resztą pól osoby. | Plan (autor) |
| Model danych | Tabela `person_surname_variants` z `tree_id`, `position`, unikalne `(person_id, lower(name))` | Wzorzec `locality_aliases`; `whereHas` + `whereLike` pasuje do obecnego wyszukiwania. | Plan |
| Normalizacja | Przycięcie, bez pustych, bez duplikatów bez względu na wielkość liter, kolejność z repo | Ta sama postać dla porównania, pamięci i zapisu. | Plan |
| Polskie znaki | Nadal istotne w wyszukiwaniu | Reguła utrwalona testem `PersonsTest.php:88`. | Plan |
| Linia w notatce | Usunięta; stare notatki poprawia kolejny import jako zmianę notatki | Wartość w jednym miejscu, bez migracji przepisującej notatki. | Plan |
| Wersja kontraktu bloku | Bez zmiany, tylko opis pola | Znaczenie i nazwa pola się nie zmieniają. | Plan |
| Testy | Pest + Vitest, bez E2E | Ryzyko w zapytaniu, planie importu i czystej funkcji dopisku. | Plan |

## Scope

**In scope:**
- Migracja, model, relacja, fabryka; zapytanie `/persons` po wariantach
- Pole `warianty` w planie, zapisie i pamięci importu, obiekt `SurnameVariants`, usunięcie linii z notatki, podsumowanie komendy
- Opis pola w `research-block-contract.md`
- Pole „Warianty nazwiska” w panelu osoby, dopisek „zapis: …” w liście i tabeli
- Testy Pest (z drugim kontem) i Vitest

**Out of scope:**
- Ręczna edycja wariantów, warianty w pickerach i spisie miejsc
- Ignorowanie polskich znaków, zmiany w dopasowaniu osób przy imporcie
- Cicha synchronizacja, migracja przepisująca stare notatki, E2E

## Architecture / Approach

Blok → `ResearchPerson::$surnameVariants` → `SurnameVariants` (normalizacja, tekst, etykieta). Od tego miejsca są dwie ścieżki:
- **Pamięć i porównanie:** `RepoValues::person()` dodaje klucz `warianty`, a `PlanImport::planFields()` porównuje go przez `ThreeWayValue`, z kluczem zmiany `O-NNNN/warianty`.
- **Zapis:** `ApplyImport` (`createPerson`, `writeMatch`, `writeUpdate`, `guardPerson`) zastępuje wiersze osoby w tej samej transakcji.

Odczyt: `PersonController::index` dokłada `orWhereHas('surnameVariants', whereLike)` do każdego słowa. `PersonResource` wysyła `surname_variants`, gdy relacja jest załadowana. Czysta funkcja TS `surnameVariantHint` wylicza dopisek z `q`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tabela wariantów i wyszukiwarka | Tabela, relacja, `/persons` znajduje po wariancie, testy z drugim kontem | `orWhereHas` poza nawiasem słowa złamie regułę „każde słowo musi pasować” |
| 2. Import zapisuje warianty | Pole w regule trzech wartości, zapis, pamięć, komenda, kontrakt | Pominięcie pola w `guardPerson` lub `match` zapisu (wyjątek „niewłaściwa wartość pola”) |
| 3. Warianty widoczne | Pole w „Dane osobowe”, dopisek na liście i w tabeli, Vitest | Dopisek przy trafieniu po nazwisku albo zmiana wyglądu listy bez wyszukiwania |

**Prerequisites:** Sail uruchomiony, S-02 (import) wdrożony; osobne konto testowe do ręcznego importu wymyślonego repo.
**Estimated effort:** ~2 sesje, 3 fazy (faza 2 największa).

## Open Risks & Assumptions

- Jeśli produkcja ma już zaimportowane osoby, pierwszy import po wdrożeniu pokaże im zmianę notatki (blok bez linii wariantów) do zatwierdzenia oraz uzupełnienie wariantów. Baza deweloperska nie ma takich osób.
- Zmiana samej kolejności wariantów w repo pojawi się jako zmiana do zatwierdzenia. Przyjęte jako rzadkie.
- Osoba odłączona przez import (zniknęła z repo, autor ją zostawił) zachowuje warianty tylko do odczytu, jak blok notatki; ponowne dopasowanie z inną listą daje konflikt.

## Success Criteria (Summary)

- „Mysiuk” na `/persons` znajduje Misiuka z dopiskiem „zapis: Mysiuk”, a lista bez wyszukiwania wygląda jak dziś.
- Import zapisuje warianty przy osobie i proponuje ich zmiany jak inne pola, a drugie konto zostaje nietknięte.
- Panel osoby pokazuje warianty, a Pest, Vitest, typy, lint i build przechodzą.
