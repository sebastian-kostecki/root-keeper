# Dodawanie osób na drzewie w panelu bocznym — Plan Brief

> Full plan: `context/changes/tree-add-person-panel/plan.md`
> Research: `context/changes/tree-add-person-panel/research.md`

## What & Why

Dodawanie krewnego na drzewie (ojciec/matka/rodzic, dziecko, małżonek — ze slotu i z menu związku) otwiera dziś pełnoekranowy modal, który przyciemnia drzewo, choć podgląd tej samej osoby otwiera się obok drzewa. Pusty stan drzewa każe „otworzyć osobę”, której nie ma. Zmiana przenosi dodawanie do jednego panelu bocznego, a po zapisie pokazuje dodaną osobę obok drzewa.

## Starting Point

Modal `PickOrCreatePersonDialog` z trzema konfiguracjami; endpointy zapisu zwracają `back()` i gubią id nowej osoby (małżonek przenosi stary panel z Referera). Panele boczne są sterowane kluczem URL (`withPanel`), mają strażnika niezapisanych zmian (`SectionEditorProvider`) i wzorzec redirectu z `?person=<id>` (`PersonController@store`). Canvas kotwiczy na osi pary, przez co slot albo karta osoby centralnej wypada z kadru.

## Desired End State

Klik w slot otwiera po prawej panel „Dodaj … — osoba” z listą osób i „Nowa osoba”; drzewo zwęża się, aktywny slot jest wyróżniony i w kadrze. Zapis lub wybór przełącza panel na podgląd tej osoby, drzewo zostaje na tej samej osobie i związku. Brudny formularz pilnuje pasek Porzuć/Zapisz. Puste drzewo ma „Dodaj pierwszą osobę”, drzewo bez probanta — „Wybierz probanta”; wybrana lub dodana osoba zostaje probantem.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Jeden panel dla czterech wejść + pusty stan | Tak; `MarryCoupleDialog`, potwierdzenia usuwania i `/persons` „Dodaj osobę” zostają modalne | Spójny wzorzec bez rozszerzania zakresu | Research (użytkownik) |
| Po zapisie/wyborze | Panel = podgląd dodanej osoby | Użytkownik od razu widzi i uzupełnia dodaną osobę | Research (użytkownik) |
| Mechanizm panelu | Klucz URL `?add=…`, kontekst rozwiązywany z arkusza po stronie klienta | `withPanel` sam zamyka panel osoby, działa strażnik i reload, bez duplikowania reguł `BuildFamilySheet` | Plan |
| Redirect po zapisie | Referer (ścieżka + query) bez kluczy paneli + `person=<id>`; zachowany `spouse` | Drzewo zostaje na tym samym związku (wymaga tego `tree-slot-child.spec.ts`) | Plan |
| Proband | Nowy `TreeProbandController@attach/@store`, tylko gdy drzewa nie ma probanta | Wzorzec par `attach`/`store`; nieaktualny panel nie podmieni probanta | Plan |
| Pusty stan „brak probanta” | Przycisk w `EmptyState`, panel nie otwiera się sam | Wybór probanta zostaje jawny | Plan |
| Aktywny slot | Wyróżniony (`aria-expanded`, `accent`) i odsłonięty w kadrze bez oznaczania widoku jako ruszonego | Sedno C5; zamknięcie panelu nadal przywraca dopasowanie | Plan |
| Kadrowanie na wąskim ekranie | Kotwica na parze, gdy się mieści, inaczej na karcie osoby centralnej | Decyzja użytkownika z research + zachowanie „para w kadrze” na desktopie | Research / Plan |
| Strażnik formularza | Tak, jak w panelu osoby (tylko widok formularza) | Drzewo obok jest klikalne — S-06 pokazało ciche gubienie danych | Plan |
| Zakres E2E | 3 specy slotów + nowy spec strażnika; pusty stan w Pest + ręcznie | Wspólny użytkownik E2E nie może tracić probanta (`test-plan.md:209`) | Plan |

