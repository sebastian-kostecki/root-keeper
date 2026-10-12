---
date: 2026-10-12T15:06:32+02:00
researcher: Claude (Opus 5.5)
git_commit: 37eea37
branch: develop
repository: root-keeper
topic: "Czy FamilySheetData ma rok ślubu par rodziców i dziadków; co dodać (+ test Pest 404) pod linie drzewa i węzeł związku"
tags: [research, tree, family-sheet, union-node, design-system, tokens]
status: complete
last_updated: 2026-10-12
last_updated_by: Claude (Opus 5.5)
---

# Research: rok ślubu par rodziców i dziadków w arkuszu rodziny

**Date**: 2026-10-12T15:06:32+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 37eea37
**Branch**: develop
**Repository**: root-keeper

## Research Question

Czy `FamilySheetData` (typ `FamilySheet` z `resources/js/types/tree.ts`) niesie rok ślubu par rodziców i dziadków, potrzebny węzłowi rodziców z design systemu (obrączki + rok)? Jeśli nie — co dodać po stronie serwera i jaki test Pest (z przypadkiem 404 dla cudzego drzewa). Kontekst: zakres zmiany w `change.md` (tokeny grubości, `MarriageRingsIcon`, `UnionNode`, linie `family-sheet.tsx`, listwa płci).

## Summary

**Nie.** Arkusz ma rok ślubu tylko dla związków osoby w centrum (`unions[].marriage_year`, `app/Actions/Trees/BuildFamilySheet.php:103-112`). Dla par rodziców jest jedynie `unmarried_parents: {focus: bool, partner: bool}` (`BuildFamilySheet.php:170`, `181-222`) — flaga „dwie znane osoby bez ślubu”, bez roku i bez rozróżnienia „ślub” / „nie wiadomo, bo brak rodzica”. Dla czterech par dziadków arkusz nie ma żadnej informacji o związku. Makieta (`Main.dc.html`) pokazuje rok ślubu na węźle rodziców („1861”) **i** na węźle pary dziadków („1828”), więc dane są potrzebne dla 2 par rodziców + 4 par dziadków.

**Korekta założenia:** arkusz nie ma API Resource. Buduje go akcja `BuildFamilySheet::handle()` jako tablicę (`BuildFamilySheet.php:53-172`), przekazywaną wprost do `Inertia::render('tree/show', ['sheet' => …])` (`app/Http/Controllers/TreeController.php:26`, `45`). Jedynym Resource w tej ścieżce jest `SheetPersonResource` dla pojedynczej osoby (`BuildFamilySheet.php:480-486`). Rozszerzenie należy więc do akcji (+ typ TS w `types/tree.ts` i PHPStan `@phpstan-type` w nagłówku akcji), nie do Resource.

**Koszt danych: zero nowych zapytań.** `loadPeople()` ładuje z `events` osobę w centrum, partnera, ich rodziców i rodziców tych rodziców (`BuildFamilySheet.php:328-343`), a `Person::events()` to `BelongsToMany` przez `event_participants` (`app/Models/Person.php:72-77`). Małżeństwo pary to część wspólna ich zdarzeń typu `Marriage`; rok daje `$event->date->formatYear()` (`app/Support/PartialDate.php:36-46`, użyte już w `BuildFamilySheet.php:104`). Test `the sheet query count does not grow with the size of the tree` (`tests/Feature/TreeTest.php:512-549`) pilnuje, żeby tak zostało.

**Test 404:** `TreeController::show` autoryzuje `Gate::authorize('view', $person)` (`TreeController.php:40`), a `PersonPolicy::view` zwraca `denyAsNotFound()` dla cudzego drzewa (`app/Policies/PersonPolicy.php:14-19`). W `tests/` (przeszukane `tree.show` we wszystkich plikach) **nie ma** testu, że `GET tree.show` z osobą z innego drzewa daje 404 — jest tylko dla `tree.ancestors` (`tests/Feature/AncestorsTest.php:233-240`) i `tree.proband.attach` (`tests/Feature/TreeProbandTest.php:68-77`). Wzorzec do skopiowania: `AncestorsTest.php:233-240`.

## Detailed Findings

### Co arkusz niesie dziś (kontrakt `FamilySheet`)

