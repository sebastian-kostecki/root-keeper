# Panel małżeństwa (S-12) — Plan Brief

> Full plan: `context/changes/marriage-side-panel/plan.md`

## What & Why

Autor otwiera obok drzewa panel małżeństwa. Panel pokazuje małżonków, datę i miejsce ślubu z edycją, źródła ślubu z pełną edycją powiązań i wspólne dzieci pary. Małżeństwo można z niego też usunąć. Edycja małżeństwa zostaje także w panelu osoby, bo PRD stawia wygodę ponad jedno miejsce edycji. To wycinek S-12 (FR-017) i czwarte użycie wspólnej powłoki panelu.

## Starting Point

- Małżeństwo to zdarzenie `events` typu `marriage` z dwoma uczestnikami `spouse`. Ma miejsce z `localities` i źródła przez `event_source`.
- Zapisy (data i miejsce, źródła, usunięcie) już działają i wracają przez `back()`.
- Panel osoby żyje w layoucie z globalnym resolverem `?person=`. Jego strażnik niezapisanych zmian jest przypięty do osoby.
- Na drzewie węzeł związku jest menu, ale nie zna id małżeństwa.
- Dzieci nie mają `marriage_id`.

## Desired End State

- **Panel:** `?marriage={id}` na każdej stronie otwiera panel `standard` „Jan Kowalski i Anna Kowalska z d. Nowak”. Pokazuje małżonków, kartę „Ślub” edytowaną jak w panelu osoby, „Źródła ślubu” i „Dzieci”. „Usuń małżeństwo” jest w nagłówku.
- **Wejścia:** pozycja „Szczegóły małżeństwa” w menu węzła związku i link w wierszu małżeństwa w Rodzinie.
- **Ochrona:** niezapisana data ślubu jest chroniona tym samym paskiem co sekcje osoby.
- **Granice:** cudze małżeństwo daje 404, a przecentrowanie drzewa nie zamyka panelu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Wejścia | Menu węzła związku (tylko ślub) + link w Rodzinie panelu osoby | Pokrywa pytanie z roadmapy; „Potwierdza” w panelu źródła zostaje bez zmian. |
| Dzieci pary | Tylko wspólne (oboje małżonkowie są rodzicami) | Ten sam zbiór co dzieci związku na drzewie (FR-014). |
| Edycja daty i miejsca | Jak w panelu osoby: widok, „Edytuj”, `SectionCard` | To samo małżeństwo edytuje się identycznie w obu panelach. |
| Działania w panelu | „Usuń małżeństwo” w nagłówku; małżonkowie i dzieci jako linki | Pełna praca nad małżeństwem bez nowych zapisów; dziecko dodaje się z drzewa. |
| Kolejność w tytule | Mężczyzna pierwszy; inne pary po dacie urodzenia, potem id | Układ zwyczajowy, stały niezależnie od wejścia, stabilne lokatory. |
| Testy | Pest (resolver, dzieci, 404, przekierowanie, `marriage_id`) + jeden spec E2E | Pest pilnuje złej pary, E2E pilnuje uogólnionego strażnika. |
| Zasięg resolvera | Globalny w `HandleInertiaRequests`, panel w layoucie | Wejście z panelu osoby musi działać na każdej stronie, na której ten panel żyje. |
| Strażnik | `SectionEditorProvider` uogólniony z `personId` na `{ key, id }` | Jeden pasek i jeden globalny nasłuch `before` dla obu paneli. |
| Po usunięciu | `destroy` przekierowuje na Referer bez `marriage` (fallback `tree.index`) | `back()` wróciłby na adres usuniętego panelu, czyli 404. |
| Przecentrowanie | Karta zachowuje `?marriage=` jak `?person=` | Panel nie znika przy pracy na drzewie, spójnie z S-06. |

## Scope

**In scope:**
- `EventPolicy::view`, `PersonOrdering::spouses`, akcja `BuildMarriagePanel`, resolver `marriagePanel`, `marriage_id` w `SheetUnion`, przekierowanie `destroy`
- `PANEL_PROPS.marriage`, uogólniony strażnik, wydzielone `SourceGroup`, `Field` i żądanie zapisu małżeństwa
- `MarriagePanel` w layoucie, `useMarriagePanel`, `MarriagePanelLink`, dialog usuwania w nagłówku
- Wejścia z węzła związku i z Rodziny; karta drzewa zachowuje panel
- Pest (`MarriagesTest`, `OwnershipBoundariesTest`, `TreeTest`), Vitest rejestru, spec E2E przez `/10x-e2e`

**Out of scope:**
- Wejście z „Potwierdza” i z karty Przeglądu panelu osoby
- Dzieci z innych związków; dodawanie dziecka z panelu
- Świadkowie, rozwód, typ małżeństwa, zmiana małżonka; zmiany schematu
- Synchronizacja panelu z przełączeniem związku na drzewie

## Architecture / Approach

Wzorzec F-02: `?marriage=` → współdzielona leniwa właściwość `marriagePanel` (404 przez `findOrFail` na zdarzeniach typu `marriage` w drzewie użytkownika i `Gate::authorize('view')`) → częściowe przeładowanie z `only` wszystkich paneli. Layout montuje `MarriagePanel` obok `PersonPanel` i podaje strażnikowi rekord otwartego panelu. Zapisy zostają w istniejących kontrolerach. Ich `back()` niesie `?marriage=` w Refererze, więc panel odświeża się sam.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend panelu małżeństwa | Resolver z 404, małżonkowie i wspólne dzieci, `marriage_id`, przekierowanie po usunięciu, Pest | Zły zbiór dzieci albo zła para w panelu |
| 2. Uogólniony strażnik i panel | Panel w layoucie z edycją, źródłami, dziećmi i usuwaniem; jeden strażnik dla obu paneli | Regresja strażnika panelu osoby |
| 3. Wejścia do panelu | Menu węzła, link w Rodzinie, przecentrowanie zachowuje panel | Pozycja menu przy związku bez ślubu |
| 4. E2E panelu małżeństwa | Spec: węzeł → panel → dzieci → strażnik, z celowymi błędami | Niestabilność przy wspólnym drzewie (sufiksy) |

**Prerequisites:** S-06 (panel osoby) i F-02 (powłoka) — `done`. Sail i serwer E2E (`scripts/e2e-server.sh`) działają lokalnie.
**Estimated effort:** ok. 2–3 sesje w 4 fazach (faza 2 największa).

## Open Risks & Assumptions

- Uogólnienie strażnika dotyka kodu, od którego zależą trzy spece E2E panelu osoby. Faza 2 uruchamia je bez zmian w specach jako bramkę.
- Otwarty panel małżeństwa nie podąża za przełączeniem związku na drzewie. Przyjęte świadomie, sprawdzane ręcznie.
- Kształt żądania helpera „dziecko pary” w E2E trzeba ustalić z istniejących Form Requestów (`persons.children.*`).

## Success Criteria (Summary)

- Z drzewa i z panelu osoby autor otwiera panel właściwej pary z samymi wspólnymi dziećmi i edytuje w nim datę, miejsce i źródła ślubu.
- Niezapisana data ślubu nie ginie po cichu, a usunięcie małżeństwa z panelu nie kończy się stroną 404.
- Cudze `?marriage=` daje 404 na każdej stronie, a panel osoby działa jak przed zmianą.
