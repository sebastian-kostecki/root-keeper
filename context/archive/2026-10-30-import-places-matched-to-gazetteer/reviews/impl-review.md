<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Miejsca z repo dopasowane do spisu

- **Plan**: context/changes/import-places-matched-to-gazetteer/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-11-16
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 5 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Kontrole automatyczne (2026-11-16):
- `migrate:rollback --step=1 && migrate`: OK.
- `artisan test --compact`: 1516 testów zielonych.
- `composer types:check`: 0 błędów.
- `pint --test`: OK.
- `npm run types:check`: OK.
- `npm run test:js`: 437 testów zielonych.
- `npm run check`: błąd formatowania w 3 plikach `.ai/rules/*.md`, które powstały przed tą zmianą (zob. F10).

Elementy ręczne 1.6 i 6.6–6.8 są odhaczone.

Po triage (2026-11-16):
- `artisan test --compact`: 1528 testów zielonych.
- `composer types:check`: 0 błędów.
- `pint`: OK.
- `npm run check`: OK.

Triage: wszystkie ustalenia naprawione według rekomendacji (F3 przez Fix A).

## Findings

### F1 — Notatka liczona przed decyzjami o zmianach i konfliktach

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/PlanImport.php:1157
- **Detail**: Planer ustala `placed`, kiedy wynikiem jest Same, Fill albo Changed, i na tej podstawie buduje notatkę. `ApplyImport` zapisuje tę notatkę bez zmian. Plan wskazał ten problem (Faza 4.4: „notatka zależna od konfliktu musi mieć dwa warianty albo być liczona w `ApplyImport`”), ale żadnego z tych rozwiązań nie zbudowano.
  - (a) Autor odrzuca zmianę miejsca w trybie „pojedynczo”. Wydarzenie zostaje przy starej miejscowości, a tekst repo znika z notatki. W kolejnym przebiegu notatka znowu się różni.
  - (b) Autor rozstrzyga konflikt „weź z repo”. Wydarzenie dostaje R, a tekst zostaje w notatce. W kolejnym przebiegu wynik to Same, notatka traci tekst i pojawia się zmiana notatki, więc przebieg nie kończy się „Nic do zapisania.”.
  - Żaden test nie sprawdza notatki po odrzuconej zmianie ani po rozstrzygnięciu FROM_REPO.
- **Fix**: Planer przekazuje dla każdej osoby zależność „klucz zdarzenia → klucz pytania o miejsce”, a `ApplyImport` po `ChangeApprovals` i `ConflictResolutions` przelicza `RepoValues::note` z faktycznie zapisanym zbiorem `placed`. Dodać testy obu scenariuszy (z drugim przebiegiem „Nic do zapisania.”).
  - Strength: Tę ścieżkę wskazał sam plan. Reguła z Critical Implementation Details („według faktycznego miejsca”) zaczyna działać naprawdę.
  - Tradeoff: `ApplyImport` musi mieć `ResearchPerson` i odczyt repo albo gotowy zestaw wejść notatki w planie. To nietrywialna zmiana w `ImportPlan`.
  - Confidence: MED — zależy, czy notatka osoby dopasowanej idzie jako `PlannedChange` (zatwierdzana osobno) i jak wtedy łączyć jej zatwierdzenie z przeliczeniem.
  - Blind spot: Nie sprawdzono, jak zachowa się notatka, gdy autor odrzuci samą zmianę notatki, a zatwierdzi miejsce.
- **Decision**: FIXED — nowa klasa `PlannedNote` (`ImportPlan::$notes`) niesie pytania o miejsce, od których zależy blok notatki. `ApplyImport::writeAnsweredNotes` przelicza blok po decyzjach autora i zapisuje go w osobie (gdy jej blok równa się planowanemu) oraz w pamięci. Test: `ImportApplyTest` „keeps the repo text in the note only when…” (odrzucona zmiana, przyjęta zmiana, konflikt „z repo”, drugi przebieg bez pozycji); na starym kodzie 2 z 3 wariantów padają.

