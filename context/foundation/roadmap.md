---
project: root-keeper
version: 5
status: draft
created: 2026-10-04
updated: 2026-10-05
prd_version: 1
main_goal: speed
top_blocker: time
milestone_id: described-sources-and-places
milestone_seq: 2
milestone_status: open
---

# Roadmap: root-keeper

> Derived from `context/foundation/prd.md` (v1 — PRD zmiany: opis źródeł, spis miejscowości, wygląd drzewa, widok przodków) + auto-researched codebase baseline (2026-10-04).
> Previous milestone M-1 (`sourced-family-tree`, prd-v1 – prd-v4) closed 2026-10-04; its full roadmap is in git history (`git show 685ffa2:context/foundation/roadmap.md`).
> Edit-in-place; archive when superseded.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-2: Opisane źródła, spis miejscowości i czytelne drzewo** — Status: open

- **Intent:** Autor opisuje akt metrykalny parafią, rokiem, typem i archiwum, a nagrobek cmentarzem i osobami pochowanymi; przy fakcie odnajduje akt po miejscu i roku i podaje numer wpisu. Każda miejscowość istnieje w drzewie raz. Drzewo wypełnia ekran, osoba, źródło, małżeństwo i miejscowość otwierają się w panelu bocznym, a autor widzi wszystkich przodków osoby — bez psucia powiązań fakt–źródło i dodawania relacji z drzewa.
- **Source materials:** `context/foundation/prd.md` (v1, zmiana brownfield; poprzednie PRD w `context/foundation/archive/prd-v1.md` – `prd-v4.md`).
- **Done when:** every F-NN and S-NN below is `done`. Jeśli zabraknie czasu do 2026-11-04, odpada tylko S-16 (FR-015) — decyzja PRD; reszta jest nieodcinalna, przesuwa się termin, nie zakres (decyzja 2026-10-04).
- **Scope anchors:** FR-001 – FR-029 (wszystkie konieczne, w tym zachowane FR-025 – FR-027 i FR-029 oraz zmienione FR-028), US-01. Odwołania do wcześniejszych PRD mają prefiks (`v2:FR-007`, `v4:FR-002`).

## Vision recap

root-keeper to prywatne, samodzielnie hostowane drzewo genealogiczne, w którym fakty urodzenia, małżeństwa i zgonu prowadzą do źródeł (M-1). Wprowadzanie prawdziwego drzewa pokazało, że nie ma gdzie rzetelnie opisać aktu: parafia i archiwum lądują w jednym polu „Pochodzenie” albo w notatce, numer wpisu jest polem źródła, choć jeden skan strony księgi zawiera wpisy wielu osób, a przy wyborze źródła nie widać roku ani parafii. Do tego drzewo zajmuje tylko górę dużego ekranu, linie do dzieci są nieczytelne, a miejsca pozostają wolnym tekstem. Ta zmiana porządkuje opis źródeł według rodzaju, wprowadza wspólny spis miejscowości i parafii, przenosi osobę i źródło do panelu bocznego, poprawia wygląd drzewa i dodaje widok przodków.

## North star

**S-03: Autor opisuje akt metrykalny parafią (ze spisu, z dopisaniem w locie), rokiem, typem aktu i archiwum (z podpowiedziami)** — to ból nr 1 z PRD i najkrótsza droga do kryterium sukcesu; przy celu `speed` idzie zaraz po spisie miejscowości i panelu źródła, a S-04 domyka na nim US-01 (odnalezienie aktu po miejscu i roku z numerem wpisu).

