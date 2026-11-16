<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Źródła ze skanami podpięte do faktów

- **Plan**: context/changes/import-sources-with-scans/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-11-16
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 3 observations

Kod wszystkich sześciu faz został przejrzany (commity e4f0633, 25aef2b, 70fbc4d, d14b38b, 90b8665, 7d4c936). Automatyczne kryteria wszystkich faz są `[x]`. Ręczne punkty 1.5, 2.3 i 6.5–6.8 czekają na przebieg na `test-pack-sources`.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Kryteria automatyczne, uruchomione 2026-11-16:
- **Testy PHP.** `artisan test --compact`: 1687 passed. Pierwszy przebieg miał błędy z innej sesji, która równolegle puszczała testy na tej samej bazie `testing`. Powtórzony w czystych warunkach przeszedł w całości.
- **Analiza statyczna.** `composer types:check`: 0 błędów.
- **Formatowanie.** `pint --dirty`: passed.
- **Frontend.** `npm run check`, `types:check` i `test:js`: passed.
- **Migracja.** `migrate:rollback --step=1 && migrate`: OK. Kolumny były puste w bazie dev, więc cofnięcie niczego nie usunęło.

Ręczne punkty 1.5, 2.3 i 6.5–6.8 czekają na przebieg na `test-pack-sources`.

Potwierdzone jako zgodne z planem:
- **Bezpieczeństwo ścieżki skanu.** `realpath` w korzeniu repo, dowiązanie poza repo, MIME z zawartości, limit 20 MB.
- **Repo tylko do odczytu.** Test sprawdza hashe plików.
- **Zakres drzewa** przy każdym odczycie i dopasowaniu.
- **Pliki przy zapisie.** `CopiedFiles` sprząta przy wycofaniu, a `DB::afterCommit` kasuje stare pliki.
- **Porównanie bez `zrodla`.** `resolvePair` pomija `zrodla` i scala listy.
- **Reguła trzech wartości** dla `zrodla`.
- **Werdykty parafii.**
- **Wstrzymanie usunięć** tylko dla źródeł.
- **Kolejność pytań** w komendzie.
- **Drugi użytkownik** w każdym teście importu.
- **Zależności.** Brak nowych zależności spoza `require`.

## Findings

### F1 — Źródło wstrzymane od usunięcia rozjeżdża plan i zapis powiązań

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyImport.php:1215 (`unlinkedSources`), app/Actions/Import/WriteResearchSources.php:236 (pętla `detach`), app/Actions/Import/PlanResearchSources.php:83,206-218
- **Detail**: Gdy plik w `zrodla/` ma błąd YAML albo wpis bez ID, źródła drzewa z `repo_id`, których brak w odczycie, trafiają do `unassessable`. Zostają z `repo_id`, ale nie są w `available`.
  - **Planer.** `appLinks()` bierze tylko `comparable()` ID, więc `appLabel` planu ich nie zawiera.
  - **Strażnik zapisu.** `sourceLinksLabel` pomija tylko `skippedSources` i usunięcia (`unlinkedSources`), więc etykieta je zawiera.
  - **Skutek.** Każda zmiana, uzupełnienie albo konflikt powiązań zdarzenia podpiętego do takiego źródła przerywa import komunikatem „Osoba … zmieniła się po planowaniu”. Dzieje się tak w każdym przebiegu, dopóki autor nie poprawi zepsutego pliku.
  - **Gdyby strażnik przepuścił,** gałąź `exact` w `link()` odpięłaby to powiązanie.
  - **Przyczyna** leży częściowo w planie: lista niedostępnych ID (plan:132-136) nie wymienia źródeł wstrzymanych.
  - **Testy** nie pokrywają tego przypadku.
- **Fix A ⭐ Recommended**: Dodać wstrzymane źródła (`unassessable` rodzaju Source) do `SourcePlan::$unavailable` i przekazać ten zbiór w `ImportPlan`. `unlinkedSources()` i pętla `detach` w `link()` korzystają z niego zamiast z samego `skippedSources`. Do tego test w ImportApplyTest: wstrzymane źródło z powiązaniem plus zmiana innego powiązania tego zdarzenia.
  - Strength: Jedno źródło prawdy dla planera, strażnika i zapisu, zgodne z zasadą „R, S i A pomijają niedostępne ID tak samo”.
  - Tradeoff: Dotyka trzech plików i kształtu `ImportPlan`.
  - Confidence: HIGH — oba subagenty niezależnie znalazły tę samą rozbieżność, potwierdzone w kodzie.
  - Blind spot: Nie sprawdzono, czy `skippedSources` używa jeszcze ktoś poza tymi dwoma miejscami.
