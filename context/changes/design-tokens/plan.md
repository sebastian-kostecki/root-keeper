# Wdrożenie tokenów z design systemu Root Keeper — plan implementacji

## Overview

Podmieniamy wartości tokenów w `resources/css/app.css` na paletę z design systemu Root Keeper (papierowe tło, mech jako jedyna barwa marki, ciemne menu w obu motywach), dodajemy szeryfową rodzinę `font-display` (Newsreader) dla tytułów i wdrażamy wybrane poprawki komponentów z sekcji 3 `implementation.md`: pełny pierścień fokusu, kolor tekstu błędu, `on-destructive` w przyciskach i plakietkach, ring 3 px w menu i oknach, ikony toastów i polskie teksty. Strażnik `view-tokens.test.ts` obejmuje od teraz także karty drzewa i panele.

Źródło: artefakt design systemu https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU (`project/implementation.md`, `project/tokens.json`, `project/README.md`).

## Current State Analysis

- `resources/css/app.css` ma nazwy zmiennych zgodne z design systemem, ale wartości z neutralnego presetu shadcn (`oklch(... 0 0)`) oraz nieużywane `--chart-1…5` (w `:root`, `.dark` i `@theme`). Brak `--font-display`, `--source*` i `--on-destructive`.
- `vite.config.ts:16-20` ładuje tylko Instrument Sans przez `bunny()` z `laravel-vite-plugin/fonts`.
- `lib/focus-ring.ts:7-8` — `focusRing` to `ring-ring/50` + `ring-[3px]`; przy nowym mchu 50% daje 2,4:1 (WCAG 1.4.11 wymaga 3:1).
- `ring-ring/50` jest w `components/ui/{button,input,select,toggle,checkbox,badge,textarea,tabs}.tsx` i ręcznie w widokach: `persons/overview-tab.tsx:143,209`, `persons/person-candidate-list.tsx:132`, `persons/person-photo-menu.tsx:90`. Ręczne `ring-[3px]` z pełnym `ring-ring`: `password-input.tsx:25`, `persons/family-tab.tsx:130`, `marriages/marriage-panel.tsx:70`, `tree/card-styles.ts:23`.
- `text-destructive` jako kolor tekstu (9 miejsc, z `hover:`): `passkey-item.tsx:64`, `parishes/parish-panel.tsx:183`, `localities/locality-panel.tsx:112`, `sources/delete-source-dialog.tsx:71`, `sources/source-panel.tsx:147,513,591`, `persons/person-modal-header.tsx:70`, `persons/source-group.tsx:154`.
- `ui/button.tsx:15` i `ui/badge.tsx:17` — wariant `destructive` używa `text-white`; badge dodatkowo `dark:bg-destructive/60`.
- Fokus w menu: `ring-2` w `app-sidebar.tsx:79,86` i `ui/sidebar.tsx:403,424,472,559,684`.
- Przycisk zamykania `ui/dialog.tsx:64-66`, `ui/sheet.tsx:73-75`: `focus:ring-2`, `sr-only` „Close”.
- `ui/sonner.tsx` nie przekazuje `icons` — bez `richColors` ikony mają kolor tekstu.
- `lib/persons-index.ts:221` zwraca „(data nieznana)”; test `lib/persons-index.test.ts:259,262`. Reszta aplikacji (`union-node.tsx:45`, `marriage-panel.tsx:136`, `events-tab.tsx:26`, `persons-table.tsx:58`) pisze bez nawiasów.
- Ustawienia: `Settings/SecurityController.php:62` `__('Password updated.')`, `Settings/ProfileController.php:42` `__('Profile updated.')`; `lang/pl.json:154,164` mapuje je na angielski tekst. `pages/settings/{profile,security,appearance}.tsx` mają angielskie `sr-only` h1. `passkey-item.tsx:67-86` — dialog usuwania klucza po angielsku. Inne kontrolery wpisują polski klucz wprost (`__('Zapisano notatkę')`).
- Opisy list: `pages/persons/index.tsx:221` „Osoby zapisane w Twoim drzewie genealogicznym”, `pages/sources/index.tsx:397` „… dokumenty Twojego drzewa”, `pages/places/index.tsx:361` „Spis miejscowości i parafii Twojego drzewa”.
- Tytuły bez `font-display`: `heading.tsx:21` (`variant="page"`), `side-panel.tsx:224`, `ui/dialog.tsx:103` (`DialogTitle`), `tree/sheet-person-card.tsx:87` (imię), `empty-state.tsx:23`, `app-logo.tsx:12` (nazwa).
- `lib/view-tokens.test.ts:16-34` skanuje tylko widoki list `/persons`, `/sources`, `/places`. Kandydaci z drzewa i paneli są czyści poza: dynamiczną geometrią `style=` w `tree/family-sheet.tsx` i `tree/ancestor-chart.tsx`, paskiem postępu `style={{ width: % }}` w `sources/source-panel.tsx:333` oraz `ring-[3px]` w `card-styles.ts` i `marriage-panel.tsx` (naprawiane w fazie 2).

