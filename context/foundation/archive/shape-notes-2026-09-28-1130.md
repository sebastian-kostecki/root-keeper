---
project: "root-keeper"
context_type: brownfield
created: 2026-09-28
updated: 2026-09-28
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 4
  hard_deadline: 2026-11-04
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "change category"
      decision: "significant feature (scan description) + new module (places gazetteer) + data model change (one residence -> residence history)"
    - topic: "trigger / insight"
      decision: "entering real data: I keep going back to the scan to find 'my' entry; people moved and one residence field loses that"
    - topic: "gazetteer scope"
      decision: "every place in the app points to the gazetteer (residence, birth, marriage, death)"
    - topic: "existing free-text places"
      decision: "may be lost — user will re-enter them"
    - topic: "timing vs milestone M-1"
      decision: "in parallel with S-09; if time runs out, S-09 and the 2026-11-04 MVP deadline win"
    - topic: "primary persona"
      decision: "unchanged — the author (assumption stated, not contested)"
    - topic: "auth / roles"
      decision: "no change — open registration, own data only, no roles"
    - topic: "gazetteer ownership"
      decision: "private per tree, invisible to other users"
    - topic: "scope vs 3-week budget"
      decision: "whole change at once (no staging); longer timeline accepted — 4 weeks after hours, in parallel with S-09"
    - topic: "scan entry form"
      decision: "entry number + free-text description (who, when, what, how) — narrowed to one per scan in Socrates FR-004"
    - topic: "'oznaczenie miejsca'"
      decision: "means the scan's place of origin, not marking a region on the image"
    - topic: "scan type"
      decision: "one type per scan: birth / marriage / death / other"
    - topic: "residence UI"
      decision: "residences as chips (place only, no period); fact places (birth, marriage, death) as a dropdown"
    - topic: "gazetteer fields"
      decision: "name, whether it still exists, administrative unit / parish / note (no coordinates)"
    - topic: "gazetteer management"
      decision: "add-new from the picker + separate gazetteer page to edit/delete"
    - topic: "scan search"
      decision: "by scan name (nice-to-have)"
    - topic: "assumptions stated, not contested"
      decision: "scan entries are not linked to events — the whole scan stays the source"
    - topic: "scan origin (Socrates FR-003)"
      decision: "free text (parish, archive, signature), not a gazetteer place — exception to 'every place from the gazetteer'"
    - topic: "scan entries (Socrates FR-004)"
      decision: "one entry number + one note per scan, no list of entries"
    - topic: "Socrates round"
      decision: "FR-005 promoted to must-have; FR-008 suggests existing places; FR-009 usage list + confirmation; FR-014 only name required; FR-015 added (matching-type scans first)"
    - topic: "place location (FR-007, resolved in cross-check)"
      decision: "optional coordinates entered manually; no in-app map, no external map providers"
    - topic: "business logic"
      decision: "confirmation rule unchanged; new data is descriptive"
    - topic: "existing scans"
      decision: "name = original file name; other new fields empty"
    - topic: "NFR"
      decision: "no new NFRs; existing ones cover new data"
    - topic: "product framing"
      decision: "no change — web-app, small user base (assumption stated, not contested)"
    - topic: "deadline"
      decision: "hard 2026-11-04 (same as MVP), after hours; S-09 keeps priority"
  frs_drafted: 15
  quality_check_status: accepted
---

# root-keeper — shape notes (brownfield)

Poprzednia sesja (MVP, greenfield): `context/foundation/archive/shape-notes-2026-09-28-0612.md`.

## Seed idea

> Potrzebuje kilku zmian w aplikacji: Skany - moim zdaniem potrzebują nazwy, typu, informacji ktore za sobą noszą, na ktory numer zwrocic uwage, moze jakie informacje mają. Zazwyczaj jeden skan zawiera kilka wpisów metrykalnych - mnie interesuje zwykle jeden - tam jest numer, jakies informacje - chcialbym to zapisać gdzies w rekordzie o skanie, może oznaczenie miejsca; po drugie na karcie osoby chcialbym moc jako miejsce zamieszkania kilka lokalizacji, zeby byla zapisana niejako historia gdzei ta osoba była, moze dobrym pomyslem byloby zrobic jakis spis roznych miejscowosci z dodatkowymi informacjami (np. czy miejscowosc jeszcze istenieje, czy juz nie istnieje, jakie dane lokalizacyjne);

