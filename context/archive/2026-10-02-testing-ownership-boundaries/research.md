---
date: 2026-10-02T11:58:21+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 3730163765886abc27a426321de04cf0c48329b4
branch: main
repository: root-keeper
topic: "Ryzyko #4 (nadużycie): inny zarejestrowany użytkownik odczytuje lub zmienia moje osoby, źródła albo pliki, podając id z mojego drzewa w adresie lub w treści żądania"
tags: [research, testing, authorization, policies, form-requests, ownership, test-plan-phase-3]
status: complete
last_updated: 2026-10-02
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Decyzje autora po badaniu (2026-10-02): wyrocznia dla id w treści, zakres fazy 3, passkey."
---

# Research: ryzyko #4 — cudze id w adresie lub w treści żądania

**Date**: 2026-10-02T11:58:21+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 3730163765886abc27a426321de04cf0c48329b4
**Branch**: main
**Repository**: root-keeper

## Research Question

`context/foundation/test-plan.md` §2 ryzyko #4 (High × Medium): *(nadużycie)* Inny zarejestrowany użytkownik odczytuje lub zmienia moje osoby, źródła albo pliki, podając id z mojego drzewa w adresie **lub w treści żądania** (np. akcje z drzewa przyjmujące wskazaną osobę). Wskazówka z §2 (`test-plan.md:42`): „Każdy endpoint odczytu i zapisu z cudzym id — w adresie i w treści — kończy się 404 i niczego nie zapisuje; plik cudzego źródła nie jest serwowany.” Do ugruntowania: pełna lista tras, które z nich przyjmują id w treści, jak serwowane są pliki.

Zakres: tylko ryzyko #4. Ryzyko #5 (reguły relacji) należy do tej samej fazy 3, ale wymaga osobnego badania.

## Summary