## Desired End State

Aplikacja w jasnym motywie stoi na papierowym tle z mchem jako akcentem, menu jest ciemne w obu motywach; tytuły stron, paneli, okien, pustych stanów, nazwa w logo i imię na karcie drzewa są pisane Newsreaderem. Każdy pierścień fokusu ma pełny kolor `ring` i 3 px (także w menu i na przycisku zamykania okna). Tekst błędu używa `destructive-foreground`, wypełnienie usuwania — `destructive` z `on-destructive`. Toasty mają ikony w kolorze stanu. W ustawieniach toasty, ukryte nagłówki i dialog klucza dostępu są po polsku; opisy list nie zwracają się „Twój”. `view-tokens.test.ts` pilnuje kart drzewa, zaślepek i paneli. Weryfikacja: bramki z sekcji 4 i zrzuty jasny/ciemny w `screenshots/after/` z `verdict.md`.

### Key Discoveries:

- Nazwy zmiennych zostają, więc `components/ui/*` i klasy ról zmieniają wygląd bez edycji (`resources/css/app.css:9-75`).
- Nowy `focusRing` (`outline-none focus-visible:ring-ring focus-visible:ring-3`) pokrywa dokładnie klasy wpisane ręcznie w 7 widokach — można je zastąpić stałą.
- `EXCEPTIONS` w `view-tokens.test.ts:83-89` dopasowuje po `file` + `token`; dla `style=` token to `style={` (po `trim()`), więc jeden wpis zwalnia wszystkie `style={` w pliku.
- Wzorzec zrzutów: `context/archive/2026-10-12-places-page/screenshots/shots.mjs` (Playwright z repo, `scripts/e2e-server.sh`, `tree-demo@example.com`, logowanie raz i `storageState`). Wygląd domyślny to `system` (`hooks/use-appearance.tsx:37`), więc motyw przełącza `colorScheme` kontekstu.

## What We're NOT Doing

- Bursztyn źródeł: `event-source-status.tsx`, `source-thumbnail.tsx`, `sources-gallery.tsx` (stopka, `focusRingOffset`), `ConfirmedMark` w `person-list-parts.tsx` — osobna zmiana. Tokeny `--source*` dodajemy, ale nikt ich jeszcze nie używa.
- Linie drzewa: `MarriageRingsIcon`, `union-node.tsx`, `family-sheet.tsx` (linie, belka, przodkowie `primary` 2 px), `--tree-line-width`, listwa płci pod zdjęciem w `PhotoStrip` — osobna zmiana.
- Stała `focusRingOffset` i etykiety „Usuń osobę” / „Usuń źródło” w dialogach usuwania (nie w zakresie zlecenia).
- `family-sheet.tsx` i `ancestor-chart.tsx` w `VIEW_FILES` — geometria liczona w runtime; wejdą razem ze zmianą linii drzewa.
- Pasek postępu wysyłania w `sources/source-panel.tsx:333` bez `style=` (zmienna CSS lub `<progress>`) — odroczone; do tego czasu wpis `EXCEPTIONS` `style={` dla tego pliku (zwalnia cały plik, więc nowy `style=` w panelu źródła nie zostanie wychwycony).
- Pozostałe angielskie teksty ustawień (`Head title`, `HeadingSmall`, breadcrumbs, `two-factor-setup-modal.tsx` „Close”).
- `ring-[3px]` wewnątrz `components/ui/*` (shadcn, poza lintem i testem) — zmieniamy tylko krycie `ring-ring/50` → `ring-ring`.
- Nowe zależności, `toHaveScreenshot`, regresje pikselowe.

