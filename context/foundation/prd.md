---
project: "root-keeper"
version: 1
status: draft
created: 2026-09-28
context_type: brownfield
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 2
  hard_deadline: 2026-11-04
  after_hours_only: true
---

# root-keeper — podgląd osoby w modalu i relacje edytowane na drzewie (zmiana brownfield)

Poprzednie PRD: MVP — wcześniejsza treść `context/foundation/prd.md` w historii git (commit `e74243f`), opisy skanów i spis miejscowości `context/foundation/prd-v2.md`, źródła i drzewo edytowalne `context/foundation/prd-v3.md`. Notatki z kształtowania tej zmiany: `context/foundation/shape-notes.md`. Wymagania tej zmiany numerowane od nowa; odwołania do wcześniejszych PRD z prefiksem (`v3:FR-001`).

## Current System Overview

root-keeper to prywatne drzewo genealogiczne: jedno drzewo na konto, każdy widzi tylko swoje dane.

- **Architektura i stack:** monolit Laravel 13 + Inertia v3/React 19 z TypeScript, shadcn/ui, PostgreSQL, samodzielny hosting w Dockerze. Szczegóły: `context/foundation/tech-stack.md`.
- **Użytkownicy:** autor (genealog pracujący na własnych materiałach); skala mała.
- **Funkcje dziś:** kamień milowy M-1 otwarty; z prd-v3 zrobione S-20 (osoba dodawana i poprawiana w modalu); S-16 – S-19 (źródła) i S-21 – S-23 (nowy rodzic / dziecko / małżonek z drzewa) jeszcze nie rozpoczęte.

Stan istotny dla tej zmiany:
- **Podgląd osoby** to osobna strona `/persons/{id}`: nagłówek (probant, „Drzewo”, „Edytuj” → modal, „Usuń”), dane podstawowe, karty rodziców i dzieci (dodawanie przez wybór istniejącej osoby, usuwanie relacji), urodzenie, małżeństwa (dodawanie/usuwanie w oknie dialogowym), zgon — z potwierdzającymi skanami.
- **Widok drzewa** `/tree/{id}` to arkusz rodziny; karta osoby ma dwie akcje: wyśrodkuj drzewo i przejdź do podglądu. Relacji na drzewie się nie edytuje (S-21 – S-23 dopiero dodadzą tworzenie nowych osób z pustych slotów).
- **Do strony podglądu prowadzi** 7 miejsc: lista osób, nagłówek i karty drzewa, krewni i małżonek na podglądzie, podgląd skanu (osoby, których fakty skan potwierdza) oraz przekierowanie po dodaniu osoby.

## Problem Statement & Motivation

Po przeniesieniu dodawania i edycji osoby do modala (S-20) zostały dwie luki:

1. **Utrata kontekstu drzewa.** Oglądanie osoby to przejście z drzewa na osobną stronę i powrót — autor gubi miejsce, w którym pracował.
2. **Niespójność.** Dodawanie i edycja są w modalu, a podgląd osoby nadal jest osobną stroną.

Zmiana: podgląd osoby staje się modalem otwieranym z każdego miejsca (drzewo, lista, podgląd skanu), a strona `/persons/{id}` znika. Drzewo przejmuje całą edycję relacji — dodawanie i usuwanie rodzica, dziecka i małżonka, zarówno nowej, jak i istniejącej osoby. Podgląd nadal pokazuje relacje, ale tylko do odczytu.

Dlaczego teraz: S-20 przeniósł formularz osoby do modala, więc podgląd na osobnej stronie jest ostatnim miejscem, które wyrywa autora z drzewa; a S-21 – S-23 (tworzenie osób z drzewa) nie są jeszcze rozpoczęte, więc drzewo można od razu zaprojektować jako jedyne miejsce edycji relacji. Obecne obejście: przejście z drzewa na stronę podglądu, edycja relacji tam i powrót na drzewo z ponownym wyśrodkowaniem.

Kategoria zmiany: znacząca funkcja (zmiana nawigacji i odpowiedzialności widoków).

## User & Persona

Bez zmian: jedynym realnym użytkownikiem jest autor aplikacji, genealog prowadzący badania na własnych materiałach. Zmiana dotyczy chwil, gdy pracuje na drzewie i zagląda w szczegóły osób oraz gdy łączy osoby relacjami.

## Success Criteria

Zmiana etapowa (bez okna, w którym jakiejś relacji nie da się edytować):
1. **Etap 1 — podgląd w modalu:** kliknięcie osoby na drzewie, liście lub podglądzie skanu otwiera modal podglądu nad bieżącą stroną; kliknięcie krewnego podmienia treść modala; zamknięcie zostawia stronę pod spodem bez zmian. Edycja relacji tymczasowo zostaje w modalu podglądu.
2. **Etap 2 — relacje na drzewie:** z drzewa dodaje się (istniejącą lub nową osobę) i usuwa rodzica, dziecko i małżonka. Dopiero wtedy relacje w podglądzie stają się tylko do odczytu, a strona `/persons/{id}` znika.

