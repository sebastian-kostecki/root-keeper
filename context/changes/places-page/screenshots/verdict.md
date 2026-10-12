# Werdykt wizualny — strona „Miejsca” (/places)

Stan: zrzuty „przed” są (`before/`). Zrzutów „po” **nie wykonano** (`after/` nie istnieje). Powód: serwer dev na porcie 8000 działa, ale baza dev nie ma żadnego użytkownika (`users` puste), więc `shots.mjs` nie zaloguje się; serwer E2E na porcie 8100 nie działa, a jego baza `e2e` nie ma danych drzewa demo (miejscowości, parafii). Tryb `after` w `shots.mjs` jest gotowy, ale wymaga uruchomionego serwera z zasianym drzewem `tree-demo@example.com`. Werdykt poniżej opiera się więc na kodzie (Fazy 1–3, zweryfikowane czytaniem plików), a nie na zrzutach „po”.

| Zarzut | Przed | Po | Status | Uzasadnienie |
|---|---|---|---|---|
| C1 — dwa wpisy w menu | `before/01-localities-desktop.png`, `before/05-parishes-desktop.png` | brak zrzutu | nie zweryfikowano | Kod: jeden wpis „Miejsca” z sumą (`app-sidebar.tsx`, Faza 1). Brak zrzutu menu „po”. |
| C2 — duplikacja stron | `before/01`, `before/05` | brak zrzutu | nie zweryfikowano | Kod: jedna strona `pages/places/index.tsx`, stare `pages/{localities,parishes}/index.tsx` usunięte, stare trasy przekierowują (Faza 1). Test e2e `places-page.spec.ts` sprawdza przekierowanie i zakładkę (nie uruchomiony). |
| C3 — ręczny pierścień fokusu | `before/04-localities-focus-row.png`, `before/08-parishes-focus-row.png` | brak zrzutu | nie zweryfikowano | Kod: link wiersza używa `focusRing`, bez `ring-[3px]` (Faza 2). Pomiar `box-shadow` po animacjach nie wykonany. |
| C4 — „Użycia” o dwóch znaczeniach | `before/05-parishes-desktop.png`, `before/06-parishes-mobile.png` | brak zrzutu | nie zweryfikowano | Kod: u parafii nagłówek „Źródła” (`parishes-table.tsx`), miejscowości nadal „Użycia” (Faza 2). Zrzut nagłówka nie wykonany. |
| C5 — tabela parafii na 375 px | `before/06-parishes-mobile.png` | brak zrzutu | nie zweryfikowano | Kod: pierwsza kolumna `stickyCell`/`stickyHeadCell` w obu tabelach (Faza 2). Przewinięcie w bok na 375 px nie zostało sprawdzone w przeglądarce. |

## Czego nie sprawdzono

- Zrzutów „po” na 1440×900 i 375×812 (oba spisy, panel z zaznaczonym wierszem, fokus na „Dodaj …” i na wierszu, liczniki wyszukiwania, „Pokaż w parafiach”).
- Pustej zakładki: wymaga drzewa bez parafii lub bez miejscowości; dane demo tego nie dają, więc nie zrobiono zrzutu „Spis jest pusty”.
- Wizualnego potwierdzenia, że przyklejona komórka ma to samo tło co otwarty wiersz (Faza 2, punkt ręczny 2.3).
- Spec `tests/e2e/places-page.spec.ts` nie został uruchomiony w tej sesji.

## Do zrobienia przed zamknięciem bramki

1. Zasiać drzewo demo na serwerze E2E (lub w bazie dev, z decyzją użytkownika), uruchomić serwer i wykonać `SHOTS_BASE=<url> node context/changes/places-page/screenshots/shots.mjs <abs>/screenshots/after after`.
2. Uzupełnić tabelę o ścieżki z `after/` i zmienić statusy na „zamknięty” lub „częściowo”.