> „Gwiazda przewodnia” oznacza tu najmniejszy kompletny wycinek, którego dostarczenie dowodzi, że zmiana działa zgodnie z zamierzeniem. Stoi tak wcześnie, jak pozwalają jego zależności, bo reszta ma znaczenie tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                            | Outcome (user can …)                                                                                     | Prerequisites          | PRD refs                                 | Status   |
| ---- | ------------------------------------ | -------------------------------------------------------------------------------------------------------- | ---------------------- | ---------------------------------------- | -------- |
| F-01 | critical-flows-e2e-guard             | (foundation) testy E2E chronią powiązanie fakt–źródło i dodawanie relacji ze slotów drzewa                | —                      | Guardrails, Constraints & Compatibility  | done |
| F-02 | side-panel-shell                     | (foundation) jest jedna wspólna powłoka panelu bocznego z prawej, obok której treść strony się zwęża      | —                      | FR-006, FR-019, FR-028, NFR (1366–2560 px) | done |
| S-01 | places-gazetteer                     | prowadzić spis miejscowości z opisem, który startuje z istniejących tekstowych miejsc                     | F-02                   | FR-007, FR-029                           | done |
| S-02 | source-side-panel                    | dodawać, oglądać i edytować źródło w szerokim panelu bocznym ze skanem obok opisu                         | F-02                   | FR-006, FR-027                           | done        |
| S-03 | act-described-by-parish-and-archive  | opisać akt parafią ze spisu (dopisaną w locie), rokiem, typem aktu i archiwum z podpowiedziami            | S-01, S-02             | FR-001, FR-008, FR-009, US-01            | in-progress |
| S-04 | source-found-by-place-and-year       | przy podpinaniu źródła do faktu szukać po miejscu i roku i podać numer wpisu widoczny przy fakcie i źródle | S-03, F-01             | FR-003, FR-004, FR-025, US-01            | proposed |
| S-05 | tree-fits-screen                     | oglądać drzewo z większymi kartami dopasowane do ekranu, z przybliżaniem i liniami dzieci od właściwej pary | F-01                   | FR-013, FR-014, FR-026                   | proposed |
| S-06 | person-side-panel                    | kliknąć kartę i oglądać oraz edytować osobę w panelu obok zwężonego drzewa, przechodzić w nim do krewnych | F-01, F-02, S-05       | FR-016, FR-019, FR-028, FR-026, FR-029   | proposed |
| S-07 | fact-places-from-gazetteer           | wybrać miejsce urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości                                 | S-01                   | FR-010, FR-022                           | proposed |
| S-08 | residence-history                    | ustawić osobie kilka miejscowości zamieszkania z opcjonalnymi latami od–do                                | S-01                   | FR-011                                   | proposed |
| S-09 | gravestone-cemetery-and-buried       | opisać nagrobek cmentarzem ze spisu i osobami pochowanymi, z podpowiedzią miejsca pochowania              | S-04, S-07             | FR-002, FR-004                           | proposed |
| S-10 | sources-grid-with-filters            | przeglądać źródła jako siatkę mniejszych kart i filtrować po rodzaju, parafii/miejscu, roku i archiwum     | S-09                   | FR-005                                   | proposed |
| S-11 | places-merge-and-delete              | scalić zduplikowaną miejscowość lub parafię z właściwą i usunąć wpis nigdzie nieużywany                   | S-03, S-07, S-08, S-09 | FR-012                                   | proposed |
| S-12 | marriage-side-panel                  | otworzyć panel małżeństwa z datą, miejscem, źródłem (z edycją) i dziećmi pary                             | S-06                   | FR-017                                   | proposed |
| S-13 | maiden-name-and-age                  | zapisać nazwisko rodowe („z d. …”, wyszukiwanie po obu nazwiskach) i widzieć wiek osoby                   | —                      | FR-020, FR-024                           | done |
| S-14 | person-events-and-note-tabs          | zobaczyć w panelu osoby zakładkę wydarzeń (własnych i najbliższej rodziny) po roku i zakładkę notatki     | S-06                   | FR-021, FR-023                           | proposed |
| S-15 | ancestors-view                       | zobaczyć osobę i wszystkich jej przodków w jednym widoku z wyborem liczby pokoleń                         | S-05                   | FR-018, FR-029                           | proposed |
| S-16 | person-photo-on-card                 | dodać zdjęcie osoby i widzieć je na karcie drzewa (inicjały, gdy zdjęcia brak)                            | S-05, S-06             | FR-015                                   | proposed |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                          | Chain                                                        | Note                                                                                                   |
| ------ | ------------------------------ | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| A      | Spis miejscowości              | `F-02` → `S-01` → `S-07`, `S-08` → `S-11`                    | Rdzeń bólu nr 2; `F-02` zasila też strumienie B i C. `S-11` czeka na wszystkie rodzaje odwołań (B: `S-03`, `S-09`). |
| B      | Opis i wyszukiwanie źródeł     | `S-02` → `S-03` → `S-04` → `S-09` → `S-10`                   | Dołącza do A w `F-02` (`S-02`), `S-01` (`S-03`) i `S-07` (`S-09`); gwiazda przewodnia `S-03` — przy celu `speed` najpierw. |
| C      | Drzewo i panel osoby           | `F-01` → `S-05` → `S-15`, `S-06` → `S-12`, `S-14`, `S-16` | Dołącza do A w `F-02` (`S-06`); `S-15` odgałęzia się od `S-05` niezależnie od panelu; `F-01` musi wyprzedzić przebudowę drzewa i panelu. `S-16` odpada pierwsze. |
| D      | Dane osoby                     | `S-13`                                                       | Bez zależności — może iść od razu, równolegle z czymkolwiek.                                           |

## Baseline

