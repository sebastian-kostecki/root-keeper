---
project: "root-keeper"
context_type: brownfield
created: 2026-10-04
updated: 2026-10-04
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
    - topic: "trigger"
      decision: "entering the author's real tree exposed UX gaps and source-description ambiguity"
    - topic: "change category"
      decision: "four parts: source description (major feature), tree look + side panel (UX), places gazetteer (new module, revives parked S-12–S-15), all-ancestors view (new view)"
    - topic: "biggest pain"
      decision: "ambiguous metrical-act (and gravestone) description; tree appearance"
    - topic: "insight"
      decision: "parish != archive; description depends on source kind; sources are recognised by year + parish; one scan holds several entries/persons"
    - topic: "primary persona"
      decision: "unchanged — the author"
    - topic: "access model"
      decision: "no change — own tree only, other user's record is 404"
    - topic: "gazetteer ownership"
      decision: "private per tree (as in prd-v2)"
    - topic: "scope"
      decision: "all four parts by 2026-11-04 (4 weeks); user accepted sustained-effort cost; person photos on cards drop first if time runs out"
    - topic: "free-text places migration"
      decision: "user accepts that moving free-text places to the gazetteer may disturb existing data and will fix by hand"
    - topic: "parish"
      decision: "separate parish list: name, denomination, seat locality from the gazetteer"
    - topic: "archive"
      decision: "archive is free text with suggestions of previously entered values (Socrates FR-009: a managed list is overkill); set per source"
    - topic: "entry -> person"
      decision: "entry number given when linking a source to a fact; visible at the fact and at the source"
    - topic: "gravestone description"
      decision: "cemetery (locality from gazetteer) + persons buried"
    - topic: "maiden name"
      decision: "optional birth-surname field; shown 'z d.'; search by both"
    - topic: "family events tab"
      decision: "own events + children's births/deaths + spouse's marriage/death"
    - topic: "burial"
      decision: "burial place only (no date)"
    - topic: "marriage panel"
      decision: "date, place, source (editable) + the couple's children"
    - topic: "ancestors view"
      decision: "all ancestors of a person; option: number of generations"
    - topic: "side panel scope"
      decision: "person, source, marriage, locality open in a right side panel; delete confirmations and small pickers stay modals"
    - topic: "residence"
      decision: "list of localities with optional from-to years"
    - topic: "core domain rule"
      decision: "one source of truth for localities: each locality exists once per tree; parishes, cemeteries, facts and residences reference it; merging a duplicate moves all references"
    - topic: "migration: provenance"
      decision: "existing 'Pochodzenie' values move to the archive field"
    - topic: "migration: residence"
      decision: "existing single residence text becomes the first residence entry (no years), via the gazetteer"
    - topic: "NFR"
      decision: "usable on 1366–2560 px screens; backups cover new data (RPO 24 h); 'no third parties' not re-confirmed -> Open Questions"
    - topic: "framing"
      decision: "no change — web-app, small scale, deadline 2026-11-04, after hours"
    - topic: "existing-system constraints"
      decision: "CI must pass; E2E (Playwright) protects critical paths (fact-source, relations from slots)"
  frs_drafted: 29
  quality_check_status: accepted
---

# root-keeper — shape notes (zmiana brownfield, po M-1 v4)

Poprzednia sesja: `context/foundation/archive/shape-notes-2026-10-04-2039.md` (prd-v4).

## Seed idea

Dosłownie od użytkownika:

