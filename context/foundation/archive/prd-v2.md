---
project: "root-keeper"
version: 2
status: draft
created: 2026-09-28
context_type: brownfield
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 4
  hard_deadline: 2026-11-04
  after_hours_only: true
---

# root-keeper — opisy skanów i spis miejscowości (zmiana brownfield)

Poprzedni PRD (MVP, greenfield): `context/foundation/prd.md`. Notatki z kształtowania tej zmiany: `context/foundation/shape-notes.md`.

> **Poprawka 2026-09-30 (decyzja użytkownika):** spis miejscowości i historia zamieszkania — FR-007 – FR-011, US-02 (wycinki S-12 – S-15) — są odłożone poza tę wersję. Zamieszkanie i miejsca faktów zostają wolnym tekstem, więc istniejące miejsca nie są tracone. Część o skanach (FR-001 – FR-005, FR-012 – FR-015, US-01) obowiązuje i jest realizowana przez prd-v3 jako opis źródła. Treść o miejscowościach poniżej zostaje jako zapis zamierzenia na kolejną wersję.

## Current System Overview

root-keeper to prywatne drzewo genealogiczne: jedno drzewo na konto, otwarta rejestracja, każdy widzi tylko swoje dane.

- **Architektura i stack:** monolit Laravel (PHP) + Inertia/React z TypeScript (oficjalny React starter kit Laravela, shadcn/ui). Samodzielny hosting (Docker na VPS); skany na lokalnym wolumenie, udostępniane wyłącznie przez autoryzowane ścieżki. Deploy ręczny: `scripts/deploy.sh` po merge'u do `main`. Backupy danych i skanów z F-01. Szczegóły: `context/foundation/tech-stack.md`.
- **Użytkownicy:** autor (genealog pracujący na własnych materiałach); skala mała.
- **Funkcje dziś:** fakty osoby (urodzenie, małżeństwo, zgon) są potwierdzane skanami aktów metrykalnych; jeden skan może potwierdzać wiele wydarzeń, małżeństwo jest jednym wydarzeniem obojga małżonków. Kamień milowy M-1 (MVP) jest otwarty — zostało S-09 (graficzny widok drzewa), termin 2026-11-04.

Stan istotny dla tej zmiany:
- Skan to sam plik (nazwa oryginału, rozmiar, miniatura, wymiary) bez żadnego opisu — świadoma decyzja MVP (FR-013, Non-Goals: „Brak opisu skanów: skan sam jest aktem”).
- Miejsce zamieszkania to jedno pole tekstowe osoby; miejsca urodzenia, ślubu i zgonu to wolny tekst na wydarzeniu.
- Nie ma spisu miejscowości.

## Problem Statement & Motivation

Wprowadzanie prawdziwego drzewa pokazało dwie luki:

1. **Skan bez opisu.** Jeden skan zwykle zawiera kilka wpisów metrykalnych, a autora interesuje zazwyczaj jeden. Nie ma gdzie zapisać, który to wpis (numer), jakie informacje niesie, jakiego typu jest skan ani oznaczyć miejsca wpisu na skanie — więc za każdym razem autor wraca do skanu i szuka „swojego” wpisu od nowa. Skan potrzebuje nazwy, typu i informacji, które niesie.
2. **Jedno miejsce zamieszkania.** Osoby się przeprowadzały, a jedno pole gubi historię, gdzie mieszkały. Autor chce móc zapisać kilka miejsc zamieszkania i mieć spis miejscowości z dodatkowymi informacjami (np. czy miejscowość jeszcze istnieje, dane lokalizacyjne), używany wszędzie, gdzie aplikacja zapisuje miejsce.

**Dlaczego teraz:** luki wyszły przy wprowadzaniu prawdziwych danych — autor ciągle wraca do skanu, żeby znaleźć „swój” wpis, a osoby się przeprowadzały i jedno pole zamieszkania to gubi. Obecne obejście: każdorazowe przeszukiwanie skanu od nowa.

