# Import — odczyt bloków danych z repo badawczego i raport — Plan Brief

> Full plan: `context/changes/research-blocks-read-and-report/plan.md`

## What & Why

Autor przepisuje badania z repo markdown do root-keepera ręcznie i to nie nadąża za badaniami. Pierwszy krok importu (M-3, S-01) to uzgodniony blok danych w plikach osób repo badawczego i komenda, która go czyta, sprawdza i raportuje — bez zapisu. Autor może więc konwertować pliki przodków i od razu widzieć błędy, zanim cokolwiek trafi do bazy.

## Starting Point

- **Repo badawcze:** w `drzewo/` jest 279 plików osób, w tym 187 przodków z numerem Ahnentafel. Treść to proza: brak frontmattera, identyfikatora osoby i bloków kodu; część plików ma CRLF.
- **Aplikacja:** brak komendy importu i `app/Actions/Import`. Parser YAML (`symfony/yaml`) jest tylko zależnością dev, więc na produkcji go nie ma.

## Desired End State

Kontrakt bloku osoby v1 jest opisany w `context/foundation/research-block-contract.md`, razem z gotową regułą do `CLAUDE.md` repo badawczego.

`vendor/bin/sail artisan import:research <e-mail> <repo>` czyta `drzewo/*.md` i wypisuje:
- ile plików przejrzano i ile ma blok,
- ile osób, relacji i małżeństw rozpoznano,
- tabelę pominiętych wpisów z powodem,
- tabelę ostrzeżeń.

Baza zostaje nietknięta.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Format bloku | YAML, `symfony/yaml` do `require` (nowa zależność zatwierdzona) | Najczytelniejszy dla autora i Claude w diffie, pakiet już jest w lockfile |
| Położenie i wersja | Frontmatter na górze pliku, `wersja: 1` | Jedno stałe miejsce, proza nietknięta, nieznana wersja odrzucana z powodem |
| Identyfikator osoby | `O-0001` (prefiks + numer, nigdy ponownie) | Czytelny w odwołaniach, zgodny z konwencją `K-07` w repo, niezależny od nazwy pliku |
| Zakres kontraktu v1 | Pełna osoba M-3 (imiona, nazwisko, warianty, płeć, Ahnentafel, rodzice, małżeństwa, urodzenie/zgon/pochówek z 5 rodzajami daty, miejscem jako tekst, poziomem A–D) | 187 plików konwertuje się raz; źródła dochodzą w S-06 |
| Małżeństwa | W plikach obojga małżonków, zgodnie; rozbieżne pominięte z ostrzeżeniem | Każdy plik kompletny jak dziś proza, nic nie wybiera się po cichu |
| Pomijanie a ostrzeżenie | Błąd kształtu (YAML, id, wersja, wartość pola, duplikat id) pomija wpis; odwołanie do osoby bez bloku, nieznany klucz, rozbieżne małżeństwo tylko ostrzegają | FR-004; konwersja po przodkach nie blokuje całych pokoleń |
| Gramatyka daty | `RRRR[-MM[-DD]]`, `ok. …`, `przed …`, `po …`, `między RRRR a RRRR`; juliańska w `notatka` | Pokrywa rodzaje dat z repo; nazwy rodzajów zgodne z przyszłym S-07 |

## Scope

**In scope:**
- dokument kontraktu z regułą do repo badawczego,
- `symfony/yaml` w `require`,
- Actions `ParsePersonBlock` i `ReadResearchRepo` oraz klasy wartości w `app/Support/Import/`,
- enumy `DateQualifier` i `ConfidenceLevel`,
- komenda `import:research` z raportem,
- wymyślone repo w `tests/Fixtures/research-repo/` i testy.

**Out of scope:**
- zapis do bazy, migracje, podgląd i zatwierdzanie (S-02 – S-04),
- dopasowanie miejsc (S-05), blok źródła i skany (S-06),
- parsowanie prozy i `_PRZODKOWIE.md`,
- konwersja plików w repo badawczym (robi ją autor z Claude) i linie boczne.

## Architecture / Approach

Komenda tylko rozpoznaje e-mail i ścieżkę, a potem drukuje raport.

`ReadResearchRepo` przegląda `drzewo/*.md` (bez `_*.md`) tylko do odczytu. Każdy plik trafia do `ParsePersonBlock`, który:
- zamienia CRLF na LF,
- wyciąga frontmatter,
- parsuje YAML z `PARSE_DATETIME`; pełna data bez cudzysłowu (obiekt daty) pomija wpis, bo PHP przesuwa dni niemożliwe, a rok-liczba staje się tekstem,
- waliduje blok przez `Validator::make` i gramatykę `ResearchDate`.

Potem `ReadResearchRepo` sprawdza zależności między plikami: duplikaty id, odwołania, płeć rodziców, numery Ahnentafel i małżeństwa. Wynik `ResearchRepoReading` jest typowany, żeby S-02 zapisywał z niego bez ponownego parsowania.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kontrakt bloku i parser YAML | Dokument kontraktu, reguła do repo, `symfony/yaml` w `require` | Kontrakt nie zmieści prawdziwego przypadku — wyjdzie to dopiero przy konwersji |
| 2. Odczyt i walidacja bloków | Actions, klasy wartości, wymyślone repo, testy | Pułapki YAML (data bez cudzysłowu → liczba), CRLF |
| 3. Komenda i raport | `import:research` z tabelami pominięć i ostrzeżeń | Raport nieczytelny przy dziesiątkach plików |

**Prerequisites:** brak (S-01 nie ma zależności). Do ręcznej weryfikacji potrzebnych jest kilka skonwertowanych plików przodków w kopii repo.
**Estimated effort:** ~2–3 sesje po godzinach w 3 fazach.

## Open Risks & Assumptions

- Notatka repo `propozycja_struktury_plikow_2026-10-20.md` odrzuca YAML zamiast markdownu. Zakładamy, że PRD z 2026-10-23 (blok obok prozy) ją zastępuje, a autor zaktualizuje notatkę w repo.
- Miejsce jako sam tekst może nie wystarczyć do rozpoznania wariantów nazw w S-05. Kontrakt pozwala dodać opcjonalne pola w v1.
- Kopia w `storage/gen-tree-import/` jest nieaktualna (246 zamiast 283 plików). Ręczny przebieg wymaga świeżej kopii.

## Success Criteria (Summary)

- Autor uruchamia komendę na repo z kilkoma skonwertowanymi przodkami i dostaje raport z liczbą osób, pominiętymi wpisami (z powodem do poprawienia) i ostrzeżeniami.
- Żadnego zapisu w bazie: testy dowodzą tego dla dwóch użytkowników o nakładających się danych.
- Testy, analiza statyczna i formatowanie przechodzą; `symfony/yaml` jest dostępny na produkcji.
