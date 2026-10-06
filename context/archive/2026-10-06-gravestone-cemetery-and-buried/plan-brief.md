# Nagrobek z cmentarzem i osobami pochowanymi (S-09) — Plan Brief

> Full plan: `context/changes/gravestone-cemetery-and-buried/plan.md`

## What & Why

Autor opisuje zdjęcie nagrobka cmentarzem (miejscowość ze spisu) i osobami pochowanymi. Miejsce pochowania osoby z listy wypełnia się cmentarzem nagrobka i pozostaje do zmiany. Przy podpinaniu źródła do faktu nagrobek odnajduje się po miejscowości cmentarza i roku. Domyka FR-002 i część „cmentarz” FR-004 — dziś nagrobek ma tylko płaski opis wspólny z „inne”.

## Starting Point

Źródło ma parafię (tylko akt) i archiwum. Pola rodzaju obsługują `excludeIf` w walidacji i zerowanie w `UpdateSource`. Filtr pickera czyta `locality_name`, dziś równe siedzibie parafii. Pochowanie to wydarzenie `Burial` bez daty, istniejące tylko przy zgonie. Wybór osoby (`PersonCandidateList`) działa tylko na drzewie.

## Desired End State

Panel nagrobka ma „Cmentarz” (picker spisu z dopisaniem w locie) i „Osoby pochowane” (wybór istniejących osób, zapis z formularzem). Zapis nagrobka daje zmarłym z listy bez pochowania cmentarz jako miejsce pochowania. Picker, galeria i tabela pokazują „cmentarz Luboml” i odnajdują nagrobek po „Luboml” + rok. Panel osoby linkuje do nagrobka, a edycja Zgonu z pustym pochowaniem startuje z cmentarzem.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Co znaczy „podpowiada się” | Zapis nagrobka ustawia pochowanie zmarłym z pustym miejscem; nie nadpisuje, nie oznacza zgonu, nie cofa | Zero kliknięć w typowym przypadku, żyjący małżonek na nagrobku nie zostaje uśmiercony | Plan (autor) |
| Których osób dotyczy | Każdy zapis nagrobka, cała lista | Kolejność wypełniania (osoby, cmentarz, zgon) nie ma znaczenia | Plan (autor) |
| Nagrobek a zgon | Osobno — lista nie podpina źródła do zgonu | FR-025 bez zmian; nagrobek bez dat nie potwierdza zgonu na wyrost | Plan (autor) |
| Dodawanie pochowanych | Tylko istniejące osoby z drzewa | Reużycie `PersonCandidateList`, brak osób bez relacji | Plan (autor) |
| Strona osoby | Link „Nagrobek” + wstępny cmentarz w edycji Zgonu | Domyka przypadek osoby oznaczonej jako zmarła później bez ukrytych zapisów | Plan (autor) |
| Model danych | `sources.cemetery_locality_id` (NO ACTION) + tabela `buried_people` (kaskady) | Wzorzec FK do spisu i kasowania konta z S-03/S-07 | Plan |
| Cmentarz w filtrze | `locality_name` nagrobka = cmentarz | Filtr pickera działa bez zmian (docblock S-04 to przewidział) | Plan |
| Kilka nagrobków osoby | Podpowiedź tylko przy jednym wspólnym cmentarzu | „Pierwszy” z kilku nie jest decyzją, którą ktoś podjął | Plan |
| Zmiana rodzaju / usunięcie | Potwierdzenie obejmuje cmentarz i listę; usunięcie czyści tylko listę | Spójne z polami aktu; miejsca pochowania osób są ich danymi | Plan |

## Scope

**In scope:**
- Kolumna cmentarza, tabela pochowanych, walidacja w drzewie, zapis jako pełne zastąpienie
- `FillBurialPlacesFromGravestone` w transakcji zapisu źródła
- `SourceResource` (cmentarz, `locality_name`, pochowani), `personCandidates` na stronie źródeł
- UI: pola nagrobka, `PickPersonDialog`, dialogi zmiany rodzaju i usuwania, cmentarz w kaflu/galerii/tabeli
- Panel osoby: link „Nagrobek”, `burial_suggestion` w Zgonie
- Pest (`GravestoneTest`, `SourcesTest`, `WriteTargetsTest`, backup), Vitest, E2E

**Out of scope:**
- Nowa osoba z poziomu nagrobka; podpinanie nagrobka do zgonu; oznaczanie zgonu
- Nazwa cmentarza poza miejscowością, daty z inskrypcji, kwatera
- Siatka i filtry `/sources` (S-10); scalanie/usuwanie miejscowości (S-11)

## Architecture / Approach

Trait walidacji wycina cmentarz i listę dla rodzajów innych niż nagrobek. `StoreSource`/`UpdateSource` synchronizują listę i wywołują `FillBurialPlacesFromGravestone`, która tworzy wydarzenie `Burial` (subject) dla zmarłych bez pochowania. Odczyt: `SourceResource.locality_name` przełącza się na cmentarz dla nagrobka, więc filtr pickera i US-01 działają bez zmian. Front: `LocalityPicker` i nowy `BuriedPeopleField` (ukryte `buried_person_ids[]`) w istniejącym `<Form>`; panel osoby czyta `gravestones` i `burial_suggestion` z `BuildPersonPreview`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend — model i zapis | Migracja, relacje, walidacja, zapis listy, uzupełnianie pochowania, potwierdzenie rodzaju | Brak klucza listy przy zerze osób musi czyścić, nie ignorować |
| 2. Backend — odczyt | Pola zasobu, eager-loading, `personCandidates`, nagrobki/podpowiedź osoby | Pominięte eager-loading w jednym z trzech miejsc → N+1 |
| 3. Frontend — panel nagrobka i lista | Pola, dialog osoby, dialogi rodzaju/usuwania, cmentarz w widokach | Ukryte inputy listy nie wyzwalają `isDirty` |
| 4. Frontend — panel osoby | Link „Nagrobek”, wstępny cmentarz w Zgonie | Wstępna wartość nie może zapisać się bez „Zapisz” |
| 5. E2E | Spec nagrobek → pochowanie → picker + cała suita | Miejscowości zostają w bazie `e2e` (brak trasy usuwania) |

**Prerequisites:** S-04 i S-07 (`done`); Sail i baza `e2e` dla Playwright.
**Estimated effort:** ~3–4 sesje w 5 fazach; fazy 1–3 wdrażać razem.

## Open Risks & Assumptions

- Zapis nagrobka po cichu zmienia dane osób (pochowanie) — świadoma decyzja; dialog niczego nie zapowiada.
- Celowo wyczyszczone pochowanie wraca przy kolejnym zapisie nagrobka (przyjęte przy wyborze „każdy zapis, cała lista”).
- Celowo wyczyszczone pochowanie wraca też przy edycji Zgonu w panelu osoby: podpowiedź z nagrobka jest stanem „czystym” formularza i zapisuje się z „Zapisz” (przyjęte; chroni tylko dopisek „Podpowiedź z nagrobka”).
- S-11 musi liczyć `sources.cemetery_locality_id` przy usuwaniu i przepinać je przy scalaniu (`Locality::cemeterySources()`).

## Success Criteria (Summary)

- Autor opisuje nagrobek cmentarzem i osobami bez wychodzenia z panelu, a zmarli z listy mają miejsce pochowania.
- Przy zgonie osoby picker z „Luboml” i rokiem pokazuje nagrobek z „cmentarz Luboml”.
- Pest, Vitest i cała suita E2E przechodzą.
