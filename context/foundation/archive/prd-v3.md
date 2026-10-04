---
project: "root-keeper"
version: 3
status: draft
created: 2026-09-28
context_type: brownfield
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 5
  hard_deadline: 2026-11-04
  after_hours_only: true
---

# root-keeper — uzupełnianie drzewa z drzewa i źródła zamiast skanów (zmiana brownfield)

Poprzednie PRD: MVP `context/foundation/prd.md`, opisy skanów i spis miejscowości `context/foundation/prd-v2.md`. Notatki z kształtowania tej zmiany: `context/foundation/shape-notes.md`.

## Current System Overview

root-keeper to prywatne drzewo genealogiczne: jedno drzewo na konto, otwarta rejestracja, każdy widzi tylko swoje dane.

- **Architektura i stack:** monolit Laravel + Inertia/React z TypeScript, shadcn/ui, PostgreSQL, samodzielny hosting w Dockerze. Szczegóły: `context/foundation/tech-stack.md`.
- **Użytkownicy:** autor (genealog pracujący na własnych materiałach); skala mała.
- **Funkcje dziś:** kamień milowy M-1 (MVP) ma wszystkie slice'y v1 zrobione (S-01 – S-09); rozszerzenie prd-v2 (S-10 – S-15: opis skanu, spis miejscowości) nie zostało rozpoczęte.

Stan istotny dla tej zmiany:
- **Widok drzewa (S-09)** to arkusz rodziny: osoba w centrum, rodzice, dziadkowie, małżonek, dzieci. Brakująca osoba to przerywana karta „nieznany” z samą etykietą — bez żadnej akcji. Drzewo służy tylko do oglądania i przechodzenia do osób.
- **Dodawanie i edycja** osoby odbywa się na osobnych stronach (`persons/create`, `persons/edit`); część akcji (małżeństwo, wybór krewnego, wybór skanu, usunięcia) ma już jednorazowe okna dialogowe.
- **Skany** to pliki aktów metrykalnych (nazwa oryginału, rozmiar, miniatura, powiększenie), które potwierdzają urodzenie, małżeństwo i zgon; jeden skan może potwierdzać wiele wydarzeń. Nie ma innego rodzaju dowodu.

## Problem Statement & Motivation

Wprowadzanie prawdziwego drzewa i codzienne poprawki pokazały trzy luki:

1. **Drzewo tylko do oglądania.** Widząc na drzewie „nieznany ojciec”, autor musi wyjść na stronę dodawania osoby, a potem wrócić i połączyć relację — gubi kontekst. Z drzewa nie da się utworzyć brakującego rodzica, dodać dzieci ani małżonka (brak małżonka ma być widoczny jako pusty slot).
2. **Formularze na osobnych stronach.** Każde dodanie i każda drobna poprawka (data, miejsce) to przejście na osobną stronę i z powrotem. Autor chce dodawania osoby i dodawania/edycji wszystkiego w oknach modalnych.
3. **„Skan” to za wąskie pojęcie.** Autor ma zdjęcia nagrobków z datami, a aplikacja przyjmuje tylko skany aktów, więc nie ma gdzie podłączyć ich jako dowodu. Skan ma stać się **źródłem** z rodzajem (np. akt metrykalny, zdjęcie nagrobka), traktowanym jako źródło faktów.

Dlaczego teraz: ból pojawia się we wszystkich trzech chwilach — przy wpisywaniu prawdziwego drzewa z widoku drzewa, przy każdej drobnej poprawce oraz po powrocie z cmentarza ze zdjęciami nagrobków. Obecne obejście: osobne strony i powrót do podglądu osoby; zdjęć nagrobków nie ma gdzie podłączyć.

Zmiana wchłania S-10 z prd-v2: opis (nazwa, typ, rok, pochodzenie, numer i notatka wpisu) projektujemy od razu dla źródła, nie dla skanu; S-10/S-11 do przeplanowania. Spis miejscowości (S-12 – S-15) bez zmian. (Poprawka 2026-09-30: spis miejscowości odłożony poza tę wersję.)

## User & Persona

Bez zmian: jedynym realnym użytkownikiem jest autor aplikacji, genealog prowadzący badania na własnych materiałach. Zmiana dotyczy chwil, gdy uzupełnia drzewo patrząc na nie, gdy poprawia drobne dane oraz gdy wraca z cmentarza ze zdjęciami nagrobków.

## Success Criteria

Przepływ zmiany (potwierdzony przez użytkownika, całość naraz — bez etapów):

