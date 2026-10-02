---
date: 2026-10-02T13:01:18+0200
researcher: Claude (Sonnet 5.5)
git_commit: f6b6d17144410b77782e18b4481403ed2c377c07
branch: main
repository: root-keeper
topic: "Czy serwer egzekwuje reguły relacji (trzeci rodzic, cykl, płeć w slocie) na każdej ścieżce zapisu — ryzyko #5"
tags: [research, relations, parent-child, marriages, risk-5]
status: complete
last_updated: 2026-10-02
last_updated_by: Claude (Sonnet 5.5)
---

# Research: reguły relacji na każdej ścieżce zapisu (ryzyko #5)

**Date**: 2026-10-02T13:01:18+0200
**Git Commit**: f6b6d17 · **Branch**: main · **Repository**: root-keeper

## Research Question

Test-plan §2, ryzyko #5: „Reguły relacji da się obejść poza filtrem wyboru — trzeci rodzic, cykl przodków, kobieta w slocie ojca”. Dowód ochrony (§2 Risk Response): serwer odrzuca trzeciego rodzica, cykl i niezgodną płeć na każdej ścieżce zapisu, niezależnie od filtra w UI. Pytania: (1) gdzie reguły są egzekwowane, (2) czy każda ścieżka przechodzi tę samą walidację, (3) co już testują testy, (4) jaka jest wyrocznia ze źródeł.

## Summary

- **Ta sprawdzona ścieżka**: 4 trasy tworzące relację rodzic–dziecko (`parent-child-relations.store`, `persons.parents.store`, `persons.children.attach`, `persons.children.store`) kończą w jednym miejscu, `CreateParentChildRelation::handle` (`app/Actions/Relations/CreateParentChildRelation.php:20-53`), w transakcji z `lockForUpdate` na obu osobach. Tam egzekwowane są: duplikat (:30), maks. dwoje rodziców (:34), cykl (:38), ta sama znana płeć drugiego rodzica (:42). Jedyny INSERT do `parent_child_relations` w kodzie żądań: :45. Błąd = `ValidationException` na kluczu `relation` (422).
- **Reguły nie są tylko w UI** — dla tych 4 tras nie znaleziono reguły egzekwowanej wyłącznie w `resources/js` (komentarz `parent-slot-dialog.tsx:41-42`: „server stays the authority”).
- **Luki względem wyroczni (kandydaci na realną rozbieżność, do decyzji autora)**:
  1. **Zmiana płci osoby już będącej rodzicem nie jest sprawdzana** — `UpdatePerson.php:49` ustawia `gender` wprost, `PersonUpdateRequest` nie zagląda do relacji. Efekt: dziecko z dwoma ojcami / „ojciec” kobieta. Archiwum zapisuje to jako świadomy brak reguły (`context/archive/2026-09-28-tree-parent-slot-existing-or-new/plan.md:40`).
  2. **`role` jest opcjonalne w `parent-child-relations.store`** (`ParentChildRelationStoreRequest.php:23,39-41`) — bez `role` kontrola „płeć pasuje do slotu” jest pomijana; zostaje tylko kontrola „druga osoba tej samej płci” z akcji.
  3. **`persons.parents.store` z `role=parent` przyjmuje dowolną płeć, także `null`** (test `ParentChildRelationsTest.php:352`) — zgodne z istniejącym testem, ale PRD mówi o płci tylko dla slotu „ojciec/matka”.
  4. **Brak strażnika „rodzic ≠ dziecko” w akcji** — tylko w 3 Form Requestach (`different:parent_id` w `ParentChildRelationStoreRequest.php:22`, `notIn` w `PersonChildAttachRequest` i `PersonChildStoreRequest`). Cykl (:38) tego nie łapie, bo `descendantsOf(child)` nie zawiera samego dziecka. Dziś nieeksploatowalne (każdy wywołujący przechodzi przez Request), jak „obrona w głąb” z `testing-ownership-boundaries`.
  5. **Małżeństwa**: serwer nie sprawdza pokrewieństwa ani płci małżonków (tylko: nie ze sobą, nie duplikat pary, to samo drzewo — `CreateMarriage.php:32-41`, `MarriageStoreRequest.php:24`). Archiwum: filtr tylko w UI, „accepted” (`context/archive/2026-09-28-tree-spouse-existing-or-new/plan.md:46,51`; impl-review F1 `PENDING`).
