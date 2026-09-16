---
project: "root-keeper"
version: 1
status: draft
created: 2026-09-16
context_type: greenfield
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  mvp_weeks: 7
  hard_deadline: 2026-11-04
  after_hours_only: true
---

# root-keeper

## Vision & Problem Statement

Jako genealog prowadzący własne badania mam dane (notatki, skany, zdjęcia) rozproszone i niepowiązane ze sobą. Fakty, np. daty urodzenia, nie są powiązane ze źródłami, np. aktami metrykalnymi. Najbardziej odczuwam to, gdy muszę znaleźć konkretną osobę i gdy chcę potwierdzić fakt: każdorazowo ręcznie przeszukuję notatki i sprawdzam, czy fakt ma podstawę w źródle. Poprzednia aplikacja genealogiczna przestała być dostępna po wygaśnięciu licencji.

Wgląd: dane genealogiczne muszą pozostać w pełni moje, niezależne od licencji i abonamentu. Istniejące darmowe narzędzia samodzielnie hostowane są zbyt ciężkie jak na mój przepływ pracy (osoba → fakt → źródło → skan). Budowa własnej aplikacji jest też celem samym w sobie: to projekt rozwojowy.

## User & Persona

Jedyny użytkownik MVP to autor aplikacji, genealog prowadzący własne badania na podstawie własnych materiałów (notatki, zdjęcia, skany akt). Sięga po aplikację, gdy szuka konkretnej osoby i gdy weryfikuje, na jakim źródle opiera się fakt.

Główna persona MVP pozostaje bez zmian (autor aplikacji), ale aplikacja nie zakłada, że ma tylko jednego użytkownika.

## Success Criteria

### Primary
- Do 4.11.2026 moje realne drzewo (min. 3 pokolenia) z faktami opartymi na skanach akt jest w aplikacji; odnajduję w niej osoby i źródła faktów i przestaję do tego używać rozproszonych notatek.

### Secondary
- Łatwo zobaczyć, które fakty nie mają jeszcze źródła.

### Guardrails
- Żadne dane ani skany nie są dostępne dla niezalogowanych ani dla innych użytkowników.
- Wprowadzone dane i wgrane skany nie giną.

## User Stories

### US-01: Potwierdzenie faktu źródłem

- **Given** zalogowany użytkownik z osobą w drzewie i aktem urodzenia powiązanym z jej urodzeniem
- **When** odnajduje tę osobę (w drzewie lub na liście) i otwiera jej podgląd
- **Then** widzi datę i miejsce urodzenia wraz ze wskazaniem aktu, na którym się opiera, i może otworzyć jego skan

#### Acceptance Criteria
- Na podglądzie osoby fakt bez powiązanego aktu jest wyraźnie odróżniony od faktu potwierdzonego.
- Akt małżeństwa jest widoczny jako źródło u obojga małżonków, bez dodawania go dwa razy.
- Skan można powiększyć na tyle, by odczytać treść aktu.

## Functional Requirements

### Konto
- FR-001: Gość może zarejestrować konto. Priority: must-have
  > Socrates: Counter-argument considered: "rejestracja + izolacja danych to realny koszt z ~70 h, a jedynym faktycznym użytkownikiem MVP jest autor." Resolution: kept as must-have; koszt świadomie zaakceptowany.
- FR-002: Użytkownik może zalogować się. Priority: must-have
  > Socrates: No counter-argument; it stands as written.

### Drzewo
- FR-003: Użytkownik ma jedno drzewo, dostępne od razu po rejestracji. Priority: must-have
  > Socrates: Counter-argument considered: "skoro drzewo jest dokładnie jedno, jawne tworzenie drzewa to zbędny krok." Resolution: changed — drzewo powstaje automatycznie wraz z kontem (było: „Użytkownik może utworzyć jedno drzewo”).
- FR-004: Użytkownik może oglądać swoje drzewo w widoku graficznym i przechodzić z niego do osób. Priority: must-have
  > Socrates: No counter-argument; it stands as written.

### Osoby
- FR-005: Użytkownik może dodać osobę z danymi: imię, nazwisko, data i miejsce urodzenia, miejsce zamieszkania, wyznanie oraz (dla zmarłych) data i miejsce śmierci; daty mogą być pełne, niepełne (rok+miesiąc, sam rok) lub oznaczone jako przybliżone („około”). Priority: must-have
  > Socrates: Counter-argument considered: "dane genealogiczne często są przybliżone — pola dokładnej daty wymuszą zgadywanie." Resolution: changed — daty niepełne i przybliżone dopuszczone.
- FR-006: Użytkownik może edytować dane osoby. Priority: must-have
  > Socrates: Counter-argument considered: "po zmianie wartości faktu akt nadal jest pokazywany jako jego potwierdzenie — fakt rozjedzie się z aktem." Resolution: kept; ryzyko zaakceptowane w MVP, zgodność faktu z aktem jest odpowiedzialnością użytkownika. Otwarte pytanie na v2.
