<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Design system Root Keeper na /sources i /places oraz polskie ustawienia

- **Plan**: context/changes/sources-places-polish/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical 4 warnings 4 observations

## Automated verification (run during review)

| Command | Result |
|---------|--------|
| `vendor/bin/sail npm run check` | PASS — 243 files formatted, no lint warnings |
| `vendor/bin/sail npm run types:check` | PASS |
| `vendor/bin/sail npm run test:js` | PASS — 22 files, 315 tests |
| `vendor/bin/sail artisan test --compact tests/Feature/Settings` | PASS — 12 tests, 68 assertions |
| `npx playwright test` (fact-source-scan, places-merge-and-delete, places-page, source-panel-edit, source-panel-parish, gravestone-cemetery-and-buried, sources-page-states, sources-grid-with-filters) | PASS — 12 passed |
| grep for bare „Zapisz”/„Usuń”/„Scal” labels in scope | PASS — no matches |

`npm run build` (3.4) was not re-run. `vite.config.ts` has an uncommitted change (font `subsets`) that is not part of this plan.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — „Scal z…” ucięte w nagłówku paneli miejscowości i parafii; werdykt tego nie rozwiązuje

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: resources/js/components/localities/locality-panel.tsx:57-81, resources/js/components/parishes/parish-panel.tsx:74-100, context/changes/sources-places-polish/screenshots/verdict.md:16,27
- **Detail**: Plan (Critical Implementation Details) kazał sprawdzić na zrzucie, czy dwa przyciski `outline sm` zostawiają miejsce na tytuł. `verdict.md` odnotowuje „Odstaje: ucięta etykieta »Scal z…«” dla panelu miejscowości i parafii i zostawia to jako „decyzja projektowa, nie poprawiam bez zgody”. Mimo to 3.12 („Werdykt zrzutów przejrzany i zaakceptowany”) i 2.6 są odhaczone. Przy 24 rem (min. szerokość panelu) i na telefonie tytuł `truncate` dostaje ok. 70–100 px, bez `title=`.
- **Fix A ⭐ Recommended**: Przenieść „Scal z…” do stopki obok „Zapisz …” (zostawić w nagłówku tylko „Usuń …”), albo skrócić etykiety nagłówka do „Scal”/„Usuń” z rzeczownikiem w `aria-label`
  - Strength: Rozwiązuje przyczynę (za mało miejsca), a stopka ma już podpowiedź o zapisie zmian — akcje na rekordzie leżą razem z jej wyjaśnieniem.
  - Tradeoff: Odchodzi od wzorca panelu źródła (akcje w nagłówku) i od treści planu; dwie zmiany układu w dwóch panelach.
  - Confidence: MED — propozycja pochodzi z `verdict.md`, nie była oglądana na zrzucie po zmianie.
  - Blind spot: Nie sprawdzono, czy panel źródła ma ten sam problem przy długim tytule („Usuń źródło”).
- **Fix B**: Zaakceptować i dodać `title={name}` na tytule panelu
  - Strength: Zero zmian układu; pełna nazwa dostępna po najechaniu.
  - Tradeoff: Etykieta „Scal z…” nadal ucięta, na dotyku brak tooltipa.
  - Confidence: MED
  - Blind spot: Nie sprawdzono szerokości na telefonie.
- **Decision**: FIXED via Fix A — „Scal z…” przeniesione do stopki obok „Zapisz …” w locality-panel.tsx i parish-panel.tsx (w nagłówku zostaje „Usuń …”)

### F2 — Ręczne punkty zaliczone bez obejrzenia motywu ciemnego

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/sources-places-polish/plan.md:328,331,370 (1.6, 1.9, 3.12), screenshots/verdict.md:4,19-23
- **Detail**: `verdict.md` stwierdza, że zrzuty ciemne (11–32) „NIE oglądane wizualnie”, a ocena opiera się na logach. Punkty 1.6/1.9 wymagają „w obu motywach”, a 3.12 akceptacji werdyktu — wszystkie `[x]`. Możliwe pozorne odhaczenie. Zrzuty istnieją, więc brakuje tylko przeglądu.
- **Fix**: Obejrzeć `after/11–32` (szczególnie 25–28, 30–32) i dopisać do `verdict.md` wynik; jeśli coś odstaje, odhaczenie zostaje cofnięte.
- **Decision**: FIXED — obejrzano 8 zrzutów ciemnych (11, 14, 15, 18, 19, 28, 30, 32), wynik dopisany do verdict.md; bez uwag poza F7. Nieobejrzane 12, 13, 16, 17, 20, 25–27, 31 (panele 25–27 do regeneracji po F1)

