---
project: "root-keeper"
context_type: brownfield
created: 2026-09-28
updated: 2026-09-28
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 2
  hard_deadline: 2026-11-04
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "pain"
      decision: "losing tree context when going to a separate preview page; inconsistency — add/edit already in a modal"
    - topic: "tree responsibility for relations"
      decision: "all relation editing (parent, child, spouse; new AND existing person) only on the tree; preview shows relations read-only"
    - topic: "preview page"
      decision: "preview is a modal everywhere; /persons/{id} page removed"
    - topic: "must preserve (phase 1)"
      decision: "facts and confirmations (incl. opening scan preview), relation rules, tree navigation (re-centre, probant)"
    - topic: "person delete"
      decision: "stays in the preview modal, with the current confirmation"
    - topic: "navigation inside modal"
      decision: "clicking a relative swaps modal content; page underneath stays; scan preview links open the modal too"
    - topic: "after adding a person"
      decision: "preview modal of the new person opens — superseded in Socrates round (FR-003): tree centred on new person"
    - topic: "marriage"
      decision: "adding/removing spouse on the tree; marriage date, place, confirmation in the preview modal"
    - topic: "primary persona"
      decision: "unchanged — the author"
    - topic: "auth / roles"
      decision: "no change — own tree only, other user's record is 404 (modal and tree actions too)"
    - topic: "relation to S-21–S-23"
      decision: "absorbs and extends them — tree slots offer existing OR new person; S-21–S-23 to be replanned"
    - topic: "timeline"
      decision: "inside 2026-11-04 deadline; gazetteer S-12–S-15 slips first"
    - topic: "old /persons/{id} URLs"
      decision: "may stop working — no redirects"
    - topic: "scope vs budget"
      decision: "scoped down to stages: (1) preview modal, relation editing temporarily stays in preview; (2) relations on tree, then preview read-only and page removed; 2 weeks"
    - topic: "secondary criterion"
      decision: "linking an existing parent from the tree takes fewer steps than today from the preview"
    - topic: "guardrails"
      decision: "facts+confirmations+scan preview, relation rules, tree navigation, no gap in relation editing, person delete with confirmation"
    - topic: "FR list"
      decision: "13 FRs as drafted from phase 1-3 decisions, all must-have"
    - topic: "tree edit range"
      decision: "around the centre: parent slots of any visible person, child of shown pair, spouse only at centre, removal on centre's relations"
    - topic: "US-01"
      decision: "Jan's modal -> close -> link existing Józef in father slot, stays on tree"
    - topic: "Socrates round"
      decision: "FR-001 full-screen on narrow; FR-002 Back button; FR-003 after add -> tree centred on new person (overrides phase 1); FR-004 gender filter in father/mother slot; FR-005 kept, rules block with reason; FR-006 'connect as marriage' between shown parents; FR-007 marriage removal lists lost data; FR-008 'show on tree' link; FR-009 kept; FR-010 scan opens full page, leaves modal; FR-011 picker hides rule-breaking people; FR-012 probant action in modal, kept; FR-013 deleting centre -> tree back to probant"
    - topic: "business logic"
      decision: "no domain rule change — navigation/view-responsibility change; slot filters and back-to-probant apply existing rules"
    - topic: "order vs sources"
      decision: "preview modal (stage 1) before S-17/S-18; sources built into the modal"
    - topic: "data migration"
      decision: "none"
    - topic: "NFR"
      decision: "no new NFRs"
    - topic: "product framing"
      decision: "no change — web-app, small; hard deadline 2026-11-04, after hours"
    - topic: "non-goals"
      decision: "no drag-and-drop on tree; no modal URL / no /persons redirects; no duplicate merging"
  frs_drafted: 13
  quality_check_status: accepted
---

# root-keeper — shape notes (brownfield)

Poprzednie sesje: MVP `context/foundation/archive/shape-notes-2026-09-28-0612.md`, prd-v2 (opisy skanów + spis miejscowości) `context/foundation/archive/shape-notes-2026-09-28-1130.md`, prd-v3 (źródła + drzewo edytowalne + modal osoby) `context/foundation/archive/shape-notes-2026-09-28-1315.md`.