- **Fix B**: Dopisać klucze `unassessable` rodzaju Source tylko do `$skip` w `unlinkedSources()` i do wykluczeń w pętli `detach`.
  - Strength: Mniejsza, punktowa zmiana.
  - Tradeoff: Trzy miejsca liczą „niedostępne” każde po swojemu, a to właśnie wywołało błąd.
  - Confidence: MED — naprawia objaw, nie wspólną definicję.
  - Blind spot: Przyszłe miejsca filtrujące powiązania mogą znów pominąć ten zbiór.
- **Decision**: FIXED (wariant Fix A, uproszczony). `PlanResearchSources::handle` dopisuje klucze wstrzymanych źródeł (`unassessable` rodzaju Source) do zbioru `skipped`, a przez niego do `unavailable` i `ImportPlan::$skippedSources`. Planer zamraża więc zdarzenie, które je wskazuje, tak jak przy źródle pominiętym, a strażnik i pętla `detach` korzystają z tego samego zbioru. Test: ImportApplyTest „keeps the links of a fact to a source held back by an unreadable file of zrodla/…”, czerwony przed poprawką.

### F2 — Zatwierdzona zmiana pliku w repo kasuje ręczny skan połączonego źródła

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/PlanResearchSources.php:241-244 (`memoryOf`), :341 (porównanie `plik_hash`), app/Actions/Import/WriteResearchSources.php:519-523
- **Detail**: Połączenie z ręcznym źródłem zostawia plik z aplikacji, ale pamięć zapisuje `plik` i `plik_hash` z repo, a porównanie pliku przyjmuje A = S. Gdy w repo pojawi się nowy skan, kolejny import pokazuje zwykłą „zmianę” z etykietą starej ścieżki w repo, a nie pliku z aplikacji.
  - Zmiany domyślnie idą przez „Zatwierdź wszystkie” (`ChangeApprovals`: przyjęte, jeśli nie odrzucone).
  - `update()` woła wtedy `ReplaceSourceFile` i `deleteAfterCommit($old)`, więc skasowany zostaje ręcznie wgrany skan.
  - Tego pliku nie ma w repo ani w kopii bazy (`backup:run --only-db`), więc nie da się go odzyskać.
  - Plan świadomie zaprojektował to jako „zmianę do zatwierdzenia, autor może odrzucić” (plan:125), ale nie uwzględnił, że stary plik nie pochodzi z repo i że zmiany są zatwierdzane zbiorczo. Ścieżka „Usuń z aplikacji” dla połączonego źródła też kasuje ręczny skan, ale jest jawna („skan do usunięcia”, domyślnie odłączenie), więc jest akceptowalna.
- **Fix A ⭐ Recommended**: Połączone źródło zapamiętuje, że skan pochodzi z aplikacji (np. `plik_hash` = hash pliku z aplikacji albo znacznik `skan: aplikacja`). Wtedy inny hash w repo to `ValueConflict` („skan w aplikacji: <nazwa> / skan w repo: <ścieżka>”), a nie cicha zmiana w zbiorczym zatwierdzeniu.
  - Strength: Autor wybiera świadomie, z nazwą własnego pliku w pytaniu; pasuje do istniejącej reguły trzech wartości.
  - Tradeoff: Zmiana pamięci połączonych źródeł i testów planu/komendy; łamie zdanie z planu:125, więc wymaga dopisku w planie.
  - Confidence: MED — wymaga przemyślenia, co po wyborze „repo” (podmiana i kasowanie po commicie są wtedy świadome).
  - Blind spot: Czy po rozstrzygnięciu konfliktu na korzyść aplikacji pytanie nie wraca w każdym przebiegu.