## Implementation Approach

Od fundamentu do szczegółu: najpierw zrzuty „przed”, potem wartości tokenów i font (najszerszy efekt, zero edycji widoków), potem klasy komponentów (fokus, kolory), potem typografia i teksty, na końcu rozszerzenie strażnika i bramka wizualna. Każda faza kończy się zielonym `check` / `types:check` / `test:js`.

## Phase 1: Tokeny i font

### Overview

Zrzuty „przed”, nowe wartości zmiennych, czyszczenie `chart-*`, nowe pozycje `@theme`, Newsreader w buildzie.

### Changes Required:

#### 1. Skrypt zrzutów i zestaw „przed”

**File**: `context/changes/design-tokens/screenshots/shots.mjs`, `screenshots/before/*.png`

**Intent**: Uchwycić stan wyjściowy przed pierwszą zmianą CSS, aby zestaw „po” miał z czym się porównać.

**Contract**: `node shots.mjs <out-dir>`; struktura jak `context/archive/2026-10-12-places-page/screenshots/shots.mjs` (logowanie raz, `storageState` w `$CLAUDE_JOB_DIR/tmp`, `settle()`). Dla `colorScheme` ∈ {`light`, `dark`} i ścieżek `/tree`, `/persons`, `/sources`, `/places` — pełnostronicowy zrzut desktop 1440×900 (`NN-<route>-<theme>.png`), oraz zrzut fokusu klawiatury na pierwszym linku wiersza `/persons` w obu motywach z logiem `boxShadow`. Aplikacja z `scripts/e2e-server.sh` (baza `e2e`, `TreeViewDemoSeeder`).

#### 2. Wartości tokenów

**File**: `resources/css/app.css`

**Intent**: Wprowadzić paletę design systemu bez zmiany nazw zmiennych.

**Contract**: Wartości `:root` i `.dark` dokładnie jak w sekcji 1 `implementation.md` (artefakt), w tym nowe `--on-destructive`, `--source`, `--source-surface`, `--source-border`; `--radius: 0.625rem` zostaje. Usunąć `--chart-1…5` z `:root`, `.dark` i `--color-chart-*` z `@theme`. Zastąpić stare komentarze źródłowe przy `--ring` / `--sidebar-accent` / `--gender-*` jednym komentarzem wskazującym artefakt design systemu.

#### 3. `@theme`

**File**: `resources/css/app.css`

**Intent**: Udostępnić rodzinę `display` i cztery nowe kolory jako klasy Tailwind.

**Contract**: `--font-display: 'Newsreader', 'Source Serif 4', Georgia, serif;` oraz `--color-source`, `--color-source-surface`, `--color-source-border`, `--color-on-destructive` mapowane na zmienne.

#### 4. Font Newsreader

**File**: `vite.config.ts`

**Intent**: Załadować Newsreader 600 obok Instrument Sans.

**Contract**: druga pozycja w `fonts` wtyczki `laravel()`: `bunny('Newsreader', { weights: [600] })`.

### Success Criteria:

#### Automated Verification:

- Zestaw `screenshots/before/` istnieje (8 zrzutów tras + 2 zrzuty fokusu)
- `grep -rn "chart-" resources/js resources/css` nic nie zwraca
- `vendor/bin/sail npm run check` przechodzi
- `vendor/bin/sail npm run build` przechodzi

#### Manual Verification:

- Aplikacja w jasnym motywie ma papierowe tło i mech; menu jest ciemne w obu motywach
- W zakładce Network ładuje się plik fontu Newsreader

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Phase 2: Fokus i kolory w komponentach

### Overview