What's already in place in the codebase as of `2026-10-04` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** partial — drzewo to stała siatka 3 pokoleń z przewijaniem w poziomie, bez dopasowania do ekranu i przybliżania (`resources/js/components/tree/family-sheet.tsx`); linie dzieci rysowane obramowaniami; kliknięcie karty przecentrowuje, „Podgląd” to ikona na karcie i przycisk w nagłówku; osoba i źródło w modalach (`resources/js/components/persons/person-preview-dialog.tsx`, `resources/js/components/sources/source-edit-dialog.tsx`), brak panelu bocznego (`ui/sheet.tsx` używa tylko sidebar); lista źródeł ma widoki Galeria/Tabela z filtrem nazwy i rodzaju; brak widoku przodków (jest pomocnik `ParentChildGraph::ancestorsOf`); brak zdjęć osób (awatar z inicjałami, nieużywany na drzewie); przybliżanie istnieje tylko w podglądzie skanu.
- **Backend / API:** present — osoby, źródła, małżeństwa, relacje rodzic–dziecko, powiązania wydarzenie–źródło (`routes/web.php`); brak kontrolerów miejscowości, parafii i archiwów.
- **Data:** partial — źródło ma płaskie pola wspólne dla rodzajów (nazwa, typ aktu, numer wpisu, rok, pochodzenie, link, notatka); powiązanie wydarzenie–źródło bez numeru wpisu; miejsce wydarzenia i zamieszkanie osoby to wolny tekst; brak nazwiska rodowego, miejsca pochowania, notatki i zdjęcia osoby; brak tabel spisów.
- **Auth:** present — Fortify (rejestracja, 2FA, passkeys) i polityki własności drzewa z 404 dla cudzych rekordów (`app/Policies/`).
- **Deploy / infra:** present — `docker-compose.prod.yml`, ręczny `scripts/deploy.sh` z `backup:run --only-db` przed `migrate`; kopie obejmują bazę i `storage/app` (więc także nowe spisy, notatki i zdjęcia osób — NFR RPO 24 h bez nowej pracy); CI: lint, analiza statyczna, Pest na PostgreSQL.
- **Observability:** partial — domyślne logi i `backup:monitor`; zmiana nie wymaga więcej.
- **E2E:** partial — Playwright skonfigurowany (`playwright.config.ts`, osobny serwer Sail, projekt `setup` z sesją), ale jedyne testy to logowanie i seed bramki; nic nie chroni fakt–źródło ani dodawania relacji ze slotów; poza CI.

## Foundations

### F-01: Testy E2E chroniące ścieżki krytyczne przed przebudową

- **Outcome:** (foundation) testy E2E w przeglądarce sprawdzają, że fakt potwierdzony źródłem prowadzi do tego samego źródła i skanu oraz że rodzica, dziecko i małżonka (istniejącego lub nowego) dodaje się z pustych slotów drzewa — z asercją na danych, nie na wyglądzie.
- **Change ID:** critical-flows-e2e-guard
- **PRD refs:** Guardrails, Constraints & Compatibility
- **Unlocks:** ścieżka weryfikacji dla `S-04` (zmienia powiązanie źródła z faktem), `S-05` (przebudowa drzewa) i `S-06` (panel osoby zamiast modala); to faza 4 planu testów (`test-plan.md` §3).
- **Prerequisites:** —
- **Parallel with:** F-02, S-01, S-02, S-03, S-07, S-08, S-13
- **Blockers:** —
- **Unknowns:**
  - Czy testy E2E od razu wchodzą do CI jako wymagana bramka PR (test-plan §4 przewiduje to po fazie 4), czy na razie zostają lokalne? Owner: user. Block: no.
  - Lokatory muszą przetrwać przejście z modala na panel — testy opisują przepływ rolami i etykietami, nie strukturą okna. Owner: `/10x-e2e`. Block: no.
- **Risk:** Musi być przed `S-04` – `S-06`: przebudowa drzewa i paneli dotyka dokładnie tych ścieżek, które PRD nazywa guardrails („chronione testami E2E przed przebudową paneli i drzewa”), a dziś chroni je tylko Pest na poziomie żądań.
- **Status:** done

### F-02: Wspólna powłoka panelu bocznego

- **Outcome:** (foundation) istnieje jedna powłoka panelu wysuwanego z prawej, bez nakładki: otwarty panel zwęża obszar treści obok siebie, ma wariant szerszy (dla źródła), zamyka się z powrotem do tego samego miejsca, a otwarcie innego rekordu podmienia jego zawartość.
- **Change ID:** side-panel-shell
- **PRD refs:** FR-006, FR-019, FR-028, NFR (1366–2560 px)
- **Unlocks:** `S-01` (panel miejscowości), `S-02` (panel źródła), `S-06` (panel osoby obok drzewa), `S-12` (panel małżeństwa) — każdy z nich podłącza do powłoki własną treść.
- **Prerequisites:** —
- **Parallel with:** F-01, S-05, S-13, S-15
- **Blockers:** —
- **Unknowns:**
  - Gdzie trzymać stan otwartego panelu (adres strony czy stan klienta), żeby zamknięcie wracało na to samo miejsce, a przejście do krewnego podmieniało zawartość? Owner: `/10x-plan`. Block: no.
- **Risk:** Tylko powłoka, bez treści żadnego rekordu — każdy z czterech paneli i tak integruje ją przez własny wycinek; bez niej `S-01` i `S-02` (równoległe) zbudowałyby dwie różne powłoki wbrew zasadzie jednej wspólnej.
- **Status:** done

## Slices

### S-01: Spis miejscowości z istniejących miejsc

