# Person Search — Plan Brief

> Full plan: `context/changes/person-search/plan.md`

## What & Why

Roadmap slice S-04 (FR-009, US-01): the user can search the list of people in their tree by name and open the person's preview. Finding a person quickly is one of the two pains named in the product vision, and the list is the way to reach a person until the graphical tree (S-09) exists.

## Starting Point

`/persons` already lists every person in the user's tree (unpaginated, sorted by last name, first name) as cards linking to the preview (`PersonController::index`, `resources/js/pages/persons/index.tsx`). There is no way to narrow it.

## Desired End State

A search box above the list narrows it as the user types (after a ~300 ms pause). The query lives in the URL (`/persons?q=…`), so refresh and Back from a preview restore both the box and the results. A search with no hits says so explicitly; an empty tree still shows the original empty-tree card.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Where filtering runs | Server, `?q=` query param, debounced Inertia GET with preserved state | Survives Back/refresh and is provable by Pest — the repo has no JS tests. |
| Matched fields & words | Query split on whitespace; every word must be in `first_name` or `last_name` | Word order doesn't matter: `kowalski jan` = `Jan Kowalski`. |
| Case & diacritics | Case-insensitive (`ILIKE`), diacritics significant: `łukasz` finds Łukasz, `lukasz` does not | No DB extension or migration; consistent with the PRD's "no spelling variants" non-goal. |
| Match type | Contains (anywhere in the name) | Finds a name from any remembered fragment; noise is negligible at this tree size. |
| Wildcards in input | `\`, `%`, `_` escaped, so they match literally | Otherwise `%` would return everyone and `_` any character. |
| Result order | Unchanged (last name → first name → id) | No relevance ranking needed for a small list. |

## Scope

**In scope:**
- `q` handling in `PersonController::index`, echoed back as `filters.q`
- Search `Input` and no-match state on `persons/index.tsx`
- Pest feature tests for matching rules, isolation between users and malformed `q`

**Out of scope:**
- Diacritic folding / `unaccent`, spelling variants
- Search by birth/death year, place, residence, religion
- Prefix-only matching, ranking, highlighting, pagination
- Search on other pages or a global search; new frontend dependencies; restyle

## Architecture / Approach

The browser keeps the input text locally and, after a typing pause, visits `/persons?q=<trimmed text>` with `preserveState` + `replace`. The controller normalizes `q` (non-string → empty, trim, max 100 chars), splits it into words and adds one `(first_name ILIKE '%w%' OR last_name ILIKE '%w%')` group per word to the existing tree-scoped query. Ordering and eager loading are untouched. No schema change.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Server-side name filter | `?q=` filtering + `filters.q` prop, six Pest tests | Forgetting LIKE escaping (covered by a test) |
| 2. Search box on the persons list | Debounced input, URL sync, no-match state | Visit loop or lost focus if the debounce doesn't skip an unchanged `q` or state isn't preserved |

**Prerequisites:** S-01 (done). Sail stack running for tests.
**Estimated effort:** ~1 short session across 2 phases.

## Open Risks & Assumptions

- Production Postgres is assumed to have the same `en_US.utf8` ctype as dev/test (same image, no locale override), so `ILIKE` folds `Ł/ł`, `Ż/ż`; confirm with `SHOW lc_ctype;` on prod.
- Users who type Polish names without diacritics will get no match — an accepted consequence of the diacritics decision; revisit only if it proves painful with the real tree.