Pełny pierścień fokusu wszędzie, 3 px w menu i przy zamykaniu okien, kolory błędu i usuwania z tokenów, ikony toastów.

### Changes Required:

#### 1. Stała fokusu

**File**: `resources/js/lib/focus-ring.ts`

**Intent**: Pełne krycie pierścienia (3:1) bez wartości w pikselach.

**Contract**: `focusRing = 'outline-none focus-visible:ring-ring focus-visible:ring-3'`; komentarz bez zmian w sensie.

#### 2. Komponenty `ui/*`

**File**: `resources/js/components/ui/{button,input,select,toggle,checkbox,badge,textarea,tabs}.tsx`

**Intent**: Pełny pierścień w komponentach shadcn.

**Contract**: `focus-visible:ring-ring/50` → `focus-visible:ring-ring`. Nie ruszać `aria-invalid:ring-destructive/20|40`.

#### 3. Ręczny fokus w widokach

**File**: `components/persons/overview-tab.tsx:143,209`, `components/persons/person-candidate-list.tsx:132`, `components/persons/person-photo-menu.tsx:90`, `components/password-input.tsx:25`, `components/persons/family-tab.tsx:130`, `components/marriages/marriage-panel.tsx:70`, `components/tree/card-styles.ts:23`

**Intent**: Jeden wzorzec fokusu poza `ui/*`.

**Contract**: W pierwszych siedmiu miejscach zestaw `outline-none` + `ring-ring(/50)` + `ring-[3px]` zastąpić `focusRing` przez `cn()` (pozostałe klasy zostają). W `card-styles.ts` (pierścień odsunięty kart drzewa) tylko `ring-[3px]` → `ring-3`.

#### 4. Menu

**File**: `resources/js/components/app-sidebar.tsx:79,86`, `resources/js/components/ui/sidebar.tsx:403,424,472,559,684`

**Intent**: Fokus 3 px także w menu; najechanie na przyciski w ciemnym menu bez jasnego kafla.

**Contract**: `focus-visible:ring-2` → `focus-visible:ring-3` przy `ring-sidebar-ring`. W `app-sidebar.tsx:79,86` (przyciski `variant="ghost"`: „Zamknij menu” i `SidebarTrigger`) dodać `hover:bg-sidebar-accent hover:text-sidebar-accent-foreground` — wariant `ghost` bierze `hover:bg-accent` z tokenów strony, co w jasnym motywie daje jasny kafel na ciemnym menu.

#### 5. Przycisk zamykania okien

**File**: `resources/js/components/ui/dialog.tsx:64-66`, `resources/js/components/ui/sheet.tsx:73-75`

**Intent**: Fokus 3 px tylko z klawiatury i polska etykieta.

**Contract**: `focus:ring-2` → `focus-visible:ring-3`, `focus:ring-ring` → `focus-visible:ring-ring`, `focus:ring-offset-2` → `focus-visible:ring-offset-2`; `sr-only` „Close” → „Zamknij okno”.

#### 6. Wariant `destructive`

**File**: `resources/js/components/ui/button.tsx:15`, `resources/js/components/ui/badge.tsx:17`

**Intent**: Kolor tekstu na wypełnieniu z tokenu.

**Contract**: `text-white` → `text-on-destructive`; w badge usunąć `dark:bg-destructive/60`.

#### 7. Tekst błędu

**File**: 9 miejsc z Current State Analysis (`passkey-item`, `parish-panel`, `locality-panel`, `delete-source-dialog`, `source-panel` ×3, `person-modal-header`, `source-group`)

**Intent**: W ciemnym motywie `destructive` to wypełnienie, nie kolor tekstu.

**Contract**: `text-destructive` → `text-destructive-foreground` i `hover:text-destructive` → `hover:text-destructive-foreground`; tła `hover:bg-destructive/10` zostają. Po zmianie `grep -rnE "text-destructive([^-]|$)" resources/js` nic nie zwraca.

#### 8. Ikony toastów

**File**: `resources/js/components/ui/sonner.tsx`

**Intent**: Ikona sukcesu i błędu w kolorze stanu.