```
- widok drzewa:
    - większe karty osób (całe drzewo źle wygląda na dużym ekranie, gdzie zajmuje tylko górną część ekranu)
    - możliwość zdjęcia osób na kartach
    - lepsze rysowanie linii (zwłaszcza do dzieci)
    - button podgląd chyba nie jest potrzebny
    - modal/panel na małżeństwo; widoczne więcej info o samym małżeństwie
- edycja osoby
    - nazwisko panieńskie (zastanowić się jak to lepiej zrobić)
    - może cały model zrobić w panelu bocznym? zeby wysuwał się panel boczny, a nie modal
    - zakładka z listą eventów danej osoby (swoje, rodzinne)
    - data i miejsce pogrzebu?
    - miejsce na notatki (nowa zakładka?)
    - widok lat danej osoby (ile ma, albo ile przeżyla)
- lista źródeł
    - mniejsze karty na grid
    - inaczej dane per źródło - tak aby bardziej opisać
        - typ
    - akt metrykalny:
        - więcej samym skanie: czego dotyczy (jaka parafia), skąd (jakie archiwum)
    - notatki - jak tam pisze jaki numer skanu kogo dotyczy (może inaczej)
    - sama edycja może zamiast modalu bardziej panel boczny
    - podczas wybierania źródeł trzeba pokazać rok i skąd (chodzi o parafie)
- nowe:
    - dodać widok całego drzewa danej osoby - czyli chcę widziec daną osobę i wszystkich jej przodków (może dodatkowo jakies opcje)
    - miejscowości jako tabela z opisem, itd. - wykorzystanie tych miejscowosc przy urodzeniu, śmierci, itp; możliwość ustawienia kilku miejsowosci jako zamieszkanie
    - zamiast modalu osoby moze jednak wysuwany panel z prawej strony?
- ogolnie moze zamiast modali wysuwany panel boczny
```

## Current System

- **Produkt:** root-keeper to prywatne, samodzielnie hostowane drzewo genealogiczne. Każde konto ma jedno drzewo i widzi tylko swoje dane.
- **Stack:** monolit Laravel 13 + Inertia v3 / React 19, shadcn/ui, PostgreSQL, Docker (self-hosted).
- **Użytkownicy:** autor, genealog wprowadzający własne prawdziwe drzewo z własnych materiałów; skala mała.
- **Funkcje dziś (M-1, S-01 – S-29 zrobione):**
  - Drzewo to arkusz rodziny: centrum, rodzice, dziadkowie, małżonek, dzieci. Relacje (rodzic, dziecko, małżonek) dodaje się z pustych slotów, wybierając istniejącą albo nową osobę.
  - Podgląd i formularz osoby działają w modalach. Podgląd pokazuje relacje tylko do odczytu, usuwanie relacji i małżeństwa jest w podglądzie.
  - Źródła mają rodzaj (akt metrykalny / zdjęcie nagrobka / inne) i jeden płaski opis, wspólny dla wszystkich rodzajów: Nazwa, Typ aktu, Numer wpisu, Rok, Pochodzenie (wolny tekst), Link, Notatka.
  - Fakty urodzenia, małżeństwa i zgonu potwierdza się źródłem każdego rodzaju. Przy wyborze źródła pasujące akty są pokazywane najpierw.
  - Miejsca (zamieszkanie, miejsca faktów) są wolnym tekstem. Spis miejscowości z prd-v2 (S-12 – S-15) odłożono 2026-09-30.

## Vision & Problem Statement

Przy wprowadzaniu prawdziwego drzewa autor nie ma gdzie rzetelnie opisać aktu metrykalnego. Akt powstał w parafii (np. Luboml), a przechowuje go archiwum (np. Archiwum Główne Akt Dawnych w Warszawie). Obie informacje trafiają dziś do jednego pola „Pochodzenie” albo do notatki. Nagrobki nie mają przemyślanego opisu, a to, który wpis na skanie dotyczy której osoby, zapisuje się w notatce. W efekcie przy wyborze źródła do faktu nie widać roku ani parafii, a opisy są niespójne. Do tego drzewo źle wygląda: małe karty zajmują tylko górę dużego ekranu, linie do dzieci są nieczytelne, nie ma zdjęć, a przycisk „Podgląd” jest zbędny. Nie ma też widoku wszystkich przodków osoby, a miejsca pozostają wolnym tekstem.

Wgląd: parafia i archiwum to dwie różne informacje, bo jedno archiwum trzyma księgi wielu parafii. Opis źródła zależy od jego rodzaju (akt, nagrobek, inne). Genealog rozpoznaje źródło po roku i parafii, nie po nazwie pliku. Jeden skan strony księgi zawiera kilka wpisów dotyczących różnych osób. Zmiana porządkuje opis źródeł, dopracowuje wygląd drzewa i nawigację (panel boczny zamiast modali), dodaje widok przodków i spis miejscowości — bez psucia powiązań fakt–źródło i dodawania relacji z drzewa.

