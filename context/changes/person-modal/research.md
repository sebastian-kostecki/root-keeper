---
date: 2026-09-29T09:51:09+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: 90eea8d6ff32d66cda91b89f01454175bb7b9c7d
branch: main
repository: root-keeper
topic: "Person modal variant B — one tabbed modal for preview/create/edit with per-section editing and a single free-text date field"
tags: [research, codebase, person-preview, person-form, partial-date, form-dialog, design-system]
status: complete
last_updated: 2026-09-29
last_updated_by: Claude (Opus 5.5)
---

# Research: Person modal variant B

**Date**: 2026-09-29T09:51:09+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: 90eea8d6ff32d66cda91b89f01454175bb7b9c7d
**Branch**: main
**Repository**: root-keeper

## Research Question

The user picked variant B of the person modal on the Design canvas
(https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F, boards `PersonModal-B`, `PersonModal-B-Zakladki`,
`PersonModal-B-Stany`, `PersonModal-B-Mobile`). Variant B has:
- one modal for preview, create and edit;
- a header with name, lifespan and chips, plus the actions „Pokaż na drzewie” and „⋯”;
- tabs Przegląd / Rodzina (n) / Źródła (n);
- cards that are each edited on their own, with a pencil, inline Anuluj/Zapisz and a guard for unsaved changes;
- one free-text date field that parses Polish genealogy notation (`1948`, `marzec 1948`, `12.3.1948`, `ok. 1948`, `~1948`).

What in the current code, backend contract and prior decisions does this redesign touch, reuse, or conflict with?

## Summary

- **Frontend (today).** Four separate pieces make up the person UI:
  - the preview modal `PersonPreviewDialog` → `PersonPreview`;
  - the create/edit form dialog `PersonFormDialog` → `PersonFormFields`, which opens *on top of* the preview for edit;
  - the three-number date input `PartialDateFields`;
  - per-fact cards (`EventCard`, `RelativesCard`, `MarriagesSection`).

  `PersonFormFields` and `PartialDateFields` have callers outside the modal: the tree „dodaj nową osobę” flow and the marriage edit dialog. Changing them therefore changes those flows too (see Detailed Findings §1).
- **Per-section save is not supported by the backend as-is.** `UpdatePerson` writes a missing key as `null`, and a missing `is_deceased` deletes the death event (`app/Actions/Persons/UpdatePerson.php:46-60`). A section can save through the existing `PUT persons/{person}` only if the client sends the full current `personForm` with that section's edits merged in. The alternative is backend changes. Marriage date/place (`PUT marriages/{id}`) and scan link/unlink (`POST|DELETE events/{event}/scans`) already have their own endpoints, so those cards are per-section today.
- **Date contract.** The server takes `{prefix}.year|month|day|is_approximate|place` with these rules (`app/Concerns/PartialDateValidationRules.php:21-27`):
  - a month requires a year and a day requires a month;
  - year is between 1000 and the current year;
  - `is_approximate` requires a year;
  - `checkdate` rejects impossible dates, with the error on `{prefix}.day` (`:33-50`).

  No date parser exists on either side (server: `app/Support/PartialDate.php` formats only; frontend: no date helper in `resources/js/lib/`). A client-side parser that emits the same parts keeps every existing request and feature test valid.
- **Design system.**
  - Present: `dropdown-menu` and `toggle-group` (installed, currently unused), `badge`, `card`, `select`, `checkbox`, `form-dialog`, and the gender tokens from variant C.
  - Missing: `tabs`, `switch`, `popover`, `alert-dialog`. None of their Radix packages is installed (`package.json:18-30`).
- **Prior decisions this must honour:**
  - relations are added only on the tree;
  - relation removal and marriage editing stay in the preview;
  - opening a scan leaves the modal;
  - saving must not drop scan links;
  - the „Potwierdzone aktem” hint is shown, not a block;
  - `?person=` plus the „Wstecz” stack drive the modal;
  - `store` redirects to `tree.show(new)`.

  One variant B board note conflicts with the last point (Open Questions Q3).

## Detailed Findings

### 1. Frontend consumers

**`PersonFormDialog`** (`resources/js/components/persons/person-form-dialog.tsx`)
- Modes: `mode: 'create'|'edit'`.
- Title „Dodaj osobę” / „Edytuj osobę” (`:50`).
- Submit `data-test` values: `create-person-button` / `update-person-button` (`:89-93`).
- Scrolls to the first error on a failed submit (`:37-43`).
- Callers:
  - `resources/js/pages/persons/index.tsx:63-68` (create, „Dodaj osobę” button)
  - `resources/js/components/persons/person-preview.tsx:35-45` (edit, „Edytuj” button inside the preview)

