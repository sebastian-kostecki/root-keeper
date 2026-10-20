---
project: "root-keeper"
version: 1
status: draft
created: 2026-10-04
context_type: brownfield
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 4
  hard_deadline: 2026-11-04
  after_hours_only: true
---

# root-keeper — opis źródeł, spis miejscowości, wygląd drzewa i widok przodków (zmiana brownfield)

Poprzednie PRD (zarchiwizowane): MVP `context/foundation/archive/prd-v1.md`, opisy skanów i spis miejscowości `context/foundation/archive/prd-v2.md`, źródła i drzewo edytowalne `context/foundation/archive/prd-v3.md`, podgląd osoby w modalu i relacje na drzewie `context/foundation/archive/prd-v4.md`. Notatki z kształtowania tej zmiany: `context/foundation/shape-notes.md`. Wymagania tej zmiany numerowane od nowa; odwołania do wcześniejszych PRD z prefiksem (`v2:FR-007`, `v4:FR-002`).

## Current System Overview

root-keeper to prywatne, samodzielnie hostowane drzewo genealogiczne: każde konto ma jedno drzewo i widzi tylko swoje dane.

- **Architektura i stack:** monolit Laravel 13 + Inertia v3 / React 19, shadcn/ui, PostgreSQL, Docker (self-hosted). Szczegóły: `context/foundation/tech-stack.md`.
- **Użytkownicy:** autor, genealog wprowadzający własne prawdziwe drzewo z własnych materiałów; skala mała.
- **Jakość dziś:** CI — lint/format bez ostrzeżeń, analiza statyczna, testy na PostgreSQL; ścieżki krytyczne (fakt–źródło, relacje ze slotów drzewa) chronione testami Pest (`WriteTargetsTest`); E2E (Playwright) skonfigurowane — na razie tylko test seed logowania, poza CI.
- **Kopie zapasowe dziś:** baza danych i `storage/app`.
- **Funkcje dziś (M-1, S-01 – S-29 zrobione):**
  - Drzewo to arkusz rodziny: centrum, rodzice, dziadkowie, małżonek, dzieci. Relacje (rodzic, dziecko, małżonek) dodaje się z pustych slotów, wybierając istniejącą albo nową osobę. Kliknięcie karty przecentrowuje drzewo; karta ma przycisk „Podgląd”.
  - Podgląd i formularz osoby działają w modalach. Podgląd pokazuje relacje tylko do odczytu, usuwanie relacji i małżeństwa jest w podglądzie.
  - Źródła mają rodzaj (akt metrykalny / zdjęcie nagrobka / inne) i jeden płaski opis, wspólny dla wszystkich rodzajów: Nazwa, Typ aktu, Numer wpisu, Rok, Pochodzenie (wolny tekst), Link, Notatka.
  - Fakty urodzenia, małżeństwa i zgonu potwierdza się źródłem każdego rodzaju. Przy wyborze źródła pasujące akty są pokazywane najpierw.
  - Miejsca (zamieszkanie, miejsca faktów) są wolnym tekstem. Spis miejscowości z prd-v2 (S-12 – S-15) odłożono 2026-09-30.

## Problem Statement & Motivation

Przy wprowadzaniu prawdziwego drzewa autor nie ma gdzie rzetelnie opisać aktu metrykalnego. Akt powstał w parafii (np. Luboml), a przechowuje go archiwum (np. Archiwum Główne Akt Dawnych w Warszawie). Obie informacje trafiają dziś do jednego pola „Pochodzenie” albo do notatki. Nagrobki nie mają przemyślanego opisu, a to, który wpis na skanie dotyczy której osoby, zapisuje się w notatce. W efekcie przy wyborze źródła do faktu nie widać roku ani parafii, a opisy są niespójne. Do tego drzewo źle wygląda: małe karty zajmują tylko górę dużego ekranu, linie do dzieci są nieczytelne, nie ma zdjęć, a przycisk „Podgląd” jest zbędny. Nie ma też widoku wszystkich przodków osoby, a miejsca pozostają wolnym tekstem.

