---
date: 2026-10-19T15:15:19+02:00
researcher: Claude (Opus 5.5)
git_commit: 07a70a1
branch: develop
repository: root-keeper
topic: "Audyt UI zakładki Wydarzenia: oś czasu ze znacznikami i wiek osoby w chwili wydarzenia"
tags: [research, ui, persons, timeline, age, events-tab, design-tokens]
status: complete
last_updated: 2026-10-19
last_updated_by: Claude (Opus 5.5)
---

# Research: Audyt UI zakładki Wydarzenia — oś czasu + wiek w chwili wydarzenia

**Date**: 2026-10-19T15:15:19+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 07a70a1
**Branch**: develop
**Repository**: root-keeper

## Research Question

Dwukierunkowy audyt UI (`/10x-ui`) zakładki Wydarzenia panelu osoby
(`resources/js/components/persons/events-tab.tsx`) względem tokenów `resources/css/app.css`.
Cel użytkownika: „linia ze znacznikami czasu gdzie są jakieś wydarzenia (…) + dodaj ile ta osoba
miała lat w chwili tego wydarzenia”. Zweryfikować 5 wstępnych zarzutów z `change.md` i zapisać
je pod `## Charges`.

## Summary

- **Tokeny są w porządku.** Skan zakodowanych wartości daje 0 trafień w `events-tab.tsx`,
  `person-panel.tsx`, `person-modal.tsx`, `event-source-status.tsx` (przed-audyt 2026-10-19).
  Widok czyta `text-muted-foreground` (2×) i domyślny kolor obramowania `border-border`
  (`resources/css/app.css:167-170`). Zmiana nie wymaga fazy wartości tokenów — wszystkie
  potrzebne role już istnieją (`--tree-line`, `--primary`, `--muted-foreground`, `--border`,
  szerokość `--tree-line-width`).
- **Brakuje struktury i danych, nie kolorów.** Lista `divide-y` zamiast osi
  (`events-tab.tsx:46-50`), brak wspólnego komponentu osi w repo i w DS, brak wieku w payloadzie
  (`TimelineEntryResource.php:28-44`), a `TimelineEntry` nie zna oglądanej osoby
  (`app/Support/TimelineEntry.php:18-22`).
- **Wiek da się policzyć istniejącym `Age::from`** z datą wydarzenia jako końcem okresu i
  `isDeceased = true`, ale wymaga to strażnika na wydarzenia po zgonie osoby — dla
  `isDeceased = true` `Age` nie ma górnego limitu (`app/Support/Age.php:86-93`).
- Wstępne zarzuty: 1, 2, 3, 5 potwierdzone; 4 (stan pusty) przesunięty do **deferred**, bo jest
  spójny z pozostałymi zakładkami. Nowy zarzut C4: link do krewnego bez focus ringa i hovera.

## Charges

