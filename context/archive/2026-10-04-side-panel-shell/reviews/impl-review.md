<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Wspólna powłoka panelu bocznego (F-02)

- **Plan**: context/changes/side-panel-shell/plan.md
- **Scope**: Full plan (3 of 3 phases)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-04
- **Verdict**: APPROVED
- **Findings**: 0 critical 2 warnings 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated criteria re-run: Vitest 131/131, tsc, `npm run check`, Pest SidePanelDemoTest 5/5, Larastan 0 errors, Pint, build — all pass. Manual items 2.5–2.6 and 3.8–3.15 are ticked with commit 7d7d84d/5f4a55c; they are not observable from the diff (UI checks), so they rest on the author's confirmation.

## Findings

### F1 — Focus restore runs on every unmount

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/side-panel.tsx:98-116
- **Detail**: Cleanup refocuses the remembered trigger on any unmount, including navigation to another page while the panel is open (focus can jump to an old, still-connected trigger). `previousFocusRef` is never cleared after restore.
- **Fix**: Restore focus only when `document.activeElement` is inside the panel or `body`, and clear the ref after restoring.
  - Strength: Keeps the plan's close-to-trigger behaviour, removes the stray jump.
  - Tradeoff: Small extra condition; must be rechecked manually (3.12).
  - Confidence: MED — reasoned from code, not reproduced.
  - Blind spot: Behaviour when the trigger sits inside a re-rendered list.
- **Decision**: FIXED via Fix (focus-free condition + ref cleared)

### F2 — Mobile full-screen panel is not inert, no reduced-motion

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/side-panel.tsx:144-156
- **Detail**: Below `md` the panel is `fixed inset-0` and behaves visually like a modal, but `aria-modal="false"` (plan decision) leaves the page behind reachable by Tab and screen reader. `animate-in slide-in-from-right` has no `motion-reduce:animate-none`.
- **Fix**: Add `motion-reduce:animate-none` now; decide on `inert` for the background below `md` when the first real consumer lands.
  - Strength: Reduced-motion is a one-class fix; inert needs real content to test.
  - Tradeoff: Inert needs a matchMedia hook and touches the layout.
  - Confidence: MED — aria-modal choice was deliberate in the plan.
  - Blind spot: Effect on F-01 role-based locators.
- **Decision**: FIXED via Fix (motion-reduce:animate-none; inert deferred to first consumer)

### F3 — Person preview and panel helper diverge

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/person-preview-provider.tsx:19-40 vs resources/js/lib/side-panel-url.ts:6-9
- **Detail**: `withPerson` does not clear other panel keys and `previewVisitOptions.only` omits `demoPanel`, while `PANEL_PROPS` already lists `person`. Intended by plan (migration is S-06); only affects the dev demo today.
- **Fix**: Migrate to `withPanel`/`panelVisitOptions` in S-06.
- **Decision**: SKIPPED (migration planned in S-06)

### F4 — Escape works only with focus inside the panel

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/side-panel.tsx:122-141
- **Detail**: Native listener sits on the panel root (per plan), so after clicking the tree Escape does nothing. Listener also re-subscribes when `onRequestClose` identity changes (cosmetic).
- **Fix**: Accept; mention in S-01 acceptance if document-level Escape is wanted.
- **Decision**: SKIPPED (accepted as planned)

### F5 — Open and close both use replace, Back leaves the page

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/hooks/use-side-panel-navigation.ts:23-34
- **Detail**: As planned (matches person preview). Browser Back does not close the panel.
- **Fix**: Accept as designed.
- **Decision**: SKIPPED (accepted as designed)

### F6 — Test gaps: non-numeric `?demo=`, hook `close()`

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/SidePanelDemoTest.php
- **Detail**: `?demo=abc` and `?demo[]=1` should yield `demoPanel === null` (200) but are untested; `close()` has no test (plan defers E2E to first consumer).
- **Fix**: Add one Pest case for `?demo=abc`.
- **Decision**: FIXED via Fix (Pest dataset for ?demo=abc and ?demo[]=1)

### F7 — Dev route and page ship to production

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: routes/web.php:42, app/Http/Controllers/SidePanelDemoController.php:16
- **Detail**: Route is registered everywhere and gated by `abort_unless(app()->isLocal(), 404)`; covered by test. Acceptable.
- **Fix**: Optionally register the route only when `app()->isLocal()`.
- **Decision**: SKIPPED (controller gate is enough)
