# Zakładki wydarzeń i notatki w panelu osoby (S-14) — Plan Brief

> Full plan: `context/changes/person-events-and-note-tabs/plan.md`
> Research: `context/changes/person-events-and-note-tabs/research.md`

## What & Why

Autor widzi w panelu osoby dwie nowe zakładki. **Wydarzenia** pokazują oś życia: własne wydarzenia oraz urodzenia i zgony dzieci i zgony małżonków, uporządkowane po dacie, z wydarzeniami bez roku na końcu. **Notatka** to jedno pole tekstowe o osobie, np. na niepewność miejsca odłożoną tu z FR-010. Realizuje FR-021 i FR-023.

## Starting Point

- **Panel osoby ma już zakładki** Przegląd / Rodzina / Źródła i strażnika niezapisanych zmian przy zmianie zakładki.
- **Dane podglądu buduje `BuildPersonPreview`.** Wydarzenia dzieci i małżonków są już ładowane, ale bez miejsc. Krewni w payloadzie mają tylko tekstowe daty.
- **Notatki osoby nie ma.**
- **Każda edycja osoby tworzy pusty wiersz urodzenia**, który Przegląd ukrywa.

## Desired End State

- **Zakładki:** Przegląd · Rodzina · Źródła · **Wydarzenia** · **Notatka**. Pasek przewija się poziomo, gdy brak miejsca.
- **Wydarzenia:** gotowa lista z serwera, na przykład „Małżeństwo: Anna Nowak — 1880”, „Urodzenie syna: Jan Kowalski — 05.03.1880, Kraków”, a na końcu „Zgon — data nieznana” i „Pochowanie — Kraków”. Imię krewnego otwiera jego panel.
- **Notatka:** karta z ołówkiem, maks. 5000 znaków. Strażnik obejmuje ją przy zmianie zakładki i zamknięciu panelu. Kropka przy „Notatka” sygnalizuje, że notatka istnieje.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres osi | Własne urodzenie, małżeństwa, zgon i pochowanie; urodzenia i zgony dzieci; zgony małżonków. Bez zamieszkań i urodzenia małżonka | FR-021 plus pochowanie jako naturalny koniec osi | Research |
| Puste wydarzenia | Urodzenie bez daty i miejsca pomijane; zgon, małżeństwo i pochowanie zawsze widoczne | Bez szumu po każdej edycji, spójnie z Przeglądem; sam zgon lub ślub to fakt | Plan |
| Remis dat | Stała kolejność typów: urodzenie → małżeństwo → urodzenie dziecka → zgon dziecka → zgon małżonka → zgon → pochowanie, potem id | Porządek życiowy niezależny od kolejności wprowadzania | Plan |
| Sortowanie | Na serwerze, jak `PersonOrdering`; sam rok przed pełną datą w tym roku; „ok.” nie wpływa | Istniejąca konwencja i jedno miejsce testów | Research / Plan |
| Etykiety | Relacja według płci krewnego (syn / córka / dziecko, mąż / żona / małżonek), liczone na serwerze | Czyta się jak kronika; jedna mapa z testem Pest | Plan |
| Wiersz krewnego | Imię jako `PersonPreviewLink` (podmiana panelu), data i miejsce, bez statusu źródła | Szybka nawigacja po rodzinie bez dublowania Przeglądu | Research |
| Kolejność zakładek | Nowe na końcu | Istniejące zakładki i specy E2E bez zmian | Plan |
| Zapis notatki | Osobny `PUT persons/{person}/note` (Request → Action) | Omija kruchy pełny payload osoby | Research |
| Edycja notatki | Karta z ołówkiem, `SectionId 'note'`, strażnik „w notatce” | Spójne z sekcjami Przeglądu | Research |
| Limit i znacznik | 5000 znaków, kropka przy triggerze | Wzorzec notatki źródła; widać notatkę bez klikania | Plan |

## Scope

**In scope:**
- kolumna `people.note`, endpoint notatki, `note` i `timeline` w podglądzie (nie w indeksie);
- `PersonTimeline` z etykietami i eager-loadem miejsc;
- zakładki Wydarzenia i Notatka, kropka, przewijany pasek zakładek;
- testy Pest (w tym granice własności i zapis do właściwej osoby) oraz spec E2E.

**Out of scope:**
- zamieszkania, urodzenie małżonka, pochowanie krewnych, wydarzenia rodziców;
- edycja i status źródła na osi, link do panelu małżeństwa;
- licznik przy „Wydarzenia”;
- wiele notatek i dziennik badań;
- reguła dla remisu bliźniąt (decyduje kolejność wprowadzenia).

## Architecture / Approach

`BuildPersonPreview` dociąga `children.events.locality` i `marriages.participants.events.locality`, a potem dokłada do `person` dwa pola: `note` oraz `timeline`. `timeline` powstaje tak: `PersonTimeline::for($person)` składa wpisy z załadowanych relacji (wydarzenie, rola, krewny) i sortuje je kluczem `[brak roku, rok, miesiąc, dzień, ranga typu, id]`, a `TimelineEntryResource` dodaje do każdego wpisu polską etykietę. Frontend tylko renderuje: `EventsTab` wyświetla listę, `NoteTab` to `SectionCard`, który zapisuje przez `useSectionSave` na trasę `persons.note.update`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Notatka — backend | Kolumna, endpoint, `person.note`, testy granic | Zapis do cudzej lub złej osoby; pełny zapis osoby czyści notatkę |
| 2. Oś — backend | `PersonTimeline`, etykiety, `person.timeline`, test liczby zapytań | Zła kolejność lub duplikat małżeństwa; N+1 dla miejsc krewnych |
| 3. Zakładki — frontend | Wydarzenia, Notatka, strażnik „w notatce”, kropka, przewijanie | Pięć triggerów w panelu ok. 410 px; fokus i dirty dla textarea |
| 4. E2E | Spec: strażnik notatki przy zmianie zakładki; link z osi do panelu dziecka | Niestabilne dane testowe — własne dane i sprzątanie per test |

**Prerequisites:** S-06 (panel osoby) gotowy; Sail działa; serwer E2E na porcie 8100 dla fazy 4.
**Estimated effort:** ~2–3 sesje w 4 fazach (backend 1–2 to jedna sesja, frontend jedna, E2E krótka).

## Open Risks & Assumptions

- Szerokość paska przy 1366 px nie jest zmierzona. Przewijanie poziome to zaplanowany fallback, a ostateczny wygląd sprawdza krok ręczny 3.5.
- Zakładamy, że każde małżeństwo ma najwyżej jednego drugiego uczestnika. Jeśli go brak, wiersz „Małżeństwo” nie ma imienia.

## Success Criteria (Summary)

- Autor widzi w „Wydarzeniach” pełną, poprawnie uporządkowaną oś osoby i jej najbliższej rodziny oraz przechodzi do krewnego jednym kliknięciem.
- Autor zapisuje notatkę z akapitami, widzi kropkę przy zakładce i nie traci niezapisanej notatki przy zmianie zakładki ani zamknięciu panelu.
- Cudza osoba to 404, a zapis notatki zmienia tylko `people.note` wskazanej osoby.
