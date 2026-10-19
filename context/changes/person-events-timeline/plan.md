# Oś czasu z wiekiem osoby w zakładce Wydarzenia — plan implementacji

## Overview

Zakładka Wydarzenia panelu osoby (`resources/js/components/persons/events-tab.tsx`) dziś pokazuje
płaską listę `divide-y`. Zamieniamy ją na pionową oś czasu: rok w wąskiej kolumnie po lewej, pod
nim wiek osoby w chwili wydarzenia, znacznik na linii, treść po prawej. Wiek liczy serwer
(istniejący `Age`), front tylko go formatuje (`formatAge`). Oś jest wspólnym komponentem
`components/timeline.tsx` zbudowanym z istniejących tokenów; kontrakt utrwalają
`view-tokens.test.ts` i reguła w `CLAUDE.md`.

## Current State Analysis

Z `research.md` (zarzuty C1–C5, D1–D2):

- **C1** — `events-tab.tsx:46-50` to `<ul className="divide-y">`; w repo i w DS nie ma komponentu osi.
- **C2** — payload wpisu (`TimelineEntryResource.php:28-44`, `types/person.ts:116-125`) nie niesie
  wieku ani samego roku; `TimelineEntry` (`app/Support/TimelineEntry.php:18-22`) nie zna oglądanej
  osoby, ale `PersonTimeline::for(Person $person)` ją ma (`PersonTimeline.php:21`).
- **C3** — wpisy bez daty pokazują „data nieznana” w miejscu daty (`events-tab.tsx:26`); pochowanie
  nigdy nie ma własnej daty i sortuje się kluczem zgonu (`PersonTimeline.php:142-155`).
- **C4** — link do krewnego (`events-tab.tsx:18-20`) nie ma stylu linku ani `focusRing`
  (wzór: `overview-tab.tsx:146`).
- **C5** — `view-tokens.test.ts:20-57` nie obejmuje `events-tab.tsx`.
- **D1** (stan pusty) i **D2** (licznik zakładki) — odroczone w research.

Tokeny: 0 literałów w widoku; wszystkie role potrzebne osi istnieją (`--tree-line`,
`--border-width-line`, `--primary`, `--muted-foreground`, `--border`, `app.css:70-73,85-96,120`).

## Desired End State

Po otwarciu panelu osoby → Wydarzenia użytkownik widzi oś czasu:

- Każdy wiersz: kolumna roku (rok; przy dacie przybliżonej małe „ok.” nad rokiem; pod rokiem wiek
  wyciszonym kolorem, np. „20 lat”, „poniżej roku”), znacznik na pionowej linii, po prawej etykieta
  (z linkiem do krewnego), a pod nią pełna data (tylko gdy ma miesiąc) · miejsce.
- Znacznik pełny `bg-primary` dla własnych wydarzeń (urodzenie, małżeństwo, zgon),
  `bg-tree-line` dla wydarzeń dzieci i małżonków; pusty (○) dla pochowania i wpisów bez daty.
- Wpis bez daty ma „?” w kolumnie roku; pochowanie ma pustą kolumnę roku.
- Odcinek linii między dwoma znacznikami jest przerywany, gdy którykolwiek z nich jest pusty; linia
  zaczyna się na pierwszym i kończy na ostatnim znaczniku.
- Wiek nie pojawia się przy: własnym urodzeniu, wpisach bez roku, wydarzeniach przed urodzeniem,
  ani przy żadnym wierszu po wierszu własnego zgonu w kolejności osi (w tym pochowanie).
- Link do krewnego ma styl linku (`text-primary`, hover underline) i widoczny `focusRing`.

Weryfikacja: Pest (`PersonTimelineTest`), Vitest (helper wiersza + `view-tokens`), istniejący E2E
zielony bez zmian, zrzuty light/dark 1366×768 i 390×844 z `verdict.md`.

### Key Discoveries:

