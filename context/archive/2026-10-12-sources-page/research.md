---
date: 2026-10-12T07:03:01+02:00
researcher: Claude (Opus 5.5)
git_commit: e3ea45e15212a756ab108d8b7df74cd906cf89e2
branch: develop
repository: root-keeper
topic: "Audyt /10x-ui widoku /sources — zarzuty względem systemu projektowego"
tags: [research, ui, sources-index, design-tokens, focus, states]
status: partial
last_updated: 2026-10-12
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika dla C3, C4 i C5"
---

# Research: Audyt /10x-ui widoku /sources

**Date**: 2026-10-12T07:03:01+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: e3ea45e15212a756ab108d8b7df74cd906cf89e2 (drzewo robocze czyste poza `context/changes/sources-page/`)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Dwukierunkowy audyt (źródło → widoki, widok → źródło) jednego widoku `/sources`
(`resources/js/pages/sources/index.tsx` + `resources/js/components/sources/sources-{filters,gallery,table,empty-state}.tsx`,
`source-thumbnail.tsx`) w trzech kategoriach /10x-ui: brakujące tokeny, brakujący współdzielony komponent,
przypadkowa architektura. Wynik: 3–5 zarzutów z plikiem, linią i wpływem na użytkownika (`## Charges`),
pozycje odroczone z powodem, wyjściowa macierz 7 stanów. Weryfikacja 5 wstępnych zarzutów z `change.md`.
Źródło tokenów: `resources/css/app.css`; wariant kontraktu: istniejący system projektowy.

## Summary

W sprawdzonych 6 plikach widoku kolory, odstępy i promienie pochodzą z tokenów: skan literałów daje
2 trafienia, oba `focus-visible:ring-[3px]` (`sources-gallery.tsx:45`, `sources-table.tsx:145`); brak hexów,
klas palety i `style=`. Widok ma już wzorce list: `Heading variant="page"`, `role="search"`, `EmptyState`,
`SortableHead` z `focusRing`, sr-only `role="status"` (`index.tsx:347-357`, `:358`, `:449-451`).

Problemy leżą w stanach, nie w kolorach:

1. **Fokus** — pierścień jest pisany ręcznie (2 miejsca), a na **zaznaczonej** karcie galerii fokus klawiatury
   jest niewidoczny: w zbudowanym CSS reguły `data-[state=selected]:ring-2/ring-primary` stoją po
   `focus-visible:ring-[3px]/ring-ring/50` i je nadpisują.
2. **Stan zaznaczenia** — trzy różne zapisy „otwartego” rekordu (karta galerii, wiersz tabeli, karta drzewa);
   w tabeli poniżej `md` lepka pierwsza kolumna (`max-md:bg-background`) zakrywa tło zaznaczenia (wniosek z klas).
3. **Błąd** — nieprawidłowy rok jest po cichu pomijany; odwrócony zakres daje „Żadne źródło nie pasuje…” bez
   wskazówki; nieudana wizyta filtra nie ma obsługi (na `/persons` jest komunikat i „Spróbuj ponownie”).
4. **Mobile** — pasek wyszukiwania na 375 px ma 5–6 wierszy (szacunek ok. 290–340 px), pierwszy wynik zaczyna się
   ok. 530–580 px od góry (wniosek z klas, niezmierzony). Ograniczenie: decyzja S-10 „wszystko widoczne, jeden klik”.
5. **Puste / wyłączone / ładowanie** — błędny tekst `SourcesEmptyKind` przy aktywnych filtrach innych niż szukanie,
   wyłączone kontrolki bez wyjaśnienia, brak wskaźnika oczekiwania, uszkodzona miniatura bez fallbacku.

Wstępny zarzut 2 z `change.md` (karta galerii vs `ui/card.tsx`) **odrzucony jako zarzut w tej postaci**:
`Card` (`ui/card.tsx:10`) to statyczny kontener z `py-6 gap-6 rounded-xl`, w repo nigdzie nie jest linkiem
(użycia: `two-factor-recovery-codes.tsx:56`, `source-panel.tsx:526`), a treść karty galerii jest ustaloną decyzją
(LV `plan.md:51-59`, GF `plan.md:23`). Realny problem to brak wspólnego stanu zaznaczenia — przeniesiony do C2.

