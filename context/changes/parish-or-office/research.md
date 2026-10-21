---
date: 2026-10-21T09:23:30+02:00
researcher: Claude (Opus 5.5)
git_commit: fa15dcb
branch: develop
repository: root-keeper
topic: "Parafia / urząd z wezwaniem: stan kodu parafii i kontrakty, których dotknie wdrożenie decyzji 3 i 4 z places-review"
tags: [research, parishes, places, sources, migration, label, merge]
status: complete
last_updated: 2026-10-21
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje autora 1–4 (etykieta Geneteka ze skrótami, dawna nazwa tylko do odczytu, pole „Wezwanie / wyróżnik”, zakładka „Parafie i urzędy”)"
---

# Research: Parafia / urząd z wezwaniem (`parish-or-office`)

**Date**: 2026-10-21T09:23:30+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: fa15dcb (drzewo robocze czyste poza `context/changes/{parish-or-office,places-review,unsourced-facts-list}/`)
**Branch**: develop
**Repository**: root-keeper

## Research Question

`/10x-research parish-or-office` — wdrożenie decyzji autora 3 i 4 z `context/changes/places-review/research.md:166-167`:

- parafia identyfikowana przez **siedzibę (wymagana) + wyznanie + wezwanie (opcjonalne)**, etykieta budowana z pól jak w Geneteka („Płock św. Bartłomieja (rzym.-kat.)”);
- parafia uogólniona na **prowadzącego akta** z polem **rodzaj** (parafia / USC-Standesamt / okręg bóżniczy lub metrykalny / inny), w UI „Parafia / urząd”; archiwum zostaje tekstem;
- migracja nie rozbija istniejących nazw automatycznie (`context/changes/parish-or-office/change.md`).

Pytanie badawcze: jaki jest dziś model parafii (po zmianach `locality-details` i `cemeteries`), co dokładnie zmiana dotknie, jaki wzorzec skopiować i jakie decyzje zostają do planu. Research `places-review` powstał na `89b5bfd`, przed zmianą `cemeteries`. Od tego czasu jest 16 commitów, a kotwice poniżej są odświeżone na `fa15dcb`.

## Summary

