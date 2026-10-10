---
date: 2026-10-10T16:41:34+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: eef643037528991b986eb611edd2b7471f7c1b78
branch: develop
repository: root-keeper
topic: "S-14 person-events-and-note-tabs — co dziś daje panel osoby i model danych, żeby dodać zakładkę wydarzeń (własne + dzieci + małżonkowie, po roku, bez daty na końcu) i zakładkę jednej notatki o osobie"
tags: [research, codebase, persons, side-panel, tabs, events, partial-date, person-note, BuildPersonPreview]
status: complete
last_updated: 2026-10-10
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika do pytań otwartych 1–4 i 6 (2026-10-10)"
---

# Research: S-14 — zakładki wydarzeń i notatki w panelu osoby

**Date**: 2026-10-10T16:41:34+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: eef6430 (lokalne referencje file:line; drzewo robocze ma tylko nieśledzony folder tej zmiany)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Wycinek S-14 z roadmapy (`context/foundation/roadmap.md:292-302`): autor widzi w panelu osoby zakładkę wydarzeń — własnych oraz urodzeń i zgonów dzieci, ślubu i zgonu małżonka — uporządkowanych po roku, z wydarzeniami bez daty na końcu, oraz zakładkę z jedną notatką tekstową o osobie (FR-021, FR-023 — `context/foundation/prd.md:161-169`).

Pytania badawcze:
1. Jak zbudowany jest dziś panel osoby (zakładki, tryb podglądu/edycji, nawigacja) i gdzie wpinają się nowe zakładki?
2. Skąd panel bierze dane i czy dane wydarzeń dzieci i małżonków są już dostępne?
3. Jak modelowane są wydarzenia i daty częściowe — co jest potrzebne do sortowania „po roku, bez daty na końcu”?
4. Gdzie i jak zapisać notatkę osoby, zgodnie z obecną ścieżką zapisu i wzorcami innych notatek?
5. Jakie wcześniejsze decyzje, ryzyka i lekcje z przeglądów dotyczą tej zmiany?

## Summary

- **Zakładki już istnieją.** Panel osoby renderuje Radix `Tabs` z trzema zakładkami: `Przegląd`, `Rodzina`, `Źródła` (`resources/js/components/persons/person-modal.tsx:58-85`). S-14 dodaje dwie pary `TabsTrigger`/`TabsContent` do tego hosta, nie wprowadza zakładek od zera. Zmiana zakładki przechodzi przez strażnika niezapisanych zmian (`requestExit`, `person-modal.tsx:54`), a zakładka wraca do `przeglad` przy zmianie osoby (`person-modal.tsx:33-36`).
- **Dane panelu to współdzielony prop `personPreview`** (`app/Http/Middleware/HandleInertiaRequests.php:60`), budowany przez `BuildPersonPreview` (`app/Actions/Persons/BuildPersonPreview.php:27`). Ten eager-load obejmuje już `events.sources`, `events.locality`, `children.events` i `marriages.participants.events` — więc daty urodzeń/zgonów dzieci i zgonu małżonka są w pamięci; **brakuje `locality` dla zdarzeń dzieci i małżonków** (bez dopisania `children.events.locality` / `marriages.participants.events.locality` miejsce w nowej liście da N+1).
- **Payload frontendu nie niesie dziś dat krewnych w formie strukturalnej.** `RelativeResource` (`app/Http/Resources/RelativeResource.php:30-37`) i `MarriageResource.spouse` (`app/Http/Resources/MarriageResource.php:47-52`) zwracają tylko sformatowane `birth_date`/`death_date`. Własne wydarzenia osoby mają `date_parts` (`resources/js/types/person.ts:14-25`). Sortowanie po roku wymaga więc nowej, gotowej listy wydarzeń z serwera albo nowych pól z częściami daty.
- **Model wydarzeń:** jedna tabela `events` z `type` ∈ {`birth`, `death`, `marriage`, `burial`} (`app/Enums/EventType.php:7-10`), data jako `date_year`/`date_month`/`date_day` (nullable) + `date_is_approximate`, osoby przez pivot `event_participants` (rola Subject/Spouse). Małżeństwo to wydarzenie z dwoma uczestnikami — to ten sam wiersz u obu małżonków. Pochowanie nie ma daty (tylko miejsce). Zamieszkania to osobna tabela `residences`, nie wydarzenia.
- **Istniejący klucz sortowania do skopiowania:** `PersonOrdering::marriageSortKey` = `[rok null ? 1 : 0, rok, miesiąc ?? 0, dzień ?? 0, id]` (`app/Support/PersonOrdering.php:123-132`) — dokładnie reguła „po roku, bez daty na końcu”. Nie ma dziś helpera, który scala wydarzenia wielu osób.
- **Notatki osoby nie ma** — ani kolumny w `people`, ani pola w typach TS. Wzorzec do skopiowania: `sources.note` (`text` nullable), reguła `['nullable', 'string', 'max:5000']` z etykietą `notatka` (`app/Concerns/SourceDescriptionValidationRules.php:28,66`); `ui/textarea.tsx` już istnieje.
- **Pułapka zapisu:** `UpdatePerson` zapisuje pola przez `$data[...] ?? null` (`app/Actions/Persons/UpdatePerson.php:55-61`), a frontend wysyła pełny `personForm` z nałożoną sekcją (`resources/js/lib/person-payload.ts:119,149`). Nowe pole `note` musi trafić do `personFormToPayload`, inaczej zapis innej sekcji je wyczyści — tak jak przy nazwisku rodowym (test `tests/Feature/PersonsTest.php:1393`, `resources/js/lib/person-payload.test.ts:25-27`). Alternatywą jest osobny endpoint notatki — to decyzja do planu.