Wgląd: parafia i archiwum to dwie różne informacje, bo jedno archiwum trzyma księgi wielu parafii. Opis źródła zależy od jego rodzaju (akt, nagrobek, inne). Genealog rozpoznaje źródło po roku i parafii, nie po nazwie pliku. Jeden skan strony księgi zawiera kilka wpisów dotyczących różnych osób. Zmiana porządkuje opis źródeł, dopracowuje wygląd drzewa i nawigację (panel boczny zamiast modali), dodaje widok przodków i spis miejscowości — bez psucia powiązań fakt–źródło i dodawania relacji z drzewa.

Dlaczego teraz: wprowadzanie prawdziwego drzewa autora odsłoniło luki w UX i niejednoznaczność opisu źródeł. Obecne obejście: parafia i archiwum w jednym polu „Pochodzenie” albo w notatce, numer wpisu i to, kogo dotyczy, w notatce — opisy są niespójne, a źródła przy fakcie trudno odnaleźć.

Najbardziej bolą: (1) niejasny opis aktów i nagrobków, (2) wygląd drzewa.

Kategoria zmiany: cztery części — opis źródła (znacząca funkcja), wygląd drzewa i panel boczny (UX), spis miejscowości (nowy moduł, wznawia odłożone S-12 – S-15), widok wszystkich przodków (nowy widok).

## User & Persona

Bez zmian: **autor**, genealog wprowadzający własne prawdziwe drzewo z aktów metrykalnych, zdjęć nagrobków i notatek. Sięga po aplikację, gdy siedzi nad skanem aktu i chce go opisać, podpiąć pod fakt i zobaczyć osobę na drzewie.

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

## User Stories

### US-01: Akt z Lubomla opisany parafią i archiwum, odnaleziony po miejscu i roku

- **Given** autor wgrał skan strony księgi jako źródło: parafia Luboml, rok 1908, typ aktu „urodzenie”, archiwum AGAD
- **When** podpina źródło do urodzenia osoby, szuka „Luboml” i 1908 i podaje wpis nr 95
- **Then** widzi ten akt na liście wyboru (z rokiem i parafią), a przy fakcie urodzenia — źródło z numerem wpisu 95

Wcześniej: parafia i archiwum lądowały w jednym polu „Pochodzenie” albo w notatce, a numer wpisu był polem źródła, nie powiązania.

#### Acceptance Criteria
- Parafię, której nie ma w spisie, autor dopisuje w trakcie opisu aktu, bez wychodzenia z panelu źródła.
- Archiwum podpowiada się z wcześniej wpisanych wartości.
- Przed zmianą: parafia i archiwum lądowały w jednym polu „Pochodzenie” albo w notatce, a numer wpisu był polem źródła, nie powiązania.
- Istniejące powiązania fakt–źródło prowadzą do tych samych źródeł co przed zmianą.

## Scope of Change

Wszystkie FR są `must-have` (zakres: całość do 2026-11-04). Jeśli zabraknie czasu, pierwsze do odcięcia jest FR-015.

### Opis źródła
- [modified] FR-001: Autor can opisać akt metrykalny osobnymi danymi: parafia (ze spisu parafii, z możliwością dopisania nowej w locie), rok, typ aktu, archiwum (tekst z podpowiedziami wcześniej wpisanych archiwów). Priority: must-have. (Było: jeden płaski opis wspólny dla wszystkich rodzajów; parafia i archiwum w jednym polu „Pochodzenie” albo w notatce.)
  > Socrates: Counter-argument considered: "brak parafii w spisie blokuje opis aktu — wprowadzanie się rwie."
  > Resolution: kept; parafię dopisuje się w locie przy opisie aktu, bez wychodzenia z panelu źródła (jak archiwum).
