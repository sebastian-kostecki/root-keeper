<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Zakładki wydarzeń i notatki w panelu osoby (S-14)

- **Plan**: context/changes/person-events-and-note-tabs/plan.md
- **Scope**: Full plan (phases 1–4 of 4)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-10
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Success criteria (run during review)

- Pest (PersonNote, PersonTimeline, OwnershipBoundaries, WriteTargets, Persons, Marriages, EventSources): 300 passed
- `composer types:check` (Larastan): 0 errors; Pint: passed
- `npm run check`: clean; `types:check`: clean; `test:js`: 260 passed; `build`: OK
- `route:list --name=persons.note`: `PUT persons/{person}/note`; migration `add_note_to_people_table` ran
- Playwright (person-events-and-note-tabs, person-side-panel, person-panel-unsaved-guard): 5 passed

## Findings

### F1 — Notatka bez zawijania długich ciągów

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/note-tab.tsx:42
- **Detail**: `<p className="text-sm whitespace-pre-wrap">` nie ma reguły łamania słów. Długi ciąg bez spacji (URL, wklejone 5000 znaków) przepełnia panel poziomo. Nigdzie w `resources/js` nie ma `break-words`.
- **Fix**: Dodaj `break-words` do `<p>` widoku notatki.
- **Decision**: FIXED (break-words)

### F2 — PersonTimeline zakłada preload relacji, a docblock mówi „bez zapytań”

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/PersonTimeline.php:12-13
- **Detail**: Brak zapytań wynika wyłącznie z eager-loadu w `BuildPersonPreview`; projekt nie włącza `preventLazyLoading`, więc inny wywołujący dostałby ciche N+1. Jedyny wywołujący jest dziś poprawny.
- **Fix**: Dopisz do docblocku wymóg preloadu (`events.locality`, `children.events.locality`, `marriages.participants.events.locality`).
- **Decision**: FIXED (docblock wymaga preloadu)

### F3 — Brak klucza `note` w PUT czyści notatkę

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/PersonNoteUpdateRequest.php:28
- **Detail**: `['nullable','string','max:5000']` przepuszcza brak klucza; `validated('note')` = null i notatka jest kasowana. UI zawsze wysyła klucz, więc dotyczy tylko ręcznego żądania.
- **Fix**: Dodaj regułę `present` przed `nullable`.
- **Decision**: FIXED (reguła `present` + test)

### F4 — Klucz sortowania przy miesiącu/dniu bez roku

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/PersonTimeline.php:120-131
- **Detail**: Wiersz z `year === null`, ale z miesiącem/dniem, dostaje `[1, 0, month, day, …]` i sortuje się wśród bezdatowych po miesiącu zamiast po randze typu. Nie sprawdzono, czy walidacja w ogóle dopuszcza miesiąc bez roku.
- **Fix**: Gdy rok jest null, użyj 0 dla miesiąca i dnia w `sortKey`.
- **Decision**: FIXED (miesiąc/dzień = 0 gdy brak roku)

### F5 — Drobiazgi UI zakładek

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/events-tab.tsx:20-32,46; person-modal.tsx:62
- **Detail**: (a) pochowanie bez miejsca renderuje pusty `<p>`; (b) wrapper `overflow-x-auto` przycina pierścień fokusu triggerów; (c) `hasNote = Boolean(person.note)` pokazuje kropkę także dla notatki z samych białych znaków; (d) `key` w events-tab zawiera zbędny prefiks typu.
- **Fix**: Pomiń pusty `<p>`, dodaj padding wrappera, użyj `person.note?.trim()` dla kropki.
- **Decision**: FIXED (a–c: brak pustego <p>, padding wrappera, trim notatki; d pominięte)