**Luka (status: partial):** brak zrzutów „przed”. Serwer E2E (127.0.0.1:8100) nie działał; jego start
(`scripts/e2e-server.sh`) buduje front i podnosi osobny kontener, a deweloperska baza ma jedno konto z 1 źródłem
(konto użytkownika, bez znanego hasła). Twierdzenia oznaczone „wniosek z klas” (mobile, lepka kolumna, uszkodzona
miniatura) trzeba potwierdzić zrzutem na początku `/10x-implement`, tak jak w `persons-index-ui`.

## Charges

Każdy zarzut: kategoria, dowód `plik:linia`, wpływ na użytkownika, kierunek naprawy. Kolejność ≈ wpływ.

### C1 — Brakujący współdzielony styl + focus-visible: ręczny pierścień i niewidoczny fokus na zaznaczonej karcie

- **Dowód:** `resources/js/components/sources/sources-gallery.tsx:45` i `resources/js/components/sources/sources-table.tsx:145`
  powtarzają `outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px]` zamiast `focusRing`
  (`resources/js/lib/focus-ring.ts:7-8`; wymóg w `CLAUDE.md` „UI: tokens and shared components”). Odpowiednik na
  `/persons`: `cn(focusRing, '-m-1 flex items-center gap-3 rounded-md p-1')` (`persons-table.tsx:142-145`).
- **Dowód (stan):** `sources-gallery.tsx:46` — `data-[state=selected]:ring-2 data-[state=selected]:ring-primary`.
  W `public/build/assets/app-Cc_OuG6h.css` reguła `.focus-visible\:ring-\[3px\]` jest na pozycji 61869,
  `.focus-visible\:ring-ring\/50` na 62474, a `.data-\[state\=selected\]\:ring-2` na 76896 i `…:ring-primary` na 77204 —
  przy równej specyficzności wygrywa późniejsza, więc zaznaczona karta z fokusem wygląda jak zaznaczona bez fokusu.
- **Wpływ:** użytkownik klawiatury po zamknięciu panelu (fokus wraca na otwartą kartę) albo przechodząc Tabem przez
  galerię nie widzi, gdzie jest fokus na karcie otwartego źródła.
- **Naprawa:** `focusRing` w obu miejscach; zaznaczenie bez pierścienia (np. `border-primary` + `bg-accent`) albo fokus
  z offsetem, tak by oba stany były rozróżnialne. Dodać pliki `/sources` do skanu tokenów (patrz „Utrwal to”).

### C2 — Brakujący współdzielony wzorzec: trzy zapisy stanu „otwarty rekord”, w tabeli mobilnej zaznaczenie znika

- **Dowód:** karta galerii: `border-primary bg-accent ring-2 ring-primary` (`sources-gallery.tsx:46`); wiersz tabeli:
  `data-[state=selected]:bg-muted` (`resources/js/components/ui/table.tsx:58`) + podkreślenie nazwy
  (`sources-table.tsx:153-158`); karta drzewa: `border-2 border-primary shadow-sm` (`tree/card-styles.ts:26`).
  Lepka kolumna: `stickyCell` = `max-md:bg-background max-md:group-hover:bg-muted` (`resources/js/lib/table-styles.ts:7`),
  bez wariantu dla `data-state=selected`.
- **Wpływ:** w tabeli poniżej 768 px pierwsza komórka otwartego wiersza ma tło strony, a reszta wiersza `bg-muted` —
  po przewinięciu poziomym widać tylko lepką kolumnę, więc nie widać, który rekord jest otwarty; na hover lepka
  komórka jest ciemniejsza (`muted`) niż reszta (`muted/50`). Wniosek z klas — do potwierdzenia zrzutem.
- **Naprawa:** jedna stała stanu zaznaczenia/hover dla elementów listy (obok `focusRing`/`table-styles.ts`),
  `stickyCell` z `group-data-[state=selected]:bg-muted`. Bez nowego tokenu koloru (role `accent`/`muted`/`primary` wystarczą).

### C3 — Stan błędu: zły rok pomijany po cichu, odwrócony zakres bez wyjaśnienia, nieudana wizyta bez komunikatu

