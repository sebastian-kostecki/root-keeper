# Karta osoby na drzewie: nazwa rodowa, łamanie po słowach, szersze kolumny — Plan Brief

> Full plan: `context/changes/tree-card-name-one-line/plan.md`
> Research: `context/changes/tree-card-name-one-line/research.md`

## What & Why

Karty na `/tree` są za duże, zwłaszcza na dużych ekranach. Drzewo jest powiększane (do 1,384 przy 2560), zamiast rozkładać się szerzej. Karta tymczasem łamie imię i nazwisko w środku słowa na 2–4 linie, a długie nazwisko wchodzi pod oko. Użytkownik chce na karcie widzieć imię i nazwisko rodowe w jednej linii, gdy ma ono rozsądną długość, bez nazwiska po ślubie.

## Starting Point

Siatka ma stałą kolumnę 12rem, zdublowaną w `family-sheet.tsx` i `ancestor-chart.tsx`, a `ZoomCanvas` dopasowuje ją skalą do 1,5. Na pierwszą linię nazwy karty dziadka zostaje 68 px. Klasy `[overflow-wrap:anywhere] break-words` się gryzą. Karta pokazuje `display_name` z „z d. …”.

## Desired End State

Karta pokazuje imię i nazwisko rodowe. Nazwa łamie się tylko między słowami, ma maks. 2 linie, potem wielokropek, a nazwa karty jest w podpowiedzi. Karty jednokolumnowe mają pasek zdjęcia 3rem. Przy 2560 kolumna ma ok. 17rem przy skali ok. 1, więc nazwy do ok. 18 znaków mieszczą się w jednej linii przy tekście 16 px. Przy 1366 i 1920 geometria się nie zmienia.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Nazwa na karcie | `card_name` = imię + (`maiden_name` ?: `last_name`), osobne pole | `display_name` z „z d.” czytają nagłówki, menu, etykiety i E2E, więc zostaje bez zmian. | Research |
| Duży ekran | Nadmiar szerokości w kolumnę, nie w skalę | Karty nie są powiększane, a nazwy dostają miejsce. | Research |
| Nazwa za długa | Łamanie po słowach, maks. 2 linie, wielokropek | Nigdy w środku słowa; zastępuje kryterium z `person-photo-on-card`. | Research |
| Miejsce na karcie jednokolumnowej | Pasek 4,5 → 3rem; oko zostaje w 1. linii; 16 px | Odrzucone: oko poza linią nazwy, 14 px, stała szersza kolumna. | Research |
| Kolumna przy zmianie obszaru | Część dopasowania: liczy się przy starcie, zmianie okna, panelu i „Dopasuj”; po ręcznym ruchu zostaje | Ta sama reguła co dla skali; zamknięcie panelu wraca do tego samego widoku. | Plan |
| Powyżej ok. 2700 px | Kolumna maks. 18rem, dalej skala do 1,5 (bez zmian w `fit-view`) | Ekran dalej wypełniony; maks. < 20rem trzyma wąski pasek na kartach dziadków. | Plan |
| Skala docelowa | 1 (przy 1920 kolumna 12rem) | Dosłownie decyzja 1; 1366 i 1920 bez zmian. Koszt: „Kowalczykows…” przy 1920. | Plan |
| Pasek zależny od szerokości | `@container` na karcie, `w-12 @xs:w-18` | Bez przekazywania propsów; reaguje na faktyczną szerokość karty. | Plan |
| Rezerwa na oko | `size-7` → `size-5` (rzeczywiste zachodzenie oka ok. 19 px) | „Aleksandra”, „Franciszek” mieszczą się obok oka zamiast spadać pod nie. | Plan |
| Podpowiedź | Natywny `title` | Bez nowego komponentu; pełna nazwa i tak jest w nazwie dostępnej. | Plan |

## Scope

**In scope:**

- `card_name` w zasobie, typach i testach.
- Układ nazwy, rezerwa na oko i pasek w `CardBody`.
- Wspólna geometria i `fitColumnRem` w `lib/fit-view.ts`.
- Kolumna w `ZoomCanvas`, podłączona do `family-sheet` i `ancestor-chart`.
- Spec E2E, zrzuty `after-*` i aktualizacja DS `PersonCard`.

**Out of scope:**

- `display_name` i `aria-label` oka.
- D1 (1366 zakotwiczone przy 0,75).
- Progi `TREE_FIT_*` i `ANCESTORS_*`.
- Czcionka nazwy.
- Pasek na kartach ≥ 20rem.
- Skala docelowa < 1.
- Kolumna zależna od wysokości obszaru.

## Architecture / Approach

Czysta funkcja `fitColumnRem({areaWidth, rootFontSize, columns, paddingRem})` w `lib/fit-view.ts` liczy kolumnę, przy której treść ma skalę 1, w granicach 12–18rem. `ZoomCanvas` dostaje `columns`/`paddingRem`, liczy kolumnę z `wrapper.clientWidth` w tym samym przebiegu rAF co dopasowanie i podaje ją treści przez render prop. Zmiana kolumny zmienia szerokość treści, `ResizeObserver` wywołuje kolejny przebieg i dopiero wtedy następuje dopasowanie i pokazanie treści. Kolumna zależy tylko od obszaru, więc pętla się nie wzbudza.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Nazwa na karcie | `card_name` + Pest + typy; karta i inicjały z nazwiskiem rodowym | Pominięty literał `SheetPerson` w teście łamie `tsc` |
| 2. Układ nazwy i paska | `line-clamp-2` + wielokropek + `title`, rezerwa `size-5`, pasek `@xs` | Pierwsze słowo szersze niż ok. 98 px dalej spada pod oko |
| 3. Kolumna z obszaru | Wspólna geometria, `fitColumnRem` + Vitest, kolumna w `ZoomCanvas` | Mignięcie wąskiej siatki lub linie rozjechane z kartami przy 17rem |
| 4. E2E, zrzuty, DS | `tree-card-name.spec.ts`, `after-*` (7 zrzutów), DS `PersonCard` | Pomiar wysokości linii w E2E zależny od czcionki |

**Prerequisites:** Sail uruchomiony; serwer E2E (`scripts/e2e-server.sh`, port 8100); dostęp do artefaktu DS.
**Estimated effort:** ok. 2–3 sesje w 4 fazach (faza 3 największa).

## Open Risks & Assumptions

- Liczba znaków na linię to szacunek (ok. 9,3 px/znak przy 16 px). Przy 1366 i 1920 nazwiska dłuższe niż ok. 12–13 znaków mają wielokropek; użytkownik to zaakceptował.
- Nie mierzono przy zwiniętym menu bocznym; kolumna dostanie wtedy ok. 25 px więcej przy 2560.
- Otwarcie panelu przy 2560 bez ręcznego ruchu zmienia szerokość kart, więc nazwy mogą się złamać inaczej niż przed otwarciem.
- Zachowanie `float` w `-webkit-line-clamp` sprawdzono tylko w Chromium.

## Success Criteria (Summary)

- Przy 2560 imię i nazwisko rodowe do ok. 18 znaków jest w jednej linii, a karty nie są powiększane.
- Na każdym ekranie nazwa nie łamie się w środku słowa, ma maks. 2 linie i nie wchodzi pod oko.
- Przy 1366 i 1920 drzewo i przodkowie zachowują się jak dziś (poza paskiem 3rem i łamaniem po słowach).