- **Outcome:** autor otwiera spis miejscowości, który powstał automatycznie z istniejących tekstowych miejsc (identyczne teksty → jedna miejscowość), dodaje i poprawia miejscowości z opisem w panelu bocznym; cudza miejscowość to 404.
- **Change ID:** places-gazetteer
- **PRD refs:** FR-007, FR-029
- **Prerequisites:** F-02
- **Parallel with:** F-01, S-02, S-05, S-06, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Jakie pola ma miejscowość poza nazwą i opisem (prd-v2 miała współrzędne wpisywane ręcznie; nazwy historyczne trafiają do opisu)? Owner: user. Block: no.
  - Przeniesienie z tekstu do spisu kopiuje, a nie usuwa: tekstowe miejsca zostają do czasu przepięcia faktów (`S-07`) i zamieszkań (`S-08`). Owner: `/10x-plan`. Block: no.
- **Risk:** Pierwsze z trzech przeniesień prawdziwych danych; idzie najwcześniej, bo od spisu zależą parafie (`S-03`), miejsca faktów (`S-07`), zamieszkania (`S-08`) i cmentarze (`S-09`). Błąd zgrupowania tekstów jest naprawialny ręcznie (PRD dopuszcza), utrata tekstu — nie.
- **Status:** done

### S-02: Źródło w szerokim panelu bocznym

- **Outcome:** autor dodaje, ogląda i edytuje źródło w szerokim panelu bocznym, w którym skan (z powiększeniem jak dziś) stoi obok formularza opisu, zamiast w modalu; usuwanie źródła po liście faktów tracących potwierdzenie działa jak dziś.
- **Change ID:** source-side-panel
- **PRD refs:** FR-006, FR-027
- **Prerequisites:** F-02
- **Parallel with:** F-01, S-01, S-05, S-06, S-07, S-08, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Czy skan i formularz obok siebie mieszczą się użytecznie przy 1366 px, czy panel źródła przy tej szerokości przechodzi w układ jeden pod drugim? Owner: `/10x-plan`. Block: no.
- **Risk:** Przenosi istniejący opis bez zmiany pól, żeby gwiazda przewodnia `S-03` zmieniała tylko treść opisu, a nie jednocześnie jego miejsce.
- **Status:** done

### S-03: Akt opisany parafią, rokiem, typem i archiwum

- **Outcome:** autor opisuje akt metrykalny osobnymi danymi: parafia ze spisu parafii (nazwa, wyznanie, miejscowość siedziby ze spisu; nową parafię dopisuje w locie, bez wychodzenia z panelu źródła), rok, typ aktu i archiwum jako tekst podpowiadający wcześniej wpisane archiwa; dotychczasowe „Pochodzenie” jest widoczne jako archiwum.
- **Change ID:** act-described-by-parish-and-archive
- **PRD refs:** FR-001, FR-008, FR-009, US-01
- **Prerequisites:** S-01, S-02
- **Parallel with:** F-01, S-05, S-06, S-07, S-08, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Jakie pola opisu zostają dla źródła rodzaju „inne” (PRD opisuje tylko akt i nagrobek)? Domyślnie: nazwa, link, notatka. Owner: user. Block: no.
  - „Pochodzenie” → archiwum to przeniesienie danych: kopiowane, a stare pole usuwane dopiero w późniejszej migracji. Owner: `/10x-plan`. Block: no.
- **Risk:** Gwiazda przewodnia — przy celu `speed` zaraz po `S-01` i `S-02`; jeśli dopisywanie parafii w locie (z miejscowością siedziby, też dopisywaną w locie) rwie wprowadzanie, wraca kontrargument z FR-001, więc to jest pierwsza rzecz do sprawdzenia na prawdziwym akcie.
- **Status:** in-progress

### S-04: Akt odnaleziony po miejscu i roku, numer wpisu przy powiązaniu

- **Outcome:** przy podpinaniu źródła do faktu autor zawęża listę po miejscu (miejscowość parafii aktu) i roku, widzi przy każdym źródle rok i parafię, a pasujący typ aktu nadal jest pierwszy; podaje numer wpisu przy powiązaniu i widzi go przy fakcie i przy źródle; stary „Numer wpisu” źródła pozostaje widoczny do ręcznego przeniesienia, a każdy istniejący fakt prowadzi do tego samego źródła i skanu.
- **Change ID:** source-found-by-place-and-year
- **PRD refs:** FR-003, FR-004, FR-025, US-01
- **Prerequisites:** S-03, F-01
- **Parallel with:** S-05, S-06, S-07, S-08, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Gdzie autor widzi stary „Numer wpisu” źródła do ręcznego przeniesienia i jak go usuwa, gdy skończy? Owner: user. Block: no.
- **Risk:** Domyka US-01 i główne kryterium sukcesu; dotyka guardrailu fakt–źródło (przenosi numer wpisu ze źródła na powiązanie), dlatego czeka na testy E2E z `F-01`. Szukanie po cmentarzu dochodzi w `S-09`.
- **Status:** proposed

### S-05: Drzewo wypełnia ekran, linie dzieci od właściwej pary