Zmiana odwraca decyzję MVP „skan sam jest aktem, bez opisu”. Idzie równolegle z S-09; przy braku czasu pierwszeństwo mają S-09 i termin MVP 2026-11-04.

## User & Persona

Bez zmian: jedynym realnym użytkownikiem jest autor aplikacji, genealog prowadzący badania na własnych materiałach. Zmiana dotyczy chwil, gdy wraca do skanu, żeby odnaleźć wpis dotyczący konkretnej osoby, i gdy zapisuje, gdzie osoba mieszkała w różnych okresach życia.

## Success Criteria

Przepływ zmiany (w słowach użytkownika):

1. Dodaję skan: załączam plik, nadaję mu nazwę (np. kogo dotyczy) — żeby identyfikować skany później, np. przy przeszukiwaniu.
2. Opisuję jeden pojedynczy wpis ze skanu (albo więcej, jak potrzebuję): kto, kiedy, co i jak zrobił.
3. Oznaczam rok oraz miejsce, skąd skan pochodzi.
4. Na karcie osoby dodaję kolejne miejsce zamieszkania i widzę miejsca jako chipy w polu zamieszkania.
5. Przy faktach wybieram miejscowość z listy.

Doprecyzowane w rundzie Sokratesa: zamiast wielu wpisów — jeden numer wpisu + jedna notatka na skan; pochodzenie skanu to wolny tekst (parafia, archiwum, sygnatura), nie miejscowość ze spisu.

### Primary
- Powyższy przepływ działa na prawdziwych danych: skan ma nazwę, typ, rok, pochodzenie oraz numer i notatkę interesującego mnie wpisu, a odnajduję go później bez przeszukiwania skanu od nowa; osoba ma historię zamieszkania z kilku miejscowości, a fakty wskazują miejscowości ze spisu. (Poprawka 2026-09-30: historia zamieszkania i miejscowości ze spisu — kroki 4–5 przepływu — odłożone poza tę wersję.)

### Secondary
- Brak (użytkownik nie wskazał).

### Guardrails
- Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” działają jak dziś.
- Opisy skanów, historia zamieszkania i spis miejscowości są niewidoczne dla innych użytkowników i niezalogowanych.

## User Stories

### US-01: Odnalezienie „mojego” wpisu na skanie

- **Given** zalogowany użytkownik ze skanem, któremu nadał nazwę, typ, rok i pochodzenie oraz zapisał numer interesującego go wpisu (np. nr 47) z notatką
- **When** odnajduje skan po nazwie na liście skanów i otwiera go
- **Then** widzi numer wpisu 47 i notatkę (kto, kiedy, co i jak zrobił) obok skanu i nie musi przeszukiwać skanu od nowa

Wcześniej: skan był samym plikiem, bez nazwy i opisu.

### US-02: Historia zamieszkania i miejsca faktów ze spisu

Odłożone poza tę wersję (poprawka 2026-09-30).

- **Given** zalogowany użytkownik z osobą w drzewie
- **When** na karcie osoby dodaje kolejne miejsca zamieszkania (wybierając miejscowość ze spisu albo dodając brakującą) i wybiera miejsce urodzenia z listy rozwijanej
- **Then** widzi miejsca zamieszkania jako chipy, a miejsce urodzenia jako miejscowość ze spisu

Wcześniej: jedno pole tekstowe zamieszkania, miejsca faktów jako wolny tekst.

## Scope of Change

### Skany
- [modified] FR-001: Użytkownik może nadać skanowi nazwę — dowolną etykietę, wymaganą przy dodawaniu. Priority: must-have. (Było: skan identyfikowany tylko nazwą oryginalnego pliku.)
  > Socrates: Counter-argument considered: "nazwa »kogo dotyczy« dubluje opisy wpisów — skan z kilkoma wpisami dotyczy wielu osób." Resolution: changed — nazwa jest dowolną etykietą (np. „Urodzenia Lipnica 1878 s. 12”), bez sugestii „kogo dotyczy”; osoby opisuje notatka.
