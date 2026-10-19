---
date: 2026-10-19T06:11:15+02:00
researcher: Claude (Opus 5.5)
git_commit: eccf927
branch: develop
repository: root-keeper
topic: "Drzewo jako domyślny dashboard, który wypełnia okno: wyższe karty, cały arkusz (dziadkowie, rodzice, para, dzieci) na jak największej części okna"
tags: [research, ui, tree, family-sheet, zoom-canvas, fit-view, card-styles, fortify-home]
status: complete
last_updated: 2026-10-19
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Kontynuacja: inspiracja PLSoft „Formularz rodzinny” (sekcja Follow-up 2026-10-19). Zestawienie z kodem, opcja kart stopniowanych, która może zastąpić U5; nowe pytania P1–P4. Zamknięta luka tokens.json."
---

# Research: drzewo jako domyślny dashboard, wypełniające okno

**Date**: 2026-10-19T06:11:15+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: eccf927
**Branch**: develop
**Repository**: root-keeper

## Research Question

Cel z `/10x-ui` (`change.md` → Notes): „bardziej jak program do drzewa genealogicznego”; `/tree` ma być domyślnym dashboardem (osoba + małżonek z przełącznikiem związków jak teraz + dzieci + rodzice + dziadkowie); karty wyższe, a cały arkusz możliwie wypełnia okno.

Pytania badawcze:
1. Co dziś decyduje o stronie po zalogowaniu i co trzeba zmienić, by była nią `/tree`?
2. Jak arkusz ustala rozmiar kart i skalę — dlaczego zostaje puste miejsce w pionie i dlaczego przy ≤ 1440 px dziadkowie są ucięci?
3. Kogo jeszcze dotknie zmiana kart i geometrii (widok przodków, testy)?
4. Jakie wcześniejsze decyzje ta zmiana zamyka, a jakim przeczy?

## Summary

- **Strona po zalogowaniu** to jedna wartość: `config/fortify.php:76` `'home' => '/persons'`. Wszystkie odpowiedzi Fortify po logowaniu/rejestracji/weryfikacji/2FA/potwierdzeniu hasła oraz passkeys i `redirectUsersTo` (`bootstrap/app.php:21`) czytają ją pośrednio. Jedyny twardy literał na froncie to fallback passkey: `resources/js/components/passkey-verify.tsx:35` (`personsIndex()`). `/tree` jest bezpieczne dla nowego konta: `CreateNewUser.php:35` tworzy drzewo, a `tree/show.tsx:56-64` ma stan pusty „Dodaj pierwszą osobę”. Zmiana przestawia 9 asercji w `tests/Feature/Auth/*` i globalny `tests/e2e/auth.setup.ts:30` (wejście przez `/login`, `:14`), czyli od niego zależy każdy przebieg E2E. `seed.spec.ts` zostaje zielony, bo wchodzi przez `/persons` i trafia tam przez `intended()`.
- **Pion jest pusty, bo nic nie czyta wysokości obszaru przy ustalaniu rozmiaru kart.** `fitColumnRem` (`lib/fit-view.ts:37-59`) liczy kolumnę z szerokości (12–18 rem). Wysokość karty to stałe `min-h-24` (`card-styles.ts:8,16`) plus treść (92–112 px). Wysokość obszaru wchodzi tylko do skali `min(vw/cw, vh/ch, 1.5)` (`fit-view.ts:104-108`). W każdym policzonym przypadku skalę ogranicza szerokość: 8 kolumn × ≥ 12 rem.
- **Ucięcie przy ≤ 1440 px** wynika z minimum kolumny 12 rem (`fit-view.ts:20`) i podłogi skali 0,75 (`fit-view.ts:2`). Jest to otwarta, odroczona regresja D1 (`archive/2026-10-12-tree-card-name-one-line/research.md:62`).
- **Wspólny wniosek:** wyższe karty mieszczą się dopiero wtedy, gdy karty są węższe lub szerokość przestaje ograniczać skalę. Wysokość karty sterowana wysokością obszaru i węższa kolumna z niższym minimum dają oba efekty naraz: puste pasy znikają, a dziadkowie mieszczą się przy 1366–1440 px. Kosztem jest kształt karty. Przy 1366 px kolumna, która mieści 8 dziadków, ma ok. 7,5 rem, a pozioma karta z paskiem zdjęcia potrzebuje ≥ 12 rem (`archive/2026-10-11-person-photo-on-card/plan-brief.md:32`). Rozstrzygnięte przez użytkownika (sekcja Decisions): karta pozioma, wyższa (U1), cała rodzina widoczna na 1366–1440 px dzięki podłodze skali ok. 0,65 (U3, U5), bez nagłówka (U2), przodkowie bez zmian (U4). **Zastąpione w kontynuacji (P1–P4):** dziadkowie bez paska zdjęcia i węższa kolumna, więc skala ≈ 0,95–1 i podłoga 0,75 zostaje. Para dostaje pełne dane i najwyższy rząd, wysokości rzędów są stopniowane, a nad dziećmi pojawia się belka „Dzieci (N)”.
- **Zmiana kart dotyka też widoku przodków:** `card-styles.ts` i `CardBody` są współdzielone z `ancestor-card.tsx`. Decyzje FR-013 „bez ręcznego przybliżania” (`foundation/prd.md:135`) i „dodatkową szerokość w kolumnę, skala ≈ 1” (`card-name-one-line/plan-brief.md:23,28`) obowiązują dalej. Ta zmiana wypełnia wysokość rozmiarem kart, nie przybliżaniem.

## Charges

Wariant kontraktu: **istniejący system projektowy** (tokeny `resources/css/app.css`, komponenty `resources/js/components/ui/`, makieta `/tree` w artefakcie DS `project/Main.dc.html`). Skan wartości zakodowanych na stałe na 7 plikach widoku: **0 trafień**. 8 `style=` w `family-sheet.tsx` to dopuszczony wyjątek geometrii (`resources/js/lib/view-tokens.test.ts:114-118`).