## Current System

root-keeper — prywatne drzewo genealogiczne (Laravel + Inertia/React), jedno drzewo na konto, otwarta rejestracja, każdy widzi tylko swoje dane. Fakty osoby (urodzenie, małżeństwo, zgon) są potwierdzane skanami aktów metrykalnych; jeden skan może potwierdzać wiele wydarzeń, małżeństwo jest jednym wydarzeniem obojga małżonków. Kamień milowy M-1 (MVP) jest otwarty — zostało S-09 (graficzny widok drzewa), termin 2026-11-04.

Stan istotny dla tej zmiany:
- Skan to sam plik (nazwa oryginału, rozmiar, miniatura, wymiary) bez żadnego opisu — świadoma decyzja MVP (FR-013, Non-Goals: „Brak opisu skanów: skan sam jest aktem”).
- Miejsce zamieszkania to jedno pole tekstowe osoby; miejsca urodzenia, ślubu i zgonu to wolny tekst na wydarzeniu.
- Nie ma spisu miejscowości.

Użytkownicy: autor (genealog pracujący na własnych materiałach).

## Vision & Problem Statement

Wprowadzanie prawdziwego drzewa pokazało dwie luki:

1. **Skan bez opisu.** Jeden skan zwykle zawiera kilka wpisów metrykalnych, a autora interesuje zazwyczaj jeden. Nie ma gdzie zapisać, który to wpis (numer), jakie informacje niesie, jakiego typu jest skan ani oznaczyć miejsca wpisu na skanie — więc za każdym razem autor wraca do skanu i szuka „swojego” wpisu od nowa. Skan potrzebuje nazwy, typu i informacji, które niesie.
2. **Jedno miejsce zamieszkania.** Osoby się przeprowadzały, a jedno pole gubi historię, gdzie mieszkały. Autor chce móc zapisać kilka miejsc zamieszkania i mieć spis miejscowości z dodatkowymi informacjami (np. czy miejscowość jeszcze istnieje, dane lokalizacyjne), używany wszędzie, gdzie aplikacja zapisuje miejsce.

Zmiana odwraca decyzję MVP „skan sam jest aktem, bez opisu”. Idzie równolegle z S-09; przy braku czasu pierwszeństwo mają S-09 i termin MVP 2026-11-04.

## User & Persona

Bez zmian: jedynym realnym użytkownikiem jest autor aplikacji, genealog prowadzący badania na własnych materiałach. Zmiana dotyczy chwil, gdy wraca do skanu, żeby odnaleźć wpis dotyczący konkretnej osoby, i gdy zapisuje, gdzie osoba mieszkała w różnych okresach życia.

## Access Control

No changes planned — current model preserved: otwarta rejestracja, każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnych danych, bez ról i współdzielenia; niezalogowany nie widzi żadnych danych.

Nowe dane wchodzą pod tę samą zasadę: opis skanu, historia zamieszkania i spis miejscowości należą do drzewa użytkownika. Spis miejscowości jest prywatny dla każdego drzewa i niewidoczny dla innych użytkowników.

## Success Criteria

Przepływ zmiany (w słowach użytkownika):

1. Dodaję skan: załączam plik, nadaję mu nazwę (np. kogo dotyczy) — żeby identyfikować skany później, np. przy przeszukiwaniu.
2. Opisuję jeden pojedynczy wpis ze skanu (albo więcej, jak potrzebuję): kto, kiedy, co i jak zrobił.
3. Oznaczam rok oraz miejsce, skąd skan pochodzi.
4. Na karcie osoby dodaję kolejne miejsce zamieszkania i widzę miejsca jako chipy w polu zamieszkania.
5. Przy faktach wybieram miejscowość z listy.

Doprecyzowane w rundzie Sokratesa: zamiast wielu wpisów — jeden numer wpisu + jedna notatka na skan; pochodzenie skanu to wolny tekst (parafia, archiwum, sygnatura), nie miejscowość ze spisu.