- **Outcome:** autor ogląda drzewo z większymi kartami, które dopasowuje się do ekranu (na dużym wypełnia dostępną przestrzeń), przybliża je i oddala, a linia do dziecka wychodzi od właściwej pary, więc widać, z którego związku jest dziecko; dodawanie rodzica, dziecka i małżonka ze slotów działa jak dziś.
- **Change ID:** tree-fits-screen
- **PRD refs:** FR-013, FR-014, FR-026
- **Prerequisites:** F-01
- **Parallel with:** F-02, S-01, S-02, S-03, S-04, S-07, S-08, S-09, S-10, S-11, S-13
- **Blockers:** —
- **Unknowns:**
  - Jak drzewo mieści się przy 1366 px z wieloma dziećmi i kilkoma małżeństwami — dopasowanie zmniejsza karty czy przechodzi w przybliżanie? Owner: `/10x-plan`. Block: no.
- **Risk:** Ból nr 2 z PRD; przebudowa siatki drzewa grozi zepsuciem slotów (guardrail), dlatego idzie po `F-01` i przed panelem osoby, który musi zwężać już dopasowujące się drzewo.
- **Status:** proposed

### S-06: Panel osoby obok drzewa

- **Outcome:** kliknięcie karty otwiera panel osoby z prawej (przycisk „Podgląd” znika), drzewo zwęża się obok panelu, ustawienie osoby w centrum jest akcją w panelu, przejście do krewnego podmienia zawartość panelu, a po zamknięciu autor jest w tym samym miejscu drzewa; panel otwiera się też z listy osób i ze źródła; edycja osoby odbywa się w panelu.
- **Change ID:** person-side-panel
- **PRD refs:** FR-016, FR-019, FR-028, FR-026, FR-029
- **Prerequisites:** F-01, F-02, S-05
- **Parallel with:** S-01, S-02, S-03, S-04, S-07, S-08, S-09, S-10, S-11, S-13, S-15
- **Blockers:** —
- **Unknowns:**
  - Czy na liście osób i przy źródle panel osoby też zwęża treść obok siebie, tak jak na drzewie? Owner: user. Block: no.
- **Risk:** Zastępuje modal podglądu we wszystkich miejscach wejścia i zmienia kliknięcie karty; historia „Wstecz” może zniknąć (FR-028), ale dodawanie relacji ze slotów i 404 dla cudzych rekordów muszą przetrwać — pilnuje tego `F-01`.
- **Status:** proposed

### S-07: Miejsca faktów i pochowania ze spisu

- **Outcome:** autor wybiera miejsce urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości (z podpowiedziami i dopisaniem w locie); istniejące tekstowe miejsca faktów wskazują na miejscowości ze spisu; niepewność opisuje w notatce.
- **Change ID:** fact-places-from-gazetteer
- **PRD refs:** FR-010, FR-022
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-04, S-05, S-06, S-08, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Czy miejsce pochowania to pole osoby, czy wydarzenie bez daty (PRD: bez daty pogrzebu)? Owner: `/10x-plan`. Block: no.
- **Risk:** Drugie przeniesienie danych (tekst → odwołanie do spisu); tekstowe miejsca zostają do usunięcia w późniejszej migracji. Miejsce pochowania musi istnieć przed nagrobkiem (`S-09`), który je podpowiada.
- **Status:** proposed

### S-08: Kilka zamieszkań z latami

- **Outcome:** autor ustawia osobie kilka miejscowości zamieszkania ze spisu z opcjonalnymi latami od–do; dotychczasowe pole „Zamieszkanie” jest pierwszą pozycją listy, bez lat.
- **Change ID:** residence-history
- **PRD refs:** FR-011
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-04, S-05, S-06, S-07, S-09, S-10, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Mały i niezależny od źródeł; wznawia odłożoną historię zamieszkania z prd-v2 (dawne S-14). Zamieszkania nie potwierdza się źródłem (Non-Goals).
- **Status:** proposed

### S-09: Nagrobek z cmentarzem i osobami pochowanymi

- **Outcome:** autor opisuje zdjęcie nagrobka cmentarzem (miejscowość ze spisu) i osobami pochowanymi; miejsce pochowania osoby z listy podpowiada się cmentarzem nagrobka (można zmienić); przy wyborze źródła do faktu nagrobek odnajduje się po miejscowości cmentarza i roku.
- **Change ID:** gravestone-cemetery-and-buried
- **PRD refs:** FR-002, FR-004
- **Prerequisites:** S-04, S-07
- **Parallel with:** S-05, S-06, S-08, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Czy dodanie osoby do listy pochowanych od razu ustawia jej miejsce pochowania, czy tylko je podpowiada przy edycji osoby? Owner: user. Block: no.
- **Risk:** Rozszerza wyszukiwanie z `S-04` o cmentarz i korzysta z miejsca pochowania z `S-07`; ustawiony po nich, żeby nie budować tych elementów dwa razy.
- **Status:** proposed

### S-10: Siatka źródeł z filtrami