- **Fix B**: Zostawić przepływ, ale przy podmianie pliku, który nie pochodzi z repo, nie kasować starego pliku (zostaje na dysku albo trafia do `sources/{tree}/replaced/`).
  - Strength: Mała zmiana w `WriteResearchSources::update`; zgodna z planem; nic nie ginie.
  - Tradeoff: Autor nadal może nieświadomie podmienić skan w widoku; zostają pliki-sieroty do ręcznego sprzątania.
  - Confidence: HIGH — prosta zmiana w jednym miejscu.
  - Blind spot: Potrzebny znacznik „plik nie z repo” — dziś nie ma go w rekordzie ani w pamięci.
- **Decision**: FIXED (wariant Fix A bez znacznika w pamięci, bo nowe pole psułoby `differsFromMemory`). Gdy repo zmienia skan (R ≠ S), `PlanResearchSources` liczy A z prawdziwego pliku aplikacji (`storedHash`).
  - Kopia z importu daje A = S, czyli zwykłą zmianę jak dotąd.
  - Ręczny skan połączonego źródła daje A ≠ S, czyli konflikt z nazwą pliku z aplikacji, domyślnie rozstrzygany na korzyść aplikacji.
  - Gdy repo się nie zmienia, nic nie jest czytane.
  - Test: ImportApplyTest „asks before a new scan of the repo replaces the manual scan of a joined source…” (oba warianty odpowiedzi), czerwony przed poprawką. Test planu „plans a new scan as a change by its hash…” dostał kopię skanu w magazynie, bo jego przygotowanie zakładało plik, którego nie tworzyło.

### F3 — Pola opisu źródła nie są sprawdzane pod blokadą przed zapisem

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/WriteResearchSources.php:119-139 (`verify`), :476-526 (`merge`, `update`)
- **Detail**: Dla osób i ślubów `ApplyImport::verify` porównuje bieżącą wartość każdego planowanego pola z `appLabel` planu (`guardPerson`, `guardWedding*`). Dla źródeł `verify` sprawdza tylko `repo_id` i hash pliku, a `applyValues` nadpisuje opis wartością z repo.
  - Między planem a zapisem komenda czeka na odpowiedzi w terminalu, więc okno jest realne.
  - Gdy autor w tym czasie zmieni w przeglądarce nazwę, archiwum, sygnaturę, notatkę albo parafię, „zmiana” zaplanowana przy A = S albo „uzupełnienie” połączenia nadpisze to po cichu.
  - Dataset strażników w ImportApplyTest.php:2306-2311 nie ma przypadku edycji opisu. Plan (Faza 5 §2) też nie wymagał tego sprawdzenia, więc to luka planu.
- **Fix**: Dodać strażnika źródła na wzór `guardPerson`. Pod blokadą porównuje bieżącą wartość każdego przyjętego pola z `appLabel` z `PlannedChange` / `PlannedFill` / `ValueConflict` i rzuca „Źródło … zmieniło się po planowaniu”. Do tego wiersz w datasecie strażników.
  - Strength: Ta sama gwarancja, jaką mają osoby i śluby; wzór już istnieje w `ApplyImport`.
  - Tradeoff: Etykiety parafii (`tokenLabel`) i pliku trzeba porównywać tak samo jak w planie.
  - Confidence: HIGH — wzór strażnika jest w tym samym pliku.
  - Blind spot: None significant.
- **Decision**: FIXED. `WriteResearchSources::verify` blokuje wiersze źródeł i dla każdej przyjętej pozycji (`takenItems`, wspólne z `valuesOf`) porównuje bieżącą etykietę pola (`fieldLabel`, parafia przez `Parish::label()`) z `appLabel` planu. Uzupełnienie wymaga pustego pola. Komunikat: „Źródło …: pole <Nazwa> zmieniło się po planowaniu”. Test: dwa nowe wiersze datasetu strażników (nazwa znanego źródła, parafia łączonego), czerwone przed poprawką.