- `Age::from($birth, $eventDate, isDeceased: true, $today)` liczy wiek na dowolną datę; brak roku po
  którejkolwiek stronie i wynik ujemny dają `null` (`app/Support/Age.php:25-60,71-73`); limit 110 lat
  działa tylko dla `isDeceased = false` (`:86-93`), stąd strażnik „po zgonie” na poziomie osi.
- `isApproximate` nie zmienia wieku (`tests/Unit/AgeTest.php:84-87`); przy wieku bez „ok.”
  (`archive/2026-10-05-maiden-name-and-age/plan-brief.md`).
- Urodzenie i zgon oglądanej osoby są już w pamięci (`BuildPersonPreview.php:29`) — wiek nie dodaje
  zapytań (test liczby zapytań `PersonTimelineTest.php:283-323`).
- `cn()` (tailwind-merge) traktuje `border-line` jako kolor i zjada `border-tree-line`;
  `family-sheet.tsx:3` używa `clsx` (`research.md`, sekcja wzorców).
- E2E `tests/e2e/person-events-and-note-tabs.spec.ts:84-90` wymaga `listitem` z „Urodzenie córki” i
  „1990” oraz linku o nazwie dziecka.
- Frontend nie ma testów komponentów; logika trafia do czystych helperów w `resources/js/lib/*.test.ts`.

## What We're NOT Doing

- Stan pusty zakładki (D1) i licznik zakładki (D2) — odroczone, spójność z innymi zakładkami.
- Zmiana zakresu wierszy osi i klucza sortowania (`PersonTimeline::sortKey`) — bez zmian (PRD FR-021).
- Fraza „w wieku …”, nawias przy wieku, „ok.” przy wieku — wiek to sam tekst `formatAge`.
- Wiek przy pochowaniu i przy wydarzeniach po własnym zgonie — wiersz zostaje, bez wieku.
- Oznaczenie „po śmierci” przy wierszach po zgonie.
- Ikony typu wydarzenia w znacznikach.
- Nowe tokeny w `app.css`, nowe zależności, `toHaveScreenshot`.
- Nowy E2E na wiek (pokrywa Pest).
- Zmiana `Person::age()` i wieku w nagłówku.

## Implementation Approach

Trzy fazy od danych do widoku. Faza 1 dokłada do payloadu `year`, `is_approximate`,
`date_has_month` i `age`, z regułą wieku liczoną po sortowaniu (bo „po zgonie” zależy od kolejności).
Faza 2 buduje czysty helper, który zamienia wpisy na wiersze osi (znacznik, kolumna, odcinki), oraz
prezentacyjny komponent `Timeline` niezależny od domeny osoby. Faza 3 podłącza zakładkę, poprawia
link, utrwala kontrakt i przechodzi przez bramkę wizualną.

## Critical Implementation Details

- **Kolejność:** wiek liczony dopiero na posortowanej liście — strażnik „po zgonie” to „każdy wiersz
  za wierszem własnego zgonu (`role = self`, typ `Death`)”, a nie porównanie dat. Przykład: zgon
  osoby „1960”, zgon syna „05.1960” → syn sortuje się za zgonem → bez wieku; zgon syna „1960” →
  ranga 3 < 5, przed zgonem → z wiekiem 60 lat.
- **Klasy linii:** kombinacje `border-l-line` + `border-tree-line` (+ `border-dashed`) składać przez
  `clsx` lub statyczny string, nie `cn` — inaczej kolor linii znika.

## Phase 1: Rok i wiek we wpisie osi (backend)

### Overview

Każdy wpis osi niesie wiek oglądanej osoby w chwili wydarzenia oraz rok i precyzję daty potrzebne
kolumnie roku.

### Changes Required:

#### 1. Nazwany konstruktor wieku na datę

**File**: `app/Support/Age.php`

**Intent**: Dodać czytelne wejście „wiek w chwili X”, żeby oś nie wołała `from()` z mylącymi
`$death`/`$isDeceased` i nieużywanym `$today`.

