---
change_id: e2e-in-ci
title: E2E (Playwright) w CI
status: implemented
created: 2026-10-22
updated: 2026-10-22
archived_at: null
---

## Notes

E2E (Playwright) w CI (issue #36)

### Wdrożenie — faza 1 (2026-10-22)

- Lokalny pełny przebieg `npx playwright test` jest czerwony już przed tą zmianą: przy domyślnej liczbie workerów (8 rdzeni → 4) padają na limicie 30 s `tree-slot-parent`, `tree-slot-child`, `tree-slot-spouse` i `fact-places-from-gazetteer`; pojedynczo przechodzą (~25–30 s każdy, bez trace ~25 s). Porównanie z ustawieniami z `develop` (`--trace=off`): te same 4 czerwone, 40 zielonych. Baza `e2e` czysta (brak resztek). Do osobnej zmiany: dlaczego te testy trwają ~25 s (Lekcja 5) — nie maskujemy podnoszeniem limitu ani ponowieniami.
- ADAPT: `trace` tylko w CI (`retain-on-failure`), lokalnie `off` jak przed zmianą — plan obiecywał lokalne zachowanie bez zmian, a nagrywanie dokłada ~4–5 s na test.
- CI (PR #51, run 39769613750): 44 testy, 2 workery, `44 passed (2.2m)`; cały job `e2e` 3m46s przy zimnym cache Chromium — mieści się w budżecie 20 min, shardowanie niepotrzebne. Testy `tree-slot-*`, czerwone lokalnie pod obciążeniem, w CI (natywny PHP) mieszczą się w limicie.
- Po decyzji „lokalnie workers: 2”: pierwszy przebieg padł na `auth.setup` (pusta strona) — w `public/hot` została resztka po zatrzymanym `npm run dev` (Vite nie odpowiadał), więc strony brały assety z nieistniejącego serwera; usunięty (`scripts/e2e-server.sh` każe tak robić). Z buildem `tree-slot-*` trwają 15–17 s zamiast ~25 s — wcześniejsze spowolnienie dawał głównie dev serwer Vite.
- Ponowny pełny przebieg (2 workery, build): 43 zielone, 1 czerwony — `fact-places-from-gazetteer` wisi 30 s w kroku 3 na „Zapisz” w sekcji „Dane osobowe”; pojedynczo zielony w 4,8 s, w CI zielony. Powtarzalny tylko w pełnym przebiegu → podejrzenie interferencji z innym testem; do osobnej zmiany (Lekcja 5), bez podnoszenia limitu.
- Zamknięte: PR #51 zmergowany do `develop` (a289f56), issue #36 zamknięte. Krok 1.3 zostaje otwarty świadomie (decyzja użytkownika): lokalny pełny przebieg ma 1 niestabilny test `fact-places-from-gazetteer` — do osobnej zmiany.