### F2 — Dwie pisownie jednej miejscowości tworzą ten sam cmentarz dwa razy

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/Import/PlaceResolver.php:226
- **Detail**: Nowy cmentarz ma klucz `decisionKey()` z tekstu repo (`bukinia|`, `bokinia|`), a nie z rozpoznanej miejscowości.
  - Scenariusz: drzewo ma „Bukinia” (id 7) z wariantem „Bokinia” i bez cmentarzy. Jedna osoba ma `pochowek.miejsce: Bukinia`, druga `Bokinia`.
  - Wtedy `newCemeteries` dostaje dwa `PlannedCemetery(null, 7)`. Drugi `StoreCemetery` łamie unikalny indeks cmentarzy i cały import się cofa („W tej miejscowości jest już cmentarz o tej nazwie”).
  - To samo dzieje się przy niepewnej „Bokinia” wskazanej na 7 obok pewnej „Bukinia” i przy dwóch odpowiedziach „Nowy cmentarz” dla tej samej miejscowości.
  - Test „adds each new locality, cemetery and variant once” sprawdza warianty tylko przy urodzeniach.
- **Fix**: W `PlaceResolver::newCemetery` deduplikować po (id miejscowości albo klucz nowej miejscowości, klucz nazwy cmentarza), tak żeby oba klucze żądań wskazywały ten sam wpis `newCemeteries`. Dodać test w `ImportApplyTest` z dwiema pisowniami i dwoma pochowaniami.
- **Decision**: FIXED — `PlaceResolver::newCemeteryKey()` daje klucz (miejscowość, nazwa cmentarza), więc dwie pisownie jednej miejscowości dają jeden nowy cmentarz. Test: `ImportApplyTest` „adds one new cemetery for burials that name its locality in two spellings” (na starym kodzie pada).

### F3 — Niepewna nazwa przy dwóch miejscowościach o tym samym kluczu (Wola / Wóla) blokuje zapis albo nie zapamiętuje wariantu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyImport.php:1093 (guardPlaces), :1233 (writePlaces); app/Actions/Localities/MergeLocality.php:98
- **Detail**: Indeks miejscowości jest po `lower(name)`, więc „Kolemczyce” i „Kołemczyce” mogą istnieć obok siebie. Taki wariant jest w prawdziwym repo. Repo „Kolemczyce” daje wtedy werdykt niepewny z oboma kandydatami, a komenda zawsze ustawia flagę wariantu (`ImportResearch.php:381`).
  - `guardPlaces` buduje `$taken[NameKey] = id` z nieposortowanego `get()`, więc zostaje tylko jedno id. Wybór drugiego kandydata zawsze rzuca „Miejsce … zmieniło się po planowaniu” i takiego importu nie da się zapisać.
  - Przy wyborze pierwszego kandydata `writePlaces` pomija wariant, bo klucz nazwy miejscowości jest ten sam. Mechanizm „wariant wygrywa z nazwą drugiej” (plan, Faza 3.4) nigdy więc nie zadziała przez import, a nowe zdarzenia z tą nazwą pytają ponownie.
  - Odpowiedź „Dodaj jako nową” przy takim kluczu zawsze rzuca wyjątek.
  - `MergeLocality::nameIsTaken` porównuje przez `lower()`, a import przez `NameKey`, więc scalenie może dodać wariant różniący się od istniejącego tylko polskim znakiem. Daje to ten sam stan niepewny.
  - Nic nie psuje danych (transakcja się cofa), ale autor zostaje bez wyjścia.
  - `ResearchPlaceMatchTest:129` buduje wariant fabryką, więc ta ścieżka zapisu nie ma testu.
