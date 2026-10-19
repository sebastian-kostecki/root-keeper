# Oś czasu z wiekiem osoby w zakładce Wydarzenia — Plan Brief

> Full plan: `context/changes/person-events-timeline/plan.md`
> Research: `context/changes/person-events-timeline/research.md`

## What & Why

Zakładka Wydarzenia panelu osoby ma wyglądać jak oś czasu — linia ze znacznikami — i przy każdym
wydarzeniu pokazywać, ile lat miała osoba. Dziś użytkownik widzi płaską listę i musi liczyć wiek w
głowie z dwóch dat.

## Starting Point

`events-tab.tsx` renderuje `<ul className="divide-y">` z etykietą, datą i miejscem. Serwer
(`PersonTimeline`) już sortuje wiersze i ma `Age` do wieku z częściowych dat, ale payload wpisu nie
niesie wieku ani samego roku. W repo nie ma komponentu osi; tokeny potrzebne linii już istnieją.

## Desired End State

Po lewej wąska kolumna z rokiem (i małym „ok.” przy dacie przybliżonej) oraz wiekiem pod nim, na
środku pionowa linia ze znacznikami, po prawej etykieta z linkiem do krewnego i „data · miejsce”.
Własne wydarzenia i wydarzenia krewnych różnią się kolorem znacznika; pochowanie i wpisy bez daty mają
pusty znacznik i przerywany odcinek. Link do krewnego ma hover i widoczny focus.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Układ | Rok w kolumnie po lewej, wiek pod rokiem, znacznik na linii, treść po prawej | Chronologia czytelna jednym spojrzeniem w wąskim panelu | Research |
| Forma wieku | Sam `formatAge` („20 lat”), wyciszony, bez „w wieku”, nawiasu i „ok.” | Spójne z wiekiem w nagłówku | Research |
| Pochowanie | Bez wieku, pusta kolumna roku, pusty znacznik | Nie ma własnej daty | Research |
| Po zgonie | Każdy wiersz za wierszem własnego zgonu (w kolejności osi) bez wieku | Jedna reguła zgodna z tym, co widać; obejmuje pochowanie | Plan |
| Kolumna vs treść | Kolumna: rok; treść: pełna data tylko gdy ma miesiąc | Równa kolumna lat bez utraty dnia i miesiąca | Plan |
| Przerywane odcinki | Odcinek przerywany, gdy którykolwiek koniec to ○ | Niepewna pozycja widoczna z obu stron | Plan |
| Znaczniki | `bg-primary` własne, `bg-tree-line` krewni, ○ niepewne | Od razu widać, co dotyczy samej osoby; istniejące tokeny | Plan |
| Komponent | Wspólny `components/timeline.tsx` + helper `lib/person-timeline.ts` | Domyka brak wspólnego komponentu (C1), logika testowalna w Vitest | Plan |
| Gdzie wiek | Serwer (`PersonTimeline` + nowe `Age::at`), front tylko `formatAge` | Ten sam podział co wiek w nagłówku | Research |
| Utrwalenie | `view-tokens.test.ts` + punkt w `CLAUDE.md` | Kontrola w CI i reguła dla następnego agenta | Plan |
| Testy | Pest + Vitest + zrzuty; E2E bez zmian | Logika tanio, wygląd przez bramkę wizualną | Plan |

## Scope

**In scope:**
- Pola `year`, `is_approximate`, `date_has_month`, `age` we wpisie osi
- `Age::at`, reguła wieku na posortowanej osi
- `components/timeline.tsx`, `lib/person-timeline.ts`, zakładka na osi
- Styl i focus linku do krewnego
- `view-tokens.test.ts`, `CLAUDE.md`, zrzuty light/dark 1366 i 390

**Out of scope:**
- Stan pusty zakładki i licznik zakładki (odroczone)
- Zmiana zakresu i kolejności wierszy osi
- Ikony typu, oznaczenie „po śmierci”, nowe tokeny, nowe zależności, nowy E2E

## Architecture / Approach

`BuildPersonPreview` → `PersonTimeline::for()` (sortowanie, potem wiek z `Age::at` i strażnikiem
„po zgonie”) → `TimelineEntryResource` (nowe pola) → `events-tab.tsx` → `personTimelineRows()`
(znacznik, kolumna, odcinki) → `Timeline`/`TimelineItem` (linia `clsx`, tokeny, `<ul>/<li>`).

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Rok i wiek we wpisie osi | Payload z wiekiem i rokiem, testy Pest | Reguła „po zgonie” liczona przed sortowaniem |
| 2. Komponent osi + helper | `Timeline` i `personTimelineRows` z testem Vitest | `cn` zjada kolor linii — trzeba `clsx` |
| 3. Zakładka + utrwalenie + bramka | Oś w panelu, link z fokusem, CI i reguła, zrzuty | Czytelność kolumny i znaczników w dark i na 390 px |

**Prerequisites:** Sail uruchomiony; serwer E2E (`scripts/e2e-server.sh`) dla Playwright.
**Estimated effort:** ~1–2 sesje w 3 fazach.

## Open Risks & Assumptions

- Przy częściowych datach tego samego roku granicę „po zgonie” rozstrzyga ranga sortowania, nie fakt
  (zgon dziecka „1960” przy zgonie osoby „1960” dostaje wiek).
- Gdy własny zgon nie ma roku, wiersz zgonu jest w grupie bez daty — wcześniejsze datowane wydarzenia
  dostają wiek, nawet jeśli faktycznie były po zgonie.
- Nie sprawdzono, czy importer (`storage/gen-tree-import/`) zapisuje daty pochówku; wszystkie
  śledzone ścieżki zostawiają je puste.

## Success Criteria (Summary)

- Użytkownik widzi chronologię na linii i wiek osoby przy każdym wydarzeniu, gdzie ma on sens.
- Wydarzenia niepewne w czasie (pochowanie, bez daty) są wizualnie odróżnione.
- CI pilnuje tokenów osi, a istniejący E2E zakładki przechodzi bez zmian.
