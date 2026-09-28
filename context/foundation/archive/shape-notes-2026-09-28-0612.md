---
project: "root-keeper"
context_type: greenfield
created: 2026-09-16
updated: 2026-09-16
product_type: web-app
target_scale:
  users: small
timeline_budget:
  mvp_weeks: 7
  hard_deadline: 2026-11-04
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "pain category"
      decision: "scattered/trapped data; unverifiable facts; search friction; dependency on third-party licence"
    - topic: "insight"
      decision: "full data ownership, independent of licences/subscriptions"
    - topic: "why build instead of adopting a free self-hosted tool"
      decision: "existing tools too heavy for the person → fact → source → scan flow; building is also a learning goal"
    - topic: "primary persona scope"
      decision: "single user — the author"
    - topic: "access model"
      decision: "open registration + login; each user sees only own data; no roles, no sharing"
    - topic: "MVP scope cut"
      decision: "person portrait photo and burial moved to v2; graphical tree and registration kept in MVP"
    - topic: "MVP timeline"
      decision: "7 weeks (full time until 2026-11-04 deadline); sustained-effort cost acknowledged"
    - topic: "one tree vs many"
      decision: "one tree per user in MVP"
    - topic: "relations"
      decision: "separate add/remove of relations between existing persons (marriage with wedding data; parent–child)"
    - topic: "record editing"
      decision: "records: add/remove + change links to events; no editing of the record itself"
    - topic: "what a source is (Socratic FR-013)"
      decision: "the scan itself is the metrical record — the source facts are derived from; no scan description in MVP"
    - topic: "dates"
      decision: "full, partial (year / year+month) and approximate ('about') dates allowed"
    - topic: "sourceable facts in MVP"
      decision: "only birth, marriage, death; religion and residence without sources until v2"
    - topic: "fact vs source (seed open question)"
      decision: "event is confirmed iff ≥1 record scan linked; 'confirmed' = has a source, not 'true'; one scan → many events; marriage is one event of both spouses"
    - topic: "where confirmation state is shown"
      decision: "person view only; tree shows no state in MVP"
    - topic: "scans local vs cloud (seed open question) — user-facing side"
      decision: "data and scans never leave the user's control / no third parties; storage location deferred to tech-stack selection"
    - topic: "devices"
      decision: "desktop browsers only in MVP"
    - topic: "product framing"
      decision: "web-app; small user scale (author + handful); hard deadline 2026-11-04; after hours ~10 h/week"
    - topic: "100x scale (Socratic)"
      decision: "domain rule does not change with scale"
    - topic: "password recovery"
      decision: "excluded from MVP"
    - topic: "data ownership vs export"
      decision: "export of all data and scans added as FR-018 nice-to-have; risk it won't ship accepted"
    - topic: "interface language"
      decision: "Polish only"
  frs_drafted: 17
  quality_check_status: accepted
---

# Shape notes

## Seed idea (verbatim)

> Aplikacja dla genealogów: osoby z drzewa wraz ze źródłami (akta metrykalne, skany). Dane własne: notatki, zdjęcia, skany — bez importu GEDCOM. Termin: 4 listopada 2026, 10 h/tydz.
> Otwarte pytania: jak modelować fakt kontra źródło, czy skany trzymać lokalnie czy w chmurze, czy MVP obsługuje jedno drzewo czy wiele.

## Vision & Problem Statement

Jako genealog prowadzący własne badania mam dane (notatki, skany, zdjęcia) rozproszone i niepowiązane ze sobą. Fakty, np. daty urodzenia, nie są powiązane ze źródłami, np. aktami metrykalnymi. Najbardziej odczuwam to, gdy muszę znaleźć konkretną osobę i gdy chcę potwierdzić fakt: każdorazowo ręcznie przeszukuję notatki i sprawdzam, czy fakt ma podstawę w źródle. Poprzednia aplikacja genealogiczna przestała być dostępna po wygaśnięciu licencji.

Wgląd: dane genealogiczne muszą pozostać w pełni moje, niezależne od licencji i abonamentu. Istniejące darmowe narzędzia samodzielnie hostowane są zbyt ciężkie jak na mój przepływ pracy (osoba → fakt → źródło → skan). Budowa własnej aplikacji jest też celem samym w sobie: to projekt rozwojowy.

## User & Persona

Jedyny użytkownik MVP to autor aplikacji, genealog prowadzący własne badania na podstawie własnych materiałów (notatki, zdjęcia, skany akt). Sięga po aplikację, gdy szuka konkretnej osoby i gdy weryfikuje, na jakim źródle opiera się fakt.

## Access Control

Rejestracja jest otwarta: każdy może założyć konto i zalogować się. Każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnych danych (osób, faktów, źródeł, notatek, zdjęć, skanów) i nie widzi danych innych użytkowników. Nie ma ról ani współdzielenia. Niezalogowany użytkownik nie widzi żadnych danych; próba dostępu kieruje do logowania lub rejestracji.

