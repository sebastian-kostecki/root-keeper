# Spis miejscowości z istniejących miejsc (S-01) — Plan Brief

> Full plan: `context/changes/places-gazetteer/plan.md`
> Research: `context/changes/places-gazetteer/research.md`

## What & Why

Autor dostaje prywatny dla drzewa spis miejscowości (nazwa + opis), który startuje automatycznie z istniejących tekstowych miejsc: teksty różniące się tylko wielkością liter lub spacjami tworzą jedną miejscowość. Spis jest fundamentem strumienia miejsc. Od niego zależą parafie (S-03), miejsca faktów (S-07), zamieszkania (S-08) i cmentarze (S-09) — FR-007, FR-029.

## Starting Point

Miejsca to dziś wolny tekst w `events.place` (urodzenie, ślub, zgon) i `people.residence`. Nie ma tabeli miejscowości ani unikalnego indeksu w drzewie. Powłoka panelu bocznego F-02 jest gotowa, ale nie ma jeszcze żadnego konsumenta z formularzem.

## Desired End State

W sidebarze jest pozycja „Miejscowości” z licznikiem. Strona pokazuje alfabetyczną listę z wyszukiwaniem. Po wdrożeniu każde drzewo ma miejscowości zasiane ze swoich tekstów, a same teksty zostają nietknięte. Autor dodaje i poprawia miejscowości w panelu obok listy. Nazwa powtórzona z inną wielkością liter jest odrzucana, a cudza miejscowość to 404.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Pola | Nazwa + opis | FR-007 wymaga tylko tego; nazwy historyczne i gmina idą do opisu (Non-Goals). | Plan |
| Unikalność nazwy | `UNIQUE (tree_id, lower(name))` + walidacja w drzewie | Warianty wielkości liter nie tworzą duplikatów; homonimy autor rozróżnia w nazwie („Lipnica (pow. bocheński)”). | Plan |
| „Identyczny tekst” przy zasiewie | Grupa = `lower(btrim(tekst))` per drzewo; nazwa = najczęstszy wariant, remis → pierwszy w `COLLATE "C"` | Grupowanie musi być zgodne z indeksem; „Kraków” ×2 + „kraków” ×1 → „Kraków”. | Plan |
| Powiązanie tekst → miejscowość | Brak; S-07/S-08 dopasują po nazwie | Najmniej pracy teraz; poprawiona w spisie literówka może wrócić w S-07 (PRD dopuszcza ręczne poprawki). | Plan |
| Teksty | Kopiowane, nie usuwane | Reguła „copy first, drop later”; usunięcie dopiero po S-07/S-08. | Research |
| Nazewnictwo | `Locality`, `/localities`, `?locality=`, `localityPanel` | „place” koliduje z `events.place`; CLAUDE.md i roadmapa mówią „locality”. | Plan |
| Zawartość panelu | Tylko formularz nazwy i opisu | Najprostszy panel; użycia pojawią się z prawdziwymi odwołaniami (S-07, S-11). | Plan |
| Dodawanie | `?locality=new`, po zapisie panel nowego wpisu | Stan w URL jak w całej powłoce; od razu widać zapisany wpis. | Plan |
| Niezapisane zmiany | Modal „Odrzucić niezapisane zmiany?” przy zamknięciu, przełączeniu, dodaniu | Opis bywa długi; wzór z demo F-02. | Plan |
| Lista | Tabela + szukanie po stronie klienta, `Intl.Collator('pl')` | Sprawdzone klocki Źródeł i Osób; dziś kilkadziesiąt wpisów. | Plan |
| Testy | Pest + Vitest, bez E2E | test-plan §1 i §7: kontrakty pokrywa Pest, układ sprawdza autor. | Plan |
| `down()` zasiewu | No-op z komentarzem | Usunięcie zasianych wierszy skasowałoby poprawki autora; tabelę usuwa `down()` migracji tabeli. | Research |

## Scope

**In scope:**
- Tabela `localities`, model, fabryka, `Tree::localities()`, migracja zasiewu z testem
- Endpointy `index/store/update`, Policy, Form Requesty, akcje, Resource, resolver `?locality=` z `new`, licznik `navCounts`
- Strona spisu, panel z formularzem, wpis `PANEL_PROPS`, pozycja w sidebarze
- Przypadki w `OwnershipBoundariesTest`, `AppShellTest`, `arrangeBackupData()`; runbook liczności przed/po

**Out of scope:**
- Usuwanie i scalanie (S-11), wybór miejscowości w faktach i zamieszkaniach (S-07, S-08), parafie (S-03)
- Mapowanie tekst → miejscowość, dopisywanie miejscowości przy zapisie osoby
- Współrzędne i inne pola, lista użyć w panelu, sortowanie kolumn, test E2E
- Ostrzeżenie o niezapisanych zmianach przy wyjściu ze strony innym linkiem

## Architecture / Approach

Wzorzec modułu Sources: trasy resource → Form Request (autoryzacja `Gate::inspect`, unikalność `lower(name)` w drzewie) → Action → toast i przekierowanie. Strona `localities/index` dostaje listę i leniwą właściwość `localityPanel` (`null` | `{ locality: null }` | `{ locality }`), którą resolver wylicza z `?locality=` (404 dla cudzego i nieistniejącego id). Panel to `SidePanel` z F-02 z formularzem Inertii. `store` przekierowuje na `?locality={id}`, a `update` wraca `back()`, więc panel i lista przeliczają się same. Zasiew to jedno zapytanie SQL z `DISTINCT ON` i `ON CONFLICT DO NOTHING` na indeksie funkcyjnym.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Model danych i zasiew | Tabela, model, zasiew z tekstów z testem migracji, dane kopii | Błędne grupowanie lub utrata tekstu — chroni test z wyrocznią i runbook liczności |
| 2. Backend spisu | Endpointy, Policy, resolver panelu, licznik, testy własności | Pominięty przypadek 404 — strażnik kompletności tras go wymusza |
| 3. Strona spisu i panel | Lista z szukaniem, panel z formularzem, pytanie o porzucenie zmian | Pierwszy formularz w powłoce F-02 — przechwycenie kliknięć przy „brudnym” formularzu |

**Prerequisites:** F-02 (`done`); Sail z PostgreSQL (`en_US.utf8`, sprawdzone `lower()` dla polskich znaków).
**Estimated effort:** ~2–3 sesje w 3 fazach.

## Open Risks & Assumptions

- Danych produkcyjnych nie sprawdzano: warianty wielkości liter i diakrytyków są nieznane. Runbook pokazuje warianty do połączenia przed `migrate`.
- „Krakow”/„Kraków” (diakrytyki) i literówki zostają osobnymi wpisami do S-11. Zmiana nazwy na istniejącą jest blokowana walidacją.
- Teksty wpisane po S-01 nie trafiają do spisu do S-07/S-08. Miejscowość przemianowana w spisie przed S-07 może zostać zasiana ponownie w S-07 ze starego tekstu.
- Przy remisie reguła `COLLATE "C"` wybierze wariant pisany wielkimi literami („LIPNICA” przed „Lipnica”); autor poprawia go zmianą nazwy.

## Success Criteria (Summary)

- Po wdrożeniu spis każdego drzewa zawiera po jednej miejscowości na grupę tekstów, a żaden tekst miejsca się nie zmienił.
- Autor dodaje i poprawia miejscowości w panelu obok listy bez przeładowania strony i nie traci niezapisanego opisu przez przypadkowe kliknięcie.
- Cudza miejscowość — w trasie i w `?locality=` — to 404, a pełna suite Pest i Vitest jest zielona.
