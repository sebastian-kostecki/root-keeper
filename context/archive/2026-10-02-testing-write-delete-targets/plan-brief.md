# Zapis i usuwanie trafiają we właściwe dane — Plan Brief

> Full plan: `context/changes/testing-write-delete-targets/plan.md`
> Research: `context/changes/testing-write-delete-targets/research.md`

## What & Why

Faza 2 wdrożenia testów (test-plan §3) dla ryzyk #1 i #3. Testy Pest mają dowieść dwóch rzeczy. Akcja z drzewa lub modala zmienia dokładnie wskazaną osobę lub parę, a potwierdzenie innych faktów zostaje. Usunięcie kasuje dokładnie to, co pokazało potwierdzenie. Produkt nie ma kosza ani cofania, więc pomyłka osoby albo zbyt szerokie usunięcie to trwała utrata lub zafałszowanie drzewa.

## Starting Point

Id w żądaniach pochodzą z propsów Inertia (`sheet`, `personPreview`), a parę wybiera serwer z `?spouse=`. Listy skutków usunięcia to prop `personPreview` (osoba) i `confirms` (źródło). Istniejące testy sprawdzają pojedyncze rekordy na fiksturze jednej pary. Żaden test nie porównuje listy skutków z faktycznie usuniętym zbiorem, a `ownershipSnapshot()` umie tylko „równe / różne”, nie „co dokładnie się zmieniło”.

## Desired End State

Dwa nowe pliki testów na jednej scenie rodzinnej: kilka związków, współrodzic, nieznany partner, źródła dzielone z postronnymi, pliki na dysku. Każdy test porównuje **pełną różnicę** bazy i dysku z oczekiwaną z fikstury. Dla usunięć osoby i źródła ta różnica jest równa liście z propsa potwierdzenia. Siłę nowych testów sprawdza selektywna bramka mutacyjna `pest --mutate` (odpowiednik Strykera dla PHP, już w repo). Cookbook §6.1 i §6.4 opisuje wzorzec, §6.7 przebieg mutacji, a §6.6 zapisuje werdykt dla fazy 4 (e2e).

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Warstwy | Tylko Pest; Vitest i wyciąganie logiki do `lib` odłożone | Id pochodzą z propsów, więc kontrakt „prop → żądanie → baza” sprawdza Pest | Research (autor) |
| `confirms` dla małżeństwa | 2 wiersze (po jednym na małżonka) to zachowanie zamierzone | Wyrocznia „zdarzenie + osoba” dopuszczała oba odczyty; rozstrzygnął autor | Research (autor) |
| Niezgodna para w drzewie własnym | Dane poprawne, bez nowej reguły; testy dokumentują `other_parent_id` ustawiony vs `null` | Wyrocznia milczy; reguła byłaby nowym zachowaniem produktu | Research (autor) |
| Mechanizm asercji | Helper `databaseRows()` + `rowDiff()`: usunięte, dodane i zmienione wiersze per tabela oraz pliki | „Nic poza tym” wynika z listy tabel z różnicą, także dla przyszłych tabel | Plan |
| Zakres zapisów #1 | 9 zapisów z drzewa + 4 z modala (edycje, przypięcie i odpięcie źródła) | Pokrywa obie połowy ryzyka #1: zła osoba lub para oraz utrata potwierdzenia po edycji | Plan |
| Bramka mutacyjna | `pest --mutate` (wbudowane `pest-plugin-mutate`) po fazach 2–3, zawężone `--class`, ad hoc, bez progu `--min` | Stryker mutuje tylko JS/TS, a testy fazy są w PHP; Infection dublowałby narzędzie już obecne w repo | Plan (prośba autora o Strykera) |
| Faza 4 (e2e) | Werdykt w §6.6, status w §3 bez zmian | Odpowiada na warunek §3 bez zmiany strategii (Lekcja 1) | Plan |
| Oczekiwane wartości | Z rekordów sceny i wyroczni z archiwum, nigdy z zapytania liczącego listę | Inaczej porównanie listy z usuniętym zbiorem byłoby tautologią | Research / test-plan §2 |

## Scope

