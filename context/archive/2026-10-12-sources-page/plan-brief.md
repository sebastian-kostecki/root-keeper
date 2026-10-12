# Widok /sources zgodny z systemem projektowym — Plan Brief

> Full plan: `context/changes/sources-page/plan.md`
> Research: `context/changes/sources-page/research.md`

## What & Why

Audyt `/10x-ui` widoku `/sources` pokazał, że kolory i odstępy już pochodzą z tokenów, a problemy leżą w stanach. Lista zarzutów:
- fokus klawiatury niewidoczny na karcie otwartego źródła,
- trzy różne zapisy „otwartego rekordu”,
- zły lub odwrócony rok pomijany po cichu,
- na telefonie filtry zajmują pierwszy ekran,
- mylący tekst pustego rodzaju i brak wskaźnika oczekiwania.

Plan zamyka zarzuty C1–C5 i zostawia zabezpieczenie (skan, reguła, E2E), żeby następny agent nie cofnął naprawy.

## Starting Point

`/sources` ma już wzorce list: `Heading variant="page"`, `role="search"`, `EmptyState`. Brakuje mu dwóch rzeczy, które `/persons` dostał w `persons-index-ui`: `focusRing` i obsługi nieudanej wizyty. Filtrowanie odbywa się po stronie klienta (`only: ['filters']`), a cała zmiana jest frontendowa.

## Desired End State

- Fokus jest widoczny wszędzie, także na otwartej karcie.
- Lepka kolumna tabeli na telefonie ma to samo tło co otwarty wiersz.
- Poniżej 640 px cztery pola filtrów chowają się pod „Filtry (N)”.
- Zły rok lub zakres 1900–1850 daje komunikat przy polach.
- Nieudana wizyta pokazuje „Spróbuj ponownie”.
- Pusty rodzaj przy filtrze miejsca mówi prawdę i ma „Wyczyść filtry”.
- Uszkodzona miniatura pokazuje ikonę `ImageOff`.
- Skan tokenów obejmuje `/sources`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Filtry na telefonie (C4) | Zwinięte poniżej `sm` pod „Filtry (N)”; desktop bez zmian | Wyniki na pierwszym ekranie bez łamania S-10 na desktopie | Research |
| Kształt „Filtry (N)” | `Collapsible` w miejscu (`forceMount`, `sm:contents`), jedne pola | Bez nowej zależności i overlayu, etykiety E2E mają jedno źródło | Plan |
| Licznik N | Każde z 4 pól osobno (Rok od + Rok do = 2); blok startuje zwinięty | Licznik odpowiada temu, co jest pod przyciskiem; pierwszy ekran zawsze z wynikami | Plan |
| Błąd roku (C3) | `InputError` + `aria-invalid` dla roku spoza 1–9999 i „od > do”, bez zamiany wartości | Wyjaśnia pustą listę, zgodnie z decyzją GF o braku zamiany | Research |
| Moment komunikatu roku | Po 300 ms przerwy, niezależnie od wizyty | „od > do” nie mruga przy każdym znaku; „12345” nie odpala wizyty | Plan |
| Zaznaczenie vs fokus (C1/C2) | `selectedListItem`: pierścień `primary` tylko `not-focus-visible`; fokus wygrywa | Wygląd bez fokusu bez zmian, fokus zawsze widoczny | Plan |
| Lepka kolumna (C2) | `stickyCell` z wariantem `selected` i nieprzezroczystym hoverem | Otwarty wiersz rozpoznawalny po przewinięciu tabeli na telefonie | Research |
| Stan wyłączony (C5) | Tylko przygaszona etykieta (`group` + `data-disabled`), bez tooltipów | Najmniejsza poprawka zgodna z decyzją użytkownika | Research |
| Obsługa błędu wizyty | `failureHandlers` przeniesione do `lib/visit-failure.ts`, wspólne z `/persons` | Jedno źródło zamiast kopii | Plan |
| Testy | Vitest (`yearFieldsError`, `hiddenFilterCount`, skan) + spec E2E przez `/10x-e2e` | Każde nowe zachowanie pod testem, jak w `persons-index-ui` | Plan |

## Scope

**In scope:** pliki widoku `/sources` (`pages/sources/index.tsx`, `components/sources/sources-{filters,gallery,table,empty-state}.tsx`, `source-thumbnail.tsx`); stałe `lib/focus-ring.ts` (użycie), `lib/list-item-styles.ts` (nowa), `lib/table-styles.ts`; `lib/visit-failure.ts` (wyjęte z `/persons`); `lib/sources-index.ts` + testy; skan `view-tokens.test.ts`, `CLAUDE.md`, spec `sources-page-states.spec.ts`, zrzuty.

**Out of scope:** D1 (ręczny pierścień w 10 innych plikach) i karta drzewa; D2–D8 (D9 tylko ocena na zrzucie); tooltipy przy wyłączonych kontrolkach; zamiana odwróconego zakresu; zmiany paska na desktopie; backend, kitchen sink, `toHaveScreenshot`; wnętrze panelu źródła.

## Architecture / Approach

Kolejność `/10x-ui`: stałe → układ widoku → stany → utrwalenie.
- Pola filtrów renderują się raz, w `Collapsible`. Od `sm` `display: contents` wpuszcza je z powrotem do flexa paska z ich `order-*`. Poniżej `sm` `data-state=closed` je chowa.
- Logika błędu roku i licznika żyje w czystych funkcjach w `lib/sources-index.ts`. Strona tylko przekazuje wynik do pól.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Zrzuty „przed” i stałe | `focusRing`, `selectedListItem`, `stickyCell` z `selected`; zrzuty potwierdzające wnioski z klas | Zmiana `stickyCell` dotyka też `/persons` |
| 2. Filtry na telefonie | „Filtry (N)” + `Collapsible`, „Wyczyść filtry” w wierszu | Rozjechanie paska desktopowego przez `display: contents` |
| 3. Stany | Błąd roku, błąd wizyty i ponowienie, `busy`, tekst pustego rodzaju, etykieta wyłączona, fallback miniatury | Przeniesienie `failureHandlers` może zmienić `/persons` |
| 4. Utrwal to | Spec E2E, skan obejmujący `/sources`, reguła w `CLAUDE.md`, zrzuty „po” | Spec mobilny i abort Inertia stabilne bez `waitForTimeout` |

**Prerequisites:** serwer E2E (`scripts/e2e-server.sh`, port 8100) z danymi `TreeViewDemoSeeder` do zrzutów; Sail działa.
**Estimated effort:** ~3–4 sesje w 4 fazach.

## Open Risks & Assumptions

- Wnioski z klas (wysokość paska na 375 px, lepka komórka, uszkodzona miniatura, D9) potwierdzi dopiero zrzut „przed”. Jeśli zrzut któregoś nie potwierdzi, odpowiednia zmiana spada do `verdict.md` jako niepotrzebna.
- Gradient jednokolorowy na `stickyCell` zakłada, że Tailwind v4 nakłada `bg-linear-*` na `bg-background` bez prześwitu. Sprawdza to kryterium 1.7.

## Success Criteria (Summary)

- Użytkownik klawiatury zawsze widzi fokus, także na otwartym źródle, a na telefonie widzi wyniki na pierwszym ekranie.
- Każda pusta lub niezmieniona lista ma wyjaśnienie (zły rok, nieudana wizyta, pusty rodzaj przez filtr).
- Regresja literału w widoku `/sources` czerwieni `test:js`, a nowe zachowania pilnuje spec E2E.
