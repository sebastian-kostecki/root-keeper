---
date: 2026-10-02T13:42:52+02:00
researcher: Sebastian Kostecki
git_commit: 4f54ce1a98760b64343cda012f0df09fc6ccce9f
branch: main
repository: root-keeper
topic: "Faza 2 test-planu: czy zapis i usuwanie trafiają we właściwe dane (ryzyka #1 i #3)"
tags: [research, testing, write-targets, delete-impact, risk-1, risk-3]
status: complete
last_updated: 2026-10-02
last_updated_by: Sebastian Kostecki
last_updated_note: "Decyzje autora 2026-10-02 dopisane do Open Questions (pytania 1, 4, Vitest)"
---

# Research: Zapis i usuwanie trafiają we właściwe dane (test-plan §3 faza 2, ryzyka #1, #3)

**Date**: 2026-10-02T13:42:52+02:00 · **Git Commit**: 4f54ce1 · **Branch**: main · **Repository**: root-keeper

## Research Question

Jaką wyrocznię (ze źródeł, nie z implementacji) i jakie ścieżki kodu ma pokryć faza 2: (#1) akcje z drzewa i modala zmieniają dokładnie wskazaną osobę lub parę, a „potwierdzenie” innych faktów się nie zmienia; (#3) zbiór faktycznie usuniętych rekordów i plików jest równy liście skutków z potwierdzenia, a dane współmałżonka i innych osób zostają. Co jest już pokryte, gdzie są luki, jaka warstwa testów jest najtańsza.

## Summary

- **Frontend nie wymyśla id.** Na sprawdzonych ścieżkach zapisu z drzewa i modala (tabele w „Detailed Findings”) każde id w żądaniu pochodzi z bieżących propsów Inertia (`sheet`, `personPreview`) w chwili kliknięcia; jedyny stan id po stronie klienta to stos „Wstecz” (`person-preview-provider.tsx:55-93`), który nie jest czytany przez żadną ścieżkę zapisu. Wybór pary robi serwer z `?spouse=`; klient wysyła `parents[0]` w adresie i `parents[1]?.id ?? null` jako `other_parent_id` (`add-child-dialog.tsx:70-76`). Ryzyko #1 sprowadza się więc głównie do **kontraktu żądania** (testowalnego w Pest) i do kilku wyciągniętych z komponentów reguł (Vitest) — nie do stanu klienta.
- **Lista skutków usunięcia ma dwa źródła prawdy**: dla osoby liczy ją klient z `personPreview` (`delete-person-dialog.tsx:44-54`), dla źródła serwer jako prop `confirms` (`SourceController.php:114-131`). Żaden test nie porównuje listy skutków z faktycznie usuniętym zbiorem (`grep -rn impact tests` → brak trafień).
- **Wyrocznia usunięcia osoby** (archiwum + PRD): znika osoba, każde jej małżeństwo (także u drugiego małżonka), wydarzenia, w których jest jedynym uczestnikiem, relacje rodzic–dziecko w obu kierunkach i powiązania źródeł tych wydarzeń; zostają źródła, pliki i powiązane osoby; `root_person_id` zerowane. Kod (`DeletePerson.php:21-32` + kaskady FK) jest z tym zgodny.
- **Najsłabiej pokryte** (jedyne luki, przy zakresie sprawdzonych testów): brak testu „osoba z wieloma małżeństwami/relacjami/źródłami + osoby postronne” z równością zbioru; `events.sources.destroy` bez asercji „drugie powiązanie nietknięte”; usunięcie źródła bez testu, że pliki i wiersze innego źródła zostają; zapisy z drzewa bez asercji „żadna inna osoba się nie zmieniła”; `lib/person-payload.ts` bez testu Vitest.
- **Nierozstrzygnięte wyrocznią**: podwójne wiersze w `confirms` dla małżeństwa (zob. Open Questions).

## Detailed Findings

### Model danych i kaskady (zakres: migracje, sprawdzone w całości przez agenta)

- Małżeństwo nie ma tabeli: to `events` typu `marriage` + dwa wiersze `event_participants` z rolą spouse. Typy zdarzeń: birth, death, marriage (`app/Enums/EventType.php:7-9`).
- Wszystkie FK z `event_participants`, `event_source`, `parent_child_relations` (parent_id i child_id) oraz `events.tree_id`, `people.tree_id`, `sources.tree_id` są `cascade`; `trees.root_person_id` → `nullOnDelete`. Brak `restrict` w migracjach.
- „Potwierdzone” nie jest przechowywane: fakt jest potwierdzony, gdy jego zdarzenie ma ≥1 wiersz `event_source` (`EventResource.php:48-53`; archiwum `2026-09-30-fact-confirmed-by-any-source/plan.md:14`). Potwierdzenie tracą tylko: odpięcie ostatniego źródła, usunięcie źródła, usunięcie małżeństwa, usunięcie osoby; edycja dat/miejsc/imion aktualizuje zdarzenie w miejscu (`UpdatePerson.php:110-128`, `UpdateMarriage.php:20`), a odznaczenie „zmarła” ze źródłami na zgonie odrzuca walidacja (`PersonUpdateRequest.php:38-42`).

### Ścieżki zapisu i usunięcia (routes/web.php:17-42, wszystkie za `auth`+`verified`)

- Zapisy z id w treści: `persons.children.attach` (`child_id`, `other_parent_id`), `persons.children.store` (`other_parent_id`), `persons.spouses.attach` (`spouse_id`), `parent-child-relations.store` (`parent_id`, `child_id`, `role`), `marriages.store` (`person_id`, `spouse_id`), `events.sources.store` (`source_id`); `persons.parents.store` niesie `role`. Walidacja `Rule::exists(...)->where('tree_id', …)` (np. `MarriageStoreRequest.php:23-24`, `ParentChildRelationStoreRequest.php:21-22`).
- Usunięcia: `persons.destroy` (`DeletePerson`), `marriages.destroy` (kontroler, bez Action; `MarriageController.php:49-59`), `parent-child-relations.destroy` (kontroler, `:34-43`), `events.sources.destroy` (`detach`, `EventSourceController.php:34-48`), `sources.destroy` (`DeleteSource`).
- `DeletePerson` (w transakcji): usuwa wszystkie zdarzenia małżeńskie osoby (czyli także u współmałżonka), potem zdarzenia z dokładnie jednym uczestnikiem, potem osobę (`DeletePerson.php:21-32`). Źródła i pliki nietknięte.
- `DeleteSource`: zbiera `path` i `thumbnail_path`, usuwa wiersz (kaskada `event_source`), **potem** `Storage::disk('local')->delete`; porażka pliku tylko `report()` (`DeleteSource.php:17-26`). Poza transakcją — wiersz bywa usunięty przy zostawionym pliku.

### Skąd id w UI (zakres: components/tree, persons, sources, lib, hooks)

| Akcja | Plik:linia | Id w żądaniu |
|---|---|---|
| dziecko — istniejące | `add-child-dialog.tsx:70-76` | `child_id` = kliknięty kandydat; `other_parent_id` = `target.parents[1]?.id ?? null`; adres: `parents[0].id` |
| dziecko — nowe | `add-child-dialog.tsx:77-88` | ukryte `other_parent_id` tylko gdy istnieje `parents[1]` |
| małżonek — istniejący / nowy | `add-spouse-dialog.tsx:176-191` | `spouse_id` / tylko `focus.id` w adresie |
| rodzic — istniejący | `parent-slot-dialog.tsx:278-285` | `parent_id`, `child_id = slot.id`, `role = slot.role` |
| rodzic — nowy | `parent-slot-dialog.tsx:286-297` | ukryte `role`, `slot.id` w adresie |
| ożenek pary | `marry-couple-dialog.tsx:336-338` | `person_id`, `spouse_id` z `sheet` |
| usunięcie osoby / małżeństwa / relacji | `delete-person-dialog.tsx:79`, `remove-marriage-dialog.tsx:52`, `remove-relation-dialog.tsx:48` | `person.id`, `marriage.id`, `relation_id` wiersza |
| odpięcie źródła | `unlink-source-dialog.tsx:37-45` | `event.id`, `source.id` wiersza |

- Przełączanie związku (`union-node.tsx:79-97`) to wizyta GET z `?spouse=`; żadnego zapisu. Klient filtruje kandydatów (`excluded_ids`, `blocked_ids`, płeć), ale autorytetem jest serwer.
- Czysta logika zamknięta w komponentach (kandydaci do wyciągnięcia do `lib`): budowa payloadów dialogów (`add-child-dialog.tsx:70-76`), filtry płci (`parent-slot-dialog.tsx:227-277`, `add-spouse-dialog.tsx:157-175`), kwalifikowalność „ożeń parę” (`family-sheet.tsx:113-128`, `215-225`), parsowanie `marriage-${id}` (`overview-tab.tsx:192-215`), liczniki skutków (`delete-person-dialog.tsx:54-81`). Już czysty, ale bez testu: `lib/person-payload.ts`.
- Możliwe nieaktualne id (nie potwierdzone wykonaniem): po `choose()` stary `sheet` jest renderowany do końca wizyty, więc kliknięcie w tym oknie wysłałoby id starego związku; serwer jest ostatnią barierą (czy odrzuca niezgodną parę — nie sprawdzono, zob. Open Questions).

### Skąd lista skutków (zakres: dialogi usuwania)

- Osoba: klient sumuje `birth.sources`, `death.sources`, źródła małżeństw; `relationCount = parents + children`; `marriageCount = marriages.length` (`delete-person-dialog.tsx:44-54`), dane z `personPreview` (`BuildPersonPreview.php:27-33`). Tekst: „zniknie także u drugiego małżonka”, źródła i powiązane osoby zostają.
- Źródło: `confirms` z serwera, po jednym wierszu na (zdarzenie, uczestnik), `is_last_source = sources_count === 1` (`SourceController.php:114-131`); dialog renderuje (`delete-source-dialog.tsx:40-84`).
- Małżeństwo: `marriage.sources.length`; relacja: tylko imiona, bez liczb (`remove-relation-dialog.tsx:59-64`), zgodnie z PRD („bez listy traconych danych”, `prd.md:106-107`).

### Co już pokrywają testy (zakres: tests/Feature, tests/Pest.php, tests/Unit; nic nie uruchamiano)

- Pokryte stanem bazy: usunięcie osoby (`PersonsTest.php:874`, `:896`), obie strony relacji przy usunięciu osoby (`ParentChildRelationsTest.php:184`), usunięcie małżonka (`MarriagesTest.php:269`), usunięcie małżeństwa (`:242`), usunięcie jednej relacji z zachowaniem rodzeństwa (`ParentChildRelationsTest.php:163`), usunięcie źródła z plikami i pivotem (`SourcesTest.php:402`) oraz odpięcie ostatniego vs nieostatniego (`:427`), update zdarzenia w miejscu (`PersonsTest.php:785`).
- Luki: brak testu sceny z postronnymi (osoby, inne małżeństwa, inne źródła); `EventSourcesTest.php:105` sprawdza tylko `assertDatabaseCount('event_source', 0)`; brak testu zbiorów usuniętych vs `confirms`/liczniki; zapisy z drzewa (`ParentChildRelationsTest.php:360-776`, `MarriagesTest.php:294-422`) przeważnie z fikstury jednej pary (część z niefiltrowanym `Person::where(...)->firstOrFail()`).
- Narzędzia do ponownego użycia: `ownershipSnapshot()` (liczba wierszy + md5 każdej tabeli `public` poza `migrations` + pliki dysku `local`; wymaga `Storage::fake('local')`), `attachBirth/attachDeath/attachMarriage/linkParent` w `tests/Pest.php`, fikstura `ownershipRecords()` w `OwnershipBoundariesTest.php:15-65`. Dotąd snapshot służył tylko równości przed/po; porównanie **różnicy** per tabela (co zniknęło) nie istnieje.
- Vitest: 7 plików w `lib/` i `hooks/`; brak testów logiki drzewa, pary, historii modala i `person-payload.ts`.

## Wyrocznia (źródła, nie implementacja)

| Zachowanie | Źródło |
|---|---|
| Usunięcie osoby: znika ona, jej wydarzenia jedynego uczestnika z powiązaniami, jej małżeństwa; źródła i pliki zostają | `archive/2026-09-25-person-edit-and-delete/plan.md:26`; zmiana dla małżeństw: `archive/2026-09-25-marriage-with-shared-source/plan.md:43`; `prd.md:67`, FR-013 `prd.md:116` |
| Potwierdzenie usunięcia pokazuje „te same skutki” | `prd.md:67`, `:116` |
| Usunięcie źródła: lista faktów (zdarzenie + osoba), które tracą potwierdzenie, oznaczenie ostatniego źródła; usuwa wiersz, powiązania i oba pliki; wiersz najpierw, pliki potem, brak pliku tolerowany | `archive/2026-09-25-scan-delete-with-impact/plan.md:5`, `:46`; `archive/2026-09-29-scans-become-sources/plan.md:162`, `:183`; `prd-v3.md:120` (FR-011) |
| Usunięcie relacji/małżeństwa po potwierdzeniu, bez listy; obie osoby zostają | `prd.md:106-107`; `archive/2026-09-25-parent-child-relations/plan.md:22` |
| Potwierdzone = ≥1 powiązane źródło; edycja nie resetuje potwierdzenia; zdarzenia aktualizowane w miejscu | `prd.md:139`; `archive/2026-09-30-fact-confirmed-by-any-source/plan.md:14`, `:37`; `archive/2026-09-25-person-edit-and-delete/plan.md:53` |
| Akcje z drzewa: wybór tworzy tylko brakujące linki, nowa osoba atomowo z relacją, błąd reguły niczego nie tworzy | `archive/2026-09-28-tree-child-existing-or-new/plan.md:20-39`, `tree-spouse-…/plan.md:25-48`, `tree-parent-slot-…/plan.md:19-31` |
| Wstecz: stos w providerze, mutacje wracają na bieżący URL | `archive/2026-09-29-person-modal/plan.md:34`, `archive/2026-09-28-person-preview-in-modal/plan.md:33` |

## Code References

- `app/Actions/Persons/DeletePerson.php:21-32` — zbiór usuwany przy kasowaniu osoby.
- `app/Actions/Sources/DeleteSource.php:17-26` — wiersz, potem pliki, błąd pliku tylko raportowany.
- `app/Http/Controllers/SourceController.php:114-131` — `confirms` (po wierszu na zdarzenie × uczestnika).
- `resources/js/components/persons/delete-person-dialog.tsx:44-54` — liczniki skutków liczone w kliencie.
- `app/Http/Controllers/MarriageController.php:49-59`, `ParentChildRelationController.php:34-43` — usunięcia bez Action.
- `app/Actions/Relations/AttachChildToParents.php` — dołącza tylko brakujące linki w transakcji z blokadą wierszy; „Ta relacja już istnieje.” gdy nic nie brakuje.
- `tests/Pest.php` (`ownershipSnapshot`) i `tests/Feature/OwnershipBoundariesTest.php:15-65` — kandydaci do ponownego użycia.

## Architecture Insights

- Kontrakt usunięcia jest wyłącznie skutkiem kaskad FK + `DeletePerson`/`DeleteSource`; lista skutków nigdzie nie jest liczona z tego samego kodu — dobra wiadomość dla testu: porównanie „lista vs zbiór” **nie** będzie tautologią, o ile oczekiwany zbiór wyprowadzimy z fikstury (znamy, co zbudowaliśmy) i wyroczni, a nie z zapytania liczącego listę (test-plan.md:41).
- Dwuwarstwowo: usunięcia i kontrakty żądań to integracja (prawdziwy PostgreSQL, kaskady — stub by kłamał); hermetyczne stuby potrzebne tylko dla niedeterministycznej porażki pliku (`Storage::delete` zwraca false) — już pokryte `SourcesTest.php:468`. Wybór stanu klienta (Vitest) dopiero po wyciągnięciu logiki do `lib`.

## Historical Context (from prior changes)

- `context/archive/2026-10-02-testing-ownership-boundaries/plan.md:62` i `…testing-relation-rules/plan.md:62` — wzorzec arrange → `Storage::fake('local')` → `ownershipSnapshot()` → żądanie → asercje; faza 3 zbadała odmowy (404 / reguły), nie poprawność zbioru zapisanego/usuniętego.
- `test-plan.md` §6.1 (wzorzec asercji na stanie danych) i §6.4 (test akcji usuwającej) mają status TBD „see §3 Phase 2” — tę fazę uzupełni cookbook.

## Related Research

- `context/archive/2026-10-02-testing-ownership-boundaries/research.md`, `context/archive/2026-10-02-testing-relation-rules/research.md` (istnieją; nie otwierano — wzorce wzięto z ich `plan.md`).

## Decyzje autora (2026-10-02)

- **`confirms` dla małżeństwa**: dwa wiersze (po jednym na małżonka) to zachowanie zamierzone; test zakłada 2 wiersze na małżeństwo (pyt. 1 poniżej rozstrzygnięte).
- **Niezgodna para w drzewie własnym**: `other_parent_id` niebędący partnerem wybranego związku, ale z tego samego drzewa, to dane poprawne; test tylko dokumentuje kontrakt (null vs ustawiony), bez nowej reguły serwera (pyt. 4 rozstrzygnięte).
- **Zakres warstw**: tylko Pest; wyciągnięcie logiki do `lib` pod Vitest odłożone, a „Unit (Vitest)” z wniosków poniżej jest poza zakresem fazy 2.

## Open Questions

1. **Wyrocznia niejednoznaczna — `confirms` dla małżeństwa.** Kod generuje dwa wiersze (po jednym na małżonka) dla tego samego zdarzenia; klucz `event_id-person_id`. Plan archiwalny mówi „każdy fakt (zdarzenie + osoba)”, co dopuszcza oba odczyty. Czy dwa wiersze to zamierzone zachowanie, czy błąd do udokumentowania? Do rozstrzygnięcia przez autora przed asercją (zasada: zatrzymaj się i zapytaj).
2. **Liczniki osoby**: `relationCount = parents + children`, a wiersze relacji usuwane są dokładnie dla tych par (kaskada). Nie zweryfikowano, że `BuildPersonPreview` ładuje `parents`/`children` tak samo szeroko jak kaskada (zakres drzewa jednego użytkownika) — to jest właśnie to, co fikstura z postronnymi ma udowodnić.
3. **Okno wyścigu**: walidacja „nie odznaczaj zmarłej ze źródłami” i późniejszy `death()->delete()` nie są pod blokadą (`PersonUpdateRequest.php:38-42`, `UpdatePerson.php:63-65`); poza zakresem testów hermetycznych (współbieżność wyłączona w fazie 3).
4. **Czy serwer odrzuca niezgodną parę** (`other_parent_id` nie będący rodzicem związku z `?spouse=`) — nie sprawdzono kontrolerów/requestów pod tym kątem; reguła `Rule::exists` weryfikuje tylko własność drzewa. Pytanie produktowe: czy „zła para w drzewie własnym” ma być w ogóle odrzucana, czy uznajemy za prawidłowe (wyrocznia milczy).
5. **Faza 4 (e2e)**: czy tańsze warstwy łapią pomyłkę osoby w UI — odpowiedź robocza: id pochodzą z propsów, więc brak dowodu na ryzyko stanu klienta poza oknem wyścigu po `choose()`; rekomendacja do `/10x-plan` — bez e2e dopóki Pest/Vitest nie pokażą luki.

## Wnioski dla /10x-plan (propozycja warstw; decyzje należą do planu)

- **Integracja (Pest)**: (a) „scena” z postronnymi — usunięcie osoby z ≥2 małżeństwami, rodzicami, dziećmi, źródłami; oczekiwany zbiór z fikstury i wyroczni, asercja różnicy per tabela i listy plików (`ownershipSnapshot` przed/po + jawna lista zniknięć); (b) usunięcie źródła z dwoma źródłami i wspólnym zdarzeniem — pliki i wiersze drugiego zostają, `confirms` zgodne z faktycznie zdetachowanymi zdarzeniami; (c) `events.sources.destroy` z drugim powiązaniem nietknięte; (d) zapisy z drzewa: kontrakt `other_parent_id` null vs ustawiony i „żadna inna osoba/para się nie zmieniła” na scenie z kilkoma związkami; (e) potwierdzenie pozostałych faktów po edycji osoby/małżeństwa.
- **Unit (Vitest)** dopiero po wyciągnięciu do `lib`: payload dziecka, filtry płci, kwalifikowalność „ożeń parę”, liczniki skutków, `person-payload.ts`.
- **Hermetyczne**: nie potrzebne poza już istniejącym przypadkiem porażki pliku.