- **Dowód:** pola „Rok od/Rok do” to `Input type="number" min=1 max=9999` bez `aria-invalid` ani komunikatu
  (`sources-filters.tsx:101-127`); `parseYearInput` zwraca `null` dla wartości spoza 1–9999 i nie-cyfr
  (`resources/js/lib/sources-index.ts:168-178`) — pole pokazuje „12345”, a filtr nie działa. Odwrócony zakres:
  `matchesYears` nic nie dopasowuje, liczniki rodzajów spadają do 0 (trzy segmenty wyłączone), widok pokazuje
  `SourcesFilteredNoResults` „Żadne źródło nie pasuje do wybranych filtrów.” (`sources-empty-state.tsx:48-75`).
  Wizyty `router.get` w `visit()` (`index.tsx:152-161`) nie mają `onHttpException`/`onNetworkError`; `/persons`
  ma `failureHandlers` + `InputError role="alert"` + „Spróbuj ponownie” (`pages/persons/index.tsx:54-69`, `:285-296`).
- **Wpływ:** genealog wpisujący zakres „1900–1850” albo literówkę „18500” widzi pustą lub nieprzefiltrowaną listę
  i nie wie dlaczego; przy zerwanym połączeniu klik filtra „nic nie robi”.
- **Naprawa:** komunikat przy polach roku (token `destructive`, `InputError`, `aria-invalid`) dla roku spoza zakresu
  i dla „od > do” — **bez** zamiany wartości (decyzja GF `plan.md:25`, `reviews/plan-review.md:32-35`);
  `failureHandlers` jak na `/persons`.

### C4 — Przypadkowa architektura (układ): na telefonie filtry zajmują pierwszy ekran

- **Dowód:** wiersz `role="search"` (`index.tsx:358-447`) to `flex flex-wrap gap-3` z wyszukiwarką, dwoma przełącznikami,
  czterema polami (`sources-filters.tsx:55-149`: place `w-full`, lata `w-28`, archiwum `w-full`) i warunkowym
  „Wyczyść filtry” (`:151-157`, `order-8`). Pola z etykietą nad sobą (ok. 56 px) i kontrolki bez etykiety (36 px)
  stoją w jednym `items-center`.
- **Wpływ (wniosek z klas, niezmierzony):** na 375 px pasek ma 5 wierszy (ok. 290 px), z „Wyczyść filtry” 6 (ok. 340 px);
  pierwszy wynik zaczyna się ok. 530–580 px od góry, więc na ekranie 667 px nie widać pełnej karty. Pojawienie się
  „Wyczyść filtry” dodaje/usuwa cały wiersz i przesuwa listę. Na desktopie etykietowane i nieetykietowane kontrolki
  nie wyrównują się do jednej linii bazowej.
- **Ograniczenie:** S-10 świadomie trzyma wszystkie filtry w pasku („Wszystko widoczne, jeden klik”, GF `plan-brief.md:39`,
  `plan.md:24`), check 3.10 „pasek zawija się czytelnie przy 375 px” odhaczony (GF `plan.md:340`); PRD nie wymaga
  mobile dla list (`foundation/prd.md:212`). Zwinięcie filtrów to decyzja użytkownika (Open Questions).

### C5 — Stany puste, wyłączone i ładowania: błędny tekst, brak wyjaśnień i wskaźnika

- **Puste:** `SourcesEmptyKind` przy pustym `q` mówi „Nie masz jeszcze {aktów}.” (`sources-empty-state.tsx:74-101`, tekst `:91`),
  choć warunek (`index.tsx:320-323`, `:462`) zachodzi też, gdy rodzaj jest pusty tylko przez filtr miejsca/roku/archiwum —
  użytkownik z aktami w innej parafii czyta, że nie ma żadnych aktów; jedyna akcja to „Pokaż wszystkie rodzaje”.
- **Wyłączone:** segmenty rodzaju z licznikiem 0 (`index.tsx:387-405`) i selecty bez opcji (`sources-filters.tsx:63`, `:135`)
  mają `opacity-50` bez wyjaśnienia; `Label` nad wyłączonym selectem nie przygasa, bo `peer-disabled`
  (`ui/label.tsx:14`) wymaga poprzedzającego rodzeństwa `peer`, a etykieta stoi przed triggerem.
