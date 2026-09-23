# Konto z gotowym drzewem i pierwsza osoba — Plan Brief

> Full plan: `context/changes/account-tree-and-first-person/plan.md`

## What & Why

S-01 z roadmapy M-1. Użytkownik zakłada konto, od razu ma swoje jedno drzewo i dodaje do niego pierwszą osobę (imię, nazwisko, urodzenie, zamieszkanie, wyznanie, zgon; daty pełne, niepełne lub „około”), a potem widzi ją na liście i w podglądzie. To pierwszy wycinek z modelem danych i izolacją per użytkownik. Źle ułożony model wydarzeń wymusiłby przebudowę gwiazdy przewodniej (S-03) i małżeństw (S-07).

## Starting Point

Starter kit Laravel + Inertia React: rejestracja i logowanie przez Fortify, pusty Dashboard, cały UI po angielsku, brak jakiegokolwiek schematu domenowego. `CreateNewUser` tworzy samego użytkownika, bez transakcji. Reset hasła, którego PRD nie chce, jest nadal włączony.

## Desired End State

Po rejestracji albo zalogowaniu użytkownik trafia na polską listę „Osoby”. Formularz tworzy osobę z wydarzeniem urodzenia (zawsze) i zgonu (gdy zaznaczono „Zmarły/a”), a podgląd pokazuje daty w formatach `12.03.1850`, `03.1850` i `ok. 1850`. Cudza osoba zwraca 404, komunikaty walidacji są po polsku, a ścieżki resetu hasła nie istnieją.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Model urodzenia/zgonu | Tabele `events` + `event_participants` (rola `subject`) | S-03 wiąże skan z `event_id`, a S-07 dodaje małżeństwo jako jedno wydarzenie z dwoma uczestnikami, bez przebudowy. |
| Zapis daty | Kolumny `date_year/month/day` + flaga `date_is_approximate` | Niepełne daty bez fałszywego `01-01`, sortowanie po roku, „około” przy każdej precyzji. |
| Pola wymagane | Imię **lub** nazwisko | Akta często nie podają imienia albo nazwiska; brak zastępuje etykieta „NN”. |
| Miejsca i wyznanie | Miejsca jako tekst; wyznanie z listy 6 wartości (z „inne”) | Spójne wyznania bez słownika miejsc, którego PRD nie wymaga. |
| Polski UI | laravel-lang/common (dev), `APP_LOCALE=pl`, nowe strony po polsku | Kompletne polskie komunikaty Laravela bez ręcznych tłumaczeń; teksty stron auth/settings później. |
| Reset hasła | Usunięty w tym wycinku (faza 3) | PRD wyklucza odzyskiwanie hasła; to ~7 plików przy niskim ryzyku. |
| Zmarły bez danych | Checkbox „Zmarły/a” tworzy wydarzenie zgonu, także puste | Odróżnia „nieznane” od „zmarł, data nieznana”; S-03 może podpiąć akt zgonu przed odczytaniem daty. |
| Urodzenie | Zawsze tworzone, także puste | Każdy się urodził; S-03 zawsze ma do czego podpiąć akt urodzenia. |
| Kolejność listy | Nazwisko → imię, osoby bez nazwiska na końcu | Naturalny porządek rodzinny; S-04 dołoży wyszukiwanie. |
| Strona startowa | Lista osób zastępuje Dashboard (także Fortify `home`) | Drzewo od razu po zalogowaniu (FR-003), bez pustego placeholdera. |

## Scope

**In scope:**
- Tabele `trees`, `people`, `events`, `event_participants`; drzewo przy rejestracji (transakcja) i migracja uzupełniająca dla istniejących kont
- Enumy `EventType`, `ParticipantRole`, `Religion`; obiekt `PartialDate` z polskim formatem
- Trasy `persons.index|create|store|show`, `PersonPolicy` (404 dla cudzych), `PersonStoreRequest`, akcja `CreatePerson`
- Strony `persons/index`, `persons/create`, `persons/show`, pozycja „Osoby” w nawigacji, usunięty Dashboard
- laravel-lang pl, `APP_LOCALE=pl`; usunięcie resetu hasła

**Out of scope:**
- Edycja i usuwanie osoby (S-05), relacje i małżeństwa (S-06, S-07), skany (S-02, S-03), wyszukiwanie i paginacja (S-04)
- Słownik miejsc, walidacja spójności między wydarzeniami, polska kolacja sortowania
- Tłumaczenie tekstów stron auth/settings w React; zmiany weryfikacji e-mail

## Architecture / Approach

Każde zapytanie domenowe zaczyna się od `$user->tree`, a dostęp do pojedynczej osoby pilnuje `PersonPolicy` z `denyAsNotFound()`. Osoba łączy się z wydarzeniami przez pivot `event_participants` z rolą. `PartialDate` formatuje daty w PHP, a `PersonResource`/`EventResource` ustalają kształt propsów Inertia. Formularz React używa istniejącego wzoru `<Form {...PersonController.store.form()}>` i współdzielonego komponentu `PartialDateFields` dla urodzenia i zgonu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Drzewo zakładane razem z kontem | Schemat, modele, drzewo przy rejestracji, uzupełnienie dla istniejących kont | Nazwa tabeli `people` i kolizja nazwy `Event` z fasadą |
| 2. Dodanie i podgląd osoby po polsku | Lista, formularz, podgląd, izolacja, walidacja dat, locale pl | Największa faza; lokalizacja może zmienić komunikaty w istniejących testach |
| 3. Usunięcie resetu hasła | Brak tras i UI resetu | Martwe importy Wayfindera łamią `tsc` |

**Prerequisites:** F-01 zakończone (backup przed migracją w `deploy.sh`); zgoda na `laravel-lang/common` jako zależność dev (udzielona 2026-09-23).
**Estimated effort:** ~3–4 sesje: faza 1 ~1, faza 2 ~2, faza 3 <1.

## Open Risks & Assumptions

- Produkcja ma `APP_LOCALE=en` w `.env.prod`, więc właściciel musi ręcznie ustawić `pl` przed deployem.
- Testy działają na pgsql lokalnie i na sqlite w CI; sortowanie „bez nazwiska na końcu” używa `orderByRaw('last_name is null')`, przenośnego na obie bazy.
- Kolejność nazwisk z polskimi znakami zależy od kolacji bazy (świadomie pominięte).

## Success Criteria (Summary)

- Nowe konto od razu ma drzewo, a lista „Osoby” jest pierwszym ekranem po zalogowaniu.
- Osoba z datą „ok. 1850” i zaznaczonym „Zmarły/a” bez danych zapisuje się i wyświetla poprawnie po polsku; cudza osoba to 404.
- Nie da się zażądać resetu hasła.
