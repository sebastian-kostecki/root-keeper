---
project: root-keeper
version: 6
status: draft
created: 2026-10-04
updated: 2026-10-24
prd_version: 1
main_goal: market-feedback
top_blocker: decisions
milestone_id: research-in-the-tree
milestone_seq: 3
milestone_status: open
---

# Roadmap: root-keeper

> Derived from `context/foundation/prd.md` (v1 z 2026-10-23 — PRD zmiany: import badań z repo badawczego) + auto-researched codebase baseline (2026-10-23).
> Previous milestones: M-1 (`sourced-family-tree`) — full roadmap in git history (`git show 685ffa2:context/foundation/roadmap.md`); M-2 (`described-sources-and-places`) — full roadmap with its Done list in git history (`git show f5646c2:context/foundation/roadmap.md`).
> Edit-in-place; archive when superseded.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-3: Badania z repo widoczne w drzewie** — Status: open

- **Intent:** Autor przenosi swoich przodków z repo badawczego do drzewa komendą importu zamiast przepisywać ich ręcznie: komenda czyta bloki danych z plików, pokazuje podsumowanie, dopasowania i konflikty, zapisuje tylko to, co autor zatwierdził, a kolejny import aktualizuje zamiast dublować. Ręczne poprawki, dane dodane tylko w aplikacji i cudze drzewa zostają nietknięte.
- **Source materials:** `context/foundation/prd.md` (v1 z 2026-10-23, zmiana brownfield; poprzednie PRD w `context/foundation/archive/prd-v1.md` – `prd-v5.md`), `context/foundation/shape-notes.md` (blok „Forward: technical-roadmap”).
- **Done when:** every F-NN and S-NN below is `done`. `S-10` (FR-020, nice-to-have) jest jedynym wycinkiem odcinalnym; reszta jest konieczna.
- **Scope anchors:** FR-001 – FR-011, FR-014 – FR-020 (FR-012 i FR-013 odrzucone w PRD), US-01 – US-03. Zakres importu w M-3: przodkowie z numerem Ahnentafel (numeracja bezpośrednich przodków z `_PRZODKOWIE.md` repo badawczego) i ich źródła; linie boczne później.

## Vision recap

Autor prowadzi badania genealogiczne w repo markdown (dziś 283 pliki osób, dziesiątki opisanych aktów, skany) i przepisuje je ręcznie do root-keepera; przepisywanie nie nadąża za badaniami. Repo badawcze jest źródłem prawdy dla zbadanych faktów, a root-keeper jego uporządkowanym widokiem — pomostem jest ustrukturyzowany blok danych utrzymywany w plikach repo obok prozy, z którego komenda importu czyta osoby, relacje, wydarzenia, miejsca i źródła. Każdy rekord pamięta swój stały identyfikator z repo, więc import można powtarzać po każdej sesji badawczej.

## North star

**S-02: autor importuje przodków z repo do pustego drzewa (np. konta testowego) i widzi ich w drzewie z relacjami i datami** — to najmniejszy przebieg całego toru (bloki → podsumowanie → zatwierdzenie → zapis → drzewo) na prawdziwych plikach; przy celu `market-feedback` (jak najwcześniej sprawdzić na prawdziwych danych) idzie zaraz po odczycie bloków, a dopasowanie do ręcznie wpisanych osób (`S-03`) buduje już na działającym torze.

