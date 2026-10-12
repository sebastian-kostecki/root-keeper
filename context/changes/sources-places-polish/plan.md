# Design system Root Keeper na /sources i /places — plan implementacji

## Overview

Wdrażamy część sekcji 3 `implementation.md` z design systemu Root Keeper, którą zmiana design-tokens świadomie odłożyła: bursztyn dla dowodów (chip potwierdzenia, znak przy dacie, miniatura skanu, stopka karty źródła, fokus kart z odstępem), etykiety przycisków z rzeczownikiem, jeden układ przycisków w panelach miejscowości i parafii (jak w panelu źródła), spójne puste stany i nagłówki sekcji. Dodatkowo kończymy polonizację nagłówków ustawień. Żadna zmiana nie dotyka zachowania ani backendu — to wygląd, teksty i dostępność.

Źródło: artefakt design systemu https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU (`project/implementation.md`, `project/components/{SourceStatus,SourceCard,DataTable,SidePanel,EmptyState,SegmentedToggle}/README.md`, `project/tokens.json`).

## Current State Analysis

- Tokeny `--source`, `--source-surface`, `--source-border` i klasy `bg-source-surface` / `border-source-border` / `text-source` są już w `resources/css/app.css` (zmiana design-tokens), ale nic ich nie używa.
- `components/persons/event-source-status.tsx:9-27` — potwierdzenie to `Badge variant="default"` z `CheckIcon` i widocznym „Potwierdzone: …”; brak to `Badge variant="outline"` z `TriangleAlertIcon`. Używany w `persons/overview-tab.tsx:373,415,448`, `persons/family-tab.tsx:228`, `persons/source-group.tsx:62`, `marriages/marriage-panel.tsx:172` (props `kinds: string[]`).
- `components/persons/person-list-parts.tsx:5-15` — `ConfirmedMark` to `CheckIcon` w `text-primary` (używany w `persons-list.tsx:43`, `persons-table.tsx:76`).
- `components/sources/source-thumbnail.tsx:26-44` — `bg-muted` zawsze, `ImageOff` w `text-muted-foreground`. Komponent używają: galeria, `sources-table.tsx:151`, `source-picker-dialog.tsx:154`, `persons/source-group.tsx:75`. Follow-up F3 z design-tokens: w ciemnym motywie `bg-muted` ≈ tło.
- `components/sources/sources-gallery.tsx:46-50` — karta z `focusRing` (przylegający pierścień, taki sam jak `selectedListItem`); stopka `:76-90` — `LinkIcon` i „potwierdza N” w kolorze tekstu.
- `lib/focus-ring.ts` ma tylko `focusRing`. `lib/list-item-styles.ts:1-7` — komentarz mówi, że fokus karty pochodzi z `focusRing`.
- `components/sources/source-panel.tsx:130-161` — wzorzec docelowy: `actions` w nagłówku (`DeleteSourceDialog` z wyzwalaczem `outline sm` „Usuń”), `footer` z `Button type="submit" form={FORM_ID}` „Zapisz”; `processing` wynoszony z `<Form>` przez lokalny `ProcessingReporter` (`:85-99`). Sekcje „Opis (opcjonalnie)” (`:355`), „Opis” (`:493`) i „Potwierdza” (`:529`) to `<p>`.
- `components/localities/locality-panel.tsx:89-130` i `components/parishes/parish-panel.tsx:162-204` — „Zapisz”, „Scal z…” (`outline`) i „Usuń” (`ghost`) w treści formularza; „Scal z…”/„Usuń” wyłączone przy `isDirty` z `aria-describedby="panel-actions-hint"` i podpowiedzią „Zapisz zmiany, aby scalić lub usunąć.”. `isDirty`/`processing` są dostępne tylko w render-propie `<Form>`. W `parish-panel` formularz to osobny `ParishForm` z `key`, a stan okien scalania/usuwania żyje w nim.
- `components/dirty-reporter.tsx` — wspólny `DirtyReporter`; `ProcessingReporter` nie jest wspólny.
- Etykiety bez rzeczownika: `delete-source-dialog.tsx:116`, `delete-locality-dialog.tsx:104`, `delete-parish-dialog.tsx:103` („Usuń”), `merge-locality-dialog.tsx:119`, `merge-parish-dialog.tsx:118` („Scal”), `entry-number-dialog.tsx:87` („Zapisz”), `legacy-entry-number.tsx:55,76` („Usuń”), `buried-people-field.tsx:100` („Usuń”, `aria-label={`Usuń ${person.display_name}`}` w `:90`).
- `components/places/places-empty-state.tsx:43` — tytuł „Spis jest pusty”; `:72-75` przycisk innego spisu w wariancie `default`. `components/sources/sources-empty-state.tsx:18` — „Nie dodano jeszcze żadnego źródła”.
- `pages/places/index.tsx` — `SegmentedToggle` z `ariaLabel="Spis"`.
- Ustawienia: `pages/settings/profile.tsx:29,36-37,134`, `security.tsx:27,34-35,144`, `appearance.tsx:9,16-17,28`, `layouts/settings/layout.tsx:15,20,25,37-38` — angielskie `Head`, breadcrumbs, menu i nagłówki `Heading`. `sr-only` h1 są już po polsku.
- `lib/view-tokens.test.ts:20-51` (`VIEW_FILES`) obejmuje galerię, miniaturę, puste stany, `/places` i `source-panel`, ale nie `event-source-status.tsx`, `locality-panel.tsx`, `parish-panel.tsx`.
- Testy E2E zależne od etykiet: `places-merge-and-delete.spec.ts:44` (`'Scal', exact: true` — przestanie działać), `fact-source-scan.spec.ts:62` (`getByText(/^Potwierdzone/)` — tekst przechodzi do `sr-only`; sprawdzone: lokator nadal go znajduje i `toBeVisible()` zwraca true, więc spec nie pęknie, ale przestaje dowodzić, że rodzaj dokumentu jest widoczny w chipie), `source-panel-edit.spec.ts:46`, `source-panel-parish.spec.ts:68`, `gravestone-cemetery-and-buried.spec.ts:67,71` („Zapisz” dopasowuje podciąg; `Usuń ${displayName}` zmieni się z aria-label). Żaden test nie szuka „Profile settings”. Pozostałe „Zapisz” w specach dotyczą paneli osoby/małżeństwa/dodawania — poza zakresem.