- **Pokrycie testami**: dla każdej reguły rodzic–dziecko jest test na co najmniej jednej trasie z asercją „nic nie zapisano” (`ParentChildRelationsTest.php`: :33 trzeci rodzic, :71/:84 cykl, :227 ta sama płeć, :262 slot×płeć, :365/:382/:396 przez `persons.parents.store`, :465 przez `children.attach`). **Niepokryte** (wg inwentarza agenta, niezweryfikowane osobno): cykl przez `other_parent_id` w `children.attach`; trzeci rodzic i cykl dla drugiego linku z `children.attach`/`children.store` w pełnej macierzy; dokładny komunikat trzeciego rodzica/cyklu/duplikatu (asercja tylko na kluczu `relation`); współbieżność; zmiana płci po podpięciu (`PersonsTest.php:617` sprawdza tylko zapis).

## Detailed Findings

### 1. Jeden punkt wejścia dla relacji rodzic–dziecko

- Łańcuchy wywołań: `PersonParentController::store → CreateParentForPerson → CreateParentChildRelation`; `PersonChildController::attach → AttachChildToParents → CreateParentChildRelation` (po jednym wywołaniu na brakującego rodzica, w jednej transakcji, blokada wszystkich osób z góry w kolejności id); `PersonChildController::store → CreateChildForParents → AttachChildToParents`; `ParentChildRelationController::store` → akcja wprost (`:17-29`).
- Odrzucenie rolluje także nowo utworzoną osobę (testy :365, :382, :396, :623).
- DB: tylko `unique(parent_id, child_id)` (migracja `2026_09_25_061809_create_parent_child_relations_table.php:21`); brak CHECK (`parent_id <> child_id`), brak ograniczenia maks. 2 rodziców, brak ograniczenia cyklu, brak ograniczenia wspólnego drzewa rodzica i dziecka.

### 2. Reguła „płeć pasuje do slotu” — zależna od ścieżki

- `persons.parents.store`: `role` wymagane; dla `father`/`mother` płeć nowej osoby musi pasować (`PersonParentStoreRequest.php:38-52`, klucz `gender`, „Płeć musi pasować do miejsca na drzewie.”); `role=parent` bez wymagań.
- `parent-child-relations.store`: `role` nullable; dla `father`/`mother` istniejący rodzic musi mieć dokładnie tę płeć, `null` = odrzucone (`ParentChildRelationStoreRequest.php:33-47`, klucz `relation`). Pominięcie `role` omija kontrolę.
- `persons.children.attach` / `persons.children.store`: brak koncepcji slotu; działa tylko strażnik „ta sama płeć” w akcji. Nieznana płeć nigdy nie koliduje (:66).

### 3. Edycja osoby może złamać niezmiennik po fakcie

- `PersonController::update → UpdatePerson` (`UpdatePerson.php:49`); brak odwołania do `parent_child_relations`. `BuildFamilySheet` (`app/Actions/Trees/BuildFamilySheet.php:274,302-312`) toleruje to przy wyświetlaniu (wg agenta; nie czytałem). Brak reguł dat (rodzic starszy od dziecka itp.) — przejrzano `app/`, nic nie znaleziono.

### 4. Wyścigi

- Blokady dotyczą wierszy `people` obu końców; graf do sprawdzenia cyklu jest czytany z całego drzewa (`ParentChildGraph::forTree`, :28). Maks. 2 rodziców jest chronione blokadą wiersza dziecka. Cykl trzech osób wstawiany jednocześnie trzema żądaniami (A→B, B→C, C→A) może teoretycznie przejść przy READ COMMITTED (wniosek agenta, **niezweryfikowany**; testy nie sprawdzają blokad).

## Code References

- `app/Actions/Relations/CreateParentChildRelation.php:20-80` — chokepoint: duplikat/2 rodziców/cykl/ta sama płeć.
- `app/Http/Requests/ParentChildRelationStoreRequest.php:21-47` — exists w drzewie, `different`, `role` nullable, kontrola slotu.
- `app/Http/Requests/PersonParentStoreRequest.php:38-52` — płeć nowego rodzica vs `role`.
- `app/Actions/Persons/UpdatePerson.php:49` — zmiana płci bez kontroli relacji.
- `app/Actions/Relations/CreateMarriage.php:27-51` — blokada, duplikat pary, jedno wydarzenie z dwoma uczestnikami.
- `tests/Feature/ParentChildRelationsTest.php:33-546` — istniejące testy reguł.
- `tests/Feature/MarriagesTest.php:92-121,333-357` — małżeństwo: self/duplikat/obce drzewo.

## Architecture Insights

- Wzorzec: Form Request (kształt, własność drzewa, slot/płeć) + Action w transakcji (struktura grafu, płeć względna) → `ValidationException` na `relation`. Reguły związane z grafem żyją w akcji, reguły z wejścia (slot, self) w Requestach — stąd „trasa pomija kontrolę” jest realnym ryzykiem tylko dla reguł w Requestach.
- Warstwa testów: Pest feature na prawdziwym PostgreSQL (graf, blokady, unique) — zgodnie z test-plan §2 „Likely cheapest layer”.