- **Ładowanie:** `SearchField` dostaje `busy` na `/persons` (`pages/persons/index.tsx:258`), na `/sources` nie
  (`index.tsx:363-370`); przełączniki i selecty są sterowane przez `filters` z serwera, więc klik zmienia stan dopiero
  po odpowiedzi.
- **Uszkodzona miniatura:** `<img>` bez `onError` (`source-thumbnail.tsx:27-33`), `thumbnail()` zwraca 404 przy braku pliku
  (`SourceController.php:483-489`); z `alt=""` w galerii wynik to prawdopodobnie pusta ramka `bg-muted` zamiast ikony
  `ImageOff` (wniosek — do zrzutu).
- **Wpływ:** mylący komunikat w stanie pustym, brak informacji „dlaczego nie mogę kliknąć” i „czy coś się dzieje”.
- **Naprawa:** tekst `SourcesEmptyKind` zależny od aktywnych filtrów (+ „Wyczyść filtry” gdy są), `busy` na `SearchField`,
  `onError` → fallback `ImageOff`; dla wyłączonych kontrolek — `title`/opis albo zostawić (Open Questions).

### Deferred (z powodem)

- **D1 — ręczny pierścień fokusu poza `/sources`:** `parishes-table.tsx:68`, `localities-table.tsx:67`, `password-input.tsx:25`,
  `marriage-panel.tsx:70`, `family-tab.tsx:130`, `person-photo-menu.tsx:90`, `overview-tab.tsx:143`, `:209`,
  `person-candidate-list.tsx:132`, `tree/card-styles.ts:23` (10 miejsc). Powód: jeden widok na zmianę (jak PI `verdict.md:33`).
- **D2 — dwa przyciski „Dodaj źródło” przy pustym drzewie** (`index.tsx:355`, `:460`): ten sam wzorzec co `/persons`
  (`pages/persons/index.tsx:307`); zmiana tylko razem z sąsiadami.
- **D3 — `?source=<cudze lub nieistniejące id>` daje 404 całej strony** (`SourceController.php:81-83`, testy
  `SourcesTest.php:98`, `OwnershipBoundariesTest.php:246`): wymagane przez `CLAUDE.md` (`denyAsNotFound`). Ewentualna
  własna strona błędu Inertia to zmiana aplikacyjna, nie tego widoku.
- **D4 — `cursor: default` na przyciskach** (preflight Tailwind v4, brak reguły w `app.css`): dotyczy całej aplikacji.
- **D5 — `max-md:shadow-[1px_0_0_var(--color-border)]` w `lib/table-styles.ts:3`, `:7`:** współdzielona stała, nie widok;
  regex skanu (`[\w:.-]*-\[-?\d+px\]`) jej nie łapie. Zostaje, dopóki nie ma tokenu cienia.
- **D6 — rozjazd parsowania roku serwer/klient** (`+1850`, spacje; GF `reviews/impl-review.md:126`): logika, nie UI.
- **D7 — nieistniejący `place`/`archive` zostaje w URL do następnej wizyty** (`sources-index.ts:146-165`): kosmetyka adresu.
- **D8 — `style={{ width }}` paska postępu w `source-panel.tsx:333`:** wartość dynamiczna, panel poza zakresem.
- **D9 — przepełnienie przełącznika rodzajów przy 3-cyfrowych licznikach na 375 px** (wniosek; LV `verdict.md:122`
  zostawił węższy wiersz jako decyzję): sprawdzić zrzutem, naprawiać tylko przy potwierdzonym ucinaniu.

## Macierz 7 stanów — stan wyjściowy

