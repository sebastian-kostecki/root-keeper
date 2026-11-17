<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Źródła ze skanami podpięte do faktów (runda 2)

- **Plan**: context/changes/import-sources-with-scans/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-11-16
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 5 observations

Druga runda pełnego przeglądu, po poprawkach z pierwszej. Przejrzane commity: e4f0633, 25aef2b, 70fbc4d, d14b38b, 90b8665, 7d4c936 i 134d250. Raport pierwszej rundy (F1–F6, z decyzjami) jest w historii gita: `git show 134d250:context/changes/import-sources-with-scans/reviews/impl-review.md`.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Kryteria automatyczne, uruchomione 2026-11-16:
- **Testy PHP.** `artisan test --compact`: 1693 passed (9443 asercje).
- **Analiza statyczna.** `composer types:check`: 0 błędów.
- **Formatowanie.** `pint --dirty`: passed.
- **Frontend.** `npm run check` (266 plików, 0 ostrzeżeń), `types:check` i `test:js` (443/443): passed.
- **Migracja.** `migrate:rollback --step=1 && migrate`: OK. Baza dev miała 0 źródeł z `repo_id` albo `signature`, więc cofnięcie niczego nie usunęło.

Ręczne punkty 1.5, 2.3 i 6.5–6.8 są `[x]` („zaakceptowane przez autora”). Baza dev nie ma dziś żadnego źródła z `repo_id`, więc ręczny przebieg szedł na innej bazie albo została ona potem wyczyszczona. Nie traktuję tego jako ustalenia.

Potwierdzone w tej rundzie:
- **Poprawki z rundy 1.** F1–F5 są poprawne i kompletne. Wstrzymane źródło używa tego samego zbioru w planerze, strażniku i pętli `detach`. Przy F2 pytanie nie wraca po rozstrzygnięciu. F3 obejmuje każde pole, które planer tworzy.
- **Zakres.** Nic z „What We're NOT Doing” nie zostało zbudowane.
- **Zgodność z planem.** Każda zaplanowana zmiana istnieje. `PlanResearchSources::handle` przyjmuje dodatkowo `ImportDecisions` (decyzje o przywróceniu), co jest zgodne z intencją planu.
- **Bezpieczeństwo.** Zakres drzewa przy każdym odczycie, ścieżka skanu w korzeniu repo, repo tylko do odczytu, blokada wierszy źródeł w `verify` i kopia bazy przed zapisem.

## Findings

### F1 — Brak katalogu `zrodla/` wygląda jak „wszystkie źródła usunięte w repo”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ReadResearchRepo.php:197 (`sourceFiles`: brak katalogu → `[]`), app/Actions/Import/ClassifyRepoRecords.php:162-171, app/Console/Commands/ImportResearch.php:106 (strażnik tylko dla osób), :281-282 (domyślnie „odłącz”)
- **Detail**: Plan mówi: „Brak katalogu to zero źródeł, nie błąd” (Faza 2 §4). Dla drzewa, które ma już źródła z importu, oznacza to, że zmieniona nazwa, przeniesienie albo niezsynchronizowane `zrodla/` (przy poprawnym `drzewo/`) zamienia każde źródło w „usunięte w repo”.
  - **Pierwszy przebieg.** Raport nie mówi nic o źródłach, a komenda pyta o każde osobno z domyślnym „Zostaw — odłącz od repo”. Enter na wszystkich czyści każde `repo_id` i pamięć.
  - **Kolejny przebieg** z powrotem `zrodla/` traktuje każde źródło jako nowe. Pytanie „ten sam skan?” ma domyślnie „Dodaj jako nowe”, więc Enter tworzy drugą kopię każdego skanu.
  - **Osoby** mają odpowiednik: „Repo nie zawiera żadnej poprawnej osoby…” (`ImportResearch.php:106`).
- **Fix A ⭐ Recommended**: Gdy katalog `zrodla/` nie istnieje, a drzewo ma źródła z `repo_id`, wstrzymaj usunięcia źródeł (`holdBackSources`, jak przy nieczytelnym pliku). Raport pokazuje wtedy ostrzeżenie „brak katalogu zrodla/ — usunięcia źródeł wstrzymane”. Do tego test komendy.
  - Strength: Korzysta z istniejącego mechanizmu wstrzymania (F1 z rundy 1 zamraża też powiązania), a import osób idzie dalej.
  - Tradeoff: Usunięcie wszystkich źródeł wymaga pustego katalogu `zrodla/`, a nie jego braku; trzeba to dopisać do kontraktu.
  - Confidence: HIGH — wstrzymanie źródeł już działa i ma testy.
  - Blind spot: Nie sprawdzono, czy pusty, ale istniejący `zrodla/` (np. częściowa synchronizacja) też powinien wstrzymywać.