| # | Kategoria | Dowód (plik:linia) | Wpływ na użytkownika | Naprawa | Status |
|---|---|---|---|---|---|
| C1 | Brakujący współdzielony komponent | `resources/js/components/persons/events-tab.tsx:46-50` — `<ul className="divide-y">` z wierszami `py-2.5` (`:12`); brak komponentu osi w `resources/js/components/ui/`, we wspólnych `components/*.tsx` i w DS (19 komponentów, żaden nie jest osią) | Wszystkie wydarzenia mają tę samą wagę; nie widać chronologii ani tego, gdzie w życiu osoby wypada dane wydarzenie | Jeden komponent osi (pionowa linia + znaczniki + etykieta daty) z istniejących tokenów: linia `border-l-line border-tree-line` (przez `clsx`/statyczny string, nie `cn` — `family-sheet.tsx:3`), znacznik wzorem `bg-primary size-1.5 rounded-full` (`person-modal.tsx:82-85`) lub pigułki z rokiem (`family-sheet.tsx:403-412`, `union-node.tsx:114`). Zachować `<ul>/<li>` (test E2E, niżej) | do planu |
| C2 | Przypadkowa architektura (brak danych) | `app/Http/Resources/TimelineEntryResource.php:28-44` i `resources/js/types/person.ts:116-125` — wpis bez wieku; `app/Support/TimelineEntry.php:18-22` ma tylko `event`, `role`, `relative`, bez oglądanej osoby | Użytkownik musi liczyć wiek w głowie z dwóch dat | Policzyć wiek na serwerze w `PersonTimeline::for()` (ma `$person`, `PersonTimeline.php:21`) i przekazać do wpisu; front formatuje `formatAge` (`resources/js/lib/age.ts:5-20`). Strażniki: brak wieku dla własnego urodzenia, dla wydarzeń po własnym zgonie i gdy brak roku | do planu |
| C3 | Przypadkowa architektura (wpisy bez daty) | `events-tab.tsx:9,26` — pochówek bez daty, reszta „data nieznana” w tym samym miejscu co data; pochówek nigdy nie ma daty (`EventFactory.php:56-64`, `CreatePerson.php:63`, `UpdatePerson.php:72`, `FillBurialPlacesFromGravestone.php:29-33`) | Na osi znacznik „data nieznana” udaje punkt w czasie; użytkownik nie odróżni wydarzeń datowanych od nieznanych | Osobna forma znacznika (np. pusty/przerywany, wzór `border-dashed` z `card-styles.ts:50`) albo osobna grupa „Bez daty” na końcu — kolejność bez zmian (PRD FR-021, `prd.md:161-163`). Pochówek przyklejony do zgonu | do planu |
| C4 | Brakujący stan (focus-visible / hover) | `events-tab.tsx:18-20` — `PersonPreviewLink` bez `className`; komponent nie dodaje stylów (`person-preview-link.tsx:45-53`); reszta panelu: `cn('text-primary rounded-sm underline-offset-4 hover:underline', focusRing)` (`overview-tab.tsx:146,215`) | Użytkownik klawiatury nie widzi, który krewny ma fokus; myszą nie widać, że imię jest linkiem | Ten sam styl linku co w `overview-tab.tsx:146` + `focusRing` (`resources/js/lib/focus-ring.ts:7-8`) | do planu |
| C5 | Brak zabezpieczenia | `resources/js/lib/view-tokens.test.ts:20-57` — `VIEW_FILES` nie zawiera `components/persons/events-tab.tsx` (jest `person-modal.tsx` `:52` i `side-panel.tsx` `:51`) | Następny agent może wrócić do literałów w osi bez sygnału z CI | Dopisać `events-tab.tsx` (i plik nowego komponentu osi) do `VIEW_FILES`; reguła w `CLAUDE.md` pod „UI: tokens and shared components” | do planu |
| D1 | Stan pusty | `events-tab.tsx:41-43` — `<p className="text-muted-foreground text-sm">Brak wydarzeń.</p>` | Nowy użytkownik nie dostaje wskazówki, gdzie dodać wydarzenie | — | **deferred**: ten sam wzór mają zakładki rodzeństwa (`family-tab.tsx:166`, `note-tab.tsx:45-49`); zmiana jednej zakładki rozbije spójność panelu — osobna zmiana dla stanów pustych panelu |
| D2 | Licznik zakładki | `person-modal.tsx:75-77` — „Wydarzenia” bez `TabCount` (Rodzina, Źródła go mają) | Brak podpowiedzi, czy zakładka coś zawiera | — | **deferred**: decyzja „bez licznika” z `archive/2026-10-10-person-events-and-note-tabs/plan.md:72-74`; poza celem tej zmiany |

## Detailed Findings

### Widok i tokeny (kierunek widok → źródło)

- `events-tab.tsx` importuje jeden komponent (`PersonPreviewLink`, `:1`) i żaden komponent z
  `components/ui/`. Literałów brak; klasy: `space-y-0.5 py-2.5` (`:12`), `text-sm font-medium`
  (`:13`), `text-muted-foreground text-sm` (`:25`, `:42`), `divide-y` (`:47`).
- `divide-y` bierze kolor z `* { @apply border-border }` (`resources/css/app.css:167-170`).
- Role do osi już istnieją: `--color-tree-line` (`app.css:70`), `--border-width-line:
  var(--tree-line-width)` (`:73`, 1.5px w `:120`), `--primary` (`:85`), `--muted-foreground`
  (`:90`), `--border` (`:96`). Dark: `--primary #86c7a2` (`:131`), `--muted-foreground #a3aca4`
  (`:136`), `--border #2c352e` (`:142`), `--tree-line #6c766e` (`:163`); szerokości linii nie są
  nadpisywane w `.dark`.

