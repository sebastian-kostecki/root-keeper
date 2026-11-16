# Miejsca z repo dopasowane do spisu: Plan Brief

> Full plan: `context/changes/import-places-matched-to-gazetteer/plan.md`

## What & Why

Import ma przypisywać zdarzeniom miejsca ze spisu drzewa zamiast wpisywać je tekstem do notatki osoby (S-05, FR-009, FR-010, FR-017). Repo badawcze ma warianty nazw (Bukinia/Bokinia, Rostoka/Roztoka, formy rosyjskie), a spis zna tylko dokładne nazwy. Każde niepewne dopasowanie idzie więc do autora, zamiast tworzyć duplikat. Razem z S-03 to warunek pierwszego importu na produkcji.

## Starting Point

Po S-02 do S-04 `miejsce` z bloku trafia tylko do bloku „Z repo badawczego” w notatce. Zdarzenia nie dostają `locality_id`, a pochowania import nie tworzy. Spis ma unikalne nazwy miejscowości bez wariantów. Zdarzenia nie mają parafii (ma ją tylko źródło). Pochowanie w aplikacji to cmentarz plus jego miejscowość, i tylko przy osobie zmarłej.

## Desired End State

Po decyzjach o tożsamości komenda pokazuje miejsca:
- tabelę nowych miejscowości (zatwierdź wszystkie albo pojedynczo); nowy cmentarz wchodzi bez pytania,
- każde niepewne dopasowanie osobno („To Bukinia, zapamiętaj wariant / Dodaj jako nową / Bez miejsca”),
- wybór cmentarza, gdy jest ich kilka.

Każde pytanie pada raz na nazwę. Po zgodzie urodzenia, zgony i śluby mają miejscowości ze spisu, a pochowania cmentarze. Zatwierdzony wariant zostaje w drzewie, a spis nie ma duplikatów. Kolejny import porównuje miejsce regułą trzech wartości jak datę.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Parafie | Poza S-05; `miejsce` = miejscowość, parafia w S-06 przy źródle | Zdarzenia nie mają parafii; bez zmian schematu zdarzeń i UI | Plan (autor) |
| Pewne / niepewne / nowe | Pewne: ta sama nazwa bez wielkości liter i polskich znaków albo zapamiętany wariant. Niepewne: różnica 1 litery (nazwa do 6 liter) lub 2 (dłuższa) albo inny szyk słów. Reszta nowa | Łapie typowe warianty z aktów bez sklejania różnych wsi | Plan (autor) |
| Pamięć wariantów | Tabela `locality_aliases`; scalanie przenosi warianty, panel miejscowości je pokazuje | Każda decyzja zapada raz, także dla nowych osób | Plan (autor) |
| Zapis pochowania | Poprawka v1: `pochowek.miejsce` = miejscowość cmentarza, nowy `pochowek.cmentarz` | Pole nie było nigdzie użyte; bez drugiej wersji parsera | Plan (autor) |
| Potwierdzanie | Raz na nazwę; nowe zbiorczo albo pojedynczo; niepewne pojedynczo, domyślnie „Bez miejsca” | Wzór S-03/S-04; przypadkowy Enter nie skleja wsi | Plan (autor) |
| Pochowanie bez zgonu | Import tworzy też zgon bez daty, z ostrzeżeniem | Pochowanie jest widoczne w UI tylko przy osobie zmarłej | Plan (autor) |
| Scalanie miejscowości | Nazwa duplikatu zostaje wariantem celu | Ręczne scalenie w `/places` uczy też import | Plan |
| „Bez miejsca” | Nie zmienia kolumny; tekst zostaje w notatce | Nic z repo nie ginie, a ręczna miejscowość zostaje | Plan |
| Porównanie miejsca | Po rozpoznanej miejscowości lub cmentarzu, pamięć `miejsce` i `cmentarz` | „Bokinia” po zapamiętanym wariancie to „bez zmian” | Plan (S-04) |
| Cmentarz | Pewny przy zgodnej nazwie albo jedynym cmentarzu; nowy, gdy miejscowość nie ma cmentarzy; w innym razie wybór | Cmentarze w spisie zwykle nie mają nazwy | Plan |

## Scope