- Typ TS: `resources/js/types/tree.ts:56-68` — `focus`, `unions`, `selected_union`, `partner`, `focus_side`, `parents: {focus, partner}` (po 2 sloty), `grandparents: {focus, partner}` (po 4 sloty), `children`, `add_child`, `add_spouse`, `unmarried_parents: {focus: boolean; partner: boolean}`.
- `SheetUnion` (`tree.ts:46-54`) ma `is_marriage`, `marriage_id`, `marriage_year` — tylko dla związków osoby w centrum. Rok to `string | null`, z prefiksem „ok. ” dla daty przybliżonej (`PartialDate.php:43-45`).
- `unmarried_parents[block]` jest `true` tylko, gdy oba sloty rodziców to osoby (`kind: 'person'`) i żadne zdarzenie `Marriage` w drzewie nie łączy obojga (`BuildFamilySheet.php:185-221`). `false` znaczy zarówno „po ślubie”, jak i „nie da się ocenić” (brak rodzica / brak partnera) — test `TreeTest.php:695-712`. Do węzła rodziców to za mało: potrzebne „po ślubie + rok”.
- Kolejność slotów: rodzice — mężczyzna lewy, kobieta prawa (`BuildFamilySheet.php:392-422`); dziadkowie — `[ojciec ojca, matka ojca, ojciec matki, matka matki]`, para = indeksy `[0,1]` i `[2,3]`, puste sloty nad zaślepką (`BuildFamilySheet.php:429-440`). Frontend rysuje łączniki dziadków dokładnie po tych parach (`family-sheet.tsx:368-379`: `slice(start, start + 2)` dla `start ∈ {0, 2}`).

### Co dodać po stronie serwera (propozycja do planu)

- Nowe pole w tablicy zwracanej przez `BuildFamilySheet::handle()`, wyrównane do istniejących łączników, np.:
  - `parent_marriages: { focus: PairMarriage | null; partner: PairMarriage | null }`
  - `grandparent_marriages: { focus: [PairMarriage | null, PairMarriage | null]; partner: [...] }`
  - gdzie `PairMarriage = { year: string | null }` istnieje tylko, gdy oba sloty pary to osoby i łączy je zdarzenie `Marriage`; `null` = brak węzła (bez ślubu albo niepełna para — design system w obu przypadkach nie rysuje węzła, `UnionNode/README.md` „Węzeł rodziców”).
- Wyliczenie z już załadowanych `$this->people[...]->events` (filtrowanych po `EventType::Marriage`), część wspólna id. Przy kilku ślubach tej samej pary — najwcześniejszy przez `PersonOrdering::marriages()` (`app/Support/PersonOrdering.php:65-70`), spójnie z zasadą dla `unions` (test `TreeTest.php:223-235`, „repeat marriages … labelled with the earliest marriage”).
- Uczestnicy małżeństwa to wyłącznie role `ParticipantRole` ∈ {`Subject`, `Spouse`} (`app/Enums/ParticipantRole.php`), a zapytanie `unmarriedParents` też nie filtruje roli — część wspólna zdarzeń `Marriage` dwóch osób oznacza ich ślub.
- Do decyzji w planie: czy `unmarried_parents` zostaje (steruje przyciskiem „Połącz jako małżeństwo”, `family-sheet.tsx:158-174`), czy da się go wyprowadzić z nowego pola po stronie klienta. Z nowym polem „para dwóch osób i `parent_marriages[block] === null`” daje tę samą informację — ale usunięcie pola zmienia kontrakt i testy `TreeTest.php:695-728`.
- Aktualizacje towarzyszące: `@phpstan-type` w nagłówku akcji (`BuildFamilySheet.php:15-26`) i `@return` `handle()` (`:38-52`) — Larastan poziom 7; typ TS w `tree.ts:56-68`.

### Testy Pest