- **Fix B**: Przerwij cały import błędem, jak przy osobach („Repo nie zawiera katalogu zrodla/, a drzewo ma źródła z importu…”).
  - Strength: Najprostsze, dokładnie wzór strażnika osób w tym samym pliku.
  - Tradeoff: Autor, który świadomie usuwa wszystkie źródła, nie zaimportuje niczego, dopóki nie utworzy pustego katalogu.
  - Confidence: HIGH — kilka linii obok istniejącego strażnika.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A). `ResearchRepoReading::hasSourcesDirectory` i `holdsBackSourceRemovals()`; `ClassifyRepoRecords` wstrzymuje usunięcia źródeł także przy braku `zrodla/`, komenda ostrzega („Brak katalogu zrodla/, a drzewo ma źródła z importu: usunięcia źródeł wstrzymane…”), a tabela „Nie do oceny” podaje powód „brak katalogu zrodla/”. Kontrakt i plan dostały dopisek (pusty katalog = świadome usunięcie wszystkich). Test: ImportResearchCommandTest „holds back the removal of every source when the repo has no zrodla directory”, czerwony przed poprawką.

### F2 — Rozdzielone odpowiedzi o rodzaju, typie i parafii po cichu gubią pola aktu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/WriteResearchSources.php:626-656 (`applyValues`), app/Actions/Sources/UpdateSource.php:44-47, app/Actions/Import/PlanResearchSources.php:319-358
- **Detail**: Zmiany pól znanego źródła są niezależnymi pozycjami, ale `UpdateSource` zeruje `record_type` i `parish_id`, gdy rodzaj nie jest aktem.
  - **Przypadek a.** Repo zmienia `inne` → `akt` z `typ` i `parafia`. W „Przejrzyj pojedynczo” autor odrzuca zmianę rodzaju, a przyjmuje typ i parafię. Źródło zostaje „inne” bez typu i parafii. Wynik liczy obie zmiany jako przyjęte, a pamięć przechodzi na R. Kolejny import widzi więc „ręczna zmiana zostaje” i nigdy ich nie zaproponuje ponownie.
  - **Przypadek b.** Autor przyjmuje `akt` → `inne`, a przy zmianie parafii wybiera „Zostaw”. Parafia i tak znika, bez ostrzeżenia. UI w tym miejscu pyta (`discard_record_fields`).
  - Plan (Faza 5 §1) opisuje to zachowanie `UpdateSource`, ale nie wiąże go z odpowiedziami w komendzie.
- **Fix**: Powiąż pozycje `typ` i `parafia` z wynikowym rodzajem. Gdy po odpowiedziach rodzaj nie jest aktem, te pozycje nie są zapisywane ani liczone, a ich pola pamięci zostają przy S, więc pytanie wróci. W opisie zmiany rodzaju `akt` → `inne` dopisz „typ i parafia zostaną wyczyszczone”. Do tego test zapisu dla obu przypadków.
  - Strength: Odpowiedzi autora i zapis przestają się rozjeżdżać; pamięć nie utrwala stanu, którego nie zapisano.
  - Tradeoff: Zależność między pozycjami w miejscu, gdzie dziś każda pozycja jest niezależna (`ChangeApprovals`).
  - Confidence: MED — wymaga wyboru miejsca (planer czy zapis) dla filtra.
  - Blind spot: Nie sprawdzono, czy tryb „Zatwierdź wszystkie” może dać takie rozbieżne odpowiedzi (prawdopodobnie nie).
- **Decision**: FIXED (wariant uproszczony). Zamiast utrzymywać pamięć przy S, `WriteResearchSources::update` pomija typ i parafię, gdy wynikowy rodzaj nie jest aktem, i nie liczy ich (działa jak „Zostaw”; pytanie i tak nie miałoby sensu przy źródle „inne”). Zmiana rodzaju z aktu na inny ma etykietę „Rodzaj (typ aktu i parafia zostaną wyczyszczone)”. Dopisek w planie. Testy w ImportApplyTest: „writes neither the record type nor the parish… keeps a source of another kind” (czerwony przed poprawką) i „clears the record type and the parish… says so in the change” (dwa warianty).