## Seed idea

> edycja i dodawanie osoby jest w modalu; tak samo w modalu chcialbym miec widok osoby; widok drzewa powinien byc bardziej odpowiedzialny za relacje, choc na widoku osoby to też musi byc widoczne;

## Current System

root-keeper — prywatne drzewo genealogiczne (Laravel 13 + Inertia v3/React 19 z TypeScript, shadcn/ui, PostgreSQL, samodzielny hosting w Dockerze), jedno drzewo na konto, każdy widzi tylko swoje dane. Kamień milowy M-1 otwarty; z prd-v3 zrobione S-20 (osoba dodawana i poprawiana w modalu); S-16 – S-19 (źródła) i S-21 – S-23 (nowy rodzic / dziecko / małżonek z drzewa) jeszcze nie rozpoczęte.

Stan istotny dla tej zmiany:
- **Podgląd osoby** to osobna strona `/persons/{id}`: nagłówek (probant, „Drzewo”, „Edytuj” → modal, „Usuń”), dane podstawowe, karty rodziców i dzieci (dodawanie przez wybór istniejącej osoby, usuwanie relacji), urodzenie, małżeństwa (dodawanie/usuwanie w oknie dialogowym), zgon — z potwierdzającymi skanami.
- **Widok drzewa** `/tree/{id}` to arkusz rodziny; karta osoby ma dwie akcje: wyśrodkuj drzewo i przejdź do podglądu. Relacji na drzewie się nie edytuje (S-21 – S-23 dopiero dodadzą tworzenie nowych osób z pustych slotów).
- **Do strony podglądu prowadzi** 7 miejsc: lista osób, nagłówek i karty drzewa, krewni i małżonek na podglądzie, podgląd skanu (osoby, których fakty skan potwierdza) oraz przekierowanie po dodaniu osoby.

Użytkownicy: autor (genealog pracujący na własnych materiałach).

## Vision & Problem Statement

Po przeniesieniu dodawania i edycji osoby do modala (S-20) zostały dwie luki:

1. **Utrata kontekstu drzewa.** Oglądanie osoby to przejście z drzewa na osobną stronę i powrót — autor gubi miejsce, w którym pracował.
2. **Niespójność.** Dodawanie i edycja są w modalu, a podgląd osoby nadal jest osobną stroną.

Zmiana: podgląd osoby staje się modalem otwieranym z każdego miejsca (drzewo, lista, podgląd skanu), a strona `/persons/{id}` znika. Drzewo przejmuje całą edycję relacji — dodawanie i usuwanie rodzica, dziecka i małżonka, zarówno nowej, jak i istniejącej osoby. Podgląd nadal pokazuje relacje, ale tylko do odczytu.

Kategoria zmiany: znacząca funkcja (zmiana nawigacji i odpowiedzialności widoków).

## User & Persona

Bez zmian: jedynym realnym użytkownikiem jest autor aplikacji, genealog prowadzący badania na własnych materiałach. Zmiana dotyczy chwil, gdy pracuje na drzewie i zagląda w szczegóły osób oraz gdy łączy osoby relacjami.

## Access Control

No changes planned — current model preserved: otwarta rejestracja, jedno drzewo na konto, każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnego drzewa. Modal podglądu i akcje relacji na drzewie podlegają tej samej regule — cudza osoba to 404.

## Success Criteria

Zmiana etapowa (bez okna, w którym jakiejś relacji nie da się edytować):
1. **Etap 1 — podgląd w modalu:** kliknięcie osoby na drzewie, liście lub podglądzie skanu otwiera modal podglądu nad bieżącą stroną; kliknięcie krewnego podmienia treść modala; zamknięcie zostawia stronę pod spodem bez zmian. Edycja relacji tymczasowo zostaje w modalu podglądu.
2. **Etap 2 — relacje na drzewie:** z drzewa dodaje się (istniejącą lub nową osobę) i usuwa rodzica, dziecko i małżonka. Dopiero wtedy relacje w podglądzie stają się tylko do odczytu, a strona `/persons/{id}` znika.