### Wzorce linii i znaczników w repo (kierunek źródło → widoki)

- `family-sheet.tsx:150-168` — obiekty `LINE` (`border-tree-line`, `border-l-line`) i `LINEAGE`
  (`border-primary`, `border-*-lineage`); pionowa łodyga `:862-870` z `aria-hidden`.
- `family-sheet.tsx:3` — `cn()` (tailwind-merge) traktuje `border-line` jako kolor i usuwa
  właściwy kolor; tam użyto `clsx`. Dotyczy każdej kombinacji `border-l-line` + `border-tree-line`.
- `ancestor-chart.tsx:53-75` — linie jako `div` z `bg-tree-line w-px` / `h-px`.
- Pigułka z rokiem: `family-sheet.tsx:403-412` (`bg-background rounded-full … text-xs font-medium
  tabular-nums`), `union-node.tsx:114`.
- Kropka: `person-modal.tsx:82-85` (`bg-primary size-1.5 rounded-full`, `aria-hidden` + `sr-only`).

### Panel i stany

- Szerokość panelu: `standard` = `md:w-[clamp(24rem,30vw,36rem)]`, mobile pełny ekran
  (`side-panel.tsx:210-216`); osoba używa `standard` (`person-panel.tsx:44`). Treść zakładki ma
  `py-4` (`person-modal.tsx:111-113`), ciało panelu `px-4 py-4` (`side-panel.tsx:248`).
- Loading: panel nie ma skeletonu ani deferred props — `person-panel.tsx:26-28` zwraca `null` bez
  podglądu; przy przejściu do krewnego stara treść zostaje do podmiany propsów
  (`side-panel-url.ts:26-36`, `use-side-panel-navigation.ts:31-33`). Dla zakładki: **N/A** —
  oś renderuje gotowe dane, bez osobnego ładowania.
- Disabled / error: zakładka jest tylko do odczytu, bez kontrolek formularza — **N/A**.

### Wiek w chwili wydarzenia (backend)

- `Age::from(?PartialDate $birth, ?PartialDate $death, bool $isDeceased, CarbonImmutable $today)`
  (`app/Support/Age.php:25`). Z datą wydarzenia jako `$death` i `$isDeceased = true`:
  - brak roku urodzenia → `null` (`:27-29`); brak roku wydarzenia → `null` (`:31-34`);
    z `isDeceased = false` brak końca oznaczałby „dziś” (`:37-38`), więc flaga musi być `true`;
  - pełne daty po obu stronach → Carbon `diff` (`:65-84`): `years` gdy ≥1, potem `months`,
    potem `days` (ten sam dzień = `{days, 0}`), wydarzenie przed urodzeniem → `null` (`:71-73`);
  - rok+miesiąc po obu stronach bez dnia → odjęcie roku, gdy `end.month < birth.month` (`:50-52`);
  - sam rok po którejkolwiek stronie → zwykła różnica lat (`:48`);
  - ten sam rok przy częściowych datach → `under_year` (`:58-60`); ujemny wynik → `null` (`:54-56`);
  - `isApproximate` nie jest czytane (test `tests/Unit/AgeTest.php:84-87`);
  - limit 110 lat działa tylko dla `isDeceased = false` (`:12`, `:86-93`) — wydarzenie po zgonie
    osoby (np. zgon syna 1990 przy zgonie osoby 1960) dostałoby wiek bez strażnika.
- Jedyne obecne wywołanie: `Person::age()` (`app/Models/Person.php:138-146`), wymaga
  załadowanych `events`. `Event::date` to accessor `PartialDate` (`app/Models/Event.php:89-101`).
- `BuildPersonPreview.php:29` ładuje `events.sources`, `events.locality`, `children.events.locality`,
  `marriages.participants.events.locality`; `:35` buduje oś. Urodzenie i zgon oglądanej osoby są
  w pamięci — wiek nie dodaje zapytań (test liczby zapytań `PersonTimelineTest.php:283-323`).
