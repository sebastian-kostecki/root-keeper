# Audit — logged-out entry (`/`, login, register, e-mail verification, 2FA, confirm password)

Audit date: 2026-09-30. View: `resources/js/layouts/auth-layout.tsx` → `resources/js/layouts/auth/auth-simple-layout.tsx`, with `resources/js/pages/auth/login.tsx` as the reference page; `resources/js/pages/welcome.tsx` at `/`.

Evidence "before": `screenshots/before/` (32 PNGs, `shots.mjs before`, seeder `TreeViewDemoSeeder`, HEAD `3657421`): light and dark, 1280 and 390; guest `/`, login, login after a failed attempt, keyboard focus on the submit button, register, register submitted empty, signed-in `/`, confirm password.

## Design-system contract as found

| Half | Where | State |
| --- | --- | --- |
| Values | `resources/css/app.css` — `:root` / `.dark`, published by `@theme` | Complete for these screens: no new value is needed (C2 maps every literal onto an existing role). No `success` role; status messages use `Alert` instead. |
| Components | `resources/js/components/ui/*` (shadcn), `input-error.tsx`, `text-link.tsx`, `password-input.tsx`, `passkey-verify.tsx` | Everything the screens need exists, including `alert.tsx` and `card.tsx`. Nothing to add. |
| Brand | `resources/js/components/app-logo.tsx` (lettermark „RK” + `name`, from `app-shell-sidebar`) | Not used by the auth layouts; they still draw the Laravel mark. |
| Named motif | shadcn/ui „login-04 / authentication split” block — the starter already ships it as `auth-split-layout.tsx` | Unused; the app renders the centred „simple” variant. |

No `.ai/rules` directory, no `context/foundation/lessons.md`.

## Charges

### C1 — Accidental architecture: `/` is the Laravel starter page

- `routes/web.php:15` renders `resources/js/pages/welcome.tsx`: „Let's get started”, links to laravel.com, Laracasts and Laravel Cloud (`:60`, `:91`, `:117`), the Laravel 13 wordmark (`:127-380`), English „Log in” / „Register” (`:27`, `:33`). `before/dark-1280-guest-home.png`: a visitor to Root Keeper sees an advert for the framework it is built with.
- Signed in, `/` shows the same page with one button „Osoby” (`:15-20`, `before/light-1280-signed-in-home.png`) instead of taking the user into the app.
- Decision (change.md): `/` becomes the way into the login screen; `welcome.tsx` goes.

### C2 — Missing tokens: literal colours on the auth screens

- `resources/js/components/input-error.tsx:14` — `text-red-600 dark:text-red-400`. `text-destructive` is not the fix: the dark `--destructive` (`resources/css/app.css:139`, `oklch(0.396 0.141 25.723)`) is a fill colour, about 2:1 as text on `--background` `0.145`. `--destructive-foreground` (`:90`, `:140`) is the text role `ui/alert.tsx:13` already uses — about 5.8:1 in dark — so the component can switch to it with no new value.
- `resources/js/pages/auth/login.tsx:95`, `resources/js/pages/auth/verify-email.tsx:15` — status messages in `text-green-600`, the same in both themes, on a plain `div`. The repo ships `ui/alert.tsx`; no token covers „success”.
- `resources/js/components/text-link.tsx:15` — `decoration-neutral-300 … dark:decoration-neutral-500`.
- `resources/js/layouts/auth/auth-simple-layout.tsx:21` — `text-[var(--foreground)] dark:text-white`; `auth-split-layout.tsx:15-16`, `:21`, `:31` — `text-white`, `bg-zinc-900`, `text-black`.

Effect: a change of `--foreground`, `--destructive` or `--primary` leaves errors, links and the brand panel in their old colours; the dark theme is patched per component.

### C3 — Accidental architecture: the auth layout is still the starter kit's