- **Fix A ⭐ Recommended**: Ujednolicić regułę na `NameKey` we wszystkich trzech miejscach:
  - `guardPlaces` trzyma `key → list<id>` i odrzuca wariant tylko wtedy, gdy wskazana miejscowość zniknęła albo klucz zajął wariant innej miejscowości,
  - `writePlaces` pomija wariant tylko wtedy, gdy `lower(name)` jest już zajęte (nazwa lub wariant),
  - `checkPlaceDecisions` odrzuca NEW, gdy nazwa koliduje kluczem z istniejącą miejscowością, i komenda nie oferuje wtedy „Dodaj jako nową”,
  - `MergeLocality::nameIsTaken` porównuje przez `NameKey` i czyta przez `$target->tree->localityAliases()`.

  Dodać testy zapisu dla wszystkich trzech odpowiedzi.
  - Strength: Usuwa całą klasę rozjazdu „lower() kontra NameKey”, którą plan opisał już przy nowych miejscowościach (Faza 5.2).
  - Tradeoff: Zmiana w czterech miejscach i 3–4 nowe testy.
  - Confidence: HIGH — błąd w `$taken` i pominięcie w `writePlaces` są widoczne bezpośrednio w kodzie.
  - Blind spot: Nie sprawdzono, czy dev albo produkcja ma dziś pary miejscowości o tym samym kluczu.
- **Fix B**: Nie dopuszczać do stanu wyjściowego: przy werdykcie „dwie miejscowości o tym samym kluczu” komenda prosi najpierw o scalenie w `/places` i przerywa import.
  - Strength: Mniej kodu, a spis zostaje bez duplikatów.
  - Tradeoff: Wymusza przerwę w imporcie. Test planu „wariant wygrywa” (Faza 3.4) traci sens.
  - Confidence: MED — zmienia zachowanie uzgodnione w planie.
  - Blind spot: Nie poprawia rozjazdu w `MergeLocality`.
- **Decision**: FIXED (Fix A) — werdykt i `PlannedAlias` niosą `holders` (miejscowości z danym kluczem w chwili planowania). `guardPlaces` odrzuca tylko nowego posiadacza klucza. `writePlaces` pomija wariant tylko przy dublecie `lower(name)`. `checkPlaceDecisions` odrzuca NEW przy zajętym kluczu, a komenda nie pokazuje wtedy „Dodaj jako nową”. `MergeLocality::nameIsTaken` porównuje przez `PlaceName::key` i czyta przez `$target->tree->localityAliases()`. Testy: `ImportApplyTest` (Wola / Wóla × 2, NEW odrzucone, wariant zajęty po planowaniu), `ImportResearchCommandTest` (brak „Dodaj jako nową”, drugi przebieg „Nic do zapisania.”), `LocalitiesTest` (scalanie przy różnicy tylko w polskich znakach).

### F4 — Brak drugiego użytkownika w większości testów `ResearchPlaceMatchTest`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/ResearchPlaceMatchTest.php
- **Detail**: Reguła z `CLAUDE.md` mówi, że każdy test importu ma drugiego użytkownika z pokrywającymi się danymi. Tutaj ma go tylko 1 z 15 testów („the places of another account never take part”), a strażnik w pomocniku `matchTemporaryPlaces` sprawdza tylko miejscowości, nie cmentarze. `ImportPlanTest`, `ImportApplyTest`, `ImportResearchCommandTest` i `ClassifyRepoRecordsTest` regułę spełniają.
- **Fix**: Dodać do pomocnika (albo `beforeEach`) drugie konto z tymi samymi miejscowościami, wariantami i cmentarzami, a w strażniku sprawdzać także kandydatów i opcje cmentarzy.
- **Decision**: FIXED — `placeTwinTree()` tworzy przed autorem drugie konto z tymi samymi nazwami, wariantem „Bokinia” i cmentarzami. `matchTemporaryCemeteries()` sprawdza też cmentarze i opcje. 14 testów używa nowego pomocnika.

