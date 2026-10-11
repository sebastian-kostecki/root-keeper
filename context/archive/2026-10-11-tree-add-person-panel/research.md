---
date: 2026-10-11T16:36:31+02:00
researcher: Claude (Opus 5.5) z Sebastianem Kosteckim
git_commit: 9a40c7aabcc3b1c89fb98ceb2751591bc6d4c1b5
branch: develop
repository: root-keeper
topic: "Przegląd strony drzewa i dodawanie osób w panelu bocznym zamiast modala"
tags: [research, ui-audit, tree, side-panel, pick-or-create-person-dialog, empty-state]
status: complete
last_updated: 2026-10-11
last_updated_by: Claude (Opus 5.5)
---

# Research: Przegląd strony drzewa i dodawanie osób w panelu bocznym

**Date**: 2026-10-11T16:36:31+02:00
**Researcher**: Claude (Opus 5.5) z Sebastianem Kosteckim
**Git Commit**: 9a40c7aabcc3b1c89fb98ceb2751591bc6d4c1b5
**Branch**: develop
**Repository**: root-keeper

## Research Question

Audyt UI (`/10x-ui`) jednego widoku — strony drzewa `resources/js/pages/tree/show.tsx` z `resources/js/components/tree/*` — w dwóch kierunkach (źródło → widoki, widok → źródło), z listą zarzutów, oraz ustalenie, co jest potrzebne, aby cztery wejścia „Dodaj ojca/matkę/rodzica”, „Dodaj dziecko”, „Dodaj małżonka” (slot i menu związku) oraz pusty stan drzewa dodawały osobę w panelu bocznym zamiast modala.

Decyzje użytkownika (2026-10-11, zapisane w `change.md`): jeden panel dodawania dla czterech wejść; `MarryCoupleDialog` i potwierdzenia usuwania zostają modalne; pusty stan dodaje pierwszą osobę z tego samego panelu i zapisana osoba zostaje probantem; po zapisie lub wyborze panel przełącza się na podgląd dodanej osoby; `/persons` „Dodaj osobę” to osobna zmiana. Po research (2026-10-11): kadrowanie drzewa (C5) naprawiamy w tej zmianie; pusty stan ma dwa warianty — drzewo bez osób oraz osoby bez probanta (Open Questions 2 i 4).

## Summary

- **Wariant kontraktu: istniejący system projektowy.** Wartości w `resources/css/app.css` (`:root`/`.dark` → `@theme`), w tym role drzewa `--tree-line`, `--tree-meta`, `--gender-*` z `context/archive/2026-09-29-tree-sheet-variant-c/tokens.md`. Komponenty w `resources/js/components/ui/` i powłoka `resources/js/components/side-panel.tsx`. Skan zakodowanych wartości na 15 plikach widoku daje 3 trafienia (sekcja „Skan”), z czego 2 to zarzut.
- **Panel dodawania da się zbudować na istniejących klockach** (`SidePanel`, `PersonCandidateList`, `PersonFormFields`, `useOptionalList`), a lokatory E2E `getByRole('dialog', { name })` przetrwają, jeśli tytuł panelu zachowa obecny tekst (`tests/e2e/support/navigation.ts:30`, `side-panel.tsx:204-209`).
- **Bloker po stronie serwera:** „po zapisie panel nowej osoby” nie działa dziś dla rodzica i dziecka — `PersonParentController.php:26`, `PersonChildController.php:31,53`, `ParentChildRelationController.php:28` zwracają `back()` i gubią id nowej osoby; `PersonSpouseController::showNewUnion` przenosi stary `?person=` z Referera. Jedynym wzorcem jest `PersonController.php:106` (`tree.show($person)?person=<id>`). Zmiana redirectów celowo zmieni asercje Pest (lista w „Testy”).
- **Precedens tworzenia w panelu istnieje:** `?source=new` (`SourceController.php:73-75`, `context/archive/2026-10-05-source-side-panel/plan-brief.md:16,26`).
- **Zrzuty (przed):** `screenshots/before/01–08`. Pokazują m.in., że modal przyciemnia całe drzewo (05), formularz zajmuje ~90% wysokości (06), a canvas po otwarciu panelu albo na 390 px gubi z kadru slot lub kartę centralną (04, 08).