## Detailed Findings

### 1. Panel osoby — host zakładek i edycja per sekcja

- Montaż w layoucie: `PersonPanel` (`resources/js/components/persons/person-panel.tsx`) czyta `personPreview` z `usePage().props`, renderuje `SidePanel variant="standard"` z nagłówkiem i `<PersonModal>`; panel jest w `AppLayout` wewnątrz `SectionEditorProvider` (`resources/js/layouts/app-layout.tsx`).
- Host zakładek `PersonModal` (`person-modal.tsx:27-87`):
  - stan zakładki `useState('przeglad')` (`:30`), reset przy zmianie `person.id` (`:33-36`);
  - `onValueChange={(value) => requestExit(() => setTab(value))}` (`:54`);
  - triggery `przeglad` / `rodzina` (z `TabCount`) / `zrodla` (z `TabCount`) (`:58-68`), treści `OverviewTab`, `FamilyTab`, `SourcesTab` (`:71-85`);
  - `TabCount` (`:14-24`) ukrywa licznik przy 0.
- `ui/tabs.tsx` (Radix) jest w tej zmianie importowany wyłącznie przez `person-modal.tsx` (wg grep badacza frontendu); listy osób/źródeł używają `SegmentedToggle`, nie `Tabs`.
- Edycja jest per sekcja, nie per panel: `SectionCard` przełącza widok/formularz w `OverviewTab`; stan w `SectionEditorProvider` (`resources/js/components/persons/section-editor.tsx`). `SectionId` to zamknięta unia `'personal' | 'birth' | 'death' | 'residences' | \`marriage-${number}\`` (`section-editor.tsx:14-19`), a `labelFor` (`:49-70`) zwraca miejscownik do paska „Masz niezapisane zmiany w …”. Edytor notatki zintegrowany ze strażnikiem wymaga nowego `SectionId` i nowego przypadku w `labelFor`.
- Nawigacja do krewnych: `PersonPreviewLink` (`resources/js/components/persons/person-preview-link.tsx`) używany w `FamilyTab`; podmienia zawartość panelu (`replace`, `preserveState`) i przechodzi przez `requestExit`, gdy sekcja jest brudna. Wiersze wydarzeń dzieci/małżonka mogą go ponownie użyć. Panel małżeństwa otwiera się dziś tylko z zakładki `Rodzina` (przycisk „Szczegóły małżeństwa…”).
- Szerokość: wariant `standard` to `md:w-[clamp(24rem,30vw,36rem)]` (`resources/js/components/side-panel.tsx:213-215`). Przy 1366 px to ok. 410 px; S-06 sprawdzało ręcznie tylko 3 zakładki (`context/archive/2026-10-05-person-side-panel/research.md:160`). Czy 5 triggerów (z licznikami) mieści się w `TabsList className="w-full sm:w-fit"` (`person-modal.tsx:58`), nie zostało zmierzone.