Najbardziej bolą: (1) niejasny opis aktów i nagrobków, (2) wygląd drzewa.

Do rozstrzygnięcia w fazie 4/5: czy parafia jest miejscowością ze spisu, czy osobnym bytem (a archiwum?).

## User & Persona

Bez zmian: **autor**, genealog wprowadzający własne prawdziwe drzewo z aktów metrykalnych, zdjęć nagrobków i notatek. Sięga po aplikację, gdy siedzi nad skanem aktu i chce go opisać, podpiąć pod fakt i zobaczyć osobę na drzewie.

## Access Control

Bez zmian — obecny model zostaje: jedno drzewo na konto, otwarta rejestracja, każdy widzi i edytuje tylko swoje dane, a cudzy rekord to 404 (także w panelach, widoku przodków i spisie miejscowości). Spis miejscowości (oraz ewentualnie parafii/archiwów) jest prywatny dla drzewa, jak w prd-v2.

## Success Criteria

### Primary
- Autor wgrywa skan strony księgi (Luboml, 1908, z AGAD) i opisuje go osobno: czego dotyczy (Luboml), rok (1908), typ (urodzenie), skąd (AGAD), a w notatce „nr 95 dotyczy X” (albo więcej). Zapisany akt jest źródłem. Przy dodawaniu źródła do faktu osoby autor znajduje ten akt, szukając po miejscu (Luboml) i roku (1908).

Przepływ zmiany:
1. Wgrywam skan strony księgi: Luboml, 1908 (z AGAD).
2. Opisuję go: czego dotyczy (Luboml), rok, typ aktu (urodzenie), skąd (AGAD); notatka „nr 95 dotyczy X”.
3. Zapisuję — akt jest źródłem.
4. Przy dodawaniu źródła do faktu osoby szukam po miejscu i roku i trafiam w ten akt.

### Secondary
- Drzewo wypełnia duży ekran: większe karty, czytelne linie do dzieci.
- Autor widzi wszystkich przodków wybranej osoby w jednym widoku.
- Osobę i źródło ogląda i edytuje w wysuwanym panelu bocznym zamiast modala.
- Miejscowość istnieje raz w spisie i jest używana w faktach, zamieszkaniu i źródle.

### Guardrails
- Powiązania fakt–źródło: każdy istniejący fakt potwierdzony źródłem nadal prowadzi do tego samego źródła i skanu.
- Dodawanie relacji z drzewa (rodzic, dziecko, małżonek — istniejąca lub nowa osoba) działa jak dziś, także po przebudowie drzewa.

Świadomie poza guardrails: przejście miejsc z wolnego tekstu na spis miejscowości (oraz ewentualnie starego opisu źródła na nowy) może zakłócić istniejące dane — autor dopuszcza ręczne poprawki.

## Timeline acknowledgment

Acknowledged on 2026-10-04: 4-week change (all four parts: source description, tree look + side panel, all-ancestors view, places gazetteer; hard deadline 2026-11-04, after hours) requires sustained dedication; user accepted. If time runs out, person photos on tree cards drop first.

## Functional Requirements

Wszystkie FR są `must-have` (zakres: całość do 2026-11-04). Jeśli zabraknie czasu, pierwsze do odcięcia jest FR-015.

### Opis źródła
- FR-001: Autor can opisać akt metrykalny osobnymi danymi: parafia (ze spisu parafii, z możliwością dopisania nowej w locie), rok, typ aktu, archiwum (tekst z podpowiedziami wcześniej wpisanych archiwów). Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "brak parafii w spisie blokuje opis aktu — wprowadzanie się rwie."
  > Resolution: kept; parafię dopisuje się w locie przy opisie aktu, bez wychodzenia z panelu źródła (jak archiwum).