> „Gwiazda przewodnia” oznacza tu najmniejszy kompletny wycinek, którego dostarczenie dowodzi, że zmiana działa zgodnie z zamierzeniem. Stoi tak wcześnie, jak pozwalają jego zależności, bo reszta ma znaczenie tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                          | Outcome (user can …)                                                                                          | Prerequisites | PRD refs                                              | Status   |
| ---- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------- | ------------- | ----------------------------------------------------- | -------- |
| S-01 | research-blocks-read-and-report    | uruchomić komendę na katalogu repo i dostać raport przeczytanych osób oraz pominiętych wpisów z powodem         | —             | FR-001, FR-002, FR-003, FR-004                        | done |
| S-02 | first-import-into-empty-tree       | zaimportować przodków do pustego drzewa po podsumowaniu i zatwierdzeniu i zobaczyć ich z relacjami i datami      | S-01, S-07    | US-01, FR-003, FR-005, FR-006, FR-008, FR-009, FR-016 | done |
| S-03 | first-import-matches-hand-entered  | przy pierwszym imporcie dopasować osoby z repo do ręcznie wpisanych i rozstrzygnąć przypadki wątpliwe            | S-02          | US-01, FR-005, FR-006, FR-007, FR-014                 | in-progress |
| S-04 | reimport-changes-and-conflicts     | ponowić import i w podglądzie zatwierdzać nowe, zmienione, konflikty z ręcznymi zmianami i usunięte w repo        | S-02          | US-02, FR-005, FR-006, FR-008, FR-014, FR-015         | blocked  |
| S-05 | import-places-matched-to-gazetteer | dostać miejsca wydarzeń (także pochowanie na cmentarzu) dopasowane do spisu i potwierdzić nowe w podglądzie      | S-02          | FR-009, FR-010, FR-017                                | proposed |
| S-06 | import-sources-with-scans          | dostać źródła z opisem i skanem podpięte do faktów z numerem wpisu                                             | S-05          | FR-001, FR-011, US-01                                 | blocked  |
| S-07 | fuzzy-event-dates                  | zapisać i zobaczyć datę wydarzenia jako zakres lat, „przed” albo „po” — ręcznie i z importu                      | —             | FR-009, FR-015                                        | done |
| S-08 | fact-confidence-level              | zobaczyć i ustawić przy fakcie poziom pewności A–D — ręcznie i z importu                                        | S-02          | FR-018, FR-015                                        | proposed |
| S-09 | surname-variant-search             | dostać warianty zapisu nazwiska z importu i znaleźć osobę w wyszukiwarce po każdym wariancie                     | S-02          | US-03, FR-019                                         | proposed |
| S-10 | import-from-scratch-outside-prod   | poza produkcją zaimportować „od zera”, czyszcząc najpierw osoby i fakty drzewa konta (z potwierdzeniem)          | S-02          | FR-020                                                | proposed |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                         | Chain                                       | Note                                                                                                                              |
| ------ | ----------------------------- | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| A      | Tor importu i tożsamość osób  | `S-01` → `S-02` → `S-03`, `S-04`            | Rdzeń: najpierw prawdziwe pliki, potem gwiazda przewodnia `S-02`; `S-03` i `S-04` czekają na decyzję o podglądzie w terminalu.     |
| B      | Miejsca i źródła              | `S-05` → `S-06`                             | Dołącza do A w `S-02`; warunek pierwszego importu na produkcji (spis miejsc bez duplikatów), `S-06` czeka na decyzję o źródłach bez skanu. |
| C      | Szczegóły faktów i osób       | `S-07`, `S-08`, `S-09`, `S-10`              | `S-07` idzie przed `S-02` (decyzja autora 2026-10-23); `S-08` – `S-10` dołączają do A w `S-02`; każda gałąź dodaje jedno pole lub tryb. |

## Baseline