### F5 — Pytanie bez odpowiedzi liczy się jako „Bez miejsca”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/PlanImport.php:605
- **Detail**: Plan (Faza 3.3) wymaga, żeby `PlaceDecisions` pokrywały dokładnie nazwy z werdyktów, jak w `checkDecisions`. Obecny kod odrzuca odpowiedź nadmiarową albo błędną, ale brakująca po cichu staje się NONE (`?? PlaceDecisions::NONE` w `PlaceResolver`). Błąd w komendzie, który zgubi pytanie, przejdzie więc niezauważony.
  - Drobne pokrewne luki:
    - `ApplyImport::guardFreeKeys` (:585) nie sprawdza klucza pochowania; kolizję łapie dopiero indeks, z surowym błędem SQL,
    - `ImportResearch::fillTable` nie pokazuje `placeFill` ślubu, choć `fillCount()` go liczy.
- **Fix**: `checkPlaceDecisions` i `checkCemeteryDecisions` rzucają wyjątek przy braku odpowiedzi na niepewną nazwę lub cmentarz „do wyboru”. Dopisać `burialRepoId` do `guardFreeKeys` i `placeFill` do `fillTable`.
- **Decision**: FIXED — brak odpowiedzi na niepewną miejscowość albo na cmentarz „do wyboru” rzuca `InvalidArgumentException` (nowa nazwa bez odpowiedzi nadal liczy się jako „Bez miejsca”). `guardFreeKeys` sprawdza klucz pochowania, a `fillTable` pokazuje miejsce ślubu. Testy: `ImportPlanTest` (brak decyzji o niepewnej miejscowości, brak wyboru cmentarza).

### F6 — Miejsca osoby przywróconej po usunięciu w aplikacji nie trafiają do pytań

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/MatchResearchPlaces.php:173
- **Detail**: `plannedEvents` bierze osoby z `knownPersons`, `identity->matched` i `identity->new`. Osoba przywrócona z „usuniętych w aplikacji” idzie przez `PlanImport::newPersonPlaces` (:1186) z `locality($text, null)`, czyli tylko przez pewne dopasowanie.
  - Nowa albo niepewna nazwa takiej osoby (np. zmieniona w repo) staje się po cichu „bez miejsca”. Tekst zostaje w notatce, więc nic nie ginie, ale autor nie dostaje pytania.
  - Tę lukę miał już kontrakt planu.
- **Fix**: Dodać przywrócone osoby (decyzje „przywróć” z `ImportDecisions`) do `plannedEvents`.
- **Decision**: FIXED — `MatchResearchPlaces::plannedEvents` obejmuje osoby przywracane z „usuniętych w aplikacji”, a `newPersonPlaces` korzysta z zapamiętanego rozpoznania (`rememberedToken`). Test: `ImportPlanTest` „asks about the places of a person brought back…” (na starym kodzie pada).

### F7 — Usunięcie `pochowek.miejsce` w repo nie czyści cmentarza (świadome odejście od planu)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/PlanImport.php:1137
- **Detail**: Plan (Faza 5.3) mówi „przy pochowaniu oba pola”. Kod wraca wcześniej, gdy pochowanie nie ma rozpoznanego cmentarza, więc niczego nie czyści. To zgodne z regułą „pochowanie tylko z cmentarzem”, bo `UpdatePerson` skasowałby pochowanie bez cmentarza, ale plan tego nie mówi. Gałąź czyszczenia w `placeAttributes` jest dla pochowania nieosiągalna.
  - Uwaga pokrewna: `RepoValues::event` ma teraz `miejsce`, więc każde zostawione jako usunięte urodzenie lub zgon z miejscem raz po wdrożeniu znowu dostanie pytanie „przywróć / zostaw” (`differsFromMemory`). To nie jest opisane w Migration Notes.
- **Fix**: Dopisać oba zachowania do planu (Faza 5.3 i Migration Notes).
- **Decision**: FIXED — plan: Faza 5.3 opisuje, że pochowania nie czyści się po usunięciu `pochowek.miejsce`, a Migration Notes jednorazowe ponowne pytanie o rekordy zostawione jako usunięte.