- FR-002: Autor can opisać zdjęcie nagrobka: cmentarz (miejscowość ze spisu) i osoby pochowane; miejsce pochowania osoby z listy pochowanych podpowiada się cmentarzem nagrobka (można zmienić). Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "cmentarz na nagrobku dubluje miejsce pochowania osoby (FR-022) — dwa miejsca prawdy."
  > Resolution: kept; miejsce pochowania podpowiada się z nagrobka, ale pozostaje edytowalne przy osobie.
- FR-003: Autor can podać numer wpisu przy powiązaniu źródła z faktem; numer jest widoczny przy fakcie i przy źródle. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "dubluje dzisiejsze pole „Numer wpisu” na źródle."
  > Resolution: kept; pole „Numer wpisu” znika ze źródła, a dotychczasowe wartości zostają widoczne do ręcznego przeniesienia.
- FR-004: Autor can przy wyborze źródła do faktu szukać po miejscu (miejscowość parafii lub cmentarza) i roku i widzi przy każdym źródle rok i parafię lub cmentarz. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "nagrobki nie mają parafii — szukanie po parafii ich nie obejmie."
  > Resolution: kept; szukanie po miejscu obejmuje miejscowość parafii i cmentarza (rozstrzygnięcie przyjęte przez facylitatora — do potwierdzenia w PRD).
- FR-005: Autor can przeglądać listę źródeł jako siatkę mniejszych kart z rodzajem, typem, parafią lub cmentarzem, rokiem i archiwum oraz filtrować ją po rodzaju, parafii/miejscu, roku i archiwum. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "przy setkach źródeł sama siatka nie wystarczy."
  > Resolution: kept and extended; lista dostaje filtry po rodzaju, parafii/miejscu, roku i archiwum.
- FR-006: Autor can dodawać, oglądać i edytować źródło w szerokim wysuwanym panelu bocznym (skan obok opisu) zamiast modala. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "panel boczny jest za wąski, żeby opisywać akt patrząc na skan."
  > Resolution: kept; panel źródła jest szerszy niż panel osoby, skan i formularz stoją obok siebie, powiększenie skanu jak dziś.

### Spisy: miejscowości, parafie, archiwa
- FR-007: Autor can prowadzić spis miejscowości z opisem; spis startuje z istniejących tekstowych miejsc (identyczne teksty → jedna miejscowość), a literówki autor poprawia i scala ręcznie. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "przeniesienie wolnego tekstu na spis zjada czas."
  > Resolution: kept; spis wstaje automatycznie z istniejących wartości, autor tylko poprawia i scala.
- FR-008: Autor can prowadzić spis parafii: nazwa, wyznanie, miejscowość siedziby ze spisu. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "trzy spisy (miejscowości, parafie, archiwa) przy jednym akcie to dużo klikania."
  > Resolution: kept; każde pole spisu podpowiada po kilku literach i pozwala dopisać nowy wpis bez wychodzenia z panelu.
- FR-009: Autor can wpisać archiwum jako tekst, który podpowiada wcześniej wpisane archiwa. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "zarządzana lista archiwów to przesada — kilka archiwów na całe drzewo."
  > Resolution: changed; zamiast listy archiwów pole tekstowe z podpowiedziami wcześniej wpisanych wartości.
- FR-010: Autor can wybrać miejsce urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "miejsce bywa niepewne („okolice Lubomla”) i nie pasuje do spisu."
  > Resolution: kept; miejsce zawsze ze spisu dla spójności, niepewność autor opisuje w notatce.
- FR-011: Autor can ustawić osobie kilka miejscowości zamieszkania z opcjonalnymi latami od–do. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "zamieszkanie bez źródła to domysł."
  > Resolution: kept; zamieszkania nie potwierdza się źródłem w tej wersji — to notatka badawcza.
- FR-012: Autor can scalić zduplikowaną miejscowość lub parafię z właściwym wpisem (powiązania przechodzą na właściwy) i usunąć wpis, który nie jest nigdzie używany. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "usuwanie wpisów spisu z listą powiązań jest rzadko potrzebne."
  > Resolution: changed; zamiast usuwania z listą powiązań — scalanie duplikatów (ważne po automatycznej migracji) i usuwanie tylko nieużywanych wpisów.

