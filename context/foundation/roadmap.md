---
project: root-keeper
version: 1
status: draft
created: 2026-09-21
updated: 2026-09-25
prd_version: 1
main_goal: speed
top_blocker: time
milestone_id: sourced-family-tree
milestone_seq: 1
milestone_status: open
---

# Roadmap: root-keeper

> Derived from `context/foundation/prd.md` (v1) + auto-researched codebase baseline.
> Edit-in-place; archive when superseded.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-1: Drzewo potwierdzone źródłami** — Status: open

- **Intent:** Prawdziwe drzewo autora (min. 3 pokolenia) jest w aplikacji; każdą osobę da się odnaleźć, a fakty urodzenia, małżeństwa i zgonu prowadzą do skanu aktu, na którym się opierają — bez sięgania do rozproszonych notatek.
- **Source materials:** `context/foundation/prd.md` (v1)
- **Done when:** every F-NN and S-NN below is `done`, plus prawdziwe drzewo zostało wprowadzone na produkcję po zakończeniu F-01.
- **Scope anchors:** FR-001 – FR-016 (wszystkie konieczne), US-01. FR-018 (nice-to-have) odłożone — patrz `## Parked`.

## Vision recap

Genealog prowadzący własne badania ma notatki, skany i zdjęcia rozproszone i niepowiązane; fakty (np. daty urodzenia) nie wskazują aktów metrykalnych, na których się opierają, a poprzednia aplikacja przestała działać po wygaśnięciu licencji. root-keeper to samodzielnie hostowana aplikacja, w której dane pozostają w pełni własnością użytkownika, a przepływ pracy jest prosty: osoba → fakt → źródło → skan.

## North star

**S-03: Użytkownik otwiera podgląd osoby, widzi urodzenie potwierdzone aktem i otwiera jego skan** — to najmniejszy przepływ, który dowodzi sensu produktu, więc przy celu `speed` idzie tak wcześnie, jak pozwalają zależności.

> „Gwiazda przewodnia” (north star) oznacza tu najmniejszy kompletny wycinek, którego dostarczenie dowodzi, że produkt działa zgodnie z wizją. Stoi tak wcześnie, jak pozwalają jego zależności, bo reszta ma znaczenie tylko wtedy, gdy on działa.

## At a glance

| ID   | Change ID                       | Outcome (user can …)                                                                  | Prerequisites | PRD refs                               | Status   |
| ---- | ------------------------------- | ------------------------------------------------------------------------------------- | ------------- | -------------------------------------- | -------- |
| F-01 | production-data-safety          | (foundation) produkcja chroni dane i skany przed utratą i przed podmiotami trzecimi   | —             | NFR (RPO 24 h), NFR (podmioty trzecie), Guardrails | done |
| S-01 | account-tree-and-first-person   | założyć konto, od razu mieć swoje drzewo i dodać pierwszą osobę                        | —             | FR-001, FR-002, FR-003, FR-005, FR-008, Access Control | done        |
| S-02 | scan-upload-and-preview         | wgrać skan aktu i obejrzeć go w powiększeniu pozwalającym odczytać treść               | S-01          | FR-013, FR-016, US-01                  | done |
| S-03 | birth-death-confirmed-by-scan   | powiązać skan z urodzeniem lub zgonem i zobaczyć na podglądzie, co jest potwierdzone   | S-01, S-02    | FR-014, FR-008, US-01                  | done |
| S-04 | person-search                   | wyszukać osobę na liście i przejść do jej podglądu                                     | S-01          | FR-009, US-01                          | in-progress |
| S-05 | person-edit-and-delete          | poprawić dane osoby i usunąć ją po jawnym potwierdzeniu                                | S-01          | FR-006, FR-007                         | done |
| S-06 | parent-child-relations          | połączyć rodzica z dzieckiem, zobaczyć relacje na podglądzie i usunąć relację          | S-01          | FR-011, FR-012, FR-008                 | proposed |
| S-07 | marriage-with-shared-source     | dodać małżeństwo z datą i miejscem i potwierdzić je jednym aktem u obojga małżonków    | S-03          | FR-010, FR-012, FR-014, US-01          | proposed |
| S-08 | scan-delete-with-impact         | usunąć skan po zobaczeniu listy faktów, które stracą źródło                            | S-03          | FR-015                                 | proposed |
| S-09 | graphical-tree-view             | oglądać swoje drzewo graficznie i przechodzić z niego do osób                          | S-06, S-07    | FR-004, US-01                          | proposed |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                      | Chain                                  | Note                                                                                       |
| ------ | -------------------------- | -------------------------------------- | ------------------------------------------------------------------------------------------ |
| A      | Fakt potwierdzony źródłem  | `S-01` → `S-02` → `S-03` → `S-08`      | Najkrótsza droga do gwiazdy przewodniej; przy celu `speed` idzie pierwsza.                 |
| B      | Rodzina i drzewo           | `S-06` → `S-07` → `S-09`               | Dołącza do strumienia A w `S-01` (S-06) i `S-03` (S-07, które używa wiązania skanów).      |
| C      | Odnajdywanie osób          | `S-04`                                 | Samodzielny wycinek po `S-01`; może iść równolegle z A.                                    |
| D      | Poprawianie danych osób    | `S-05`                                 | Samodzielny wycinek po `S-01`; może iść równolegle z A.                                    |
| E      | Bezpieczeństwo danych      | `F-01`                                 | Bez zależności od kodu; musi się zakończyć, zanim wejdą prawdziwe skany i dane.            |

