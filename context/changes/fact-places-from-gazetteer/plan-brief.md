# Miejsca faktów i pochowania ze spisu (S-07) — Plan Brief

> Full plan: `context/changes/fact-places-from-gazetteer/plan.md`
> Research: `context/changes/fact-places-from-gazetteer/research.md`

## What & Why

Autor wybiera miejsce urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości (podpowiedzi od pierwszej litery, dopisanie w locie) zamiast wpisywać wolny tekst, więc „Luboml” i „Lubomla” przestają być dwoma niepowiązanymi miejscami (FR-010, FR-022). Istniejące tekstowe miejsca faktów od razu wskazują na miejscowości ze spisu. To warunek dla nagrobka (S-09), który podpowiada miejsce pochowania, i dla scalania miejscowości (S-11).

## Starting Point

Miejsce każdego faktu to tekst `events.place`, zapisywany przez jedną regułę i jedno mapowanie we wszystkich przepływach. Spis `localities` z S-01 powstał z tych tekstów, ale ich nie powiązał. Teksty wpisane po S-01 nie są w spisie, a miejsca pochowania nie ma wcale.

## Desired End State

Każde pole „Miejsce” (panel osoby, modal tworzenia, dialogi slotów drzewa, ślub) to picker ze spisu z „Dodaj miejscowość „X””. Sekcja Zgon ma „Miejsce pochowania”. Po migracji każde wydarzenie z tekstem ma `locality_id`. Picker źródła przy fakcie otwiera się z miejscowością i rokiem faktu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Model pochowania | Wydarzenie `EventType::Burial` bez daty | Jedno odwołanie „fakt → miejscowość” dla wszystkich miejsc faktów | Research |
| Przepięcie tekstów | Migracja od razu: dosianie (SQL S-01) + FK po `lower(btrim)` | Istniejące miejsca wskazują na spis zaraz po wdrożeniu | Research |
| Niepewność miejsca | Poza S-07 (notatka osoby w S-14) | S-07 nie zależy od S-14 | Research |
| Próg podpowiedzi | Od pierwszej litery, jak dziś | Spójność z pickerami parafii | Research |
| Gdzie pochowanie w UI | W sekcji Zgon, tylko przy „Osoba nie żyje” | Jedno miejsce edycji w panelu, modalu i dialogach drzewa | Plan |
| Cykl życia pochowania | Wiersz tylko przy wybranej miejscowości; odznaczenie zgonu lub wyczyszczenie usuwa | Brak pustych wydarzeń bez daty i miejsca | Plan |
| Pochowanie a źródła | Wyłączone (404), nie ma go w zakładce „Źródła” | FR-025 obejmuje tylko urodzenie, ślub i zgon; S-09 ma własne powiązanie | Plan |
| Tekst `events.place` | Zamrożony, nieczytany, nienadpisywany | „Kopiuj najpierw, usuwaj później” i jedno źródło prawdy w UI | Plan |
| Filtr miejsca przy źródle | Wypełniany miejscowością faktu | Mała zmiana domykająca US-01 | Plan |
| Lista miejscowości w formularzach | Współdzielony `Inertia::optional` ładowany na żądanie | Formularze żyją na kilku stronach; wzorzec `availableSources` | Plan |
| Kontrakt odczytu | `EventResource.place` = nazwa miejscowości | Konsumenci na froncie bez zmian | Plan |

## Scope

**In scope:**
- `events.locality_id` + migracja przepięcia z własnym testem
- Kontrakt `{prefix}.locality_id` (urodzenie, zgon, ślub) i `burial.locality_id`
- `EventType::Burial`, `Person::burial()`, blokada źródeł dla pochowania
- Wspólny `LocalityPicker` (także w obu formularzach parafii)
- `POST localities/inline` jako „znajdź albo utwórz” (istniejąca nazwa → 200 z tą miejscowością zamiast błędu duplikatu)
- Wypełnianie „Miejsce” w pickerze źródła
- Testy Pest, Vitest, E2E (strażnicy F-01 + nowe ryzyko S-07)

**Out of scope:**
- Usuwanie lub wyświetlanie `events.place`
- Notatka niepewności (S-14), data pochowania, nagrobek jako źródło pochowania (S-09)
- Zamieszkania (S-08), scalanie/usuwanie miejscowości (S-11)
- Składanie diakrytyków, naprawa miejscowości przemianowanych przed S-07

## Architecture / Approach

Migracja dodaje nullable FK `events.locality_id` (`NO ACTION`), dosiewa brakujące miejscowości SQL-em seeda S-01 (tylko z `events.place`) i ustawia FK po `lower(l.name) = lower(btrim(place))`. Backend zmienia regułę i mapowanie w jednym miejscu (`PartialDateValidationRules`, `MapsEventInput`), dodaje pochowanie w `CreatePerson`/`UpdatePerson` i czyta nazwę przez `Event::locality`. Front zastępuje pole tekstowe w `PartialDateInput` komponentem `LocalityPicker` (SuggestField + `useHttp` → `localities/inline` + `useOptionalList('localityOptions')`), a payload pełnego zastąpienia niesie `burial`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Migracja i model | FK, dosianie, przepięcie, test migracji | Zgubiony lub źle dopasowany tekst — chroni test i nietknięta kolumna |
| 2. Backend | Nowy kontrakt zapisu, pochowanie, odczyt z miejscowości, lista opcji | Zmiana kontraktu łamie istniejące testy i wymaga fazy 3 przed wdrożeniem |
| 3. Frontend | Picker we wszystkich formularzach, pochowanie w Zgonie, filtr źródła | Pełne zastąpienie zeruje pochowanie przy edycji innej sekcji |
| 4. E2E (`/10x-e2e`) | Strażnicy F-01 na nowym kontrakcie + test ryzyka S-07 | Zmiana zachowania strażnika S-04 musi być świadoma i przejrzana |

**Prerequisites:** S-01 (spis `localities`, `POST localities/inline`) — done; Sail i baza `e2e` działają.
**Estimated effort:** ~3–4 sesje w 4 fazach; fazy 1–3 wdrażać razem (sama migracja przed nowym kodem rozjedzie tekst i `locality_id`).

## Open Risks & Assumptions

- Danych produkcyjnych nie sprawdzano: liczba dosianych miejscowości jest nieznana — runbook przed/po migracji w planie.
- Miejscowość przemianowana w spisie przed S-07 zostanie utworzona ponownie ze starego tekstu; poprawka ręczna do czasu S-11.
- Lista podpowiedzi w panelu `standard` (~410 px) jest niesprawdzona — krok ręczny 3.6.
- Spis jest mały z założenia; cała lista trafia do klienta.

## Success Criteria (Summary)

- Autor wybiera lub dopisuje miejscowość dla każdego faktu i pochowania bez opuszczania formularza.
- Po wdrożeniu żaden fakt z wpisanym miejscem nie jest bez miejscowości, a żaden tekst nie zmienił się.
- Przy podpinaniu źródła akt odnajduje się po miejscu i roku faktu bez wpisywania.