- `resources/js/layouts/auth/auth-simple-layout.tsx:20-23` — the Laravel mark (`resources/js/components/app-logo-icon.tsx`) above every auth form; the brand text is `sr-only` and holds the page title instead of the app name. The user never sees „Root Keeper” before signing in, then lands in a shell that says „Root Keeper” with an „RK” tile.
- `resources/js/layouts/auth/auth-card-layout.tsx`, `auth-split-layout.tsx` — two unused variants; `app-logo-icon.tsx` is imported only by the three auth layouts.
- `resources/js/pages/auth/login.tsx` — no path for a forgotten password (Fortify `resetPasswords` is off, `config/fortify.php:163-174`). Deferred to its own change; this layout keeps a place for the link next to „Hasło”.

### C4 — Language: English everywhere before sign-in

- `login.tsx:21`, `:34`, `:50`, `:58`, `:69`, `:80`, `:85-87`, `:104-105`; `register.tsx:19`, `:30`, `:39`, `:48`, `:62`, `:69`, `:77`, `:86`, `:100`, `:105-107`, `:118-119`; `verify-email.tsx:12`, `:16-17`, `:26`, `:33`, `:43-45`; `confirm-password.tsx:24-26`, `:33`, `:35`, `:52`, `:63-65`; `two-factor-challenge.tsx` (headings, labels, toggle link); `resources/js/components/passkey-verify.tsx:55-56`, `:69`.
- Validation messages are already Polish (`before/dark-390-register-errors.png`: „Pole nazwa jest wymagane.” under „Name”). The user reads two languages on one form, and the attribute name „nazwa” will not match the Polish label „Imię i nazwisko” (the canvas draws „Pole imię i nazwisko jest wymagane.”); the attribute name lives in the Polish validation language file.

### C5 — Layout: the passkey block and the form do not read as one flow

- `resources/js/components/passkey-verify.tsx:63` — `my-6` around the separator, inside the layout's `gap-8` (`auth-simple-layout.tsx:14`) and the form's `gap-6` (`login.tsx:28`). `before/light-1280-login.png`: about 60 px of empty space above and below „OR CONTINUE WITH EMAIL”, so the separator floats and the page reads as two unrelated forms.
- `login.tsx:94-98` — the status message is rendered after the form, below „Sign up”; the user who was just sent here with a message reads the form first.

### C6 — Keyboard: positive `tabIndex` on the forms

- `login.tsx:41`, `:56`, `:67`, `:75`, `:86`; `register.tsx:36`, `:53`, `:66`, `:82`, `:96`, `:106` — `tabIndex={1…6}`. The browser visits these before anything with `tabIndex` 0, so on the login page the keyboard skips „Sign in with a passkey” and the brand link and reaches them only after „Sign up”. The visual order and the tab order differ.

## Canvas

https://claude.ai/artifact/H3Q5s74TyFYEEftERDRr1w — boards „A — split z panelem marki”, „B — karta na tle”, „C — hasło + formularz”; for A: „rejestracja”, „stany formularza” (error, submitting, keyboard focus), „weryfikacja e-mail”, „motyw ciemny”, „mobile”, „mobile, rejestracja z błędami, ciemny”. Tokens on the canvas are copied from `resources/css/app.css` at `3657421` (`project/rk.css` on the canvas); no new values. 

## Decisions (2026-09-30)

Variant A chosen („split z panelem marki”). The „Nie pamiętasz hasła?” link is not rendered until the password-reset change ships. Phone keeps the repo's 36 px `ui/input` / `ui/button`: the 44 px drawn on the phone boards would be a per-view override of shared components; a touch-size decision belongs to those components for every form, not to this view (deferred below).

## Deferred (visible, not in this change unless the plan takes them)

- Password reset (Fortify `resetPasswords`, mail, `forgot-password` / `reset-password` pages, tests) — its own change; decided 2026-09-30.
- Touch-size inputs and buttons on phones (44 px, as drawn on „A — mobile”) — a change to `ui/input` / `ui/button` for all forms.
- Settings pages remain English (see `app-shell-sidebar/audit.md`).