### Primary
- Powyższy przepływ działa na prawdziwych danych: skan ma nazwę, typ, rok, pochodzenie oraz numer i notatkę interesującego mnie wpisu, a odnajduję go później bez przeszukiwania skanu od nowa; osoba ma historię zamieszkania z kilku miejscowości, a fakty wskazują miejscowości ze spisu.

### Secondary
- Brak (użytkownik nie wskazał).

### Guardrails
- Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” działają jak dziś.
- Opisy skanów, historia zamieszkania i spis miejscowości są niewidoczne dla innych użytkowników i niezalogowanych.

## Timeline acknowledgment

Acknowledged on 2026-09-28: 4-week change (after hours, in parallel with S-09) requires sustained dedication; user accepted. Przy braku czasu pierwszeństwo mają S-09 i termin MVP 2026-11-04.

Termin zmiany: twardo 2026-11-04 (ten sam co MVP), po godzinach. Do terminu zostaje ok. 5 tygodni na S-09 i tę zmianę razem.

## User Stories

### US-01: Odnalezienie „mojego” wpisu na skanie

- **Given** zalogowany użytkownik ze skanem, któremu nadał nazwę, typ, rok i pochodzenie oraz zapisał numer interesującego go wpisu (np. nr 47) z notatką
- **When** odnajduje skan po nazwie na liście skanów i otwiera go
- **Then** widzi numer wpisu 47 i notatkę (kto, kiedy, co i jak zrobił) obok skanu i nie musi przeszukiwać skanu od nowa

Wcześniej: skan był samym plikiem, bez nazwy i opisu.

### US-02: Historia zamieszkania i miejsca faktów ze spisu

- **Given** zalogowany użytkownik z osobą w drzewie
- **When** na karcie osoby dodaje kolejne miejsca zamieszkania (wybierając miejscowość ze spisu albo dodając brakującą) i wybiera miejsce urodzenia z listy rozwijanej
- **Then** widzi miejsca zamieszkania jako chipy, a miejsce urodzenia jako miejscowość ze spisu

Wcześniej: jedno pole tekstowe zamieszkania, miejsca faktów jako wolny tekst.

## Functional Requirements

### Skany
- FR-001: Użytkownik może nadać skanowi nazwę — dowolną etykietę, wymaganą przy dodawaniu. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "nazwa »kogo dotyczy« dubluje opisy wpisów — skan z kilkoma wpisami dotyczy wielu osób." Resolution: changed — nazwa jest dowolną etykietą (np. „Urodzenia Lipnica 1878 s. 12”), bez sugestii „kogo dotyczy”; osoby opisuje notatka.
- FR-002: Użytkownik może oznaczyć typ skanu: urodzenia, małżeństwa, zgonu lub inny. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "księga mieszana ma na jednej stronie wpisy różnych typów — jeden typ skanu wtedy kłamie." Resolution: kept; mieszane księgi to wyjątek, „inny” wystarcza.
- FR-003: Użytkownik może oznaczyć rok skanu i jego pochodzenie wolnym tekstem (parafia, archiwum, sygnatura). Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "skan pochodzi z parafii lub archiwum, nie z miejscowości — spis miejscowości nie pasuje." Resolution: changed — pochodzenie jest wolnym tekstem, nie miejscowością ze spisu (było: „miejsce pochodzenia — miejscowość ze spisu”).
- FR-004: Użytkownik może zapisać przy skanie numer interesującego go wpisu i jedną notatkę tekstową (kto, kiedy, co i jak zrobił) oraz je poprawić. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "kilka wpisów na skan to więcej pracy; jedna notatka zrobi to samo." Resolution: changed — jedna notatka + numer wpisu na skan, bez listy wpisów (było: „opis jednego lub kilku wpisów”).
- FR-005: Użytkownik może poprawić nazwę, typ, rok i pochodzenie istniejącego skanu. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "literówka w nazwie przy dodawaniu zostaje na zawsze i psuje odnajdywanie skanu." Resolution: changed — awans z nice-to-have na must-have (przy okazji istniejące skany można uzupełnić).
- FR-006: Użytkownik może wyszukać skan po nazwie na liście skanów. Priority: nice-to-have. Change: new
  > Socrates: No counter-argument; it stands as written.