- FR-007: Użytkownik może usunąć osobę po jawnym potwierdzeniu. Priority: must-have
  > Socrates: Counter-argument considered: "bez potwierdzenia jedno kliknięcie niszczy pracę." Resolution: changed — usunięcie wymaga jawnego potwierdzenia; bez kosza/cofania w MVP.
- FR-008: Użytkownik może zobaczyć podgląd jednej osoby: jej dane, relacje i źródła faktów. Priority: must-have
  > Socrates: No counter-argument; it stands as written.
- FR-009: Użytkownik może wyszukać osobę na liście. Priority: must-have
  > Socrates: No counter-argument; it stands as written.

### Relacje
- FR-010: Użytkownik może połączyć dwie osoby relacją małżeństwa z datą i miejscem ślubu; jedna osoba może mieć kilka małżeństw. Priority: must-have
  > Socrates: Counter-argument considered: "powtórne małżeństwa po owdowieniu są w metrykach częste." Resolution: changed — osoba może mieć wiele relacji małżeństwa, każda z własną datą i miejscem ślubu.
- FR-011: Użytkownik może połączyć dwie osoby relacją rodzic–dziecko; dziecko może mieć tylko jednego znanego rodzica. Priority: must-have
  > Socrates: Counter-argument considered: "dzieci nieślubne, przybrane, nieznany ojciec — relacja bez rozróżnienia może zafałszować drzewo." Resolution: changed — dopuszczony jeden znany rodzic; brak rozróżnienia biologiczny/przybrany w MVP.
- FR-012: Użytkownik może usunąć relację po jawnym potwierdzeniu. Priority: must-have
  > Socrates: Counter-argument considered: "bez potwierdzenia łatwo zniszczyć strukturę drzewa." Resolution: changed — usunięcie relacji wymaga jawnego potwierdzenia (spójnie z FR-007).

