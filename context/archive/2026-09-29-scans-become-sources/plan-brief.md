# Scans Become Sources — Plan Brief

> Full plan: `context/changes/scans-become-sources/plan.md`

## What & Why

Roadmap S-16 (prd-v3 FR-005, FR-009, FR-010, FR-011). „Skan” is too narrow a word: the author has gravestone photos with dates and nowhere to put them as evidence. „Skany” become „Źródła” with a kind (akt metrykalny / zdjęcie nagrobka / inne). Every existing scan becomes akt metrykalny. Every later source slice (S-17, S-18, S-19) builds on this.

## Starting Point

- **Data.** `scans` + `event_scan`. Files sit at `scans/{tree}/…` on the private `local` disk, and the full path is stored on the row. „Potwierdzone” is never stored; it is derived as "≥1 pivot row".
- **UI.** A separate upload page, and a delete dialog that always shows the long form with an impact list. The fact picker accepts any scan.

## Desired End State

- The sidebar says „Źródła” (`/sources`). Every old file is there as an akt metrykalny, with its thumbnail and zoom.
- A new source is uploaded with a required kind, and the kind shows as a badge on the list and on the preview page.
- Deleting an unlinked source is a short „Usunąć źródło?”. A linked one still lists the facts that lose confirmation.
- The fact picker offers only akts until S-18.
- Every file lives under `sources/{tree}/…`. Every fact is confirmed after the deploy exactly when it was before.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Rename depth | Full: tables `sources`/`event_source`, model `Source`, routes `/sources`, components, types | S-17 – S-19 build on „source” with no translation layer; prd-v3 allows `/scans` to break | Plan |
| Upload until S-17 | Keep the page, add a required kind (`ToggleGroup`), with the field extracted as `source-kind-field.tsx` | S-16 stays focused on the migration; S-17 moves the field into its modal unchanged | Plan (roadmap unknown) |
| Linking before S-18 | Only akt metrykalny, enforced by `EventSourceStoreRequest` and `availableSources` | The confirmation rule and „Potwierdzone aktem” stay true; S-18 changes the rule in one place | Plan |
| Files on disk | Move everything to `sources/`, row by row, idempotent, in its own non-transactional migration | One clean prefix (user's choice); per-row consistency makes a crash resumable instead of corrupting | Plan (user overrode the "keep `scans/`" recommendation) |
| Existing data | Table rename keeps the pivot rows; `kind` backfilled to `metrical_record` | Confirmation is derived from pivot rows, so it carries over 1:1 with no data copy | PRD v3 + Plan |
| Delete | Short confirmation without links; impact list with links | prd-v3 FR-011 | PRD v3 |
| Design system | No new tokens; `Badge` + `ToggleGroup` from `components/ui` | The kind is a label, not a new visual concept | Plan |
| Old URLs | `/scans` returns 404, no redirects | prd-v3 Non-Goals: the author is the only user | PRD v3 |

## Scope

**In scope:**

- `SourceKind` enum.
- Two migrations: schema, and the file move.
- Full backend and frontend rename.
- Required kind on upload, and the kind badge.
- Akt-only linking.
- Two-variant delete.
- Copy „skan” → „źródło” / „akt”.
- Kitchen sink and screenshot gate.
- Deploy runbook (full backup, confirmed-count check).

**Out of scope:**

- Upload modal, description fields and kind editing (S-17).
- Linking every kind and showing the kind next to a fact (S-18).
- Matching akts first in the picker (S-19).
- `/scans` redirects and multi-upload.
- Renaming Postgres sequence, index and constraint names.
- Delete from the list cards.

## Architecture / Approach

Contract before pixels.

1. Phase 1 changes schema, files and backend atomically, because the table rename forces model, routes and Wayfinder to change together. The frontend gets only the mechanical rename, and a hidden `kind=metrical_record` keeps upload working.
2. Phase 2 adds the visible behaviour.
3. Phase 3 renders every state from `TreeViewDemoSeeder`, screenshots it against a `before/` baseline from `main`, and records the deploy checks.

The file move uses only `DB::table` and `Storage`, never models, so later code changes cannot break it. Each row either moves with its files or stays untouched.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract — sources in the data and the backend | Migrations, `Source` + kind, `/sources` routes, akt-only linking, renamed and new Pest tests, compiling frontend | File move leaves a row pointing at a missing file; mitigated by per-row, idempotent moves and a test for each failure mode |
| 2. The view — kind, akt-only picker and the short delete | Kind choice on upload, kind badges, „akt” copy in the picker, two-variant delete, all „skan” copy gone | Copy drift: a leftover „skan” or the wrong „akt” vs „źródło” in one of ~10 components |
| 3. Kitchen sink, screenshot gate and deploy | All kinds and delete states in the seeder, before/after screenshots with `verdict.md`, deploy runbook | `deploy.sh` backs up only the DB; the full backup is a manual step that must not be skipped |

**Prerequisites:** S-02, S-03 and S-08 are done (roadmap). Sail is running. The npx cache has Playwright for the screenshot script.
**Estimated effort:** about 2–3 sessions. Phase 1 is the largest (~40 files, mostly mechanical).

## Open Risks & Assumptions

- `deploy.sh` ran `migrate` as root, so the file move would create `root:root 0700` directories that PHP (`sail`) cannot read. Phase 3 switches migrate to `-u sail`, and the runbook checks directory ownership plus a test upload.
- The file move runs inside `migrate --force` during deploy. A slow or failing filesystem stops the deploy after the schema migration. The rows stay consistent, and re-running `migrate` resumes the move.
- The confirmed count is checked by SQL before and after the deploy (runbook in `change.md`). An automated test covers the rename path, but not real production data.
- A gravestone uploaded after S-16 cannot confirm a death until S-18 ships. This is accepted.

## Success Criteria (Summary)

- Every old scan is visible in „Źródła” as akt metrykalny, with its thumbnail and zoom, and every fact keeps its „potwierdzone/niepotwierdzone” state.
- A gravestone photo can be uploaded as „zdjęcie nagrobka” and deleted with the short confirmation.
- Production has no files or rows left under `scans/`, and the pre-deploy full backup is listed.