What's already in place in the codebase as of `2026-10-23` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** partial — daty wydarzeń formatuje `formatPartialDate` (`resources/js/lib/partial-date.ts`), tylko dokładne i „ok.”; fakty ze źródłami w panelu osoby (`components/persons/sources-tab.tsx`, `overview-tab.tsx`, `event-source-status.tsx`); brak poziomu A–D, wariantów nazwiska i znacznika importu; wyszukiwarka w `pages/persons/index.tsx`.
- **Backend / API:** partial — Actions zapisu do ponownego użycia poza HTTP (`Persons/CreatePerson`, `Relations/CreateMarriage`, `Relations/CreateParentChildRelation`, `Events/LinkSourceToEvent`, `Localities|Parishes|Cemeteries/Store*`, `Merge*`); `Sources/StoreSource` przyjmuje tylko `UploadedFile`; brak `app/Actions/Import` i komendy importu (jedyna komenda: `RestoreBackup`, bez Laravel Prompts); wyszukiwanie osób w `PersonController` tylko po `first_name` / `last_name`; parser YAML (`symfony/yaml`) jest tylko zależnością pakietów dev.
- **Data:** partial — data wydarzenia = rok / miesiąc / dzień + `date_is_approximate` (bez zakresu, „przed”, „po”); brak identyfikatora repo, poziomu A–D, wariantów nazwiska, sygnatury źródła i śledzenia zmian od ostatniego importu; skan źródła wymagany; numer wpisu na powiązaniu fakt–źródło; miejsca unikalne w drzewie, dopasowanie nazw tylko dokładne (bez wielkości liter); małżeństwo to wydarzenie z uczestnikami, rodzic–dziecko to osobna tabela.
- **Auth:** present — Fortify i polityki własności z 404 (`app/Policies/`), `tests/Feature/OwnershipBoundariesTest.php`; komenda importu nie przechodzi przez Policy (reguła „Research repo import” w `CLAUDE.md`).
- **Deploy / infra:** present — `scripts/deploy.sh` z `backup:run --only-db` przed `migrate` (`scripts/lib/production-deploy.sh`); codzienny backup bazy i `storage/app`; obraz produkcyjny instaluje `--no-dev`; kopii bazy tuż przed zapisem importu jeszcze nie ma.
- **Observability:** partial — domyślne logi i `backup:monitor`; import potrzebuje tylko własnego raportu w terminalu.
- **Testy:** present — Pest (1036), Vitest, Playwright w CI; brak `tests/Fixtures/research-repo/` i testów komend z wyborem (`expectsChoice`).
- **Repo badawcze:** absent — żaden plik osoby nie ma jeszcze bloku danych (0 z 246 w kopii `storage/gen-tree-import/`, 0 z 283 w żywym repo).

## Foundations

Brak. Każdy element przekrojowy wchodzi w pierwszym wycinku, który go używa: format bloku osoby, wymyślone repo testowe i parser — `S-01`; identyfikator repo przy rekordach, test granicy drzewa dla komendy i kopia bazy przed zapisem — `S-02`; śledzenie ręcznych zmian — `S-04`; format bloku źródła — `S-06`.

## Slices

### S-01: Odczyt bloków danych z repo i raport

- **Outcome:** autor uruchamia komendę importu z e-mailem konta i katalogiem repo; komenda czyta bloki danych z plików przodków (format bloku osoby uzgodniony i opisany jako kontrakt, z regułą utrzymania do dopisania w `CLAUDE.md` repo badawczego), sprawdza je i wypisuje raport: ile osób przeczytano, które wpisy pominięto i dlaczego — bez zapisu do bazy.
- **Change ID:** research-blocks-read-and-report
- **PRD refs:** FR-001, FR-002, FR-003, FR-004
- **Prerequisites:** —
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:**
  - Czy parser YAML (`symfony/yaml`) dochodzi do zależności produkcyjnych (nowa zależność — zgoda autora), czy blok ma inny format? Owner: autor. Block: no.
  - Jak blok jest wersjonowany i gdzie stoi w pliku (na górze czy obok prozy)? Owner: `/10x-plan`. Block: no.
- **Risk:** Pierwszy kontakt z prawdziwymi plikami — przy celu `market-feedback` idzie pierwszy, bo ujawnia, czy blok da się napisać dla prawdziwych przodków, zanim cokolwiek trafi do bazy. Konwersja plików przodków dzieje się w repo badawczym (autor z Claude) i zjada ten sam czas co reszta M-3; testy działają wyłącznie na wymyślonym repo w `tests/Fixtures/`.
- **Status:** done

### S-02: Pierwszy import przodków do pustego drzewa

- **Outcome:** autor importuje przodków do drzewa bez ręcznych wpisów (np. konta testowego): komenda pokazuje podsumowanie, po zatwierdzeniu zapisuje osoby (imiona, nazwisko, płeć), relacje rodzic–dziecko, małżeństwa oraz urodzenia, śluby i zgony z każdym rodzajem daty z `S-07` (dokładna, „ok.”, zakres, „przed”, „po”), każdy rekord z identyfikatorem z repo; na produkcji przed zapisem powstaje kopia bazy; zapis trafia wyłącznie do drzewa wskazanego konta; autor widzi przodków w drzewie i w widoku przodków.
- **Change ID:** first-import-into-empty-tree
- **PRD refs:** US-01, FR-003, FR-005, FR-006, FR-008, FR-009, FR-016
- **Prerequisites:** S-01, S-07 (decyzja autora 2026-10-23: daty nieostre w schemacie przed pierwszym importem)
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:**
  - ~~Wydarzenia bez miejsca do czasu `S-05`, a pochowanie (wymaga cmentarza) wchodzi dopiero w `S-05` — czy to akceptowalne na koncie testowym?~~ Rozstrzygnięte w planie (2026-10-23): miejsca, pochowanie, poziomy, notatki wydarzeń i warianty nazwiska trafiają tekstem do notatki osoby do czasu `S-05`, `S-08`, `S-09`.