1. Na drzewie klikam pusty slot „nieznany ojciec/matka” → modal „dodaj osobę” → zapis → osoba od razu na drzewie jako rodzic, bez opuszczania drzewa.
2. Przy osobie na drzewie dodaję dziecko → modal → dziecko na drzewie.
3. Brak małżonka widać jako pusty slot → klik → modal → małżonek na drzewie.
4. Dodaję i poprawiam osobę w modalu (nie na osobnej stronie).
5. W „Źródłach” (dawniej „Skany”) dodaję źródło w modalu: plik + rodzaj (akt metrykalny / zdjęcie nagrobka) + opis; potwierdzam nim fakt jak dziś skanem.

Z pustego slotu na drzewie powstaje zawsze **nowa** osoba; łączenie z osobą już istniejącą w drzewie zostaje na podglądzie osoby jak dziś. Zakres modali: dodawanie/edycja osoby oraz dodawanie/edycja źródła; fakty (urodzenie, zgon, ślub) i relacje zostają w obecnej formie.

### Primary
- Powyższy przepływ 1–5 działa na prawdziwych danych: brakującego rodzica, dziecko i małżonka tworzę z drzewa w modalu i widzę ich od razu na drzewie; osobę dodaję i poprawiam w modalu; zdjęcie nagrobka dodaję jako źródło rodzaju „nagrobek” i potwierdzam nim fakt.

### Secondary
- Mniej kliknięć przy wpisywaniu: rodzinę osoby (rodzice, małżonek, dzieci) uzupełniam w całości bez opuszczania drzewa.

### Guardrails
- Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” przechodzą 1:1 na źródła.
- Wgrane pliki, miniatury i powiększenie działają; żaden plik nie ginie.
- Poprawność relacji: z drzewa nie da się dodać trzeciego rodzica, drugiego ojca ani uczynić kogoś własnym przodkiem.
- Usunięcie źródła dalej pokazuje fakty, które stracą potwierdzenie, i wymaga jawnego potwierdzenia.

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

## Scope of Change

### Drzewo
- [new] FR-001: Użytkownik może z pustego slotu rodzica na drzewie utworzyć w modalu nową osobę, która od razu staje się rodzicem tej osoby i pojawia się na drzewie; slot ustawia płeć (ojciec → mężczyzna, matka → kobieta, „nieznany rodzic” → wybór w modalu), a płeć jest w modalu zablokowana. Priority: must-have.
  > Socrates: Counter-argument considered: "ojciec często już jest w drzewie (wpisany z innej gałęzi) — »zawsze nowa osoba« produkuje duplikaty." Resolution: kept; ryzyko duplikatów zaakceptowane — łączenie z istniejącą osobą zostaje na podglądzie osoby, duplikaty poprawiane ręcznie.
- [new] FR-002: Użytkownik może z drzewa dodać w modalu nowe dziecko pary pokazanej na drzewie (z małżonkiem — oboje rodzice, bez małżonka — tylko ta osoba); modal pokazuje, do kogo trafi dziecko, a dziecko od razu pojawia się na drzewie. Priority: must-have.
  > Socrates: Counter-argument considered: "osoba z kilkoma małżonkami (przełącznik związku) — dziecko może trafić do złej pary." Resolution: changed — dziecko trafia do pary aktualnie wybranej przełącznikiem związku, a modal jawnie pokazuje obojga rodziców.
- [new] FR-003: Użytkownik widzi pusty slot małżonka przy osobie w centrum drzewa, gdy nie ma ona małżonka, i może z niego utworzyć w modalu nowego małżonka, widocznego od razu na drzewie. Priority: must-have.
  > Socrates: Counter-argument considered: "pusty slot małżonka przy każdym dziecku i młodej osobie zaśmieca drzewo." Resolution: changed — pusty slot tylko przy osobie w centrum; żeby dodać małżonka komuś innemu, przestawiam go na środek.

### Osoba
- [modified] FR-004: Użytkownik może dodać i poprawić osobę w modalu; strony `persons/create` i `persons/edit` znikają, a na wąskim ekranie modal zajmuje cały ekran i się przewija. Priority: must-have. (Było: dodawanie i edycja osoby na osobnych stronach.)
  > Socrates: Counter-argument considered: "formularz osoby jest długi (daty częściowe, miejsca) — w modalu na telefonie nieczytelny." Resolution: changed — pełnoekranowy, przewijany modal na wąskim ekranie; na komputerze zwykły modal.