| # | Kategoria | Dowód (plik:linia) | Wpływ na użytkownika |
| --- | --- | --- | --- |
| C1 | Przypadkowa architektura | `config/fortify.php:76` `'home' => '/persons'`; `resources/js/components/passkey-verify.tsx:35` fallback `personsIndex()` | Po zalogowaniu autor trafia na listę osób, a nie na drzewo, które jest pierwszą pozycją menu i celem logo (`app-sidebar.tsx:28-54,66-67`). Dashboardem jest dziś lista. |
| C2 | Brakujący token / przypadkowa architektura geometrii | `resources/js/lib/fit-view.ts:37-59` (kolumna tylko z szerokości); `resources/js/components/tree/card-styles.ts:8,16` (`min-h-24`, jedyne źródło wysokości karty); `resources/js/components/zoom-canvas.tsx:154-176` (`settleColumn` nie czyta `clientHeight`) | Przy 1920×1080 arkusz zajmuje ok. 542 z 938 px wysokości obszaru (~43% pusto). Karty są niskie mimo wolnego miejsca. |
| C3 | Przypadkowa architektura geometrii | `resources/js/lib/fit-view.ts:20` `COLUMN_MIN_REM = 12`, `:2` `TREE_FIT_FLOOR = 0.75`; `zoom-canvas.tsx:202-245` (tryb kotwicy) | Przy 1366–1440 px skrajni dziadkowie są ucięci po obu stronach, a widok startuje zakotwiczony na parze. Nie widać całej rodziny bez przeciągania. |
| C4 | Brakujący współdzielony wzorzec karty | `resources/js/components/tree/family-sheet.tsx:646` (rodzice `col-span-2`), `:709-712` (para = 2 kolumny + odstęp), `:54` `WIDE_CHILDREN_MAX = 4`; `resources/js/components/tree/sheet-person-card.tsx:42-46` (pasek `w-12 @xs:w-18`) | Karty rodziców, pary i dzieci są szerokie i płaskie (ok. 4:1), a dziadków wąskie. Arkusz nie wygląda jak program genealogiczny z jednakowymi kartami, a szeroki rząd rodziców ogranicza skalę. |
| C5 | Przypadkowa architektura (chrome strony) | `resources/js/pages/tree/show.tsx:84-108` (h1 + akcje + `gap-6`); `resources/js/layouts/app/app-sidebar-layout.tsx:20-35` (margines, `py-6`, okruszki) | Ok. 110 px nad obszarem drzewa i 32 px pod nim (≈ 15% wysokości przy 900 px). Imię probanta powtarza się w okruszkach i w h1. |

Żaden zarzut nie jest odroczony na etapie badania. Decyzja o telefonie (390 px) jest w Open Questions.

## Detailed Findings

### 1. Strona po zalogowaniu (C1)

- Źródło wartości: `config/fortify.php:76`. Brak `Fortify::redirects`, własnych odpowiedzi i `config/passkeys.php` (sprawdzone: `app/Providers/*`, `app/Http/Responses` nie istnieje, `bootstrap/app.php`).
- Odpowiedzi vendor kończą się `redirect()->intended(Fortify::redirects(...))`, a ta funkcja wraca do `fortify.home` (`vendor/laravel/fortify/src/Fortify.php:99-101`). Dotyczy to Login, TwoFactorLogin, Register, VerifyEmail (`?verified=1`), PasswordConfirmed i RedirectAsIntended (`vendor/laravel/fortify/src/Http/Responses/*.php`). Passkeys: `'passkeys.redirect' => Fortify::redirects('login')` (`vendor/laravel/fortify/src/FortifyServiceProvider.php:139`). Reset hasła idzie do `login`, wylogowanie do `/` — bez zmian.
- Zalogowany użytkownik na `/login`/`/register` trafia do `config('fortify.home')` (`bootstrap/app.php:21`). `/` przekierowuje na `/login` (`routes/web.php:28`).
- Frontend: jedyny literał to `passkey-verify.tsx:35`, używany na `pages/auth/login.tsx:30` i `pages/auth/confirm-password.tsx:19`. Logo w panelu bocznym i nagłówku mobilnym prowadzi już do `/tree` (`app-sidebar.tsx:66-67`, `app-mobile-header.tsx:8,26`).
- `/tree` jako cel dla nowego konta: `TreeController.php:20-33` renderuje `tree/show` z `sheet = null`, gdy nie ma probanta. Strona pokazuje `ProbandEmptyState` (`show.tsx:124-138`), a stany są pokryte w `tests/Feature/TreeTest.php:113-146`. Nowe konto dostaje drzewo w `CreateNewUser.php:35` (test `RegistrationTest.php:29-40`). Brak drzewa u użytkownika (`$tree` null, `TreeController.php:23`) kończy się błędem, ale `/persons` ma to samo założenie (`PersonController.php:33`). Zmiana nie wprowadza tu nowego ryzyka.
- Asercje do przestawienia (9 w Pest + 1 globalna w E2E):
  - `tests/Feature/Auth/AuthenticationTest.php:29`, `RegistrationTest.php:26`, `EmailVerificationTest.php:37,82,97`, `VerificationNotificationTest.php:31` — `persons.index`;
  - `tests/Feature/Auth/EntryTest.php:12` `assertRedirect('/persons')` i `:15-20` (komponent `persons/index`, nazwa testu „ends up on the people list”);
  - `tests/e2e/auth.setup.ts:30-33`: `toHaveURL(/\/persons/)` i nagłówek „Osoby”, po `goto('/login')` w `:14`.
  - `tests/e2e/seed.spec.ts:47` zostaje zielony (wejście przez `/persons` → `intended()`), ale jego tytuł mówi o lądowaniu na `/persons`.
- Brak pokrycia (bez zmian w tej zmianie): przekierowanie po wyzwaniu 2FA, po potwierdzeniu hasła i po logowaniu passkey.

### 2. Dopasowanie arkusza (C2, C3)