### Primary
- Autor klika osobę na drzewie, ogląda jej dane, fakty z potwierdzeniami i relacje w modalu, przechodzi w modalu do rodzica, zamyka modal i jest dokładnie w tym samym miejscu drzewa; brakującego ojca podłącza z pustego slotu na drzewie (istniejąca osoba albo nowa), a zbędną relację usuwa z drzewa.

### Secondary
- Podłączenie istniejącego rodzica z drzewa wymaga mniej kroków niż dziś z podglądu osoby.

### Guardrails
- Fakty i potwierdzenia: urodzenie, małżeństwo i zgon z potwierdzającymi skanami/źródłami widoczne w modalu; podgląd skanu (powiększenie) nadal otwiera się z faktu.
- Reguły relacji: maks. dwoje rodziców, nikt nie jest własnym przodkiem — także przy dodawaniu z drzewa.
- Nawigacja drzewa: wyśrodkowanie drzewa na osobie i ustawianie probanta działają jak dziś.
- Brak luki w edycji relacji: w każdym momencie wdrażania każdą relację da się dodać i usunąć z jakiegoś miejsca.
- Usuwanie osoby z modala pokazuje te same skutki i wymaga jawnego potwierdzenia.

## User Stories

### US-01: Autor ogląda osobę i podłącza brakującego ojca bez wychodzenia z drzewa

- **Given** drzewo wyśrodkowane na Janie, z pustym slotem „nieznany ojciec”, a Józef istnieje już w drzewie jako osoba bez relacji z Janem
- **When** autor klika kartę Jana, ogląda w modalu jego fakty i relacje, zamyka modal, a następnie w slocie ojca wybiera istniejącego Józefa
- **Then** drzewo, bez opuszczania go, pokazuje Józefa jako ojca Jana, a modal podglądu Jana pokazuje Józefa w sekcji „Rodzice” tylko do odczytu

Wcześniej: podgląd był osobną stroną, a ojca podłączało się z podglądu osoby, nie z drzewa.

#### Acceptance Criteria
- Zamknięcie modala zostawia drzewo w tym samym miejscu (ta sama osoba w centrum, ta sama para).
- Podłączenie Józefa podlega regułom relacji (maks. dwoje rodziców, brak cyklu).
- W modalu podglądu nie ma akcji dodawania ani usuwania rodzica.

## Scope of Change

### Podgląd osoby w modalu
- [new] FR-001: Użytkownik otwiera podgląd osoby w modalu z drzewa, listy osób i podglądu skanu; zamknięcie modala zostawia stronę pod spodem bez zmian; na wąskich ekranach modal zajmuje cały ekran. Priority: must-have.
  > Socrates: Counter-argument considered: "dane, relacje, trzy fakty i małżeństwa to za dużo treści na modal, zwłaszcza na telefonie." Resolution: changed — na wąskich ekranach modal pełnoekranowy (jak formularz osoby w S-20).
- [new] FR-002: Użytkownik w modalu podglądu klika krewnego (rodzic, dziecko, małżonek) i modal podmienia treść na tę osobę; przycisk „Wstecz” w modalu wraca do poprzednio oglądanej osoby. Priority: must-have.
  > Socrates: Counter-argument considered: "po trzech przeskokach (ojciec → dziadek → wujek) nie wiadomo, jak wrócić." Resolution: changed — przycisk „Wstecz” w modalu.
- [modified] FR-003: Po dodaniu osoby z listy użytkownik trafia na drzewo wyśrodkowane na nowej osobie. Priority: must-have. (Było: przekierowanie na stronę podglądu.)
  > Socrates: Counter-argument considered: "drzewo jest miejscem relacji — nowa osoba bez relacji wymaga podłączenia, nie oglądania." Resolution: changed — zamiast modala podglądu przejście na drzewo wyśrodkowane na nowej osobie (zmienia decyzję z fazy 1).
- [modified] FR-008: Podgląd osoby pokazuje relacje (rodzice, dzieci, małżonkowie) tylko do odczytu, z odnośnikiem „Pokaż na drzewie” przy relacjach; data, miejsce i potwierdzenie małżeństwa nadal edytuje się w podglądzie. Priority: must-have. (Było: dodawanie i usuwanie rodziców, dzieci i małżeństw z podglądu osoby.)
  > Socrates: Counter-argument considered: "oglądając osobę z listy lub skanu i widząc błąd w relacji, trzeba jakoś trafić na drzewo." Resolution: changed — odnośnik „Pokaż na drzewie” przy relacjach (drzewo wyśrodkowane na tej osobie).
