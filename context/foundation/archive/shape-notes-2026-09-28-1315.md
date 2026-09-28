---
project: "root-keeper"
context_type: brownfield
created: 2026-09-28
updated: 2026-09-28
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 5
  hard_deadline: 2026-11-04
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "pain moment"
      decision: "all three: entering the real tree from the tree view, every small edit on a separate page, gravestone photos with nowhere to attach"
    - topic: "meaning of 'scan -> source'"
      decision: "one 'source' concept with a kind (vital record, gravestone photo, ...); every kind can confirm facts"
    - topic: "relation to prd-v2"
      decision: "sources absorb S-10 (description designed for source, not scan); S-10/S-11 to be replanned; gazetteer S-12-S-15 unchanged"
    - topic: "must preserve (phase 1)"
      decision: "existing scan-event confirmations and confirmed/unconfirmed state; uploaded files, thumbnails and zoom preview"
    - topic: "primary persona"
      decision: "unchanged — the author"
    - topic: "auth / roles"
      decision: "no change — own tree only, other user's record is 404"
    - topic: "scope vs 3-week budget"
      decision: "whole change at once (no staging); longer timeline accepted — 5 weeks after hours, to 2026-11-04"
    - topic: "modal scope"
      decision: "person add/edit + source add/edit; facts and relations stay as they are"
    - topic: "empty slot on tree"
      decision: "always creates a NEW person; linking an existing person stays on person preview"
    - topic: "guardrails"
      decision: "confirmations 1:1, files/preview intact, relation correctness, source delete shows impact + confirmation"
    - topic: "blast radius"
      decision: "/scans URLs; persons/create and persons/edit pages"
    - topic: "source kinds"
      decision: "vital record, gravestone photo, other"
    - topic: "source description"
      decision: "common: name, year, origin, note; vital record adds type + entry number; only file + kind required"
    - topic: "child from tree"
      decision: "child of the pair shown on tree (union switch picks the pair); modal shows both parents"
    - topic: "person pages"
      decision: "persons/create and persons/edit removed; full-screen modal on narrow screens"
    - topic: "Socrates round"
      decision: "FR-001 duplicates risk accepted; FR-003 spouse slot only at focus; FR-005 existing scans -> vital record; FR-007 warn on kind change; FR-008 one 'confirmed' state + visible kind; FR-009 matching vital records first (absorbs S-11); FR-010 old /scans URLs may break; FR-011 simple confirm when no links; FR-012 slot sets and locks gender"
    - topic: "business logic"
      decision: "confirmation rule modified: any source kind confirms, kind visible; new tree-slot rules (role + gender, child to shown pair)"
    - topic: "migration rollback"
      decision: "backups (F-01) right before deploy"
    - topic: "NFR"
      decision: "no new NFRs"
    - topic: "priority vs gazetteer"
      decision: "this change wins; S-12-S-15 slip past 2026-11-04 if needed"
    - topic: "product framing"
      decision: "no change — web-app, small; hard deadline 2026-11-04, after hours"
    - topic: "non-goals"
      decision: "no modals for facts/relations; no duplicate merging; no multi-file upload; no weaker confirmation state; no /scans redirects"
  frs_drafted: 12
  quality_check_status: accepted
---

# root-keeper — shape notes (brownfield)

Poprzednie sesje: MVP `context/foundation/archive/shape-notes-2026-09-28-0612.md`, prd-v2 (opisy skanów + spis miejscowości) `context/foundation/archive/shape-notes-2026-09-28-1130.md`.

## Seed idea

> - drzewo
>     - modal na dodawanie osoby
>     - modale na dodawanie czegos/edycje (wszystkiego)
>     - jak drzewo - nieznany ojciec to dać możliwość utworzenia go z drzewa
>     - jak drzewo dać mozliwosc dodawania dzieci
>     - jak drzewo dac mozliwosc dodawania wspolmazonka (jeśli nie ma to ma byc widoczny pusty slot)
> - zamiast skany -> źródła (to musi być traktowane jako źródła)
> - do źródeł należy również zaliczyć zdjęcia nagrobków

## Current System

