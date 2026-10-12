# Strona „Miejsca” (/places) — Plan Brief

> Full plan: `context/changes/places-page/plan.md`
> Research: `context/changes/places-page/research.md`

## What & Why

Miejscowości i parafie to jedna domena: parafia ma siedzibę w miejscowości, a „użycia” miejscowości liczą parafie. Mimo to są dwiema stronami z dwoma wpisami w menu. Szukając „Tarnów”, autor musi sprawdzić obie i nie widzi powiązania.
Łączymy je w jedną stronę `/places` „Miejsca” z przełącznikiem zakładek. Przy okazji zamykamy duplikację kodu, ręczny pierścień fokusu, dwuznaczną kolumnę „Użycia” i uciętą tabelę parafii na telefonie (C1–C5).

## Starting Point

`pages/localities/index.tsx` i `pages/parishes/index.tsx` to niemal identyczne kopie (~200 linii): ten sam debounce, `openFromList`, panele boczne. Mają osobne kontrolery, 6 przekierowań na stare indeksy i dwa liczniki w menu. Wzór przełącznika sterowanego adresem, błędu wizyty i lepkiej kolumny już istnieje w `/sources`.

## Desired End State

Menu ma jeden wpis „Miejsca” z sumą. `/places` pokazuje „Miejscowości n | Parafie n” (`?kind=parafie`), wspólną wyszukiwarkę i tabelę zakładki.
W trakcie wyszukiwania liczniki pokazują trafienia w obu spisach. Gdy zakładka nie ma trafień, „Brak wyników” proponuje „Pokaż w parafiach (n)”. Pusta zakładka pozwala dodać pierwszy rekord.
Stare adresy przekierowują z zachowaniem panelu i frazy. Nieudana wizyta ma „Spróbuj ponownie”. Na 375 px nazwa jest przyklejona.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Wzór scalenia | A: `SegmentedToggle` w URL, tabela per zakładka | Kolumny typów się nie pokrywają; B łamią parafie bez siedziby, C pogarsza mobile | Research |
| Adres | Nowe `/places`; GET `/localities`, `/parishes` → 302 z `q` i kluczem panelu; 6 przekierowań po zapisie na `/places` | Stare zakładki w przeglądarce i deep linki dalej działają | Research |
| Kolumna liczby (C4) | Liczby bez zmian; u parafii nagłówek „Źródła” | Jedna nazwa nie może znaczyć dwóch rzeczy na jednej stronie | Research |
| Licznik w menu | Suma obu spisów (liczona w pasku, `navCounts` bez zmian) | Rozbicie widać na przełączniku | Research |
| Liczniki przy wyszukiwaniu | Trafienia w każdym spisie + „Pokaż w parafiach (n)” w „Brak wyników” | Autor od razu widzi trafienie w drugim spisie (sedno C1) | Plan |
| Segment z licznikiem 0 | Zawsze aktywny; pusta zakładka z „Dodaj …” | Pierwszy rekord dodaje się tą samą drogą; świadome odstępstwo od `/sources` | Plan |
| Parametr zakładki | `?kind=parafie`, domyślne `miejscowosci` pominięte | Konwencja: angielski klucz, polska wartość, jak `kind=akty` | Plan |
| Strażnik zmian | Jeden strażnik, klucz z prefiksem (`locality:3`) | Na stronie jest najwyżej jeden panel; prefiks usuwa kolizję id bez zmiany hooka | Plan |
| Mobile (C5) | `stickyCell` na pierwszej kolumnie obu tabel | Wzór `/sources` i `/persons`, bez ukrywania danych | Plan |
| Nieświeża lista | `ParishPanel.onLocalityCreated` → `reload(['localities','navCounts'])` | Jedyna zmiana w panelu; miejscowość dodana inline jest od razu widoczna | Plan |
| Testy starych indeksów | Przeniesione do `PlacesTest.php` i `places-index.test.ts` | Żaden przypadek nie znika; zatwierdzenie planu = zgoda na przeniesienie | Plan |

## Scope

**In scope:**
- `PlaceController` i trasa `/places`, przekierowania w 4 kontrolerach;
- `pages/places/index.tsx`, `lib/places-index.ts`, `components/places/places-empty-state.tsx`;
- obie tabele, `app-sidebar.tsx`, prop `onLocalityCreated` w `ParishPanel`;
- testy Pest i Vitest, spec e2e (przepięty + nowy), skan tokenów, `CLAUDE.md`, zrzuty „po” i werdykt.

**Out of scope:**
- wzory B i C;
- wnętrze paneli i dialogów;
- zmiana liczb w kolumnach;
- wnioskowanie zakładki z klucza panelu;
- wyszukiwanie bez diakrytyków;
- wspólny hook list dla `/persons` i `/sources`;
- `toHaveScreenshot`, nowe zależności.

## Architecture / Approach

Jedna strona trzyma jeden stan wyszukiwania i jedną wizytę (`withPlacesQuery` podmienia `q` i `kind`, zachowując klucze paneli), wzorem `/sources`. Przełączenie zakładki i wyszukiwanie przeładowują tylko `filters`, a obie listy przychodzą przy pełnym wejściu. Liczniki i podpowiedź drugiej zakładki liczą czyste funkcje (`placesCounts`, `otherKindHint`) pod Vitestem. Tabele i panele zostają per typ.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Strona /places i przekierowania | Działająca `/places`, 8 przekierowań, jeden wpis w menu, testy przeniesione | Przeniesienie testów i spec e2e zależny od adresu |
| 2. Tabele i stałe | `focusRing`, `stickyCell`, „Źródła”, wspólne stany puste | Lepka komórka w zaznaczonym wierszu |
| 3. Stany | Liczniki trafień, „Pokaż w …”, pusta zakładka, błąd i `busy`, odświeżenie po inline | Liczniki z żywego wyszukiwania vs tekst statusu z zatwierdzonego |
| 4. Utrwal to | Skan tokenów, reguła, spec `places-page.spec.ts`, zrzuty „po” i `verdict.md` | Pusta zakładka w danych E2E (seeder ma 1 parafię) |

**Prerequisites:** Sail działa; serwer E2E (`scripts/e2e-server.sh`, port 8100) z `TreeViewDemoSeeder` do zrzutów; zrzuty „przed” już są w `screenshots/before/`.
**Estimated effort:** ~3–4 sesje w 4 fazach.

## Open Risks & Assumptions

- Zakładamy, że nikt poza paskiem bocznym, breadcrumbs i testami nie linkuje do `/localities` ani `/parishes` (research: panele i pickery nie linkują). Zewnętrzne zakładki obsłuży 302.
- `/places?parish=3` bez `kind` otwiera panel nad zakładką Miejscowości bez zaznaczonego wiersza. Akceptujemy, bo przekierowanie z `/parishes` zawsze dodaje `kind`.

## Success Criteria (Summary)

- Autor szuka miejsca w jednym miejscu i widzi trafienia w obu spisach. Jednym kliknięciem przechodzi do spisu, w którym są.
- Stare adresy i deep linki z panelem dalej działają. Każdy zapis wraca na `/places` z właściwą zakładką.
- Regresja literału w widoku `/places` czerwieni `test:js`, a przejście między zakładkami i przekierowania pilnuje spec e2e.