## Charges

Każdy zarzut: plik:linia, wpływ na użytkownika, kategoria, proponowana faza. Fazy wg `/10x-ui`: środowisko/biblioteka → wartości tokenów → jeden widok → stany.

| # | Kategoria | Dowód | Wpływ na użytkownika | Faza |
|---|---|---|---|---|
| C1 | Przypadkowa architektura | `resources/js/components/tree/pick-or-create-person-dialog.tsx:142-146` (`Dialog` + `FormDialogContent`), wywoływany z `parent-slot-dialog.tsx:53`, `add-child-dialog.tsx:34`, `add-spouse-dialog.tsx:51` (wariant `slot` z `family-sheet.tsx` i wariant `menu` z `union-node.tsx:218-232`); zrzuty `05`, `06`, `07` | Dodając krewnego, użytkownik traci drzewo z oczu: modal przyciemnia cały widok, a formularz „Nowa osoba” zajmuje prawie cały ekran, choć podgląd tej samej osoby otwiera się obok drzewa (`08`). | jeden widok |
| C2 | Przypadkowa architektura (reguła agenta) | `CLAUDE.md:57` „…small pickers (choose a person, add a parish inline) stay modal”; źródło `context/foundation/shape-notes.md:54` bez uzasadnienia, skopiowane przez `stack-assessment.md:88,125` | Następny agent odtworzy modal przy każdym nowym wyborze osoby — reguła cofa tę zmianę. Dodatkowo `PickOrCreatePersonDialog` nie jest „small picker”: zawiera pełny `PersonFormFields`. | reguła („Utrwal to”) |
| C3 | Przypadkowa architektura (stan pusty) + brakujący komponent | `resources/js/pages/tree/show.tsx:47-71`: doraźny `Card` z tekstem „Nie wybrano probanta. Otwórz osobę i wybierz…” i linkiem do `/persons`; współdzielony `EmptyState` istnieje w `resources/js/components/empty-state.tsx:3-31` (wzorzec użycia `pages/persons/index.tsx:185-192`); zrzuty `01`, `02` | Nowy użytkownik na pustym drzewie dostaje polecenie „otwórz osobę”, której nie ma, i musi wyjść z drzewa, aby zacząć. | jeden widok |
| C4 | Przypadkowa architektura (ścieżka zapisu) | `PersonParentController.php:26`, `PersonChildController.php:31,53`, `ParentChildRelationController.php:28` → `back()`; akcje zwracają `Person`, ale wynik jest odrzucany; `PersonSpouseController.php:52-64` (`showNewUnion`) dokleja stary `person` z Referera; wzorzec docelowy `PersonController.php:106` | Bez zmiany redirectów panel po zapisie zostaje na starej osobie albo znika — użytkownik nie widzi, kogo właśnie dodał, i nie może od razu uzupełnić jego danych. | środowisko (backend) |
| C5 | Widok / układ | Canvas kotwiczy na `coupleRef` (`family-sheet.tsx:304,373`) — środek osi pary; zrzut `04` (390 px): karta osoby centralnej poza kadrem; zrzut `08` (1440 px, otwarty panel): slot „Dodaj ojca” wypada z kadru po zwężeniu drzewa | Po otwarciu panelu z slotu użytkownik nie widzi miejsca, do którego dodaje osobę; na telefonie nie widzi osoby, której drzewo ogląda. | jeden widok (w tej zmianie — decyzja użytkownika 2026-10-11) |
| C6 | Brakujące tokeny | `family-sheet.tsx:428` `h-[30px]` (pień do dzieci, „≈30px” z canvasu, `context/archive/2026-09-29-tree-sheet-variant-c/plan.md:318`); `union-node.tsx:117` `h-[34px]` (kopia `.sel` z canvasu, `…/plan.md:258`) | Niski: wartości spoza skali odstępów rozjeżdżają się przy zmianie skali (np. `h-8`/`h-9` kontrolek obok); nie wpływają dziś na czytelność. | wartości tokenów |

Luki stanów (wejście do fazy stanów, nie osobne zarzuty) — sekcja „Macierz 7 stanów”.

## Detailed Findings

### Skan zakodowanych wartości (przed-audyt i po research)

