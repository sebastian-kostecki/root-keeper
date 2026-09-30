# App shell — variant A („bez górnej belki”) Implementation Plan

## Overview

The authenticated shell is rebuilt to variant A of the canvas https://claude.ai/artifact/EgWsEjc3AE64kvCcXpKAKN (boards „A — bez górnej belki”, „A — zwinięty, strona drzewa”, „A — stany”, „A — motyw ciemny”, „A — mobile”, „A — mobile, menu”). Named motif: the shadcn/ui sidebar block that collapses to icons, `variant="inset"` — the one the repo already ships.

The top bar goes away on desktop. The sidebar holds the brand, the collapse button, three destinations with counters and the account row. Nested pages show a link to their parent section above the title. Phones get a slim bar with a menu button. The starter kit's leftovers (Laravel name and mark, „Repository” / „Documentation” links, English account menu, an unused second shell) are removed.

Input: `audit.md` (charges C1–C7), `tokens.md` (values), `screenshots/before/` (24 shots of the current shell). The change is outside the roadmap, like the other UI passes.

## Current State Analysis

- **Layout.** `resources/js/layouts/app/app-sidebar-layout.tsx:12-18` renders `AppSidebar`, then `AppSidebarHeader` (a 64 px bar: trigger + breadcrumbs, `resources/js/components/app-sidebar-header.tsx:11-14`), then the page in a `px-6 py-6 md:px-4` wrapper.
- **Sidebar.** `resources/js/components/app-sidebar.tsx:52-76`: logo linking to `/persons` (`:59`), `NavMain` with a group label „Drzewo” above the item „Drzewo” (`resources/js/components/nav-main.tsx:17`), two footer links to the Laravel starter kit (`:39-50`), `NavUser` with the name only.
- **Brand.** `AppLogo` prints `config('app.name')`, which is „Laravel” (`config/app.php:16`, `.env.example:1`), next to the Laravel mark (`resources/js/components/app-logo-icon.tsx`). The production build already sets `VITE_APP_NAME` to „Root Keeper” (`docker-compose.prod.yml:8`, `docker/8.5/Dockerfile.prod:47`).
- **Tokens.** Eight `--sidebar-*` roles exist in both themes (`resources/css/app.css:101-108`, `:149-156`). Three fail their role (audit C1): the current-page fill, the focus ring, and the dark `--sidebar-primary-foreground`.
- **Literal colours** in `user-info.tsx:18`, `nav-footer.tsx:30`, `app-logo.tsx:11`, `app.tsx:35` (audit C2).
- **Strings.** „Settings”, „Log out” (`user-menu-content.tsx:44`, `:58`); „Sidebar”, „Displays the mobile sidebar.”, „Toggle sidebar” (`ui/sidebar.tsx:183-184`, `:270`, `:282`, `:285`).
- **Dead code.** `components/app-header.tsx` and `layouts/app/app-header-layout.tsx` are imported by nothing; they carry a second copy of the navigation.
- **Shared props.** `HandleInertiaRequests::share` (`app/Http/Middleware/HandleInertiaRequests.php:41-60`) sends `name`, `auth`, `sidebarOpen`, `personPreview` (closure) and `availableSources` (optional). Nothing about how many people or sources the tree holds.
- **Mobile.** The sidebar is a sheet (`ui/sidebar.tsx:179-201`). After choosing an item the page changes and the sheet stays open over it (checked at 390 px: `/persons` → tap „Źródła” → URL is `/sources`, sheet still mounted).

## Desired End State

**Desktop, sidebar expanded (board „A — bez górnej belki”)**

- No top bar. The content card starts with the page's own heading, 24 px from the card's top.
- Sidebar header: lettermark tile „RK” + the app name „Root Keeper”, one link to `/tree`; at its right the collapse button.
- Navigation, no group label: Drzewo, Osoby, Źródła. Osoby and Źródła show the tree's counts at the right (e.g. 41, 18): text colour, regular weight, 12 px, tabular figures. A count of 0 is not shown.
- Icons: Drzewo `Network`, Osoby `Users`, Źródła `ScrollText`.
- Footer: only the account row — avatar with initials, name, e-mail under it, up/down chevrons.
- Nothing links to the Laravel starter kit.

**Desktop, sidebar collapsed (board „A — zwinięty, strona drzewa”)**

- Icon rail: lettermark, the expand button under it, three icons, avatar at the bottom.
- A tooltip on each destination gives its name and, when there is a count, the count: „Źródła · 18”.
- The account menu opens to the right of the rail.