## Baseline

What's already in place in the codebase as of `2026-09-21` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — React + Inertia + TypeScript ze starter kitu; strony auth, dashboard i settings (`resources/js/pages/`), brak stron domenowych.
- **Backend / API:** present (szkielet) — tylko kontrolery Settings i akcje Fortify (`app/Http/Controllers/Settings/`, `routes/web.php`), brak tras domenowych.
- **Data:** partial — tylko migracje users/cache/jobs/passkeys (`database/migrations/`); Postgres na produkcji, prywatny dysk plików skonfigurowany (`config/filesystems.php`); brak schematu domenowego.
- **Auth:** present — rejestracja, logowanie, 2FA, passkeys (`app/Providers/FortifyServiceProvider.php`). Brak: automatycznego drzewa przy rejestracji i izolacji danych per użytkownik. Starter kit zawiera reset hasła, który PRD wyklucza.
- **Deploy / infra:** present — `docker-compose.prod.yml`, `scripts/deploy.sh`, CI lint/static-analysis/tests (`.github/workflows/`), pierwszy deploy wykonany; Spatie backups zaplanowane. Otwarte: DNS-only niepotwierdzony, backupy bez szyfrowania i bez działającego maila, brak próby odtworzenia, brak backupu przed migracją.
- **Observability:** partial — domyślny stack logów i `backup:monitor`; brak śledzenia błędów (usługi zewnętrzne wyklucza NFR).

## Foundations

### F-01: Bezpieczeństwo danych produkcyjnych przed wprowadzeniem prawdziwego drzewa

- **Outcome:** (foundation) produkcja nie przepuszcza ruchu przez podmiot trzeci, backupy są szyfrowane, alarmy o awarii backupu docierają, przed migracją powstaje backup bazy, a odtworzenie backupu ze skanem zostało raz przećwiczone.
- **Change ID:** production-data-safety
- **PRD refs:** NFR (RPO 24 h), NFR (podmioty trzecie), Guardrails
- **Unlocks:** ścieżka weryfikacji „wprowadzenie prawdziwego drzewa” (kryterium sukcesu M-1) dla danych z `S-01`, `S-03`, `S-07`; wymiarowanie backupów skanów po limicie rozmiaru ustalonym w `S-02`.
- **Prerequisites:** —
- **Parallel with:** S-01, S-02, S-03, S-04, S-05, S-06, S-07, S-08, S-09
- **Blockers:** Kroki wyłącznie ręczne właściciela: zmiana rekordu DNS, edycja `.env.prod` na serwerze, próba odtworzenia.
- **Unknowns:**
  - Czy skany zostają w nocnych archiwach Spatie, czy przechodzą na osobny, przyrostowy plan backupu — rozstrzygnąć po zmierzeniu pierwszych 20–30 prawdziwych skanów. Owner: user. Block: no.
- **Risk:** Stoi osobno i równolegle, bo nie zależy od kodu, ale musi się zakończyć przed prawdziwymi danymi: PRD nie ma kosza ani cofania, więc utrata danych bez przećwiczonego odtworzenia jest nieodwracalna.
- **Status:** done

## Slices

### S-01: Konto z gotowym drzewem i pierwsza osoba

