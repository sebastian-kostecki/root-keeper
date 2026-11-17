# Daty nieostre: zakres, przed, po — Plan Brief

> Full plan: `context/changes/fuzzy-event-dates/plan.md`

## What & Why

Daty w repo badawczym są nieostre: „ok. 1799”, „przed 1809”, „po 1801”, „między 1805 a 1811”. Aplikacja zna dziś tylko datę dokładną i „ok.”. S-07 dodaje rodzaj daty wydarzenia (dokładna, ok., przed, po, zakres lat), który autor wpisuje i widzi w formularzu, na osi czasu, w panelu osoby i na kartach. Idzie przed importem S-02, żeby zakres był widoczny i edytowalny od pierwszego importu.

## Starting Point

- `events` ma rok, miesiąc i dzień oraz flagę `date_is_approximate`.
- Data wpisywana jest w jedno pole „Data”, którego parser rozpoznaje „ok.” z tekstu i odrzuca „przed”, „po” oraz zakresy.
- Formatowanie jest w PHP (`PartialDate`) i w TS (`formatPartialDate`).
- Wiek i sortowanie ignorują „ok.”.
- Enum `DateQualifier` istnieje od S-01, ale nic go nie zapisuje.

## Desired End State

Autor wpisuje w polu „Data”: „przed 1809”, „po 12.05.1801”, „1805–1811” albo „między 1805 a 1811”. Po zapisie widzi:
- na kartach i w latach życia: „1805–1811 – 1870”, „przed 1809 – 1870”;
- w panelu osoby: „między 1805 a 1811”;
- na osi czasu: „przed”, „po” albo „ok.” nad rokiem, a zakres w dwóch liniach.

Przy dacie nieostrej wiek się nie pokazuje. Istniejące daty dokładne i „ok.” wyglądają jak dotąd. S-02 zapisuje import przez ten sam kontrakt co formularze.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Model danych | Nowe `date_qualifier` i `date_until_year`, kopia z flagi, flaga zostaje i jest wyliczana z rodzaju | Reguła „copy first, drop later” z CLAUDE.md; żadna wartość nie ginie | CLAUDE.md |
| Wpisywanie | Tekst w tym samym polu „Data”, parser zna przed, po i zakres | Jedno pole jak dotąd, ta sama gramatyka co blok repo | Plan |
| Zakres | Tylko lata, od < do; „przed” i „po” z dowolną precyzją | Zgodnie z gramatyką bloku i `ResearchDate` z S-01 | Kontrakt bloku |
| Wygląd zakresu | Krótko „1805–1811”, długo „między 1805 a 1811” | Mieści się na karcie, jak zapis zamieszkań „1900–1910” | Plan |
| Wiek | Brak wieku, gdy data urodzenia albo końca jest przed, po lub zakresem; „ok.” jak dziś | Nie pokazujemy liczby, której nie znamy | Plan |
| Oś czasu | Słowo rodzaju nad rokiem, zakres w dwóch liniach, szczegóły bez powtórzenia słowa | Rozszerza dzisiejszy wzór i usuwa podwójne „ok.” | Plan |
| Kontrakt API | `qualifier` i `until_year` zamiast `is_approximate`; brak klucza = `exact` | Pełna podmiana działa jak dotąd; S-02 importuje przez `MapsEventInput` | Plan (S-02) |
| Sortowanie | Po zapisanym roku, miesiącu i dniu; zakres po roku początku | Tak jak dziś ignorowane „ok.”, bez nowych reguł | Plan |
| Karta przodka | Ten sam ciąg lat życia co karta drzewa | Oba biorą `lifespan` z serwera; CLAUDE.md wymaga decyzji w planie | Plan |

## Scope

**In scope:**
- migracja z testem i model;
- `PartialDate`, walidacja, `MapsEventInput`, zasoby, wiek;
- parser, formatter, pole, payload, oś czasu, sortowanie `/persons`;
- testy Pest i Vitest, jedna specyfikacja E2E, zrzuty ekranu.

**Out of scope:**
- usunięcie flagi `date_is_approximate` (osobna zmiana);
- import dat (S-02);
- zakres z miesiącem lub otwarty, styl juliański, pory roku;
- wiek jako przedział, sortowanie zależne od rodzaju;
- rodzaj daty dla zamieszkań, źródeł i pochowania;
- osobna kontrolka wyboru rodzaju.

## Architecture / Approach

Rodzaj daty jest źródłem prawdy w kolumnie `date_qualifier`. Model `Event` wylicza z niego flagę przy zapisie. Po stronie serwera `PartialDate` niesie rodzaj i rok końca, a czytają go formatowanie (`format`, `formatYear`, `formatWithoutQualifier`), wiek (`isFuzzy`) i zasoby (`date_parts`). Po stronie klienta parser zamienia tekst na części, a ukryte pola `qualifier` i `until_year` idą natywnie (panel dodawania) albo przez `person-payload.ts` (sekcje i małżeństwo). Krótka forma TS jest lustrem PHP.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Schemat i model | Kolumny, kopia z flagi, test migracji, flaga wyliczana z rodzaju, fabryka i helpery | Niski — tylko nowe kolumny i jedna aktualizacja |
| 2. Serwer | `PartialDate` z rodzajem, walidacja, mapowanie, zasoby, wiek; testy Pest | Pominięcie miejsca, które pisze lub czyta flagę wprost |
| 3. Frontend | Parser i formatter, pole, payload, oś czasu, sortowanie; Vitest, zrzuty | „1805-1811” złapane jako miesiąc-rok; pełna podmiana gubi rodzaj |
| 4. E2E | Specyfikacja: zakres i „przed” przeżywają zapisy sekcji | Niski — jedna specyfikacja przez `/10x-e2e` |

**Prerequisites:** brak (S-07 nie zależy od innych wycinków). Fazy 1–3 wdrażamy na produkcję razem.
**Estimated effort:** ~3 sesje po godzinach w 4 fazach.

## Open Risks & Assumptions

- Między fazą 2 a 3 klient wysyła `is_approximate`, którego serwer już nie zna. Na produkcję idą razem; lokalnie „ok.” zapisane w tym oknie stanie się `exact`.
- `down()` migracji gubi „przed”, „po” i zakres wpisane po wdrożeniu; „ok.” przetrwa dzięki fladze.
- S-02 zakłada nazwy `qualifier` / `until_year` w `MapsEventInput` i wartości `DateQualifier`. Zmiana nazw w implementacji wymaga poprawki planu S-02 (`plan.md:130`).

## Success Criteria (Summary)

- Autor zapisuje i widzi datę jako dokładną, „ok.”, przed, po albo zakres lat w urodzeniu, zgonie i ślubie, a po ponownym otwarciu i edycji innej sekcji data jest ta sama.
- Istniejące daty po migracji wyglądają identycznie; test migracji dowodzi, że żadna wartość nie zginęła.
- Testy Pest, Vitest i E2E, typy, lint i Larastan przechodzą.