### F4 — Liczniki w podsumowaniu i wyniku są niedokładne

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Console/Commands/ImportResearch.php:585-602 (`linkCount`), app/Actions/Import/ApplyImport.php:371,378
- **Detail**: Dwa błędy liczników:
  - **`linkCount` zaniża liczbę powiązań.** Pomija `mergedSources[*]->entries` i powiązania dopinane albo odpinane przez zatwierdzone zmiany `*_sources` przy istniejących zdarzeniach. Przebieg, który tylko dopina źródła do istniejących faktów, pokazuje „powiązania z faktami: 0”.
  - **Przywrócone źródło liczy się dwa razy.** `WriteResearchSources::handle` dolicza je do `created`, który trafia do `newSources`. Jednocześnie `restored` liczy `count($plan->restorations)`, więc to samo źródło pojawia się jako „nowe” i jako „przywrócone”.
- **Fix**: Doliczyć `entries` połączeń i różnice powiązań z zatwierdzonych zmian do `linkCount`, a w `WriteResearchSources` liczyć przywrócone osobno od nowych.
- **Decision**: FIXED.
  - **`linkCount`** dolicza `entries` połączeń oraz powiązania z uzupełnień i zmian `*_sources` istniejących faktów (jako liczbę powiązań, z którymi fakt zostaje); konflikty pomija.
  - **`WriteResearchSources`** nie dolicza przywróconych do `created`.
  - **Testy.** Zaktualizowano oczekiwania w trzech testach, które utrwalały stare liczenie: ImportResearchCommandTest (pierwszy import ma 2 powiązania, zmiana numeru wpisu 1) i ImportApplyTest (przywrócenie: `newSources` 0).

### F5 — `rodzaj: inne` ze złym `typ` albo niepełną `parafia` pomija wpis zamiast ostrzec

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/ParseSourceBlock.php:230-236
- **Detail**: Kontrakt (research-block-contract.md:102,104) mówi, że przy `inne` pola `typ` i `parafia` są „pomijane z ostrzeżeniem”. Reguły `typ: in:…` i `parafia.miejscowosc: required_with:parafia` działają jednak przed sprawdzeniem rodzaju. Wpis `inne` z niedozwolonym `typ` albo z `parafia` bez `miejscowosc` jest więc pominięty w całości. Poprawne wartości przy `inne` dają ostrzeżenie zgodnie z kontraktem.
- **Fix**: Przy `rodzaj: inne` usunąć `typ` i `parafia` z danych (z ostrzeżeniem) przed `Validator::make` albo użyć `exclude_if:rodzaj,inne` dla tych reguł.
- **Decision**: FIXED. Reguły `typ`, `parafia` i `parafia.*` mają `exclude_if:rodzaj,inne`, a ostrzeżenie powstaje jak dotąd. Test: ResearchRepoReadTest „reads a source of another kind with a wrong record type or an incomplete parish…”, czerwony przed poprawką.

### F6 — Hash każdego skanu przy każdym przebiegu, kopia nie powiązana ze sprawdzonym hashem

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ParseSourceBlock.php:404, app/Actions/Import/WriteResearchSources.php:141-147,202-212,612-631
- **Detail**: Trzy drobne koszty, akceptowalne przy obecnej skali:
  - **Hash przy każdym przebiegu.** Każdy skan w blokach (do 20 MB) jest czytany i hashowany przy każdym `import:research`, także gdy nie ma nic do zapisania. Plan to przewidział („kilka sekund”).
  - **Kopia bez ponownego hasha.** `verify` hashuje ponownie skany do skopiowania, ale sama kopia (`putFileAs`) idzie później i nie jest sprawdzana. Plik podmieniony między `verify` a kopią trafi więc do aplikacji pod starym hashem. Ryzyko jest niskie, bo repo to lokalny katalog autora.
  - **Zapytania przy powiązaniach.** Zapis powiązań robi kilka zapytań na każde powiązanie (`currentEntries` w pętli, `LinkSourceToEvent`).
- **Fix**: Na razie nic. Przy wzroście repo: cache hashy po rozmiarze i mtime, porównanie hasha skopiowanego pliku ze `scanHash`, jeden odczyt `event_source` dla wszystkich zdarzeń.
- **Decision**: SKIPPED — zgodnie z rekomendacją („na razie nic”).

## Triage

| Decyzja | Ustalenia |
|---------|-----------|
| Fixed | F1, F2, F3, F4, F5 |
| Skipped | F6 |

Po poprawkach: pełny zestaw PHP przechodzi (1693 passed), `composer types:check` daje 0 błędów, a `pint --dirty` przechodzi. Frontendu nie zmieniano.