- **Outcome:** użytkownik może założyć konto, zalogować się, od razu mieć swoje jedno drzewo i dodać do niego osobę (imię, nazwisko, urodzenie, zamieszkanie, wyznanie, zgon; daty pełne, niepełne lub „około”), a następnie zobaczyć ją na liście i w podglądzie.
- **Change ID:** account-tree-and-first-person
- **PRD refs:** FR-001, FR-002, FR-003, FR-005, FR-008, Access Control
- **Prerequisites:** —
- **Parallel with:** F-01
- **Blockers:** —
- **Unknowns:**
  - Czy urodzenie i zgon są od początku osobnymi wydarzeniami osoby (a nie polami osoby), żeby S-03 i S-07 mogły wiązać z nimi skany bez przebudowy? Owner: `/10x-plan`. Block: no.
  - Usunięcie resetu hasła ze starter kitu (PRD: brak odzyskiwania hasła) — w tym wycinku czy osobno? Owner: user. Block: no.
- **Risk:** Pierwszy wycinek wprowadza model danych i izolację danych per użytkownik; źle ułożony model wydarzeń wymusi przebudowę gwiazdy przewodniej, a brak izolacji łamie guardrail dostępu.
- **Status:** done

### S-02: Wgranie i podgląd skanu aktu

- **Outcome:** użytkownik może wgrać skan aktu metrykalnego do swojego drzewa i obejrzeć go w powiększeniu pozwalającym odczytać treść; skan jest niedostępny dla niezalogowanych i innych użytkowników.
- **Change ID:** scan-upload-and-preview
- **PRD refs:** FR-013, FR-016, US-01
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-04, S-05, S-06
- **Blockers:** —
- **Unknowns:**
  - Jaki maksymalny rozmiar skanu przyjąć? Od niego zależy wymiarowanie backupów w F-01. Owner: user. Block: no.
- **Risk:** Stoi przed S-03, bo bez skanu nie ma czego wiązać; ryzykiem jest wyciek pliku przez publiczny dostęp albo zbyt słabe powiększenie, przez które akt jest nieczytelny.
- **Status:** done

### S-03: Urodzenie i zgon potwierdzone skanem (gwiazda przewodnia)

- **Outcome:** użytkownik może powiązać skan z urodzeniem lub zgonem osoby i zmienić to powiązanie, a na podglądzie osoby widzi datę i miejsce wydarzenia ze wskazaniem aktu, otwiera skan, a wydarzenie bez aktu jest wyraźnie odróżnione od potwierdzonego.
- **Change ID:** birth-death-confirmed-by-scan
- **PRD refs:** FR-014, FR-008, US-01
- **Prerequisites:** S-01, S-02
- **Parallel with:** F-01, S-04, S-05, S-06
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Najwcześniej, jak pozwalają zależności, bo to dowód całej wizji; ryzykiem jest model, w którym jeden skan nie może potwierdzać wielu wydarzeń (Logika biznesowa), co wyjdzie dopiero w S-07.
- **Status:** done

### S-04: Wyszukiwanie osoby na liście

- **Outcome:** użytkownik może wyszukać osobę na liście swoich osób i przejść do jej podglądu.
- **Change ID:** person-search
- **PRD refs:** FR-009, US-01
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-05, S-06, S-07, S-08, S-09
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Mały, niezależny wycinek; odnajdywanie osoby to jeden z dwóch bólów z wizji, więc nie może zostać na koniec, ale nie blokuje gwiazdy przewodniej.
- **Status:** in-progress

### S-05: Edycja i usunięcie osoby

- **Outcome:** użytkownik może poprawić dane osoby i usunąć osobę po jawnym potwierdzeniu.
- **Change ID:** person-edit-and-delete
- **PRD refs:** FR-006, FR-007
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-04, S-06, S-07, S-08, S-09
- **Blockers:** —
- **Unknowns:**
  - Co dzieje się z relacjami i powiązaniami skanów usuwanej osoby (relacje powstają później, w S-06 i S-07)? Owner: `/10x-plan`. Block: no.
- **Risk:** Bez kosza i cofania (Non-Goals) usunięcie jest nieodwracalne, więc potwierdzenie i zasięg usunięcia muszą być jednoznaczne.
- **Status:** done

### S-06: Relacja rodzic–dziecko

