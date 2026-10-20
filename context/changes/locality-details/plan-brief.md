# Szczegóły miejscowości — Plan Brief

> Full plan: `context/changes/locality-details/plan.md`
> Research: `context/changes/places-review/research.md`

## What & Why

Miejscowość ma dziś tylko nazwę i opis, więc rodzaj, położenie, dawną przynależność i to, że wsi już nie ma, autor dopisuje w opisie, gdzie nie da się tego zobaczyć na liście ani w podpowiedziach. Autor: „czy dziś istnieje — to ważne, bo są nieistniejące obecnie”. Zmiana dodaje te informacje jako pola i oznacza nieistniejące miejscowości wszędzie, gdzie się je wybiera.

## Starting Point

`localities(name, description)` z unikalną nazwą w drzewie; panel z polami Nazwa/Opis; podpowiedzi dostają `{id, name}` i wspólny `SuggestField` pokazuje tylko nazwę. PRD Non-Goal (`prd.md:238`) odsyłał takie informacje do opisu.

## Desired End State

Panel miejscowości ma Rodzaj, Współrzędne (wklejane „50.0614, 19.9366” + „Pokaż na mapie” w OpenStreetMap), Przynależność historyczną i „Nie istnieje dziś” z opisem zaniku. Nieistniejąca miejscowość ma plakietkę na liście `/places` i na górze panelu oraz dopisek „nie istnieje” w każdej podpowiedzi wyboru miejscowości. Lista ma kolumnę „Rodzaj”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres pól | rodzaj, współrzędne, przynależność, „nie istnieje dziś”; bez nazw alternatywnych | Najtańsze i najbardziej użyteczne szczegóły; nazwy alternatywne zmieniałyby wyszukiwanie i unikalność | Research |
| Osobna zmiana | pierwsza z trzech (potem parafia/urząd, cmentarze) | Najmniejsza i niezależna | Research |
| Rodzaje | miasto, wieś, kolonia, osada, przysiółek, folwark / majątek, inne; pole opcjonalne | Ustawa z 2003 + rodzaje historyczne, bez rozdrabniania | Plan |
| Współrzędne | jedno pole do wklejenia, parsowane na serwerze do dwóch kolumn | Jeden ruch kopiuj-wklej z mapy; DMS nieobsługiwany | Plan |
| Przynależność historyczna | jedno pole tekstowe | Mieści każdy okres; strukturalne pola i tak nie oddadzą zmian w czasie | Plan |
| Znacznik „nie istnieje” | lista `/places`, podpowiedzi, góra panelu miejscowości | Widoczny tam, gdzie miejscowość się przegląda i wybiera; panele osoby/źródła bez zmian | Plan |
| Lista `/places` | kolumna „Rodzaj”, szukanie dalej po nazwie | Minimalna zmiana, zgodna z filtrami na `/sources` | Plan |
| Mapa | link do OpenStreetMap, bez mapy w panelu | Zero zależności; NFR o podmiotach trzecich już nie obowiązuje | Plan |
| Scalanie | wartości celu wygrywają, puste uzupełniane z duplikatu; różne przynależności łączone jak opisy; „nie istnieje” = OR | Rozszerza dzisiejsze łączenie opisów; tekst wolny nie ginie, a pola jednowartościowe (rodzaj, współrzędne, opis zaniku) zostają z celu | Plan review F2 |
| Testy | Pest + Vitest, bez E2E | Żadne ryzyko nie wymaga przeglądarki (test-plan „koszt × sygnał”) | Plan |

## Scope

**In scope:**
- 6 kolumn na `localities`, enum `LocalityKind`, `Coordinates::parse()`, walidacja, akcje, zasób, flaga `is_defunct` w opcjach podpowiedzi
- reguła scalania nowych pól
- pola panelu, status „nie istnieje”, link OSM, kolumna Rodzaj i plakietka, `hintOf` w `SuggestField`
- przeredagowanie Non-Goal i dopisek przy FR-007 w PRD

**Out of scope:**
- nazwy alternatywne, mapa w panelu, DMS, strukturalna przynależność
- szukanie po przynależności/rodzaju, filtr „tylko nieistniejące”
- znacznik przy faktach osoby, osi czasu, panelu źródła i kartach drzewa
- szczegóły przy dopisaniu w locie, zmiana unikalności, cmentarze, parafia/urząd, E2E

## Architecture / Approach

Backend przejmuje cały kontrakt: kolumny nullable (bez przenoszenia danych), parsowanie współrzędnych w `App\Support\Coordinates`, akcje `Store/UpdateLocality` przyjmują tablicę szczegółów, `LocalityResource` wystawia pola + `kind_label`, opcje podpowiedzi niosą `is_defunct`. Frontend tylko formatuje: helpery w `lib/localities-index.ts` (`localityHint`, `formatCoordinates`, `openStreetMapUrl`), `SuggestField` dostaje opcjonalne `hintOf` niewpływające na dopasowanie.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Model i zapis (backend) | Kolumny, walidacja, zapis, zasób, opcje z flagą, scalanie, PRD, testy Pest | Zmiana sygnatur akcji i kształtu opcji psuje istniejące asercje (`PersonsTest.php:1677`) |
| 2. Panel, lista i podpowiedzi (frontend) | Pola panelu, status, link mapy, kolumna i plakietka, dopisek w podpowiedziach, Vitest, zrzuty | Plakietka w przyklejonej kolumnie na wąskim ekranie; tokeny w `view-tokens` |

**Prerequisites:** Sail uruchomiony; brak zależności od innych zmian.
**Estimated effort:** ~2 sesje (po jednej na fazę).

## Open Risks & Assumptions

- Zakładamy, że współrzędne autor kopiuje z map w zapisie dziesiętnym z kropką; inne formaty dostają czytelny komunikat zamiast cichego błędu.
- Wycofanie migracji usuwa wpisane szczegóły (akceptowalne przed wdrożeniem; produkcja robi kopię bazy przed `migrate`).

## Success Criteria (Summary)

- Autor zapisuje i odczytuje rodzaj, współrzędne, przynależność i „nie istnieje dziś” w panelu, a „Pokaż na mapie” trafia w punkt.
- Nieistniejącą miejscowość widać na liście, w panelu i w każdej podpowiedzi wyboru miejscowości.
- Pest, Vitest, lint, typy i build zielone; zrzuty w `screenshots/`.
