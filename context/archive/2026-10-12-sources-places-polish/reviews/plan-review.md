<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Design system Root Keeper na /sources i /places oraz polskie ustawienia

- **Plan**: context/changes/sources-places-polish/plan.md
- **Mode**: Deep
- **Date**: 2026-10-12
- **Verdict**: REVISE → SOUND po triage
- **Findings**: 0 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Ścieżki 30/30 ✓ (jedyna nieistniejąca to `processing-reporter.tsx`, zaplanowana jako nowa); symbole ✓ (`ProcessingReporter` w `source-panel.tsx`, `emptyTreeCopy`, `ariaLabel="Spis"`, `FORM_ID`); brief↔plan ✓; Progress↔Phase ✓ (1: 5+4, 2: 5+5, 3: 7+5, nagłówki `### Phase N` zgodne). Weryfikacja kodu: panele, fokus, miniatura/chip, puste stany, ustawienia, zakres specy E2E i `shots.mjs`.

## Findings

### F1 — Kryterium 3.11 jest nieosiągalne: część nagłówków ustawień zostaje po angielsku

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 — #3 Ustawienia po polsku; Progress 3.11
- **Detail**: Plan zmienia `Head`, breadcrumbs, menu i `Heading` w czterech plikach, ale na `/settings/profile` i `/settings/security` zostają angielskie `Heading`: `components/delete-user.tsx:24` („Delete account”), `components/manage-two-factor.tsx:49` („Two-factor authentication”), `components/manage-passkeys.tsx:48` („Passkeys”), oraz `aria-label="Settings"` nawigacji w `layouts/settings/layout.tsx:45`. „What We're NOT Doing” wyłącza „2FA, klucze dostępu poza nagłówkami `Heading`”, więc te nagłówki wchodzą w zakres, ale nie ma ich w Changes Required. Kryterium 3.11 („nagłówki … po polsku”) nie przejdzie.
- **Fix**: Dopisać do #3 pliki `delete-user.tsx`, `manage-two-factor.tsx`, `manage-passkeys.tsx` z polskimi tytułami i opisami `Heading` oraz `aria-label="Ustawienia"` w layoucie; reszta tekstów (przyciski, pola, 2FA) bez zmian.
- **Decision**: FIXED (Fix in plan) — dopisano `delete-user.tsx`, `manage-two-factor.tsx`, `manage-passkeys.tsx` i `aria-label` layoutu do Phase 3 #3

### F2 — Spec `fact-source-scan` nie pęknie po zmianie, a plan zakłada, że pęknie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 — #7; Current State Analysis (tests/e2e/fact-source-scan.spec.ts:62)
- **Detail**: Plan twierdzi, że `getByText(/^Potwierdzone/)` przestanie działać, gdy prefiks trafi do `sr-only`. Sprawdzone Playwrightem na zrenderowanym `<span sr-only>Potwierdzone: </span>Akt urodzenia`: lokator nadal znajduje 1 element i `toBeVisible()` zwraca true (span ma 1 px, nie jest ukryty). Spec zostaje zielony, ale przestaje dowodzić, że rodzaj dokumentu jest widoczny w chipie — dokładnie to, co zmiana przenosi na ekran.
- **Fix**: Zmienić wymaganie w #7: asercja ma sprawdzać widoczną treść chipa (rodzaj dokumentu, np. `getByText('Akt urodzenia')` w obrębie faktu urodzenia) obok istniejącej; opis „spec pęka” zastąpić „spec słabnie”.
- **Decision**: FIXED (Fix in plan) — Current State i Phase 1 #7 poprawione: spec słabnie, dodana asercja widocznego rodzaju dokumentu

### F3 — Zrzuty: plan wskazuje aplikację bez danych demo

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 1 — #1 Skrypt zrzutów; Phase 3 — #4; Key Discoveries
- **Detail**: Plan mówi: aplikacja z `scripts/e2e-server.sh`, `tree-demo@example.com`. `e2e-server.sh` startuje osobną bazę `e2e` i tworzy tylko użytkownika `E2E_USERNAME` bez seedowania — drzewa demo tam nie ma (ten sam błąd przeszedł z planu design-tokens; tamten `shots.mjs` w nagłówku mówi o serwerze dev Sail na `localhost:8000` z `TreeViewDemoSeeder`). Dodatkowo Phase 3 zmienia tytuły pustych stanów „Brak źródeł/miejscowości/parafii”, ale lista zrzutów nie ma żadnego pustego stanu; seeder ma do tego `tree-demo-empty@example.com`.
- **Fix A ⭐ Recommended**: Poprawić Contract: zrzuty z serwera dev Sail (`SHOTS_BASE`, domyślnie `http://localhost:8000`, dane `TreeViewDemoSeeder`), plus dodać zrzuty pustych stanów `/sources` i `/places` (oba spisy) na `tree-demo-empty@example.com` z osobnym `storageState`.
  - Strength: Zgodne z faktycznym `shots.mjs`; pokrywa wszystkie zmienione widoki, w tym puste stany.
  - Tradeoff: Skrypt loguje dwóch użytkowników; zestaw rośnie o ~6 zrzutów.
  - Confidence: HIGH — nagłówek archiwalnego `shots.mjs` i seeder potwierdzają obie rzeczy.
  - Blind spot: Czy dev build w `public/build` jest świeży przy porównaniu przed/po (znana pułapka z design-tokens) — plan już o tym pisze.
