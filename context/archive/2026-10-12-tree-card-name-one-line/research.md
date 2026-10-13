---
date: 2026-10-12T20:06:37+02:00
researcher: Claude (Opus 5.5)
git_commit: 12ba81b
branch: develop
repository: root-keeper
topic: "Karty na drzewie są za duże (zwłaszcza na dużych rozdzielczościach), a imię i nazwisko rodowe rozsądnej długości ma się mieścić w jednej linii"
tags: [research, ui, tree, sheet-person-card, ancestor-card, fit-view, zoom-canvas]
status: complete
last_updated: 2026-10-12
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika (szersze kolumny, łamanie po słowach maks. 2 linie, węższy pasek) i ich rachunek"
---

# Research: karta osoby na drzewie — jedna linia imienia i nazwiska na dużych ekranach

**Date**: 2026-10-12T20:06:37+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 12ba81b (pomiary na drzewie roboczym bez zmian w kodzie aplikacji)
**Branch**: develop
**Repository**: root-keeper

## Research Question

„Karty na tree-page chyba są za duże, bo imię i nazwisko (gdy jest długie) powinno się mieścić w jednej linii (w dość rozsądnej długości). Chcę widzieć imię i nazwisko rodowe — bez nazwiska po małżeństwie. Chodzi mi zwłaszcza o wielkie rozdzielczości.”

## Summary

- **Karty wyglądają na za duże na dużych ekranach, bo drzewo jest powiększane, a nie rozkładane szerzej.** Siatka ma stałe kolumny 12rem (`family-sheet.tsx:26`, `ancestor-chart.tsx:10`). Start dopasowuje ją skalą do `TREE_FIT_MAX = 1.5` (`lib/fit-view.ts:5`, `zoom-canvas.tsx:137-150`). Zmierzone przy otwartym menu bocznym:

  | Ekran | Skala `/tree` | Karta dziadka na ekranie | Linie imienia i nazwiska |
  | --- | --- | --- | --- |
  | 2560×1440 | 1,384 | 266×213 px | 2–4 |
  | 1920×1080 | 0,993 | 191×153 px | 2–4 |
  | 1366×768 | 0,75 (próg, widok zakotwiczony) | 144×116 px | 2–4 |

  Na 2560 nazwa ma ok. 22 px wysokości liter (16 px × 1,384), a mimo to łamie się, bo układ wewnątrz karty się nie zmienia.
- **Na pierwszą linię imienia na karcie dziadka zostaje 68 px.** Rachunek: 192 px − pasek `w-18` 72 px (`sheet-person-card.tsx:44`) − padding 24 px (`:88`) − rezerwa na oko 28 px (`:91-95`). Przykłady łamania: „Stanisła/w”, „Genowe/fa”, „Władysł/aw” (zrzuty `screenshots/before-tree-{1920,2560}.png`).
- **Błąd klas: długie nazwisko wychodzi pod oko i jest ucinane.** `break-words` (`overflow-wrap: break-word`) stoi w zbudowanym CSS po `[overflow-wrap:anywhere]` i przy tej samej specyficzności wygrywa. Obie klasy są w `sheet-person-card.tsx:89`. Długie słowo poszerza wtedy kolumnę zamiast się łamać: pole nazwy „Aleksandra Brzęczyszczykiewicz” ma 154 px przy kolumnie tekstu 96 px, a tekst „Brzęczyszczyk…” i „Konstantyno…” jest przycięty krawędzią karty (`overflow-hidden`, `card-styles.ts:15`).
- **Karta rodzica i dziecka (2 kolumny, 396 px) mieści 26 znaków w jednej linii przy 16 px.** „Krystyna Zawadzka-Pietrzak” zajmuje 243 px, ok. 9,3 px na znak. Problem dotyczy kart jednokolumnowych:
  - 8 dziadków w `/tree`;
  - dzieci, gdy jest ich więcej niż 4 (`family-sheet.tsx:45`);
  - wszystkich kart w `/tree/{id}/ancestors`.
