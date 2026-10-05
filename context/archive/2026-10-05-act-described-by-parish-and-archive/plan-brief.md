# Akt opisany parafią, rokiem, typem i archiwum (S-03) — Plan Brief

> Full plan: `context/changes/act-described-by-parish-and-archive/plan.md`
> Research: `context/changes/act-described-by-parish-and-archive/research.md`

## What & Why

Autor opisuje akt metrykalny osobnymi danymi: parafia ze spisu parafii (dopisywana w locie, z miejscowością siedziby ze spisu), rok, typ aktu i archiwum z podpowiedziami. Dziś parafia, archiwum i sygnatura lądują w jednym polu „Pochodzenie”. To ból nr 1 z PRD i gwiazda przewodnia M-2 (FR-001, FR-008, FR-009, US-01).

## Starting Point

Rok i typ aktu już są kolumnami źródła. „Pochodzenie” to `sources.provenance` (tekst). Spis miejscowości z S-01 daje gotowy wzorzec modułu spisu. Panel źródła z S-02 ma formularz z polami niekontrolowanymi, który resetuje każda wizyta Inertia przeładowująca panel. W aplikacji nie ma comboboxa ani endpointu JSON.

## Desired End State

Strona `/parishes` (menu „Parafie”) z listą i panelem edycji. W panelu aktu pole „Parafia” podpowiada parafie („Nazwa (wyznanie)”) i oferuje „Dodaj parafię „…””. To otwiera modal, w którym brakującą miejscowość siedziby dopisuje się jednym kliknięciem, a wpisany opis źródła zostaje. „Archiwum” (dla każdego rodzaju) podpowiada wcześniej wpisane archiwa. Stare „Pochodzenie” jest skopiowane do archiwum. Lista źródeł pokazuje parafię i archiwum.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| „Pochodzenie” → archiwum | Kopia 1:1; `provenance` zostaje w bazie | Parafii nie da się wydzielić automatycznie; kopiuj, potem usuń | Research (autor 1) |
| Zakres pól | Parafia, typ, numer tylko dla aktu; archiwum, rok, nazwa, link, notatka dla wszystkich | Dzisiejsze „Pochodzenie” nagrobka i „inne” musi mieć gdzie trafić | Research (autor 2) |
| Unikalność parafii | Nazwa (bez wielkości liter) + wyznanie w drzewie; brak wyznania = brak | W jednym mieście bywają parafie różnych wyznań o tej samej nazwie | Research (autor 3) |
| Wybór parafii | Własne pole z podpowiedziami + modal „Nowa parafia”, bez nowych zależności | Wielokrotnego użytku w S-07/S-08/S-09 | Research (autor 4) |
| Strona spisu | `/parishes` z panelem, kopia modułu miejscowości | FR-008: prowadzić spis parafii | Research (autor 5) |
| Wymagalność | Tylko nazwa; wyznanie i siedziba opcjonalne | Szybkie wprowadzanie; bez siedziby akt nie znajdzie się po miejscu w S-04 | Research (autor 6) |
| Siedziba, której nie ma w spisie | „Dodaj miejscowość „X”” w liście tworzy ją z samej nazwy | Brak modala nad modalem — ryzyko nr 1 z roadmapy | Plan |
| Lista źródeł | Kolumny „Parafia” i „Archiwum” zamiast „Pochodzenie”; karta „typ · rok · parafia” | Widać akty czekające na ręczne wydzielenie parafii | Plan |
| Podpowiedzi archiwów | Ten sam komponent w trybie wolnego tekstu (nie `<datalist>`) | Jednolite zachowanie i wygląd obok pola parafii | Plan |
| Dopisanie w locie | `useHttp` + trasy JSON `parishes/inline`, `localities/inline` | Brak wizyty Inertia = niezapisany formularz źródła się nie resetuje | Plan |
| Testy | Pest + Vitest + jedna faza E2E | E2E chroni stan formularza w przeglądarce, którego Pest nie widzi | Plan |

