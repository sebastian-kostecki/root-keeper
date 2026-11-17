# Poziom pewności A–D przy fakcie — Plan Brief

> Full plan: `context/changes/fact-confidence-level/plan.md`

## What & Why

Każdy fakt (urodzenie, zgon, pochowanie, ślub) dostaje osobne pole „poziom pewności” A–D (A akt pierwotny, B indeks / źródło wtórne, C wskazówka, D hipoteza), niezależne od źródeł. Autor widzi je w aplikacji i ustawia ręcznie, a import przenosi je z repo badawczego (FR-018, FR-015, roadmapa S-08). Dziś poziom z repo ląduje tylko jako tekst „poziom X” w notatce osoby.

## Starting Point

Enum `ConfidenceLevel` (A–D) istnieje, a parser bloku waliduje `poziom` dla wszystkich czterech faktów. `events` nie ma jednak kolumny, a import dopisuje poziom do notatki osoby. Zapis osoby to pełne zastąpienie (brak klucza czyści wartość). Pochowanie w UI to tylko wybór cmentarza w sekcji Zgon.

## Desired End State

Kolumna `events.confidence` (null = nieokreślony). Karty Urodzenie, Ślub i Zgon w panelu osoby, panel małżeństwa i nagłówki zakładki Źródła pokazują neutralny znacznik „Poziom A” obok statusu źródła oraz pole „Poziom pewności: A — akt pierwotny”. Formularze faktów (także pochowanie i panel dodawania osoby) mają Select z opisami. Import zapisuje poziom przy fakcie i porównuje go regułą trzech wartości, a „poziom X” znika z notatki.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Pochowanie | Ma poziom, edytowalny w sekcji Zgon obok cmentarza | Każdy fakt z bloku ma poziom i nic nie zostaje w notatce; FR-015 wymaga ręcznej edycji. |
| Gdzie widać | Panele faktów: Przegląd, panel małżeństwa, nagłówki zakładki Źródła | Spełnia FR-018 bez zmiany kart drzewa, osi czasu i listy osób. |
| Wygląd | Znacznik „Poziom A” w nagłówku karty + pole z opisem; bez poziomu: brak znacznika, pole „nieokreślony” | Widać od razu i wiadomo, co znaczy litera. |
| Kolor | Neutralny znacznik dla A–D | Bez nowych tokenów; nie konkuruje ze statusem źródła. |
| Kontrolka | Select „nieokreślony / A — akt pierwotny / …” | Znaczenie liter widać przy wyborze; wzorzec pola „Forma ślubu”. |
| Ponowny import | Reguła trzech wartości z kluczem pamięci `poziom`; bez pamięci: uzupełnienie albo konflikt | Jak data, miejsce i warianty: nic bez zgody autora. |
| „poziom X” w notatce | Usunięty; stare notatki poprawia kolejny import jako zmianę Notatki | Wartość w jednym miejscu, bez migracji przepisującej notatki. |
| Model danych | `events.confidence` nullable + `label()`/`options()` w enumie, prop `confidenceLevels` | Wzorzec `MarriageForm`; nowa pusta kolumna bez `MigrationTest`. |
| Semantyka zapisu | Brak klucza `confidence` = null, jak `marriage_form` | Zgodne z pełnym zastąpieniem; front zawsze wysyła poziom każdego faktu. |
| Wersja kontraktu bloku | Bez zmiany, tylko opis pola | Znaczenie i nazwa `poziom` się nie zmieniają. |
| Testy | Pest + Vitest + 1 spec E2E | E2E pilnuje utraty poziomu przy pełnym zapisie, którą wcześniej złapał spec dat nieostrych. |

## Scope

**In scope:**
- Migracja, enum z opisami, model, fabryka, walidacja w żądaniach osoby i małżeństwa, `MapsEventInput`, zasoby, prop
- Select poziomu w formularzach urodzenia, zgonu z pochowaniem i ślubu; znacznik i pole w panelach faktów
- Import: pamięć `poziom`, porównanie (wydarzenia, śluby, wpisy ręczne), zapis, guardy, usunięcie z notatki, linia podsumowania, opis w kontrakcie
- Testy Pest (z drugim kontem w imporcie), Vitest, jeden spec Playwright, zrzuty ekranu

