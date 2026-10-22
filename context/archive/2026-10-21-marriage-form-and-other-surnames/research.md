---
date: 2026-10-21T15:47:44+02:00
researcher: Claude (Opus 5.5)
git_commit: ac82432
branch: develop
repository: root-keeper
topic: "Forma ślubu, para bez ślubu i inne nazwiska osoby: stan modelu małżeństwa, par i nazwisk oraz kontrakty, których dotknie zmiana"
tags: [research, marriages, events, family-sheet, union, persons, surnames, search, pickers]
status: complete
last_updated: 2026-10-21
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje autora 1–5: para bez ślubu bez zmian, forma ślubu w panelu i Rodzinie bez podpowiedzi ze źródeł, inne nazwiska poza zakresem (zostaje nazwisko rodowe)"
---

# Research: Forma ślubu, para bez ślubu i inne nazwiska (`marriage-form-and-other-surnames`)

**Date**: 2026-10-21T15:47:44+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: ac82432 (drzewo robocze czyste poza `context/changes/marriage-form-and-other-surnames/`)
**Branch**: develop
**Repository**: root-keeper

## Research Question

`/10x-research marriage-form-and-other-surnames` — zalążek z `change.md`:

- **forma ślubu** (cywilny / wyznaniowy / cywilno-wyznaniowy / nieznana) w panelu małżeństwa,
- **status pary bez ślubu**,
- **lista innych nazwisk osoby** (po mężu / wariant pisowni / vel) przeszukiwana przez wyszukiwarkę i picker.

Pytania: jak dziś zapisane jest małżeństwo i para, czy „para bez ślubu” już istnieje, gdzie wyszukiwanie i pickery dopasowują nazwiska, jaki istniejący wzorzec pasuje do listy nazwisk, co mówią wcześniejsze decyzje (PRD, archiwum).

## Summary

1. **Małżeństwo = `Event{type: marriage}` + dwa `event_participants{role: spouse}`**; zapisywane pola to tylko data częściowa i `locality_id` (`app/Http/Requests/MarriageUpdateRequest.php:26-40`, `app/Http/Requests/MarriageStoreRequest.php:20-26`, `app/Actions/Relations/UpdateMarriage.php:20`, `app/Actions/Persons/MapsEventInput.php:98-107`). Formy ślubu nie modeluje nic: grep po `civil|religious|konkordat|cywil|wyznani|kości` w `app/` trafia wyłącznie na `Religion` osoby / parafii / cmentarza i `CemeteryManager::Religious`. „Typ małżeństwa” był świadomie poza zakresem w trzech wcześniejszych zmianach.
2. **Para bez ślubu już istnieje — ale tylko pośrednio, przez wspólne dziecko.** `BuildFamilySheet` dokłada po małżonkach współrodziców bez małżeństwa jako unie z `is_marriage: false`, `marriage_id: null` (`app/Actions/Trees/BuildFamilySheet.php:77-117`); węzeł pokazuje „bez ślubu” (`resources/js/components/tree/union-node.tsx:37-50`), linia pary jest przerywana, jest przycisk „Połącz jako małżeństwo”. Brakuje: pary bez ślubu **bez wspólnego dziecka**, rekordu/id dla takiej pary (nie ma panelu, źródeł, notatki) — a dodanie małżonka na drzewie zawsze tworzy małżeństwo (`PersonSpouseController.php:19-42`).
3. **Dwa śluby jednej pary są zablokowane przy zapisie** („Ta para ma już małżeństwo.”, `app/Actions/Relations/CreateMarriage.php:32-41`), z decyzją archiwalną „jedno małżeństwo na parę; cywilny + kościelny pokrywają skany na jednym zdarzeniu”. Wartość „cywilno-wyznaniowy” pasuje do tej decyzji: jedno zdarzenie, źródła z USC i z parafii.
4. **Inne nazwiska są dziś non-goalem PRD** („Brak wielu nazwisk po kolejnych mężach … reszta w notatce”, `context/foundation/prd.md:240`), warianty pisowni też były parkowane. Zmiana musi zmodyfikować PRD.
5. **Wyszukiwanie ma dwa niezależne miejsca**: serwerowe na `/persons` (ILIKE po `first_name`/`last_name`/`maiden_name`, `app/Http/Controllers/PersonController.php:42-53`) i klienckie w pickerach — filtr po samym `display_name` (`resources/js/components/persons/person-candidate-list.tsx:33-38`) z payloadu `ListPersonCandidates` (`app/Actions/Trees/ListPersonCandidates.php:17-31`). Nazwisko rodowe pickery znajdują tylko dzięki „z d.” w `displayName()`; inne nazwiska wymagają nowego pola w payloadzie kandydata i w zapytaniu serwera.
6. **Wzorzec listy = zamieszkania**: lista zagnieżdżona w PUT osoby z pełnym zastąpieniem (`UpdatePerson::replaceResidences`, `app/Actions/Persons/UpdatePerson.php:94-122`; `resources/js/components/persons/residences-fields.tsx`), bez własnych tras i polityki.

