# Widok /persons zgodny z systemem projektowym — Plan Brief

> Full plan: `context/changes/persons-index-ui/plan.md`
> Research: `context/changes/persons-index-ui/research.md`

## What & Why

`/persons` to strona startowa po zalogowaniu, a odbiega od systemu projektowego w warstwie wzorców, nie kolorów. Nową osobę dodaje się w modalu, który po zapisie wyrzuca użytkownika na drzewo. Nagłówek i wyszukiwarka różnią się od sąsiednich list, prymitywy są skopiowane z klas, a nieudane wyszukiwanie wygląda jak wynik. Zmiana zamyka zarzuty C1–C5 audytu `/10x-ui` i zostawia regułę oraz kontrolę, żeby następny widok nie rozjechał się tak samo.

## Starting Point

Lista i tabela stoją na tokenach. „Dodaj osobę” to `PersonCreateDialog` (modal), a zapis przekierowuje na `/tree/{id}?person={id}`. Zmiana `tree-add-person-panel` zbudowała już panel dodawania osoby (`?add=…`) z trybem samego formularza i redirect `PersonPanelRedirect` „ten sam widok + podgląd osoby”. Jej Faza 3 jest zacommitowana (`af8606e`), ale kolejne fazy drzewa edytują w drzewie roboczym `tree/show.tsx` i `family-sheet.tsx`.

## Desired End State

„Dodaj osobę” otwiera obok listy panel z formularzem. Zapis zamienia go na podgląd nowej osoby, a lista zostaje z tymi samymi filtrami. Wyszukiwanie i przełączanie widoku nie zamykają paneli, a brudny formularz chroni pasek niezapisanych zmian. Strona ma `<h1>` i landmark wyszukiwania, skoki do litery są prawdziwymi przyciskami, fokus pochodzi z jednej stałej, błąd daty i błąd wyszukiwania używają tokenu `destructive`. Test Vitest czerwieni się przy literale w widoku.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Tworzenie osoby | Panel boczny, po zapisie podgląd nowej osoby, lista zostaje | Odwraca S-06; ta sama osoba nie może mieć dwóch wzorców (nowa w modalu, istniejąca w panelu) | Research |
| Klucz URL i komponent | `?add=person` w panelu dodawania z drzewa, tryb `startsInForm`, panel dostaje gotowy cel od strony | Bez nowego komponentu; panel nie musi znać stron | Plan |
| Filtry po zapisie | Zachowane (`q/view/sort` z Referera), nawet gdy nowa osoba ich nie spełnia | Domyślne zachowanie `PersonPanelRedirect`, spójne z drzewem; użytkownik wraca tam, skąd przyszedł | Plan |
| Wyszukiwanie przy otwartym panelu | Panel zostaje (`withPersonsQuery`, jak „Źródła”); dotyczy też podglądu osoby | Pozwala sprawdzić duplikat bez utraty formularza | Plan |
| Stała fokusu | `lib/focus-ring.ts` tylko w miejscach widoku `/persons`; pozostałe 10 plików deferred | Jeden widok na zmianę | Research |
| Stan błędu | `onHttpException`/`onNetworkError` → `false`, komunikat + „Spróbuj ponownie” przy polu | Stare wyniki nie mogą udawać nowych | Research + Plan |
| Pokrycie testami | Pest (redirect), Vitest (cel, query), jeden spec E2E (tworzenie, panel przy wyszukiwaniu, strażnik, błąd) | Modal → panel to największa zmiana zachowania i dziś nie ma testu | Plan |
| Kontrola „Utrwal to” | Blok UI w `CLAUDE.md` + test Vitest skanu literałów w plikach `/persons` | Regresja jest czerwona w CI, bez nowej zależności | Plan |
| Bramka wizualna | Zrzuty `before/after` + `verdict.md` w folderze zmiany, bez `toHaveScreenshot` | Konwencja repo | Research |

## Scope