- Nazwy `$death` / `$isDeceased` nie opisują użycia z datą wydarzenia; dodatkowy nazwany
  konstruktor to decyzja planu (obserwacja, nie wymaganie).

### Wiek (frontend)

- `formatAge(age: PersonAge)` (`resources/js/lib/age.ts:5-20`): `N rok|lata|lat`, `N mies.`,
  `N dzień|dni` lub `poniżej dnia`, `poniżej roku`. Typ `PersonAge` (`types/person.ts:71-74`).
- Obecne użycia: `person-modal-header.tsx:36` (format „lifespan (wiek)”),
  `tree/sheet-person-card.tsx:89,178`. Fraza „w wieku …” nie występuje w `app/`, `resources/js`,
  `lang/` (grep agenta; sprawdzona ścieżka).

### Testy, na które zmiana wpływa

- `tests/Feature/PersonTimelineTest.php` — helper `timelineRows` (`:39-50`) redukuje wiersz do
  `[label, date, place, relative]` i porównuje `toBe`; nowe pole wymaga rozszerzenia helpera albo
  osobnej asercji (surowy dostęp wzorem `:267-281`). Scena `timelineRangeScene` (`:58-86`): Adam
  ur. 1900, śluby 1920/1930, zgon 1960, pochówek, syn 1925–1990 (zgon syna po zgonie Adama),
  córka 1928, Ewa zm. 1950 — gotowe przypadki dla wieku i strażnika „po zgonie”.
- `tests/Unit/AgeTest.php` (87 linii) — pokrywa reguły `Age`.
- E2E `tests/e2e/person-events-and-note-tabs.spec.ts:54-91` szuka
  `getByRole('listitem').filter({ hasText: 'Urodzenie córki' })` z „1990” i klika
  `getByRole('link', { name: childName })` — oś musi zachować `<li>`, etykietę i nazwę linku.

### Bramka wizualna

- Brak `toHaveScreenshot` w repo (`playwright.config.ts:25` tylko `screenshot: 'only-on-failure'`),
  brak strony kitchen-sink. Konwencja: `context/changes/<id>/screenshots/shots.mjs` (Playwright,
  light + dark, `deviceScaleFactor: 2` dla linii 1.5px), PNG `NN-<nazwa>-<motyw>.png` i
  `verdict.md` (`archive/2026-10-12-tree-lines-union-node/screenshots/`,
  `archive/2026-10-12-design-tokens/screenshots/`). Rozmiary: 1366×768 i 390×844
  (`archive/2026-10-19-ancestors-view-polish/plan.md:351-362`).

## Code References

- `resources/js/components/persons/events-tab.tsx:8-37` — `EventRow`; `:40-51` — `EventsTab`
- `resources/js/components/persons/person-modal.tsx:75-77,111-113` — wyzwalacz i treść zakładki
- `resources/js/components/persons/person-preview-link.tsx:45-53` — link bez własnych stylów
- `resources/js/components/persons/overview-tab.tsx:146,215` — styl linku + `focusRing`
- `resources/js/lib/focus-ring.ts:7-8` — `focusRing`
- `resources/js/components/tree/family-sheet.tsx:3,150-168,403-412,862-870` — linie, pigułki, uwaga o `cn`
- `resources/js/lib/view-tokens.test.ts:20-57,68-97,103-124` — pliki, reguły, wyjątki
- `app/Support/Age.php:25-101` — reguły wieku
- `app/Support/PersonTimeline.php:21-32,122-155` — budowa i sortowanie osi
- `app/Support/TimelineEntry.php:18-22` — pola wpisu
- `app/Http/Resources/TimelineEntryResource.php:19-44` — payload
- `app/Actions/Persons/BuildPersonPreview.php:29,35` — eager loading i oś
- `resources/js/lib/age.ts:5-20` — `formatAge`
- `tests/Feature/PersonTimelineTest.php:39-50,58-86,283-323` — helper, scena, liczba zapytań
- `tests/e2e/person-events-and-note-tabs.spec.ts:54-91` — E2E zakładki

## Architecture Insights