1. **Klucz parafii to dziś nazwa + wyznanie, a siedziba jest opcjonalna.** Indeks `(tree_id, lower(name), coalesce(religion,''))` (`database/migrations/2026_10_05_114218_create_parishes_table.php:28`); żadna późniejsza migracja nie zmienia `parishes`. Ten sam klucz jest powtórzony w trzech miejscach, które muszą się zgadzać: indeks, reguła w `app/Concerns/ParishValidationRules.php:27-42` i `MergeParish::nameAndReligionTaken()` (`app/Actions/Parishes/MergeParish.php:89-97`).
2. **Etykietę buduje jedno miejsce**, `Parish::label()` (`app/Models/Parish.php:42-45`): „Nazwa (rzymskokatolickie)”. **Skróconej formy wyznania („rzym.-kat.”) nie ma nigdzie** w `app/` ani `resources/js` (`app/Enums/Religion.php:14-24` ma tylko pełne przymiotniki). Etykiety z serwera używają karty i tabela źródeł, filtr „Miejsce”, okna scalania i usuwania, toast scalenia oraz picker parafii w źródle. **Surowe `name` czyta 8 miejsc w 6 grupach**, które nie poszłyby za nową etykietą: tytuł panelu, link w tabeli, wyszukiwanie i sortowanie zakładki, `parish_name` w wyborze źródła do faktu, sortowanie SQL `orderBy('name')` (×3) i sprzątanie E2E (lista w §3).
3. **Wzorcem jest `Cemetery`, rekord z wymaganą miejscowością w kluczu i opcjonalną nazwą.** Indeks wyrażeniowy z `coalesce` (`2026_10_20_111716_create_cemeteries_table.php:30`), reguła `implicit` (`app/Concerns/CemeteryValidationRules.php:84-112`), `cemeteryDetails()` zamieniający pusty tekst na null (`:48-59`), etykieta z serwera (`app/Models/Cemetery.php:46-51`), enum z `label()` + `options()` (`app/Enums/CemeteryManager.php:12-31`) i sortowanie opcji w PHP po etykiecie (`SourceController.php:147,164`).
4. **Gdy siedziba wejdzie do klucza, scalanie miejscowości złamie indeks.** `MergeLocality` przenosi parafie zwykłym `update` (`app/Actions/Localities/MergeLocality.php:50`), a kolizje obsługuje tylko dla cmentarzy (`:47,68-82` + `MergeCemetery::moveInto()` `app/Actions/Cemeteries/MergeCemetery.php:50-66`). `MergeParish` nie ma odpowiednika `moveInto()`, bo jego logika siedzi w jednej transakcji `handle()`.
5. **Dane: lokalnie 2 parafie, z czego 1 bez siedziby.** Chodzi o „Parafia w Lubomlu” w drzewie autora; miejscowość „Luboml” w tym drzewie istnieje, ale nic jej nie łączy z tekstem. Druga to „Parafia rzymskokatolicka w Tarnowie” z seedera demo, z siedzibą Tarnów. 3 źródła wskazują parafię. Produkcji nie sprawdzano. **Nie ma precedensu placeholdera ani migracji zaostrzającej istniejącą kolumnę do NOT NULL.** W `cemeteries` wiersze legacy, których nie dało się zmapować, zostały z NULL, a „wymagane” pilnuje walidacja (`database/migrations/2026_10_20_130000_move_gravestone_cemeteries_to_cemeteries.php:11-14`, `database/migrations/2026_10_21_100000_assign_cemeteries_to_burials.php:11-14`).
6. **Zasięg zmiany** (§3–§6): około 20 plików backendu, około 25 plików frontendu, 9 plików Pest, 9 plików Vitest, 5 specyfikacji Playwright i pomocnik `tests/e2e/support/api.ts`. Do tego PRD FR-001/FR-008 i 3 reguły w `CLAUDE.md`. Fabryka `ParishFactory` domyślnie tworzy parafię bez siedziby (`database/factories/ParishFactory.php:25-26`), a większość wywołań w testach na tym polega.

## Detailed Findings

### 1. Model i tożsamość dziś

- Schemat: `name varchar(255) NOT NULL`, `religion` nullable string (cast na `App\Enums\Religion`), `locality_id` nullable FK bez akcji ON DELETE (NO ACTION; komentarz o kaskadzie user → tree, `create_parishes_table.php:18-22`). Brak opisu/notatki, brak własnego indeksu na `locality_id` (raport z `pg_indexes`, baza lokalna).
- Walidacja (`app/Concerns/ParishValidationRules.php`, 64 linie): `name` `bail|required|string|max:255` + domknięcie `lower(name)=lower(?)` i `coalesce(religion,'')` (`:27-42`, zapytanie `:32`, komunikat „Parafia o tej nazwie i wyznaniu już istnieje.” `:40`); `religion` nullable `Rule::enum` (`:44`); `locality_id` nullable, istnieje w drzewie (`:45-49`); atrybuty nazwa / wyznanie / miejscowość siedziby (`:56-63`). Nie ma helpera `parishDetails()` — kontrolery składają argumenty ręcznie (`ParishController.php:35-40,52-57`, `InlineParishController.php:18-23`).
- Akcje: `StoreParish::handle(Tree, string, ?Religion, ?int)` (`app/Actions/Parishes/StoreParish.php:18`) i `UpdateParish::handle(Parish, string, ?Religion, ?int)` (`UpdateParish.php:17`) zamieniają `UniqueConstraintViolationException` na ten sam komunikat (`StoreParish.php:26-27`, `UpdateParish.php:25-26`).
- `MergeParish::handle` (`MergeParish.php:17-60`): przenosi tylko `sources.parish_id` (`:39`), usuwa duplikat, uzupełnia **puste** `locality_id` (`:48-50`) i puste `religion` celu, to drugie tylko gdy `nameAndReligionTaken()` jest fałszem (`:52-54,89-97`); `fillTarget()` w savepoincie ponawia bez `religion` przy naruszeniu indeksu (`:68-84`). Różne wyznania wolno scalać (`context/archive/2026-10-06-places-merge-and-delete/plan.md:43`).
- `DeleteParish` (`DeleteParish.php:16-35,42`): odmowa, gdy parafię wskazuje źródło.
- Miejscowość: parafia blokuje usunięcie miejscowości (`app/Actions/Localities/DeleteLocality.php:22-25`); scalanie przenosi siedzibę bez kontroli kolizji (`MergeLocality.php:50`).

