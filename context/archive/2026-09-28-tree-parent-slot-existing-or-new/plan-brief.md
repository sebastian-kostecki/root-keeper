# Existing or New Parent from an Empty Tree Slot — Plan Brief

> Full plan: `context/changes/tree-parent-slot-existing-or-new/plan.md`

## What & Why

Roadmap S-25, the v4 north star: the author sees „Nieznany ojciec" on the tree and fills it in on the spot — by picking a person who already exists in the tree or by creating a new one — without leaving the tree. Today the empty slot is a dead label and parents can only be linked from the preview modal, which also lets a child get two fathers.

## Starting Point

`BuildFamilySheet` renders empty parent slots as `{kind: 'unknown', label}` with no child or role; the frontend shows them as a static dashed card. Relation rules (duplicate, max two parents, no cycle) live in `CreateParentChildRelation`; candidates for pickers exist only for the preview modal (`?person=`). The person form cannot preset or lock sex, and creating a person redirects to that person's tree.

## Desired End State

Every „Nieznany ojciec / matka / rodzic" slot (parents and grandparents) opens one dialog: a searchable list of fitting people (right sex, no self/parents/descendants), a hint about hidden unknown-sex people, and „Nowa osoba" with the slot's sex locked (father's surname prefilled). The parent appears on the tree at once, same centre and union. When a parent link is created, the server refuses a second parent of the same known sex.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Existing vs new | Both, from the slot | v4 replaces v3's "always new" to reduce duplicates. | PRD v4 FR-004 |
| Rule-breaking candidates | Hidden, not disabled | The picker must never offer a link the rules refuse. | PRD v4 FR-011 |
| New person's sex | Set and locked by slot; free for „Nieznany rodzic" | A person created as father must be a man. | PRD v3 FR-001 |
| Dialog shape | One dialog, list first, „Nowa osoba" switches view | One target on small grandparent cards; existing-first counters duplicates. | Plan |
| Unknown-sex people in father/mother slot | Hidden, with a count hint | Literal FR-004, no silent edits, tree stays consistent. | Plan |
| Sex rule on server | On every new link (tree and preview): no second parent of same known sex; slot role checked; sex edits not guarded | Enforces the v3 guardrail independent of UI, one place for the rule. | Plan |
| Prefill | Father slot: child's surname | Most common case in Polish records; editable. | Plan |
| Candidate source | Slot carries `excluded_ids`; lazy `parentCandidates` prop | Reuses the in-memory graph and the existing optional-prop pattern. | Plan |
| New person + link | One transaction, dedicated `persons/{person}/parents` endpoint | A rule failure must not leave an orphan person. | Plan |

## Scope

**In scope:** clickable parent/grandparent unknown slots; list view with sex/rule filter and hint; attach via existing relation endpoint with `role`; new-person view with locked sex and surname prefill; atomic create endpoint; same-sex server rule and matching blocked reasons in the preview picker; `genders`/`religions`/`parentCandidates` on the tree page; Pest feature tests.

**Out of scope:** child (S-26), spouse and „Połącz jako małżeństwo" (S-27), removal from tree (S-28), read-only preview (S-29); clickable partner/empty slots; setting sex on existing people; cleaning existing same-sex parent pairs; guarding sex changes in the person edit form (the rule applies only when a link is created); tokens, `ui/*` edits, screenshot-test infrastructure.

## Architecture / Approach

Server computes, client hides, server enforces. `BuildFamilySheet` adds `parent_of {id, display_name, last_name, role, excluded_ids}` to parent slots. The tree page lazily loads all tree people with sex; `ParentSlotDialog` filters by `excluded_ids` and role. Attach → `parent-child-relations.store` + `role`; create → `PersonParentController@store` → `CreateParentForPerson` (CreatePerson + CreateParentChildRelation in one transaction). Both redirect `back()`, so the tree URL and union survive.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Attach an existing parent from the slot | Server sex rule, slot metadata, lazy candidates, clickable slot + list dialog (US-01 end to end) | Client filter drifting from server rules |
| 2. New parent from the same dialog | Atomic create endpoint, „Nowa osoba" view with locked sex and surname prefill | Orphan person on rule failure; form regressions in `PersonFormDialog` if the body is extracted |

**Prerequisites:** S-24 (preview modal) and S-06 (relations) done — both are.
**Estimated effort:** ~2 sessions, one per phase.

## Open Risks & Assumptions

- Assumes little or no existing data with two same-sex parents; such pairs remain and only block further links.
- The preview picker gains new blocked reasons now, before S-29 removes its relation editing.
- Visual gate is manual (no screenshot tests in the repo), as in S-20/S-24.

## Success Criteria (Summary)

- US-01: from „Nieznany ojciec" the author attaches existing Józef as Jan's father without leaving the tree; centre and union unchanged.
- A new father/mother created from a slot has the right sex, appears on the tree at once, and nothing is created when a rule refuses.
- No new parent link — from the tree or the preview — can give a child a second father or mother.