- Dom testów: `tests/Feature/TreeTest.php` (helper `sheetFor()` w `:95-102`, pomocnicy `attachMarriage()` / `linkParent()` w `tests/Pest.php:54-80`).
- Przypadki dla nowego pola: para rodziców po ślubie z rokiem; ślub bez daty (`year: null`); para bez ślubu → `null`; jeden rodzic nieznany → `null`; para dziadków (np. rodzice ojca) z rokiem i pozostałe pary `null`; blok partnera; dwa śluby tej samej pary → najwcześniejszy rok; data przybliżona → „ok. 1828”.
- 404: `GET route('tree.show', $foreign)` dla `Person::factory()->create()` z innego drzewa → `assertNotFound()` (wzorzec `AncestorsTest.php:233-240`). Endpoint nie jest nowy, ale ten przypadek nie jest dziś pokryty, a CLAUDE.md wymaga go dla endpointów drzewa.
- Licznik zapytań (`TreeTest.php:512-549`) musi zostać zielony bez zmian.

### Frontend — stan wyjściowy dla zakresu zmiany

- **`union-node.tsx`** (pokryty przez `view-tokens.test.ts:42`): trigger `rounded-lg h-8.5 border` (`:110`); stany tekstowe już są — „ślub 1890”, „ślub, data nieznana”, „bez ślubu” (`unionStatus`, `:37-49`), „1 z 2” (`:74-75`, `:116-120`), „Związki” gdy wybrany związek z nieznanym partnerem i jest jedyny (`:121-127`). `aria-label` „Związek 1 z 2: …” (`:97-99`). Przy `unions.length === 0` komponent zwraca `null` (`:67-69`) — wtedy na arkuszu nie ma węzła ani linii pary (`family-sheet.tsx:411-416` rysuje linię tylko dla `partner.kind !== 'empty'`). Dane centrum (`is_marriage`, `marriage_year`) wystarczają do wszystkich stanów pigułki — zmiana serwera dotyczy tylko węzłów rodziców/dziadków.
- **`family-sheet.tsx`** (NIE jest w `VIEW_FILES` — `view-tokens.test.ts:16-18` pomija go celowo z powodu geometrii runtime, `style=` w `:29-35`, `:245-246`, `:347-349`, `:426`):
  - `PairConnector` (`:124-156`): jeden element-nawias `inset-x-[25%] h-3 rounded-b-md border-x border-b` + pień `w-px` — nie da się z niego zrobić jednego ramienia przerywanego, więc trzeba go rozbić na dwa ramiona (zgodnie z `implementation.md`: `rounded-bl-xl` / `rounded-br-xl`).
  - Akcja „Połącz jako małżeństwo” siedzi w środku łącznika rodziców (`:149-153`, `:397-404`), tam gdzie DS stawia węzeł rodziców. Nie ma konfliktu: przycisk jest tylko dla pary bez ślubu, węzeł tylko dla pary po ślubie.
  - Linia pary `h-px` (`:411-416`), pień do dzieci `w-px` (`:466-478`), belka dzieci z segmentów `h-px w-1/2` i pionów `w-px h-3` (`:258-273`). Brak przerywanego odgałęzienia do slotu „Dziecko” (slot stoi obok belki, `:278-289`).
  - Blok osoby w centrum to `block === 'focus'` niezależnie od strony (`order`, `:302-305`) — po tym kluczu wybiera się linię przodków w `primary`.
- **`sheet-person-card.tsx`** (pokryty, `view-tokens.test.ts:40`): `<img className="absolute inset-0 size-full …">` (`:54`) na pasku `stripGender` (`:29-33`, `:42-47`) — zmiana na `inset-x-0 top-0 bottom-1` odsłoni listwę 4 px w kolorze płci; `size-full` trzeba wtedy usunąć (inaczej `height: 100%` wygra z `bottom-1`).
- **Zaślepki**: `placeholderAction` ma `border-dashed border-tree-line` (`card-styles.ts:34-38`); statyczna zaślepka „Nieznany partner” bez akcji to `placeholderStatic` `bg-muted border-transparent` (`card-styles.ts:30-31`) — nie jest przerywana.

### Tokeny i Tailwind v4