Przepływ (`zoom-canvas.tsx`):
- Stan `columnRem` startuje od `COLUMN_MIN_REM` (`:110-111`). Treść dostaje tylko `children(columnRem)` (`:28`, `:551-553`), czyli szerokość kolumny, bez wysokości.
- `ResizeObserver` na obszarze i treści (`:341-345`) wywołuje `refit()` tylko wtedy, gdy widok nie został przesunięty (`:296-315`). `refit()` najpierw wywołuje `settleColumn()` (`:154-176`). Jeśli kolumna się zmieniła, ustawia stan i zwraca false, a dopasowanie skali odbywa się w następnym przebiegu.
- `fit()` (`:178-254`) → `chooseFitView` (`fit-view.ts:84-113`): `scale = min(vw/cw, vh/ch, max)`. Poniżej `floor` wynik to kotwica na parze, a gdy para się nie mieści — na karcie osoby w centrum (`chooseAnchor`, `fit-view.ts:71-77`).
- Wysokość obszaru jest używana wyłącznie w `vh/ch` i przy centrowaniu (`:200`, `:243`). Naturalne miejsce na wysokość sterowaną obszarem to `settleColumn()`, gdzie `wrapper.clientHeight` jest dostępne obok `clientWidth`. Potrzebny byłby drugi stan z tym samym uzgadnianiem „zmieniło się → false” i drugi argument callbacku `children`.

Pionowa budowa arkusza (`family-sheet.tsx`), H = wysokość rzędu kart:
- pady `p-2` 8+8 px (`:599`); dziadkowie H; łączniki dziadków `h-11` 44 px (`:231`); rodzice H; łączniki rodziców 44 px albo 48 px z akcją „Połącz jako małżeństwo”; para H; trzon `h-7.5` 30 px albo 48 px (`:752-769`); dzieci `pt-4` + H + `pb-2` (`:487-490`).
- Suma przy 1–3 dzieciach i bez akcji: **4H + 158 px**. Bez dzieci: 4H + 134. Przy ≥ 4 dzieciach (slot pod rzędem): 5H + 166.
- Szerokość: `8c + 5,25` rem + 1 rem padu (`:33-35`, `:30`).

Obliczenia (ręczne, rem = 16 px, panel boczny rozwinięty, bez otwartego panelu; nie mierzone w przeglądarce):

| Okno | Obszar drzewa (≈) | Kolumna dziś | Treść dziś | Skutek dziś | Kolumna, przy której mieści się całość w skali 1 | H przy skali 1 = (wys. − 158) / 4 |
| --- | --- | --- | --- | --- | --- | --- |
| 1920×1080 | 1624 × 938 px | 12 rem | 1636 × 542 px | skala ≈ 0,993 (szerokość), ~43% pionu puste | 12 rem (jest) | ≈ 195 px |
| 1440×900 | 1144 × 758 px | 12 rem | 1636 px szer. | całość wymaga 0,70 < 0,75 → kotwica, ~41 px ucięte z każdej strony | ≈ 8 rem | ≈ 150 px |
| 1366×768 (domyślny viewport E2E, `playwright.config.ts:31-36`) | ≈ 1070 × 626 px (pomiar z `card-name-one-line/research.md:62`) | 12 rem | 1636 × 624 px | kotwica 0,75 (D1) | ≈ 7,5 rem | ≈ 117 px |

Wniosek (wnioskowanie z tabeli): przy 1366–1440 px całe 5 pokoleń mieści się w skali 1 przy kolumnie ok. 7,5–8 rem (lub ≈ 10,4 rem przy 1366 px i ≈ 11,1 rem przy 1440 px w skali 0,75). Pozioma karta z paskiem zdjęcia ma dolną granicę 12 rem (`archive/2026-10-11-person-photo-on-card/plan-brief.md:32`: ≥ 6 rem tekstu obok paska). Pionowa karta (zdjęcie u góry) nie ma tej granicy, bo tekst zajmuje całą szerokość karty.

### 3. Konsumenci i testy (wpływ zmiany)

- `ZoomCanvas` jest używany w dwóch miejscach: `family-sheet.tsx:570-578` i `ancestor-chart.tsx:97-105` (ze `zoomControls`, podłogą `ANCESTORS_FIT_FLOOR = 0.1`).
- `cardSurface` / `personCardSurface` (`card-styles.ts:7-8,15-16`) używają: `unknown-person-card.tsx:33,46`, `sheet-person-card.tsx:133`, `ancestor-card.tsx:23-24,34`. `CardBody` używają `SheetPersonCard` (`sheet-person-card.tsx:142,159`) i `AncestorCard` (`ancestor-card.tsx:41-47`). Zmiana tych plików zmienia więc także widok przodków, chyba że plan rozdzieli warianty.
- `COLUMN_MIN_REM` / `COLUMN_MAX_REM` czytają tylko `zoom-canvas.tsx` i `fit-view.ts` (plus testy). Uzasadnienie `ANCESTORS_FIT_FLOOR` (`fit-view.ts:7-13`) opiera się na minimum 12 rem. Inwariant: `COLUMN_MAX_REM` < 20 rem, bo przy 20 rem włącza się szeroki pasek `@xs` (`fit-view.ts:22`).
- Wysokość karty zależy dziś częściowo od treści (92–112 px przy dwuliniowym imieniu, `sheet-person-card.tsx:90-120`). Komórki siatki się rozciągają, a karty mają `h-full`, więc w rzędzie wyrównują się do najwyższej.
- Testy pilnujące geometrii:
  - `resources/js/lib/fit-view.test.ts:24-132` — kontrakty `fitColumnRem` (np. `fit(1624)` → 12, `fit(2264)` → 16,75), `chooseAnchor`, `chooseFitView`. Zmiana minimum kolumny przestawia `:24-30,40-56`.
  - `resources/js/lib/view-tokens.test.ts:21-118` — w `sheet-person-card.tsx`, `card-styles.ts`, `ancestor-card.tsx` i `unknown-person-card.tsx` zakazany jest `style=` i klasy `-[Npx]`. Wyjątek ma tylko `family-sheet.tsx`. Klasa ze zmienną CSS (`min-h-(--x)`) nie łapie się na wzorzec `:89`.
  - `tests/e2e/tree-card-name.spec.ts:65-68` (2560×1440: imię dziadka w 1 linii) i `:109-114` (1366×768: „Aleksandra Brzęczyszczykiewicz” w ≤ 2 liniach). Oba zależą od szerokości tekstu w karcie.
  - `tests/e2e/ancestors-view.spec.ts:175-224` — tylko względne szerokości przy przybliżaniu.
  - Pozostałe specyfikacje drzewa (`tree-card-recentre`, `tree-slot-*`, `person-photo-on-card`) nie sprawdzają rozmiarów ani pozycji. Żaden test nie sprawdza, że dziadkowie mieszczą się w oknie ani że arkusz wypełnia wysokość.
  - Nagłówek h1 jest kotwicą E2E: `tests/e2e/support/navigation.ts:75-78` (`gotoTree` czeka na h1) i `person-panel-unsaved-guard.spec.ts:58` (treść h1).