**In scope:**
- `databaseRows()`, `rowDiff()`, `familyScene()` w `tests/Pest.php`
- `tests/Feature/DeleteImpactTest.php`: osoba, źródło, małżeństwo, relacja
- `tests/Feature/WriteTargetsTest.php`: wybór pary, 9 zapisów z drzewa, 4 z modala
- Bramka `pest --mutate` po fazach 2–3 z oceną każdej przetrwałej mutacji
- `test-plan.md` §4, §5 (bramka ad hoc), §6.1, §6.4, §6.6, §6.7

**Out of scope:**
- Vitest i refaktor dialogów do `lib`; e2e i okno wyścigu po `choose()`
- Nowe reguły serwera i jakiekolwiek zmiany kodu produkcyjnego
- Przepisywanie istniejących testów; zmiana strategii lub kolumn §3
- Stryker, Infection, nowe zależności; bramka mutacyjna w CI i progi `--min`

## Architecture / Approach

Scena → GET strony i odczyt id z propsa, tak jak robi to dialog → `$before = databaseRows()` → żądanie → `rowDiff()` porównane w całości z oczekiwaną różnicą zbudowaną z rekordów sceny. Usunięcie osoby sprawdza trzy zbiory: fikstura = `personPreview` (`marriages[].id`, `relation_id`, pary zdarzenie–źródło) = różnica. Usunięcie źródła sprawdza `confirms` = odłączone zdarzenia, `is_last_source` = stan po usunięciu, a pliki usunięte to tylko pliki tego źródła. Testy charakteryzują istniejący kod. Czerwony test oznacza rozbieżność z wyrocznią: wtedy pytamy autora.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Helper i scena | Różnica wierszy per tabela + scena z postronnymi | Helper zwracający pustą różnicę dałby fałszywą zieleń (chronią przed tym asercje niepustych różnic) |
| 2. Usunięcia (#3) | 4 testy: osoba (3 zbiory), źródło (`confirms`), małżeństwo, relacja + bramka `--mutate` | Oczekiwania przepisane z kodu zamiast z fikstury |
| 3. Zapisy (#1) | Wybór pary z `?spouse=`, 9 zapisów z drzewa, 4 z modala + bramka `--mutate` | Żądania budowane z rekordów sceny zamiast z propsa — wtedy test nie sprawdza kontraktu UI |
| 4. Cookbook | §6.1, §6.4 bez TBD; §6.7 i §4/§5 dla mutacji; werdykt e2e w §6.6 | Wzorzec zbyt związany z tą sceną, by przenieść go na nowy endpoint |

**Prerequisites:** Sail działa (PostgreSQL 18), zielony `main`; research z decyzjami autora.
**Estimated effort:** ~2 sesje w 4 fazach (wszystkie przez `/10x-implement`).

## Open Risks & Assumptions

- Zakładamy, że testy przejdą od razu (charakteryzacja). Czerwony wynik bez mutacji to sygnał błędu w kodzie lub w wyroczni i wymaga decyzji autora, a nie zmiany oczekiwania.
- Okno po `choose()` (stary `sheet` do końca wizyty) zostaje niezweryfikowane; werdykt w §6.6 mówi, kiedy wrócić do e2e.
- Stan bazowy bramki (2026-10-02): w `DeletePerson.php:22` przetrwała mutacja „bez kasowania małżeństw” (`PersonsTest.php`, wynik 83,33%) — faza 2 musi ją zabić. Przebieg dla `BuildFamilySheet` może być długi; dopuszczalny per klasa.
- Mutacje spoza katalogu mutatorów Pest (np. „aktualizacja w miejscu” → „usuń i utwórz”) zostają ręcznymi kontrolami w kryteriach faz 2–3.

## Success Criteria (Summary)

- Dwa nowe pliki testów zielone; `pest --mutate` nie zostawia nieocenionej przetrwałej mutacji, a każda ręczna mutacja z kryteriów (krok małżeństw w `DeletePerson`, miniatura w `DeleteSource`, `confirms`, wybór związku, aktualizacja w miejscu, `detach`) daje czerwony test.
- Lista skutków osoby i źródła jest dowodnie równa usuniętemu zbiorowi; zapisy z drzewa i modala nie dotykają nikogo poza wskazaną osobą lub parą.
- Cookbook pozwala napisać kolejny test zapisu lub usunięcia bez czytania tego planu.
