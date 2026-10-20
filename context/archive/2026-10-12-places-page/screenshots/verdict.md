# Werdykt wizualny — strona „Miejsca” (/places)

Stan: zrzuty „przed” w `before/`, „po” w `after/` (2026-10-20, serwer E2E `scripts/e2e-server.sh` na 127.0.0.1:8100, baza `e2e` zasiana `TreeViewDemoSeeder`, konto `tree-demo@example.com`; `node shots.mjs <abs>/after after`). 1440×900 i 375×812, motyw jasny.

| Zarzut | Przed | Po | Status | Uzasadnienie |
|---|---|---|---|---|
| C1 — dwa wpisy w menu | `before/01-localities-desktop.png`, `before/05-parishes-desktop.png` | `after/01-places-miejscowosci-desktop.png`, `after/02-places-parafie-desktop.png` | zamknięty | Jeden wpis „Miejsca” z sumą 5 (4 miejscowości + 1 parafia) zamiast „Miejscowości” i „Parafie”. |
| C2 — duplikacja stron | `before/01`, `before/05` | `after/01`, `after/02`, `after/11`, `after/12` | zamknięty | Jedna strona z przełącznikiem „Miejscowości 4 / Parafie 1”; wyszukiwanie liczy trafienia w obu zakładkach, „Pokaż w parafiach (1)” przechodzi na `?kind=parafie` z tym samym `q`. `places-page.spec.ts` przechodzi (3 passed). |
| C3 — ręczny pierścień fokusu | `before/04-localities-focus-row.png`, `before/08-parishes-focus-row.png` | `after/08-places-miejscowosci-focus-row.png`, `after/10-places-parafie-focus-row.png` | zamknięty | Link wiersza ma `:focus-visible` i ten sam pierścień co przycisk „Dodaj …” (`0 0 0 3px rgb(44, 90, 67)`, pomiar po animacjach). |
| C4 — „Użycia” o dwóch znaczeniach | `before/05-parishes-desktop.png`, `before/06-parishes-mobile.png` | `after/02-places-parafie-desktop.png`, `after/01-places-miejscowosci-desktop.png` | zamknięty | U parafii nagłówek „Źródła”, u miejscowości „Użycia”. |
| C5 — tabela parafii na 375 px | `before/06-parishes-mobile.png` | `after/04-places-parafie-mobile.png`, `after/03-places-miejscowosci-mobile.png` | częściowo | Pierwsza kolumna jest `sticky` w obu tabelach, a nagłówek z „Dodaj …” mieści się w szerokości. **Nowa usterka:** długa nazwa parafii („Parafia rzymskokatolicka w Tarnowie”) wychodzi poza przyklejoną komórkę (szerokość 224 px, treść 272 px, `white-space: nowrap`, `overflow: visible`) i nakłada się na kolumnę „Wyznanie” już bez przewijania. Do osobnej zmiany: zawijanie lub ucinanie nazwy w przyklejonej komórce. |

## Czego nie sprawdzono

- Pustej zakładki „Spis jest pusty”: dane demo mają obie listy niepuste.
- Tła przyklejonej komórki przy otwartym wierszu podczas przewijania w bok (punkt ręczny 2.3).