- [modified] FR-002: Autor can opisać zdjęcie nagrobka: cmentarz (miejscowość ze spisu) i osoby pochowane; miejsce pochowania osoby z listy pochowanych podpowiada się cmentarzem nagrobka (można zmienić). Priority: must-have. (Było: nagrobek opisany tym samym płaskim opisem co akt.)
  > Socrates: Counter-argument considered: "cmentarz na nagrobku dubluje miejsce pochowania osoby (FR-022) — dwa miejsca prawdy."
  > Resolution: kept; miejsce pochowania podpowiada się z nagrobka, ale pozostaje edytowalne przy osobie.
- [modified] FR-003: Autor can podać numer wpisu przy powiązaniu źródła z faktem; numer jest widoczny przy fakcie i przy źródle. Priority: must-have. (Było: „Numer wpisu” jako pole źródła; to, kogo dotyczy który wpis — w notatce.)
  > Socrates: Counter-argument considered: "dubluje dzisiejsze pole „Numer wpisu” na źródle."
  > Resolution: kept; pole „Numer wpisu” znika ze źródła, a dotychczasowe wartości zostają widoczne do ręcznego przeniesienia.
- [modified] FR-004: Autor can przy wyborze źródła do faktu szukać po miejscu (miejscowość parafii lub cmentarza) i roku i widzi przy każdym źródle rok i parafię lub cmentarz. Priority: must-have. (Było: najpierw akty o pasującym typie, bez roku i parafii przy źródle.)
  > Socrates: Counter-argument considered: "nagrobki nie mają parafii — szukanie po parafii ich nie obejmie."
  > Resolution: kept; szukanie po miejscu obejmuje miejscowość parafii i cmentarza (zaproponowane przez facylitatora, potwierdzone przez autora 2026-10-04).
- [modified] FR-005: Autor can przeglądać listę źródeł jako siatkę mniejszych kart z rodzajem, typem, parafią lub cmentarzem, rokiem i archiwum oraz filtrować ją po rodzaju, parafii/miejscu, roku i archiwum. Priority: must-have. (Było: lista większych kart.)
  > Socrates: Counter-argument considered: "przy setkach źródeł sama siatka nie wystarczy."
  > Resolution: kept and extended; lista dostaje filtry po rodzaju, parafii/miejscu, roku i archiwum.
- [modified] FR-006: Autor can dodawać, oglądać i edytować źródło w szerokim wysuwanym panelu bocznym (skan obok opisu) zamiast modala. Priority: must-have. (Było: modal.)
  > Socrates: Counter-argument considered: "panel boczny jest za wąski, żeby opisywać akt patrząc na skan."
  > Resolution: kept; panel źródła jest szerszy niż panel osoby, skan i formularz stoją obok siebie, powiększenie skanu jak dziś.

### Spisy: miejscowości, parafie, archiwa
- [new] FR-007: Autor can prowadzić spis miejscowości z opisem; spis startuje z istniejących tekstowych miejsc (identyczne teksty → jedna miejscowość), a literówki autor poprawia i scala ręcznie (rozszerzone 2026-10-20, `locality-details`: rodzaj, współrzędne, przynależność historyczna, „nie istnieje dziś”). Priority: must-have.
  > Socrates: Counter-argument considered: "przeniesienie wolnego tekstu na spis zjada czas."
  > Resolution: kept; spis wstaje automatycznie z istniejących wartości, autor tylko poprawia i scala.
- [new] FR-008: Autor can prowadzić spis parafii: nazwa, wyznanie, miejscowość siedziby ze spisu. Priority: must-have.
  > Socrates: Counter-argument considered: "trzy spisy (miejscowości, parafie, archiwa) przy jednym akcie to dużo klikania."
  > Resolution: kept; każde pole spisu podpowiada po kilku literach i pozwala dopisać nowy wpis bez wychodzenia z panelu.
- [new] FR-009: Autor can wpisać archiwum jako tekst, który podpowiada wcześniej wpisane archiwa. Priority: must-have.
  > Socrates: Counter-argument considered: "zarządzana lista archiwów to przesada — kilka archiwów na całe drzewo."
  > Resolution: changed; zamiast listy archiwów pole tekstowe z podpowiedziami wcześniej wpisanych wartości.