Wzorzec z `/10x-ui` uruchomiony na `pages/tree/show.tsx`, `components/tree/*.ts*`, `zoom-canvas.tsx`, `side-panel.tsx`, `persons/person-create-dialog.tsx`, `form-dialog.tsx`, `lib/gender-styles.ts`: **3 trafienia**.

- `components/tree/card-styles.ts:23` `focus-visible:ring-[3px]` — standard shadcn (ten sam w `ui/button.tsx:8`, `ui/input.tsx:12`), nie zarzut.
- `components/tree/family-sheet.tsx:428` `h-[30px]` — C6.
- `components/tree/union-node.tsx:117` `h-[34px]` — C6.

### Źródło → widoki (kto czyta tokeny i komponenty)

Liczba klas tokenów (bg/text/border/ring z rolami `tree-*`, `gender-*`, `primary`, `muted*`, `accent`, `background`, `foreground`, `card*`, `ring`, `border`) i importów z `components/ui/` na plik, policzona grepem na tym commicie:

| Plik | Klasy tokenów | Importy `ui/` |
|---|---|---|
| `pages/tree/show.tsx` | 1 | 3 |
| `tree/family-sheet.tsx` | 10 | 0 |
| `tree/sheet-person-card.tsx` | 10 | 1 |
| `tree/card-styles.ts` | 8 | 0 |
| `tree/union-node.tsx` | 6 | 1 |
| `tree/pick-or-create-person-dialog.tsx` | 2 | 2 |
| `tree/marry-couple-dialog.tsx` | 0 | 2 |
| `tree/set-root-person-button.tsx` | 0 | 1 |
| `tree/parent-slot-dialog.tsx`, `add-child-dialog.tsx`, `add-spouse-dialog.tsx`, `unknown-person-card.tsx` | 0 | 0 (składają `PickOrCreatePersonDialog` / `card-styles`) |

Wniosek: tokeny drzewa są żywe (czytane przez `card-styles.ts`, `lib/gender-styles.ts`, `sheet-person-card.tsx`, `family-sheet.tsx`); nie ma martwego pliku tokenów ani klas palety w widoku. Kontrakt nie wymaga fazy wyboru wartości — faza tokenów sprowadza się do C6.

### Mechanika paneli (jak dodać panel dodawania)

- Rejestr paneli: `resources/js/lib/side-panel-url.ts:6-13` (`PANEL_PROPS`: person, marriage, demo, locality, parish, source). `withPanel()` usuwa klucze pozostałych paneli przy otwieraniu (`:44-48`) — po stronie klienta jeden panel na URL.
- Nawigacja: `resources/js/hooks/use-side-panel-navigation.ts` — `open` = `router.visit(withPanel(...), panelVisitOptions)`, `close` = `router.replace` bez żądania do serwera.
- Rozwiązywanie propsów: `person`/`marriage` w `app/Http/Middleware/HandleInertiaRequests.php:60-69` (współdzielone, leniwe), pozostałe w kontrolerach stron (`SourceController.php:70-90`, `LocalityController.php:32-50`, `ParishController.php:37-53`, `SidePanelDemoController.php:22-36`).
- Montaż: `PersonPanel`/`MarriagePanel` w `resources/js/layouts/app-layout.tsx:28-41`; panele source/locality/parish/demo renderowane w swoich stronach i portalowane do tego samego slotu (`side-panel.tsx:93-97`, `layouts/app/app-sidebar-layout.tsx:39`).
- Dwa warianty panelu dodawania (do wyboru w planie):
  - **Klucz URL** (np. `?add=…`): wpis w `PANEL_PROPS` (test `side-panel-url.test.ts:65-74` przejdzie automatycznie), resolver w `TreeController@index/show`, kontekst slotu (rola, osoba, para rodziców) w query i walidowany na serwerze. Zalety: zamyka panel osoby przez `withPanel`, link, przeżywa reload. Wzorzec: `?source=new`, `?locality=new`.
  - **Stan klienta** (bez URL): `<SidePanel>` renderowany z `FamilySheet` z lokalnego stanu. `withPanel` go nie zamknie — przy otwartym `?person=` oba panele wyrenderują się obok siebie (slot jest wierszem flex), więc kod musi sam zamknąć panel osoby (`usePersonPreview().close` przez `requestExit`).