### Miejscowości
- FR-007: Użytkownik może prowadzić spis miejscowości swojego drzewa: nazwa, czy miejscowość nadal istnieje, jednostka / parafia / notatka oraz opcjonalne współrzędne geograficzne wpisywane ręcznie. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "bez danych lokalizacyjnych (wymienionych w pierwszym opisie) spis traci sens — nie wiadomo, gdzie miejscowość leżała." Resolution: changed (rozstrzygnięte w fazie 7) — dodane opcjonalne współrzędne wpisywane ręcznie, bez mapy w aplikacji i bez usług zewnętrznych.
- FR-008: Użytkownik może dodać brakującą miejscowość do spisu bezpośrednio z listy wyboru; lista przy wpisywaniu podpowiada istniejące miejscowości (z jednostką), a dodanie nowej jest proponowane, gdy nic nie pasuje. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "szybkie dodawanie z listy produkuje literówki i duplikaty tej samej miejscowości." Resolution: changed — podpowiedzi istniejących miejscowości przy wpisywaniu, „dodaj nową” dopiero gdy nic nie pasuje.
- FR-009: Użytkownik może edytować i usuwać miejscowości na osobnej stronie spisu; przed usunięciem widzi osoby i fakty, które stracą miejsce, i potwierdza usunięcie. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "usunięcie używanej miejscowości po cichu czyści zamieszkanie i miejsca faktów." Resolution: changed — lista użyć + jawne potwierdzenie przed usunięciem (spójnie z usuwaniem skanu, FR-015 MVP).
- FR-010: Użytkownik może dodać osobie kilka miejsc zamieszkania ze spisu, widocznych jako chipy, i usunąć każde z nich. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "zamieszkanie bez źródła — nie wiadomo, z którego aktu wiem o zamieszkaniu." Resolution: kept; Non-Goal MVP „brak źródeł dla zamieszkania” obowiązuje dalej.
- FR-011: Użytkownik może wybrać miejsce urodzenia, ślubu i zgonu z listy rozwijanej miejscowości ze spisu. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "ślub odbywa się w parafii, nie w miejscowości." Resolution: kept; parafię zapisuję w polu „jednostka / parafia” miejscowości.

### Zachowane
- FR-012: Użytkownik może powiązać skan z urodzeniem, małżeństwem lub zgonem, a stan „potwierdzone/niepotwierdzone” działa jak dziś. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "typ skanu może kłócić się z wydarzeniem (skan »zgonu« przy urodzeniu)." Resolution: kept (wiązanie i stan bez zmian) + FR-015: skany o pasującym typie proponowane jako pierwsze.
- FR-013: Użytkownik widzi na podglądzie osoby miejsce faktu wraz ze wskazaniem aktu. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "zmiana nazwy w spisie zmienia miejsce historycznego faktu u wszystkich osób, a akt podaje dawną nazwę." Resolution: kept; edycja miejscowości to poprawka tej samej miejscowości, dawne nazwy w notatce miejscowości.
- FR-014: Użytkownik może wgrać, podejrzeć i usunąć skan (z listą faktów tracących źródło) jak dziś. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "obowiązkowe nowe pola spowolnią wgrywanie wielu skanów." Resolution: changed — przy wgrywaniu wymagana jest tylko nazwa (FR-001); typ, rok, pochodzenie, numer wpisu i notatka są opcjonalne.

### Wynik rundy Sokratesa
- FR-015: Przy wiązaniu skanu z wydarzeniem użytkownik widzi najpierw skany o typie pasującym do wydarzenia; pozostałe nadal może wybrać. Priority: must-have. Change: modified
  > Socrates: Dodane jako rozstrzygnięcie kontrargumentu do FR-012 (niezgodność typu skanu z wydarzeniem).

## Business Logic

Reguła potwierdzania bez zmian: wydarzenie osoby (urodzenie, małżeństwo, zgon) jest potwierdzone wtedy i tylko wtedy, gdy powiązany jest z nim co najmniej jeden skan aktu metrykalnego.