**`PersonFormFields`** (`resources/js/components/persons/person-form-fields.tsx`)
- Props: `lockedGender`, which disables the Select and still posts a hidden input, and `defaults.last_name`.
- Contains the „Zmarły/a” checkbox, and the death fields render only when it is checked.
- Shows `CONFIRMED_HINT` for confirmed birth/death.
- Callers (2):
  - `person-form-dialog.tsx:70-75`
  - `resources/js/components/tree/pick-or-create-person-dialog.tsx:214-220`, create only, with `lockedGender`/`defaults` from `parent-slot-dialog.tsx:95-102`, `add-child-dialog.tsx:77-88` and `add-spouse-dialog.tsx:91-99`

**`PartialDateFields`** (`resources/js/components/persons/partial-date-fields.tsx`)
- Three `type="number"` inputs, a place input and an „około” checkbox.
- Posts `${prefix}.day|month|year|place|is_approximate`.
- Callers (3):
  - `person-form-fields.tsx` for birth and death
  - `resources/js/components/persons/marriage-form-dialog.tsx:47-57` (`prefix="marriage"`, fed from `marriage.date_parts`)

**Preview**
- `PersonPreviewDialog` (`person-preview-dialog.tsx`):
  - hosted only by `resources/js/layouts/app-layout.tsx:14-18`;
  - reads `personPreview` and `availableScans` from `usePage().props`;
  - builds the `ur. X · zm. Y` lifespan (`:25-30`);
  - shows „Wstecz” from the provider stack.
- `PersonPreview` (`person-preview.tsx`) composes the preview; its only caller is the dialog:
  - the Probant badge or `SetRootPersonButton`, and „Pokaż na drzewie”;
  - the edit dialog and `DeletePersonDialog`;
  - an info Card with residence, gender and religion;
  - `RelativesCard` for parents and children;
  - `EventCard` Urodzenie, `MarriagesSection` and `EventCard` Zgon.
- `EventCard` (`event-card.tsx`) holds:
  - `EventSourceStatus`;
  - the `ScanPickerDialog` „Powiąż skan”;
  - the date/place grid;
  - the scan thumbnails with `UnlinkScanDialog` „Odepnij”, which is the content variant B moves to the Źródła tab.

**Navigation**
- `PersonPreviewLink` has 6 callers:
  - `pages/persons/index.tsx:98`
  - `pages/tree/show.tsx:104`
  - `pages/scans/show.tsx:82`
  - `components/tree/sheet-person-card.tsx:73`
  - `relatives-card.tsx:34`
  - `marriages-section.tsx:38`
- These callers do not depend on the modal's inner layout.

**Duplicated lifespan string.** The `ur. X · zm. Y` join is rebuilt in 4 places:
- `person-preview-dialog.tsx:25-30`
- `relatives-card.tsx:39-50`
- `marriages-section.tsx:43-57`
- `pages/persons/index.tsx:108-115`

This is a candidate for a small shared helper (accidental architecture).

**DOM-coupled checks.**
- No Pest test asserts on DOM: there is no `tests/Browser`, and grep of `tests/` finds no `data-test` or `#birth-*`.
- The archived screenshot scripts do not touch the person form or the preview. They were checked in:
  - `context/archive/2026-09-28-tree-family-sheet-ui/screenshots/shots.mjs`
  - `context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs`

### 2. Backend contract

**Routes** (`routes/web.php`)
- `:18`: persons `index|store|update|destroy`, with no show, create or edit.
- `:22`: root person.
- `:28-29`: event scan link/unlink.
- `:31-37`: relation create endpoints.
- `:39`: marriages `store|update|destroy`.

**Rules** (`PersonStoreRequest.php:22-40`; `PersonUpdateRequest` extends it)
- At least one of first/last name is required on every payload (`:30-31`). A birth-only save still has to carry the name.
- Every `death.*` rule gets `prohibited_unless:is_deceased,1,true` (`:24-27`).
- Update adds one extra check: unchecking „Zmarły/a” while the death has scans fails with „Najpierw odepnij skany od zgonu na podglądzie osoby.” (`PersonUpdateRequest.php:23-44`).

