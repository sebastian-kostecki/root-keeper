# Źródła ze skanami podpięte do faktów: Plan Brief

> Full plan: `context/changes/import-sources-with-scans/plan.md`

## What & Why

Import ma przenosić źródła z repo badawczego: opis aktu (parafia, rok, typ, archiwum, sygnatura, link) i plik skanu skopiowany do aplikacji, podpięte do faktów z numerem wpisu (S-06, FR-001, FR-011, US-01). Bez tego autor nadal przepisuje każdy akt ręcznie, a drzewo z importu nie ma dowodów. Decyzja z 2026-11-15: wchodzą tylko źródła ze skanem; reszta jest wypisana w raporcie z powodem.

## Starting Point

Źródło w aplikacji to jeden plik (jpg, png, webp, do 20 MB) z opisem. Numer wpisu leży na powiązaniu ze zdarzeniem. Źródło nie ma sygnatury ani klucza z repo, a import nie czyta `zrodla/`. Prawdziwe repo nie ma jeszcze żadnych bloków. Opisy źródeł to proza obejmująca wiele aktów, a większość skanów leży poza repo (w tym 27 PDF-ów).

## Desired End State

Autor wpisuje bloki `skany:` w plikach `zrodla/` i `zrodla: [{id, wpis}]` przy faktach osób. Komenda:
- raportuje pominięte skany,
- pyta o nowe i niejednoznaczne parafie,
- pyta o połączenie z ręcznie wgranym skanem tego samego faktu,
- po zgodzie tworzy źródła ze skanem, miniaturą, opisem i sygnaturą, podpięte do faktów.

Kolejny import aktualizuje opis, powiązania i plik według reguły trzech wartości. Pyta o źródła usunięte w repo lub w aplikacji, a bez zmian kończy „Nic do zapisania.”. Formularz źródła ma pole „Sygnatura”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Źródła bez skanu | Pominięte z powodem w raporcie; wejdą, gdy skan pojawi się w repo | Źródło w aplikacji zawsze ma plik (FR-011 bez zmian) | Roadmapa (2026-11-15) |
| Miejsce bloku | Frontmatter `skany:` w plikach `zrodla/`, a fakty wskazują `zrodla: [{id, wpis}]` | Blok obok prozy; jeden opis ma wiele aktów, a repo cytuje akty przy faktach | Plan (autor) |
| Model źródła | Jeden skan = jedno źródło; rozkładówka z kilkoma aktami to jedno źródło z kilkoma numerami wpisu | Pasuje do `event_source.entry_number` bez zmian schematu powiązań | Plan |
| Prefiks ID | `ZR-NNNN` | `Z-` zajęte przez zadania w repo badawczym | Plan (autor) |
| Sygnatura | Nowa kolumna `signature` z polem w formularzu; archiwum zostaje nazwą | Filtr archiwum nie rozpadnie się na pojedyncze sygnatury | Plan (autor) |
| Parafie | `parafia: {miejscowosc, wyznanie, rodzaj, wezwanie}`; siedziba jak miejscowość S-05, parafia jak cmentarz; nowe zatwierdzane tabelą | Te same reguły i pytania co S-05, raz na parafię, bez duplikatów | Plan (autor) |
| Ręcznie wgrane skany | Kandydat przy tym samym fakcie (zgodny typ i rok); pytanie „połącz / dodaj jako nowe”, domyślnie nowe | Bez duplikatów (US-01); ręczny plik zostaje (FR-014) | Plan (autor) |
| Połączenie | Ręczny plik zostaje, puste pola dostają wartości z repo, różne zostają bez pytania | Ręczne dane mają pierwszeństwo, a repo uzupełnia braki | Plan (autor) |
| Ponowny import | Pełny tor S-04: opis i powiązania regułą trzech wartości, nowy hash pliku jako zmiana, usunięte w repo i w aplikacji | Źródła zachowują się jak osoby i zdarzenia | Plan (autor) |
| Pliki nieobsługiwane | PDF, brak pliku, plik poza repo, ponad 20 MB: pominięte z powodem | Bez nowych zależności; autor kopiuje lub konwertuje skan do repo | Plan (autor) |
| Ręczne powiązania | Nie liczą się w porównaniu powiązań, więc nigdy nie znikają ani nie dają konfliktu | Import rusza tylko to, co sam przyniósł | Plan |
| Nagrobki, pochowanie | Poza S-06 | Wymagają cmentarza i pochowanych; pochowanie nie przyjmuje źródeł | Plan |

