# Person modal (variant B) Implementation Plan

## Overview

Replace the person preview modal and the create/edit dialog layered on top of it with variant B from the Design canvas (https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F, boards `PersonModal-B`, `-Zakladki`, `-Stany`, `-Mobile`).

Variant B is one modal for both preview and create. It has:
- a header with identity, chips, „Pokaż na drzewie” and a „⋯” menu;
- tabs Przegląd / Rodzina (n) / Źródła (n);
- cards that each edit on their own, with a guard for unsaved changes;
- one free-text date field that understands Polish genealogy notation.

The free-text date field replaces the three-number date input everywhere in the app. This is a UI change with a design-system contract. The backend contract barely changes.

## Current State Analysis

Per `context/changes/person-modal/research.md` (verified against the code while planning):

- **Preview.** `PersonPreviewDialog` (`resources/js/components/persons/person-preview-dialog.tsx:16-71`) renders `PersonPreview` (`person-preview.tsx:19-128`). The preview holds:
  - an action row: Probant/`SetRootPersonButton`, „Pokaż na drzewie”, „Edytuj”, „Usuń”;
  - an info Card;
  - two `RelativesCard`s;
  - the birth `EventCard`, the `MarriagesSection` and the death `EventCard`.
- **Edit.** Edit opens `PersonFormDialog` (`person-form-dialog.tsx:30-104`) *on top of* the preview. That dialog wraps `PersonFormFields` (`person-form-fields.tsx:39-193`), which the tree create flow also uses (`resources/js/components/tree/pick-or-create-person-dialog.tsx:214-220`).
- **Dates.** `PartialDateFields` (`partial-date-fields.tsx`) has three number inputs plus place and an „około” checkbox. It has 3 call sites: birth, death, and `MarriageFormDialog` (`marriage-form-dialog.tsx:47-57`).
- **Update is a full replace.** `UpdatePerson` (`app/Actions/Persons/UpdatePerson.php:46-60`) writes every missing key as `null` and deletes the death event when `is_deceased` is falsy. Birth and death events are updated in place, so their scan links survive (`:69-87`).
- **Date rules.** `app/Concerns/PartialDateValidationRules.php:142-175`:
  - a month requires a year, and a day requires a month;
  - the year is between 1000 and the current year;
  - `is_approximate` requires a year;
  - `checkdate` errors land on `{prefix}.day`.

  No date parser exists on either side.
- **Modal state.** The modal is driven by `?person=` and the shared `personPreview` prop (`app/Http/Middleware/HandleInertiaRequests.php:50-87`, `app/Actions/Persons/BuildPersonPreview.php:113-124`). The „Wstecz” stack lives in `person-preview-provider.tsx`.
- **After create.** `store` redirects to `tree.show(new)` (`app/Http/Controllers/PersonController.php:78`), which `tests/Feature/PersonsTest.php:250` pins.
- **Payload gaps against the boards:**
  - `RelativeResource` (`app/Http/Resources/RelativeResource.php:28-37`) and `MarriageResource` spouse (`MarriageResource.php:89-94`) carry no `gender`.
  - Linked scans in `EventResource.php:37` carry no `uploaded_at`.
- **Design system.**
  - Present: `dropdown-menu` and `toggle-group`, installed but unused; `badge`, `card`, `select`, `checkbox`, `form-dialog`; gender tokens `--gender-*`, `--tree-*` (`resources/css/app.css:63-71, 109-120, 157-165`).
  - Missing: `tabs`.
  - `genderCard` lives in the tree-only `resources/js/components/tree/card-styles.ts:9-13`.
  - The `ur. X · zm. Y` join is duplicated 4 times: `person-preview-dialog.tsx:25-30`, `relatives-card.tsx:39-50`, `marriages-section.tsx:43-57`, `pages/persons/index.tsx:108-115`.
- **Tooling.** There is no JS test runner script. `vp test` (Vitest, bundled with vite-plus 0.3, `node_modules/vite-plus/docs/guide/test.md`) is available with no new dependency. CI runs `npm run check` (`.github/workflows/lint.yml:48`) but no JS tests.
- **No `.ai/rules/` directory and no `context/foundation/lessons.md` exist**, so no committed rules apply.

## Desired End State

Opening any person (`?person=<id>`) shows the variant B modal:

- **Header.**
  - An initials avatar in the gender colour, the name, and a lifespan `12.03.1948 – ok. 2015`.
  - Chips for gender and religion, plus „Probant” for the proband.
  - „Pokaż na drzewie”, and „⋯” with „Pokaż na drzewie” / „Ustaw jako probanta” (not shown for the proband) / „Usuń osobę…”.
  - „Wstecz” when the stack is non-empty.
- **Przegląd.** Cards „Dane osobowe”, „Urodzenie”, one „Małżeństwo z X” per marriage, and „Zgon”.
  - Each card has a pencil that turns it into an inline form with Anuluj/Zapisz while the other cards dim.
  - Only one card edits at a time.
  - Empty birth shows „Nie znamy daty ani miejsca urodzenia.” + „Uzupełnij urodzenie”. A living person shows „Osoba żyje albo nie wiemy, że zmarła.” + „Oznacz zgon”. Both buttons open the card straight into edit mode.
- **Rodzina (n).**
  - Sections: Rodzice, Małżeństwa (read-only date and place, source status), Dzieci.
  - Rows are gender-coloured and open that relative's preview.
  - „⋯” on a row holds „Usuń relację” / „Usuń małżeństwo”.
  - The footer hint says relatives are added on the tree.
  - n = parents + marriages + children.
- **Źródła (n).**
  - One group per event (Urodzenie, each Małżeństwo, Zgon when present), showing date, place and status.
  - Scan rows show thumbnail, file name, „dodano DD.MM.YYYY”, Otwórz (leaves the modal) and Odepnij (confirmation).
  - Each group has „Powiąż skan” / „Powiąż kolejny skan”.
  - n = distinct linked scans; the counter is hidden when there are none.