1. **Wyrocznia z PRD jest jednoznaczna dla id w adresie, milczy o id w treści.** PRD v1 (`git show e74243f:context/foundation/prd.md:41,111,128`): „Żadne dane ani skany nie są dostępne […] dla innych użytkowników”, „pełny dostęp wyłącznie do własnych danych”. prd-v3 `:148` i prd v4 (`context/foundation/prd.md:143`): „cudzy rekord to 404”, „cudza osoba to 404”. Żaden z czterech PRD nie opisuje statusu dla cudzego id **w treści** żądania.
2. **Konflikt wyroczni (do rozstrzygnięcia przed `/10x-plan`).** `test-plan.md:42` wymaga 404 także dla id w treści. Archiwum świadomie przyjęło **błąd walidacji** (302 z błędem w sesji dla żądań webowych, ogólny komunikat „Zaznaczone pole … jest nieprawidłowe.”, taki sam dla id cudzego i nieistniejącego) — `archive/2026-09-25-parent-child-relations/plan.md:11`, `archive/2026-09-29-scans-become-sources/reviews/impl-review.md:70-78` (F5, „SKIPPED”). Kod i istniejące testy realizują wariant z archiwum. Patrz Open Questions #1.
3. **Id w adresie: 19 nazw tras z parametrem modelu w `routes/web.php:17-40`; przy poprawnej treści każda z nich daje 404 dla cudzego id i nie zapisuje** (na podstawie odczytu kodu; 404 dla każdej z 19 tras ma test — lista w Detailed Findings). Cztery polityki (`app/Policies/*.php`) zwracają `Response::denyAsNotFound()` w każdej z 9 metod.
4. **Realna rozbieżność z wyrocznią: 5 tras `persons/{person}/…` waliduje treść przed autoryzacją.** `persons.parents.store`, `persons.children.attach`, `persons.children.store`, `persons.spouses.attach`, `persons.spouses.store` sprawdzają osobę z adresu dopiero w ciele kontrolera (`PersonParentController.php:19`, `PersonChildController.php:22,43`, `PersonSpouseController.php:22,40`), a ich Form Requesty nie mają `authorize()`. Wniosek z kodu (nieuruchomiony): cudza **istniejąca** osoba w adresie + niepoprawna treść → błąd walidacji zamiast 404; nieistniejące id → 404 przy wiązaniu. To łamie „cudza osoba to 404” (prd v4 `:143`) i decyzję z archiwum „autoryzuj przed walidacją, żeby 422 nie zdradzało istnienia rekordu” (`archive/2026-09-24-birth-death-confirmed-by-scan/plan.md:94`). Skutek: wyrocznia istnienia id osoby; brak zapisu i brak odczytu danych cudzej osoby (walidacja `after` w `PersonParentStoreRequest.php:32-42` czyta tylko pola żądania). Żaden istniejący test nie wysyła cudzej osoby z niepoprawną treścią.
5. **Id w treści: 6 endpointów, 9 pól; każde pole ma regułę `Rule::exists(...)->where('tree_id', $this->user()->tree->id)`** (9 wystąpień, wypisanych niżej). W 5 z 6 endpointów kontroler dodatkowo pobiera rekord przez `$tree->people()->findOrFail(...)`. Wyjątek: `events.sources.store` przekazuje `source_id` prosto do `syncWithoutDetaching` (`EventSourceController.php:21`) — ochroną jest tylko reguła walidacji.
6. **Pliki źródeł nie są dostępne poza dwiema trasami z `Gate`.** Dysk `local`: `root` = `storage/app/private`, `'serve' => false` (`config/filesystems.php:35-36`); pliki w `sources/{tree_id}/{uuid}.ext` (`app/Actions/Sources/StoreSource.php:33`); `sources.file` i `sources.thumbnail` wołają `Gate::authorize('view')` (`SourceController.php:170,187`). Brak symlinku `public/storage`, brak `Storage::url`/`temporaryUrl` w kodzie (sprawdzone grepem w `app/`, listing `public/`).
7. **Pokrycie testami jest szerokie, ale jednostkowe — brak datasetu po trasach** (antywzorzec „sprawdzenie jednej trasy na próbę” z `test-plan.md:42` jest częściowo spełniony, bo testy istnieją dla wszystkich 19 tras, ale rozproszone po 6 plikach i z niejednolitym dowodem „niczego nie zapisano”). Luki wymienione w sekcji „Pokrycie testami”.

## Detailed Findings

### Wyrocznia — źródła

| Źródło | Treść | Co rozstrzyga |
|---|---|---|
| PRD v1 `e74243f:context/foundation/prd.md:41` (Guardrails) | „Żadne dane ani skany nie są dostępne dla niezalogowanych ani dla innych użytkowników.” | odczyt i pliki: zakaz |
| PRD v1 `:128` (Access Control) | „Rejestracja jest otwarta […] pełny dostęp wyłącznie do własnych danych (osób, faktów, źródeł, notatek, zdjęć, skanów) i nie widzi danych innych użytkowników.” | zapis i odczyt: tylko własne |
| prd-v3 `context/foundation/prd-v3.md:134,148` | „prywatność drzewa (cudzy rekord = 404)”; „cudzy rekord to 404” | status dla cudzego rekordu |
| prd v4 `context/foundation/prd.md:132,143` | „Podgląd osoby i akcje relacji na drzewie podlegają tej samej regule — cudza osoba to 404.” | status dla akcji z drzewa |
| `CLAUDE.md` (Backend conventions) | „another user's record is a 404, not a 403” | konwencja |
| — | brak zdania o id w treści żądania w żadnym PRD (sprawdzone: Access Control, Guardrails, NFR, Constraints w czterech PRD) | **nierozstrzygnięte** |

### Id w adresie — trasy (`routes/web.php:17-40`, grupa `auth` + `verified`)

