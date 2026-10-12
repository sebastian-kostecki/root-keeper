# Nazwisko rodowe i wiek osoby (S-13) — Plan Brief

> Full plan: `context/changes/maiden-name-and-age/plan.md`
> Research: `context/changes/maiden-name-and-age/research.md`

## What & Why

S-13 z roadmapy (FR-020, FR-024). Autor genealogii musi zapisać nazwisko rodowe kobiety i rozpoznawać ją po nim na drzewie i w wyszukiwarce. Musi też od razu widzieć wiek osoby: ile przeżyła albo ile ma, bez liczenia w pamięci z dat częściowych.

## Starting Point

Osoba ma tylko imię i nazwisko. Nazwa wyświetlana powstaje w jednym miejscu (`Person::displayName()`), a wyszukiwanie przeszukuje dwie kolumny. Wieku nie ma nigdzie. Żywot jest składany w PHP dla karty drzewa i w TS dla nagłówka podglądu, a zapis osoby to pełne zastąpienie wszystkich pól.

## Desired End State

Formularz ma pole „Nazwisko rodowe”. Osoba wyświetla się wszędzie jako „Anna Kowalska z d. Nowak” i jest znajdowana po obu nazwiskach. Nagłówek osoby i karta drzewa pokazują wiek w nawiasie po żywocie: „1850 – 1922 (72 lata)”, „ur. 1980 (45 lat)”, „02.01.1890 – 20.03.1890 (2 mies.)”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Gdzie składać „z d.” | W `displayName()` — wszędzie, także na kartach drzewa | Jedno źródło nazwy; picker na drzewie od razu szuka po obu nazwiskach | Research / Plan |
| Samo nazwisko rodowe | Błąd walidacji pod polem | PRD: jedno znane nazwisko idzie w pole główne | Plan |
| Rodowe = nazwisko | Zapisane, „z d.” ukryte (bez względu na wielkość liter) | Brak redundancji przy zachowaniu danych | Plan |
| Pole w formularzu | „Nazwisko rodowe” dla każdej płci; E2E na `exact: true` | Obejmuje też mężczyzn i osoby bez płci; zmiana selektora, nie asercji | Plan |
| Wiek przy datach niepełnych | Sama różnica z części znanych w obu datach, bez „ok.” | Wybór autora: prosty odbiór, „około” nie zmienia wyniku | Plan |
| Brzmienie | W nawiasie po żywocie, odmiana przez `pluralPhrase` | Krótkie; kontekst żywotu mówi „przeżyła” vs „ma” | Plan |
| Niemowlęta | Miesiące/dni przy pełnych datach, „poniżej roku” przy niepełnych | Oddaje to, co zapisują metryki zgonów niemowląt | Plan |
| Gdzie pokazać wiek | Nagłówek osoby + karta drzewa | Widać bez otwierania osoby; Rodzina i lista poza zakresem | Plan |
| Próg „ile ma” | Ukryj przy ≥ 110 lat (tylko bez zdarzenia zgonu) | Dosłownie PRD | Plan |
| „Dziś” | Data w Europe/Warsaw tylko dla wieku; aplikacja zostaje w UTC | Urodziny o polskiej północy bez ryzyka dla timestampów | Plan |
| Gdzie liczyć wiek | Serwer (`App\Support\Age`), tekst w TS (`formatAge`) | Karta drzewa ma żywot z PHP; odmiana w jednym miejscu | Plan |

## Scope

**In scope:**
- Kolumna `maiden_name`, walidacja, akcje, zasoby, payload, pole w formularzu i w widoku Przeglądu
- „z d.” w `displayName()`, wyszukiwanie po trzech kolumnach, inicjały bez nazwiska rodowego
- Wiek w nagłówku osoby i na karcie drzewa, testy Unit/Feature/Vitest, lokatory w 3 specach E2E

**Out of scope:**
- Wiele nazwisk po mężach; wiek w Rodzinie, liście, tabeli i pickerze
- Oznaczanie niepewności wieku („ok.”, zakresy); walidacja „zgon przed urodzeniem”
- Zmiana strefy aplikacji, placeholdera, sortowania; nowy spec E2E; powłoka panelu (S-06)

## Architecture / Approach

Nazwisko rodowe przechodzi całą ścieżkę pełnego zastąpienia (request → akcja → `PersonFormResource` → `person-payload.ts` → `PERSONAL_KEYS`), a prezentacja zmienia się w jednej metodzie modelu. Wiek liczy czysta klasa `Age::from(birth, death, isDeceased, today)`. `Person::age()` podaje jej „dziś” w Europe/Warsaw, `PersonResource` i `SheetPersonResource` zwracają `age: {unit, value}`, a `formatAge()` w TS składa tekst do nawiasu po żywocie.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Nazwisko rodowe | Pole, „z d.” wszędzie, wyszukiwanie po obu nazwiskach | Pominięcie pola w payloadzie czyści je przy zapisie innej sekcji |
| 2. Wiek osoby | Wiek w nagłówku i na karcie drzewa | Brzegi dat częściowych i „dziś” — pokryte testem Unit i `travelTo` |

**Prerequisites:** Sail uruchomiony; brak zależności od innych slice'ów (S-13 jest równoległy).
**Estimated effort:** ~1–2 sesje w 2 fazach.

## Open Risks & Assumptions

- Dłuższe nazwy z „z d.” mogą zawijać się na wąskich kartach drzewa — sprawdzane ręcznie w obu fazach.
- Przodek bez wpisanego zgonu, ur. po 1916, nadal „ma” 100+ lat — świadoma konsekwencja progu 110 z PRD.
- Brzeg „0 dni” → „poniżej dnia” wybrany w planowaniu bez osobnego pytania.

## Success Criteria (Summary)

- Autor wpisuje nazwisko rodowe i widzi „z d. …” na drzewie, w listach i w nagłówku, a wyszukiwarka i picker znajdują osobę po nim.
- Autor widzi wiek zmarłych i żyjących (poniżej 110 lat) w nagłówku i na karcie drzewa, z poprawną polską odmianą.
- Pest, Vitest, typy, lint i trzy specy E2E slotów drzewa są zielone.
