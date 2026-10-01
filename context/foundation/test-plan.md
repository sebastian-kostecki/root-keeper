# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-09-30

Treść planu jest po polsku; wartości statusu w §3 zostają po angielsku, bo czyta je parser orkiestratora.

## 1. Strategy

Testy w tym projekcie stosują trzy nienegocjowalne zasady:

1. **Koszt × sygnał.** Wygrywa najtańszy test, który daje prawdziwy sygnał dla ryzyka. Nie awansuj do e2e, bo e2e „wydaje się bezpieczniejsze”. Nie kładź modelu wizji na deterministycznym porównaniu, które już łapie regresję.
2. **Obawy użytkownika są dowodem pierwszej klasy.** Ryzyka zakotwiczone w „autor obawia się X, a awaria ujawniłaby się gdzieś w <obszarze>” mają tę samą wagę co linie PRD czy dane o hot-spotach.
3. **Risks are scenarios, not code locations.** Ten plan opisuje, *co może zawieść* i *dlaczego uważamy to za prawdopodobne* — na podstawie dokumentów, wywiadu i *sygnału* z kodu (częstotliwość zmian, struktura, baza testów). NIE twierdzi, że wie, która linia odpowiada za awarię. Tę wiedzę wytwarza `/10x-research` w każdej fazie wdrożenia. Jeśli plan i badanie nie zgadzają się co do miejsca awarii, prawdą jest badanie.

Hot-spot scope used for likelihood weighting: `app/`, `resources/js/` (bez `resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`, `resources/js/components/ui`), `routes/`, `database/`, `scripts/` — 98 commitów w ostatnich 30 dniach (stan 2026-09-30).

## 2. Risk Map