root-keeper — prywatne drzewo genealogiczne (Laravel + Inertia/React z TypeScript, shadcn/ui, PostgreSQL, samodzielny hosting w Dockerze), jedno drzewo na konto, otwarta rejestracja, każdy widzi tylko swoje dane. Kamień milowy M-1 (MVP) ma wszystkie slice'y v1 zrobione (S-01 – S-09); rozszerzenie prd-v2 (S-10 – S-15: opis skanu, spis miejscowości) nie zostało rozpoczęte.

Stan istotny dla tej zmiany:
- **Widok drzewa (S-09)** to arkusz rodziny: osoba w centrum, rodzice, dziadkowie, małżonek, dzieci. Brakująca osoba to przerywana karta „nieznany” z samą etykietą — bez żadnej akcji. Drzewo służy tylko do oglądania i przechodzenia do osób.
- **Dodawanie i edycja** osoby odbywa się na osobnych stronach (`persons/create`, `persons/edit`); część akcji (małżeństwo, wybór krewnego, wybór skanu, usunięcia) ma już jednorazowe okna dialogowe.
- **Skany** to pliki aktów metrykalnych (nazwa oryginału, rozmiar, miniatura, powiększenie), które potwierdzają urodzenie, małżeństwo i zgon; jeden skan może potwierdzać wiele wydarzeń. Nie ma innego rodzaju dowodu.

Użytkownicy: autor (genealog pracujący na własnych materiałach).

## Vision & Problem Statement

Wprowadzanie prawdziwego drzewa i codzienne poprawki pokazały trzy luki:

1. **Drzewo tylko do oglądania.** Widząc na drzewie „nieznany ojciec”, autor musi wyjść na stronę dodawania osoby, a potem wrócić i połączyć relację — gubi kontekst. Z drzewa nie da się utworzyć brakującego rodzica, dodać dzieci ani małżonka (brak małżonka ma być widoczny jako pusty slot).
2. **Formularze na osobnych stronach.** Każde dodanie i każda drobna poprawka (data, miejsce) to przejście na osobną stronę i z powrotem. Autor chce dodawania osoby i dodawania/edycji wszystkiego w oknach modalnych.
3. **„Skan” to za wąskie pojęcie.** Autor ma zdjęcia nagrobków z datami, a aplikacja przyjmuje tylko skany aktów, więc nie ma gdzie podłączyć ich jako dowodu. Skan ma stać się **źródłem** z rodzajem (np. akt metrykalny, zdjęcie nagrobka), traktowanym jako źródło faktów.

Zmiana wchłania S-10 z prd-v2: opis (nazwa, typ, rok, pochodzenie, numer i notatka wpisu) projektujemy od razu dla źródła, nie dla skanu; S-10/S-11 do przeplanowania. Spis miejscowości (S-12 – S-15) bez zmian.

## User & Persona

Bez zmian: jedynym realnym użytkownikiem jest autor aplikacji, genealog prowadzący badania na własnych materiałach. Zmiana dotyczy chwil, gdy uzupełnia drzewo patrząc na nie, gdy poprawia drobne dane oraz gdy wraca z cmentarza ze zdjęciami nagrobków.

## Access Control

No changes planned — current model preserved: otwarta rejestracja, jedno drzewo na konto, każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnych danych, bez ról i współdzielenia; cudzy rekord to 404.

Nowe dane i akcje wchodzą pod tę samą zasadę: źródła (w tym zdjęcia nagrobków) oraz dodawanie/edycja z drzewa działają tylko na własnym drzewie.

## Success Criteria

Przepływ zmiany (potwierdzony przez użytkownika, całość naraz — bez etapów):

1. Na drzewie klikam pusty slot „nieznany ojciec/matka” → modal „dodaj osobę” → zapis → osoba od razu na drzewie jako rodzic, bez opuszczania drzewa.
2. Przy osobie na drzewie dodaję dziecko → modal → dziecko na drzewie.
3. Brak małżonka widać jako pusty slot → klik → modal → małżonek na drzewie.
4. Dodaję i poprawiam osobę w modalu (nie na osobnej stronie).
5. W „Źródłach” (dawniej „Skany”) dodaję źródło w modalu: plik + rodzaj (akt metrykalny / zdjęcie nagrobka) + opis; potwierdzam nim fakt jak dziś skanem.