- **„Rozsądna długość”:** w bazie dev (121 osób) imię i nazwisko rodowe ma p50 = 15, p75 = 18, p90 = 26, p95 = 32 i max = 62 znaki. Imię i nazwisko rodowe (`maiden_name ?: last_name`) prawie nie zmienia rozkładu, bo `maiden_name` ma 1 osoba.
- **Gdzie zmienić nazwę na karcie:** pochodzi z `app/Http/Resources/SheetPersonResource.php:44` (`displayName()` z „z d. …”). `display_name` czytają też nagłówki, okruszki, menu związków, etykiety „dodaj małżonka” i `MarryCoupleDialog`. Bezpieczna droga to nowe pole (np. `card_name`) tylko dla `CardBody`, a nie zmiana `display_name`.
- **Sedno decyzji:**
  - Przy 1920 i mniejszych ekranach 8 kart dziadków obok siebie nie pomieści 26 znaków w jednej linii przy czytelnej wielkości tekstu (rachunek w „Wariantach”).
  - „Jedna linia” jest osiągalna na dużych ekranach, jeśli nadmiar szerokości pójdzie w szersze kolumny zamiast w skalę powyżej 1, a karta odda pasek i rezerwę na oko.
  - Na mniejszych ekranach trzeba wybrać między wielokropkiem a łamaniem po słowach.

## Charges

Zarzuty z audytu `/10x-ui`. Kategorie: brakujący token, brakujący współdzielony komponent, przypadkowa architektura. Kolumna „Faza” będzie uzupełniona przez `/10x-plan`.

| # | Kategoria | Dowód (plik:linia) | Wpływ na użytkownika |
| --- | --- | --- | --- |
| C1 | przypadkowa architektura | `lib/fit-view.ts:5` (`TREE_FIT_MAX = 1.5`), `components/zoom-canvas.tsx:137-150`, stała kolumna `family-sheet.tsx:26` i `ancestor-chart.tsx:10` | Na 2560×1440 drzewo jest powiększone 1,384× zamiast rozłożone szerzej. Karty dziadków mają 266×213 px, a imię i nazwisko i tak zajmuje 3–4 linie, więc ekran pełen jest wielkich kart z poszatkowanymi nazwiskami. |
| C2 | brakujący token (konflikt klas) | `components/tree/sheet-person-card.tsx:89`: `[overflow-wrap:anywhere] break-words`, w CSS wygrywa `break-words` | Długie nazwisko (np. „Brzęczyszczykiewicz”) wychodzi pod przycisk oka i jest ucięte krawędzią karty, więc nie da się go przeczytać. |
| C3 | przypadkowa architektura (geometria karty) | `sheet-person-card.tsx:44` (pasek `w-18`), `:88` (`px-3`), `:91-95` (rezerwa `float-right size-7`), `:161` (oko `absolute top-1 right-1`) | Na karcie 12rem pierwsza linia ma 68 px (ok. 7 znaków), więc imiona łamią się w środku słowa („Stanisła/w”) już przy 20-znakowych nazwach. |
| C4 | brakujący token | `COLUMN_REM = 12` zdublowane w `family-sheet.tsx:26` i `ancestor-chart.tsx:10`, liczone w komentarzu `lib/fit-view.ts:7-12`; DS ma `photo-strip-width` i `person-card-min-height`, ale nie ma szerokości kolumny | Zmiana szerokości karty w jednym widoku nie przechodzi do drugiego, więc `/tree` i `/ancestors` mogą się rozjechać. |
| C5 | przypadkowa architektura (treść) | `app/Http/Resources/SheetPersonResource.php:44` (`displayName()`), `CardBody` `sheet-person-card.tsx:102`, inicjały `:63` | Karta pokazuje nazwisko po ślubie i „z d. …” zamiast imienia i nazwiska rodowego, co wydłuża nazwę i nie jest tym, czego użytkownik szuka w drzewie. |
| D1 | **deferred** (poza zakresem dużych ekranów) | pomiar `tree@1366`: skala 0,75, treść 1636 px przy obszarze 1070 px; kryterium `context/archive/2026-10-05-tree-fits-screen/plan.md:259` (cała rodzina przy ≥ 0,75) | Na 1366 px całe drzewo z dziadkami już się nie mieści i widok startuje zakotwiczony. To regres z poszerzenia do 12rem (`person-photo-on-card`), nie z tej zmiany. Plan nie może tego pogorszyć; naprawa to osobna zmiana. |

## Detailed Findings

### Pomiar (Playwright, baza `e2e`, menu boczne otwarte)

Rodzina testowa:
- osoba centralna i małżonka (po 25 znaków);
- 4 rodziców (20–26 znaków);
- 8 dziadków (9–30 znaków);
- 2 dzieci.