### Źródła
- [modified] FR-005: „Skany” stają się „Źródłami”; każde źródło ma rodzaj: akt metrykalny, zdjęcie nagrobka lub inne. Istniejące skany stają się źródłami rodzaju „akt metrykalny”. Priority: must-have. (Było: skany — wyłącznie pliki aktów metrykalnych.)
  > Socrates: Counter-argument considered: "istniejące skany dostaną rodzaj automatycznie, a wśród nich mogą już być zdjęcia nagrobków." Resolution: changed — wszystkie istniejące skany → „akt metrykalny”; błędne rodzaje poprawiam ręcznie przez edycję (FR-007).
- [new] FR-006: Użytkownik może dodać źródło w modalu: plik i rodzaj (wymagane) oraz opcjonalny opis — każde źródło: nazwa, rok, pochodzenie, notatka; akt metrykalny dodatkowo typ (urodzenia / małżeństwa / zgonu / inny) i numer wpisu. Priority: must-have.
  > Socrates: Counter-argument considered: "po cmentarzu mam dziesiątki zdjęć — opis przy każdym pliku z osobna spowalnia wrzucanie." Resolution: changed — wymagane tylko plik i rodzaj; opis uzupełniany później w edycji. Jedno źródło = jeden plik.
- [new] FR-007: Użytkownik może poprawić rodzaj i opis źródła w modalu; zmiana rodzaju z aktu metrykalnego na inny pokazuje, które pola (typ, numer wpisu) znikną, i wymaga potwierdzenia. Priority: must-have.
  > Socrates: Counter-argument considered: "zmiana rodzaju z aktu na nagrobek kasuje typ i numer wpisu bez ostrzeżenia." Resolution: changed — ostrzeżenie z listą znikających pól i potwierdzenie przed zapisem.
- [modified] FR-008: Użytkownik może potwierdzić urodzenie, małżeństwo i zgon źródłem każdego rodzaju; stan pozostaje „potwierdzone”, a przy fakcie widać rodzaj źródła (np. „potwierdzone: nagrobek”). Priority: must-have. (Było: potwierdzać mógł tylko skan aktu metrykalnego.)
  > Socrates: Counter-argument considered: "nagrobek nie jest dowodem na równi z aktem — ten sam stan »potwierdzone« zawyża pewność." Resolution: changed — jeden stan „potwierdzone”, ale rodzaj źródła widoczny przy fakcie; o wadze dowodu decyduje użytkownik.

### Zachowane
- [preserved] FR-009: Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” przechodzą 1:1 na źródła; przy wyborze źródła dla faktu najpierw widać akty metrykalne o typie pasującym do faktu, potem resztę. Priority: must-have.
  > Socrates: Counter-argument considered: "wybór źródła przy fakcie pokaże też nagrobki i »inne« — trudniej znaleźć właściwy akt." Resolution: changed — pasujące akty najpierw (wchłania v2:FR-015 / S-11 z prd-v2).
- [preserved] FR-010: Wgrane pliki, miniatury i powiększenie działają dla każdego źródła; żaden plik nie ginie. Priority: must-have.
  > Socrates: Counter-argument considered: "zakładki i linki do /scans przestaną działać po zmianie na źródła." Resolution: kept; stare adresy `/scans` mogą przestać działać — jedynym użytkownikiem jest autor, zakładki poprawi sam.
- [preserved] FR-011: Usunięcie źródła z powiązaniami pokazuje fakty, które stracą potwierdzenie, i wymaga jawnego potwierdzenia; źródło bez powiązań usuwa się po prostym potwierdzeniu. Priority: must-have.
  > Socrates: Counter-argument considered: "źródło wgrane przez pomyłkę, bez powiązań — dialog skutków to zbędny krok." Resolution: changed — bez powiązań krótkie „Usunąć?”, z powiązaniami lista faktów jak dziś.
- [preserved] FR-012: Relacje pozostają poprawne także przy tworzeniu z drzewa: maks. dwoje rodziców, nikt nie jest własnym przodkiem. Priority: must-have.
  > Socrates: Counter-argument considered: "poprawność to też płeć — osoba utworzona w slocie »ojciec« musi być mężczyzną, inaczej drzewo kłamie." Resolution: changed — slot ustawia i blokuje płeć (zapisane w FR-001).