### Primary
Autor klika osobę na drzewie, ogląda jej dane, fakty z potwierdzeniami i relacje w modalu, przechodzi w modalu do rodzica, zamyka modal i jest dokładnie w tym samym miejscu drzewa; brakującego ojca podłącza z pustego slotu na drzewie (istniejąca osoba albo nowa), a zbędną relację usuwa z drzewa.

### Secondary
Podłączenie istniejącego rodzica z drzewa wymaga mniej kroków niż dziś z podglądu osoby.

### Guardrails
- Fakty i potwierdzenia: urodzenie, małżeństwo i zgon z potwierdzającymi skanami/źródłami widoczne w modalu; podgląd skanu (powiększenie) nadal otwiera się z faktu.
- Reguły relacji: maks. dwoje rodziców, nikt nie jest własnym przodkiem — także przy dodawaniu z drzewa.
- Nawigacja drzewa: wyśrodkowanie drzewa na osobie i ustawianie probanta działają jak dziś.
- Brak luki w edycji relacji: w każdym momencie wdrażania każdą relację da się dodać i usunąć z jakiegoś miejsca.
- Usuwanie osoby z modala pokazuje te same skutki i wymaga jawnego potwierdzenia.

## Functional Requirements

### Podgląd osoby w modalu
- FR-001: Użytkownik otwiera podgląd osoby w modalu z drzewa, listy osób i podglądu skanu; zamknięcie modala zostawia stronę pod spodem bez zmian; na wąskich ekranach modal zajmuje cały ekran. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "dane, relacje, trzy fakty i małżeństwa to za dużo treści na modal, zwłaszcza na telefonie." Resolution: changed — na wąskich ekranach modal pełnoekranowy (jak formularz osoby w S-20).
- FR-002: Użytkownik w modalu podglądu klika krewnego (rodzic, dziecko, małżonek) i modal podmienia treść na tę osobę; przycisk „Wstecz” w modalu wraca do poprzednio oglądanej osoby. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "po trzech przeskokach (ojciec → dziadek → wujek) nie wiadomo, jak wrócić." Resolution: changed — przycisk „Wstecz” w modalu.
- FR-003: Po dodaniu osoby z listy użytkownik trafia na drzewo wyśrodkowane na nowej osobie (dziś: przekierowanie na stronę podglądu). Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "drzewo jest miejscem relacji — nowa osoba bez relacji wymaga podłączenia, nie oglądania." Resolution: changed — zamiast modala podglądu przejście na drzewo wyśrodkowane na nowej osobie (zmienia decyzję z fazy 1).
- FR-008: Podgląd osoby pokazuje relacje (rodzice, dzieci, małżonkowie) tylko do odczytu, z odnośnikiem „Pokaż na drzewie” przy relacjach; data, miejsce i potwierdzenie małżeństwa nadal edytuje się w podglądzie. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "oglądając osobę z listy lub skanu i widząc błąd w relacji, trzeba jakoś trafić na drzewo." Resolution: changed — odnośnik „Pokaż na drzewie” przy relacjach (drzewo wyśrodkowane na tej osobie).
- FR-009: Strona `/persons/{id}` znika; stare adresy mogą przestać działać. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "bez adresu nie da się zapisać ani wysłać linku do konkretnej osoby." Resolution: kept; jedynym użytkownikiem jest autor, drzewo z centrum wystarcza jako adres.

