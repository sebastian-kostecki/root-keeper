---
project: "root-keeper"
context_type: brownfield
created: 2026-10-23
updated: 2026-10-23
product_type: web-app
target_scale:
  users: small
timeline_budget:
  delivery_weeks: 5
  hard_deadline: null
  after_hours_only: true
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "session"
      decision: "fresh session for M-3; M-2 notes archived to archive/shape-notes-2026-10-23-0744.md"
    - topic: "M-3 scope vs GitHub issues"
      decision: "import from the research repo is the core of M-3; only issues that work with it (#37–#41 candidates) join"
    - topic: "source of truth"
      decision: "research repo is the truth for researched facts; root-keeper is its organised view"
    - topic: "bridge"
      decision: "export file in an agreed format produced on the research side; the app validates and imports it (no prose parsing, no GEDCOM for this path)"
    - topic: "conflicts"
      decision: "preview of changes and approval before anything is written"
    - topic: "import content"
      decision: "persons, relations, events; sources linked to facts; scan files; confidence levels A–D and notes"
    - topic: "existing data"
      decision: "part of the production tree was entered by hand and overlaps the repo — first import must match, not duplicate"
    - topic: "must preserve"
      decision: "tree ownership boundary; things added only in the app (photos, hand-uploaded scans, notes, persons outside the repo); one places list (no duplicate localities/parishes/cemeteries). Undo of a bad import NOT selected"
    - topic: "trigger"
      decision: "server command with terminal preview; a GEDCOM import in the UI comes later as separate work"
    - topic: "target tree"
      decision: "command names the account by e-mail; writes only to that account's tree; app auth and roles unchanged"
    - topic: "export producer"
      decision: "each research-repo person/source file gets a structured data block maintained by Claude next to the prose; a script collects the blocks into the export file (deterministic, visible in git diff); one-time conversion of ~246 + 55 files"
    - topic: "first slice"
      decision: "persons, relations, events + matching to existing records, with preview; sources, scans and A–D levels in later steps"
    - topic: "GitHub issues in M-3"
      decision: "#37 unsourced-facts list and #38 source status on tree card join; #39, #40, #41 stay parked"
    - topic: "timeline / scope down"
      decision: "~3 weeks after hours; ~3 weeks after hours, scope reduced (final split: see M-3 / M-4 split)"
    - topic: "blast radius"
      decision: "main risk: manual corrections in the app overwritten by an import"
    - topic: "secondary"
      decision: "dry run: preview only, no write (later dropped in Socrates round)"
    - topic: "M-3 / M-4 split"
      decision: "M-3 = data blocks in research repo (ancestors first) + import of persons/relations/events with matching + sources with scans + A–D field per fact + fuzzy dates + name variants with search; #37, #38 parked; other notes (open questions, curiosities) later"
    - topic: "Socrates round"
      decision: "command reads repo dir directly (no export script); skip invalid entries with report; per-change approval; first import shows summary + matches/conflicts only; stable id in block; new date kinds (range/before/after); scans back in M-3; A–D as own field per fact; variant search; dry run and #37 dropped; wipe-flag only outside production"
    - topic: "core domain rule"
      decision: "person identity: stable id from the data block, or on first import >= 2 independent matching elements beyond name; otherwise the author decides"
    - topic: "value precedence / removal"
      decision: "repo value replaces app value silently only if unchanged in the app since last import, else conflict; record gone from repo shown in preview for delete/keep"
    - topic: "migration"
      decision: "only new empty fields; no rewrite of existing rows"
    - topic: "timeline (revised)"
      decision: "4–5 weeks after hours, cost accepted (delivery_weeks: 5)"
    - topic: "NFR"
      decision: "DB backup right before an import write on production; real research data never in repo/fixtures/CI; CI and E2E stay green"
    - topic: "framing"
      decision: "no change — web-app, small; no hard deadline; after hours"
    - topic: "existing-system constraints"
      decision: "deploy via scripts/deploy.sh with DB backup before migrate; research-repo format change follows its CLAUDE.md and is reviewed by the author"
    - topic: "non-goals"
      decision: "GEDCOM/UI import; two-way sync; issues #37–#41"
  frs_drafted: 18
  quality_check_status: accepted