Sondę uruchomiono jako tymczasowy spec i usunięto po pomiarze. Zrzuty są w `screenshots/before-{tree,ancestors}-{1366,1920,2560}.png`.

| Widok @ szerokość | Obszar (px) | Treść (px) | Skala | Karta dziadka: układ → ekran | Linie (dziadkowie) |
| --- | --- | --- | --- | --- | --- |
| tree@1366 | 1070×626 | 1636×624 | 0,75 | 192×154 → 144×116 | 2–4 |
| tree@1920 | 1624×938 | 1636×624 | 0,993 | 192×154 → 191×153 | 2–4 |
| tree@2560 | 2264×1298 | 1636×624 | 1,384 | 192×154 → 266×213 | 2–4 |
| ancestors@1366 | 1070×622 | 1636×548 | 0,654 | 192×114 → 126×75 | 1–2 (rodzice 2–3) |
| ancestors@2560 | 2264×1294 | 1636×548 | 1,384 | 192×114 → 266×158 | 1–2 (rodzice 2–3) |

- Na 2560 skalę ogranicza szerokość (2264/1636 = 1,384), nie sufit 1,5. Wysokość pozwalałaby na 2,08. Nad drzewem zostaje wtedy pusty pas (zrzut `before-tree-2560.png`).
- Karta dziadka (154 px) jest o 51% wyższa od karty rodzica (102 px) w tym samym drzewie. Różnica pochodzi z łamania nazwy.
- Na karcie przodka nie ma oka ani rezerwy (`ancestor-card.tsx:29-48`), więc pierwsza linia ma 96 px, a nie 68. Przy 2560 łamanie jest tam mniejsze, ale nadal 2–3 linie dla nazw ≥ 20 znaków.

### Geometria karty (stan obecny)

- `personCardSurface` to `flex … min-h-24 overflow-hidden` (`card-styles.ts:14-15`).
- Pasek zdjęcia `w-18` = 4,5rem (`sheet-person-card.tsx:44`), co odpowiada tokenowi DS `photo-strip-width`.
- Kolumna danych `px-3 py-3.5`, nazwa `font-display text-base leading-tight font-semibold` (`:88-89`).
- Przycisk oka `absolute top-1 right-1 size-7` (`:161`). Tylko pierwsza linia nazwy oddaje mu miejsce przez `float-right size-7` (`:91-95`). Tak wybrano w `person-photo-on-card/plan.md:303-304`, żeby daty miały ≥ 6rem.
- Szerokości kart w `/tree`:
  - dziadkowie: 1 kolumna;
  - rodzice i para: 2 kolumny + odstęp (`COUPLE_CARD_REM`, `family-sheet.tsx:33`);
  - dzieci: 2 kolumny do 4 dzieci, 1 kolumna od 5 (`WIDE_CHILDREN_MAX = 4`, `:45`).

### Warianty — rachunek szerokości dla 26 znaków (p90) w jednej linii

Założenia: ok. 9,3 px na znak przy 16 px (zmierzone: 243 px dla 26 znaków); przy 14 px proporcjonalnie ok. 8,2 px, czyli 213 px (szacunek, nie pomiar). Szerokość obszaru drzewa jest zmierzona przy otwartym menu. Siatka ma 8 kolumn: 8·C + 7·0,75rem + 1rem paddingu.

| Wariant karty | Potrzebna kolumna C | Skala całego drzewa: 1366 / 1920 / 2560 | Tekst na ekranie przy 2560 |
| --- | --- | --- | --- |
| Dziś (16 px, pasek 72, oko w linii) | ≈ 23rem (367 px) | 0,35 / 0,53 / 0,74 | ≈ 11,9 px |
| 14 px, pasek 72, oko w linii | ≈ 21rem (337 px) | 0,38 / 0,58 / 0,81 | ≈ 11,4 px |
| 14 px, pasek 3rem, oko poza linią nazwy | ≈ 18rem (285 px) | 0,45 / 0,68 / 0,94 | ≈ 13,2 px |
| Responsywna kolumna: C = szerokość obszaru przy skali 1, w granicach 12–18rem | 12 / 12 / ≈ 17rem | 0,75 (zakotw.) / 0,99 / ≈ 1,0 | 14–16 px |