Wiązanie modeli jest globalne: brak `resolveRouteBinding`, `Route::bind`, `scopeBindings`, `->missing()` w `app/`, `bootstrap/`, `routes/`, `config/` (grep). Jedyne dostosowanie: `Route::pattern('person'|'source', '[0-9]+')` (`app/Providers/AppServiceProvider.php:29-30`). Cudzy rekord wiąże się więc poprawnie, a ochronę daje polityka.

| Trasa | Autoryzacja (kotwica) | Kolejność względem walidacji | Cudze id, poprawna treść | Test |
|---|---|---|---|---|
| persons.update | `PersonUpdateRequest::authorize()` → `Gate::inspect('update')` (`PersonUpdateRequest.php:15-18`) | przed | 404 | `PersonsTest.php:754` |
| persons.destroy | `Gate::authorize('delete')` (`PersonController.php:123`) | brak Form Requestu | 404 | `PersonsTest.php:921` |
| tree.show | `Gate::authorize('view')` (`TreeController.php:39`) | GET | 404 | `TreeTest.php:144` |
| tree.root-person.update | `Gate::authorize('update')` (`TreeRootPersonController.php:19`) + `abort_unless(...tree_id..., 404)` (`SetTreeRootPerson.php:15`) | brak Form Requestu | 404 | `TreeTest.php:22,33` |
| sources.show | `Gate::authorize('view')` (`SourceController.php:112`) | GET | 404 | `SourcesTest.php:60` |
| sources.update | `SourceUpdateRequest::authorize()` (`:22-25`) | przed | 404 | `SourcesTest.php:640` |
| sources.destroy | `Gate::authorize('delete')` (`SourceController.php:156`) | brak Form Requestu | 404 | `SourcesTest.php:432` |
| sources.file | `Gate::authorize('view')` (`SourceController.php:170`) | GET | 404 | `SourcesTest.php:122` |
| sources.thumbnail | `Gate::authorize('view')` (`SourceController.php:187`) | GET | 404 | `SourcesTest.php:133` |
| events.sources.store | `EventSourceStoreRequest::authorize()` → `Gate::inspect('update', event)` (`:16-19`) | przed | 404 | `EventSourcesTest.php:82,94,104` |
| events.sources.destroy | `Gate::authorize('update', $event)` + `Gate::authorize('view', $source)` (`EventSourceController.php:36-37`); `detach() === 0` → 404 (`:39-43`) | brak Form Requestu | 404 (cudze `{event}` lub cudze `{source}`) | `EventSourcesTest.php:138,173` |
| parent-child-relations.destroy | `Gate::authorize('delete')` (`ParentChildRelationController.php:36`) | brak Form Requestu | 404 | `ParentChildRelationsTest.php:153` |
| marriages.update | `MarriageUpdateRequest::authorize()`: nie-małżeństwo → 404, potem `Gate::inspect('update')` (`:22-31`) | przed | 404 | `MarriagesTest.php:242` |
| marriages.destroy | `Gate::authorize('update')` (`MarriageController.php:51`) + `abort_unless(type === Marriage, 404)` (`:52`) | brak Form Requestu | 404 | `MarriagesTest.php:253` |
| persons.parents.store | `Gate::authorize('view')` (`PersonParentController.php:19`) | **po** (`PersonParentStoreRequest` bez `authorize()`) | 404; niepoprawna treść → błąd walidacji | `ParentChildRelationsTest.php:422` (tylko poprawna treść) |
| persons.children.attach | `Gate::authorize('view')` (`PersonChildController.php:22`) | **po** (`PersonChildAttachRequest` bez `authorize()`) | 404; niepoprawna treść → błąd walidacji | `ParentChildRelationsTest.php:573` (tylko poprawna treść) |
| persons.children.store | `Gate::authorize('view')` (`PersonChildController.php:43`) | **po** (`PersonChildStoreRequest` bez `authorize()`) | 404; niepoprawna treść → błąd walidacji | `ParentChildRelationsTest.php:723` (tylko poprawna treść) |
| persons.spouses.attach | `Gate::authorize('view')` (`PersonSpouseController.php:22`) | **po** (`PersonSpouseAttachRequest` bez `authorize()`) | 404; niepoprawna treść → błąd walidacji | `MarriagesTest.php:393` (tylko poprawna treść) |
| persons.spouses.store | `Gate::authorize('view')` (`PersonSpouseController.php:40`) | **po** (`PersonStoreRequest` bez `authorize()`) | 404; niepoprawna treść → błąd walidacji | `MarriagesTest.php:443` (tylko poprawna treść) |

