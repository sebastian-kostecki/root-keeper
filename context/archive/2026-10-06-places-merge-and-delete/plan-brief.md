# Scalanie i usuwanie miejscowości i parafii (S-11) — Plan Brief

> Full plan: `context/changes/places-merge-and-delete/plan.md`

## What & Why

Autor scala zduplikowaną miejscowość lub parafię z właściwym wpisem — wszystkie odwołania przechodzą na właściwy — i usuwa wpis, którego nic nie używa (FR-012). Automatyczne przeniesienie tekstowych miejsc do spisu zostawiło literówki („Luboml” / „Lubomla”), które dziś można tylko przemianować, a nie połączyć; spis ma zawierać każdą miejscowość raz.

## Starting Point

Spisy miejscowości i parafii istnieją z panelem bocznym (dodawanie, edycja), ale bez usuwania, scalania i liczby użyć. Do miejscowości odwołują się cztery kolumny (siedziba parafii, cmentarz nagrobka, miejsca faktów z pochowaniem, zamieszkania), do parafii — akty. Wszystkie klucze obce mają `NO ACTION`, więc baza nie pozwoli usunąć używanego wpisu.

## Desired End State

Spisy mają kolumnę „Użycia”. W panelu wpisu „Scal z…” otwiera modal z wyborem właściwego wpisu i liczbą przenoszonych odwołań; po scaleniu otwiera się panel właściwego wpisu, duplikat znika, a każde jego odwołanie wskazuje na właściwy. „Usuń” usuwa nieużywany wpis, a dla używanego mówi, gdzie jest używany, i podsuwa scalenie. E2E nie zostawia już miejscowości ani parafii w bazie testowej.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres odwołań i atomowość | 4 rodzaje odwołań miejscowości + akty parafii, jedna transakcja, potem usunięcie duplikatu | Wymóg z CLAUDE.md i roadmapy S-11 (w tym `residences`). | Roadmap / CLAUDE.md |
| Usuwanie | Tylko nieużywany wpis | FR-012 po rewizji Sokratesa. | PRD |
| Punkt wejścia scalania | „Scal z…” w panelu duplikatu → modal z wyborem właściwego | Literówkę zwykle widać na błędnym wpisie; modal to wzorzec małych pickerów. | Plan |
| Dane duplikatu | Puste pola właściwego uzupełnia duplikat; różne opisy są złączane; nazwa właściwego zostaje | Nic nie ginie; wyznanie parafii przechodzi tylko bez kolizji indeksu. | Plan |
| Zamieszkania po scaleniu | Redukcja tylko identycznych (osoba, lata od–do); różne lata zostają | Bez dubli i bez utraty przerw w zamieszkaniu. | Plan |
| Usuwanie używanego | Dialog z miejscami użycia i przyciskiem „Scal z…”, bez usuwania | Autor wie, dlaczego i co dalej; wzorzec `delete-source-dialog`. | Plan |
| Liczba użyć | Kolumna „Użycia” w obu spisach | Widać nieużywane wpisy i dublety bez otwierania paneli. | Plan |
| Różne wyznania parafii | Scalenie dozwolone, modal pokazuje pełne etykiety | Decyzja autora; budżet pytań wyczerpany — przyjęte domyślnie. | Plan |
| Testy | Pest + sprzątanie w `cleanupBySuffix` + jeden E2E scalania | Logika w Pest, kierunek scalania w przeglądarce, zamknięty wyjątek czyszczenia. | Plan |

## Scope

**In scope:**
- Trasy `destroy` i `merge` dla miejscowości i parafii, polityki `delete`, akcje z blokadami
- Liczby użyć w zasobach, kolumna „Użycia”, modale scalania i usuwania w panelach
- Pest (`LocalitiesTest`, `ParishesTest`, `OwnershipBoundariesTest`, `DeleteImpactTest`), Vitest, E2E

**Out of scope:**
- Podpowiadanie podobnych nazw, scalanie wielu naraz, cofanie scalenia, wybór pola po polu
- Łączenie zamieszkań o różnych latach
- Zmiany i usuwanie zamrożonych tekstów `events.place` / `people.residence`

## Architecture / Approach

Kontroler (`destroy` / jednoakcyjny `…MergeController`) → Form Request (`target_id` z drzewa, różny od scalanego) → akcja w `DB::transaction` z `lockForUpdate` obu wierszy. Kolejność w scalaniu: masowe `UPDATE` odwołań → redukcja identycznych zamieszkań → usunięcie duplikatu → uzupełnienie pustych pól właściwego (po usunięciu, bo wyznanie parafii mogłoby zderzyć się z duplikatem w indeksie). Usuwanie liczy użycia pod blokadą; błąd FK `23503` przy wyścigu zamienia się w ten sam komunikat walidacji. Przekierowanie z toastem otwiera panel właściwego wpisu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend — miejscowości | Liczby użyć, scalanie 4 rodzajów odwołań, usuwanie nieużywanej, Pest | Pominięty rodzaj odwołania lub błędna redukcja zamieszkań z `NULL` latami |
| 2. Backend — parafie | To samo dla aktów; wyznanie bez kolizji | Kolizja indeksu `(nazwa, wyznanie)` przy uzupełnianiu |
| 3. Frontend | Kolumna „Użycia”, modale „Scal z…” i „Usuń” w obu panelach | Odwrócony kierunek scalania w UI; porzucenie niezapisanej edycji |
| 4. E2E (`/10x-e2e`) | Sprzątanie spisów po sufiksie + test scalania | Kolejność sprzątania wbrew FK |

**Prerequisites:** S-03, S-07, S-08, S-09 zakończone (wszystkie rodzaje odwołań istnieją); środowisko Sail i serwer E2E.
**Estimated effort:** ~2–3 sesje w 4 fazach.

## Open Risks & Assumptions

- Złączony opis może przekroczyć 5000 znaków; przy następnej edycji walidacja poprosi o skrócenie (świadomie — inaczej opis by przepadł).
- Scalenie jest nieodwracalne w aplikacji; jedyną drogą odwrotu jest kopia bazy.
- Linia wyjątku w `test-stack.md` (sekcja prowadzona przez `/10x-e2e-setup`) jest usuwana ręcznie w Fazie 4.

## Success Criteria (Summary)

- Autor scala „Lubomla” z „Luboml” z panelu i każdy fakt, zamieszkanie, parafia i nagrobek pokazuje „Luboml”, a „Lubomla” znika ze spisu.
- Nieużywany wpis da się usunąć, używanego nie — z wyjaśnieniem i drogą do scalenia.
- Cudzy wpis to 404, cel z cudzego drzewa to błąd walidacji; testy E2E nie zostawiają wpisów spisu.
