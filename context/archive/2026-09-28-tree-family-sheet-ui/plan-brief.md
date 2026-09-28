# Family Sheet View (`tree/show`) — Plan Brief

> Full plan: `context/changes/tree-family-sheet-ui/plan.md`
> Audit (research equivalent): `context/changes/tree-family-sheet-ui/audit.md`

## What & Why

The family sheet already reads its colours from tokens, but it has layout and state problems:
- Pages sit flush against the panel.
- The lines say "children of Jan", not "children of Jan and Anna".
- In light mode the selected union, hover and keyboard focus are nearly invisible.
- At 390px the user sees a third of the sheet with no cue that it continues.

This plan fixes the five audit charges (C1–C5) plus D2/D3 on one view, with global token and layout changes where the fix belongs there.

## Starting Point

`tree/show.tsx` renders `UnionSwitch` and `FamilySheet`, an 8-column `min-w-[72rem]` grid (grandparents → parents → couple → children) built from shadcn tokens in `app.css` and `components/ui/*`. The children bracket starts at column 1 while the couple's descent line drops at 50%. `--ring` is `oklch(0.87)` (~1.5:1). There is no page gutter in `app-sidebar-layout.tsx`, and settings pads itself.

## Desired End State

- Every sidebar page has one gutter aligned with the breadcrumb bar, and the tree title is an `h1`.
- A couple line joins focus and partner; the children hang centred from its midpoint, with „Dodaj dziecko” under them.
- Selected union, hover and a ≥3:1 keyboard focus ring are visible in both themes.
- At 390px the sheet opens centred on the focus person, with an edge fade and a one-row union switch.
- `screenshots/after/` pairs 1:1 with `before/`, and `audit.md` records a gate verdict per charge.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Motif | PL-SOFT „Formularz rodzinny” + Ancestris gender frames | Already chosen in the tree-view research; not re-researched. | Research (audit) |
| Gutter (C1) | One container in `app-sidebar-layout.tsx`, settings layout drops its `px-4 py-6` | The shared-component fix; per-page padding is the duplicate. | Plan |
| Couple line (C2) | Horizontal line focus↔partner, descent from its midpoint, children centred | Completes the motif: the line *is* the data. | Plan |
| Children centring | Row of `n` starts at column `5 − n`, via literal `col-start-*` classes | Symmetric bracket around 50% for n = 1–4, with no maths at runtime. | Plan (derived) |
| Hover (C3) | Full `bg-accent` + `foreground/20` border on non-strip sides | Every background role is `0.97` on white, so background alone can't show hover; no new token. | Plan |
| Selected union (C3) | `data-[state=on]:border-primary ring-1 ring-primary` in `union-switch.tsx` | Existing high-contrast role; `ui/toggle.tsx` untouched. | Audit + Plan |
| Focus ring (C4) | `--ring: oklch(0.556 0 0)` in both themes, source comment in `app.css`; offset halo on cards; eye button full `ring-ring` | ≈4.7:1 light / ≈4:1 dark (WCAG 1.4.11); offset separates it from the focus person's ring. | Plan (derived) |
| Mobile (C5) | Keep one grid; centre focus via `scrollLeft`, mask edge fade, one-row union switch below `md` | Smallest change that keeps one layout and gets the sheet above the fold. | Plan |
| Title (D2) | Additive `Heading variant="page"` (an `h1`, no margin), used by tree only | Fixes the gap + h1 without shifting 8+ other callers. | Plan |
| Card classes (D3) | One `components/tree/card-styles.ts` | C3/C4 touch both cards; fix once, no drift. | Audit |
| Kitchen sink | Seeder gains a 5-children couple (appended); `shots.mjs` derives IDs from the proband | The >4-children state is otherwise unverified; reseeding shifts absolute IDs. | Plan |

## Scope

**In scope:** `app.css` `--ring`; `app-sidebar-layout.tsx`; `settings/layout.tsx` padding; `heading.tsx` `page` variant; `tree/show.tsx`; `components/tree/{family-sheet,union-switch,sheet-person-card,unknown-person-card,card-styles}`; a new `hooks/use-centre-in-scroll.ts`; `TreeViewDemoSeeder`; `shots.mjs`; gate table in `audit.md`.

**Out of scope:** new colour tokens, edits to `components/ui/*`, `--sidebar-ring`, a stacked mobile layout, the default `Heading` variant, D1 (404 error page), D4 (placeholder affordance), D5 (grandparent density), an in-repo screenshot test, and Pest tests.

## Architecture / Approach

Contract before pixels:
1. Phase 1 changes only shared values and structure (token, layout container, heading variant).
2. Phase 2 fixes the sheet's geometry.
3. Phase 3 moves the card interaction classes into one module and fixes the states there.
4. Phase 4 adds a single scroll-centring hook reused by the sheet and the union switch.
5. Phase 5 is the screenshot gate, judged charge by charge.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract — ring, gutter, heading | Visible focus ring, page gutter on every sidebar page, tree `h1` | Double padding or spacing shift on settings pages |
| 2. Sheet geometry (C2) | Couple line, centred children + bracket, desktop fits at 1440px, 5-children seed | Dynamic `col-start` classes not generated by Tailwind; reseed shifts IDs |
| 3. Interaction states (C3/C4/D3) | Selected union, visible hover, offset focus halo, shared card classes | Hover border overriding the gender strip |
| 4. Mobile (C5) | Focus centred at 390px, edge fade, one-row union switch | `scrollIntoView` making the page jump vertically |
| 5. Screenshot gate | `after/` 1:1 with `before/` plus new pages/states; verdicts in `audit.md` | Treating visual findings as cosmetic |

**Prerequisites:** Sail up, `TreeViewDemoSeeder` seeded, the npx-cached Playwright used by `shots.mjs` available.
**Estimated effort:** ~2–3 sessions across 5 phases (Phase 2 is the largest).

## Open Risks & Assumptions

- The ring's contrast ratios are computed from OKLCH lightness; the gate confirms them visually, not with a contrast tool.
- `min-w-[68rem]` assumes the 1440px panel stays ≈1170px wide. A wider sidebar would bring back a small horizontal scroll on desktop.
- Moving the gutter to the layout slightly changes the settings pages at 390px (24px instead of 16px inline). Accepted as the cost of one source.
- States still not captured by the gate: the disabled `SetRootPersonButton` while processing, and the loading `personCandidates` in dialogs.

## Success Criteria (Summary)

- In light mode a user can tell at a glance which union is shown, where the keyboard is, and which card is under the pointer.
- The lines read "children of this couple" for every union in the kitchen sink.
- On a phone, `/tree` opens on the focus person with a clear cue that the sheet continues sideways.