### Relacje na drzewie
Zakres: sloty rodziców każdej widocznej osoby (także dziadków), dziecko pokazanej pary, małżonek tylko przy osobie w centrum, połączenie pokazanych rodziców w małżeństwo, usuwanie relacji osoby w centrum; dla pozostałych — wyśrodkowanie drzewa na nich.
- FR-004: Użytkownik z pustego slotu rodzica na drzewie podłącza istniejącą osobę albo tworzy nową (S-21: tylko nowa); w slocie ojca/matki wybór pokazuje tylko osoby pasującej płci. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "do slotu »ojciec« można wybrać kobietę." Resolution: changed — w slocie ojca/matki wybór filtruje po płci (nowa osoba — płeć ustawiona i zablokowana jak w prd-v3).
- FR-005: Użytkownik dodaje z drzewa dziecko pokazanej pary — istniejącą osobę albo nową (S-22: tylko nowa). Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "istniejące dziecko ma już jednego rodzica spoza pary — podłączenie dałoby mu troje rodziców." Resolution: kept; reguły relacji blokują, a komunikat mówi dlaczego.
- FR-006: Użytkownik z pustego slotu małżonka przy osobie w centrum podłącza istniejącą osobę albo tworzy nowego małżonka (S-23: tylko nowy); gdy pokazani na arkuszu ojciec i matka nie mają małżeństwa, drzewo oferuje „Połącz jako małżeństwo” między nimi. Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "rodzice na arkuszu często nie są w danych małżeństwem — trzeba wyśrodkować na ojcu, żeby ich połączyć." Resolution: changed — akcja „Połącz jako małżeństwo” między pokazanymi rodzicami; pusty slot małżonka nadal tylko przy centrum.
- FR-007: Użytkownik usuwa z drzewa relację rodzic–dziecko i małżeństwo po potwierdzeniu (dziś: z podglądu osoby); potwierdzenie usunięcia małżeństwa wymienia, co zginie (data, miejsce, potwierdzenia). Priority: must-have. Change: modified
  > Socrates: Counter-argument considered: "małżeństwo to więcej niż relacja — usunięcie kasuje datę, miejsce i potwierdzenia." Resolution: changed — potwierdzenie wymienia tracone dane.

### Zachowane
- FR-010: Fakty (urodzenie, małżeństwo, zgon) z potwierdzeniami działają w modalu; otwarcie skanu z faktu opuszcza modal i prowadzi na pełną stronę podglądu skanu. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "modal na modalu: podgląd → wiązanie źródła → powiększenie skanu; powiększenie potrzebuje całego ekranu." Resolution: changed — otwarcie skanu wychodzi z modala na pełną stronę skanu.
- FR-011: Reguły relacji (maks. dwoje rodziców, nikt nie jest własnym przodkiem) obowiązują przy każdej edycji z drzewa; wybór osoby ukrywa osoby, których podłączenie złamałoby regułę. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "wybór pokazuje osoby zakazane i błąd pojawia się dopiero po wyborze." Resolution: changed — wybór ukrywa osoby tworzące cykl lub trzeciego rodzica.
- FR-012: Wyśrodkowanie drzewa na osobie działa jak dziś; „Ustaw jako probanta” przenosi się do modala podglądu. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "ustawienie probanta z modala otwartego z listy lub skanu zmienia drzewo, którego w tej chwili nie widać." Resolution: kept; akcja rzadka, akceptowalne.
- FR-013: Usunięcie osoby z modala pokazuje te same skutki i wymaga jawnego potwierdzenia; po usunięciu osoby z centrum drzewo wraca do probanta (usunięcie probanta — jak dziś, drzewo bez probanta). Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "usunięcie osoby z centrum zostawia drzewo bez centrum." Resolution: changed — drzewo wraca do probanta; usunięcie samego probanta zachowuje dzisiejsze zachowanie.

## User Stories

### US-01: Autor ogląda osobę i podłącza brakującego ojca bez wychodzenia z drzewa

Given drzewo wyśrodkowane na Janie, z pustym slotem „nieznany ojciec”, a Józef istnieje już w drzewie jako osoba bez relacji z Janem
When autor klika kartę Jana, ogląda w modalu jego fakty i relacje, zamyka modal, a następnie w slocie ojca wybiera istniejącego Józefa
Then drzewo, bez opuszczania go, pokazuje Józefa jako ojca Jana, a modal podglądu Jana pokazuje Józefa w sekcji „Rodzice” tylko do odczytu

