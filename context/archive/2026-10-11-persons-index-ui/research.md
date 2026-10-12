---
date: 2026-10-11T17:37:49+02:00
researcher: Claude (Opus 5.5)
git_commit: fccf66ea1d4fc0edfe29bcf7489b22033d4eaeb4
branch: develop
repository: root-keeper
topic: "Audyt /10x-ui widoku /persons — zarzuty względem systemu projektowego"
tags: [research, ui, persons-index, design-tokens, side-panel, states]
status: complete
last_updated: 2026-10-11
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika dla C1, C3 i bramki wizualnej"
---

# Research: Audyt /10x-ui widoku /persons

**Date**: 2026-10-11T17:37:49+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: fccf66ea1d4fc0edfe29bcf7489b22033d4eaeb4 (drzewo robocze brudne — trwa Faza 2 `tree-add-person-panel`; pliki `/persons` poza `person-candidate-list.tsx` i `section-editor.tsx` niezmienione względem HEAD)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Dwukierunkowy audyt (źródło → widoki, widok → źródło) jednego widoku `/persons`
(`resources/js/pages/persons/index.tsx` + `resources/js/components/persons/persons-{list,table,empty-state}.tsx`
i to, co renderują) w trzech kategoriach /10x-ui: brakujące tokeny, brakujący współdzielony komponent,
przypadkowa architektura. Wynik: 3–5 zarzutów z plikiem, linią i wpływem na użytkownika (`## Charges`),
plus wstępna macierz 7 stanów. Źródło tokenów: `resources/css/app.css`; wariant kontraktu: istniejący system.

## Summary

Widok stoi na tokenach: w zakresie `/persons` skan literałów daje 3 trafienia `ring-[3px]` w plikach
z `change.md` i jedną klasę palety w formularzu tworzenia (`partial-date-input.tsx:102`); kolory płci idą
przez `lib/gender-styles.ts` → tokeny `gender-*`. Problemy leżą wyżej niż kolory:

1. **Tworzenie osoby** to modal, a zapis przenosi użytkownika z listy na `/tree/{id}?person={id}`
   (`PersonController.php:106`) — świadoma decyzja S-06, ale sprzeczna z dzisiejszym kierunkiem
   (panele boczne, FR-019) i z wzorcem sąsiednich list, które tworzą rekord w panelu.
2. **Nagłówek strony** odbiega od sąsiadów: `Heading` w wariancie domyślnym (`<h2>` + `mb-8`) zamiast
   `variant="page"` (`<h1>`), brak landmarku `role="search"`.
3. **Duplikaty prymitywów**: skoki do litery odtwarzają `Button outline sm`; ten sam ręczny pierścień
   fokusu jest w 4 miejscach w zakresie widoku (16 wystąpień w 14 plikach poza `ui/`), bez wspólnej stałej.
4. **Token błędu**: komunikat błędu daty w formularzu tworzenia używa `text-red-600`, nie `destructive`.
5. **Stany**: brak stanu błędu dla nieudanego wyszukiwania/przełączenia; stan „brak wyników” nie pokazuje
   ładowania przy kolejnym wyszukiwaniu.

Rozdzielenie „domu” (logowanie → `/persons`, logo i pierwsza pozycja menu → `/tree`) jest
udokumentowaną decyzją (`app-shell-sidebar/plan-brief.md:24`) przypiętą testem E2E — zarzut z przed-audytu
przeniesiony do **deferred**.

Luka: brak zrzutów ekranu (serwer E2E nie działał, jego start wymaga `npm run build` przy równolegle
edytowanej Fazie 2 drzewa). Twierdzenia wizualne (przesunięcie przycisku przez `mb-8`, zawijanie na 375 px)
są wnioskami z kodu; potwierdzić zrzutem „przed” na początku `/10x-implement`.

## Charges

Każdy zarzut: kategoria, dowód `plik:linia`, wpływ na użytkownika, kierunek naprawy. Kolejność ≈ wpływ.

### C1 — Przypadkowa architektura: tworzenie osoby w modalu i wyrzucenie z listy po zapisie