- [modified] FR-010: Autor can wybrać miejsce urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości. Priority: must-have. (Było: miejsca faktów jako wolny tekst.)
  > Socrates: Counter-argument considered: "miejsce bywa niepewne („okolice Lubomla”) i nie pasuje do spisu."
  > Resolution: kept; miejsce zawsze ze spisu dla spójności, niepewność autor opisuje w notatce.
- [modified] FR-011: Autor can ustawić osobie kilka miejscowości zamieszkania z opcjonalnymi latami od–do. Priority: must-have. (Było: jedno pole „Zamieszkanie” jako wolny tekst.)
  > Socrates: Counter-argument considered: "zamieszkanie bez źródła to domysł."
  > Resolution: kept; zamieszkania nie potwierdza się źródłem w tej wersji — to notatka badawcza.
- [new] FR-012: Autor can scalić zduplikowaną miejscowość lub parafię z właściwym wpisem (powiązania przechodzą na właściwy) i usunąć wpis, który nie jest nigdzie używany. Priority: must-have.
  > Socrates: Counter-argument considered: "usuwanie wpisów spisu z listą powiązań jest rzadko potrzebne."
  > Resolution: changed; zamiast usuwania z listą powiązań — scalanie duplikatów (ważne po automatycznym przeniesieniu) i usuwanie tylko nieużywanych wpisów.

### Drzewo
- [modified] FR-013: Autor can oglądać drzewo z większymi kartami, które dopasowuje się do ekranu (wypełnia duży) i przesuwa się przeciąganiem; skala zależy od ekranu, bez ręcznego przybliżania (decyzja S-05, 2026-10-05). Priority: must-have. (Było: małe karty zajmują tylko górę dużego ekranu.)
  > Socrates: Counter-argument considered: "większe karty nie zmieszczą się na laptopie."
  > Resolution: kept and extended; drzewo dopasowuje się do ekranu i ma przybliżanie/oddalanie.
- [modified] FR-014: Autor can czytelnie odróżnić linie łączące; linia do dziecka wychodzi od właściwej pary (małżeństwa), więc widać, z którego związku jest dziecko. Priority: must-have. (Było: linie do dzieci nieczytelne.)
  > Socrates: Counter-argument considered: "dzieci z różnych małżeństw — „czytelne linie” nie mówią, z którego związku."
  > Resolution: kept and sharpened; linia do dziecka wychodzi od właściwej pary.
- [new] FR-015: Autor can dodać zdjęcie osoby i widzi je na karcie drzewa; karta bez zdjęcia pokazuje inicjały lub sylwetkę (pierwsze do odcięcia). Priority: must-have.
  > Socrates: Counter-argument considered: "większość przodków nie ma zdjęć — drzewo wyglądałoby nierówno."
  > Resolution: kept; karta bez zdjęcia pokazuje zastępczy znak (inicjały/sylwetka).
- [modified] FR-016: Autor can otworzyć panel osoby kliknięciem karty; przycisk „Podgląd” znika, a ustawienie osoby w centrum drzewa jest akcją w panelu. Priority: must-have. (Było: kliknięcie karty przecentrowuje drzewo, podgląd przyciskiem „Podgląd”.)
  > Socrates: Counter-argument considered: "kliknięcie karty dziś przecentrowuje drzewo — zmiana zabiera szybką nawigację."
  > Resolution: kept; kliknięcie = panel osoby, przecentrowanie przenosi się do przycisku w panelu.
- [new] FR-017: Autor can otworzyć panel małżeństwa: data, miejsce, źródło (z edycją) i dzieci pary. Priority: must-have.
  > Socrates: Counter-argument considered: "panel małżeństwa dubluje sekcję małżeństwa w panelu osoby."
  > Resolution: kept; oba miejsca pozwalają edytować małżeństwo — wygoda ważniejsza niż jedno miejsce edycji.
