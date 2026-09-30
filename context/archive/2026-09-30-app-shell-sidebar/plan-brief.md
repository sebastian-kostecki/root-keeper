# App shell — variant A („bez górnej belki”) — Plan Brief

> Full plan: `context/changes/app-shell-sidebar/plan.md`
> Research: `context/changes/app-shell-sidebar/audit.md` (charges), `context/changes/app-shell-sidebar/tokens.md` (values)

## What & Why

The shell a signed-in user sees on every page is still the starter kit's: it is called „Laravel”, links to the starter's repository, repeats each page title in a top bar, speaks English in the account menu, and barely shows which section is open or where keyboard focus is. This change rebuilds it to variant A of the design canvas https://claude.ai/artifact/EgWsEjc3AE64kvCcXpKAKN.

## Starting Point

A shadcn sidebar (`variant="inset"`, collapsible to icons) with three destinations, two starter links and the account row; a 64 px top bar holding the collapse button and breadcrumbs. The sidebar tokens exist in both themes, but three of them have values that do not work, and four components carry literal colours. A second, unused shell with its own navigation sits in the repo.

## Desired End State

The sidebar shows „RK Root Keeper” (link to the tree), the collapse button, Drzewo / Osoby 41 / Źródła 18 and the account row with the e-mail. There is no top bar on desktop; a nested page shows a link to its section („Drzewo”, „Źródła”) above its title. The current destination and keyboard focus are clearly visible in light and dark. On a phone a slim bar opens a sheet with 44 px rows that closes after a choice. The account menu reads „Ustawienia” and „Wyloguj”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Variant | A — no top bar | Removes the repeated title and gives 64 px of height back on every page, most of all on the tree. | Plan |
| Brand | „Root Keeper” + placeholder lettermark „RK”, in the shell only | The repo has no logo, and the Laravel mark must not stay. | Plan |
| Logo link | `/tree`; the post-login target stays `/persons` | The logo matches the first menu item, and a new account still lands on a page with „Dodaj osobę”. | Plan |
| Unused header shell, `nav-footer.tsx` | Deleted in this change | A second navigation with starter links and literal colours should not be reconnectable. | Plan |
| Settings pages | Stay English | Translating only their navigation would mix languages on one page; it is a separate view. | Plan |
| Token values | Five changes to `--sidebar-accent`, `--sidebar-ring`, dark `--sidebar-primary-foreground` | The current-page fill and focus ring were about 1.4:1 on the panel. | Research |
| Counters | `navCounts` shared closure; text colour, regular weight; 0 is not shown | Same counter rule as `sources-list-views`; partial reloads do not pay for the queries. | Plan |
| Parent trail | All breadcrumbs except the last, above the page title; nothing for section pages | The last crumb always equals the `h1`. | Research |
| Source icon | `ScrollText` instead of `FileImage` | Sources are no longer only scans. | Research |
| Phone sheet | Closes after choosing a destination; visible close button | Today it stays open over the new page. | Plan |
| Pages | Not edited | They already pass breadcrumbs to the layout. | Plan |

## Scope

**In scope:**
- Five sidebar token values; avatar fallback and progress bar moved to tokens.
- Shared prop `navCounts` with a Pest test; app name default „Root Keeper”.
- Sidebar, account row and menu, parent trail, phone bar; Polish control names.
- Removal of `app-header.tsx`, `app-header-layout.tsx`, `nav-footer.tsx`, `app-sidebar-header.tsx`, `AppVariant`.
- States: collapsed with tooltips, hover, current page, focus, open menu, phone sheet, empty tree.
- Screenshot gate against the six A boards.

**Out of scope:**
- The post-login target; settings translation; a real logo; the auth layouts.
- Variant B and C features (sidebar search, proband line, title bar, dark sidebar in light theme).
- Theme switch in the account menu; `SidebarRail`; page paddings and page headings.
- Dependency changes; production configuration; an in-repo screenshot test; a roadmap entry.

## Architecture / Approach

Values change in `resources/css/app.css`; components reference roles only. The server adds one lazily evaluated shared prop with two counts scoped to the user's tree. The layout stops rendering the top bar and instead renders a phone-only bar and the parent trail above the page; pages are untouched. Everything is built from parts the sidebar primitive already exports (`SidebarMenuBadge`, `SidebarTrigger`); the primitive itself only gets Polish names.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tokens and literal colours | Visible current item and focus ring; no literal colour in avatar and progress bar | A token value that reads well in one theme and not the other. |
| 2. Server contract | `navCounts`, its test, the app name | Counting outside the user's tree. |
| 3. The shell | New sidebar, no top bar, parent trail, phone bar, Polish strings, dead code removed | Something still importing a deleted file; the trail's spacing above different page headers. |
| 4. States | Collapsed rail, tooltips, focus order, phone sheet that closes, empty tree | One header serving expanded, collapsed and phone; closing the sheet without breaking the account menu inside it. |
| 5. Screenshot gate | `after/` shots and `verdict.md` | The boards were not rendered and checked after publishing; differences surface here. |

**Prerequisites:** Sail running; the demo tree seeded (`TreeViewDemoSeeder`); Playwright in the npx cache and Node on the PATH, as for `screenshots/before/`; local `.env` with `APP_NAME="Root Keeper"` before the gate.
**Estimated effort:** about 4 sessions; phases 1 and 2 fit in one.

## Open Risks & Assumptions

- The canvas boards were published without a visual check; the gate compares against them and against the plan's Desired End State, and records differences.
- Contrast figures in `tokens.md` are estimates from OKLCH lightness; they are re-measured by eye in the gate.
- `APP_NAME` on the server is set by the human after deploy; until then production prints the old name.
- The source page's viewer keeps its height formula; with 64 px less chrome it may leave different spacing, recorded in the gate.
- Settings stay English behind a Polish „Ustawienia” until their own change.

## Success Criteria (Summary)

- On any page the user sees the app's own name, which section is open and how many people and sources the tree holds, and nothing from the starter kit.
- The page title appears once, and the tree gets the full height and, collapsed, the full width.
- The shell is usable with the keyboard and on a phone: visible focus, Polish control names, a menu that closes after a choice.