### F8 — Rozpoznawanie miejsc przegląda cały spis dla każdego zdarzenia

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/Import/PlaceResolver.php:106; app/Actions/Import/MatchResearchPlaces.php:234
- **Detail**: Przy ponownym imporcie większość zdarzeń jest zapamiętana. Każde idzie przez `known()` → `verdict()`, a każde takie wywołanie liczy na nowo `PlaceName::key` (`Str::ascii`) dla wszystkich miejscowości i wariantów, w razie potrzeby także `levenshtein`. Do tego `ApplyImport::localityName` / `cemeteryLabel` i odczyt nazwy w `writePlaces` (:1231) robią jedno zapytanie na pole albo wariant.
  - Przy dziesiątkach miejscowości z planu to pomijalne, ale spis po scaleniu z seedem może urosnąć do setek.
- **Fix**: W konstruktorze `PlaceResolver` i w `MatchResearchPlaces::handle` zbudować raz mapy `klucz → id` (nazwy i warianty) i zapamiętywać werdykt dla klucza; nazwy miejscowości wczytać jednym zapytaniem.
- **Decision**: FIXED (częściowo) — nowa klasa `PlaceMatcher` liczy klucze spisu raz i zapamiętuje werdykt dla klucza, `PlaceResolver` grupuje cmentarze po miejscowości, a `writePlaces` nie odpytuje bazy dla każdego wariantu. Bez zmian zostały `ApplyImport::localityName` / `cemeteryLabel`: pytają bazę tylko przy polach z pozycją planu.

### F9 — Drobne niezgodności wzorców

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Localities/MergeLocality.php:101; app/Actions/Import/MatchResearchPlaces.php:123, :234; app/Support/Import/PlaceName.php
- **Detail**:
  - `MergeLocality` czyta warianty przez `LocalityAlias::query()->where('tree_id', …)` zamiast przez relację drzewa.
  - `MatchResearchPlaces` wystawia publicznie `cemeteries()` i `verdict()`, a `App\Support\Import\PlaceResolver` zależy od tej Action. Pozostałe Actions importu mają tylko `handle()`.
  - `PlaceName::key` to inna nazwa `NameKey::normalize`: `ApplyImport` używa `NameKey`, a dopasowanie używa `PlaceName`.
- **Fix**: Czytać przez `$target->tree->localityAliases()`. Wydzielić `verdict()` / `cemeteries()` do klasy w `app/Support/Import/` (np. `PlaceMatcher`), której używają Action i `PlaceResolver`. Używać jednej funkcji klucza.
- **Decision**: FIXED (częściowo) — `MergeLocality` czyta warianty przez relację drzewa. `verdict()` przeszedł do `App\Support\Import\PlaceMatcher`, więc `PlaceResolver` nie zależy już od Action. `ApplyImport` używa `PlaceName::key`. `MatchResearchPlaces::cemeteries()` zostaje publiczne: to osobny krok, który komenda wywołuje po decyzjach o miejscowościach.

### F10 — `npm run check` nie przechodzi na develop

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: .ai/rules/index.md, .ai/rules/boost/http-js-routes.md, .ai/rules/boost/tests.md
- **Detail**: Oxfmt zgłasza formatowanie w 3 plikach `.ai/rules`. Te pliki zmienił ostatnio commit fc3b740, sprzed tej zmiany, więc to nie jest regresja tego planu. Kryterium 1.5 jest odhaczone, a polecenie z planu dziś nie przechodzi i CI je uruchamia.
- **Fix**: `vendor/bin/sail npm run check:fix` (albo wykluczyć `.ai/` z `fmt`, jeśli te pliki nie mają być formatowane) w osobnym commicie `chore`.
- **Decision**: FIXED — `vendor/bin/sail npm run check:fix` sformatował tabele w `.ai/rules/*.md`; `npm run check` przechodzi.
