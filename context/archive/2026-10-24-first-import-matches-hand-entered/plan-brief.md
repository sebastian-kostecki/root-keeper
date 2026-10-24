# Pierwszy import dopasowuje osoby wpisane ręcznie — Plan Brief

> Full plan: `context/changes/first-import-matches-hand-entered/plan.md`

## What & Why

Komenda `import:research` ma zadziałać na drzewie z ręcznie wpisanymi osobami: osobę z repo przypisać do istniejącej zamiast tworzyć duplikat, przypadki wątpliwe i konflikty oddać autorowi do rozstrzygnięcia pojedynczo, a zdjęcia, skany, notatki i inne dane z aplikacji zostawić. To najryzykowniejsze założenie PRD (import nie zdubluje ręcznego drzewa) i odpowiedź na PRD Open Questions 4 (podgląd w terminalu przy dziesiątkach decyzji).

## Starting Point

Po S-02 import działa tylko na drzewie bez osób: odczyt → plan → zgoda → kopia na produkcji → zapis w jednej transakcji, każdy rekord z `repo_id`. W dev jest ręczne drzewo (110 osób): każda ma urodzenie (75 z rokiem), miejsce to tylko miejscowość ze spisu, brak pola na nazwisko po mężu. Ok. 51 osób repo ma tu imiennika, 11 kilku.

## Desired End State

Autor uruchamia import na swoim drzewie. Komenda pokazuje tabelę dopasowań pewnych do zbiorczego zatwierdzenia. Każdy przypadek wątpliwy pokazuje z tabelą porównania i opcjami „to ta sama / nowa / pomiń”, a każdy konflikt wartości z opcjami „zostaw z aplikacji / weź z repo”. Po zgodzie dopasowane osoby dostają `repo_id`, uzupełnione puste pola i dopisany blok notatki. Nowe osoby, relacje i małżeństwa wchodzą obok, a nic z aplikacji nie znika. Drzewo z rekordami z importu to odmowa (S-04).

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Kandydat | Pierwsze imię + nazwisko lub wariant, bez wielkości liter i polskich znaków | Łapie różnice zapisu z aktów, a dwa elementy chronią przed fałszywym dopasowaniem. |
| Elementy | Sześć osobnych: rok i miejsce urodzenia, ojciec, matka, małżonek, rok zgonu; krewni po imieniu i nazwisku | Tak genealog porównuje akty; bez propagacji dopasowań. |
| Zgodność lat | Różnica ≤ 2 lata (rok vs zakres: [od−2, do+2]; zakresy: nakładanie); „przed/po” tylko przeczą | Lata w repo bywają wyliczone z wieku; ±2 daje najwięcej zgodnych bez sklejania ojca i syna. |
| Pewne vs wątpliwe | Pewne = jedyny kandydat, ≥ 2 zgodne, 0 sprzecznych | Sprzeczny rok to klasyczny sygnał ojca/syna o tym samym imieniu. |
| Sprzeczności | Daty, płeć, rodzic o innym imieniu; miejsce i małżonek nigdy | Nazwisko bywa po mężu, miejsca mają warianty (S-05), powtórne śluby są normalne. |
| Pewne dopasowania | Tabela + „zatwierdź wszystkie / przejrzyj pojedynczo” | Zgoda autora (FR-006) bez dziesiątek identycznych odpowiedzi. |
| Wątpliwe | To <kandydat> / inna — dodaj jako nową / pomiń (domyślne) | Autor może odłożyć osobę, której dziś nie rozstrzygnie. |
| Wartości | Puste w drzewie → uzupełnij z repo (także doprecyzowanie daty: ten sam rok, repo z miesiącem/dniem); różne → konflikt, domyślnie „zostaw z aplikacji” | Obie wartości w podglądzie (PRD), bez pytań o oczywiste uzupełnienia. |
| Relacje | Brakujące dodaj, istniejące oznacz, sprzeczne pomiń z raportem | Ręczne relacje nigdy nie znikają; niezmienniki aplikacji działają jak dziś. |
| Notatka | Blok „Z repo badawczego” dopisany pod istniejącą | Ręczna treść zostaje, miejsca i poziomy nie giną do S-05/S-08/S-09. |
| Sesja | Jeden przebieg, decyzje w pamięci, przerwanie = od nowa | Bez plików stanu; zapis i kopia dopiero po ostatniej zgodzie. |
| Pierwszy import | Drzewo bez osób z `repo_id`; z nimi → odmowa (S-04) | Czysta granica z ponownym importem. |