**Contract**: `public static function at(?PartialDate $birth, ?PartialDate $moment): ?self` —
deleguje do `from($birth, $moment, true, CarbonImmutable::now())`; reguły bez zmian.

#### 2. Wiek we wpisie osi

**File**: `app/Support/TimelineEntry.php`

**Intent**: Wpis przenosi policzony wiek do resource'u.

**Contract**: konstruktor dostaje czwarty parametr `public ?Age $age = null`; `rank()` bez zmian.

#### 3. Liczenie wieku na posortowanej osi

**File**: `app/Support/PersonTimeline.php`

**Intent**: Po sortowaniu w `for()` przejść listę i odtworzyć wpisy z wiekiem według reguły z
„Critical Implementation Details”; bez dodatkowych zapytań.

**Contract**: `for(Person $person): list<TimelineEntry>` — kolejność i zakres wierszy bez zmian.
Wiek = `Age::at($person->birth()?->date, $entry->event->date)`, z wyjątkami → `null`:
własne urodzenie (`role = self`, typ `Birth`); każdy wiersz za wierszem własnego zgonu.
Wiersz własnego zgonu dostaje wiek. Pozostałe `null` wynikają z `Age` (brak roku urodzenia lub
wydarzenia, wydarzenie przed urodzeniem). Docblock klasy opisuje regułę.

#### 4. Pola payloadu

**File**: `app/Http/Resources/TimelineEntryResource.php`

**Intent**: Wystawić rok, precyzję i wiek; `date` zostaje jak jest (pełny tekst, używany przez
testy i treść wiersza).

**Contract**: dodać do tablicy i do PHPDoc `@return`:
`year: int|null` (`date_year`), `is_approximate: bool`, `date_has_month: bool`
(`date_month !== null`), `age: array{unit: …, value: int|null}|null` (`Age::toArray()`).

#### 5. Testy osi

**File**: `tests/Feature/PersonTimelineTest.php` (oraz `tests/Unit/AgeTest.php` dla `Age::at`)

**Intent**: Pokryć regułę wieku na istniejącej scenie i przypadkach brzegowych, bez psucia
istniejących asercji `timelineRows`.

**Contract**: osobny helper (wzór surowego dostępu `:267-281`) zwracający `[label, age]`; helper
`timelineRows` bez zmian. Przypadki:
- scena Adama (ur. 1900): Urodzenie → `null`; Małżeństwo 1920 → 20 lat; Urodzenie syna 1925 → 25;
  Urodzenie córki 1928 → 28; Małżeństwo 1930 → 30; Zgon żony 1950 → 50; Zgon 1960 → 60;
  Pochowanie → `null`; Zgon syna 1990 → `null`;
- zgon osoby „1960”, zgon dziecka „05.1960” → `null`; zgon dziecka „1960” → 60 lat;
- osoba bez roku urodzenia → wszystkie `null`; wpis bez roku → `null`;
- wydarzenie przed urodzeniem osoby → `null`;
- urodzenie „05.1900” i małżeństwo „03.1920” → 19 lat; data przybliżona nie zmienia wieku;
- payload: `year`, `is_approximate`, `date_has_month` dla daty pełnej, roku i przybliżonej;
- `Age::at` w `AgeTest.php`: jeden test, że daje ten sam wynik co `from(..., true, ...)`.
Test liczby zapytań (`:283-323`) bez zmian i zielony.

### Success Criteria:

#### Automated Verification:

