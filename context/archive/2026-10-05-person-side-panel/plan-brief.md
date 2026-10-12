# Panel osoby obok drzewa (S-06) — Plan Brief

> Full plan: `context/changes/person-side-panel/plan.md`
> Research: `context/changes/person-side-panel/research.md`

## What & Why

Podgląd i edycja osoby przechodzą z modala do prawego panelu bocznego F-02. Otwarty panel nie zasłania drzewa: drzewo zwęża się obok niego. Kliknięcie karty otwiera panel osoby zamiast przecentrowywać, przycisk „Podgląd” znika, a przecentrowanie staje się akcją panelu. To wycinek S-06 z roadmapy (FR-016, FR-019, FR-026, FR-028, FR-029).

## Starting Point

Stan podglądu już żyje w adresie (`?person=`, globalny resolver z 404 w `HandleInertiaRequests`), ale renderuje się jako Radix `Dialog` montowany w layoucie. Powłoka `SidePanel` z F-02 jest gotowa i używana przez panele źródła, miejscowości i parafii. Strażnik niezapisanych sekcji osoby obejmuje dziś tylko wnętrze modala.

## Desired End State

Na każdej stronie aplikacji `?person={id}` otwiera po prawej panel `standard` nazwany imieniem i nazwiskiem osoby. Ma zakładki Przegląd/Rodzina/Źródła i edycję sekcji jak dziś. Karta drzewa, także karta centrum, otwiera panel. „Pokaż na drzewie” centruje drzewo na osobie z tym samym związkiem co dawne kliknięcie karty i zostawia panel otwarty. Krewny z Rodziny podmienia treść panelu, a zamknięcie zostawia autora w tym samym miejscu drzewa. „Potwierdza” w panelu źródła zastępuje panel źródła panelem osoby.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| „Ustaw w centrum” | „Pokaż na drzewie” → `tree/{id}?person={id}`, panel zostaje otwarty | Przecentrowanie już istnieje; „Ustaw jako probanta” to inna akcja | Research |
| Gdzie żyje panel | W layoucie, na każdej stronie (drzewo, lista, źródła, miejscowości) | Globalny resolver zwęża dowolną stronę bez pracy serwera | Research |
| Wejście ze źródła | Panel osoby zastępuje panel źródła (`withPanel`) | Jedna powłoka, jeden panel naraz | Research |
| Dodawanie osoby | Zostaje modalem; po zapisie otwiera się panel nowej osoby | Poza zakresem przeniesienia podglądu | Research |
| Szerokość | `standard` (~410 px przy 1366 px) | CLAUDE.md: szerszy jest tylko panel źródła; drzewo zachowuje miejsce | Plan |
| Strażnik niezapisanej sekcji | Na akcjach panelu: zamknij, inna osoba (karta/lista/Rodzina/źródło), „Pokaż na drzewie”, źródło | Spójne z panelem źródła; podniesiony do layoutu, żeby obejmował karty | Plan |
| Związek po przecentrowaniu | Zachowany: panel bierze `spouse` z `sheet` strony drzewa (jak kontekst karty) | Przecentrowanie na dziadku pokazuje gałąź z babcią, jak dziś | Plan |
| `?person` przy związku i małżonku | Zachowany: `mergeQuery` w węźle związku, `person` z Referera w `PersonSpouseController` | Panel nie znika przy zwykłej pracy na drzewie (FR-028) | Plan |
| Przesunięty widok drzewa | Bez przywracania transformacji; ręczne sprawdzenie, że centrum zostaje widoczne | Zero nowej logiki w `ZoomCanvas` po S-05 | Plan |
| Historia „Wstecz” | Usunięta | FR-028 jej nie wymaga; upraszcza provider | Research |

## Scope

**In scope:**
- `PersonPanel` w `SidePanel` zamiast `PersonPreviewDialog`; strażnik sekcji w layoucie; pasek niezapisanych zmian w panelu
- Migracja na `withPanel`/`panelVisitOptions` (F3 z F-02); strażnik formularza źródła przy „Potwierdza”
- Karta (także centrum) otwiera panel; bez „Podgląd” na karcie i w nagłówku drzewa
- Podpowiedzi związku (`centreHints`, Vitest), `mergeQuery` w węźle związku, `person` w przekierowaniu małżonka (Pest)
- F9: regiony sekcji Rodziny/Źródeł i lokatory `getByRole('region')`; nowy spec E2E przepływu panelu

**Out of scope:**
- Zmiany danych panelu i resolvera; dodawanie osoby w panelu
- Przywracanie przesuniętego widoku i przycisk „Dopasuj widok”
- Strażnik sekcji na nawigacjach spoza panelu (menu boczne, sloty, inny typ panelu z listy strony)
- Wyróżnienie karty otwartej osoby; zmiana etykiety „Pokaż na drzewie”

## Architecture / Approach

`AppLayout` owija stronę w `PersonPreviewProvider` → `SidePanelSlotProvider` → `SectionEditorProvider(personId | null)`, a `PersonPanel` portaluje się do slotu za `<main>`. Wszystkie wejścia (karta, lista, Rodzina, źródło) to `PersonPreviewLink` z `panelVisitOptions`, który przy brudnej sekcji oddaje wizytę do `requestExit`. Panel liczy `{ focusId, spouses }` czystą funkcją `centreHints(sheet)` z propu `sheet` strony drzewa (`usePage().props`; na innych stronach go nie ma). Panel buduje z tego „Pokaż na drzewie” albo ją ukrywa dla centrum.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Panel osoby zamiast modala | Panel w layoucie, strażnik na stronie, wejście ze źródła ze strażnikiem, regiony F9 | Zgubiona brudna sekcja przy kliknięciu poza panelem; lokatory E2E |
| 2. Kliknięcie karty i przecentrowanie | Karta otwiera panel, „Pokaż na drzewie” ze związkiem, `?person` przy związku i małżonku | Zły związek po przecentrowaniu; panel zamykany przez przekierowanie |
| 3. E2E i ocena przy 1366 px | Spec karta → panel → krewny → zamknięcie; ocena szerokości i zwężania | Panel `standard` za wąski na edycję sekcji |

**Prerequisites:** F-01 (E2E), F-02 (powłoka), S-05 (dopasowanie drzewa) — wszystkie `done`.
**Estimated effort:** ~2–3 sesje w 3 fazach.

## Open Risks & Assumptions

- Szerokość `standard` może zawijać formularze sekcji. Sprawdzamy to w fazie 3, a w razie potrzeby poprawiamy układ treści, nie wariant.
- Biblioteka zoomu może przyciąć przesunięty widok przy zwężeniu. Przyjmujemy to, dopóki centrum zostaje widoczne.
- Otwarcie panelu innego typu z listy strony (np. źródła) przy brudnej sekcji osoby porzuca zmiany bez pytania.

## Success Criteria (Summary)

- Klik karty → panel tej osoby obok zwężonego drzewa; zamknięcie → to samo centrum i związek.
- Edycja osoby w panelu nie ginie po cichu przy akcjach panelu ani przy kliknięciu innej karty.
- Cudzy `?person=` nadal daje 404, a sloty drzewa działają jak dziś przy otwartym panelu.
