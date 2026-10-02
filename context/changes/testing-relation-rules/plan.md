# Reguły relacji (ryzyko #5) — plan implementacji

## Overview

Faza 3 wdrożenia testów (`context/foundation/test-plan.md` §3), część dla ryzyka #5: reguł relacji nie da się obejść poza filtrem wyboru. Serwer odrzuca trzeciego rodzica, cykl przodków i niezgodną płeć na każdej ścieżce zapisu. Plan zamyka dwie luki w kodzie wybrane przez autora (`role` wymagane w `parent-child-relations.store`, strażnik zmiany płci osoby będącej rodzicem), uzupełnia brakujące komórki macierzy „reguła × ścieżka” w `tests/Feature/ParentChildRelationsTest.php`, zaostrza asercje istniejących testów odrzucenia (dokładny komunikat i `ownershipSnapshot()` bez zmian) i zapisuje wzorzec w cookbooku §6.5.

## Current State Analysis

Źródło: `context/changes/testing-relation-rules/research.md` (badanie i decyzje autora z 2026-10-02).

- **Jeden punkt wejścia.** 4 trasy tworzące relację rodzic–dziecko (`parent-child-relations.store`, `persons.parents.store`, `persons.children.attach`, `persons.children.store`) kończą w `CreateParentChildRelation::handle` (`app/Actions/Relations/CreateParentChildRelation.php:20-53`). Tam działają: duplikat (:30), maks. dwoje rodziców (:34), cykl (:38), druga osoba tej samej znanej płci (:42, `guardSameSexParent` :62-80). Błąd to `ValidationException` na `relation`.
- **Reguła slotu żyje w Requestach.** `PersonParentStoreRequest.php:31,38-52` wymaga `role` i sprawdza płeć nowej osoby (klucz `gender`). `ParentChildRelationStoreRequest.php:23` ma `role` nullable — bez `role` kontrola slotu (:33-47) jest pomijana.
- **Edycja osoby łamie niezmiennik po fakcie.** `UpdatePerson.php:49` ustawia `gender` wprost; ani akcja, ani `PersonUpdateRequest` nie patrzą na relacje. Skutek: dziecko z dwoma ojcami. Archiwum odnotowało to jako świadomy brak reguły (`context/archive/2026-09-28-tree-parent-slot-existing-or-new/plan.md:40`).
- **Relacja nie przechowuje roli** (tylko `parent_id`, `child_id`). Slot ojca/matki na drzewie wynika z płci rodzica, więc reguła przy zmianie płci musi opierać się na grafie.
- **Macierz reguła × trasa** (stan testów w `tests/Feature/ParentChildRelationsTest.php`; „klucz” = asercja tylko na kluczu `relation`, bez komunikatu):

| Reguła | `parent-child-relations.store` | `persons.parents.store` | `persons.children.attach` | `persons.children.store` |
|---|---|---|---|---|
| trzeci rodzic | :33 (klucz) | :396 (klucz) | :465 para ✓; **samotny rodzic — brak** | n/d (nowe dziecko nie ma rodziców) |
| cykl | :71, :84 (klucz) | n/d (nowa osoba nie ma potomków) | :509 osoba z adresu (klucz); **przez `other_parent_id` — brak** | n/d |
| ta sama płeć | :227 ✓ | :382 ✓ | :496 ✓, :674 ✓ | :623 ✓ |
| płeć × slot | :262 ✓ (tylko z `role`) | :365 ✓ | n/d | n/d |
| duplikat | :59 (klucz) | n/d | :450 ✓ | n/d |

- **Wyrocznia komunikatów**: `context/archive/2026-09-25-parent-child-relations/plan.md:92-94` („Ta relacja już istnieje.”, „Ta osoba ma już dwoje rodziców.”, „Ta relacja utworzyłaby cykl w drzewie.”), PRD `context/foundation/prd.md:103` („komunikat mówi dlaczego”).

## Desired End State

- `parent-child-relations.store` bez `role` kończy się błędem walidacji na `role` i niczego nie zapisuje; `role=parent` zachowuje się jak dziś.
- Zmiana płci osoby na znaną płeć X jest odrzucana błędem na `gender`, gdy któreś jej dziecko ma innego rodzica płci X. Zmiana na „nieznana”, zmiana u samotnego rodzica i edycja bez zmiany płci przechodzą.
- Każda stosowalna (nie „n/d”) komórka macierzy ma test, który sprawdza dokładny komunikat i dowodzi braku zapisu przez `ownershipSnapshot()`.
- `test-plan.md` §6.5 „Reguły relacji” opisuje wzorzec zamiast TBD.