## Desired End State

Fakt potwierdzony źródłem pokazuje bursztynowy chip z ikoną dokumentu i rodzajami („Akt urodzenia”), czytnik słyszy „Potwierdzone: Akt urodzenia”; brak źródła to szary chip „Bez źródła” z `CircleDashed`. Data potwierdzona na liście osób ma bursztynową ikonę `FileText`. Źródło bez podglądu ma bursztynową powierzchnię z `ImageOff` (także w ciemnym motywie), stopka karty „potwierdza N” jest bursztynowa, a fokus karty w galerii ma pierścień z odstępem, odróżnialny od zaznaczenia. Panele źródła, miejscowości i parafii mają jeden układ: zapis „Zapisz źródło/miejscowość/parafię” w przypiętej stopce, „Scal z…” i „Usuń” jako `outline sm` w nagłówku. Każdy przycisk zapisu, usuwania i scalania mówi, czego dotyczy. Puste stany mają tytuły „Brak źródeł” / „Brak miejscowości” / „Brak parafii”, a jedynym `default` na `/places` jest przycisk dodawania. Ustawienia są po polsku w tytule karty, breadcrumbs, menu i nagłówkach. Weryfikacja: bramki `check` / `types:check` / `test:js`, dotknięte specy E2E i zrzuty jasny/ciemny z `verdict.md`.

### Key Discoveries:

- Wzorzec przeniesienia przycisków poza `<Form>` już działa w `source-panel.tsx:85-161`: przycisk w `footer` z `form={id}`, stan formularza wynoszony reporterem w efekcie.
- `section-title` w `tokens.json` = 1rem / 500 (`text-base font-medium`) i odpowiada `Heading variant="small"`, ale ten wariant renderuje `h2` (`components/heading.tsx:24-31`), więc sekcje panelu to zwykłe `<h3 className="text-base font-medium">` (panel ma tytuł wyżej w hierarchii).
- `getByRole('button', { name: 'Zapisz' })` bez `exact` dopasowuje podciąg — „Zapisz źródło” nadal pasuje; jedynie `exact: true` w specu scalania pęka. Specy i tak aktualizujemy do pełnych nazw, żeby test mówił, w co klika.
- Wzorzec zrzutów: `context/archive/2026-10-12-design-tokens/screenshots/shots.mjs` (logowanie raz, `storageState` w `$CLAUDE_JOB_DIR/tmp`, `colorScheme` light/dark, log `boxShadow` dla fokusu).

## What We're NOT Doing