## Scope

**In scope:**
- Redirecty sześciu endpointów dodawania, endpointy probanta, `hasPeople`
- Panel `?add=` (lista, formularz, strażnik, luki stanów listy kandydatów), usunięcie modala i jego wrapperów
- Dwa warianty `EmptyState` i tryb probanta
- `ZoomCanvas`: kotwica zastępcza i odsłanianie; styl aktywnego slotu; tokeny `h-7.5`/`h-8.5`
- E2E (sloty + strażnik), przepisanie reguły `CLAUDE.md`, zrzuty „po”

**Out of scope:**
- „Dodaj osobę” na `/persons`, `MarryCoupleDialog`, potwierdzenia usuwania, picker pochowanych
- Resolver `?add=` na serwerze, automatyczne otwieranie panelu probanta, E2E pustego stanu / drugie konto
- `toHaveScreenshot`, kitchen sink, zapamiętywanie widoku, ręczny zoom

## Architecture / Approach

Klik slotu → `withPanel(url, 'add', 'father:987')` → strona rozwiązuje cel z `FamilySheet` (`resolveAddTarget`) i renderuje `AddPersonPanel` (portal do slotu `SidePanel`) w zagnieżdżonym `SectionEditorProvider`. Wybór → `router.post` / zapis → `<Form>`; serwer przekierowuje przez `PersonPanelRedirect` na ten sam widok z `?person=<id>`, więc `add` znika, a layout otwiera `PersonPanel`. Canvas: każdy zaplanowany przebieg = `fit()` (jeśli nieruszony) → odsłonięcie aktywnego slotu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Ścieżka zapisu i endpointy probanta | Redirect „ten sam widok + podgląd osoby”, `TreeProbandController`, `hasPeople`, Pest | Celowo zmienione asercje redirectów w `ParentChildRelationsTest`/`MarriagesTest` |
| 2. Panel dodawania dla slotów | `?add=`, `AddPersonPanel` ze strażnikiem, usunięty modal | Specy slotów muszą przejść bez zmian (tytuły = lokatory) |
| 3. Pusty stan i proband | `EmptyState` × 2, tryb `proband` w panelu | Pusty stan tylko ręcznie w przeglądarce |
| 4. Kadrowanie i aktywny slot | Kotwica zastępcza, odsłanianie, styl aktywny, tokeny C6 | Współdzielony `ZoomCanvas` (widok przodków); kolejność `fit()` → odsłonięcie |
| 5. E2E, reguła, bramka wizualna | Asercje panelu, spec strażnika, `CLAUDE.md`, zrzuty `after/` | Klikalność slotów przy otwartym panelu na 1366×768 |

**Prerequisites:** Sail działa; serwer E2E (`scripts/e2e-server.sh`, `E2E_USERNAME`/`E2E_PASSWORD` w `.env`); zrzuty `before/` już są.
**Estimated effort:** ~4–5 sesji w 5 fazach (faza 2 największa).

## Open Risks & Assumptions

- Zakładamy, że po zwężeniu drzewa przez panel (≈700 px przy 1366) para się nie mieści i kotwica przechodzi na kartę osoby centralnej — to zmienia kadr przy otwartym panelu względem dziś (zamierzone).
- Nieaktualny `?add=` (slot zajęty w innej karcie przeglądarki) po prostu nie otwiera panelu — bez komunikatu.
- Pusty stan i proband nie mają ochrony E2E; regresję w UI złapie tylko weryfikacja ręczna.

## Success Criteria (Summary)

- Dodanie każdego rodzaju krewnego odbywa się obok widocznego drzewa i kończy podglądem dodanej osoby na tym samym widoku.
- Nowy użytkownik zaczyna drzewo z pustej strony drzewa, bez wychodzenia do `/persons`.
- Specy slotów, nowy spec strażnika, Pest i Vitest są zielone; zrzuty „po” pokazują brak przyciemnienia i slot/kartę centralną w kadrze.
