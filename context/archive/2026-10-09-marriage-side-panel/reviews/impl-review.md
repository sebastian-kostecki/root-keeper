<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Panel małżeństwa (S-12)

- **Plan**: context/changes/marriage-side-panel/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-10
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Automated verification (uruchomione w przeglądzie)

- Pest małżeństw/drzewa/granic: 142 passed
- Pest pozostałych miejsc z małżeństwami: 294 passed
- Larastan: 0 errors; Pint: passed
- `npm run types:check`: czysto; `npm run check`: 222 plików sformatowane, brak ostrzeżeń lint
- Vitest: 260 passed
- Nie uruchamiano: build i Playwright (E2E) — wynik z commitów 391214a / cdce140 (Progress `[x]`)
- Pola Manual w Progress są odhaczone i mają odbicie w diffie (spec E2E z celowymi błędami, kod strażnika, menu, link w Rodzinie).

## Findings

### F1 — Stary błąd zapisu wraca po ponownym otwarciu panelu małżeństwa

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/marriages/marriage-panel.tsx:95-96, resources/js/hooks/use-section-save.ts:23-26,52
- **Detail**: `useSectionSave` jest wywołany w `MarriagePanel` nad wczesnym `return null`, a komponent jest zamontowany w layoucie na stałe. Stan `failure` przeżywa więc zamknięcie panelu (wcześniej żył w `OverviewTab`, który odmontowuje się razem z panelem). Scenariusz: zapis daty ślubu z błędem walidacji → zamknięcie panelu → ponowne otwarcie tego samego małżeństwa → „Edytuj” w karcie „Ślub”: `errorsFor` zwraca stare błędy (`failure.id === id` i `editor.editing === id`) w świeżym formularzu. `PersonPanel` nie ma tego problemu (zawartość montuje się dopiero po otwarciu).
- **Fix**: Przenieść wywołanie `useSectionSave` do komponentu treści montowanego tylko przy otwartym panelu (po `marriagePanel === null` early return), albo wyczyścić `failure` przy zmianie `marriage.id`/zamknięciu.
- **Decision**: FIXED — Fix now: `MarriagePanel` to cienka powłoka, treść w `MarriagePanelContent` z `key={marriage.id}` (hook montuje się tylko przy otwartym panelu). Lint, tsc i E2E marriage-side-panel zielone.

### F2 — Pasek „niezapisane zmiany” w kreatorze osoby stracił stylowanie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/components/persons/section-editor.tsx:344-347, resources/js/components/persons/person-create-dialog.tsx:141
- **Detail**: Plan (Phase 2.6) mówił tylko o dodaniu `UnsavedChangesBar` do panelu małżeństwa. W implementacji pasek przeniesiono do propa `footer` w `SidePanel` (`side-panel.tsx:251` owija go w `border-t px-4 py-3`) i z samego `UnsavedChangesBar` usunięto `bg-background absolute inset-x-0 bottom-0 z-30 border-t px-6 py-4 shadow-lg`. Panel osoby i panel małżeństwa działają, ale `PersonCreateDialog` nadal renderuje `<UnsavedChangesBar />` bezpośrednio w `FormDialogContent` — bez pozycjonowania, tła, obramowania i paddingu. Zmiana nieopisana w planie, żaden spec nie sprawdza wyglądu paska w kreatorze („Dodaj osobę”).
- **Fix**: W `person-create-dialog.tsx` owinąć pasek w kontener z dawnymi klasami (`bg-background border-t px-6 py-4`) albo zwrócić stylowanie do `UnsavedChangesBar` wariantem; sprawdzić wizualnie kreator przy brudnym formularzu.
- **Decision**: FIXED — Fix now: pasek w kreatorze owinięty w `bg-background border-t px-6 py-4`, renderowany tylko przy `hasPendingExit` (statyczny footer pod formularzem zamiast dawnego `absolute`). Lint i tsc zielone; wygląd w przeglądarce NIE sprawdzony — do obejrzenia ręcznie (Dodaj osobę → brudny formularz → zamknij).

### F3 — Dwa odstępstwa od planu w UI (uzasadnione, nieudokumentowane)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/persons/family-tab.tsx:258-281, resources/js/components/marriages/marriage-panel.tsx (`PersonLink`)
- **Detail**: (1) Plan: `MarriagePanelLink` w części `extra` wiersza `MarriageRow`. Faktycznie: nowy slot `action` w `PersonRow` i przycisk-ikona `HeartHandshake` z `aria-label` „Szczegóły małżeństwa z {małżonek}” obok linku małżonka (uzasadnienie w komentarzu: zagnieżdżone linki to nieprawidłowy HTML). (2) Plan: małżonkowie i dzieci „jak `PersonRow` w Rodzinie”. Faktycznie: lokalny `PersonLink` w `marriage-panel.tsx`. Intencja zachowana w obu przypadkach.
- **Fix**: Dopisać krótki addendum do planu (źródło prawdy dla przyszłych przeglądów); ewentualnie później ujednolicić `PersonLink` z `PersonRow`.
- **Decision**: FIXED — Fix now: addendum „przegląd implementacji, 2026-10-10” dopisany do plan.md przed sekcją Progress (oba odstępstwa + zmiana paska).

