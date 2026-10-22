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
