---
title: Kontrakt bloku osoby (repo badawcze → import)
version: 1
created: 2026-10-23
updated: 2026-11-17
---

# Kontrakt bloku osoby v1

Blok danych osoby to frontmatter YAML na samej górze pliku osoby w `drzewo/`, przed nagłówkiem H1. Proza pod blokiem pozostaje bez zmian. Aplikacja (`import:research`) czyta tylko blok; nigdy nie pisze w repo badawczym.

## Przykład (dane wymyślone)

```yaml
---
wersja: 1                       # wymagane, tylko 1
id: O-0042                      # wymagane, ^O-\d{4,}$, stałe, nigdy nie używane ponownie
ahnentafel: [42]                # opcjonalne, lista liczb > 0; implex: [50, 142]
imiona: Jan                     # wymagane imiona albo nazwisko
nazwisko: Kowal                 # nazwisko rodowe
warianty_nazwiska: [Kowalski]   # opcjonalne, lista tekstów
plec: M                         # opcjonalne, M | K
rodzice:                        # opcjonalne
  ojciec: O-0084
  matka: O-0085
urodzenie:                      # opcjonalne; obecność klucza = wydarzenie zaszło
  data: "1801-05-12"            # pełna data zawsze w cudzysłowie
  miejsce: Wieś Przykładowa     # nazwa miejscowości, mianownik
  poziom: A                     # opcjonalne, A | B | C | D
  notatka: "24 IV 1801 st.st."  # opcjonalne (styl juliański, uwagi)
zgon: {}                        # zmarł, data nieznana
pochowek:                       # miejsce = miejscowość cmentarza
  miejsce: Wieś Przykładowa
  cmentarz: Cmentarz parafialny # opcjonalne; nazwa cmentarza, gdy w miejscowości jest ich kilka
malzenstwa:                     # opcjonalne; w plikach obojga małżonków, zgodnie
  - z: O-0043
    data: ok. 1825
    miejsce: Wieś Przykładowa
    poziom: B
---
```

## Pola

| Pole | Wymagane | Opis |
|---|---|---|
| `wersja` | tak | Liczba `1`. Inna wartość = wpis pominięty. |
| `id` | tak | `O-NNNN` (min. 4 cyfry). Stałe; nie zmienia się przy zmianie nazwy pliku; nie jest używane ponownie. |
| `ahnentafel` | nie | Lista liczb dodatnich (implex: kilka numerów). |
| `imiona`, `nazwisko` | co najmniej jedno | Nazwisko = rodowe. |
| `warianty_nazwiska` | nie | Lista tekstów. Import zapisuje je przy osobie (bez powtórzeń, bez względu na wielkość liter); wyszukiwarka osób znajduje osobę po każdym wariancie. |
| `plec` | nie | `M` albo `K`. |
| `rodzice.ojciec`, `rodzice.matka` | nie | `id` rodzica. |
| `urodzenie`, `zgon`, `pochowek` | nie | Wydarzenie: `data`, `miejsce`, `poziom` (import zapisuje go przy fakcie i porównuje przy ponownym imporcie), `notatka`. Obecność klucza (także `{}`) oznacza, że wydarzenie zaszło. |
| `pochowek.cmentarz` | nie | Nazwa cmentarza, gdy w miejscowości jest ich kilka. Znany tylko w `pochowek`; w innych zdarzeniach to nieznany klucz (ostrzeżenie). |
| `malzenstwa` | nie | Lista: `z` (id współmałżonka) oraz `data`, `miejsce`, `poziom`, `notatka`, `zrodla`. Zapis w plikach obojga małżonków, zgodny (poza `zrodla`, zob. niżej). |
| `urodzenie.zrodla`, `zgon.zrodla`, `malzenstwa[].zrodla` | nie | Lista `{id: ZR-NNNN, wpis: "15/1837"}`: źródła, które potwierdzają fakt. `id` wskazuje wpis w bloku źródła, `wpis` (opcjonalny, do 50 znaków, liczba zamienia się na tekst) to numer wpisu w akcie. Powtórzone `id` w jednym zdarzeniu: ostrzeżenie, zostaje pierwsze. W `pochowek` to nieznany klucz (pochowanie nie przyjmuje źródeł). |