- **Outcome:** użytkownik może połączyć dwie osoby relacją rodzic–dziecko (dziecko może mieć tylko jednego znanego rodzica), zobaczyć rodziców i dzieci na podglądzie osoby i usunąć relację po jawnym potwierdzeniu.
- **Change ID:** parent-child-relations
- **PRD refs:** FR-011, FR-012, FR-008
- **Prerequisites:** S-01
- **Parallel with:** F-01, S-02, S-03, S-04, S-05, S-07, S-08
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Bez relacji nie da się wprowadzić 3 pokoleń z kryterium sukcesu ani narysować drzewa; ryzykiem jest dopuszczenie cykli lub więcej niż dwóch rodziców.
- **Status:** proposed

### S-07: Małżeństwo potwierdzone jednym aktem u obojga małżonków

- **Outcome:** użytkownik może połączyć dwie osoby małżeństwem z datą i miejscem ślubu (osoba może mieć kilka małżeństw), powiązać z nim skan aktu, który widać jako źródło u obojga małżonków bez dodawania go dwa razy, i usunąć relację po jawnym potwierdzeniu.
- **Change ID:** marriage-with-shared-source
- **PRD refs:** FR-010, FR-012, FR-014, US-01
- **Prerequisites:** S-03
- **Parallel with:** F-01, S-04, S-05, S-06, S-08
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Po S-03, bo używa tego samego wiązania skanów; to najtrudniejsza reguła modelu (jedno wydarzenie dwóch osób) i sprawdza, czy model z S-01/S-03 się utrzyma.
- **Status:** proposed

### S-08: Usunięcie skanu z listą faktów tracących źródło

- **Outcome:** użytkownik może usunąć skan po potwierdzeniu, widząc wcześniej listę faktów, które stracą źródło; fakty bez ostatniego skanu stają się niepotwierdzone.
- **Change ID:** scan-delete-with-impact
- **PRD refs:** FR-015
- **Prerequisites:** S-03
- **Parallel with:** F-01, S-04, S-05, S-06, S-07, S-09
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Wymaga istniejących powiązań (S-03); ciche odłączenie faktów od źródła łamie Logikę biznesową, a usunięcie pliku ze skanem jest nieodwracalne.
- **Status:** proposed

### S-09: Graficzny widok drzewa

- **Outcome:** użytkownik może oglądać swoje drzewo w widoku graficznym i przechodzić z niego do podglądu osób.
- **Change ID:** graphical-tree-view
- **PRD refs:** FR-004, US-01
- **Prerequisites:** S-06, S-07
- **Parallel with:** F-01, S-04, S-05, S-08
- **Blockers:** —
- **Unknowns:**
  - Jak czytelnie pokazać wiele małżeństw jednej osoby i dziecko z jednym znanym rodzicem? Owner: `/10x-plan`. Block: no.
- **Risk:** Na końcu, bo potrzebuje obu rodzajów relacji; to najbardziej niepewny element interfejsu, więc przy braku czasu grozi mu uproszczenie, ale nie wypada z zakresu (FR-004 jest konieczne).
- **Status:** proposed

## Backlog Handoff