### 2. Wzorzec cmentarza (do powtórzenia)

| Element | Cmentarz (kotwica) | Odpowiednik dla parafii / urzędu |
| --- | --- | --- |
| Klucz | `UNIQUE (tree_id, locality_id, lower(coalesce(name,'')))` `2026_10_20_111716_create_cemeteries_table.php:30` | `(tree_id, locality_id, kind?, coalesce(religion,''), lower(coalesce(dedication,'')))` — skład do decyzji |
| Wymagana miejscowość | `locality_id` NOT NULL `:18-20`; walidacja `required` `CemeteryValidationRules.php:31-35` | wymagana w walidacji; w bazie zależy od decyzji o wierszach bez siedziby |
| Pusty tekst → null | `cemeteryDetails()` `:48-59` | wezwanie |
| Reguła unikalności | anonimowa `ValidationRule` z `$implicit = true`, pomija nieliczbowe `locality_id` `:84-112`, dwa komunikaty `:108-109` | to samo dla klucza z wezwaniem |
| Enum | `CemeteryManager::label()` + `options()` `app/Enums/CemeteryManager.php:12-31`; opcje przez `HandleInertiaRequests.php:78`, `PlaceController.php:38`, `SourceController::panelOptions` `:110`; typ TS `resources/js/types/cemetery.ts:26-27` | nowy enum rodzaju |
| Etykieta | `Cemetery::label()` wymaga załadowanej `locality` `app/Models/Cemetery.php:46-51`; `toOption()` `:58-66` | `Parish::label()` z siedziby |
| Sortowanie opcji | w PHP po `mb_strtolower(label())` `SourceController.php:147,164`, `HandleInertiaRequests.php:82` | zamiast `orderBy('name')` |
| Kolizje przy scalaniu miejscowości | `mergeCollidingCemeteries()` `MergeLocality.php:68-82` + `MergeCemetery::moveInto()` `MergeCemetery.php:50-66` | brak — do dopisania |
| Okno dodawania | `NewCemeteryDialog` z `LocalityPicker`, bez `required` po stronie klienta, błąd z serwera `new-cemetery-dialog.tsx:160-171`; `Select` z `NONE` `:54-55,173-199` | `NewParishDialog` |

Poza zakresem tej tabeli: `Cemetery::label()` nie używa wyznania (`Cemetery.php:46-51`), więc skrót wyznania byłby nowym kodem (`Religion` dziś tylko pełne przymiotniki, `app/Enums/Religion.php:14-24`; frontend nie mapuje wyznań sam, dostaje `religion_label` — `resources/js/types/parish.ts:4-5`).

### 3. Konsumenci etykiety i nazwy