- Testy osi i wieku przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonTimelineTest.php tests/Unit/AgeTest.php`
- Larastan bez błędów: `vendor/bin/sail bin phpstan analyse`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Payload `personPreview.person.timeline` osoby z danymi zawiera `year`, `is_approximate`, `date_has_month`, `age` zgodnie z regułą

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne
potwierdzenie przed fazą 2.

---

## Phase 2: Wspólny komponent osi + helper wiersza

### Overview

Prezentacyjny komponent osi niezależny od osoby oraz czysty helper, który zamienia wpisy osi osoby
na jego wiersze.

### Changes Required:

#### 1. Typ wpisu

**File**: `resources/js/types/person.ts`

**Intent**: Typ `TimelineEntry` odzwierciedla nowe pola payloadu.

**Contract**: dodać `year: number | null`, `is_approximate: boolean`, `date_has_month: boolean`,
`age: PersonAge | null`.

#### 2. Helper wierszy osi osoby

**File**: `resources/js/lib/person-timeline.ts` (+ `person-timeline.test.ts`)

**Intent**: Jedno miejsce na reguły widoku: rodzaj znacznika, tekst kolumny, tekst wieku, data w
treści i styl odcinka do następnego wiersza.

**Contract**: `personTimelineRows(entries: TimelineEntry[]): PersonTimelineRow[]`, gdzie wiersz ma:
- `marker: 'own' | 'relative' | 'unknown'` — `unknown` dla `type === 'burial'` lub `year === null`;
  `relative` dla `relative !== null && type !== 'marriage'` (dzieci, małżonkowie); reszta `own`;
- `year: string | null` (`'?'` dla wpisu bez roku, `null` dla pochowania), `approximate: boolean`;
- `age: string | null` (`formatAge`);
- `date: string | null` — `entry.date` tylko gdy `date_has_month`, inaczej `null`;
- `segmentBelow: 'solid' | 'dashed' | null` — `null` dla ostatniego wiersza, `dashed` gdy ten lub
  następny wiersz ma `marker === 'unknown'`.
Test Vitest: każdy rodzaj znacznika, „?” vs pusta kolumna pochowania, „ok.”, data tylko z miesiącem,
odcinki (zgon → pochowanie → zgon syna 1990: dashed, dashed; ostatni `null`), wiek `null`.

#### 3. Komponent osi

**File**: `resources/js/components/timeline.tsx`

**Intent**: Wspólny prezentacyjny komponent: pionowa linia, znaczniki, kolumna po lewej, treść po
prawej; tylko tokeny, `<ul>/<li>` dla dostępności i E2E.

**Contract**: `Timeline` (`<ul>`, `children`) i `TimelineItem` z propsami
`marker: 'primary' | 'muted' | 'hollow'`, `aside: ReactNode` (kolumna), `segmentBelow:
'solid' | 'dashed' | null`, `children` (treść). Znacznik `aria-hidden`; pełny `bg-primary` /
`bg-tree-line`, pusty `bg-background` z obramowaniem `border-line border-tree-line`; odcinek
`border-l-line border-tree-line` (+ `border-dashed`) składany przez `clsx`. Bez stylu inline,
bez arbitralnych pikseli; wąska kolumna o stałej szerokości z `tabular-nums`.

### Success Criteria:

#### Automated Verification:

- Testy helpera przechodzą: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Lint i format przechodzą: `vendor/bin/sail npm run check`

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej przejdź do fazy 3 (faza nie
ma kroków ręcznych — komponent jest widoczny dopiero w fazie 3).

---

## Phase 3: Zakładka Wydarzenia na osi + utrwalenie + bramka wizualna

### Overview

Zakładka renderuje oś, link do krewnego dostaje styl i fokus, kontrakt trafia do testu tokenów i
`CLAUDE.md`, wygląd przechodzi przez zrzuty.

### Changes Required:

#### 1. Zakładka na osi

**File**: `resources/js/components/persons/events-tab.tsx`

**Intent**: Zamienić `divide-y` na `Timeline` zasilany `personTimelineRows`; usunąć „data
nieznana” (zastępuje ją „?” i pusty znacznik).

**Contract**: kolumna `aside` = „ok.” (małe, gdy `approximate`) + rok + wiek (`text-muted-foreground`);
„?” z `sr-only` „data nieznana”. Treść = etykieta (`text-sm font-medium`) z linkiem do krewnego, pod
nią `date · place` (`text-muted-foreground text-sm`), pomijając puste części. Mapowanie znacznika:
`own → primary`, `relative → muted`, `unknown → hollow`. `key` jak dziś (`${type}-${id}`).
Stan pusty „Brak wydarzeń.” bez zmian.

#### 2. Link do krewnego (C4)

**File**: `resources/js/components/persons/events-tab.tsx`

**Intent**: Ten sam styl linku co w Przeglądzie i widoczny fokus klawiatury.

**Contract**: `className={cn('text-primary rounded-sm underline-offset-4 hover:underline', focusRing)}`
na `PersonPreviewLink` (wzór `overview-tab.tsx:146`; `cn` jest tu bezpieczne — brak klas `border-*`).

#### 3. Kontrola tokenów

**File**: `resources/js/lib/view-tokens.test.ts`

**Intent**: Następny agent nie wróci do literałów w osi bez sygnału z CI.

**Contract**: dopisać `components/persons/events-tab.tsx` i `components/timeline.tsx` do `VIEW_FILES`;
bez nowych wyjątków.

#### 4. Reguła w CLAUDE.md

**File**: `CLAUDE.md` (sekcja „UI: tokens and shared components”)

**Intent**: Utrwalić komponent osi i podział wiek-serwer / tekst-front.

**Contract**: dopisać `timeline` do listy wspólnych `components/{…}.tsx` oraz jeden punkt: oś czasu
to `components/timeline.tsx`; wiek w chwili wydarzenia liczy serwer (`PersonTimeline` + `Age::at`),
front tylko formatuje (`formatAge`); linie osi składać przez `clsx`, nie `cn`. Wspomnieć, że
kontrola `view-tokens.test.ts` obejmuje zakładkę Wydarzenia.

#### 5. Bramka wizualna

**File**: `context/changes/person-events-timeline/screenshots/` (`shots.mjs`, PNG, `verdict.md`)

**Intent**: Potwierdzić wygląd osi w obu motywach i na telefonie, wzorem
`archive/2026-10-12-tree-lines-union-node/screenshots/`.

**Contract**: Playwright, `deviceScaleFactor: 2`, light + dark, 1366×768 i 390×844, PNG
`NN-<nazwa>-<motyw>.png`. Scena: osoba z urodzeniem, dwoma ślubami, dziećmi, zgonem małżonka, zgonem
własnym, pochowaniem, zgonem dziecka po własnym zgonie, wpisem bez daty i datą przybliżoną. Stany:
domyślny, hover linku, focus linku (Tab). `verdict.md` ocenia: czytelność kolumny roku, kontrast
znaczników `primary`/`tree-line`/pustego w dark, przerywane odcinki, brak poziomego scrolla na 390.

### Success Criteria:

#### Automated Verification:

- Testy JS (w tym `view-tokens`) przechodzą: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Lint i format przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Istniejący E2E zakładki przechodzi bez zmian: `npx playwright test tests/e2e/person-events-and-note-tabs.spec.ts`

#### Manual Verification:

- Zrzuty light/dark 1366×768 i 390×844 zapisane w `screenshots/` z `verdict.md` bez otwartych uwag
- Oś czyta się chronologicznie: rok i wiek po lewej, znaczniki własne vs krewni rozróżnialne w obu motywach
- Pochowanie i wpis bez daty mają pusty znacznik i przerywane odcinki; bez daty pokazuje „?”
- Link do krewnego ma hover i widoczny focus ring; kliknięcie podmienia panel jak dotąd

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie
zrzutów.

---

## Testing Strategy

### Unit Tests:

- `AgeTest.php`: `Age::at` równoważne `from(..., true, ...)`.
- `person-timeline.test.ts`: rodzaje znaczników, kolumna („?”, pusta, „ok.”), data tylko z miesiącem,
  odcinki przerywane po obu stronach pustego znacznika, ostatni wiersz bez odcinka.
- `view-tokens.test.ts`: nowe pliki pod kontrolą tokenów.

### Integration Tests:

- `PersonTimelineTest.php`: wiek na scenie Adama, granica „po zgonie” przy datach częściowych tego
  samego roku, brak roku urodzenia, wydarzenie przed urodzeniem, daty częściowe, nowe pola payloadu;
  liczba zapytań bez zmian.
- E2E `person-events-and-note-tabs.spec.ts` bez zmian — strażnik `<li>`, etykiety i linku.

### Manual Testing Steps:

1. Otworzyć `/tree`, panel osoby z pełną sceną (jak w bramce wizualnej) → Wydarzenia.
2. Sprawdzić wiek przy ślubach i dzieciach, brak wieku przy urodzeniu, pochowaniu i zgonie dziecka po zgonie.
3. Przejść Tabem do linku krewnego, sprawdzić focus ring; kliknąć — panel podmienia treść.
4. Powtórzyć w dark i na szerokości telefonu.

## Performance Considerations

Wiek liczony w pamięci na już załadowanych relacjach; zero dodatkowych zapytań (pilnuje test
`PersonTimelineTest.php:283-323`).

## Migration Notes

Brak zmian schematu i danych.

## References

- Research: `context/changes/person-events-timeline/research.md`
- Poprzednia oś: `context/archive/2026-10-10-person-events-and-note-tabs/plan.md`
- Reguły wieku: `context/archive/2026-10-05-maiden-name-and-age/plan.md`
- Wzorce linii: `resources/js/components/tree/family-sheet.tsx:3,150-168,862-870`
- Kropka: `resources/js/components/persons/person-modal.tsx:82-85`
- Link + fokus: `resources/js/components/persons/overview-tab.tsx:146`, `resources/js/lib/focus-ring.ts:7-8`
- Bramka wizualna: `context/archive/2026-10-12-tree-lines-union-node/screenshots/`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Rok i wiek we wpisie osi (backend)

#### Automated

- [x] 1.1 Testy osi i wieku przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonTimelineTest.php tests/Unit/AgeTest.php` — 9747479
- [x] 1.2 Larastan bez błędów: `vendor/bin/sail bin phpstan analyse` — 9747479
- [x] 1.3 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — 9747479

