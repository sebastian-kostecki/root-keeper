---
project: "root-keeper"
version: 1
status: draft
created: 2026-10-23
context_type: brownfield
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 5
  hard_deadline: null
  after_hours_only: true
---

# root-keeper — import badań z repo badawczego (zmiana brownfield, M-3)

Poprzednie PRD (zarchiwizowane): MVP `context/foundation/archive/prd-v1.md`, kolejne zmiany `archive/prd-v2.md` – `archive/prd-v4.md`, M-2 (opis źródeł, spis miejsc, wygląd drzewa, widok przodków) `archive/prd-v5.md`. Notatki z kształtowania tej zmiany: `context/foundation/shape-notes.md` (M-2: `archive/shape-notes-2026-10-23-0744.md`). Numeracja FR pochodzi z notatek; FR-012 i FR-013 odrzucono w rundzie kontrargumentów (zob. Scope of Change).

## Current System Overview

root-keeper to prywatne, samodzielnie hostowane drzewo genealogiczne: każde konto ma jedno drzewo i widzi tylko swoje dane.

- **Architektura i stack:** monolit Laravel 13 + Inertia v3 / React 19, shadcn/ui, PostgreSQL 18, Docker (self-hosted).
- **Użytkownicy:** autor; skala mała.
- **Logowanie i dostęp:** Fortify (e-mail + hasło, passkeys), jedno konto = jedno drzewo, bez ról; cudzy rekord to 404 (Policy z `denyAsNotFound`).
- **Funkcje dziś (M-1 i M-2 zrobione):** arkusz rodziny z relacjami ze slotów i panelami bocznymi (osoba, źródło, małżeństwo, miejscowość); źródła opisane rodzajem, parafią, rokiem, typem aktu, archiwum; numer wpisu przy powiązaniu źródła z faktem; spis miejscowości, parafii/urzędów i cmentarzy (`/places`); zamieszkania z latami; oś czasu wydarzeń; widok przodków z zoomem; zdjęcie osoby na karcie; notatka osoby. Daty wydarzeń są dokładne albo przybliżone („ok.”); wyszukiwarka osób szuka po nazwisku i nazwisku rodowym; źródło zawsze ma plik skanu.
- **Jakość dziś:** CI — lint bez ostrzeżeń, analiza statyczna, testy Pest; E2E ścieżek krytycznych (Playwright).
- **Wdrożenie dziś:** `scripts/deploy.sh` robi kopię bazy (`backup:run --only-db`) przed `migrate`.
- **Drugi system (poza root-keeperem):** repo badawcze `genealogy-project` (markdown, prowadzone przez autora z Claude; żywe repo w `/mnt/d/my-projects/genealogy-project`, kopia w `storage/gen-tree-import/`, poza gitem root-keepera). Zasady w jego `CLAUDE.md`: nie zmyślać, każdy fakt ze źródłem, fakt oddzielony od wniosku, poziomy pewności A–D (A akt pierwotny, B indeks/źródło wtórne, C wskazówka, D hipoteza).
  - `drzewo/`: 246 plików osób (jeden plik na osobę, nazwa `nazwisko_imie_rok.md`) + `_PRZODKOWIE.md` (indeks Ahnentafel, pokolenia I–IX, rodzeństwo poza numeracją, implex).
  - Wpis osoby to szablon linii (`Nr Ahnentafel`, `Warianty zapisu nazwiska`, `Płeć`, `Urodzenie`, `Małżeństwo`, `Zgon`, `Rodzice`, `Dzieci`, `Zawód / religia / ciekawostki`, `Otwarte pytania`), ale wartości to proza: data, miejsce, parafia, numer aktu, sygnatura, poziom pewności, chrzestni, świadkowie i wnioski w jednej linii; często „brak danych”.
  - `zrodla/`: 55 opisów źródeł (odczyty aktów, indeksy, kwerendy) + `skany/` (~21 pozycji, ok. 39 MB w kopii); `dziennik/` (sesje badawcze), `notatki/`, `TODO.md`.
  - Dziś część treści repo jest przepisana ręcznie do root-keepera na produkcji; obie strony się pokrywają.

## Problem Statement & Motivation