- **Risk:** Gwiazda przewodnia: na pustym drzewie nie ma dopasowań ani konfliktów, więc podgląd to samo podsumowanie, a ryzyko skupia się na torze zapisu. Test z drugim użytkownikiem o tych samych nazwiskach i miejscach dowodzi granicy drzewa, bo komenda omija Policy.
- **Uwaga z przeglądu S-01 (F3):** odczyt nie wykrywa cykli rodzic–dziecko (A rodzicem B i B rodzicem A) ani samo-przodków; S-02 musi to sprawdzić przed zapisem relacji.
- **Status:** done

### S-03: Pierwszy import dopasowuje osoby wpisane ręcznie

- **Outcome:** przy pierwszym imporcie do drzewa z ręcznie wpisanymi osobami komenda dopasowuje osobę z repo do istniejącej, gdy zgadzają się co najmniej dwa niezależne elementy poza imieniem i nazwiskiem, pokazuje przypadki wątpliwe i konflikty do rozstrzygnięcia pojedynczo, a dopasowana osoba zachowuje zdjęcie, skany, notatkę i dane dodane tylko w aplikacji — bez duplikatów.
- **Change ID:** first-import-matches-hand-entered
- **PRD refs:** US-01, FR-005, FR-006, FR-007, FR-014
- **Prerequisites:** S-02
- **Parallel with:** S-04, S-05, S-07, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:**
  - Jak wygląda podgląd w terminalu przy dziesiątkach dopasowań i konfliktów, żeby rozstrzyganie pojedynczo było wykonalne? (PRD Otwarte pytania 4, termin: przed planem komendy importu) Owner: autor. Block: yes.
  - Które elementy liczą się jako niezależne (rodzice, rok urodzenia z jaką tolerancją, miejsce urodzenia, małżonek)? Owner: autor. Block: no.
- **Risk:** Najryzykowniejsze założenie PRD — założenie, które gdy okaże się fałszywe, przekreśla wartość całej zmiany: import na produkcji nie zdubluje ręcznie wpisanego drzewa. Idzie zaraz po gwieździe przewodniej, żeby reguła tożsamości została sprawdzona na prawdziwych danych przed pierwszym importem na produkcji.
- **Status:** in-progress

### S-04: Kolejny import z podglądem zmian i konfliktów

- **Outcome:** autor ponawia import zaktualizowanej kopii repo; rekordy rozpoznane po identyfikatorze z repo, podgląd pokazuje nowe, zmienione, konflikty z ręcznymi zmianami (obie wartości) i rekordy usunięte w repo (usuń albo zostaw); wartość z repo zastępuje wartość w aplikacji bez pytania tylko wtedy, gdy nikt jej w aplikacji nie zmienił od ostatniego importu; rekordy bez identyfikatora repo nigdy nie są ruszane; ręczna edycja działa jak dotąd.
- **Change ID:** reimport-changes-and-conflicts
- **PRD refs:** US-02, FR-005, FR-006, FR-008, FR-014, FR-015
- **Prerequisites:** S-02
- **Parallel with:** S-03, S-05, S-06, S-07, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:**
  - Jak wygląda podgląd w terminalu przy dziesiątkach zmian i konfliktów? (PRD Otwarte pytania 4, wspólne z `S-03`) Owner: autor. Block: yes.
  - Na jakim poziomie aplikacja pamięta „zmienione od ostatniego importu” (pole, rekord)? Owner: `/10x-plan`. Block: no.