Z pustego slotu na drzewie powstaje zawsze **nowa** osoba; łączenie z osobą już istniejącą w drzewie zostaje na podglądzie osoby jak dziś.

Zakres modali: dodawanie/edycja osoby oraz dodawanie/edycja źródła. Fakty (urodzenie, zgon, ślub) i relacje zostają w obecnej formie.

Blast radius wskazany przez użytkownika: adresy `/scans` (zakładki, linki) po zmianie na źródła; strony `persons/create` i `persons/edit` (znikają albo zostają obok modali).

### Primary
- Powyższy przepływ 1–5 działa na prawdziwych danych: brakującego rodzica, dziecko i małżonka tworzę z drzewa w modalu i widzę ich od razu na drzewie; osobę dodaję i poprawiam w modalu; zdjęcie nagrobka dodaję jako źródło rodzaju „nagrobek” i potwierdzam nim fakt.

### Secondary
- Mniej kliknięć przy wpisywaniu: rodzinę osoby (rodzice, małżonek, dzieci) uzupełniam w całości bez opuszczania drzewa.

### Guardrails
- Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” przechodzą 1:1 na źródła.
- Wgrane pliki, miniatury i powiększenie działają; żaden plik nie ginie.
- Poprawność relacji: z drzewa nie da się dodać trzeciego rodzica, drugiego ojca ani uczynić kogoś własnym przodkiem.
- Usunięcie źródła dalej pokazuje fakty, które stracą potwierdzenie, i wymaga jawnego potwierdzenia.

## Timeline acknowledgment

Acknowledged on 2026-09-28: 5-week change (after hours, all at once, no staging) requires sustained dedication; user accepted.

Termin: 2026-11-04 — ten sam, na który celuje nierozpoczęty prd-v2 (spis miejscowości S-12 – S-15 konkuruje o ten sam czas).

## User Stories

### US-01: Nieznany ojciec utworzony z drzewa

- **Given** zalogowany użytkownik z osobą na drzewie, której ojciec jest nieznany (slot „Nieznany ojciec”)
- **When** klika slot, w modalu wpisuje dane nowej osoby (płeć ustawiona na mężczyznę) i zapisuje
- **Then** widzi ojca na drzewie połączonego z dzieckiem, nie opuszczając widoku drzewa

Wcześniej: slot „nieznany” był samą etykietą; trzeba było wyjść na stronę dodawania osoby, wrócić na podgląd dziecka i połączyć relację.

### US-02: Nagrobek potwierdza zgon

- **Given** zalogowany użytkownik ze zdjęciem nagrobka z datą zgonu osoby
- **When** w „Źródłach” dodaje w modalu źródło rodzaju „zdjęcie nagrobka” (plik + rodzaj, opis opcjonalny), a potem wiąże je ze zgonem osoby
- **Then** zgon ma stan „potwierdzone”, a przy fakcie widać rodzaj źródła („nagrobek”) i można otworzyć zdjęcie w powiększeniu

Wcześniej: aplikacja przyjmowała tylko „skany” aktów; zdjęcia nagrobka nie było gdzie podłączyć jako dowodu.

## Functional Requirements

### Drzewo
- FR-001: Użytkownik może z pustego slotu rodzica na drzewie utworzyć w modalu nową osobę, która od razu staje się rodzicem tej osoby i pojawia się na drzewie; slot ustawia płeć (ojciec → mężczyzna, matka → kobieta, „nieznany rodzic” → wybór w modalu), a płeć jest w modalu zablokowana. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "ojciec często już jest w drzewie (wpisany z innej gałęzi) — »zawsze nowa osoba« produkuje duplikaty." Resolution: kept; ryzyko duplikatów zaakceptowane — łączenie z istniejącą osobą zostaje na podglądzie osoby, duplikaty poprawiane ręcznie.
- FR-002: Użytkownik może z drzewa dodać w modalu nowe dziecko pary pokazanej na drzewie (z małżonkiem — oboje rodzice, bez małżonka — tylko ta osoba); modal pokazuje, do kogo trafi dziecko, a dziecko od razu pojawia się na drzewie. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "osoba z kilkoma małżonkami (przełącznik związku) — dziecko może trafić do złej pary." Resolution: changed — dziecko trafia do pary aktualnie wybranej przełącznikiem związku, a modal jawnie pokazuje obojga rodziców.
- FR-003: Użytkownik widzi pusty slot małżonka przy osobie w centrum drzewa, gdy nie ma ona małżonka, i może z niego utworzyć w modalu nowego małżonka, widocznego od razu na drzewie. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "pusty slot małżonka przy każdym dziecku i młodej osobie zaśmieca drzewo." Resolution: changed — pusty slot tylko przy osobie w centrum; żeby dodać małżonka komuś innemu, przestawiam go na środek.