### Drzewo
- FR-013: Autor can oglądać drzewo z większymi kartami, które dopasowuje się do ekranu (wypełnia duży), z przybliżaniem i oddalaniem. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "większe karty nie zmieszczą się na laptopie."
  > Resolution: kept and extended; drzewo dopasowuje się do ekranu i ma przybliżanie/oddalanie.
- FR-014: Autor can czytelnie odróżnić linie łączące; linia do dziecka wychodzi od właściwej pary (małżeństwa), więc widać, z którego związku jest dziecko. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "dzieci z różnych małżeństw — „czytelne linie” nie mówią, z którego związku."
  > Resolution: kept and sharpened; linia do dziecka wychodzi od właściwej pary.
- FR-015: Autor can dodać zdjęcie osoby i widzi je na karcie drzewa; karta bez zdjęcia pokazuje inicjały lub sylwetkę (pierwsze do odcięcia). Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "większość przodków nie ma zdjęć — drzewo wyglądałoby nierówno."
  > Resolution: kept; karta bez zdjęcia pokazuje zastępczy znak (inicjały/sylwetka).
- FR-016: Autor can otworzyć panel osoby kliknięciem karty; przycisk „Podgląd” znika, a ustawienie osoby w centrum drzewa jest akcją w panelu. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "kliknięcie karty dziś przecentrowuje drzewo — zmiana zabiera szybką nawigację."
  > Resolution: kept; kliknięcie = panel osoby, przecentrowanie przenosi się do przycisku w panelu.
- FR-017: Autor can otworzyć panel małżeństwa: data, miejsce, źródło (z edycją) i dzieci pary. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "panel małżeństwa dubluje sekcję małżeństwa w panelu osoby."
  > Resolution: kept; oba miejsca pozwalają edytować małżeństwo — wygoda ważniejsza niż jedno miejsce edycji.
- FR-018: Autor can zobaczyć wybraną osobę i wszystkich jej przodków w jednym widoku z wyborem liczby pokoleń, z dopasowaniem do ekranu i przybliżaniem jak w drzewie. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "przy 6+ pokoleniach widok przodków robi się nieczytelny."
  > Resolution: kept; ten sam mechanizm dopasowania i przybliżania co FR-013.

### Osoba
- FR-019: Autor can oglądać i edytować osobę w wysuwanym panelu z prawej zamiast modala; otwarty panel nie zasłania drzewa — drzewo zwęża się obok niego. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "panel zasłania prawą część drzewa."
  > Resolution: kept; drzewo przesuwa się i zwęża obok otwartego panelu.
- FR-020: Autor can zapisać nazwisko rodowe; osoba wyświetla się „z d. …” i jest wyszukiwana po obu nazwiskach. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "kobieta bez znanego męża ma tylko nazwisko rodowe."
  > Resolution: kept; gdy znane jest jedno nazwisko, wpisuje się je w główne pole, a „z d.” pojawia się tylko, gdy znane są oba.
- FR-021: Autor can zobaczyć zakładkę wydarzeń osoby: własne oraz urodzenia i zgony dzieci, ślub i zgon małżonka, uporządkowane po roku, z wydarzeniami bez daty na końcu. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "daty częściowe (sam rok, brak daty) psują kolejność osi czasu."
  > Resolution: kept; sortowanie po roku, wydarzenia bez daty na końcu.
- FR-022: Autor can zapisać miejsce pochowania osoby. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "brak — rozważane razem z FR-023."
  > Resolution: kept as written (przypomnienie: podpowiada się z nagrobka — FR-002).
- FR-023: Autor can prowadzić notatkę o osobie — jedno pole tekstowe w osobnej zakładce. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "notatki jako lista wpisów z datą (dziennik badań)?"
  > Resolution: kept simple; jedna notatka tekstowa wystarczy.
- FR-024: Autor can zobaczyć wiek osoby — ile przeżyła (gdy zna datę zgonu) albo ile ma (tylko gdy wynik jest poniżej ok. 110 lat; inaczej wiek nie jest pokazywany). Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "brak daty zgonu nie znaczy, że osoba żyje — ur. 1850 nie „ma 176 lat”."
  > Resolution: kept; „ile ma” tylko poniżej progu ok. 110 lat.