### 4. Nagłówek nad drzewem (C5)

- Łańcuch: `show.tsx:41-54` `fillHeight` → `app-sidebar-layout.tsx:20-35` (`h-svh`, inset `md:m-2`, `py-6 md:px-4`, `Breadcrumbs`) → `show.tsx:84` `flex … gap-6` → wiersz nagłówka `:85-107` (h1 `text-xl`, akcje `Button size="sm"` h-8 albo `Badge` dla probanta) → `ZoomCanvas` `min-h-64 flex-1` (`zoom-canvas.tsx:489-491`).
- Nad obszarem jest ≈ 8 + 24 + 22 + 32 + 24 ≈ 110 px, pod nim ≈ 32 px. Panel boczny dodawania wchodzi przez portal (`side-panel.tsx:93-123`) i zwęża tylko szerokość.
- Ostatni okruszek równa się h1 (`archive/2026-09-30-app-shell-sidebar/plan-brief.md:22,29`).

## Code References

- `config/fortify.php:76` — `'home' => '/persons'`
- `bootstrap/app.php:21` — `redirectUsersTo(fn () => config('fortify.home'))`
- `resources/js/components/passkey-verify.tsx:9,35` — fallback `personsIndex()`
- `app/Http/Controllers/TreeController.php:20-33` — `/tree` bez probanta → `sheet: null`
- `resources/js/pages/tree/show.tsx:41-54,84-108,124-138` — `fillHeight`, nagłówek, stan pusty
- `resources/js/lib/fit-view.ts:2,5,20,23,26,37-59,84-113` — podłoga/maks. skali, granice kolumny, `fitColumnRem`, `chooseFitView`
- `resources/js/components/zoom-canvas.tsx:110-111,154-176,178-254,296-315,551-553` — stan kolumny, `settleColumn`, `fit`, `refit`, callback treści
- `resources/js/components/tree/family-sheet.tsx:27,33-48,54,231,598-604,646,709-712,752-769,487-490` — 8 kolumn, siatka, progi dzieci, pasma pionowe
- `resources/js/components/tree/card-styles.ts:7-8,15-16` — `cardSurface`, `personCardSurface` (`min-h-24`)
- `resources/js/components/tree/sheet-person-card.tsx:42-46,90-120,163-176` — pasek zdjęcia, kolumna danych, przycisk oka
- `resources/js/components/tree/ancestor-card.tsx:23-24,34,41-47` — współdzielone style i `CardBody`
- `resources/js/lib/fit-view.test.ts`, `resources/js/lib/view-tokens.test.ts:89,114-118` — testy kontraktu geometrii i tokenów
- `tests/Feature/Auth/{Authentication,Registration,EmailVerification,VerificationNotification,Entry}Test.php`, `tests/e2e/auth.setup.ts:14,30-33` — asercje strony po zalogowaniu
- `tests/e2e/tree-card-name.spec.ts:65-68,109-114`, `tests/e2e/support/navigation.ts:75-78` — szerokość tekstu karty, h1

## Architecture Insights

- Rozmiar arkusza ma dwa pokrętła: kolumnę (z szerokości) i skalę (transform). Trzeciego — wysokości karty — nie ma, a archiwum wprost wyłączyło je z zakresu (`archive/2026-10-12-tree-card-name-one-line/plan.md:45`). Ta zmiana je dodaje.
- Wartości geometrii żyją w TS (`fit-view.ts`), a wysokość karty w klasie Tailwind (`card-styles.ts`). Nie ma tokenu w `app.css` dla wysokości karty ani kolumny. W DS (`project/ds/rootkeeper/tokens.json`) są według archiwum `person-card-min-height` i `photo-strip-width`, ale tego pliku nie czytałem (luka). Wysokość obliczana w czasie działania i tak nie może być statycznym tokenem. Zgodny z `view-tokens.test.ts` sposób to zmienna CSS ustawiona na korzeniu arkusza (który ma już dopuszczony `style`) i klasa `min-h-(--…)`/`h-(--…)` w `card-styles.ts`.
- Makieta `/tree` w DS (`project/Main.dc.html` w artefakcie Nvqqp3f43K3Kf22tNudBuS) odpowiada dzisiejszej implementacji: poziome karty z paskiem po lewej, wysokość 96 px, rodzice/para 2 kolumny, duży h1 + „Probant”, domyślna skala 0,8. Wyższe/pionowe karty to świadome odejście od makiety — po zmianie trzeba będzie zaktualizować DS.

## Historical Context (from prior changes)