| Stan | Dziś | Dowód | Luka |
|---|---|---|---|
| default | tokeny + komponenty repo; 2 literały `ring-[3px]` | `sources-gallery.tsx:45`, `sources-table.tsx:145` | C1 |
| hover | `hover:bg-accent/50` (karta), `hover:bg-muted/50` (wiersz), sort `hover:bg-accent` — tokeny | `sources-gallery.tsx:45`, `ui/table.tsx:58`, `table-parts.tsx:47` | lepka komórka ciemniejsza niż wiersz < md (C2) |
| focus-visible | `ui/*` mają własny pierścień; `SortableHead` z `focusRing`; karta i link wiersza ręcznie | `table-parts.tsx:46`, `lib/focus-ring.ts:7-8` | niewidoczny na zaznaczonej karcie (C1) |
| disabled | segmenty z licznikiem 0, selecty bez opcji: `opacity-50` | `index.tsx:387-405`, `sources-filters.tsx:63`, `:135` | brak wyjaśnienia, `Label` nie przygasa (C5) |
| error | brak: zły rok pomijany, wizyta bez obsługi błędu | `sources-index.ts:168-178`, `index.tsx:152-161` | C3 |
| empty | 4 warianty `EmptyState` + komunikat „Ukryto N…” | `sources-empty-state.tsx:14-101`, `index.tsx:453-478` | błędny tekst `SourcesEmptyKind` (C5) |
| loading | pierwsze wejście blokujące (skeleton N/A, jak PI `research.md:123`); miniatury z zarezerwowanym miejscem | `sources-gallery.tsx:52`, `sources-table.tsx:150-151` | brak `busy`, klik bez natychmiastowej reakcji, przesunięcie przez „Wyczyść filtry” (C4, C5) |

## Detailed Findings

### Źródło → widoki

- Tokeny: `resources/css/app.css:10-72` (`@theme`), `.dark` (`:124-167`) redefiniuje wszystkie kolory; brak tokenów
  odstępów i tokenu „selected”/„focus”. 14 użyć klas tokenów i 14 importów współdzielonych komponentów w 6 plikach widoku.
- `focusRing` importują tylko `table-parts.tsx:3`, `persons-list.tsx:9`, `persons-table.tsx:26`.
- `persons-view-tokens.test.ts:15-23` skanuje 7 plików `/persons` + `table-parts.tsx`; żadnego pliku `/sources`.
  Po dopisaniu plików `/sources` test złapałby tylko 2 trafienia `ring-[3px]`.

### Widok → źródło

- `ring-[3px]` ×2 → `focusRing` (C1). `aspect-[3/2]` (`sources-gallery.tsx:52`) to proporcja, nie piksel — skan go nie łapie i nie powinien.
- Karta galerii (`sources-gallery.tsx:45-46`) — brak odpowiednika w `ui/`; `Card` nie pasuje (statyczny, `py-6 gap-6`).

### Struktura względem sąsiednich list

- Nagłówek i wrapper tabeli (`rounded-lg border`) identyczne z `/persons`, `/parishes`, `/localities`.
- Tylko `/sources` ma pasek filtrów, „Wyczyść filtry”, kolumnę akcji i galerię z container queries.
- `/persons` ma obsługę błędu wizyty i `aria-busy` na wynikach; `/sources`, `/parishes`, `/localities` nie.

### Punkty wejścia

- Wylogowany → `/login` (grupa `auth`+`verified`, `routes/web.php:29`; `SourcesTest.php:20`).
- `?source=abc` → panel `null` po cichu (`SourceController.php:77-79`); `?source=new` → pusty panel.
- Złe parametry (`kind`, `view`, `year_*`, `place`) normalizowane po cichu (`SourceController.php:343-403`), bez FormRequest.

## Code References

- `resources/js/components/sources/sources-gallery.tsx:45-46` — karta: ręczny pierścień, stan zaznaczenia
- `resources/js/components/sources/sources-table.tsx:145` — link wiersza z ręcznym pierścieniem
- `resources/js/lib/focus-ring.ts:7-8` — wspólna stała fokusu
- `resources/js/lib/table-styles.ts:3`, `:7` — lepka kolumna bez wariantu zaznaczenia
- `resources/js/components/sources/sources-filters.tsx:101-127` — pola roku bez stanu błędu
- `resources/js/lib/sources-index.ts:168-178` — `parseYearInput`
- `resources/js/pages/sources/index.tsx:152-161` — `visit()` bez obsługi błędu
- `resources/js/pages/persons/index.tsx:54-69`, `:258`, `:285-296` — wzorzec błędu i `busy`
- `resources/js/components/sources/sources-empty-state.tsx:81-101` — `SourcesEmptyKind`
- `resources/js/components/sources/source-thumbnail.tsx:27-33` — `<img>` bez `onError`
- `resources/js/components/ui/label.tsx:14` — `peer-disabled`
- `resources/js/lib/persons-view-tokens.test.ts:15-23` — lista plików skanu