- `personCandidates` (`TreeController.php:28,46`, `Inertia::optional`) działa z panelu portalowanego, bo portal zachowuje kontekst Inertii; `useOptionalList` (`hooks/use-relation-candidates.ts:15-41`) robi `router.reload({ only })`. Jeśli panel trafi do layoutu, musi czytać prop z `usePage()` (istnieje tylko na `tree/show`); `panelVisitOptions.only` zawiera tylko propsy paneli, więc `load()` nadal jest potrzebny.

### Strażnik niezapisanych zmian

- Panele osoby i małżeństwa używają globalnego `SectionEditorProvider` (`app-layout.tsx:30`, `components/persons/section-editor.tsx:89-278`): `requestExit`, `UnsavedChangesBar` (Porzuć / Zapisz) i `router.on('before')`, który blokuje GET opuszczające rekord przy brudnym formularzu, a przepuszcza POST.
- Precedens trybu tworzenia: `persons/person-create-dialog.tsx:40-46` montuje własny `SectionEditorProvider record={{ key: 'person', id: 0 }}`, śledzi brudność przez serializację + `MutationObserver` (`:171-200`) i rejestruje submit (`:203-221`).
- `PickOrCreatePersonDialog` nie ma dziś żadnego strażnika (Esc/X zamykają bez pytania — decyzja `context/archive/2026-09-28-person-form-in-modal/plan-brief.md:25`). W panelu niemodalnym drzewo obok jest klikalne, co w S-06 otworzyło ścieżki cichej utraty danych (`context/archive/2026-10-05-person-side-panel/reviews/impl-review.md:42,56`) — formularz w panelu dodawania potrzebuje strażnika.

### Ścieżka zapisu (backend)

| Endpoint | Akcja | Redirect dziś |
|---|---|---|
| `PersonParentController@store` | `CreateParentForPerson` (zwraca Person, odrzucany) | `back()` (`:26`) |
| `PersonChildController@attach` / `@store` | `AttachChildToParents` / `CreateChildForParents` | `back()` (`:31`, `:53`) |
| `ParentChildRelationController@store` (wybór istniejącego rodzica) | `CreateParentChildRelation` | `back()` (`:28`) |
| `PersonSpouseController@attach` / `@store` | `CreateMarriage` / `CreateSpouseForPerson` | `showNewUnion()` → `tree.show($person, spouse=…)` + stary `person` z Referera (`:52-64`) |
| `PersonController@store` | `CreatePerson` | `tree.show($person)?person=$person->id` (`:106`) |
| `TreeRootPersonController@update` | `SetTreeRootPerson` | `back()` |

- `root_person_id` zapisuje tylko `app/Actions/Trees/SetTreeRootPerson.php:14-18`; `CreatePerson` (`app/Actions/Persons/CreatePerson.php:44-69`) go nie ustawia. „Pierwsza osoba zostaje probantem” wymaga nowej logiki (w akcji albo w kontrolerze pustego drzewa).
- Wybór istniejącej osoby: id kandydata zna klient, więc panel po wyborze może otworzyć `?person=<candidateId>` także bez zmiany serwera — ale tylko jeśli redirect `back()` nie nadpisze URL-a; spójniej jest zmienić redirecty.

### Macierz 7 stanów obecnego przepływu dodawania