**Missing keys = clear** (`UpdatePerson.php:46-60`, verified)
- first_name, last_name, gender, residence and religion use `?? null`.
- Birth is `putEvent(..., $data['birth'] ?? [])`, which nulls every part.
- A falsy or missing `is_deceased` deletes the death event.
- The birth/death event is updated in place (`:69-87`), so its id and scan links survive a full-form save. `PersonsTest.php:601` pins this.
- No test covers an update with a missing `birth` or `religion` key. The nulling behaviour above comes from reading the code.

**Marriage.** `MarriageUpdateRequest` has only `partialDateRules('marriage')` plus `validateFullDate`. `UpdateMarriage` (`app/Actions/Relations/UpdateMarriage.php:18-23`) is a full replace of date and place, and leaves participants and scans alone.

**Read path**
- `personPreview` is a shared-prop closure (`app/Http/Middleware/HandleInertiaRequests.php:50-54`), resolved from `?person=` (`:67-87`).
- It is built by `app/Actions/Persons/BuildPersonPreview.php:25-36`, which returns `{person, personForm, genders, religions, isRootPerson}`.
- `availableScans` is `Inertia::optional` (`:55-57`) and loaded only by the scan picker.
- The per-event scan lists already exist: `person.birth.scans`, `person.death.scans`, `person.marriages[].scans`.
- Neither the per-person scan total nor the relatives count exists on the server. Both can be derived on the client:
  - scans: the distinct scan ids across those lists, because one scan may be linked to several events;
  - relatives: `parents.length + children.length + marriages.length`.

**Feature tests that pin the contract**
- Date validation (store): `tests/Feature/PersonsTest.php:317-381`.
- Update behaviour: `PersonsTest.php:425, 571, 601, 622, 643, 658, 678, 826`.
- Preview props: `PersonsTest.php:531, 739-799`.
- Marriage: `tests/Feature/MarriagesTest.php:62, 77, 196`.
- Scan links: `tests/Feature/EventScansTest.php`.
- A client-side parser that keeps the payload shape leaves all of these valid.

### 3. Date model and parser surface

- **Storage.** Columns `date_year`, `date_month`, `date_day` and `date_is_approximate` (`app/Models/Event.php:29, 40-46`).
- **Display.** `PartialDate::format()` gives `DD.MM.YYYY` | `MM.YYYY` | `YYYY`, prefixed with `ok. ` (`app/Support/PartialDate.php:19-34`).
- **Precision rule.** Full, month+year or year only; „około” at any precision, but only with a year (`context/archive/2026-09-23-account-tree-and-first-person/plan-brief.md:22`, validation `plan.md:295-303`).
- **Checkbox pitfall.** It must post `"1"`, not Radix's default `"on"` (`account-tree-and-first-person/plan.md:295-303`, `reviews/impl-review.md:77`). With a text field, `is_approximate` comes from the parser rather than a Checkbox, so this pitfall moves into the hidden-input serialisation.
- **Parser.** None exists in the repo, so it is new code with no precedent. Inputs on the Design canvas (board `PersonModal-B`, date strip):

  | Input | Parsed result |
  |---|---|
  | `1948` | year |
  | `marzec 1948` | 03.1948 |
  | `12.3.1948` | 12.03.1948 |
  | `ok. 1948`, `~1948`, `około 1948` | approximate |
  | `12.3.48` | warning: two-digit year |
  | anything unrecognised | error |

  The server stays the authority on range and `checkdate`. The client should still show the server error under the single field. Today the server error keys are `{prefix}.day|month|year`, so the new field has to map all three keys onto itself.

### 4. Design system realisation

- **Primitives in `resources/js/components/ui/`** (26 files):
  - present: alert, avatar, badge, breadcrumb, button, card, checkbox, collapsible, dialog, dropdown-menu, icon, input-otp, input, label, navigation-menu, placeholder-pattern, select, separator, sheet, sidebar, skeleton, sonner, spinner, toggle-group, toggle, tooltip;
  - missing from this set: `tabs`, `switch`, `popover`, `alert-dialog`.
- **Installed Radix packages** (`package.json:18-30`): avatar, checkbox, collapsible, dialog, dropdown-menu, label, navigation-menu, select, separator, slot, toggle, toggle-group, tooltip.
  - Variant B's tabs need either a new `@radix-ui/react-tabs` dependency (needs approval per CLAUDE.md) or a hand-rolled ARIA tablist. The precedent for hand-rolling is `resources/js/components/appearance-tabs.tsx`, a button group without tablist semantics.
  - The „Osoba nie żyje” switch can be the existing `checkbox` or `toggle`.
  - The gender segmented control maps to `toggle-group`.