- **Risk:** Główne ryzyko wskazane przez autora — ręczna poprawka znika po imporcie. Pola dodane w późniejszych wycinkach (`S-05` – `S-09`) wchodzą do tego samego podglądu zmian; jeśli `S-04` wyląduje przed nimi, każdy z nich rozszerza podgląd o swoje pole.
- **Uwaga z przeglądu S-02 (F3):** osoba z importu może nie mieć urodzenia (import tworzy wydarzenie tylko przy kluczu w bloku). Urodzenie dopisane potem w aplikacji ma `repo_id = null`, więc nie jest zmianą z repo, a gdy repo później doda `urodzenie`, `S-04` musi zdecydować: uzupełnić ręczne wydarzenie czy pokazać konflikt.
- **Status:** blocked

### S-05: Miejsca z repo dopasowane do spisu

- **Outcome:** import przypisuje wydarzeniom miejsca ze spisu drzewa — miejscowości, parafie i cmentarze (pochowanie wchodzi jako wydarzenie na cmentarzu) — dopasowując je do istniejących wpisów; nowe miejsca i niepewne dopasowania (warianty pisowni) autor potwierdza w podglądzie, a spis nie dubluje miejscowości, parafii ani cmentarzy.
- **Change ID:** import-places-matched-to-gazetteer
- **PRD refs:** FR-009, FR-010, FR-017
- **Prerequisites:** S-02
- **Parallel with:** S-03, S-04, S-07, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:**
  - Jak miejsce jest zapisane w bloku (sama nazwa, czy z parafią / jednostką nadrzędną), żeby warianty typu Bukinia/Bokinia dały się rozpoznać? Owner: autor. Block: no.
- **Risk:** Spis miejsc ma dziś tylko dokładne dopasowanie nazw, a repo ma warianty polskie i rosyjskie — każde niepewne dopasowanie idzie do autora zamiast tworzyć duplikat. Razem z `S-03` warunek pierwszego importu na produkcji.
- **Status:** proposed

### S-06: Źródła ze skanami podpięte do faktów

- **Outcome:** import przenosi źródła z bloków źródeł repo (opis: parafia, rok, typ aktu, archiwum, sygnatura, link; plik skanu kopiowany z repo do magazynu aplikacji) i podpina je do faktów z numerem wpisu; repo badawcze pozostaje tylko czytane; ręcznie wgrane skany zostają.
- **Change ID:** import-sources-with-scans
- **PRD refs:** FR-001, FR-011, US-01
- **Prerequisites:** S-05
- **Parallel with:** S-04, S-07, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:**
  - Co z opisami źródeł w repo bez skanu (ok. 55 opisów na ok. 21 skanów), skoro źródło w aplikacji zawsze ma plik? Owner: autor. Block: yes.
- **Risk:** Najszerszy blok danych (format bloku źródła dochodzi tu, nie w `S-01`) i jedyny wycinek kopiujący pliki; czeka na parafie ze spisu (`S-05`), bo opis aktu wskazuje parafię.
- **Status:** blocked

### S-07: Daty nieostre: zakres, przed, po

- **Outcome:** autor zapisuje i widzi datę wydarzenia jako dokładną, „ok.”, zakres lat, „przed” albo „po” — w formularzu, na osi czasu i w panelu osoby; import tych rodzajów dat z repo robi `S-02`, który idzie po `S-07`; istniejące daty wyglądają jak dotąd, styl juliański zostaje w notatce faktu.
- **Change ID:** fuzzy-event-dates
- **PRD refs:** FR-009, FR-015
- **Prerequisites:** —
- **Parallel with:** S-03, S-04, S-05, S-06, S-08, S-09, S-10
- **Blockers:** —
- **Unknowns:**
  - Czy rodzaj daty zastępuje flagę „ok.” (migracja przenosząca dane: kopiuj, testuj, usuń flagę później — wg `CLAUDE.md`), czy dochodzi obok niej jako puste pole (PRD: istniejące wiersze bez przepisywania)? Owner: autor. Block: no.
- **Risk:** Jedyna zmiana istniejącego formatu danych w M-3; dotyka formularza, osi czasu i parsera dat, więc idzie jako osobny wycinek zamiast w `S-02`.
- **Status:** done

### S-08: Poziom pewności A–D przy fakcie

- **Outcome:** autor widzi przy fakcie poziom pewności A–D (A akt pierwotny, B indeks / źródło wtórne, C wskazówka, D hipoteza) niezależny od źródeł i może go ustawić ręcznie; import przenosi poziom z repo; istniejące fakty mają poziom nieokreślony.
- **Change ID:** fact-confidence-level
- **PRD refs:** FR-018, FR-015
- **Prerequisites:** S-02
- **Parallel with:** S-03, S-04, S-05, S-06, S-07, S-09, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Nowe puste pole bez przepisywania danych; osobno od `S-07`, bo dotyczy faktu, a nie daty, i może iść równolegle.
- **Status:** proposed