- [removed] FR-009: Strona `/persons/{id}` znika; stare adresy mogą przestać działać. Priority: must-have. Uzasadnienie: podgląd żyje wyłącznie w modalu.
  > Socrates: Counter-argument considered: "bez adresu nie da się zapisać ani wysłać linku do konkretnej osoby." Resolution: kept; jedynym użytkownikiem jest autor, drzewo z centrum wystarcza jako adres.

### Relacje na drzewie
Zakres: sloty rodziców każdej widocznej osoby (także dziadków), dziecko pokazanej pary, małżonek tylko przy osobie w centrum, połączenie pokazanych rodziców w małżeństwo, usuwanie relacji osoby w centrum; dla pozostałych — wyśrodkowanie drzewa na nich.
- [modified] FR-004: Użytkownik z pustego slotu rodzica na drzewie podłącza istniejącą osobę albo tworzy nową; w slocie ojca/matki wybór pokazuje tylko osoby pasującej płci. Priority: must-have. (Było w v3:FR-001 / S-21: tylko nowa osoba.)
  > Socrates: Counter-argument considered: "do slotu »ojciec« można wybrać kobietę." Resolution: changed — w slocie ojca/matki wybór filtruje po płci (nowa osoba — płeć ustawiona i zablokowana jak w prd-v3).
- [modified] FR-005: Użytkownik dodaje z drzewa dziecko pokazanej pary — istniejącą osobę albo nową. Priority: must-have. (Było w v3:FR-002 / S-22: tylko nowa osoba.)
  > Socrates: Counter-argument considered: "istniejące dziecko ma już jednego rodzica spoza pary — podłączenie dałoby mu troje rodziców." Resolution: kept; reguły relacji blokują, a komunikat mówi dlaczego.
- [modified] FR-006: Użytkownik z pustego slotu małżonka przy osobie w centrum podłącza istniejącą osobę albo tworzy nowego małżonka; gdy pokazani na arkuszu ojciec i matka nie mają małżeństwa, drzewo oferuje „Połącz jako małżeństwo” między nimi. Priority: must-have. (Było w v3:FR-003 / S-23: tylko nowy małżonek.)
  > Socrates: Counter-argument considered: "rodzice na arkuszu często nie są w danych małżeństwem — trzeba wyśrodkować na ojcu, żeby ich połączyć." Resolution: changed — akcja „Połącz jako małżeństwo” między pokazanymi rodzicami; pusty slot małżonka nadal tylko przy centrum.
- [modified] FR-007: Użytkownik usuwa z drzewa relację rodzic–dziecko i małżeństwo po potwierdzeniu; potwierdzenie usunięcia małżeństwa wymienia, co zginie (data, miejsce, potwierdzenia). Priority: must-have. (Było: usuwanie z podglądu osoby.)
  > Socrates: Counter-argument considered: "małżeństwo to więcej niż relacja — usunięcie kasuje datę, miejsce i potwierdzenia." Resolution: changed — potwierdzenie wymienia tracone dane.

### Zachowane
- [preserved] FR-010: Fakty (urodzenie, małżeństwo, zgon) z potwierdzeniami działają w modalu; otwarcie skanu z faktu opuszcza modal i prowadzi na pełną stronę podglądu skanu. Priority: must-have.
  > Socrates: Counter-argument considered: "modal na modalu: podgląd → wiązanie źródła → powiększenie skanu; powiększenie potrzebuje całego ekranu." Resolution: changed — otwarcie skanu wychodzi z modala na pełną stronę skanu.
- [preserved] FR-011: Reguły relacji (maks. dwoje rodziców, nikt nie jest własnym przodkiem) obowiązują przy każdej edycji z drzewa; wybór osoby ukrywa osoby, których podłączenie złamałoby regułę. Priority: must-have.
  > Socrates: Counter-argument considered: "wybór pokazuje osoby zakazane i błąd pojawia się dopiero po wyborze." Resolution: changed — wybór ukrywa osoby tworzące cykl lub trzeciego rodzica.
- [preserved] FR-012: Wyśrodkowanie drzewa na osobie działa jak dziś; „Ustaw jako probanta” przenosi się do modala podglądu. Priority: must-have.
  > Socrates: Counter-argument considered: "ustawienie probanta z modala otwartego z listy lub skanu zmienia drzewo, którego w tej chwili nie widać." Resolution: kept; akcja rzadka, akceptowalne.
- [preserved] FR-013: Usunięcie osoby z modala pokazuje te same skutki i wymaga jawnego potwierdzenia; po usunięciu osoby z centrum drzewo wraca do probanta (usunięcie probanta — jak dziś, drzewo bez probanta). Priority: must-have.
  > Socrates: Counter-argument considered: "usunięcie osoby z centrum zostawia drzewo bez centrum." Resolution: changed — drzewo wraca do probanta; usunięcie samego probanta zachowuje dzisiejsze zachowanie.