- `context/archive/2026-10-05-tree-fits-screen/plan-brief.md:16,23-24,32` — arkusz wypełnia wysokość pod nagłówkiem przez `fillHeight` + `ZoomCanvas`; skala 0,75–1,5. **Nadal prawda.** Mechanizm zostaje, zmienia się to, czym wypełniamy pion.
- `context/archive/2026-10-05-tree-fits-screen/plan.md:178` — „proporcje jak dziś: dziadek 1 kolumna, rodzic 2, para 4”. **Do ponownej decyzji** (C4).
- `context/foundation/prd.md:135` (FR-013) — skala zależy od ekranu, bez ręcznego przybliżania. **Obowiązuje.** Zmiana nie dodaje przybliżania.
- `context/archive/2026-10-11-person-photo-on-card/plan-brief.md:32,77` — kolumna 10 → 12 rem, „nie wracać do 10rem” (powód: ≥ 6 rem tekstu obok paska). **Obowiązuje dla karty poziomej.** Dla karty pionowej powód odpada. Odrzucona alternatywa: kolumna ~14 rem, bo 1366 px częściej by kotwiczył (`reviews/plan-review.md:25-42`).
- `context/archive/2026-10-12-tree-card-name-one-line/plan-brief.md:23-31`, `plan.md:45,55` — kolumna 12–18 rem z szerokości, skala ≈ 1, imię ≤ 2 linie, 16 px, `COLUMN_MAX` < 20 rem; „nie mierzymy kolumny pod kątem wysokości” poza zakresem. **Ta zmiana zamyka ten punkt poza zakresem.** Użytkownik odrzucił wtedy: stałą szerszą kolumnę, samą kompaktową kartę, jedną linię z wielokropkiem, przeniesienie oka i font 14 px (`research.md:176`).
- `context/archive/2026-10-12-tree-card-name-one-line/research.md:62` (D1, deferred) — przy 1366 px drzewo z dziadkami startuje zakotwiczone (regres z 12 rem). **Otwarte. Ta zmiana może je zamknąć** (C3).
- `context/archive/2026-09-23-account-tree-and-first-person/plan-brief.md:30`, `context/archive/2026-09-30-app-shell-sidebar/plan-brief.md:24,45` — `fortify.home = /persons`, logo → `/tree`, „cel po zalogowaniu zostaje /persons”, bo nowe konto ma tam „Dodaj osobę”. **Powód nieaktualny:** `/tree` ma stan pusty „Dodaj pierwszą osobę” od `archive/2026-10-11-tree-add-person-panel/plan-brief.md:16`.
- `context/archive/2026-09-28-graphical-tree-view/plan-brief.md:46`, `context/archive/2026-09-29-tree-sheet-variant-c/plan-brief.md:36,42` — jeden układ na każdej szerokości; pionowy układ mobilny to osobna zmiana (odroczona, niezbudowana). `context/foundation/prd.md:212` — NFR obejmuje 1366–2560 px.
- `context/archive/2026-09-30-app-shell-sidebar/plan-brief.md:22,29` — brak górnego paska („oddaje 64 px wysokości… przede wszystkim drzewu”), ostatni okruszek = h1.

## Related Research

- `context/archive/2026-10-05-tree-fits-screen/research.md`
- `context/archive/2026-10-12-tree-card-name-one-line/research.md`
- `context/archive/2026-10-11-tree-add-person-panel/research.md`

## Decisions (użytkownik, 2026-10-19)

| # | Pytanie | Decyzja | Skutek dla planu |
| --- | --- | --- | --- |
| U1 | Kształt karty | **Poziome, wyższe.** Pasek zdjęcia po lewej, dzisiejsze proporcje (dziadek 1 kolumna, rodzic 2, para 2 w 4, dzieci 2/1). | Kolumna zostaje ≥ 12 rem (`person-photo-on-card/plan-brief.md:32`). Wysokość karty rośnie z wysokością obszaru (C2). C4 przechodzi z „zmiany proporcji” na „wyższe karty w tych samych proporcjach”. |
| U2 | Nagłówek | **Można się go pozbyć; przyciski nie są niezbędne.** | Usunąć wiersz nagłówka `show.tsx:85-107` i jego `gap-6` (ok. 56 px). h1 zostaje jako `sr-only` (dostępność, `tests/e2e/support/navigation.ts:75-78`, `person-panel-unsaved-guard.spec.ts:58`). Funkcje nie giną: „Ustaw jako probanta” jest też w nagłówku panelu osoby (`resources/js/components/persons/person-modal-header.tsx:181`), a „Wróć do probanta” = pozycja „Drzewo” w menu (`tree.index`). Do przesądzenia w planie: czy `SetRootPersonButton` (`components/tree/set-root-person-button.tsx`) i odznaka „Probant” znikają całkiem. Żaden test E2E ich nie klika (grep `tests/e2e`). |
| U3 | Laptop 1366–1440 px | **Cała rodzina widoczna.** Telefon 390 px odroczony (start na parze, przeciąganie jak dziś). | Zamyka D1 (`card-name-one-line/research.md:62`). W połączeniu z U1 wymaga skali poniżej dzisiejszej podłogi 0,75 — patrz napięcie niżej. |
| U4 | Widok przodków | **Osobny wariant.** Przodkowie zostają przy obecnej karcie. | Plan rozdziela style: `ancestor-card.tsx` zachowuje `min-h-24` i dzisiejsze `CardBody`. Nowa wysokość dotyczy tylko kart arkusza (`sheet-person-card.tsx`, `unknown-person-card.tsx` w arkuszu). `ANCESTORS_FIT_FLOOR` i jego uzasadnienie bez zmian. |

**Napięcie U1 × U3 (do rozstrzygnięcia w planie):** przy karcie poziomej (kolumna ≥ 12 rem) arkusz ma ≥ 1636 px szerokości (`8·12 + 5,25 + 1` rem). Cała rodzina mieści się w szerokości obszaru (obliczenia ręczne, rem = 16 px, panel boczny rozwinięty, nagłówek usunięty) przy skali:
- 1366×768: obszar ≈ 1070 px → skala ≈ 0,65; imię 16 px renderuje się jako ≈ 10,5 px;
- 1440×900: obszar ≈ 1144 px → skala ≈ 0,70 (≈ 11,2 px).

Obie wartości są poniżej `TREE_FIT_FLOOR = 0.75` (`fit-view.ts:2`), którego powodem była czytelność kart przy starcie (`tree-fits-screen/plan-brief.md:23`). Przy zwiniętym panelu bocznym obszar rośnie o ok. 190 px (`ui/sidebar.tsx:232`) i 1366 px daje skalę ≈ 0,77.

Wysokość przestaje ograniczać po usunięciu nagłówka. Przy skali 0,65 i obszarze ≈ 682 px budżet treści to ≈ 1050 px, czyli H do ≈ 220 px przed skalowaniem. Plan musi więc też ograniczyć H od góry (np. proporcją karty), żeby karta pozioma nie zrobiła się kwadratowa.

**U5 (użytkownik, 2026-10-19): wybrana opcja (a).** Obniżyć podłogę skali drzewa do ok. 0,65. Karta zostaje pozioma (≥ 12 rem), a poniżej ok. 1280 px zostaje kotwica na parze jak dziś. Dotyczy tylko `TREE_FIT_FLOOR` arkusza (`fit-view.ts:2`); `ANCESTORS_FIT_FLOOR` bez zmian (U4). Zmiana przestawia `fit-view.test.ts:95-113` i jest odejściem od powodu „czytelne przy starcie” z `tree-fits-screen/plan-brief.md:23`, które plan musi odnotować.