- Linie drzewa, `MarriageRingsIcon`, `union-node.tsx`, `family-sheet.tsx`, listwa płci w `PhotoStrip` — osobna zmiana.
- Etykiety „Usuń osobę” i pozostałe dialogi usuwania poza źródłem/miejscowością/parafią/starym numerem (np. `delete-person-dialog.tsx`, zdjęcie osoby).
- Etykiety „Zapisz” w panelach osoby, małżeństwa, dodawania osoby i w paskach niezapisanych zmian.
- Przeniesienie przełącznika spisu na `/places` do rzędu wyszukiwania — zostaje nad wyszukiwarką (decyzja, nie błąd).
- Teksty formularzy ustawień (pola, przyciski, 2FA, klucze dostępu) poza nagłówkami `Heading`, `Head`, breadcrumbs i menu.
- Zmiana `CLAUDE.md` (wpis o `focusRing`) — `focusRingOffset` opisuje komentarz w `lib/focus-ring.ts`.
- Backend, Pest, nowe zależności, regresje pikselowe (`toHaveScreenshot`).

## Implementation Approach

Trzy fazy od najmniej do najbardziej strukturalnej zmiany w obrębie widoku: najpierw zrzuty „przed” i bursztyn (same klasy i ikony, props bez zmian), potem panele i etykiety (jedyna zmiana struktury — wyniesienie stanu formularza w dwóch panelach według wzorca `source-panel`), na końcu puste stany, `/places`, ustawienia i bramka wizualna. Każda faza kończy się zielonym `check` / `types:check` / `test:js` i przejściem dotkniętych specy E2E.

## Critical Implementation Details

**Zrzuty „przed” najpierw.** Zestaw `screenshots/before/` musi powstać przed pierwszą edycją widoku i z aktualnego buildu (`vendor/bin/sail npm run build`) — w design-tokens nieaktualny `public/build` dał fałszywy log fokusu.

**Stan formularza poza `<Form>` (faza 2).** `isDirty` i `processing` trzeba wynieść do komponentu panelu, bo przyciski nagłówka i stopki leżą poza formularzem. Callback przekazany do `DirtyReporter` musi łączyć lokalny `setIsDirty` z `onDirtyChange` strony (strażnik niezapisanych zmian na `/places` nadal musi go dostawać). `DirtyReporter` przy odmontowaniu woła `onDirtyChange(false)`, więc zmiana rekordu (`key`) sama zeruje stan — nie dokładaj osobnego resetu. W `parish-panel` stan okien scalania/usuwania i same okna przechodzą z `ParishForm` do `ParishPanel`, żeby przyciski nagłówka mogły je otwierać. W `locality-panel` okna i ich stan są już na poziomie panelu — wynosimy tylko `isDirty` i `processing`. Połączony callback (lokalny `setIsDirty` + `onDirtyChange`) owinąć w `useCallback` o stabilnej tożsamości: `DirtyReporter` woła `onDirtyChange(false)` w cleanupie efektu przy każdej zmianie tożsamości callbacka. `footer` w `SidePanel` to goły `div` (`side-panel.tsx:216`) — przycisk zapisu i podpowiedź umieścić we własnym kontenerze flex. Dwa przyciski `outline sm` w nagłówku zostawiają przy 24 rem ok. 150 px na tytuł (`truncate`): sprawdzić na zrzucie.

## Phase 1: Dowody w bursztynie

### Overview

Zrzuty „przed”, nowa stała fokusu z odstępem, bursztyn w chipie potwierdzenia, znaku przy dacie, miniaturze i stopce karty źródła.

### Changes Required:

#### 1. Skrypt zrzutów i zestaw „przed”

**File**: `context/changes/sources-places-polish/screenshots/shots.mjs`, `screenshots/before/*.png`

**Intent**: Uchwycić stan wyjściowy wszystkich widoków, które zmienia ta zmiana, zanim padnie pierwsza edycja.

**Contract**: `node shots.mjs <out-dir>`; struktura jak `context/archive/2026-10-12-design-tokens/screenshots/shots.mjs` (aplikacja: serwer dev Sail pod `SHOTS_BASE`, domyślnie `http://localhost:8000`, z danymi `TreeViewDemoSeeder`, użytkownik `tree-demo@example.com`; `scripts/e2e-server.sh` nie nadaje się — ma pustą bazę `e2e` bez seedera; logowanie raz, `storageState` w `$CLAUDE_JOB_DIR/tmp`). Dla `colorScheme` light i dark: `/sources` (galeria), `/sources` z otwartym panelem źródła, `/places` z otwartym panelem miejscowości, `/places?kind=parafie` z otwartym panelem parafii, `/places` z wyszukiwaniem bez wyników w bieżącym spisie, `/persons` (tabela ze znakiem potwierdzenia), panel osoby z faktem potwierdzonym i bez źródła, `/settings/profile`; fokus klawiatury na karcie galerii z logiem `boxShadow`; puste stany (tytuły „Brak …” zmienia faza 3): `/sources`, `/places` i `/places?kind=parafie` na drugim użytkowniku `tree-demo-empty@example.com` (osobny `storageState`). Identyfikatory rekordów skrypt bierze z listy (pierwszy link), nie z twardych id. Brakujące w demo stany (np. źródło bez miniatury) skrypt pomija z komunikatem w logu.