**Page header rule (boards „A — zwinięty”, „A — stany”)**

| Page | Breadcrumbs passed by the page | Shown above the `h1` |
|---|---|---|
| `/persons`, `/sources`, `/tree` without a proband, settings pages | one item | nothing |
| `/tree/{person}`, `/tree` with a proband | Drzewo, person name | link „Drzewo” |
| `/sources/{source}` | Źródła, source name | link „Źródła” |

The trail shows every item except the last one (the current page), as links, separated by chevrons when there is more than one. The `nav` has the accessible name „Ścieżka”.

**Account menu (board „A — stany”)**: avatar, name and e-mail; „Ustawienia”; „Wyloguj”.

**Phone (boards „A — mobile”, „A — mobile, menu”)**

- A 56 px bar on top, visible only below `md`: a 44 px menu button („Otwórz menu”) and the brand link.
- The sheet: brand, a 44 px close button („Zamknij menu”), 44 px rows with 16 px text and 20 px icons, counters, the account row.
- Choosing a destination closes the sheet.

**Tokens**: the five values of `tokens.md` are in `resources/css/app.css` with a line naming their source. No literal colour is left in the shell components or in the progress bar.

**Server**: every full Inertia response for a signed-in user carries `navCounts: { people, sources }` for the user's own tree; guests get `null`.

**Verification**: Pest covers `navCounts`; `screenshots/after/` plus `screenshots/verdict.md` record the shell in light and dark at 1280 and 390 px against the six A boards.

### Key Discoveries:

- Every part the design needs is already exported by `resources/js/components/ui/sidebar.tsx` and unused: `SidebarMenuBadge` (`:573-593`, hidden when collapsed, carries `font-medium`), `SidebarTrigger` (`:249-273`). No shadcn component is added.
- `SidebarTrigger` always draws the panel icon (`ui/sidebar.tsx:269`); the phone bar needs a menu icon, so it uses `useSidebar().toggleSidebar` with a plain `Button`.
- The mobile sheet hides the sheet's own close button (`ui/sidebar.tsx:190`, `[&>button]:hidden`); the visible close button is ours and calls `setOpenMobile(false)`.
- The `nav` in `ui/breadcrumb.tsx:8` spreads props after its `aria-label`, so the Polish name is passed from `breadcrumbs.tsx` without editing the primitive.
- Inertia writes the progress colour straight into CSS (`background: ${color}`, `node_modules/@inertiajs/core/dist/index.js:4962`), so `var(--primary)` works.
- Shared closures are evaluated only when the response includes them. The partial reloads in the app (`only: ['filters']`, `['personPreview']`, `['availableSources']`, the relation candidates) do not run the count queries; every mutation answers with a redirect and a full response, which refreshes the counts.
- Every user has a tree from registration (`app/Actions/Fortify/CreateNewUser.php:35`); `availableSources` already relies on it (`HandleInertiaRequests.php:55-57`).
- `UserInfo` is used by the sidebar row and by the account menu (`nav-user.tsx:37`, `user-menu-content.tsx:31`), on two different backgrounds.
- The seeder has an empty-tree user, `tree-demo-empty@example.com`; Fortify throttles logins at five per minute, so `shots.mjs` logs in once per user and reuses the cookies.
- Six assertions in `tests/Feature/Auth/` expect the post-login redirect to `persons.index`; this plan does not touch that target.

## What We're NOT Doing

- **No change to the post-login target.** Login, registration and e-mail verification still land on `/persons`. Only the logo link changes, to `/tree`.
- **No translation of settings.** `layouts/settings/layout.tsx` and `pages/settings/*.tsx` stay English; a separate change.
- **No new logo.** „RK” is a placeholder lettermark in the shell. `AppLogoIcon` (the Laravel mark) stays in the three auth layouts; logged-out views are a separate change.
- **No variant B or C features:** no person search in the sidebar, no proband line under „Drzewo”, no page title in a top bar, no dark sidebar in the light theme.
- **No theme switch in the account menu.** Appearance stays on its settings page.
- **No `SidebarRail`.** Collapse is the button and Ctrl/Cmd+B.
- **No change to page paddings** (`px-6 py-6 md:px-4`) or to any page's own heading.
- **No retuning of `source-viewer.tsx:46`** (`h-[calc(100vh-12rem)]`); the gate records how the source page looks with 64 px less chrome.
- **No dependency change.** `components/ui/navigation-menu.tsx` and its Radix package stay, although `app-header.tsx` was their only user.
- **No change to production configuration.** `APP_NAME` on the server and in the local `.env` is set by hand.
- No in-repo screenshot test and no Playwright dependency; the gate stays a per-change script.
- No roadmap entry.

