<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Akt opisany parafią, rokiem, typem i archiwum (S-03)

- **Plan**: context/changes/act-described-by-parish-and-archive/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-10-05
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success criteria run: Pest 230/230 (Parishes, Localities, Ownership, AppShell, Backup, SourceArchiveMigration, Sources, SourceMigration), Larastan 0 errors, Pint pass, Vitest 174/174, tsc pass, `npm run check` pass, build pass, `grep provenance resources/js` empty. Playwright not re-run in this review (requires running app).

## Findings

### F1 — `down()` migracji źródeł jest stratne, a komentarz twierdzi, że nie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_10_05_*_add_parish_and_archive_to_sources_table.php (down() + docblock)
- **Detail**: Docblock mówi „Lossless: provenance is never touched”. Po migracji `archive` jest jedyną żywą kopią (UpdateSource nie pisze już `provenance`), więc rollback po edycjach kasuje je i przywraca nieaktualne `provenance`. Plan (Migration Notes) opisuje to uczciwie, kod nie.
- **Fix**: Popraw docblock: rollback traci archiwa wpisane po wdrożeniu; `provenance` sprzed wdrożenia zostaje.
- **Decision**: Fixed — docblock `down()` poprawiony (Fix now)

### F2 — Enter na już wybranym polu rekordu podmienia wybór na pierwszą opcję

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/suggest-field.tsx:131-135 (`open`, `onClick={open}`)
- **Detail**: W trybie rekordu `open()` ustawia `activeIndex = 0`. Kliknięcie pola z wybraną parafią/miejscowością i Enter wybiera pierwszą alfabetyczną opcję (także przy pustym zapytaniu), cicho zmieniając dane.
- **Fix**: Przy otwarciu podświetlaj aktualnie wybraną opcję, a gdy brak wpisanego zapytania — `-1`; auto-podświetlenie 0 dopiero po wpisaniu tekstu.
  - Strength: Usuwa cichą zmianę danych; mała zmiana w jednym miejscu.
  - Tradeoff: Enter bez wpisanego tekstu nic nie wybiera (zgodne z konwencją combobox).
  - Confidence: MED — nie uruchamiałem scenariusza w przeglądarce.
  - Blind spot: Czy test Vitest/E2E zakłada podświetlenie 0 po kliknięciu.
- **Decision**: Fixed — `open()` podświetla wybraną opcję lub nic (Fix now)

### F3 — Anulowanie modala „Nowa parafia” w trakcie żądania i tak tworzy i wybiera parafię

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/parishes/new-parish-dialog.tsx:108-109 (`save()`), :118
- **Detail**: `onOpenChange` wywołuje `onClose()` bez przerwania `useHttp`. Gdy POST już leci, odpowiedź i tak woła `onCreated` + `onClose` — użytkownik, który anulował, dostaje parafię w bazie i wybraną w formularzu aktu. To samo dla `createLocality`.
- **Fix**: Zablokuj „Anuluj”/Escape/zamknięcie, gdy `busy` (`onOpenChange` ignoruje zamknięcie przy `busy`).
  - Strength: Prosta, bez zależności od API anulowania `useHttp`.
  - Tradeoff: Przez ułamek sekundy nie da się zamknąć modala.
  - Confidence: HIGH — kod jawnie nie sprawdza `busy` przy zamykaniu.
  - Blind spot: Nie sprawdzałem, czy `useHttp.cancel()` byłoby lepsze.
- **Decision**: Fixed — zamknięcie modala zablokowane przy `busy`, „Anuluj” wyłączony w trakcie żądania (Fix now)

### F4 — `disabled` na polu podczas dopisywania miejscowości gubi fokus

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/parishes/new-parish-dialog.tsx (~176), resources/js/components/parishes/parish-panel.tsx (~151)
- **Detail**: Wyłączenie sfokusowanego inputu podczas żądania wrzuca fokus na `body`; użytkownik klawiatury traci miejsce.
- **Fix**: Użyj `readOnly` + `aria-busy` zamiast `disabled`, albo przywróć fokus po żądaniu.
- **Decision**: Fixed — `readOnly` + `aria-busy` zamiast `disabled` w SuggestField (Fix now)

### F5 — Listbox bez nazwy dostępnościowej

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/suggest-field.tsx (`role="listbox"`)
- **Detail**: Brak `aria-label`/`aria-labelledby` na liście i `aria-haspopup="listbox"` na polu.
- **Fix**: Dodaj `aria-haspopup="listbox"` i `aria-label` listy z etykiety pola.
- **Decision**: Fixed — `aria-haspopup`, `aria-label` listy, nowy wymagany prop `label` (Fix now)

### F6 — Notatka w roadmapie przy S-11 jest niezacommitowana

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md
- **Detail**: Faza 5 wymaga notatki „dopisać sprzątanie parafii i miejscowości w source-panel-parish.spec.ts” przy S-11. Istnieje w working tree, ale plik jest `M` i nie wszedł do żadnego commita fazy 5.
- **Fix**: Zacommituj roadmap.md (razem z innymi zmianami roadmapy, jeśli to zamierzone).
- **Decision**: Fixed differently — bez commita; roadmap.md zostaje w working tree do wspólnego commita z poprawkami F1–F5 (robi użytkownik)

## Uwagi bez ustalenia

- Brak odchyleń od planu (Agent 1: wszystkie pozycje MATCH). Drobne EXTRA: `formatSourceSummary` także dopisuje parafię; stany fabryki `withReligion`/`seatedIn` użyte w testach.
- `sources_count` w `ParishResource` nigdzie nie jest ładowane (zgodnie z planem „tylko gdy załadowany”).
- Dodatkowy `count()` w `navCounts` na każde żądanie — spójne z miejscowościami.