#### 2. Fokus z odstępem

**File**: `resources/js/lib/focus-ring.ts`, `resources/js/lib/list-item-styles.ts`, `resources/js/components/sources/sources-gallery.tsx, `resources/js/components/tree/card-styles.ts`

**Intent**: Fokus karty w galerii odsunięty od karty, żeby nie mylił się z przylegającym pierścieniem zaznaczenia.

**Contract**: Nowy eksport `focusRingOffset = 'outline-none focus-visible:ring-3 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background'` z komentarzem (karty list z zaznaczeniem). Karta galerii: `focusRing` → `focusRingOffset`. Komentarz `selectedListItem` wspomina obie stałe; zasada „fokus wygrywa nad zaznaczeniem” bez zmian. `cardInteractive` w `components/tree/card-styles.ts:23` ma już identyczny zestaw — zastąpić go przez `export const cardInteractive = focusRingOffset` (import z `@/lib/focus-ring`), żeby wzorzec miał jedno źródło; wygląd kart drzewa bez zmian (`outline-none` jest już częścią stałej, więc osobne `focus-visible:outline-none` odpada).

#### 3. Chip potwierdzenia

**File**: `resources/js/components/persons/event-source-status.tsx`

**Intent**: Bursztyn tylko dla dowodów; brak źródła to stan badań, nie ostrzeżenie (README `SourceStatus`).

**Contract**: Props bez zmian (`kinds: string[]`). Potwierdzony: `Badge variant="outline"` (oba stany; bez nadpisywania `default`) z klasami `bg-source-surface border-source-border text-source`, `FileTextIcon`, ukryte `<span className="sr-only">Potwierdzone: </span>`, widoczna treść `kinds.join(', ')`; zachowane zawijanie (`h-auto max-w-full shrink whitespace-normal`). Brak: `Badge variant="outline"`, przezroczysty z ramką `border`, `text-muted-foreground`, `CircleDashedIcon` zamiast `TriangleAlertIcon`, „Bez źródła”.

#### 4. Znak przy dacie

**File**: `resources/js/components/persons/person-list-parts.tsx`

**Intent**: Potwierdzenie źródłem ma ikonę dokumentu w bursztynie, jak chip.

**Contract**: `ConfirmedMark`: `CheckIcon` w `text-primary` → `FileTextIcon` w `text-source`; rozmiar, wyrównanie, `aria-hidden` i `sr-only` „potwierdzone źródłem” bez zmian. Zaktualizować komentarz (✓ → ikona dokumentu).

#### 5. Miniatura bez podglądu

**File**: `resources/js/components/sources/source-thumbnail.tsx`

**Intent**: Skan to dowód — brak podglądu stoi na bursztynowej powierzchni; zamyka follow-up F3 (ciemna miniatura na `/sources`).

**Contract**: Tło kontenera `bg-source-surface` i ikona `ImageOff` w `text-source`, gdy obrazu nie ma lub nie wczytał się; `bg-muted` zostaje pod wczytanym obrazem. Dotyczy wszystkich użyć komponentu (galeria, tabela, wybór źródła, źródła osoby).

#### 6. Stopka karty

**File**: `resources/js/components/sources/sources-gallery.tsx`

**Intent**: „potwierdza N wydarzeń” niesie bursztyn dowodu.

**Contract**: Gałąź z wydarzeniami: `LinkIcon` i tekst w `text-source`; gałąź „bez powiązań” zostaje `text-muted-foreground`.

#### 7. Strażnik i test E2E

**File**: `resources/js/lib/view-tokens.test.ts`, `tests/e2e/fact-source-scan.spec.ts`

**Intent**: Strażnik tokenów pilnuje chipa; spec potwierdzenia sprawdza ten sam stan po przeniesieniu prefiksu do `sr-only`.

**Contract**: `VIEW_FILES` += `components/persons/event-source-status.tsx`. Spec (`:62` i komentarz `:1`): zostaje asercja `getByText(/^Potwierdzone/)` (przechodzi również na `sr-only`) i dochodzi asercja, że chip faktu urodzenia pokazuje rodzaj dokumentu źródła (widoczny tekst w obrębie regionu „Urodzenie”, np. nazwa rodzaju z danych specu); lokator według reguł z `CLAUDE.md` (bez CSS). Asercja jest wzmocnieniem — nie osłabia istniejącej.

### Success Criteria:

#### Automated Verification:

- Zrzuty „przed” zapisane w `screenshots/before/` przed pierwszą edycją widoku
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Testy JS (z `event-source-status.tsx` w `VIEW_FILES`) przechodzą: `vendor/bin/sail npm run test:js`
- Spec potwierdzenia przechodzi: `npx playwright test tests/e2e/fact-source-scan.spec.ts`

#### Manual Verification:

- Chip potwierdzenia jest bursztynowy z ikoną dokumentu, „Bez źródła” szary z przerywanym kółkiem — w podglądzie osoby i panelu małżeństwa, w obu motywach
- Czytnik ekranu (lub drzewo dostępności) czyta „Potwierdzone: …” przed rodzajami
- Fokus klawiatury na karcie galerii ma pierścień z odstępem, odróżnialny od zaznaczenia otwartej karty
- Miniatura bez podglądu i stopka karty są bursztynowe, czytelne w ciemnym motywie

**Implementation Note**: Po fazie i zielonych bramkach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Panele i etykiety

### Overview

Jeden układ przycisków w panelach miejscowości i parafii, etykiety z rzeczownikiem w panelach i oknach, nagłówki sekcji w panelu źródła.

### Changes Required:

#### 1. Wspólny `ProcessingReporter`

**File**: `resources/js/components/processing-reporter.tsx` (nowy), `resources/js/components/sources/source-panel.tsx`

**Intent**: Trzy panele wynoszą `processing` z `<Form>` do przycisku w stopce — jeden komponent zamiast kopii.

**Contract**: Przenieść `ProcessingReporter` z `source-panel.tsx:85-99` bez zmiany zachowania (props `processing`, `onChange`; reset na odmontowanie), obok `components/dirty-reporter.tsx` i w jego stylu; `source-panel` importuje go.

#### 2. Panel miejscowości

**File**: `resources/js/components/localities/locality-panel.tsx`

**Intent**: Ten sam układ co panel źródła: zapis zawsze widoczny w stopce, działania na rekordzie w nagłówku.

**Contract**: `SidePanel` dostaje `footer` z `Button type="submit" form="locality-form"` „Zapisz miejscowość” (spinner przy `processing`) oraz — gdy `isDirty` i rekord istnieje — `<p id="panel-actions-hint">` „Zapisz zmiany, aby scalić lub usunąć.” obok przycisku. `actions` (tylko dla istniejącego rekordu): „Scal z…” `outline sm` i „Usuń” `outline sm` z `text-destructive-foreground hover:text-destructive-foreground`, oba `disabled={isDirty}` z `aria-describedby="panel-actions-hint"` przy `isDirty`. `isDirty`/`processing` wyniesione stanem panelu (patrz Critical Implementation Details). Treść formularza: tylko pola.

#### 3. Panel parafii

**File**: `resources/js/components/parishes/parish-panel.tsx`

**Intent**: Jak panel miejscowości.

**Contract**: Jak w punkcie 2, z `form="parish-form"` i „Zapisz parafię”. Stan `mergeOpen`/`deleteOpen` oraz `MergeParishDialog`/`DeleteParishDialog` przechodzą z `ParishForm` do `ParishPanel`; `ParishForm` raportuje `isDirty` i `processing` w górę.

#### 4. Panel źródła

**File**: `resources/js/components/sources/source-panel.tsx`

**Intent**: Przycisk mówi, co robi; sekcje dostępne z listy nagłówków czytnika.

**Contract**: Stopka „Zapisz” → „Zapisz źródło”; wyzwalacz w nagłówku „Usuń” → „Usuń źródło”. „Opis (opcjonalnie)” (`:355`), „Opis” (`:493`) i „Potwierdza” (`:529`): `<p>` → `<h3 className="text-base font-medium">` (styl `section-title`); „Potwierdza” traci `text-muted-foreground text-sm`.

#### 5. Etykiety w oknach i polach

**File**: `components/sources/delete-source-dialog.tsx`, `components/localities/delete-locality-dialog.tsx`, `components/parishes/delete-parish-dialog.tsx`, `components/localities/merge-locality-dialog.tsx`, `components/parishes/merge-parish-dialog.tsx`, `components/sources/entry-number-dialog.tsx`, `components/sources/legacy-entry-number.tsx`, `components/sources/buried-people-field.tsx` (wszystkie w `resources/js/`)

**Intent**: Każdy przycisk zapisu, usuwania i scalania mówi, czego dotyczy.

**Contract**: Przycisk potwierdzenia: „Usuń źródło” / „Usuń miejscowość” / „Usuń parafię”; „Scal miejscowości” / „Scal parafie”; „Zapisz numer wpisu”; w `legacy-entry-number` wyzwalacz i potwierdzenie „Usuń stary numer”. `buried-people-field`: widoczne „Usuń z listy”, `aria-label` „Usuń z listy: {display_name}” (widoczna etykieta jest początkiem nazwy dostępnej — WCAG 2.5.3). Tytuły okien i „Scal z…” w oknie „Nie można usunąć” bez zmian.

#### 6. Strażnik i testy E2E

**File**: `resources/js/lib/view-tokens.test.ts`, `tests/e2e/places-merge-and-delete.spec.ts`, `tests/e2e/source-panel-edit.spec.ts`, `tests/e2e/source-panel-parish.spec.ts`, `tests/e2e/gravestone-cemetery-and-buried.spec.ts`

**Intent**: Strażnik pilnuje dwóch przebudowanych paneli; specy szukają przycisków po nowych nazwach (zmiana nazwy, nie zachowania — asercje bez zmian).

**Contract**: `VIEW_FILES` += `components/localities/locality-panel.tsx`, `components/parishes/parish-panel.tsx`. Specy: „Scal” → „Scal miejscowości” (`exact`), „Zapisz” → „Zapisz źródło” w panelu źródła, `Usuń ${displayName}` → `Usuń z listy: ${displayName}`; komentarz `source-panel-edit.spec.ts:3` zgodny z nową etykietą.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Testy JS (z panelami w `VIEW_FILES`) przechodzą: `vendor/bin/sail npm run test:js`
- Specy paneli przechodzą: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts tests/e2e/places-page.spec.ts tests/e2e/source-panel-edit.spec.ts tests/e2e/source-panel-parish.spec.ts tests/e2e/gravestone-cemetery-and-buried.spec.ts`
- W zakresie nie zostało gołe „Zapisz”/„Usuń”/„Scal” jako etykieta przycisku (`grep -nE '^\s*(Zapisz|Usuń|Scal)\s*$'` na plikach z punktów 2–5 nie zwraca nic)