### Wchłonięte z prd-v2
- [modified] v2:FR-001 – v2:FR-005 i v2:FR-014 (S-10, opis skanu) — opis dotyczy teraz źródła (FR-006, FR-007).
- [modified] v2:FR-015 (S-11, skany o pasującym typie najpierw) — realizowane przez FR-009.

## Constraints & Compatibility

- **Kompatybilność wsteczna:** stare adresy `/scans` mogą przestać działać (jedynym użytkownikiem jest autor). Strony `persons/create` i `persons/edit` znikają.
- **Dane istniejące:** wszystkie istniejące skany stają się źródłami rodzaju „akt metrykalny”; pliki, miniatury i powiązania z wydarzeniami przechodzą 1:1; stan „potwierdzone/niepotwierdzone” każdego faktu jest taki sam przed i po zmianie. Opis źródła startuje pusty poza nazwą oryginału pliku.
- **Plan wycofania:** backupy danych i plików (F-01) wykonane tuż przed wdrożeniem.
- **Zachowane:** potwierdzenia faktów 1:1; pliki, miniatury i powiększenie; usuwanie źródła z listą faktów tracących potwierdzenie; ograniczenia relacji; prywatność drzewa (cudzy rekord = 404).
- **prd-v2:** spis miejscowości (S-12 – S-15) jest odłożony poza tę wersję — ta zmiana ma pierwszeństwo. (Poprawka 2026-09-30, decyzja użytkownika; było: bez zmian, ale przy braku czasu przesuwa się za termin 2026-11-04.)
- **Wymagania niefunkcjonalne:** brak nowych — istniejące (prywatność danych drzewa, czytelność powiększenia skanu) obejmują źródła każdego rodzaju.

## Business Logic Changes

Fakt osoby jest „potwierdzony”, gdy jest powiązany z co najmniej jednym źródłem dowolnego rodzaju, a przy fakcie zawsze widać rodzaj źródła, które go potwierdza.

Dotychczasowa reguła: fakt (urodzenie, małżeństwo, zgon) jest „potwierdzony”, gdy ma co najmniej jeden powiązany skan aktu metrykalnego. Zmiana: potwierdzać może źródło każdego rodzaju (akt metrykalny, zdjęcie nagrobka, inne); stan pozostaje jeden, a o wadze dowodu decyduje użytkownik, widząc rodzaj źródła przy fakcie. Jedno źródło nadal może potwierdzać wiele wydarzeń, a małżeństwo nadal jest jednym wydarzeniem obojga małżonków.

Nowe reguły drzewa: pusty slot wyznacza rolę i płeć nowej osoby — slot „nieznany ojciec” tworzy ojca-mężczyznę, „nieznana matka” matkę-kobietę, „nieznany rodzic” rodzica o płci wybranej przez użytkownika. Dziecko utworzone z drzewa należy do pary aktualnie pokazanej na drzewie. Pusty slot małżonka istnieje tylko przy osobie w centrum drzewa. Tworzenie z drzewa podlega tym samym ograniczeniom relacji co dziś (maks. dwoje rodziców, nikt nie jest własnym przodkiem).

## Access Control Changes

No access control changes — current model preserved: otwarta rejestracja, jedno drzewo na konto, każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnych danych, bez ról i współdzielenia; cudzy rekord to 404.

Nowe dane i akcje wchodzą pod tę samą zasadę: źródła (w tym zdjęcia nagrobków) oraz dodawanie/edycja z drzewa działają tylko na własnym drzewie.

## Non-Goals

- **Brak modali dla faktów i relacji** — urodzenie, zgon, ślub i relacje zostają w obecnej formie; modale obejmują tylko osobę i źródło.
- **Brak scalania duplikatów osób** — tworzenie z drzewa zawsze robi nową osobę (ryzyko zaakceptowane w FR-001); duplikaty poprawiane ręcznie.
- **Brak wgrywania wielu plików naraz** — jedno źródło = jeden plik, dodawany pojedynczo (wymagane tylko plik i rodzaj, FR-006).
- **Brak słabszego stanu potwierdzenia dla nagrobka** — jeden stan „potwierdzone” + widoczny rodzaj źródła (FR-008).
- **Brak przekierowań ze starych adresów `/scans`** — jedynym użytkownikiem jest autor (FR-010).

## Open Questions

1. **Przeplanowanie S-10 i S-11 w roadmapie** — ta zmiana wchłania opis skanu (S-10) i „pasujące najpierw” (S-11) z prd-v2; ich slice'y trzeba przepisać na źródła. Owner: `/10x-roadmap`. Block: no.
