---
title: Kontrakt bloku osoby (repo badawcze → import)
version: 1
created: 2026-10-23
updated: 2026-11-16
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
| `urodzenie`, `zgon`, `pochowek` | nie | Wydarzenie: `data`, `miejsce`, `poziom`, `notatka`. Obecność klucza (także `{}`) oznacza, że wydarzenie zaszło. |
| `pochowek.cmentarz` | nie | Nazwa cmentarza, gdy w miejscowości jest ich kilka. Znany tylko w `pochowek`; w innych zdarzeniach to nieznany klucz (ostrzeżenie). |
| `malzenstwa` | nie | Lista: `z` (id współmałżonka) oraz `data`, `miejsce`, `poziom`, `notatka`. Zapis w plikach obojga małżonków, zgodny. |

Poziom pewności `poziom`: `A`–`D`, jak w prozie repo.

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

Pliki `_*.md` (indeksy) i podkatalogi `drzewo/` są ignorowane. Plik bez frontmattera to „brak bloku”, nie błąd.

## Wersjonowanie

Nowe opcjonalne klucze dochodzą w v1 razem z aktualizacją aplikacji. Zmiana znaczenia lub nazwy pola to `wersja: 2`. Komenda zna tylko wersje, które obsługuje.

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
- Sprawdź: `vendor/bin/sail artisan import:research <e-mail> <ścieżka-repo>` (pokazuje raport i podsumowanie, a zapisuje dopiero po odpowiedzi „tak”; do samego sprawdzenia bloku odpowiedz „nie”).
- Sprawdź nowe miejsca w podglądzie: import pyta raz o każdą nową miejscowość („Nowe miejscowości”), o każdą niepewną pisownię („Niepewne”, np. Bokinia przy Bukini w spisie) i o cmentarz, gdy w miejscowości jest ich kilka. Literówka w bloku wychodzi tu jako nowa miejscowość albo niepewne dopasowanie: popraw blok i uruchom ponownie zamiast dodawać miejscowość do spisu.
```

## Uwaga dla repo badawczego

Notatka `notatki/propozycja_struktury_plikow_2026-10-20.md` (linie 156–160) odrzuca YAML zamiast markdownu. PRD z 2026-10-23 jest późniejsze i wprowadza blok obok prozy; notatkę aktualizuje autor w repo (aplikacja tam nie pisze).
