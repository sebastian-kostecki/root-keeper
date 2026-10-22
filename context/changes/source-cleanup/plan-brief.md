# Porządki w źródłach — nagrobek bez roku, archiwum i numeru wpisu — Plan Brief

> Full plan: `context/changes/source-cleanup/plan.md`

## What & Why

Akt metrykalny ma rok, archiwum i numer wpisu przy fakcie, i tak zostaje. Zdjęcie nagrobka tych danych nie potrzebuje, a dziś nadal je zapisuje i pokazuje. Ta zmiana usuwa je z nagrobka konsekwentnie: w danych, na serwerze, w filtrach i w wiązaniu z faktem.

## Starting Point

Formularz od zmiany `cemeteries` ukrywa Rok i Archiwum nagrobka tylko przez CSS, więc pola wciąż się wysyłają, a walidacja i `UpdateSource` ich nie czyszczą. Karta galerii, picker, tabela i filtry pokazują rok i archiwum nagrobka. „Numer wpisu” przy powiązaniu fakt↔źródło nigdzie nie sprawdza rodzaju. Picker faktu podstawia rok i filtruje po nim, więc nagrobek bez roku zniknąłby przy zgonie.

## Desired End State

Nagrobek w bazie nigdy nie ma roku, archiwum ani numeru wpisu, a dawne wartości stoją w jego notatce. Na `/sources` ustawione Lata lub Archiwum pokazują tylko źródła z tymi danymi, bez nagrobków i bez liczenia ich jako „bez roku/archiwum”. W panelu osoby picker dalej odnajduje nagrobek przy podstawionym roku. Po jego wybraniu i powiązaniu nie ma pola ani przycisku „Numer wpisu”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Istniejący rok/archiwum nagrobków | Migracja dopisuje je do notatki („Rok: …”, „Archiwum: …”) i zeruje pola | Nic nie ginie, a UI czyści się od razu; to reguła „copy first” z CLAUDE.md. |
| Filtr Lata/Archiwum na `/sources` | Ukrywa nagrobki i nie liczy ich w „Ukryto N bez …” | Kto zaznacza lata, szuka aktów, nie nagrobków (decyzja autora). |
| Rok w pickerze faktu | Nagrobek przechodzi podstawiony rok, odnajdywany po miejscu cmentarza | Rok podstawia się sam z faktu, a przy zgonie nagrobek ma być widoczny od razu. |
| Numer wpisu przy nagrobku | Ukryty w UI; serwer pomija go przy wiązaniu, edycja zwraca 404 | UI i dane spójne; 404 jak dla faktu, który nie przyjmuje źródeł. |
| Istniejące numery wpisu przy nagrobkach | Ta sama migracja dopisuje je do notatki nagrobka i zeruje | Reguła „no value lost” dla migracji danych (lokalnie 0 takich). |
| Rodzaj „inne” | Bez zmian | Zakres zgodny z notatką; „inne” może być np. księgą, gdzie rok ma sens. |
| Ukryte pola w formularzu | Zostają zamontowane, serwer je ignoruje | Zmiana rodzaju w formularzu nie gubi wpisanych danych. |

## Scope

**In scope:**
- Migracja nagrobków (rok, archiwum, numery wpisu → notatka) z testem Pest.
- `excludeIf` dla roku i archiwum nagrobka, czyszczenie w `UpdateSource`.
- Pominięcie `entry_number` przy wiązaniu nagrobka, 404 przy edycji numeru.
- Reguły filtrów `/sources` i pickera dla nagrobka.
- Brak pola i przycisku „Numer wpisu” dla nagrobka.
- Testy Pest, Vitest i E2E `gravestone-cemetery-and-buried`.

**Out of scope:**
- Rodzaj „inne”, stary `sources.entry_number`, usuwanie kolumn.
- Zmiany wyświetlania „nr X” i opisów: po migracji wartości są `null` i napisy znikają same.
- Podstawianie roku i filtr miejsca w pickerze.

## Architecture / Approach

Backend ustala niezmiennik „nagrobek nie ma roku, archiwum ani numeru wpisu”. Migracja doprowadza do niego istniejące dane, a walidacja i akcje pilnują go przy zapisie. Widoki nie potrzebują więc warunków po rodzaju. Frontend zmienia tylko dwie funkcje filtrów (`sources-index.ts`, `source-place-year-filter.ts`) i dwa miejsca z wejściem „Numer wpisu” (`source-picker-dialog.tsx`, `source-group.tsx`).

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend i dane | Migracja do notatki + reguły zapisu źródła i wiązania + testy Pest | Nieodwracalna migracja; produkcja ma backup przed `migrate`. |
| 2. Frontend i E2E | Reguły filtrów, brak „Numeru wpisu” dla nagrobka, poprawione fixtury i E2E | Fixtury Vitest z nagrobkiem z rokiem zmieniają oczekiwane liczniki. |

**Prerequisites:** Sail uruchomiony; środowisko E2E (`scripts/e2e-server.sh`).
**Estimated effort:** ~1 sesja, 2 fazy.

## Open Risks & Assumptions

- Danych produkcyjnych nie znamy. Migracja obsługuje dowolną liczbę nagrobków z rokiem, archiwum lub numerami.
- Notatka nagrobka może urosnąć ponad limit walidacji 5000 znaków tylko w skrajnym przypadku. Migracja nie waliduje, a przy następnej edycji autor zobaczy błąd długości.

## Success Criteria (Summary)

- Nagrobek nigdzie nie pokazuje roku, archiwum ani numeru wpisu, a dawne wartości są w jego notatce.
- Filtr Lat lub Archiwum na `/sources` pokazuje same źródła z tymi danymi, bez fałszywego „Ukryto N bez roku”.
- Przy zgonie picker dalej podsuwa nagrobek z cmentarza i wiąże go bez numeru.