### 2. Skąd panel bierze dane

- `personPreview` to closure we współdzielonych propsach (`HandleInertiaRequests.php:60`), rozwiązywana z `?person=<id>` przez `previewPerson()` (`:87`) — `findOrFail` w drzewie użytkownika + `Gate::authorize('view')`; cudza osoba to 404 na każdej stronie hosta. Nie ma osobnej trasy `persons.show`.
- `BuildPersonPreview::handle` (`BuildPersonPreview.php:27`) ładuje: `events.sources`, `events.locality`, `parents.events`, `children.events`, `marriages.sources`, `marriages.locality`, `marriages.participants.events`, `residences.locality`, `gravestones.cemetery`; zwraca `person` (`PersonResource`), `personForm` (`PersonFormResource`), `genders`, `religions`, `isRootPerson`.
- Co jest w payloadzie dziś (wg `PersonResource` / typów `person.ts`): `birth`, `death` (z `date_parts`, `place`, źródłami), `burial_place`, `age`, `parents`/`children` jako `Relative` (tylko `birth_date`/`death_date` jako tekst), `marriages` (`MarriageResource` z datą małżeństwa jako wydarzeniem i `spouse` z tekstowymi datami), `residences`, `gravestones`. Typ TS `PersonEvent.type` to `'birth' | 'death' | 'marriage'` (`person.ts:16`) — bez `burial`.
- Wniosek: wszystkie potrzebne wiersze `events` są już ładowane jednym `load`; do listy wydarzeń z miejscami trzeba dopisać `children.events.locality` i `marriages.participants.events.locality` oraz zbudować na serwerze posortowaną listę (albo dodać `date_parts` do `Relative`/`spouse`).

### 3. Model wydarzeń i daty częściowe

- `EventType` (`app/Enums/EventType.php:7-10`): Birth, Death, Marriage, Burial; `acceptsSources()` false tylko dla Burial (`:24-27`); etykiety PL „Urodzenie”, „Zgon”, „Małżeństwo”, „Pochowanie” (`:12-20`).
- Relacje w `app/Models/Person.php`: `events()` (`:69`), `parents()` (`:92`), `children()` (`:102`), `marriages()` (`:112`), `birth()`/`death()`/`burial()` (`:117-127`, czytają załadowaną kolekcję `events`).
- Dzieci przez `parent_child_relations` (bez `marriage_id`) — dzieci osoby nie są przypisane do konkretnego małżeństwa.
- Daty: `events.date_year`, `date_month`, `date_day` nullable + `date_is_approximate`; `Event::date` zwraca `App\Support\PartialDate` z `format()` (`dd.mm.yyyy` / `mm.yyyy` / `yyyy`, prefiks `ok. `). Frontend: `formatPartialDate(parts, 'short' | 'long')` w `resources/js/lib/partial-date.ts`.
- Pochowanie nie ma daty (zapisywane tylko z miejscem w `UpdatePerson`), więc przy sortowaniu „bez daty na końcu” zawsze trafiłoby na koniec.
- Zgon może istnieć bez daty („data nieznana”) — taki wiersz też idzie na koniec.
- Sortowanie: `PersonOrdering::marriageSortKey` (`PersonOrdering.php:123-132`) daje: znany rok przed brakiem roku, potem rok/miesiąc/dzień rosnąco (brak miesiąca/dnia przed znanym w tym samym roku), potem `id`. Flaga „około” nie wpływa na klucz. Ten sam porządek stosuje `PersonOrdering::marriages` (`:40-45`).
- Deduplikacja: małżeństwo osoby to wiersz z jej `events`; „ślub małżonka” z FR-021 to ten sam wiersz, więc lista nie może dodawać go drugi raz ze strony małżonka.