`zrodla` ślubu wystarczy wpisać u jednego małżonka. Gdy są u obojga, muszą być takie same (kolejność bez znaczenia); dwie różne niepuste listy pomijają małżeństwo z ostrzeżeniem. Pusta lista u jednego małżonka bierze listę drugiego.

Poziom pewności `poziom`: `A`–`D`, jak w prozie repo. Import zapisuje go w polu „Poziom pewności” faktu (urodzenie, zgon, pochowanie, ślub), a nie w notatce osoby. Przy ponownym imporcie porównuje go jak datę i miejsce: zmiana w repo trafia do aplikacji, gdy nikt nie zmienił poziomu ręcznie, a rozbieżność obu stron zostaje konfliktem do rozstrzygnięcia.

## Blok źródła

Blok źródła to frontmatter YAML na samej górze pliku w `zrodla/` (rekurencyjnie, bez plików `_*`). Jeden plik może opisywać kilka skanów. Import przenosi tylko źródła ze skanem: wpis bez pliku albo z plikiem, którego aplikacja nie przyjmie, jest pomijany z powodem w raporcie, a kolejny import doda źródło, gdy skan pojawi się w repo.

### Przykład (dane wymyślone)

```yaml
---
wersja: 1                         # wymagane, tylko 1
skany:                            # wymagane, lista
  - id: ZR-0012                   # wymagane, ^ZR-\d{4,}$, stałe, nigdy nie używane ponownie
    plik: zrodla/skany/wies_1837_akt15.jpg   # ścieżka od korzenia repo; brak = wpis pominięty („brak skanu”)
    rodzaj: akt                   # wymagane: akt | inne
    typ: urodzenie                # tylko przy akt: urodzenie | slub | zgon | inne
    nazwa: Akt urodzenia 15/1837  # opcjonalne
    rok: 1837                     # opcjonalne, 1000…bieżący
    parafia:                      # tylko przy akt
      miejscowosc: Wieś Przykładowa   # wymagane w parafii; siedziba, mianownik
      wyznanie: rzymskokatolickie     # opcjonalne
      rodzaj: parafia                 # opcjonalne, domyślnie parafia
      wezwanie: św. Jana              # opcjonalne
    archiwum: AP Przykładowe      # opcjonalne, sama nazwa archiwum
    sygnatura: 35/1813/0/2.4/58   # opcjonalne
    link: https://example.org/skan/34   # opcjonalne, http(s)
    notatka: "skan 34, prawa strona"    # opcjonalne
---
```

Fakt w bloku osoby wskazuje źródło po `id`: `urodzenie: {zrodla: [{id: ZR-0012, wpis: "15/1837"}]}`.

### Pola

| Pole | Wymagane | Opis |
|---|---|---|
| `wersja` | tak | Liczba `1`. Inna wartość = cały plik pominięty. |
| `skany` | tak | Lista wpisów. Nie lista = cały plik pominięty. |
| `id` | tak | `ZR-NNNN` (min. 4 cyfry). Stałe, nie używane ponownie. Ten sam `id` w dwóch wpisach (w jednym pliku albo w różnych) pomija wszystkie te wpisy. |
| `plik` | tak (bez niego wpis jest pomijany) | Ścieżka względna od korzenia repo do skanu: jpg, png albo webp, do 20 MB. |
| `rodzaj` | tak | `akt` albo `inne`. `nagrobek` jest pomijany („nagrobki: osobna zmiana”). |
| `typ` | nie | Tylko przy `rodzaj: akt`. Przy `inne` pole jest pomijane z ostrzeżeniem. |
| `nazwa`, `rok`, `archiwum`, `sygnatura`, `link`, `notatka` | nie | `rok` 1000…bieżący, `link` to http(s). |
| `parafia` | nie | Tylko przy `rodzaj: akt` (przy `inne` pomijana z ostrzeżeniem): `miejscowosc` (wymagane), `wyznanie`, `rodzaj`, `wezwanie`. |