**Idą za `Parish::label()` bez zmian w kodzie klienta** (wystarczy zmienić metodę): `ParishResource.php:30` (`label`), `SourceResource.php:71` (`parish_label`) → `lib/source-description.ts:16-24,108-123`, `sources-table.tsx:173-177`, `sources-gallery.tsx:7`, `source-picker-dialog.tsx:20`; `SourceController::placeOptions` `:120-142` → `sources-filters.tsx:382-385`; `ParishMergeController.php:25` (toast); `merge-parish-dialog.tsx:72-76,84-93`; `delete-parish-dialog.tsx:62,71`; picker parafii w źródle `source-description-fields.tsx:172-189` (`labelOf = option.label`).

**Czytają surowe `name` (nie pójdą za etykietą):**
- `parish-panel.tsx:80` — tytuł panelu;
- `parishes-table.tsx:78` — tekst linku w wierszu;
- `lib/parishes-index.ts:5-19` (`filterParishes` po `name` + `locality_name`, `sortParishes` po `name`) → `/places` `pages/places/index.tsx:285-286` i liczniki zakładek `lib/places-index.ts:28`;
- `SourceResource.php:72` (`parish_name`) → `lib/source-place-year-filter.ts:24-35` (wybór źródła do faktu);
- SQL `orderBy('name')`: `PlaceController.php:32`, `SourceController.php:108,138`;
- `tests/e2e/support/api.ts:478-483` — sprzątanie szuka `parish.name.includes(suffix)` (cmentarze szukają po `locality_name`, `:485-494`).

**N+1 przy etykiecie z siedziby:** `SourceController::placeOptions` (`:136-140`) nie ładuje `locality`; `ParishMergeController.php:25` woła `label()` na celu bez `locality` (w `app/` i `bootstrap/` nie ma `preventLazyLoading`, więc zadziała, ale z dodatkowym zapytaniem).

**Wyszukiwanie tekstowe:** po stronie serwera brak `ilike`/LIKE na `parishes.name` (jedyne dopasowania tekstu to równość w `ParishValidationRules.php:32` i `MergeParish.php:94`). Po stronie klienta: `lib/suggestions.ts:7-37` (`filterSuggestions`) i `canOfferCreate` (`:40-53`) dopasowują po `labelOf`. Etykieta złożona z siedziby i wezwania sama udostępni je w pickerze. „Dodaj parafię „X”” wstawia wpisany tekst jako `name` do `NewParishDialog` (`new-parish-dialog.tsx:28,60,113-121`; `source-description-fields.tsx:272-283`).

**Znacznik „nie istnieje” (locality-details):** pokazuje go tylko `localityHint()` w `LocalityPicker` (`resources/js/lib/localities-index.ts:28-32`, `locality-picker.tsx:116`), czyli przy wyborze siedziby w panelu i w oknie nowej parafii. `Parish::label()`, `ParishResource` i picker parafii w źródle go nie mają (`source-description-fields.tsx:174-188`, bez `hintOf`). Zakres locality-details celowo ograniczył znacznik do podpowiedzi miejscowości (`context/archive/2026-10-20-locality-details/plan.md:24,43`).

### 4. Pliki zapisu i UI dotknięte nowymi polami