Nowe dane — nazwa, typ, rok, pochodzenie, numer wpisu i notatka skanu, spis miejscowości, historia zamieszkania — są opisowe i nie wpływają na stan potwierdzenia. Źródłem faktu nadal jest cały skan; numer wpisu i notatka nie są powiązane z konkretnym wydarzeniem.

Drobne reguły wynikające ze zmiany:
- Przy wiązaniu skanu z wydarzeniem skany o typie pasującym do wydarzenia są proponowane jako pierwsze (FR-015); typ nie blokuje wiązania.
- Usunięcie miejscowości odpina ją od wszystkich osób i faktów, które jej używały — dopiero po pokazaniu listy użyć i jawnym potwierdzeniu (FR-009).
- Edycja miejscowości jest poprawką tej samej miejscowości i zmienia miejsce wszędzie, gdzie jest użyta.

## Constraints & Preserved Behavior

Preserved (nie może się zepsuć):
- Istniejące powiązania skan–wydarzenie i stan „potwierdzone/niepotwierdzone” (FR-012).
- Wgrane skany, ich pliki i miniatury oraz podgląd z powiększeniem (FR-014).
- Miejsce faktu widoczne na podglądzie osoby ze wskazaniem aktu — po zmianie jako miejscowość ze spisu (FR-013).
- Usuwanie skanu z listą faktów tracących źródło (S-08, FR-014).
- Izolacja danych: nowe dane widoczne wyłącznie dla właściciela drzewa.

Migracja danych:
- Istniejące skany dostają nazwę równą nazwie oryginalnego pliku; typ, rok, pochodzenie, numer wpisu i notatka zostają puste do uzupełnienia (FR-005).
- Istniejące miejsca zapisane wolnym tekstem (zamieszkanie osoby, miejsca urodzenia, ślubu i zgonu) mogą zostać utracone — użytkownik wprowadzi je ponownie ze spisu. Świadoma decyzja użytkownika.

Zgodność wsteczna / integracje: brak zewnętrznych integracji ani konsumentów API; aplikacja ma jednego realnego użytkownika.

## Non-Functional Requirements

Brak nowych. Obowiązują dotychczasowe i obejmują nowe dane (opisy skanów, spis miejscowości, historia zamieszkania):
- Żadne dane użytkownika nie są dostępne dla niezalogowanych ani dla innych użytkowników.
- Dane nie trafiają do podmiotów trzecich ani usług zewnętrznych, nad którymi właściciel instancji nie ma kontroli.
- Po awarii tracone są najwyżej dane z ostatnich 24 h.
- Aplikacja jest w pełni użyteczna w aktualnych przeglądarkach na komputerze.

Ograniczenia istniejącego systemu (faza 6): deploy ręczny (`scripts/deploy.sh` po merge'u do `main`); backupy danych i skanów z F-01 muszą objąć nowe dane; typ produktu (aplikacja webowa) i skala użytkowników bez zmian.

## Non-Goals

### Functional
- Brak zaznaczania obszaru wpisu na obrazie skanu: numer wpisu i notatka wystarczają (faza 4 — „oznaczenie miejsca” oznaczało pochodzenie skanu).
- Brak wyszukiwania skanów po treści notatki: wyszukiwanie tylko po nazwie (FR-006).
- Brak mapy w aplikacji i zewnętrznych dostawców map: lokalizacja miejscowości to ręcznie wpisane współrzędne (FR-007, faza 7).
- Brak przenoszenia istniejących miejsc zapisanych wolnym tekstem do spisu miejscowości: użytkownik wprowadzi je ponownie (Constraints & Preserved Behavior).
- Non-Goals z PRD MVP obowiązują dalej, w tym brak źródeł dla zamieszkania (potwierdzone w rundzie Sokratesa, FR-010) — z wyjątkiem „Brak opisu skanów”, który ta zmiana świadomie odwraca.

### Non-functional
- Brak nowych celów jakościowych — obowiązują NFR z PRD MVP.

## Open Questions

Brak otwartych pytań.

Rozstrzygnięte w fazie 7 (2026-09-28):
- Dane lokalizacyjne miejscowości (FR-007) → opcjonalne współrzędne wpisywane ręcznie; bez mapy w aplikacji i bez zewnętrznych dostawców map (zgodnie z NFR o podmiotach trzecich).