- [new] FR-018: Autor can zobaczyć wybraną osobę i wszystkich jej przodków w jednym widoku z wyborem liczby pokoleń, z dopasowaniem do ekranu i przybliżaniem w tym widoku (przyciski, Ctrl+kółko, szczypanie). Priority: must-have.
  > Socrates: Counter-argument considered: "przy 6+ pokoleniach widok przodków robi się nieczytelny."
  > Resolution: kept; dopasowanie jak w FR-013, a przybliżanie tylko w widoku przodków, bez zmiany drzewa.

### Osoba
- [modified] FR-019: Autor can oglądać i edytować osobę w wysuwanym panelu z prawej zamiast modala; otwarty panel nie zasłania drzewa — drzewo zwęża się obok niego. Priority: must-have. (Było: podgląd i formularz osoby w modalach.)
  > Socrates: Counter-argument considered: "panel zasłania prawą część drzewa."
  > Resolution: kept; drzewo przesuwa się i zwęża obok otwartego panelu.
- [new] FR-020: Autor can zapisać nazwisko rodowe; osoba wyświetla się „z d. …” i jest wyszukiwana po obu nazwiskach. Priority: must-have.
  > Socrates: Counter-argument considered: "kobieta bez znanego męża ma tylko nazwisko rodowe."
  > Resolution: kept; gdy znane jest jedno nazwisko, wpisuje się je w główne pole, a „z d.” pojawia się tylko, gdy znane są oba.
- [new] FR-021: Autor can zobaczyć zakładkę wydarzeń osoby: własne oraz urodzenia i zgony dzieci, ślub i zgon małżonka, uporządkowane po roku, z wydarzeniami bez daty na końcu. Priority: must-have.
  > Socrates: Counter-argument considered: "daty częściowe (sam rok, brak daty) psują kolejność osi czasu."
  > Resolution: kept; sortowanie po roku, wydarzenia bez daty na końcu.
- [new] FR-022: Autor can zapisać miejsce pochowania osoby. Priority: must-have.
  > Socrates: Counter-argument considered: "brak — rozważane razem z FR-023."
  > Resolution: kept as written (przypomnienie: podpowiada się z nagrobka — FR-002).
- [new] FR-023: Autor can prowadzić notatkę o osobie — jedno pole tekstowe w osobnej zakładce. Priority: must-have.
  > Socrates: Counter-argument considered: "notatki jako lista wpisów z datą (dziennik badań)?"
  > Resolution: kept simple; jedna notatka tekstowa wystarczy.
- [new] FR-024: Autor can zobaczyć wiek osoby — ile przeżyła (gdy zna datę zgonu) albo ile ma (tylko gdy wynik jest poniżej ok. 110 lat; inaczej wiek nie jest pokazywany). Priority: must-have.
  > Socrates: Counter-argument considered: "brak daty zgonu nie znaczy, że osoba żyje — ur. 1850 nie „ma 176 lat”."
  > Resolution: kept; „ile ma” tylko poniżej progu ok. 110 lat.

### Zachowane
- [preserved] FR-025: Autor can potwierdzić urodzenie, małżeństwo i zgon źródłem każdego rodzaju i przejść z faktu do skanu. Priority: must-have.
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).
- [preserved] FR-026: Autor can dodać rodzica, dziecko i małżonka (istniejącego lub nowego) z pustych slotów drzewa. Priority: must-have.
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).
- [preserved] FR-027: Autor can usunąć źródło po zobaczeniu listy faktów, które stracą potwierdzenie. Priority: must-have.
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).
- [modified] FR-028: Autor can w panelu osoby przechodzić do krewnych i po zamknięciu panelu być w tym samym miejscu; historia „Wstecz” między krewnymi nie jest wymagana. Priority: must-have. (Było: przechodzenie do krewnych w modalu podglądu z przyciskiem „Wstecz”, v4:FR-002.)
  > Socrates: Counter-argument considered: "czy historia „Wstecz” między krewnymi musi przetrwać przejście na panel?"
  > Resolution: changed; przejście do krewnego i powrót na to samo miejsce po zamknięciu zostają, historia „Wstecz” może zniknąć.