### F3 — Okna scalania/usuwania parafii przeżywają zmianę rekordu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/parishes/parish-panel.tsx:52-53,128-146
- **Detail**: `mergeOpen`/`deleteOpen` i okna były w `ParishForm` z `key={parish.id}` (remount przy zmianie rekordu). Plan przeniósł je do `ParishPanel`, który nie jest keyowany, więc otwarte okno (np. po Back/Forward w przeglądarce) może przejść na inną parafię i potwierdzenie dotyczy już nowego rekordu. `LocalityPanel` ma ten sam układ od początku (`locality-panel.tsx:159-165`), więc to w parafii regresja, w miejscowości istniejące ryzyko.
- **Fix**: W obu panelach dodać `key={record?.id ?? 'new'}` na oknach scalania i usuwania (jedna linia na okno), albo wyzerować flagi przy zmianie `record?.id`.
  - Strength: Odtwarza zachowanie sprzed przeniesienia; `key` jest wzorcem użytym już dla `<Form>`.
  - Tradeoff: Okno otwarte w czasie przejścia znika bez ostrzeżenia.
  - Confidence: HIGH — przyczyna widoczna w kodzie; nie odtworzono scenariusza w przeglądarce.
  - Blind spot: Czy `SidePanel` z `contentKey` już remountuje dzieci (wtedy problem nie występuje).
- **Decision**: FIXED — flagi `mergeOpen`/`deleteOpen` zerują się przy zmianie `record.id` w obu panelach (render-phase reset); `key` na oknach odpadł — remontowanie otwartego okna Radix psuło spec scalania (okno zostawało w „Loading”), a same okna i tak czyszczą swój stan przy zamknięciu

### F4 — Identyfikator formularza parafii zdublowany

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/parishes/parish-panel.tsx:51,176
- **Detail**: Przycisk zapisu w stopce używa `const formId = 'parish-form'`, a `ParishForm` ma na sztywno `id="parish-form"`. Zmiana jednego bez drugiego zostawia „Zapisz parafię” bez działania. `LocalityPanel` używa jednej stałej w obu miejscach.
- **Fix**: Przekazać `formId` do `ParishForm` jako prop.
- **Decision**: FIXED — `formId` przekazywany do `ParishForm` jako prop

### F5 — Przycisk „Usuń” w nagłówku ma etykietę inną niż w planie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/localities/locality-panel.tsx:~65, resources/js/components/parishes/parish-panel.tsx:~97
- **Detail**: Plan (Faza 2, pkt 2) określa „Usuń” `outline sm`; kod ma „Usuń miejscowość” / „Usuń parafię”. Jest to zgodne z celem planu (rzeczownik) i z jego bramką grep, więc to niespójność planu, nie błąd. Pogłębia jednak F1 (dłuższa etykieta = mniej miejsca na tytuł).
- **Fix**: Zaktualizować plan o addendum; jeśli F1 rozwiążesz skracając etykiety, zrobi się to samo.
- **Decision**: FIXED — addendum w plan.md (etykiety „Usuń …” oraz przeniesienie „Scal z…” do stopki)

### F6 — `outline-none` w `focusRingOffset` i `cardInteractive`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/focus-ring.ts:9-15, resources/js/components/tree/card-styles.ts:24
- **Detail**: Plan świadomie kazał zastąpić `cardInteractive` przez `focusRingOffset` (z `outline-none`). W trybie wysokiego kontrastu pierścień z `box-shadow` jest usuwany, a `outline-none` usuwa też zapasowy kontur; Tailwind v4 oferuje `outline-hidden`. `focusRing` ma ten sam wzorzec, więc to dotyczy całego repo, nie tej zmiany.
- **Fix**: Zostawić; ewentualnie osobna zmiana `outline-none` → `outline-hidden` w obu stałych.
- **Decision**: SKIPPED — dotyczy całego repo (`focusRing` ma ten sam wzorzec), poza zakresem

### F7 — Formularze ustawień nadal po angielsku

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/pages/settings/{profile,security}.tsx, resources/js/components/{appearance-tabs,delete-user,manage-passkeys,manage-two-factor}.tsx
- **Detail**: Nagłówki są po polsku, ale pola („Name”, „Current password”), przyciski („Save”), „Light/Dark/System”, okno usuwania konta i komunikaty pozostały po angielsku. Plan wprost wyłącza te teksty („Teksty formularzy ustawień … poza nagłówkami”), więc to zgodne z zakresem. `delete-user.tsx` i `profile.tsx` mają też klasy palety (`border-red-100 bg-red-50 text-red-600`) poza strażnikiem `VIEW_FILES`.
- **Fix**: Zapisać jako osobną zmianę (polonizacja formularzy ustawień + tokeny w `delete-user.tsx`).
- **Decision**: SKIPPED — formularze ustawień poza zakresem planu; kandydat na osobną zmianę

### F8 — Drobne niespójności tekstów

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/persons-empty-state.tsx:11, resources/js/components/{localities/merge-locality-dialog,parishes/merge-parish-dialog}.tsx
- **Detail**: Pusty stan osób nadal mówi „Nie dodano jeszcze żadnej osoby”, a miejscowości/parafie/źródła mają „Brak …” (osoby są poza zakresem planu). Przycisk potwierdzenia scalenia „Scal miejscowości” (liczba mnoga) dotyczy jednego scalenia; „Scal tę miejscowość” brzmiałoby naturalniej.
- **Fix**: Wyrównać „Brak osób” przy okazji kolejnej zmiany pustych stanów; etykieta scalania — bez pośpiechu.
- **Decision**: SKIPPED
