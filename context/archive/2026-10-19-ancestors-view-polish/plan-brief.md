# Dopracowanie widoku przodków — Plan Brief

> Full plan: `context/changes/ancestors-view-polish/plan.md`
> Research: `context/changes/ancestors-view-polish/research.md`

## What & Why

Widok przodków ma pięć problemów zgłoszonych przez użytkownika:
- trudno do niego trafić (jest tylko w menu „⋯” panelu);
- kółko myszy nie zoomuje;
- nie da się zobaczyć więcej niż 6 pokoleń;
- zdjęcie jest za małe;
- wiek zaśmieca gęsty wykres.

Audyt `/10x-ui` potwierdził, że widok jest zbudowany z tokenów (0 trafień skanu), a problem leży w zachowaniu i punktach wejścia, nie w kolorach.

## Starting Point

- Zakres 2–6 jest na sztywno w kontrolerze, w kliencie i w progu startu.
- `BuildAncestorChart` nie chroni się przed cyklem. Ratuje go tylko limit pokoleń, a w danych dev istnieje pętla.
- Karta przodka dziedziczy `CardBody` drzewa, z wiekiem i z avatarem `size-10`.

## Desired End State

- Na `/tree` jest przycisk „Przodkowie”.
- Przełącznik oferuje każdą głębokość od 2 do tylu pokoleń, ile jest w danych, i znika, gdy wybór jest jeden.
- Samo kółko zoomuje małym krokiem.
- Głęboki wykres startuje na osobie w czytelnej skali i daje się oddalić do całości.
- Karta przodka ma avatar 3.5rem i nie ma wieku. Drzewo wygląda jak dotąd.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zoom kółkiem | Samo kółko, tylko widok przodków, mały krok | Dziś samo kółko nic nie robi, a Ctrl+kółko skacze do maksimum; drzewo dalej nie zoomuje | Research |
| Opcje pokoleń | Każda liczba 2…N, N z danych | Użytkownik woli pełną kontrolę niż „6 + Wszystkie” | Research + Plan |
| Krawędź N = 2 | Przełącznik ukryty | Kontrolka bez wyboru nic nie robi | Plan |
| Domyślna / adres | `min(4, N)`; adres > N przycinany do N; śmieci → domyślna | Link z innej osoby dalej działa, zamiast skakać do 4 | Plan |
| Cykl w danych | Stop na ścieżce (powtórzona karta bez rodziców) + sufit techniczny 20 | Głębokość z danych nie może zależeć od limitu, który dziś ratuje pętlę | Plan |
| Start głębokiego wykresu | Całość, gdy mieści się ≥ 0.1; inaczej osoba w czytelnej skali, oddalanie do całości | Przy 10 pokoleniach całość to pasek ok. 40 px | Plan |
| Avatar przodka | `size-14` (3.5rem), obok imienia | Ok. 40% większy, a imię zachowuje ok. 100 px przy kolumnie 12rem | Plan |
| Wiek | Ukryty tylko na karcie przodka (UI), backend bez zmian | `CardBody` ma dwóch konsumentów; jeden prop wariantu zamiast kopii | Research |
| Wejście | Przycisk „Przodkowie” w rzędzie tytułu `/tree`; menu „⋯” zostaje | Widoczność bez łamania E2E menu | Research |

## Scope

**In scope:**
- `BuildAncestorChart`, `ParentChildGraph` i `TreeAncestorsController` (głębokość, cykl, przycięcie);
- `ancestors.tsx`, `ZoomCanvas` i `fit-view` (przełącznik, kółko, start);
- `CardBody`/`CardAvatar` (wariant przodka) i przycisk na `/tree`;
- E2E R1–R4, zrzuty, strażnik `view-tokens` i reguła w `CLAUDE.md`.

**Out of scope:**
- C7: czytelność na mobile poza startem głębokiego wykresu;
- C8: naprawa pętli w danych i walidacja relacji;
- zoom na drzewie;
- opcja „Wszystkie”;
- zmiany tokenów.

## Architecture / Approach

Serwer liczy N tą samą regułą, którą buduje wykres: najdłuższa linia przodków, a krawędź do osoby już na ścieżce jest pomijana, z pamięcią per osoba i sufitem 20. Wysyła `available_generations` obok `generations`. Klient tylko układa opcje i wykres. `ZoomCanvas` dostaje opcjonalne `anchorScale` i `minScale` liczone do całości tylko przy `zoomControls`, więc drzewo działa po staremu. Karta przodka korzysta z `CardBody variant="ancestor"`, czyli z jednego miejsca wspólnego z drzewem, bez kopii markupu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend | `available_generations`, stop na ścieżce, przycięcie adresu, testy Pest | N rozjechane z faktyczną głębokością wykresu przy zawężeniu linii |
| 2. Widok przodków | Przełącznik 2…N, zoom samym kółkiem, czytelny start głębokiego wykresu | Zmiana `ZoomCanvas` przecieka na drzewo; płynność przy ok. 170 kolumnach |
| 3. Karta i wejście | Brak wieku, avatar `size-14`, przycisk „Przodkowie” | Wyższy rząd tytułu łamie `tree-fill-window.spec.ts` |
| 4. E2E i zabezpieczenie | R1–R4, macierz 7 stanów, zrzuty „after”, strażnik i reguła | Krok kółka i skala zakotwiczenia dobrane na oko, bez pomiaru |

**Prerequisites:** branch `feat/ancestors-view-polish` od `develop`, działający Sail i serwer E2E (`scripts/e2e-server.sh`), baza dev z drzewem 1 do ręcznej weryfikacji głębokości 10.
**Estimated effort:** ok. 3–4 sesje w 4 fazach.

## Open Risks & Assumptions

- `ANCESTORS_ANCHOR_SCALE` (start 0.75) i `ANCESTORS_WHEEL_STEP` są dobierane w przeglądarce. Kryteria są w planie, ale wartości nie są zmierzone.
- Wydajność renderu ok. 170 kolumn i kilkuset kart nie była mierzona. Jeśli przewijanie się tnie, potrzebna będzie osobna zmiana (np. wirtualizacja).
- Test cyklu zmienia oczekiwanie z `[child, parent, child, parent]` na `[child, parent, child]`. To celowa zmiana zachowania przez stop na ścieżce.
- Przełącznik przy N do 20 (19 opcji) musi mieścić się bez poziomego przewijania strony. Dane dev mają najwyżej 10.

## Success Criteria (Summary)

- Użytkownik wchodzi w przodków jednym kliknięciem z drzewa i widzi wszystkie pokolenia, jakie są w danych.
- Kółko myszy zoomuje płynnie. Głęboki wykres startuje czytelnie na osobie w centrum.
- Karty przodków pokazują wyraźne zdjęcie bez wieku, a drzewo i jego testy są bez zmian.
