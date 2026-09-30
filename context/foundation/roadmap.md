---
project: root-keeper
version: 4
status: draft
created: 2026-09-21
updated: 2026-09-30
prd_version: 4
main_goal: speed
top_blocker: time
milestone_id: sourced-family-tree
milestone_seq: 1
milestone_status: open
---

# Roadmap: root-keeper

> Derived from PRD v1 (MVP — dawna treść `context/foundation/prd.md`, dziś w historii git, commit `e74243f`) + `context/foundation/prd-v2.md` (v2, brownfield) + `context/foundation/prd-v3.md` (v3, brownfield) + `context/foundation/prd.md` (v4, brownfield) + auto-researched codebase baseline.
> Previous versions: v1 only — `context/foundation/archive/2026-09-28-roadmap-sourced-family-tree.md`; v1 + v2 — `context/foundation/archive/2026-09-28-roadmap-sourced-family-tree-2.md`; v1 + v2 + v3 — `context/foundation/archive/2026-09-28-roadmap-sourced-family-tree-3.md`.
> Edit-in-place; archive when superseded.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-1: Drzewo potwierdzone źródłami** — Status: open

- **Intent:** Prawdziwe drzewo autora (min. 3 pokolenia) jest w aplikacji; każdą osobę da się odnaleźć, a fakty urodzenia, małżeństwa i zgonu prowadzą do skanu aktu, na którym się opierają — bez sięgania do rozproszonych notatek.
- **Rozszerzenie (2026-09-28, prd-v2):** skan niesie nazwę, typ, rok, pochodzenie oraz numer i notatkę interesującego wpisu, a miejsca (zamieszkanie, urodzenie, ślub, zgon) pochodzą z prywatnego spisu miejscowości drzewa.
- **Rozszerzenie (2026-09-28, prd-v3):** brakującego rodzica, dziecko i małżonka tworzy się z drzewa w modalu; osobę dodaje się i poprawia w modalu; skany stają się źródłami z rodzajem (akt metrykalny, zdjęcie nagrobka, inne), a potwierdzać fakt może źródło każdego rodzaju. Opis skanu z prd-v2 dotyczy teraz źródła. Przy braku czasu pierwszeństwo ma prd-v3, a spis miejscowości (S-12 – S-15) przesuwa się za termin 2026-11-04.
- **Zastąpione przez prd-v3:** S-10 `scan-description` (#18) → S-17 `source-description-in-modal`; S-11 `scan-type-first-when-linking` (#19) → S-19 `matching-sources-first`. Żaden z nich nie był rozpoczęty.
- **Rozszerzenie (2026-09-28, prd-v4):** podgląd osoby staje się modalem otwieranym z drzewa, listy i podglądu skanu, a strona podglądu osoby znika; drzewo przejmuje dodawanie relacji — z pustych slotów podłącza się istniejącą albo nową osobę (rodzic, dziecko, małżonek, połączenie pokazanych rodziców), a usuwanie relacji i małżeństwa zostaje w podglądzie osoby; podgląd pokazuje relacje bez akcji dodawania. Etapami: najpierw modal (S-24), potem dodawanie relacji na drzewie (S-25 – S-27), na końcu podgląd bez dodawania (S-29). Modal idzie przed S-17/S-18.
- **Zastąpione przez prd-v4:** S-21 `parent-from-tree-slot` → S-25 `tree-parent-slot-existing-or-new`; S-22 `child-from-tree` → S-26 `tree-child-existing-or-new`; S-23 `spouse-from-tree-slot` → S-27 `tree-spouse-existing-or-new`. Żaden z nich nie był rozpoczęty ani nie miał issue.
- **Porzucone (2026-09-29, decyzja użytkownika):** S-28 `tree-relation-removal` — usuwanie relacji zostaje w podglądzie osoby.
- **Source materials:** PRD v1 (MVP) — historia git, `git show e74243f:context/foundation/prd.md` (plik `prd.md` nadpisany przez prd-v4); `context/foundation/prd-v2.md` (v2, zmiana brownfield); `context/foundation/prd-v3.md` (v3, zmiana brownfield); `context/foundation/prd.md` (v4, zmiana brownfield).
- **Done when:** every F-NN and S-NN below is `done`, plus prawdziwe drzewo zostało wprowadzone na produkcję po zakończeniu F-01.
- **Scope anchors:** v1: FR-001 – FR-016 (wszystkie konieczne), US-01. FR-018 (nice-to-have) odłożone — patrz `## Parked`. v2 (identyfikatory z prefiksem `v2:`, bo prd-v2 numeruje FR od nowa): v2:FR-001 – v2:FR-005, v2:FR-007 – v2:FR-015 (wszystkie konieczne, w tym zachowane v2:FR-012 – v2:FR-014), v2:US-01, v2:US-02. v2:FR-006 (nice-to-have) odłożone. v3 (prefiks `v3:`): v3:FR-001 – v3:FR-012 (wszystkie konieczne, w tym zachowane v3:FR-009 – v3:FR-012), v3:US-01, v3:US-02; v3:FR-006 zmienia v2:FR-001 (nazwa nie jest już wymagana — tylko plik i rodzaj). v4 (prefiks `v4:`): v4:FR-001 – v4:FR-013 (wszystkie konieczne, w tym zachowane v4:FR-010 – v4:FR-013), v4:US-01; v4:FR-004 – v4:FR-006 rozszerzają v3:FR-001 – v3:FR-003 (istniejąca albo nowa osoba), a v4:US-01 wchłania v3:US-01. Identyfikatory bez prefiksu (`FR-001`) odnoszą się do PRD v1 z historii git.

## Vision recap

Genealog prowadzący własne badania ma notatki, skany i zdjęcia rozproszone i niepowiązane; fakty (np. daty urodzenia) nie wskazują aktów metrykalnych, na których się opierają, a poprzednia aplikacja przestała działać po wygaśnięciu licencji. root-keeper to samodzielnie hostowana aplikacja, w której dane pozostają w pełni własnością użytkownika, a przepływ pracy jest prosty: osoba → fakt → źródło → skan.

Wprowadzanie prawdziwego drzewa pokazało dwie luki (prd-v2): skan bez opisu zmusza do szukania „swojego” wpisu od nowa przy każdym powrocie, a jedno pole zamieszkania gubi historię przeprowadzek. Stąd opis skanu i wspólny spis miejscowości dla wszystkich miejsc w aplikacji.

Kolejne trzy luki (prd-v3): drzewo służy tylko do oglądania — brakującego ojca, dziecka czy małżonka nie da się dodać bez wychodzenia z drzewa; każde dodanie i poprawka osoby to osobna strona; a „skan” to za wąskie pojęcie — zdjęć nagrobków z datami nie ma gdzie podłączyć jako dowodu. Stąd tworzenie osób z drzewa w modalu i źródła z rodzajem zamiast skanów.

Po przeniesieniu formularza osoby do modala zostały dwie luki (prd-v4): oglądanie osoby wciąż wyrywa autora z drzewa na osobną stronę, a dodawanie i edycja są w modalu, podgląd zaś nie. Stąd podgląd osoby w modalu i drzewo jako miejsce dodawania relacji — podgląd pokazuje je bez akcji dodawania, a usuwanie relacji zostaje w podglądzie.

## North star

**S-03: Użytkownik otwiera podgląd osoby, widzi urodzenie potwierdzone aktem i otwiera jego skan** — to najmniejszy przepływ, który dowodzi sensu produktu, więc przy celu `speed` idzie tak wcześnie, jak pozwalają zależności.

> „Gwiazda przewodnia” (north star) oznacza tu najmniejszy kompletny wycinek, którego dostarczenie dowodzi, że produkt działa zgodnie z wizją. Stoi tak wcześnie, jak pozwalają jego zależności, bo reszta ma znaczenie tylko wtedy, gdy on działa.

**Rozszerzenie prd-v2** — dotychczasowa gwiazda S-10 (opis skanu) została zastąpiona przez S-17 (opis źródła w modalu) w ramach prd-v3.

**Rozszerzenie prd-v3** — dotychczasowa gwiazda S-21 (nowy rodzic z pustego slotu) została zastąpiona przez S-25 w ramach prd-v4.

**Rozszerzenie prd-v4 — S-25: Autor ogląda osobę w modalu i podłącza istniejącego ojca z pustego slotu, nie wychodząc z drzewa** — domyka v4:US-01 (główne kryterium sukcesu prd-v4) i dowodzi obu połówek zmiany; przy celu `speed` idzie zaraz po modalu podglądu (S-24), równolegle ze strumieniem źródeł.

## At a glance

| ID   | Change ID                       | Outcome (user can …)                                                                  | Prerequisites | PRD refs                               | Status   |
| ---- | ------------------------------- | ------------------------------------------------------------------------------------- | ------------- | -------------------------------------- | -------- |
| F-01 | production-data-safety          | (foundation) produkcja chroni dane i skany przed utratą i przed podmiotami trzecimi   | —             | NFR (RPO 24 h), NFR (podmioty trzecie), Guardrails | done |
| S-01 | account-tree-and-first-person   | założyć konto, od razu mieć swoje drzewo i dodać pierwszą osobę                        | —             | FR-001, FR-002, FR-003, FR-005, FR-008, Access Control | done        |
| S-02 | scan-upload-and-preview         | wgrać skan aktu i obejrzeć go w powiększeniu pozwalającym odczytać treść               | S-01          | FR-013, FR-016, US-01                  | done |
| S-03 | birth-death-confirmed-by-scan   | powiązać skan z urodzeniem lub zgonem i zobaczyć na podglądzie, co jest potwierdzone   | S-01, S-02    | FR-014, FR-008, US-01                  | done |
| S-04 | person-search                   | wyszukać osobę na liście i przejść do jej podglądu                                     | S-01          | FR-009, US-01                          | done |
| S-05 | person-edit-and-delete          | poprawić dane osoby i usunąć ją po jawnym potwierdzeniu                                | S-01          | FR-006, FR-007                         | done |
| S-06 | parent-child-relations          | połączyć rodzica z dzieckiem, zobaczyć relacje na podglądzie i usunąć relację          | S-01          | FR-011, FR-012, FR-008                 | done |
| S-07 | marriage-with-shared-source     | dodać małżeństwo z datą i miejscem i potwierdzić je jednym aktem u obojga małżonków    | S-03          | FR-010, FR-012, FR-014, US-01          | done        |
| S-08 | scan-delete-with-impact         | usunąć skan po zobaczeniu listy faktów, które stracą źródło                            | S-03          | FR-015                                 | done |
| S-09 | graphical-tree-view             | oglądać swoje drzewo graficznie i przechodzić z niego do osób                          | S-06, S-07    | FR-004, US-01                          | done |
| S-12 | places-gazetteer                | prowadzić prywatny spis miejscowości: dodawać i poprawiać nazwę, istnienie, jednostkę/parafię/notatkę i współrzędne | S-01 | v2:FR-007, v2:FR-009                   | ready    |
| S-13 | fact-places-from-gazetteer      | wybrać miejsce urodzenia, ślubu i zgonu ze spisu (z podpowiedziami i dodaniem brakującej) i widzieć je na podglądzie | S-12, S-03, S-07 | v2:FR-011, v2:FR-008, v2:FR-013, v2:US-02 | proposed |
| S-14 | residence-history               | dodać osobie kilka miejsc zamieszkania ze spisu, widocznych jako chipy, i usunąć każde | S-12          | v2:FR-010, v2:FR-008, v2:US-02         | proposed |
| S-15 | place-delete-with-impact        | usunąć miejscowość po zobaczeniu osób i faktów, które stracą miejsce                   | S-13, S-14    | v2:FR-009                              | proposed |
| S-20 | person-form-in-modal            | dodawać i poprawiać osobę w modalu zamiast na osobnych stronach                        | S-01, S-05    | v3:FR-004                              | done |
| S-24 | person-preview-in-modal         | otworzyć podgląd osoby w modalu z drzewa, listy i podglądu skanu, przechodzić w nim do krewnych i wracać „Wstecz”, a po zamknięciu być w tym samym miejscu | S-20, S-09 | v4:FR-001, v4:FR-002, v4:FR-003, v4:FR-010, v4:FR-012, v4:FR-013, v4:US-01 | done |
| S-16 | scans-become-sources            | widzieć dotychczasowe skany jako źródła rodzaju „akt metrykalny”, wgrywać źródło z rodzajem (akt / nagrobek / inne) i usuwać je jak dziś | S-02, S-03, S-08 | v3:FR-005, v3:FR-009, v3:FR-010, v3:FR-011 | done |
| S-17 | source-description-in-modal     | dodać źródło w modalu (plik i rodzaj) z opcjonalnym opisem, poprawić rodzaj i opis i widzieć numer wpisu i notatkę przy źródle | S-16, S-24 | v3:FR-006, v3:FR-007, v3:US-02, v2:FR-001, v2:FR-002, v2:FR-003, v2:FR-004, v2:FR-005, v2:FR-014, v2:US-01 | proposed |
| S-18 | fact-confirmed-by-any-source    | potwierdzić urodzenie, małżeństwo i zgon źródłem każdego rodzaju i widzieć rodzaj źródła przy fakcie | S-16, S-24 | v3:FR-008, v3:US-02 | proposed |
| S-19 | matching-sources-first          | przy wiązaniu źródła z wydarzeniem widzieć najpierw akty o pasującym typie, potem resztę | S-17, S-18 | v3:FR-009, v2:FR-015, v2:FR-012 | proposed |
| S-25 | tree-parent-slot-existing-or-new | z pustego slotu rodzica na drzewie podłączyć istniejącą osobę (wybór filtrowany płcią i regułami) albo utworzyć nową i od razu widzieć ją na drzewie | S-24, S-06 | v4:FR-004, v4:FR-011, v4:US-01, v3:FR-001, v3:FR-012, v3:US-01 | done |
| S-26 | tree-child-existing-or-new      | dodać z drzewa dziecko pokazanej pary — istniejącą osobę albo nową — i od razu widzieć je na drzewie | S-24, S-06 | v4:FR-005, v4:FR-011, v3:FR-002, v3:FR-012 | done |
| S-27 | tree-spouse-existing-or-new     | z pustego slotu małżonka przy centrum podłączyć istniejącą osobę albo nowego małżonka i połączyć pokazanych rodziców w małżeństwo | S-24, S-07 | v4:FR-006, v4:FR-011, v3:FR-003 | done |
| S-29 | preview-relations-read-only     | widzieć relacje w podglądzie bez dodawania (usuwanie zostaje) z przyciskiem „Pokaż na drzewie” | S-25, S-26, S-27 | v4:FR-008 | done |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                      | Chain                                  | Note                                                                                       |
| ------ | -------------------------- | -------------------------------------- | ------------------------------------------------------------------------------------------ |
| A      | Fakt potwierdzony źródłem  | `F-01`; `S-01` → `S-02` → `S-03` → `S-08`; odgałęzienia `S-01` → `S-04`, `S-01` → `S-05` | Najkrótsza droga do gwiazdy przewodniej M-1 (zakończone); `F-01` szło równolegle, bez zależności od kodu. |
| B      | Rodzina i drzewo           | `S-06` → `S-07` → `S-09`               | Dołącza do strumienia A w `S-01` (S-06) i `S-03` (S-07). Zakończone.                       |
| C      | Źródła (prd-v3)            | `S-16` → `S-17`, `S-18` → `S-19`       | Dołącza do strumienia A w `S-08` i do D w `S-24` (`S-17`, `S-18` powstają od razu w modalu podglądu); `S-17` i `S-18` mogą iść równolegle. Wchłania opis skanu z prd-v2. |
| D      | Drzewo odpowiedzialne za relacje (prd-v3 + v4) | `S-20` → `S-24` → `S-25`, `S-26`, `S-27` → `S-29` | Dołącza do A w `S-05` i do B w `S-09`; `S-25` – `S-27` mogą iść równolegle po `S-24`, `S-29` na końcu (dodawanie i usuwanie relacji mają po jednym miejscu). Gwiazda v4: `S-25`. |
| E      | Spis miejscowości (prd-v2) | `S-12` → `S-13`, `S-14` → `S-15`       | Przy braku czasu przesuwa się za termin 2026-11-04 (pierwszeństwo ma prd-v3); `S-15` czeka na `S-13` i `S-14`. |

## Baseline

What's already in place in the codebase as of `2026-09-28` (auto-researched + user-confirmed; refreshed for the prd-v3 extension, after S-09; frontend line updated for prd-v4, after S-20).
Foundations below assume these are present and do NOT re-scaffold them. The prd-v2, prd-v3 and prd-v4 extensions need no new foundation.

- **Frontend:** present — strony osób (lista, podgląd; dodawanie i edycja w modalu `resources/js/components/persons/person-form-dialog.tsx` od S-20) i skanów (lista, wgrywanie, podgląd); do strony podglądu osoby prowadzi 7 miejsc (lista, nagłówek i karty drzewa, krewni i małżonek w podglądzie, podgląd skanu, przekierowanie po dodaniu osoby); widok drzewa z S-09 (arkusz rodziny, sloty „nieznany” bez akcji, `resources/js/components/tree/`); komponenty okien dialogowych i paneli już używane w 11 okienkach — małżeństwo, wybór krewnego i skanu, usuwanie (`resources/js/components/ui/dialog.tsx`, `resources/js/components/{persons,scans}/`).
- **Backend / API:** present — kontrolery zasobów osób, skanów, małżeństw, relacji i powiązań wydarzenie–skan; akcje, polityki własności drzewa i zasoby API (`routes/web.php`, `app/Actions/`, `app/Policies/`, `app/Http/Resources/`).
- **Data:** present — PostgreSQL; osoby (z płcią, opcjonalną), wydarzenia, skany, powiązania wydarzenie–skan, relacje rodzic–dziecko (`database/migrations/`). Skan ma tylko dane pliku — bez rodzaju i opisu (`app/Models/Scan.php`). Miejsca dziś: zamieszkanie osoby i miejsce wydarzenia to wolny tekst; brak spisu miejscowości. Wyszukiwanie osób bez rozróżniania wielkości liter istnieje (`PersonController`).
- **Auth:** present — rejestracja, logowanie, 2FA, passkeys (`app/Providers/FortifyServiceProvider.php`); własność danych przez polityki drzewa.
- **Deploy / infra:** present — `docker-compose.prod.yml`, ręczny `scripts/deploy.sh`, backupy całej bazy i `storage/app` (obejmują nowe dane z prd-v2 i prd-v3), CI lint/static-analysis/tests na PostgreSQL od PR #17.
- **Observability:** partial — domyślny stack logów i `backup:monitor`; brak śledzenia błędów (usługi zewnętrzne wyklucza NFR).

## Foundations

### F-01: Bezpieczeństwo danych produkcyjnych przed wprowadzeniem prawdziwego drzewa

- **Outcome:** (foundation) produkcja nie przepuszcza ruchu przez podmiot trzeci, backupy są szyfrowane, alarmy o awarii backupu docierają, przed migracją powstaje backup bazy, a odtworzenie backupu ze skanem zostało raz przećwiczone.
- **Change ID:** production-data-safety
- **PRD refs:** NFR (RPO 24 h), NFR (podmioty trzecie), Guardrails
- **Unlocks:** ścieżka weryfikacji „wprowadzenie prawdziwego drzewa” (kryterium sukcesu M-1) dla danych z `S-01`, `S-03`, `S-07`; wymiarowanie backupów skanów po limicie rozmiaru ustalonym w `S-02`.
- **Prerequisites:** —
- **Parallel with:** S-01, S-02, S-03, S-04, S-05, S-06, S-07, S-08, S-09
- **Blockers:** Kroki wyłącznie ręczne właściciela: zmiana rekordu DNS, edycja `.env.prod` na serwerze, próba odtworzenia.
- **Unknowns:**
  - Czy skany zostają w nocnych archiwach Spatie, czy przechodzą na osobny, przyrostowy plan backupu — rozstrzygnąć po zmierzeniu pierwszych 20–30 prawdziwych skanów. Owner: user. Block: no.
- **Risk:** Stoi osobno i równolegle, bo nie zależy od kodu, ale musi się zakończyć przed prawdziwymi danymi: PRD nie ma kosza ani cofania, więc utrata danych bez przećwiczonego odtworzenia jest nieodwracalna.
- **Status:** done

## Slices

### S-01: Konto z gotowym drzewem i pierwsza osoba

- **Outcome:** użytkownik może założyć konto, zalogować się, od razu mieć swoje jedno drzewo i dodać do niego osobę (imię, nazwisko, urodzenie, zamieszkanie, wyznanie, zgon; daty pełne, niepełne lub „około”), a następnie zobaczyć ją na liście i w podglądzie.
- **Change ID:** account-tree-and-first-person
- **PRD refs:** FR-001, FR-002, FR-003, FR-005, FR-008, Access Control
- **Prerequisites:** —
- **Parallel with:** F-01
- **Blockers:** —
- **Unknowns:**
  - Czy urodzenie i zgon są od początku osobnymi wydarzeniami osoby (a nie polami osoby), żeby S-03 i S-07 mogły wiązać z nimi skany bez przebudowy? Owner: `/10x-plan`. Block: no.
  - Usunięcie resetu hasła ze starter kitu (PRD: brak odzyskiwania hasła) — w tym wycinku czy osobno? Owner: user. Block: no.
- **Risk:** Pierwszy wycinek wprowadza model danych i izolację danych per użytkownik; źle ułożony model wydarzeń wymusi przebudowę gwiazdy przewodniej, a brak izolacji łamie guardrail dostępu.
- **Status:** done

### S-02: Wgranie i podgląd skanu aktu

- **Outcome:** użytkownik może wgrać skan aktu metrykalnego do swojego drzewa i obejrzeć go w powiększeniu pozwalającym odczytać treść; skan jest niedostępny dla niezalogowanych i innych użytkowników.
- **Change ID:** scan-upload-and-preview
- **PRD refs:** FR-013, FR-016, US-01
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-04, S-05, S-06
- **Blockers:** —
- **Unknowns:**
  - Jaki maksymalny rozmiar skanu przyjąć? Od niego zależy wymiarowanie backupów w F-01. Owner: user. Block: no.
- **Risk:** Stoi przed S-03, bo bez skanu nie ma czego wiązać; ryzykiem jest wyciek pliku przez publiczny dostęp albo zbyt słabe powiększenie, przez które akt jest nieczytelny.
- **Status:** done

### S-03: Urodzenie i zgon potwierdzone skanem (gwiazda przewodnia)

- **Outcome:** użytkownik może powiązać skan z urodzeniem lub zgonem osoby i zmienić to powiązanie, a na podglądzie osoby widzi datę i miejsce wydarzenia ze wskazaniem aktu, otwiera skan, a wydarzenie bez aktu jest wyraźnie odróżnione od potwierdzonego.
- **Change ID:** birth-death-confirmed-by-scan
- **PRD refs:** FR-014, FR-008, US-01
- **Prerequisites:** S-01, S-02
- **Parallel with:** F-01, S-04, S-05, S-06
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Najwcześniej, jak pozwalają zależności, bo to dowód całej wizji; ryzykiem jest model, w którym jeden skan nie może potwierdzać wielu wydarzeń (Logika biznesowa), co wyjdzie dopiero w S-07.
- **Status:** done

### S-04: Wyszukiwanie osoby na liście

- **Outcome:** użytkownik może wyszukać osobę na liście swoich osób i przejść do jej podglądu.
- **Change ID:** person-search
- **PRD refs:** FR-009, US-01
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-05, S-06, S-07, S-08, S-09
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Mały, niezależny wycinek; odnajdywanie osoby to jeden z dwóch bólów z wizji, więc nie może zostać na koniec, ale nie blokuje gwiazdy przewodniej.
- **Status:** done

### S-05: Edycja i usunięcie osoby

- **Outcome:** użytkownik może poprawić dane osoby i usunąć osobę po jawnym potwierdzeniu.
- **Change ID:** person-edit-and-delete
- **PRD refs:** FR-006, FR-007
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-04, S-06, S-07, S-08, S-09
- **Blockers:** —
- **Unknowns:**
  - Co dzieje się z relacjami i powiązaniami skanów usuwanej osoby (relacje powstają później, w S-06 i S-07)? Owner: `/10x-plan`. Block: no.
- **Risk:** Bez kosza i cofania (Non-Goals) usunięcie jest nieodwracalne, więc potwierdzenie i zasięg usunięcia muszą być jednoznaczne.
- **Status:** done

### S-06: Relacja rodzic–dziecko

- **Outcome:** użytkownik może połączyć dwie osoby relacją rodzic–dziecko (dziecko może mieć tylko jednego znanego rodzica), zobaczyć rodziców i dzieci na podglądzie osoby i usunąć relację po jawnym potwierdzeniu.
- **Change ID:** parent-child-relations
- **PRD refs:** FR-011, FR-012, FR-008
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-04, S-05, S-07, S-08
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Bez relacji nie da się wprowadzić 3 pokoleń z kryterium sukcesu ani narysować drzewa; ryzykiem jest dopuszczenie cykli lub więcej niż dwóch rodziców.
- **Status:** done

### S-07: Małżeństwo potwierdzone jednym aktem u obojga małżonków

- **Outcome:** użytkownik może połączyć dwie osoby małżeństwem z datą i miejscem ślubu (osoba może mieć kilka małżeństw), powiązać z nim skan aktu, który widać jako źródło u obojga małżonków bez dodawania go dwa razy, i usunąć relację po jawnym potwierdzeniu.
- **Change ID:** marriage-with-shared-source
- **PRD refs:** FR-010, FR-012, FR-014, US-01
- **Prerequisites:** S-03
- **Parallel with:** F-01, S-04, S-05, S-06, S-08
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Po S-03, bo używa tego samego wiązania skanów; to najtrudniejsza reguła modelu (jedno wydarzenie dwóch osób) i sprawdza, czy model z S-01/S-03 się utrzyma.
- **Status:** done

### S-08: Usunięcie skanu z listą faktów tracących źródło

- **Outcome:** użytkownik może usunąć skan po potwierdzeniu, widząc wcześniej listę faktów, które stracą źródło; fakty bez ostatniego skanu stają się niepotwierdzone.
- **Change ID:** scan-delete-with-impact
- **PRD refs:** FR-015
- **Prerequisites:** S-03
- **Parallel with:** F-01, S-04, S-05, S-06, S-07, S-09
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Wymaga istniejących powiązań (S-03); ciche odłączenie faktów od źródła łamie Logikę biznesową, a usunięcie pliku ze skanem jest nieodwracalne.
- **Status:** done

### S-09: Graficzny widok drzewa

- **Outcome:** użytkownik może oglądać swoje drzewo w widoku graficznym i przechodzić z niego do podglądu osób.
- **Change ID:** graphical-tree-view
- **PRD refs:** FR-004, US-01
- **Prerequisites:** S-06, S-07
- **Parallel with:** F-01, S-04, S-05, S-08, S-12, S-13, S-14, S-15, S-16, S-17, S-18, S-19
- **Blockers:** —
- **Unknowns:**
  - Jak czytelnie pokazać wiele małżeństw jednej osoby i dziecko z jednym znanym rodzicem? Owner: `/10x-plan`. Block: no. Rozstrzygnięte w planie: jeden partner naraz z przełącznikiem związków; brakujący rodzic jako przerywany znacznik »Nieznany ojciec/matka/rodzic«.
- **Risk:** Na końcu M-1 v1, bo potrzebuje obu rodzajów relacji; to najbardziej niepewny element interfejsu, więc przy braku czasu grozi mu uproszczenie, ale nie wypada z zakresu (FR-004 jest konieczne). Ma pierwszeństwo przed wycinkami z prd-v2.
- **Status:** done

### S-12: Spis miejscowości

- **Outcome:** użytkownik może na osobnej stronie prowadzić prywatny spis miejscowości swojego drzewa: dodawać miejscowość (wymagana tylko nazwa), oznaczać, czy nadal istnieje, zapisywać jednostkę / parafię / notatkę i opcjonalne współrzędne wpisane ręcznie, oraz poprawiać te dane; spis jest niewidoczny dla innych użytkowników.
- **Change ID:** places-gazetteer
- **PRD refs:** v2:FR-007, v2:FR-009
- **Prerequisites:** S-01
- **Parallel with:** S-09, S-16, S-17, S-18, S-19, S-20, S-24, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:**
  - Czy dopuszczać dwie miejscowości o tej samej nazwie (np. dwie Lipnice w różnych parafiach), rozróżniane jednostką / parafią? Owner: `/10x-plan`. Block: no.
- **Risk:** Otwiera strumień miejscowości i nie zmienia jeszcze istniejących danych; ryzykiem jest model spisu, który nie udźwignie podpowiedzi z jednostką (v2:FR-008) w S-13 i S-14. Usuwanie miejscowości czeka na S-15, bo lista użyć ma sens dopiero, gdy miejscowości są używane. Od prd-v3 przy braku czasu ustępuje wycinkom v3.
- **Status:** ready

### S-13: Miejsca faktów ze spisu

- **Outcome:** użytkownik wybiera miejsce urodzenia, ślubu i zgonu z listy miejscowości ze spisu; przy wpisywaniu widzi podpowiedzi istniejących miejscowości (z jednostką), a gdy nic nie pasuje, może dodać nową bez wychodzenia z formularza; na podglądzie osoby widzi miejsce faktu jako miejscowość ze spisu wraz ze wskazaniem aktu.
- **Change ID:** fact-places-from-gazetteer
- **PRD refs:** v2:FR-011, v2:FR-008, v2:FR-013, v2:US-02
- **Prerequisites:** S-12, S-03, S-07
- **Parallel with:** S-09, S-14, S-16, S-17, S-18, S-19, S-20, S-24, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:**
  - Co z dotychczasowymi miejscami faktów zapisanymi wolnym tekstem: usunąć przy zmianie czy zostawić do wglądu, dopóki użytkownik nie wprowadzi ich ze spisu? PRD dopuszcza ich utratę. Owner: user. Block: no.
- **Risk:** Najbardziej ryzykowna zmiana danych rozszerzenia: dotyka trzech rodzajów wydarzeń i świadomie gubi wolne teksty, więc przed wdrożeniem musi powstać backup (procedura z F-01); pierwszy odbiorca wyboru miejscowości z podpowiedziami (v2:FR-008), który S-14 użyje ponownie.
- **Status:** proposed

### S-14: Historia zamieszkania

- **Outcome:** użytkownik może na karcie osoby dodać kilka miejsc zamieszkania, wybierając miejscowości ze spisu (albo dodając brakującą), widzi je jako chipy w polu zamieszkania i może usunąć każde z nich.
- **Change ID:** residence-history
- **PRD refs:** v2:FR-010, v2:FR-008, v2:US-02
- **Prerequisites:** S-12
- **Parallel with:** S-09, S-13, S-16, S-17, S-18, S-19, S-20, S-24, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:**
  - Co z dotychczasowym zamieszkaniem zapisanym wolnym tekstem: usunąć przy zmianie czy zostawić do wglądu do czasu ponownego wprowadzenia? PRD dopuszcza utratę. Owner: user. Block: no.
  - Pole zamieszkania jest częścią formularza osoby, który w S-20 przechodzi do modala — jeśli S-20 jest już zrobione, chipy powstają od razu w modalu. Owner: `/10x-plan`. Block: no.
- **Risk:** Zastępuje jedno pole tekstowe listą miejsc bez okresów i bez źródeł (poza zakresem); jeśli idzie przed S-13, to on pierwszy buduje wybór miejscowości z podpowiedziami.
- **Status:** proposed

### S-15: Usunięcie miejscowości z listą użyć

- **Outcome:** użytkownik może usunąć miejscowość ze spisu po jawnym potwierdzeniu, widząc wcześniej osoby i fakty, które stracą miejsce; usunięcie odpina ją od wszystkich zamieszkań i faktów.
- **Change ID:** place-delete-with-impact
- **PRD refs:** v2:FR-009
- **Prerequisites:** S-13, S-14
- **Parallel with:** S-09, S-16, S-17, S-18, S-19, S-20, S-24, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Na końcu, bo lista użyć wymaga zamieszkań (S-14) i miejsc faktów (S-13); ciche odpięcie miejsca łamie regułę z prd-v2, a bez kosza usunięcie jest nieodwracalne — ten sam wzorzec co usuwanie skanu (S-08).
- **Status:** proposed

### S-20: Osoba dodawana i poprawiana w modalu

- **Outcome:** użytkownik dodaje i poprawia osobę w modalu (z listy osób i z podglądu osoby) bez przechodzenia na osobną stronę; strony dodawania i edycji osoby znikają, a na wąskim ekranie modal zajmuje cały ekran i się przewija.
- **Change ID:** person-form-in-modal
- **PRD refs:** v3:FR-004
- **Prerequisites:** S-01, S-05
- **Parallel with:** S-12, S-13, S-14, S-15, S-16, S-17, S-18, S-19
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Pierwszy w strumieniu drzewa, bo modal osoby jest wspólnym elementem S-21 – S-23; ryzykiem jest długi formularz (daty częściowe, miejsca) nieczytelny w modalu na telefonie.
- **Status:** done

### S-24: Podgląd osoby w modalu

- **Outcome:** użytkownik otwiera podgląd osoby w modalu z drzewa, listy osób i podglądu skanu, a zamknięcie modala zostawia stronę pod spodem bez zmian; w modalu przechodzi do krewnego (rodzic, dziecko, małżonek) i wraca przyciskiem „Wstecz”; widzi fakty z potwierdzeniami (otwarcie skanu prowadzi na pełną stronę podglądu skanu), ustawia probanta i usuwa osobę z tym samym potwierdzeniem co dziś (po usunięciu osoby z centrum drzewo wraca do probanta); po dodaniu osoby z listy trafia na drzewo wyśrodkowane na nowej osobie; na wąskim ekranie modal zajmuje cały ekran. Edycja relacji tymczasowo zostaje w modalu (do S-29).
- **Change ID:** person-preview-in-modal
- **PRD refs:** v4:FR-001, v4:FR-002, v4:FR-003, v4:FR-010, v4:FR-012, v4:FR-013, v4:US-01
- **Prerequisites:** S-20, S-09
- **Parallel with:** S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Czy strona `/persons/{id}` zostaje do S-29 jako zapasowa, czy wszystkie 7 wejść przechodzi na modal od razu, a strona tylko czeka na usunięcie? Owner: `/10x-plan`. Block: no.
  - Co robi przycisk „wstecz” przeglądarki przy otwartym modalu, skoro modal nie ma własnego adresu (prd-v4 §Non-Goals)? Owner: `/10x-plan`. Block: no.
- **Risk:** Etap 1 prd-v4 i jedyny wycinek v4 gotowy od razu; idzie przed S-17/S-18, żeby rodzaj źródła przy fakcie powstał od razu w modalu (prd-v4 §Constraints). Przenosi cały dzisiejszy podgląd naraz (fakty, probant, usuwanie), bo strona i modal nie mogą się rozjechać — stąd więcej FR niż w pozostałych wycinkach; ryzykiem jest „modal na modalu” przy wiązaniu źródeł i zgubienie którejś akcji relacji przy przenosinach.
- **Status:** done

### S-16: Skany stają się źródłami z rodzajem

- **Outcome:** użytkownik widzi dotychczasowe skany w sekcji „Źródła” jako źródła rodzaju „akt metrykalny”, przy wgrywaniu nowego źródła wybiera rodzaj (akt metrykalny, zdjęcie nagrobka, inne), a pliki, miniatury, powiększenie i istniejące potwierdzenia faktów działają jak dziś; źródło bez powiązań usuwa po prostym potwierdzeniu, a źródło z powiązaniami — po liście faktów, które stracą potwierdzenie.
- **Change ID:** scans-become-sources
- **PRD refs:** v3:FR-005, v3:FR-009, v3:FR-010, v3:FR-011
- **Prerequisites:** S-02, S-03, S-08
- **Parallel with:** S-12, S-13, S-14, S-15, S-20, S-24, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:**
  - Czy wgrywanie do czasu S-17 zostaje na obecnej osobnej stronie (z nowym wyborem rodzaju), czy modal wgrywania wchodzi już tutaj? Owner: `/10x-plan`. Block: no.
- **Risk:** Pierwszy w strumieniu źródeł, bo każda dalsza zmiana stoi na nowym pojęciu „źródło”; to jedyna zmiana istniejących danych w prd-v3 — każdy fakt musi mieć ten sam stan „potwierdzone/niepotwierdzone” przed i po, więc przed wdrożeniem backup (procedura z F-01). Stare adresy `/scans` mogą przestać działać (prd-v3).
- **Status:** done

### S-17: Źródło dodawane i opisywane w modalu

- **Outcome:** użytkownik dodaje źródło w modalu — wymagane tylko plik i rodzaj — z opcjonalnym opisem: nazwa, rok, pochodzenie, notatka, a dla aktu metrykalnego także typ (urodzenia, małżeństwa, zgonu, inny) i numer wpisu; poprawia rodzaj i opis w modalu, przy czym zmiana rodzaju z aktu na inny najpierw pokazuje znikające pola i wymaga potwierdzenia; na podglądzie źródła widzi numer wpisu i notatkę obok obrazu.
- **Change ID:** source-description-in-modal
- **PRD refs:** v3:FR-006, v3:FR-007, v3:US-02, v2:FR-001, v2:FR-002, v2:FR-003, v2:FR-004, v2:FR-005, v2:FR-014, v2:US-01
- **Prerequisites:** S-16, S-24
- **Parallel with:** S-18, S-12, S-13, S-14, S-15, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:**
  - Czy lista źródeł pokazuje obok nazwy także rodzaj, typ i rok, żeby dało się odnaleźć źródło bez wyszukiwania (v2:FR-006 odłożone)? Owner: `/10x-plan`. Block: no.
- **Risk:** Zastępuje S-10 (#18); prd-v3 zmienia v2:FR-001 — nazwa nie jest już wymagana, żeby nie spowalniać wgrywania zdjęć z cmentarza. Ryzykiem jest ciche skasowanie typu i numeru wpisu przy zmianie rodzaju. Od prd-v4 czeka na modal podglądu (S-24), żeby modale źródeł powstały od razu w nim.
- **Status:** proposed

### S-18: Fakt potwierdzony źródłem każdego rodzaju

- **Outcome:** użytkownik wiąże z urodzeniem, małżeństwem lub zgonem źródło dowolnego rodzaju (np. zdjęcie nagrobka ze zgonem); fakt dostaje stan „potwierdzone”, a przy fakcie widać rodzaj źródła (np. „potwierdzone: nagrobek”) i można otworzyć źródło w powiększeniu.
- **Change ID:** fact-confirmed-by-any-source
- **PRD refs:** v3:FR-008, v3:US-02
- **Prerequisites:** S-16, S-24
- **Parallel with:** S-17, S-12, S-13, S-14, S-15, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Zmienia regułę potwierdzania: jeden stan dla każdego rodzaju, więc rodzaj widoczny przy fakcie jest jedynym sygnałem wagi dowodu; nie może zmienić stanu żadnego istniejącego faktu. v3:US-02 jest kompletna dopiero razem z S-17 (dodanie nagrobka w modalu). Od prd-v4 czeka na S-24, bo rodzaj źródła przy fakcie powstaje od razu w modalu podglądu.
- **Status:** proposed

### S-19: Pasujące akty najpierw przy wyborze źródła

- **Outcome:** użytkownik przy wiązaniu źródła z urodzeniem, małżeństwem lub zgonem widzi najpierw akty metrykalne o typie pasującym do wydarzenia, a potem pozostałe źródła (nagrobki, inne), które nadal może wybrać; powiązania i stan „potwierdzone/niepotwierdzone” działają jak dziś.
- **Change ID:** matching-sources-first
- **PRD refs:** v3:FR-009, v2:FR-015, v2:FR-012
- **Prerequisites:** S-17, S-18
- **Parallel with:** S-12, S-13, S-14, S-15, S-25, S-26, S-27, S-29
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Zastępuje S-11 (#19); potrzebuje typu aktu (S-17) i wiązania źródeł każdego rodzaju (S-18). Porządek tylko pomaga wybrać — nie może blokować wiązania ani zmieniać reguły potwierdzania.
- **Status:** proposed

### S-25: Istniejący lub nowy rodzic z pustego slotu na drzewie (gwiazda przewodnia v4)

- **Outcome:** użytkownik klika na drzewie slot „nieznany ojciec / matka / rodzic” (także przy dziadkach) i podłącza istniejącą osobę — w slocie ojca/matki wybór pokazuje tylko osoby pasującej płci i ukrywa osoby, których podłączenie złamałoby reguły relacji — albo tworzy w modalu nową osobę (płeć ustawiona i zablokowana przez slot; przy „nieznany rodzic” wybiera ją sam); rodzic od razu pojawia się na drzewie, bez opuszczania drzewa.
- **Change ID:** tree-parent-slot-existing-or-new
- **PRD refs:** v4:FR-004, v4:FR-011, v4:US-01, v3:FR-001, v3:FR-012, v3:US-01
- **Prerequisites:** S-24, S-06
- **Parallel with:** S-26, S-27, S-12, S-13, S-14, S-15, S-16, S-17, S-18, S-19
- **Blockers:** —
- **Unknowns:**
  - Czy wybór istniejącej osoby i utworzenie nowej są w jednym modalu (np. wyszukiwarka z przyciskiem „Nowa osoba”), czy to dwie osobne akcje slotu? Owner: `/10x-plan`. Block: no.
- **Risk:** Gwiazda v4 (zastępuje S-21 `parent-from-tree-slot`, nierozpoczęty) — domyka v4:US-01 i jako pierwszy buduje wybór istniejącej osoby z filtrem reguł, który S-26 i S-27 użyją ponownie. Ryzykiem jest wybór pokazujący osoby łamiące reguły (maks. dwoje rodziców, brak cyklu) albo obejście tych reguł przy tworzeniu z drzewa.
- **Status:** done

### S-26: Dziecko pokazanej pary — istniejące lub nowe

- **Outcome:** użytkownik dodaje z drzewa dziecko pary aktualnie pokazanej (parę wybiera przełącznik związku; bez małżonka — dziecko tylko tej osoby): wybiera istniejącą osobę albo tworzy nową w modalu, który pokazuje obojga rodziców; dziecko od razu pojawia się na drzewie, a podłączenie dające dziecku trzeciego rodzica się nie udaje.
- **Change ID:** tree-child-existing-or-new
- **PRD refs:** v4:FR-005, v4:FR-011, v3:FR-002, v3:FR-012
- **Prerequisites:** S-24, S-06
- **Parallel with:** S-25, S-27, S-12, S-13, S-14, S-15, S-16, S-17, S-18, S-19
- **Blockers:** —
- **Unknowns:**
  - v4:FR-005 mówi „reguły blokują, a komunikat mówi dlaczego”, a v4:FR-011 — „wybór ukrywa osoby łamiące regułę”: ukryć dziecko, które ma już rodzica spoza pary, czy pokazać je z wyjaśnieniem, dlaczego nie da się go podłączyć? Owner: `/10x-plan` (w razie wątpliwości user). Block: no.
- **Risk:** Zastępuje S-22 `child-from-tree` (nierozpoczęty); u osoby z kilkoma małżeństwami dziecko może trafić do złej pary — dlatego para z przełącznika i jawni rodzice w modalu. Jeśli idzie przed S-25, to on pierwszy buduje wybór istniejącej osoby.
- **Status:** done

### S-27: Małżonek z drzewa i połączenie pokazanych rodziców

- **Outcome:** użytkownik widzi pusty slot małżonka przy osobie w centrum drzewa, gdy nie ma ona małżonka, i podłącza z niego istniejącą osobę albo tworzy nowego małżonka w modalu; gdy pokazani na arkuszu ojciec i matka nie mają małżeństwa, łączy ich akcją „Połącz jako małżeństwo”; nowe małżeństwo od razu widać na drzewie, a datę, miejsce i potwierdzenie uzupełnia w podglądzie osoby.
- **Change ID:** tree-spouse-existing-or-new
- **PRD refs:** v4:FR-006, v4:FR-011, v3:FR-003
- **Prerequisites:** S-24, S-07
- **Parallel with:** S-25, S-26, S-12, S-13, S-14, S-15, S-16, S-17, S-18, S-19
- **Blockers:** —
- **Unknowns:**
  - Czy małżeństwo utworzone z drzewa (slot małżonka i „Połącz jako małżeństwo”) powstaje bez daty i miejsca jako fakt niepotwierdzony do uzupełnienia w podglądzie (małżeństwo jest jednym wydarzeniem obojga — S-07)? Owner: `/10x-plan`. Block: no.
- **Risk:** Zastępuje S-23 `spouse-from-tree-slot` (nierozpoczęty); pusty slot tylko przy centrum, żeby nie zaśmiecać drzewa. Ryzykiem jest ukryte założenie małżeństwa, którego użytkownik nie zamierzał.
- **Status:** done

### S-29: Relacje w podglądzie bez dodawania

- **Outcome:** użytkownik widzi w modalu podglądu rodziców, dzieci i małżonków bez akcji dodawania (dodaje się je na drzewie); usuwanie relacji i małżeństwa oraz datę, miejsce i potwierdzenie małżeństwa nadal obsługuje w podglądzie; przycisk „Pokaż na drzewie” wyśrodkowuje drzewo na tej osobie.
- **Change ID:** preview-relations-read-only
- **PRD refs:** v4:FR-008
- **Prerequisites:** S-25, S-26, S-27
- **Parallel with:** S-12, S-13, S-14, S-15, S-16, S-17, S-18, S-19
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Ostatni w strumieniu: akcje dodawania znikają z podglądu dopiero, gdy każdą relację da się dodać z drzewa (S-25 – S-27).
- **Status:** done

## Backlog Handoff

GitHub milestone: [M-1](https://github.com/sebastian-kostecki/root-keeper/milestone/1). Issues #18 (S-10) i #19 (S-11) są zastąpione przez S-17 i S-19; S-16 – S-19 i S-24 – S-29 nie mają jeszcze issues (S-21 – S-23, zastąpione przez prd-v4, nigdy ich nie miały).

| Roadmap ID | Issue | Change ID                     | Suggested issue title                                          | Ready for `/10x-plan` | Notes                                                        |
| ---------- | ----- | ----------------------------- | -------------------------------------------------------------- | --------------------- | ------------------------------------------------------------ |
| F-01       | #2    | production-data-safety        | Zabezpieczenie danych produkcyjnych przed prawdziwym drzewem   | yes                   | Część kroków wyłącznie ręcznie (DNS, `.env.prod`, odtworzenie) |
| S-01       | #3    | account-tree-and-first-person | Konto z automatycznym drzewem i dodanie pierwszej osoby        | yes                   | Run `/10x-plan account-tree-and-first-person`                |
| S-02       | #4    | scan-upload-and-preview       | Wgrywanie i podgląd skanu aktu z powiększeniem                 | no                    | Czeka na S-01                                                |
| S-03       | #5    | birth-death-confirmed-by-scan | Urodzenie i zgon potwierdzone skanem na podglądzie osoby       | no                    | Gwiazda przewodnia; czeka na S-01, S-02                      |
| S-04       | #6    | person-search                 | Wyszukiwanie osoby na liście                                   | no                    | Czeka na S-01                                                |
| S-05       | #7    | person-edit-and-delete        | Edycja i usuwanie osoby z potwierdzeniem                       | no                    | Czeka na S-01                                                |
| S-06       | #8    | parent-child-relations        | Relacje rodzic–dziecko                                         | no                    | Czeka na S-01                                                |
| S-07       | #9    | marriage-with-shared-source   | Małżeństwa z aktem widocznym u obojga małżonków                | yes                   | S-03 done → odblokowane. Run `/10x-plan marriage-with-shared-source` |
| S-08       | #10   | scan-delete-with-impact       | Usuwanie skanu z listą faktów tracących źródło                 | yes                   | S-03 done → odblokowane. Run `/10x-plan scan-delete-with-impact` |
| S-09       | #11   | graphical-tree-view           | Graficzny widok drzewa                                         | yes                   | S-06, S-07 done → odblokowane. Run `/10x-plan graphical-tree-view`; pierwszeństwo przed prd-v2 |
| S-12       | #20   | places-gazetteer              | Prywatny spis miejscowości drzewa                              | yes                   | Run `/10x-plan places-gazetteer`; przy braku czasu ustępuje prd-v3 |
| S-13       | #21   | fact-places-from-gazetteer    | Miejsca urodzenia, ślubu i zgonu wybierane ze spisu            | no                    | Czeka na S-12                                                |
| S-14       | #22   | residence-history             | Historia zamieszkania osoby ze spisu miejscowości              | no                    | Czeka na S-12                                                |
| S-15       | #23   | place-delete-with-impact      | Usuwanie miejscowości z listą użyć                             | no                    | Czeka na S-13, S-14                                          |
| S-20       | —     | person-form-in-modal          | Dodawanie i edycja osoby w modalu                              | yes                   | Zrobione (2026-09-28)                                        |
| S-24       | —     | person-preview-in-modal       | Podgląd osoby w modalu z drzewa, listy i podglądu skanu        | yes                   | Run `/10x-plan person-preview-in-modal`; odblokowuje gwiazdę v4 S-25 oraz S-17, S-18 |
| S-16       | —     | scans-become-sources          | Skany jako źródła z rodzajem (akt / nagrobek / inne)           | yes                   | Run `/10x-plan scans-become-sources`; backup przed wdrożeniem |
| S-17       | —     | source-description-in-modal   | Dodawanie i opis źródła w modalu (zastępuje #18)               | no                    | Czeka na S-16, S-24                                          |
| S-18       | —     | fact-confirmed-by-any-source  | Fakt potwierdzony źródłem każdego rodzaju, rodzaj widoczny przy fakcie | no            | Czeka na S-16, S-24                                          |
| S-19       | —     | matching-sources-first        | Pasujące akty najpierw przy wyborze źródła (zastępuje #19)     | no                    | Czeka na S-17, S-18                                          |
| S-25       | —     | tree-parent-slot-existing-or-new | Istniejący lub nowy rodzic z pustego slotu na drzewie (zastępuje S-21) | no           | Gwiazda v4; czeka na S-24                                    |
| S-26       | —     | tree-child-existing-or-new    | Dziecko pokazanej pary z drzewa — istniejące lub nowe (zastępuje S-22) | no           | Czeka na S-24                                                |
| S-27       | —     | tree-spouse-existing-or-new   | Małżonek z drzewa i połączenie pokazanych rodziców (zastępuje S-23) | no              | Czeka na S-24                                                |
| S-29       | —     | preview-relations-read-only   | Relacje w podglądzie bez dodawania, „Pokaż na drzewie”         | no                    | Czeka na S-25 – S-27                                         |

## Open Roadmap Questions

1. **Porządek na GitHubie** — zamknąć #18 i #19 jako zastąpione przez S-17 i S-19 oraz założyć issues dla S-16 – S-19 i S-24 – S-29 w kamieniu M-1. Owner: user. Block: no (planowanie może ruszyć bez tego).
2. **PRD v1 poza drzewem plików** — `context/foundation/prd.md` nadpisano treścią prd-v4, więc odwołania bez prefiksu (`FR-001` – `FR-016`, `US-01`) w wycinkach S-01 – S-09 oraz odwołania w `prd-v2.md` i `prd-v3.md` do „MVP `context/foundation/prd.md`” prowadzą dziś do historii git (commit `e74243f`). Przywrócić jako `prd-v1.md`? Owner: user. Block: no.

Pytania w wycinkach S-12 – S-29 nie blokują planowania.

Rozstrzygnięte:

- **Deploy ręczny czy automatyczny po merge'u?** (#12, 2026-09-21) — Ręczny: właściciel uruchamia `scripts/deploy.sh` na VPS po merge'u do `main`. Workflow deployu nie powstaje; `tech-stack.md` poprawiony (`ci_default_flow: manual-promotion`).
- **Przeplanowanie S-10 i S-11 w roadmapie** (prd-v3, Otwarte pytania nr 1; 2026-09-28) — S-10 → S-17 `source-description-in-modal`, S-11 → S-19 `matching-sources-first`; oba jako część strumienia źródeł po S-16.
- **Przeplanowanie S-21 – S-23 w roadmapie** (prd-v4, Otwarte pytania nr 1; 2026-09-28) — S-21 → S-25, S-22 → S-26, S-23 → S-27 (istniejąca albo nowa osoba), nowe S-24 (modal podglądu), S-28 (usuwanie relacji z drzewa; porzucone 2026-09-29) i S-29 (podgląd tylko do odczytu); S-24 przed S-17/S-18.

## Parked

- **Eksport wszystkich danych i skanów w otwartym formacie (FR-018)** — Why parked: nice-to-have, a cel `speed` i ryzyko `time` odkładają wszystko poza wymaganiami koniecznymi; PRD świadomie akceptuje to ryzyko (Non-Goals).
- **Zdjęcie osoby (portret)** — Why parked: PRD §Non-Goals, przesunięte do v2.
- **Dane o pogrzebie** — Why parked: PRD §Non-Goals, przesunięte do v2.
- **Zbiorcza lista faktów bez źródła** — Why parked: PRD §Non-Goals; odróżnienie na podglądzie osoby (S-03) wystarcza.
- **Źródła dla wyznania i miejsca zamieszkania** — Why parked: PRD §Non-Goals (FR-014).
- **Wiele drzew, role, współdzielenie** — Why parked: PRD §Non-Goals (FR-003, Access Control).
- **Kosz i cofanie, historia zmian** — Why parked: PRD §Non-Goals. (Opis skanów wrócił do zakresu z prd-v2, a od prd-v3 jako opis źródła — S-17.)
- **Pilnowanie zgodności faktu z aktem, stan „sprzeczny”** — Why parked: PRD §Non-Goals, Logika biznesowa.
- **Rozróżnienie rodzica biologicznego i przybranego** — Why parked: PRD §Non-Goals (FR-011).
- **Stan potwierdzenia w widoku drzewa** — Why parked: PRD §Non-Goals.
- **GEDCOM, OCR, warianty pisowni, integracje zewnętrzne** — Why parked: PRD §Non-Goals.
- **Odzyskiwanie hasła** — Why parked: PRD §Non-Goals; istniejący reset ze starter kitu do usunięcia (Unknown w S-01).
- **Urządzenia mobilne, wielojęzyczność** — Why parked: PRD §Non-Goals (non-functional).
- **Wyszukiwanie skanu po nazwie (v2:FR-006)** — Why parked: nice-to-have, a cel `speed` i ryzyko `time` odkładają wszystko poza koniecznym; nazwy na liście źródeł (S-17) wystarczają do odnalezienia źródła.
- **Zaznaczanie obszaru wpisu na obrazie skanu** — Why parked: prd-v2 §Non-Goals; numer wpisu i notatka wystarczają.
- **Wiele wpisów na skan i powiązanie wpisu z wydarzeniem** — Why parked: prd-v2 §Non-Goals; jeden numer wpisu i jedna notatka, źródłem faktu pozostaje cały skan.
- **Wyszukiwanie skanów po treści notatki** — Why parked: prd-v2 §Non-Goals.
- **Mapa w aplikacji i zewnętrzni dostawcy map** — Why parked: prd-v2 §Non-Goals; współrzędne wpisywane ręcznie (NFR o podmiotach trzecich).
- **Okresy przy miejscach zamieszkania** — Why parked: prd-v2 §Non-Goals; chipy pokazują tylko miejscowość.
- **Przeniesienie istniejących miejsc z wolnego tekstu do spisu** — Why parked: prd-v2 §Non-Goals; użytkownik wprowadzi je ponownie.
- **Modale dla faktów i relacji** — Why parked: prd-v3 §Non-Goals; od prd-v4 relacje dodaje się na drzewie (S-25 – S-27), a fakty zostają w podglądzie osoby (w modalu od S-24) w obecnej formie.
- **Scalanie duplikatów osób** — Why parked: prd-v3 i prd-v4 §Non-Goals; od prd-v4 slot na drzewie pozwala wybrać istniejącą osobę (S-25 – S-27), ale już zdublowanych osób się nie scala — poprawiane ręcznie.
- **Wgrywanie wielu plików naraz** — Why parked: prd-v3 §Non-Goals; jedno źródło = jeden plik (v3:FR-006).
- **Słabszy stan potwierdzenia dla nagrobka i „innych”** — Why parked: prd-v3 §Non-Goals; jeden stan „potwierdzone” + widoczny rodzaj (v3:FR-008).
- **Przekierowania ze starych adresów `/scans`** — Why parked: prd-v3 §Non-Goals; jedynym użytkownikiem jest autor (v3:FR-010).
- **Przeciąganie kart na drzewie** — Why parked: prd-v4 §Non-Goals; relacje łączy się przez sloty i wybór osoby.
- **Adres podglądu osoby i przekierowania ze starych `/persons/{id}`** — Why parked: prd-v4 §Non-Goals; podgląd nie ma własnego adresu (v4:FR-009).

## Milestone History

## Done

- **F-01: (foundation) produkcja chroni dane i skany przed utratą i przed podmiotami trzecimi** — Archived 2026-09-22 → `context/archive/2026-09-22-production-data-safety/`. Lesson: —.
- **S-01: użytkownik może założyć konto, zalogować się, od razu mieć swoje jedno drzewo i dodać do niego osobę (imię, nazwisko, urodzenie, zamieszkanie, wyznanie, zgon; daty pełne, niepełne lub „około”), a następnie zobaczyć ją na liście i w podglądzie.** — Archived 2026-09-23 → `context/archive/2026-09-23-account-tree-and-first-person/`. Lesson: —.
- **S-02: użytkownik może wgrać skan aktu metrykalnego do swojego drzewa i obejrzeć go w powiększeniu pozwalającym odczytać treść; skan jest niedostępny dla niezalogowanych i innych użytkowników.** — Archived 2026-09-24 → `context/archive/2026-09-24-scan-upload-and-preview/`. Lesson: —.
- **S-03: użytkownik może powiązać skan z urodzeniem lub zgonem osoby i zmienić to powiązanie, a na podglądzie osoby widzi datę i miejsce wydarzenia ze wskazaniem aktu, otwiera skan, a wydarzenie bez aktu jest wyraźnie odróżnione od potwierdzonego.** — Archived 2026-09-24 → `context/archive/2026-09-24-birth-death-confirmed-by-scan/`. Lesson: —.
- **S-05: użytkownik może poprawić dane osoby i usunąć osobę po jawnym potwierdzeniu.** — Archived 2026-09-25 → `context/archive/2026-09-25-person-edit-and-delete/`. Lesson: —.
- **S-04: użytkownik może wyszukać osobę na liście swoich osób i przejść do jej podglądu.** — Archived 2026-09-25 → `context/archive/2026-09-25-person-search/`. Lesson: —.
- **S-06: użytkownik może połączyć dwie osoby relacją rodzic–dziecko (dziecko może mieć tylko jednego znanego rodzica), zobaczyć rodziców i dzieci na podglądzie osoby i usunąć relację po jawnym potwierdzeniu.** — Archived 2026-09-25 → `context/archive/2026-09-25-parent-child-relations/`. Lesson: —.
- **S-08: użytkownik może usunąć skan po potwierdzeniu, widząc wcześniej listę faktów, które stracą źródło; fakty bez ostatniego skanu stają się niepotwierdzone.** — Archived 2026-09-25 → `context/archive/2026-09-25-scan-delete-with-impact/`. Lesson: —.
- **S-07: użytkownik może połączyć dwie osoby małżeństwem z datą i miejscem ślubu (osoba może mieć kilka małżeństw), powiązać z nim skan aktu, który widać jako źródło u obojga małżonków bez dodawania go dwa razy, i usunąć relację po jawnym potwierdzeniu.** — Archived 2026-09-25 → `context/archive/2026-09-25-marriage-with-shared-source/`. Lesson: —.
- **S-09: użytkownik może oglądać swoje drzewo w widoku graficznym i przechodzić z niego do podglądu osób.** — Archived 2026-09-28 → `context/archive/2026-09-28-graphical-tree-view/`. Lesson: —.
- **S-20: użytkownik dodaje i poprawia osobę w modalu (z listy osób i z podglądu osoby) bez przechodzenia na osobną stronę; strony dodawania i edycji osoby znikają, a na wąskim ekranie modal zajmuje cały ekran i się przewija.** — Archived 2026-09-28 → `context/archive/2026-09-28-person-form-in-modal/`. Lesson: —.
- **S-24: użytkownik otwiera podgląd osoby w modalu z drzewa, listy osób i podglądu skanu, a zamknięcie modala zostawia stronę pod spodem bez zmian; w modalu przechodzi do krewnego (rodzic, dziecko, małżonek) i wraca przyciskiem „Wstecz”; widzi fakty z potwierdzeniami (otwarcie skanu prowadzi na pełną stronę podglądu skanu), ustawia probanta i usuwa osobę z tym samym potwierdzeniem co dziś (po usunięciu osoby z centrum drzewo wraca do probanta); po dodaniu osoby z listy trafia na drzewo wyśrodkowane na nowej osobie; na wąskim ekranie modal zajmuje cały ekran. Edycja relacji tymczasowo zostaje w modalu (do S-29).** — Archived 2026-09-28 → `context/archive/2026-09-28-person-preview-in-modal/`. Lesson: —.
- **S-25: użytkownik klika na drzewie slot „nieznany ojciec / matka / rodzic” (także przy dziadkach) i podłącza istniejącą osobę — w slocie ojca/matki wybór pokazuje tylko osoby pasującej płci i ukrywa osoby, których podłączenie złamałoby reguły relacji — albo tworzy w modalu nową osobę (płeć ustawiona i zablokowana przez slot; przy „nieznany rodzic” wybiera ją sam); rodzic od razu pojawia się na drzewie, bez opuszczania drzewa.** — Archived 2026-09-28 → `context/archive/2026-09-28-tree-parent-slot-existing-or-new/`. Lesson: —.
- **S-26: użytkownik dodaje z drzewa dziecko pary aktualnie pokazanej (parę wybiera przełącznik związku; bez małżonka — dziecko tylko tej osoby): wybiera istniejącą osobę albo tworzy nową w modalu, który pokazuje obojga rodziców; dziecko od razu pojawia się na drzewie, a podłączenie dające dziecku trzeciego rodzica się nie udaje.** — Archived 2026-09-28 → `context/archive/2026-09-28-tree-child-existing-or-new/`. Lesson: —.
- **S-27: użytkownik widzi pusty slot małżonka przy osobie w centrum drzewa, gdy nie ma ona małżonka, i podłącza z niego istniejącą osobę albo tworzy nowego małżonka w modalu; gdy pokazani na arkuszu ojciec i matka nie mają małżeństwa, łączy ich akcją „Połącz jako małżeństwo”; nowe małżeństwo od razu widać na drzewie, a datę, miejsce i potwierdzenie uzupełnia w podglądzie osoby.** — Archived 2026-09-28 → `context/archive/2026-09-28-tree-spouse-existing-or-new/`. Lesson: —.
- **S-29: użytkownik widzi w modalu podglądu rodziców, dzieci i małżonków bez akcji dodawania (dodaje się je na drzewie); usuwanie relacji i małżeństwa oraz datę, miejsce i potwierdzenie małżeństwa nadal obsługuje w podglądzie; przycisk „Pokaż na drzewie” wyśrodkowuje drzewo na tej osobie.** — Archived 2026-09-29 → `context/archive/2026-09-29-preview-relations-read-only/`. Lesson: —.
- **S-16: użytkownik widzi dotychczasowe skany w sekcji „Źródła” jako źródła rodzaju „akt metrykalny”, przy wgrywaniu nowego źródła wybiera rodzaj (akt metrykalny, zdjęcie nagrobka, inne), a pliki, miniatury, powiększenie i istniejące potwierdzenia faktów działają jak dziś; źródło bez powiązań usuwa po prostym potwierdzeniu, a źródło z powiązaniami — po liście faktów, które stracą potwierdzenie.** — Archived 2026-09-30 → `context/archive/2026-09-29-scans-become-sources/`. Lesson: —.