- Dowód: `resources/js/components/persons/person-create-dialog.tsx:77-92` (`Dialog` + `FormDialogContent className="sm:h-[90vh]"`),
  wyzwalacz `resources/js/pages/persons/index.tsx:125`, użyty w nagłówku (`:138-141`) i w pustym stanie (`:186-191`);
  zapis `app/Http/Controllers/PersonController.php:106` → `redirect(route('tree.show', $person).'?person='.id)`.
- Wpływ: dodając osobę z listy, użytkownik pracuje w oknie zasłaniającym listę, a po zapisie ląduje na drzewie
  nowej osoby zamiast na liście, z której przyszedł — przy dopisywaniu kilku osób z rzędu każda wymaga powrotu
  do `/persons`. Podgląd istniejącej osoby z tej samej listy otwiera się w panelu (`person-preview-link.tsx:46-53`),
  więc „nowa” i „istniejąca” osoba mają dwa różne wzorce.
- Kontekst: decyzja S-06 „zostaje modalem” (`context/archive/2026-10-05-person-side-panel/research.md:155-156`);
  odłożone jako „osobna zmiana” w `context/changes/tree-add-person-panel/change.md` (Notes) i `plan.md:40`.
  Sąsiednie listy tworzą rekord w panelu (`Button asChild` + `Link` z `panelVisitOptions`, np. `pages/sources/index.tsx`).
  CLAUDE.md „Side panels vs modals” mówi o otwieraniu osoby, nie wprost o tworzeniu.
- Kierunek: panel tworzenia na wspólnej powłoce `side-panel.tsx`, reużywając panel dodawania z `tree-add-person-panel`
  (klucz `?add=` w `lib/side-panel-url.ts`, `PersonFormFields`); po zapisie panel przełącza się na podgląd nowej osoby,
  lista zostaje. **Decyzja produktowa dla planu** (zob. Open Questions) + zależność od zamknięcia Fazy 2 drzewa.

### C2 — Brakujący współdzielony wzorzec: nagłówek strony i wyszukiwarka inne niż na sąsiednich listach

- Dowód: `resources/js/pages/persons/index.tsx:133-136` — `<Heading title description />` w wariancie domyślnym,
  który renderuje `<h2>` i `mb-8` (`resources/js/components/heading.tsx:17`, `:25-32`); sąsiedzi mają `variant="page"`
  (`pages/sources/index.tsx:350`, `pages/localities/index.tsx:156`, `pages/parishes/index.tsx:169`).
  Wiersz wyszukiwania `index.tsx:145` bez `role="search"` (sąsiedzi: `sources/index.tsx:360`, `localities/index.tsx:165`,
  `parishes/index.tsx:178`); `SegmentedToggle` bez `iconOnlyBelowSm` (`index.tsx:156-172`, por. `sources/index.tsx:414`).
- Wpływ: strona startowa po zalogowaniu nie ma `<h1>` ani landmarku wyszukiwania — czytnik ekranu nie ma
  punktu zaczepienia; `mb-8` wewnątrz `flex items-center` (`index.tsx:132`) przesuwa „Dodaj osobę” poniżej linii
  tytułu (wniosek z kodu, do potwierdzenia zrzutem); na wąskim ekranie przełącznik zajmuje więcej miejsca niż na „Źródłach”.
- Kierunek: `variant="page"`, `role="search"`, `iconOnlyBelowSm` — jak w trzech sąsiednich stronach. Bez nowego komponentu.

### C3 — Brakujący współdzielony komponent: skoki do litery jako ręczny przycisk, pierścień fokusu kopiowany

- Dowód: `resources/js/components/persons/persons-list.tsx:105-111` — `<a>` z `inline-flex h-8 min-w-8 … rounded-md border px-2 text-sm font-medium hover:bg-accent … focus-visible:ring-[3px]`,
  czyli odtworzony `buttonVariants({ variant: 'outline', size: 'sm' })` (`resources/js/components/ui/button.tsx:8`),
  bez `bg-background`, `shadow-xs` i `focus-visible:border-ring`.
  Ten sam ciąg `focus-visible:ring-ring/50 … focus-visible:ring-[3px]` w zakresie widoku: `persons-list.tsx:60`, `:108`,
  `persons-table.tsx:141`, `components/table-parts.tsx:44` (4 wystąpienia); w całym `resources/js` poza `components/ui/`
  16 wystąpień w 14 plikach (grep `focus-visible:ring-\[3px\]`). Jedyna wspólna stała, `cardInteractive`
  (`resources/js/components/tree/card-styles.ts:23`), ma inny kształt (halo z offsetem) i jest tylko dla kart drzewa.