### Wchłonięte z prd-v3
- [modified] v3:FR-001 – v3:FR-003 (S-21 – S-23, nowy rodzic / dziecko / małżonek z drzewa) — rozszerzone o podłączanie istniejącej osoby, usuwanie relacji i połączenie rodziców (FR-004 – FR-007).
- [modified] Decyzja prd-v3 „pusty slot zawsze tworzy nową osobę; łączenie z istniejącą zostaje na podglądzie osoby” — zastąpiona: łączenie z istniejącą osobą odbywa się na drzewie (FR-004 – FR-006).
- [modified] Non-goal prd-v3 „brak modali dla faktów i relacji” — relacje nie trafiają do modali, lecz na drzewo; fakty zostają w podglądzie (FR-008, FR-010).

## Constraints & Compatibility

- **Dane istniejące:** bez zmian — osoby, relacje, małżeństwa i potwierdzenia pozostają takie same; zmienia się wyłącznie miejsce oglądania i edycji.
- **Kompatybilność wsteczna:** strona `/persons/{id}` znika, stare adresy mogą przestać działać (jedynym użytkownikiem jest autor; bez przekierowań). 7 miejsc prowadzących dziś do podglądu (lista, nagłówek i karty drzewa, krewni i małżonek w podglądzie, podgląd skanu, przekierowanie po dodaniu osoby) dostaje nowy cel.
- **Etapowanie:** etap 1 (podgląd w modalu, edycja relacji tymczasowo w modalu) przed etapem 2 (relacje na drzewie, podgląd tylko do odczytu) — w żadnym momencie nie może zabraknąć miejsca do dodania lub usunięcia którejś relacji.
- **Kolejność względem źródeł (prd-v3):** modal podglądu (etap 1) idzie przed S-17/S-18, żeby rodzaj źródła przy fakcie i modale źródeł powstały od razu w modalu podglądu.
- **S-21 – S-23 (prd-v3):** wchłonięte i rozszerzone (istniejąca albo nowa osoba, usuwanie, połączenie rodziców) — do przeplanowania.
- **Termin:** wspólny z prd-v3 — 2026-11-04, praca po godzinach; ta zmiana 2 tygodnie. Przy braku czasu spis miejscowości (S-12 – S-15) przesuwa się jako pierwszy.
- **Zachowane:** fakty z potwierdzeniami i otwieranie podglądu skanu; reguły relacji; wyśrodkowanie drzewa i probant; usuwanie osoby z listą skutków i jawnym potwierdzeniem; prywatność drzewa (cudzy rekord = 404).
- **Wymagania niefunkcjonalne:** brak nowych — istniejące (prywatność danych drzewa, czytelność powiększenia skanu) obejmują podgląd osoby i akcje relacji na drzewie.

## Business Logic Changes

No domain logic change. This is a navigation and view-responsibility change.

Obecne reguły pozostają bez zmian: fakt osoby jest „potwierdzony”, gdy jest powiązany z co najmniej jednym źródłem (skanem); osoba ma maks. dwoje rodziców; nikt nie jest własnym przodkiem; małżeństwo jest jednym wydarzeniem obojga małżonków. Zmienia się tylko miejsce edycji relacji (drzewo zamiast podglądu osoby). Filtry wyboru w slotach (płeć w slocie ojca/matki, ukrywanie osób łamiących reguły) oraz powrót drzewa do probanta po usunięciu osoby z centrum tylko stosują istniejące reguły w interfejsie.

## Access Control Changes

No access control changes — current model preserved: otwarta rejestracja, jedno drzewo na konto, każdy zalogowany użytkownik ma pełny dostęp wyłącznie do własnego drzewa. Podgląd osoby i akcje relacji na drzewie podlegają tej samej regule — cudza osoba to 404.

## Non-Goals

- **Brak przeciągania kart na drzewie** — relacje łączy się przez sloty i wybór osoby, nie przez drag-and-drop.
- **Brak adresu podglądu osoby** — podgląd nie ma własnego adresu (deep linku); brak przekierowań ze starych `/persons/{id}` (FR-009).
- **Brak scalania duplikatów osób** — wybór „istniejąca osoba” w slocie zmniejsza ryzyko duplikatów, ale już zdublowanych osób się nie scala; poprawiane ręcznie.

## Open Questions

1. **Przeplanowanie S-21 – S-23 w roadmapie** — ta zmiana wchłania i rozszerza tworzenie osób z drzewa (v3:FR-001 – v3:FR-003) o podłączanie istniejącej osoby, usuwanie relacji i połączenie rodziców; slice'y trzeba przepisać, a etap 1 (podgląd w modalu) ustawić przed S-17/S-18. Owner: `/10x-roadmap`. Block: no.