- `authorize()` mają dokładnie 4 z 12 Form Requestów w `app/Http/Requests/` (bez `Settings/`): `EventSourceStoreRequest:16`, `MarriageUpdateRequest:22`, `SourceUpdateRequest:22`, `PersonUpdateRequest:15` (grep `authorize` w `app/Http/Requests/*.php`).
- Mechanizm 404 w ścieżce Form Requestu: `FormRequest::passesAuthorization()` wywołuje `$result->authorize()` dla `Response` (`vendor/laravel/framework/src/Illuminate/Foundation/Http/FormRequest.php:342-351`), co rzuca `AuthorizationException` ze statusem 404 (`vendor/laravel/framework/src/Illuminate/Auth/Access/Response.php:96-98,148-157`). Dla `Gate::authorize()` przyjęte to samo zachowanie na podstawie testów z `assertNotFound()` (ścieżka w vendor nieprześledzona).
- Kolejność „walidacja przed bramką w kontrolerze” dla pięciu tras wynika z tego, że Form Request jest walidowany przy rozwiązywaniu argumentów akcji, przed wykonaniem jej ciała. To wniosek z kodu — **nie uruchomiono** żądania z cudzą osobą i niepoprawną treścią.
- Trasy bez id w adresie i bez id w treści: `persons.index`, `persons.store`, `tree.index`, `sources.index`, `sources.store` — zapytania przez `$request->user()->tree` (`PersonController.php:32,36,99`, `TreeController.php:22-23`, `SourceController.php:41,96`).

### Id w zapytaniu (query string)

- `?person=` — wspólny prop `personPreview` na **każdej** odpowiedzi Inertia (`app/Http/Middleware/HandleInertiaRequests.php:54-58`): tylko cyfry (`:76`), `$user->tree->people()->findOrFail()` (`:84`), `Gate::authorize('view')` (`:86`) → cudze lub nieistniejące id daje 404 całej strony. Test tylko na `persons.index` (`PersonsTest.php:984`); na innych stronach nietestowane (ten sam kod middleware).
- `?spouse=` na `tree.index`/`tree.show` — tylko wybór jednego z małżeństw zbudowanych z grafu własnej osoby (`app/Actions/Trees/BuildFamilySheet.php:348-363`), nigdy wyszukiwanie po id; nieznana wartość → pierwsze małżeństwo. Test: `TreeTest.php:212`.
- `persons.index` (`q`, `view`, `sort`) i `sources.index` (`q`, `kind`, `view`, `sort`) — żaden parametr nie jest id (`PersonController.php:29-31`; `SourceController.php`).

### Id w treści żądania