### S-09: Wyszukiwanie po wariantach nazwiska

- **Outcome:** import przenosi warianty zapisu nazwiska osoby, autor widzi je przy osobie, a wyszukiwarka osób znajduje osobę po każdym wariancie (np. Misiuk po „Mysiuk”).
- **Change ID:** surname-variant-search
- **PRD refs:** US-03, FR-019
- **Prerequisites:** S-02
- **Parallel with:** S-03, S-04, S-05, S-06, S-07, S-08, S-10
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Mały, samodzielny wycinek; wyszukiwanie dziś obejmuje tylko imię i nazwisko, więc zmienia się zapytanie listy osób, nie jej wygląd.
- **Status:** proposed

### S-10: Import „od zera” poza produkcją

- **Outcome:** autor poza produkcją uruchamia import z opcją „od zera”, która po potwierdzeniu usuwa osoby i fakty drzewa wskazanego konta przed importem; na produkcji opcja jest niedostępna.
- **Change ID:** import-from-scratch-outside-prod
- **PRD refs:** FR-020
- **Prerequisites:** S-02
- **Parallel with:** S-03, S-04, S-05, S-06, S-07, S-08, S-09
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Jedyny wycinek odcinalny (nice-to-have); przy celu `market-feedback` przydaje się wcześnie do powtarzania importu na koncie testowym, ale niczego nie odblokowuje.
- **Status:** proposed

## Backlog Handoff

| Roadmap ID | Change ID                          | Suggested issue title                                        | Ready for `/10x-plan` | Notes |
| ---------- | ---------------------------------- | ------------------------------------------------------------ | --------------------- | ----- |
| S-01       | research-blocks-read-and-report    | Import: odczyt bloków danych z repo badawczego i raport      | yes                   | Run `/10x-plan research-blocks-read-and-report`; decyzja o `symfony/yaml` w planie. |
| S-02       | first-import-into-empty-tree       | Import: pierwszy import przodków do pustego drzewa           | planned               | Gwiazda przewodnia; plan gotowy, implementacja czeka na S-07. |
| S-03       | first-import-matches-hand-entered  | Import: dopasowanie do osób wpisanych ręcznie                | no                    | Czeka na S-02 i decyzję o podglądzie w terminalu (PRD OQ 4). |
| S-04       | reimport-changes-and-conflicts     | Import: kolejny import z podglądem zmian i konfliktów        | no                    | Czeka na S-02 i decyzję o podglądzie w terminalu (PRD OQ 4). |
| S-05       | import-places-matched-to-gazetteer | Import: miejsca dopasowane do spisu                          | no                    | Czeka na S-02. |
| S-06       | import-sources-with-scans          | Import: źródła ze skanami podpięte do faktów                 | no                    | Czeka na S-05 i decyzję o źródłach bez skanu. |
| S-07       | fuzzy-event-dates                  | Daty nieostre: zakres, przed, po                             | yes                   | Idzie przed S-02 (decyzja autora 2026-10-23); import dat robi S-02. Run `/10x-new fuzzy-event-dates`. |
| S-08       | fact-confidence-level              | Poziom pewności A–D przy fakcie                              | no                    | Czeka na S-02. |
| S-09       | surname-variant-search             | Wyszukiwanie osób po wariantach nazwiska                     | no                    | Czeka na S-02. |
| S-10       | import-from-scratch-outside-prod   | Import „od zera” poza produkcją                              | no                    | Nice-to-have; czeka na S-02. |

## Open Roadmap Questions

