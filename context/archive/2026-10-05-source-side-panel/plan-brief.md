# Źródło w szerokim panelu bocznym (S-02) — Plan Brief

> Full plan: `context/changes/source-side-panel/plan.md`
> Research: `context/changes/source-side-panel/research.md`

## What & Why

Autor dodaje, ogląda i edytuje źródło w szerokim panelu bocznym obok listy: skan z powiększeniem stoi obok formularza opisu, zamiast na osobnej stronie i w modalach. Usuwanie po liście faktów tracących potwierdzenie działa jak dziś (FR-006, FR-027). Pola opisu zostają bez zmian, żeby S-03 zmieniał tylko treść opisu, a nie jednocześnie jego miejsce.

## Starting Point

Dziś podgląd to strona `/sources/{id}` ze skanem obok opisu tylko do odczytu, a dodawanie i edycja to modale. Powłoka panelu F-02 (wariant szeroki) i wzorzec konsumenta z S-01 (miejscowości: `?locality=`, resolver w kontrolerze, `<Form key>`, strażnik zmian) już istnieją. Strażnik jest jednak skopiowany per strona.

## Desired End State

`/sources?source={id}` otwiera obok listy panel: skan wypełnia wysokość panelu po lewej, a formularz 20rem, „Potwierdza” i „Zapisz” stoją po prawej. „Usuń” jest w nagłówku. `?source=new` pokazuje wybór pliku w kolumnie skanu i lokalny podgląd przed zapisem. Dodanie i usunięcie zachowują filtry listy. `/sources/{id}` przekierowuje do panelu. Niezapisane zmiany są chronione wspólnym strażnikiem, a poniżej `md` strona za pełnoekranowym panelem jest `inert`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Los `/sources/{id}` | Przekierowanie na `/sources?source={id}` | Stare linki i 404 dla cudzych działają, a testy przepinamy zamiast kasować. | Research |
| Zasięg `?source=` | Tylko lista `/sources` (resolver w `index`) | Wzór S-01; wejścia z modala osoby przechodzą na listę jak dziś. | Research |
| Układ przy 1366 px | Zawsze obok siebie | Wymóg PRD FR-006; ok. 375 px skanu, do zmierzenia. | Research |
| Tryb panelu | Od razu formularz | Jak panel miejscowości; jeden widok zamiast podglądu i edycji. | Research |
| Dodawanie | Lokalny podgląd pliku przed zapisem | Skan obok opisu od pierwszej chwili. | Research |
| Strażnik zmian | Wspólny hook + dialog dla miejscowości i źródeł | Drugi konsument, więc wydzielamy zamiast kopiować trzeci raz. | Research |
| E2E | Jeden nowy spec | Pierwszy test panelu, odłożony przez F-02 i S-01. | Research |
| Menu wiersza tabeli | Tylko „Otwórz” | „Edytuj” dawałoby ten sam panel pod inną etykietą. | Plan |
| Filtry po dodaniu/usunięciu | Zachowane (z adresu poprzedniej strony) | Praca w Tabeli zostaje w Tabeli; naprawia ograniczenie F5 z S-01. | Plan |
| Kolumny panelu | Formularz stałe 20rem, skan reszta | Sprawdzona szerokość pól; każdy dodatkowy piksel idzie na skan. | Plan |
| Pusty skan przy dodawaniu | Pole pliku w kolumnie skanu | Pierwszy krok tam, gdzie potem pojawi się podgląd. | Plan |
| `inert` poniżej `md` | Dodany teraz w powłoce | Domyka odłożone F2 F-02 dla wszystkich paneli. | Plan |
| Scenariusz E2E | Edycja z ochroną zmian | Pokrywa powłokę, strażnik i odświeżenie, których Pest nie widzi. | Plan |

## Scope