## Implementation Approach

**Tokens first.** Phase 1 changes only values and removes literals, so the old shell already shows a visible current item and focus ring. The view phases then reference roles, never colours.

**Contract before the view.** Phase 2 adds the counts and the app name, with tests, so the sidebar consumes a stable prop.

**One view.** Phase 3 rebuilds the shell's default state at 1280 px and deletes what the new layout no longer uses. Phase 4 covers the named states: collapsed, hover, current page, keyboard focus, open account menu, phone sheet, empty tree. `disabled` and `error` do not occur in the shell; `loading` is the navigation progress bar, recoloured in Phase 1.

**Gate.** Phase 5 re-shoots the set of `screenshots/before/` plus the states the new shell adds and writes the verdict against the boards.

Pages are not edited. They keep passing `breadcrumbs` through `setLayoutProps` or `Page.layout`; only what the layout does with them changes.

## Critical Implementation Details

- **Counter visibility.** `SidebarMenuBadge` is positioned absolutely and hidden in icon mode; it needs `font-normal` to match the board. When the count is 0 the badge is not rendered at all and the tooltip shows the name alone.
- **Trigger position.** Expanded: in the header row, right of the brand. Collapsed: on its own row under the lettermark. On phones it is replaced by the close button. One header component has to serve the three cases; the phone case is decided by `useSidebar().isMobile`, the collapsed case by the `group-data-[collapsible=icon]` classes the primitive already uses.
- **Closing the sheet.** `openMobile` lives in `SidebarProvider`, which persists across Inertia visits. The sheet must close when a destination or the brand is chosen; opening the account menu inside the sheet must not close it.
- **`UserInfo` on two grounds.** In the sidebar row the e-mail is `text-sidebar-foreground/70` and the avatar fallback is `bg-sidebar-accent text-sidebar-accent-foreground`; on hover and while the menu is open the row itself takes the accent fill, so the fallback switches to `bg-background`. In the account menu the e-mail stays `text-muted-foreground` and the fallback is `bg-muted text-foreground`.

## Phase 1: Tokens and literal colours

### Overview

The five sidebar values of `tokens.md` go into the token source, and the two literals that survive the rebuild (avatar fallback, progress bar) move to tokens. The old shell stays in place and becomes readable: visible current item, visible focus ring.

### Changes Required:

#### 1. Sidebar token values

**File**: `resources/css/app.css`

**Intent**: Give the current-page fill, the focus ring and the dark on-primary text values that do their job (audit C1).

**Contract**: `:root` — `--sidebar-accent: oklch(0.945 0 0)` (`:105`), `--sidebar-ring: oklch(0.556 0 0)` (`:108`). `.dark` — `--sidebar-primary-foreground: oklch(0.205 0 0)` (`:152`), `--sidebar-accent: oklch(0.29 0 0)` (`:153`), `--sidebar-ring: oklch(0.556 0 0)` (`:156`). One comment line above the changed light values names the source, in the form already used for the gender tints (`:114`): `context/changes/app-shell-sidebar/tokens.md` and the canvas URL. No token is added, none is renamed, `@theme` is untouched.

#### 2. Avatar fallback

**File**: `resources/js/components/user-info.tsx`

**Intent**: Replace `bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white` (`:18`) with token roles (audit C2).

**Contract**: Default fallback is `bg-muted text-foreground`. No `dark:` colour class remains in the file.

#### 3. Navigation progress bar

**File**: `resources/js/app.tsx`

**Intent**: The Inertia progress bar takes its colour from the theme instead of `#4B5563` (`:35`).

**Contract**: `progress.color` is `'var(--primary)'`.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- No literal colour left in the two files: `grep -nE "neutral-|text-(white|black)|#[0-9A-Fa-f]{6}" resources/js/components/user-info.tsx resources/js/app.tsx` prints nothing
- The five values are in place: `grep -nE "sidebar-(accent|ring|primary-foreground):" resources/css/app.css` shows `0.945`, `0.556` for light and `0.205`, `0.29`, `0.556` for dark

#### Manual Verification:

- At 1280 px, light and dark: on `/persons` the item „Osoby” is clearly filled, and Tab onto „Źródła” shows a ring that is easy to see
- The progress bar during a slow navigation is dark in the light theme and light in the dark theme
- `/persons`, `/sources` and `/tree` content looks as before (only `--sidebar-*` values changed)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Phase 2: Server contract — counters and app name

### Overview