### 4. Notatka osoby — zapis

- Brak kolumny: migracje `people` (`database/migrations/2026_09_23_045722_create_people_table.php`, dodatki `gender`, `maiden_name`) nie mają `note`; jedyne `note` w migracjach to `sources.note` (`database/migrations/2026_09_30_072040_add_description_to_sources_table.php:22`). Potwierdza to `context/archive/2026-10-06-fact-places-from-gazetteer/research.md:169` („dziś notatki osoby nie ma”).
- Ścieżka zapisu osoby: `PUT persons/{person}` → `PersonController::update` → `PersonUpdateRequest` (rozszerza `PersonStoreRequest`, reguły od `PersonStoreRequest.php:22`, `maiden_name` w `:41`, etykieta `:74`) → `UpdatePerson::handle` (`UpdatePerson.php:55-61`, `$data[...] ?? null`) → toast + `redirect()->back()`.
- Frontend: `personFormToPayload(personForm)` (`person-payload.ts:119`) jako baza + `mergeSectionIntoPayload` (`:149`) nakłada edytowaną sekcję (`PersonSection = 'personal' | 'birth' | 'death' | 'residences'`, `:13`). Zapis przez `useSectionSave` (`resources/js/hooks/use-section-save.ts`).
- Dwie drogi dla notatki (obie zgodne z konwencją Form Request → Action):
  - **A. przez `PUT persons/{id}`**: kolumna + `#[Fillable]` + reguła w `PersonStoreRequest` + `UpdatePerson` + `PersonFormResource`/`PersonResource` + `PersonPayload.note` w `personFormToPayload` + nowa `PersonSection` i `SectionId`. Ryzyko: każde przyszłe pole musi trafić do payloadu (lekcja z `context/archive/2026-10-05-maiden-name-and-age/reviews/impl-review.md` F1); test wzorcowy `PersonsTest.php:1393`.
  - **B. osobny endpoint** (np. `PUT persons/{person}/note` z własnym Form Request i Action): notatka nie zależy od pełnego payloadu, ale to nowa trasa (Pest: happy path + 404 cudzej osoby, wpis w `WriteTargetsTest`/`OwnershipBoundariesTest`).
- Wzorzec walidacji: `['nullable', 'string', 'max:5000']`, etykieta `notatka` (`SourceDescriptionValidationRules.php:28,66`); `localities.description` ma ten sam limit. Testy wzorcowe: `tests/Feature/SourcesTest.php:553-566` (wieloakapitowy tekst), `:606` (5001 znaków → błąd). PRD nie podaje limitu — 5000 to analogia, nie wymóg.
- UI: `resources/js/components/ui/textarea.tsx` (`field-sizing-content`); użycia: `components/localities/locality-panel.tsx`, `components/sources/source-description-fields.tsx`.
- Kopie zapasowe: notatka w kolumnie `people` jest objęta zrzutem bazy bez nowej pracy (`context/foundation/roadmap.md:84`).

### 5. Testy

- Pest: kształt podglądu asertowany w `tests/Feature/PersonsTest.php` (m.in. `:1393`), `tests/Feature/EventSourcesTest.php`, `tests/Feature/DeleteImpactTest.php` (helper `personPreviewUrl()`); ochrona granic: `OwnershipBoundariesTest.php`, cele zapisu: `WriteTargetsTest.php`.
- Vitest działa tylko na czystych funkcjach (`resources/js/lib/*.test.ts`, brak testów komponentów) — sortowanie/scalanie po stronie klienta byłoby testowalne tylko jako czysta funkcja w `lib/`; sortowanie na serwerze testuje się Pestem (wzór: testy porządku małżeństw w `tests/Feature/MarriagesTest.php`).
- E2E (`tests/e2e/`): `person-side-panel.spec.ts`, `person-panel-unsaved-guard.spec.ts`, `person-panel-page-navigation-guard.spec.ts`, `person-panel-show-on-tree.spec.ts` i specy sekcji. Żaden spec nie klika bezpośrednio triggerów zakładek, a strażnik `requestExit` przy zmianie zakładki nie ma dedykowanego testu (wg badacza frontendu).