- **Outcome:** autor przegląda listę źródeł jako siatkę mniejszych kart z rodzajem, typem, parafią lub cmentarzem, rokiem i archiwum oraz filtruje ją po rodzaju, parafii/miejscu, roku i archiwum.
- **Change ID:** sources-grid-with-filters
- **PRD refs:** FR-005
- **Prerequisites:** S-09
- **Parallel with:** S-05, S-06, S-08, S-11, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Czy widok „Tabela” zostaje obok nowej siatki? Owner: user. Block: no.
- **Risk:** Czeka na komplet pól opisu (akt w `S-03`, nagrobek w `S-09`), żeby karty i filtry powstały raz; punkt wyjścia — istniejąca galeria z filtrem rodzaju.
- **Status:** proposed

### S-11: Scalanie i usuwanie miejscowości i parafii

- **Outcome:** autor scala zduplikowaną miejscowość lub parafię z właściwym wpisem — wszystkie odwołania (parafie, cmentarze nagrobków, miejsca faktów, miejsca pochowania, zamieszkania) przechodzą na właściwy wpis — i usuwa wpis, który nie jest nigdzie używany.
- **Change ID:** places-merge-and-delete
- **PRD refs:** FR-012
- **Prerequisites:** S-03, S-07, S-08, S-09
- **Parallel with:** S-05, S-06, S-10, S-12, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:** —
- **Uwaga (E2E):** dopisać sprzątanie parafii i miejscowości w `source-panel-parish.spec.ts` (dziś zostają w bazie `e2e`, bo nie ma tras usuwania).
- **Risk:** Na końcu strumienia miejsc, bo musi objąć każdy rodzaj odwołania naraz; do tego czasu literówki po automatycznym przeniesieniu poprawia się edycją nazwy w spisie.
- **Status:** proposed

### S-12: Panel małżeństwa

- **Outcome:** autor otwiera panel małżeństwa z datą, miejscem, źródłem (z edycją) i dziećmi pary; małżeństwo nadal można edytować także w panelu osoby.
- **Change ID:** marriage-side-panel
- **PRD refs:** FR-017
- **Prerequisites:** S-06
- **Parallel with:** S-01, S-02, S-03, S-04, S-07, S-08, S-09, S-10, S-11, S-13, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Skąd otwiera się panel małżeństwa — z linii pary na drzewie, z sekcji małżeństwa w panelu osoby, czy z obu? Owner: user. Block: no.
- **Risk:** Czwarte użycie wspólnej powłoki; nisko w kolejności, bo edycja małżeństwa już działa w podglądzie osoby.
- **Status:** proposed

### S-13: Nazwisko rodowe i wiek

- **Outcome:** autor zapisuje nazwisko rodowe — osoba wyświetla się „z d. …”, gdy znane są oba nazwiska, i jest wyszukiwana po obu — oraz widzi wiek osoby: ile przeżyła (gdy zna datę zgonu) albo ile ma (tylko gdy wynik jest poniżej ok. 110 lat).
- **Change ID:** maiden-name-and-age
- **PRD refs:** FR-020, FR-024
- **Prerequisites:** —
- **Parallel with:** F-01, F-02, S-01, S-02, S-03, S-04, S-05, S-06, S-07, S-08, S-09, S-10, S-11, S-12, S-14, S-15, S-16
- **Blockers:** —
- **Unknowns:**
  - Jak liczyć wiek przy datach niepełnych lub „około” (sam rok → wiek przybliżony)? Owner: `/10x-plan`. Block: no.
- **Risk:** Bez zależności — dobry kandydat na równoległe uruchomienie agenta; panel osoby (`S-06`) przejmie wyświetlanie bez przebudowy danych.
- **Status:** done

### S-14: Zakładki wydarzeń i notatki w panelu osoby

- **Outcome:** autor widzi w panelu osoby zakładkę wydarzeń — własnych oraz urodzeń i zgonów dzieci, ślubu i zgonu małżonka — uporządkowanych po roku, z wydarzeniami bez daty na końcu, oraz zakładkę z jedną notatką tekstową o osobie.
- **Change ID:** person-events-and-note-tabs
- **PRD refs:** FR-021, FR-023
- **Prerequisites:** S-06
- **Parallel with:** S-01, S-02, S-03, S-04, S-07, S-08, S-09, S-10, S-11, S-12, S-13, S-15, S-16
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Rozbudowuje panel osoby o zakładki, więc idzie po `S-06`; samodzielny i bez ryzyka dla guardrails.
- **Status:** proposed

### S-15: Widok wszystkich przodków

- **Outcome:** autor wybiera osobę i widzi ją oraz wszystkich jej przodków w jednym widoku z wyborem liczby pokoleń, dopasowanym do ekranu i z przybliżaniem jak w drzewie; widok służy do oglądania (bez dodawania osób i relacji); cudza osoba to 404.
- **Change ID:** ancestors-view
- **PRD refs:** FR-018, FR-029
- **Prerequisites:** S-05
- **Parallel with:** F-02, S-01, S-02, S-03, S-04, S-06, S-07, S-08, S-09, S-10, S-11, S-12, S-13, S-14, S-16
- **Blockers:** —
- **Unknowns:**
  - Czy kliknięcie przodka w tym widoku otwiera panel osoby (wtedy zależność od `S-06`)? Owner: user. Block: no.