- **Fix B**: Tylko poprawić opis źródła aplikacji; puste stany sprawdzić ręcznie.
  - Strength: Mniej pracy, mniejszy zestaw zrzutów.
  - Tradeoff: Zmiana tytułów i wariantu przycisku w pustych stanach nie wchodzi do bramki wizualnej.
  - Confidence: MED — manualne 3.8/3.9 już to częściowo pokrywają.
  - Blind spot: Nie wiadomo, czy ręczna kontrola będzie wykonana w obu motywach.
- **Decision**: FIXED via Fix A — źródło zrzutów: serwer dev Sail :8000 + `TreeViewDemoSeeder`; dodane zrzuty pustych stanów na `tree-demo-empty@example.com` (plan.md Phase 1 #1, plan-brief.md)

### F4 — Tekst kontroli ręcznej dla „brak wyników” nie zgadza się z UI

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 — Manual 3.8; Manual Testing Steps pkt 5; Phase 3 #1
- **Detail**: Plan opisuje przycisk jako „Parafie (n)” / „Miejscowości (n)”. W kodzie etykieta to `Pokaż w ${kindLocative}` (`pages/places/index.tsx:262`), np. „Pokaż w parafiach (1)”; `places-page.spec.ts:70-72` asercjonuje właśnie to. Zmiana planu (wariant outline) jest poprawna, tylko opis weryfikacji wprowadza w błąd.
- **Fix**: Poprawić 3.8, Manual Testing pkt 5 i brief na „Pokaż w parafiach (n)” / „Pokaż w miejscowościach (n)”.
- **Decision**: FIXED (Fix in plan) — etykieta „Pokaż w parafiach (n)” w Phase 3 #1, 3.8, Manual Testing pkt 5 i Progress 3.8

### F5 — `focusRingOffset` duplikuje istniejące `cardInteractive`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 — #2 Fokus z odstępem
- **Detail**: `components/tree/card-styles.ts:23` już eksportuje `cardInteractive` z dokładnie tym zestawem (`focus-visible:ring-ring ring-offset-background ring-3 ring-offset-2 outline-none`). Nowa stała w `lib/focus-ring.ts` to druga kopia tego samego wzorca (rozrost wzorców); import z `tree/card-styles` do galerii źródeł byłby odwrotnym kierunkiem zależności.
- **Fix**: Zdefiniować `focusRingOffset` w `lib/focus-ring.ts` (jak w planie) i zmienić `cardInteractive` tak, by go używał (`cardInteractive = focusRingOffset`), żeby wzorzec miał jedno źródło.
- **Decision**: FIXED (Fix in plan) — Phase 1 #2: `cardInteractive = focusRingOffset`, plik `card-styles.ts` dopisany

### F6 — Pominięte szczegóły wykonawcze w panelach i galerii

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 2 — #2, #3; Critical Implementation Details; Phase 1 — #2, #3
- **Detail**: Weryfikacja kodu wykazała niedopowiedzenia, które implementator odkryje w trakcie: (1) `LocalityPanel` ma już `mergeOpen`/`deleteOpen` na poziomie panelu — wynosić trzeba tylko `isDirty`/`processing`; lift dialogów dotyczy wyłącznie `ParishForm` (`parish-panel.tsx:73`, dialogi `:211-225`). (2) `DirtyReporter` woła `onDirtyChange(false)` w cleanupie przy każdej zmianie tożsamości callbacka (`dirty-reporter.tsx:17`) — połączony callback (lokalny `setIsDirty` + `onDirtyChange`) warto owinąć w `useCallback`. (3) `footer` w `SidePanel` to goły `div` (`side-panel.tsx:216`) — przycisk i `<p id="panel-actions-hint">` potrzebują własnego kontenera flex. (4) Nagłówek przy szerokości 24 rem po dodaniu dwóch przycisków `outline sm` zostawia ok. 150 px na tytuł (jest `truncate`, bez tooltipu). (5) Zewnętrzny pierścień (5 px) karty galerii przy pierwszej kolumnie nie został sprawdzony pod kątem przycięcia przez kontener przewijania. (6) Chip: `variant="default"` nadpisywany klasami działa tylko dzięki tailwind-merge — `variant="outline"` dla obu stanów jest czystszy.
- **Fix**: Dopisać te punkty do „Critical Implementation Details” (stały callback, flex w stopce, lift tylko tam, gdzie potrzeba, `variant="outline"` w chipie) i dodać do werdyktu zrzutów obserwację: szerokość tytułu w nagłówku panelu i przycinanie pierścienia karty.
- **Decision**: FIXED (Fix in plan) — Critical Implementation Details, kontrakt chipa (`variant="outline"`) i kontrakt werdyktu uzupełnione
