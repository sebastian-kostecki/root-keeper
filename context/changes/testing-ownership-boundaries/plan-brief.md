# Granice własności (ryzyko #4) — Plan Brief

> Full plan: `context/changes/testing-ownership-boundaries/plan.md`
> Research: `context/changes/testing-ownership-boundaries/research.md`

## What & Why

Faza 3 wdrożenia testów, część dla ryzyka #4: inny zarejestrowany użytkownik nie może odczytać ani zmienić moich osób, źródeł i plików, podając id z mojego drzewa w adresie lub treści żądania. Rejestracja jest otwarta, więc to realny wektor nadużycia; PRD mówi wprost: „cudza osoba to 404”.

## Starting Point

Testy cudzego id istnieją dla wszystkich 19 tras z id w adresie, ale rozproszone po 6 plikach, z niejednolitym dowodem braku zapisu i bez wariantu z pustą treścią. Badanie znalazło jedną realną dziurę: 5 tras `persons/{person}/…` waliduje treść przed sprawdzeniem właściciela, więc cudza osoba + niepoprawna treść daje błąd walidacji zamiast 404 (wyrocznia istnienia id).

## Desired End State

Jeden plik `tests/Feature/OwnershipBoundariesTest.php` dowodzi dla każdej trasy z id w adresie: 404 i zero zmian w bazie i na dysku — przy treści poprawnej i pustej. Strażnik wywala CI, gdy pojawi się nowa trasa z parametrem bez przypadku w datasecie. 5 tras zagnieżdżonych autoryzuje przed walidacją. Cookbook §6.5 mówi, jak dopisać test własności dla nowego endpointu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wyrocznia: cudze id w adresie | 404 i brak zapisu niezależnie od treści | PRD v4 „cudza osoba to 404”; 422 zdradzałoby istnienie rekordu | Research |
| Wyrocznia: cudze id w treści | Błąd walidacji bez zapisu, komunikat jak dla nieistniejącego id | Nie zdradza istnienia; zgodne z kodem i decyzją z archiwum (F5) | Research |
| Cudzy passkey | 403 akceptowane, klucz nie znika | Kod vendor, dane konta, nie drzewa | Research |
| Zakres | Tylko ryzyko #4; #5 osobno | #5 nie jest jeszcze zbadane | Research |
| Mechanizm poprawki 5 tras | `authorize(): Response` w Form Request (+ nowy `PersonSpouseStoreRequest`) | Wzorzec już w 4 żądaniach; `PersonStoreRequest` dzieli `persons.store` bez `{person}` | Plan |
| Warianty treści w datasecie | Poprawna i pusta dla tras z treścią | Łapie całą klasę „walidacja przed autoryzacją”, także na przyszłych trasach | Plan |
| Dowód braku zapisu | Skrót wszystkich tabel (wiersze + md5) i lista plików przed/po | Jedna asercja łapie zapis w tabeli, o której autor testu nie pomyślał | Plan |
| Kompletność i duplikaty | Strażnik z `Route::getRoutes()`; rozproszone testy w pełni pokryte — usunięte | Nowy endpoint bez testu wywala CI; brak nadmiarowych kopii | Plan |

## Scope

**In scope:**
- poprawka kolejności autoryzacji w 5 trasach `persons/{person}/…` (test-first)
- dataset 19 tras × warianty treści + helper `ownershipSnapshot()` + strażnik tras
- usunięcie zdublowanych testów, dowód braku zapisu w tych, które zostają
- cudze `?person=` na stronach hosta, `tree_id` w treści `persons.store`/`sources.store`, cudzy passkey
- cookbook §6.5 (część własności) i wiersz fazy 3 w §3

**Out of scope:**
- ryzyko #5 (reguły relacji), zmiana wyroczni dla id w treści, brzmienie `test-plan.md:42`
- obrona w głąb w `app/Actions/Relations/`, wspólna reguła „moje drzewo”, refaktor `BackupTestCase`
- konfiguracja serwera WWW, wygląd strony 404

## Architecture / Approach

Pest feature na prawdziwym PostgreSQL (polityki porównują `tree_id`, reguły `exists` to SQL — stub by kłamał). Lista wpisów datasetu (trasa, metoda, parametry adresu z drzewa ofiary, treść z drzewa atakującego) zasila jednocześnie dataset i strażnika. Każdy przypadek: `Storage::fake('local')`, snapshot → żądanie → `assertNotFound()` → snapshot równy.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Pięć tras → 404 (`/10x-tdd`) | helper, dataset 5 tras (5 czerwonych → zielone), `authorize()` w Form Requestach | regresja happy path / błędów walidacji własnej osoby |
| 2. Dataset 19 tras + strażnik | ok. 29 przypadków, strażnik, usunięte duplikaty | usunięcie testu z asercją, której dataset nie pokrywa |
| 3. `?person=`, `tree_id`, passkey | domknięcie granic spoza adresu | brak fabryki passkeya, `password.confirm` |
| 4. Cookbook §6.5 + §3 | wzorzec do kopiowania dla nowych endpointów | — |

**Prerequisites:** Sail uruchomiony (`vendor/bin/sail up -d`), zielony `tests/Feature` na `main`.
**Estimated effort:** ~2 sesje (faza 1 + 2 w jednej, 3 + 4 w drugiej).

## Open Risks & Assumptions

- Zakładamy (z odczytu kodu, nieuruchomione), że 5 przypadków z pustą treścią jest dziś czerwonych — faza 1 to potwierdza jako pierwszy krok; inny wynik zatrzymuje fazę.
- Faza 2 i 3 to testy charakteryzujące — czerwony wynik oznacza nowy błąd do zgłoszenia autorowi, nie do cichej poprawki.
- Skrót bazy zakłada `SESSION_DRIVER`/`CACHE_STORE` = `array` w testach; zmiana tego zmusi do wykluczenia kolejnych tabel.

## Success Criteria (Summary)

- Cudze id w adresie dowolnej trasy daje 404 i niczego nie zmienia — także przy pustej treści.
- Nowa trasa z id w adresie nie przejdzie CI bez przypadku w datasecie.
- Następny endpoint dostaje test własności według §6.5, bez ponownego badania.