GitHub milestone: [M-1](https://github.com/sebastian-kostecki/root-keeper/milestone/1).

| Roadmap ID | Issue | Change ID                     | Suggested issue title                                          | Ready for `/10x-plan` | Notes                                                        |
| ---------- | ----- | ----------------------------- | -------------------------------------------------------------- | --------------------- | ------------------------------------------------------------ |
| F-01       | #2    | production-data-safety        | Zabezpieczenie danych produkcyjnych przed prawdziwym drzewem   | yes                   | Część kroków wyłącznie ręcznie (DNS, `.env.prod`, odtworzenie) |
| S-01       | #3    | account-tree-and-first-person | Konto z automatycznym drzewem i dodanie pierwszej osoby        | yes                   | Run `/10x-plan account-tree-and-first-person`                |
| S-02       | #4    | scan-upload-and-preview       | Wgrywanie i podgląd skanu aktu z powiększeniem                 | no                    | Czeka na S-01                                                |
| S-03       | #5    | birth-death-confirmed-by-scan | Urodzenie i zgon potwierdzone skanem na podglądzie osoby       | no                    | Gwiazda przewodnia; czeka na S-01, S-02                      |
| S-04       | #6    | person-search                 | Wyszukiwanie osoby na liście                                   | no                    | Czeka na S-01                                                |
| S-05       | #7    | person-edit-and-delete        | Edycja i usuwanie osoby z potwierdzeniem                       | no                    | Czeka na S-01                                                |
| S-06       | #8    | parent-child-relations        | Relacje rodzic–dziecko                                         | no                    | Czeka na S-01                                                |
| S-07       | #9    | marriage-with-shared-source   | Małżeństwa z aktem widocznym u obojga małżonków                | no                    | Czeka na S-03                                                |
| S-08       | #10   | scan-delete-with-impact       | Usuwanie skanu z listą faktów tracących źródło                 | no                    | Czeka na S-03                                                |
| S-09       | #11   | graphical-tree-view           | Graficzny widok drzewa                                         | no                    | Czeka na S-06, S-07                                          |

## Open Roadmap Questions

Brak otwartych pytań. PRD nie ma otwartych pytań.

Rozstrzygnięte:

- **Deploy ręczny czy automatyczny po merge'u?** (#12, 2026-09-21) — Ręczny: właściciel uruchamia `scripts/deploy.sh` na VPS po merge'u do `main`. Workflow deployu nie powstaje; `tech-stack.md` poprawiony (`ci_default_flow: manual-promotion`).

## Parked

- **Eksport wszystkich danych i skanów w otwartym formacie (FR-018)** — Why parked: nice-to-have, a cel `speed` i ryzyko `time` odkładają wszystko poza wymaganiami koniecznymi; PRD świadomie akceptuje to ryzyko (Non-Goals).
- **Zdjęcie osoby (portret)** — Why parked: PRD §Non-Goals, przesunięte do v2.
- **Dane o pogrzebie** — Why parked: PRD §Non-Goals, przesunięte do v2.
- **Zbiorcza lista faktów bez źródła** — Why parked: PRD §Non-Goals; odróżnienie na podglądzie osoby (S-03) wystarcza.
- **Źródła dla wyznania i miejsca zamieszkania** — Why parked: PRD §Non-Goals (FR-014).
- **Wiele drzew, role, współdzielenie** — Why parked: PRD §Non-Goals (FR-003, Access Control).
- **Opis skanów, kosz i cofanie, historia zmian** — Why parked: PRD §Non-Goals.
- **Pilnowanie zgodności faktu z aktem, stan „sprzeczny”** — Why parked: PRD §Non-Goals, Logika biznesowa.
- **Rozróżnienie rodzica biologicznego i przybranego** — Why parked: PRD §Non-Goals (FR-011).
- **Stan potwierdzenia w widoku drzewa** — Why parked: PRD §Non-Goals.
- **GEDCOM, OCR, warianty pisowni, integracje zewnętrzne** — Why parked: PRD §Non-Goals.
- **Odzyskiwanie hasła** — Why parked: PRD §Non-Goals; istniejący reset ze starter kitu do usunięcia (Unknown w S-01).
- **Urządzenia mobilne, wielojęzyczność** — Why parked: PRD §Non-Goals (non-functional).

## Milestone History

## Done

- **F-01: (foundation) produkcja chroni dane i skany przed utratą i przed podmiotami trzecimi** — Archived 2026-09-22 → `context/archive/2026-09-22-production-data-safety/`. Lesson: —.
- **S-01: użytkownik może założyć konto, zalogować się, od razu mieć swoje jedno drzewo i dodać do niego osobę (imię, nazwisko, urodzenie, zamieszkanie, wyznanie, zgon; daty pełne, niepełne lub „około”), a następnie zobaczyć ją na liście i w podglądzie.** — Archived 2026-09-23 → `context/archive/2026-09-23-account-tree-and-first-person/`. Lesson: —.
- **S-02: użytkownik może wgrać skan aktu metrykalnego do swojego drzewa i obejrzeć go w powiększeniu pozwalającym odczytać treść; skan jest niedostępny dla niezalogowanych i innych użytkowników.** — Archived 2026-09-24 → `context/archive/2026-09-24-scan-upload-and-preview/`. Lesson: —.
- **S-03: użytkownik może powiązać skan z urodzeniem lub zgonem osoby i zmienić to powiązanie, a na podglądzie osoby widzi datę i miejsce wydarzenia ze wskazaniem aktu, otwiera skan, a wydarzenie bez aktu jest wyraźnie odróżnione od potwierdzonego.** — Archived 2026-09-24 → `context/archive/2026-09-24-birth-death-confirmed-by-scan/`. Lesson: —.
- **S-05: użytkownik może poprawić dane osoby i usunąć osobę po jawnym potwierdzeniu.** — Archived 2026-09-25 → `context/archive/2026-09-25-person-edit-and-delete/`. Lesson: —.
