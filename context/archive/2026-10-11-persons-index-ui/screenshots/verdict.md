# Werdykt zrzutów — persons-index-ui (Faza 5)

Zrzuty: `before/` (Faza 1, stan wyjściowy) i `after/` (Faza 5, `shots.mjs` na serwerze E2E 8100, dane `TreeViewDemoSeeder`).
Obejrzane w tej sesji: `01`, `02`, `06`, `10` (desktop i 375 px). Pozostałe pary (`03`–`05`, `07`–`09`, mobilne `04`) zapisane,
ale nieocenione wizualnie — wpis „nieocenione” poniżej, nie „zgodne”.

## przed

- `mb-8` (C2): na zrzucie 1440 px „Dodaj osobę” stoi w linii z tytułem „Osoby”; przesunięcia nie widać w tej skali. Wniosek z kodu nie został potwierdzony zrzutem.
- Mobile 375 px (`02`): przełącznik i wiersz wyszukiwania zajmowały więcej miejsca niż po zmianie (C2).
- Modal „Dodaj osobę” (`06`, `07`) zasłaniał listę (C1).
- Tabela na 375 px (`04`, waga D6): nie oceniono w tej sesji.

## po

- `01` (1440 px): nagłówek i wiersz wyszukiwania jak przed; `Heading variant="page"` i `role="search"` nie zmieniają tu wyglądu wizualnie, zmieniają semantykę (h1, landmark).
- `02` (375 px): przełącznik Lista/Tabela pokazuje same ikony; wiersz wyszukiwania mieści się w szerokości ekranu. Zamierzona zmiana (C2).
- `06` (1440 px): „Dodaj osobę” otwiera panel po prawej, formularz „Dodaj osobę” z podpisem „Po zapisaniu otworzy się podgląd tej osoby.”, zamiast modala (zamierzona zmiana C1). Lista zostaje po lewej.
  - Do sprawdzenia: na zrzucie lewy pasek nawigacji wygląda na przesunięty nad listą. Wygląda to na artefakt zrzutu wykonanego w trakcie animacji otwierania panelu, a nie na regresję układu — nie potwierdzone powtórnym zrzutem.
- `07` (375 px): nieocenione wizualnie w tej sesji; zachowanie panelu pełnoekranowego sprawdzone w specu E2E i Fazie 3 (manual).
- `10-search-error-desktop` (nowy): pod polem wyszukiwania czerwony komunikat „Nie udało się wyszukać — pokazane wyniki mogą być nieaktualne.” i przycisk „Spróbuj ponownie”; poniżej stare wyniki przygaszone nie zostały wyłączone z listy (zamierzone: C5 mówi o komunikacie, nie o ukryciu wyników).
- `03`, `04`, `05`, `08`, `09`: zapisane, nieocenione wizualnie w tej sesji. Pierścień fokusu skoków i wierszy: w logu zrzutów `:focus-visible = true` dla obu elementów.

## deferred

- **D1** „Dom” rozdzielony (logowanie → `/persons`, logo → `/tree`). Decyzja udokumentowana (`app-shell-sidebar/plan-brief.md:24`) i przypięta `tests/e2e/seed.spec.ts`. Wrócić osobną zmianą.
- **D2** Duplikaty w formularzu tworzenia (`Card`, ręczny awatar, `sm:h-[90vh]`): znikły razem z modalem (C1). Nie dotyczy już `/persons`; zostaje w `person-create-dialog` historycznie usuniętym.
- **D3** Badge „Probant” w dwóch wariantach (`secondary` vs domyślny w panelu osoby). Inny widok; ujednolicić przy zmianie panelu osoby.
- **D4** `PersonAvatar` obok `ui/avatar.tsx`. Zamiana dotyka drzewa; poza zakresem.
- **D5** Brak paginacji, ciężki payload (`PersonController.php:35-59`). Wydajność, nie kontrakt UI.
- **D6** Tabela na 375 px: menu akcji poza ekranem. Nie oceniono zrzutem `04` w tej sesji; waga do potwierdzenia przez właściciela.
- **D7** `align-[-2px]` w `person-list-parts.tsx`: wyjątek jawny w `persons-view-tokens.test.ts`, brak tokenu dla optycznego wyrównania.
- Poza D1–D7: pozostałe 10 plików z ręcznym pierścieniem fokusu (decyzja C3) — deferred, jeden widok na zmianę.