### Mapowanie wartości na aplikację

| Pole bloku | Wartość | Aplikacja |
|---|---|---|
| `rodzaj` | `akt` | `SourceKind::MetricalRecord` (`metrical_record`) |
| `rodzaj` | `inne` | `SourceKind::Other` (`other`) |
| `typ` | `urodzenie` / `slub` / `zgon` / `inne` | `SourceRecordType` `birth` / `marriage` / `death` / `other` |
| `parafia.wyznanie` | `rzymskokatolickie` | `Religion::RomanCatholic` (`roman_catholic`) |
| `parafia.wyznanie` | `grekokatolickie` | `Religion::GreekCatholic` (`greek_catholic`) |
| `parafia.wyznanie` | `ewangelickie` | `Religion::Evangelical` (`evangelical`) |
| `parafia.wyznanie` | `prawoslawne` | `Religion::Orthodox` (`orthodox`) |
| `parafia.wyznanie` | `mojzeszowe` | `Religion::Jewish` (`jewish`) |
| `parafia.wyznanie` | `inne` | `Religion::Other` (`other`) |
| `parafia.rodzaj` | `parafia` / `usc` / `okreg_bozniczy` / `inny` | `ParishKind` `parish` / `civil_registry` / `jewish_district` / `other` |

Wartość spoza listy (np. „greckokatolickie”) pomija wpis z powodem.

### Pominięcie a ostrzeżenie (źródła)

**Pominięcie wpisu** (reszta pliku idzie dalej): brak `plik` („brak skanu — źródło wejdzie, gdy skan pojawi się w repo”), plik nie istnieje, plik poza repo (ścieżka bezwzględna, `..`, dowiązanie poza repo), PDF („zapisz skan jako JPG”), inny format niż jpg, png, webp (rozszerzenie i zawartość), plik większy niż 20 MB, `rodzaj: nagrobek`, zły `id`, zła wartość `rodzaj`, `typ`, `wyznanie`, `parafia.rodzaj`, zły `rok` albo `link`, duplikat `id` (pominięte są wszystkie wpisy z tym `id`, także poprawny, gdy drugi został pominięty z innego powodu).

**Pominięcie pliku**: błąd składni YAML, brak albo zła `wersja`, `skany` niebędące listą, plik `.md` będący dowiązaniem poza repo („Plik poza repo”).

**Ostrzeżenie**: nieznany klucz, `typ` lub `parafia` przy `rodzaj: inne` (pole pominięte), `archiwum` wygląda na sygnaturę (cyfra po przecinku), ten sam plik skanu pod dwoma `id`, odwołanie `zrodla` do `id` bez poprawnego wpisu („źródło ZR-… nie istnieje albo zostało pominięte”), powtórzone `id` w `zrodla` jednego zdarzenia, rozbieżne `zrodla` ślubu u obojga małżonków (małżeństwo pominięte).

Plik w `zrodla/` bez frontmattera to „bez bloku”, nie błąd. Aplikacja tylko czyta skan i kopiuje go do własnego magazynu; nigdy nie przenosi ani nie usuwa pliku w repo.

Brak katalogu `zrodla/` to zero źródeł w raporcie, ale nie „wszystkie źródła usunięte w repo”: gdy drzewo ma źródła z importu, usunięcia źródeł są wstrzymane z ostrzeżeniem, bo katalog mógł zostać przeniesiony albo niezsynchronizowany. Żeby usunąć z aplikacji wszystkie źródła z repo, zostaw pusty katalog `zrodla/`.

## Miejsca