- Backend: `app/Models/Parish.php:14-23` (fillable, phpdoc `locality_id int|null`), `ParishValidationRules.php`, `ParishStoreRequest.php:26-29`, `ParishUpdateRequest.php:19-35`, `StoreParish`, `UpdateParish`, `MergeParish`, `ParishController.php:33-59`, `InlineParishController.php:16-25`, `ParishResource.php:24-31`, `PlaceController.php:31-38`, `SourceController.php:99-142`, `MergeLocality.php:50`, `database/factories/ParishFactory.php:25-26,32-44` (stany `withReligion()`, `seatedIn()`), `database/seeders/TreeViewDemoSeeder.php:345-352,376-378`.
- Frontend: `types/parish.ts:1-16`, `types/source.ts:23-26`, `components/parishes/{parish-panel,new-parish-dialog,parishes-table,merge-parish-dialog,delete-parish-dialog}.tsx`, `components/sources/source-description-fields.tsx:172-189,272-283`, `components/places/places-empty-state.tsx:27-30,43` („Dodaj pierwszą parafię: nazwę, wyznanie i siedzibę.”), `pages/places/index.tsx:73,309-323,346-348,432,454`, `lib/parishes-index.ts`, `lib/places-usage.ts:43-45,84-85,109-116`.
- Wayfinder (generowane, nazwy tras mogą zostać): `parishes.{index,store,update,destroy,merge,inline.store}` (`routes/web.php:13-14,54-56`); slug `?kind=parafie` (`PlaceController.php:113-117`, `lib/places-index.ts:6,11`) i klucz filtra `parafia-{id}` (`SourceController.php:349-377`, `lib/sources-index.ts:49,156,259,274`).
- Napisy „parafi*” widoczne dla autora: backend 15 kotwic w 10 plikach (komunikaty i toasty: `ParishValidationRules.php:40`, `StoreParish.php:27`, `UpdateParish.php:26`, `DeleteParish.php:42`, `MergeParish.php:36`, `ParishMergeRequest.php:50-51,63`, `ParishController.php:42,59,73`, `ParishMergeController.php:25`, `SourceDescriptionValidationRules.php:68`); frontend: pliki z listy wyżej oraz `sources-table.tsx:99` („Parafia / cmentarz”), `sources-filters.tsx:382` („Parafie”), `source-description.ts:123`.
- `lib/view-tokens.test.ts:40,60` skanuje `parishes-table.tsx` i `parish-panel.tsx`; nowy lub przemianowany plik komponentu trzeba tam dopisać (reguła w `CLAUDE.md`).

### 5. Migracja danych

- Dane lokalne (`vendor/bin/sail psql`, baza `laravel`): 2 parafie, 1 z `locality_id IS NULL` (id 1 „Parafia w Lubomlu”, roman_catholic, drzewo 1; id 3 „Parafia rzymskokatolicka w Tarnowie”, roman_catholic, siedziba Tarnów, drzewo 6 — seeder demo); 3 źródła z `parish_id` (1 → parafia 1; 27, 29 → parafia 3); grupowanie po (tree, locality, religion) — 0 kolizji. Baza `e2e`: 0 parafii.
- Wzorzec testu migracji: `require` migracji, `->down()`, wstawienie wierszy w starym kształcie przez `DB::table()` (dwa drzewa), `->up()`, asercje nowego kształtu, nienaruszonej starej kolumny i rundy `down()` (`tests/Feature/SourceMigrationTest.php:14-35`, `tests/Feature/GravestoneCemeteryMigrationTest.php:11-33,51-121`, `tests/Feature/SourceArchiveMigrationTest.php:15-49`).
- Wiersze bez siedziby: precedens `cemeteries` — kolumny legacy zostają NULL, „wymagane” tylko w walidacji (patrz Summary 5). Jedyne dodanie NOT NULL do istniejącej tabeli to `sources.kind` z wartością domyślną (`database/migrations/2026_09_29_100000_rename_scans_to_sources.php:24-28`) — nie pasuje do siedziby. Gdy `locality_id` zostanie nullable w bazie, a wejdzie do klucza, PostgreSQL traktuje NULL jako różne — potrzebny `coalesce` albo `NULLS NOT DISTINCT` (PG ≥ 15; projekt na PG 18).
- Stara nazwa: precedensy „copy first, drop later” z tekstem do ręcznego przeniesienia — `legacy_entry_number` (`app/Http/Resources/SourceResource.php:68`), `cemetery_locality_id` jako pole tylko do odczytu (`context/archive/2026-10-20-cemeteries/plan.md:261`, `follow-ups/review-fixes.md`), `provenance` (`context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md:42`). Parafia nie ma kolumny opisu, w którą dałoby się przelać nazwę (cmentarz ma `description`).

### 6. Testy, które zmiana dotknie