## Code References

- `resources/js/components/persons/person-modal.tsx:27-87` — host zakładek panelu osoby (Przegląd/Rodzina/Źródła), strażnik zmiany zakładki
- `resources/js/components/persons/section-editor.tsx:14-19,49-70` — `SectionId` i `labelFor`
- `resources/js/components/side-panel.tsx:213-215` — szerokość wariantów panelu
- `resources/js/lib/person-payload.ts:13,119,149` — `PersonSection`, `personFormToPayload`, `mergeSectionIntoPayload`
- `resources/js/types/person.ts:14-25` — `PersonEvent` z `date_parts`
- `app/Http/Middleware/HandleInertiaRequests.php:60,87` — prop `personPreview` i `previewPerson()`
- `app/Actions/Persons/BuildPersonPreview.php:27` — eager-load panelu
- `app/Http/Resources/RelativeResource.php:30-37` — krewny: tylko tekstowe daty
- `app/Http/Resources/MarriageResource.php:43-52` — małżonek: tylko tekstowe daty
- `app/Enums/EventType.php:7-27` — typy wydarzeń, etykiety, `acceptsSources`
- `app/Models/Person.php:69-127` — relacje i akcesory wydarzeń
- `app/Support/PersonOrdering.php:40-45,123-132` — sortowanie małżeństw „bez daty na końcu”
- `app/Actions/Persons/UpdatePerson.php:55-61` — pełne zastąpienie pól osoby
- `app/Http/Requests/PersonStoreRequest.php:22,41,74` — reguły i etykiety
- `app/Concerns/SourceDescriptionValidationRules.php:28,66` — wzorzec reguły notatki
- `tests/Feature/PersonsTest.php:1393` — test zachowania pola przy zapisie innej sekcji

## Architecture Insights

- Panele są hostowane w layoucie, a dane idą przez współdzielony prop rozwiązywany z query stringu — nowe zakładki nie potrzebują nowych tras odczytu, tylko rozszerzenia `BuildPersonPreview`/Resources.
- Porządek list jest liczony na serwerze (`PersonOrdering`) i podawany gotowy; zgodnie z tym lista wydarzeń najpewniej powinna przychodzić posortowana w payloadzie (wniosek z wzorca, nie decyzja).
- Zapis osoby to „pełny formularz + nałożona sekcja” — każde nowe pole osoby jest kosztem w kilku miejscach i pułapką regresji.
- Mapowanie „podsumowania osoby” jest już w trzech kopiach (`BuildMarriagePanel::summary`, `RelativeResource`, `MarriageResource.spouse`) — dług przyjęty w `context/archive/2026-10-09-marriage-side-panel/reviews/` (F4); lista wydarzeń nie powinna dokładać czwartej.

## Historical Context (from prior changes)

- `context/foundation/shape-notes.md:91,93` — źródło wycinka: „zakładka z listą eventów danej osoby (swoje, rodzinne)”, „miejsce na notatki (nowa zakładka?)”.
- `context/foundation/prd.md:161-169` — FR-021 (sort po roku, bez daty na końcu; Socrates o datach częściowych — „kept”), FR-023 (jedno pole; „dziennik badań” odrzucony).
- `context/archive/2026-10-06-fact-places-from-gazetteer/research.md:169` i plan — niepewność miejsca opisywana „w notatce” przesunięta na notatkę osoby z S-14 (FR-010).
- `context/archive/2026-10-06-residence-history/plan.md:47` — zamieszkania poza zakładką rodziny i kartami; nic nie kieruje ich do listy wydarzeń.
- `context/archive/2026-10-05-person-side-panel/plan.md:26` — pasek niezapisanych zmian pyta też przy zmianie zakładki; nowe zakładki muszą zachować `requestExit`.
- `context/archive/2026-10-05-maiden-name-and-age/reviews/impl-review.md` F1 — nowe pole osoby musi być w `personFormToPayload`/`mergeSectionIntoPayload`, z testem w `person-payload.test.ts`.
- `context/archive/2026-10-09-marriage-side-panel/reviews/` — F1: hooki trzymające błędy (`useSectionSave`) nie nad wczesnym `return null` komponentu w layoucie; F4: dług trzech kopii podsumowania osoby (ACCEPTED).
- `context/foundation/test-plan.md:29` (Ryzyko 1: zapis do złej osoby) — zapis notatki powinien wejść do `WriteTargetsTest` i `OwnershipBoundariesTest`.