- `miejsce` to nazwa miejscowości w mianowniku, bez parafii, gminy i nawiasów (`Zalesie`, nie `Zalesie (par. Uchanie)`). Gdy znana jest tylko parafia, zostaw `miejsce` puste; parafie przyjdą osobną zmianą (S-06).
- `pochowek.miejsce` to miejscowość cmentarza, a opcjonalne `pochowek.cmentarz` to nazwa cmentarza, gdy w miejscowości jest ich kilka.
- Wariant pisowni (`Bokinia` obok `Bukinia`) zostaje w prozie. W bloku stoi jedna nazwa.
- `pochowek` bez `zgon` aplikacja czyta jako zgon bez daty (ostrzeżenie).

Decyzja z 2026-10-30: to poprawka v1 bez zmiany numeru wersji, bo pole `pochowek` nie było użyte w żadnym bloku przed tą datą.

## Gramatyka `data`

- `RRRR`, `RRRR-MM`, `RRRR-MM-DD` — dokładna (także częściowa),
- `ok. <data>` — przybliżona,
- `przed <data>`, `po <data>`,
- `między RRRR a RRRR` — zakres, od < do.

Rok w przedziale 1000…bieżący, data prawdziwa kalendarzowo. Daty piszemy w kalendarzu gregoriańskim; styl juliański idzie do `notatka`.

Data z miesiącem i dniem stoi w cudzysłowie (`data: "1801-05-12"`). Bez cudzysłowu YAML zamienia ją w obiekt daty, a PHP po cichu przesuwa dzień niemożliwy (`1801-02-31` → `1801-03-03`), więc taki wpis jest pomijany z powodem „ujmij datę w cudzysłów”. Sam rok może być liczbą (`data: 1801`).

## Pominięcie a ostrzeżenie

**Pominięcie** (wpis nie wchodzi do importu, raport podaje plik i powód): niezamknięty frontmatter lub błąd składni YAML, blok niebędący mapą, brak/zła `wersja`, brak/zły `id`, brak imion i nazwiska, zła `plec`, zły `ahnentafel`, zła `data`, data bez cudzysłowu, zły `poziom`, odwołanie niezgodne z wzorcem lub wskazujące na własne `id`, zły typ wartości, duplikat `id` w kilku plikach (wszystkie wpisy pominięte).

**Ostrzeżenie** (osoba przeczytana): nieznany klucz, odwołanie do `id` bez poprawnego bloku, rodzic o niezgodnej płci, ten sam numer `ahnentafel` u dwóch osób, małżeństwo zapisane tylko po jednej stronie, rozbieżne dane małżeństwa po obu stronach (małżeństwo pominięte), ta sama para dwa razy w jednym pliku, blok niezaczynający się w pierwszej linii pliku, `miejsce` z nawiasem, przecinkiem albo `par.` / `parafi` („miejsce wygląda na opis, nie nazwę miejscowości”; wartość zostaje), `pochowek` bez `zgon` („pochowanie bez zgonu: dodano zgon bez daty”).

Pliki `_*.md` (indeksy) i podkatalogi `drzewo/` są ignorowane (w `zrodla/` podkatalogi są czytane). Plik bez frontmattera to „brak bloku”, nie błąd.

## Wersjonowanie

Nowe opcjonalne klucze dochodzą w v1 razem z aktualizacją aplikacji. Tak weszły blok źródła i klucz `zrodla` (2026-11-16): to nowe opcjonalne klucze, bez zmiany znaczenia istniejących. Zmiana znaczenia lub nazwy pola to `wersja: 2`. Komenda zna tylko wersje, które obsługuje.

## Reguła do wklejenia w `CLAUDE.md` repo badawczego

