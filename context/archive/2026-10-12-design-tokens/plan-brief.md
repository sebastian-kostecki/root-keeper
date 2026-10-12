# Wdrożenie tokenów z design systemu Root Keeper — Plan Brief

> Full plan: `context/changes/design-tokens/plan.md`
> Design system: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU (`project/implementation.md`, `project/README.md`)

## What & Why

Aplikacja ma dziś neutralny preset shadcn: szarości, białe menu, półprzezroczysty pierścień fokusu (przy nowym mchu 2,4:1, poniżej WCAG 3:1). Wdrażamy paletę i typografię design systemu Root Keeper („spokojnie jak czytelnia archiwum”) oraz poprawki komponentów, które bez nowych tokenów nie działają poprawnie (tekst błędu w ciemnym motywie, tekst na przycisku usuwania, ikony toastów, polskie teksty).

## Starting Point

`resources/css/app.css` ma już właściwe nazwy zmiennych, więc `components/ui/*` i klasy ról zmienią wygląd bez edycji widoków. Brak rodziny `display`, tokenów `source*` i `on-destructive`; `view-tokens.test.ts` pilnuje tylko widoków list `/persons`, `/sources`, `/places`.

## Desired End State

Papierowe tło, mech jako jedyny akcent, ciemne menu w obu motywach; tytuły stron, paneli, okien, pustych stanów, nazwa w logo i imię na karcie drzewa w Newsreaderze. Każdy fokus pełny i 3 px. Błąd w `destructive-foreground`, usuwanie na `destructive` z `on-destructive`. Ustawienia i opisy list po polsku, bez „Twój”. Strażnik tokenów pilnuje też kart drzewa i paneli. Zrzuty jasny/ciemny czterech tras z werdyktem.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres z sekcji 3 | Fokus, destructive, `on-destructive`, `font-display`, menu/okna, sonner, „data nieznana”, ustawienia, opis list, `VIEW_FILES` | Bursztyn źródeł i linie drzewa to osobne zmiany | Zlecenie |
| Opisy list | Wszystkie trzy (`/persons`, `/sources`, `/places`) bez „Twój” | Ta sama zasada z README, a zrzuty i tak obejmują te strony | Plan |
| Fokus poza `ui/*` | Wszystkie 8 ręcznych miejsc → `focusRing` (karty drzewa: `ring-3`) | Pełny pierścień 3:1 wszędzie, bez wartości w px | Plan |
| Dynamiczne `style=` | `family-sheet` i `ancestor-chart` poza `VIEW_FILES`; pasek postępu w `source-panel` jako wpis `EXCEPTIONS` | Strażnik działa od dziś bez przepisywania układu drzewa | Plan |
| Toasty ustawień | Polski klucz wprost w kontrolerze, martwe klucze z `pl.json` usunięte | Tak robią pozostałe kontrolery | Plan |
| Dialog klucza dostępu | Cały dialog po polsku, nie tylko „Anuluj”/„Usuń klucz” | Jedno okno nie miesza języków | Plan |
| Zrzuty | `shots.mjs` wzorem `places-page`, `colorScheme` light/dark, przed i po | Sprawdzony skrypt, bez nowych zależności | Plan |

## Scope

**In scope:**
- Wartości `:root`/`.dark`, usunięcie `chart-*`, `@theme` (`font-display`, `source*`, `on-destructive`), Newsreader w `vite.config.ts`
- `focusRing` i `ui/*` z pełnym `ring-ring`; 8 ręcznych miejsc fokusu; `ring-3` w menu; przycisk zamykania okna (`focus-visible:ring-3`, „Zamknij okno”)
- `text-on-destructive` w button/badge; `text-destructive` → `text-destructive-foreground` (9 miejsc); ikony sonner
- `font-display` w 6 miejscach; „data nieznana”; ustawienia po polsku; opisy trzech list
- `VIEW_FILES` o drzewo (karty, zaślepki, `card-styles.ts`) i panele

**Out of scope:**
- Bursztyn źródeł (`event-source-status`, miniatury, galeria, `ConfirmedMark`, `focusRingOffset`)
- Linie drzewa, `MarriageRingsIcon`, `union-node`, listwa płci w `PhotoStrip`
- Etykiety „Usuń osobę/źródło”, pozostałe angielskie teksty ustawień, `ring-[3px]` wewnątrz `ui/*`

## Architecture / Approach

Od fundamentu do szczegółu: zrzuty „przed” → wartości tokenów i font (najszerszy efekt bez edycji widoków) → klasy komponentów → typografia i teksty → strażnik i zrzuty „po”. Każda faza kończy się zielonymi `check` / `types:check` / `test:js`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tokeny i font | Nowa paleta, `@theme`, Newsreader, zrzuty „przed” | Zrzuty „przed” muszą powstać przed edycją CSS |
| 2. Fokus i kolory | Pełny fokus 3 px, kolory błędu/usuwania, ikony toastów | Pominięte miejsce `ring-ring/50` lub `text-destructive` (łapie grep) |
| 3. Typografia i teksty | `font-display`, „data nieznana”, ustawienia i opisy po polsku | Szeryf w roli interfejsu (np. przycisk) — kontrola wzrokowa |
| 4. Strażnik i bramka | Rozszerzony `VIEW_FILES`, zrzuty „po”, `verdict.md` | Wyjątek `style={` zwalnia cały `source-panel.tsx` |

**Prerequisites:** Sail uruchomiony; `scripts/e2e-server.sh` z bazą `e2e` i `TreeViewDemoSeeder` do zrzutów.
**Estimated effort:** ~1–2 sesje, 4 fazy.

## Open Risks & Assumptions

- Zakładamy, że wartości kontrastu z design systemu (np. `destructive` 6,7:1, `ring` ≥3:1) są poprawne — `verdict.md` odnotuje każdy widoczny problem.
- Tokeny `source*` wchodzą bez użycia aż do zmiany bursztynu.

## Success Criteria (Summary)

- Zrzuty jasny/ciemny `/tree`, `/persons`, `/sources`, `/places` pokazują nową paletę i szeryfowe tytuły; fokus pełny i 3 px.
- `test:js` (z szerszym `VIEW_FILES`), `check`, `types:check`, `build` i testy ustawień przechodzą.