- Wpływ: hover i fokus skoków do litery wyglądają inaczej niż każdy inny przycisk obrysowany w aplikacji; następny
  widok skopiuje ciąg klas zamiast stałej, a zmiana tokenu fokusu wymaga edycji 14 plików.
- Kierunek: `Button variant="outline" size="sm" asChild` dla skoków; wspólna stała pierścienia dla wierszy-linków
  (np. w `lib/`), użyta w 4 miejscach widoku. Pozostałe 10 plików poza zakresem — deferred (jeden widok na zmianę).

### C4 — Brakujący token: komunikat błędu daty w klasie palety

- Dowód: `resources/js/components/persons/partial-date-input.tsx:102` — `'text-sm text-red-600 dark:text-red-400'`
  (renderowany w formularzu tworzenia otwieranym z `/persons`); repozytorium ma `InputError`
  (`resources/js/components/input-error.tsx:13`, `text-destructive-foreground`, wartości `.dark` w `resources/css/app.css`)
  i `text-destructive` (np. `sources/source-panel.tsx:513`).
- Wpływ: błąd daty ma inny odcień niż każdy inny błąd walidacji, a tryb ciemny jest obsłużony ręcznie w klasie,
  nie w tokenie — zmiana `--destructive` go nie obejmie.
- Kierunek: token `destructive` (przez `InputError` lub tę samą klasę co `InputError`).

### C5 — Stany: brak stanu błędu i luki ładowania

- Dowód: `resources/js/pages/persons/index.tsx:57-80` — `visitSearch` przekazuje tylko `onStart`/`onCancel`/`onFinish`,
  `visitDisplay` (`:84-95`) żadnych handlerów; brak `onHttpException`/`onNetworkError` w pliku (grep).
  Stan „w trakcie” to tylko `aria-busy` + `opacity-60` na liście wyników (`:197-204`) i spinner w polu (`components/search-field.tsx:30-35`);
  gałąź „brak wyników” (`:194-195`) nie jest przygaszana. Brak `Skeleton` na stronie, choć repo go używa
  (`persons/person-candidate-list.tsx`, `sources/source-picker-dialog.tsx`).
- Wpływ: gdy wyszukiwanie padnie (sieć, 500), spinner gaśnie, a na ekranie zostają stare wyniki bez komunikatu —
  użytkownik bierze je za wynik nowego zapytania; region `role="status"` (`:177`) nic nie ogłasza.
- Kierunek: komunikat błędu przy polu z tokenem `destructive` i „Spróbuj ponownie”; spójne przygaszanie/`aria-busy`
  także dla gałęzi „brak wyników”. Pierwsze ładowanie to blokująca wizyta Inertia z paskiem postępu (`resources/js/app.tsx:32-34`) — N/A dla skeletonu, do zapisania w macierzy.

### Deferred (z powodem)

- **D1 — „Dom” rozdzielony (przed-audyt, zarzut 2).** Logowanie → `/persons` (`config/fortify.php:76`, `bootstrap/app.php:21`),
  logo i pierwsza pozycja menu → `/tree` (`resources/js/components/app-sidebar.tsx:32`, `:72`). Powód odroczenia: świadoma
  decyzja „The logo matches the first menu item, and a new account still lands on a page with „Dodaj osobę””
  (`context/archive/2026-09-30-app-shell-sidebar/plan-brief.md:24`), przypięta `tests/e2e/seed.spec.ts:11-22`.
  Po C1 + pustym stanie drzewa z `tree-add-person-panel` (Faza 3: pierwsza osoba z drzewa) argument „nowe konto musi
  trafić tam, gdzie jest Dodaj osobę” słabnie — warto wrócić do tego osobną zmianą.
- **D2 — Duplikaty w formularzu tworzenia:** lokalny `Card` (`person-create-dialog.tsx:48-64`) zamiast `ui/card.tsx`;
  ręczny awatar „?” (`person-create-dialog.tsx:100-108`) zamiast `PersonAvatar size="md"` (`person-avatar.tsx:17-21`);
  `sm:h-[90vh]` (`:91`) nadpisuje `form-dialog.tsx:23`. Powód: znikną lub zmienią się razem z C1 — ocenić po decyzji o panelu.