#### Manual

- [x] 1.4 Payload `personPreview.person.timeline` osoby z danymi zawiera `year`, `is_approximate`, `date_has_month`, `age` zgodnie z regułą — 9747479

### Phase 2: Wspólny komponent osi + helper wiersza

#### Automated

- [x] 2.1 Testy helpera przechodzą: `vendor/bin/sail npm run test:js`
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [x] 2.3 Lint i format przechodzą: `vendor/bin/sail npm run check`

### Phase 3: Zakładka Wydarzenia na osi + utrwalenie + bramka wizualna

#### Automated

- [ ] 3.1 Testy JS (w tym `view-tokens`) przechodzą: `vendor/bin/sail npm run test:js`
- [ ] 3.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [ ] 3.3 Lint i format przechodzą: `vendor/bin/sail npm run check`
- [ ] 3.4 Build przechodzi: `vendor/bin/sail npm run build`
- [ ] 3.5 Istniejący E2E zakładki przechodzi bez zmian: `npx playwright test tests/e2e/person-events-and-note-tabs.spec.ts`

#### Manual

- [ ] 3.6 Zrzuty light/dark 1366×768 i 390×844 zapisane w `screenshots/` z `verdict.md` bez otwartych uwag
- [ ] 3.7 Oś czyta się chronologicznie: rok i wiek po lewej, znaczniki własne vs krewni rozróżnialne w obu motywach
- [ ] 3.8 Pochowanie i wpis bez daty mają pusty znacznik i przerywane odcinki; bez daty pokazuje „?”
- [ ] 3.9 Link do krewnego ma hover i widoczny focus ring; kliknięcie podmienia panel jak dotąd