## Detailed Findings

### A. Małżeństwo: schemat i ścieżka zapisu

- `events` (`database/migrations/2026_09_23_045723_create_events_table.php:14-24`): `tree_id`, `type`, data częściowa, `place` (tekst legacy), później `locality_id` i `cemetery_id`. Brak indeksu unikalnego.
- `event_participants` (`database/migrations/2026_09_23_045724_create_event_participants_table.php:14-21`): `role` NOT NULL (`ParticipantRole::Subject|Spouse`, `app/Enums/ParticipantRole.php:5-9`), UNIQUE(event_id, person_id), bez `tree_id`.
- `parent_child_relations` (`database/migrations/2026_09_25_061809_create_parent_child_relations_table.php:14-23`): brak kolumny pary/unii — dziecko nie wskazuje małżeństwa.
- Trasy: `Route::resource('marriages', MarriageController)->only(store, update, destroy)` (`routes/web.php:78`); `persons/{person}/spouses[/attach]` (`routes/web.php:69-70`).
- `CreateMarriage` (`app/Actions/Relations/CreateMarriage.php:24-55`): blokady obu osób, odrzucenie duplikatu pary (zapytanie, nie constraint), utworzenie zdarzenia i dwóch uczestników.
- `UpdateMarriage` (`app/Actions/Relations/UpdateMarriage.php:20`) aktualizuje tylko `eventAttributesFrom($date)` — datę i miejsce; `MarriageUpdateRequest` (`:26-40`) odrzuca nie-małżeństwo jako 404 i waliduje wyłącznie `partialDateRules('marriage')`.
- Payload panelu: `BuildMarriagePanel` (małżeństwo, małżonkowie, wspólne dzieci), `MarriageResource` = `EventResource` + `spouse` (`app/Http/Resources/MarriageResource.php:41-43`); prop `marriagePanel` z `?marriage=<event id>` wymaga `type = Marriage` i dokładnie 2 uczestników (`app/Http/Middleware/HandleInertiaRequests.php:68-71, 121-148`).
- Front: `resources/js/components/marriages/marriage-panel.tsx` — karta „Ślub” (widok: Data, Miejsce `:180-186`; edycja `MarriageFields`, `resources/js/components/persons/person-section-fields.tsx:256-268`), „Źródła ślubu” (`:197-201`), „Dzieci” (`:203-215`). Ta sama karta jest edytowalna z przeglądu osoby (`resources/js/components/persons/overview-tab.tsx:270-286, 403-411`). Zapis: `marriageSaveRequest` / `marriageFieldDefaults` (`resources/js/lib/person-payload.ts:186-211`), body `{marriage: PartialDatePayload}`.

**Wniosek dla formy ślubu:** najmniejsza zmiana to nullable kolumna na `events` (znacząca tylko dla `type = marriage`; `null` = nieznana) przechodząca przez `partialDateRules`-podobną regułę w `MarriageStore/UpdateRequest`, `MapsEventInput::eventAttributesFrom`, `EventResource`/`MarriageResource`, `MarriageFields` i `marriageSaveRequest`. Alternatywa (osobna tabela małżeństw) nie jest potrzebna dla samej formy.

**Wyprowadzanie formy ze źródeł:** możliwe tylko jako podpowiedź — `marriage.sources → source.parish → kind` (`sources.parish_id`, `app/Models/Source.php:23,39,81`; `app/Enums/ParishKind.php:5-29`). Nie rozróżnia „wyznaniowy” od „cywilno-wyznaniowy” (księgi parafialne Królestwa Polskiego 1826–1945 były aktami stanu cywilnego — `context/archive/2026-10-20-places-review/research.md:39,102-105`), a źródła bez parafii (nagrobek, inne) nic nie mówią. Dziś nic takiego nie liczy.

### B. Para i unia na drzewie