## Related Research

- `context/archive/2026-10-05-person-side-panel/research.md`
- `context/archive/2026-10-09-marriage-side-panel/research.md`
- `context/archive/2026-10-05-maiden-name-and-age/research.md`
- `context/archive/2026-10-06-fact-places-from-gazetteer/research.md`

## Open Questions

Decyzje produktowe/projektowe nierozstrzygnięte w źródłach (właściciel: użytkownik lub `/10x-plan`):

1. Czy lista wydarzeń obejmuje pochowanie osoby (bez daty → zawsze na końcu) i zamieszkania? FR-021 wymienia „własne” bez rozwinięcia; zamieszkania nie są wydarzeniami w modelu.
2. Czy wydarzenia małżonka obejmują jego urodzenie? FR-021 wymienia tylko ślub i zgon małżonka.
3. Zapis notatki: przez pełny `PUT persons/{id}` (droga A) czy osobny endpoint (droga B)?
4. Edycja notatki: karta z ołówkiem jak sekcje Przeglądu (nowy `SectionId`, strażnik) czy zawsze edytowalne pole z przyciskiem Zapisz?
5. Limit długości notatki (analogia: 5000 znaków) i czy notatka ma być widoczna gdzieś poza zakładką (np. znacznik przy triggerze).
6. Co robi wiersz wydarzenia krewnego: link do panelu tej osoby (`PersonPreviewLink`), do panelu małżeństwa, czy tylko tekst? Czy pokazujemy miejsce i status potwierdzenia źródłem?
7. Kolejność w tym samym roku: wg klucza `marriageSortKey` (miesiąc/dzień, potem id) czy stała kolejność typów (np. urodzenie przed ślubem)?
8. Kolejność i nazwy zakładek oraz czy 5 triggerów mieści się w panelu `standard` przy 1366 px (niezmierzone).

### Decyzje użytkownika (2026-10-10)

- **Pyt. 1–2 (zakres listy):** poza minimum FR-021 lista obejmuje **pochowanie osoby** (bez daty → na końcu, z miejscem pochowania). **Bez** zamieszkań i **bez** urodzenia małżonka.
- **Pyt. 3 (zapis notatki):** **osobny endpoint** (droga B, np. `PUT persons/{person}/note` z własnym Form Request i Action) — notatka nie wchodzi do pełnego payloadu `PUT persons/{id}`.
- **Pyt. 4 (edycja):** **karta z ołówkiem** jak sekcje Przeglądu — podgląd, ołówek otwiera pole, Zapisz/Anuluj, nowy `SectionId` z wpisem w `labelFor`, więc strażnik niezapisanych zmian obejmuje zamknięcie panelu i zmianę zakładki.
- **Pyt. 6 (wiersz krewnego):** imię dziecka/małżonka jest **linkiem do panelu tej osoby** (`PersonPreviewLink`, podmiana panelu), wiersz pokazuje datę i miejsce; bez znacznika potwierdzenia źródłem.
- Pozostają dla `/10x-plan` (domyślne, nieblokujące): pyt. 5 — limit notatki (analogia 5000 znaków, etykieta „notatka”); pyt. 7 — kolejność w tym samym roku (domyślnie klucz `marriageSortKey`); pyt. 8 — kolejność/nazwy zakładek i pomiar szerokości przy 1366 px.