#### Manual Verification:

- Panel miejscowości i parafii: „Zapisz …” w przypiętej stopce, „Scal z…” i „Usuń” w nagłówku; nowy rekord bez przycisków w nagłówku (zapis widoczny bez przewijania, akcje przed „Zamknij panel”)
- Po zmianie pola „Scal z…” i „Usuń” są wyłączone, podpowiedź w stopce; po zapisie wracają
- Strażnik niezapisanych zmian na `/places` nadal pyta przed zamknięciem panelu ze zmianami
- Okna scalania i usuwania otwierają się z nagłówka i działają jak wcześniej (scalenie przenosi do zachowanego rekordu)
- W panelu źródła „Opis” i „Potwierdza” są nagłówkami h3 (lista nagłówków czytnika / drzewo dostępności)

**Implementation Note**: Po fazie i zielonych bramkach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Phase 3: Puste stany, /places, ustawienia i bramka

### Overview

Tytuły i warianty pustych stanów, etykieta przełącznika spisu, polskie nagłówki ustawień, zrzuty „po” i werdykt.

### Changes Required:

#### 1. Puste stany

**File**: `resources/js/components/places/places-empty-state.tsx`, `resources/js/components/sources/sources-empty-state.tsx`

**Intent**: Tytuły pustych stanów w jednej formie; jedynym `default` na stronie zostaje przycisk dodawania.

