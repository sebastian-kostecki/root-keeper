# Audit — authenticated app shell (sidebar, header, user menu)

Audit date: 2026-09-30. View: `resources/js/layouts/app/app-sidebar-layout.tsx` + `resources/js/components/{app-sidebar,app-sidebar-header,nav-main,nav-footer,nav-user,user-menu-content,user-info,app-logo,breadcrumbs}.tsx`.
Design canvas: https://claude.ai/artifact/EgWsEjc3AE64kvCcXpKAKN (boards „A — bez górnej belki”, „B — panel roboczy”, „C — szyna”, and for A: „zwinięty, strona drzewa”, „stany”, „motyw ciemny”, „mobile”, „mobile, menu”). Variant A was chosen on 2026-09-30 (see Decisions below).

Evidence "before": `screenshots/before/` (24 PNGs, `shots.mjs before`, seeder `TreeViewDemoSeeder`, HEAD `2ac431b`): light and dark; 1280 expanded on /tree, /persons, /sources, /settings/profile, user menu, keyboard focus, collapsed, tooltip, collapsed user menu; 390 closed, sheet, sheet user menu.

## Design-system contract as found

| Half | Where | State |
| --- | --- | --- |
| Values | `resources/css/app.css` — `:root` / `.dark`, published by `@theme` | Eight `--sidebar-*` roles exist in both themes. Three of them carry values that do not do their job (C1). |
| Components | `resources/js/components/ui/sidebar.tsx` (shadcn sidebar) | Complete: `SidebarMenuBadge`, `SidebarMenuAction`, `SidebarRail`, `SidebarInput`, `SidebarSeparator` are exported and unused. Nothing new has to be added for variant A or B. |
| Named motif | shadcn/ui sidebar, „collapses to icons” block with `variant="inset"` | The starter's shell is this block; the change extends it, no second system. |

No `.ai/rules` directory, no `context/foundation/lessons.md`.

## Charges

### C1 — Missing tokens: three sidebar roles have values that fail their role

- `resources/css/app.css:105` — `--sidebar-accent: oklch(0.97 0 0)` on `--sidebar: oklch(0.985 0 0)`. The current-page item differs from the panel by 1.5% lightness; in `before/light-1280-expanded-persons.png` the user cannot tell at a glance which section is open.
- `resources/css/app.css:108` — `--sidebar-ring: oklch(0.87 0 0)` (about 1.4:1 on the panel; WCAG 2.2 SC 1.4.11 asks 3:1). `before/light-1280-expanded-focus-nav.png`: keyboard focus on „Źródła” is a pale outline. Dark (`:156`, `0.439` on `0.205`) is about 2.3:1.
- `resources/css/app.css:152` — dark `--sidebar-primary-foreground` equals `--sidebar-primary` (`0.985`). `resources/js/components/app-logo.tsx:11` works around it with `text-white dark:text-black`, so the token is unusable for anything else placed on `bg-sidebar-primary`.

### C2 — Missing tokens: literal colours inside shell components

- `resources/js/components/nav-footer.tsx:30` — `text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100`.
- `resources/js/components/user-info.tsx:18` — `bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white` on the avatar fallback.
- `resources/js/components/app-logo.tsx:11` — `text-white dark:text-black`.
- `resources/js/app.tsx:35` — Inertia progress bar `#4B5563`.

Effect: a change of the sidebar or primary tokens leaves the avatar, the footer links, the logo and the loading bar in their old colours; dark mode is patched per component instead of at the token layer.

### C3 — Accidental architecture: the shell is still the starter kit's

- `.env.example:1` — `APP_NAME=Laravel`; `resources/js/components/app-logo-icon.tsx:5-10` is the Laravel mark. The user sees „Laravel” as the name of their genealogy app.
- `resources/js/components/app-sidebar.tsx:39-50` — footer links „Repository” and „Documentation” lead to the Laravel starter kit on GitHub and laravel.com.
- `resources/js/components/nav-main.tsx:17` — group label „Drzewo” directly above the item „Drzewo” (`app-sidebar.tsx:23`).
- `resources/js/components/app-sidebar.tsx:59` — the logo links to `/persons` (also the post-login target, `config/fortify.php:76`) while the first item of the menu is „Drzewo”.
- `resources/js/components/app-header.tsx`, `resources/js/layouts/app/app-header-layout.tsx` — a second, unused shell with its own copy of the navigation (`app-header.tsx:44-58`); nothing imports the layout.

### C4 — Accidental architecture: the top bar repeats the page title

- `resources/js/components/app-sidebar-header.tsx:11-14` — a 64 px bar (48 px collapsed) holding the trigger and the breadcrumbs. On section pages the only crumb equals the heading below it (`resources/js/pages/persons/index.tsx:33-38` vs `:133-136`); on nested pages the last crumb equals the `h1` (`resources/js/pages/tree/show.tsx:34-44` vs `:82`). The user reads the same word twice and loses 64 px of height on every page, most visibly on the tree.

### C5 — Missing shared component use / language: English strings in a Polish UI

- `resources/js/components/user-menu-content.tsx:44`, `:58` — „Settings”, „Log out”.
- `resources/js/components/ui/sidebar.tsx:183-184`, `:270`, `:282`, `:285` — „Sidebar”, „Displays the mobile sidebar.”, „Toggle sidebar” (screen-reader names of the sheet and the trigger).
- `resources/js/components/ui/breadcrumb.tsx:8` — `aria-label="breadcrumb"`.
- `resources/js/layouts/settings/layout.tsx:15`, `:20`, `:25`, `:37-38` — „Profile”, „Security”, „Appearance”, „Settings”, „Manage your profile and account settings”.

Effect: a screen-reader user hears English control names; every user meets English the moment they open the account menu.

### C6 — Minor: user menu side when collapsed

- `resources/js/components/nav-user.tsx:44-50` — `side="left"` for a sidebar that is on the left; Radix flips it, the menu lands over the content (`before/light-1280-collapsed-user-menu.png`). Should be `right`.

### C7 — Accidental architecture: the phone menu stays open after a choice

- `resources/js/components/ui/sidebar.tsx:68`, `:179-201` — `openMobile` lives in `SidebarProvider`, which persists across Inertia visits, and nothing closes the sheet on navigation. Checked at 390 px on 2026-09-30: `/persons` → menu → „Źródła” changes the URL to `/sources` and the sheet is still mounted over the new page. The user has to tap outside to see where they went.

## Decisions (2026-09-30, planning)

Variant A chosen. Brand „Root Keeper” with the placeholder lettermark „RK”, in the shell only. The logo links to `/tree`; the post-login target stays `/persons`. The unused header shell and `nav-footer.tsx` are deleted in this change. Settings stay English.

## Deferred (visible, not in this change unless the plan takes them)

- Contents of the settings pages (`resources/js/pages/settings/*.tsx`) are English; they are their own view. Only the settings sub-navigation labels (C5) belong to the shell.
- Auth layouts use the same Laravel mark with literal colours (`resources/js/layouts/auth/*.tsx`); logged-out views are outside this change.
- Variant B needs behaviour that does not exist: a person search reachable from every page and the page title/actions moved from each page into the top bar.