- Pest: `tests/Feature/ParishesTest.php` (czyszczenie siedziby `'locality_id' => ''` `:40-46`; unikalność nazwa+wyznanie `:67-90`, komunikat `:71`; scalanie uzupełnia pustą siedzibę `:214-225`; reguły wyznania przy scalaniu `:237-260`), `PlacesTest.php:197-213` (etykiety „Katedralna (rzymskokatolickie)”, „Św. Jana”), `SourcesTest.php` (`:938-1002` parafia bez siedziby w opcjach i `parish_locality_id`; `:1514` „Św. Jana (rzymskokatolickie)”; `:1544-1560` „Parafia bez siedziby”), `DeleteImpactTest.php:63-83,300-326`, `LocalitiesTest.php:284-335,538-551`, `OwnershipBoundariesTest.php:298-310` (POST tylko `name`, bez siedziby — `:303`), `AppShellTest.php:46-53`, `tests/BackupTestCase.php:128,156`, `SourceArchiveMigrationTest.php`.
- Vitest: `lib/parishes-index.test.ts`, `source-description.test.ts:47`, `source-place-year-filter.test.ts:54`, `places-index.test.ts:83`, `places-usage.test.ts`, `sources-index.test.ts`, `side-panel-url.test.ts:28`, `view-tokens.test.ts:40,60`, `suggestions.test.ts:39-51`.
- Playwright: `tests/e2e/support/api.ts:284-300` (`createParish({name, localityId})` → `name`, `religion`, `locality_id`), `:478-483` (sprzątanie po `name`); `source-panel-parish.spec.ts:36-78` (combobox „Parafia”, opcja „Dodaj parafię „…””, okno „Nowa parafia”, `getByLabel('Nazwa')`, `toHaveValue(parishName)` — zmienia się najbardziej); `places-page.spec.ts:27-89` (tytuł panelu i link = `parishName`, „Pokaż w parafiach (1)”); `places-merge-and-delete.spec.ts:22-68`; `sources-grid-with-filters.spec.ts:31-47` i `source-found-by-place-and-year.spec.ts:47-77` tylko przez pomocnika.

## Code References

- `database/migrations/2026_10_05_114218_create_parishes_table.php:18-28` — kolumny i indeks unikalności nazwa + wyznanie.
- `app/Concerns/ParishValidationRules.php:27-49` — reguła unikalności i nullable siedziba.
- `app/Models/Parish.php:42-45` — `label()`.
- `app/Enums/Religion.php:14-24` — tylko pełne przymiotniki.
- `app/Actions/Parishes/MergeParish.php:39,48-54,68-97` — scalanie, uzupełnianie siedziby i wyznania, kolizja po nazwie.
- `app/Actions/Localities/MergeLocality.php:47-50,68-82` — kolizje cmentarzy obsłużone, parafii nie.
- `app/Actions/Cemeteries/MergeCemetery.php:50-66` — `moveInto()` bez własnej transakcji.
- `database/migrations/2026_10_20_111716_create_cemeteries_table.php:18-30`, `app/Concerns/CemeteryValidationRules.php:25-112`, `app/Models/Cemetery.php:46-66`, `app/Enums/CemeteryManager.php:7-31` — wzorzec.
- `app/Http/Resources/ParishResource.php:24-31`, `SourceResource.php:70-74` — `label`, `parish_label`, `parish_name`, `locality_name` z siedziby.
- `app/Http/Controllers/SourceController.php:99-142,349-377`, `PlaceController.php:31-38,113-117` — opcje, sortowanie, filtry, slug.
- `resources/js/components/parishes/new-parish-dialog.tsx:28-41,107-178`, `parish-panel.tsx:80,171-256`, `parishes-table.tsx:41-45,78` — formularze i lista.
- `resources/js/lib/parishes-index.ts:5-19`, `source-place-year-filter.ts:24-35`, `suggestions.ts:7-53` — dopasowanie tekstu.
- `database/factories/ParishFactory.php:25-26` — domyślnie bez siedziby.
- `tests/e2e/support/api.ts:284-300,478-494` — pomocnik i sprzątanie E2E.