**Contract**: `PlacesEmptyTree`: tytuł z mapy per rodzaj — „Brak miejscowości” / „Brak parafii” (do `emptyTreeCopy`). `PlacesNoResults`: przycisk „Pokaż w {nazwa listy} ({count})” (etykieta `Pokaż w ${kindLocative}` z `pages/places/index.tsx:262`, tekst bez zmian) → `variant="outline"`. Źródła: „Nie dodano jeszcze żadnego źródła” → „Brak źródeł”.

#### 2. Przełącznik spisu

**File**: `resources/js/pages/places/index.tsx`

**Intent**: Etykieta mówi, co się przełącza. Miejsce przełącznika (nad wyszukiwarką) bez zmian.

**Contract**: `ariaLabel="Spis"` → `ariaLabel="Rodzaj miejsca"`.

#### 3. Ustawienia po polsku

**File**: `resources/js/pages/settings/profile.tsx`, `resources/js/pages/settings/security.tsx`, `resources/js/pages/settings/appearance.tsx`, `resources/js/layouts/settings/layout.tsx`, `resources/js/components/delete-user.tsx`, `resources/js/components/manage-two-factor.tsx`, `resources/js/components/manage-passkeys.tsx`

**Intent**: Strona ustawień nie miesza angielskich nagłówków z polskim menu.