| Endpoint | Pole | Reguła (kotwica) | Ponowne pobranie przez drzewo | Cudze id | Test |
|---|---|---|---|---|---|
| events.sources.store | `source_id` | `Rule::exists('sources','id')->where('tree_id', …)` (`EventSourceStoreRequest.php:33`) | **brak** — `syncWithoutDetaching([$request->validated('source_id')])` (`EventSourceController.php:21`) | błąd walidacji, brak zapisu | `EventSourcesTest.php:47,71` |
| marriages.store | `person_id`, `spouse_id` | `MarriageStoreRequest.php:23,24` | `$tree->people()->findOrFail` (`MarriageController.php:24-25`) | błąd walidacji, brak zapisu | `MarriagesTest.php:121` |
| parent-child-relations.store | `parent_id`, `child_id` | `ParentChildRelationStoreRequest.php:21,22` | `ParentChildRelationController.php:21-22` | błąd walidacji, brak zapisu | `ParentChildRelationsTest.php:115` |
| persons.children.attach | `child_id`, `other_parent_id` | `PersonChildAttachRequest.php:29,35` | `PersonChildController.php:25-27` | błąd walidacji, brak zapisu | `ParentChildRelationsTest.php:557` (dataset `:571`) |
| persons.children.store | `other_parent_id` | `PersonChildStoreRequest.php:27` | `PersonChildController.php:46-48` | błąd walidacji, brak zapisu | `ParentChildRelationsTest.php:645` |
| persons.spouses.attach | `spouse_id` | `PersonSpouseAttachRequest.php:28` | `PersonSpouseController.php:25` | błąd walidacji, brak zapisu | `MarriagesTest.php:381` |

- Brak wspólnej reguły „należy do mojego drzewa” (brak `app/Rules/`, makra, traitu) — 9 wystąpień wzorca jest powielonych ręcznie (lista w tabeli).
- `tree_id` nigdy nie pochodzi z treści: akcje budują atrybuty jawnie albo z `validated()`/`safe()->except(...)`, a żaden Form Request nie ma reguły dla `tree_id` (grep `tree_id` w `app/Http/Requests/` zwraca wyłącznie filtry `where('tree_id', …)`; `#[Fillable]` modeli Person, Event, Source, ParentChildRelation zawiera `tree_id`). Brak testu wysyłającego `tree_id` w treści.
- Akcje w `app/Actions/Relations/` (`CreateMarriage.php:24-51`, `CreateParentChildRelation.php:20-53`, `AttachChildToParents.php:23-48`) nie sprawdzają, czy przekazane osoby należą do `$tree`; robią to tylko kontrolery. Klucze obce `parent_id`/`child_id`/`person_id` to zwykłe `constrained('people')` bez złożonego klucza z `tree_id` (migracje `create_parent_child_relations_table:17-18`, `create_event_participants_table:17`). Dziś nieeksploatowalne (każdy wywołujący przechodzi przez kontroler), istotne dla przyszłych wywołań.

### Pliki źródeł

- Dysk `local`: `storage/app/private`, `'serve' => false` (`config/filesystems.php:35-36`) — wyłącza wbudowaną trasę podpisanych URL-i; decyzja z `archive/2026-09-24-scan-upload-and-preview/plan.md:43,74` i regresja w `SourcesTest.php:29` (`storage.local` nie jest zarejestrowana).
- Ścieżka `sources/{tree_id}/{uuid}.ext` (`StoreSource.php:33`); `SourceResource` nie wystawia `path`/`thumbnail_path`.
- Odpowiedzi plikowe: `Cache-Control: private`, `nosniff` (`SourceController.php:199-206`; test `SourcesTest.php:365-366`).
- Konfiguracja serwera WWW (alias na `storage/`) — nieprzejrzana (poza repozytorium).

### Trasy spoza `routes/web.php`

- `routes/settings.php`: żadna trasa nie przyjmuje id; operują na `$request->user()`.
- Pakiet passkeys (rejestrowany przez Fortify, `vendor/laravel/fortify/routes/routes.php:217`): `DELETE /user/passkeys/{passkey}` → `abort_unless($passkey->user_id === $user->getKey(), 403)` (`vendor/laravel/passkeys/src/Http/Controllers/PasskeyRegistrationController.php:79`). Cudzy klucz → **403**, nie 404. Dane konta, nie drzewa — poza literalnym zakresem ryzyka #4 (osoby, źródła, pliki); odnotowane jako fakt.
- `verification.verify` (`/email/verify/{id}/{hash}`) — mechanizm Fortify z podpisem, poza zakresem.

### Pokrycie testami — luki względem wyroczni