- **Risk:** Korzysta z mechanizmu dopasowania i przybliżania z `S-05`, żeby 6+ pokoleń pozostało czytelne; samodzielny nowy widok, więc może iść równolegle z resztą.
- **Status:** proposed

### S-16: Zdjęcie osoby na karcie drzewa

- **Outcome:** autor dodaje zdjęcie osoby w panelu i widzi je na karcie drzewa; karta bez zdjęcia pokazuje inicjały lub sylwetkę.
- **Change ID:** person-photo-on-card
- **PRD refs:** FR-015
- **Prerequisites:** S-05, S-06
- **Parallel with:** S-01, S-02, S-03, S-04, S-07, S-08, S-09, S-10, S-11, S-12, S-13, S-14, S-15
- **Blockers:** —
- **Unknowns:**
  - Czy zdjęcie przechodzi przez tę samą obsługę plików i miniatur co źródła? Owner: `/10x-plan`. Block: no.
- **Risk:** Ostatni z rozmysłem — PRD wskazuje FR-015 jako pierwsze do odcięcia, gdy zabraknie czasu; kopie `storage/app` obejmują zdjęcia bez nowej pracy.
- **Status:** proposed

## Backlog Handoff

GitHub milestone: [M-2](https://github.com/sebastian-kostecki/root-keeper/milestone/2). Issues #20 – #23 (dawne S-12 – S-15 ze spisu miejscowości prd-v2) przepięte 2026-10-04 do M-2 jako `S-01`, `S-07`, `S-08` i `S-11` — nowe tytuły i opisy, etykiety `slice` + `status:proposed`, bez etykiet `stream:*` z M-1. Pozostałe wycinki dostaną nowe issues.

| Roadmap ID | Change ID                           | Suggested issue title                                              | Ready for `/10x-plan` | Notes |
| ---------- | ----------------------------------- | ------------------------------------------------------------------ | --------------------- | ----- |
| F-01       | critical-flows-e2e-guard            | Testy E2E: fakt–źródło i relacje ze slotów drzewa                  | yes                   | Faza 4 planu testów; prowadzić przez `/10x-e2e`. Odblokowuje `S-04` – `S-06`. |
| F-02       | side-panel-shell                    | Wspólna powłoka panelu bocznego                                    | yes                   | Run `/10x-plan side-panel-shell`; odblokowuje gwiazdę przewodnią przez `S-01`, `S-02`. |
| S-01       | places-gazetteer                    | Spis miejscowości z istniejących miejsc                            | no                    | Czeka na F-02; issue #20. |
| S-02       | source-side-panel                   | Źródło w szerokim panelu bocznym                                   | no                    | Czeka na F-02. |
| S-03       | act-described-by-parish-and-archive | Akt opisany parafią, rokiem, typem i archiwum                      | no                    | Gwiazda przewodnia; czeka na S-01, S-02. |
| S-04       | source-found-by-place-and-year      | Szukanie źródła po miejscu i roku, numer wpisu przy powiązaniu     | no                    | Domyka US-01; czeka na S-03, F-01. |
| S-05       | tree-fits-screen                    | Drzewo dopasowane do ekranu, linie dzieci od właściwej pary        | no                    | Czeka na F-01. |
| S-06       | person-side-panel                   | Panel osoby obok drzewa zamiast modala                             | no                    | Czeka na F-01, F-02, S-05. |
| S-07       | fact-places-from-gazetteer          | Miejsca faktów i pochowania ze spisu miejscowości                  | no                    | Czeka na S-01; issue #21. |
| S-08       | residence-history                   | Kilka zamieszkań z latami                                          | no                    | Czeka na S-01; issue #22. |
| S-09       | gravestone-cemetery-and-buried      | Nagrobek z cmentarzem i osobami pochowanymi                        | no                    | Czeka na S-04, S-07. |
| S-10       | sources-grid-with-filters           | Siatka źródeł z filtrami                                           | no                    | Czeka na S-09. |
| S-11       | places-merge-and-delete             | Scalanie i usuwanie miejscowości i parafii                         | no                    | Czeka na S-03, S-07, S-08, S-09; issue #23. |
| S-12       | marriage-side-panel                 | Panel małżeństwa                                                   | no                    | Czeka na S-06. |
| S-13       | maiden-name-and-age                 | Nazwisko rodowe i wiek osoby                                       | yes                   | Run `/10x-plan maiden-name-and-age`; bez zależności. |
| S-14       | person-events-and-note-tabs         | Zakładki wydarzeń i notatki w panelu osoby                         | no                    | Czeka na S-06. |
| S-15       | ancestors-view                      | Widok wszystkich przodków                                          | no                    | Czeka na S-05. |
| S-16       | person-photo-on-card                | Zdjęcie osoby na karcie drzewa                                     | no                    | Czeka na S-05, S-06; pierwsze do odcięcia. |

## Open Roadmap Questions

Brak otwartych pytań. PRD nie ma otwartych pytań (oba rozstrzygnięte 2026-10-04), a pytania w wycinkach nie blokują planowania.

Rozstrzygnięte:

- **Co z issues #20 – #23 (spis miejscowości z prd-v2)?** (2026-10-04, user) — Przepiąć do M-2: #20 → `S-01`, #21 → `S-07`, #22 → `S-08`, #23 → `S-11`, z tytułami i opisami zaktualizowanymi do nowych wycinków; pozostałe wycinki dostają nowe issues.
- **Co odpada po FR-015, jeśli zabraknie czasu do 2026-11-04?** (2026-10-04, user) — Nic więcej: odcinalne jest tylko `S-16` (FR-015), reszta zakresu jest nieodcinalna — przy braku czasu przesuwa się termin, nie zakres.

## Parked

- **Udostępnianie drzewa rodzinie** — Why parked: PRD §Non-Goals; jeden użytkownik na drzewo, model dostępu bez zmian.
- **Dodawanie osób i relacji z widoku przodków** — Why parked: PRD §Non-Goals; widok przodków (`S-15`) służy do oglądania.
- **Import/eksport GEDCOM** — Why parked: PRD §Non-Goals.
- **Nazwy historyczne i zmiany granic miejscowości** — Why parked: PRD §Non-Goals; trafiają do opisu miejscowości (`S-01`).
- **Świadkowie ślubu** — Why parked: PRD §Non-Goals; panel małżeństwa (`S-12`) pokazuje datę, miejsce, źródło i dzieci.
- **Wiele nazwisk po kolejnych mężach** — Why parked: PRD §Non-Goals; nazwisko i nazwisko rodowe (`S-13`), reszta w notatce.
- **Data pogrzebu i potwierdzanie zamieszkania źródłem** — Why parked: PRD §Non-Goals; pochowanie to samo miejsce (`S-07`), zamieszkanie to notatka badawcza (`S-08`).
- **Zarządzana lista archiwów** — Why parked: PRD §Non-Goals; archiwum to tekst z podpowiedziami (`S-03`).
- **Historia „Wstecz” między krewnymi** — Why parked: PRD §Usunięte (FR-028); znika przy przejściu na panel (`S-06`).
- **Pozostałe odłożone z M-1** (eksport danych, kosz i cofanie, OCR, scalanie osób, wgrywanie wielu plików i inne) — Why parked: bez zmian względem M-1; lista w historii git (`git show 685ffa2:context/foundation/roadmap.md`, sekcja Parked). Z tamtej listy wracają w M-2: zdjęcie osoby (`S-16`), spis miejscowości z historią zamieszkania (`S-01`, `S-07`, `S-08`, `S-11`), przeniesienie istniejących miejsc do spisu (`S-01`), okresy zamieszkań (`S-08`) i numer wpisu przy powiązaniu (`S-04`).

## Milestone History

- **M-1: Drzewo potwierdzone źródłami** (`sourced-family-tree`) — closed 2026-10-04. Fakty urodzenia, małżeństwa i zgonu prowadzą do źródeł każdego rodzaju, podgląd osoby w modalu, relacje dodawane z drzewa (prd-v1 – prd-v4; spis miejscowości odłożony).

## Done

- **F-02: (foundation) istnieje jedna powłoka panelu wysuwanego z prawej, bez nakładki: otwarty panel zwęża obszar treści obok siebie, ma wariant szerszy (dla źródła), zamyka się z powrotem do tego samego miejsca, a otwarcie innego rekordu podmienia jego zawartość.** — Archived 2026-10-04 → `context/archive/2026-10-04-side-panel-shell/`. Lesson: —.
- **F-01: (foundation) testy E2E w przeglądarce sprawdzają, że fakt potwierdzony źródłem prowadzi do tego samego źródła i skanu oraz że rodzica, dziecko i małżonka (istniejącego lub nowego) dodaje się z pustych slotów drzewa — z asercją na danych, nie na wyglądzie.** — Archived 2026-10-05 → `context/archive/2026-10-04-critical-flows-e2e-guard/`. Lesson: —.
- **S-13: autor zapisuje nazwisko rodowe — osoba wyświetla się „z d. …”, gdy znane są oba nazwiska, i jest wyszukiwana po obu — oraz widzi wiek osoby: ile przeżyła (gdy zna datę zgonu) albo ile ma (tylko gdy wynik jest poniżej ok. 110 lat).** — Archived 2026-10-05 → `context/archive/2026-10-05-maiden-name-and-age/`. Lesson: —.
- **S-01: autor otwiera spis miejscowości, który powstał automatycznie z istniejących tekstowych miejsc (identyczne teksty → jedna miejscowość), dodaje i poprawia miejscowości z opisem w panelu bocznym; cudza miejscowość to 404.** — Archived 2026-10-05 → `context/archive/2026-10-05-places-gazetteer/`. Lesson: —.
- **S-02: autor dodaje, ogląda i edytuje źródło w szerokim panelu bocznym, w którym skan (z powiększeniem jak dziś) stoi obok formularza opisu, zamiast w modalu; usuwanie źródła po liście faktów tracących potwierdzenie działa jak dziś.** — Archived 2026-10-05 → `context/archive/2026-10-05-source-side-panel/`. Lesson: —.