## Scope

**In scope:**
- Action dopasowania z regułą tożsamości.
- Plan z decyzjami: uzupełnienia, konflikty, relacje na wspólnym grafie drzewa i repo, pominięcia.
- Zapis do istniejących rekordów bez `UpdatePerson`.
- Podgląd w terminalu (Laravel Prompts `select`) i testy każdej warstwy z drugim użytkownikiem.

**Out of scope:**
- Ponowny import i „usunięte w repo” (S-04).
- Miejsca ze spisu (S-05).
- Poziomy i warianty w schemacie (S-08, S-09).
- Kandydaci po nazwisku męża.
- Zamiana ręcznych relacji.
- Zapis i wznawianie decyzji.
- Tryb nieinteraktywny z dopasowaniami.
- Zmiany UI.

## Architecture / Approach

Tok odczyt → **`MatchResearchPersons`** (werdykty z kandydatami i porównaniem, bez zapisu) → komenda zbiera `IdentityDecisions` → **`PlanFirstImport`** (nowe, dopasowane, uzupełnienia, konflikty, relacje sprawdzone na wspólnym grafie, pominięcia) → komenda zbiera `ConflictResolutions` i zgodę → kopia na produkcji → **`ApplyFirstImport`** w jednej transakcji. `ApplyFirstImport` pod blokadą ponownie sprawdza bramkę i dopasowane osoby, a relacje i małżeństwa tworzy przez istniejące Actions.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Dopasowanie osób | `MatchResearchPersons`, `NameKey`, `YearSpan` z testami granicznymi | Reguła za luźna (sklejenie) albo za ścisła (duplikat). |
| 2. Plan z decyzjami | Uzupełnienia, konflikty, relacje na wspólnym grafie, pominięcia | Relacja sprzeczna z drzewem przepuszczona → cofnięty cały import. |
| 3. Zapis | `repo_id` i wartości w ręcznych rekordach, nowe rekordy, atomowo | Przypadkowa zmiana danych z aplikacji (miejscowość, źródła, zamieszkania). |
| 4. Komenda | Podgląd pewnych, wątpliwych i konfliktów w terminalu + zgoda | Czytelność przy dziesiątkach decyzji; testy pytań Prompts. |

**Prerequisites:** S-02 zarchiwizowane (`repo_id` w schemacie); kopia bazy dev do ręcznego przebiegu.
**Estimated effort:** ~4 sesje po godzinach, po jednej na fazę.

## Open Risks & Assumptions

- Prawdziwe repo nie ma jeszcze bloków. Regułę sprawdzi ręcznie tylko `test-pack` (7 bloków), a na pełnych danych dopiero przy konwersji repo.
- Kobieta wpisana pod nazwiskiem po mężu nie jest kandydatem. Bez innego kandydata wejdzie jako nowa, czyli możliwy duplikat do ręcznego scalenia.
- Osoba pominięta teraz nie wejdzie przy kolejnym imporcie, dopóki S-04 nie obsłuży nowych osób w drzewie z `repo_id`.
- Zmiany w aplikacji w trakcie sesji (minuty) nie są wykrywane poza istnieniem i `repo_id` dopasowanych osób. Przyjęte: jeden autor.

## Success Criteria (Summary)

- Import na drzewie z ręcznymi osobami nie tworzy duplikatu osoby pewnie dopasowanej, a każdą wątpliwą rozstrzyga autor.
- Dopasowana osoba zachowuje zdjęcie, notatkę, źródła, miejscowości i zamieszkania; ręczne relacje nie znikają.
- Cudze drzewo nietknięte, przerwanie lub „nie” niczego nie zapisuje, testy importu i pełny zestaw zielone.