Wnioski:
- Przy 1920 obszar ma 1624 px, czyli ok. 192 px na kartę przy skali 1. Wiersz 8 dziadków nie pomieści 26 znaków w jednej linii przy żadnym rozsądnym rozmiarze tekstu: przy 0,68 × 14 px wychodzi 9,5 px, poniżej progu 0,75. Jedna linia przy 1920 wymaga obcinania (wielokropek) albo zgody na 2 linie.
- Przy 2560 responsywna kolumna ok. 17rem (272 px) przy skali 1 daje na nazwę:
  - 200 px po oddaniu paska 3rem i przeniesieniu oka, czyli ok. 24 znaki przy 14 px albo ok. 21 przy 16 px;
  - 148 px przy obecnym pasku i oku, czyli ok. 15 znaków przy 16 px.
- Z menu bocznym zwiniętym obszar jest szerszy o ok. 200 px, czyli ok. 25 px więcej na kolumnę. Tego wariantu nie mierzono.

### Nazwa na karcie: imię i nazwisko rodowe

- Ładunek karty buduje wyłącznie `SheetPersonResource` (`SheetPersonResource.php:31-51`, `display_name` w `:44`). Wołają go `BuildFamilySheet::render()` (`app/Actions/Trees/BuildFamilySheet.php:527-533`) i `BuildAncestorChart::render()` (`app/Actions/Trees/BuildAncestorChart.php:143-149`). Typ phpstan `SheetPerson` jest w `BuildFamilySheet.php:19`, typ TS w `resources/js/types/tree.ts:3-11`.
- `display_name` czytają też miejsca, które mają zostać z pełną nazwą:
  - nagłówek, okruszki i `<Head>` w `pages/tree/show.tsx:47,82,86` oraz `pages/tree/ancestors.tsx:26,52,56`;
  - menu związków `union-node.tsx:138`;
  - `add-person-config.ts:110,275`;
  - `marry-couple-dialog.tsx:16,61,69`.
- Test E2E `tests/e2e/person-panel-unsaved-guard.spec.ts:58` oczekuje „… z d. Kowalska” w h1 drzewa. Zmiana znaczenia `display_name` by go zepsuła.
- Proponowany kształt: osobne pole (np. `card_name` = `first_name ?? 'NN'` + `maiden_name ?: last_name ?? 'NN'`) w zasobie, w typie phpstan i w typie TS. Użyć go w `CardBody` (`sheet-person-card.tsx:102`) i w inicjałach (`:63`).
  - `hooks/use-initials.tsx:11` już odcina „z d. …”, więc dziś inicjały biorą nazwisko po ślubie.
  - Rozbieranie `display_name` na froncie odrzucono: zawodzi przy wielu imionach i gdy `maiden_name` równa się `last_name` bez względu na wielkość liter (`Person.php:152-154`).
- Nazwa dostępna:
  - oko ma `aria-label="Podgląd: ${display_name}"` (`:166`);
  - link karty przodka nie ma `aria-label`, więc jego nazwa to treść karty (`ancestor-card.tsx:29-48`).
  - Selektory E2E (`Podgląd: <imię nazwisko>`, `mężczyzna <imię nazwisko>`) używają osób bez `maiden_name`, więc nowe pole ich nie zmienia.
- Testy do aktualizacji przy nowym wymaganym polu w `SheetPerson`: literały w `resources/js/lib/centre-hints.test.ts:5-15`, `lib/ancestor-layout.test.ts:5-15` i `lib/add-person-target.test.ts:15-23`. Nie sprawdzono, czy `tsconfig` obejmuje `*.test.ts`. Brak testu Pest na nazwę karty; nowy przypadek pasuje do `tests/Feature/TreeTest.php` i `tests/Feature/AncestorsTest.php`.

## Code References

- `resources/js/components/tree/sheet-person-card.tsx:44` – pasek zdjęcia `w-18`
- `resources/js/components/tree/sheet-person-card.tsx:88-102` – kolumna danych, nazwa z `[overflow-wrap:anywhere] break-words hyphens-auto`, rezerwa na oko
- `resources/js/components/tree/sheet-person-card.tsx:157-172` – przycisk oka i jego `aria-label`
- `resources/js/components/tree/card-styles.ts:14-15` – `personCardSurface` (`min-h-24`, `overflow-hidden`)
- `resources/js/components/tree/family-sheet.tsx:25-45` – `COLUMN_REM`, `COLUMN_GAP_REM`, `GRID_REM`, `COUPLE_CARD_REM`, `WIDE_CHILDREN_MAX`
- `resources/js/components/tree/ancestor-chart.tsx:9-14,84-127` – osobna kopia `COLUMN_REM`, szerokość karty przodka
- `resources/js/lib/fit-view.ts:1-16,42-69` – `TREE_FIT_FLOOR`, `TREE_FIT_MAX`, `ANCESTORS_*`, `chooseFitView`
- `resources/js/components/zoom-canvas.tsx:137-150` – dopasowanie: skala = min(szer., wys., max)
- `app/Http/Resources/SheetPersonResource.php:21-51` – ładunek karty, `display_name`
- `app/Models/Person.php:148-157` – `displayName()` z „z d.”
- `resources/js/types/tree.ts:3-11,80-90` – `SheetPerson`, `AncestorNode`, `AncestorChart`