- Unie ogniska: najpierw małżonkowie (wg `PersonOrdering::marriages`), potem współrodzice bez małżeństwa wyliczeni ze wspólnych dzieci (`app/Actions/Trees/BuildFamilySheet.php:60-117`); dzieci bez drugiego rodzica → unia `unknown` (`:120-130`).
- Para rodziców/dziadków: `pairMarriage()` daje obrączki i rok tylko przy zdarzeniu Marriage (`BuildFamilySheet.php:371-391`); `unmarriedParents()` (`:199-240`, surowe SQL na `event_participants` ⨝ `events`) steruje przyciskiem „Połącz jako małżeństwo” (`resources/js/components/tree/family-sheet.tsx:420-434, 657-665`; `marry-couple-dialog.tsx:25-37`).
- Front: „bez ślubu”, przerywana ramka i linia (`union-node.tsx:37-50, 73-76, 115`; `family-sheet.tsx:667-670, 810-826`). Panel małżeństwa otwiera się tylko przy `marriage_id` (`union-node.tsx:177-195`).
- `BuildAncestorChart` nie czyta małżeństw (tylko `ParentChildGraph`).
- Dodanie dziecka/rodzica nie wymaga małżeństwa (`other_parent_id` w `PersonChildStoreRequest.php:33`, `PersonChildAttachRequest.php:41`).

**Wniosek:** „para bez ślubu” z dzieckiem jest obsłużona bez rekordu. Nowe są tylko: (a) para bez ślubu bez wspólnych dzieci, (b) panel/źródła/notatka dla pary bez ślubu. Oba wymagają albo rozszerzenia zdarzenia Marriage o stan „bez ślubu” (semantycznie zdarzenie przestaje być ślubem: oś czasu, „Źródła ślubu”, `source-matching.ts` dla `record_type = marriage`), albo osobnego rekordu pary — dotyka wszystkich punktów z sekcji „Punkty styku” poniżej.

### C. Konsumenci zdarzenia Marriage (punkty styku przy zmianie semantyki)

- Oś czasu: własne śluby i zgony małżonków (`app/Support/PersonTimeline.php:85, 131-143`; `app/Support/TimelineEntry.php:26-33`; `resources/js/lib/person-timeline.ts:23`).
- Panel osoby: przegląd, Rodzina `MarriageRow` (`family-tab.tsx:213-315`), Źródła „Małżeństwo z …” (`sources-tab.tsx:20-24`), typy `resources/js/types/person.ts:17, 50-66, 138`.
- Źródła: „Potwierdza” (`SourceController.php:88, 281-293`), dopasowanie `record_type === 'marriage'` (`resources/js/lib/source-matching.ts:17-20`).
- Usuwanie: `DeletePerson.php:29-31`, `delete-person-dialog.tsx:45-72`, `remove-marriage-dialog.tsx:28-53`, `MarriageController::destroy` (`:50-60`).
- Miejsca: `MergeLocality.php:55`, `DeleteLocality.php:23` (liczy zdarzenia).
- Testy/fixtury: `tests/Pest.php:54-70` (`attachMarriage`), `:252-257` (współrodzic bez ślubu), `WriteTargetsTest.php:97-155` (dokładne tabele zapisu), `MarriagesTest`, `TreeTest`, E2E `marriage-side-panel.spec.ts`, `tree-slot-spouse.spec.ts`, `tests/e2e/support/api.ts`; seedery `TreeViewDemoSeeder.php:132-217`, `AncestorsDemoSeeder.php:89`.

### D. Nazwiska: zapis, wyświetlanie, wyszukiwanie