- **D3 — Badge „Probant” w dwóch wariantach:** `secondary` w `person-list-parts.tsx:19` i `tree/show.tsx:90`, domyślny w
  `person-modal-header.tsx:64` (panel osoby). Powód: panel osoby to inny widok; ujednolicić przy jego zmianie.
- **D4 — `PersonAvatar` obok `ui/avatar.tsx`.** Powód: działa i jest wspólny dla listy/tabeli/drzewa; zamiana na Radix
  dotyka drzewa (`tree/sheet-person-card.tsx`) — poza jednym widokiem.
- **D5 — Brak paginacji, ciężki payload** (`PersonController.php:35-59` ładuje wszystkie osoby z wydarzeniami).
  Powód: wydajność, nie kontrakt UI; decyzja „bez paginacji” z `context/archive/2026-09-25-person-search/plan-brief.md`.
- **D6 — Tabela na mobile:** menu akcji poza ekranem do przewinięcia w bok (`persons-table.tsx:175-199`, sticky tylko
  pierwsza kolumna `lib/table-styles.ts`). Powód: wymaga zrzutu 375 px, żeby ocenić wagę; sprawdzić w fazie widoku.
- **D7 — `align-[-2px]`** (`person-list-parts.tsx:10`) — optyczne wyrównanie ikony, brak tokenu; za mały, by planować.

## Macierz 7 stanów — stan wyjściowy

| Kontrolka | default | hover | focus-visible | disabled | error | empty | loading |
|---|---|---|---|---|---|---|---|
| Pole wyszukiwania (`search-field.tsx`, `ui/input`) | komponent | `Input` | `Input` ring | brak (nieużywane) | **brak** (C5) | placeholder | spinner w polu |
| Przełącznik Lista/Tabela (`segmented-toggle.tsx`) | komponent | `hover:bg-muted` (`ui/toggle.tsx:8`) | ring w `ui/toggle.tsx:8` | wspierane, nieużywane | brak | N/A | brak (wizyta `only:['filters']`) |
| Nagłówki sortowania (`table-parts.tsx:41-59`) | surowy `<button>` | `hover:bg-accent` | ręczny ring (C3) | N/A | brak | N/A | N/A (sort po stronie klienta) |
| Skoki do litery (`persons-list.tsx:105-111`) | ręczny przycisk (C3) | `hover:bg-accent` | ręczny ring (C3) | N/A | N/A | N/A | N/A |
| Wiersz osoby (lista `:60`, tabela `:141`) | tokeny | `hover:bg-accent/50` / `TableRow hover:bg-muted/50` | ręczny ring (C3) | N/A | N/A | `EmptyState` ×2 (`persons-empty-state.tsx`) | `opacity-60` tylko gdy są wyniki (C5) |
| „Dodaj osobę” (`ui/button`) | komponent | wariant | `Button` ring | wspierane, nieużywane | błędy w modalu, data w palecie (C4) | drugi CTA w pustym stanie | brak |
| Menu akcji w tabeli (`persons-table.tsx:175-199`) | `Button ghost icon` + `aria-label` | ghost | `Button` ring | N/A | N/A | N/A | N/A |

Focus-visible na tabeli nigdy nie był sfotografowany (`context/archive/2026-09-29-persons-list-views/reviews/impl-review.md:22`).

## Detailed Findings

### Źródło → widoki

- Tokeny: `resources/css/app.css` — `:root`/`.dark` → `@theme` (`--color-*`), w tym własne `gender-*`, `tree-line`, `tree-meta`
  (źródła wartości w komentarzach przy blokach: `app-shell-sidebar/tokens.md`, `tree-sheet-variant-c/tokens.md`).
- Komponenty: `resources/js/components/ui/` (shadcn, 29 plików) + współdzielone `empty-state`, `search-field`,
  `segmented-toggle`, `heading`, `table-parts`, `side-panel`. W zakresie widoku: `persons-list.tsx` nie importuje nic z `ui/`
  (wiersze, skoki i nagłówki liter z klas), `persons-table.tsx` importuje `Button`, `DropdownMenu`, `Table`.