**Contract**: `Head` i breadcrumbs: „Ustawienia profilu” / „Ustawienia zabezpieczeń” / „Ustawienia wyglądu”. Menu: „Profil”, „Zabezpieczenia”, „Wygląd”. `Heading` w layoucie: „Ustawienia” z opisem „Profil, hasło i wygląd aplikacji”. `Heading variant="small"`: „Profil” / „Imię i nazwisko oraz adres e-mail konta”; „Zmiana hasła” / „Długie, losowe hasło chroni konto”; „Wygląd” / „Motyw jasny, ciemny lub zgodny z systemem”; w `delete-user.tsx` „Usunięcie konta” / „Usuwa konto wraz ze wszystkimi danymi”; w `manage-two-factor.tsx` „Uwierzytelnianie dwuskładnikowe” / „Ustawienia drugiego składnika logowania”; w `manage-passkeys.tsx` „Klucze dostępu” / „Logowanie bez hasła kluczami dostępu”; `aria-label="Settings"` nawigacji w `layout.tsx:45` → „Ustawienia”. Bez „Ty/Twój”. Pozostałe teksty formularzy (pola, przyciski, treść 2FA i okien) bez zmian.

#### 4. Zrzuty „po” i werdykt

**File**: `context/changes/sources-places-polish/screenshots/after/*.png`, `screenshots/verdict.md`

**Intent**: Bramka wizualna z `CLAUDE.md`: porównanie przed/po dla każdego zmienionego widoku w obu motywach.

**Contract**: Ten sam `shots.mjs` na świeżym buildzie (`vendor/bin/sail npm run build`). `verdict.md` per widok i motyw: co się zmieniło, czy coś odstaje (kontrast bursztynu, układ nagłówka panelu, zawijanie chipa z kilkoma rodzajami, szerokość tytułu w nagłówku panelu z dwoma przyciskami, przycinanie zewnętrznego pierścienia fokusu karty galerii w pierwszej kolumnie), log `boxShadow` fokusu karty (oczekiwany pierścień `ring` 3 px z odstępem 2 px w kolorze `background`) oraz status F3.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- Build przechodzi: `vendor/bin/sail npm run build`
- Specy stron przechodzą: `npx playwright test tests/e2e/places-page.spec.ts tests/e2e/sources-page-states.spec.ts tests/e2e/sources-grid-with-filters.spec.ts`
- Testy ustawień przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/Settings`
- Zrzuty „po” i `verdict.md` zapisane w `screenshots/`

#### Manual Verification:

- Pusty spis `/places` ma tytuł „Brak miejscowości” / „Brak parafii”; brak wyników pokazuje „Pokaż w parafiach (n)” / „Pokaż w miejscowościach (n)” jako outline (obok „Wyczyść wyszukiwanie”)
- Pusta lista `/sources` ma tytuł „Brak źródeł”
- Czytnik ogłasza przełącznik na `/places` jako „Rodzaj miejsca”
- Karta przeglądarki, breadcrumbs, menu i nagłówki trzech stron ustawień są po polsku (`/settings/profile`, `/settings/security`, `/settings/appearance`)
- Werdykt zrzutów przejrzany i zaakceptowany

---

## Testing Strategy

### Unit Tests:

- `lib/view-tokens.test.ts` z rozszerzonym `VIEW_FILES` (chip, oba panele) — żadnych palet, heksów ani arbitralnych px w nowych klasach.
- Brak nowych testów jednostkowych: zmiany są w widokach (klasy, ikony, teksty), bez nowej logiki.

### Integration Tests:

- E2E dotknięte zmianą etykiet: `places-merge-and-delete`, `source-panel-edit`, `source-panel-parish`, `gravestone-cemetery-and-buried`, `fact-source-scan` — te same scenariusze, nowe nazwy w lokatorach, asercje biznesowe bez zmian.
- `places-page`, `sources-page-states`, `sources-grid-with-filters` jako regresja stron.
- Pest: tylko `tests/Feature/Settings` jako regresja (bez zmian w PHP).

### Manual Testing Steps:

1. Otwórz osobę z potwierdzonym urodzeniem i niepotwierdzonym zgonem — chip bursztynowy i szary „Bez źródła”, w obu motywach.
2. Na `/sources` przejdź Tabem po kartach galerii, otwórz jedną — fokus z odstępem, zaznaczenie przylegające.
3. Otwórz miejscowość, zmień nazwę — „Scal z…”/„Usuń” wyłączone, podpowiedź w stopce; zapisz — odblokowane; scal z inną miejscowością.
4. Otwórz źródło z nagrobkiem, usuń pochowanego — „Usuń z listy”.
5. Wyszukaj na `/places` nazwę parafii w spisie miejscowości — przycisk „Pokaż w parafiach (n)” jako outline.
6. Przejdź po trzech stronach ustawień — wszystko w nagłówkach po polsku.

## References

- Design system: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU (`project/implementation.md` sekcja 3, README `SourceStatus`, `SourceCard`, `DataTable`, `SidePanel`, `EmptyState`, `SegmentedToggle`, `DeleteDialog`, `Button`)
- Poprzednia zmiana: `context/archive/2026-10-12-design-tokens/` (plan „What We're NOT Doing”, `follow-ups/review-fixes.md` F3, `screenshots/shots.mjs`, `screenshots/verdict.md`)
- Wzorzec panelu: `resources/js/components/sources/source-panel.tsx:85-161`
- Wzorzec reportera: `resources/js/components/dirty-reporter.tsx`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Dowody w bursztynie

#### Automated

- [x] 1.1 Zrzuty „przed” zapisane w `screenshots/before/` przed pierwszą edycją widoku
- [x] 1.2 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [x] 1.3 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [x] 1.4 Testy JS (z `event-source-status.tsx` w `VIEW_FILES`) przechodzą: `vendor/bin/sail npm run test:js`
- [x] 1.5 Spec potwierdzenia przechodzi: `npx playwright test tests/e2e/fact-source-scan.spec.ts`

#### Manual

- [x] 1.6 Chip potwierdzenia jest bursztynowy z ikoną dokumentu, „Bez źródła” szary z przerywanym kółkiem — w podglądzie osoby i panelu małżeństwa, w obu motywach
- [x] 1.7 Czytnik ekranu (lub drzewo dostępności) czyta „Potwierdzone: …” przed rodzajami
- [x] 1.8 Fokus klawiatury na karcie galerii ma pierścień z odstępem, odróżnialny od zaznaczenia otwartej karty
- [x] 1.9 Miniatura bez podglądu i stopka karty są bursztynowe, czytelne w ciemnym motywie

### Phase 2: Panele i etykiety

#### Automated

- [ ] 2.1 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [ ] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [ ] 2.3 Testy JS (z panelami w `VIEW_FILES`) przechodzą: `vendor/bin/sail npm run test:js`
- [ ] 2.4 Specy paneli przechodzą: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts tests/e2e/places-page.spec.ts tests/e2e/source-panel-edit.spec.ts tests/e2e/source-panel-parish.spec.ts tests/e2e/gravestone-cemetery-and-buried.spec.ts`
- [ ] 2.5 W zakresie nie zostało gołe „Zapisz”/„Usuń”/„Scal” jako etykieta przycisku

