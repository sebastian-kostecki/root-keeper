# Family sheet in variant C — Plan Brief

> Full plan: `context/changes/tree-sheet-variant-c/plan.md`
> Audit (acts as frame + research): `context/changes/tree-sheet-variant-c/audit.md` · Tokens: `context/changes/tree-sheet-variant-c/tokens.md`

## What & Why

Bring the desktop family sheet to variant C of the Design canvas (https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F). Today gender is a 4px edge strip, the relationship lines nearly vanish (`--border`, ≈1.2:1), the union choice sits in a chip row above the sheet, far from the couple it controls, and a „Dzieci” heading cuts the descent line. Variant C makes gender the card's fill, makes the lines readable, and puts the union choice on the couple node.

## Starting Point

The sheet is `pages/tree/show.tsx` + `components/tree/family-sheet.tsx` (an 8-column grid). Cards share `card-styles.ts`. Unions come from `BuildFamilySheet.php` with a server-built `label`, rendered by `UnionSwitch` (a `ToggleGroup`). The previous change (`tree-family-sheet-ui`) left a Playwright gate script and an `after/` set that is this change's "before" set.

## Desired End State

At 1440px, light and dark:
- Cards are tinted by gender, with centred text and no icons (gender stays as `sr-only` text). Hover colours the border only, and the focus person has a primary border.
- Placeholders say what they do („Dodaj ojca”, „Dodaj dziecko”…), and all lines use a visible `--tree-line`.
- A dropdown on the couple line („1 z 4 · ślub 2001”) switches unions and adds a spouse.
- Generation headings are screen-reader only, and „Dodaj dziecko” sits in the children row.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Token set | 6 roles: gender surface/border ×2, `--tree-line`, `--tree-meta` | Existing hues, only lightness chosen; contrast of meta text on tint ≥ 4.5:1 | Research (`tokens.md`) |
| Gender cue | Colour only + `sr-only` text | User decision; icons removed | Research (audit D2) |
| Card hover | Border in gender colour, fill untouched | `bg-accent` would erase the colour that now carries meaning | Research (audit C2) |
| One union | Still a dropdown, without „1 z 1” | Only way to add a second spouse; overrides canvas „sama etykieta” | Research (audit C3) |
| Empty trigger (1 union, unknown partner) | „Związki” + chevron | Visible text; icon-only is easy to miss | Plan |
| `label` field | Replaced by nullable `partner_name`; status built client-side | No dead field; „ślub ?” becomes „ślub, data nieznana” | Plan |
| Menu → spouse dialog | Controlled `open` on `PickOrCreatePersonDialog`, `switcher` mode → `menu` | A menu item unmounts on close and can't be a `DialogTrigger` | Research (audit C3b) |
| Centre-couple „Połącz jako małżeństwo” | Stays on the descent line below the node | Same pattern as parents' chips; no second controlled dialog | Plan |
| Generation headings | `sr-only` | Screen-reader structure kept, visual cut removed | Research (audit C4) |
| „Dodaj dziecko” with 3 children in the last row | Own centred row (same row only after 1–2) | 3 children fill cols 2–7; children stay centred on the couple axis | Plan |
| Mobile | Out of scope; 390px shot for regressions only | Second layout = its own change | Research (audit D1) |

## Scope

**In scope:** tokens in `app.css`; `partner_name` in the payload + Pest; `card-styles.ts`, person card, placeholders, connectors; new `union-node.tsx`, controlled dialog, `UnionSwitch` removed; `sr-only` headings and children-row placement; one 3-children fixture; the screenshot gate + verdict.

**Out of scope:** mobile vertical layout, header meta line („ur. 1975 · 4 związki · 5 dzieci”), empty-state redesign, a second visual gender cue, an in-repo screenshot test, `components/ui/*` edits.

## Architecture / Approach

Contract first: tokens and payload (Phase 1). Then the shared look in one module (Phase 2). Then the only new interaction, `UnionNode`: a `DropdownMenu` with radio items and „Dodaj małżonka” opening a controlled `AddSpouseDialog` (Phase 3). Then geometry (Phase 4), and finally a screenshot gate that judges each charge (Phase 5). The dropdown reuses the existing `router.visit(treeShow(id, {query: {spouse}}), {preserveScroll: true})` flow, so the backend's union selection is unchanged.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract | 6 tokens (both themes, `@theme`), `partner_name` replaces `label`, Pest updated | Missed `label` reader (only `union-switch.tsx:66` today) |
| 2. Cards & lines | Tinted cards, verb placeholders, `--tree-line` connectors | Hover/focus classes leaking the old `bg-accent` onto tints |
| 3. Union node | Dropdown on the couple line; chip row removed | Radix menu → dialog: candidates not loading, `pointer-events` stuck, focus not returned |
| 4. Geometry | `sr-only` headings, „Dodaj dziecko” in row, 3-children fixture | Bracket centring at the 1/2/3/4/5-child boundaries |
| 5. Screenshot gate | States inventory shot light/dark + 390px, `verdict.md` | Contrast estimates in `tokens.md` not holding on real render |

**Prerequisites:** Sail up, `TreeViewDemoSeeder` seeded, Playwright in the npx cache (path in `shots.mjs`).
**Estimated effort:** ~2–3 sessions across 5 phases.

## Open Risks & Assumptions

- The contrast figures in `tokens.md` are OKLCH estimates, confirmed in Phase 5. If `--tree-meta` or `--tree-line` fails, adjust the value in `app.css` + `tokens.md`, not per component.
- Absolutely centring the node on the couple line assumes the two couple cells stay symmetric (`max-w-72`). A long partner name that grows the card taller keeps the node on the vertical centre, not on the line, which is acceptable per the canvas.
- On 390px the node lives inside the sideways-scrolling sheet. `useCentreInScroll` centres the focus card, so the node may be partly off-screen until the mobile change (D1).

## Success Criteria (Summary)

- A user tells the two families' men and women apart at a glance and can follow every line from grandparents to children.
- The union choice sits on the couple it controls; switching or adding a spouse works by mouse and keyboard.
- The gate's `verdict.md` shows C1–C4 passing in light and dark with no 390px regression.