Główna persona MVP pozostaje bez zmian (autor aplikacji), ale aplikacja nie zakłada, że ma tylko jednego użytkownika.

## MVP flow (working draft)

1. Rejestracja / logowanie.
2. Dodanie osoby: imię, nazwisko, data i miejsce urodzenia, miejsce zamieszkania, wyznanie → zapis.
3. Dołączenie aktu urodzenia (skan) jako źródła faktu urodzenia.
4. Dodanie małżonka i powiązanie relacją małżeństwa; dane ślubu (data, miejsce) + akt małżeństwa jako źródło.
5. Dodanie rodziców i dziadków z relacjami rodzic–dziecko, ich danymi i źródłami.
6. Dla osób zmarłych: data i miejsce śmierci + akt zgonu (jeśli jest).
7. Przeglądanie osób przez graficzne drzewo lub wyszukiwanie na liście.

Przesunięte do v2 (decyzja zakresu z Fazy 3): zdjęcie osoby (portret); data i miejsce pogrzebu.

## Success Criteria

### Primary
- Do 4.11.2026 moje realne drzewo (min. 3 pokolenia) z faktami opartymi na skanach akt jest w aplikacji; odnajduję w niej osoby i źródła faktów i przestaję do tego używać rozproszonych notatek.

### Secondary
- Łatwo zobaczyć, które fakty nie mają jeszcze źródła.

### Guardrails
- Żadne dane ani skany nie są dostępne dla niezalogowanych ani dla innych użytkowników.
- Wprowadzone dane i wgrane skany nie giną.

## Timeline acknowledgment

Acknowledged on 2026-09-16: 7-week MVP requires sustained dedication; user accepted.

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

## Business Logic

Wydarzenie osoby (urodzenie, małżeństwo, zgon) jest potwierdzone wtedy i tylko wtedy, gdy powiązany jest z nim co najmniej jeden skan aktu metrykalnego; w przeciwnym razie jest niepotwierdzone.

Dane wejściowe to wydarzenia wprowadzone przez użytkownika oraz powiązania wydarzeń ze skanami aktów. Jeden skan może być źródłem dla wielu wydarzeń. Jedno wydarzenie ma zwykle jeden skan, ale może mieć kilka. Małżeństwo jest jednym wydarzeniem obojga małżonków, więc powiązany z nim akt potwierdza je u obu osób.

„Potwierdzone” znaczy „ma źródło”, a nie „jest prawdziwe”. Zgodność wpisanej wartości z treścią skanu jest odpowiedzialnością użytkownika. Usunięcie ostatniego powiązanego skanu zmienia wydarzenie na niepotwierdzone (FR-015).

Użytkownik widzi stan na podglądzie osoby: niepotwierdzone wydarzenie jest wyraźnie odróżnione od potwierdzonego. Drzewo w MVP nie pokazuje stanu.

## Non-Functional Requirements

- Żadne dane ani skany użytkownika nie są dostępne dla niezalogowanych ani dla innych użytkowników.
- Dane i skany użytkownika nie trafiają do podmiotów trzecich ani usług zewnętrznych, nad którymi właściciel instancji nie ma kontroli.
- Po awarii tracone są najwyżej dane wprowadzone w ciągu ostatnich 24 h (wartość domyślna zaakceptowana przez użytkownika).
- Aplikacja jest w pełni użyteczna w aktualnych przeglądarkach na komputerze; urządzenia mobilne nie są celem MVP.

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

## Open Questions (running)

1. **Rozjazd faktu i aktu po edycji (FR-006):** ryzyko zaakceptowane w MVP; do rozważenia w v2. Owner: user.
2. **Wizja „pełna własność danych” a eksport jako nice-to-have (FR-018):** jeśli eksport nie powstanie do 2026-11-04, napięcie z wizją pozostaje nierozwiązane. Owner: user. By: 2026-11-04.

Rozstrzygnięte w Fazie 7: odzyskiwanie hasła → wykluczone (Non-Goals); eksport danych → FR-018 nice-to-have; język interfejsu → polski.

## Forward: tech-stack

- Preferencja: aplikacja hostowana na własnym serwerze użytkownika (zgłoszone w Fazie 1).
- Otwarte pytanie z pomysłu wyjściowego: skany lokalnie czy w chmurze — decyzja o przechowywaniu należy do wyboru stacku; zobowiązania widoczne dla użytkownika uchwycone jako NFR w Fazie 5 (dane i skany nie trafiają do podmiotów trzecich poza kontrolą właściciela instancji; utrata danych po awarii ≤ 24 h).

## Quality cross-check

Run on 2026-09-16 — all items present (Access Control, Business Logic one-sentence rule, project artifacts, timeline-cost acknowledgment, Non-Goals; preserved behavior n/a for greenfield). Open questions resolved in Phase 7: password recovery, data export, interface language. Status: accepted.