- `app.css` ma `--tree-line` / `--tree-meta` w `:root` (`:115-116`), `.dark` (`:158-159`) i `@theme` (`:70-71`); nie ma `--tree-line-width` ani `--lineage-line-width`.
- `tree-corner-radius = 0.75rem` (DS `tokens.json`, `layout`) = domyślne `rounded-xl` (DS `radius-xl` „domyślny Tailwind, nie nadpisany”); `app.css` nie nadpisuje `--radius-xl` (`:17-19`) — nowy token promienia niepotrzebny.
- Tailwind 4.3.3 (`node_modules/tailwindcss/package.json`) rozwiązuje `border-<nazwa>` najpierw jako kolor (`--border-color`, `--color`), potem jako grubość z przestrzeni `--border-width-*` (`node_modules/tailwindcss/dist/lib.js`, handler `border`). Wniosek: `--border-width-tree-line` zderzyłoby się z kolorem `border-tree-line`; grubość potrzebuje innej nazwy w `@theme` (np. `--border-width-line: var(--tree-line-width)` → `border-line`, `border-x-line`?) albo składni `border-(length:--tree-line-width)` / `w-(--tree-line-width)`. Repo używa już `w-(--…)` (`components/ui/sidebar.tsx:169`). Czy `border-x-*`/`border-t-*` też czytają `--border-width-*` — do potwierdzenia w planie (sprawdzone tylko `border`).
- Reguła „arbitrary pixel value” w `view-tokens.test.ts` łapie `-[Npx]` (całkowite px, `:85-88`); `[1.5px]` nie pasuje do `\d+px` (kropka), więc test tej wartości **nie złapie** — zakaz `[1.5px]` w widokach trzeba utrzymać przeglądem albo poszerzyć wzorzec.

## Code References

- `app/Actions/Trees/BuildFamilySheet.php:53-172` — `handle()`, kształt arkusza
- `app/Actions/Trees/BuildFamilySheet.php:103-112` — rok ślubu tylko dla związków centrum
- `app/Actions/Trees/BuildFamilySheet.php:181-222` — `unmarriedParents()`, jedno zapytanie, tylko pary rodziców
- `app/Actions/Trees/BuildFamilySheet.php:328-343` — `loadPeople()` z `events` dla rodziców i dziadków
- `app/Actions/Trees/BuildFamilySheet.php:392-440` — sloty rodziców i dziadków (kolejność par)
- `app/Http/Controllers/TreeController.php:20-51` — `index`/`show`, `Gate::authorize('view')` w `:40`
- `app/Policies/PersonPolicy.php:14-19` — `denyAsNotFound()`
- `app/Support/PersonOrdering.php:65-70` — kolejność małżeństw
- `app/Support/PartialDate.php:36-46` — `formatYear()` z „ok. ”
- `resources/js/types/tree.ts:46-68` — `SheetUnion`, `FamilySheet`
- `resources/js/components/tree/family-sheet.tsx:124-156` — `PairConnector`
- `resources/js/components/tree/family-sheet.tsx:406-478` — linia pary, węzeł, pień
- `resources/js/components/tree/family-sheet.tsx:237-292` — belka dzieci
- `resources/js/components/tree/union-node.tsx:37-132` — stany i trigger pigułki
- `resources/js/components/tree/sheet-person-card.tsx:36-66` — `PhotoStrip`
- `resources/js/components/tree/card-styles.ts:30-41` — zaślepki
- `resources/js/lib/view-tokens.test.ts:16-53` — `VIEW_FILES` (bez `family-sheet.tsx`)
- `tests/Feature/TreeTest.php:95-102`, `:512-549`, `:695-728` — helper, licznik zapytań, `unmarried_parents`
- `tests/Feature/AncestorsTest.php:233-240` — wzorzec testu 404

## Architecture Insights

- Arkusz drzewa to kontrakt „stały kształt + stała liczba zapytań”: wszystko liczone z grafu (`ParentChildGraph`) i jednej paczki osób z `events`. Nowe dane o parach powinny iść tą samą drogą, nie przez nowe zapytania czy relacje ładowane per para.
- Dane dla łączników są wyrównane do slotów (bloki `focus`/`partner`, pary dziadków po indeksach 0-1 i 2-3) — nowe pole najprościej trzymać w tym samym układzie, żeby `PairConnector` dostał je obok `slots`.
- Węzeł rodziców jest dekoracyjny (`aria-hidden`); rok jest dostępny też w panelu małżeństwa — nowe pole nie wymaga `marriage_id`, chyba że plan zechce klikalny węzeł (DS mówi „bez akcji”).

## Historical Context (from prior changes)