## Architecture Insights

- Wszystkie odległości siatki (linie, szerokość par i dzieci, pozycje belek) wynikają z `COLUMN_REM` i `COLUMN_GAP_REM`. Wartość ustawiana w czasie działania (np. z szerokości obszaru) przesuwa całą geometrię razem, ale stała jest dziś modułowa w dwóch plikach. Wspólne źródło jest warunkiem wstępnym każdego z wariantów.
- `ZoomCanvas` mierzy `content.offsetWidth` (`zoom-canvas.tsx:139-150`). Kolumna zależna od szerokości obszaru tworzy pętlę: obszar → kolumna → treść → skala. Ponowne dopasowanie po zmianie treści już istnieje (`ResizeObserver` na `content`, `:289-291`), ale kolumnę trzeba liczyć z szerokości obszaru, nie z treści, żeby pętla się nie wzbudzała.
- Tokeny kolorów karty są czyste: skan zakodowanych wartości daje 0 trafień na plikach drzewa. Jedyną wartością arbitralną jest `[overflow-wrap:anywhere]` (C2).

## Historical Context (from prior changes)

- `context/archive/2026-10-11-person-photo-on-card/plan-brief.md:32,77`: kolumna 10 → 12rem, „nie wracać do 10rem”, przy nieczytelności na 1366 stroić pasek lub kolumnę. **Nadal obowiązuje** (kod); zmiana może poszerzać.
- `…/person-photo-on-card/plan.md:303-304`: rezerwa na oko tylko w pierwszej linii, żeby daty miały ≥ 6rem. `reviews/plan-review.md:25-41` odrzucił kolumnę ok. 14rem z powodu 1366. **Nadal w kodzie**; wariant „oko poza linią nazwy” ją zmienia.
- `…/person-photo-on-card/plan.md:340`: „długie imię na karcie dziadka łamie się czytelnie” (odhaczone 3.6, fb35d0b). **Zaprzeczone przez pomiar:** łamie się w środku słowa i wychodzi pod oko (C2, C3). Ta zmiana zastępuje to kryterium.
- `context/archive/2026-10-05-tree-fits-screen/plan-brief.md:24`, `plan.md:20`: `TREE_FIT_MAX = 1.5`, „wypełnia duży ekran bez groteskowo dużej małej rodziny”. **Częściowo:** przy 12rem i 2560 dopasowanie daje 1,384, czyli karty 266×213 px, a to jest właśnie „za duże”, na które skarży się użytkownik. Wartość kalibrowano przy 10rem (`screenshots/verdict.md:22-23`).
- `context/foundation/prd.md:135` (FR-013) i `:212` (NFR 1366–2560 px, duży ekran wypełniony). **Obowiązuje.** Wariant responsywny spełnia „wypełnia” szerokością kolumn zamiast skalą.
- DS (artefakt Root Keeper Design System), `PersonCard/README.md`: nazwa „imię i nazwisko (z „z d.” dla nazwiska rodowego)”. **Sprzeczne** z doprecyzowaniem użytkownika; DS wymaga aktualizacji razem ze zmianą.

## Related Research

- `context/archive/2026-10-05-tree-fits-screen/` (verdict ze skalami przy 10rem)
- `context/archive/2026-10-11-person-photo-on-card/`
- `context/archive/2026-10-10-ancestors-view/plan.md:189` (`ANCESTORS_ZOOM_MAX` = 1,5 „jak drzewo”)

## Decisions (użytkownik, 2026-10-12)

