# Siatka źródeł z filtrami (S-10) — Plan Brief

> Full plan: `context/changes/sources-grid-with-filters/plan.md`

## What & Why

Lista `/sources` staje się gęstą siatką mniejszych kart i dostaje filtry po parafii/miejscu, roku i archiwum obok istniejącego wyszukiwania i rodzaju (FR-005, roadmapa S-10). Przy setkach źródeł sama siatka nie wystarczy — autor musi szybko zawęzić listę do aktów z danej miejscowości, lat i archiwum.

## Starting Point

Lista ładuje wszystkie źródła drzewa i filtruje w przeglądarce po nazwie i rodzaju; stan filtrów (`q`, `kind`, `view`, `sort`) jest w adresie. Galeria ma 2–4 kolumny wg szerokości ekranu, karta pokazuje już rodzaj, typ, rok, parafię/cmentarz, archiwum i „potwierdza N”. Obok jest sortowalna Tabela.

## Desired End State

Galeria ma 2–6 kolumn liczonych od szerokości obszaru listy (mniej przy otwartym panelu), karty są mniejsze, treść ta sama. W pasku są „Parafia / miejsce”, „Rok od”, „Rok do”, „Archiwum” i „Wyczyść filtry”; filtry są w adresie, działają w Galerii i Tabeli, przeżywają przeładowanie i dodanie źródła. Gdy filtr ukrywa źródła bez potrzebnych danych, lista mówi „Ukryto N źródeł bez roku / miejsca / archiwum”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Widok Tabela | Zostaje obok siatki, nowe filtry działają w obu | Tabela jest jedynym sortowalnym widokiem i ma już kolumny parafii i archiwum. |
| Znaczenie „parafia/miejsce” | Wybór ze spisu: miejscowość → akty parafii z siedzibą tam + nagrobki z jej cmentarza; parafia → tylko jej akty | Dokładne dopasowanie po id, bez fałszywych trafień fragmentu tekstu. |
| Znaczenie „rok” | Zakres od–do, włącznie, każda granica opcjonalna | Przegląd dekady to jedno ustawienie zamiast wielu. |
| Archiwum | Wybór z użytych wartości, dokładna równość | Jeden klik, warianty pisowni widoczne jako osobne pozycje. |
| Źródła bez danych | Ukryte z licznikiem „Ukryto N … bez …” | Wynik dokładny, a autor wie o nieopisanych źródłach (wzorzec z S-04). |
| Karty | Gęściej, ta sama treść, kolumny z zapytań kontenerowych | Spełnia FR-005 bez nowej treści; siatka nie ściska się przy panelu. |
| Układ filtrów | W pasku obok wyszukiwania, zawijane na wąskim ekranie | Wszystko widoczne, jeden klik. |
| Liczniki rodzaju | Liczą po wszystkich filtrach poza rodzajem | Licznik mówi, ile pokaże klik. |
| Gdzie filtrowanie | W przeglądarce, filtry w adresie (`place`, `year_from`, `year_to`, `archive`) | Kontynuacja decyzji z widoków listy; skala „mała” z PRD. |
| Walidacja na serwerze | Tylko kształt; wartość spoza opcji klient traktuje jak nieustawioną | Częściowe przeładowanie `filters` nie może pytać bazy o źródła. |
| „Wyczyść filtry” | Zeruje wyszukiwanie, rodzaj, miejsce, lata, archiwum; zostawia widok i sortowanie | Czyści to, co zawęża listę, nie sposób jej oglądania. |
| E2E | Jeden test ryzyka: miejsce + lata → przeładowanie → „Wyczyść filtry” | Pokrywa podłączenie pól, adresu i siatki; logikę pokrywa Vitest. |

## Scope

**In scope:**

- Normalizacja, echo i przenoszenie nowych filtrów (po dodaniu/usunięciu źródła; zdejmowanie filtra, który ukryłby nowe źródło)
- Listy opcji miejsc i archiwów; `parish_locality_id` w zasobie źródła
- Filtrowanie, liczniki rodzaju, licznik ukrytych nieopisanych; puste stany z „Wyczyść filtry”
- Gęsta siatka z zapytaniami kontenerowymi
- Testy Pest, Vitest i jeden E2E

**Out of scope:**

- Filtrowanie/paginacja po stronie serwera; zmiany wyboru źródła do faktu (S-04)
- Sortowanie w Galerii, nowe kolumny sortowania tabeli
- Opcje „bez roku/miejsca/archiwum”, wielokrotny wybór, zwijanie polskich znaków
- Nowa treść kart, spis archiwów

## Architecture / Approach

Cztery nowe klucze adresu idą tą samą drogą co `q`/`kind`: serwer normalizuje kształt i odsyła je w `filters`, dokłada domknięcia `placeOptions` i `archiveOptions` oraz `parish_locality_id` w zasobie. Klient buduje adres przez `withSourcesQuery` i liczy wszystko jedną funkcją `filterSources` (widoczne, liczniki rodzaju, ukryte nieopisane), z której korzystają siatka, tabela, status i pasek.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kontrakt adresu i dane (backend) | Normalizacja, opcje, `parish_locality_id`, Referer, Pest | Zapytanie o miejscowości z `orWhere` wyciekające poza drzewo |
| 2. Logika filtrowania (lib) | Typy, adres, `filterSources` z licznikami i ukrytymi, Vitest | Nowa sygnatura `filterSources` — wszyscy wywołujący do przepięcia |
| 3. Pasek filtrów i gęsta siatka (UI) | Pola, „Wyczyść filtry”, „Ukryto N…”, puste stany, siatka | Opóźnione wizyty lat gubione przy kliku w kartę |
| 4. E2E filtrów | `sources-grid-with-filters.spec.ts` | Wyścig 300 ms opóźnienia pól roku w teście |

**Prerequisites:** S-09 (cmentarz nagrobka) — zrobione; brak zmian schematu.
**Estimated effort:** ~2–3 sesje w 4 fazach.

## Open Risks & Assumptions

- Zakładamy skalę „małą” (setki źródeł) — filtrowanie i listy opcji w całości w przeglądarce.
- Po scaleniu miejscowości (S-11) stary adres z `place=miejscowosc-<duplikat>` pokaże filtr jako nieustawiony — świadomy kompromis zamiast walidacji na serwerze.
- Wybór źródła do faktu nadal używa fragmentu tekstu i dokładnego roku — dwie różne reguły „miejsca” w aplikacji.

## Success Criteria (Summary)

- Autor wybiera miejscowość i zakres lat i widzi tylko pasujące źródła; liczniki i komunikat o ukrytych mówią prawdę.
- Filtry przeżywają przeładowanie, panel i dodanie źródła; „Wyczyść filtry” przywraca pełną listę.
- Siatka mieści 5–6 kart w rzędzie na szerokim ekranie i nie ściska się przy otwartym panelu.
