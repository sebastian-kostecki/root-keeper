# Wspólna powłoka panelu bocznego (F-02) — Plan Brief

> Full plan: `context/changes/side-panel-shell/plan.md`
> Research: `context/changes/side-panel-shell/research.md`

## What & Why

Jedna powłoka panelu wysuwanego z prawej, bez nakładki: otwarty panel zwęża treść strony obok siebie, ma wariant szeroki (źródło), zamyka się do tego samego miejsca, a otwarcie innego rekordu podmienia zawartość. Bez niej S-01 (miejscowość) i S-02 (źródło), które idą równolegle, zbudowałyby dwie różne powłoki wbrew zasadzie jednej wspólnej; potem dołączą S-06 (osoba) i S-12 (małżeństwo).

## Starting Point

Rekord „nad stroną” otwiera dziś tylko podgląd osoby — modal sterowany `?person=` (współdzielona właściwość, częściowe przeładowanie, 404 w middleware, zamknięcie bez żądania). Layout to wiersz flex `[lewe menu | <main>]` bez miejsca na prawy panel; `ui/sheet.tsx` ma nakładkę, a drugi `SidebarProvider` zderzyłby się z lewym menu.

## Desired End State

W layoucie jest slot panelu obok `<main>`; dowolny komponent renderuje `<SidePanel>` i treść strony zwęża się obok niego. Panel ma warianty standard (≈410 px przy 1366 / 576 px przy 2560) i szeroki (≈750 / 1152 px), pełny ekran poniżej 768 px, zamknięcie X/Escape wstrzymywane przez strażnika zmian. Wspólny pomocnik URL otwiera, podmienia i zamyka panel danego typu, trzymając w adresie najwyżej jeden klucz panelu. Strona `/dev/side-panel` (tylko `local`) pokazuje wszystkie zachowania.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Stan otwartego panelu | W URL, wzorzec „parametr → właściwość → częściowe przeładowanie” | Mutacje `back()` odświeżają panel za darmo, 404 w jednym miejscu, pozycja drzewa nietknięta. | Research |
| Kształt adresu | Osobny klucz na typ (`?person=`, `?source=`, `?place=`, `?marriage=`) + wspólny pomocnik, który usuwa klucze innych paneli | `?person=`, przekierowania i testy zostają bez zmian przed F-01. | Plan |
| Szerokości | Płynne: `clamp(24rem,30vw,36rem)` / `clamp(40rem,55vw,72rem)`, lewe menu bez zmian | Rośnie z ekranem 1366–2560 bez dodatkowego stanu. | Plan |
| Ekran < 768 px | Panel na pełny ekran, bez przyciemnienia, `z-40` pod dialogami | Zachowuje dzisiejsze pełnoekranowe doświadczenie mobilne. | Plan |
| Weryfikacja bez konsumenta | Vitest pomocnika + strona demo tylko w `local` + test Pest | Bez nowych zależności; wzorzec dla S-01/S-02. | Plan |
| Przecentrowanie drzewa | Zostaje dla S-05/S-06 | S-05 i tak przebuduje siatkę; F-02 nie dotyka guardrails. | Plan |
| Osadzenie w layoucie | Portal do slotu `display: contents` po `<main>` | Zwężanie przez sam flex, bez zmiany `ui/sidebar.tsx`; panel renderowalny z layoutu i ze strony. | Plan |
| Rola dostępności | `role="dialog"`, `aria-modal="false"`, nazwa = tytuł | Lokatory F-01 rolą i nazwą przetrwają przejście modal → panel. | Plan |

## Scope

**In scope:**
- `lib/side-panel-url.ts` (rejestr klucz → właściwość, `withPanel`, `panelVisitOptions`) + testy Vitest
- `useSidePanelNavigation(key)` — `hrefFor`, `open`, `close`
- `components/side-panel.tsx` — `SidePanel`, `SidePanelSlot`, `SidePanelSlotProvider`; montaż w `AppLayout`/`AppSidebarLayout`
- Strona i kontroler demo (`/dev/side-panel`, 404 poza `local`) + `tests/Feature/SidePanelDemoTest.php`

**Out of scope:**
- Przeniesienie podglądu osoby, źródła, miejscowości, małżeństwa (S-06, S-02, S-01, S-12); `PersonPreviewProvider` bez zmian
- Przecentrowanie drzewa, auto-zwijanie lewego menu, przeciągany brzeg, animacja zamknięcia
- Nowe zależności (jsdom/Testing Library) i test E2E panelu

## Architecture / Approach

Konsument renderuje `<SidePanel>` (w layoucie jak dziś `PersonPreviewDialog` albo na stronie); portal umieszcza go w `SidePanelSlot`, który stoi w wierszu flex `SidebarProvider` po `<main>` (`flex-1 min-w-0`) — `<main>` zwęża się sam. Otwarcie to wizyta Inertii na ten sam adres z kluczem panelu (`replace`, `preserveState`, `preserveScroll`, `only` = wszystkie właściwości paneli z rejestru); serwer rozwiązuje klucz na dane lub 404; zamknięcie to `router.replace` z wyzerowaną właściwością, bez żądania.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Pomocnik URL i nawigacja panelu | Czysta logika adresu + hook `open`/`close`/`hrefFor`, testy Vitest | `only` bez całego rejestru zostawiłby dane poprzedniego panelu — dwa panele naraz |
| 2. Powłoka i slot w layoucie | `SidePanel` z wariantami, sticky, pełnym ekranem < md, Escape i fokusem; slot w layoucie | Escape z zagnieżdżonego modala zamyka też panel (rozwiązane natywnym nasłuchem + `defaultPrevented`) |
| 3. Strona demo i weryfikacja | `/dev/side-panel` tylko w `local`, test Pest, ręczne sprawdzenie 1366 / 2560 / < 768 px | Szeroki panel przy 1366 px zostawia treści ok. 320 px |

**Prerequisites:** brak (F-02 nie zależy od F-01); działający Sail i `npm run dev` do ręcznego sprawdzenia.
**Estimated effort:** ~2 sesje w 3 fazach.

## Open Risks & Assumptions

- Szerokości przy 1366 px pochodzą z arytmetyki, nie z pomiaru; przy szerokim panelu i rozwiniętym menu treść ma ok. 320 px — akceptowane, S-02 zdecyduje o układzie skanu i formularza w panelu.
- Zachowanie Escape i fokusu z zagnieżdżonym modalem jest wyprowadzone z kodu Radix; potwierdza je ręczny test na stronie demo.
- Strona demo zostaje w repozytorium jako kod deweloperski (404 poza `local`).
- Do S-06 drzewo obok otwartego panelu nie przecentrowuje się — dziś nikt nie otwiera panelu na drzewie.

## Success Criteria (Summary)

- Na stronie demo panel zwęża treść obok siebie przy 1366 i 2560 px, nie zasłania jej, a zamknięcie zostawia stronę w tym samym miejscu.
- Podmiana rekordu zmienia treść panelu bez nowych wpisów historii; Escape i strażnik zmian działają, także z zagnieżdżonym modalem.
- Testy Vitest i Pest, `check`, `types:check`, `build` i Larastan zielone; podgląd osoby działa jak przed zmianą.
