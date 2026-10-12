<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Akt opisany parafią, rokiem, typem i archiwum (S-03)

- **Plan**: context/changes/act-described-by-parish-and-archive/plan.md
- **Mode**: Deep
- **Date**: 2026-10-05
- **Verdict**: REVISE → SOUND (po poprawkach)
- **Findings**: 2 critical, 4 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | PASS |

## Grounding
Grounding: 24/24 paths ✓, 7/7 symbols ✓, brief↔plan ✓, Progress↔Fazy ✓

## Findings

### F1 — Escape w liście podpowiedzi zamknie panel i modal

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details (klawiatura); Faza 3 §2, Faza 4 §3
- **Detail**: `side-panel.tsx:189-196` łapie Escape natywnym listenerem na korzeniu panelu, który odpala przed delegowanym `onKeyDown` Reacta (React 19 deleguje do `#app`), więc `preventDefault()` w polu przychodzi za późno. Radix DismissableLayer łapie Escape na `document` w fazie capture i zamyka modal „Nowa parafia” przed jakimkolwiek handlerem pola.
- **Fix**: `SuggestField` obsługuje Escape w `onKeyDownCapture`; `DialogContent` w `NewParishDialog` dostaje `onEscapeKeyDown` z `preventDefault()`, gdy `event.target` ma `aria-expanded="true"`.
- **Decision**: FIXED

### F2 — „Osobny <form> w portalu” wyśle formularz źródła

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 4 §3
- **Detail**: Syntetyczny `submit` Reacta przechodzi przez portal według drzewa React. Inertia `<Form>` ma `onSubmit` jako prop Reacta (`@inertiajs/react` 3.8, `dist/index.js:1120-1123`: `preventDefault()` + `submit()`), więc submit formularza modala wysłałby źródło.
- **Fix**: Zapis tylko przyciskiem `type="button"` z `onClick`; jeśli `<form>` będzie potrzebny, jego `onSubmit` robi `preventDefault()` i `stopPropagation()`.
- **Decision**: FIXED

### F3 — „Dodaj parafię” znika przy tej samej nazwie z innym wyznaniem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Desired End State pkt 3; Faza 3 §1–2; Faza 4 §2
- **Detail**: `canOfferCreate` porównuje samą nazwę, a parafie są unikalne po nazwie i wyznaniu (decyzja autora 3). Rzymskokatolickiej „Św. Jana” nie dałoby się dodać z panelu, gdy istnieje prawosławna „Św. Jana”.
- **Fix**: `SuggestField` dostaje `offerCreate?: 'unmatched' | 'always'`; pole parafii używa `'always'`, a duplikat zatrzymuje 422 w modalu.
- **Decision**: FIXED

### F4 — Strażnik niezapisanych zmian nie zobaczy zmian w SuggestField

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §2, Faza 4 §4
- **Detail**: `isDirty` z Inertia `<Form>` liczy się tylko z natywnych `input`/`change`/`reset` na elemencie formularza (`dist/index.js:982-998`). Select „Typ aktu” działa dzięki ukrytemu natywnemu `<select>` z Radix; ukryty input ustawiany przez React i wartość wpisana programowo zdarzeń nie wysyłają.
- **Fix**: Tryb rekordu wysyła bubbling `Event('change')` z ukrytego inputu po zmianie; tryb tekstu ustawia `value` natywnym setterem i wysyła `Event('input')`.
- **Decision**: FIXED

### F5 — Sprzątanie parafii i miejscowości w E2E jest niewykonalne

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 5 §1
- **Detail**: `cleanupBySuffix` (`tests/e2e/support/api.ts:279-308`) usuwa przez trasy HTTP; tras usuwania parafii i miejscowości nie ma do S-11, a baza `e2e` jest trwała (`scripts/e2e-server.sh` robi tylko `migrate`).
- **Fix A ⭐ Recommended**: Jawny wyjątek do S-11 — źródło usuwa `cleanupBySuffix`, parafia i miejscowość z sufiksem timestamp zostają; wpis w `test-stack.md` i notatka przy S-11 w roadmapie.
  - Strength: Sufiks wyklucza kolizje; zero nowej infrastruktury.
  - Tradeoff: Spisy w bazie e2e rosną do S-11; odstępstwo od zasady czyszczenia z CLAUDE.md.
  - Confidence: HIGH — nazwy unikalne, inne specy nie czytają tych spisów.
  - Blind spot: S-11 musi pamiętać o dopisaniu sprzątania.
- **Fix B**: Teardown przez `sail exec` + tinker na bazie e2e.
  - Strength: Spełnia zasadę czyszczenia już teraz.
  - Tradeoff: Nowy wzorzec; spec zależy od kontenera i schematu bazy.
  - Confidence: MED — tinker jest w e2e-server.sh, ale nie z testu.
  - Blind spot: Nazwa kontenera i DB w CI niesprawdzone.
- **Decision**: FIXED (Fix A)

### F6 — parish_label w SourceResource: N+1 i niewymienieni konsumenci

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 2 §4, Faza 4 §1
- **Detail**: Eager loading był zaplanowany tylko dla listy w `index`. `SourceResource` buduje też `availableSources` (`HandleInertiaRequests.php:60-62`) i pojedyncze źródło w `sourcePanel`; `formatSourceMeta` czyta też `source-picker-dialog.tsx:90`; opcje parafii potrzebują `locality`.
- **Fix**: `with('parish')` we wszystkich trzech miejscach, `with('locality')` dla opcji parafii; picker wpisany jako dotknięty konsument.
- **Decision**: FIXED

### F7 — Miejscowość dopisana w modalu ginie przy ponownym otwarciu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 4 §2–3
- **Detail**: Nowa miejscowość trafiała tylko do lokalnego stanu modala; druga nowa parafia w tym samym formularzu jej nie widzi, a ponowne „Dodaj miejscowość” daje 422.
- **Fix**: Opcje miejscowości w stanie `SourceDescriptionFields`, modal zwraca nową przez `onLocalityCreated`.
- **Decision**: FIXED