- **`form-dialog.tsx`** exports `FormDialogContent/Header/Body/Footer`. It is full screen below `sm` and `sm:max-w-2xl sm:max-h-[90vh]` above, with pinned header and footer and overlay clicks prevented (`:14-29`). `FormDialogHeader` has a `leading` slot for „Wstecz” (`:31-61`).
- **Tokens** (`resources/css/app.css:63-71` theme map; light `:109-120`, source comment `:114`; dark `:157-165`):
  - `--gender-male|female|unknown`
  - `--gender-{male,female}-surface|border`
  - `--tree-line`, `--tree-meta`

  The design's relative rows (blue/pink cards) use exactly these.
- **`genderCard`.** `resources/js/components/tree/card-styles.ts:9-13` maps gender to surface, border and hover classes. It is recorded as a *tree* primitive (`context/archive/2026-09-29-tree-sheet-variant-c/audit.md:18`). Reusing it in the modal makes it cross-domain, so it becomes a candidate to move to a shared location (accidental architecture).
- **No new colour token is needed** for variant B. The canvas boards use only primary, muted, border, ring, destructive and the gender/tree tokens.

## Code References

- `resources/js/components/persons/person-preview.tsx:25-120`: preview composition that will be replaced by tabs.
- `resources/js/components/persons/person-form-dialog.tsx:15-105`: create/edit dialog, layered over the preview for edit.
- `resources/js/components/persons/person-form-fields.tsx`: fields shared with the tree create flow (`pick-or-create-person-dialog.tsx:214-220`).
- `resources/js/components/persons/partial-date-fields.tsx`: the 3-number date input; 3 call sites including `marriage-form-dialog.tsx:47-57`.
- `resources/js/components/persons/event-card.tsx`: scan link/unlink UI (the future Źródła tab).
- `resources/js/components/persons/person-preview-provider.tsx:23-33`: `previewVisitOptions`, `?person=` handling, „Wstecz” stack.
- `resources/js/components/form-dialog.tsx:14-90`: shared modal shell.
- `resources/js/components/tree/card-styles.ts:9-13`: `genderCard`.
- `app/Actions/Persons/UpdatePerson.php:46-60`: full-replace update (missing key → null, missing `is_deceased` → delete death).
- `app/Http/Requests/PersonStoreRequest.php:22-40`, `PersonUpdateRequest.php:23-44`: rules and the death-with-scans guard.
- `app/Concerns/PartialDateValidationRules.php:21-50`: date rules and `validateFullDate`.
- `app/Actions/Persons/BuildPersonPreview.php:25-36`, `app/Http/Middleware/HandleInertiaRequests.php:50-87`: preview payload.
- `app/Actions/Relations/UpdateMarriage.php:18-23`, `routes/web.php:28-29, 39`: marriage and scan-link endpoints.

## Architecture Insights

- **Modal state lives in the URL** (`?person=`), and the modal is a shared Inertia prop. Every mutation redirects back and the preview re-renders from fresh props, which gives a pattern for per-section saves: after a successful save the card leaves edit mode and shows the refreshed prop.
- **Charges visible so far**, in the lesson's three categories:
  - *Missing shared component:*
    - a free-text partial-date input with parser and error mapping (replaces `PartialDateFields` at 3 call sites);
    - an editable section card (view ⇄ edit with inline actions);
    - tabs.
  - *Accidental architecture:*
    - 4 copies of the lifespan join;
    - `genderCard` living under `tree/` while the modal needs it;
    - edit layered as a second dialog on top of the preview.
  - *Missing tokens:* none found. Variant B fits the existing tokens.

## Historical Context (from prior changes)

- `context/archive/2026-09-28-person-preview-in-modal/plan-brief.md:21-25`:
  - `?person=` plus the shared prop; open, swap and close replace history; „Wstecz” is a client stack (settled).
  - Redirects: update goes back; destroy goes to the referrer minus `person`; store goes to `tree.show(new)` (settled).
- `context/archive/2026-09-28-person-preview-in-modal/plan.md:22, 59, 272`:
  - the edit form opens on top of the preview;
  - overlay clicks never dismiss the preview.

  This layering is history, not a recorded preference. No document argues that preview and form must stay separate.
- `context/archive/2026-09-28-person-form-in-modal/plan-brief.md:25-27`, `plan.md:38, 71`:
  - the FormDialog shell; overlay clicks do not close it;
  - no „discard changes?” prompt, **deferred** (not rejected). Variant B's unsaved-changes bar picks this up.