- **Date field.** Every date is one text field with an interpretation line under it:
  - `1948` → „1948 tylko rok”;
  - `marzec 1948` → „03.1948 miesiąc i rok”;
  - `12.3.1948` → „12.03.1948 dokładna data”;
  - `ok. 1948` → approximate;
  - `12.3.48` → „1848 czy 1948? wpisz rok 4 cyframi”;
  - anything unrecognised → „Wpisz np. 1948, 03.1948 lub 12.03.1948”.

  This applies in the modal, the create mode and the tree „dodaj nową osobę” flow.
- **Unsaved-changes guard.** With unsaved changes in a card, every exit inside the modal shows the bar „Masz niezapisane zmiany w <sekcja>.” with Porzuć / Zapisz instead of acting: another pencil, a tab, X/Esc, „Wstecz”, a relative link, „Pokaż na drzewie”, and the „⋯” items.
- **Create.** „Dodaj osobę” on the persons list opens the same modal shell as „Nowa osoba” with every card open. It saves to `store`, which lands on `tree.show(new)?person=new`, so the new person's preview is open over their tree.
- **Mobile.** Below `sm` the modal is full screen (as today). An editing card takes over the modal body („Edycja sekcji” header, tabs hidden) with Anuluj/Zapisz pinned in the footer.

Verification:
- Pest (`PersonsTest`, `MarriagesTest`, `EventScansTest`);
- Vitest for the parser (`npm run test:js`);
- `npm run check`, `types:check`, `build`;
- the screenshot gate in Phase 6, judged against the four canvas boards.

### Key Discoveries:

- A section can save through the existing `PUT persons/{person}` only when the client sends the full current `personForm` with the section merged in (`UpdatePerson.php:46-60`). Missing keys clear data.
- `PersonUpdateRequest.php:23-44` uses `$this->boolean('is_deceased')`, and `MapsEventInput::isChecked` uses `FILTER_VALIDATE_BOOL`. A JSON payload with real booleans is therefore accepted (see also `PersonsTest.php:296`).
- Server date errors arrive on three keys (`{prefix}.year|month|day`) plus `{prefix}.is_approximate`. The single text field must show whichever of them is present.
- `DeletePersonDialog`, `RemoveRelationDialog`, `UnlinkScanDialog` and `ScanPickerDialog` accept only a `trigger`. Opening them from a „⋯” `DropdownMenuItem` needs a controlled `open`/`onOpenChange`, because a Radix dialog trigger nested inside a closing menu loses focus and closes.
- `PersonPreviewLink` (`person-preview-link.tsx:20-61`) is the single navigation point to relatives, so the guard can hook in there once.
- The tree kitchen sink precedent is `database/seeders/TreeViewDemoSeeder.php` (`tree-demo@example.com`) plus `context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs`, which uses Playwright from the npx cache and is not a repo dependency.

## What We're NOT Doing

- No backend partial-update endpoint. Per-section save reuses `PUT persons/{person}` with the merged full payload (research Q1(a)).
- No co-parent hint on child rows („z Anną Wiśniewską”) and no scan descriptions („Akt urodzenia 112/1948”, S-17). Scans show `original_name`.
- No `@radix-ui/react-switch`. „Osoba nie żyje” stays the existing `Checkbox`, laid out as the board's switch row.
- No new colour tokens.
- No redesign of the tree create flow's layout. `pick-or-create-person-dialog.tsx` keeps its list→form dialog. It only gets the new fields through `PersonFormFields`.
- No editing of the marriage date in Rodzina. The marriage date is edited only on its Przegląd card.
- No adding of relations in the modal (settled: tree only). No change to relation or marriage removal rules.
- No guard for browser-level navigation (reload, tab close, browser back). The guard covers exits inside the modal only.
- No date ranges, „przed/po”, seasons, or two-digit-year guessing. All are rejected with a hint.
- No residence history (S-14), no „skan” → „źródło” rename (S-16), no source type per fact (S-18).
- No second gender cue beyond colour for rows (variant C deferred D2). The header's gender chip is text.

## Implementation Approach

Contract before pixels.

1. **Phase 1** lands the three payload fields the boards need, and the create redirect.
2. **Phase 2** builds the parser and the text date input and swaps them in at every date call site, so the rest of the plan consumes one finished component.
3. **Phase 3** replaces the preview body with the tabbed shell in view mode. The old „Edytuj” dialog is kept temporarily behind the new header's menu so editing still works.
4. **Phase 4** adds per-section editing and the guard, then deletes the layered edit dialog and `MarriageFormDialog`.
5. **Phase 5** moves create onto the shell and rebuilds `PersonFormFields` from the same section fields, for the tree flow.
6. **Phase 6** seeds the kitchen sink, shoots every state and judges each charge. A failed shot goes back to its phase.

Charges from the audit, in the lesson's three categories:
- **Missing tokens:** none.
- **Missing shared components:** partial-date input, editable section card, tabs.
- **Accidental architecture:** 4 copies of the lifespan join; `genderCard` under `tree/`; edit layered as a second dialog.

## Critical Implementation Details

- **State sequencing, per-section save.** Build the payload from the *current* `personForm` prop at submit time, not at edit start. Only the fields the section renders are overwritten. `is_deceased` and the `death` block must always be sent from `personForm` unless the Zgon card is the section being saved. Otherwise saving „Dane osobowe” for a deceased person deletes the death event and its scan links.
- **Date input serialisation.** The input posts hidden `{prefix}.year|month|day|is_approximate`, with `is_approximate` as `"1"`/`"0"` and never Radix's `"on"`. When the text is empty, all parts are empty (clearing the date). When the text is unparseable or has a two-digit year, submit is blocked on the client and the hint turns into an error. The server stays the authority on the year range and `checkdate`.
- **Guard and Radix.** `FormDialogContent` already prevents overlay dismissal. Esc (`onEscapeKeyDown`) and the X must call `preventDefault` while a card has unsaved changes and route through the guard. After „Porzuć”/„Zapisz” succeeds, the guard runs the pending exit once.

## Phase 1: Contract — payload fields and create redirect

### Overview