### F3 — ID z jednym poprawnym i jednym pominiętym wpisem nie jest pomijany w całości

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/ReadResearchRepo.php:153
- **Detail**: Plan (Faza 2 §4): „ID w dwóch wpisach … pomija wszystkie te wpisy”. `$countById` liczy tylko wpisy poprawne. Gdy `ZR-0001` ma jeden wpis poprawny i drugi pominięty (np. bez `plik`), poprawny zostaje w `$reading->sources`, a ID trafia też do `skippedSourceIds()`.
  - `ClassifyRepoRecords` usuwa ten klucz, więc źródło nie wchodzi.
  - `MatchResearchPlaces` i `MatchResearchSources` dalej o nie pytają (parafia, „ten sam skan?”), a odpowiedź jest ignorowana.
  - Raport liczy je jako przeczytane.
  - Test „skips every entry of an id that is written twice…” pokrywa tylko dwa poprawne wpisy.
- **Fix**: W `readSources` licz duplikaty łącznie z pominiętymi wpisami, które mają ID, i pomijaj wtedy także wpis poprawny; dopisz przypadek do testu duplikatów.
- **Decision**: FIXED. `readSources` liczy duplikaty łącznie z pominiętymi wpisami, które mają ID, i pomija wtedy także wpis poprawny; pliki w komunikacie są posortowane. Test: ResearchRepoReadTest „skips the valid entry of an id whose other entry is skipped”, czerwony przed poprawką. Kontrakt uzupełniony.

### F4 — Trzy testy planu bez drugiego użytkownika (reguła CLAUDE.md)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/ImportPlanTest.php:2043, :2074, :2086
- **Detail**: CLAUDE.md: „Every import feature test has a second user with overlapping data”. Te trzy testy tworzą tylko jedno drzewo:
  - „plans new sources with a sure, a new and no parish…”. Taka sama parafia w Lipnicy na innym koncie to dokładnie wyciek, który ten test powinien wykluczać.
  - „leaves out a new parish the author answered with no parish”.
  - „rejects decisions about parishes and sources that import did not ask about”. Jego `$other` to źródło w tym samym drzewie.

  Pozostałe nowe testy importu mają drugie konto.
- **Fix**: Dodaj w tych testach drugie konto z tą samą siedzibą, parafią i ID `ZR-`; asercja, że planowana parafia i źródła nie wskazują na jego rekordy.
- **Decision**: FIXED. Pomocnik `srcOtherLipnica()` (drugie konto z tą samą Lipnicą, parafią i urzędem) w trzech testach; asercje, że pewna i nowa parafia wskazują rekordy autora, a decyzja „połącz” ze źródłem innego konta jest odrzucana.

### F5 — `linkCount` nadal liczy niedokładnie

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Console/Commands/ImportResearch.php:586-617
- **Detail**: Po poprawce F4 z rundy 1 licznik wciąż się rozjeżdża:
  - **Za dużo.** Liczy też odrzucone zmiany powiązań (`$plan->changes` bez filtra zatwierdzeń). Powiązanie do łączonego albo przywróconego źródła z nowego zdarzenia liczy dwa razy: w `birthSources` / `restoration->sources` i w `merge->entries`.
  - **Za mało.** Nie liczy konfliktów rozstrzygniętych na korzyść repo.
  - **Komentarz** mówi, że konflikty są „rozstrzygane później”, choć `resolveConflicts` już zadziałał.
- **Fix**: Przekaż `$approvals` i `$resolutions` do `linkCount` i licz unikalne pary (zdarzenie, źródło), jak `WriteResearchSources::linkPlan`.
- **Decision**: FIXED. Nowa `WriteResearchSources::plannedLinkCount()` liczy unikalne pary (zdarzenie, źródło) z tej samej `linkPlan`, którą wykonuje zapis, z odpowiedziami autora; komenda używa jej w podsumowaniu (odpowiedzi domyślne) i w pytaniu o zapis. Stara `linkCount` usunięta. Test: ImportResearchCommandTest „does not count the links of a change the author rejects one by one”, czerwony przed poprawką; liczniki istniejących testów bez zmian.

### F6 — Strażnik pól źródła (F3 z rundy 1) bez testu ścieżki pozytywnej

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/ImportApplyTest.php:2315-2342, app/Actions/Import/WriteResearchSources.php:589
- **Detail**: Żaden test zapisu ani komendy nie zapisuje przyjętej zmiany rodzaju, typu, roku, parafii czy archiwum znanego źródła. Dataset strażników sprawdza tylko odrzucenie (nazwa, parafia łączonego), więc nic nie dowodzi, że `guardFields` przepuszcza niepuste etykiety `kind` / `record_type` / `year` / parafii. Dziś etykiety się zgadzają (sprawdzone w kodzie). Do tego `SourceFileTest::diskScan()` zostawia katalogi `source-file-test-*` w katalogu tymczasowym.
- **Fix**: Dodaj test zapisu przyjmujący zmiany rodzaju, typu, roku i parafii znanego źródła; sprzątaj katalog tymczasowy w `SourceFileTest`.
- **Decision**: FIXED. Testy zapisu przyjmujące zmiany rodzaju, typu, roku, archiwum i parafii znanego źródła w obu kierunkach (inne → akt, akt → inne), więc strażnik przepuszcza niepuste etykiety. `SourceFileTest` sprząta swoje katalogi tymczasowe w `afterEach` (wzór z ImportApplyTest). W `/tmp` kontenera zostało 149 katalogów z wcześniejszych przebiegów, nieusuniętych.