- [preserved] FR-029: Autor can widzieć tylko swoje dane; cudzy rekord (także w panelach, spisach i widoku przodków) to 404. Priority: must-have.
  > Socrates: Counter-argument considered: "czy to zachowanie może się zmienić przy przejściu na panele i przebudowie drzewa?"
  > Resolution: kept; zachowane bez zmian (guardrail).

### Usunięte
- [removed] Przycisk „Podgląd” na karcie drzewa — kliknięcie karty otwiera panel osoby (FR-016).
- [removed] Pole „Numer wpisu” na źródle — numer wpisu należy do powiązania źródła z faktem (FR-003); dotychczasowe wartości zostają widoczne do ręcznego przeniesienia.
- [removed] Pole „Pochodzenie” źródła — wartości przechodzą do pola archiwum (FR-009).
- [removed] Historia „Wstecz” między krewnymi w podglądzie osoby — może zniknąć przy przejściu na panel (FR-028).

### Wznowione z prd-v2
- [modified] Spis miejscowości i historia zamieszkania z prd-v2 (v2:FR-007 – v2:FR-011, S-12 – S-15), odłożone 2026-09-30, wracają w tej zmianie jako FR-007 – FR-012; spis jest prywatny dla drzewa, jak w prd-v2.

## Constraints & Compatibility

- **Zachowane bez zmian:** powiązania fakt–źródło (każdy istniejący fakt prowadzi do tego samego źródła i skanu); dodawanie rodzica, dziecka i małżonka ze slotów drzewa; usuwanie źródła z listą faktów tracących potwierdzenie; pierwszeństwo pasującego typu aktu przy wyborze źródła; własność danych (cudzy rekord = 404).
- **Zmienione świadomie:** historia „Wstecz” między krewnymi w podglądzie osoby może zniknąć przy przejściu na panel; kliknięcie karty drzewa otwiera panel zamiast przecentrowywać.
- **Przeniesienie istniejących danych (autor dopuszcza ręczne poprawki):**
  - Miejsca wydarzeń i zamieszkanie (wolny tekst) → spis miejscowości tworzony automatycznie z istniejących wartości (identyczne teksty → jedna miejscowość).
  - Jedno pole „Zamieszkanie” → pierwsza pozycja listy zamieszkań, bez lat.
  - „Pochodzenie” źródła → pole archiwum.
  - „Numer wpisu” źródła → znika jako pole źródła; dotychczasowe wartości pozostają widoczne do ręcznego przeniesienia na powiązania z faktami.
- **Ograniczenia istniejącego systemu:** CI musi przechodzić jak dziś (zob. Current System Overview); ścieżki krytyczne z guardrails (fakt–źródło, relacje ze slotów drzewa) mają być chronione testami E2E przed przebudową paneli i drzewa (dziś chroni je Pest — zob. Current System Overview).
- **Kopie zapasowe** muszą objąć nowe dane (spisy, zdjęcia osób, notatki) — tak jak dotychczasowe kopie (zob. Current System Overview).
- **Wymagania niefunkcjonalne:**
  - Drzewo, widok przodków i panele są użyteczne na ekranach od 1366 px do 2560 px szerokości; na dużym ekranie drzewo wypełnia dostępną przestrzeń.
  - Nowe dane (spisy miejscowości i parafii, zdjęcia osób, notatki, zamieszkania) są objęte kopią zapasową z utratą najwyżej 24 h danych (RPO 24 h), jak dotychczasowe dane.
  - [removed] Wymaganie z poprzednich PRD „dane drzewa i skany nie trafiają do podmiotów trzecich” przestaje obowiązywać (decyzja autora 2026-10-04) — dotyczy wszystkich danych, także zdjęć osób.
- **Termin:** 4 tygodnie, twardy termin 2026-11-04, praca po godzinach; wszystkie cztery części (opis źródła, wygląd drzewa i panel boczny, widok przodków, spis miejscowości). Koszt stałego wysiłku zaakceptowany 2026-10-04. Jeśli zabraknie czasu, pierwsze odpadają zdjęcia osób na kartach (FR-015). Rdzeń bólu: opis źródła (FR-001 – FR-006) i spis miejscowości (FR-007 – FR-012).

