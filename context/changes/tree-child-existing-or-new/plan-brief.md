# Existing or New Child of the Shown Pair — Plan Brief

> Full plan: `context/changes/tree-child-existing-or-new/plan.md`

## What & Why

Roadmap S-26 (v4:FR-005, FR-011): from the tree, the user adds a child of the pair currently shown. The pair is the focus person plus the partner chosen in the union switcher, or the focus person alone when there is no partner. The child is either an existing person or a new one. The child appears on the tree immediately, and a link that would give the child a third parent (or break another relation rule) cannot be made. It replaces the never-started S-22, which only allowed a new person.

## Starting Point

S-25 built the parent-slot dialog (a list of tree people plus a „Nowa osoba" form), the on-demand `parentCandidates` prop and an atomic create-and-link endpoint. The sheet already knows the selected union, but the "Dzieci" row has no add action. Relation rules (max two parents, no cycle, no second father/mother) are enforced in `CreateParentChildRelation`.

## Desired End State

The "Dzieci" row ends with a dashed „Dodaj dziecko" card. It opens a dialog that names the parents („Rodzice: Jan Kowalski i Anna Nowak", or „Rodzic: Jan Kowalski (drugi nieznany)").
- **List:** people who can be the pair's child. Rule-breakers are hidden, and a line counts them and says why.
- **„Nowa osoba":** the person form with the surname prefilled.

Either path links the child to every parent of the pair in one transaction, and the tree shows the child without changing its centre or union.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Rule-breaking candidates | Hidden, with a count hint („N osób ukryto — mają już innych rodziców.") | Satisfies FR-011's "hide" and FR-005's "say why", consistent with the S-25 slot. |
| No partner shown, candidate has another parent | Allowed when the rules pass (the child gets the focus person as a parent and keeps the other one) | Fixes the common "mother known, father added later" gap without detours; the server needs no special rule. |
| Default surname of a new child | The pair's man's surname, else the focus person's | Matches most Polish records and S-25's father-slot prefill; always editable. |
| Which pair | Always the union chosen in the switcher; no "focus alone" while a partner is shown | The roadmap's risk: a child landing in the wrong pair. |
| Existing partial child (child of the focus only) | Offered; only the missing link is added | This is how a child moves from „Nieznany partner" into a known union. |
| Where exclusion/blocking is computed | Server-side, in the sheet (`add_child`), from the in-memory graph | The client only hides; the server stays the authority. |
| Dialog code | Extract a shared pick-or-create shell from `ParentSlotDialog` | Avoids a second 250-line copy; S-27 (spouse) needs the same shell. |
| Candidate prop | Rename `parentCandidates` → `personCandidates` | It now serves parent and child dialogs. |

## Scope

**In scope:**
- `add_child` sheet metadata (parents, excluded, blocked, default surname).
- The attach endpoint (`POST persons/{person}/children/attach`) and the create endpoint (`POST persons/{person}/children`), both atomic, with the toast „Dodano dziecko".
- The shared dialog shell, `AddChildDialog`, and the card in the children row.
- Feature tests.

**Out of scope:**
- "Focus alone" while a partner is shown; add-child on non-centre cards.
- Spouse slot (S-27), relation removal from the tree (S-28), read-only preview (S-29).
- Disabled-with-reason rows.
- Sex lock or other prefills.
- Server-side check that the two parents form a union.
- New tokens, `ui/*` edits, screenshot infrastructure.

## Architecture / Approach

1. `BuildFamilySheet` derives `add_child` from the selected union and the `ParentChildGraph`.
   - Excluded: the pair, their ancestors, and children of every parent of the pair.
   - Blocked: `|parents(C) ∪ pair| > 2`, or in single-parent mode another parent of the focus's sex.
2. The dialog hides both groups and counts the blocked ones.
3. Picking posts to `PersonChildController@attach`, which runs `AttachChildToParents`: in one transaction, `CreateParentChildRelation` for each missing link.
4. „Nowa osoba" posts to `@store`, which runs `CreateChildForParents` (`CreatePerson` + attach in one transaction).
5. Both redirect `back()`, keeping `?spouse=`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Attach an existing child | Card, dialog list with hint, attach endpoint, shared shell, prop rename, tests | Extracting the shell must not change the S-25 parent slot; a partially created pair link must roll back |
| 2. New child from the same dialog | „Nowa osoba" view with default surname, create endpoint, tests | Atomicity of person + two links |

**Prerequisites:** S-24 and S-25 done (the shared dialog comes from S-25); S-06 relations.
**Estimated effort:** ~2 sessions across 2 phases.

## Open Risks & Assumptions

- In the „Nieznany partner" union, attaching a child who already has another parent moves them into a different union than the one on screen; the switcher and the toast show where they went (accepted). With no union, the tree shows the new union directly.
- Legacy data with two same-sex co-parents cannot get a child from the tree (the same-sex rule refuses the second link); this is accepted.
- The pair's "man" is decided by known sex only; with unknown sexes, the default surname falls back to the focus person's.

## Success Criteria (Summary)

- From Jan and Anna's union, the user attaches existing Piotr or creates a new child. The child shows on the tree immediately, with both parents, and the tree doesn't move.
- The list never offers someone who would get a third parent or create a cycle, and says how many were hidden and why.
- The S-25 parent slot works exactly as before.
