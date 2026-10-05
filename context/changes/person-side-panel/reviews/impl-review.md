<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Panel osoby obok drzewa (S-06)

- **Plan**: context/changes/person-side-panel/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-05
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated checks run on 2026-10-05 (HEAD a74b640): `npm run check` passes, `types:check` passes, Vitest passes 194/194, the grep for leftovers is empty, full Pest passes 588/588, full Playwright passes 10/10.

## Findings

### F1 — Przekierowanie po dodaniu małżonka czyta `url()->previous()` zamiast samego Referera

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/PersonSpouseController.php:53
- **Detail**: Gdy brakuje nagłówka Referer, `url()->previous()` bierze adres zapisany w sesji. `SourceController::listQuery` (`SourceController.php:102-118`) celowo czyta tylko Referer i opisuje ten powód w docblocku. Zapis bez Referera albo z nieaktualnym adresem z sesji (np. z innej karty) może więc otworzyć panel innej osoby. Jeśli ta osoba została w międzyczasie usunięta, po udanym zapisie autor trafia na 404. Plan przewidywał test „bez Referera adres jest jak dziś”, ale osobnego testu nie ma; ten przypadek sprawdzają tylko pośrednio stare asercje bez `from()`.
- **Fix**: Czytać `$request->headers->get('referer')`, tak jak `listQuery`, i dodać test w `MarriagesTest`: bez Referera, ale z poprzednim adresem `?person=` zapisanym w sesji, adres przekierowania nie zawiera `person`.
- **Decision**: FIXED (Fix now: tylko Referer + test `attach ignores a person kept only in the session previous url`, czerwony na starym kodzie)

### F2 — Wyścig „Zapisz” na pasku z kliknięciem innej karty

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/section-editor.tsx:106-115, 151-174
- **Detail**: `saveAndExit` zapamiętuje `pending.action` przed asynchronicznym `submit()`. W tym czasie drzewo obok panelu jest klikalne, więc kliknięcie innej karty wywołuje `requestExit`, który nadpisuje `pending`. Gdy zapis się kończy, wykonuje się stara akcja, a `setPending(null)` porzuca nowe żądanie. Przy modalu kart nie dało się kliknąć, więc ta ścieżka powstała dopiero przez tę zmianę.
- **Fix**: Gdy `isSaving`, `requestExit` i `start` ignorują nowe żądania (pasek ma wtedy i tak zablokowane przyciski).
  - Strength: To zmiana w jednym miejscu, a zachowanie da się łatwo przewidzieć: zapis kończy rozpoczętą akcję.
  - Tradeoff: Kliknięcie w trakcie zapisu jest gubione bez komunikatu (zapis trwa zwykle ułamek sekundy).
  - Confidence: HIGH. Cały przepływ siedzi w jednym providerze.
  - Blind spot: Nie sprawdziłem, czy `useGuardedClick` w zakładce Źródła korzysta z tej samej ścieżki co `requestExit`.
- **Decision**: FIXED (Fix now: `requestExit` i `start` wracają bez akcji, gdy `isSaving`)

### F3 — Strażnik brudnej sekcji nie obejmuje nawigacji strony obok panelu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/nav-main.tsx:38, resources/js/pages/sources/index.tsx:127
- **Detail**: Te ścieżki zdejmują `?person=` i po cichu porzucają brudną sekcję: menu boczne, okruszki oraz otwarcie źródła, miejscowości lub parafii z listy strony. `withPanel` usuwa przy tym klucz `person`. Plan świadomie wyłączył to w „What We're NOT Doing” (strażnik tylko na akcjach panelu, jak w panelu źródła). Implementacja jest więc zgodna z planem, ale panel nie jest modalny, więc do tych ścieżek dochodzi się łatwiej niż przy dawnym modalu.
- **Fix**: Zapisać follow-up z globalnym strażnikiem: nasłuch `router.on('before')` w `SectionEditorProvider`, który przy `isDirty` anuluje wizytę i przekazuje ją do `requestExit`. Dla spójności objąłby też formularz źródła.
  - Strength: Domyka całą klasę utraty danych naraz, zamiast łatać pojedyncze linki.
  - Tradeoff: Globalny nasłuch wizyt musi przepuszczać częściowe wizyty samego panelu i zapisy sekcji. To nowa i nietrywialna logika.
  - Confidence: MED. Wzorzec zdarzenia `before` w Inertia v3 wymaga sprawdzenia w dokumentacji.
  - Blind spot: Nie sprawdziłem interakcji z `useUnsavedChangesGuard` źródła.
