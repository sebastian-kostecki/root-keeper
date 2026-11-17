# Changelog

Wszystkie istotne zmiany w root-keeperze. Format oparty na [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/).

Numeracja wersji: wersja minor odpowiada kamieniowi milowemu z `context/foundation/roadmap.md` (0.3.0 = M-3), a wersja patch oznacza poprawki między kamieniami.

## [Unreleased]

### Dodane

- Repo badawcze na produkcji: klon git na hoście zamontowany w kontenerze tylko do odczytu pod `/research` (`RESEARCH_REPO_HOST_DIR`), więc `import:research <email> /research` działa na produkcji.

## [0.3.0] - 2026-11-17

Kamień milowy M-3 „Badania z repo widoczne w drzewie” oraz zmiany od 0.2.0: odświeżony wygląd drzewa, list i miejsc, cmentarze i urzędy w spisie miejsc, a także poprawki produkcyjne.

### Dodane

#### Import z repo badawczego (M-3)

- Komenda `import:research {email} {path}` przenosi przodków z numerem Ahnentafel z bloków danych w plikach repo badawczego do drzewa wskazanego konta. Format bloku osoby i źródła opisuje `context/foundation/research-block-contract.md`.
- Raport odczytu: liczba przeczytanych osób oraz pominięte wpisy z plikiem i powodem. Nieprawidłowy wpis nie zatrzymuje importu.
- Pierwszy import pokazuje podsumowanie i zapisuje dopiero po zatwierdzeniu. Zapisuje osoby, relacje rodzic–dziecko, małżeństwa oraz urodzenia, śluby i zgony. Każdy rekord pamięta stały identyfikator z repo.
- Dopasowanie do osób wpisanych ręcznie wymaga co najmniej dwóch zgodnych elementów poza imieniem i nazwiskiem. Przypadki wątpliwe i konflikty autor rozstrzyga pojedynczo w terminalu. Zdjęcia, skany i notatki dopasowanej osoby zostają.
- Kolejny import rozpoznaje rekordy po identyfikatorze z repo. Podgląd pokazuje rekordy nowe, zmienione, konflikty z ręcznymi zmianami (obie wartości) oraz usunięte w repo (do usunięcia albo zostawienia). Wartość z repo zastępuje wartość w aplikacji bez pytania tylko wtedy, gdy nikt jej nie zmienił od ostatniego importu. Rekordy dodane tylko w aplikacji nie są ruszane.
- Miejsca wydarzeń dopasowywane są do spisu: miejscowości (z wariantami nazw), parafii i urzędów oraz cmentarzy. Pochowanie wchodzi jako wydarzenie na cmentarzu. Nowe miejsca i niepewne dopasowania autor potwierdza w podglądzie.
- Źródła ze skanem są importowane z opisem (parafia, rok, typ aktu, archiwum, sygnatura, link) i podpinane do faktów z numerem wpisu. Plik skanu jest kopiowany do magazynu aplikacji, a repo badawcze pozostaje tylko do odczytu. Opis bez skanu trafia do raportu z powodem.
- Na produkcji przed pierwszym zapisem importu powstaje kopia bazy. Jeśli kopia się nie uda, nic nie jest zapisywane.
- Opcja `--from-scratch` (tylko poza produkcją) po potwierdzeniu usuwa osoby i fakty drzewa konta przed importem.

#### Fakty i osoby

- Daty nieostre: oprócz daty dokładnej i „ok.” można zapisać zakres lat, „przed” albo „po” — w formularzu, na osi czasu, w panelu osoby i w imporcie.
- Poziom pewności faktu A–D (A akt pierwotny, B indeks / źródło wtórne, C wskazówka, D hipoteza), ustawiany ręcznie i z importu. Istniejące fakty mają poziom nieokreślony.
- Warianty zapisu nazwiska widoczne przy osobie i na liście. Wyszukiwarka znajduje osobę po każdym wariancie, np. Misiuk po „Mysiuk”.
- Forma ślubu przy małżeństwie oraz jedno pole nazwiska rodowego.
- Zakładka „Wydarzenia” w panelu osoby jako oś z rokiem i wiekiem.

#### Miejsca

- Cmentarze jako rekordy spisu w zakładce „Cmentarze” na `/places`. Nagrobek wskazuje cmentarz, a pochowanie odbywa się na cmentarzu.
- Parafie i urzędy w jednej zakładce „Parafie i urzędy” (rodzaj, wyznanie, wezwanie) ze scalaniem duplikatów.
- Szczegóły miejscowości w panelu z podpowiedziami.
- Jedna strona `/places` z trzema zakładkami. Stare adresy `/localities` i `/parishes` przekierowują na nią.

#### Drzewo i listy

- Drzewo startuje od razu po zalogowaniu i wypełnia okno. Karty mają warianty, avatar zamiast paska zdjęcia i nazwę w jednej linii.
- Linie drzewa prowadzą od węzła pary (obrączki, pigułka, listwa płci).
- Panel dodawania osoby ze slotów drzewa, pusty stan drzewa i wybór osoby centralnej.
- Widok przodków z przełącznikiem głębokości i przyciskiem „Przodkowie” na drzewie.
- Lista osób z wyszukiwarką, skokami do litery i panelem tworzenia osoby.
- Pasek filtrów źródeł z pigułkami. Na telefonie filtry są zwinięte.

#### Bezpieczeństwo i infrastruktura