**In scope:**
- tabela wariantów ze scalaniem, usuwaniem i podglądem w panelu,
- poprawka kontraktu v1 i odczyt `cmentarz`,
- klucz pochowania `O-…/pochowek` w całym torze S-04,
- `MatchResearchPlaces` z regułą podobieństwa,
- reguła trzech wartości dla miejsca,
- notatka bez rozpoznanych miejsc,
- zapis nowych miejsc i miejsc zdarzeń,
- pytania w komendzie.

**Out of scope:**
- parafie i `events.parish_id`,
- edycja wariantów w UI i szukanie po nich w pickerze,
- zawieranie się nazw jako niepewne,
- rodzaj i szczegóły nowych miejsc,
- odczyt miejsca z prozy,
- `wersja: 2`,
- data pochowania w UI,
- poziomy A–D (S-08).

## Architecture / Approach

Tok importu:
1. odczyt,
2. tożsamość,
3. usunięte w repo i w aplikacji,
4. **`MatchResearchPlaces`** (werdykty miejscowości, potem cmentarzy, bez zapisu),
5. komenda zbiera `PlaceDecisions`,
6. `PlanImport` (miejsce jako pole zdarzenia, pochowanie jak zgon, nowe miejsca i warianty w planie),
7. zmiany i konflikty,
8. zgoda i kopia bazy,
9. `ApplyImport`: w jednej transakcji najpierw nowe miejscowości, warianty i cmentarze przez `StoreLocality` / `StoreCemetery`, potem wydarzenia z `locality_id` i cmentarzem przez `burialPlaceFrom`.

Wszystkie odczyty spisu idą przez drzewo konta.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Warianty w spisie | `locality_aliases`, scalanie, usuwanie, panel | Nowa tabela pominięta w scalaniu (reguła CLAUDE.md) |
| 2. Kontrakt i odczyt | Poprawka v1, `cmentarz`, ostrzeżenia, klucz pochowania, zgon z pochowania | Zmiana fixture rusza oczekiwania wielu testów |
| 3. Dopasowanie miejsc | `PlaceName`, `MatchResearchPlaces`, `PlaceDecisions` | Reguła za luźna (Bielin/Bielsk) albo za ścisła |
| 4. Plan importu | Miejsce w regule trzech wartości, plan pochowania, notatka, liczniki | `PlanImport` (1490 linii) rośnie; pytania o nazwy bez zmian |
| 5. Zapis | Nowe miejsca i miejsca zdarzeń atomowo, sprawdzenie pod blokadą | Niezmiennik pochowania obejściem `burialPlaceFrom` |
| 6. Komenda | Pytania raz na nazwę, podsumowanie, testy, ręczny przebieg | Czytelność przy ok. 70 nazwach na prawdziwych danych |

**Prerequisites:** S-02, S-03 i S-04 zrobione (są). Sail działa. Do ręcznego przebiegu autor przygotowuje kopię `test-pack-places` z miejscami w blokach.
**Estimated effort:** ~6 sesji, po jednej na fazę (Faza 4 najcięższa).

## Open Risks & Assumptions

- Prawdziwe repo nie ma jeszcze bloków. Regułę podobieństwa sprawdzi ręcznie tylko `test-pack`; na pełnych danych dopiero przy konwersji repo.
- Konwersja repo musi pisać `miejsce` jako jedną nazwę w mianowniku. Gdy znana jest tylko parafia, `miejsce` zostaje puste do S-06. Ostrzeżenie łapie tylko nawiasy, przecinki i „par.”.
- Pary typu Siedliszcze / Siedliszcze Bramowe wejdą jako nowe. Sprzątanie to ręczne scalenie, które od teraz zapamiętuje wariant.
- Drzewa po S-02 do S-04 (dev i test) zobaczą raz zmianę bloku notatki i uzupełnienie miejsc.

## Success Criteria (Summary)

- Import przypisuje zdarzeniom miejscowości i cmentarze ze spisu, a nowe i niepewne miejsca potwierdza autor, raz na nazwę. Spis nie dostaje duplikatów.
- Zatwierdzony wariant nie wraca jako pytanie, a drugi przebieg bez zmian kończy się „Nic do zapisania.”.
- Ręcznie zmienione miejsce nie znika bez pytania, cudze drzewo jest nietknięte, a pełny zestaw testów przechodzi.