**Contract**: prop `icons={{ success: <CircleCheckIcon className="text-primary size-4" />, error: <CircleXIcon className="text-destructive-foreground size-4" /> }}` (ikony z `lucide-react`), przed `{...props}`.

### Success Criteria:

#### Automated Verification:

- `grep -rn "ring-ring/50" resources/js` nic nie zwraca
- `grep -rnE "text-destructive([^-]|$)" resources/js` nic nie zwraca
- `vendor/bin/sail npm run check` przechodzi
- `vendor/bin/sail npm run types:check` przechodzi
- `vendor/bin/sail npm run test:js` przechodzi

#### Manual Verification:

- Tab po `/persons`, panelu osoby i menu: pierścień pełny, 3 px, w jasnym i ciemnym motywie
- Przycisk „Usuń” w dialogu usuwania ma biały tekst na czerwieni w obu motywach; tekst błędu w ciemnym motywie jest jasnoczerwony
- Toast po zapisie pokazuje zieloną ikonę

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Phase 3: Typografia i teksty

### Overview

`font-display` w tytułach, jedna forma „data nieznana”, polskie teksty w ustawieniach, opisy list bez „Twój”.

### Changes Required:

#### 1. `font-display`

**File**: `components/heading.tsx:21`, `components/side-panel.tsx:224`, `components/ui/dialog.tsx:103`, `components/tree/sheet-person-card.tsx:87`, `components/empty-state.tsx:23`, `components/app-logo.tsx:12`

**Intent**: Szeryfowa rodzina niesie tożsamość tylko w tytułach i imieniu na karcie.

**Contract**: dodać klasę `font-display` do tytułu strony (`variant="page"`), tytułu `SidePanel`, `DialogTitle`, imienia na karcie, tytułu pustego stanu i nazwy w logo; rozmiary i grubości bez zmian.

#### 2. „data nieznana”

**File**: `resources/js/lib/persons-index.ts:221`, `resources/js/lib/persons-index.test.ts:259,262`

**Intent**: Jedna forma w całej aplikacji.

**Contract**: `formatEventMeta('zm.', …)` bez daty zwraca „zm. data nieznana” / „zm. data nieznana, Tarnów”; test zaktualizowany do nowej formy.

#### 3. Ustawienia po polsku

**File**: `app/Http/Controllers/Settings/SecurityController.php:62`, `app/Http/Controllers/Settings/ProfileController.php:42`, `lang/pl.json:154,164`, `resources/js/pages/settings/{security,profile,appearance}.tsx` (`sr-only` h1), `resources/js/components/passkey-item.tsx:67-86`

**Intent**: Toasty, ukryte nagłówki i dialog usuwania klucza po polsku.

**Contract**: `__('Zapisano hasło')`, `__('Zapisano profil')` (polski klucz wprost, jak w innych kontrolerach); usunąć martwe klucze `Password updated.` / `Profile updated.` z `lang/pl.json`. h1: „Ustawienia zabezpieczeń”, „Ustawienia profilu”, „Ustawienia wyglądu”. Dialog klucza: `sr-only` „Usuń klucz”, tytuł „Usunąć klucz dostępu?”, opis po polsku, „Anuluj”, przycisk „Usuń klucz” / „Usuwanie…”.

#### 4. Opisy list

**File**: `resources/js/pages/persons/index.tsx:221`, `resources/js/pages/sources/index.tsx:397`, `resources/js/pages/places/index.tsx:361`

**Intent**: Bez „Ty/Twój” w nagłówkach.

**Contract**: „Osoby zapisane w drzewie genealogicznym”, „Akty, zdjęcia nagrobków i inne dokumenty zapisane w drzewie genealogicznym”, „Miejscowości i parafie zapisane w drzewie genealogicznym”.

### Success Criteria:

#### Automated Verification:

- `grep -rn "Twoim\|Twojego\|(data nieznana)" resources/js` i `grep -rn "Password updated\|Profile updated" app lang` nic nie zwracają
- `vendor/bin/sail npm run test:js` przechodzi (w tym `persons-index.test.ts`)
- `vendor/bin/sail artisan test --compact tests/Feature/Settings` przechodzi
- `vendor/bin/sail bin pint --dirty --format agent` bez zmian do zrobienia
- `vendor/bin/sail npm run check` i `vendor/bin/sail npm run types:check` przechodzą