- Walidacja (`app/Http/Requests/PersonStoreRequest.php:39-41, 60`): samo `maiden_name` bez `last_name` → błąd „Gdy znasz tylko jedno nazwisko…”. Dziedziczą: `PersonUpdateRequest`, `PersonSpouseStoreRequest`, `PersonChildStoreRequest`, `PersonParentStoreRequest`, `TreeProbandStoreRequest`.
- Zapis: `CreatePerson.php:47-53`, `UpdatePerson.php:55-61` — pełne zastąpienie: brak klucza = `null` / pusta lista (`PersonsTest.php:870`). Nowy klucz musi być zawsze w payloadzie (`resources/js/lib/person-payload.ts:122-184`, `PERSONAL_KEYS` w `overview-tab.tsx:47-53`) i w liście kluczy `WriteTargetsTest.php:283,357`.
- Wyświetlanie: `Person::displayName()` (`app/Models/Person.php:148-157`, „z d.”), `cardNameParts()` (`:162-180`, rodowe ?: nazwisko — decyzja `context/archive/2026-10-12-tree-card-name-one-line/plan-brief.md:22`), `hooks/use-initials.tsx:11` (obcina od „z d.”).
- Wyszukiwanie serwerowe `/persons`: wycina „z d.”, każde słowo AND, każde słowo OR po trzech kolumnach, ILIKE (wrażliwe na diakrytyki, `PersonsTest.php:86`) (`PersonController.php:42-53`). Indeks tylko `(tree_id, last_name, first_name)` (`database/migrations/2026_09_23_045722_create_people_table.php:23`); brak pg_trgm/unaccent.
- Pickery: `ListPersonCandidates` zwraca wszystkich bez filtra `{id, display_name, birth_date, gender}` (`app/Actions/Trees/ListPersonCandidates.php:17-31`) jako `Inertia::optional` w `TreeController.php:28,45` i `SourceController.php:58`; filtr kliencki `matchesFilter(display_name)` (`person-candidate-list.tsx:33-38`, użycie `:109`). Konsumenci: `tree/add-person-panel.tsx:222`, `persons/pick-person-dialog.tsx:34-75` (pochowani: `sources/buried-people-field.tsx:123`).
- Sortowanie/grupowanie listy po `last_name` (`resources/js/lib/persons-index.ts:75-111, 168-190`) — inne nazwiska nie powinny go zmieniać.

### E. Wzorzec listy: zamieszkania

- Tabela `residences` z `tree_id` i `person_id` (cascade) — `database/migrations/2026_10_06_110402_create_residences_from_residence_texts.php:20-31`; model `app/Models/Residence.php`, fabryka, `ResidenceResource`.
- Walidacja w `PersonUpdateRequest.php:27-61, 99-114`: `prepareForValidation` usuwa puste wiersze z zachowaniem indeksów, `max:50`, reguły per wiersz.
- Zapis: `UpdatePerson::replaceResidences` (`UpdatePerson.php:94-122`) — bez zmian nic nie robi, inaczej kasuje i odtwarza w kolejności payloadu, w transakcji osoby.
- Front: `residences-fields.tsx` (stabilne klucze `:40-81`, nazwy `residences.{i}.*` `:92`, „Usuń zamieszkanie N” `:141`, „Dodaj zamieszkanie” `:151-154`); karta w przeglądzie `overview-tab.tsx:325-363`; sekcja `section-editor.tsx:18, 59`.
- Testy: `PersonsTest.php:819-934`, `OwnershipBoundariesTest.php:364-382`, `WriteTargetsTest.php:340-369`, `person-payload.test.ts:110-160`, E2E `residence-history.spec.ts`.
- Różnica dla nazwisk: zamieszkania nie biorą udziału w tworzeniu osoby (`CreatePerson` ich nie zapisuje); jeśli inne nazwiska mają być w formularzu tworzenia (panel `?add=`), to odstępstwo od wzorca.

### F. Lokatory E2E, które mogą kolidować

- `getByLabel('Nazwisko', { exact: true })` — 5 specyfikacji (m.in. `tree-slot-spouse.spec.ts:44`, `persons-add-person-panel.spec.ts:28,40,82`): bezpieczne dla nowej etykiety.
- `getByRole('textbox', { name: 'Nazwisko rodowe' })` bez `exact` — 4 specyfikacje (`person-panel-unsaved-guard.spec.ts:40`, `residence-history.spec.ts:83`, `fact-places-from-gazetteer.spec.ts:83`, `person-panel-page-navigation-guard.spec.ts:25`): nowe pole nie może mieć etykiety zawierającej „Nazwisko rodowe”.

## Code References