**Kategoria zmiany:** integracja — nowa możliwość: import z repo badawczego. Z propozycji M-3 na GitHubie żadna nie dołącza (#37 i #38 rozważone i odłożone).

Autor prowadzi badania w repo markdown (246 osób, dziesiątki opisanych aktów, skany) i przepisuje je ręcznie do root-keepera; przepisywanie nie nadąża za badaniami, a autor chce oglądać wyniki badań jako drzewo (karty, przodkowie, oś czasu), a nie jako pliki. Repo będzie dalej rosło i zmieniało się, więc przeniesienie nie jest jednorazowe — autor co jakiś czas aktualizuje kopię repo i chce przenieść zmiany. Obejście dziś: ręczne przepisywanie każdej osoby, faktu i aktu z markdownu.

Wgląd: repo badawcze jest źródłem prawdy dla zbadanych faktów, a root-keeper jego uporządkowanym widokiem. Wpisy w repo to proza (data, miejsce, parafia, akt, sygnatura, poziom A–D, wnioski w jednej linii), więc pomostem jest ustrukturyzowany blok danych w plikach repo, utrzymywany obok prozy, a nie odczytywanie prozy przez aplikację. Każdy rekord pamięta swój klucz z repo, żeby kolejny import aktualizował zamiast dublować.

## User & Persona

Bez zmian: **autor** — genealog prowadzący własne badania w repo markdown z Claude i oglądający drzewo w root-keeperze; jedyny użytkownik importu, uruchamia go z dostępem do serwera. Zmienia się jego sposób wprowadzania danych: zamiast przepisywać badania ręcznie, przenosi je importem i rozstrzyga dopasowania i konflikty.

## Success Criteria

### Primary
- Autor przenosi swoje badania do aplikacji importem z dopasowaniem do już wpisanych danych:
  1. Autor aktualizuje kopię repo badawczego na serwerze (pliki z blokami danych i skany).
  2. Uruchamia komendę importu z e-mailem konta i ścieżką do repo; komenda sama czyta bloki danych z plików.
  3. Komenda sprawdza bloki; błędne wpisy pomija i wypisuje w raporcie z powodem, resztę importuje.
  4. Przy pierwszym imporcie komenda pokazuje podsumowanie oraz wyłącznie dopasowania do istniejących osób i konflikty; przy kolejnych — pełny podgląd zmian (nowe, zmienione, konflikty z ręcznymi zmianami, nowe miejsca do potwierdzenia).
  5. Autor zatwierdza lub odrzuca pojedyncze zmiany i rozstrzyga wątpliwe dopasowania.
  6. Zapis zatwierdzonych zmian i podsumowanie.
  7. W aplikacji autor widzi przeniesione osoby w drzewie, z faktami, poziomem pewności A–D, źródłami i skanami.

### Secondary
- Brak — próbny przebieg (dry run) usunięty w rundzie kontrargumentów, bo zatwierdzanie pojedynczych zmian go zastępuje (zaakceptowane przez autora, zob. Open Questions 5).

### Guardrails
- Ręczna poprawka wpisana w aplikacji nigdy nie znika po imporcie bez pokazania jej w podglądzie i zgody autora (główne ryzyko wskazane przez autora).
- Rzeczy dodane tylko w aplikacji (zdjęcia osób, ręcznie wgrane skany, notatki, osoby spoza repo) przetrwają każdy import.
- Import zapisuje i dopasowuje tylko w drzewie wskazanego konta; cudze drzewo nietknięte. Import nie przechodzi przez zwykłe sprawdzanie uprawnień w aplikacji, więc granicy dowodzi osobny test.
- Jeden spis miejsc: import nie dubluje miejscowości, parafii ani cmentarzy.
- Ręczne wpisywanie i edycja w aplikacji działają jak dotąd.

## User Stories

### US-01: Pierwszy import badań do drzewa z ręcznie wpisanymi osobami

- **Given** drzewo autora na produkcji zawiera część osób wpisanych ręcznie, a na serwerze leży kopia repo badawczego z blokami danych w plikach przodków i skanami
- **When** autor uruchamia komendę importu z e-mailem konta i katalogiem repo
- **Then** komenda pokazuje podsumowanie, raport pominiętych wpisów oraz dopasowania do istniejących osób i konflikty; autor rozstrzyga je pojedynczo, a po zatwierdzeniu osoby, relacje, wydarzenia (z poziomem A–D i datami nieostrymi), miejsca, źródła i skany trafiają do drzewa bez duplikatów, a ręczne poprawki zostają tam, gdzie autor tak wybrał

Wcześniej: autor przepisywał każdą osobę, fakt i akt ręcznie z markdownu.

### US-02: Kolejny import po nowej sesji badawczej

- **Given** drzewo zostało wcześniej zaimportowane, a autor dopisał w repo nowe osoby i poprawił odczyt aktu
- **When** autor uruchamia import zaktualizowanej kopii repo
- **Then** podgląd pokazuje tylko nowe osoby i zmienione wartości (rekordy rozpoznane po stałym identyfikatorze z bloku), autor zatwierdza je pojedynczo, a nic się nie dubluje

Wcześniej: każdą zmianę z nowej sesji badawczej autor przepisywał ręcznie (jak w US-01).

### US-03: Wyszukanie osoby po wariancie nazwiska

- **Given** zaimportowana osoba Misiuk ma wariant zapisu Mysiuk
- **When** autor wpisuje „Mysiuk” w wyszukiwarce osób
- **Then** lista pokazuje tę osobę

Wcześniej: wyszukiwarka znajdowała tylko po nazwisku i nazwisku rodowym.

## Scope of Change

Wszystkie FR są `must-have` poza FR-020 (`nice-to-have`). Zakres w M-3: najpierw tylko przodkowie (pliki z numerem Ahnentafel i ich źródła); linie boczne w kolejnych krokach.

### Repo badawcze
- [new] FR-001: Autor może utrzymywać w plikach osób i źródeł repo badawczego blok danych obok prozy; w M-3 konwertowane są pliki przodków z numerem Ahnentafel (i ich źródła), linie boczne później. Priority: must-have.
  > Socrates: Counter-argument considered: "przegląd konwersji ~300 plików zje M-3". Resolution: zmieniony — najpierw tylko przodkowie (Ahnentafel), linie boczne w kolejnych krokach.
- [new] FR-002: Autor może wskazać komendzie katalog repo — komenda sama czyta bloki danych z plików (bez skryptu i pliku pośredniego w repo badawczym). Priority: must-have.
  > Socrates: Counter-argument considered: "skrypt w repo badawczym to drugi projekt do utrzymania". Resolution: zmieniony — bez skryptu eksportu; komenda czyta katalog z blokami bezpośrednio.

### Komenda importu
- [new] FR-003: Autor może uruchomić na serwerze komendę importu, wskazując konto (e-mail) i katalog repo; import zapisuje tylko do drzewa tego konta. Priority: must-have.
  > Socrates: Counter-argument considered: "repo i skany trzeba ręcznie skopiować na serwer". Resolution: kept; ręczne kopiowanie akceptowalne przy rzadkich importach.
- [new] FR-004: Autor dostaje raport wpisów, których bloki są niezgodne z formatem (z powodem); błędne wpisy są pomijane, reszta się importuje. Priority: must-have.
  > Socrates: Counter-argument considered: "jeden błędny plik blokuje cały import". Resolution: zmieniony — z „wszystko albo nic” na pominięcie błędnych wpisów z raportem.
- [new] FR-005: Autor widzi przed zapisem: przy pierwszym imporcie podsumowanie oraz dopasowania i konflikty; przy kolejnych pełny podgląd zmian (nowe, zmienione, konflikty z ręcznymi zmianami — obie wartości). Priority: must-have.
  > Socrates: Counter-argument considered: "pierwszy import to setki pozycji »nowe«". Resolution: zmieniony — pierwszy import: podsumowanie + tylko dopasowania i konflikty.
- [new] FR-006: Autor może zatwierdzać lub odrzucać pojedyncze zmiany z podglądu; bez zgody nic się nie zapisuje. Priority: must-have.
  > Socrates: Counter-argument considered: "zatwierdzenie wszystko albo nic jest za grube". Resolution: zmieniony — zatwierdzanie per zmiana.
- [new] FR-007: Autor może przy pierwszym imporcie dopasować osoby z repo do osób już wpisanych ręcznie; wątpliwe przypadki rozstrzyga sam. Priority: must-have.
  > Socrates: Counter-argument considered: "wyczyścić drzewo zamiast dopasowywać". Resolution: kept — autor po rozważeniu wrócił do dopasowania (zdjęcia i skany wiszą na istniejących osobach); czyszczenie tylko poza produkcją (FR-020).
- [new] FR-008: Autor może ponawiać import: każda osoba i źródło niesie w bloku stały identyfikator (nie nazwę pliku), więc kolejny import aktualizuje zamiast dublować. Priority: must-have.
  > Socrates: Counter-argument considered: "nazwa pliku się zmienia (`_xxxx` → rok)". Resolution: zmieniony — kluczem jest stały identyfikator w bloku.
- [new] FR-009: Autor może przenieść osoby (imiona, nazwisko, nazwisko rodowe, płeć), relacje rodzic–dziecko, małżeństwa i wydarzenia (urodzenie, ślub, zgon, pochówek) z datą i miejscem; data może być dokładna, przybliżona („ok.”), zakresem lat albo „przed”/„po”. Priority: must-have.
  - [modified] Rodzaje daty — było: dokładna albo przybliżona („ok.”); teraz także zakres lat, „przed” i „po”.
  > Socrates: Counter-argument considered: "daty w repo są nieostre (ok. 1799, przed 1809, 1805–1811, dwa style kalendarza)". Resolution: zmieniony — nowe rodzaje daty: zakres, przed, po; styl juliański zostaje w notatce faktu.
- [new] FR-010: Autor może przenieść miejsca dopasowane do spisu drzewa (miejscowości, parafie, cmentarze); nowe miejsca potwierdza w podglądzie, nic się nie dubluje. Priority: must-have.
  > Socrates: Counter-argument considered: "warianty nazw miejscowości (Bukinia/Bokinia, formy polska/rosyjska, nieistniejąca Binduga)". Resolution: zmieniony — nowe miejsca i niepewne dopasowania do potwierdzenia w podglądzie.
- [new] FR-011: Autor może przenieść źródła (opis: parafia, rok, typ aktu, archiwum, sygnatura, link; plik skanu z repo) podpięte do faktów z numerem wpisu. Priority: must-have.
  > Socrates: Counter-argument considered: "źródła bez skanów są półpełne, a aplikacja wymaga pliku". Resolution: zmieniony — skany wracają do M-3; źródło nadal zawsze ma plik.
- [new] FR-018: Autor może przenieść poziom pewności (A–D) jako osobne pole przy fakcie i widzieć go w aplikacji. Priority: must-have.
  > Socrates: Counter-argument considered: "poziom przy fakcie czy przy powiązaniu ze źródłem?". Resolution: osobne pole przy fakcie, niezależne od źródeł.
- [new] FR-019: Autor może przenieść warianty zapisu nazwiska osoby, a wyszukiwarka osób znajduje osobę po każdym wariancie. Priority: must-have.
  - [modified] Wyszukiwarka osób — było: po nazwisku i nazwisku rodowym; teraz także po każdym wariancie zapisu nazwiska.
  > Socrates: Counter-argument considered: "bez wyszukiwania po wariantach to tylko tekst". Resolution: zmieniony — dodane wyszukiwanie po wariantach.
- [new] FR-020: Autor może poza produkcją uruchomić import „od zera”, który przed importem usuwa osoby i fakty drzewa wskazanego konta (z potwierdzeniem). Priority: nice-to-have.
  > Socrates: Counter-argument considered: "niebezpieczna na produkcji — pomyłka w e-mailu kasuje drzewo". Resolution: zmieniony — tylko poza produkcją.

### Zachowane
- [preserved] FR-014: Autor zachowuje po każdym imporcie dane dodane tylko w aplikacji (zdjęcia osób, ręcznie wgrane skany, notatki, osoby spoza repo). Priority: must-have.
  > Socrates: No counter-argument; it stands as written.
- [preserved] FR-015: Autor może ręcznie wpisywać i edytować dane w aplikacji jak dotąd, także pola zaimportowane. Priority: must-have.
  > Socrates: No counter-argument; edycja wszędzie jak dotąd, konflikty łapie podgląd.
- [preserved] FR-016: Autor widzi wyłącznie swoje drzewo; cudzy rekord to 404, a import nie dopasowuje ani nie zapisuje rekordów z cudzego drzewa. Priority: must-have.
  > Socrates: Counter-argument considered: "komenda omija sprawdzanie uprawnień w aplikacji". Resolution: kept; test musi udowodnić granicę własności dla komendy.
- [preserved] FR-017: Autor ma jeden spis miejsc ze scalaniem duplikatów i unikalnością w obrębie drzewa. Priority: must-have.
  > Socrates: Counter-argument considered: "granice i nazwy historyczne (gubernie, powiaty w różnych latach)". Resolution: kept; historyczne jednostki administracyjne nie są w M-3 — do Open Questions (autor nie wybrał ich jako Non-Goal).

### Odrzucone w rundzie kontrargumentów (nie wchodzą)
- ~~FR-012: próbny przebieg (dry run)~~
  > Socrates: Counter-argument considered: "zbędny przy zatwierdzaniu per zmiana". Resolution: usunięty.
- ~~FR-013: lista „Do zbadania” (#37)~~
  > Socrates: Counter-argument considered: "repo już ma TODO.md — lista zadań badawczych żyje w repo". Resolution: usunięty z M-3; #37 wraca do parked.

## Constraints & Compatibility

- **Integracja:** komenda czyta kopię repo badawczego (bloki danych + skany) skopiowaną na serwer; repo badawcze jest tylko czytane, nigdy zapisywane. Format bloku danych jest kontraktem między repo a aplikacją; zasady utrzymania bloku dopisuje się do `CLAUDE.md` repo badawczego.
- **Repo badawcze:** zmiana jego formatu (blok danych) musi być zgodna z jego `CLAUDE.md` (nie zmyślać, fakt ze źródłem, poziomy A–D) i przeglądana przez autora.
- **Istniejące dane:** nowe pola (stały identyfikator repo przy osobie/źródle/fakcie, poziom A–D przy fakcie, rodzaje daty zakres/przed/po, warianty nazwiska) dochodzą jako puste; istniejące fakty bez poziomu (= nieokreślony), daty i wpisy bez zmian. Żadne istniejące dane nie są przepisywane. Każdą zmianę struktury danych da się cofnąć, zgodnie z zasadami repo.
- **Zgodność wstecz:** ręczne wpisywanie i edycja, istniejące daty (dokładne i „ok.”), źródła zawsze z plikiem, adresy stron i panele bez zmian.
- **Zachowane:** granica własności drzewa (także dla komendy — test), jeden spis miejsc z unikalnością i scalaniem, dane dodane tylko w aplikacji, bramki jakości CI i E2E ścieżek krytycznych (zob. Current System Overview).
- **Wdrożenie:** kopia bazy przed zmianą struktury danych przy wdrożeniu zostaje (zob. Current System Overview); zmiany tej zmiany idą tą samą ścieżką.
- **Dane badawcze:** prawdziwe dane tylko lokalnie i na produkcji; nie trafiają do repo root-keepera, danych testowych ani CI — testy na danych przykładowych.
- **Wymagania niefunkcjonalne:**
  - Przed zapisem importu na produkcji istnieje kopia bazy danych z chwili tuż przed importem.
  - Prawdziwe dane badawcze nie pojawiają się w repozytorium root-keepera, w danych testowych ani w CI; testy importu działają na danych przykładowych.
  - Istniejące bramki jakości nie pogarszają się: CI przechodzi bez ostrzeżeń, E2E ścieżek krytycznych zielone.
- **Termin:** 4–5 tygodni po godzinach (delivery_weeks: 5, górna granica), bez twardego terminu. Koszt stałego wysiłku zaakceptowany 2026-10-23, po tym, jak zakres urósł w rundzie kontrargumentów (skany, poziomy A–D, daty nieostre, wyszukiwanie po wariantach wróciły do M-3).

## Business Logic Changes

Osoba z repo badawczego jest tą samą osobą co osoba w drzewie, gdy zgadza się jej stały identyfikator z bloku danych albo — przy pierwszym imporcie — co najmniej dwa niezależne elementy poza imieniem i nazwiskiem; w każdym innym przypadku decyduje autor.

Reguła dziś: brak — osoby wchodzą do drzewa tylko ręcznie, a o tym, czy to ta sama osoba, decyduje autor przy przepisywaniu. Wejścia nowej reguły: stały identyfikator z bloku (po pierwszym imporcie zapamiętany przy rekordzie), a przy pierwszym imporcie elementy osoby z repo i z drzewa (np. rodzice, rok i miejsce urodzenia, małżonek). Zasada „dwóch niezależnych zgodnych elementów” pochodzi z `CLAUDE.md` repo badawczego (nie scalać po samym imieniu i nazwisku). Wynik: osoba dopasowana pewnie, osoba nowa albo przypadek wątpliwy — ten ostatni zawsze trafia do autora w podglądzie.

Reguły pomocnicze:
- **Pierwszeństwo wartości:** wartość z repo zastępuje wartość w aplikacji bez pytania tylko wtedy, gdy w aplikacji nikt jej nie zmienił od ostatniego importu; inaczej to konflikt do decyzji autora (obie wartości w podglądzie).
- **Zniknięcie z repo:** rekord z identyfikatorem repo, którego w repo już nie ma (scalenie, błędna tożsamość, usunięty plik), trafia do podglądu jako „usunięty w repo”; autor decyduje: usuń albo zostaw. Rekordy bez identyfikatora repo (dodane tylko w aplikacji) nigdy nie są ruszane.
- **Miejsca:** miejsce z repo dopasowuje się do spisu drzewa; nowe miejsca i niepewne dopasowania (warianty pisowni) autor potwierdza w podglądzie.
- **Daty (modyfikacja):** dziś data wydarzenia jest dokładna albo przybliżona („ok.”); po zmianie może być też zakresem lat, „przed” albo „po”. Styl juliański zostaje w notatce faktu.
- **Poziom pewności (nowe):** każdy fakt może mieć poziom A–D niezależny od źródeł; fakt bez poziomu ma poziom nieokreślony.

## Access Control Changes

No access control changes — current model preserved: logowanie i model ról w aplikacji bez zmian, jedno konto = jedno drzewo, cudzy rekord to 404.

Nowa ścieżka zapisu poza aplikacją: import uruchamia się komendą na serwerze — dostęp ma ten, kto ma dostęp do serwera. Komenda wskazuje konto (e-mail) i zapisuje wyłącznie do drzewa tego konta; nic nie trafia do cudzego drzewa (dopasowanie do istniejących rekordów też tylko w obrębie tego drzewa). Działa tak samo lokalnie, np. na koncie testowym. Import „od zera” (FR-020) jest dostępny tylko poza produkcją.

## Non-Goals

- **Import GEDCOM i import z interfejsu aplikacji** — „profesjonalny” import GEDCOM w interfejsie to osobna, późniejsza praca; M-3 to komenda dla repo badawczego autora.
- **Synchronizacja w dwie strony** — aplikacja nigdy nie zapisuje do repo badawczego; poprawki wracają do repo ręcznie.
- **#37, #38, #39, #40, #41** — lista „Do zbadania” (repo ma TODO.md), znacznik źródła na karcie, wiele skanów naraz, eksport, widok potomków zostają w parked.
- **Cofnięcie złego importu** — autor nie wybrał go w M-3 (rozważone przy „co musi przetrwać”).
- **Próbny przebieg (dry run)** — zastąpiony zatwierdzaniem pojedynczych zmian (FR-012 odrzucone).
- **Linie boczne w imporcie M-3** — w M-3 bloki danych dostają tylko przodkowie z numerem Ahnentafel (FR-001); termin dla linii bocznych w Open Questions.
- **Pozostałe notatki z repo** (otwarte pytania, ciekawostki) — nie są importowane w M-3.

## Open Questions

1. **Historyczne jednostki administracyjne (gubernie, powiaty, zmiany nazw w czasie) — czy i kiedy?** Dziś jeden rekord miejscowości (z rundy kontrargumentów FR-017). — Owner: autor. Termin: nieustalony.
2. **Kiedy linie boczne (pliki bez numeru Ahnentafel) dostaną bloki danych i wejdą do importu?** (FR-001) — Owner: autor. Termin: nieustalony.
3. **Gdzie trafią pozostałe notatki z repo (otwarte pytania, ciekawostki)?** — Później, bez decyzji. Owner: autor.
4. **Jak wygląda podgląd w terminalu przy dziesiątkach konfliktów, żeby zatwierdzanie per zmiana było wykonalne?** (FR-005/FR-006) — Owner: autor. Termin: przed planem komendy importu.
5. ~~**Success Criteria → Secondary jest puste po usunięciu dry run.**~~ — Zaakceptowane przez autora 2026-10-23 (quality cross-check).
