# Forma ślubu — Plan Brief

> Full plan: `context/changes/marriage-form-and-other-surnames/plan.md`
> Research: `context/changes/marriage-form-and-other-surnames/research.md`

## What & Why

Małżeństwo dostaje atrybut **forma ślubu**: cywilny / wyznaniowy / cywilno-wyznaniowy / nieznana (domyślna). Autor ustawia ją ręcznie, bo z aktu wie, przed kim zawarto ślub, a dziś nie ma tego gdzie zapisać. Mimo nazwy zmiany para bez ślubu i inne nazwiska osoby są poza zakresem (decyzje autora z researchu).

## Starting Point

Małżeństwo to zdarzenie `marriage` z dwojgiem małżonków, a zapisują się tylko data częściowa i miejsce (`PUT marriages/{id}` → `UpdateMarriage`, pełne zastąpienie). Karta „Ślub” pokazuje datę i miejsce w panelu małżeństwa i w przeglądzie osoby, a wiersz w Rodzinie pokazuje „data, miejsce”.

## Desired End State

W karcie „Ślub” jest Select „Forma ślubu”, a w widoku zawsze pole „Forma ślubu” (pusta = wyszarzone „nieznana”). Wiersz małżeństwa w Rodzinie przy znanej formie pokazuje „ślub cywilny, 15.06.2001, Kraków”, a przy nieznanej wygląda jak dziś. Drzewo, oś czasu i nagłówek panelu się nie zmieniają.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Para bez ślubu | Bez zmian (wyliczana ze wspólnych dzieci) | Zapisana para bez ślubu to zmiana modelu, osobna zmiana. | Research |
| Inne nazwiska | Poza zakresem, non-goal PRD zostaje | Wystarcza nazwisko + nazwisko rodowe; „poprawi się później”. | Research |
| Gdzie widać formę | Karta „Ślub” (panel + przegląd) i Rodzina | Tam autor czyta szczegóły małżeństwa; drzewo i oś czasu bez szumu. | Research |
| Podpowiedź ze źródeł | Brak, wybór ręczny | Rodzaj prowadzącego akta nie odróżnia wyznaniowego od cywilno-wyznaniowego. | Research |
| Model danych | Nullable `events.marriage_form` + enum `MarriageForm`, `null` = nieznana | Atrybut zdarzenia jak data i miejsce; bez nowych tras i tabel. | Plan |
| Pełne zastąpienie | Brak `marriage.form` w PUT zapisuje `null`; front zawsze je wysyła | Ta sama semantyka co data i miejsce w `UpdateMarriage`. | Plan |
| Kontrolka | Select jak Wyznanie, pierwsza opcja „Nieznana” | Mieści się w wąskim panelu obok daty i miejsca. | Plan |
| Widok karty | Pole zawsze, pusta = wyszarzone „nieznana” | Widać, że formę można uzupełnić; to samo słowo co w Select. | Plan |
| Wiersz Rodziny | Przedrostek „ślub {forma}, …” tylko przy znanej formie | Stare małżeństwa wyglądają bez zmian. | Plan |
| Nagłówek panelu | Bez zmian | Forma jest w karcie tuż pod nagłówkiem. | Plan |
| Testy | Pest + Vitest, bez E2E | Ryzyko leży na granicy HTTP i payloadu. | Plan |
| Demo | Jan i Anna (2001) = cywilno-wyznaniowy | Demo pokazuje oba stany. | Plan |

## Scope

**In scope:**
- Enum, kolumna, walidacja (`Rule::enum`) w store i update małżeństwa, mapowanie w `CreateMarriage` / `UpdateMarriage`
- `marriage_form` + `marriage_form_label` w payloadzie małżeństwa, współdzielony prop `marriageForms`
- Select w `MarriageFields`, pole w karcie „Ślub” (panel i `FactView`), przedrostek w Rodzinie
- Seeder demo, testy Pest i Vitest, FR-017 w PRD

**Out of scope:**
- Zapisana para bez ślubu, inne nazwiska, wyszukiwanie i pickery
- Podpowiedź formy ze źródeł
- Forma na drzewie, na osi czasu, w nagłówku panelu, na liście `/persons`
- Wybór formy przy tworzeniu małżeństwa (dialog, dodanie małżonka) — małżeństwo powstaje z formą nieznaną
- E2E

## Architecture / Approach

Forma płynie istniejącą ścieżką: `MarriageUpdateRequest` / `MarriageStoreRequest` (`marriage.form`) → nowa metoda w traicie `MapsEventInput::marriageAttributesFrom` (urodzenie i zgon bez zmian) → kolumna `events.marriage_form` z rzutowaniem na enum → `EventResource` (pola tylko dla małżeństwa) → `MarriageFields` / `marriageSaveRequest` na froncie. Opcje Select pochodzą z `MarriageForm::options()` przez prop współdzielony, wzorem `cemeteryManagers`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Model i zapis formy ślubu | Kolumna, enum, walidacja, payload, prop, seeder, testy Pest | Forma wycieka do mapowania urodzenia/zgonu, jeśli trafi do `eventAttributesFrom` |
| 2. Edycja i wyświetlanie formy ślubu | Select, pole w karcie, przedrostek w Rodzinie, Vitest, PRD | Zapis samej daty kasuje formę, jeśli payload pominie `form` |

**Prerequisites:** Sail uruchomiony, baza deweloperska zmigrowana; brak zależności od innych zmian.
**Estimated effort:** ~1 sesja, 2 fazy.

## Open Risks & Assumptions

- Zakładam, że żaden test ani spec nie porównuje dokładnego payloadu małżeństwa Jan i Anna z `TreeViewDemoSeeder` — do sprawdzenia przed zmianą seedera.
- Identyfikator zmiany zawiera nieaktualną część „other-surnames”; folder zostaje pod tą nazwą.

## Success Criteria (Summary)

- Autor ustawia formę ślubu w karcie „Ślub” w panelu małżeństwa lub w przeglądzie osoby i widzi ją u obu małżonków.
- Edycja samej daty lub miejsca nie zmienia ustawionej formy, a stare małżeństwa pokazują „nieznana” i wyglądają w Rodzinie jak przed zmianą.
- Testy Pest, Vitest, typy, lint i build przechodzą.