### Zachowane
- FR-025: Autor can potwierdzić urodzenie, małżeństwo i zgon źródłem każdego rodzaju i przejść z faktu do skanu. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).
- FR-026: Autor can dodać rodzica, dziecko i małżonka (istniejącego lub nowego) z pustych slotów drzewa. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).
- FR-027: Autor can usunąć źródło po zobaczeniu listy faktów, które stracą potwierdzenie. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).
- FR-028: Autor can w panelu osoby przechodzić do krewnych i po zamknięciu panelu być w tym samym miejscu; historia „Wstecz” między krewnymi nie jest wymagana. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "czy historia „Wstecz” między krewnymi musi przetrwać przejście na panel?"
  > Resolution: changed; przejście do krewnego i powrót na to samo miejsce po zamknięciu zostają, historia „Wstecz” może zniknąć.
- FR-029: Autor can widzieć tylko swoje dane; cudzy rekord (także w panelach, spisach i widoku przodków) to 404. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).

## User Stories

### US-01: Akt z Lubomla opisany parafią i archiwum, odnaleziony po miejscu i roku

- **Given** autor wgrał skan strony księgi jako źródło: parafia Luboml, rok 1908, typ aktu „urodzenie”, archiwum AGAD
- **When** podpina źródło do urodzenia osoby, szuka „Luboml” i 1908 i podaje wpis nr 95
- **Then** widzi ten akt na liście wyboru (z rokiem i parafią), a przy fakcie urodzenia — źródło z numerem wpisu 95

#### Acceptance Criteria
- Parafię, której nie ma w spisie, autor dopisuje w trakcie opisu aktu, bez wychodzenia z panelu źródła.
- Archiwum podpowiada się z wcześniej wpisanych wartości.
- Przed zmianą: parafia i archiwum lądowały w jednym polu „Pochodzenie” albo w notatce, a numer wpisu był polem źródła, nie powiązania.
- Istniejące powiązania fakt–źródło prowadzą do tych samych źródeł co przed zmianą.

## Business Logic

Każda miejscowość istnieje w drzewie raz, a parafie, cmentarze nagrobków, miejsca faktów (urodzenie, ślub, zgon, pochowanie) i zamieszkania osób odwołują się do niej. Scalenie duplikatu przenosi wszystkie te odwołania na właściwy wpis.

Reguła dziś: miejsca to wolny tekst przy osobie (zamieszkanie) i wydarzeniu (miejsce), więc „Luboml” i „Lubomla” to dwa różne miejsca, a nic ich nie łączy. Zmiana: autor wybiera miejsce ze spisu (z podpowiedziami i dopisywaniem w locie), a spis startuje z istniejących tekstów (identyczne teksty → jedna miejscowość). Literówki autor scala ręcznie, a wpis nieużywany może usunąć.

Reguły pomocnicze:
- **Wybór źródła (modyfikacja S-19):** dziś przy wiązaniu źródła z wydarzeniem najpierw pokazywane są akty o pasującym typie. Po zmianie autor może dodatkowo zawęzić źródła po miejscu (miejscowość parafii aktu lub cmentarza nagrobka) i roku; pierwszeństwo pasującego typu zostaje.
- **Opis zależy od rodzaju źródła:** akt metrykalny — parafia, rok, typ aktu, archiwum; nagrobek — cmentarz i osoby pochowane. Miejsce pochowania osoby z listy pochowanych podpowiada się cmentarzem nagrobka (można zmienić).
- **Numer wpisu należy do powiązania źródła z faktem,** nie do źródła — jeden skan, wiele wpisów, wiele osób.
- **Wiek:** ile przeżyła, gdy znana jest data zgonu; ile ma tylko wtedy, gdy wynik jest poniżej ok. 110 lat — inaczej wiek nie jest pokazywany.

## Constraints & Preserved Behavior