```markdown
## Blok danych osoby (frontmatter YAML)

- Każdy plik osoby w `drzewo/` zaczyna się blokiem YAML według `context/foundation/research-block-contract.md` aplikacji root-keeper (wersja 1), przed nagłówkiem H1. Proza pod blokiem zostaje.
- `miejsce` to sama nazwa miejscowości w mianowniku, bez parafii, gminy i nawiasów. Wariant pisowni zostaje w prozie, a w bloku stoi jedna nazwa. `pochowek.miejsce` to miejscowość cmentarza, a `pochowek.cmentarz` (opcjonalnie) to nazwa cmentarza; podaj ją, gdy w miejscowości jest kilka cmentarzy. Gdy znasz tylko parafię, zostaw `miejsce` puste. `pochowek` bez `zgon` dodaje zgon bez daty.
- Nie zmyślaj: wpisuj do bloku tylko to, co wynika z prozy pliku. Brak wiedzy = brak klucza.
- Blok musi zgadzać się z prozą. Zmieniasz prozę (data, rodzice, małżeństwo) — zmień blok w tym samym commicie.
- Nowa osoba dostaje kolejne `O-NNNN`. Numer nigdy nie jest używany ponownie, także po usunięciu osoby.
- Nie zmieniaj `id` przy zmianie nazwy pliku.
- Pełną datę (`RRRR-MM-DD`) zawsze ujmuj w cudzysłów. Daty w kalendarzu gregoriańskim; styl juliański w `notatka`.
- Małżeństwo zapisuj w plikach obojga małżonków, z tymi samymi danymi.
- Źródła: plik w `zrodla/` zaczyna się blokiem `skany:` (zob. „Blok źródła” w kontrakcie). Jeden skan to jeden wpis z własnym `ZR-NNNN`. Rozkładówka z kilkoma aktami to jeden wpis, a numery aktów stoją przy faktach w `zrodla: [{id, wpis}]`; ten sam plik pod dwoma `id` daje ostrzeżenie. ID `ZR-` nigdy nie jest używane ponownie, także po usunięciu źródła. Skan leży w `zrodla/skany/` jako jpg, png albo webp do 20 MB; PDF zapisz jako JPG. `zrodla` ślubu wystarczy wpisać u jednego małżonka; jeśli są u obojga, muszą być takie same. Pochowanie nie ma `zrodla`.
- Sprawdź: `vendor/bin/sail artisan import:research <e-mail> <ścieżka-repo>` (pokazuje raport i podsumowanie, a zapisuje dopiero po odpowiedzi „tak”; do samego sprawdzenia bloku odpowiedz „nie”).
- Sprawdź nowe miejsca w podglądzie: import pyta raz o każdą nową miejscowość („Nowe miejscowości”), o każdą niepewną pisownię („Niepewne”, np. Bokinia przy Bukini w spisie) i o cmentarz, gdy w miejscowości jest ich kilka. Literówka w bloku wychodzi tu jako nowa miejscowość albo niepewne dopasowanie: popraw blok i uruchom ponownie zamiast dodawać miejscowość do spisu.
- Sprawdź parafie i połączenia źródeł w podglądzie: import pyta o nowe parafie i urzędy („Nowe parafie i urzędy”: zatwierdź wszystkie albo odpowiedz pojedynczo „Dodaj do spisu” / „Bez parafii”), o parafię, gdy w siedzibie pasuje kilka („Dodaj nową” / „Bez parafii”), i o każdy skan, który może być już ręcznie wgranym źródłem („To ten sam skan?”: „Dodaj jako nowe” albo „Połącz z <nazwa pliku>”). Literówka w siedzibie `parafia.miejscowosc` wychodzi jako nowa miejscowość albo nowa parafia: popraw blok i uruchom ponownie zamiast dodawać ją do spisu.
- Czytaj raport „Pominięte skany” (plik bloku, `ZR-`, powód): pominięty skan nie wchodzi do aplikacji, a jego powiązania z faktami są wtedy ignorowane. Popraw powód (dodaj `plik`, skopiuj skan do `zrodla/skany/`, zapisz PDF jako JPG, zmniejsz plik poniżej 20 MB) i uruchom ponownie; źródło wejdzie przy następnym imporcie.
```

## Uwaga dla repo badawczego

Notatka `notatki/propozycja_struktury_plikow_2026-10-20.md` (linie 156–160) odrzuca YAML zamiast markdownu. PRD z 2026-10-23 jest późniejsze i wprowadza blok obok prozy; notatkę aktualizuje autor w repo (aplikacja tam nie pisze).