Najważniejsze scenariusze awarii, uporządkowane według wpływ × prawdopodobieństwo. Kolumna Source podaje *dowód, który podniósł ryzyko* — nigdy plik jako „miejsce awarii” (patrz §1, zasada 3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | Fakt, akt lub relacja zapisuje się przy **niewłaściwej osobie** (dziecko przy złej parze, rodzic w złym slocie, źródło przy innej osobie) albo potwierdzenie faktu znika po cichu po edycji — drzewo przestaje odpowiadać prawdzie. | High | High | wywiad P1 („przypisane innej osobie”), P3 (modal osoby i drzewo przebudowywane najczęściej); prd v4 FR-004 – FR-006, prd-v3 FR-002; hot-spot dir `resources/js/components/persons` — 120 zmian/30d, `resources/js/components/tree` — 45, `app/Actions/Relations` — 16; 20 z 98 commitów w zakresie to poprawki |
| 2 | Kopia zapasowa nie obejmuje wszystkich danych (w tym plików źródeł po zmianie skany → źródła) albo **odtworzenie** na czystej instancji nie przywraca kompletnych danych z czytelnymi plikami. | High | Medium | wywiad P1 (utrata danych, skanów), P2 (utrata dostępu po wygaśnięciu licencji), P4 (odtworzenie przećwiczone raz ręcznie, przed zmianą na źródła); PRD v1 NFR (RPO 24 h) i Guardrails („dane i skany nie giną”); archive `2026-09-22-production-data-safety/plan.md`, `2026-09-29-scans-become-sources/plan.md` (zmiana nazw tabel i katalogu plików) |
| 3 | Usunięcie osoby, źródła, relacji lub małżeństwa **kasuje więcej**, niż pokazało potwierdzenie, a produkt nie ma kosza ani cofania. | High | Medium | wywiad P1 („przypadkowe utracenie danych”); PRD v1 FR-007, FR-015 i Non-Goals (brak kosza i cofania); prd-v3 FR-011; prd v4 FR-013; archive `2026-09-25-person-edit-and-delete/plan.md`, `2026-09-25-scan-delete-with-impact/plan.md` |
| 4 | *(nadużycie)* Inny zarejestrowany użytkownik odczytuje lub zmienia moje osoby, źródła albo pliki, podając id z mojego drzewa w adresie **lub w treści żądania** (np. akcje z drzewa przyjmujące wskazaną osobę). | High | Medium | PRD v1 Access Control (otwarta rejestracja) i Guardrails; prd v4 Access Control („cudza osoba to 404”); hot-spot dir `app/Http/Requests` — 30 zmian/30d, `app/Policies` — 10 |
| 5 | Reguły relacji da się obejść poza filtrem wyboru — trzeci rodzic, cykl przodków, kobieta w slocie ojca — i drzewo „kłamie”. | Medium | Medium | wywiad P1 (dane nieprawdziwe w odniesieniu do osoby); prd v4 FR-011 i Guardrails; prd-v3 FR-012; hot-spot dir `app/Actions/Relations` — 16 zmian/30d |

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | Po akcji z drzewa lub modala w bazie zmieniła się dokładnie wskazana osoba / para, inne osoby są nietknięte, a stan „potwierdzone” pozostałych faktów się nie zmienił. | „Backend ma testy, więc przebudowa UI nie pomyli osób” — UI może wysłać złe id osoby lub pary. | Skąd UI bierze id osoby i pary (przełącznik związku, historia „Wstecz” w modalu); kształt żądań z drzewa i modala; które reguły wyboru pary są czystą logiką po stronie klienta. | integracja (Pest feature) na kontrakcie żądania; unit (Vitest) dla czystej logiki wyboru pary / historii modala | asercja tylko na kodzie odpowiedzi zamiast na stanie danych; oczekiwana wartość przepisana z implementacji (problem wyroczni) |
| #2 | Archiwum kopii zawiera bazę i każdy plik źródła (oryginał i miniaturę); odtworzenie na czystej instancji daje tę samą liczbę osób, faktów i powiązań, a pliki się otwierają. | „Komenda backupu zakończyła się bez błędu, więc kopia jest kompletna.” | Konfiguracja kopii i ścieżki plików po zmianie nazw; procedura odtworzenia z F-01; istniejące testy harmonogramu kopii; co z tego da się sprawdzić automatycznie, a co wymaga drillu na serwerze. | integracja (kopia na dysk tymczasowy + sprawdzenie zawartości) oraz spisany, powtarzalny drill odtworzenia (manual smoke) | test sprawdzający tylko, że komenda jest zaplanowana; drill bez porównania liczności danych przed i po |
| #3 | Zbiór faktycznie usuniętych lub odłączonych rekordów i plików jest równy liście skutków pokazanej w potwierdzeniu; dane współmałżonka i innych osób zostają. | „Kaskada w bazie usuwa tylko to, co trzeba.” | Kaskady i klucze obce; skąd pochodzi lista skutków; usuwanie plików z dysku. | integracja (Pest feature) | porównanie listy skutków z tym samym zapytaniem, które ją liczy (tautologia) |
| #4 | Każdy endpoint odczytu i zapisu z cudzym id — w adresie i w treści — kończy się 404 i niczego nie zapisuje; plik cudzego źródła nie jest serwowany. | „Wiązanie modelu z trasy i polityka wystarczą” — id w treści żądania ich omija. | Pełna lista tras; które przyjmują id osób, par lub źródeł w treści; jak serwowane są pliki. | integracja (Pest feature, dataset po trasach) | sprawdzenie jednej trasy „na próbę”; oczekiwanie 403 zamiast 404 |
| #5 | Serwer odrzuca trzeciego rodzica, cykl i niezgodną płeć na każdej ścieżce zapisu (nowa i istniejąca osoba, z drzewa i z podglądu), niezależnie od filtra w UI. | „Wybór ukrywa zakazane osoby, więc serwer nie musi sprawdzać.” | Gdzie reguły są egzekwowane; czy wszystkie ścieżki tworzenia relacji przechodzą tę samą walidację. | integracja (Pest feature) | testowanie tylko filtra wyboru; tylko ścieżka szczęśliwa |

## 3. Phased Rollout

Każdy wiersz to osobna faza wdrożenia, która otwiera własny folder zmiany przez `/10x-new`. Status przesuwa się od lewej do prawej; orkiestrator aktualizuje go, gdy na dysku pojawiają się artefakty.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Kopia i odtworzenie | Udowodnić, że kopia obejmuje bazę i wszystkie pliki źródeł, a odtworzenie na czystej instancji przywraca kompletne dane (kolejność: decyzja autora, wywiad P1/P4) | #2 | integration + manual smoke (drill odtworzenia) | change opened | `context/changes/testing-backup-restore/` |
| 2 | Zapis i usuwanie trafiają we właściwe dane | Udowodnić, że akcje z drzewa i modala zmieniają dokładnie wskazaną osobę lub parę, a usunięcia kasują dokładnie to, co pokazało potwierdzenie | #1, #3 | integration + unit | not started | — |
| 3 | Granice własności i reguły relacji | Udowodnić, że cudze id (w adresie i w treści) daje 404 bez zapisu, a reguły relacji egzekwuje serwer na każdej ścieżce | #4, #5 | integration | not started | — |
| 4 | Przepływy drzewa i modala w przeglądarce | 1–2 krytyczne przepływy (np. v4 US-01: istniejący ojciec z pustego slotu) z asercją na danych, nie na wyglądzie — tylko jeśli badanie fazy 2 pokaże, że tańsze warstwy nie łapią pomyłki osoby w UI | #1 (połowa UI) | e2e | not started | — |

## 4. Stack

| Layer | Tool | Version | Notes |
|---|---|---|---|
| backend integration + unit | Pest (PHPUnit) + pest-plugin-laravel | 5.2 / 13.3 | ~23 pliki, ~330 przypadków, głównie `tests/Feature`; w CI na PostgreSQL 18 |
| frontend unit | Vitest przez `vp test` (vite-plus) | 0.3 | 7 plików, ~95 przypadków, wyłącznie czysta logika w `resources/js/lib` i `resources/js/hooks` |
| e2e | none yet — see Phase 4 | — | kandydat: `pestphp/pest-plugin-browser` + Playwright (checked: 2026-09-30); nie jest zależnością projektu |
| backup / restore | spatie backup + drill odtworzenia z F-01 | — | dziś bez automatycznej weryfikacji zawartości — see Phase 1 |
| (optional) AI-native | eksploracyjny przebieg agenta po drzewie w przeglądarce (checked: 2026-09-30) | n/a | When NOT to use: jako bramka regresji ani do oceny wyglądu (§7); tylko rekonesans przed napisaniem testu fazy 4 |

Profil bazy testów: `meaningful` — konfiguracja Pest i Vitest, ~30 plików testów rozproszonych po domenach; brak jakiejkolwiek warstwy klikającej po prawdziwym UI (przebudowy UI sprawdzane jednorazowymi skryptami zrzutów w folderach zmian).

**Stack grounding tools (current session):**
- Docs: Laravel Boost `search-docs` — potwierdzono Pest 5 browser testing (`pest-plugin-browser`, Playwright, `assertNoJavaScriptErrors`); checked: 2026-09-30
- Search: Exa.ai — dostępne, nieużyte (dokumentacja Boost wystarczyła); checked: 2026-09-30
- Runtime/browser: none — brak MCP przeglądarki w tej sesji; checked: 2026-09-30
- Provider/platform: GitHub przez CLI `gh` (nie MCP) — możliwy odczyt stanu CI; nieużyte; checked: 2026-09-30

## 5. Quality Gates

| Gate | Where | Required? | Catches |
|---|---|---|---|
| lint + format (Pint, Oxlint/Oxfmt przez `vp check`) | local + CI (`lint`) | required | styl i ostrzeżenia lint (denyWarnings) |
| typecheck (Larastan level 7, `tsc`) | local + CI (`static-analysis`) | required — stan zielony do potwierdzenia w badaniu fazy 1 | dryf typów |
| backend unit + integration (Pest) | local + CI (`tests`, PostgreSQL 18) | required | regresje logiki, autoryzacji i danych |
| frontend unit (Vitest) | local + CI (`lint`, `npm run test:js`) | required | regresje czystej logiki UI |
| weryfikacja zawartości kopii | CI (`tests`) | required after §3 Phase 1 | kopia bez plików źródeł lub bez danych |
| drill odtworzenia na czystej instancji | ręcznie, na serwerze | required after §3 Phase 1 (rytm ustala faza) | kopia, której nie da się odtworzyć |
| e2e na krytycznych przepływach drzewa i modala | CI on PR | required after §3 Phase 4 | UI wysyła akcję do niewłaściwej osoby |

## 6. Cookbook Patterns

Jak dodać nowy test w tym projekcie. Każda podsekcja wypełnia się po zakończeniu odpowiedniej fazy wdrożenia.

### 6.1 Test nowego endpointu (backend)

- **Location**: `tests/Feature/<Domain>Test.php` (konwencja z `CLAUDE.md`: happy path + cudzy rekord = 404).
- **Reference test**: `tests/Feature/PersonsTest.php`.
- **Run locally**: `vendor/bin/sail artisan test --compact tests/Feature/<Domain>Test.php`.
- Wzorzec asercji na stanie danych (właściwa osoba / para) — TBD — see §3 Phase 2.

### 6.2 Test czystej logiki frontendu

- **Location**: obok modułu, `resources/js/lib/<module>.test.ts`.
- **Reference test**: `resources/js/lib/partial-date.test.ts`.
- **Run locally**: `vendor/bin/sail npm run test:js`.

### 6.3 Test kopii zapasowej i drill odtworzenia

- TBD — see §3 Phase 1 (wzorzec: kopia zawiera bazę i każdy plik źródła; odtworzenie zachowuje liczności i czytelność plików).

### 6.4 Test akcji usuwającej (skutki = lista w potwierdzeniu)

- TBD — see §3 Phase 2 (wzorzec: usunięty zbiór równy pokazanej liście skutków, dane innych osób nietknięte).

### 6.5 Test własności i reguł relacji

- TBD — see §3 Phase 3 (wzorzec: dataset tras z cudzym id w adresie i treści → 404 bez zapisu; reguły relacji na każdej ścieżce zapisu).

### 6.6 Test przepływu w przeglądarce

- TBD — see §3 Phase 4 (wzorzec: przepływ drzewa lub modala z asercją na danych, nie na wyglądzie).

## 7. What We Deliberately Don't Test

Wykluczenia uzgodnione w wywiadzie (P5). Obowiązują, dopóki nie zmieni się założenie.

- **Wygląd — kolory, odstępy, tryb ciemny** — autor ocenia to sam przy każdej przebudowie UI. Ponownie rozważyć, gdy aplikacja zyska innych użytkowników. (Source: Phase 2 interview Q5.)
- **Same widoki i układ stron** — autor widzi je sam; testy UI sprawdzają tylko skutek w danych (§3 Phase 4), nie układ. Ponownie rozważyć, gdy błąd widoku ukryje lub zafałszuje dane. (Source: Phase 2 interview Q5.)
- **Eksport danych** — funkcja nie istnieje (FR-018 odłożony); ochronę przed utratą dostępu daje odtworzenie z kopii (§2 #2). Ponownie rozważyć, gdy eksport wejdzie do zakresu. (Source: roadmap `## Parked`, interview Q2.)

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-09-30
- Stack versions last verified: 2026-09-30
- AI-native tool references last verified: 2026-09-30

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
