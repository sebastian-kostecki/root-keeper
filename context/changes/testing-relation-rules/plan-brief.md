# Reguły relacji (ryzyko #5) — Plan Brief

> Full plan: `context/changes/testing-relation-rules/plan.md`
> Research: `context/changes/testing-relation-rules/research.md`

## What & Why

Faza 3 test-planu, część dla ryzyka #5: reguł relacji (trzeci rodzic, cykl przodków, niezgodna płeć) nie wolno obejść poza filtrem wyboru w UI — serwer musi je odrzucać na każdej ścieżce zapisu. Research znalazł dwie realne luki: zmiana płci osoby po podpięciu jej jako rodzica oraz pominięcie `role` w `parent-child-relations.store`. Autor zdecydował, że obie naprawiamy.

## Starting Point

4 trasy tworzące relację rodzic–dziecko kończą w `CreateParentChildRelation` (duplikat, maks. dwoje rodziców, cykl, ta sama płeć). Prawie każda reguła ma test na co najmniej jednej trasie, ale część testów sprawdza tylko klucz błędu i liczbę wierszy w jednej tabeli. Brakuje dwóch komórek macierzy w `persons.children.attach`. Edycja osoby (`UpdatePerson`) w ogóle nie patrzy na relacje.

## Desired End State

Brak `role` → błąd walidacji. Zmiana płci, która dałaby dziecku dwóch ojców lub dwie matki, jest odrzucana komunikatem pod polem „Płeć”. Każda stosowalna komórka „reguła × trasa” ma test z dokładnym komunikatem i dowodem, że nic nie zapisano (`ownershipSnapshot()`). Cookbook §6.5 mówi, jak testować nową ścieżkę tworzącą relację.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Zmiana płci rodzica | Naprawiamy w kodzie | Ryzyko #5 ma być realnie zamknięte, nie tylko udokumentowane | Research |
| Kształt reguły płci | Strażnik grafu: odrzuć zmianę na znaną płeć X, gdy któreś dziecko ma innego rodzica płci X; „nieznana” zawsze dozwolona | Lustro reguły z tworzenia relacji; pozwala poprawić pomyłkę u samotnego rodzica | Plan |
| Kiedy strażnik działa | Tylko gdy płeć faktycznie się zmienia | Starsze niespójne pary nie blokują edycji imienia | Plan |
| Miejsce reguły płci | Akcja `UpdatePerson`, w transakcji, błąd na `gender` | Reguły grafu żyją w akcjach; formularz już wyświetla błąd `gender` | Plan |
| `role` w `parent-child-relations.store` | Wymagane; `parent` nadal z dowolną płcią | Spójność z `persons.parents.store`; UI i tak zawsze wysyła `role` | Research |
| Zakres testów | Uzupełnić luki w `ParentChildRelationsTest.php` + zaostrzyć asercje | Brak duplikatów, każdy test łapie inną regresję | Plan |
| Wyrocznia komunikatów | Teksty z archiwum `2026-09-25-parent-child-relations/plan.md:92-94` | Pochodzą z decyzji, nie z kodu — to nie test lustrzany | Plan |
| Małżeństwa, CHECK w DB, wyścigi | Poza zakresem | Decyzja autora w researchu | Research |

## Scope

**In scope:**
- `role` wymagane w `ParentChildRelationStoreRequest`
- Strażnik zmiany płci w `UpdatePerson` + testy w `PersonsTest.php`
- 2 nowe komórki macierzy (cykl przez `other_parent_id`, trzeci rodzic u samotnego rodzica) w `children.attach`
- Dokładne komunikaty i `ownershipSnapshot()` w testach odrzucenia relacji
- Cookbook §6.5 „Reguły relacji”, wiersz §3

**Out of scope:**
- Kolumna roli w relacji, sprzątanie starszych par tej samej płci
- Pokrewieństwo i płeć małżonków
- Strażnik rodzic ≠ dziecko w akcji, CHECK w DB, testy współbieżności
- Infection / testowanie mutacyjne narzędziem (ręczna kontrola mutacji zamiast tego)

## Architecture / Approach

Bez nowych warstw: Form Request (kształt i slot) + akcja w transakcji (reguły grafu) → `ValidationException`. Strażnik płci czyta graf `ParentChildGraph::forTree` i płeć współrodziców świeżo z bazy, jak `guardSameSexParent`. Testy: Pest feature na PostgreSQL, wzorzec „arrange → snapshot → żądanie → dokładny komunikat → snapshot bez zmian”.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. `role` wymagane (`/10x-tdd`) | Brak `role` → błąd; 22 istniejące wywołania w testach dostają `role: parent` | Pominięte wywołanie w teście zacznie testować walidację zamiast reguły akcji |
| 2. Strażnik zmiany płci (`/10x-tdd`) | Odrzucenie zmiany płci dającej dwóch ojców/dwie matki | Za szeroki wyzwalacz zablokuje edycję starszych danych |
| 3. Luki macierzy (`/10x-implement`) | 2 nowe testy + zaostrzone asercje | Czerwony test = rozbieżność z wyrocznią → stop i pytanie, nie przepisywanie oczekiwania |
| 4. Cookbook §6.5 i §3 (`/10x-implement`) | Wzorzec dla nowej ścieżki tworzącej relację | — |

**Prerequisites:** Sail uruchomiony (PostgreSQL 18); `testing-ownership-boundaries` zarchiwizowana (helper `ownershipSnapshot()` w `tests/Pest.php`).
**Estimated effort:** ~1–2 sesje, 4 małe fazy.

## Open Risks & Assumptions

- Samotny „ojciec” zmieniony na kobietę staje się na drzewie matką — przyjęte, bo slot wynika z płci, a relacja nie przechowuje roli.
- Wyścig: zmiana płci równolegle z dodaniem drugiego rodzica może teoretycznie dać parę tej samej płci (blokady obejmują inne wiersze) — świadomie poza zakresem.

## Success Criteria (Summary)

- Żadna z 4 tras tworzących relację ani edycja osoby nie pozwala zapisać trzeciego rodzica, cyklu ani dwóch rodziców tej samej znanej płci.
- Każde odrzucenie mówi dlaczego (dokładny komunikat) i dowodzi, że nic nie zapisano.
- Nowa ścieżka zapisu relacji dostaje testy z cookbooka §6.5 bez ponownego badania.