## Scope

**In scope:**
- sygnatura i `repo_id` na źródle, pole w formularzu,
- zapis i podmiana pliku z dysku,
- blok źródła i `zrodla` w kontrakcie v1,
- odczyt `zrodla/**` z bezpieczną ścieżką i hashem,
- dopasowanie parafii i ręcznych skanów,
- plan i zapis z regułą trzech wartości,
- pytania, raport i wynik w komendzie.

**Out of scope:**
- nagrobki z repo i źródła na pochowaniu,
- PDF-y i konwersja,
- wiele plików na źródło,
- odczyt z prozy, z `## Metadane` i z `manifest.tsv`,
- konwersja prawdziwego repo,
- przenoszenie sygnatur z archiwum,
- szukanie i filtr po sygnaturze,
- `events.parish_id`,
- poziomy A–D (S-08).

## Architecture / Approach

Kroki importu, z nowymi elementami pogrubionymi:
1. odczyt (**`ParseSourceBlock`**, `zrodla` w zdarzeniach),
2. tożsamość,
3. usunięte w repo i w aplikacji (**także źródła**),
4. miejsca (**parafie** w `MatchResearchPlaces` / `PlaceResolver`, token `P:`),
5. **`MatchResearchSources`** (połączenia),
6. `PlanImport` z **`PlanResearchSources`** (powiązania jako pole zdarzenia `zrodla`),
7. zmiany i konflikty,
8. zgoda i kopia bazy,
9. `ApplyImport` z **`WriteResearchSources`**: parafie, źródła (kopia przez `StoreSource` z `File`), powiązania. Pliki skopiowane w nieudanej transakcji są kasowane, a stare dopiero po commicie.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Źródło gotowe na import | `signature`, `repo_id`, pole w UI, `StoreSourceFile` / `ReplaceSourceFile` | Refaktor zapisu pliku psuje upload (testy SourcesTest) |
| 2. Kontrakt i odczyt | Blok `skany:`, `zrodla` w faktach, klucz `ZR-`, raport pominięć | Ścieżka skanu jako wektor wyjścia poza repo |
| 3. Dopasowanie parafii i skanów | Werdykty parafii, kandydaci „ten sam skan” | Za luźny kandydat skleja różne akty |
| 4. Plan importu | Nowe, zmienione, konflikty i usunięte dla opisu, pliku i powiązań | Zmiana pamięci zdarzeń daje fałszywe zmiany w istniejących drzewach |
| 5. Zapis | Atomowy zapis z kopią plików i sprzątaniem | Sieroty plików po wycofaniu albo utrata starego pliku |
| 6. Komenda | Pytania, podsumowanie, testy, ręczny przebieg | Czytelność przy dziesiątkach źródeł |

**Prerequisites:** S-05 zrobione (jest). Sail działa. Do ręcznych przebiegów autor przygotowuje `storage/gen-tree-import/test-pack-sources` z blokami i skanami.
**Estimated effort:** ~6 sesji, po jednej na fazę; najcięższe są fazy 4 i 5.

## Open Risks & Assumptions

- Prawdziwe repo nie ma bloków. Konwersja (ok. 108 opisów, ok. 780 aktów, skany z folderu „AKTA …” i PDF-y) to duża praca autora i nie jest częścią tego planu.
- Kopia repo na serwerze musi zawierać skany AGAD wyłączone z gita (kopia katalogu, nie `git clone`).
- Ręczne skany z sygnaturą wpisaną w archiwum zostają tak, jak są. Połączenie uzupełni pustą sygnaturę z repo, ale nie wyczyści archiwum.
- Pliki skanów nie są w kopii bazy. Przywrócenie bazy z kopii zostawia pliki nowszych źródeł bez rekordu.

## Success Criteria (Summary)

- Import tworzy źródła ze skanem, opisem, sygnaturą i parafią ze spisu, podpięte do faktów z numerem wpisu. Ręczne skany zostają, a spis nie dubluje parafii.
- Kolejny import bez zmian kończy się „Nic do zapisania.”, a zmiany, konflikty i usunięcia źródeł przechodzą przez ten sam tor co osoby.
- Repo badawcze i drzewo innego użytkownika są nietknięte, a pełny zestaw testów przechodzi.