- Oś jest liczona i sortowana wyłącznie na serwerze; front renderuje gotową listę
  (`archive/2026-10-10-person-events-and-note-tabs/plan.md:290`). Wiek pasuje do tego podziału:
  liczba na serwerze (`Age`), tekst w TS (`formatAge`) — tak samo jak wiek w nagłówku
  (`archive/2026-10-05-maiden-name-and-age/plan.md:54,200`).
- Linie drzewa mają własne tokeny roli (`tree-line`, `line`/`lineage`); oś w panelu może użyć tej
  samej roli, bez nowych wartości w `app.css`.

## Historical Context (from prior changes)

- `context/archive/2026-10-10-person-events-and-note-tabs/plan.md:77` — „Bez przeliczania wieku i
  dat na osi” (supported jako ówczesny zakres; ta zmiana świadomie go rozszerza).
- `…/plan.md:40-47,92-94` — zakres wierszy i klucz sortowania; pochówek dziedziczy klucz zgonu
  (supported, zgodne z `PersonTimeline.php:122-155`).
- `…/plan.md:72-74` — bez statusu źródła, bez edycji, bez licznika na zakładce (supported).
- `context/archive/2026-10-05-maiden-name-and-age/plan.md:56-62` — reguły wieku; flaga „około” nie
  zmienia wyniku; `plan-brief.md` — „bez „ok.”” przy wieku (supported, `AgeTest.php:84-87`).
- `context/archive/2026-10-19-ancestors-view-polish/plan-brief.md:13` — wiek ukryty na kartach
  przodków, bo „zaśmieca gęsty wykres” (precedens: wiek tam, gdzie jest miejsce).
- `context/archive/2026-10-12-design-tokens/plan.md:206,218-224` — wspólna forma „data nieznana”.
- `context/foundation/prd.md:161-163` (FR-021) — kolejność po roku, bez daty na końcu.
- Zmiana nie odpowiada żadnemu otwartemu slice’owi roadmapy (S-13, S-14 done,
  `roadmap.md:279-302`); jak inne przebiegi UI, jest poza roadmapą.

## Related Research

- `context/archive/2026-10-19-ancestors-view-polish/research.md` — poprzedni przebieg `/10x-ui`
  (macierz 7 stanów, krok utrwalenia w `view-tokens.test.ts` i `CLAUDE.md`).

## Open Questions

Decyzje produktowe dla użytkownika (przed `/10x-plan`):

1. Układ osi: data po lewej od linii (kolumna lat) czy znaczniki na linii z datą w treści wiersza.
2. Forma wieku: w nawiasie jak w nagłówku („(34 lata)”), osobna etykieta/plakietka, czy fraza
   „w wieku 34 lat” (nowe sformułowanie w aplikacji).
3. Pochówek: bez wieku czy wiek z daty zgonu.
4. Wydarzenia po zgonie osoby (zgon dziecka, małżonka): bez wieku (rekomendacja) czy z
   oznaczeniem „po śmierci”.

### Decyzje użytkownika (2026-10-19)

1. **Układ:** rok w wąskiej kolumnie po lewej od pionowej linii, znacznik na linii, treść po
   prawej; wiek pod rokiem. Wpisy bez daty i pochowanie mają pusty znacznik (○) i przerywany
   odcinek linii; bez daty pokazują „?” w kolumnie roku.
2. **Forma wieku:** sam wiek z `formatAge` („20 lat”, „22 lata”, „poniżej roku”), wyciszony
   kolor — bez frazy „w wieku” i bez nawiasu.
3. **Pochowanie:** bez wieku.
4. **Wydarzenia po zgonie osoby:** wiersz zostaje w swoim miejscu, bez wieku.

Wynikające reguły dla planu: brak wieku także dla własnego urodzenia i dla wpisów bez roku
(`Age.php:27-34`); przy przybliżonych datach bez „ok.” przy wieku (decyzja z
`archive/2026-10-05-maiden-name-and-age/plan-brief.md`).

Luka: nie sprawdzono, czy importer (`storage/gen-tree-import/`, nieśledzony) zapisuje daty
pochówku; wszystkie śledzone ścieżki zapisu zostawiają datę pochówku pustą.
