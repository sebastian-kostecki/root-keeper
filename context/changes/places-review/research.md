---
date: 2026-10-20T10:08:49+02:00
researcher: Claude (Opus 5.5)
git_commit: 89b5bfd
branch: develop
repository: root-keeper
topic: "Przegląd modelu miejsc: cmentarze jako typ miejsca, szczegóły miejscowości, tożsamość parafii i „prowadzący akta”, inne typy miejsc"
tags: [research, places, localities, parishes, cemeteries, sources, gravestone, archive, data-model]
status: complete
last_updated: 2026-10-20
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje autora 1–6 (cmentarz jako rekord, pochowanie → cmentarz, parafia/urząd z wezwaniem, szczegóły miejscowości z „nie istnieje”, trzy zmiany)"
---

# Research: przegląd modelu miejsc (`places-review`)

**Date**: 2026-10-20T10:08:49+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 89b5bfd (drzewo robocze czyste poza `context/changes/{places-review,unsourced-facts-list}/`)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Użytkownik (`/10x-research places-review`):

- zastanawiam się nad dołożeniem cmentarzy jako kolejny typ miejsca (typ: komunalny, parafialny);
- może dokładniejsze informacje o miejscowościach;
- parafia: czy to nazwa, czy raczej wezwanie? Nazwa jest teraz unikalna, a unikalne powinno być wezwanie. Czy parafie w ogóle są potrzebne, czy raczej ogólne „miejsce na akta” (akta są parafialne, miejskie itp.) — do przemyślenia;
- może inne potrzebne typy miejsc? Jak robią to podobne aplikacje?

Zakres: tylko badanie (stan kodu, wcześniejsze decyzje, wzorce zewnętrzne). Wybory produktowe zebrane w „Open Questions”.

## Summary

1. **Cmentarz dziś nie jest bytem — to rola miejscowości.** Nagrobek wskazuje miejscowość przez `sources.cemetery_locality_id` (`database/migrations/2026_10_06_125653_add_cemetery_and_buried_people_to_sources.php:17`). Rodzaj/nazwę cmentarza świadomie wyłączono w S-09 z jedynym uzasadnieniem „cmentarz = miejscowość ze spisu (FR-002)” (`context/archive/2026-10-06-gravestone-cemetery-and-buried/plan.md:44`). Zewnętrznie dominuje model „cmentarz jako miejsce osadzone w miejscowości, z własnym typem” (FamilySearch Places typ *Cemetery*; Gramps — typ własny w hierarchii). Polskie prawo dzieli cmentarze wg zarządcy na **komunalne i wyznaniowe** (ustawa z 31.01.1959, art. 1) oraz **wojenne** (ustawa z 28.03.1933); „parafialny” to potoczna nazwa cmentarza wyznaniowego rzymskokatolickiego, więc „komunalny/parafialny” to w praktyce dwie osie: *zarządca* i *wyznanie*.
2. **Miejscowość ma tylko nazwę i opis** (`database/migrations/2026_10_05_081630_create_localities_table.php:18-19`), unikalną nazwę w drzewie (`:24`). Współrzędne, jednostki administracyjne i nazwy historyczne świadomie odłożono do opisu (`context/archive/2026-10-05-places-gazetteer/plan.md:41`, PRD Non-Goals `context/foundation/prd.md:238`). Powód przeciw współrzędnym (NFR „bez podmiotów trzecich”) usunięto z PRD 2026-10-04 (`context/foundation/prd.md:214,246`), ale tematu potem nie podjęto.
3. **Parafia: unikalna jest para nazwa + wyznanie, a nie sama nazwa** (`database/migrations/2026_10_05_114218_create_parishes_table.php:28`). Siedziba jest opcjonalna i nie należy do klucza, więc dwie parafie „Św. Jana” rzymskokatolickie w różnych miejscowościach dziś się wykluczają. **Wezwanie nie nadaje się na klucz** — setki parafii mają to samo wezwanie. Geneteka i szukajwarchiwach identyfikują parafię jako **siedziba + wyznanie (+ wezwanie, gdy w miejscowości jest więcej parafii)**, np. „Gąbin (ewang.)”, „Płock św. Bartłomieja (Fara)”, „Akta stanu cywilnego Parafii Rzymskokatolickiej św. Floriana w Krakowie”.
4. **„Parafia” to w praktyce *twórca aktu*, a akta tworzyły też inne urzędy.** W Królestwie Polskim od 1826 proboszcz był urzędnikiem stanu cywilnego; akta żydowskie prowadziły okręgi bóżnicze; w zaborze pruskim od 1874 Standesamt (USC); w Galicji od 1875 okręgi metrykalne dla Żydów; od 1946 USC. Geneteka trzyma parafie i urzędy na **jednej liście** („Gostynin USC”, „Warszawa ASC Cyrkuł I”). Wzorce (Evidence Explained, GEDCOM X, Gramps Repository) rozdzielają **twórcę** (parafia/urząd) od **przechowawcy** (archiwum + sygnatura) — root-keeper już ma ten podział (parafia ≠ archiwum, `context/foundation/prd.md:39`), brakuje tylko typu twórcy innego niż parafia. Nigdzie w `context/` nie rozważano aktów spoza parafii (grep USC/stanu cywilnego/Standesamt/okręg — brak wyników).
5. **Inne typy miejsc:** w programach genealogicznych pojawiają się kościół/miejsce kultu, folwark/majątek, jednostki administracyjne i kościelne (dekanat, diecezja). Dla jednoosobowej aplikacji najtańsze i najbardziej użyteczne okazują się: **rodzaj miejscowości** (miasto, wieś, kolonia, osada, przysiółek, folwark/majątek), **współrzędne**, **historyczna przynależność jako tekst** oraz ewentualnie **nazwy alternatywne**. Pełna datowana hierarchia (Gramps `PlaceRef.date`) to najdroższa część i rzadko się zwraca.