1. **Duży ekran → szersze kolumny zamiast powiększania.** Nadmiar szerokości obszaru idzie w szerokość kolumny (od 12rem w górę), a skala startu zostaje ok. 1. Przy 1366 i 1920 geometria się nie zmienia, bo obszar nie ma zapasu (`C ≈ 11,9rem` przy 1920).
2. **Nazwa, która się nie mieści: łamanie wyłącznie między słowami, maks. 2 linie, potem wielokropek.** Nigdy w środku słowa. To zastępuje kryterium `person-photo-on-card/plan.md:340` i usuwa C2.
3. **Karta jednokolumnowa oddaje miejsce tylko przez węższy pasek zdjęcia (4,5 → ok. 3rem).** Oko zostaje w pierwszej linii (rezerwa 28 px), czcionka nazwy zostaje 16 px.

Odrzucone przez użytkownika: stała szersza kolumna dla wszystkich ekranów, sama kompaktowa karta, wielokropek w jednej linii, oko poza linią nazwy, czcionka 14 px.

### Rachunek po decyzjach (16 px, ok. 9,3 px/znak, pasek 48 px, padding 24 px, oko 28 px w linii 1)

| Ekran (menu otwarte) | Kolumna C | Linia 1 (z okiem) | Linia 2 | Przykład |
| --- | --- | --- | --- | --- |
| 1366 | 12rem (192 px), skala 0,75 zakotwiczona (D1) | 92 px ≈ 9–10 zn. | 120 px ≈ 12 zn. | „Bartłomiej” (ok. 93–95 px, na granicy linii 1) / „Kowalczykows…” |
| 1920 | 12rem (192 px), skala ≈ 0,99 | 92 px ≈ 9–10 zn. | 120 px ≈ 12 zn. | „Aleksandra / Brzęczyszczy…”, „Stanisław / Wróblewski” mieści się |
| 2560 | ≈ 17rem (272 px), skala ≈ 1 | 172 px ≈ 18 zn. | 200 px ≈ 21 zn. | „Krystyna Zawadzka- / Pietrzak” albo 1 linia do ok. 18 zn. |

Konsekwencje dla planu:

- Przy 1366 i 1920 nazwiska dłuższe niż ok. 12 znaków dostaną wielokropek w drugiej linii (np. „Kowalczykowski”, 14 zn. ≈ 130 px > 120 px). Imię i nazwisko o długości p50 = 15 zn. zwykle mieści się w 2 liniach; p90 = 26 zn. tylko przy 2560. To wynika wprost z decyzji 1 i 3; plan powinien to pokazać w bramce wizualnej przy 1920. Jeśli to za mało, jedyną dźwignią bez zmiany decyzji jest dopuszczenie kolumny nieco > 12rem przy 1920 kosztem skali < 1 (np. 13rem → skala ok. 0,92).
- Łamanie po słowach z limitem 2 linii: `line-clamp-2` + `overflow-wrap: normal` zamiast `[overflow-wrap:anywhere] break-words hyphens-auto`. Pojedyncze słowo dłuższe niż linia (np. „Brzęczyszczykiewicz” przy 120 px) przy `overflow-wrap: normal` wychodzi poza pole w poziomie; plan musi zapewnić, że zostaje ucięte w obrębie kolumny tekstu (nie pod okiem i nie krawędzią karty) i że pełna nazwa jest w nazwie dostępnej lub podpowiedzi.
- Kolumna zależna od szerokości obszaru: wspólne źródło geometrii dla `family-sheet.tsx` i `ancestor-chart.tsx` (C4), kolumna liczona z szerokości obszaru, a nie z treści (pętla z `ResizeObserver`, `zoom-canvas.tsx:289-291`). Zmiana `TREE_FIT_MAX` lub `ANCESTORS_ZOOM_MAX` nie wynika wprost z decyzji, bo przy szerszej kolumnie skala i tak spada do ok. 1; plan rozstrzyga, czy sufit zostaje.
- Węższy pasek: token DS `photo-strip-width` = 4,5rem; plan wybiera między zmianą tokenu dla wszystkich kart a wariantem dla kart jednokolumnowych, i aktualizuje DS (`PersonCard/README.md` także o nazwę bez „z d.”).

## Open Questions

Luki techniczne (dla planu):

- Nie mierzono z menu bocznym zwiniętym ani z otwartym panelem bocznym osoby, który zwęża obszar drzewa.
- Liczba znaków na linię to szacunek z 9,3 px/znak (pomiar jednej nazwy), nie pomiar każdej nazwy.
- Nie sprawdzono, czy `tsconfig` obejmuje `*.test.ts` (literały `SheetPerson` w trzech testach Vitest).