| Stan | Jest dziś | Luka |
|---|---|---|
| default | slot: `unknown-person-card.tsx:22-46` + `card-styles.ts` (`cardSurface`, `placeholderAction`); nagłówek `form-dialog.tsx:31-62` | — |
| hover | slot `hover:bg-accent hover:border-solid` (`card-styles.ts:81`); wiersz kandydata `hover:bg-accent` (`person-candidate-list.tsx:116`) | — |
| focus-visible | slot `cardInteractive` (`card-styles.ts:22-23`); przyciski i pola shadcn | **wiersz kandydata bez `focus-visible`** (`person-candidate-list.tsx:116`) — tylko domyślny outline przeglądarki |
| disabled | wiersze wyłączone podczas `processingId` (`person-candidate-list.tsx:112-114`); `Zapisz disabled={processing}` (`pick-or-create-person-dialog.tsx:236`); zablokowana płeć (`person-section-fields.tsx:113-116`) | brak spinnera/etykiety „zapisywanie” (`ui/spinner.tsx` istnieje, nieużyty tu) |
| error | `InputError` pod listą (`pick-or-create-person-dialog.tsx:129-136,174`), per pole (`person-section-fields.tsx:66,80,91,…`), przewinięcie do pierwszego błędu przez `bodyRef` (`:110-116`) | w panelu kontener przewijania to wewnętrzny div `side-panel.tsx:248` bez refa — przewijanie musi szukać od `<section>` |
| empty | `Brak pasujących osób w drzewie.` + podpowiedź o ukrytych (`pick-or-create-person-dialog.tsx:160-171`); zrzut `05` | — |
| loading | 4× `Skeleton h-10` (`person-candidate-list.tsx:52-60`); błąd wczytania `Nie udało się wczytać osób. Zamknij okno i spróbuj ponownie.` (`:62-68`) | kopia „Zamknij okno” nie pasuje do panelu; brak przycisku ponowienia |

Fokus: modal przy pustej liście ustawia fokus na „Zamknij” (zrzut `05`), nie na wyszukiwarce. `SidePanel` fokusuje nagłówek przy montowaniu i zmianie `contentKey` (`side-panel.tsx:178-180`) i oddaje fokus elementowi sprzed otwarcia, jeśli ten nadal istnieje (`:149-175`). Po udanym dodaniu slot znika, więc powrót fokusu nie zadziała — fokus przejmie nagłówek panelu nowej osoby (`contentKey` = id). Wariant `menu` (union-node) dziś przywraca fokus przez `onCloseAutoFocus` (`union-node.tsx:221-224`); `SidePanel` nie ma tego hooka.

### Testy, które dotknie zmiana

- **E2E (przetrwają przy zachowanym tytule):** `tests/e2e/support/navigation.ts:14-23` (`treeSlot`, aria-label slotu), `:26-32` (`slotDialog` = `getByRole('dialog', { name: 'Dodaj … — …' })`), `:57-63` (`Dodaj małżonka` w menu). Specy `tree-slot-parent.spec.ts:38-57`, `tree-slot-child.spec.ts:57-79`, `tree-slot-spouse.spec.ts:160-176` używają `Szukaj osoby`, `Nowa osoba`, `Imię`, `Nazwisko` (exact), `Kobieta`, `Zapisz` i `toBeHidden()` na dialogu. Uwaga: jeśli po zapisie ta sama instancja panelu zmieni tytuł na nazwisko dodanej osoby, `toBeHidden()` na lokatorze z nazwą nadal przejdzie. W kroku 2 parent-spec klika slot `matkę`, gdy obok jest otwarty panel osoby — przy 1366×768 (`playwright.config.ts:34`) slot musi pozostać klikalny (patrz C5).
- **E2E support API:** `tests/e2e/support/api.ts:144-156` (`createPerson`) oczekuje redirectu `/tree/\d+` z `?person=`; `:159-172` (`createSpouse`) oczekuje `?spouse=` (dodatkowe `&person=` nie szkodzi); `createParent`/`createChild` (`:178-234`) oczekują tylko 302.
- **Pest — asercje redirectów, które zmienią się celowo, jeśli zmienią się redirecty:** `tests/Feature/ParentChildRelationsTest.php:350,376,470,658` (`assertRedirect($treeUrl)`); `tests/Feature/MarriagesTest.php:308,328,340,351,363,425,447` (`tree.show[person, spouse]`, `:328`/`:447` z zachowanym `person`). `PersonsTest.php:444,702` (`persons.store`) bez zmian. `TreeTest.php:112-120` (`sheet` null) — dojdzie test ustawienia probanta dla pierwszej osoby.
- **Vitest:** tylko `resources/js/lib/side-panel-url.test.ts` (`:65-75` przejdzie po dodaniu klucza; `:4-62` zakłada, że otwarcie panelu usuwa pozostałe klucze).
- `PersonCandidateList` jest współdzielony z `persons/pick-person-dialog.tsx:9` (pochowani w źródle; `gravestone-cemetery-and-buried.spec.ts:61-65`) — zmiany listy (np. focus-visible) dotkną też tego modala.