- `app/Actions/Trees/BuildFamilySheet.php:77-117` — współrodzice bez ślubu jako unie `is_marriage: false`
- `app/Actions/Trees/BuildFamilySheet.php:199-240, 371-391` — `unmarriedParents()`, `pairMarriage()`
- `app/Actions/Relations/CreateMarriage.php:32-41` — „Ta para ma już małżeństwo.”
- `app/Http/Controllers/PersonSpouseController.php:19-42` — dodanie małżonka = utworzenie małżeństwa
- `app/Actions/Persons/MapsEventInput.php:98-107` — mapowanie pól zdarzenia (data, `locality_id`)
- `resources/js/components/marriages/marriage-panel.tsx:135-219` — opis, karta „Ślub”, źródła, dzieci
- `resources/js/components/persons/person-section-fields.tsx:256-268` — `MarriageFields`
- `resources/js/lib/person-payload.ts:122-211` — pełny payload osoby, `marriageSaveRequest`
- `resources/js/components/tree/union-node.tsx:37-50` — „ślub {rok}” / „ślub, data nieznana” / „bez ślubu”
- `app/Http/Controllers/PersonController.php:42-53` — wyszukiwanie po trzech kolumnach
- `app/Actions/Trees/ListPersonCandidates.php:17-31` — payload kandydatów pickera
- `resources/js/components/persons/person-candidate-list.tsx:33-38` — filtr po `display_name`
- `app/Models/Person.php:148-180` — `displayName()`, `cardNameParts()`
- `app/Actions/Persons/UpdatePerson.php:94-122` — `replaceResidences` (wzorzec listy)
- `context/foundation/prd.md:239-240` — non-goals: świadkowie, wiele nazwisk po mężach

## Architecture Insights

- **Para nie jest bytem.** Związek istnieje jako zdarzenie Marriage albo jako wniosek z `parent_child_relations`. Każde „nadanie tożsamości” parze bez ślubu to zmiana modelu, nie pola.
- **Forma ślubu to atrybut zdarzenia**, tak jak data i miejsce — mieści się w istniejącej ścieżce `MarriageStore/UpdateRequest → CreateMarriage/UpdateMarriage → EventResource → MarriageFields`, bez nowych tras.
- **Dwa wyszukiwania muszą zostać spójne:** serwer (`/persons`) i klient (pickery). Wspólna reguła dopasowania jest dziś zduplikowana w PHP i TS; inne nazwiska trzeba dodać w obu.
- **Pełne zastąpienie przy zapisie osoby** wymusza, by nowa lista była zawsze w payloadzie każdej sekcji.
- Rozbieżność środowiska: deweloperska baza ma kolumnę `confidence` na `events` i `event_source`, której nie tworzy żadna migracja w repo (raport agenta z Boost `database-schema`) — warto sprawdzić przed migracją na `events`.

## Historical Context (from prior changes)

- `context/archive/2026-09-25-marriage-with-shared-source/plan.md:22,32,36,43,47` — małżeństwo = zdarzenie + 2 uczestników, bez tabeli małżeństw; „dwa małżeństwa tej samej pary (cywilny + kościelny) — jedno na parę, skany na jednym zdarzeniu”; typ małżeństwa poza zakresem. **Nadal obowiązuje** (`CreateMarriage.php:32-41`).
- `context/archive/2026-09-28-tree-spouse-existing-or-new/plan.md:12,38,69` — współrodzic bez ślubu jako unia „bez ślubu”, przycisk „Połącz jako małżeństwo”. **Nadal obowiązuje** (`BuildFamilySheet.php:77-117`).
- `context/archive/2026-10-09-marriage-side-panel/plan.md:156,364,456` — `marriage_id` null dla pary bez ślubu; panel tylko dla małżeństwa; usunięcie małżeństwa zostawia unię „bez ślubu” z dziećmi. Brief: typ małżeństwa i zmiany schematu poza zakresem. **Nadal obowiązuje.**
- `context/archive/2026-10-12-tree-lines-union-node/plan.md:60,68,96` — węzeł rodziców tylko przy Marriage; test „dwa śluby tej samej pary dają rok najwcześniejszego” (tolerancja odczytu, mimo blokady zapisu).
- `context/archive/2026-10-05-maiden-name-and-age/plan-brief.md` — „z d.” w `displayName()`, wyszukiwanie po trzech kolumnach, samo rodowe = błąd; „wiele nazwisk po mężach” poza zakresem. **Nadal obowiązuje.**
- `context/archive/2026-10-12-tree-card-name-one-line/plan-brief.md:22` — karta drzewa: imię + rodowe ?: nazwisko.
- `context/archive/2026-09-25-person-search/plan.md:18,38` — ILIKE, bez unaccent i wariantów pisowni (non-goal PRD v1: `context/foundation/archive/prd-v1.md:149`).
- `context/foundation/archive/prd-v1.md:87` — dzieci nieślubne: dozwolony jeden znany rodzic, bez rozróżnienia biologiczny/przybrany.
- `context/archive/2026-10-21-parish-or-office/plan.md:42,72` — `ParishKind` (parafia / USC / okręg bóżniczy / inny), `Religion` niezależne od rodzaju; źródło → parafia/urząd, nie małżeństwo → parafia.