- Niewykorzystane, a pasujące: `Button asChild` (skoki), `Skeleton` (ładowanie), `ui/card` (formularz tworzenia, D2).

### Widok → źródło

- Literały w zakresie: `ring-[3px]` ×4 (C3), `text-red-600 dark:text-red-400` ×1 (C4), `sm:h-[90vh]` (D2), `align-[-2px]` (D7).
  Hex/rgb/oklch/inline `style=` w plikach widoku: 0.
- `lib/table-styles.ts` używa `shadow-[1px_0_0_var(--color-border)]` — wartość arbitralna, ale przez token; nie zarzut.

### Punkty wejścia

- Wylogowany → `auth` w `routes/web.php:29` przekierowuje na `/login` (przypięte `tests/e2e/seed.spec.ts:11-22`).
- `verified` w praktyce nie blokuje (`app/Models/User.php` nie implementuje `MustVerifyEmail`) — brak stanu „niezweryfikowany”.
- Pusty widok: `PersonsEmptyTree` (`index.tsx:185-193`) z drugim „Dodaj osobę”; pole i przełącznik ukryte (`index.tsx:144`).
- Parametry z linku są normalizowane w kontrolerze (`PersonController.php:70-95`: `view`, `sort` z białej listy, `q` przycięte
  do 100 znaków) — śmieci w URL nie wywracają widoku; URL nie jest kanonizowany (bez wpływu na UI).
- `?person=<id spoza drzewa>` → 404 całej strony (`app/Http/Middleware/HandleInertiaRequests.php:100`, `findOrFail`) —
  zgodne z regułą „rekord innego użytkownika = 404”; globalne, nie zarzut tego widoku.
- Otwarcie osoby: `PersonPreviewLink` → `?person=ID` z `replace`/`preserveScroll` (`person-preview-link.tsx:46-53`,
  `lib/side-panel-url.ts`); zamknięcie zachowuje przewinięcie i widok (`hooks/use-side-panel-navigation.ts:35-50`).

## Code References

- `resources/js/pages/persons/index.tsx:57-95` — wizyty wyszukiwania/wyświetlania bez obsługi błędów (C5)
- `resources/js/pages/persons/index.tsx:125-141` — wyzwalacz modala i nagłówek `Heading` w wariancie domyślnym (C1, C2)
- `resources/js/pages/persons/index.tsx:145-172` — wiersz wyszukiwania bez `role="search"` (C2)
- `resources/js/pages/persons/index.tsx:177-204` — region statusu, pusty stan, przygaszanie (C5)
- `resources/js/components/heading.tsx:13-32` — `mb-8` + `<h2>` w wariancie domyślnym
- `resources/js/components/persons/persons-list.tsx:60`, `:105-111` — wiersz i skoki do litery (C3)
- `resources/js/components/persons/persons-table.tsx:141` — link w wierszu tabeli (C3)
- `resources/js/components/table-parts.tsx:44` — przycisk sortowania (C3)
- `resources/js/components/persons/partial-date-input.tsx:102` — kolor błędu z palety (C4)
- `resources/js/components/persons/person-create-dialog.tsx:48-108` — modal tworzenia, lokalny Card i awatar (C1, D2)
- `app/Http/Controllers/PersonController.php:106` — przekierowanie po zapisie na drzewo (C1)
- `resources/js/components/tree/card-styles.ts:23` — jedyna wspólna stała fokusu (C3)

## Architecture Insights

- Kontrakt systemu projektowego działa na poziomie kolorów (tokeny + `gender-styles`); dryf jest w warstwie wzorców:
  każda lista składa nagłówek, wyszukiwarkę i pierścień fokusu sama, bez wspólnej stałej — stąd różnice między `/persons`
  a sąsiadami i 16 kopii pierścienia.
- CLAUDE.md nie ma bloku UI o tokenach/komponentach ani wzmianki o kitchen sinku; `.ai/rules` i `lessons.md` nie istnieją.
  Nic nie zachęca do literałów, ale nic też nie wskazuje stałej fokusu czy wariantu nagłówka — krok „Utrwal to” ma co dodać.