### Osoba
- FR-004: Użytkownik może dodać i poprawić osobę w modalu; strony `persons/create` i `persons/edit` znikają, a na wąskim ekranie modal zajmuje cały ekran i się przewija. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "formularz osoby jest długi (daty częściowe, miejsca) — w modalu na telefonie nieczytelny." Resolution: changed — pełnoekranowy, przewijany modal na wąskim ekranie; na komputerze zwykły modal.

### Źródła
- FR-005: „Skany” stają się „Źródłami”; każde źródło ma rodzaj: akt metrykalny, zdjęcie nagrobka lub inne. Istniejące skany stają się źródłami rodzaju „akt metrykalny”. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "istniejące skany dostaną rodzaj automatycznie, a wśród nich mogą już być zdjęcia nagrobków." Resolution: changed — wszystkie istniejące skany → „akt metrykalny”; błędne rodzaje poprawiam ręcznie przez edycję (FR-007).
- FR-006: Użytkownik może dodać źródło w modalu: plik i rodzaj (wymagane) oraz opcjonalny opis — każde źródło: nazwa, rok, pochodzenie, notatka; akt metrykalny dodatkowo typ (urodzenia / małżeństwa / zgonu / inny) i numer wpisu. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "po cmentarzu mam dziesiątki zdjęć — opis przy każdym pliku z osobna spowalnia wrzucanie." Resolution: changed — wymagane tylko plik i rodzaj; opis uzupełniany później w edycji. Jedno źródło = jeden plik.
- FR-007: Użytkownik może poprawić rodzaj i opis źródła w modalu; zmiana rodzaju z aktu metrykalnego na inny pokazuje, które pola (typ, numer wpisu) znikną, i wymaga potwierdzenia. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "zmiana rodzaju z aktu na nagrobek kasuje typ i numer wpisu bez ostrzeżenia." Resolution: changed — ostrzeżenie z listą znikających pól i potwierdzenie przed zapisem.
- FR-008: Użytkownik może potwierdzić urodzenie, małżeństwo i zgon źródłem każdego rodzaju; stan pozostaje „potwierdzone”, a przy fakcie widać rodzaj źródła (np. „potwierdzone: nagrobek”). Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "nagrobek nie jest dowodem na równi z aktem — ten sam stan »potwierdzone« zawyża pewność." Resolution: changed — jeden stan „potwierdzone”, ale rodzaj źródła widoczny przy fakcie; o wadze dowodu decyduje użytkownik.

### Zachowane
- FR-009: Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” przechodzą 1:1 na źródła; przy wyborze źródła dla faktu najpierw widać akty metrykalne o typie pasującym do faktu, potem resztę. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "wybór źródła przy fakcie pokaże też nagrobki i »inne« — trudniej znaleźć właściwy akt." Resolution: changed — pasujące akty najpierw (wchłania v2:FR-015 / S-11 z prd-v2).
- FR-010: Wgrane pliki, miniatury i powiększenie działają dla każdego źródła; żaden plik nie ginie. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "zakładki i linki do /scans przestaną działać po zmianie na źródła." Resolution: kept; stare adresy `/scans` mogą przestać działać — jedynym użytkownikiem jest autor, zakładki poprawi sam.
- FR-011: Usunięcie źródła z powiązaniami pokazuje fakty, które stracą potwierdzenie, i wymaga jawnego potwierdzenia; źródło bez powiązań usuwa się po prostym potwierdzeniu. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "źródło wgrane przez pomyłkę, bez powiązań — dialog skutków to zbędny krok." Resolution: changed — bez powiązań krótkie „Usunąć?”, z powiązaniami lista faktów jak dziś.
- FR-012: Relacje pozostają poprawne także przy tworzeniu z drzewa: maks. dwoje rodziców, nikt nie jest własnym przodkiem. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "poprawność to też płeć — osoba utworzona w slocie »ojciec« musi być mężczyzną, inaczej drzewo kłamie." Resolution: changed — slot ustawia i blokuje płeć (zapisane w FR-001).