- [new] FR-002: Użytkownik może oznaczyć typ skanu: urodzenia, małżeństwa, zgonu lub inny. Priority: must-have.
  > Socrates: Counter-argument considered: "księga mieszana ma na jednej stronie wpisy różnych typów — jeden typ skanu wtedy kłamie." Resolution: kept; mieszane księgi to wyjątek, „inny” wystarcza.
- [new] FR-003: Użytkownik może oznaczyć rok skanu i jego pochodzenie wolnym tekstem (parafia, archiwum, sygnatura). Priority: must-have.
  > Socrates: Counter-argument considered: "skan pochodzi z parafii lub archiwum, nie z miejscowości — spis miejscowości nie pasuje." Resolution: changed — pochodzenie jest wolnym tekstem, nie miejscowością ze spisu (było: „miejsce pochodzenia — miejscowość ze spisu”).
- [new] FR-004: Użytkownik może zapisać przy skanie numer interesującego go wpisu i jedną notatkę tekstową (kto, kiedy, co i jak zrobił) oraz je poprawić. Priority: must-have.
  > Socrates: Counter-argument considered: "kilka wpisów na skan to więcej pracy; jedna notatka zrobi to samo." Resolution: changed — jedna notatka + numer wpisu na skan, bez listy wpisów (było: „opis jednego lub kilku wpisów”).
- [new] FR-005: Użytkownik może poprawić nazwę, typ, rok i pochodzenie istniejącego skanu. Priority: must-have.
  > Socrates: Counter-argument considered: "literówka w nazwie przy dodawaniu zostaje na zawsze i psuje odnajdywanie skanu." Resolution: changed — awans z nice-to-have na must-have (przy okazji istniejące skany można uzupełnić).
- [new] FR-006: Użytkownik może wyszukać skan po nazwie na liście skanów. Priority: nice-to-have.
  > Socrates: No counter-argument; it stands as written.

### Miejscowości
Odłożone poza tę wersję (poprawka 2026-09-30): FR-007 – FR-011 nie wchodzą do kamienia M-1.
- [new] FR-007: Użytkownik może prowadzić spis miejscowości swojego drzewa: nazwa, czy miejscowość nadal istnieje, jednostka / parafia / notatka oraz opcjonalne współrzędne geograficzne wpisywane ręcznie. Priority: must-have.
  > Socrates: Counter-argument considered: "bez danych lokalizacyjnych (wymienionych w pierwszym opisie) spis traci sens — nie wiadomo, gdzie miejscowość leżała." Resolution: changed (rozstrzygnięte w fazie 7) — dodane opcjonalne współrzędne wpisywane ręcznie, bez mapy w aplikacji i bez usług zewnętrznych.
- [new] FR-008: Użytkownik może dodać brakującą miejscowość do spisu bezpośrednio z listy wyboru; lista przy wpisywaniu podpowiada istniejące miejscowości (z jednostką), a dodanie nowej jest proponowane, gdy nic nie pasuje. Priority: must-have.
  > Socrates: Counter-argument considered: "szybkie dodawanie z listy produkuje literówki i duplikaty tej samej miejscowości." Resolution: changed — podpowiedzi istniejących miejscowości przy wpisywaniu, „dodaj nową” dopiero gdy nic nie pasuje.
- [new] FR-009: Użytkownik może edytować i usuwać miejscowości na osobnej stronie spisu; przed usunięciem widzi osoby i fakty, które stracą miejsce, i potwierdza usunięcie. Priority: must-have.
  > Socrates: Counter-argument considered: "usunięcie używanej miejscowości po cichu czyści zamieszkanie i miejsca faktów." Resolution: changed — lista użyć + jawne potwierdzenie przed usunięciem (spójnie z usuwaniem skanu, FR-015 MVP).