Rozważone opcje:
- (a) obniżyć podłogę skali drzewa do ok. 0,65, z tekstem ≈ 10–11 px na 1366 px — **wybrana**;
- (b) węższy pasek / niższe minimum kolumny przy wąskim obszarze, wbrew „nie wracać do 10rem” i odrzuceniom z `card-name-one-line/research.md:176`;
- (c) połączenie (a) z kotwicą tylko poniżej np. 1280 px.

## Open Questions

Wszystkie pytania produktowe 1–5 poniżej są rozstrzygnięte przez U1–U4 (sekcja Decisions). Zostawione dla śladu:

1. **Kształt karty.** (a) Pionowa, jednakowa szerokość na wszystkich pokoleniach, zdjęcie/inicjały u góry: pozwala zejść z kolumną do ok. 7,5–10 rem, mieści dziadków przy 1366–1440 px i wypełnia wysokość. (b) Pozioma z paskiem, tylko wyższa: kolumna zostaje ≥ 12 rem, wysokość rośnie przy ≥ 1920 px, ale przy 1366–1440 px dziadkowie dalej są ucięci (D1 zostaje).
2. **Szerokość rodziców, pary i dzieci.** Zostają 2-kolumnowe (dzisiejsze proporcje), czy jednakowe z dziadkami (styl programów genealogicznych, węższy arkusz)?
3. **Nagłówek.** Kompaktowy pasek (okruszki + akcje w jednej linii, h1 mniejszy lub wizualnie ukryty, ale nadal obecny dla dostępności i E2E) czy bez zmian?
4. **Wąskie ekrany.** Przy 1366–1440 px pierwszeństwo ma cała rodzina w mniejszej skali czy czytelny rozmiar z kotwicą? Telefon 390 px: odroczyć (kotwica jak dziś) z powodem „układ mobilny to osobna zmiana”?
5. **Widok przodków.** Czy dostaje ten sam kształt karty (wspólne `CardBody` / `card-styles.ts`), czy plan rozdziela warianty?

Luki dowodowe (nie blokują planu):
- Wszystkie liczby pikseli są obliczone ręcznie (rem = 16 px, panel boczny rozwinięty). Zwinięty panel boczny i otwarty panel boczny zmieniają szerokość. Pomiar w przeglądarce należy do fazy implementacji.
- ~~`project/ds/rootkeeper/tokens.json` w artefakcie DS — nieprzeczytany.~~ **Zamknięte w kontynuacji niżej:** `photo-strip-width` 4,5 rem, `person-card-min-height` 6 rem, `gender-band-height` 0,25 rem.

## Follow-up 2026-10-19: inspiracja PLSoft „Formularz rodzinny”

Źródło: zrzut ekranu https://www.plsoft.pl/wp-content/uploads/2020/09/formularz-768x543.png (program „Drzewo genealogiczne” PLSoft, 768×543 px; obejrzany jako obraz, nie mierzony w pikselach).

### Co widać na zrzucie

- **Pasma pokoleń z widocznymi, obróconymi etykietami** po lewej: „Dziadkowie”, „Rodzice”, „Związek”, a dzieci mają osobne pasmo z belką „Dzieci (3)”.
- **Karty stopniowane: im bliżej pary, tym większe.**
  - Dziadkowie (8 kart): wąskie, **bez zdjęcia**, tylko imię i nazwisko, lata „(*1870 +1954)” i ID.
  - Rodzice (4 karty): szersze, zdjęcie po lewej, lata i ID.
  - Para: najwyższy rząd, duże zdjęcie i pełne dane (data i miejsce urodzenia, data i miejsce zgonu, „m. poch.”, ID).
  - Dzieci: karta pionowa (imię u góry, lata, zdjęcie pod spodem, ikony).
- **Wysokości rzędów są różne.** Rzędy nie mają jednego H: pion wypełnia głównie rząd pary.
- Między małżonkami jest pole „Dane związku” (zdjęcie ślubne, Id, Edytuj/Usuń). Obok każdego małżonka jest kolumna przycisków: Dodaj, Wstaw z listy, Usuń, „Rodzeństwo (6)”, „Związki (1)”.
- Łączniki to poziome paski z gradientem niebieski→brzoskwiniowy, a nad każdym dziadkiem jest strzałka „w górę”.
- Pasek narzędzi: Wstecz/Dalej, Historia, Wydruk (PDF), przełączniki (Animacja, nazwisko rodowe, ID) oraz **„Ustaw jako ekran startowy”**.

### Zestawienie z kodem