The shell gets the two numbers it shows and the name it prints.

### Changes Required:

#### 1. Shared counts

**File**: `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Send the number of people and sources of the signed-in user's tree with every full response, so the sidebar can show them on any page.

**Contract**: New shared key `navCounts`, a closure returning `array{people: int, sources: int}` for a signed-in user and `null` for a guest. Both numbers are counted through `$request->user()->tree` (`people()`, `sources()`), never through a global query. The PHPDoc of `share` keeps its array shape style.

#### 2. Shared prop type

**File**: `resources/js/types/global.d.ts`

**Intent**: Type the new prop for pages and components.

**Contract**: `sharedPageProps.navCounts: { people: number; sources: number } | null`.

#### 3. Feature test

**File**: `tests/Feature/AppShellTest.php` (new, `vendor/bin/sail artisan make:test --pest AppShellTest`)

**Intent**: Pin the contract and the tree scoping. Read the `testing-best-practices` skill before writing.

**Contract**: Three tests, each on a real page response (`/persons`): the counts equal the user's own people and sources; another user's people and sources are not counted; a guest response (`/`) has `navCounts` equal to `null`. Models come from factories.

#### 4. App name

**Files**: `config/app.php`, `.env.example`

**Intent**: The app calls itself „Root Keeper” wherever `APP_NAME` is not set, and new checkouts start with that name.

**Contract**: `config/app.php:16` default is `'Root Keeper'`; `.env.example:1` is `APP_NAME="Root Keeper"` (quoted, it has a space). The local `.env` and the production environment are not edited by this plan.

### Success Criteria:

#### Automated Verification:

- New tests pass: `vendor/bin/sail artisan test --compact tests/Feature/AppShellTest.php`
- Auth redirects are untouched: `vendor/bin/sail artisan test --compact tests/Feature/Auth`
- PHP style is applied: `vendor/bin/sail bin pint --dirty --format agent`
- Static analysis passes: `vendor/bin/sail bin phpstan analyse`
- Types pass: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Local `.env` has `APP_NAME="Root Keeper"` and the old sidebar prints „Root Keeper”
- The production `APP_NAME` is noted as a deployment step for the human

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: The shell — sidebar, page header, account menu

### Overview

The default state at 1280 px, sidebar expanded, plus the phone bar, the Polish strings and the removal of what the new layout does not use. States are Phase 4.

### Changes Required:

#### 1. Brand

**File**: `resources/js/components/app-logo.tsx`

**Intent**: Replace the Laravel mark with the placeholder lettermark and drop the colour workaround (audit C2, C3).

**Contract**: A 32 px tile `bg-sidebar-primary text-sidebar-primary-foreground` with the text „RK”, followed by the shared `name`. No `text-white` / `dark:text-black`. `AppLogoIcon` is no longer imported here; the file `app-logo-icon.tsx` stays for the auth layouts.

#### 2. Sidebar composition

**File**: `resources/js/components/app-sidebar.tsx`

**Intent**: Header with brand and collapse button, three destinations with counts, account row; no starter links (audit C3).

**Contract**:
- The brand link goes to `treeIndex()`.
- `SidebarTrigger` sits in the header row at the right of the brand.
- `mainNavItems` is built inside the component from `usePage().props.navCounts`: Drzewo (no count), Osoby (`people`), Źródła (`sources`, icon `ScrollText`).
- `footerNavItems` and `NavFooter` are gone; `SidebarFooter` holds `NavUser` only.
- `variant="inset"` and `collapsible="icon"` stay.

#### 3. Navigation item type

**File**: `resources/js/types/navigation.ts`

**Intent**: Let a destination carry its counter.

**Contract**: `NavItem.count?: number`.

#### 4. Navigation list

**File**: `resources/js/components/nav-main.tsx`

**Intent**: Remove the duplicated group label and show the counters (audit C3).

**Contract**: No `SidebarGroupLabel`. For an item with `count > 0`, a `SidebarMenuBadge` with `font-normal` shows the number. The `nav` landmark keeps an accessible name („Nawigacja główna”).

#### 5. Account row and menu

**Files**: `resources/js/components/nav-user.tsx`, `resources/js/components/user-menu-content.tsx`, `resources/js/components/user-info.tsx`

**Intent**: Show who is signed in, in Polish, on token colours (audit C5, C6).

**Contract**:
- The row shows name and e-mail; colours as in „`UserInfo` on two grounds” above.
- Menu items: „Ustawienia”, „Wyloguj”. `data-test` attributes are kept.
- The menu opens on the `right` when the sidebar is collapsed (was `left`), `bottom` otherwise.

#### 6. Layout without the top bar

**Files**: `resources/js/layouts/app/app-sidebar-layout.tsx`, `resources/js/components/app-mobile-header.tsx` (new), `resources/js/components/app-sidebar-header.tsx` (deleted)

**Intent**: Remove the bar that repeats the page title and give phones a way to open the menu (audit C4).

**Contract**:
- `AppMobileHeader`: `md:hidden`, 56 px, bottom border `border-border`; a ghost icon `Button` of 44 px with the `Menu` icon and the name „Otwórz menu”, calling `toggleSidebar`; then the brand link to `treeIndex()`.
- The layout renders `AppMobileHeader`, then the content wrapper (unchanged classes) holding `Breadcrumbs` and the page.
- `AppSidebarHeader` is deleted.

#### 7. Parent trail above the title

**File**: `resources/js/components/breadcrumbs.tsx`

**Intent**: Show where the page sits without repeating its title.

**Contract**: Renders every breadcrumb except the last as a link, with separators between them; renders nothing when there is at most one item. The `Breadcrumb` gets `aria-label="Ścieżka"` and a small bottom margin so the trail sits 2 px above the page heading. No `BreadcrumbPage` is rendered.

#### 8. Polish names in the sidebar primitive

**File**: `resources/js/components/ui/sidebar.tsx`

**Intent**: Screen-reader names of the sheet and the trigger in Polish (audit C5).

**Contract**: Sheet title „Menu”, description „Nawigacja aplikacji” (`:183-184`). The trigger's hidden text is „Zwiń panel boczny” when expanded and „Rozwiń panel boczny” when collapsed (`:270`). The rail's `aria-label` and `title` become „Przełącz panel boczny” (`:282`, `:285`). No class or behaviour change in this file in this phase.

#### 9. Remove the unused shell

**Files**: `resources/js/components/app-header.tsx`, `resources/js/layouts/app/app-header-layout.tsx`, `resources/js/components/nav-footer.tsx` (all deleted); `resources/js/components/app-shell.tsx`, `resources/js/components/app-content.tsx`, `resources/js/types/ui.ts`

**Intent**: Delete the second navigation and the footer-link component (decision: remove in this change).

**Contract**: `AppVariant` is removed from `types/ui.ts`. `AppShell` and `AppContent` lose the `variant` prop and keep only the sidebar branch; `app-sidebar-layout.tsx` stops passing it. `grep -rn "app-header\|nav-footer\|AppVariant" resources/js` finds nothing.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Production build passes: `vendor/bin/sail npm run build`
- JS tests still pass: `vendor/bin/sail npm run test:js`
- Deleted pieces are unreferenced: `grep -rnE "app-header|app-sidebar-header|nav-footer|AppVariant" resources/js` prints nothing
- No literal colour and no starter link in the shell: `grep -rnE "neutral-|text-(white|black)|laravel\.com|react-starter-kit" resources/js/components/app-*.tsx resources/js/components/nav-*.tsx resources/js/components/user-*.tsx` prints nothing
- PHP suite is unaffected: `vendor/bin/sail artisan test --compact tests/Feature/AppShellTest.php tests/Feature/Auth`

#### Manual Verification:

- 1280 px, light: `/persons` matches board „A — bez górnej belki” — brand „RK Root Keeper”, collapse button, Drzewo / Osoby 41 / Źródła 18, account row with e-mail, no top bar, no footer links
- `/tree` shows the link „Drzewo” above „Jan Kowalski”; a source page shows „Źródła” above the source name; `/persons`, `/sources` and `/settings/profile` show no trail
- The brand link opens `/tree`; the account menu reads „Ustawienia” and „Wyloguj” and both work
- 390 px: the bar with the menu button and brand is visible, the button opens the sheet, the page has no sideways scroll
- Adding a person raises the „Osoby” counter without a manual refresh

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: States

### Overview

Every state the shell has, in both themes: collapsed with tooltips, hover, current page, keyboard focus, open account menu, phone sheet, empty tree.

### Changes Required:

#### 1. Collapsed header and tooltips

**Files**: `resources/js/components/app-sidebar.tsx`, `resources/js/components/nav-main.tsx`

**Intent**: The rail of board „A — zwinięty”: lettermark, expand button under it, icons with tooltips that include the count.

**Contract**: In icon mode the header stacks the lettermark and the trigger, each 32 px, centred; the app name is hidden. Tooltip text is the title, or `„{title} · {count}”` when `count > 0`. The brand link keeps an accessible name when only the tile is visible.

#### 2. Phone sheet

**Files**: `resources/js/components/app-sidebar.tsx`, `resources/js/components/nav-main.tsx`, `resources/js/components/nav-user.tsx`

**Intent**: Touch-sized rows, a visible way to close, and a sheet that gets out of the way after a choice (audit C7).

**Contract**:
- Inside the sheet (`data-mobile="true"`), destination rows are 44 px with 16 px text and 20 px icons; the account row is 56 px.
- The header shows a 44 px ghost button with the `X` icon and the name „Zamknij menu” instead of the collapse trigger.
- Activating a destination or the brand link calls `setOpenMobile(false)`. Opening the account menu does not close the sheet; choosing „Ustawienia” does.

#### 3. Focus and current page

**Files**: `resources/js/components/app-sidebar.tsx`, `resources/js/components/app-mobile-header.tsx`

**Intent**: Every control of the shell shows focus with the sidebar ring, and the current destination is announced.

**Contract**: Tab order in the expanded sidebar is brand, collapse button, Drzewo, Osoby, Źródła, account row. The brand link and the two header buttons show `ring-sidebar-ring` on `focus-visible` (the menu buttons already do). The current destination's link has `aria-current="page"`.

#### 4. Empty tree

**File**: none beyond Phase 3 (behaviour check)

**Intent**: A new account sees the same shell without numbers.

**Contract**: With `navCounts` `{ people: 0, sources: 0 }` no badge is rendered and tooltips show names only (board „A — stany”, „Puste drzewo — bez liczników”).

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- Types pass: `vendor/bin/sail npm run types:check`
- Production build passes: `vendor/bin/sail npm run build`

#### Manual Verification:

- 1280 px collapsed, light and dark: rail as on board „A — zwinięty”; hovering „Źródła” shows „Źródła · 18”; the account menu opens to the right; Ctrl/Cmd+B and the button both toggle, and the state survives a reload
- Keyboard only: Tab walks brand, collapse button, three destinations, account row; each shows a visible ring in light and dark; Enter on the account row opens the menu and Escape returns focus to it
- 390 px: rows are 44 px, the close button closes the sheet, tapping „Źródła” opens `/sources` with the sheet closed, „Ustawienia” from the account menu opens settings with the sheet closed
- Signed in as `tree-demo-empty@example.com`: no counters in the sidebar or in tooltips
- A screen reader (or the accessibility tree) names the controls „Zwiń panel boczny” / „Rozwiń panel boczny”, „Otwórz menu”, „Zamknij menu”, and marks the current destination

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Screenshot gate

### Overview

The kitchen sink is the demo tree (`TreeViewDemoSeeder`). The script re-shoots the „before” set and the states the new shell adds; the verdict compares them with the six A boards.

### Changes Required:

#### 1. Shot script

**File**: `context/changes/app-shell-sidebar/screenshots/shots.mjs`

**Intent**: Cover every state of Phase 4 and the page header rule, in both themes.

**Contract**: Keeps the existing names, so `before/` and `after/` pair up, and adds per theme: at 1280 — collapsed on `/tree`, a source page (first source of the demo tree), expanded for the empty-tree user; at 390 — `/tree`, the sheet for the empty-tree user. The empty-tree user gets its own single login (two logins per run in total). Selectors that referred to the old header (`[data-sidebar="trigger"]` at 390 px) point at the new menu button. Output goes to `screenshots/after/`.

#### 2. Verdict

**File**: `context/changes/app-shell-sidebar/screenshots/verdict.md` (new)

**Intent**: Record what was compared, what matches and what differs, in the form of `context/archive/2026-09-30-sources-list-views/screenshots/verdict.md`.

**Contract**: A table with one row per state and its shots; a section of differences from the boards, each either fixed or left with a reason; the deferred items (settings in English, auth layouts with the Laravel mark, production `APP_NAME`); a note on which shots were opened by eye. If the boards cannot be read back from the artifact, the verdict says so and compares against this plan's Desired End State.

### Success Criteria:

#### Automated Verification:

- The script runs to the end: `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node context/changes/app-shell-sidebar/screenshots/shots.mjs context/changes/app-shell-sidebar/screenshots/after`
- Every „before” shot has an „after” twin: each file name of `screenshots/before/` exists in `screenshots/after/`
- Format, lint and types still pass: `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`
- The PHP tests of the change pass: `vendor/bin/sail artisan test --compact tests/Feature/AppShellTest.php tests/Feature/Auth`

#### Manual Verification:

- The human compares `after/` with the six A boards and accepts or lists differences
- Text contrast holds in both themes: counters, e-mail line, parent link, tooltip
- `/sources/{id}` with the viewer is acceptable with 64 px less chrome above it
- The human runs the whole suite: `vendor/bin/sail artisan test --compact`
- `/10x-impl-review app-shell-sidebar` is run and its UI findings are triaged

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful. This is the last phase.

---

## Testing Strategy

### Unit Tests:

- None added. The only new logic in the browser is „all breadcrumbs but the last” and „show the count when above zero”; both are checked by eye in the gate.

### Integration Tests:

- `tests/Feature/AppShellTest.php`: `navCounts` for the user's own tree; another user's records not counted; `null` for a guest.
- `tests/Feature/Auth/*` run unchanged as the guard that the post-login target did not move.

### Manual Testing Steps:

1. Sign in as `tree-demo@example.com` at 1280 px; walk `/tree`, `/persons`, `/sources`, a source page, `/settings/profile`; check brand, counters, parent link, no top bar.
2. Collapse the sidebar; check tooltips, the account menu side, and that the state survives a reload.
3. Go through the shell with the keyboard only, in light and dark.
4. At 390 px open the sheet, choose each destination, open the account menu, close with the button and by tapping outside.
5. Sign in as `tree-demo-empty@example.com`; check there are no counters.
6. Add a person and a source; check both counters rise.

## Performance Considerations

Two `COUNT` queries per full page response for a signed-in user, both through indexed `tree_id` columns. Partial reloads that name their props do not run them.

## Migration Notes

No data migration. After deploy the human sets `APP_NAME="Root Keeper"` on the server (and locally); until then the sidebar prints the old value from the environment. The `sidebar_state` cookie keeps working.

## References

- Audit: `context/changes/app-shell-sidebar/audit.md`
- Token values: `context/changes/app-shell-sidebar/tokens.md`
- Design canvas: https://claude.ai/artifact/EgWsEjc3AE64kvCcXpKAKN
- „Before” shots and script: `context/changes/app-shell-sidebar/screenshots/before/`, `screenshots/shots.mjs`
- Gate form to follow: `context/archive/2026-09-30-sources-list-views/screenshots/verdict.md`
- Sidebar primitive: `resources/js/components/ui/sidebar.tsx:150-247`, `:249-273`, `:469-539`, `:573-593`
- Shared props pattern: `app/Http/Middleware/HandleInertiaRequests.php:41-60`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tokens and literal colours

#### Automated

- [x] 1.1 Format and lint pass: `vendor/bin/sail npm run check` — fb53d41
- [x] 1.2 Types pass: `vendor/bin/sail npm run types:check` — fb53d41
- [x] 1.3 No literal colour left in the two files: `grep -nE "neutral-|text-(white|black)|#[0-9A-Fa-f]{6}" resources/js/components/user-info.tsx resources/js/app.tsx` prints nothing — fb53d41
- [x] 1.4 The five values are in place: `grep -nE "sidebar-(accent|ring|primary-foreground):" resources/css/app.css` shows `0.945`, `0.556` for light and `0.205`, `0.29`, `0.556` for dark — fb53d41

#### Manual

- [x] 1.5 At 1280 px, light and dark: on `/persons` the item „Osoby” is clearly filled, and Tab onto „Źródła” shows a ring that is easy to see — fb53d41
- [x] 1.6 The progress bar during a slow navigation is dark in the light theme and light in the dark theme — fb53d41
- [x] 1.7 `/persons`, `/sources` and `/tree` content looks as before (only `--sidebar-*` values changed) — fb53d41

### Phase 2: Server contract — counters and app name

#### Automated

- [x] 2.1 New tests pass: `vendor/bin/sail artisan test --compact tests/Feature/AppShellTest.php` — 2fdccec
- [x] 2.2 Auth redirects are untouched: `vendor/bin/sail artisan test --compact tests/Feature/Auth` — 2fdccec
- [x] 2.3 PHP style is applied: `vendor/bin/sail bin pint --dirty --format agent` — 2fdccec
- [x] 2.4 Static analysis passes: `vendor/bin/sail bin phpstan analyse` — 2fdccec
- [x] 2.5 Types pass: `vendor/bin/sail npm run types:check` — 2fdccec

#### Manual

- [x] 2.6 Local `.env` has `APP_NAME="Root Keeper"` and the old sidebar prints „Root Keeper” — 2fdccec
- [x] 2.7 The production `APP_NAME` is noted as a deployment step for the human — 2fdccec

### Phase 3: The shell — sidebar, page header, account menu

#### Automated

- [x] 3.1 Format and lint pass: `vendor/bin/sail npm run check` — 8d4d2f0
- [x] 3.2 Types pass: `vendor/bin/sail npm run types:check` — 8d4d2f0
- [x] 3.3 Production build passes: `vendor/bin/sail npm run build` — 8d4d2f0
- [x] 3.4 JS tests still pass: `vendor/bin/sail npm run test:js` — 8d4d2f0
- [x] 3.5 Deleted pieces are unreferenced: `grep -rnE "app-header|app-sidebar-header|nav-footer|AppVariant" resources/js` prints nothing — 8d4d2f0
- [x] 3.6 No literal colour and no starter link in the shell: `grep -rnE "neutral-|text-(white|black)|laravel\.com|react-starter-kit" resources/js/components/app-*.tsx resources/js/components/nav-*.tsx resources/js/components/user-*.tsx` prints nothing — 8d4d2f0
- [x] 3.7 PHP suite is unaffected: `vendor/bin/sail artisan test --compact tests/Feature/AppShellTest.php tests/Feature/Auth` — 8d4d2f0

#### Manual

- [x] 3.8 1280 px, light: `/persons` matches board „A — bez górnej belki” — brand „RK Root Keeper”, collapse button, Drzewo / Osoby 41 / Źródła 18, account row with e-mail, no top bar, no footer links — 8d4d2f0
- [x] 3.9 `/tree` shows the link „Drzewo” above „Jan Kowalski”; a source page shows „Źródła” above the source name; `/persons`, `/sources` and `/settings/profile` show no trail — 8d4d2f0
- [x] 3.10 The brand link opens `/tree`; the account menu reads „Ustawienia” and „Wyloguj” and both work — 8d4d2f0
- [x] 3.11 390 px: the bar with the menu button and brand is visible, the button opens the sheet, the page has no sideways scroll — 8d4d2f0
- [x] 3.12 Adding a person raises the „Osoby” counter without a manual refresh — 8d4d2f0

### Phase 4: States

#### Automated

- [x] 4.1 Format and lint pass: `vendor/bin/sail npm run check` — 3657421
- [x] 4.2 Types pass: `vendor/bin/sail npm run types:check` — 3657421
- [x] 4.3 Production build passes: `vendor/bin/sail npm run build` — 3657421

#### Manual

- [x] 4.4 1280 px collapsed, light and dark: rail as on board „A — zwinięty”; hovering „Źródła” shows „Źródła · 18”; the account menu opens to the right; Ctrl/Cmd+B and the button both toggle, and the state survives a reload — 3657421
- [x] 4.5 Keyboard only: Tab walks brand, collapse button, three destinations, account row; each shows a visible ring in light and dark; Enter on the account row opens the menu and Escape returns focus to it — 3657421
- [x] 4.6 390 px: rows are 44 px, the close button closes the sheet, tapping „Źródła” opens `/sources` with the sheet closed, „Ustawienia” from the account menu opens settings with the sheet closed — 3657421
- [x] 4.7 Signed in as `tree-demo-empty@example.com`: no counters in the sidebar or in tooltips — 3657421
- [x] 4.8 A screen reader (or the accessibility tree) names the controls „Zwiń panel boczny” / „Rozwiń panel boczny”, „Otwórz menu”, „Zamknij menu”, and marks the current destination — 3657421

### Phase 5: Screenshot gate

#### Automated

- [x] 5.1 The script runs to the end: `LD_LIBRARY_PATH=/tmp/chromelibs/x/usr/lib/x86_64-linux-gnu node context/changes/app-shell-sidebar/screenshots/shots.mjs context/changes/app-shell-sidebar/screenshots/after`
- [x] 5.2 Every „before” shot has an „after” twin: each file name of `screenshots/before/` exists in `screenshots/after/`
- [x] 5.3 Format, lint and types still pass: `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`
- [x] 5.4 The PHP tests of the change pass: `vendor/bin/sail artisan test --compact tests/Feature/AppShellTest.php tests/Feature/Auth`

#### Manual

- [ ] 5.5 The human compares `after/` with the six A boards and accepts or lists differences
- [ ] 5.6 Text contrast holds in both themes: counters, e-mail line, parent link, tooltip
- [ ] 5.7 `/sources/{id}` with the viewer is acceptable with 64 px less chrome above it
- [ ] 5.8 The human runs the whole suite: `vendor/bin/sail artisan test --compact`
- [ ] 5.9 `/10x-impl-review app-shell-sidebar` is run and its UI findings are triaged