## Business Logic

Fakt osoby jest „potwierdzony”, gdy jest powiązany z co najmniej jednym źródłem dowolnego rodzaju, a przy fakcie zawsze widać rodzaj źródła, które go potwierdza.

Regułę modyfikujemy: dziś fakt (urodzenie, małżeństwo, zgon) jest „potwierdzony”, gdy ma co najmniej jeden powiązany skan aktu metrykalnego. Po zmianie potwierdzać może źródło każdego rodzaju (akt metrykalny, zdjęcie nagrobka, inne); stan pozostaje jeden, a o wadze dowodu decyduje użytkownik, widząc rodzaj źródła przy fakcie. Jedno źródło nadal może potwierdzać wiele wydarzeń, a małżeństwo nadal jest jednym wydarzeniem obojga małżonków.

Nowe reguły drzewa: pusty slot wyznacza rolę i płeć nowej osoby — slot „nieznany ojciec” tworzy ojca-mężczyznę, „nieznana matka” matkę-kobietę, „nieznany rodzic” rodzica o płci wybranej przez użytkownika. Dziecko utworzone z drzewa należy do pary aktualnie pokazanej na drzewie. Pusty slot małżonka istnieje tylko przy osobie w centrum drzewa. Tworzenie z drzewa podlega tym samym ograniczeniom relacji co dziś (maks. dwoje rodziców, nikt nie jest własnym przodkiem).

## Constraints & Preserved Behavior

- **Migracja danych:** wszystkie istniejące skany stają się źródłami rodzaju „akt metrykalny”; pliki, miniatury i powiązania z wydarzeniami przechodzą 1:1; stan „potwierdzone/niepotwierdzone” każdego faktu jest taki sam przed i po migracji. Opis źródła startuje pusty poza nazwą oryginału pliku.
- **Plan wycofania:** backupy danych i plików (F-01) wykonane tuż przed wdrożeniem.
- **Kompatybilność wsteczna:** stare adresy `/scans` mogą przestać działać (jedynym użytkownikiem jest autor). Strony `persons/create` i `persons/edit` znikają.
- **Zachowane:** potwierdzenia faktów 1:1; pliki, miniatury i powiększenie; usuwanie źródła z listą faktów tracących potwierdzenie; ograniczenia relacji; prywatność drzewa (cudzy rekord = 404).
- **prd-v2:** zmiana wchłania v2:FR-001 – v2:FR-005 i v2:FR-014 (S-10, opis — teraz opis źródła) oraz v2:FR-015 (S-11, pasujące najpierw). Spis miejscowości (S-12 – S-15) bez zmian, ale przy braku czasu przesuwa się za termin 2026-11-04 — ta zmiana ma pierwszeństwo.

## Non-Functional Requirements

Brak nowych NFR — istniejące (prywatność danych drzewa, czytelność powiększenia skanu) obejmują źródła każdego rodzaju i modale.

## Product framing

- Typ produktu: bez zmian — aplikacja webowa (`web-app`).
- Skala: bez zmian — autor, mała (`small`).
- Termin: twardo 2026-11-04, praca po godzinach; 5 tygodni. Przy braku czasu ta zmiana ma pierwszeństwo przed spisem miejscowości (S-12 – S-15).

## Non-Goals

- **Brak modali dla faktów i relacji** — urodzenie, zgon, ślub i relacje zostają w obecnej formie; modale obejmują tylko osobę i źródło.
- **Brak scalania duplikatów osób** — tworzenie z drzewa zawsze robi nową osobę (ryzyko zaakceptowane w FR-001); duplikaty poprawiane ręcznie.
- **Brak wgrywania wielu plików naraz** — jedno źródło = jeden plik, dodawany pojedynczo (wymagane tylko plik i rodzaj, FR-006).
- **Brak słabszego stanu potwierdzenia dla nagrobka** — jeden stan „potwierdzone” + widoczny rodzaj źródła (FR-008).
- **Brak przekierowań ze starych adresów `/scans`** — jedynym użytkownikiem jest autor (FR-010).
