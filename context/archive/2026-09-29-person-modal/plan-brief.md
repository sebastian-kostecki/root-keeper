# Person modal (variant B) — Plan Brief

> Full plan: `context/changes/person-modal/plan.md`
> Research: `context/changes/person-modal/research.md`

## What & Why

The person modal is being rebuilt as variant B from the Design canvas. It becomes one modal with:
- a header showing identity, chips and a „⋯” menu;
- tabs Przegląd / Rodzina / Źródła;
- cards that each edit on their own, with a guard for unsaved changes;
- a single free-text date field that reads Polish genealogy notation (`12 marca 1948`, `ok. 1948`, `12 III 1948`).

The goal is to fix one fact quickly, without opening a whole form layered over the preview, and to type dates the way they are copied from records.

## Starting Point

Today's preview (`person-preview.tsx`) is a stack of cards with an „Edytuj” button. That button opens a second full-form dialog on top (`person-form-dialog.tsx`). Dates are three number inputs plus a checkbox (`partial-date-fields.tsx`), used by person birth/death, the marriage dialog and the tree „dodaj nową osobę” flow. The backend update is a full replace: missing keys clear data (`UpdatePerson.php:46-60`).

## Desired End State

Opening a person shows the variant B header and tabs:
- **Przegląd** has cards for personal data, birth, each marriage and death. Each card edits in place through its pencil, one at a time. Empty events offer „Uzupełnij urodzenie” / „Oznacz zgon”.
- **Rodzina** lists parents, spouses and children as gender-coloured rows that open the relative. Removal sits under „⋯”.
- **Źródła** groups linked scans by event, with open, unlink and link actions.

Any exit from a card with unsaved changes asks „Porzuć / Zapisz”. On phones, section editing is full screen with a pinned Zapisz. „Dodaj osobę” opens the same shell with every card open and lands on the new person's tree with the preview open. Every date input in the app is the new text field.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Per-section save | Client merges the section into the full `personForm` → existing `PUT persons/{id}` | No backend partial-update semantics; every existing update test stays valid. | Research |
| Tabs primitive | Add shadcn `tabs` (`@radix-ui/react-tabs`); „Osoba nie żyje” stays `Checkbox` | Correct tablist ARIA for one approved dependency; no switch package. | Plan |
| After create | `store` → `/tree/<id>?person=<id>` | Honours FR-003 (tree) and the board („przejdzie w podgląd”). | Plan |
| Date field scope | Replace `PartialDateFields` everywhere (modal, marriage, tree create flow) | One date notation across the app; old component deleted. | Plan |
| Marriages | Przegląd owns the editable marriage fact card; Rodzina shows the spouse row read-only plus „⋯ Usuń małżeństwo” | One place to edit, one to remove. | Plan |
| Guard coverage | All exits in the modal: pencil, tab, X/Esc, Wstecz, relative link, Pokaż na drzewie, ⋯ items, Źródła actions | A half-typed edit never vanishes silently; closes the deferred discard prompt. | Plan |
| Payload gaps | Add relative/spouse `gender` and linked-scan `uploaded_at`; no co-parent, no scan descriptions | Enables gender rows and „dodano” with tiny tested changes; descriptions wait for S-17. | Plan |
| Mobile edit | Full-screen section edit below `sm` with pinned footer | Keyboard never hides Zapisz (the prd-v3 phone pain point). | Plan |
| Parser grammar | Board notations plus Roman-numeral months, `ca`, separators `. / -` and space, case- and diacritic-insensitive; reject two-digit years, ranges, przed/po, seasons | Matches how metrical-book dates are transcribed; server keeps range and `checkdate`. | Plan |
| Parser tests | Vitest through `vp test` (bundled with vite-plus) + CI step | Parser is new code with no precedent; no new dependency needed. | Plan |

## Scope

**In scope:**
- Variant B header, the „⋯” menu and three tabs.
- Per-section editing with the guard; the mobile edit view.
- Create mode on the same shell.
- A text date input and parser, used everywhere.
- Relative/spouse gender and scan `uploaded_at` in the payload; the create redirect.
- A shared lifespan helper; `genderCard` moved to a shared module.
- Seeded kitchen sink and screenshot gate.

**Out of scope:**
- A backend partial update.
- A switch primitive; new tokens.
- Co-parent hints; scan descriptions (S-17).
- Adding relations in the modal.
- Marriage date editing in Rodzina.
- A guard for browser navigation (reload or browser back).
- Date ranges, „przed/po”.
- Redesigning the tree create dialog's layout.
- S-14, S-16, S-18.

## Architecture / Approach

The modal stays URL-driven (`?person=`, shared `personPreview` prop, „Wstecz” stack unchanged).

`PersonPreviewDialog` hosts `PersonModalHeader` and `PersonModal`, which holds the tabs. A `SectionEditorProvider` owns which card is editing, whether it has unsaved changes, and the pending exit. Every exit calls `requestExit(action)`.

`SectionCard` switches between view and edit, using section field groups that are also reused by create mode and the tree's `PersonFormFields`.

Person sections save through `personFormToPayload` + a section overlay, sent as `PUT persons/{id}`. Marriage cards save with `PUT marriages/{id}`. After a save, the redirect back refreshes the prop, and the card returns to view mode.

`lib/partial-date.ts` parses and formats dates, and `PartialDateInput` posts the unchanged `{prefix}.year|month|day|is_approximate|place` keys.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Contract | `gender`, `uploaded_at`, create redirect + Pest | `tree/{person}` param collides with the `?person=` query key |
| 2. Date parser + input | `lib/partial-date.ts`, Vitest, `PartialDateInput` at all 3 call sites | Grammar edge cases (Roman `I` vs `1`, genitive forms) |
| 3. Modal shell, read-only tabs | Header, ⋯ menu, Przegląd/Rodzina/Źródła, helpers, shadcn tabs | Dialogs opened from dropdown items (controlled mode) |
| 4. Per-section edit + guard | Section cards, merged-payload save, guard over all exits, mobile edit | Dropping death/scans through an incomplete merged payload |
| 5. Create mode | „Nowa osoba” shell; tree `PersonFormFields` on shared fields | Tree flow's locked gender and hidden relation inputs |
| 6. Screenshot gate | Seeder additions, `shots.mjs`, `verdict.md` | Seeder additions shifting variant C id offsets |

**Prerequisites:** Sail up; approval recorded here for the `@radix-ui/react-tabs` dependency; Playwright available from the npx cache (as for variant C).
**Estimated effort:** ~4–6 sessions across 6 phases (Phases 2 and 4 are the heavy ones).

## Open Risks & Assumptions

- Assumes the merged payload always carries `is_deceased` and `death` from the current prop unless the Zgon card is being saved. A mistake here silently deletes death events, so manual check 4.6 exists for that.
- Dirty detection compares serialised form values at edit start with current values. Controlled widgets (ToggleGroup, Select) must mirror into hidden inputs for that comparison to see them.
- The guard does not protect against a browser reload or browser back (accepted).
- The persons list lifespan changes from `ur. X · zm. Y` to `X – Y` through the shared helper.

## Success Criteria (Summary)

- A single field (for example the death place) can be fixed in two clicks without losing any other data or scan link.
- Dates typed as `12 marca 1948`, `ok. 1948` or `12 III 1948` save correctly everywhere; `12.3.48` and free text are refused with a clear hint.
- The screenshot verdict shows every board state (light, dark, 390 px) passing, with no open fail.