### Akty metrykalne
- FR-013: Użytkownik może dodać skan aktu metrykalnego (bez dodatkowego opisu). Priority: must-have
  > Socrates: Counter-argument considered (user's own framing): "skan sam jest aktem metrykalnym — źródłem, z którego wyprowadzamy dane; osobny opis aktu jest zbędny w MVP." Resolution: changed — akt = skan; brak opisu skanów w MVP (było: „dodać akt metrykalny ze skanem”).
- FR-014: Użytkownik może powiązać skan aktu z wydarzeniem osoby (urodzenie, małżeństwo, zgon) i zmienić to powiązanie. Priority: must-have
  > Socrates: Counter-argument considered: "wyznania i miejsca zamieszkania nie da się potwierdzić aktem, choć akty je zawierają." Resolution: kept; w MVP źródła tylko dla urodzenia, małżeństwa i zgonu — wyznanie i miejsce zamieszkania bez źródeł do v2.
- FR-015: Użytkownik może usunąć skan aktu po potwierdzeniu, widząc wcześniej listę faktów, które stracą źródło. Priority: must-have
  > Socrates: Counter-argument considered: "usunięcie skanu po cichu zmienia powiązane fakty na »bez źródła«." Resolution: changed — przed usunięciem aplikacja pokazuje fakty, które stracą źródło, i wymaga potwierdzenia.
- FR-016: Użytkownik może podejrzeć skan aktu. Priority: must-have
  > Socrates: No counter-argument; it stands as written.

### Dane
- FR-018: Użytkownik może wyeksportować wszystkie swoje dane i skany w otwartym formacie. Priority: nice-to-have
  > Socrates: Counter-argument considered: "przy ~70 h nice-to-have realnie nie powstanie, a napięcie z wizją pełnej własności danych zostanie nierozwiązane." Resolution: kept as nice-to-have; ryzyko świadomie zaakceptowane (dodane w Fazie 7 przy rozstrzyganiu napięcia wizja ↔ brak eksportu).

Usunięte w rundzie Sokratesa:
- ~~FR-017: Użytkownik może zobaczyć listę faktów bez źródła. Priority: nice-to-have~~
  > Socrates: Counter-argument considered: "skoro fakt bez źródła jest wyraźnie odróżniony na podglądzie osoby (kryterium US-01), osobna lista jest zbędna." Resolution: removed from MVP; lista zbiorcza w v2 (kandydat do Non-Goals).

## Non-Functional Requirements

- Żadne dane ani skany użytkownika nie są dostępne dla niezalogowanych ani dla innych użytkowników.
- Dane i skany użytkownika nie trafiają do podmiotów trzecich ani usług zewnętrznych, nad którymi właściciel instancji nie ma kontroli.
- Po awarii tracone są najwyżej dane wprowadzone w ciągu ostatnich 24 h (wartość domyślna zaakceptowana przez użytkownika).
- Aplikacja jest w pełni użyteczna w aktualnych przeglądarkach na komputerze; urządzenia mobilne nie są celem MVP.

## Business Logic

Wydarzenie osoby (urodzenie, małżeństwo, zgon) jest potwierdzone wtedy i tylko wtedy, gdy powiązany jest z nim co najmniej jeden skan aktu metrykalnego; w przeciwnym razie jest niepotwierdzone.

Dane wejściowe to wydarzenia wprowadzone przez użytkownika oraz powiązania wydarzeń ze skanami aktów. Jeden skan może być źródłem dla wielu wydarzeń. Jedno wydarzenie ma zwykle jeden skan, ale może mieć kilka. Małżeństwo jest jednym wydarzeniem obojga małżonków, więc powiązany z nim akt potwierdza je u obu osób.

„Potwierdzone” znaczy „ma źródło”, a nie „jest prawdziwe”. Zgodność wpisanej wartości z treścią skanu jest odpowiedzialnością użytkownika. Usunięcie ostatniego powiązanego skanu zmienia wydarzenie na niepotwierdzone (FR-015).

Użytkownik widzi stan na podglądzie osoby: niepotwierdzone wydarzenie jest wyraźnie odróżnione od potwierdzonego. Drzewo w MVP nie pokazuje stanu.

## Access Control

Rejestracja jest otwarta: każdy może założyć konto i zalogować się. Każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnych danych (osób, faktów, źródeł, notatek, zdjęć, skanów) i nie widzi danych innych użytkowników. Nie ma ról ani współdzielenia. Niezalogowany użytkownik nie widzi żadnych danych; próba dostępu kieruje do logowania lub rejestracji.

## Non-Goals

### Functional
- Brak zdjęcia osoby (portretu): przesunięte do v2 przy cięciu zakresu w Fazie 3.
- Brak danych o pogrzebie (data, miejsce): przesunięte do v2 przy cięciu zakresu w Fazie 3.
- Brak zbiorczej listy faktów bez źródła: odróżnienie na podglądzie osoby wystarcza (runda Sokratesa, FR-017).
- Brak źródeł dla wyznania i miejsca zamieszkania: w MVP potwierdzane są tylko urodzenie, małżeństwo i zgon (FR-014).
- Brak wielu drzew na użytkownika: jedno drzewo, tworzone automatycznie z kontem (FR-003).
- Brak ról i współdzielenia danych między użytkownikami: każdy widzi wyłącznie własne dane (Access Control).
- Brak opisu skanów: skan sam jest aktem (FR-013).
- Brak kosza i cofania usunięć: w zamian jawne potwierdzenie (FR-007, FR-012, FR-015).
- Brak historii zmian: edycja nadpisuje poprzednią wartość.
- Brak pilnowania zgodności faktu z aktem po edycji: akt pozostaje powiązany z wydarzeniem, a zgodność wartości z treścią skanu jest odpowiedzialnością użytkownika (FR-006, Business Logic).
- Brak gwarancji eksportu danych w MVP: FR-018 jest nice-to-have; własność danych w MVP zapewnia to, że dane i skany nie trafiają do podmiotów trzecich (Non-Functional Requirements).
- Brak stanu „sprzeczny” dla faktu: „potwierdzone” znaczy tylko „ma źródło” (Business Logic).
- Brak rozróżnienia rodzica biologicznego i przybranego (FR-011).
- Brak oznaczeń stanu potwierdzenia w widoku drzewa: stan widoczny tylko na podglądzie osoby.
- Brak importu i eksportu GEDCOM: materiały są własne, wprowadzane ręcznie.
- Brak automatycznego odczytywania skanów (OCR/transkrypcja) i proponowania faktów ze skanu.
- Brak wyszukiwania wariantów pisowni imion i nazwisk.
- Brak integracji z zewnętrznymi serwisami i bazami genealogicznymi.
- Brak odzyskiwania hasła (resetu przez e-mail): utrata hasła wymaga kontaktu z właścicielem instancji.

### Non-functional
- Brak wsparcia urządzeń mobilnych: MVP celuje wyłącznie w przeglądarki na komputerze.
- Brak wielojęzyczności: interfejs wyłącznie po polsku.

## Open Questions

Brak otwartych pytań.

Rozstrzygnięte w Fazie 7: odzyskiwanie hasła → wykluczone (Non-Goals); eksport danych → FR-018 nice-to-have; język interfejsu → polski.

Rozstrzygnięte 2026-09-16 (po wygenerowaniu PRD):
- Rozjazd faktu i aktu po edycji (FR-006) → ryzyko zaakceptowane, pytanie zamknięte; zapisane w Non-Goals.
- Wizja „pełna własność danych” a eksport jako nice-to-have (FR-018) → eksport pozostaje nice-to-have, ryzyko zaakceptowane; zapisane w Non-Goals.
- Skala ruchu i wolumen danych → `qps: low` (autor i kilka osób); `data_volume: small` (do ~500 skanów w pierwszym roku).