Wcześniej: podgląd był osobną stroną, a ojca podłączało się z podglądu osoby, nie z drzewa.

#### Acceptance Criteria
- Zamknięcie modala zostawia drzewo w tym samym miejscu (ta sama osoba w centrum, ta sama para).
- Podłączenie Józefa podlega regułom relacji (maks. dwoje rodziców, brak cyklu).
- W modalu podglądu nie ma akcji dodawania ani usuwania rodzica.

## Business Logic

No domain logic change. This is a navigation and view-responsibility change.

Obecne reguły pozostają bez zmian: fakt osoby jest „potwierdzony”, gdy jest powiązany z co najmniej jednym źródłem (skanem); osoba ma maks. dwoje rodziców; nikt nie jest własnym przodkiem; małżeństwo jest jednym wydarzeniem obojga małżonków. Zmienia się tylko miejsce edycji relacji (drzewo zamiast podglądu osoby). Filtry wyboru w slotach (płeć w slocie ojca/matki, ukrywanie osób łamiących reguły) oraz powrót drzewa do probanta po usunięciu osoby z centrum tylko stosują istniejące reguły w interfejsie.

## Constraints & Preserved Behavior

- **Migracja danych:** brak — osoby, relacje, małżeństwa i potwierdzenia bez zmian; zmienia się wyłącznie miejsce oglądania i edycji.
- **Kompatybilność wsteczna:** strona `/persons/{id}` znika, stare adresy mogą przestać działać (jedynym użytkownikiem jest autor; bez przekierowań). 7 miejsc prowadzących dziś do podglądu (lista, nagłówek i karty drzewa, krewni i małżonek w podglądzie, podgląd skanu, przekierowanie po dodaniu osoby) dostaje nowy cel.
- **Etapowanie:** etap 1 (podgląd w modalu, edycja relacji tymczasowo w modalu) przed etapem 2 (relacje na drzewie, podgląd tylko do odczytu) — w żadnym momencie nie może zabraknąć miejsca do dodania lub usunięcia którejś relacji.
- **Kolejność względem źródeł (prd-v3):** modal podglądu (etap 1) idzie przed S-17/S-18, żeby rodzaj źródła przy fakcie i modale źródeł powstały od razu w modalu podglądu.
- **S-21 – S-23 (prd-v3):** wchłonięte i rozszerzone (istniejąca albo nowa osoba, usuwanie, połączenie rodziców) — do przeplanowania.
- **Zachowane:** fakty z potwierdzeniami i otwieranie podglądu skanu; reguły relacji; wyśrodkowanie drzewa i probant; usuwanie osoby z listą skutków i jawnym potwierdzeniem; prywatność drzewa (cudzy rekord = 404).

## Non-Functional Requirements

Brak nowych NFR — istniejące (prywatność danych drzewa, czytelność powiększenia skanu) obejmują modal podglądu i akcje relacji na drzewie.

## Product framing

- Typ produktu: bez zmian — aplikacja webowa (`web-app`).
- Skala: bez zmian — autor, mała (`small`).
- Termin: twardo 2026-11-04 (wspólny z prd-v3), praca po godzinach; ta zmiana 2 tygodnie. Przy braku czasu spis miejscowości (S-12 – S-15) przesuwa się jako pierwszy.

## Non-Goals

- **Brak przeciągania kart na drzewie** — relacje łączy się przez sloty i wybór osoby, nie przez drag-and-drop.
- **Brak adresu modala** — modal podglądu nie ma własnego adresu (deep linku); brak przekierowań ze starych `/persons/{id}` (FR-009).
- **Brak scalania duplikatów osób** — wybór „istniejąca osoba” w slocie zmniejsza ryzyko duplikatów, ale już zdublowanych osób się nie scala; poprawiane ręcznie.

## Quality cross-check

Wszystkie elementy obecne (Access Control, Business Logic, artefakty, koszt czasu — 2 tygodnie etapami, Non-Goals, Preserved behavior). Brak luk.
