# Logged-out entry — variant A („split z panelem marki”) Implementation Plan

## Overview

The login screen becomes the app's front door. Every `auth/*` page is rebuilt on board „A — split z panelem marki” of the canvas https://claude.ai/artifact/H3Q5s74TyFYEEftERDRr1w (with „A — rejestracja”, „A — stany formularza”, „A — weryfikacja e-mail”, „A — motyw ciemny”, „A — mobile”, „A — mobile, rejestracja z błędami, ciemny”). Named motif: the shadcn/ui split authentication block, which the starter already ships as `auth-split-layout.tsx`.

The left half is a brand panel on the sidebar surface: the „RK” lettermark and „Root Keeper”, a small family-sheet motif, and one sentence saying what the app is. When the user signs in, the sidebar takes the same place. The right half holds the form. `/` stops being the Laravel starter page: guests go to the login screen, signed-in users go into the app. All logged-out screens are in Polish.

Input: `audit.md` (charges C1–C6, decisions), `screenshots/before/` (32 shots). The change is outside the roadmap, like the other UI passes.

## Current State Analysis

- **Entry.** `routes/web.php:15` renders `welcome.tsx` at `/` (named `home`): the Laravel 13 starter page, English, ~40 literal hex colours, links to laravel.com. `resources/js/app.tsx:15-16` gives it no layout.
- **Redirect loop in waiting.** `guest` middleware (Fortify's GET `/login`, `/register`) sends a signed-in user to `route('home')` (`vendor/laravel/framework/src/Illuminate/Auth/Middleware/RedirectIfAuthenticated.php`, `defaultRedirectUri`); `bootstrap/app.php` does not override it. If `/` simply redirected to `/login`, a signed-in user would bounce `/` → `/login` → `/`.
- **Layout.** `resources/js/app.tsx:17-18` wraps every `auth/*` page in `layouts/auth-layout.tsx`, which renders `layouts/auth/auth-simple-layout.tsx`: the Laravel mark (`components/app-logo-icon.tsx`), a centred `h1` and description, the page. `auth-card-layout.tsx` and `auth-split-layout.tsx` are unused; `app-logo-icon.tsx` is imported only by the three auth layouts.
- **Brand.** `components/app-logo.tsx` (from `app-shell-sidebar`) renders the „RK” tile on `bg-sidebar-primary` plus the shared `name` prop.
- **Pages.** `pages/auth/{login,register,verify-email,confirm-password,two-factor-challenge}.tsx` — English strings, positive `tabIndex` on login and register, status messages in `text-green-600` below or above the form. `two-factor-challenge.tsx` sets its title via `setLayoutProps`; the others via `Page.layout = { title, description }`.
- **Shared form parts.** `components/input-error.tsx:14` (`text-red-600 dark:text-red-400`) and `components/text-link.tsx:15` (`decoration-neutral-300 … dark:decoration-neutral-500`) are used by every form in the app. `components/passkey-verify.tsx` (login, confirm password) adds `my-6` around its separator and English defaults; `components/password-input.tsx:26` names the toggle „Show password” / „Hide password”.
- **Tokens.** Every role the canvas uses exists in `resources/css/app.css`. `--destructive-foreground` (`:90`, `:140`) is the text role `ui/alert.tsx:13` already uses; `ui/alert.tsx` covers status messages.
- **Validation copy.** `lang/pl/validation.php:228` names the attribute `name` „nazwa” → „Pole nazwa jest wymagane.” under the register name field. The sources form sets its own `'name' => 'nazwa'` (`app/Concerns/SourceDescriptionValidationRules.php:39`); register and profile (`app/Concerns/ProfileValidationRules.php`) and the vendor passkey-name request use the global one.
- **E-mail verification is not enforced.** `app/Models/User.php:5` has the `MustVerifyEmail` import commented out and the class (`:33`) implements only `PasskeyUser`: no link is sent on registration, `verified` never blocks, a new user lands on `/persons`. `/email/verify` still renders `verify-email.tsx` for a user whose `email_verified_at` is null (the base `User` carries the `MustVerifyEmail` trait), so the page is reachable only by URL.
- **Tests that pin today's `/`.** `tests/Feature/ExampleTest.php:4` expects `200` on `route('home')`; `tests/Feature/AppShellTest.php:34` reads the guest's shared props from `/`.

## Desired End State

**`/`**: a guest lands on `/login`; a signed-in user lands on `/persons` (`fortify.home`). A signed-in user opening `/login` or `/register` also lands on `/persons`. `welcome.tsx` is gone.

**Desktop ≥ 1024 px (board „A — split z panelem marki”)** — two equal columns, full viewport height.

- Left panel: `bg-sidebar`, `text-sidebar-foreground`, right border `border-sidebar-border`; 32/40 px padding; content spread top-to-bottom:
  - top: the brand link (`AppLogo`) to `home`;
  - middle, decorative (`aria-hidden`): two person cards side by side („Ojciec” male surface, „Matka” female surface), a `tree-line` connector, one card „Ty” (unknown surface); each card has a 12 px `tree-meta` line with a check icon: „akt urodzenia”, „akt małżeństwa”, „akt chrztu”;
  - bottom: „Drzewo genealogiczne, w którym każda data ma źródło.” (24 px, medium) and „Dodawaj osoby i relacje, dołączaj skany aktów i potwierdzaj nimi daty urodzin, ślubów i zgonów.” (14 px, muted).
- Right column: the page, centred, max 360 px wide, left-aligned `h1` (20 px, medium) and description (14 px, muted), then the page's blocks 24 px apart.

**Below 1024 px (board „A — mobile”)**: no panel. The brand link sits at the top left, the page under it, and „Drzewo genealogiczne, w którym każda data ma źródło.” (12 px, muted, centred) at the bottom of the viewport. Side padding 24 px. Inputs and buttons keep the repo's 36 px (decision in `audit.md`).

**Login (boards A, „A — stany formularza”)**: „Zaloguj się” / „Podaj adres e-mail i hasło albo użyj klucza dostępu.”; a status message, when present, as an `Alert` above everything else; „Zaloguj się kluczem dostępu” (outline, full width); separator „lub e-mailem” (12 px, muted, no capitals) with no extra margin; „Adres e-mail”, „Hasło” (with the eye toggle „Pokaż hasło” / „Ukryj hasło”), „Zapamiętaj mnie”, „Zaloguj się”; „Nie masz konta? Załóż konto”. No „Nie pamiętasz hasła?” link. A field with an error has a red border and ring (`aria-invalid`) and the message under it in `--destructive-foreground`. While submitting, the button shows the spinner and is disabled.

**Register (boards „A — rejestracja”, „A — mobile, rejestracja z błędami, ciemny”)**: „Załóż konto” / „Każde konto ma własne, prywatne drzewo.”; „Imię i nazwisko”, „Adres e-mail”, „Hasło”, „Powtórz hasło”, „Załóż konto”; „Masz już konto? Zaloguj się”. An empty name reads „Pole imię i nazwisko jest wymagane.”

**E-mail verification (board „A — weryfikacja e-mail”)**: „Potwierdź adres e-mail” / „Wysłaliśmy link na adres podany przy rejestracji. Kliknij go, aby wejść do drzewa.”; after a resend an `Alert` „Wysłaliśmy nowy link” / „Sprawdź skrzynkę na adres podany przy rejestracji.”; „Wyślij link ponownie” (secondary, full width); „Wyloguj się”.

**Confirm password, two-factor challenge**: same layout, Polish (strings in Phase 4).

**Keyboard**: Tab follows the visual order on every page (brand link → passkey → e-mail → password → eye toggle → remember → submit → footer link); focus is visible in both themes.

**Browser tab titles**: „Logowanie”, „Rejestracja”, „Weryfikacja adresu e-mail”, „Potwierdzenie hasła”, „Weryfikacja dwuetapowa” (plus the app name suffix from `app.tsx`).

### Key Discoveries:

- `RedirectIfAuthenticated::defaultRedirectUri` targets `route('home')`; `bootstrap/app.php` has no `redirectUsersTo` — the loop above is real.
- `ui/alert.tsx:13` already uses `text-destructive-foreground` for its destructive variant; `input-error` can follow it with no new token value.
- `lib/gender-styles.ts:4-8` (`genderSurface`) holds the card surfaces the tree sheet uses; the panel motif reuses it rather than new classes.
- `app-logo.tsx` hides its name only under `group-data-[collapsible=icon]`, so it renders complete outside the sidebar.
- `two-factor-challenge.tsx:119` repeats `text-link`'s literal decoration classes on a `button`.

## What We're NOT Doing

- Password reset (Fortify `resetPasswords`, mail, `forgot-password` / `reset-password` pages, tests) — its own change; the link stays out until then.
- Enforcing e-mail verification (`User implements MustVerifyEmail`, mail on registration, locking unverified users out) — a behaviour change with its own tests and production mailer; `verify-email.tsx` is restyled here so it is ready, and checked by opening `/email/verify` directly.
- 44 px touch-size inputs and buttons on phones — a change to `ui/input` / `ui/button` for every form (deferred in `audit.md`).
- English strings on the settings pages (`pages/settings/*`) — their own view; `password-input`'s toggle label becomes Polish everywhere, including there.
- A marketing landing page, feature list or screenshots of the app for guests.
- New colour tokens or a `success` role.
- A screenshot-test runner: the gate is `screenshots/shots.mjs` (npx Playwright, not a dependency), as in `app-shell-sidebar`.

## Implementation Approach

Contract before pixels, as in `app-shell-sidebar`: first the two shared form parts read tokens (their effect reaches every form, so it is checked on its own), then the entry route with its tests, then the layout, then the pages and their states, then the screenshot gate. Each phase leaves the app working.

## Critical Implementation Details

- **Timing & lifecycle** — the `guest` redirect target and the `/` redirect land in the same phase (Phase 2). `Route::redirect('/', '/login')` alone creates the `/` ↔ `/login` loop for signed-in users; `redirectUsersTo` pointing at `config('fortify.home')` breaks it.
- **User experience spec** — the separator spacing comes from the layout's `gap-6` between the page's blocks; `PasskeyVerify` returns its button and separator as siblings, so its own margins must go or the gap doubles (the 60 px of C5).

## Phase 1: Shared form parts read tokens

### Overview

The error text and the text link take their colours from roles, in both themes. Every form in the app uses these two components.

### Changes Required:

#### 1. Field error

**File**: `resources/js/components/input-error.tsx`

**Intent**: Replace the literal reds with the destructive text role, the one `ui/alert.tsx` uses (audit C2).

**Contract**: `className` base becomes `text-sm text-destructive-foreground`; props and `data-slot="input-error"` unchanged.

#### 2. Text link

**File**: `resources/js/components/text-link.tsx`

**Intent**: Take the underline colour from `--muted-foreground` instead of `neutral-300` / `neutral-500` (audit C2).

**Contract**: `decoration-muted-foreground/50`, hover `decoration-current` as today; no `dark:` variant.

#### 3. Recovery-code toggle

**File**: `resources/js/pages/auth/two-factor-challenge.tsx:119`

**Intent**: The toggle button copies `text-link`'s literal classes; give it the same token classes as item 2.

**Contract**: class string only.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- No literal neutral/red colour left in the three files: `grep -nE "neutral-|red-[0-9]" resources/js/components/input-error.tsx resources/js/components/text-link.tsx resources/js/pages/auth/two-factor-challenge.tsx` prints nothing

#### Manual Verification:

- A validation error in a signed-in form (e.g. „Dodaj osobę” with an empty name) reads clearly in light and dark
- Links on `/settings/profile` keep a visible underline in both themes; hover darkens it

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: `/` leads into the app

### Overview

The starter page goes. `/` sends guests to the login screen and signed-in users into the app, with no redirect loop.

### Changes Required:

#### 1. Route

**File**: `routes/web.php:15`

**Intent**: `/` stops rendering `welcome` and redirects to the login screen; the route keeps the name `home`, which Fortify's logout, the tests and Wayfinder's `home()` use.

**Contract**: `Route::redirect('/', '/login')->name('home')` (or the equivalent to the named `login` route). Regenerate Wayfinder: `vendor/bin/sail artisan wayfinder:generate`.

#### 2. Where `guest` sends a signed-in user

**File**: `bootstrap/app.php` (`withMiddleware`)

**Intent**: A signed-in user who opens `/login` or `/register` (and so `/`) lands on `fortify.home` (`/persons`), not on `/`.

**Contract**: `$middleware->redirectUsersTo(...)` returning `config('fortify.home')`. Confirm the method name and signature with Boost `search-docs` (`redirectUsersTo`) before writing it.

#### 3. Starter page

**Files**: `resources/js/pages/welcome.tsx` (delete), `resources/js/app.tsx:15-16`

**Intent**: Remove the page and its `welcome` case in the layout switch.

**Contract**: no page named `welcome` remains; `grep -rn welcome resources/js` prints nothing outside generated dirs.

#### 4. Tests

**Files**: `tests/Feature/Auth/EntryTest.php` (new, `vendor/bin/sail artisan make:test --pest Auth/EntryTest` — check whether the subfolder form is accepted, else create beside `AuthenticationTest.php`), `tests/Feature/ExampleTest.php`, `tests/Feature/AppShellTest.php:33-37`

**Intent**: Pin the new entry behaviour and move the two tests that relied on `/` rendering a page.

**Contract**:
- `EntryTest`: a guest on `/` is redirected to `/login`; a signed-in user on `/login` is redirected to `/persons`; a signed-in user following redirects from `/` ends on `/persons` with `200`.
- `ExampleTest`: assert the redirect to `route('login')` instead of `assertOk()`.
- `AppShellTest` „the shell counts are null for a guest”: read the props from `/login`.

### Success Criteria:

#### Automated Verification:

- Entry and affected tests pass: `vendor/bin/sail artisan test --compact tests/Feature/Auth/EntryTest.php tests/Feature/ExampleTest.php tests/Feature/AppShellTest.php tests/Feature/Auth`
- Pint: `vendor/bin/sail bin pint --dirty --format agent`
- Types and build pass: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run build`

#### Manual Verification:

- Logged out, `http://localhost:8000/` shows the login screen at `/login`
- Logged in, `/` and `/login` both open „Osoby”; logging out returns to the login screen

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: The split layout

### Overview

All `auth/*` pages render in the layout of board A: the brand panel on the left from 1024 px, the brand row on top below it. The starter's three auth variants and the Laravel mark go.

### Changes Required:

#### 1. Brand panel

**File**: `resources/js/components/auth/auth-brand-panel.tsx` (new)

**Intent**: The left half of board A: brand link, the decorative family-sheet motif, the sentence and the paragraph (texts in Desired End State).

**Contract**: default-exported component, no props. Brand: `Link` to `home()` wrapping `AppLogo`, with a visible focus ring. Motif: `aria-hidden`, three cards using `genderSurface.male` / `.female` / `.unknown` from `lib/gender-styles.ts`, connectors drawn with `bg-tree-line` (1 px), meta lines `text-tree-meta` with lucide `CircleCheck` at 12 px. Surface `bg-sidebar text-sidebar-foreground border-r border-sidebar-border`. No literal colours.

#### 2. Layout

**File**: `resources/js/layouts/auth/auth-split-layout.tsx` (rewrite), `resources/js/layouts/auth-layout.tsx`

**Intent**: The split of board A; `auth-layout.tsx` renders it instead of the simple variant.

**Contract**: `AuthLayoutProps` unchanged (`title`, `description`, `children`). Grid `min-h-svh`, `lg:grid-cols-2`; the panel `hidden lg:flex`; the form column centres a `max-w-[360px]` stack with `gap-6`: header (`h1` `text-xl font-medium`, description `text-muted-foreground text-sm`, 8 px apart, left-aligned), then `children`. Below `lg`: brand link at the top (`p-6`), the sentence as a 12 px muted footer at the bottom. The page's blocks are direct children of the `gap-6` stack.

#### 3. Starter leftovers

**Files**: `resources/js/layouts/auth/auth-simple-layout.tsx`, `resources/js/layouts/auth/auth-card-layout.tsx`, `resources/js/components/app-logo-icon.tsx` (delete)

**Intent**: Remove the unused variants and the Laravel mark (audit C3).

**Contract**: `grep -rn "AppLogoIcon\|auth-simple-layout\|auth-card-layout" resources/js` prints nothing.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Production build passes: `vendor/bin/sail npm run build`
- No literal colours in the layout and panel: `grep -nE "#[0-9a-fA-F]{3,6}|zinc-|neutral-|text-white|text-black" resources/js/layouts/auth resources/js/components/auth` prints nothing

#### Manual Verification:

- 1280 px, light and dark: the layout of `/login` matches boards „A — split z panelem marki” and „A — motyw ciemny” — panel, motif, sentence, form column width, left-aligned header. Copy and the passkey separator spacing are still Phase 4's; the full board match is checked in 4.7 and 5.3
- 1024 px: the panel shows; 1023 px and 390 px: brand row on top, sentence at the bottom, no horizontal scroll
- The brand link opens the login screen (guest) and is reachable and visibly focused by keyboard

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Pages, copy and states

### Overview

Each auth page speaks Polish, follows the visual tab order, shows errors on the field itself and status messages as an `Alert` at the top.

### Changes Required:

#### 1. Login

**File**: `resources/js/pages/auth/login.tsx`

**Intent**: Polish copy (Desired End State), no `tabIndex`, status as `Alert` rendered first (audit C5), `aria-invalid` on a field whose error is present, spinner + disabled while processing.

**Contract**: `Login.layout` `{ title: 'Zaloguj się', description: 'Podaj adres e-mail i hasło albo użyj klucza dostępu.' }`; `Head` „Logowanie”; `data-test="login-button"` kept. The „Hasło” label row stays a flex row (`flex items-center`) so the password-reset change can add a right-aligned „Nie pamiętasz hasła?” link (`ml-auto`); no placeholder is rendered now (decision in `change.md` / audit C3).

#### 2. Register

**File**: `resources/js/pages/auth/register.tsx`

**Intent**: Polish copy, no `tabIndex`, `aria-invalid` on fields with errors; drop the extra `mt-2` on the name error so all fields space alike; spinner + `disabled={processing}` on the submit button, as on login (today `register.tsx:93-99` has the spinner but no `disabled`).

**Contract**: `Register.layout` `{ title: 'Załóż konto', description: 'Każde konto ma własne, prywatne drzewo.' }`; `Head` „Rejestracja”; placeholders „Jan Kowalski”, „email@example.com”, „Hasło”, „Powtórz hasło”; `data-test="register-user-button"` kept.

#### 3. E-mail verification

**File**: `resources/js/pages/auth/verify-email.tsx`

**Intent**: Polish copy; the „link sent” status as an `Alert` with a check icon above the button; the resend button full width. The page is reachable only by URL until verification is enforced (see What We're NOT Doing); the copy is written for that later state.

**Contract**: `VerifyEmail.layout` `{ title: 'Potwierdź adres e-mail', description: 'Wysłaliśmy link na adres podany przy rejestracji. Kliknij go, aby wejść do drzewa.' }`; `Head` „Weryfikacja adresu e-mail”; `Alert` title „Wysłaliśmy nowy link”, description „Sprawdź skrzynkę na adres podany przy rejestracji.”; button „Wyślij link ponownie”; link „Wyloguj się”.

#### 4. Confirm password

**File**: `resources/js/pages/auth/confirm-password.tsx`

**Intent**: Polish copy and passkey labels.

**Contract**: layout `{ title: 'Potwierdź hasło', description: 'To chroniona część aplikacji. Potwierdź hasło, aby kontynuować.' }`; `Head` „Potwierdzenie hasła”; passkey `label` „Potwierdź kluczem dostępu”, `loadingLabel` „Potwierdzanie…”, `separator` „lub hasłem”; label „Hasło”; button „Potwierdź hasło”.

#### 5. Two-factor challenge

**File**: `resources/js/pages/auth/two-factor-challenge.tsx`

**Intent**: Polish copy for both modes.

**Contract**: code mode title „Kod uwierzytelniający”, description „Wpisz kod z aplikacji uwierzytelniającej.”, toggle „użyj kodu odzyskiwania”; recovery mode title „Kod odzyskiwania”, description „Potwierdź dostęp do konta jednym z kodów odzyskiwania.”, toggle „użyj kodu z aplikacji”, placeholder „Wpisz kod odzyskiwania”; lead-in „albo”; button „Dalej”; `Head` „Weryfikacja dwuetapowa”.

#### 6. Passkey block

**File**: `resources/js/components/passkey-verify.tsx`

**Intent**: Polish defaults; the separator without `my-6` and without capitals, so the layout's `gap-6` spaces it (audit C5).

**Contract**: defaults `label` „Zaloguj się kluczem dostępu”, `loadingLabel` „Uwierzytelnianie…”, `separator` „lub e-mailem”; any other user-visible string in the file in Polish; props unchanged.

#### 7. Password toggle

**File**: `resources/js/components/password-input.tsx:26-27`

**Intent**: The toggle's accessible name in Polish, and the toggle reachable by keyboard (today `tabIndex={-1}` keeps it out of the Tab order the Keyboard spec lists). Applies to every password field in the app, settings included.

**Contract**: `aria-label` „Pokaż hasło” / „Ukryj hasło”; `tabIndex={-1}` removed.

#### 8. Validation attribute

**Files**: `app/Concerns/ProfileValidationRules.php`, `app/Actions/Fortify/CreateNewUser.php:23`, `app/Http/Requests/Settings/ProfileUpdateRequest.php`

**Intent**: The register (and profile) name error names the field as its label does. The global `'name' => 'nazwa'` in `lang/pl/validation.php:228` stays: it also labels the passkey name (`laravel/passkeys` `PasskeyRegistrationRequest`), where „imię i nazwisko” would be wrong. Follows `SourceDescriptionValidationRules::sourceDescriptionAttributes()`.

**Contract**: `ProfileValidationRules::profileAttributes(): array` returning `['name' => 'imię i nazwisko']` (PHPDoc `@return array<string, string>`); `CreateNewUser` passes it as the 4th argument of `Validator::make($input, [...], [], $this->profileAttributes())`; `ProfileUpdateRequest::attributes()` returns it. `lang/pl/validation.php` unchanged.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Production build passes: `vendor/bin/sail npm run build`
- Pint, then auth, entry, settings and sources tests pass: `vendor/bin/sail bin pint --dirty --format agent` and `vendor/bin/sail artisan test --compact tests/Feature/Auth tests/Feature/Settings tests/Feature/SourcesTest.php`
- No positive `tabIndex` left on auth pages: `grep -rn "tabIndex={[1-9]" resources/js/pages/auth` prints nothing
- No English copy left on auth pages: `grep -rniE "log in|log out|sign up|sign in with|email address|>\s*password\s*<|placeholder=\"password\"|remember me|create account|confirm password|continue|recovery code|authentication code|two-factor authentication|authenticating|resend|show password|hide password" resources/js/pages/auth resources/js/components/passkey-verify.tsx resources/js/components/password-input.tsx` prints nothing (checked against today's files: every hit is UI copy, none is code)

#### Manual Verification:

- Wrong password at 1280 px, light and dark: matches „A — stany formularza” / „A — motyw ciemny” — red border and ring on the e-mail field, „Błędny login lub hasło.” under it
- Register submitted empty at 390 px, dark: matches „A — mobile, rejestracja z błędami, ciemny”, including „Pole imię i nazwisko jest wymagane.”
- Keyboard only on `/login`: Tab goes brand → passkey → e-mail → password → eye toggle → remember → submit → „Załóż konto”, each with a visible ring; on `/settings/security` the eye toggles are reachable by Tab too
- A local user with `email_verified_at` null opening `/email/verify` directly sees „A — weryfikacja e-mail”; „Wyślij link ponownie” shows the `Alert`
- A local user with 2FA on sees the Polish challenge in both modes; the toggle switches modes

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Screenshot gate

### Overview

The same shot set as `before/`, plus the states the change adds, rendered in both themes at 1280 and 390, compared with the boards and with `before/`.

### Changes Required:

#### 1. Shot script

**File**: `context/changes/auth-entry/screenshots/shots.mjs`

**Intent**: Add the submitting state (delay the `POST /login` with `page.route` and shoot while the spinner shows) and e-mail verification (register a throwaway `shots-<timestamp>@example.com` user in the local database — registration lands on `/persons`, since verification is not enforced — then open `/email/verify` directly and shoot it before and after „Wyślij link ponownie”). Keep every `before/` shot name so the pairs line up; `signed-in-home` now shows „Osoby”.

**Contract**: `node shots.mjs after` writes `after/<theme>-<width>-<state>.png`; header comment lists the new states and notes that the verification shots create a local user.

#### 2. Evidence

**File**: `context/changes/auth-entry/screenshots/after/`

**Intent**: The rendered set, checked against the canvas boards; differences that are intended are listed in the phase's commit message, not hidden.

**Contract**: PNGs only; no baseline tool.

### Success Criteria:

#### Automated Verification:

- The script runs cleanly: `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node context/changes/auth-entry/screenshots/shots.mjs context/changes/auth-entry/screenshots/after`
- Every `before/` shot name has an `after/` counterpart

#### Manual Verification:

- Each `after/` shot matches its board: A, dark, mobile, register, register errors (mobile, dark), states (error, submitting, focus), e-mail verification
- `after/*-guest-home.png` shows the login screen, `after/*-signed-in-home.png` shows „Osoby”
- Contrast holds in dark: error text, muted description, `tree-meta` on the card surfaces

**Implementation Note**: After completing this phase, run `/10x-impl-review auth-entry`.

---

## Testing Strategy

### Unit Tests:

- None; the change is routing, layout and copy.

### Integration Tests:

- `tests/Feature/Auth/EntryTest.php`: guest `/` → `/login`; signed-in `/login` → `/persons`; signed-in `/` followed → `/persons` `200`.
- Existing `tests/Feature/Auth/*` (login, register, verification, confirm password, 2FA) must stay green: they post the same fields and assert the same redirects.
- `ExampleTest` and `AppShellTest` updated to the new `/`.

### Manual Testing Steps:

1. Logged out: open `/`, land on `/login`; wrong password → error on the field; right password → „Osoby”.
2. Logged in: open `/`, `/login`, `/register` → „Osoby”.
3. Register a new user (lands on „Osoby”) → open `/email/verify` directly → resend → `Alert`.
4. Resize 1280 → 1024 → 1023 → 390: panel appears/disappears at 1024, no horizontal scroll.
5. Keyboard-only pass on `/login` and `/register`.

## Performance Considerations

None: one extra redirect on `/` for signed-in users (`/` → `/login` → `/persons`).

## Migration Notes

Bookmarks to `/` keep working. Nothing in the database changes.

## References

- Audit and decisions: `context/changes/auth-entry/audit.md`
- Canvas: https://claude.ai/artifact/H3Q5s74TyFYEEftERDRr1w
- Evidence before: `context/changes/auth-entry/screenshots/before/`
- Similar change: `context/changes/app-shell-sidebar/plan.md` (tokens → shell → states → screenshot gate)
- Card surfaces: `resources/js/lib/gender-styles.ts:4-8`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shared form parts read tokens

#### Automated

- [x] 1.1 Format and lint pass: `vendor/bin/sail npm run check`
- [x] 1.2 Types pass: `vendor/bin/sail npm run types:check`
- [x] 1.3 No literal neutral/red colour left in the three files

#### Manual

- [x] 1.4 A validation error in a signed-in form reads clearly in light and dark
- [x] 1.5 Links on `/settings/profile` keep a visible underline in both themes; hover darkens it

### Phase 2: `/` leads into the app

#### Automated

- [ ] 2.1 Entry and affected tests pass
- [ ] 2.2 Pint: `vendor/bin/sail bin pint --dirty --format agent`
- [ ] 2.3 Types and build pass

#### Manual

- [ ] 2.4 Logged out, `/` shows the login screen at `/login`
- [ ] 2.5 Logged in, `/` and `/login` both open „Osoby”; logging out returns to the login screen

### Phase 3: The split layout

#### Automated

- [ ] 3.1 Format and lint pass: `vendor/bin/sail npm run check`
- [ ] 3.2 Types pass: `vendor/bin/sail npm run types:check`
- [ ] 3.3 Production build passes: `vendor/bin/sail npm run build`
- [ ] 3.4 No literal colours in the layout and panel

#### Manual

- [ ] 3.5 1280 px, light and dark: the layout of `/login` matches boards „A — split z panelem marki” and „A — motyw ciemny”
- [ ] 3.6 1024 px: the panel shows; 1023 px and 390 px: brand row on top, sentence at the bottom, no horizontal scroll
- [ ] 3.7 The brand link opens the login screen and is reachable and visibly focused by keyboard

### Phase 4: Pages, copy and states

#### Automated

- [ ] 4.1 Format and lint pass: `vendor/bin/sail npm run check`
- [ ] 4.2 Types pass: `vendor/bin/sail npm run types:check`
- [ ] 4.3 Production build passes: `vendor/bin/sail npm run build`
- [ ] 4.4 Pint, then auth, entry, settings and sources tests pass
- [ ] 4.5 No positive `tabIndex` left on auth pages
- [ ] 4.6 No English copy left on auth pages

#### Manual

- [ ] 4.7 Wrong password at 1280 px, light and dark: matches „A — stany formularza” / „A — motyw ciemny”
- [ ] 4.8 Register submitted empty at 390 px, dark: matches „A — mobile, rejestracja z błędami, ciemny”
- [ ] 4.9 Keyboard only on `/login`: Tab follows the visual order (eye toggle included), each with a visible ring
- [ ] 4.10 An unverified local user opening `/email/verify` directly sees „A — weryfikacja e-mail”; resend shows the `Alert`
- [ ] 4.11 A local user with 2FA on sees the Polish challenge in both modes

### Phase 5: Screenshot gate

#### Automated

- [ ] 5.1 The script runs cleanly and writes `after/`
- [ ] 5.2 Every `before/` shot name has an `after/` counterpart

#### Manual

- [ ] 5.3 Each `after/` shot matches its board
- [ ] 5.4 `after/*-guest-home.png` shows the login screen, `after/*-signed-in-home.png` shows „Osoby”
- [ ] 5.5 Contrast holds in dark: error text, muted description, `tree-meta` on the card surfaces