## Architecture Insights

- Etykieta jest liczona po stronie serwera (`label()` w modelu → `*_label` w zasobie), a klient tylko ją wyświetla. `cemeteries` utrwaliło tę konwencję (`context/archive/2026-10-20-cemeteries/plan-brief.md`, wiersz „Architecture / Approach”). Osiem miejsc z §3, które czytają `name`, to odstępstwa od niej. Przy zmianie etykiety trzeba je przepiąć na `label` albo świadomie zostawić.
- Klucz unikalności żyje w trzech miejscach: indeks, reguła walidacji, `MergeParish`. W `cemeteries` doszło czwarte, `MergeLocality`, i to samo czeka parafie, gdy siedziba wejdzie do klucza.
- Ścieżka „akt znaleziony przez miejscowość siedziby” (decyzja PRD `context/foundation/prd.md:247`) zależy od `parish_locality_id`. Wymagana siedziba ją wzmacnia, bo dziś akt parafii bez siedziby nie trafia do wyszukiwania po miejscu (`tests/Feature/SourcesTest.php:991-1002`).
- Nazwy w kodzie (`parishes`, `parish_id`, `Parish*`, trasy, `?kind=parafie`, `parafia-{id}`) można zostawić. Zmiana dotyczy etykiet UI (wniosek z `places-review/research.md:109`, dalej aktualny: żaden konsument nie zależy od słowa „parafia” w identyfikatorze).

## Historical Context (from prior changes)

- `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md:138`, `plan-brief.md:24` — klucz nazwa + wyznanie — **zastąpione** decyzją 3.
- `…/research.md:139`, `plan.md:116` — etykieta „Nazwa (wyznanie)” — **zastąpione** etykietą z pól.
- `…/research.md:141`, `plan.md:22`, `plan-brief.md:27` — siedziba opcjonalna, akt bez siedziby nie trafia po miejscu — **zastąpione** (siedziba wymagana); dla wierszy legacy częściowo aktualne do czasu uzupełnienia.
- `…/plan.md:42-43` — brak automatycznego wyciągania z tekstu, stara kolumna zostaje — **aktualne** i zgodne z `change.md`.
- `…/plan.md:249` — picker parafii z `offerCreate="always"` — **aktualne**.
- `context/archive/2026-10-06-places-merge-and-delete/plan.md:43` — scalanie różnych wyznań dozwolone — **aktualne**; `:53,179` (uzupełnianie siedziby i wyznania tylko przy braku kolizji) — **do przerobienia** pod nowy klucz.
- `context/archive/2026-10-12-places-page/research.md:106` — hierarchia „miejscowość → parafie” odrzucona m.in. dlatego, że parafie mogą nie mieć siedziby — **ten argument odpada**; `plan.md:63` (parafie poza panelem miejscowości) nadal obowiązuje jako decyzja zakresu.
- `context/archive/2026-10-20-cemeteries/plan.md:29` — „parafia nie ma miejscowości w kluczu, cmentarz musi, bo scalania kolidują” — **teraz dotyczy też parafii**; `:44` odkłada „parafia / urząd z wezwaniem” do tej zmiany; `:49,56` (etykieta z serwera, pusty → null) — wzorce do użycia.
- `context/archive/2026-10-20-locality-details/plan.md:24,240` — znacznik „nie istnieje” w podpowiedziach siedziby — **aktualne**; `:45` odkłada parafię / urząd tutaj.

## Related Research

- `context/changes/places-review/research.md` — źródło decyzji 3 i 4 (wzorce Geneteka, szukajwarchiwach, twórca vs przechowawca).
- `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md`
- `context/archive/2026-10-12-places-page/research.md`
- `context/archive/2026-10-20-cemeteries/plan.md`, `plan-brief.md`