**In scope:**
- Resolver `sourcePanel`, przekierowania `show/store/destroy` z filtrami, przepięte testy Pest (z wyrocznią FR-027)
- `SourcePanel` (edycja, dodawanie z podglądem, usuwanie, „Potwierdza”), `SourceViewer` dopasowany do panelu
- Galeria, tabela, „Dodaj źródło”, modal osoby i picker otwierają panel; filtry zachowują klucz panelu
- Wspólny strażnik zmian (też w miejscowościach i na stronie demo) i `inert` poniżej `md`
- Usunięcie `sources/show.tsx`, `source-edit-dialog.tsx`, `source-create-dialog.tsx`, `source-details.tsx`
- Nowy spec E2E i `fact-source-scan.spec.ts` zawężony do panelu

**Out of scope:**
- Nowe pola opisu (S-03), panel źródła poza `/sources`, osoba w panelu (S-06)
- Przeciągnij i upuść pliku, usuwanie z menu tabeli, strażnik przy wyjściu z listy menu lub Wstecz
- E2E dodawania z podglądem (sprawdzenie ręczne)

## Architecture / Approach

Stan panelu żyje w adresie (`?source=`), dane w leniwej właściwości `sourcePanel` na `/sources` (`LocalityController` jako wzór, `PANEL_PROPS.source`). Zapis `update` wraca przez `back()`. `store` i `destroy` czytają filtry z poprzedniego adresu (Referer), bo filtr `kind` zderzyłby się z polem formularza `kind`. Panel to `SidePanel variant="wide"` z kontenerem: dwie kolumny od 36rem, poniżej jedna pod drugą.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Wspólny strażnik i `inert` | Hook i dialog porzucenia dla miejscowości i demo; `inert` poniżej `md` | Przywracanie fokusu do elementu w `inert` |
| 2. Backend panelu źródła | `sourcePanel`, przekierowania z filtrami, przepięte i nowe testy Pest | Osłabienie wyroczni FR-027 przy przepinaniu |
| 3. Panel źródła na liście | Panel edycji/dodawania/usuwania, galeria i tabela otwierają panel | Skan ok. 375 px przy 1366 px; cykl życia object URL |
| 4. Wejścia spoza listy i sprzątanie | Modal osoby i picker prowadzą do panelu; martwe pliki usunięte | Pominięte odwołanie do usuniętego komponentu |
| 5. E2E panelu źródła | Spec edycji z ochroną zmian; fakt → źródło zawężone do panelu | Niestabilność przy wspólnym drzewie (sufiksy) |

**Prerequisites:** F-02 (done), S-01 (done); Sail i serwer E2E (`scripts/e2e-server.sh`) działają lokalnie.
**Estimated effort:** ok. 3–4 sesje w 5 fazach (faza 3 największa).

## Open Risks & Assumptions

- Szerokość skanu przy 1366 px (ok. 375 px) wynika z arytmetyki, nie z pomiaru. Faza 3 ją mierzy. Gdyby okazała się nieużyteczna, wraca pytanie o układ (zmiana decyzji z badań).
- Między fazami 2 i 3 `fact-source-scan.spec.ts` jest czerwony (E2E poza CI).
- Zachowanie filtrów zakłada, że przeglądarka wysyła Referer z pełnym zapytaniem (domyślna polityka `strict-origin-when-cross-origin` dla tego samego originu). Bez niego lista wraca do domyślnych filtrów, a zapis działa.
- Vitest działa bez DOM, więc hook strażnika i panel pokrywają E2E i sprawdzenie ręczne, a nie testy jednostkowe.

## Success Criteria (Summary)

- Autor przy 1366 px dodaje, ogląda i edytuje źródło w panelu ze skanem obok formularza i nie traci niezapisanych zmian bez pytania.
- Usuwanie pokazuje fakty tracące potwierdzenie, a wyrocznia FR-027 w Pest dalej przechodzi.
- Lista zachowuje filtry i widok przy otwieraniu, dodawaniu i usuwaniu, a stare linki `/sources/{id}` trafiają do panelu.