- Bramki wizualne w repo to zrzuty per zmiana (`context/archive/*/screenshots/{shots.mjs,verdict.md}`) na danych
  z `database/seeders/TreeViewDemoSeeder.php`; `toHaveScreenshot` nie jest używany w `tests/` (grep). Demo-strona
  istnieje tylko dla powłoki panelu (`routes/web.php:70`, `dev/side-panel`, tylko `local`).

## Historical Context (from prior changes)

- `context/archive/2026-09-28-person-form-in-modal/plan-brief.md:5-8`, `:21` — S-20: dodawanie/edycja osoby ze stron do modala;
  po dodaniu „zostań na liście” (`back()`). **Częściowo nieaktualne**: modal zgadza się z kodem, „zostań na liście” — nie
  (dziś `PersonController.php:106` przekierowuje na drzewo).
- `context/archive/2026-10-05-person-side-panel/research.md:155-156` — S-06: tworzenie zostaje modalem, zapis otwiera panel
  na drzewie. **Zgodne z kodem.**
- `context/changes/tree-add-person-panel/change.md` (Notes), `plan.md:40`, `:367` — „Dodaj osobę” na `/persons` to osobna
  zmiana; reguła CLAUDE.md do przepisania. **Aktualne**; ta zmiana jest tą „osobną zmianą”.
- `context/archive/2026-09-23-account-tree-and-first-person/plan-brief.md:30` — lista osób jako Fortify `home`; uzasadnienie
  cytuje „FR-003”, które w dzisiejszym PRD oznacza numer wpisu (`context/foundation/prd.md:101`) — **numer nieaktualny**,
  decyzja o `home` aktualna.
- `context/archive/2026-09-30-app-shell-sidebar/plan-brief.md:24` — logo → `/tree`, cel po logowaniu `/persons`. **Zgodne z kodem.**
- `context/archive/2026-09-29-persons-list-views/plan.md` — dwa widoki w URL, grupowanie po literze, skoki, badge Probant;
  stan „w trakcie” = spinner + przygaszenie; werdykt zrzutów uznał wygląd skoków za kosmetyczny
  (`screenshots/verdict.md`). Brak decyzji o stanie błędu.
- `context/archive/2026-09-25-person-search/plan-brief.md` — wyszukiwanie po stronie serwera, debounce 300 ms, max 100 znaków,
  bez paginacji. **Zgodne z kodem** (`index.tsx:113`, `:153`).
- `context/foundation/prd.md:155` — FR-019: osoba oglądana i edytowana w panelu zamiast modala; o tworzeniu nie mówi.

## Related Research

- `context/changes/tree-add-person-panel/research.md` — panel dodawania na drzewie (wspólne `PersonFormFields`, `?add=`).
- `context/archive/2026-10-05-person-side-panel/research.md` — panel osoby.
- `context/archive/2026-09-29-persons-list-views/` — obecny kształt listy i tabeli.

## Decisions (użytkownik, 2026-10-11)

1. **C1:** tworzenie osoby z `/persons` przechodzi do panelu bocznego; po zapisie panel pokazuje podgląd nowej osoby,
   lista zostaje (odwraca S-06, `context/archive/2026-10-05-person-side-panel/research.md:155-156`). Reużyć panel
   dodawania z `tree-add-person-panel`; implementacja po zamknięciu jego Fazy 2. Zmiana dotyka przekierowania
   `PersonController.php:106` (i asercji `tests/Feature/PersonsTest.php:444`, która je przypina).
2. **C3:** wspólna stała pierścienia fokusu tylko w 4 miejscach widoku `/persons`; pozostałe 10 plików → deferred.
3. **Bramka wizualna:** zrzuty per zmiana (`screenshots/{before,after}` + `shots.mjs` + `verdict.md` w folderze zmiany,
   dane z `TreeViewDemoSeeder`); bez `toHaveScreenshot` w repo.

## Open Questions

1. Potwierdzić zrzutem „przed” (desktop + 375 px): przesunięcie przycisku przez `mb-8` (C2) i wagę D6.
2. Plan: jak panel tworzenia na `/persons` współdzieli klucz URL z panelem dodawania drzewa (`?add=` zakłada cel —
   rodzic/dziecko/małżonek; na liście celu brak) — rozstrzygnąć po lekturze kodu Fazy 2.
