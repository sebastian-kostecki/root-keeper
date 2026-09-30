<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Logged-out entry — variant A („split z panelem marki”)

- **Plan**: context/changes/auth-entry/plan.md
- **Mode**: Deep
- **Date**: 2026-09-30
- **Verdict**: REVISE → SOUND after triage (all 7 findings fixed in the plan)
- **Findings**: 1 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding

14/14 paths ✓ (`components/auth/` is a new dir), 6/6 symbols ✓ (`redirectUsersTo`, `defaultRedirectUri`, `genderSurface`, `--destructive-foreground`, `--tree-line`/`--tree-meta`, Wayfinder `home` on a redirect route), brief↔plan ✓.

Verified sound: the `/` ↔ `/login` loop is real (`RedirectIfAuthenticated::defaultRedirectUri` tries `dashboard` then `home`; no `dashboard` route), so `redirectUsersTo(callable|string)` (`Middleware.php:554`) is required; Wayfinder's generator filters named routes only by name, so `home()` survives `Route::redirect`; every token role is exposed in `@theme`.

## Findings

### F1 — E-mail verification screen is never reached

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 4 #3 + 4.10, Phase 5 #1, Manual Testing step 3
- **Detail**: `app/Models/User.php:5` has the `MustVerifyEmail` import commented out; the class (`:33`) implements only `PasskeyUser`. No link is sent on registration, `verified` never blocks, a new user lands on `/persons`. 4.10 and manual step 3 could not pass; the copy „Wysłaliśmy link…” is untrue while verification is off.
- **Fix A ⭐ Recommended**: Keep the restyle, reach `/email/verify` by URL, scope out enforcement
  - Strength: Stays a UI change; the base `User` trait still renders the prompt for a null `email_verified_at`.
  - Tradeoff: Dead UI until verification is enforced.
  - Confidence: HIGH — code path confirmed.
  - Blind spot: Throwaway users from the shot script accumulate in the local DB.
- **Fix B**: Implement `MustVerifyEmail` in this change
  - Strength: Flow and copy become true.
  - Tradeoff: Behaviour change; unverified existing users locked out; prod mailer; new tests.
  - Confidence: MED — prod mail setup and user data unchecked.
  - Blind spot: How many prod users are unverified.
- **Decision**: FIXED (Fix A) — Current State bullet, What We're NOT Doing line, Phase 4 #3 note, 4.10, Phase 5 #1, manual step 3.

### F2 — Eye toggle is not in the tab order

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Desired End State → Keyboard; Phase 4 #7; 4.9
- **Detail**: `password-input.tsx:27` has `tabIndex={-1}`; the plan changed only the `aria-label`, yet the Keyboard spec and 4.9 list the eye toggle in the Tab order.
- **Fix A ⭐ Recommended**: Remove `tabIndex={-1}` in Phase 4 #7
  - Strength: Toggle usable by keyboard, as specced.
  - Tradeoff: App-wide (settings password forms too).
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Fix B**: Drop the eye toggle from the Keyboard spec and 4.9
  - Strength: No app-wide change.
  - Tradeoff: Toggle stays mouse-only.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — Phase 4 #7 contract; 4.9 also checks `/settings/security`.

### F3 — The global `name` attribute also names passkeys

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 4 #8 (`lang/pl/validation.php:228`)
- **Detail**: The vendor `PasskeyRegistrationRequest.php:37` validates `name` (a passkey's label) with the global attribute; changing it to „imię i nazwisko” mislabels that error. The repo already sets per-form attributes (`SourceDescriptionValidationRules::sourceDescriptionAttributes()`).
- **Fix**: Keep global „nazwa”; add `ProfileValidationRules::profileAttributes()`, pass it in `CreateNewUser` `Validator::make` and `ProfileUpdateRequest::attributes()`.
  - Strength: Existing pattern; only the two user-name forms change.
  - Tradeoff: Two more PHP files, Pint.
  - Confidence: HIGH.
  - Blind spot: No test asserts the message (copy only).
- **Decision**: FIXED — Phase 4 #8 rewritten; 4.4 now runs Pint and `tests/Feature/Settings`.

### F4 — The Phase 3 manual gate needs Phase 4's work

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 → 3.5
- **Detail**: 3.5 expected a board match while copy is English and the passkey `my-6` remains until Phase 4 #6.
- **Fix**: Limit 3.5 to the layout; full board match stays in 4.7 and 5.3.
- **Decision**: FIXED

### F5 — The English-copy grep misses strings

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 → 4.6
- **Detail**: Case-sensitive grep missed „Two-factor authentication”, „Recovery code”, „Authentication code”, „Or continue with email”, „Authenticating...”, „Log out”, „Resend…”, and never scanned `password-input.tsx`.
- **Fix**: `grep -rniE` with extended terms and `password-input.tsx` in scope (tested against current files: every hit is UI copy).
- **Decision**: FIXED

### F6 — The register button is not disabled while submitting

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 4 #2
- **Detail**: `register.tsx:93-99` has the spinner but no `disabled`; double submit possible.
- **Fix**: Add spinner + `disabled={processing}` to the Register intent.
- **Decision**: FIXED

### F7 — The "place for the reset link" decision is not carried into the plan

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 #1
- **Detail**: `change.md` and audit C3 promise a slot next to „Hasło”; the plan only said the link stays out.
- **Fix**: Login contract: the „Hasło” label row stays a flex row for a future `ml-auto` link; no placeholder.
- **Decision**: FIXED
