# Graphical Tree View (Family Sheet) — Plan Brief

> Full plan: `context/changes/graphical-tree-view/plan.md`
> Research: `context/changes/graphical-tree-view/research.md`

## What & Why

Roadmap slice S-09 (FR-004, US-01): the user should browse their tree graphically and move from it to people. We build
a PL-SOFT-style „Formularz rodzinny”. It is a four-row grid (Dziadkowie / Rodzice / Związek / Dzieci) centred on one
person. The user walks it by re-centring on any card and opens a person's preview from a separate button.

## Starting Point

- **Data exists, but no tree surface.** People, parent–child pairs (per parent, not per couple) and marriages (events
  with two spouses) already exist, but there is no tree route, page or nav item.
- **No gender, no root person.** Persons have no gender, and a tree has no root person.
- **Reusable pieces.** `ParentChildGraph` loads all edges in one query. `Religion` is the ready pattern for a nullable
  enum field.

## Desired End State

„Drzewo” in the sidebar opens the sheet on the stored proband, or an empty state until one is set:
- **Cards** show name, years („ok. 1850 – 1912”) and gender: a colour strip plus a Mars/Venus icon.
- **Placeholders.** Missing parents are dashed „Nieznany ojciec / Nieznana matka / Nieznany rodzic” cards.
- **Navigation.** Clicking a card re-centres the sheet, and „Podgląd” opens `persons.show`.
- **Unions.** A switch chooses among the focus person's unions: marriages, co-parents without a marriage, and an
  unknown partner. The partner's ancestors and the children follow the chosen union.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Tree surface | Family sheet grid (4 rows) | PL-SOFT's main navigation screen; a CSS grid needs no layout algorithm or library. | Plan (research Q1) |
| Card click | Re-centre on the card; „Podgląd” is a separate button | Exploration is the primary action; the preview stays one click away. | Plan |
| Preview target | Navigate to `persons.show` | No duplicated person rendering; the preview already shows sources (US-01). | Plan |
| Several marriages | One partner + union switch | Compact and predictable; order is marriages by date → co-parents → unknown partner. | Plan (research Q2) |
| One known parent | Dashed „Nieznany …” placeholder | Makes gaps visible as research hints. | Plan (research Q3) |
| Layout engine | Hand-rolled CSS grid, no new dependency | Follows from the family-sheet choice. | Plan (research Q4) |
| Default centre | Stored proband (`trees.root_person_id`) | A stable home person; the alternatives were an arbitrary or insertion-order pick. | Plan |
| Proband UX | Button on preview + toolbar; empty state; delete warns | An explicit choice that follows the delete-warning convention. | Plan |
| Ancestor rows | Both partners: 4 parents + 8 grandparents | True to PL-SOFT's couple-centred sheet. | Plan |
| Card content | Name + years only | Compact enough for an 8-card row. | Plan |
| Gender | New nullable `people.gender` (male/female; null = unknown) | Needed for card styling and father/mother placement. | Plan (user request) |
| Gender look | Left colour strip + Mars/Venus icon | Not colour-only, legible in dark mode. | Plan |
| Parent order | Male left, female right, unknown between; birth date tie-break | Genealogy convention; rows read consistently across generations. | Plan |
| Narrow screens | Horizontal scroll with fixed min width | One layout; alignment always correct. | Plan |

## Scope

**In scope:**
- The gender field (forms, preview).
- Proband storage and endpoint, plus the delete warning.
- The family sheet builder and routes `/tree`, `/tree/{person}?spouse=`.
- Sheet components, the gender tokens, and the sidebar and „Pokaż w drzewie” entry points.
- A demo seeder and screenshots.

**Out of scope:**
- A pan/zoom or generated tree, fan charts, siblings, export.
- All spouses shown at once, or a person modal.
- Source badges, places or photos on cards.
- Biological vs adoptive parents.
- An automatic proband.
- Screenshot test infrastructure.

## Architecture / Approach

The server does all the relationship reasoning. `BuildFamilySheet` (an Action) uses `ParentChildGraph` plus batched
loads to derive unions, pick the selected one, and fill a fixed-shape payload. Each slot is `person`, `unknown` (with
a label) or `empty`, and each person is rendered through `SheetPersonResource`.

`TreeController` serves it to one Inertia page, `tree/show`. The React side only renders an 8-column grid of shared
components from `components/tree/`. The only new design tokens are the gender colours, in `app.css`. The ordering
keys move from `PersonResource` into `App\Support\PersonOrdering`, so the preview and the sheet agree.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Gender field | Nullable enum on Person through forms, resources and preview | Select sentinel for „Nieznana” (Radix disallows empty values) |
| 2. Proband | `trees.root_person_id`, `PUT tree/root-person/{person}`, badge, delete warning | Circular FK (`nullOnDelete` vs cascades) on account deletion |
| 3. Sheet backend | `BuildFamilySheet`, `/tree` routes, `formatYear`, `PersonOrdering` | Union derivation correctness (children not tied to marriages) |
| 4. Sheet UI | Grid, cards, placeholders, union switch, toolbar, entry points | Connectors and alignment across 8/4/2 column spans |
| 5. Visual gate | Kitchen-sink seeder, screenshots of every state, roadmap note | States missed by the seeder go unreviewed |

**Prerequisites:** S-06 and S-07 are done (parent–child relations and marriages exist); Sail is running.
**Estimated effort:** about 3–4 sessions across 5 phases (Phases 3 and 4 are the largest).

## Open Risks & Assumptions

- **Unverified visual reference.** The PL-SOFT screenshots were never viewed (research gap). Colours and frame shape
  follow the text description and genealogy conventions, not a pixel reference.
- **Whole-tree edge loading.** Loading every edge per request assumes a personal-sized tree (hundreds of people).
- **Same-gender or both-unknown parents** fall back to birth-date order, so their sides can swap between
  generations. This is accepted.
- **Deadline.** Per the roadmap, S-09 is the riskiest UI in M-1. If time runs short before 2026-11-04, the grandparent
  row is the first thing to simplify, not a phase to drop.

## Success Criteria (Summary)

- From the sidebar the user sees their proband's family sheet across 3 generations plus children, and can walk to any
  relative by clicking cards and open a preview from any card.
- Several marriages and single-parent children are navigable via the union switch, and gaps are visible as dashed
  placeholders.
- All `TreeTest`/`PersonsTest` cases, `npm run check`, `types:check` and `build` pass; screenshots of every seeded
  state are in the change folder.