#### Manual Verification:

- Tytuły stron list, panelu, okna, pustego stanu, nazwa w logo i imię na karcie drzewa są szeryfowe; przyciski, etykiety i tabele zostają bezszeryfowe
- Zmiana hasła i profilu pokazuje polski toast

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Phase 4: Strażnik i bramka wizualna

### Overview

Rozszerzenie `view-tokens.test.ts` o drzewo i panele, pełne bramki z sekcji 4 i zrzuty „po”.

### Changes Required:

#### 1. `VIEW_FILES` i `EXCEPTIONS`

**File**: `resources/js/lib/view-tokens.test.ts`

**Intent**: Strażnik tokenów pilnuje kart drzewa, zaślepek i paneli, gdzie jest najwięcej tokenów płci i drzewa.

**Contract**: Dodać do `VIEW_FILES`: `components/tree/{ancestor-card,sheet-person-card,unknown-person-card,union-node,add-person-panel,marry-couple-dialog,set-root-person-button}.tsx`, `components/tree/{card-styles,add-person-config}.ts`, `components/side-panel.tsx`, `components/persons/person-modal.tsx`, `components/sources/source-panel.tsx`, `components/marriages/marriage-panel.tsx`. Dodać wpis `EXCEPTIONS` `{ file: 'components/sources/source-panel.tsx', token: 'style={', reason: 'szerokość paska postępu wysyłania pliku liczona w runtime (procent); odroczone: pasek przez zmienną CSS lub <progress> w osobnej zmianie (plan.md, What We\'re NOT Doing)' }`. Zaktualizować komentarz nagłówka i nazwę `describe` (widoki list, drzewo i panele) oraz dopisać przy `VIEW_FILES`, że `family-sheet.tsx` i `ancestor-chart.tsx` są pominięte z powodu geometrii liczonej w runtime (do zmiany linii drzewa).

#### 2. Zrzuty „po” i werdykt

**File**: `context/changes/design-tokens/screenshots/after/*.png`, `screenshots/verdict.md`

**Intent**: Bramka wizualna dla jasnego i ciemnego motywu.

**Contract**: `node shots.mjs screenshots/after` — ten sam zestaw co „przed”. `verdict.md`: dla każdej trasy i motywu jedno zdanie, co się zmieniło (tło, akcent, menu, tytuł szeryfowy) i czy coś odstaje (np. kontrast, nieczytelny tekst); log `boxShadow` fokusu potwierdza pełny kolor `ring` i 3 px.

### Success Criteria:

#### Automated Verification:

- `vendor/bin/sail npm run test:js` przechodzi z rozszerzonym `VIEW_FILES`
- `vendor/bin/sail npm run check` przechodzi
- `vendor/bin/sail npm run types:check` przechodzi
- `vendor/bin/sail npm run build` przechodzi
- `screenshots/after/` zawiera 8 zrzutów tras i 2 zrzuty fokusu, a log fokusu pokazuje kolor `ring` bez przezroczystości i 3 px

#### Manual Verification:

- `verdict.md` i zrzuty jasny/ciemny `/tree`, `/persons`, `/sources`, `/places` zaakceptowane przez użytkownika

**Implementation Note**: Po fazie zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `persons-index.test.ts` — nowa forma „zm. data nieznana” (z miejscem i bez).
- `view-tokens.test.ts` — rozszerzona lista plików, test „scans every file it names” łapie literówki w ścieżkach.

### Integration Tests:

- `tests/Feature/Settings` — istniejące testy profilu i bezpieczeństwa przechodzą po zmianie tekstu toastu (nie asercjonują treści; nowe testy dla zmiany samego tekstu nie są potrzebne).

### Manual Testing Steps:

1. Przełącz motyw w Ustawieniach → Wygląd i obejrzyj `/tree`, `/persons`, `/sources`, `/places`.
2. Przejdź klawiaturą przez listę `/persons`, otwórz panel osoby, dojdź do menu — pierścień pełny 3 px.
3. Otwórz dialog usuwania osoby — przycisk zamykania ma etykietę „Zamknij okno” (czytnik / inspektor) i ring 3 px z klawiatury.
4. Zapisz profil — toast „Zapisano profil” z zieloną ikoną.

## References

- Design system: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU (`project/implementation.md` sekcje 1–4, `project/README.md`)
- Wzorzec zrzutów: `context/archive/2026-10-12-places-page/screenshots/shots.mjs`
- Strażnik tokenów: `resources/js/lib/view-tokens.test.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tokeny i font

#### Automated

- [x] 1.1 Zestaw `screenshots/before/` istnieje (8 zrzutów tras + 2 zrzuty fokusu) — 2e766b5
- [x] 1.2 `grep -rn "chart-" resources/js resources/css` nic nie zwraca — 2e766b5
- [x] 1.3 `vendor/bin/sail npm run check` przechodzi — 2e766b5
- [x] 1.4 `vendor/bin/sail npm run build` przechodzi — 2e766b5

#### Manual

- [x] 1.5 Aplikacja w jasnym motywie ma papierowe tło i mech; menu jest ciemne w obu motywach — 2e766b5
- [x] 1.6 W zakładce Network ładuje się plik fontu Newsreader — 2e766b5

### Phase 2: Fokus i kolory w komponentach

#### Automated

- [ ] 2.1 `grep -rn "ring-ring/50" resources/js` nic nie zwraca
- [ ] 2.2 `grep -rnE "text-destructive([^-]|$)" resources/js` nic nie zwraca
- [ ] 2.3 `vendor/bin/sail npm run check` przechodzi
- [ ] 2.4 `vendor/bin/sail npm run types:check` przechodzi
- [ ] 2.5 `vendor/bin/sail npm run test:js` przechodzi

#### Manual

- [ ] 2.6 Tab po `/persons`, panelu osoby i menu: pierścień pełny, 3 px, w jasnym i ciemnym motywie
- [ ] 2.7 Przycisk „Usuń” w dialogu usuwania ma biały tekst na czerwieni w obu motywach; tekst błędu w ciemnym motywie jest jasnoczerwony
- [ ] 2.8 Toast po zapisie pokazuje zieloną ikonę

### Phase 3: Typografia i teksty

#### Automated

- [ ] 3.1 `grep -rn "Twoim\|Twojego\|(data nieznana)" resources/js` i `grep -rn "Password updated\|Profile updated" app lang` nic nie zwracają
- [ ] 3.2 `vendor/bin/sail npm run test:js` przechodzi (w tym `persons-index.test.ts`)
- [ ] 3.3 `vendor/bin/sail artisan test --compact tests/Feature/Settings` przechodzi
- [ ] 3.4 `vendor/bin/sail bin pint --dirty --format agent` bez zmian do zrobienia
- [ ] 3.5 `vendor/bin/sail npm run check` i `vendor/bin/sail npm run types:check` przechodzą

#### Manual

- [ ] 3.6 Tytuły stron list, panelu, okna, pustego stanu, nazwa w logo i imię na karcie drzewa są szeryfowe; przyciski, etykiety i tabele zostają bezszeryfowe
- [ ] 3.7 Zmiana hasła i profilu pokazuje polski toast

### Phase 4: Strażnik i bramka wizualna

#### Automated

- [ ] 4.1 `vendor/bin/sail npm run test:js` przechodzi z rozszerzonym `VIEW_FILES`
- [ ] 4.2 `vendor/bin/sail npm run check` przechodzi
- [ ] 4.3 `vendor/bin/sail npm run types:check` przechodzi
- [ ] 4.4 `vendor/bin/sail npm run build` przechodzi
- [ ] 4.5 `screenshots/after/` zawiera 8 zrzutów tras i 2 zrzuty fokusu, a log fokusu pokazuje kolor `ring` bez przezroczystości i 3 px

#### Manual

- [ ] 4.6 `verdict.md` i zrzuty jasny/ciemny `/tree`, `/persons`, `/sources`, `/places` zaakceptowane przez użytkownika