## Architecture Insights

Widok `/sources` wyprzedza sąsiadów funkcjonalnie (filtry, galeria), ale nie dostał dwóch rzeczy, które `/persons`
zyskał w `persons-index-ui`: `focusRing` i obsługi błędu wizyty. Stan „otwarty rekord” nie ma wspólnego zapisu —
każda lista i drzewo definiują go po swojemu, a lepka kolumna tabeli (wspólna z `/persons`) go nie zna.

## Historical Context (from prior changes)

- `context/archive/2026-10-09-sources-grid-with-filters/plan-brief.md:39` — wszystkie filtry w pasku (ogranicza C4)
- `context/archive/2026-10-09-sources-grid-with-filters/plan.md:25`, `reviews/plan-review.md:32-35` — odwrócony zakres bez zamiany (ogranicza C3)
- `context/archive/2026-10-09-sources-grid-with-filters/reviews/impl-review.md:111-119` — „Wyczyść filtry” ukryty, gdy pusty stan ma własny (F3; C4 nie może pokazać obu)
- `context/archive/2026-10-09-sources-grid-with-filters/plan.md:196`, `:204` — etykiety „Parafia / miejsce”, „Rok od”, „Rok do”, „Archiwum” i tekst „Znaleziono: N…” są kontraktem E2E
- `context/archive/2026-09-30-sources-list-views/plan.md:51-59`, `:119` — treść karty, segment z licznikiem 0 wyłączony
- `context/archive/2026-09-30-sources-list-views/research.md:159` — liczniki w kolorze `foreground` (kontrast na wybranym tle)
- `context/archive/2026-10-11-persons-index-ui/research.md:101`, `verdict.md:33` — `focusRing` dla pozostałych plików odroczony (C1 go podejmuje)
- `context/archive/2026-10-11-persons-index-ui/screenshots/shots.mjs` — wzorzec bramki zrzutów (E2E 8100, 1440×900 i 375×812, log `:focus-visible`, `page.route` abort)

## Related Research

- `context/archive/2026-10-11-persons-index-ui/research.md` — ten sam audyt dla `/persons`
- `context/archive/2026-10-09-sources-grid-with-filters/research.md`, `context/archive/2026-09-30-sources-list-views/research.md`

## Decisions (użytkownik, 2026-10-12)

- **C4 — filtry na telefonie:** zwinąć poniżej `sm` (640 px). Na desktopie pasek bez zmian (S-10 zostaje);
  poniżej `sm` pola „Parafia / miejsce”, „Rok od”, „Rok do”, „Archiwum” chowają się pod przyciskiem „Filtry (N)”
  z licznikiem aktywnych filtrów. Etykiety pól (kontrakt E2E) bez zmian. Zmienia S-10 tylko dla wąskiego ekranu.
- **C3 — błąd roku:** komunikat przy polach roku (`InputError`, `aria-invalid`, token `destructive`) dla roku spoza
  1–9999 i dla „Rok od” > „Rok do”; wartości nie są zamieniane (GF `plan.md:25`); pusty stan bez zmian.
- **C5 — stan wyłączony:** tylko poprawki — etykieta nad wyłączonym selectem przygasa razem z nim; bez tooltipów i opisów.
  Pozostałe części C5 (tekst `SourcesEmptyKind`, `busy`, fallback miniatury) bez osobnej decyzji — kierunek naprawy jak w zarzucie.

## Open Questions

1. **Zrzuty „przed”:** do zrobienia na początku `/10x-implement` (serwer E2E 8100 + dane demo ze źródłami, w tym jedno
   bez pliku miniatury); potwierdzają wnioski z klas w C2, C4, C5 i D9.
2. **C4 — kształt „Filtry (N)” poniżej `sm`:** rozwijany blok w miejscu (Collapsible z `ui/`) czy panel — do ustalenia
   w `/10x-plan`; rozwinięcie w miejscu nie wymaga nowej zależności (`resources/js/components/ui/collapsible.tsx`).