## Scope

**In scope:**
- Tabela, model, Policy, walidacja, akcje i strona `/parishes` z panelem `?parish=`
- Trasy JSON do dopisania parafii i miejscowości w locie
- `sources.parish_id`, `sources.archive`, migracja kopiująca `provenance` z testem
- Współdzielony `SuggestField` (tryb rekordu i tekstu), pola Parafia i Archiwum, modal „Nowa parafia”
- Dialog zmiany rodzaju obejmuje parafię; kolumny i karta na liście źródeł
- Spec E2E dopisania parafii w locie

**Out of scope:**
- Usunięcie kolumny `provenance` (późniejsza migracja), automatyczne wydzielanie parafii z tekstu
- Usuwanie i scalanie parafii/miejscowości (S-11); szukanie po miejscu i roku, numer wpisu przy fakcie (S-04); cmentarz nagrobka (S-09)
- Nowe zależności frontendu; sortowanie po parafii/archiwum; dopasowanie bez polskich znaków

## Architecture / Approach

Backend: moduł `Parish` skopiowany z `Locality` (indeks `(tree_id, lower(name), coalesce(religion,''))`, walidacja w drzewie, akcje z przechwyceniem wyścigu, Policy z 404). Źródło dostaje FK `parish_id` (`NO ACTION`, żeby kaskada usunięcia konta działała) i tekst `archive`. `UpdateSource` przestaje dotykać `provenance`. `sourcePanel` niesie opcje parafii, miejscowości, archiwów i wyznań, a filtr działa po stronie klienta. Front: `SuggestField` w panelu (Escape z `preventDefault`, żeby nie zamknąć panelu), modal w portalu wysyła `useHttp` i zwraca nową parafię do lokalnych opcji pola.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Spis parafii — backend | Tabela, ścieżka zapisu, strona, trasy JSON, licznik, testy | Unikalność z NULL-em wyznania w indeksie i walidacji |
| 2. Parafia i archiwum w źródle | Kolumny, kopia „Pochodzenia”, walidacja, opcje panelu | Edycja kasująca zachowane `provenance` |
| 3. Komponent podpowiedzi + strona parafii | `SuggestField`, `/parishes`, dopisanie siedziby | Klawiatura/Escape w panelu, szerokość 20rem |
| 4. Formularz i lista źródeł | Pola Parafia/Archiwum, modal, dialog rodzaju, kolumny | Reset niezapisanego formularza przy dopisaniu |
| 5. E2E | Spec dopisania parafii w locie | Niestabilność przez lokatory lub czekanie na czas |

**Prerequisites:** S-01 (spis miejscowości) i S-02 (panel źródła) — oba `done`. Działający Sail i środowisko E2E (`scripts/e2e-server.sh`).
**Estimated effort:** ~4–5 sesji po godzinach w 5 fazach.

## Open Risks & Assumptions

- Danych produkcyjnych „Pochodzenia” nie sprawdzano. Kopia jest 1:1, więc nic nie ginie, ale może być sporo ręcznego wydzielania parafii.
- Zakładamy, że spisy pozostaną małe (setki wpisów), więc pełne listy w `sourcePanel` wystarczą. Przy tysiącach przejście na wyszukiwanie przez `useHttp` nie zmieni API komponentu.
- Literówka w „Dodaj miejscowość” tworzy zbędny wpis w spisie; naprawa przez edycję teraz, scalenie w S-11.

## Success Criteria (Summary)

- Autor opisuje prawdziwy akt z nową parafią i nową miejscowością siedziby bez wychodzenia z panelu źródła i bez utraty wpisanych pól.
- Każde dotychczasowe „Pochodzenie” jest widoczne jako archiwum, a archiwum podpowiada wcześniej wpisane wartości.
- Spis parafii działa jak spis miejscowości: unikalny w drzewie, cudzy wpis = 404, objęty kopią zapasową.