Testy cudzego id istnieją dla wszystkich 19 tras z id w adresie i dla wszystkich 9 pól id w treści (tabele wyżej). Brakuje:

1. **Cudza osoba w adresie + niepoprawna treść** dla 5 tras `persons/{person}/…` — nietestowane; według kodu dałoby błąd walidacji, nie 404 (Summary #4). To jedyny znaleziony przypadek, w którym test zgodny z wyrocznią PRD byłby dziś czerwony.
2. **Dowód „niczego nie zapisano”** brakuje w: `MarriagesTest.php:242` (`marriages.update`, sam status), `EventSourcesTest.php:94,104` (sam status). Częściowy w: `PersonsTest.php:754` (tylko `first_name`, bez wydarzeń urodzenia/zgonu), `PersonsTest.php:921` (tylko wiersz osoby, bez wydarzeń).
3. **`?person=` na innych stronach** niż `persons.index` — nietestowane.
4. **Brak datasetu po trasach** — jedyny dataset dotyczący cudzych id to nazwy pól w `ParentChildRelationsTest.php:571`; ustawienie „innego użytkownika” jest powielane inline (`User::factory()->create()` + `->for($otherUser->tree)`); `UserFactory::configure()` tworzy drzewo automatycznie (`database/factories/UserFactory.php:23-28`).
5. **Status dla id w treści** nigdy nie jest asertowany jako kod — wszędzie `assertSessionHasErrors` (302). Brak `assertForbidden`/`assertUnprocessable` w `tests/`.
6. **Brak testu wstrzyknięcia `tree_id`** w treści `persons.store`/`sources.store`.

## Code References

- `routes/web.php:17-40` — 26 nazw tras domenowych w grupie `auth`+`verified`
- `app/Policies/{Person,Source,Event,ParentChildRelation}Policy.php` — 9 metod, każda `tree_id === $user->tree->id` albo `denyAsNotFound()`
- `app/Http/Controllers/PersonParentController.php:19` — bramka po walidacji
- `app/Http/Controllers/PersonChildController.php:22,43` — bramka po walidacji
- `app/Http/Controllers/PersonSpouseController.php:22,40` — bramka po walidacji
- `app/Http/Requests/EventSourceStoreRequest.php:16-19` — wzorzec „autoryzuj przed walidacją”
- `app/Http/Controllers/EventSourceController.php:21` — `source_id` bez ponownego pobrania przez drzewo
- `app/Http/Middleware/HandleInertiaRequests.php:74-86` — `?person=` na każdej stronie
- `app/Providers/AppServiceProvider.php:29-30` — jedyne dostosowanie wiązania (wzorce cyfrowe)
- `config/filesystems.php:35-36` — prywatny dysk bez `serve`
- `app/Http/Controllers/SourceController.php:170,187,199-206` — serwowanie plików
- `vendor/laravel/passkeys/src/Http/Controllers/PasskeyRegistrationController.php:79` — 403 dla cudzego klucza

## Architecture Insights

- **Dwie warstwy dla id w treści, jedna dla id w adresie.** Id w treści: reguła `exists` ograniczona do drzewa + `findOrFail` przez `$tree->people()` w kontrolerze (poza `events.sources.store`). Id w adresie: globalne wiązanie + polityka; brak `scopeBindings`.
- **Dwa wzorce kolejności autoryzacji współistnieją.** Wzorzec „`authorize(): Response` w Form Request” (4 żądania) wprowadzony w `birth-death-confirmed-by-scan` celowo przeciw wyroczni istnienia; pięć późniejszych tras zagnieżdżonych (`tree-*-existing-or-new`, `parent-child-relations`) wróciło do bramki w kontrolerze.
- **Cudze id w treści jest nieodróżnialne od nieistniejącego** (ten sam komunikat walidacji) — to był argument za akceptacją wariantu 302/422 w archiwum (F5).
- Warstwa najtańsza z sygnałem dla tego ryzyka: Pest feature na prawdziwej bazie (polityki porównują `tree_id`, reguły `exists` to SQL) — zgodnie z `test-plan.md:42`. Stub nic tu nie daje.

## Historical Context (from prior changes)

- `context/archive/2026-09-23-account-tree-and-first-person/plan.md:80` — `PersonPolicy` odpowiada 404 „żeby nie zdradzać istnienia rekordu”. **Aktualne** dla id w adresie; **częściowo** naruszone na 5 trasach zagnieżdżonych przy niepoprawnej treści.
- `context/archive/2026-09-24-birth-death-confirmed-by-scan/plan.md:94` i `reviews/plan-review.md:46-56` (F3, FIXED) — autoryzacja przed walidacją, „inaczej 422 zdradza, że wydarzenie istnieje”. **Aktualne** dla `events.sources.store`; **nie zastosowane** w 5 trasach `persons/{person}/…`.
- `context/archive/2026-09-25-parent-child-relations/plan.md:11,103` — „cudze id w treści odrzucane jako błąd walidacji przez `Rule::exists(...)->where('tree_id', …)`”. **Aktualne** (9 reguł).
- `context/archive/2026-09-29-scans-become-sources/reviews/impl-review.md:70-78` (F5, SKIPPED) — 422 zamiast 404 dla cudzego źródła w treści przyjęte świadomie (brak wycieku istnienia). Potwierdzone w `archive/2026-09-30-fact-confirmed-by-any-source/plan.md:111`. **Aktualne**; sprzeczne z dosłownym brzmieniem `test-plan.md:42`.
- `context/archive/2026-09-25-parent-child-relations/reviews/impl-review.md:25` — osoby sprawdzane dwa razy, `tree_id` nigdy z wejścia. **Aktualne**.
- `context/archive/2026-09-28-graphical-tree-view/reviews/impl-review.md:82-92` — obrona w głąb w `SetTreeRootPerson` (FIXED). **Aktualne** (`SetTreeRootPerson.php:15`); analogicznej obrony brak w akcjach `Relations/`.
- `context/archive/2026-09-24-scan-upload-and-preview/plan.md:43,74` + `reviews/impl-review.md:172-186` — pliki tylko przez kontroler z `Gate`, `serve => false`. **Aktualne**.
- `context/archive/2026-09-25-scan-delete-with-impact/plan.md:27,104` — testy cudzego usunięcia mają dowodzić, że nic nie usunięto. **Częściowo** przestrzegane (luka #2 wyżej).
- `context/archive/2026-09-28-tree-family-sheet-ui/audit.md:94`, `archive/2026-09-29-tree-sheet-variant-c/audit.md:103` — goła strona 404 bez powłoki aplikacji; otwarte, poza zakresem tego ryzyka (wygląd, §7).

## Related Research

- `context/archive/2026-10-01-testing-backup-restore/research.md` — faza 1 tego samego wdrożenia testów (inne ryzyko).
- Ryzyko #5 (reguły relacji) — jeszcze niezbadane; ten sam folder zmiany.

## Decisions (autor, 2026-10-02)

Odpowiedzi autora na pytania otwarte; zastępują rekomendacje z pierwotnej wersji sekcji Open Questions.

1. **Wyrocznia dla cudzego id w treści = błąd walidacji bez zapisu**, z tym samym ogólnym komunikatem co dla nieistniejącego id (wariant z archiwum, F5). 404 pozostaje wyrocznią dla cudzego id w adresie i w `?person=`. 9 istniejących testów id w treści (`assertSessionHasErrors` + brak zapisu) jest zgodnych z wyrocznią. Brzmienie `test-plan.md:42` („w adresie i w treści — kończy się 404”) jest nieaktualne dla treści — do poprawy przy `/10x-test-plan --refresh` (poza tą lekcją; nie zmieniane tutaj).
2. **5 tras `persons/{person}/…` — test + poprawka.** Wyrocznia: cudza istniejąca osoba w adresie → 404 i brak zapisu **niezależnie od treści** (także pustej/niepoprawnej). Faza nadaje się do `/10x-tdd`; pierwszy czerwony test: „POST na `persons/{cudza}/children/attach` z pustą treścią zwraca 404 i niczego nie zapisuje”. Kierunek poprawki z archiwum: autoryzacja w `authorize(): Response` Form Requestu (wzorzec `EventSourceStoreRequest.php:16-19`) — wybór mechanizmu należy do `/10x-plan`.
3. **Zakres fazy 3 dla ryzyka #4 obejmuje dodatkowo:**
   - dataset po 19 trasach z id w adresie: 404 + dowód braku zapisu (zamiast wyłącznie rozproszonych pojedynczych testów);
   - uzupełnienie dowodów braku zapisu: `marriages.update` (`MarriagesTest.php:242`), `EventSourcesTest.php:94,104`, `persons.update`/`persons.destroy` (także wydarzenia osoby — `PersonsTest.php:754,921`);
   - cudze `?person=` na stronach innych niż `persons.index` (np. `tree.show`, `sources.show`) → 404;
   - `tree_id` w treści `persons.store`/`sources.store` jest ignorowane (rekord trafia do drzewa zalogowanego użytkownika).
4. **Passkey objęty zakresem:** `DELETE /user/passkeys/{cudzy}` nie usuwa cudzego klucza; status **403** jest akceptowany (zachowanie kodu vendor, `PasskeyRegistrationController.php:79`) — wyjątek od reguły 404, bo dotyczy konta, nie drzewa.
5. **Nie w tej fazie:** sprawdzenie konfiguracji serwera WWW (pozostaje luką, Open Question #4); badanie ryzyka #5 (osobno, później).

## Open Questions


1. **Wyrocznia dla cudzego id w treści: 404 czy błąd walidacji?** `test-plan.md:42` mówi 404; archiwum (F5) i kod — błąd walidacji z ogólnym komunikatem, nieodróżnialny od nieistniejącego id. PRD nie rozstrzyga. Decyzja autora zmienia, czy 9 istniejących testów jest zgodnych z wyrocznią, czy plan ma zawierać zmianę kodu. Rekomendacja badania: przyjąć „błąd walidacji bez zapisu, ten sam komunikat co dla nieistniejącego id” jako wyrocznię dla treści (gwarancja „nie zdradza istnienia, niczego nie zapisuje” jest zachowana) i poprawić brzmienie `test-plan.md:42` przy następnym `--refresh`; 404 zostaje wyrocznią dla id w adresie i query. Owner: autor. Block: tak, dla `/10x-plan`. **Rozstrzygnięte** — Decisions #1.
2. **Czy 5 tras `persons/{person}/…` ma dawać 404 także przy niepoprawnej treści?** Wyrocznia PRD („cudza osoba to 404”) i decyzja z archiwum (autoryzacja przed walidacją) wskazują, że tak; dziś według kodu daje błąd walidacji. Pierwszy czerwony test nadaje się do `/10x-tdd`: „POST na `persons/{cudza}/children/attach` z pustą treścią zwraca 404 i niczego nie zapisuje”. Potwierdzenie uruchomieniem — w planie. Owner: autor. Block: nie (wyrocznia z PRD jest jednoznaczna). **Rozstrzygnięte** — Decisions #2.
3. **403 dla cudzego passkeya** (`PasskeyRegistrationController.php:79`, kod vendor) — poza literalnym zakresem ryzyka #4; czy autor chce go objąć? Owner: autor. Block: nie. **Rozstrzygnięte** — Decisions #4.
4. **Konfiguracja serwera WWW** (czy nic nie wystawia `storage/` bezpośrednio) — poza repozytorium (`../kostecki-dev-infra`); nieprzejrzane. Owner: autor. Block: nie. **Otwarte** — poza fazą 3 (Decisions #5).