### F7 — Pytanie „ten sam skan?” nie pokazuje faktu źródła z repo

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Console/Commands/ImportResearch.php:537 (`decideSourceMatches`)
- **Detail**: Plan (Faza 6 §1): pytanie pokazuje „ID, nazwa, rok, typ i fakt” źródła z repo wobec kandydata. Wiersz źródła z repo ma w kolumnie „Fakt” „—”; fakty pokazują tylko kandydaci.
- **Fix**: Wypełnij kolumnę „Fakt” zdarzeniami, które wskazują to ID przez `zrodla`, tymi samymi, z których `MatchResearchSources` zbiera kandydatów.
- **Decision**: FIXED. `SourceMatch::$events` niesie zdarzenia drzewa cytujące źródło z repo (jedno zapytanie, tylko gdy są kandydaci); kolumna „Fakt” wiersza „Z repo” je pokazuje, wspólny `factsText` dla obu wierszy. Test: asercja w ResearchSourceMatchTest „a manual source on the matched birth is a candidate…”.

### F8 — Skopiowane skany kasowane przy każdym wyjątku, także po commicie

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyImport.php:95-105
- **Detail**: `catch (Throwable)` kasuje pliki z `CopiedFiles` niezależnie od tego, czy transakcja się wycofała. Wyjątek z callbacku `afterCommit` albo COMMIT o nieznanym wyniku (zerwane połączenie) skasowałby pliki, na które wskazują zapisane rekordy. Dziś to tylko ryzyko ukryte: jedyny `afterCommit` (`WriteResearchSources.php:705`) tylko raportuje.
- **Fix**: Kasuj kopie tylko wtedy, gdy wiadomo, że transakcja się wycofała (np. znacznik ustawiany na końcu ciała transakcji i sprawdzenie `DB::transactionLevel()`); w razie wątpliwości lepiej zostawić sieroty niż usunąć pliki.
- **Decision**: FIXED. `CopiedFiles::bodyFinished()` ustawiane na końcu ciała transakcji; `ApplyImport::handle` kasuje kopie tylko przy `rolledBack()`. Test: ImportApplyTest „keeps the copied scans when the import fails only after the commit” (callback `afterCommit` rzuca), czerwony przed poprawką; test wycofania dalej przechodzi.

### F9 — Plik `.md` w `zrodla/` może być dowiązaniem poza repo

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ReadResearchRepo.php:127-133, :210-215
- **Detail**: Skan przechodzi kontrolę `realpath` w korzeniu repo, ale plik bloku nie. `isFile()` idzie za dowiązaniem, a `File::get` czyta cel, więc plik spoza repo jest parsowany, a fragmenty mogą trafić do komunikatów błędu YAML w terminalu. Ryzyko jest niskie, bo repo jest lokalne i należy do autora.
- **Fix**: Ta sama kontrola co w `ParseSourceBlock::resolveScan`: `realpath` i `str_starts_with($real, $root.'/')`, a inaczej pominięcie „plik poza repo”.
- **Decision**: FIXED. `readSources` sprawdza `realpath` pliku bloku względem korzenia repo i pomija dowiązanie poza repo z powodem „Plik poza repo” (bez ID, więc wstrzymuje usunięcia źródeł). Test: ResearchRepoReadTest „skips a source file that is a symlink leaving the repo without reading it”, czerwony przed poprawką. Kontrakt uzupełniony.

## Triage

| Decyzja | Ustalenia |
|---------|-----------|
| Fixed | F1 (Fix A), F2 (uproszczony), F3, F4, F5, F6, F7, F8, F9 |

Po poprawkach: pełny zestaw PHP przechodzi (1702 passed, 9468 asercji), `composer types:check` daje 0 błędów, a `pint --dirty` przechodzi. Frontendu nie zmieniano. Każda poprawka zachowania ma test, który był czerwony przed poprawką (sprawdzone przez cofnięcie zmiany w kodzie aplikacji).