| Cecha PLSoft | Stan w Root Keeper | Dowód |
| --- | --- | --- |
| Kolor karty według płci | **Jest.** Tokeny `gender-*-surface/border` i `genderCard` | `resources/css/app.css:63-69,111-117`; `resources/js/lib/gender-styles.ts`; `sheet-person-card.tsx:135` |
| Etykiety pokoleń | **Są, ale tylko `sr-only`:** „Dziadkowie”, „Rodzice”, „Związek”, „Dzieci” | `family-sheet.tsx:158-160` (`RowLabel` = `<h3 className="sr-only">`), użycia `:605,641,671,484,773` |
| Karty stopniowane | **Brak.** Każde pokolenie używa tego samego `SheetPersonCard` → `CardBody` (pasek zdjęcia, imię ≤ 2 linie, lata, wiek). Różnią się tylko szerokość (dziadek 1 kolumna, rodzic 2, para 2 + odstęp) i obramowanie osoby w centrum. Para ma tę samą szerokość co rodzic | `family-sheet.tsx:37-40,135-141,610,646,711`; `sheet-person-card.tsx:36-68,78-123` |
| Różne wysokości rzędów | **Brak.** Jedna siatka bez `gridTemplateRows`, wiersze `auto`, karty `h-full min-h-24`. Najwyższa karta w rzędzie wyznacza jego wysokość | `family-sheet.tsx:43-48,604`; `card-styles.ts:8,16`; `sheet-person-card.tsx:139` |
| Pełne dane pary (daty, miejsca, pochówek) | **Brak w payloadzie.** `SheetPerson` ma tylko `lifespan` (same lata), `age`, `gender`, `photo_version`. Dane są w bazie: zdarzenia birth/death/burial, `events.locality_id`. `loadPeople` już ładuje `events`, ale nie `events.locality` | `app/Http/Resources/SheetPersonResource.php:34-52`; `app/Actions/Trees/BuildFamilySheet.php:357-358`; `app/Models/Person.php:120,125,130`; `app/Http/Resources/EventResource.php:43` |
| „Dane związku” między małżonkami | **Częściowo.** Pigułka `UnionNode` na linii pary pokazuje status („ślub 1890”, „bez ślubu”) i „k z N”. Menu: przełącznik związków, „Szczegóły małżeństwa” (panel), „Dodaj małżonka”. Payload pary niesie tylko `marriage_year`, bez miejsca i pełnej daty. **Zdjęcia ślubnego nie ma w modelu danych** | `family-sheet.tsx:737-749`; `union-node.tsx:38-50,109-226`; `BuildFamilySheet.php:109-117,390` |
| „Związki (1)” | **Pośrednio.** `sheet.unions.length`, użyte w „k z N” | `union-node.tsx:71` |
| „Rodzeństwo (6)” | **Brak.** Grep `sibling\|rodzeństw` w `app/` i `resources/js` daje 0 trafień. `ParentChildGraph::childrenOf` pozwoliłby to policzyć bez nowych zapytań | `BuildFamilySheet.php:79-80` |
| ID na karcie | **Brak.** Jest tylko bazowe `id`, nigdzie niewyświetlane, i nie ma osobnego numeru dla użytkownika | `SheetPersonResource.php:44` |
| Strzałka „w górę” nad dziadkiem | **Funkcjonalnie jest.** Kliknięcie dowolnej karty (poza osobą w centrum) to `Link` → `treeShow(id)`, czyli przejście pokolenie wyżej. Nie ma osobnej strzałki | `sheet-person-card.tsx:140-160` |
| Wstecz/Dalej | **Przez historię przeglądarki** (wnioskowanie z opcji wizyt, niesprawdzone w przeglądarce). Zmiana centrum i związku dodaje wpis, panele używają `replace`. Brak przycisków w aplikacji | `union-node.tsx:85-95`; `lib/side-panel-url.ts:26-31` |
| Pasmo dzieci z belką i licznikiem, „Dodaj syna / córkę / Wybierz z listy” | **Brak belki i licznika.** Jeden slot „Dodaj dziecko” obok rzędu (≤ 3 dzieci) albo pod nim, a panel dodawania to wybór lub utworzenie osoby. Licznik dzieci jest tylko w menu `UnionNode` | `family-sheet.tsx:471-524,563-568`; `add-person-config.ts:101-106`; `union-node.tsx:148-153` |
| Wydruk/PDF, przełączniki, Historia | **Brak.** Grep `window.print\|wydruk\|drukuj\|pdf\|@media print\|print:` w `resources/`, `app/` i `routes/` daje 0 trafień | — |
| „Ustaw jako ekran startowy” | **Brak ustawienia.** Strona startowa to stała `config/fortify.php:76` (C1). Grep „ekran startowy\|startow” w `app/`, `resources/js` daje 0 trafień | C1 |

### Co inspiracja zmienia w dotychczasowych wnioskach

**1. Karty stopniowane rozwiązują napięcie U1 × U3 inaczej niż U5 (wnioskowanie, obliczenia ręczne).** Minimum kolumny 12 rem ma jeden powód: ≥ 6 rem tekstu obok paska zdjęcia (`archive/2026-10-11-person-photo-on-card/plan-brief.md:32`). Gdy dziadkowie nie mają paska, jak w PLSoft, ten powód ich nie dotyczy.
- Przy kolumnie 8 rem rodzic i para mają nadal 2·8 + 0,75 = 16,75 rem, więc pasek zdjęcia mieści się z zapasem.
- Arkusz ma wtedy 8·8 + 5,25 + 1 = 70,25 rem ≈ 1124 px. Na 1366×768 (obszar ≈ 1070 px) daje to skalę ≈ 0,95, a na 1440×900 (≈ 1144 px) skalę ≈ 1,0. U5 przy tych oknach dawało 0,65 i 0,70.
- Dla czytelności liczy się iloczyn rozmiaru fontu i skali. Imię dziadka 13 px × 0,95 ≈ 12,4 px. W U5 imię 16 px × 0,65 ≈ 10,5 px dotyczyło każdej karty, także pary. Przy kartach stopniowanych podłoga `TREE_FIT_FLOOR = 0,75` (`fit-view.ts:2`) mogłaby zostać, a odejście od „czytelne przy starcie” z `tree-fits-screen/plan-brief.md:23` byłoby zbędne.
- Koszty:
  - Przy ≥ 5 dzieciach karta dziecka ma 1 kolumnę (`family-sheet.tsx:53-54,321-326`), czyli 8 rem < 12 rem. Potrzebny jest wtedy wąski wariant karty dziecka: bez paska albo pionowy ze zdjęciem u góry, jak w PLSoft.
  - Długie imię w karcie 8 rem nie zmieści się w 2 liniach przy 16 px. `tree-card-name.spec.ts:95-114` sprawdza właśnie babcię „Aleksandra Brzęczyszczykiewicz” przy 1366 px (≤ 2 linie). Mniejszy font imienia dziadka albo zmiana tego wymagania to decyzja produktowa. Według CLAUDE.md asercji nie wolno dopasowywać po cichu.
  - Wartość „8 rem” jest przykładem. Faktyczne minimum zależy od fontu i paddingu karty dziadka i wymaga pomiaru w przeglądarce.
  - Inwariant `COLUMN_MAX_REM < 20 rem` (`fit-view.ts:22`) i uzasadnienie `ANCESTORS_FIT_FLOOR` (`fit-view.ts:7-13`, oparte na 12 rem) wymagają przeglądu, jeśli minimum kolumny zmieni się globalnie. Przodkowie zostają przy 12 rem (U4), więc minimum musiałoby być parametrem arkusza.
- Kierunek jest sprzeczny z U1 („pasek zdjęcia po lewej, dzisiejsze proporcje” dla każdej karty) i z U5. **Wymaga decyzji użytkownika (P1).**

