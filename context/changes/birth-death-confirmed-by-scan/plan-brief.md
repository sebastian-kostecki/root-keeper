# Birth and Death Confirmed by Scan — Plan Brief

> Full plan: `context/changes/birth-death-confirmed-by-scan/plan.md`

## What & Why

Roadmap slice S-03, the north star of M-1 (FR-014, FR-008, US-01). The user links scans of vital-record acts to a person's birth or death and sees on the person preview which facts rest on a source and which do not. This is the smallest flow that proves the product idea: person → fact → source → scan.

## Starting Point

S-01 modelled birth and death as tree-scoped `events` with participants, planned so that scans could attach by `event_id`. S-02 delivered tree-scoped scans, authorized file serving and a zoom viewer. Nothing links events to scans yet. The preview shows only date and place, and a death event exists only for people saved as deceased.

## Desired End State

- On `/persons/{id}`, birth and death cards show "Potwierdzone aktem" (filled badge, check icon) or "Bez źródła" (outline badge, warning icon, muted), plus thumbnails of linked scans that open the zoom viewer.
- "Powiąż skan" opens a lazily loaded picker of the tree's scans. "Odepnij" asks for confirmation and warns when the last source is removed.
- `/scans/{id}` lists the events it confirms ("Urodzenie — Jan Kowalski") with links to the people.
- No endpoint touches another user's events or scans.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Confirmation rule | Confirmed iff ≥ 1 linked scan, computed server-side (`is_confirmed`) | PRD Business Logic, kept in one place so the UI never re-derives it. | PRD |
| Data model | `event_scan` pivot, unique pair, cascades | One scan backs many events and one event may have several scans; S-07 marriage needs no change. | PRD / Plan |
| Where linking happens | Person preview, per event card | Matches the person → fact → source flow and picks the event unambiguously. | Plan |
| Opening a linked scan | Navigate to existing `/scans/{id}` | Reuses the tested full-height zoom viewer. | Plan |
| Scan page backlinks | "Potwierdza" list, read-only | Navigation from act to people, and a head start for S-08. | Plan |
| Unlink | Confirmation dialog with a last-scan warning | Consistent with the PRD rule of explicit confirmation for destructive actions. | Plan |
| Status look | Existing Badge variants + icon + text, no new tokens | Doesn't rely on color alone and leaves the design system unchanged. | Plan |
| Picker data | `Inertia::optional` prop, loaded on dialog open, with skeleton | The preview doesn't carry up to ~500 scans on every visit. | Plan |
| Duplicate link | Idempotent (`syncWithoutDetaching`), linked scans disabled in the picker | No error state for a harmless repeat. | Plan |

## Scope

**In scope:**
- `event_scan` migration, `Event::scans` / `Scan::events`, `EventType::label`, `EventPolicy`
- `EventScanController` store/destroy + `EventScanStoreRequest` (tree-scoped `scan_id`)
- `EventResource` `id`/`is_confirmed`/`scans`; person preview `availableScans`; scan preview `confirms`
- Shared `ScanThumbnail`, `EventSourceStatus`, `EventCard`, `ScanPickerDialog`, `UnlinkScanDialog`
- Feature tests (`EventScansTest`, `PersonsTest`, `ScansTest`)

**Out of scope:** linking from the scan page; a modal viewer on the person page; adding a death event to a living person (S-05); marriage (S-07); scan deletion (S-08); new color tokens; picker search or pagination; upload from inside the picker; status on the list or tree view.

## Architecture / Approach

The person preview eager-loads `events.scans`. `EventResource` derives `is_confirmed`, and `EventCard` renders the status, the sources and the actions. "Powiąż skan" → dialog → `router.reload({ only: ['availableScans'] })` → click → `POST events/{event}/scans {scan_id}` → policy + tree-scoped `exists` → `syncWithoutDetaching` → `back()` with a toast. "Odepnij" → confirm → `DELETE events/{event}/scans/{scan}` → detach (404 if not linked). The scan preview eager-loads `events.participants` into `confirms`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Event–scan linking backend | Pivot, relations, policy, link/unlink endpoints, preview payloads, feature tests | Cross-tree leak through `scan_id` or event binding |
| 2. Linking UI | Status badge, event cards with sources, picker, unlink dialog, scan page backlinks | Status difference not clear enough without color, or stale picker state after linking |

**Prerequisites:** S-01 and S-02 done (they are).
**Estimated effort:** ~2 sessions (one per phase).

## Open Risks & Assumptions

- People saved as living have no death event, so their death act can't be linked until S-05 adds person editing. This is accepted for this slice.
- The status distinction relies on a filled vs outline badge, an icon and text in a monochrome theme. The manual light/dark screenshot check is the gate. If it isn't clear enough, adding semantic tokens is the follow-up, not a change inside this slice.
- The picker has no search. With a few hundred scans, recognition relies on thumbnails and newest-first order. S-04-style search for scans isn't planned.

## Success Criteria (Summary)

- US-01 holds end to end: a person with a linked birth act shows the date and place with "Potwierdzone aktem", and the act opens readable in the zoom viewer.
- An event without a source is clearly distinguishable from a confirmed one, in both themes.
- Tests prove that no user can link, unlink or see another tree's events or scans.