---

# root-keeper — shape notes (zmiana brownfield, M-3)

Poprzednia sesja: `context/foundation/archive/shape-notes-2026-10-23-0744.md` (M-2, prd v1 z 2026-10-04).

## Seed idea

Dosłownie od użytkownika:

```
weź to co jest na githubie issues, ale zobacz storage/gen-tree-import - tutaj mam apliakcje badawczą dla mojego drzewa i chcialbym jakos to moc (dane z tego drzewa) czasami przenosic na moje drzewo w aplikacji (co jakis czas bede aktualizowal; co proponujesz?
```

Otwarte issues na GitHubie (propozycje na M-3 z przeglądu po M-2, 2026-10-13):

- #37 Lista faktów bez źródła („Do zbadania”) — `unsourced-facts-list`
- #38 Stan potwierdzenia źródłem na karcie drzewa — `tree-card-source-status`
- #39 Wgrywanie wielu skanów naraz — `multi-source-upload`
- #40 Eksport danych drzewa i skanów w otwartym formacie — `tree-data-export`
- #41 Widok wszystkich potomków — `descendants-view`
- (#28 Wydzielić wspólną etykietę grupy w modalu osoby — porządkowe, bez etykiety `slice`)

## Observed: research repo (`storage/gen-tree-import/`)

Stan obejrzany 2026-10-23 (fakty z plików, nie decyzje):

- `storage/gen-tree-import/` jest w `.gitignore`. Zawiera `genealogy-project/` (klon repo git, ostatni commit 2026-10-15, 192 commity) oraz luźne kopie `dziennik/` i `zrodla/`. Żywe repo leży w `/mnt/d/my-projects/genealogy-project`.
- Repo badawcze prowadzi autor z Claude; zasady w jego `CLAUDE.md`: nie zmyślać, każdy fakt ze źródłem, fakt oddzielony od wniosku, poziomy pewności A–D (A akt pierwotny, B indeks/źródło wtórne, C wskazówka, D hipoteza).
- `drzewo/`: 246 plików osób (jeden plik na osobę, nazwa `nazwisko_imie_rok.md`) + `_PRZODKOWIE.md` (indeks Ahnentafel, pokolenia I–IX, rodzeństwo poza numeracją, implex).
- Wpis osoby to szablon linii (`Nr Ahnentafel`, `Warianty zapisu nazwiska`, `Płeć`, `Urodzenie`, `Małżeństwo`, `Zgon`, `Rodzice`, `Dzieci`, `Zawód / religia / ciekawostki`, `Otwarte pytania`), ale wartości to proza: data, miejsce, parafia, numer aktu, sygnatura, poziom pewności, chrzestni, świadkowie i wnioski w jednej linii; często „brak danych”.
- `zrodla/`: 55 opisów źródeł (odczyty aktów, indeksy, kwerendy) + `skany/` (~21 pozycji, ok. 39 MB w kopii).
- `dziennik/`: dziennik sesji badawczych; `notatki/`, `TODO.md`.
- PRD M-2 ma w Non-Goals: „Brak importu/eksportu GEDCOM — dane wchodzą ręcznie, jak dotąd”.

## Current System

- **Produkt:** root-keeper — prywatne, samodzielnie hostowane drzewo genealogiczne; każde konto ma jedno drzewo i widzi tylko swoje dane.
- **Stack:** monolit Laravel 13 + Inertia v3 / React 19, shadcn/ui, PostgreSQL 18, Docker (self-hosted).
- **Użytkownicy:** autor; skala mała.
- **Funkcje dziś (M-1 i M-2 zrobione):** arkusz rodziny z relacjami ze slotów i panelami bocznymi (osoba, źródło, małżeństwo, miejscowość); źródła opisane rodzajem, parafią, rokiem, typem aktu, archiwum; numer wpisu przy powiązaniu źródła z faktem; spis miejscowości, parafii/urzędów i cmentarzy (`/places`); zamieszkania z latami; oś czasu wydarzeń; widok przodków z zoomem; zdjęcie osoby na karcie; notatka osoby.
- **Drugi system (poza root-keeperem):** repo badawcze `genealogy-project` (markdown, prowadzone z Claude) — opis w „Observed: research repo” wyżej. Dziś część jego treści jest przepisana ręcznie do root-keepera na produkcji; obie strony się pokrywają.

## Vision & Problem Statement

**Kategoria zmiany:** integracja (nowa możliwość: import z repo badawczego). Z propozycji M-3 na GitHubie żadna nie dołącza (#37 i #38 rozważone i odłożone).

**Ból:** autor prowadzi badania w repo markdown (246 osób, dziesiątki opisanych aktów, skany) i przepisuje je ręcznie do root-keepera; przepisywanie nie nadąża za badaniami, a autor chce oglądać wyniki badań jako drzewo (karty, przodkowie, oś czasu), a nie jako pliki. Repo będzie dalej rosło i zmieniało się, więc przeniesienie nie jest jednorazowe — autor co jakiś czas aktualizuje kopię repo i chce przenieść zmiany.

**Wgląd:** repo badawcze jest źródłem prawdy dla zbadanych faktów, a root-keeper jego uporządkowanym widokiem. Wpisy w repo to proza (data, miejsce, parafia, akt, sygnatura, poziom A–D, wnioski w jednej linii), więc pomostem jest ustrukturyzowany blok danych w plikach repo, utrzymywany obok prozy, a nie parsowanie prozy przez aplikację. Każdy rekord pamięta swój klucz z repo, żeby kolejny import aktualizował zamiast dublować.

Decyzje (faza 1):

- Źródło prawdy: repo badawcze.
- Pomost: blok danych w plikach repo; komenda czyta katalog repo i waliduje bloki (rozstrzygnięte w rundzie kontrargumentów; pierwotnie: plik eksportu).
- Konflikty z ręcznymi zmianami w aplikacji: podgląd zmian i zatwierdzenie przed zapisem.
- Zawartość importu: osoby, relacje, wydarzenia; źródła i ich podpięcie do faktów; pliki skanów; poziomy pewności; warianty nazwisk (pozostałe notatki — później).
- Stan danych: część drzewa na produkcji wpisana ręcznie i pokrywa się z repo — pierwszy import musi dopasować istniejące rekordy, nie dublować.
- Uruchomienie: komenda na serwerze (podgląd w terminalu). Import GEDCOM z UI — później, osobna praca.

## User & Persona

Bez zmian: autor — genealog prowadzący własne badania w repo markdown z Claude i oglądający drzewo w root-keeperze; jedyny użytkownik importu, uruchamia go z dostępem do serwera.

## Access Control

Obecny model: logowanie (Fortify, e-mail + hasło, passkeys), jedno konto = jedno drzewo, bez ról; cudzy rekord to 404 (`denyAsNotFound`).

Zmiany: model logowania i ról w aplikacji bez zmian. Import uruchamia się komendą na serwerze — dostęp ma ten, kto ma dostęp do serwera. Komenda wskazuje konto (e-mail) i zapisuje wyłącznie do drzewa tego konta; nic nie trafia do cudzego drzewa (dopasowanie do istniejących rekordów też tylko w obrębie tego drzewa). Działa tak samo lokalnie, np. na koncie testowym.

## Timeline acknowledgment

Acknowledged on 2026-10-23: 4–5-week change (delivery_weeks: 5, upper bound) requires sustained dedication after hours; user accepted after scope grew in the Socrates round (scans, A–D levels, fuzzy dates, variant search back in M-3).

## Success Criteria

### Primary

Autor przenosi swoje badania do aplikacji importem z dopasowaniem do już wpisanych danych:

1. Autor aktualizuje kopię repo badawczego na serwerze (pliki z blokami danych i skany).
2. Uruchamia komendę importu z e-mailem konta i ścieżką do repo; komenda sama czyta bloki danych z plików.
3. Komenda sprawdza bloki; błędne wpisy pomija i wypisuje w raporcie z powodem, resztę importuje.
4. Przy pierwszym imporcie komenda pokazuje podsumowanie oraz wyłącznie dopasowania do istniejących osób i konflikty; przy kolejnych — pełny podgląd zmian (nowe, zmienione, konflikty z ręcznymi zmianami, nowe miejsca do potwierdzenia).
5. Autor zatwierdza lub odrzuca pojedyncze zmiany i rozstrzyga wątpliwe dopasowania.
6. Zapis zatwierdzonych zmian i podsumowanie.
7. W aplikacji autor widzi przeniesione osoby w drzewie, z faktami, poziomem pewności A–D, źródłami i skanami.

### Secondary

_(brak — dry run usunięty w rundzie kontrargumentów, bo zatwierdzanie pojedynczych zmian go zastępuje)_

### Guardrails

- Ręczna poprawka wpisana w aplikacji nigdy nie znika po imporcie bez pokazania jej w podglądzie i zgody autora (główne ryzyko wskazane przez autora).
- Rzeczy dodane tylko w aplikacji (zdjęcia osób, ręcznie wgrane skany, notatki, osoby spoza repo) przetrwają każdy import.
- Import zapisuje i dopasowuje tylko w drzewie wskazanego konta; cudze drzewo nietknięte (komenda nie przechodzi przez Policy — dowodzi tego test).
- Jeden spis miejsc: import nie dubluje miejscowości, parafii ani cmentarzy.
- Ręczne wpisywanie i edycja w aplikacji działają jak dotąd.

## Functional Requirements

### Repo badawcze

- FR-001: Autor może utrzymywać w plikach osób i źródeł repo badawczego blok danych obok prozy; w M-3 konwertowane są pliki przodków z numerem Ahnentafel (i ich źródła), linie boczne później. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "przegląd konwersji ~300 plików zje M-3". Resolution: zmieniony — najpierw tylko przodkowie (Ahnentafel), linie boczne w kolejnych krokach.
- FR-002: Autor może wskazać komendzie katalog repo — komenda sama czyta bloki danych z plików (bez skryptu i pliku pośredniego w repo badawczym). Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "skrypt w repo badawczym to drugi projekt do utrzymania". Resolution: zmieniony — bez skryptu eksportu; komenda czyta katalog z blokami bezpośrednio.

### Komenda importu

- FR-003: Autor może uruchomić na serwerze komendę importu, wskazując konto (e-mail) i katalog repo; import zapisuje tylko do drzewa tego konta. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "repo i skany trzeba ręcznie skopiować na serwer". Resolution: kept; ręczne kopiowanie akceptowalne przy rzadkich importach.
- FR-004: Autor dostaje raport wpisów, których bloki są niezgodne z formatem (z powodem); błędne wpisy są pomijane, reszta się importuje. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "jeden błędny plik blokuje cały import". Resolution: zmieniony — z „wszystko albo nic” na pominięcie błędnych wpisów z raportem.
- FR-005: Autor widzi przed zapisem: przy pierwszym imporcie podsumowanie oraz dopasowania i konflikty; przy kolejnych pełny podgląd zmian (nowe, zmienione, konflikty z ręcznymi zmianami — obie wartości). Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "pierwszy import to setki pozycji »nowe«". Resolution: zmieniony — pierwszy import: podsumowanie + tylko dopasowania i konflikty.
- FR-006: Autor może zatwierdzać lub odrzucać pojedyncze zmiany z podglądu; bez zgody nic się nie zapisuje. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "zatwierdzenie wszystko albo nic jest za grube". Resolution: zmieniony — zatwierdzanie per zmiana.
- FR-007: Autor może przy pierwszym imporcie dopasować osoby z repo do osób już wpisanych ręcznie; wątpliwe przypadki rozstrzyga sam. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "wyczyścić drzewo zamiast dopasowywać". Resolution: kept — autor po rozważeniu wrócił do dopasowania (zdjęcia i skany wiszą na istniejących osobach); czyszczenie tylko poza produkcją (FR-020).
- FR-008: Autor może ponawiać import: każda osoba i źródło niesie w bloku stały identyfikator (nie nazwę pliku), więc kolejny import aktualizuje zamiast dublować. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "nazwa pliku się zmienia (`_xxxx` → rok)". Resolution: zmieniony — kluczem jest stały identyfikator w bloku.
- FR-009: Autor może przenieść osoby (imiona, nazwisko, nazwisko rodowe, płeć), relacje rodzic–dziecko, małżeństwa i wydarzenia (urodzenie, ślub, zgon, pochówek) z datą i miejscem; data może być dokładna, przybliżona („ok.”), zakresem lat albo „przed”/„po”. Priority: must-have. Change: new (daty: modified)
  > Socrates: Counter-argument considered: "daty w repo są nieostre (ok. 1799, przed 1809, 1805–1811, dwa style kalendarza)". Resolution: zmieniony — nowe rodzaje daty: zakres, przed, po; styl juliański zostaje w notatce faktu.
- FR-010: Autor może przenieść miejsca dopasowane do spisu drzewa (miejscowości, parafie, cmentarze); nowe miejsca potwierdza w podglądzie, nic się nie dubluje. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "warianty nazw miejscowości (Bukinia/Bokinia, formy polska/rosyjska, nieistniejąca Binduga)". Resolution: zmieniony — nowe miejsca i niepewne dopasowania do potwierdzenia w podglądzie.
- FR-011: Autor może przenieść źródła (opis: parafia, rok, typ aktu, archiwum, sygnatura, link; plik skanu z repo) podpięte do faktów z numerem wpisu. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "źródła bez skanów są półpełne, a aplikacja wymaga pliku". Resolution: zmieniony — skany wracają do M-3; źródło nadal zawsze ma plik.
- FR-018: Autor może przenieść poziom pewności (A–D) jako osobne pole przy fakcie i widzieć go w aplikacji. Priority: must-have. Change: new
  > Socrates: Counter-argument considered: "poziom przy fakcie czy przy powiązaniu ze źródłem?". Resolution: osobne pole przy fakcie, niezależne od źródeł.
- FR-019: Autor może przenieść warianty zapisu nazwiska osoby, a wyszukiwarka osób znajduje osobę po każdym wariancie. Priority: must-have. Change: new (wyszukiwanie: modified)
  > Socrates: Counter-argument considered: "bez wyszukiwania po wariantach to tylko tekst". Resolution: zmieniony — dodane wyszukiwanie po wariantach.
- FR-020: Autor może poza produkcją uruchomić import „od zera”, który przed importem usuwa osoby i fakty drzewa wskazanego konta (z potwierdzeniem). Priority: nice-to-have. Change: new
  > Socrates: Counter-argument considered: "niebezpieczna na produkcji — pomyłka w e-mailu kasuje drzewo". Resolution: zmieniony — tylko poza produkcją.

### Usunięte w rundzie kontrargumentów

- ~~FR-012: próbny przebieg (dry run)~~
  > Socrates: Counter-argument considered: "zbędny przy zatwierdzaniu per zmiana". Resolution: usunięty.
- ~~FR-013: lista „Do zbadania” (#37)~~
  > Socrates: Counter-argument considered: "repo już ma TODO.md — lista zadań badawczych żyje w repo". Resolution: usunięty z M-3; #37 wraca do parked.

### Zachowane

- FR-014: Autor zachowuje po każdym imporcie dane dodane tylko w aplikacji (zdjęcia osób, ręcznie wgrane skany, notatki, osoby spoza repo). Priority: must-have. Change: preserved
  > Socrates: No counter-argument; it stands as written.
- FR-015: Autor może ręcznie wpisywać i edytować dane w aplikacji jak dotąd, także pola zaimportowane. Priority: must-have. Change: preserved
  > Socrates: No counter-argument; edycja wszędzie jak dotąd, konflikty łapie podgląd.
- FR-016: Autor widzi wyłącznie swoje drzewo; cudzy rekord to 404, a import nie dopasowuje ani nie zapisuje rekordów z cudzego drzewa. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "komenda omija polityki HTTP". Resolution: kept; test musi udowodnić granicę własności dla komendy.
- FR-017: Autor ma jeden spis miejsc ze scalaniem duplikatów i unikalnością w obrębie drzewa. Priority: must-have. Change: preserved
  > Socrates: Counter-argument considered: "granice i nazwy historyczne (gubernie, powiaty w różnych latach)". Resolution: kept; historyczne jednostki administracyjne nie są w M-3 — do Open Questions (autor nie wybrał ich jako Non-Goal).

## User Stories

### US-01: Pierwszy import badań do drzewa z ręcznie wpisanymi osobami

- **Given** drzewo autora na produkcji zawiera część osób wpisanych ręcznie, a na serwerze leży kopia repo badawczego z blokami danych w plikach przodków i skanami
- **When** autor uruchamia komendę importu z e-mailem konta i katalogiem repo
- **Then** komenda pokazuje podsumowanie, raport pominiętych wpisów oraz dopasowania do istniejących osób i konflikty; autor rozstrzyga je pojedynczo, a po zatwierdzeniu osoby, relacje, wydarzenia (z poziomem A–D i datami nieostrymi), miejsca, źródła i skany trafiają do drzewa bez duplikatów, a ręczne poprawki zostają tam, gdzie autor tak wybrał
- **Before:** autor przepisywał każdą osobę, fakt i akt ręcznie z markdownu.

### US-02: Kolejny import po nowej sesji badawczej

- **Given** drzewo zostało wcześniej zaimportowane, a autor dopisał w repo nowe osoby i poprawił odczyt aktu
- **When** autor uruchamia import zaktualizowanej kopii repo
- **Then** podgląd pokazuje tylko nowe osoby i zmienione wartości (rekordy rozpoznane po stałym identyfikatorze z bloku), autor zatwierdza je pojedynczo, a nic się nie dubluje

### US-03: Wyszukanie osoby po wariancie nazwiska

- **Given** zaimportowana osoba Misiuk ma wariant zapisu Mysiuk
- **When** autor wpisuje „Mysiuk” w wyszukiwarce osób
- **Then** lista pokazuje tę osobę
- **Before:** wyszukiwarka znajdowała tylko po nazwisku i nazwisku rodowym.

## Business Logic

Osoba z repo badawczego jest tą samą osobą co osoba w drzewie, gdy zgadza się jej stały identyfikator z bloku danych albo — przy pierwszym imporcie — co najmniej dwa niezależne elementy poza imieniem i nazwiskiem; w każdym innym przypadku decyduje autor.

Wejścia: stały identyfikator z bloku (po pierwszym imporcie zapamiętany przy rekordzie), a przy pierwszym imporcie elementy osoby z repo i z drzewa (np. rodzice, rok i miejsce urodzenia, małżonek). Zasada „dwóch niezależnych zgodnych elementów” pochodzi z `CLAUDE.md` repo badawczego (nie scalać po samym imieniu i nazwisku). Wynik: osoba dopasowana pewnie, osoba nowa albo przypadek wątpliwy — ten ostatni zawsze trafia do autora w podglądzie.

Reguły pomocnicze:

- **Pierwszeństwo wartości:** wartość z repo zastępuje wartość w aplikacji bez pytania tylko wtedy, gdy w aplikacji nikt jej nie zmienił od ostatniego importu; inaczej to konflikt do decyzji autora (obie wartości w podglądzie).
- **Zniknięcie z repo:** rekord z identyfikatorem repo, którego w repo już nie ma (scalenie, błędna tożsamość, usunięty plik), trafia do podglądu jako „usunięty w repo”; autor decyduje: usuń albo zostaw. Rekordy bez identyfikatora repo (dodane tylko w aplikacji) nigdy nie są ruszane.
- **Miejsca:** miejsce z repo dopasowuje się do spisu drzewa; nowe miejsca i niepewne dopasowania (warianty pisowni) autor potwierdza w podglądzie.

## Constraints & Preserved Behavior

- **Integracja:** komenda czyta kopię repo badawczego (bloki danych + skany) skopiowaną na serwer; repo badawcze jest tylko czytane, nigdy zapisywane. Format bloku danych jest kontraktem między repo a aplikacją; zasady utrzymania bloku dopisuje się do `CLAUDE.md` repo badawczego.
- **Migracje danych:** nowe pola (stały identyfikator repo przy osobie/źródle/fakcie, poziom A–D przy fakcie, rodzaje daty zakres/przed/po, warianty nazwiska) dodawane jako puste; istniejące fakty bez poziomu (= nieokreślony), daty i wpisy bez zmian. Brak przepisywania istniejących wierszy. Migracje odwracalne zgodnie z zasadami repo.
- **Zgodność wstecz:** ręczne wpisywanie i edycja, istniejące daty (dokładne i „ok.”), źródła zawsze z plikiem, URL-e i panele bez zmian.
- **Zachowane:** granica własności drzewa (także dla komendy — test), jeden spis miejsc z unikalnością i scalaniem, dane dodane tylko w aplikacji, CI (lint bez ostrzeżeń, analiza statyczna, Pest) i E2E ścieżek krytycznych.
- **Deploy:** przez `scripts/deploy.sh` — `backup:run --only-db` przed `migrate` zostaje; nowe migracje idą tą samą ścieżką.
- **Repo badawcze:** zmiana jego formatu (blok danych) musi być zgodna z jego `CLAUDE.md` (nie zmyślać, fakt ze źródłem, poziomy A–D) i przeglądana przez autora.
- **Dane badawcze:** prawdziwe dane tylko lokalnie i na produkcji; nie trafiają do repo root-keepera, fixtures ani CI — testy na danych przykładowych.

## Non-Functional Requirements

- Przed zapisem importu na produkcji istnieje kopia bazy danych z chwili tuż przed importem.
- Prawdziwe dane badawcze nie pojawiają się w repozytorium root-keepera, w fixtures ani w CI; testy importu działają na danych przykładowych.
- Istniejące bramki jakości nie pogarszają się: CI przechodzi bez ostrzeżeń, E2E ścieżek krytycznych zielone.

## Product framing

- `product_type`: web-app — bez zmian (komenda importu to narzędzie operatora, nie nowa powierzchnia produktu).
- `target_scale.users`: small — bez zmian (jeden autor).
- `timeline_budget`: delivery_weeks 5 (4–5, koszt przyjęty), hard_deadline: null, after_hours_only: true.

## Non-Goals

- **Import GEDCOM i import z UI** — „profesjonalny” import GEDCOM w interfejsie to osobna, późniejsza praca; M-3 to komenda dla repo badawczego autora.
- **Synchronizacja w dwie strony** — aplikacja nigdy nie zapisuje do repo badawczego; poprawki wracają do repo ręcznie.
- **#37, #38, #39, #40, #41** — lista „Do zbadania” (repo ma TODO.md), znacznik źródła na karcie, wiele skanów naraz, eksport, widok potomków zostają w parked.

## Quality cross-check

Run 2026-10-23: Access Control, Business Logic, project artifacts, timeline-cost acknowledgment, Non-Goals, preserved behavior — all present. Note (outside the checklist): Success Criteria → Secondary is empty after dry run was dropped. User accepted.

## Open Questions (robocze)

1. Historyczne jednostki administracyjne (gubernie, powiaty, zmiany nazw w czasie) — czy i kiedy; dziś jeden rekord miejscowości (z rundy kontrargumentów FR-017).
2. Kiedy linie boczne (pliki bez numeru Ahnentafel) dostaną bloki danych i wejdą do importu (FR-001).
3. Gdzie trafią pozostałe notatki z repo (otwarte pytania, ciekawostki) — później, bez decyzji.
4. Jak wygląda podgląd w terminalu przy dziesiątkach konfliktów, żeby zatwierdzanie per zmiana było wykonalne (FR-005/FR-006).

## Forward: technical-roadmap

- Format bloku danych (np. YAML na górze pliku) i jego wersjonowanie — do ustalenia w planie; reguła utrzymania bloku dopisywana do `CLAUDE.md` repo badawczego.
- Komenda Artisan z interaktywnym zatwierdzaniem; kopia bazy (`backup:run --only-db`) tuż przed zapisem na produkcji.
- Ochrona flagi „od zera” przed produkcją (np. sprawdzenie środowiska).
- Test granicy własności dla komendy (odpowiednik `OwnershipBoundariesTest`) i testy importu na danych przykładowych, nigdy na prawdziwych.