### Bramka wizualna

- Brak `toHaveScreenshot` w `tests/e2e`; `context/foundation/test-plan.md` §7 wyklucza wygląd z testów, a `context/archive/2026-10-05-tree-fits-screen/plan.md:46` ustalił jednorazowy skrypt zrzutów w folderze zmiany.
- Kitchen sink: `resources/js/pages/dev/side-panel.tsx` (`?demo=`, tylko `APP_ENV=local`, `SidePanelDemoController.php`) — bez treści dodawania osoby. Nie sprawdzono, czy serwer E2E ma `APP_ENV=local`.
- Zrzuty „przed” w `screenshots/before/` zrobione `playwright-cli` na serwerze E2E (`scripts/e2e-server.sh`, port 8100), 1440×900 i 390×844:
  - `01`/`02` pusty stan, `03`/`04` arkusz osoby 987 (bez krewnych), `05` modal — lista, `06`/`07` modal — formularz, `08` panel osoby obok drzewa.

## Code References

- `resources/js/pages/tree/show.tsx:47-71` — pusty stan (Card + link do /persons)
- `resources/js/components/tree/pick-or-create-person-dialog.tsx:63-249` — modal wybierz-lub-utwórz (lista ↔ formularz, `useOptionalList`, brak strażnika)
- `resources/js/components/tree/parent-slot-dialog.tsx:53-104`, `add-child-dialog.tsx:34-89`, `add-spouse-dialog.tsx:51-101` — trzy konfiguracje (tytuł, filtr, `pick`, `form`)
- `resources/js/components/tree/union-node.tsx:200-232` — „Dodaj małżonka” z menu + `onCloseAutoFocus`
- `resources/js/components/side-panel.tsx:117-254` — powłoka panelu (portal, fokus, Escape, inert na mobile, warianty szerokości)
- `resources/js/lib/side-panel-url.ts:6-54` — rejestr kluczy i `withPanel`
- `resources/js/components/persons/person-create-dialog.tsx:40-221` — wzorzec trybu tworzenia ze strażnikiem
- `resources/js/components/empty-state.tsx:3-31`, `resources/js/components/persons/persons-empty-state.tsx:7-16` — współdzielony stan pusty
- `app/Http/Controllers/PersonController.php:100-107` — redirect z `?person=<nowy>`
- `app/Http/Controllers/SourceController.php:70-90` — `?source=new` (tworzenie w panelu)
- `app/Actions/Trees/SetTreeRootPerson.php:14-18` — jedyne miejsce ustawiania probanta
- `tests/e2e/support/navigation.ts:14-63` — lokatory slotów i dialogu

## Architecture Insights

- Panele są sterowane URL-em i rozwiązywane leniwie na serwerze; „tworzenie w panelu” ma już wzorzec `?<key>=new` (source, locality). Panel dodawania osoby pasuje do tego wzorca, ale potrzebuje kontekstu slotu (rola + osoba / para rodziców / osoba centralna), którego `new` nie niesie.
- Po zapisie panel przełącza się wyłącznie przez redirect z kluczem panelu (`PersonController.php:106` + `HandleInertiaRequests.php:60-64`) — po stronie klienta nie ma logiki „otwórz panel po sukcesie”.
- Jeden panel naraz to decyzja (`context/archive/2026-10-04-side-panel-shell/plan-brief.md:143`), egzekwowana tylko przez `withPanel`; panel ze stanu klienta ją obchodzi.
- Na ekranach < `md` panel jest pełnoekranowy (`side-panel.tsx:211`), więc na telefonie różnica modal → panel jest mała (porównaj `07`); zysk jest na desktopie.

## Historical Context (from prior changes)