## Detailed Findings

### 1. Obecny model danych miejsc

**Miejscowość** — `localities(id, tree_id, name, description)`, `UNIQUE (tree_id, lower(name))` (`database/migrations/2026_10_05_081630_create_localities_table.php:16-24`). Brak kolumn typu, współrzędnych i administracji (grep `latitude|longitude|gmina|powiat|wojew|historical` w `app/`, migracjach i `resources/js/` — brak trafień, raport modelu). Panel: pola Nazwa i Opis (`resources/js/components/localities/locality-panel.tsx:135,148`). Ujednoznacznienie tych samych nazw odbywa się w samej nazwie, np. „Lipnica (pow. bocheński)” (`context/archive/2026-10-05-places-gazetteer/plan-brief.md:23`).

Miejscowość jest wskazywana przez (zbiór przenoszony przy scalaniu, `app/Actions/Localities/MergeLocality.php:41-44`): `parishes.locality_id`, `events.locality_id` (także pochowanie — `events.type='burial'`, `app/Enums/EventType.php:10`), `residences.locality_id`, `sources.cemetery_locality_id`. Usunięcie dozwolone tylko przy zerze tych odwołań (`app/Actions/Localities/DeleteLocality.php:16-40`).

**Parafia** — `parishes(id, tree_id, name, religion nullable, locality_id nullable)` (`database/migrations/2026_10_05_114218_create_parishes_table.php:16-22`), unikalność `(tree_id, lower(name), coalesce(religion,''))` (`:28`), komunikat „Parafia o tej nazwie i wyznaniu już istnieje.” (`app/Concerns/ParishValidationRules.php:27-43`). Wyznanie to enum osoby `App\Enums\Religion` (rzymskokatolickie, grekokatolickie, ewangelickie, prawosławne, mojżeszowe, inne; `app/Enums/Religion.php:14`). Etykieta „Nazwa (wyznanie)” z `Parish::label()` (`app/Models/Parish.php:42-45`) trafia na karty źródeł, tabelę, filtr „Miejsce” i wybór źródła do faktu (`app/Http/Resources/SourceResource.php:69-75`, `resources/js/lib/source-description.ts:17-38`). Parafia nie ma opisu. Wskazują ją tylko `sources.parish_id` (`app/Actions/Parishes/MergeParish.php:39`).