- [modified] FR-010: Użytkownik może dodać osobie kilka miejsc zamieszkania ze spisu, widocznych jako chipy, i usunąć każde z nich. Priority: must-have. (Było: jedno pole tekstowe zamieszkania.)
  > Socrates: Counter-argument considered: "zamieszkanie bez źródła — nie wiadomo, z którego aktu wiem o zamieszkaniu." Resolution: kept; Non-Goal MVP „brak źródeł dla zamieszkania” obowiązuje dalej.
- [modified] FR-011: Użytkownik może wybrać miejsce urodzenia, ślubu i zgonu z listy rozwijanej miejscowości ze spisu. Priority: must-have. (Było: miejsca faktów jako wolny tekst.)
  > Socrates: Counter-argument considered: "ślub odbywa się w parafii, nie w miejscowości." Resolution: kept; parafię zapisuję w polu „jednostka / parafia” miejscowości.

### Zachowane
- [preserved] FR-012: Użytkownik może powiązać skan z urodzeniem, małżeństwem lub zgonem, a stan „potwierdzone/niepotwierdzone” działa jak dziś. Priority: must-have.
  > Socrates: Counter-argument considered: "typ skanu może kłócić się z wydarzeniem (skan »zgonu« przy urodzeniu)." Resolution: kept (wiązanie i stan bez zmian) + FR-015: skany o pasującym typie proponowane jako pierwsze.
- [preserved] FR-013: Użytkownik widzi na podglądzie osoby miejsce faktu wraz ze wskazaniem aktu. Priority: must-have.
  > Socrates: Counter-argument considered: "zmiana nazwy w spisie zmienia miejsce historycznego faktu u wszystkich osób, a akt podaje dawną nazwę." Resolution: kept; edycja miejscowości to poprawka tej samej miejscowości, dawne nazwy w notatce miejscowości.
- [preserved] FR-014: Użytkownik może wgrać, podejrzeć i usunąć skan (z listą faktów tracących źródło) jak dziś. Priority: must-have.
  > Socrates: Counter-argument considered: "obowiązkowe nowe pola spowolnią wgrywanie wielu skanów." Resolution: changed — przy wgrywaniu wymagana jest tylko nazwa (FR-001); typ, rok, pochodzenie, numer wpisu i notatka są opcjonalne.

### Wynik rundy Sokratesa
- [modified] FR-015: Przy wiązaniu skanu z wydarzeniem użytkownik widzi najpierw skany o typie pasującym do wydarzenia; pozostałe nadal może wybrać. Priority: must-have. (Było: skany nie miały typu.)
  > Socrates: Dodane jako rozstrzygnięcie kontrargumentu do FR-012 (niezgodność typu skanu z wydarzeniem).

## Constraints & Compatibility

Zachowane (nie może się zepsuć):
- Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” (FR-012).
- Wgrane skany, ich pliki i miniatury oraz podgląd z powiększeniem (FR-014).
- Miejsce faktu widoczne na podglądzie osoby ze wskazaniem aktu — po zmianie jako miejscowość ze spisu (FR-013).
- Usuwanie skanu z listą faktów tracących źródło (S-08, FR-014).
- Izolacja danych: nowe dane widoczne wyłącznie dla właściciela drzewa.

Istniejące dane:
- Istniejące skany dostają nazwę równą nazwie oryginalnego pliku; typ, rok, pochodzenie, numer wpisu i notatka zostają puste do uzupełnienia (FR-005).
- Istniejące miejsca zapisane wolnym tekstem (zamieszkanie osoby, miejsca urodzenia, ślubu i zgonu) mogą zostać utracone — użytkownik wprowadzi je ponownie ze spisu. Świadoma decyzja użytkownika. (Poprawka 2026-09-30: nie dotyczy tej wersji — spis miejscowości jest odłożony, wolne teksty zostają.)

Zgodność wsteczna / integracje: brak zewnętrznych integracji ani konsumentów API; aplikacja ma jednego realnego użytkownika.

