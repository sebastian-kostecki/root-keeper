---
change_id: tree-card-name-one-line
title: Tree card name one line
status: implemented
created: 2026-10-12
updated: 2026-10-13
archived_at: null
---

## Notes

Zmiana UI prowadzona przez `/10x-ui`.

- **Widok:** `/tree` (`resources/js/pages/tree/show.tsx`), karta osoby `resources/js/components/tree/sheet-person-card.tsx`; `CardBody` dzieli z nią karta widoku przodków (`ancestor-card.tsx`).
- **Źródło tokenów:** `resources/css/app.css` (`gender-*`, `tree-meta`, `tree-line`), geometria kart w `resources/js/components/tree/card-styles.ts`. Wariant kontraktu: istniejący system projektowy (rozszerzamy, nie rozwidlamy).
- **Cel użytkownika:** karty na drzewie są za duże, bo długie imię i nazwisko łamie się na kilka linii; imię i nazwisko o rozsądnej długości ma się mieścić w jednej linii.
- **Doprecyzowanie użytkownika:** na karcie imię i nazwisko rodowe (`maiden_name`, a gdy brak, `last_name`), bez nazwiska po małżeństwie i bez „z d. …”. Tylko na karcie; `Person::displayName()` używają też lista, panele i etykiety dostępności.
- **Założenie do potwierdzenia:** „rozsądna długość” ≈ 26 znaków (p90 w bazie dev: p50 15, p75 18, p90 26, p95 32, max 62); dłuższe obcięte wielokropkiem z pełną nazwą w podpowiedzi.

Przed-audyt (zarzuty-kandydaci do `research.md`):

1. `sheet-person-card.tsx:89` — na pierwszą linię imienia zostaje ok. 4,25rem (kolumna 12rem − pasek `w-18` 4,5rem z l. 44 − padding 1,5rem − rezerwa na oko 1,75rem z l. 93), czyli 8–9 znaków; nazwa 26-znakowa zajmuje 3–4 linie i karta rośnie w pionie.
2. `sheet-person-card.tsx:89` — `[overflow-wrap:anywhere] hyphens-auto` tnie nazwisko w środku słowa.
3. `COLUMN_REM = 12` zdublowane w `family-sheet.tsx:26` i `ancestor-chart.tsx:10`, plus komentarz w `lib/fit-view.ts:9` — szerokość karty nie ma jednego źródła.
4. Konflikt z decyzją z `context/archive/2026-10-11-person-photo-on-card/` (`plan.md:340` „długie imię łamie się czytelnie”, `plan-brief.md:77` czytelność drzewa przy 1366 px) — poszerzenie kart zmniejsza skalę dopasowanego drzewa.

Skan zakodowanych wartości na plikach drzewa: 0 kolorów/palet/wartości px-rem; jedyna wartość arbitralna to `[overflow-wrap:anywhere]`. Brak zrzutu ekranu w przed-audycie (brak logowania do bazy dev) — do zrobienia w research przy 1366 px i jednej szerokości mobilnej.