Dane lokalne (baza deweloperska, 2 parafie): nazwy zawierają siedzibę i wyznanie w tekście — „Parafia w Lubomlu” (bez siedziby), „Parafia rzymskokatolicka w Tarnowie” (siedziba Tarnów). Danych produkcyjnych nie sprawdzano.

**Cmentarz** — brak tabeli; `sources.cemetery_locality_id` → `localities` (`database/migrations/2026_10_06_125653_add_cemetery_and_buried_people_to_sources.php:17`), pole „Cmentarz” w formularzu nagrobka to zwykły `LocalityPicker` (`resources/js/components/sources/source-description-fields.tsx:164-174`), wyświetlane jako „cmentarz {miejscowość}” (`resources/js/lib/source-description.ts:17-27`). `FillBurialPlacesFromGravestone` tworzy pochowania z `locality_id = cemetery_locality_id` (`app/Actions/Sources/FillBurialPlacesFromGravestone.php:14-35`); podpowiedź miejsca pochowania w formularzu osoby (`app/Http/Resources/PersonFormResource.php:55,64-73`).

**Archiwum** — tekst na źródle, `sources.archive` varchar(255) (`database/migrations/2026_10_05_114945_add_parish_and_archive_to_sources_table.php:21`), podpowiedź „Np. Archiwum Diecezjalne w Tarnowie, sygnatura” (`source-description-fields.tsx:196-210`); osobny filtr z użytych wartości (`app/Http/Controllers/SourceController.php:143-155`).

**Strona `/places`** — przełącznik `?kind=` z dwoma segmentami Miejscowości / Parafie (`app/Http/Controllers/PlaceController.php:90-93`, `resources/js/pages/places/index.tsx:298-307`); reguła w `CLAUDE.md` („Miejscowości i parafie to jedna strona `/places` z przełącznikiem `?kind=`…”).

**Filtry źródeł** — „Miejsce” na `/sources` łączy miejscowości użyte jako cmentarz lub siedziba parafii oraz parafie użyte w aktach (`app/Http/Controllers/SourceController.php:116-140`, dopasowanie `resources/js/lib/sources-index.ts:261-279`); wybór źródła do faktu szuka tekstowo w `parish_name`/`locality_name` (`resources/js/lib/source-place-year-filter.ts:11-44`).

### 2. Cmentarz jako typ miejsca — wzorce i skutki