Wymagania jakościowe — brak nowych. Obowiązują dotychczasowe i obejmują nowe dane (opisy skanów, spis miejscowości, historia zamieszkania):
- Żadne dane użytkownika nie są dostępne dla niezalogowanych ani dla innych użytkowników.
- Dane nie trafiają do podmiotów trzecich ani usług zewnętrznych, nad którymi właściciel instancji nie ma kontroli.
- Po awarii tracone są najwyżej dane z ostatnich 24 h — backupy muszą objąć nowe dane.
- Aplikacja jest w pełni użyteczna w aktualnych przeglądarkach na komputerze.

Harmonogram: termin twardo 2026-11-04 (ten sam co MVP), po godzinach, ok. 4 tygodnie pracy równolegle z S-09 (zaakceptowane 2026-09-28). Przy braku czasu pierwszeństwo mają S-09 i termin MVP.

## Business Logic Changes

Reguła potwierdzania bez zmian: wydarzenie osoby (urodzenie, małżeństwo, zgon) jest potwierdzone wtedy i tylko wtedy, gdy powiązany jest z nim co najmniej jeden skan aktu metrykalnego.

Nowe dane — nazwa, typ, rok, pochodzenie, numer wpisu i notatka skanu, spis miejscowości, historia zamieszkania — są opisowe i nie wpływają na stan potwierdzenia. Źródłem faktu nadal jest cały skan; numer wpisu i notatka nie są powiązane z konkretnym wydarzeniem.

Drobne reguły wynikające ze zmiany:
- Przy wiązaniu skanu z wydarzeniem skany o typie pasującym do wydarzenia są proponowane jako pierwsze (FR-015); typ nie blokuje wiązania.
- Usunięcie miejscowości odpina ją od wszystkich osób i faktów, które jej używały — dopiero po pokazaniu listy użyć i jawnym potwierdzeniu (FR-009).
- Edycja miejscowości jest poprawką tej samej miejscowości i zmienia miejsce wszędzie, gdzie jest użyta.

## Access Control Changes

No access control changes — current model preserved: otwarta rejestracja, każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnych danych, bez ról i współdzielenia; niezalogowany nie widzi żadnych danych.

Nowe dane wchodzą pod tę samą zasadę: opis skanu, historia zamieszkania i spis miejscowości należą do drzewa użytkownika. Spis miejscowości jest prywatny dla każdego drzewa i niewidoczny dla innych użytkowników.

## Non-Goals

### Functional
- Brak zaznaczania obszaru wpisu na obrazie skanu: numer wpisu i notatka wystarczają („oznaczenie miejsca” oznaczało pochodzenie skanu).
- Brak listy wielu wpisów na skan: jeden numer wpisu + jedna notatka (FR-004).
- Brak powiązania numeru wpisu i notatki z konkretnym wydarzeniem: źródłem faktu pozostaje cały skan.
- Brak wyszukiwania skanów po treści notatki: wyszukiwanie tylko po nazwie (FR-006).
- Brak mapy w aplikacji i zewnętrznych dostawców map: lokalizacja miejscowości to ręcznie wpisane współrzędne (FR-007).
- Brak okresów przy miejscach zamieszkania: chipy pokazują tylko miejscowość.
- Brak przenoszenia istniejących miejsc zapisanych wolnym tekstem do spisu miejscowości: użytkownik wprowadzi je ponownie (Constraints & Compatibility).
- Brak zmian w roli użytkownika, rejestracji i uprawnieniach.
- Non-Goals z PRD MVP obowiązują dalej, w tym brak źródeł dla zamieszkania (potwierdzone w rundzie Sokratesa, FR-010) — z wyjątkiem „Brak opisu skanów”, który ta zmiana świadomie odwraca.

### Non-functional
- Brak nowych celów jakościowych — obowiązują NFR z PRD MVP.

## Open Questions

Brak otwartych pytań.

Rozstrzygnięte w fazie 7 (2026-09-28):
- Dane lokalizacyjne miejscowości (FR-007) → opcjonalne współrzędne wpisywane ręcznie; bez mapy w aplikacji i bez zewnętrznych dostawców map (zgodnie z NFR o podmiotach trzecich).