## Historical Context (from prior changes)

- `context/archive/2026-09-25-parent-child-relations/plan.md:23,50,93-94` — reguły serwera, komunikaty, kolejność blokad.
- `context/archive/2026-09-28-tree-parent-slot-existing-or-new/plan.md:11,39-40,53,71,271` — wcześniej brak reguły płci; świadomy brak strażnika przy zmianie płci; istniejące pary tej samej płci nie są sprzątane.
- `context/archive/2026-09-28-tree-child-existing-or-new/plan-brief.md:30,47,73` — „klient tylko ukrywa, serwer jest autorytetem”; brak serwerowej kontroli, że dwoje rodziców to związek.
- `context/archive/2026-09-28-tree-spouse-existing-or-new/plan.md:46,51` i `reviews/impl-review.md:26-32` — filtr płci/pokrewieństwa małżonków tylko w UI.
- `context/archive/2026-10-02-testing-ownership-boundaries/plan.md:45` — #5 wyłączone do tego badania.

## Related Research

- `context/archive/2026-10-02-testing-ownership-boundaries/research.md` — wyrocznia własności, wzorzec datasetu i `ownershipSnapshot()` (do użycia jako „brak zapisu”).

## Oracle (z PRD, nie z implementacji)

- `context/foundation/prd.md:64`, FR-011 (:112): „maks. dwoje rodziców, nikt nie jest własnym przodkiem — także przy dodawaniu z drzewa”; :103: trzeci rodzic „blokują reguły relacji, a komunikat mówi dlaczego”; :101: slot ojca/matki — płeć pasująca; `prd-v3.md:122-123`: slot ustawia i blokuje płeć nowej osoby; :139: małżeństwo to jedno wydarzenie obojga.
- PRD nie mówi nic o: zmianie płci rodzica, kolejności/płci przy `role=parent`, pokrewieństwie i płci małżonków (poza filtrem UI).

## Open Questions (wymagają decyzji autora — oracle nie rozstrzyga)

1. **Zmiana płci osoby będącej rodzicem**: dziś dozwolona i może dać dwóch ojców lub „ojca-kobietę”. Opcje: (a) świadomy brak reguły — test charakteryzujący dokumentuje stan; (b) odrzucać zmianę płci, gdy łamie relację (zmiana kodu, ryzyko #5 realnie zamknięte).
2. **`parent-child-relations.store` bez `role`**: czy pominięcie `role` ma omijać kontrolę slotu (dziś tak), czy ma być wymagane?
3. **`role=parent` z płcią `null`/dowolną** przy tworzeniu rodzica — akceptowane (zgodnie z testem :352)? Zakładam tak.
4. **Małżeństwa**: czy kolejny plan ma testować pokrewieństwo/płeć małżonków? Dotychczas „UI only, accepted”; #5 w test-plan o małżeństwach nie mówi — proponuję poza zakresem.
5. **Strażnik rodzic ≠ dziecko w akcji** i CHECK w DB: obrona w głąb, poza zakresem (jak w poprzedniej zmianie)?
6. **Wyścig na cyklu** nieudowodniony — czy chcemy test współbieżności (kosztowny), czy świadomie poza zakresem?

## Decisions (autor, 2026-10-02)

1. **Zmiana płci rodzica** — poprawiamy kod: serwer odrzuca zmianę płci osoby, gdy łamie istniejącą relację (dwóch rodziców tej samej znanej płci u dziecka; „ojciec” niebędący mężczyzną, „matka” niebędąca kobietą — uwaga: dziś relacja nie przechowuje roli, więc kształt reguły do ustalenia w planie). Wyrocznia: autor.
2. **\`role\` w \`parent-child-relations.store\`** — ma być wymagane; ojciec = mężczyzna, matka = kobieta (spójne z \`persons.parents.store\`).
3. **Przyjęte z propozycji** (brak sprzeciwu): \`role=parent\` z dowolną płcią zostaje; małżeństwa (pokrewieństwo/płeć) poza zakresem; strażnik rodzic≠dziecko w akcji, CHECK w DB i test wyścigu poza zakresem.

## Gap w badaniu

Nie czytałem osobiście: `ParentChildGraph`, `BuildFamilySheet`, polityk, testów (opieram się na inwentarzu agentów dla testów i małżeństw). Zweryfikowałem sam: `CreateParentChildRelation.php`, `ParentChildRelationStoreRequest.php`, `UpdatePerson.php:49`.