Wzorce zewnętrzne:
- **FamilySearch Places**: cmentarz to standardowe miejsce typu *Cemetery (20)* z jurysdykcją (miejscowość nadrzędna), współrzędnymi i datą ([lista typów](https://www.familysearch.org/en/fieldops/fs-places-familysearch-places-place-types), [przykład](https://api.familysearch.org/platform/places/10385250?flag=fsh)).
- **Gramps**: brak typu Cemetery w liście wbudowanej ([placetype.py](https://raw.githubusercontent.com/gramps-project/gramps/master/gramps/gen/lib/placetype.py)); użytkownicy tworzą własny typ wewnątrz miejscowości ([wątek](https://gramps.discourse.group/t/storage-of-grave-information/3241)).
- **GEDCOM 7**: `PLAC` to lista jednostek od najmniejszej, może zawierać „a specific building, farm, or cemetery” ([spec](https://gedcom.io/specifications/FamilySearchGEDCOMv7.html)).
- **Legacy / Family Historian**: cmentarz jako adres przy miejscu albo jako miejsce — dwa sposoby zapisu, które rozjeżdżają wyszukiwanie ([Legacy](https://legacyfamilytree.com/help/en/articles/12810117-how-to-search-for-cemeteries-and-burial-events), [FHUG](https://fhug.org.uk/kb/kb-article/working-with-places-and-addresses-for-new-users/)).
- **Find a Grave / BillionGraves**: cmentarz ma nazwę, nazwy alternatywne, lokalizację, GPS, opis, status „No Longer Exists” (Find a Grave; strony pomocy zwracały 403, opis z wyników wyszukiwania).

Rodzaje w Polsce: komunalne i wyznaniowe (ustawa o cmentarzach z 31.01.1959, art. 1 — [ELI](https://eli.gov.pl/api/acts/DU/1959/62/text.html)), wojenne (ustawa z 28.03.1933 — [ELI](https://eli.gov.pl/api/acts/DU/1933/311/text.html)); potocznie wg wyznania: parafialny (rzym.-kat.), ewangelicki, prawosławny, unicki, żydowski (kirkut), muzułmański (mizar) — tylko [pl.wikipedia](https://pl.wikipedia.org/wiki/Cmentarz), bez źródła prawnego dla pełnej listy.

Wzorzec w kodzie, który cmentarz mógłby powtórzyć: parafia jest już „miejscem osadzonym w miejscowości” (`parishes.locality_id`) na tej samej stronie `/places`, ze scalaniem, usuwaniem tylko nieużywanych i dopisywaniem w locie (`InlineParishController`). Cmentarz `(nazwa, miejscowość, zarządca, wyznanie, opis)` miałby identyczny kształt.

Miejsca w kodzie, których dotyka zamiana `cemetery_locality_id` na odwołanie do cmentarza (zbiór z raportu modelu, nie wyczerpujący poza wymienionymi ścieżkami):
- migracja danych: każda istniejąca para (drzewo, miejscowość cmentarza) → jeden cmentarz; „copy first, drop later” i test `tests/Feature/<Name>MigrationTest.php` (reguły `CLAUDE.md`);
- `MergeLocality` (przenosi `sources.cemetery_locality_id`, `:41-44`) i `DeleteLocality` (licznik `cemeterySources`);
- `LocalityResource` `usage.gravestones` i `lib/places-usage.ts:36-60`;
- `SourceResource::locality_name` dla nagrobka (`:73`), filtry `SourceController.php:116-140`, `sources-index.ts:261-279`, wybór źródła do faktu (`source-place-year-filter.ts`) — wyszukiwanie po miejscowości musi dalej trafiać nagrobek przez miejscowość cmentarza (decyzja PRD `context/foundation/prd.md:247`);
- `FillBurialPlacesFromGravestone` i `PersonFormResource` (podpowiedź miejsca pochowania);
- `/places` — trzeci segment i aktualizacja reguły w `CLAUDE.md`.

Otwarte: czy miejsce pochowania (`events.locality_id` przy `burial`) zostaje miejscowością, czy wskazuje cmentarz (zmienia FR-002 „pochowanie to samo miejsce”, `context/foundation/prd.md:241`).

### 3. Szczegóły miejscowości — wzorce

- **Gramps**: nazwa, nazwy alternatywne z datą i językiem, typ, współrzędne, kod, hierarchia „Enclosed by” z datami ([place.py](https://raw.githubusercontent.com/gramps-project/gramps/master/gramps/gen/lib/place.py), [placeref.py](https://raw.githubusercontent.com/gramps-project/gramps/master/gramps/gen/lib/placeref.py)).
- **GEDCOM X / FamilySearch**: `names`, `type`, `jurisdiction`, `latitude/longitude`, `temporalDescription` ([model](https://raw.githubusercontent.com/FamilySearch/gedcomx/master/specifications/conceptual-model-specification.md)).
- **Polska**: rodzaje miejscowości wg ustawy z 2003 o urzędowych nazwach miejscowości, art. 2: miasto, osiedle, wieś, osada, kolonia, przysiółek i części ([ELI 2019](https://api.sejm.gov.pl/eli/acts/DU/2019/1443/text.pdf); definicje przysiółka i osady leśnej niesprawdzone). Historycznie także folwark, majątek/dwór. Słownik geograficzny Królestwa Polskiego podaje przy haśle zwykle pow., gm., par., a w zaborze rosyjskim gub. ([Wikisource](https://wikisource.org/wiki/S%C5%82ownik_geograficzny_Kr%C3%B3lestwa_Polskiego/Bekersdorf)).
- Parafia to osobna oś (kościelna), nie poziom hierarchii administracyjnej — GEDCOM 7 wymienia hierarchie „political, ecclesiastical, and geographical” osobno.

Koszt w root-keeperze: pola tekstowe (rodzaj, przynależność historyczna) i współrzędne to kolumny na `localities` + pola w `locality-panel.tsx`. Nazwy alternatywne zmieniają wyszukiwanie w `LocalityPicker` i potencjalnie unikalność (`localities_tree_id_lower_name_unique`). Zmiana PRD Non-Goal „Brak nazw historycznych i zmian granic” (`context/foundation/prd.md:238`) to decyzja autora.

### 4. Parafia: tożsamość i „prowadzący akta”

Tożsamość w źródłach polskich:
- Geneteka: „Gąbin (ewang.)”, „Andrzejewo (mojż.)”, „Chotycze (gr.-kat.)”, „Płock św. Bartłomieja (Fara)”, „Warszawa św. Krzyż”, urzędy: „Gostynin USC”, „Warszawa ASC Cyrkuł I” ([mazowieckie](https://geneteka.genealodzy.pl/index.php?op=gt&lang=pol&w=07mz), [Warszawa](https://geneteka.genealodzy.pl/index.php?op=gt&lang=pol&w=71wa)).
- szukajwarchiwach: „Akta stanu cywilnego Parafii Rzymskokatolickiej w Brzozowie” ([60/441/0](https://www.szukajwarchiwach.gov.pl/en/zespol/-/zespol/156228)), z wezwaniem tylko w dużym mieście: „…św. Floriana w Krakowie” ([29/326/0](https://www.szukajwarchiwach.gov.pl/en/zespol/-/zespol/31523)); urzędy pruskie jako „Urząd Stanu Cywilnego w …” ([przykład](https://www.szukajwarchiwach.gov.pl/en/zespol/-/zespol/35870)).
- Wniosek: wezwanie to atrybut doprecyzowujący (gdy w siedzibie jest więcej parafii), nie klucz. Hipoteza „unikalne powinno być wezwanie” — **sprzeczna** z tymi źródłami; zgodna z nimi jest unikalność **siedziba + wyznanie + wezwanie**.

Kto prowadził akta (twórca):
- Królestwo Polskie od 1826: proboszcz jako urzędnik stanu cywilnego; akta żydowskie w okręgach bóżniczych prowadzone przez urzędników świeckich ([FS Wiki](https://www.familysearch.org/en/wiki/Russian_Poland_Civil_Registration), [JRI-Poland](https://www.jri-poland.org/?p=3882)).
- Zabór pruski: Standesamt od 1.10.1874, w całej Rzeszy od 1.01.1876 ([FS Wiki](https://www.familysearch.org/en/wiki/Germany_Civil_Registration)).
- Galicja: proboszczowie jako urzędnicy metrykalni (patenty 1782/1784), księgi żydowskie od 1875 w okręgach metrykalnych (JRI, źródło wtórne).
- Od 1.01.1946: świecki USC ([FS Wiki](https://www.familysearch.org/en/wiki/Poland_Civil_Registration)).

Twórca vs przechowawca: Gramps Repository („where sources are stored”: Archive, Church, Cemetery, Library… — [repotype.py](https://raw.githubusercontent.com/gramps-project/gramps/master/gramps/gen/lib/repotype.py)), GEDCOM 7 `REPO` + `CALN` (sygnatura), GEDCOM X `authors` / `repository`, Evidence Explained: twórcą jest jednostka jurysdykcyjna, repozytorium to ostatni element cytatu ([blog](https://evidenceexplained.com/node/1514)). W root-keeperze twórca = parafia (`sources.parish_id`), przechowawca = `sources.archive` (tekst) — podział już jest; zarządzaną listę archiwów odrzucono (`context/foundation/prd.md:242`).

Skutki uogólnienia parafii na „parafia / urząd” (typ twórcy): kolumna typu i wezwania na `parishes`, zmiana klucza unikalności (indeks wyrażeniowy z `:28`), etykieta `Parish::label()` (używana na kartach, w tabeli, filtrach i wyborze źródła), napisy „Parafia” w `source-description-fields.tsx:145-161`, `parish-panel.tsx`, `new-parish-dialog.tsx`, segment `/places`. Nazwy `parishes`/`parish_id` w kodzie mogą zostać (zmiana nazwy tabeli nie jest wymagana do zmiany etykiet). Istniejące nazwy typu „Parafia rzymskokatolicka w Tarnowie” nie rozbiją się automatycznie na siedzibę/wyznanie/wezwanie — migracja może tylko zachować nazwę, a wezwanie zostawić puste.

### 5. Inne typy miejsc

Z listy FamilySearch: Place of Worship, parafie wg wyznania, Deanery, Diocese, Civil Registration District, Village/Town/City/Hamlet/Farm/Estate/Colony, Municipality/District/Governorate/Province. Z punktu widzenia root-keepera:
- **kościół / miejsce kultu** — fakt ślubu wskazuje dziś miejscowość, a akt parafię; osobny kościół dublowałby parafię (wniosek, nie decyzja);
- **folwark / majątek / kolonia** — mieszczą się jako *rodzaj miejscowości*, nie osobny byt;
- **gmina / powiat / gubernia / diecezja / dekanat** — jednostki nadrzędne; najtańsza forma to tekst „przynależność historyczna” przy miejscowości (lub parafii); pełna hierarchia odrzucona dla `/places` w places-page (`context/archive/2026-10-12-places-page/research.md:98-107`);
- **urząd stanu cywilnego / okręg bóżniczy / okręg metrykalny** — rodzaje twórcy aktu (pkt 4), nie miejsca.

## Code References

- `database/migrations/2026_10_05_081630_create_localities_table.php:16-24` — `localities` (name, description), unikalna nazwa w drzewie.
- `database/migrations/2026_10_05_114218_create_parishes_table.php:16-28` — `parishes`, unikalność nazwa + wyznanie.
- `database/migrations/2026_10_05_114945_add_parish_and_archive_to_sources_table.php:20-24` — `sources.parish_id`, `sources.archive`.
- `database/migrations/2026_10_06_125653_add_cemetery_and_buried_people_to_sources.php:17-28` — `sources.cemetery_locality_id`, `buried_people`.
- `app/Models/Parish.php:42-45` — `label()` „Nazwa (wyznanie)”.
- `app/Concerns/ParishValidationRules.php:19-51`, `app/Concerns/LocalityValidationRules.php:16-38` — reguły i unikalność.
- `app/Actions/Localities/MergeLocality.php:41-44` — zbiór odwołań do miejscowości.
- `app/Actions/Parishes/MergeParish.php:39-54` — scalanie parafii.
- `app/Actions/Sources/FillBurialPlacesFromGravestone.php:14-35` — pochowanie z cmentarza nagrobka.
- `app/Http/Resources/SourceResource.php:69-75` — `parish_label`, `locality_name` (cmentarz lub siedziba).
- `app/Http/Controllers/SourceController.php:116-155` — opcje filtrów Miejsce i Archiwum.
- `app/Http/Controllers/PlaceController.php:20-93` — `/places`, `?kind=`.
- `resources/js/components/sources/source-description-fields.tsx:145-210` — pola Parafia, Cmentarz, Archiwum.
- `resources/js/components/parishes/parish-panel.tsx:207-257`, `resources/js/components/localities/locality-panel.tsx:135-148` — panele.
- `resources/js/lib/source-description.ts:17-38`, `resources/js/lib/sources-index.ts:261-279`, `resources/js/lib/source-place-year-filter.ts:11-44` — etykiety i dopasowanie po miejscu.

## Architecture Insights

- Wzorzec „rekord drzewa osadzony w miejscowości” już istnieje (parafia → `locality_id`) razem z całym zestawem: strona `/places` z `?kind=`, panel boczny, dopisanie w locie, scalanie w transakcji, usuwanie tylko nieużywanych, polityka `denyAsNotFound()`. Cmentarz jako byt powtórzyłby go bez nowej architektury; ogólna tabela `places(kind, parent_id)` byłaby nowym wzorcem i przepisaniem miejscowości i parafii.
- Wyszukiwanie źródła po miejscu opiera się na „miejscowości pośredniej” (siedziba parafii / miejscowość cmentarza) — każda zmiana modelu musi zachować tę ścieżkę.
- Zmiany danych podlegają regułom `CLAUDE.md` „Migrations that move existing data” (test migracji, copy first/drop later, `down()`); kolumny legacy po M-2 wciąż czekają na usunięcie (issue #35).

## Historical Context (from prior changes)

- `context/foundation/shape-notes.md:35-36,131` — parafia jako osobny spis (nie pole miejscowości), bo jedno archiwum trzyma księgi wielu parafii — **nadal aktualne**.
- `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md:138,149` — klucz nazwa + wyznanie, uzasadnienie „w jednym mieście bywa parafia rzymskokatolicka i prawosławna o tej samej nazwie” — **częściowo**: rozwiązuje ten przypadek, ale blokuje tę samą nazwę i wyznanie w różnych miejscowościach; wezwania nie rozważano.
- `context/archive/2026-10-06-gravestone-cemetery-and-buried/plan.md:44` — „Nazwa/rodzaj cmentarza poza miejscowością … — cmentarz = miejscowość ze spisu (FR-002); dopisek autor robi w nazwie lub notatce” — decyzja zakresu S-09, bez argumentu merytorycznego przeciw bytowi cmentarza.
- `context/archive/2026-10-05-places-gazetteer/plan.md:41` — bez współrzędnych, jednostki administracyjnej i nazw historycznych (do opisu); PRD `context/foundation/prd.md:238` Non-Goal nazw historycznych — **aktualne jako decyzja M-2**, do ewentualnej rewizji.
- `context/foundation/prd.md:122-123,242` — archiwum jako tekst z podpowiedziami, bez zarządzanej listy — **aktualne**.
- `context/archive/2026-10-12-places-page/research.md:98-109` — odrzucony widok drzewa „miejscowość → parafie” na `/places`; parafie miejscowości w jej panelu jako możliwy dodatek.
- `context/archive/2026-10-06-places-merge-and-delete/plan.md:43` — scalanie parafii o różnych wyznaniach dozwolone (domyślnie).

## Related Research

- `context/archive/2026-10-05-places-gazetteer/research.md`
- `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md`
- `context/archive/2026-10-06-gravestone-cemetery-and-buried/` (plan, plan-brief)
- `context/archive/2026-10-12-places-page/research.md`

## Decyzje autora (2026-10-20)

Odpowiedzi na pytania 1–6 poniżej (AskUserQuestion, dwie rundy):

1. **Cmentarz — osobny rekord** osadzony w miejscowości, jak parafia: nazwa, miejscowość, **zarządca** (komunalny / wyznaniowy / wojenny / inny) i **wyznanie** (enum `Religion`), opis; trzecia zakładka na `/places`; migracja z obecnych `sources.cemetery_locality_id`.
2. **Miejsce pochowania wskazuje cmentarz** (a przez niego miejscowość); podpowiedź z nagrobka podstawia ten sam cmentarz. Istniejące pochowania: miejscowość zostaje, cmentarz dopisany tam, gdzie jednoznaczny. Zmienia FR-002 i `context/foundation/prd.md:241`.
3. **Parafia identyfikowana przez siedzibę + wyznanie + wezwanie**: nowe pole wezwanie (opcjonalne), siedziba wymagana, etykieta budowana z pól jak w Geneteka („Płock św. Bartłomieja (rzym.-kat.)”). Istniejące nazwy trzeba przenieść (siedziba/wezwanie nie wynikają automatycznie z tekstu nazwy).
4. **Parafia uogólniona na „prowadzącego akta”**: ta sama lista z polem **rodzaj** (parafia / USC-Standesamt / okręg bóżniczy lub metrykalny / inny), w UI „Parafia / urząd”; archiwum zostaje tekstem (przechowawca).
5. **Szczegóły miejscowości**: rodzaj miejscowości, współrzędne, przynależność historyczna (tekst: gmina, powiat, gubernia/województwo, zabór) oraz — dopisane przez autora — **„nie istnieje dziś”**: przełącznik + opcjonalny opis (np. „zajęta pod zalew 1975”, „włączona do X”), widoczny znacznik na liście i w podpowiedziach. Autor: „czy dziś istnieje — to ważne, bo są nieistniejące obecnie”. **Nazw alternatywnych nie dodajemy.**
6. **Zakres: trzy osobne zmiany**, każda z własnym planem i migracją; `places-review` zostaje researchem, od którego startują:
   - szczegóły miejscowości (najmniejsza, niezależna);
   - parafia / urząd z wezwaniem (zmiana klucza unikalności i etykiety `Parish::label()`);
   - cmentarze (nowy rekord, migracja nagrobków i pochowań, filtry po miejscu, scalanie/usuwanie miejscowości).

Pytania 1–6 w sekcji poniżej są rozstrzygnięte powyższymi decyzjami; zostają jako zapis tego, co rozważano.

## Open Questions

Decyzje autora (produktowe), potrzebne przed `/10x-plan` — **rozstrzygnięte 2026-10-20, patrz „Decyzje autora”**:

1. **Cmentarz**: osobny rekord osadzony w miejscowości (jak parafia: nazwa, miejscowość, zarządca, wyznanie, opis) czy zostaje miejscowością z dopiskiem w nazwie/notatce? Jaki zestaw rodzajów: zarządca (komunalny / wyznaniowy / wojenny / inny) + wyznanie, czy jedna lista (parafialny, komunalny, żydowski, ewangelicki, wojenny…)?
2. **Miejsce pochowania**: zostaje miejscowością, czy wskazuje cmentarz (zmiana FR-002 i podpowiedzi z nagrobka)?
3. **Parafia — tożsamość**: wezwanie jako osobne pole; klucz siedziba + wyznanie + wezwanie (siedziba wymagana?) zamiast nazwa + wyznanie; czy etykieta budowana z pól („Płock św. Bartłomieja (rzym.-kat.)”), czy nazwa zostaje wpisywana ręcznie.
4. **Parafia → „parafia / urząd”**: dodać rodzaj twórcy aktu (parafia, USC/Standesamt, okręg bóżniczy/metrykalny, inny) czy zostać przy parafii (akta USC opisywać jako parafię z dopiskiem)? Nazwa w UI („Parafia / urząd”, „Kancelaria”, „Urząd metrykalny”).
5. **Szczegóły miejscowości**: które z: rodzaj (miasto, wieś, kolonia, osada, przysiółek, folwark/majątek), współrzędne (z mapą czy bez), przynależność historyczna (tekst: gmina, powiat, gubernia/województwo, zabór), nazwy alternatywne (np. niemiecka do 1945, z latami — wpływa na wyszukiwanie)? Każde poza opisem zmienia Non-Goal PRD `context/foundation/prd.md:238`.
6. **Zakres i kolejność**: jedna zmiana czy kilka (np. cmentarze; parafia/urząd z wezwaniem; szczegóły miejscowości) i czy otworzyć M-3 przez `/10x-prd` (zmiany FR-002, FR-007, FR-008 i Non-Goals), czy prowadzić jako zmiany poza roadmapą.

Luki: brak danych produkcyjnych (ile parafii/cmentarzy i jakie nazwy); poradniki AGAD i AP Warszawa (PDF) nieodczytane; dokumentacja RootsMagic, MyHeritage, Ancestry niezweryfikowana.