**In scope:**
- C1: panel tworzenia, redirect `store`, usunięcie `person-create-dialog.tsx` (D2 znika)
- C2: `Heading variant="page"`, `role="search"`, `iconOnlyBelowSm`
- C3: skoki do litery jako `Button outline sm asChild`; stała fokusu w `persons-list`, `persons-table`, `table-parts`
- C4: błąd daty na tokenie; C5: stan błędu i spójne „w trakcie”
- Spec E2E, test skanu, reguła w `CLAUDE.md`, zrzuty

**Out of scope:**
- Stała fokusu poza widokiem (10 plików), „dom” (D1), badge Probant (D3), `PersonAvatar` (D4), paginacja (D5), tabela na mobile (D6, tylko oceniona), `align-[-2px]` (D7)
- Przenoszenie plików panelu z `components/tree/`, serwerowy prop dla `add`, kitchen sink / `toHaveScreenshot`

## Architecture / Approach

Strona `/persons` czyta `add` z URL i podaje `AddPersonPanel` cel `{ kind: 'person' }` (drzewo podaje swój cel z arkusza). Config celu `person` to sam formularz `PersonController.store.form()` bez listy i bez ładowania kandydatów. Zapis idzie przez `PersonPanelRedirect::to(…, route('persons.index'))`, więc serwer odsyła na Referera bez `add` i z `person=<id>`, a layout pokazuje podgląd. Wizyty wyszukiwania ustawiają tylko `q/view/sort` na adresie na żywo, więc klucze paneli przeżywają, a strażnik zagnieżdżonego providera blokuje tylko wizyty opuszczające panel.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tokeny i stała fokusu | Zrzuty „przed”, `focusRing`, błąd daty na tokenie | `table-parts` zmienia się w 4 tabelach — wygląd musi zostać identyczny |
| 2. Widok listy | `<h1>`, landmark, ikony na mobile, skoki jako `Button` | `variant="page"` zmienia rytm odstępów nad wyszukiwarką |
| 3. Panel tworzenia | `?add=person`, redirect z filtrami, panel przeżywa wyszukiwanie, modal usunięty | Konflikt z Fazą 3 drzewa w tych samych plikach — start po jej commicie |
| 4. Stany wyszukiwania | Błąd + ponowienie, „w trakcie” dla każdej gałęzi, macierz 7 stanów | Wyścig: błąd tylko z najnowszej wizyty |
| 5. Utrwal to i bramka | Spec E2E, test skanu, reguła `CLAUDE.md`, zrzuty „po” + werdykt | Spec na wspólnym użytkowniku E2E — sprzątanie po sufiksie |

**Prerequisites:** Faza 3 `tree-add-person-panel` zacommitowana (spełnione) i czyste `tree/show.tsx` + `family-sheet.tsx` przed Fazą 3 tej zmiany (Fazy 1–2 niezależne); serwer E2E (`scripts/e2e-server.sh`) do zrzutów i specu.
**Estimated effort:** ~3 sesje (1–2 razem, 3 osobno, 4–5 razem).

## Open Risks & Assumptions

- Zachowanie paneli przy wyszukiwaniu zmienia też dzisiejsze zachowanie podglądu osoby na `/persons` (dziś wyszukiwanie go zamyka). To decyzja świadoma, przypięta w weryfikacji ręcznej 3.10.
- Wnioski wizualne z research (`mb-8` przesuwa przycisk, waga D6) potwierdza dopiero zrzut „przed” w Fazie 1.
- Faza 5 drzewa może przepisać tę samą linię `CLAUDE.md` — kto będzie drugi, scala wyjątek „Dodaj osobę na /persons”.

## Success Criteria (Summary)

- Dodanie kilku osób z rzędu z listy nie wymaga opuszczania `/persons`, a wpisane dane nie giną przy wyszukiwaniu ani przy kliknięciu w listę.
- Nieudane wyszukiwanie jest widoczne i da się je ponowić.
- Literał koloru lub wartość arbitralna w widoku `/persons` czerwieni `npm run test:js`.
