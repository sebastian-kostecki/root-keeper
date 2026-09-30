# Source Description in Modal — Plan Brief

> Full plan: `context/changes/source-description-in-modal/plan.md`

## What & Why

Roadmap S-17 (prd-v3 FR-006, FR-007; prd-v2 FR-001 – FR-005, FR-014). A source is a file with a kind and nothing else, so the author re-reads the image every time to find „his" entry. This change gives a source a description and moves adding and editing into modals, so a cemetery photo takes a file and a kind, and an akt can carry its type, entry number and note.

## Starting Point

`sources` has no description columns and no `update` endpoint. Upload is a separate page. The file name is the only label of a source and is read directly in about ten components, including the person modal. Linking is akt-only until S-18.

## Desired End State

- „Dodaj źródło" on the „Źródła" list opens a modal: file and kind are required, the description is optional. After saving, the new source's preview opens.
- „Edytuj" on the preview opens a modal for kind and description.
- Switching an akt to another kind never erases typ or numer wpisu silently.
- The list card and the preview show the description; a named source shows its name everywhere.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Description fields | nazwa, rok, pochodzenie, notatka; akt also typ and numer wpisu | Required only file and kind, so bulk uploads stay fast | PRD v3 FR-006 |
| Link | New optional field, `http`/`https` only, clickable on the preview only | The author wants the address of the akt in an online archive; the card stays one link | Plan (user, outside the PRD) |
| Where the modals open | Only in „Źródła" — upload from the list, edit from the preview | Matches prd-v3 flow 5 and the earlier no-modal-on-modal decision | Plan (user, confirmed twice) |
| After upload | Open the new source's preview, as today | The author wants to see the image and describe it right away | Plan (user overrode „stay on the list") |
| Nazwa | New optional field; the file name is the fallback wherever a source is shown | No backfill, and a typed name never reaches the download header | Plan (user) |
| List card | Name, kind, „typ · rok", pochodzenie; upload date only when those are empty | Names on the list replace the parked search | Plan (user added pochodzenie) |
| Kind change on a linked akt | Allowed; the links stay | It is the repair path for scans misclassified by S-16, and no fact loses its confirmation | Plan (user) |
| Erase confirmation | Only when typ or numer wpisu has a value, saved or typed; lists only those fields | Fixing dozens of migrated scans needs no empty confirmations | Plan (user) |
| Erase rule authority | The server rejects an unconfirmed erase; the client predicate is a superset | „Silent erase" becomes testable in Pest and survives a UI bug | Plan |
| Typ aktu control | `Select` with a „brak" option | Four options do not fit a toggle at 390 px | Plan |
| Unsaved-changes guard | None in the source modals | The existing guard is person-specific and cannot track a file input | Plan |
| Design system | `Textarea` from shadcn; no new tokens | The only missing primitive; the warning uses `destructive` | Plan |

## Scope

**In scope:**

- Migration with seven nullable columns; `SourceRecordType` enum.
- Store with a description; new `update` endpoint with request, action and policy.
- Resource with the description and one `display_name`.
- `Textarea`, shared description fields, upload modal, edit modal with the erase confirmation.
- List cards and the preview panel next to the image.
- Removal of the upload page and its route (404 on the old address).
- Kitchen-sink states, `before/` and `after/` screenshots, written verdict.

**Out of scope:**

- Adding or editing a source from the person modal.
- Linking non-akt kinds and the kind next to a fact (S-18); matching akts first (S-19).
- Search by name; a link on list cards; edit or delete from cards.
- „Zapisz i dodaj kolejne", multi-upload, replacing a source's file.
- Backfilling names; new tokens; an automated screenshot test.

## Architecture / Approach

Contract before pixels. Phase 1 adds the data and the backend and then shoots the baseline, so before and after share the same seeded data. Phase 2 adds the missing shared components and the upload modal. Phase 3 adds the edit modal, whose erase rule is one tested pure function mirrored by a server rule. The akt-only fields stay mounted while hidden; the server drops them for any other kind.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract — the description in the data and the backend | Migration, enum, store with description, `update` endpoint, resource fields, Pest tests, seeder states, `before/` shots | The baseline is skipped again, as in S-16, and cannot be recovered later |
| 2. Upload modal and the list | `Textarea`, description fields, „Dodaj źródło" modal, new cards, display name everywhere, upload page removed | Removing the route without the numeric pattern turns the old address into a 500 |
| 3. Edit modal and the preview | „Edytuj źródło" with the erase confirmation, description panel next to the image | Confirm-then-submit ordering: the flag must be in the form before the programmatic submit |
| 4. Screenshot gate | `after/` shots in both themes and widths, `verdict.md`, copy check, full suite | Seeded sources have no files, so the panel next to a real image needs one real upload |

**Prerequisites:** S-16 and S-24 are done. Sail is running. Playwright is available in the npx cache for the screenshot script.
**Estimated effort:** about 3 sessions across 4 phases. Phase 1 is the largest on the backend, Phase 3 the most delicate on the frontend.

## Open Risks & Assumptions

- Until S-18, a fact can read „Potwierdzone aktem" while confirmed by a source whose kind was changed to gravestone, and such a source cannot be re-linked once unlinked. Accepted by the user.
- Closing a source modal with Esc or X discards typed text without a prompt.
- The link field is outside prd-v3; it is a small additive column, and the PRD is not updated by this plan.
- The rollback is code-only (revert on `main` and redeploy): `migrate:rollback` is prohibited in production, so the description columns stay in the database and no description is lost.
- `CLAUDE.md:58` still names `components/scans/delete-scan-dialog.tsx` as the delete pattern; the real file is `components/sources/delete-source-dialog.tsx`. Not fixed here.

## Success Criteria (Summary)

- A gravestone photo is added in a modal with only a file and a kind, and an akt can be saved with its type, entry number, provenance, link and note.
- The entry number and the note are readable next to the image, and a source is found on the list by its name, type, year and provenance.
- Changing an akt to another kind never removes its type or entry number without an explicit confirmation, and no fact changes its confirmed state.