### F4 — Duplikacja: mapowanie skrótu osoby i logika przekierowania po usunięciu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Relations/BuildMarriagePanel.php:71-87, app/Http/Controllers/MarriageController.php:67-85
- **Detail**: `BuildMarriagePanel::summary` to trzecia kopia mapowania `{id, display_name, gender, birth_date, death_date}` (obok `RelativeResource` i `MarriageResource.spouse`); `marriage` idzie przez `EventResource`, reszta ręcznie, więc konwencja „Resource + `->resolve()`” jest spełniona częściowo. `MarriageController::urlAfterDelete` powiela walidację hosta/ścieżki z `PersonController::urlAfterDelete`, ale jest od niej słabsza (nie dopasowuje ścieżki do tras; przyjmuje dowolną ścieżkę tego samego hosta — skutek to najwyżej redirect na własny 404, bez skutków bezpieczeństwa). Open redirect sprawdzony: wynik zawsze zaczyna się od `getSchemeAndHttpHost()`, referer czytany z nagłówka, nie z `url()->previous()` (lekcja F1 z S-06 zachowana).
- **Fix A ⭐ Recommended**: Zostawić, odnotować dług w roadmapie/follow-upie
  - Strength: Kod działa i jest przetestowany; wydzielenie dotyka dwóch kontrolerów i istniejących Resources poza zakresem S-12.
  - Tradeoff: Trzecia kopia mapowania zostaje do następnej zmiany.
  - Confidence: HIGH — brak błędu, tylko dług.
  - Blind spot: Czwarty konsument powieli wzorzec.
- **Fix B**: Wydzielić wspólny Resource skrótu osoby i helper przekierowania w `app/Support`
  - Strength: Jedno źródło prawdy dla kształtu skrótu i walidacji referera.
  - Tradeoff: Refaktoryzacja `RelativeResource`, `MarriageResource`, `PersonController` — szeroki promień zmian.
  - Confidence: MEDIUM — nie sprawdzono wszystkich konsumentów kształtu.
  - Blind spot: Wpływ na istniejące testy Resource i typy TS.
- **Decision**: ACCEPTED — Fix A: zostawione jako znany dług, bez zmian w kodzie.

### F5 — Luki w pokryciu testami

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/MarriagesTest.php, tests/e2e/marriage-side-panel.spec.ts
- **Detail**: Brak: (a) testu gałęzi `abort_unless(participants->count() === 2, 404)` w resolverze (martwa przez UI); (b) testów referera `//evil.com` i `user@host` dla `destroy` (kod bezpieczny, regresja nieprzypięta); (c) E2E usunięcia małżeństwa z nagłówka panelu — „panel znika, drzewo zostaje, bez 404” (Progress 2.10) pokrywa tylko ręczna weryfikacja i Pest przekierowania. Asercje istniejących testów są konkretne, nie tautologiczne; E2E zgodny z CLAUDE.md (bez `waitForTimeout`, `getByRole`, sufiks i czyszczenie).
- **Fix**: Dodać dwa przypadki Pest (zdarzenie z ≠2 uczestnikami → 404; referer `//evil.com` → `tree.index`) i opcjonalnie krok usunięcia w spec E2E.
- **Decision**: FIXED — Fix now: dodane dwa testy w `MarriagesTest` (małżeństwo bez dwóch małżonków → 404; referer `//evil.example.com/...` → `tree.index`). Test 404 zweryfikowany mutacyjnie (po usunięciu `abort_unless` czerwony: 500). Bez E2E usunięcia — (c) zostaje pokryte ręcznie (Progress 2.10) i Pestem przekierowania.

### F6 — `BuildMarriagePanel` ładuje więcej niż trzeba

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Relations/BuildMarriagePanel.php:30,36
- **Detail**: `$marriage->load([... 'participants.events', 'tree'])` ponownie pobiera `participants` załadowane już przez resolver (`loadMissing`), a `tree` to dodatkowe zapytanie. `ParentChildGraph::forTree` wczytuje wszystkie relacje drzewa, by wyznaczyć dzieci dwóch osób — bez N+1, ale koszt rośnie z rozmiarem drzewa (zgodnie z istniejącym użyciem grafu).
- **Fix**: Użyć `loadMissing` dla `participants`; ograniczenie zapytania o dzieci do rodziców pary zostawić na wypadek wzrostu drzew.
- **Decision**: FIXED — Fix now: `load` → `loadMissing` w `BuildMarriagePanel`; zapytanie o dzieci bez zmian. Pest (144), Larastan i Pint zielone.
