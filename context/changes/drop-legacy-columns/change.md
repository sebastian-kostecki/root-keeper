---
change_id: drop-legacy-columns
title: Usunięcie starych kolumn po migracjach M-2
status: implementing
created: 2026-10-22
updated: 2026-10-22
archived_at: null
---

## Notes

Issue #35: usunięcie kolumn, których po migracjach M-2 nikt nie czyta ani nie zapisuje (`people.residence`, `events.place`, `sources.entry_number` z UI „Stary numer wpisu”). Kandydatki dopisane później: `people.maiden_name` (przeniesione do `last_name` 2026-10-21), `sources.provenance` (skopiowane do `archive`), `sources.cemetery_locality_id` (zastąpione `cemetery_id`). Przed usunięciem — kontrola danych produkcyjnych.

### Wdrożenie (2026-10-22)

- Lokalnie przed restore: bezpiecznik zatrzymał `migrate` — `events.place (bez kopii: 347); sources.entry_number (bez kopii: 1)` (dane z importu z 15.10), drop został Pending.
- Próba generalna: `backup:restore` nocnej kopii produkcji (2026-10-22-01-45-01.zip, 66 MB) → `migrate` przeszedł przez dzisiejsze migracje danych i obie nowe; kolumn brak, `event_source.entry_number` = 38/145/118 dla źródeł 2/3/7.
- CI na PR #53 zielone (lint, static-analysis, tests, e2e 3m42s).
