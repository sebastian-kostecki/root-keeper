# Review fixes — critical-flows-e2e-guard

Follow-ups queued from `reviews/impl-review.md` (2026-10-05). They are not done in F-01, because F-01 does not change production code.

## F9 — familySection bez lokatora strukturalnego (dla S-06)

- **Kiedy**: w S-06 (przejście modala osoby na panel), które i tak przebudowuje ten widok.
- **Kod produkcyjny**: `resources/js/components/persons/family-tab.tsx:136-151` (`Section`). Dodać `aria-labelledby`, które wskazuje `id` nagłówka `<h3>` (np. z `useId()`). `<section>` z nazwą dostępności staje się wtedy rolą `region`.
- **Test**: w `tests/e2e/support/navigation.ts` zamienić `familySection` z `dialog.locator('section').filter({ has: heading h3 })` na `dialog.getByRole('region', { name, exact: true })`. Usunąć wyjątek z §6.6 „Pułapki” w `context/foundation/test-plan.md`.
- **Weryfikacja**: `npx playwright test` zielony, a w `tests/e2e/` nie ma już `locator('section')`.