**2. Wypełnianie pionu przez rząd pary, nie przez jednakowe H.** Pion zawiera dziś 4H + 158 px (sekcja 2). PLSoft rozkłada wolną wysokość nierówno.
- Siatka pozwala to zrobić bez przebudowy: `gridTemplateRows` w `gridStyle` (`family-sheet.tsx:43-48`) albo zmienne CSS wysokości rzędu na korzeniu arkusza (który ma już dopuszczony `style`, `view-tokens.test.ts:114-118`), czytane w `card-styles.ts` jako `min-h-(--…)`.
- Pułapka: `RowLabel` (`sr-only` `<h3>`) jest elementem siatki. Jawne `grid-template-rows` musi liczyć tylko rzędy rzeczywiste (wnioskowanie, niesprawdzone w przeglądarce).
- Przykład dla 1366×768 przy skali ≈ 0,95 (obszar ≈ 682 px wysokości bez nagłówka, U2): budżet ≈ 718 px − 158 px ≈ 560 px. Mógłby się rozłożyć np. dziadkowie 88, rodzice 120, para 200, dzieci 152 (proporcje do ustalenia w planie).
- Górny limit H z akapitu U5 („żeby karta pozioma nie zrobiła się kwadratowa”) dotyczy wtedy głównie pary, której wyższa karta ma co pokazać, jeśli dostanie pełne dane (P2).

**3. Pełne dane pary są tanie po stronie backendu.**
- `loadPeople` już ładuje `events` (`BuildFamilySheet.php:357-358`). Dodanie dat i miejsc do zasobu tylko dla osoby w centrum i partnera wymaga dociągnięcia `events.locality`.
- Payload pozostałych kart nie musi rosnąć.
- Format daty i miejsca istnieje w `EventResource` (`EventResource.php:43` dla nazwy miejscowości). Ponowne użycie formatera daty jest do sprawdzenia w planie.

**4. Poza zakresem tej zmiany (proponowane jako osobne zmiany, nie badane głębiej):** zdjęcie ślubne (brak w modelu danych), licznik rodzeństwa, wyświetlane ID, wydruk/PDF, przełączniki widoku, przyciski Wstecz/Dalej, „Wstaw z listy” i „Usuń” na karcie, gradientowe łączniki. To nowe funkcje, a nie wypełnianie okna. Gradient łączników dodatkowo nie ma tokenu (`LINE`/`LINEAGE`, `family-sheet.tsx:57-75`; `app.css:70-74,118-121`). „Ustaw jako ekran startowy” w tej zmianie realizuje stała zmiana `fortify.home` (C1). Ustawienie per użytkownik byłoby nowym polem.

### Nowe pytania do decyzji (P1–P4)

- **P1 — Karty dziadków:** (a) zostają z paskiem zdjęcia, kolumna ≥ 12 rem, podłoga 0,65 (U1 + U5 bez zmian); (b) karty stopniowane jak w PLSoft: dziadkowie bez zdjęcia, węższa kolumna arkusza (≈ 8 rem), skala ≈ 0,95–1 na 1366–1440 px, podłoga 0,75 zostaje. Zastępuje U5 i część U1, a przy ≥ 5 dzieciach wymaga wąskiego wariantu karty dziecka.
- **P2 — Treść karty pary:** pełne dane (data i miejsce urodzenia, zgonu, pochówku) czy jak dziś (lata + wiek)?
- **P3 — Wysokości rzędów:** stopniowane (para najwyższa) czy jedno H dla wszystkich rzędów?
- **P4 — Widoczne etykiety pokoleń i belka „Dzieci (N)”:** pokazać, kosztem ok. 1,5–2 rem szerokości na pionowe etykiety (wnioskowanie), czy zostawić `sr-only`?

### Decyzje (użytkownik, 2026-10-19, kontynuacja)

Zastrzeżenie użytkownika: PLSoft to **inspiracja dla widoku drzewa i dashboardu, nie przeniesienie 1:1**. Plan bierze z niego zasady (stopniowanie, wypełnianie pionu przez parę, pełne dane pary), a nie wygląd (gradienty, kolumny przycisków, pasek narzędzi).

| # | Decyzja | Skutek dla planu |
| --- | --- | --- |
| P1 | **Karty stopniowane.** Dziadkowie bez paska zdjęcia, węższa kolumna arkusza (≈ 8 rem, do zmierzenia) | **Zastępuje U5:** `TREE_FIT_FLOOR = 0,75` zostaje, nie obniżamy go do 0,65. **Zmienia U1** tylko dla dziadków: rodzice, para i dzieci (≤ 4) zostają poziome z paskiem i w dzisiejszych proporcjach. Minimum kolumny musi być parametrem arkusza, bo przodkowie zostają przy 12 rem (U4) i `ANCESTORS_FIT_FLOOR` się nie zmienia. Plan musi rozstrzygnąć: wąski wariant karty dziecka przy ≥ 5 dzieciach, font imienia dziadka i wymaganie `tree-card-name.spec.ts:95-114` (≤ 2 linie) jako świadomą decyzję, nie dopasowanie asercji. Przestawia `fit-view.test.ts:24-30,40-56` (`fitColumnRem`). |
| P2 | **Pełne dane pary.** Data i miejsce urodzenia, zgonu i pochówku na kartach osoby w centrum i partnera | Rozszerzenie payloadu tylko dla pary (`SheetPersonResource` albo osobny zasób), dociągnięcie `events.locality` (`BuildFamilySheet.php:357-358`). Nowe pola obejmuje test Pest w `tests/Feature/TreeTest.php`. |
| P3 | **Stopniowane wysokości rzędów.** Para najwyższa, rodzice średni, dziadkowie najniżsi | Wysokości rzędów to zmienne CSS na korzeniu arkusza, czytane w `card-styles.ts` (`min-h-(--…)`). Wartości liczy `settleColumn` z `wrapper.clientHeight` (sekcja 2). Górny limit H dotyczy głównie pary. Uwaga na `RowLabel` w siatce przy jawnym `grid-template-rows`. |
| P4 | **Tylko belka „Dzieci (N)”**, bez widocznych pionowych etykiet | `RowLabel` „Dziadkowie”, „Rodzice”, „Związek” zostają `sr-only`. „Dzieci” staje się widoczną belką z licznikiem, a jej wysokość wchodzi do stałej części pionu (dziś 158 px). Licznik = liczba dzieci wybranego związku w `sheet`. |

Akapit „Napięcie U1 × U3” i decyzja U5 wyżej zostają dla śladu. **U5 jest nieaktualne (zastąpione przez P1).**