## Related Research

- `context/archive/2026-10-20-places-review/research.md` — historia prowadzących akta (proboszcz jako urzędnik stanu cywilnego 1826, Standesamt 1874, USC 1946)
- `context/archive/2026-10-21-parish-or-office/research.md`
- `context/archive/2026-10-05-maiden-name-and-age/research.md`
- `context/archive/2026-10-06-residence-history/` (plan wzorca listy)

## Open Questions

Decyzje autora (produktowe), od których zależy zakres planu:

1. **Para bez ślubu** — czy wystarcza obecne „bez ślubu” wyliczane ze wspólnych dzieci, czy potrzebna jest zapisana para bez ślubu (bez dzieci, z panelem/źródłami)? Druga opcja to zmiana modelu (sekcje B–C).
2. **Wartości formy ślubu** i gdzie je widać: tylko panel małżeństwa, czy też etykieta węzła na drzewie („ślub cywilny 1890”), oś czasu, Rodzina.
3. **Podpowiedź ze źródeł** (rodzaj prowadzącego akta) — tak/nie.
4. **Inne nazwiska** — rodzaje (stała lista po mężu / wariant pisowni / vel czy wolny tekst), czy mają się gdzieś wyświetlać (Przegląd? lista osób?), czy wchodzą do formularza tworzenia osoby, czy wyszukiwanie w pickerach też je obejmuje.
5. **PRD** — zmiana non-goal `prd.md:240` (i ewentualnie wariantów pisowni) na wymaganie.

Nie zweryfikowane osobiście (raport agenta): kolumna `confidence` w bazie deweloperskiej; kotwice frontendu w sekcjach B–C (`family-sheet.tsx`, `family-tab.tsx`, `source-matching.ts`) podane przez agenta, nie czytane w całości.

## Decyzje autora (2026-10-21)

1. **Para bez ślubu — bez zmian.** Zostaje obecne „bez ślubu” wyliczane ze wspólnych dzieci (`BuildFamilySheet.php:77-117`). Model pary się nie zmienia; zapisana para bez ślubu (bez dzieci, z panelem) to ewentualna osobna zmiana. Pytanie otwarte 1 — zamknięte.
2. **Forma ślubu widoczna w panelu małżeństwa i w Rodzinie**: karta „Ślub” w panelu małżeństwa, karta małżeństwa w przeglądzie osoby (`overview-tab.tsx:270-286`) i wiersz małżeństwa w zakładce Rodzina (`family-tab.tsx:213-315`). Drzewo (`union-node.tsx`) i oś czasu bez zmian. Wartości: cywilny / wyznaniowy / cywilno-wyznaniowy / nieznana (domyślna). Pytanie otwarte 2 — zamknięte.
3. **Bez podpowiedzi ze źródeł.** Autor wybiera formę ręcznie; rodzaj prowadzącego akta nie jest używany. Pytanie otwarte 3 — zamknięte.

4. **Inne nazwiska — poza zakresem.** Autor wybiera najprostsze rozwiązanie: osoba ma nadal *Nazwisko* i *Nazwisko rodowe* („z d.” widoczne jak dziś, `Person.php:148-157`); lista innych nazwisk (po mężu / wariant / vel) nie powstaje, „jak będzie trzeba, to się potem poprawi”. Sekcje D–F zostają jako materiał na ewentualną przyszłą zmianę. Pytanie otwarte 4 — zamknięte.
5. **PRD:** non-goal „Brak wielu nazwisk po kolejnych mężach” (`prd.md:240`) zostaje bez zmian. Forma ślubu rozszerza FR-017 (`prd.md:147`: panel małżeństwa = data, miejsce, źródło, dzieci) — plan powinien dopisać do niej formę ślubu. Pytanie otwarte 5 — zamknięte.

**Zakres zmiany po decyzjach:** wyłącznie forma ślubu (cywilny / wyznaniowy / cywilno-wyznaniowy / nieznana) jako atrybut zdarzenia Marriage, edytowana i widoczna w karcie „Ślub” (panel małżeństwa i przegląd osoby) oraz w wierszu małżeństwa w Rodzinie (sekcja A). Identyfikator zmiany `marriage-form-and-other-surnames` zawiera nieaktualną już część „other-surnames”.
