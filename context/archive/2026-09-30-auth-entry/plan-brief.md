# Logged-out entry — variant A — Plan Brief

> Full plan: `context/changes/auth-entry/plan.md`
> Research: `context/changes/auth-entry/audit.md` (audit charges C1–C6 and decisions)

## What & Why

Someone opening Root Keeper without being signed in sees the Laravel 13 starter page at `/`, then English login forms under the Laravel logo. Inside the app, the shell already says „Root Keeper” with an „RK” tile. This change makes the login screen the front door, in Polish and in the app's own look, with nothing left from the starter kit.

## Starting Point

`/` renders `welcome.tsx` (starter page, ~40 hex literals). Every `auth/*` page uses the centred `auth-simple-layout` with the Laravel mark; two other variants sit unused. Field errors and link underlines use literal colours in components shared by every form. Signed-in users hitting `/login` are sent to `/`, which is fine today but becomes a loop once `/` redirects to `/login`.

## Desired End State

Guests opening `/` land on `/login`; signed-in users land on „Osoby”. From 1024 px, every auth page is split in two: on the left a brand panel on the sidebar surface („RK Root Keeper”, a small family-sheet motif, „Drzewo genealogiczne, w którym każda data ma źródło.”), on the right the form. On phones: brand on top, form, sentence at the bottom. All copy is Polish, errors sit on the field with a red border, status messages are an `Alert` at the top, and Tab follows the visual order.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| What `/` is for a guest | The login screen; no marketing page | A private family tree has nothing to sell to strangers; one view to maintain. | Research (change.md) |
| Look | Variant A: split with a brand panel | The panel takes the sidebar's place and surface, so signing in is continuous. | Research (canvas) |
| `/` mechanics | Redirect: guest → `/login`, signed-in → `/persons`; `guest` middleware also → `/persons` | Fortify alone owns the login page; the `guest` target breaks the `/` ↔ `/login` loop. | Plan |
| Colours | No new tokens; errors on `--destructive-foreground`, statuses via `ui/alert` | Every role already exists; `ui/alert` uses the same error role. | Research |
| Password reset | Separate change; link not shown until then | It is a feature (mail, pages, tests), not a visual change. | Research |
| Phone input height | Keep 36 px | 44 px belongs to `ui/input` / `ui/button` for every form, not to one view. | Research |
| Dead code | Delete `welcome.tsx`, `auth-simple`/`auth-card` layouts, `app-logo-icon` | Same rule as the shell change: no second, unused copy. | Plan |

## Scope

**In scope:**
- `/` redirect, `guest` redirect target, entry tests
- Split layout + brand panel for all `auth/*` pages
- Polish copy on login, register, verify e-mail, confirm password, 2FA, passkey block, password toggle, `name` validation attribute
- Error/status/focus states, tab order
- `input-error` and `text-link` read tokens (app-wide effect)
- Screenshot gate with `shots.mjs`

**Out of scope:**
- Password reset; 44 px touch sizes; English settings pages; a marketing landing page; new tokens; a screenshot-test runner

## Architecture / Approach

Contract before pixels: shared form parts read tokens → entry route and tests → layout (`auth-split-layout.tsx` rewritten, new `components/auth/auth-brand-panel.tsx` reusing `AppLogo` and `genderSurface`) → pages and states → screenshots. `auth-layout.tsx` stays the one entry point that `app.tsx` assigns to `auth/*`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Shared form parts read tokens | Errors and link underlines from roles, both themes | Changes every form in the app; checked on a signed-in form |
| 2. `/` leads into the app | Redirects, no loop, `welcome.tsx` gone, tests | The `guest` redirect target must land with the `/` redirect |
| 3. The split layout | Board A for every auth page; Laravel mark gone | Panel height at 1024–1280 px with the long register form |
| 4. Pages, copy and states | Polish copy, `Alert` statuses, `aria-invalid`, tab order | 2FA and verification need a suitable local user to check |
| 5. Screenshot gate | `after/` set vs `before/` and the boards | Verification shots create a throwaway local user |

**Prerequisites:** Sail up at `localhost:8000`, `TreeViewDemoSeeder` users, Playwright in the npx cache (as for `app-shell-sidebar`).
**Estimated effort:** ~2 sessions across 5 phases.

## Open Risks & Assumptions

- `redirectUsersTo` is assumed to be the Laravel 13 API for the `guest` target; Phase 2 confirms it with `search-docs` first.
- `input-error` in dark moves from `red-400` to `--destructive-foreground` (`oklch(0.637 0.237 25.331)`, about 5.8:1 on the background); every signed-in form picks this up.
- The login page shows a status only when Fortify sends one; with password reset off, that is rare. The `Alert` is ready for the reset change.

## Success Criteria (Summary)

- A guest opening Root Keeper sees a Polish login screen with the app's own brand, never the Laravel page.
- Errors, focus and the submitting state are visible on the field in light and dark, on desktop and phone.
- `after/` screenshots match the canvas boards for every state named in the plan.