**Out of scope:**
- Poziom na osi czasu, liście i tabeli osób, kartach drzewa i przodków
- Kolory per poziom, poziom przy zamieszkaniach i danych osobowych
- Wyprowadzanie poziomu ze źródeł, ostrzeżenia „A bez źródła”
- Migracja przepisująca dane lub notatki, zmiana `wersja` kontraktu, seedery

## Architecture / Approach

Ręczny zapis rozszerza wspólny szew `MapsEventInput::eventAttributesFrom()` o `confidence`, więc osoba, panel dodawania i małżeństwo dostają poziom w jednym miejscu. Pochowanie przekazuje `burial.confidence` razem z cmentarzem. Odczyt: `EventResource` (`confidence`, `confidence_label`), `PersonResource` (`burial_confidence`), `PersonFormResource`. Front: `ConfidenceField` (Select + ukryte pole) i `ConfidenceBadge` w slocie `badge` obok `EventSourceStatus`. Payload niesie poziom każdego faktu przy każdym zapisie. Import: `RepoValues::event()` dodaje klucz `poziom`, a `PlanImport::confidenceItem()` porównuje go przez `ThreeWayValue` w `planCompared()` i w gałęzi ślubów (nowe sloty `PlannedMarriage`). `ApplyImport` zapisuje przez `createEvent`, `markEvent`, `CreateMarriage` i sprawdza nowe ramiona guardów.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Serwer — kolumna, zapis ręczny i odczyt | Kolumna, enum z opisami, walidacja, zapis i odczyt, Pest | Pochowanie: poziom zapisany bez cmentarza albo zgubiony przy `putEvent` |
| 2. Frontend — wybór i wyświetlanie | Select, znacznik, pole, payload, Vitest, zrzuty | Payload pomija poziom nieedytowanej sekcji → cicha utrata przy pełnym zapisie |
| 3. Import — poziom z repo | Reguła trzech wartości, zapis, guardy, notatka bez „poziom X”, komenda, kontrakt | Brak ramienia w `writeUpdate`/`guardPerson` (wyjątek „niewłaściwa wartość pola”) albo pominięty poziom przy `createEvent` |
| 4. E2E — poziom przeżywa zapis innej sekcji | Jeden spec Playwright przez `/10x-e2e` | Spec zielony mimo zepsutego payloadu (sprawdzane celowym zepsuciem) |

**Prerequisites:** S-02 (import) jest zrobiony. Worktree `.claude/worktrees/fact-confidence-level` nie ma `vendor/` ani `node_modules/`. Przed pierwszym poleceniem Sail trzeba je zainstalować w worktree albo uruchamiać Sail z tego katalogu.
**Estimated effort:** ~3–4 sesje w 4 fazach (Faza 3 największa).

## Open Risks & Assumptions

- Pierwszy ponowny import po wdrożeniu pokaże uzupełnienie poziomu przy każdym fakcie z `poziom` i jedną zmianę Notatki u każdej takiej osoby. Zamierzone, ale to dużo pozycji w jednym podglądzie. Raz wrócą też pytania o zachowane usunięcia rekordów z poziomem w repo.
- Zakładamy, że `poziom` ślubu jest zgodny u obojga małżonków (parser już pomija rozbieżne małżeństwo).

## Success Criteria (Summary)

- Autor ustawia poziom A–D przy urodzeniu, zgonie, pochowaniu i ślubie i widzi go w panelach faktów; fakty sprzed zmiany są „nieokreślone”.
- Zapis jednej sekcji nie kasuje poziomu w innej.
- Import przenosi poziom z repo do pola faktu i nigdy nie nadpisuje ręcznej zmiany bez zgody autora.
