# Parent–Child Relations — Plan Brief

> Full plan: `context/changes/parent-child-relations/plan.md`

## What & Why

Roadmap slice S-06 (FR-011, FR-012, FR-008): the user can link two people of their tree as parent and child, see parents and children on the person preview, and remove a relation after explicit confirmation. Without relations the author cannot enter the 3 generations required by the success criterion, and the graphical tree (S-09) has nothing to draw.

## Starting Point

People and their birth/death events exist, with scans as sources; nothing connects one person to another. Link/unlink precedent exists for scans (`EventScanController`, `ScanPickerDialog`, `UnlinkScanDialog`), and every model is scoped to the user's single tree via `tree_id` policies returning 404.

## Desired End State

The person preview shows "Rodzice" and "Dzieci" cards (birth order, links to relatives, "Usuń relację" with confirmation). "Dodaj rodzica" (hidden at two parents) and "Dodaj dziecko" open a filterable picker in which unlinkable people are greyed out with a reason. The server rejects self-links, duplicates, a third parent, cycles and foreign-tree people. Deleting a person removes their relations and the confirmation dialog says how many.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Data model | Dedicated `parent_child_relations` table (tree_id, parent_id, child_id, unique pair, cascades) | Independent of birth events, trivial cascade on person delete, simple queries for S-09. |
| Parent limit | 0–2 parents of any kind | PRD allows one known parent; `Person` has no sex field, so no mother/father slots. |
| Cycle definition | Reject when the parent is the child or one of its descendants; A→B→C plus A→C is allowed | Only true cycles are forbidden — no extra genealogy judgement. |
| Add entry points | Both "Dodaj rodzica" and "Dodaj dziecko" on the preview, one endpoint | User enters the tree top-down or bottom-up as the record dictates. |
| Create-and-link | Existing people only | Smallest slice; the create form stays unchanged. |
| Blocked candidates | Shown disabled with a reason; server still validates | User understands why someone cannot be picked, like "Już powiązany" for scans. |
| Ordering | Birth date (partial dates on known parts, unknown last), then name, then id | Matches how a family is read — eldest first. |
| Date plausibility | No check | PRD non-goal of policing fact consistency; old records are often wrong. |
| Siblings | Not shown | One click away via a parent; avoids half/full-sibling semantics. |
| Delete-person dialog | States "N relacji rodzinnych" and that relatives stay | Explicit confirmation stays honest about what is destroyed. |
| Testing | Pest feature tests for every rule, cross-tree 404s, cascade, payload order and reasons; UI manual | Covers everything that could corrupt the tree, matching existing test style. |

## Scope

**In scope:**
- Relation table, model, factory, `Person::parents()` / `children()`
- Store/destroy endpoints with rules enforced atomically in one action
- Preview cards, picker with reasons and filter, removal dialog
- Relation count in the delete-person dialog

**Out of scope:**
- Biological/adoptive distinction, mother/father labels, sex field
- Date plausibility checks, siblings, create-and-link from the picker
- Sources for relations, persons index changes, browser tests, undo

## Architecture / Approach

`ParentChildRelationStoreRequest` validates shape, tree ownership and self-link → `CreateParentChildRelation` locks both people, builds `ParentChildGraph` from the tree's pairs, checks duplicate / two parents / cycle, and inserts inside one transaction. The same graph feeds `ListRelationCandidates`, the optional `relationCandidates` prop the picker reloads on open, so picker reasons and server rules cannot disagree. The preview gets pre-sorted `parents` / `children` through `whenLoaded`, leaving the index payload unchanged.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Relation model and rules | Table, model, graph, action, endpoints, rule tests | Race between concurrent adds — mitigated by row locks inside the transaction |
| 2. Relations on the person view | Sorted relatives, picker with reasons, removal dialog, delete-person count | Reason labels drifting from server rules — mitigated by the shared graph |

**Prerequisites:** S-01 (done); Sail running; PostgreSQL test database.
**Estimated effort:** ~2 sessions across 2 phases.

## Open Risks & Assumptions

- A cycle created by two simultaneous requests on four different people is theoretically possible (locks cover only the two people per request); acceptable for a single-user tree.
- Approximate dates ("ok. 1850") sort as exact.
- Loading all tree people for the picker assumes a personal-scale tree (hundreds, not tens of thousands).

## Success Criteria (Summary)

- The author can enter three generations by linking existing people from either side and see them on each preview.
- The tree cannot get a self-link, a third parent, a duplicate or a cycle, and no relation leaks across users.
- Every relation removal — directly or through person deletion — is explicitly confirmed and states its consequences.
