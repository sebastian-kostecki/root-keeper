# Design system Root Keeper na /sources i /places — Plan Brief

> Full plan: `context/changes/sources-places-polish/plan.md`
> Design system: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU (`project/implementation.md` sekcja 3, README `SourceStatus`, `SourceCard`, `SidePanel`, `EmptyState`, `SegmentedToggle`)

## What & Why

Zmiana design-tokens wprowadziła paletę i tokeny `source*`, ale świadomie odłożyła ich użycie. Teraz wdrażamy resztę zasad design systemu na źródłach i miejscach: bursztyn oznacza dowód, przycisk mówi, czego dotyczy, wszystkie panele rekordów mają jeden układ przycisków, puste stany i sekcje mają jedną formę. Przy okazji kończymy polonizację nagłówków ustawień.

## Starting Point

Potwierdzenie faktu to zielona plakietka z ✓, brak źródła — ostrzegawczy trójkąt; miniatura bez podglądu jest szara (w ciemnym motywie prawie niewidoczna, follow-up F3). Panele miejscowości i parafii trzymają „Zapisz”, „Scal z…” i „Usuń” w treści formularza, panel źródła — w stopce i nagłówku. Etykiety „Zapisz”/„Usuń”/„Scal” nie mówią, czego dotyczą; ustawienia mają angielskie tytuły i nagłówki.

## Desired End State

Fakt potwierdzony pokazuje bursztynowy chip z rodzajem dokumentu (czytnik: „Potwierdzone: Akt urodzenia”), brak — szare „Bez źródła” z przerywanym kółkiem; skan bez podglądu, znak przy dacie i stopka karty są bursztynowe, a fokus karty galerii ma odstęp od zaznaczenia. Panele źródła, miejscowości i parafii: zapis „Zapisz źródło/miejscowość/parafię” w stopce, „Scal z…”/„Usuń” w nagłówku. Puste stany „Brak źródeł/miejscowości/parafii”, ustawienia po polsku.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres | Wiersze `implementation.md` o bursztynie, etykietach, układzie paneli, pustych stanach, h3 i `ariaLabel` + nagłówki ustawień | Reszta (linie drzewa, „Usuń osobę”) to osobne zmiany | Zlecenie |
| Przełącznik spisu na `/places` | Zostaje nad wyszukiwarką | Działa jak zakładki, nie filtr | Zlecenie |
| Nazwa dostępna „Usuń z listy” | `aria-label` „Usuń z listy: {nazwisko}” | Widoczna etykieta musi być częścią nazwy (WCAG 2.5.3), nazwisko nadal rozróżnia przyciski | Plan |
| Podpowiedź przy wyłączonych akcjach | W stopce obok „Zapisz …”, nagłówek wskazuje ją przez `aria-describedby` | Zawsze widoczna i stoi przy przycisku, który odblokowuje akcje; nagłówek zostaje wąski | Plan |
| Ustawienia | Także nagłówki `Heading` w layoucie i trzech stronach, poza Head/breadcrumbs/menu | Strona nie miesza „Settings” z menu „Profil” | Plan |
| Stan formularza poza `<Form>` | Wzorzec `source-panel`: `form={id}`, `DirtyReporter` + wspólny `ProcessingReporter` | Sprawdzony w repo, bez nowego mechanizmu | Plan |
| Nagłówki sekcji | `<h3 className="text-base font-medium">` | `section-title` = 1rem/500, a `Heading variant="small"` renderuje `h2` | Plan |
| Testy | Nowe nazwy w lokatorach E2E, asercje bez zmian; strażnik tokenów += chip i dwa panele | Zmiana nazwy, nie zachowania | Zlecenie / Plan |

## Scope

**In scope:**
- `focusRingOffset` i jego użycie w galerii; `EventSourceStatus`, `ConfirmedMark`, `SourceThumbnail`, stopka galerii w bursztynie
- Układ paneli miejscowości i parafii jak panel źródła; etykiety z rzeczownikiem w panelach, oknach usuwania/scalania, numerze wpisu, starym numerze, pochowanych
- Puste stany (`outline`, tytuły „Brak …”), h3 w panelu źródła, `ariaLabel` „Rodzaj miejsca”
- Ustawienia: Head, breadcrumbs, menu, nagłówki `Heading`
- Specy E2E z nowymi nazwami, `VIEW_FILES`, zrzuty przed/po z werdyktem

**Out of scope:**
- Linie drzewa, `MarriageRingsIcon`, `union-node`, listwa płci
- „Usuń osobę” i etykiety w panelach osoby/małżeństwa/dodawania
- Pola i przyciski formularzy ustawień, 2FA, klucze dostępu
- Backend, nowe zależności, regresje pikselowe

## Architecture / Approach

Same zmiany widoków, bez zmian props ani backendu. Bursztyn to klasy ról (`bg-source-surface`, `border-source-border`, `text-source`) już obecne w `@theme`. Jedyna zmiana struktury: panele miejscowości i parafii wynoszą `isDirty`/`processing` z `<Form>` do stanu panelu, żeby przyciski w `actions` i `footer` `SidePanel` mogły z nich korzystać — dokładnie jak `source-panel.tsx:85-161`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Dowody w bursztynie | Zrzuty „przed”, chip, znak przy dacie, miniatura, stopka, fokus z odstępem | Zrzuty „przed” muszą powstać na świeżym buildzie przed edycją |
| 2. Panele i etykiety | Jeden układ przycisków w 3 panelach, etykiety z rzeczownikiem, h3, specy E2E | Strażnik niezapisanych zmian na `/places` musi nadal dostawać `onDirtyChange` |
| 3. Puste stany, /places, ustawienia, bramka | Tytuły „Brak …”, outline, „Rodzaj miejsca”, ustawienia po polsku, zrzuty „po” + werdykt | Demo może nie mieć wszystkich stanów do zrzutu (źródło bez miniatury) |

**Prerequisites:** Sail uruchomiony; serwer dev Sail (`localhost:8000`, `TreeViewDemoSeeder`: `tree-demo@example.com` i `tree-demo-empty@example.com`) do zrzutów; `scripts/e2e-server.sh` (baza `e2e`) do specy.
**Estimated effort:** ~1 sesja, 3 fazy.

## Open Risks & Assumptions

- Kontrast bursztynu (`source` na `source-surface`) przyjmujemy z design systemu; werdykt zrzutów odnotuje każdy problem.
- `fact-source-scan.spec.ts` po przeniesieniu „Potwierdzone:” do `sr-only` musi sprawdzać stan przez treść chipa, nie widoczny prefiks.
- Miniatura w bursztynie zmienia też tabelę źródeł, wybór źródła i źródła osoby (ten sam komponent) — zamierzone.

## Success Criteria (Summary)

- Dowody są bursztynowe, „Bez źródła” jest neutralne, fokus karty galerii odróżnia się od zaznaczenia — w obu motywach.
- Trzy panele mają ten sam układ przycisków, każdy przycisk mówi, czego dotyczy; ustawienia i puste stany po polsku i w jednej formie.
- `check`, `types:check`, `test:js`, build, dotknięte specy E2E i testy ustawień przechodzą; werdykt zrzutów zaakceptowany.
