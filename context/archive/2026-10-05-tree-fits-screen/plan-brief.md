# S-05 Drzewo wypełnia ekran — Plan Brief

> Full plan: `context/changes/tree-fits-screen/plan.md`
> Research: `context/changes/tree-fits-screen/research.md`

## What & Why

Autor ogląda drzewo z większymi kartami, które dopasowuje się do ekranu i na dużym go wypełnia. Może je przybliżać i oddalać, a linia od pary prowadzi do każdego dziecka (FR-013, FR-014). Dziś małe karty zajmują tylko górę ekranu, a przy więcej niż 4 dzieciach rzędy od drugiego nie są połączone z parą. Dodawanie rodzica, dziecka i małżonka ze slotów musi działać jak dziś (FR-026, guardrail).

## Starting Point

Arkusz rodziny to siatka `min-w-[68rem]` z poziomym przewijaniem i centrowaniem przez `scrollLeft` (`family-sheet.tsx`, `use-centre-in-scroll.ts`). Strona nie ma określonej wysokości, a dzieci stoją w rzędach po 4 z niepołączonymi belkami. Pokazywany jest jeden związek naraz z przełącznikiem. `react-zoom-pan-pinch` jest już zależnością (przeglądarka skanu).

## Desired End State

Drzewo wypełnia wysokość okna pod nagłówkiem i przy wejściu jest dopasowane w całości, z limitem 150% na dużym ekranie. Gdy całość wymagałaby mniej niż 75%, startuje w 75% wyśrodkowane na parze. Kółko przybliża, przeciągnięcie przesuwa, a pasek w rogu ma „Powiększ”, „Pomniejsz” i „Dopasuj”. Karty są większe, wszystkie dzieci stoją w jednym rzędzie pod jedną belką połączoną z parą, a sloty i E2E działają bez zmian asercji przy 1366×768.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Związki na drzewie | Jeden naraz z przełącznikiem; FR-014 = linie od widocznej pary do wszystkich dzieci | Payload `sheet` i lokatory E2E bez zmian; zgodne z decyzją `graphical-tree-view` | Research |
| Gdy drzewo się nie mieści | Dopasuj w całości, ale nie poniżej 75%; poniżej startuj w 75% na parze; „Dopasuj” = całość | Karty zawsze czytelne na starcie, para w centrum zawsze w kadrze | Plan |
| Duży ekran | Dopasowanie powiększa do limitu 150% | Spełnia „wypełnia duży ekran” bez groteskowo dużej małej rodziny | Plan |
| Układ dzieci | Jeden rząd pod jedną belką, zejście do każdego dziecka | Klasyczny układ genealogiczny: jedna belka = jedna para | Plan |
| Szerokość kart dzieci | Do 4 dzieci szerokość rodzica, od 5 szerokość dziadka | Małe rodziny jak dziś, 8 dzieci mieści się w szerokości rzędu dziadków | Plan |
| Proporcje kart | Jak dziś (dziadek = ½ rodzica), wszystkie wyższe i z większą czcionką | Zaakceptowany wariant C, najwęższe drzewo = największa skala | Plan |
| Ponowne dopasowanie | Po zmianie szerokości tylko, gdy widok nie był ruszany; zmiana osoby/związku zawsze | Panel z S-06 zwęża dopasowane drzewo, a po zamknięciu autor jest w tym samym miejscu | Plan |
| Kółko myszy | Przybliża wokół kursora; przeciągnięcie przesuwa | Spójne z przeglądarką skanu; strona już się nie przewija | Plan |
| Kontrolki | Pływający pasek w prawym dolnym rogu obszaru drzewa | Zostaje przy drzewie, gdy panel je zwęża | Plan |
| Viewport E2E | 1366×768 (było 1280×720) | Testy strzegą slotów na dolnej granicy NFR | Plan |
| Mechanizm | Stały naturalny rozmiar + transformacja w ogólnym `ZoomCanvas`; linie w CSS | Jeden mechanizm dla S-05, S-06 i S-15, bez nowych zależności i SVG | Plan |

## Scope

**In scope:**
- czysta funkcja doboru widoku (Vitest) i komponent `ZoomCanvas` (próg, limit, ponowny fit, pasek, tłumienie kliknięcia po przeciągnięciu, fokus);
- prop layoutu `fillHeight`;
- arkusz w obszarze, bez `useCentreInScroll`;
- większe karty, jeden rząd dzieci z belką;
- viewport E2E 1366;
- seeder demo z rodzinami 8 i 10 dzieci, skrypt zrzutów i werdykt.

**Out of scope:** kilka związków naraz, panel osoby (S-06), zdjęcia na kartach (S-16), SVG i nowe zależności, minimapa, strzałki i dwuklik, zapamiętywanie widoku, projekt E2E 2560, testy pikselowe, zmiany asercji E2E, zmiany payloadu `sheet`.

## Architecture / Approach

`tree/show.tsx` ustawia `fillHeight` w layoucie, który daje treści wysokość okna. Arkusz w stałym rozmiarze naturalnym trafia do `ZoomCanvas` z `key = osoba:związek` (każda wizyta dopasowuje od nowa) i `anchorRef` = wiersz pary.

`ZoomCanvas` (`react-zoom-pan-pinch`) stosuje wynik `chooseFitView` (całość ≤ 150% albo kotwica 75%). Po `ResizeObserver` ponownie dopasowuje tylko, gdy autor nie ruszał widoku.

Linie zostają w CSS: pień od pary, belka z półodcinków w komórkach dzieci i zejścia do kart.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Obszar przybliżania i dopasowania | Drzewo wypełnia okno, dopasowanie z progiem i limitem, przybliżanie, pasek; E2E zielone przy 1366 | Kliknięcia i fokus w transformowanej treści (przeciągnięcie nad kartą, Tab przewijający wrapper) |
| 2. Większe karty i jeden rząd dzieci | Większe karty, belka od pary do każdego dziecka, szerokość dzieci zależna od N | Geometria belki w CSS przy dowolnym N i slocie obok rzędu |
| 3. Ocena wyglądu na granicach NFR | Zrzuty 1366/1920/2560, 0–10 dzieci, zwężenie; werdykt i poprawki | Stałe wymiary mogą wymagać strojenia, by ≤ 8 dzieci mieściło się w ≥ 75% przy 1366 |

**Prerequisites:** F-01 (E2E slotów) — gotowe. Działające środowisko Sail i serwer E2E (`scripts/e2e-server.sh`).
**Estimated effort:** ~2–3 sesje w 3 fazach (faza 1 największa).

## Open Risks & Assumptions

- Rzeczywiste szerokości przy 1366 px to arytmetyka, nie pomiar. Stałe kolumny i kart mogą wymagać strojenia w fazie 3.
- Ostrość tekstu przy skali 150% wynika z odczytu kodu biblioteki (2D transform bez `will-change`), a potwierdzą ją zrzuty.
- Playwright może chybić kliknięciem przy transformacji. Poprawka idzie wyłącznie do `support/navigation.ts`.
- Zwężenie panelem symulujemy szerokością okna, bo prawdziwy panel na drzewie przychodzi w S-06.

## Success Criteria (Summary)

- Na 1366–2560 px drzewo wypełnia obszar pod nagłówkiem, jest dopasowane (≥ 75% albo wyśrodkowane na parze) i da się je przybliżać i oddalać.
- Od widocznej pary jedna linia prowadzi do każdego dziecka, także przy 5+ dzieciach.
- Sloty dodawania rodzica, dziecka i małżonka działają jak dziś: pełny zestaw E2E zielony przy 1366×768, Pest drzewa bez zmian.