#### Manual

- [ ] 2.6 Panel miejscowości i parafii: „Zapisz …” w przypiętej stopce, „Scal z…” i „Usuń” w nagłówku; nowy rekord bez przycisków w nagłówku
- [ ] 2.7 Po zmianie pola „Scal z…” i „Usuń” są wyłączone, podpowiedź w stopce; po zapisie wracają
- [ ] 2.8 Strażnik niezapisanych zmian na `/places` nadal pyta przed zamknięciem panelu ze zmianami
- [ ] 2.9 Okna scalania i usuwania otwierają się z nagłówka i działają jak wcześniej
- [ ] 2.10 W panelu źródła „Opis” i „Potwierdza” są nagłówkami h3

### Phase 3: Puste stany, /places, ustawienia i bramka

#### Automated

- [ ] 3.1 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [ ] 3.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [ ] 3.3 Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- [ ] 3.4 Build przechodzi: `vendor/bin/sail npm run build`
- [ ] 3.5 Specy stron przechodzą: `npx playwright test tests/e2e/places-page.spec.ts tests/e2e/sources-page-states.spec.ts tests/e2e/sources-grid-with-filters.spec.ts`
- [ ] 3.6 Testy ustawień przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/Settings`
- [ ] 3.7 Zrzuty „po” i `verdict.md` zapisane w `screenshots/`

#### Manual

- [ ] 3.8 Pusty spis `/places` ma tytuł „Brak miejscowości” / „Brak parafii”; brak wyników pokazuje „Pokaż w parafiach (n)” / „Pokaż w miejscowościach (n)” jako outline
- [ ] 3.9 Pusta lista `/sources` ma tytuł „Brak źródeł”
- [ ] 3.10 Czytnik ogłasza przełącznik na `/places` jako „Rodzaj miejsca”
- [ ] 3.11 Karta przeglądarki, breadcrumbs, menu i nagłówki trzech stron ustawień są po polsku
- [ ] 3.12 Werdykt zrzutów przejrzany i zaakceptowany