- **Decision**: FIXED (follow-up w `follow-ups/review-fixes.md` — globalny strażnik `router.on('before')`, osobna zmiana)

### F4 — Nieaktualne „modal” w tekście UI i komentarzach

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/person-create-dialog.tsx:114, resources/js/components/persons/section-card.tsx:45-46, tests/e2e/support/navigation.ts:2-3
- **Detail**: Tekst UI „Po zapisaniu modal przejdzie w podgląd tej osoby.” jest nieaktualny, bo po zapisie otwiera się panel. Komentarz w `section-card.tsx` mówi o „modal body”. Nagłówek `navigation.ts` wymienia ikonę „Podgląd” jako celowo nieużywaną, a tej ikony już nie ma.
- **Fix**: Zmienić tekst na „Po zapisaniu otworzy się panel tej osoby.” i poprawić oba komentarze.
- **Decision**: FIXED (Fix now: tekst UI + komentarze `section-card.tsx` i `navigation.ts`)

### F5 — `openPersonFromPanel` powiela `usePersonPreview().open`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/sources/index.tsx:155-173
- **Detail**: Funkcja ręcznie składa adres z `window.location` i wywołuje `withPanel` oraz `router.visit`. Dokładnie to robi `useSidePanelNavigation('person').open`, dostępne przez `usePersonPreview()`. Ten kształt zapisano w kontrakcie planu, więc to nie odchylenie, tylko zbędne powielenie.
- **Fix**: Zastąpić ciało `guard` wywołaniem `guard(() => open(personId))` z `usePersonPreview()`.
- **Decision**: FIXED (Fix differently: `guard(() => openPerson(personId))` z `useSidePanelNavigation('person')` zamiast `usePersonPreview()`, bez zależności od providera osoby)

### F6 — Karta centrum jako link nie ma wyróżnienia dla czytnika ekranu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/tree/sheet-person-card.tsx:43-58
- **Detail**: Karta fokusu była wcześniej `div` bez akcji. Teraz jest linkiem o takiej samej nazwie dostępności jak każda inna karta, więc czytnik ekranu nie wie, że to centrum drzewa.
- **Fix**: Dodać do linku `aria-current="true"`, gdy `isFocus`.
- **Decision**: FIXED (Fix now: `aria-current="true"` na linku karty centrum)

### F7 — E2E nie pokrywa strażnika brudnej sekcji przy kliknięciu karty

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/person-side-panel.spec.ts
- **Detail**: Najbardziej ryzykowna nowa ścieżka to kliknięcie karty drzewa przy brudnej sekcji. Działa tylko dlatego, że `SectionEditorProvider` przeniesiono do layoutu. Sprawdzono ją ręcznie (1.8), ale żaden test jej nie przypina. Tak samo „Pokaż na drzewie” z `person` i `spouse` (2.6) ma tylko weryfikację ręczną.
- **Fix**: Dodać drugi test przez `/10x-e2e`: edycja Urodzenia, klik innej karty, widoczny alert „Masz niezapisane zmiany”, nazwa dialogu bez zmian, „Porzuć” podmienia panel.
- **Decision**: FIXED (follow-up w `follow-ups/review-fixes.md` — test przez `/10x-e2e`)

## Triage (2026-10-06)

- Fixed w kodzie: F1, F2, F4, F5 (inną drogą: `useSidePanelNavigation('person')`), F6
- Fixed jako follow-up (`follow-ups/review-fixes.md`): F3, F7
- Weryfikacja po poprawkach:
  - `npm run check`: przechodzi
  - `types:check`: przechodzi
  - Vitest: 194/194
  - Pest (`MarriagesTest`, `PersonsTest`, `SourcesTest`): 244/244
  - Playwright: 10/10
  - nowy test F1 był czerwony na starym kodzie kontrolera