- **Zachowane bez zmian:** powiązania fakt–źródło (każdy istniejący fakt prowadzi do tego samego źródła i skanu); dodawanie rodzica, dziecka i małżonka ze slotów drzewa; usuwanie źródła z listą faktów tracących potwierdzenie; pierwszeństwo pasującego typu aktu przy wyborze źródła; własność danych (cudzy rekord = 404).
- **Zmienione świadomie:** historia „Wstecz” między krewnymi w podglądzie osoby może zniknąć przy przejściu na panel; kliknięcie karty drzewa otwiera panel zamiast przecentrowywać.
- **Migracje danych (autor dopuszcza ręczne poprawki):**
  - Miejsca wydarzeń i zamieszkanie (wolny tekst) → spis miejscowości tworzony automatycznie z istniejących wartości (identyczne teksty → jedna miejscowość).
  - Jedno pole „Zamieszkanie” → pierwsza pozycja listy zamieszkań, bez lat.
  - „Pochodzenie” źródła → pole archiwum.
  - „Numer wpisu” źródła → znika jako pole źródła; dotychczasowe wartości pozostają widoczne do ręcznego przeniesienia na powiązania z faktami.
- **Ograniczenia istniejącego systemu:** CI musi przechodzić jak dziś (lint/format bez ostrzeżeń, analiza statyczna, testy na PostgreSQL); ścieżki krytyczne z guardrails (fakt–źródło, relacje ze slotów drzewa) są chronione testami E2E.
- **Kopie zapasowe** muszą objąć nowe dane (spisy, zdjęcia osób, notatki) — jak dotychczasowe backupy bazy i `storage/app`.

## Non-Functional Requirements

- Drzewo, widok przodków i panele są użyteczne na ekranach od 1366 px do 2560 px szerokości; na dużym ekranie drzewo wypełnia dostępną przestrzeń.
- Nowe dane (spisy miejscowości i parafii, zdjęcia osób, notatki, zamieszkania) są objęte kopią zapasową z utratą najwyżej 24 h danych (RPO 24 h), jak dotychczasowe dane.

## Product framing

- `product_type`: web-app — bez zmian (istniejąca aplikacja webowa).
- `target_scale.users`: small — bez zmian (autor).
- `timeline_budget`: delivery_weeks 4, hard_deadline 2026-11-04, after_hours_only true.

## Non-Goals

- **Brak udostępniania drzewa rodzinie** — nadal jeden użytkownik na drzewo; model dostępu bez zmian.
- **Brak dodawania osób i relacji z widoku przodków** — widok przodków służy do oglądania; relacje dodaje się na drzewie-arkuszu.
- **Brak importu/eksportu GEDCOM** — dane wchodzą ręcznie, jak dotąd.
- **Brak nazw historycznych i zmian granic miejscowości** — takie informacje trafiają do opisu miejscowości.
- **Brak świadków ślubu** — panel małżeństwa pokazuje datę, miejsce, źródło i dzieci pary.
- **Brak wielu nazwisk po kolejnych mężach** — osoba ma nazwisko i nazwisko rodowe; reszta w notatce.
- **Brak daty pogrzebu i potwierdzania zamieszkania źródłem** — pogrzeb to samo miejsce pochowania; zamieszkanie to notatka badawcza bez źródła.

## Quality cross-check

Run 2026-10-04 — all six items present (Access Control, Business Logic, project artifacts, timeline-cost acknowledgment, Non-Goals, preserved behavior). User accepted.

Open items for `/10x-prd` `## Open Questions` (not gate gaps):
1. **NFR „dane nie trafiają do podmiotów trzecich”** (z poprzednich PRD) nie zostało ponownie potwierdzone w tej sesji — dotyczy zwłaszcza zdjęć osób. Owner: autor.
2. **FR-004** — rozstrzygnięcie „szukanie źródła po miejscu obejmuje miejscowość parafii i cmentarza” przyjął facylitator; do potwierdzenia przez autora.

Scope note: 29 FR w 4 tygodnie (3 nowe spisy/listy, 4 migracje danych, panele boczne w czterech miejscach) — koszt zaakceptowany; pierwsze do odcięcia FR-015 (zdjęcia). Rdzeń bólu: opis źródła (FR-001 – FR-006) i spis miejscowości (FR-007 – FR-012).