- `context/archive/2026-10-12-design-tokens/plan.md:40` — odłożone do osobnej zmiany: `MarriageRingsIcon`, `union-node.tsx`, linie `family-sheet.tsx` (belka, przodkowie `primary` 2 px), `--tree-line-width`, listwa płci w `PhotoStrip`. Wspierane: to jest ta zmiana.
- `context/archive/2026-10-12-design-tokens/plan.md:42` — „`family-sheet.tsx` i `ancestor-chart.tsx` w `VIEW_FILES` — geometria liczona w runtime; wejdą razem ze zmianą linii drzewa”. Częściowo sprzeczne z notatką w `change.md` („`view-tokens.test.ts` obejmuje już components/tree”): obejmuje karty, węzeł i style (`view-tokens.test.ts:39-47`), ale **nie** `family-sheet.tsx` (`:16-18`), gdzie powstaną linie.
- `context/archive/2026-10-12-design-tokens/plan.md:275` — dodane wtedy pliki drzewa do `VIEW_FILES` i wyjątek `style={` dla `source-panel.tsx`; dla `family-sheet.tsx` analogiczny wyjątek `style={` (geometria kolumn) byłby potrzebny.

## Related Research

- `context/archive/2026-10-12-design-tokens/` — tokeny kolorów i fontu (wejście tej zmiany).
- `context/archive/2026-09-29-tree-sheet-variant-c/plan.md` — pierwotny układ arkusza (nie czytany w całości; nie wpływa na odpowiedź).

## Decisions (user, 2026-10-12)

Rozstrzygnięcia pytań 1–4 poniżej (AskUserQuestion po badaniu):

- **D1 (pyt. 1):** stan „Związki” obejmuje też arkusz bez żadnego związku — `UnionNode` renderuje się przy `unions = []` (menu z „Dodaj małżonka”), linia pary ciągła od osoby w centrum, przerywana do slotu „dodaj małżonka”.
- **D2 (pyt. 2):** linia do **każdej** zaślepki jest przerywana, także do statycznej „Nieznany partner” (`placeholderStatic`).
- **D3 (pyt. 3):** `unmarried_parents` zostaje bez zmian; nowe pola (`parent_marriages`, `grandparent_marriages`) dochodzą obok.
- **D4 (pyt. 4):** `family-sheet.tsx` wchodzi do `VIEW_FILES` z wyjątkiem `style={` (geometria kolumn), a reguła „arbitrary pixel value” zostaje poszerzona o ułamki (`[1.5px]`).

Pytanie 5 (nazwa utility grubości) zostaje dla planu — to wybór techniczny.

## Open Questions

1. **„Brak partnera — Związki” w DS**: dziś pigułka „Związki” pojawia się tylko przy jedynym związku z nieznanym partnerem (`union-node.tsx:121-127`), a przy braku związków węzeł i linia pary nie istnieją (`union-node.tsx:67-69`, `family-sheet.tsx:411`). Czy stan DS (lewa linia ciągła, prawa przerywana do zaślepki) ma objąć też arkusz bez żadnego związku (slot „dodaj małżonka”)? Wtedy `UnionNode` musi renderować się przy `unions = []` (menu z samym „Dodaj małżonka”).
2. **Linia do statycznej zaślepki „Nieznany partner”**: DS każe rysować linię do zaślepki przerywaną; ta zaślepka nie ma przerywanej ramki (`placeholderStatic`). Przerywać linię tylko do zaślepek z akcją, czy do każdej?
3. **Kształt nowego pola**: dwa pola (`parent_marriages`, `grandparent_marriages`) vs jedno zagnieżdżone; czy `unmarried_parents` zostaje.
4. **Strażnik tokenów dla `family-sheet.tsx`**: dodać plik do `VIEW_FILES` z wyjątkiem `style={` (geometria kolumn) — zgodnie z odłożeniem w `design-tokens` — oraz ewentualnie poszerzyć regułę px o ułamki (`[1.5px]`), żeby zakaz z `change.md` był egzekwowany testem.
5. **Nazwa utility grubości** w Tailwind 4.3: `@theme` `--border-width-*` z nazwą niekolidującą z kolorem `tree-line` vs `border-(length:--tree-line-width)`; sprawdzić w planie `border-x-*` / `border-t-*` i `w-(--…)` dla pni.