- Zabezpieczenia otwartej rejestracji: limit prób, weryfikacja e-maila w kolejce oraz powiadomienie właściciela o nowym koncie.
- Nagłówki bezpieczeństwa. Aplikacja ufa tylko adresowi klienta i schematowi przekazanym przez Traefika.
- Testy E2E (Playwright) w CI oraz Dependabot dla Composera i npm (PR-y do `develop`).

### Zmienione

- Produkcja serwuje aplikację przez nginx + PHP-FPM zamiast `artisan serve`.
- Produkcja działa pod adresem `genealogy.kostecki.dev` i dołącza do Traefika przez własną sieć aplikacji.
- Obraz produkcyjny nie zawiera sekretów ani danych użytkowników (`.dockerignore`).
- Tokeny projektu (kolory, typografia, fokus) i font z polskimi znakami (latin-ext) we wszystkich widokach.
- `symfony/yaml` jest zależnością produkcyjną, bo parsuje bloki danych importu.
- Zaktualizowane `laravel/framework` (13.42, poprawka podpisanych URL-i) i `laravel/wayfinder` (0.1.23).

### Usunięte

- Sześć starych kolumn osoby, wydarzenia i źródła zastąpionych w M-2, w tym pole źródła „Numer wpisu”. Stary numer wpisu został wcześniej przeniesiony na powiązanie fakt–źródło, a migracja przerywa się, jeśli jakaś wartość nie ma kopii.

### Poprawione

- Dopasowanie drzewa do ekranu po zmianie kolumny lub wysokości.
- Slot „Dodaj dziecko” na wysokość karty dziecka.
- Długa nazwa miejsca zawija się w przyklejonej kolumnie na wąskim ekranie.
- Odczyt argumentu archiwum w `backup:restore`.

### Migracje

Wdrożenie uruchamia `backup:run --only-db` przed `migrate`. Nowe migracje względem poprzedniego wdrożenia:

- `add_date_qualifier_to_events_table` — kopiuje flagę „ok.” do nowego rodzaju daty; `date_is_approximate` zostaje jako pole pochodne.
- `add_repo_id_to_people_events_and_parent_child_relations`, `create_repo_snapshots_table` — identyfikator repo i pamięć ostatniego importu.
- `create_locality_aliases_table` — warianty nazw miejscowości.
- `create_person_surname_variants_table` — warianty nazwiska.
- `add_signature_and_repo_id_to_sources_table` — sygnatura i identyfikator repo źródła.
- `add_confidence_to_events_table` — poziom pewności faktu.

## [0.2.0] - 2026-10-11

Kamień milowy M-2 „Opisane źródła, spis miejscowości i czytelne drzewo”.

### Dodane

- Spis miejscowości z opisem, wypełniony na start istniejącymi tekstowymi miejscami. Wybór miejsca urodzenia, ślubu, zgonu i pochowania ze spisu.
- Opis aktu parafią ze spisu (dopisywaną w locie), rokiem, typem aktu i archiwum z podpowiedziami.
- Źródło w szerokim panelu bocznym ze skanem obok opisu. Siatka źródeł z filtrami po rodzaju, parafii lub miejscu, roku i archiwum.
- Wyszukiwanie źródła po miejscu i roku przy podpinaniu do faktu. Numer wpisu jest przy powiązaniu fakt–źródło.
- Kilka miejscowości zamieszkania osoby z opcjonalnymi latami od–do.
- Nagrobek opisany cmentarzem ze spisu i osobami pochowanymi.
- Scalanie zduplikowanej miejscowości lub parafii i usuwanie nieużywanych wpisów.
- Panel małżeństwa z datą, miejscem, źródłem i dziećmi pary.
- Nazwisko rodowe („z d. …”, wyszukiwanie po obu nazwiskach) i wiek osoby.
- Zakładki wydarzeń i notatki w panelu osoby.
- Widok przodków z wyborem liczby pokoleń.
- Zdjęcie osoby na karcie drzewa (inicjały, gdy zdjęcia brak).
- Testy E2E powiązania fakt–źródło i dodawania relacji ze slotów drzewa.

### Zmienione

- Drzewo dopasowane do ekranu: większe karty, przybliżanie i linie dzieci od właściwej pary.
- Osoba w panelu bocznym obok zwężonego drzewa, z przechodzeniem do krewnych (wspólna powłoka panelu bocznego).

## [0.1.0] - 2026-10-04

Kamień milowy M-1 „Drzewo potwierdzone źródłami”.

### Dodane

- Konto z własnym drzewem. Dane innych kont są niewidoczne (404).
- Osoby: dodawanie, edycja i usuwanie po potwierdzeniu, wyszukiwanie na liście, podgląd w modalu z przechodzeniem do krewnych.
- Relacje rodzic–dziecko i małżeństwa z datą i miejscem, dodawane z pustych slotów drzewa (rodzic, dziecko, małżonek).
- Graficzne drzewo z przejściem do osób.
- Źródła różnych rodzajów (akt metrykalny, nagrobek, inne) ze skanem oglądanym w powiększeniu.
- Potwierdzanie urodzenia, małżeństwa i zgonu źródłem, z podpowiedzią aktów pasującego typu.
- Usuwanie źródła po pokazaniu faktów, które stracą potwierdzenie.
- Produkcja z codzienną kopią bazy i skanów oraz ochroną danych przed podmiotami trzecimi.

[Unreleased]: https://github.com/sebastian-kostecki/root-keeper/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/sebastian-kostecki/root-keeper/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/sebastian-kostecki/root-keeper/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/sebastian-kostecki/root-keeper/releases/tag/v0.1.0