## Business Logic Changes

Każda miejscowość istnieje w drzewie raz, a parafie, cmentarze nagrobków, miejsca faktów (urodzenie, ślub, zgon, pochowanie) i zamieszkania osób odwołują się do niej. Scalenie duplikatu przenosi wszystkie te odwołania na właściwy wpis.

Reguła dziś: miejsca to wolny tekst przy osobie (zamieszkanie) i wydarzeniu (miejsce), więc „Luboml” i „Lubomla” to dwa różne miejsca, a nic ich nie łączy. Zmiana: autor wybiera miejsce ze spisu (z podpowiedziami i dopisywaniem w locie), a spis startuje z istniejących tekstów (identyczne teksty → jedna miejscowość). Literówki autor scala ręcznie, a wpis nieużywany może usunąć.

Reguły pomocnicze:
- **Wybór źródła (modyfikacja S-19):** dziś przy wiązaniu źródła z wydarzeniem najpierw pokazywane są akty o pasującym typie. Po zmianie autor może dodatkowo zawęzić źródła po miejscu (miejscowość parafii aktu lub cmentarza nagrobka) i roku; pierwszeństwo pasującego typu zostaje.
- **Opis zależy od rodzaju źródła:** akt metrykalny — parafia, rok, typ aktu, archiwum; nagrobek — cmentarz i osoby pochowane. Miejsce pochowania osoby z listy pochowanych podpowiada się cmentarzem nagrobka (można zmienić).
- **Numer wpisu należy do powiązania źródła z faktem,** nie do źródła — jeden skan, wiele wpisów, wiele osób.
- **Wiek:** ile przeżyła, gdy znana jest data zgonu; ile ma tylko wtedy, gdy wynik jest poniżej ok. 110 lat — inaczej wiek nie jest pokazywany.

## Access Control Changes

No access control changes — current model preserved: jedno drzewo na konto, otwarta rejestracja, każdy widzi i edytuje tylko swoje dane, a cudzy rekord to 404 (także w panelach, widoku przodków i spisie miejscowości). Spis miejscowości i spis parafii są prywatne dla drzewa, jak w prd-v2.

## Non-Goals

- **Brak udostępniania drzewa rodzinie** — nadal jeden użytkownik na drzewo; model dostępu bez zmian.
- **Brak dodawania osób i relacji z widoku przodków** — widok przodków służy do oglądania; relacje dodaje się na drzewie-arkuszu.
- **Brak importu/eksportu GEDCOM** — dane wchodzą ręcznie, jak dotąd.
- **Brak nazw alternatywnych i datowanej hierarchii jednostek** — dawne nazwy trafiają do opisu; przynależność historyczna to jedno pole tekstowe miejscowości.
- **Brak świadków ślubu** — panel małżeństwa pokazuje datę, miejsce, źródło i dzieci pary.
- **Brak wielu nazwisk po kolejnych mężach** — osoba ma nazwisko i nazwisko rodowe; reszta w notatce.
- **Brak daty pogrzebu i potwierdzania zamieszkania źródłem** — pogrzeb to samo miejsce pochowania; zamieszkanie to notatka badawcza bez źródła.
- **Brak zarządzanej listy archiwów** — archiwum to tekst z podpowiedziami (FR-009).

## Open Questions

1. ~~**Czy NFR „dane nie trafiają do podmiotów trzecich” (z poprzednich PRD) nadal obowiązuje?**~~ — Rozstrzygnięte 2026-10-04 (autor): nie obowiązuje, dla żadnych danych, także zdjęć osób (FR-015). Zob. Constraints & Compatibility.
2. ~~**FR-004: czy szukanie źródła po miejscu obejmuje miejscowość parafii i cmentarza?**~~ — Rozstrzygnięte 2026-10-04 (autor): tak — akt pasuje przez miejscowość siedziby parafii, nagrobek przez miejscowość cmentarza.