1. **Jak wygląda podgląd w terminalu przy dziesiątkach konfliktów, żeby zatwierdzanie per zmiana było wykonalne?** (PRD Otwarte pytania 4, FR-005 / FR-006) — Owner: autor. Block: S-03, S-04.
2. **Historyczne jednostki administracyjne (gubernie, powiaty, zmiany nazw w czasie) — czy i kiedy?** (PRD Otwarte pytania 1) — Owner: autor. Block: — (poza M-3; dziś jeden rekord miejscowości).
3. **Kiedy linie boczne (pliki bez numeru Ahnentafel) dostaną bloki danych i wejdą do importu?** (PRD Otwarte pytania 2) — Owner: autor. Block: roadmap-wide (kandydat na kolejny kamień milowy).
4. **Gdzie trafią pozostałe notatki z repo (otwarte pytania, ciekawostki)?** (PRD Otwarte pytania 3) — Owner: autor. Block: — (później, bez decyzji).

## Parked

- **Import GEDCOM i import z interfejsu aplikacji** — Why parked: PRD §Non-Goals; M-3 to komenda dla repo badawczego autora.
- **Synchronizacja w dwie strony** — Why parked: PRD §Non-Goals; aplikacja nigdy nie zapisuje do repo badawczego.
- **#37 lista „Do zbadania”, #38 znacznik źródła na karcie, #39 wiele skanów naraz, #40 eksport, #41 widok potomków** — Why parked: PRD §Non-Goals (FR-013 odrzucone — repo ma `TODO.md`).
- **Cofnięcie złego importu** — Why parked: PRD §Non-Goals; kopia bazy przed zapisem (`S-02`) jest jedyną siatką.
- **Próbny przebieg (dry run)** — Why parked: PRD §Non-Goals; FR-012 odrzucone, zastępuje je zatwierdzanie pojedynczych zmian.
- **Linie boczne w imporcie** — Why parked: PRD §Non-Goals; w M-3 tylko przodkowie z numerem Ahnentafel (Open Roadmap Questions 3).
- **Pozostałe notatki z repo (otwarte pytania, ciekawostki)** — Why parked: PRD §Non-Goals (Open Roadmap Questions 4).
- **Pozostałe odłożone z M-1 i M-2** (udostępnianie drzewa, dodawanie osób z widoku przodków, świadkowie ślubu, zarządzana lista archiwów, kosz i cofanie, OCR, scalanie osób i inne) — Why parked: bez zmian; listy w historii git (sekcje Parked w `git show 685ffa2:context/foundation/roadmap.md` i `git show f5646c2:context/foundation/roadmap.md`).

## Milestone History

- **M-1: Drzewo potwierdzone źródłami** (`sourced-family-tree`) — closed 2026-10-04. Fakty urodzenia, małżeństwa i zgonu prowadzą do źródeł każdego rodzaju, podgląd osoby w modalu, relacje dodawane z drzewa (prd-v1 – prd-v4; spis miejscowości odłożony).
- **M-2: Opisane źródła, spis miejscowości i czytelne drzewo** (`described-sources-and-places`) — closed 2026-10-11. Akt opisany parafią, rokiem, typem i archiwum; spis miejscowości używany w faktach, nagrobkach i zamieszkaniu; drzewo dopasowane do ekranu z panelami bocznymi osoby, źródła i małżeństwa, widok przodków oraz zdjęcie osoby na karcie (prd-v1, FR-001 – FR-029).

## Done

- **S-01: Import: odczyt bloków danych z repo badawczego i raport** — Archived 2026-10-23 → `context/archive/2026-10-23-research-blocks-read-and-report/`. Lesson: —.
- **S-07: autor zapisuje i widzi datę wydarzenia jako dokładną, „ok.”, zakres lat, „przed” albo „po” — w formularzu, na osi czasu i w panelu osoby** — Archived 2026-10-23 → `context/archive/2026-10-23-fuzzy-event-dates/`. Lesson: —.
- **S-02: autor importuje przodków do drzewa bez ręcznych wpisów (np. konta testowego): komenda pokazuje podsumowanie, po zatwierdzeniu zapisuje osoby (imiona, nazwisko, płeć), relacje rodzic–dziecko, małżeństwa oraz urodzenia, śluby i zgony z każdym rodzajem daty z `S-07` (dokładna, „ok.”, zakres, „przed”, „po”), każdy rekord z identyfikatorem z repo; na produkcji przed zapisem powstaje kopia bazy; zapis trafia wyłącznie do drzewa wskazanego konta; autor widzi przodków w drzewie i w widoku przodków.** — Archived 2026-10-24 → `context/archive/2026-10-23-first-import-into-empty-tree/`. Lesson: —.
