# Drzewo jako domyślny dashboard wypełniający okno — Plan Brief

> Full plan: `context/changes/tree-dashboard-fill-window/plan.md`
> Research: `context/changes/tree-dashboard-fill-window/research.md`

## What & Why

`/tree` ma być stroną startową, która wygląda „bardziej jak program do drzewa genealogicznego”. Para, rodzice, dziadkowie i dzieci mają wypełniać okno wyższymi kartami. Dziś po zalogowaniu trafia się na listę osób. Pion arkusza jest w ok. 43% pusty (1920×1080), a na laptopie (1366–1440 px) skrajni dziadkowie są ucięci, bo widok startuje zakotwiczony na parze.

## Starting Point

- **Strona startowa:** `fortify.home = /persons`.
- **Kolumna kart:** wynika tylko z szerokości (12–18 rem). Każda karta ma tę samą treść i stałe `min-h-24`, a wysokość obszaru wpływa tylko na skalę.
- **Nagłówek:** nad drzewem jest wiersz z h1 i przyciskami probanta.
- **Payload:** karty mają tylko lata i wiek.
- **Wspólne komponenty:** karty i `ZoomCanvas` współdzieli widok przodków.

## Desired End State

Po zalogowaniu użytkownik widzi drzewo bez nagłówka. Karty są stopniowane:
- **dziadkowie:** kompaktowi, bez zdjęcia, imię i nazwisko w dwóch liniach;
- **rodzice:** jak dziś;
- **para:** szeroka karta na 3 kolumny, z datą i miejscem urodzenia, zgonu, pochówku i wiekiem.

Rzędy rosną z wysokością okna w granicach limitów. Przy 1366–1440 px cała rodzina jest widoczna od startu w skali ≈ 1. Nad dziećmi jest etykieta „Dzieci (N)”. Widok przodków się nie zmienia. Pracujemy na branchu `feat/tree-dashboard-fill-window`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Strona po zalogowaniu | `/tree` (stała `fortify.home`) | `/tree` ma stan pusty, więc powód `/persons` wygasł | Research (C1) |
| Nagłówek | Usunięty; h1 `sr-only`; bez odznaki „Probant” i przycisków | Pion dla drzewa; ustawianie probanta zostaje w panelu osoby | Research (U2) + Plan |
| Karty dziadków | Kompaktowe, bez zdjęcia, węższa kolumna arkusza (7,5 rem) | Mieści 8 dziadków przy 1366 px w skali ≈ 1 bez obniżania podłogi 0,75 | Research (P1) |
| Imiona w karcie kompaktowej | Imię w linii 1, nazwisko w linii 2, zawsze; wielokropek | Przewidywalny układ; świadomie zmienia test „1 linia przy 2560” | Plan |
| Dzieci przy ≥ 5 | Ta sama karta kompaktowa | Jeden wąski wariant: wąska karta = bez zdjęcia | Plan |
| Karta pary | 3 kolumny (max ≈ 28 rem), pasek 4,5 rem | Mieści „ur. 12 marca 1890, Kraków” w 1 linii i większe zdjęcie | Plan |
| Dane pary | ur./zm./poch. (data słownie + miejsce) + wiek; braki pomijane | Gotowy `formatPartialDate` z panelu osoby; dane tylko dla pary | Research (P2) + Plan |
| Wysokości rzędów | Stopniowane (para > rodzice ≈ dzieci > dziadkowie), min/max, nadmiar wyśrodkowany | Karty nie robią się puste ani kwadratowe na 2560 px | Research (P3) + Plan |
| Etykiety pokoleń | Tylko widoczne „Dzieci (N)”; slot „Dodaj dziecko” bez zmian | Licznik bez zmiany triggerów E2E | Research (P4) + Plan |
| Widok przodków | Bez zmian (karta, kolumna 12 rem, podłoga) | Osobny wariant | Research (U4) |
| `TREE_FIT_FLOOR` | Zostaje 0,75 | Karty stopniowane zastąpiły obniżenie do 0,65 | Research (P1 > U5) |

## Scope