Add the data the boards display and make create land on the new person's preview. Backend and TS types only; no visible UI change yet.

### Changes Required:

#### 1. Relative and spouse gender

**File**: `app/Http/Resources/RelativeResource.php`, `app/Http/Resources/MarriageResource.php`

**Intent**: Expose each relative's and spouse's gender so Rodzina rows and avatars can use the gender surface/border tokens.

**Contract**: The new `gender: 'male'|'female'|null` field carries the enum value only; no label is needed. It goes on each `Relative` and on `Marriage.spouse`. Update the PHPDoc array shapes.

#### 2. Linked scan upload date

**File**: `app/Http/Resources/EventResource.php`

**Intent**: The Źródła rows show „dodano DD.MM.YYYY”.

**Contract**: Each entry in `scans` gains `uploaded_at` (ISO 8601 string, same source as `ScanResource`'s `uploaded_at`). This applies to birth, death and marriage events alike.

#### 3. Create redirect opens the preview

**File**: `app/Http/Controllers/PersonController.php` (`store`)

**Intent**: After „Dodaj osobę”, the tree centres on the new person *and* the modal shows their preview. This satisfies both FR-003 and the board note.

**Contract**: The redirect target is `/tree/<id>?person=<id>`.

The route is `tree/{person}` (`routes/web.php:21`), so its parameter name *is* `person`. Passing `['person' => …]` to `route()` fills the path segment and cannot also produce the query key. Build the URL as `route('tree.show', $person)` with `?person=<id>` appended, for example via `http_build_query` or Laravel's URI query helper (check `search-docs`). Do not pass a second `person` key to `route()`.

#### 4. Types

**File**: `resources/js/types/person.ts`

**Intent**: Mirror the new fields.

**Contract**: `Relative.gender`, `Marriage.spouse.gender`, `LinkedScan.uploaded_at`.

#### 5. Tests

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/MarriagesTest.php` (or wherever the preview spouse props are asserted, `PersonsTest.php:739-799`)

**Intent**:
- Update the store redirect assertion (`PersonsTest.php:250`).
- Add assertions that the preview carries relative/spouse `gender` and linked-scan `uploaded_at`.

**Contract**: The happy path only. Ownership 404s are unchanged, because no new endpoint is added.

### Success Criteria:

#### Automated Verification:

- Person, marriage and scan-link feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/EventScansTest.php`
- Larastan passes: `vendor/bin/sail composer types:check`
- Pint clean: `vendor/bin/sail bin pint --dirty --format agent`
- TS types check: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Creating a person from the persons list lands on `/tree/<id>?person=<id>` with the (old) preview open over the tree

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Partial-date parser and text date input

### Overview

Build the one missing shared component the whole plan depends on. It is a pure parser/formatter with unit tests, plus an input component that emits the existing request shape. Swap it in at all 3 call sites and delete `PartialDateFields`.

### Changes Required:

#### 1. Parser and formatter

**File**: `resources/js/lib/partial-date.ts` (new)

**Intent**: Turn Polish genealogy notation into the date parts the server validates, and format parts back for display and for the interpretation line.

**Contract**:
- `parsePartialDate(text): { kind: 'empty' } | { kind: 'ok', parts: {year, month, day, is_approximate}, precision: 'year'|'month'|'day' } | { kind: 'two-digit-year' } | { kind: 'invalid' }`.
- **Accepted input:**
  - Matching is case-insensitive and accepts input with or without Polish diacritics.
  - An approximate prefix: `ok.` / `ok` / `około` / `okolo` / `~` / `ca` / `ca.`.
  - The date itself is one of:
    - `YYYY`;
    - `M.YYYY`, `D.M.YYYY` with separators `.` `/` `-` or space;
    - a Polish month name in the nominative or genitive, or its 3-letter abbreviation, with an optional day (`marzec 1948`, `12 marca 1948`, `12 mar 1948`);
    - a Roman-numeral month I–XII in the month position (`III 1948`, `12 III 1948`, `12.III.1948`).
- **Rejected input:**
  - a two-digit year → `two-digit-year`;
  - ranges, `przed`/`po`, seasons, a day > 31, a month > 12, trailing text → `invalid`.
- `formatPartialDate(parts, 'short' | 'long')`:
  - `short` matches the server's `PartialDate::format` (`12.03.1948`, `03.1948`, `1948`, prefix `ok. `);
  - `long` is the card display (`12 marca 1948`, `marzec 1948`, `około 2015`).
- `describePrecision(precision)` returns „dokładna data” / „miesiąc i rok” / „tylko rok”.
- The server keeps range and `checkdate` validation. The parser does not check the calendar beyond the day ≤ 31 and month ≤ 12 bounds.

#### 2. Unit tests and runner

**File**: `resources/js/lib/partial-date.test.ts` (new), `vite.config.ts` (`test` block), `package.json` (script), `.github/workflows/lint.yml` (step)

**Intent**: The board's own „Przeciw” list says the parser must be tested. `vp test` gives Vitest with no new dependency. Read `node_modules/vite-plus/docs/guide/test.md` before editing the config.

**Contract**:
- A `test.include` entry of `resources/js/**/*.test.ts` in `vite.config.ts`, and a `"test:js": "vp test"` script.
- A CI step `npm run test:js` after `npm run check` in `lint.yml`.
- Tests cover:
  - every example on the board's date strip;
  - genitive and abbreviated month names;
  - Roman months, including `I` vs `1` by position;
  - input without diacritics;
  - each approximate marker;
  - each rejection class;
  - `short`/`long` formatting of each precision.
- The test file imports from `vite-plus/test`.

#### 3. Date input component

**File**: `resources/js/components/persons/partial-date-input.tsx` (new)

**Intent**: One labelled text field („Data”) with the live interpretation line and the place field below it. It replaces the three number inputs and the „około” checkbox.

**Contract**:
- Props: `prefix: 'birth'|'death'|'marriage'`, `errors`, `defaultValue?: {year, month, day, is_approximate, place}`.
- It renders hidden inputs `{prefix}.year|month|day|is_approximate` plus a visible `{prefix}.place`.
- The initial text is `formatPartialDate(defaultValue, 'short')`.
- Hint states:
  - neutral hint when empty („np. 1948, 03.1948, ok. 1948”);
  - „<short> <precision>” when valid;
  - the board's error text for `two-digit-year` / `invalid`.
- It shows the first present server error of `{prefix}.year|month|day|is_approximate` under the text field, and `{prefix}.place` under place.
- It exposes validity so the enclosing form can block submit, e.g. via `setCustomValidity` on the text input so native form validation stops submit, or a callback. The implementer picks one; it must work inside Inertia `<Form>`.
- The text input's id is `${prefix}-date`, which the screenshot script and error scroll rely on.

#### 4. Swap call sites and delete the old component

**File**: `resources/js/components/persons/person-form-fields.tsx`, `resources/js/components/persons/marriage-form-dialog.tsx`, `resources/js/components/persons/partial-date-fields.tsx` (delete)

**Intent**: All three call sites use the new input. Birth and death `defaultValue` come from `person.birth` / `person.death`; the marriage `defaultValue` comes from `marriage.date_parts` + `marriage.place`. The „Potwierdzone aktem” hint stays where it is.

**Contract**: No remaining import of `partial-date-fields`. Request payload keys are unchanged.

### Success Criteria:

#### Automated Verification:

- Parser unit tests pass: `vendor/bin/sail npm run test:js`
- Format and lint pass: `vendor/bin/sail npm run check`
- TS types check: `vendor/bin/sail npm run types:check`
- Build succeeds: `vendor/bin/sail npm run build`
- Date validation feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php`

#### Manual Verification:

- Edit dialog: typing `12 marca 1948` shows „12.03.1948 dokładna data”, saves, and the preview shows `12.03.1948`
- Edit dialog: `12.3.48` and `przed wojną` block submit with the board's hint text
- `31.2.1948` reaches the server and its „Podana data jest nieprawidłowa.” error shows under the single date field
- Clearing the birth date text saves an empty birth date
- Tree „dodaj nową osobę” (parent/child/spouse slot) and the marriage „Edytuj” dialog both use the new field and save correctly

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Modal shell and read-only tabs

### Overview

Replace the preview body with variant B in view mode: the header, the „⋯” menu, three tabs, the Przegląd/Rodzina/Źródła content, and the empty states. Land the shared helpers the tabs need. Editing still goes through the old layered `PersonFormDialog`, reachable from the „Edytuj” pencil on „Dane osobowe” until Phase 4 replaces it.

### Changes Required:

#### 1. Tabs primitive (new dependency, approved)

**File**: `resources/js/components/ui/tabs.tsx` (new, via shadcn), `package.json`

**Intent**: Accessible tablist with arrow-key navigation.

**Contract**:
- `@radix-ui/react-tabs` is added, via `vendor/bin/sail npx shadcn@latest add tabs` or an equivalent manual add matching the existing `components.json` style.
- Do not run `shadcn init`.
- `ui/*` stays lint-ignored as configured.

#### 2. Shared helpers (accidental architecture)

**File**: `resources/js/lib/lifespan.ts` (new), `resources/js/lib/gender-styles.ts` (new), `resources/js/components/tree/card-styles.ts`, `resources/js/components/tree/{sheet-person-card,union-node,unknown-person-card}.tsx`

**Intent**:
- One lifespan formatter for the board's format.
- `genderCard` moved out of `tree/`, because the modal now uses it too.

**Contract**:
- `formatLifespan(birthDate, deathDate)` takes the server's short strings:
  - both present → `A – B`;
  - only birth → `ur. A`;
  - only death → `zm. B`;
  - neither → `null`.
- It replaces all 4 copies (`person-preview-dialog.tsx:25-30`, `relatives-card.tsx:39-50`, `marriages-section.tsx:43-57`, `pages/persons/index.tsx:108-115`).
- `genderCard` moves to `lib/gender-styles.ts` with the same export name and values. The tree components re-point their import; `card-styles.ts` keeps its other exports. The tree look must not change.

#### 3. Modal header and menu

**File**: `resources/js/components/persons/person-modal-header.tsx` (new), `resources/js/components/persons/person-preview-dialog.tsx`

**Intent**: The board's identity header replaces `FormDialogHeader` in the preview dialog.

**Contract**:
- It renders `DialogTitle`/`DialogDescription` for a11y:
  - the title is the name;
  - the description is the lifespan, or sr-only „Podgląd osoby” when there is none.
- „Wstecz” uses the existing `leading` behaviour.
- Avatar initials sit on `genderCard[gender ?? 'unknown']`.
- Chips: gender label, religion label, `Probant` (when `isRootPerson`).
- Actions: a „Pokaż na drzewie” button, and a „⋯” `DropdownMenu` with „Pokaż na drzewie”, „Ustaw jako probanta” (hidden for the proband) and „Usuń osobę…”.
  - „Ustaw jako probanta” calls the same `TreeRootPersonController.update` put as `SetRootPersonButton`. Extract a shared hook or function rather than duplicating it.
  - „Usuń osobę…” opens `DeletePersonDialog` in controlled mode.

#### 4. Controlled mode for trigger-only dialogs

**File**: `resources/js/components/persons/delete-person-dialog.tsx`, `resources/js/components/persons/remove-relation-dialog.tsx`, `resources/js/components/persons/remove-marriage-dialog.tsx`

**Intent**: These dialogs must open from `DropdownMenuItem`s.

**Contract**:
- An optional `open` / `onOpenChange` pair is added. When it is given, `trigger` is optional and no `DialogTrigger` is rendered.
- Existing trigger usage keeps working.

#### 5. Tabs content

**File**: `resources/js/components/persons/person-modal.tsx` (new, replaces `person-preview.tsx`), `resources/js/components/persons/overview-tab.tsx`, `resources/js/components/persons/family-tab.tsx`, `resources/js/components/persons/sources-tab.tsx` (new). Delete `person-preview.tsx`, `relatives-card.tsx`, `marriages-section.tsx` and `event-card.tsx` once their content has moved; keep `event-source-status.tsx`.

**Intent**: The board's three tabs in view mode.

**Contract**:
- **Tabs.** `Tabs` default `przeglad`. Triggers „Przegląd”, „Rodzina” with a count, and „Źródła” with a count. The count is hidden when zero.
  - Rodzina count = `parents.length + marriages.length + children.length`.
  - Źródła count = distinct scan ids across `birth.scans`, `death.scans` and `marriages[].scans`.
  - The active tab is local state, reset to Przegląd when `person.id` changes.
- **Przegląd:**
  - „Dane osobowe”: Imię, Nazwisko, Zamieszkanie, with „brak danych” for empty fields.
  - „Urodzenie” / each „Małżeństwo z X” / „Zgon” as fact cards:
    - `EventSourceStatus`;
    - Data in `formatPartialDate(parts,'long')` and Miejsce;
    - a footer `n skan(y/ów) · <first original_name>` when scans exist, or else „Powiąż skan” (`ScanPickerDialog`).
    - Birth/death parts come from `personForm.birth|death`; marriage parts from `date_parts`.
  - Empty birth (no date and no place) → „Nie znamy daty ani miejsca urodzenia.” + „Uzupełnij urodzenie”.
  - No death → „Osoba żyje albo nie wiemy, że zmarła.” + „Oznacz zgon”.
  - Until Phase 4, both CTAs and the pencils open the old `PersonFormDialog`.
- **Rodzina:**
  - Sections Rodzice, Małżeństwa, Dzieci.
  - Rows use `genderCard[gender]` surfaces, initials, and name + `formatLifespan`. Each row is a `PersonPreviewLink`.
  - „⋯” per row holds „Usuń relację” / „Usuń małżeństwo”, using the controlled dialogs.
  - Marriage rows show the date (short), place and `EventSourceStatus`, read-only.
  - Empty-section hints are kept („Brak znanych rodziców. Dodasz ich na drzewie.” etc.), and the footer hint is taken from the board.
- **Źródła:**
  - One group per event (Urodzenie; „Małżeństwo z X”; Zgon if present), with a header of label, `date, place` and `EventSourceStatus`.
  - Scan rows hold `ScanThumbnail`, `original_name`, „dodano DD.MM.YYYY” (from `uploaded_at`), „Otwórz” (`Link` to `ScanController.show`) and „Odepnij” (`UnlinkScanDialog`).
  - The group action is „Powiąż skan” when there are no scans, or else „Powiąż kolejny skan”.
  - An empty group reads „Brak skanu. Powiąż akt <zdarzenia>, żeby potwierdzić zdarzenie.”
  - `availableScans` threading is unchanged.

#### 6. Dialog host

**File**: `resources/js/components/persons/person-preview-dialog.tsx`

**Intent**: Render `PersonModalHeader` + `PersonModal` inside `FormDialogContent`. Tabs sit under the header, and the body scrolls.

**Contract**: Hosting (`app-layout.tsx`), `?person=` handling and the „Wstecz” stack are unchanged.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- TS types check: `vendor/bin/sail npm run types:check`
- Build succeeds: `vendor/bin/sail npm run build`
- Preview-props feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`

#### Manual Verification:

- Header matches the `PersonModal-B` board: avatar colour, lifespan `A – B`, chips, „⋯” items (no „Ustaw jako probanta” for the proband)
- „⋯ › Usuń osobę…” opens the existing confirmation with the impact list; „Ustaw jako probanta” works
- Tab counters match (Rodzina n, Źródła distinct scans, hidden at 0); arrow keys move between tabs
- Rodzina rows open the relative's preview and „Wstecz” returns; „⋯ › Usuń relację/małżeństwo” confirmations work
- Źródła: Otwórz leaves the modal to the scan page; Odepnij confirms and unlinks; Powiąż opens the picker
- Tree sheet cards look unchanged after the `genderCard` move

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Per-section editing and the unsaved-changes guard

### Overview

Cards edit in place, one at a time, with a guard over every exit inside the modal and a full-screen edit view on phones. Afterwards the layered edit dialog and `MarriageFormDialog` are gone.

### Changes Required:

#### 1. Section editor state and guard

**File**: `resources/js/components/persons/section-editor.tsx` (new: context + hook)

**Intent**: One owner for „which section is editing, is it dirty, what exit is pending”.

**Contract**:
- `SectionEditorProvider` wraps the modal content. It provides:
  - `editing: SectionId | null`;
  - `isDirty`;
  - `start(id)`, which is guarded when another section is dirty;
  - `cancel()`;
  - `markDirty(boolean)`;
  - `requestExit(action: () => void)`.
- `requestExit(action)` runs `action` immediately when nothing is dirty. Otherwise it stores `action` and shows the guard bar.
- Bar actions:
  - „Porzuć” discards the edit and runs `action`;
  - „Zapisz” submits the dirty section and runs `action` only on success, clearing it on error.
- The bar text is „Masz niezapisane zmiany w <label>.”
- `SectionId` is `'personal'|'birth'|'death'|\`marriage-${id}\``. The provider resets when `person.id` changes.

#### 2. Editable section card

**File**: `resources/js/components/persons/section-card.tsx` (new)

**Intent**: The shared view ⇄ edit card from the boards.

**Contract**:
- Props: `id`, `title`, `badge?`, `view: ReactNode`, `edit: ReactNode` (fields), `onSubmit`, `processing`.
- **View:** a pencil button (`aria-label="Edytuj <title>"`); the card dims (`opacity` + `inert`) while another section edits.
- **Edit:** a `<form>` with the fields and Anuluj/Zapisz.
  - Dirty = the serialised form values differ from those at edit start.
  - It scrolls the first `[data-slot="input-error"]` into view on error, as `person-form-dialog.tsx:37-43` did.
- **Below `sm`:** the editing card renders as the full body („Edycja sekcji” header with back = cancel via guard; tabs and other cards hidden). Anuluj/Zapisz sit in a `FormDialogFooter` pinned at the bottom.

#### 3. Section field groups

**File**: `resources/js/components/persons/person-section-fields.tsx` (new)

**Intent**: Fields per section, reused by per-section edit here and by create/`PersonFormFields` in Phase 5.

**Contract**:
- **`PersonalFields`:** Imię, Nazwisko, Płeć as a `ToggleGroup` Mężczyzna/Kobieta/Nieznana with hidden `gender` and a `lockedGender` prop, Wyznanie `Select`, Miejsce zamieszkania.
- **`BirthFields`:** confirmed hint + `PartialDateInput prefix="birth"`.
- **`DeathFields`:** an „Osoba nie żyje” `Checkbox` (posts `is_deceased=1`) in the card header, with `PartialDateInput prefix="death"` shown when checked, plus the confirmed hint.
- **`MarriageFields`:** `PartialDateInput prefix="marriage"`.

#### 4. Save wiring

**File**: `resources/js/components/persons/overview-tab.tsx`, `resources/js/lib/person-payload.ts` (new)

**Intent**: The personal, birth and death cards save through `PUT persons/{id}` with the full merged payload. Marriage cards save through `PUT marriages/{id}`.

**Contract**:
- `personFormToPayload(personForm)` maps `PersonFormData` to the request shape:
  - `is_deceased` → boolean;
  - null events → empty parts;
  - `death` is omitted when not deceased.
- The section's form values overwrite only that section's keys. For the Zgon card that is `is_deceased` + `death.*`.
- Submit with `router.put(PersonController.update.url(id), payload, { preserveScroll: true, preserveState: true, onSuccess: exit edit, onError: keep editing })`. For marriage: `MarriageController.update`.
- Server errors are shown in the editing card. An error on a key outside the section (not expected) is shown as a generic line in the card.
- The `is_deceased` death-with-scans error („Najpierw odepnij skany od zgonu…”) shows in the Zgon card.
- After success the card leaves edit mode and shows the refreshed `personPreview` prop.
- „Uzupełnij urodzenie” and „Oznacz zgon” call `start('birth')` / `start('death')`. „Oznacz zgon” starts with the checkbox checked.

#### 5. Route every exit through the guard

**File**: `person-preview-dialog.tsx`, `person-modal-header.tsx`, `person-modal.tsx` (tabs `onValueChange`), `person-preview-link.tsx`

**Intent**: The guard covers these exits:
- pencil on another card;
- tab switch;
- X;
- Esc;
- „Wstecz”;
- relative links;
- „Pokaż na drzewie” (button and menu);
- „⋯” items („Ustaw jako probanta”, „Usuń osobę…”);
- „Otwórz”/„Powiąż” in Źródła.

**Contract**:
- `Dialog onOpenChange(false)` → `requestExit(close)`; `onEscapeKeyDown` → `preventDefault` while dirty, then `requestExit(close)`.
- The tab change is gated through `requestExit(() => setTab(value))`.
- `PersonPreviewLink` and the in-modal `Link`s: on a plain click while dirty, `preventDefault` and `requestExit(() => router.visit(...same options))`. The link must still push onto the „Wstecz” stack.
- `PersonPreviewLink` must work outside the provider (tree page, scans page, persons list): no guard when there is no `SectionEditorProvider`.

#### 6. Remove the layered edit and marriage dialogs

**File**: `resources/js/components/persons/person-form-dialog.tsx` (drop `mode: 'edit'`), `resources/js/components/persons/marriage-form-dialog.tsx` (delete)

**Intent**: Editing lives only in cards now.

**Contract**:
- `PersonFormDialog` keeps create mode only, until Phase 5 replaces it.
- No imports of `MarriageFormDialog` remain.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- TS types check: `vendor/bin/sail npm run types:check`
- Build succeeds: `vendor/bin/sail npm run build`
- Person, marriage and scan-link feature tests still pass (payload shape unchanged): `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/EventScansTest.php`
- Parser unit tests pass: `vendor/bin/sail npm run test:js`

#### Manual Verification:

- Editing „Dane osobowe” of a deceased person with a death scan saves and the death event and its scan link survive
- Editing only the Urodzenie place keeps name, gender, religion, residence and death unchanged
- „Oznacz zgon” opens Zgon in edit with „Osoba nie żyje” checked; saving creates the death; unchecking it with a linked death scan shows the server error in the card
- Marriage card edit saves date/place and keeps the certificate link
- With a dirty card each exit (other pencil, tab, X, Esc, Wstecz, relative link, Pokaż na drzewie, ⋯ items, Źródła Otwórz/Powiąż) shows the bar; Porzuć continues, Zapisz saves then continues, a failing Zapisz stays with errors
- A card with no edits (or edits reverted) never shows the bar
- At 390px, editing a section shows the full-screen „Edycja sekcji” view with pinned Anuluj/Zapisz visible while the keyboard is open
- Keyboard: pencil, fields and Zapisz are reachable by Tab; focus returns to the pencil after save/cancel

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Create mode on the shared shell

### Overview

„Dodaj osobę” opens the variant B shell as „Nowa osoba” with every card open. `PersonFormFields`, used by the tree create flow, is rebuilt from the same section fields.

### Changes Required:

#### 1. Create modal

**File**: `resources/js/components/persons/person-create-dialog.tsx` (new, replaces `person-form-dialog.tsx`), `resources/js/pages/persons/index.tsx`

**Intent**: The `PersonModal-B` „Tworzenie” board: a „?” avatar, the title „Nowa osoba”, and the description „Po zapisaniu modal przejdzie w podgląd tej osoby.”.

**Contract**:
- No tabs. The cards „Dane osobowe”, „Urodzenie” and „Zgon” are open, built from the Phase 4 section fields in one Inertia `<Form {...PersonController.store.form()}>`.
- The footer holds Anuluj and „Dodaj osobę” (`data-test="create-person-button"`).
- Scroll to the first error on failure.
- The guard applies: X, Esc or Anuluj with typed data → the same Porzuć/Zapisz bar, where Zapisz submits the create.
- On success Inertia follows the Phase 1 redirect to `/tree/<id>?person=<id>`.
- Delete `person-form-dialog.tsx`.

#### 2. Tree create flow fields

**File**: `resources/js/components/persons/person-form-fields.tsx`

**Intent**: The tree „dodaj nową osobę” form uses the same fields as the modal.

**Contract**:
- The props API is unchanged (`genders`, `religions`, `errors`, `person?`, `lockedGender?`, `defaults?`).
- It renders `PersonalFields` + `BirthFields` + `DeathFields` stacked, with separators.
- `pick-or-create-person-dialog.tsx` is untouched. The locked gender still posts its hidden value.

### Success Criteria:

#### Automated Verification:

- Format and lint pass: `vendor/bin/sail npm run check`
- TS types check: `vendor/bin/sail npm run types:check`
- Build succeeds: `vendor/bin/sail npm run build`
- Person, relation and marriage create feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php`

#### Manual Verification:

- Persons list „Dodaj osobę” shows the „Nowa osoba” board; saving lands on the tree with the new person's preview open
- Creating with an invalid date shows the error in the Urodzenie card and scrolls to it
- Closing a create form with typed data shows the guard bar
- Tree: adding a new parent (locked gender), child and spouse still works and saves dates typed as text

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 6: Screenshot gate

### Overview

Render every state from the four boards on seeded data, shoot them, and judge each charge. There is no screenshot test in the repo, so this stays a scripted gate, not a CI baseline.

### Changes Required:

#### 1. Kitchen-sink data

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: The demo tree must cover the modal's states.

**Contract**: The seeder adds, without changing the existing tree-sheet persons' creation order (the variant C `shots.mjs` relies on id offsets):
- the proband with a birth + marriage scan (`ScanFactory`, linked through the event pivot) and an approximate death without a scan;
- a sparse person (only name and gender: no dates, no death, one relative);
- a person with a death scan, for the „Odepnij skany od zgonu” error.

The additions are appended after the existing persons, and the comment block is updated.

#### 2. Shots script and verdict

**File**: `context/changes/person-modal/screenshots/shots.mjs` (new), `context/changes/person-modal/screenshots/verdict.md` (new)

**Intent**: Reproducible screenshots of each state, judged against the canvas.

**Contract**:
- The script is based on the variant C script: Playwright from the npx cache, logs in as `tree-demo@example.com`, and reads the ids of the added persons from the page, not hard-coded.
- **States, light and dark at 1280px:**
  - Przegląd full;
  - Przegląd sparse (empty CTAs);
  - editing „Dane osobowe”;
  - Urodzenie edit with a valid parse hint;
  - Zgon edit with a date error (`zima 2015`);
  - the guard bar;
  - the „⋯” menu open, for a non-proband and for the proband;
  - Rodzina;
  - Źródła;
  - create;
  - keyboard focus on a tab and on a pencil.
- **States at 390px:** Przegląd, full-screen section edit, create.
- **Output:** `screenshots/after/`.
- **`verdict.md`:** one row per charge (partial-date input, section card, tabs, lifespan helper, `genderCard` move, layered-edit removal) and per board state, marked pass or fail with the shot name. A fail goes back to its phase. Baselines are never blind-updated.

### Success Criteria:

#### Automated Verification:

- Seeder runs cleanly: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Tree view feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`
- Shots script writes every listed state: `node context/changes/person-modal/screenshots/shots.mjs context/changes/person-modal/screenshots/after`

#### Manual Verification:

- Every shot judged against its board in `verdict.md`, with no open fail
- Dark mode shots readable (chips, dimmed cards, guard bar, gender rows)
- Variant C tree shots re-run unchanged for the moved `genderCard` (spot-check sheet cards)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/partial-date.test.ts`:
  - every notation on the board's date strip;
  - genitive and abbreviated months;
  - Roman months;
  - input without diacritics;
  - approximate markers (`ok.`, `ok`, `około`, `okolo`, `~`, `ca`, `ca.`);
  - rejections (two-digit year, ranges, `przed`/`po`, seasons, day 32, month 13, trailing text);
  - `short` vs `long` formatting per precision, and `short` parity with `PartialDate::format`.

### Integration Tests:

- `PersonsTest`:
  - store redirect to `tree.show` with `?person=`;
  - the preview carries relative gender and linked-scan `uploaded_at`;
  - the existing update tests stay green, proving the merged-payload contract is unchanged.
- `MarriagesTest`: the spouse carries gender; update behaviour is unchanged.
- No DOM tests. The repo has no browser suite, and the screenshot gate covers the visual states.

### Manual Testing Steps:

1. Open the proband from the tree; check the header, the counts and each tab against the boards.
2. Edit the Urodzenie place only, save, and confirm the scan link and the other fields are intact.
3. Start editing „Dane osobowe”, type, then click a relative. The guard appears: Porzuć navigates, and Zapisz saves then navigates.
4. „Oznacz zgon” on a living person, `ok. 2015`, save; then try to uncheck on a person with a death scan and see the error.
5. At 390px, edit a section with the on-screen keyboard open; Zapisz is visible.
6. Create a person from the list with `12 III 1948`; land on the tree with the preview open.
7. Add a child on the tree with a text date.

## Performance Considerations

- The distinct-scan count and the lifespan strings are computed client-side from the existing payload, so no extra queries are needed.
- `gender` and `uploaded_at` read columns already loaded by the eager loads in `BuildPersonPreview.php:115` (`parents.events`, `children.events`, `marriages.participants.events`, `events.scans`), so no N+1.

## Migration Notes

- No schema change.
- The redirect change affects only `store`.
- Rollback is a revert: the frontend and the payload additions are backwards-compatible, and the extra fields are ignored by older code.

## References

- Related research: `context/changes/person-modal/research.md`
- Design canvas: https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F (`PersonModal-B`, `PersonModal-B-Zakladki`, `PersonModal-B-Stany`, `PersonModal-B-Mobile`)
- Modal shell: `resources/js/components/form-dialog.tsx:14-89`
- Settled modal navigation: `context/archive/2026-09-28-person-preview-in-modal/plan-brief.md:21-25`
- Deferred discard prompt: `context/archive/2026-09-28-person-form-in-modal/plan.md:38`
- Tokens and screenshot gate precedent: `context/archive/2026-09-29-tree-sheet-variant-c/` (`tokens.md`, `screenshots/shots.mjs`, `verdict.md`)
- Full-replace update: `app/Actions/Persons/UpdatePerson.php:46-60`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — payload fields and create redirect

#### Automated

- [x] 1.1 Person, marriage and scan-link feature tests pass — 418a964
- [x] 1.2 Larastan passes — 418a964
- [x] 1.3 Pint clean — 418a964
- [x] 1.4 TS types check — 418a964

#### Manual

- [x] 1.5 Creating a person from the persons list lands on `/tree/<id>?person=<id>` with the (old) preview open over the tree — 418a964

### Phase 2: Partial-date parser and text date input

#### Automated

- [x] 2.1 Parser unit tests pass — a392d82
- [x] 2.2 Format and lint pass — a392d82
- [x] 2.3 TS types check — a392d82
- [x] 2.4 Build succeeds — a392d82
- [x] 2.5 Date validation feature tests still pass — a392d82

#### Manual

- [x] 2.6 Edit dialog: typing `12 marca 1948` shows „12.03.1948 dokładna data”, saves, and the preview shows `12.03.1948` — a392d82
- [x] 2.7 Edit dialog: `12.3.48` and `przed wojną` block submit with the board's hint text — a392d82
- [x] 2.8 `31.2.1948` reaches the server and its „Podana data jest nieprawidłowa.” error shows under the single date field — a392d82
- [x] 2.9 Clearing the birth date text saves an empty birth date — a392d82
- [x] 2.10 Tree „dodaj nową osobę” (parent/child/spouse slot) and the marriage „Edytuj” dialog both use the new field and save correctly — a392d82

### Phase 3: Modal shell and read-only tabs

#### Automated

- [x] 3.1 Format and lint pass — 8dc9d5d
- [x] 3.2 TS types check — 8dc9d5d
- [x] 3.3 Build succeeds — 8dc9d5d
- [x] 3.4 Preview-props feature tests still pass — 8dc9d5d

#### Manual

- [x] 3.5 Header matches the `PersonModal-B` board: avatar colour, lifespan `A – B`, chips, „⋯” items (no „Ustaw jako probanta” for the proband) — 8dc9d5d
- [x] 3.6 „⋯ › Usuń osobę…” opens the existing confirmation with the impact list; „Ustaw jako probanta” works — 8dc9d5d
- [x] 3.7 Tab counters match (Rodzina n, Źródła distinct scans, hidden at 0); arrow keys move between tabs — 8dc9d5d
- [x] 3.8 Rodzina rows open the relative's preview and „Wstecz” returns; „⋯ › Usuń relację/małżeństwo” confirmations work — 8dc9d5d
- [x] 3.9 Źródła: Otwórz leaves the modal to the scan page; Odepnij confirms and unlinks; Powiąż opens the picker — 8dc9d5d
- [x] 3.10 Tree sheet cards look unchanged after the `genderCard` move — 8dc9d5d

### Phase 4: Per-section editing and the unsaved-changes guard

#### Automated

- [x] 4.1 Format and lint pass — 6dbc3c5
- [x] 4.2 TS types check — 6dbc3c5
- [x] 4.3 Build succeeds — 6dbc3c5
- [x] 4.4 Person, marriage and scan-link feature tests still pass (payload shape unchanged) — 6dbc3c5
- [x] 4.5 Parser unit tests pass — 6dbc3c5

#### Manual

- [x] 4.6 Editing „Dane osobowe” of a deceased person with a death scan saves and the death event and its scan link survive — 6dbc3c5
- [x] 4.7 Editing only the Urodzenie place keeps name, gender, religion, residence and death unchanged — 6dbc3c5
- [x] 4.8 „Oznacz zgon” opens Zgon in edit with „Osoba nie żyje” checked; saving creates the death; unchecking it with a linked death scan shows the server error in the card — 6dbc3c5
- [x] 4.9 Marriage card edit saves date/place and keeps the certificate link — 6dbc3c5
- [x] 4.10 With a dirty card each exit (other pencil, tab, X, Esc, Wstecz, relative link, Pokaż na drzewie, ⋯ items, Źródła Otwórz/Powiąż) shows the bar; Porzuć continues, Zapisz saves then continues, a failing Zapisz stays with errors — 6dbc3c5
- [x] 4.11 A card with no edits (or edits reverted) never shows the bar — 6dbc3c5
- [x] 4.12 At 390px, editing a section shows the full-screen „Edycja sekcji” view with pinned Anuluj/Zapisz visible while the keyboard is open — 6dbc3c5
- [x] 4.13 Keyboard: pencil, fields and Zapisz are reachable by Tab; focus returns to the pencil after save/cancel — 6dbc3c5

### Phase 5: Create mode on the shared shell

#### Automated

- [x] 5.1 Format and lint pass
- [x] 5.2 TS types check
- [x] 5.3 Build succeeds
- [x] 5.4 Person, relation and marriage create feature tests pass

#### Manual

- [x] 5.5 Persons list „Dodaj osobę” shows the „Nowa osoba” board; saving lands on the tree with the new person's preview open
- [x] 5.6 Creating with an invalid date shows the error in the Urodzenie card and scrolls to it
- [x] 5.7 Closing a create form with typed data shows the guard bar
- [x] 5.8 Tree: adding a new parent (locked gender), child and spouse still works and saves dates typed as text

### Phase 6: Screenshot gate

#### Automated

- [ ] 6.1 Seeder runs cleanly
- [ ] 6.2 Tree view feature tests still pass
- [ ] 6.3 Shots script writes every listed state

#### Manual

- [ ] 6.4 Every shot judged against its board in `verdict.md`, with no open fail
- [ ] 6.5 Dark mode shots readable (chips, dimmed cards, guard bar, gender rows)
- [ ] 6.6 Variant C tree shots re-run unchanged for the moved `genderCard` (spot-check sheet cards)
