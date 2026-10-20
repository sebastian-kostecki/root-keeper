# Werdykt wizualny — panel dodawania osoby na drzewie

Stan: zrzuty „przed” w `before/01–08` (konto E2E, osoba „Ojciec e2etest-…” bez krewnych), „po” w `after/01–08` (2026-10-20, serwer E2E `scripts/e2e-server.sh` na 127.0.0.1:8100, baza `e2e` zasiana `TreeViewDemoSeeder`; `node shots.mjs <abs>/after 4083`). 01–02: `tree-demo-empty@example.com` (puste drzewo), 03–08: `tree-demo@example.com`, Wiktor Skanowski — bez rodziców i dzieci, jak osoba „przed”. 1440×900 i 390×844, motyw jasny. Inne dane i nowy wygląd z `design-tokens` (kolory, krój) są różnicą tła, nie zmiany.

| Zrzut | Przed | Po | Status | Uzasadnienie |
|---|---|---|---|---|
| 01, 02 — puste drzewo | Karta z tekstem „Nie wybrano probanta. Otwórz osobę…” i linkiem do osób | `EmptyState` „Drzewo jest puste” z przyciskiem „Dodaj pierwszą osobę” | zamknięty (C3) | Pusty stan ze wspólnego komponentu; pierwsza osoba dodaje się bez wychodzenia z drzewa. |
| 03 — arkusz, 1440 px | Sloty i karta | Sloty i karta, przycisk „Przodkowie” | bez zmian | Ujęcie odniesienia. |
| 04 — arkusz, 390 px | Karta osoby centralnej poza kadrem (~109 px) | Karta w całości w kadrze (pomiar: `true`) | zamknięty (C5) | Kotwica na karcie osoby, nie na środku osi pary. |
| 05 — dodaj ojca, lista | Modal przyciemnia całe drzewo | Panel z prawej obok zwężonego drzewa, bez nakładki; slot „Dodaj ojca” wyróżniony i w kadrze (lewa krawędź 278 px przy granicy obszaru drzewa 272 px); adres `?add=father:4083` | zamknięty (C1, C5) | Drzewo widoczne podczas wyboru osoby. |
| 06 — dodaj ojca, formularz | Formularz w modalu na ~90% wysokości | Formularz w panelu, „Wróć do listy” i „Zapisz” w stopce, drzewo obok | zamknięty (C1) | — |
| 07 — formularz, 390 px | Formularz w modalu | Panel na pełną szerokość, stopka z akcjami widoczna | zamknięty (C1) | — |
| 08 — panel osoby, 1440 px | Slot „Dodaj ojca” wypada z kadru, karta przycięta | Karta osoby centralnej w całości w kadrze; slot „Dodaj ojca” wystaje 39 px z 189 px poza lewą krawędź obszaru drzewa | częściowo | Kryterium planu („aktywny slot w kadrze”) dotyczy panelu dodawania (05) i jest spełnione; przy panelu osoby slot nie jest aktywny. Wyróżnienie „Dodaj małżonka” w 08 to najechanie kursorem po kliknięciu oka (kursor zostaje w miejscu, drzewo zwęża się pod nim), nie stan aplikacji. |

## Kryteria z planu (Faza 5, punkt 4)

- Brak przyciemnienia drzewa przy dodawaniu: spełnione (05–07).
- Slot w kadrze: spełnione dla aktywnego slotu (05); przy panelu osoby częściowo (08).
- Karta osoby centralnej w kadrze na 390 px: spełnione (04).
- Pusty stan z `EmptyState`: spełnione (01, 02).