**In scope:**
- `fortify.home` i fallback passkey;
- usunięcie nagłówka `/tree`;
- `details` i części imienia w payloadzie;
- warianty kart `compact`/`pair`, etykieta „Dzieci (N)”;
- minimum kolumny jako parametr, budżet wysokości w `ZoomCanvas`, rozkład rzędów;
- testy Pest, Vitest i E2E oraz zrzuty.

**Out of scope:**
- widok przodków, układ mobilny;
- elementy PLSoft (zdjęcie ślubne, rodzeństwo, ID, wydruk, Wstecz/Dalej, gradienty);
- ekran startowy per użytkownik, marginesy layoutu;
- aktualizacja artefaktu DS.

## Architecture / Approach

`ZoomCanvas` dostaje opcjonalne `columnMinRem` i `fillHeight`. Pętla `refit()` liczy kolejno kolumnę z szerokości, skalę z szerokości treści, potem budżet wysokości (wysokość obszaru ÷ skala). Budżet idzie do treści jako drugi argument callbacku. `FamilySheet` odejmuje stały pion (łączniki, pień, pady) i rozdziela resztę na cztery rzędy czystą funkcją `distributeRows` (min/max/waga). Wynik trafia jako `minHeight` komórek siatki, a `chooseFitView` nadal liczy skalę z rzeczywistego rozmiaru i centruje. Backend dodaje `details` tylko przy `render(..., withDetails: true)` dla osoby w centrum i partnera.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Start na drzewie, bez nagłówka | Lądowanie na `/tree`, arkusz bez wiersza nagłówka | `auth.setup.ts` (globalny dla E2E) i h1 `sr-only` w `gotoTree` |
| 2. Dane pary w payloadzie | `details` i `card_given_name`/`card_surname` | Wyciek `details` na inne karty / przodków, liczba zapytań |
| 3. Warianty kart arkusza | Karta kompaktowa, karta pary, „Dzieci (N)” | Niezamierzona zmiana kart przodków; dostępna nazwa karty kompaktowej |
| 4. Geometria wypełnienia | Kolumna 7,5 rem dla arkusza, rzędy z wysokości obszaru | Pętla renderów przy resize; rozjazd stałych pionu z DOM |
| 5. E2E i bramka wizualna | R1 (1366: cała rodzina), R2 (1920: wypełnienie), zrzuty | Niestabilne pomiary bounding box; zmiana testu 1 nazw kart |

**Prerequisites:** branch `feat/tree-dashboard-fill-window` (utworzony od `develop`); działający Sail i serwer E2E (`scripts/e2e-server.sh`).
**Estimated effort:** ok. 3–4 sesje: fazy 1–2 razem, 3, 4, 5.

## Open Risks & Assumptions

- Liczby pikseli (7,5 rem, budżety, limity rzędów) są obliczone ręcznie przy rem = 16 px i rozwiniętym panelu bocznym. Fazy 3–4 potwierdzają je pomiarem i zrzutami.
- Zakładamy, że h1 `sr-only` przechodzi `toBeVisible()` w Playwright (ramka 1×1 px). Jeśli nie, zmieniamy oczekiwanie helpera `gotoTree` na `toBeAttached()` w przeglądanym diffie.
- Karta pary 3 kolumny zostawia mniej miejsca na `UnionNode` między kartami. Sprawdzić najdłuższą pigułkę („ślub 1890 · 2 z 3”) przy 1366 px.
- Usunięcie odznaki „Probant” z drzewa oznacza, że drzewo nie pokazuje, kto jest probantem (decyzja użytkownika).

## Success Criteria (Summary)

- Po zalogowaniu widać drzewo, a przy 1366×768 i 1440×900 wszyscy dziadkowie i dzieci są widoczni od startu, bez przeciągania.
- Przy 1920×1080 arkusz zajmuje co najmniej ok. 80% wysokości obszaru. Para jest najwyższa i pokazuje daty, miejsca i wiek.
- Widok przodków i dotychczasowe testy E2E działają bez regresji, poza świadomie zmienionym testem 1 w `tree-card-name.spec.ts`.
