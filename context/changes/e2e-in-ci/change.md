---
change_id: e2e-in-ci
title: E2E (Playwright) w CI
status: implementing
created: 2026-10-22
updated: 2026-10-22
archived_at: null
---

## Notes

E2E (Playwright) w CI (issue #36)

### Wdrożenie — faza 1 (2026-10-22)

- Lokalny pełny przebieg `npx playwright test` jest czerwony już przed tą zmianą: przy domyślnej liczbie workerów (8 rdzeni → 4) padają na limicie 30 s `tree-slot-parent`, `tree-slot-child`, `tree-slot-spouse` i `fact-places-from-gazetteer`; pojedynczo przechodzą (~25–30 s każdy, bez trace ~25 s). Porównanie z ustawieniami z `develop` (`--trace=off`): te same 4 czerwone, 40 zielonych. Baza `e2e` czysta (brak resztek). Do osobnej zmiany: dlaczego te testy trwają ~25 s (Lekcja 5) — nie maskujemy podnoszeniem limitu ani ponowieniami.
- ADAPT: `trace` tylko w CI (`retain-on-failure`), lokalnie `off` jak przed zmianą — plan obiecywał lokalne zachowanie bez zmian, a nagrywanie dokłada ~4–5 s na test.