- `context/foundation/prd.md:93, 106, 110, 128`:
  - FR-008: the preview shows relations without add actions, keeps removal, and edits marriage date/place.
  - FR-007: relation and marriage removal happen in the preview.
  - FR-010: opening a scan leaves the modal.
  - One place per add or remove action.

  All settled.
- `context/archive/2026-09-29-preview-relations-read-only/plan-brief.md:7, 21, 25`: the tree is the only place to add relations; empty sections hint „Dodasz ich na drzewie.” S-28 (removal on the tree) was dropped (`context/foundation/roadmap.md:32`).
- `context/archive/2026-09-25-person-edit-and-delete/plan-brief.md:22-26`: the „Potwierdzone aktem” hint (a hint, not a block); no silent unlink; events updated in place.
- `context/archive/2026-09-24-birth-death-confirmed-by-scan/plan-brief.md:24-32`: confirmed means ≥1 linked scan; linking is per event card; „Odepnij” needs confirmation.
- `context/archive/2026-09-25-marriage-with-shared-source/plan-brief.md:21-29`: one marriage event per couple; editing must not drop the certificate; the date rules were extracted to the shared trait.
- `context/foundation/prd-v3.md:103`, `roadmap.md:304`: the long form with partial dates was unreadable on a phone. It was fixed with the full-screen modal, not with a new date input. No document mentions free-text dates or calls the three inputs a pain point, so this is a new decision.
- Roadmap items that will land in this modal later: S-17 source description and S-18 fact confirmed by any source (`roadmap.md:80-81, 334-357`); S-14 residence history (`roadmap.md:269-281`); S-16 renames „skan” to „źródło” (`roadmap.md:79`). The Źródła tab should leave room for a source type per fact.
- `context/archive/2026-09-29-tree-sheet-variant-c/`: tokens (`tokens.md:10-24`), `genderCard`, screenshot gate (`screenshots/shots.mjs`, `verdict.md`). Still deferred there: D2, a second gender cue beyond colour (`audit.md:101-104`). The modal's relative rows and chips inherit that gap unless the gender text chip covers it.

## Related Research

- `context/archive/2026-09-29-tree-sheet-variant-c/audit.md`: audit of the family sheet (tokens, card-styles).
- `context/archive/2026-09-28-graphical-tree-view/research.md`: origin of the gender colours (not re-read here).

## Open Questions

For `/10x-plan`:

**Q1. Per-section save mechanism.**
- (a) The client merges the section into the full `personForm` and posts to the existing `PUT persons/{person}`. No backend change, and all tests stay valid.
- (b) Backend partial update, with `sometimes` rules plus `array_key_exists` in `UpdatePerson`. This changes the behaviour pinned at `PersonsTest.php:643`.

Recommendation already discussed with the user: (a).

**Q2. Tabs primitive.**
- Add `@radix-ui/react-tabs` via shadcn `tabs` (a new dependency, which needs approval), or
- hand-roll an ARIA tablist (precedent: `appearance-tabs.tsx`, which lacks tablist semantics).

**Q3. What happens after „Dodaj osobę”.** The canvas note on `PersonModal-B` says „Po zapisaniu modal przejdzie w podgląd tej osoby”. The settled redirect is `tree.show(new)` (FR-003). One option that satisfies both is `tree.show(new)?person=new`, but it needs a product decision.

**Q4. Scope of the date field.**
- Replace `PartialDateFields` at all 3 call sites, which puts the marriage dialog in scope. `MarriageFormDialog` is a plain `DialogContent`; in variant B marriage editing becomes a card in Rodzina.
- Replace `PersonFormFields` in the tree create flow (`pick-or-create-person-dialog.tsx`) too, or leave the tree flow on the old fields for now.

**Q5. Parser rules.**
- Accepted grammar: Polish month names, including genitive (`12 marca 1948`) and abbreviations; separators `.` `/` `-` and space; approximate markers `ok.`, `około`, `~`, `ca`.
- Two-digit year: warn, never guess.
- Whether to support ranges or „przed/po” (not supported by the data model, so they should be rejected).

**Q6. Marriages.** The canvas shows marriages both in Przegląd and in Rodzina. The plan should decide whether they are duplicated or live in one tab only.

**Q7. Unsaved-changes guard coverage.** Should it also cover closing the modal (X/Esc) and switching tabs while a card is dirty? That was deferred in `person-form-in-modal/plan.md:38`.