Weryfikacja: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/PersonsTest.php` zielone; ręczna kontrola mutacji (wyłączenie strażnika lub reguły `required` → odpowiedni test czerwony).

### Key Discoveries:

- Jedyny wywołujący `parent-child-relations.store` z UI (`resources/js/components/tree/parent-slot-dialog.tsx:85-91`) zawsze wysyła `role` — wymaganie `role` nie zmienia frontendu. W testach 22 z 24 wywołań tej trasy w `ParentChildRelationsTest.php` nie wysyła `role`.
- Komunikat braku `role` daje tłumaczenie: „Pole rola jest wymagane.” (`lang/pl/validation.php:135,254`).
- Formularz osoby wyświetla błąd na `gender` (`resources/js/components/persons/person-section-fields.tsx:112`), więc odrzucenie zmiany płci na tym kluczu będzie widoczne bez zmian w UI.
- `UpdatePerson` ma jednego wywołującego: `PersonController::update` (`app/Http/Controllers/PersonController.php:109-116`).
- `ParentChildGraph::forTree` + `childrenOf` / `parentsOf` (`app/Support/ParentChildGraph.php`) wystarczą, by znaleźć współrodziców; płeć czytamy świeżo z bazy, jak `guardSameSexParent` (`CreateParentChildRelation.php:56-64`).
- `ownershipSnapshot()` (`tests/Pest.php:109-141`) haszuje każdą tabelę poza `migrations` i listę plików dysku `local`; wymaga wcześniejszego `Storage::fake('local')`.
- `AttachChildToParents` wywołuje akcję raz na brakującego rodzica w jednej transakcji (`app/Actions/Relations/AttachChildToParents.php:23-48`) — przy cyklu przez `other_parent_id` pierwszy link (osoba z adresu) przechodzi, drugi pada, a transakcja musi wycofać pierwszy.

## What We're NOT Doing

- **Kolumna roli w `parent_child_relations`** — slot wynika z płci; samotny „ojciec” zmieniony na kobietę staje się na drzewie matką, co jest spójne (decyzja planu).
- **Sprzątanie istniejących par tej samej płci** — strażnik działa tylko przy zmianie płci; dane historyczne zostają (archiwum `2026-09-28-tree-parent-slot-existing-or-new`).
- **`role=parent` z dowolną płcią, także `null`** — zostaje bez zmian (research Decisions #3, test :352).
- **Małżeństwa** (pokrewieństwo, płeć małżonków) — poza zakresem #5 (research Decisions #3).
- **Strażnik „rodzic ≠ dziecko” w akcji i CHECK w DB** — obrona w głąb, dziś nieeksploatowalna (research Decisions #3).
- **Test współbieżności** (cykl trzech osób trzema żądaniami, zmiana płci równolegle z dodaniem rodzica) — nieudowodniony wyścig, poza zakresem (research Decisions #3).
- **Nowy plik z całą macierzą** — dublowałby ~8 istniejących testów; uzupełniamy luki w istniejącym pliku (decyzja planu).
- **Testowanie mutacyjne narzędziem** (Infection dla PHP nie jest zależnością projektu) — zamiast tego ręczna kontrola mutacji w kryteriach faz.
- **Reguły dat** (rodzic starszy od dziecka) — PRD o nich nie mówi.

## Implementation Approach

Kolejność według łańcucha lekcji: najpierw dwie zmiany kodu test-first (fazy 1–2, każdą da się nazwać jednym czerwonym testem), potem siatka na istniejący kod (faza 3, testy charakteryzujące — oczekiwany wynik zielony; czerwony oznacza rozbieżność z wyrocznią, wtedy zatrzymaj się i zapytaj autora, zamiast przepisywać oczekiwanie z kodu), na końcu cookbook (faza 4). Warstwa: Pest feature na prawdziwym PostgreSQL — graf, unique i transakcje to SQL, stub by kłamał (test-plan §2, #5).

Wspólny wzorzec testu odrzucenia w tym planie: arrange → `Storage::fake('local')` → `$before = ownershipSnapshot()` → żądanie → `assertSessionHasErrors([klucz => dokładny komunikat])` → `expect(ownershipSnapshot())->toBe($before)`.

## Faza 1: `role` wymagane w `parent-child-relations.store`

Tryb: `/10x-tdd`. Pierwszy czerwony test: „żądanie bez `role` kończy się błędem na `role` i niczego nie zapisuje”.

### Overview

Zamyka obejście kontroli slotu przez pominięcie `role` (research Decisions #2). Ojciec = mężczyzna, matka = kobieta — spójnie z `persons.parents.store`.

### Changes Required:

#### 1. Reguła walidacji

**File**: `app/Http/Requests/ParentChildRelationStoreRequest.php`

**Intent**: `role` staje się wymagane; dozwolone wartości i kontrola slotu w `withValidator` bez zmian.

**Contract**: `'role' => ['required', Rule::in(['father', 'mother', 'parent'])]`. Błąd na `role` z domyślnym komunikatem „Pole rola jest wymagane.”.

#### 2. Testy

**File**: `tests/Feature/ParentChildRelationsTest.php`

**Intent**: Nowy test braku `role` (z `ownershipSnapshot()`); każde istniejące wywołanie `parent-child-relations.store` bez `role` dostaje `'role' => 'parent'`, żeby nadal sprawdzało regułę akcji, a nie walidację.

**Contract**: test `a relation without a role is rejected on role and writes nothing`. Testy :262 (`a slot role must match…`) i :291 (`an unknown role…`) zostają bez zmian.

### Success Criteria:

#### Automated Verification:

- Nowy test braku `role` był czerwony przed zmianą reguły i jest zielony po niej
- `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php` zielone
- `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu

#### Manual Verification:

- Wybór istniejącej osoby w slocie ojca/matki/rodzica na drzewie nadal tworzy relację (UI wysyła `role`)

**Implementation Note**: Po zielonych kryteriach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Strażnik zmiany płci rodzica

Tryb: `/10x-tdd`. Pierwszy czerwony test: „zmiana płci na mężczyznę osoby, której dziecko ma już ojca, kończy się błędem na `gender` i niczego nie zmienia”.

### Overview

Zamyka obejście „dwóch ojców / dwóch matek” przez edycję płci po podpięciu (research Decisions #1). Reguła jest lustrem `guardSameSexParent` z akcji tworzenia relacji, wyrażonym przez graf.

### Changes Required:

#### 1. Akcja edycji osoby

**File**: `app/Actions/Persons/UpdatePerson.php`

**Intent**: Wewnątrz istniejącej transakcji, przed zapisem: gdy nowa płeć jest znana i różna od zapisanej (czytanej świeżo z bazy z `lockForUpdate` na wierszu osoby), sprawdź każde dziecko osoby z `ParentChildGraph::forTree($person->tree)`; jeśli któreś ma innego rodzica (≠ ta osoba) o nowej płci, rzuć `ValidationException` na `gender`. Reguła grafu w akcji, nie w Requeście — zgodnie z wzorcem „reguły grafu żyją w akcji” (research, Architecture Insights).

**Contract**:
- Wyzwalacz: `nowa ≠ null` i `nowa ≠ zapisana`. Zmiana na `null` i edycja bez zmiany płci nigdy nie sprawdzają relacji (starsze niespójne pary nie blokują edycji imienia).
- Komunikat: mężczyzna → „Ta osoba jest rodzicem dziecka, które ma już ojca.”; kobieta → „Ta osoba jest rodzicem dziecka, które ma już matkę.” (UI string, polski).
- Odrzucenie nie zmienia niczego: ani osoby, ani wydarzeń urodzenia/zgonu (zapis wydarzeń stoi po kontroli albo transakcja go wycofuje).

#### 2. Testy

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Odrzucenia przez `persons.update` z `ownershipSnapshot()` i dokładnym komunikatem oraz przypadki dozwolone z asercją na zapisanej płci.

**Contract**:
- `changing the sex of a parent is rejected when their child already has a parent of that sex` — dataset: `nieznana → mężczyzna, dziecko ma ojca`; `kobieta → mężczyzna, dziecko ma ojca`; `mężczyzna → kobieta, dziecko ma matkę`; `konflikt tylko u jednego z dwojga dzieci`.
- `changing the sex of a parent is allowed when no child gets two parents of the same sex` — dataset: `samotny rodzic mężczyzna → kobieta`; `mężczyzna → nieznana przy dziecku z matką`; `współrodzic o nieznanej płci`.
- `editing a parent without changing their sex is allowed even when their child already has two parents of that sex` — para tej samej płci wstawiona przez `linkParent`, zmiana imienia przechodzi.

### Success Criteria:

#### Automated Verification:

- Pierwszy test odrzucenia był czerwony przed zmianą akcji i jest zielony po niej
- `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php` zielone
- `vendor/bin/sail composer types:check` (Larastan) bez nowych błędów
- `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu

#### Manual Verification:

- W modalu osoby zmiana płci ojca na „Mężczyzna” u drugiego rodzica dziecka pokazuje komunikat pod polem „Płeć”, a płeć się nie zmienia
- Ręczna kontrola mutacji: zakomentowanie warunku strażnika czerwieni test odrzucenia

**Implementation Note**: Po zielonych kryteriach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Luki macierzy i zaostrzone asercje

Tryb: `/10x-implement`. Testy charakteryzujące istniejący kod — oczekiwany wynik zielony. Czerwony test = rozbieżność z wyrocznią: zatrzymaj się i zapytaj autora.

### Overview

Każda komórka macierzy z „Current State Analysis” dostaje test z dokładnym komunikatem i dowodem braku zapisu.

### Changes Required:

#### 1. Brakujące komórki

**File**: `tests/Feature/ParentChildRelationsTest.php`

**Intent**: Dwa nowe testy dla `persons.children.attach`.

**Contract**:
- `attaching a child who is an ancestor of the other parent is rejected as a cycle and the first link is rolled back` — `X` jest rodzicem partnera `P`; `attach(focus, child_id=X, other_parent_id=P)` → „Ta relacja utworzyłaby cykl w drzewie.”, snapshot bez zmian (link focus→X wycofany).
- `a lone parent cannot attach a child who already has two parents` — dziecko z dwojgiem rodziców, `attach(focus, child_id)` bez `other_parent_id` → „Ta osoba ma już dwoje rodziców.”, snapshot bez zmian.

#### 2. Zaostrzenie istniejących testów odrzucenia

**File**: `tests/Feature/ParentChildRelationsTest.php`

**Intent**: Asercja na kluczu `relation` zamienia się na dokładny komunikat z wyroczni (archiwum `2026-09-25-parent-child-relations/plan.md:92-94`), a `assertDatabaseCount` na `ownershipSnapshot()` przed i po żądaniu. Nazwy testów bez zmian.

**Contract**: :33 i :396 → „Ta osoba ma już dwoje rodziców.”; :59 → „Ta relacja już istnieje.”; :71, :84, :509 → „Ta relacja utworzyłaby cykl w drzewie.”. Snapshot robiony po arrange (także po żądaniach przygotowujących), tuż przed odrzucanym żądaniem. Testy już sprawdzające komunikat (:227, :365, :382, :450, :465, :496, :623, :674) dostają tylko snapshot, jeśli dziś liczą jedną tabelę.

### Success Criteria:

#### Automated Verification:

- `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php` zielone
- `grep -c "assertSessionHasErrors('relation')" tests/Feature/ParentChildRelationsTest.php` zwraca 0
- `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu

#### Manual Verification:

- Ręczna kontrola mutacji: usunięcie warunku cyklu (:38) w `CreateParentChildRelation` czerwieni testy cyklu na `parent-child-relations.store` i `persons.children.attach` (oba warianty)

**Implementation Note**: Po zielonych kryteriach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Cookbook §6.5 i stan §3

Tryb: `/10x-implement`.

### Overview

Zapisuje wzorzec testu reguł relacji w `test-plan.md`, żeby nowa ścieżka zapisu relacji dostała testy bez ponownego badania.

### Changes Required:

#### 1. Cookbook

**File**: `context/foundation/test-plan.md` §6.5, podsekcja „Reguły relacji (ryzyko #5)”

**Intent**: Zastąpić TBD wzorcem z tej zmiany.

**Contract**: format jak część „Własność”: Location (`tests/Feature/ParentChildRelationsTest.php`, zmiana płci w `tests/Feature/PersonsTest.php`), Reference test, Run locally, Wyrocznia (komunikaty z archiwum; strażnik płci; `role` wymagane), macierz reguła × trasa z komórkami n/d i ich uzasadnieniem, Pułapki (komórki n/d wynikają z nowej osoby bez relacji; przy `children.attach` z parą pierwszy link musi być wycofany; snapshot po arrange; `Storage::fake('local')` przed snapshotem; nowe wywołania `parent-child-relations.store` w testach wymagają `role`), Nowa ścieżka tworząca relację (musi przejść przez `CreateParentChildRelation`; dopisz jej kolumnę w macierzy i test na każdą stosowalną regułę).

#### 2. Wiersz fazy 3 w §3

**File**: `context/foundation/test-plan.md` §3

**Intent**: Odnotować drugi folder zmiany fazy 3.

**Contract**: wiersz `| 3 | Granice własności i reguły relacji |`: Change folder z obiema zmianami (`testing-ownership-boundaries` zarchiwizowana, `context/changes/testing-relation-rules/`), Status bez cofania. Bez zmian w §1–§2, §4–§5 (strategia należy do Lekcji 1).

### Success Criteria:

#### Automated Verification:

- §6.5 nie zawiera już „TBD” dla reguł relacji: `grep -n "Reguły relacji" -A6 context/foundation/test-plan.md`
- `vendor/bin/sail artisan test --compact tests/Feature` zielone

#### Manual Verification:

- Autor czyta §6.5 i potwierdza, że wystarcza do napisania testów reguł dla nowej ścieżki tworzącej relację bez czytania tego planu

---

## Testing Strategy

### Integration Tests (Pest feature, PostgreSQL):

- Faza 1: brak `role` → błąd na `role`, brak zapisu.
- Faza 2: macierz zmian płci (4 odrzucenia, 3 dozwolone, 1 edycja bez zmiany płci przy starszej parze).
- Faza 3: 2 nowe komórki macierzy + zaostrzone 6 testów (komunikat) i snapshot we wszystkich testach odrzucenia relacji.

Przypadki brzegowe wynikające z ryzyka: nieznana płeć (nigdy nie koliduje), konflikt tylko u jednego z kilkorga dzieci, wycofanie pierwszego linku w transakcji `AttachChildToParents`, starsze niespójne dane.

### Manual Testing Steps:

1. Na drzewie wybierz istniejącą osobę w slocie ojca — relacja powstaje.
2. W modalu drugiego rodzica dziecka, które ma ojca, zmień płeć na „Mężczyzna” — komunikat pod polem, płeć bez zmian.
3. U samotnego rodzica zmień płeć — zapis przechodzi, drzewo przenosi go do drugiego slotu.

## Migration Notes

Brak migracji. Istniejące pary tej samej płci zostają; strażnik nie blokuje ich edycji, dopóki płeć się nie zmienia.

## References

- Research: `context/changes/testing-relation-rules/research.md`
- Strategia: `context/foundation/test-plan.md` §2 (ryzyko #5), §3 (faza 3), §6.5
- Wyrocznia komunikatów: `context/archive/2026-09-25-parent-child-relations/plan.md:92-94`
- PRD: `context/foundation/prd.md:64,101,103,112`; `context/foundation/prd-v3.md:122-123`
- Wzorzec snapshotu i cookbooka: `context/archive/2026-10-02-testing-ownership-boundaries/plan.md`, `tests/Pest.php:109-141`
- Punkt wejścia reguł: `app/Actions/Relations/CreateParentChildRelation.php:20-80`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: `role` wymagane w `parent-child-relations.store`

#### Automated

- [x] 1.1 Nowy test braku `role` był czerwony przed zmianą reguły i jest zielony po niej — 13e4eab
- [x] 1.2 `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php` zielone — 13e4eab
- [x] 1.3 `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu — 13e4eab

#### Manual

- [x] 1.4 Wybór istniejącej osoby w slocie ojca/matki/rodzica na drzewie nadal tworzy relację (UI wysyła `role`) — 13e4eab

### Phase 2: Strażnik zmiany płci rodzica

#### Automated

- [x] 2.1 Pierwszy test odrzucenia był czerwony przed zmianą akcji i jest zielony po niej — b4ff45f
- [x] 2.2 `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php` zielone — b4ff45f
- [x] 2.3 `vendor/bin/sail composer types:check` (Larastan) bez nowych błędów — b4ff45f
- [x] 2.4 `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu — b4ff45f

#### Manual

- [x] 2.5 W modalu osoby zmiana płci ojca na „Mężczyzna” u drugiego rodzica dziecka pokazuje komunikat pod polem „Płeć”, a płeć się nie zmienia — b4ff45f
- [x] 2.6 Ręczna kontrola mutacji: zakomentowanie warunku strażnika czerwieni test odrzucenia — b4ff45f

### Phase 3: Luki macierzy i zaostrzone asercje

#### Automated

- [x] 3.1 `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php` zielone — f91771b
- [x] 3.2 `grep -c "assertSessionHasErrors('relation')" tests/Feature/ParentChildRelationsTest.php` zwraca 0 — f91771b
- [x] 3.3 `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu — f91771b

#### Manual

- [x] 3.4 Ręczna kontrola mutacji: usunięcie warunku cyklu (:38) w `CreateParentChildRelation` czerwieni testy cyklu na `parent-child-relations.store` i `persons.children.attach` (oba warianty) — f91771b

### Phase 4: Cookbook §6.5 i stan §3

#### Automated

- [x] 4.1 §6.5 nie zawiera już „TBD” dla reguł relacji: `grep -n "Reguły relacji" -A6 context/foundation/test-plan.md`
- [x] 4.2 `vendor/bin/sail artisan test --compact tests/Feature` zielone

#### Manual

- [x] 4.3 Autor czyta §6.5 i potwierdza, że wystarcza do napisania testów reguł dla nowej ścieżki tworzącej relację bez czytania tego planu
