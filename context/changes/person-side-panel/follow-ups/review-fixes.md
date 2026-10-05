# Review fixes — person-side-panel

Follow-upy z `reviews/impl-review.md` (2026-10-06), które nie mieszczą się w S-06.

## F3 — Globalny strażnik niezapisanych zmian na nawigacji strony

- **Problem**: panel osoby nie jest modalny, więc strona obok niego zostaje klikalna. Te nawigacje zdejmują `?person=` i po cichu porzucają brudną sekcję osoby:
  - menu boczne (`resources/js/components/nav-main.tsx:38`);
  - okruszki;
  - otwarcie źródła, miejscowości albo parafii z listy strony. Na przykład `openFromList` → `guardLink` w `resources/js/pages/sources/index.tsx:127` pyta tylko strażnika źródła, a `withPanel` usuwa klucz `person`.

  S-06 świadomie to wyłączył („What We're NOT Doing”: strażnik tylko na akcjach panelu, jak w panelu źródła).
- **Kierunek**: jeden globalny strażnik zamiast łatania pojedynczych linków. `SectionEditorProvider` (layout) nasłuchuje zdarzenia `before` routera Inertia. Gdy `isDirty`, anuluje wizytę i oddaje ją do `requestExit`.
- **Przepuszcza**:
  - częściowe wizyty samego panelu osoby (`only` = propsy paneli), gdy nie zmieniają `person`;
  - zapisy sekcji (POST/PUT/PATCH sekcji);
  - wizyty, które już przeszły przez `requestExit`.
- **Do sprawdzenia**:
  - API zdarzenia `before` w Inertia v3 (`search-docs`);
  - czy ten sam mechanizm powinien objąć `useUnsavedChangesGuard` formularza źródła, miejscowości i parafii.
- **Weryfikacja**: E2E w kolejności: brudna sekcja Urodzenie, klik w menu bocznym „Źródła”, widoczny pasek „Masz niezapisane zmiany”, adres bez zmian, „Porzuć”, przejście.

## F7 — E2E strażnika brudnej sekcji przy kliknięciu karty

- **Ryzyko**: kliknięcie karty drzewa przy brudnej sekcji osoby po cichu porzuca edycję. Strażnik działa tylko dlatego, że `SectionEditorProvider` jest w layoucie (`resources/js/layouts/app-layout.tsx`). Wcześniej sprawdzono to tylko ręcznie (1.8). Od triage F2 strażnik ignoruje też kliknięcia w trakcie zapisu.
- **Jak**: przez `/10x-e2e` (eksploracja `playwright-cli`, przegląd pod pięć antywzorców, weryfikacja celowym błędem). Drugi test w `tests/e2e/person-side-panel.spec.ts`: edycja pola Urodzenie bez zapisu, klik karty rodzica, widoczny alert „Masz niezapisane zmiany”, dialog nadal nazwany imieniem centrum, „Porzuć”, dialog rodzica.
- **Opcjonalnie**: „Pokaż na drzewie” u dziadka prowadzi na `tree/{dziadek}?spouse={babcia}&person={dziadek}`, a panel zostaje otwarty (dziś tylko ręcznie, 2.6).
- **Celowy błąd**: przenieść `SectionEditorProvider` z powrotem do samego panelu, wtedy test ma być czerwony.
- **Status (2026-10-06)**: zrobione przez `/10x-e2e`.
  - `tests/e2e/person-panel-unsaved-guard.spec.ts`: klik karty przy brudnej sekcji pyta, a „Zapisz” zapisuje u centrum i potem otwiera kliknięty panel. Celowy błąd: link karty pomija `editor.isDirty`; wynik czerwony na alercie.
  - `tests/e2e/person-panel-show-on-tree.spec.ts`: „Pokaż na drzewie” u ojca z dwoma związkami. Celowy błąd: brak `spouse` w adresie; wynik czerwony na karcie matki, bo drzewo pokazuje związek z Drugą i „Brak dzieci”.
  - Pełne E2E: 12/12.