## Decyzje autora (2026-10-21)

Odpowiedzi na pytania 1–4 poniżej (AskUserQuestion, jedna runda):

1. **Etykieta w stylu Geneteka ze skrótami:** „Płock św. Bartłomieja (rzym.-kat.)”, „Gostynin USC”, „Andrzejewo okręg bóżniczy”. Wyznanie dostaje nowy krótki opis w `App\Enums\Religion` (obok pełnego `label()`). Rodzaj inny niż parafia to dopisek po siedzibie. Etykietę dalej buduje serwer (`Parish::label()`).
2. **Dawna nazwa tylko do odczytu:** `name` zostaje w bazie jako „dawna nazwa”, widoczna w panelu, dopóki autor jej nie wyczyści („copy first, drop later”). W bazie `locality_id` może być NULL dla starych wierszy, a nowy zapis i edycja wymagają siedziby w walidacji (precedens `cemeteries`). Etykieta parafii bez siedziby pokazuje dawną nazwę.
3. **Jedno pole „Wezwanie / wyróżnik”** (opcjonalne) w kluczu i w etykiecie: „św. Bartłomieja”, „Fara”, „Cyrkuł I”.
4. **Zakładka i grupa filtra „Parafie i urzędy”**, pole w źródle „Parafia / urząd”. Slug `?kind=parafie`, trasy `parishes.*`, klucz filtra `parafia-{id}` i nazwy w kodzie zostają.

Założenia przyjęte bez pytania (do potwierdzenia w planie): rodzaj wchodzi do klucza, czyli parafia i USC w tej samej miejscowości to dwa rekordy. Scalanie miejscowości scala kolidujące parafie w tej samej transakcji, jak cmentarze. Wiersz bez siedziby uczestniczy w kluczu przez `coalesce` albo `NULLS NOT DISTINCT`.

Pytania 1–4 w sekcji poniżej są rozstrzygnięte powyższymi decyzjami i zostają jako zapis tego, co rozważano.

## Open Questions

Decyzje produktowe przed `/10x-plan` (fakty wyżej, wybór należy do autora):

1. **Format etykiety.** Skrót wyznania jak w Geneteka („(rzym.-kat.)”) czy pełny przymiotnik jak dziś („(rzymskokatolickie)”)? Jak pokazać rodzaj inny niż parafia („Gostynin USC”, „Andrzejewo okręg bóżniczy”)?
2. **Stara nazwa i parafie bez siedziby.** Czy `name` zostaje jako pole tylko do odczytu („dawna nazwa”) widoczne w panelu, dopóki autor nie przeniesie danych? Czy baza dopuszcza NULL w siedzibie dla wierszy legacy, a wymóg pilnuje tylko walidacja (precedens `cemeteries`)? Co pokazuje etykieta takiej parafii?
3. **Wyróżnik poza wezwaniem.** Geneteka odróżnia też „Fara”, „Cyrkuł I”, „ASC” (`places-review/research.md:97`). Czy wezwanie to jedno pole „wezwanie / wyróżnik”, czy wezwanie plus osobny dopisek?
4. **Klucz i nazwy w UI.** Czy rodzaj wchodzi do klucza (parafia i USC w tej samej miejscowości)? Jak nazwać zakładkę i grupę filtra („Parafie” → „Parafie i urzędy”?)? Czy slug `?kind=parafie` zostaje?

Domyślne założenie, jeśli autor nie zdecyduje inaczej: scalanie miejscowości scala kolidujące parafie w tej samej transakcji, jak cmentarze (reguła `CLAUDE.md` „Merging a duplicate…”).

Luki: danych produkcyjnych nie sprawdzano (liczba parafii bez siedziby i kształt nazw); skrótów wyznań w Geneteka nie zweryfikowano poza przykładami z `places-review` („ewang.”, „mojż.”, „gr.-kat.”).