- `context/archive/2026-09-28-tree-parent-slot-existing-or-new/plan-brief.md:24`, `plan.md:55` — dialog wybierz-lub-utwórz powstał, zanim istniał panel boczny; pełnoekranowy `FormDialogContent` wybrano ze względu na długi formularz na telefonie. **Nadal prawda** (`pick-or-create-person-dialog.tsx:146`). Porównania dialog vs panel nie było.
- `context/archive/2026-09-28-person-form-in-modal/plan-brief.md:24-25,42` — „bez zagnieżdżonych dialogów”, Esc/X zamykają bez pytania. **Nadal prawda** dla modala; dla panelu niewystarczające (patrz strażnik).
- `context/foundation/shape-notes.md:54,108` — „pickers stay modal” to zakres użytkownika, bez uzasadnienia; seed brzmiał „ogólnie może zamiast modali wysuwany panel boczny”. Decyzja z 2026-10-11 ją **zastępuje** dla dodawania osób.
- `context/archive/2026-10-05-person-side-panel/plan-brief.md:25,43` — „Dodaj osobę” na /persons zostało modalem jako poza zakresem S-06. **Nadal prawda**; ta zmiana też to wyklucza.
- `context/archive/2026-09-28-graphical-tree-view/plan-brief.md:39-40` — wybór probanta jest celowo jawny. **Częściowo zastąpione:** decyzja z 2026-10-11 ustawia probanta automatycznie tylko dla pierwszej osoby dodanej z pustego drzewa. Ponowny projekt pustego stanu był odroczony dwa razy (`context/archive/2026-09-29-tree-sheet-variant-c/plan-brief.md:42`, `audit.md:110`).
- `context/archive/2026-09-23-account-tree-and-first-person/plan-brief.md:30` — po logowaniu użytkownik ląduje na `/persons`, nie na pustym drzewie. **Nadal prawda**; pusty stan drzewa osiąga się z nawigacji.
- `context/foundation/roadmap.md:12,24,355-357` — M-2 zamknięty, brak M-3, brak slice'u dla tej zmiany. FR-026 (`context/foundation/prd.md:178`) wymaga dodawania rodzica/dziecka/małżonka ze slotów, bez wskazania modal/panel.
- `context/foundation/roadmap.md:101`, `test-plan.md:198-209` — F-01 wymaga, aby strażnicze specy slotów przetrwały przejście modal → panel.

## Related Research

- `context/archive/2026-10-04-side-panel-shell/research.md` — powłoka panelu, Escape przy zagnieżdżonych modalach (`:95`), z-index panel 40 / dialog 50.
- `context/archive/2026-10-05-person-side-panel/research.md` — przeniesienie podglądu osoby do panelu, decyzja o /persons (`:155-156`).
- `context/archive/2026-10-05-tree-fits-screen/research.md` — dopasowanie canvasu (dotyczy C5).

## Open Questions

1. **Panel dodawania: klucz URL czy stan klienta?** URL (np. `?add=father:987`) daje zamknięcie panelu osoby, link i reload kosztem resolvera i walidacji kontekstu na serwerze; stan klienta jest tańszy, ale omija `withPanel` i strażnika `router.on('before')`. Decyzja techniczna dla `/10x-plan`.
2. ~~C5 w tej zmianie czy odroczony?~~ **Rozstrzygnięte (użytkownik, 2026-10-11): w tej zmianie.** Po otwarciu panelu dodawania kadr przesuwa się tak, by kliknięty slot był widoczny; na wąskim ekranie kotwica obejmuje kartę osoby centralnej.
3. **Wybór istniejącej osoby a „panel nowej osoby”:** przy podpięciu istniejącego kandydata panel ma się przełączyć na jego podgląd (decyzja użytkownika obejmuje „lub wybraniu istniejącej”) — wymaga to zmiany redirectów `attach`/`ParentChildRelationController@store` albo nawigacji po sukcesie po stronie klienta.
4. ~~Probant dla pierwszej osoby~~ **Rozstrzygnięte (użytkownik, 2026-10-11): dwa warianty pustego stanu.** (a) drzewo bez osób → „Dodaj pierwszą osobę” otwiera panel, zapisana osoba zostaje probantem; (b) osoby są, brak probanta → panel z wyborem probanta z listy osób (jak wybór kandydata) i „Dodaj nową osobę”; wybrana lub dodana osoba zostaje probantem. Dziś oba przypadki dają ten sam `sheet: null` (`TreeController.php:20-31`), więc strona potrzebuje informacji, czy drzewo ma osoby.
5. Nie sprawdzono, czy serwer E2E działa z `APP_ENV=local` (potrzebne, jeśli kitchen sink ma żyć na `/dev/side-panel`).
