# Preview without relation adding (S-29) — Plan Brief

> Full plan: `context/changes/preview-relations-read-only/plan.md`

## What & Why

The person preview modal stops offering „Dodaj rodzica”, „Dodaj dziecko” and „Dodaj małżeństwo”, so the tree is the only place to **add** relations. During planning the user decided that **removal stays in the preview**: the tree only views and adds. So S-28 (removing relations from the tree) is dropped, and the roadmap and PRD v4 are amended to match.

## Starting Point

S-24 already moved the preview into a global modal and deleted the `/persons/{id}` page (`73d4fa4`), so that half of the roadmap's S-29 is done. S-25 – S-27 made the tree able to add every relation type. The preview still has its own add pickers, fed by a `relationCandidates` shared prop, alongside remove buttons and marriage date/place editing.

## Desired End State

In the preview, Rodzice, Dzieci and Małżeństwa show no add buttons. „Usuń relację”, „Usuń małżeństwo” and the marriage „Edytuj” work as today. An empty section reads e.g. „Brak znanych rodziców. Dodasz ich na drzewie.”, and the header button „Pokaż na drzewie” centres the tree on the person. No code computes `relationCandidates` any more. The roadmap shows S-28 as dropped and S-29 narrowed, and PRD FR-007/FR-008 describe adding on the tree and removing in the preview.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Where relations are removed | Preview only; S-28 dropped | User decision: the tree is for viewing and adding people only. | Plan |
| What leaves the preview | Only the add actions (parent, child, marriage) | The tree already covers every add, so no relation loses its only entry point. | Plan |
| `/persons/{id}` page | Out of scope | Already removed by S-24; `GET` returns 405 and is tested. | Research |
| „Pokaż na drzewie” | Relabel the existing header button | It already centres the tree and closes the modal; one entry point, no duplicates. | Plan |
| Empty relation sections | Keep the text and add „Dodasz ich/je na drzewie.” | Explains where the Add button went so it isn't read as a bug. | Plan |
| Dead code | Full cleanup, including 4 relation-candidate tests and one trimmed test | Nothing should compute a prop for a UI that no longer exists; the tree uses its own `personCandidates`. | Plan |
| Marriage-removal confirmation | Unchanged (no date/place warning) | Keeps scope to removing add paths; today's text already names both spouses and the scan links. | Plan |
| Recording the S-28 drop | Final docs phase in this change | Roadmap and PRD match the code the moment this change closes. | Plan |

## Scope

**In scope:**
- Remove the add triggers and the marriage-dialog create branch; delete `relative-picker-dialog.tsx`, `useRelationCandidates` and the `RelationCandidate` type.
- Relabel „Pokaż w drzewie” → „Pokaż na drzewie”; empty-state hints.
- Remove the `relationCandidates` shared prop, `ListRelationCandidates` and the tests covering them.
- Amend the roadmap (S-28 dropped, S-29 narrowed) and PRD v4 FR-007/FR-008; delete the `tree-relation-removal/` stub.

**Out of scope:**
- Any relation removal on the tree.
- A date/place warning in the marriage-removal dialog.
- `/persons/{id}` (already gone), visual restyle, screenshot gate, GitHub issues, the `feat/tree-relation-removal` branch.

## Architecture / Approach

Only deletions on the preview side. The endpoints stay: the tree still calls `ParentChildRelationController::store` and `MarriageController::store`, and the preview still calls both `destroy` actions and `MarriageController::update`. The `useOptionalList` hook stays because the tree's picker uses it. The frontend goes first, then the backend prop and tests, then the documents.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Preview without add actions | Add buttons gone, remove and marriage edit kept, relabelled link, empty-state hints | Accidentally dropping a remove or edit action while trimming the components |
| 2. Backend cleanup | `relationCandidates` prop, action and tests removed; `availableScans` test kept | Trimming the shared PersonsTest too far and losing `availableScans` coverage |
| 3. Foundation docs | Roadmap S-28 dropped, S-29 narrowed; PRD FR-007/FR-008 amended; stub folder removed | Leaving a stale „S-28” or „tylko do odczytu” line that contradicts the code |

**Prerequisites:** S-25 – S-27 done (they are). S-28 is no longer a prerequisite.
**Estimated effort:** ~1 short session across 3 small phases.

## Open Risks & Assumptions

- The change id stays `preview-relations-read-only` although the preview isn't fully read-only; only the title changes.
- The branch currently checked out is `feat/tree-relation-removal`, which has no commits of its own. Implementation should run on a branch named for this change; the user decides what happens to the old branch.
- PRD v4 is amended in place with a dated note, not versioned to v5.

## Success Criteria (Summary)

- The preview no longer lets you add a parent, child or marriage, and everything else in it still works.
- Every relation can still be added (tree) and removed (preview), with no gap at any commit.
- The roadmap and PRD tell the same story as the code.
