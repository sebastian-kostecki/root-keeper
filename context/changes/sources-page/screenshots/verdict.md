# Zrzuty — verdict

## Przed (stan wyjściowy, przed fazą 1)

Zrzuty: `before/01`–`10` (`shots.mjs`, serwer E2E `scripts/e2e-server.sh`, dane `TreeViewDemoSeeder`, konto `tree-demo@example.com`). Pomiary z przebiegu skryptu.

| Wniosek | Klasa | Werdykt | Dowód |
|---|---|---|---|
| Pasek filtrów zajmuje pierwszy ekran na 375 px | C4 | potwierdzony | `02-gallery-mobile`: pasek wyszukiwania ma wysokość 288 px (od y=194 do y=482), pierwsza karta zaczyna się dopiero ok. y=507. |
| Lepka komórka zaznaczonego wiersza nie ma tła wiersza | C2 | potwierdzony | `04-table-mobile-selected`: pierwsza komórka zaznaczonego wiersza ma `background-color` oklch(1 0 0) (`bg-background`), pozostałe komórki są przezroczyste. Lepka komórka nie pokazuje zaznaczenia. |
| Otwarta karta nie pokazuje fokusu klawiatury | C1 | potwierdzony | `05-focus-selected-card`: Tab dociera do otwartej karty (`aria-current="true"`), `:focus-visible` = true, ale `box-shadow` to tylko ring 2 px w kolorze primary (oklch 0.205). Pierścień fokusu 3 px (`ring/50`) został przesłonięty przez pierścień zaznaczenia. |
| Miniatura uszkodzona pokazuje ikonę przeglądarki | C5 | potwierdzony | `09-thumbnail-broken`: kafelki z przerwaną miniaturą pokazują ikonę obrazu uszkodzonego w lewym górnym rogu pustego pola. Karty bez pliku mają ikonę zastępczą. |
| Pusty stan „Brak wyników” mówi „Nie masz jeszcze nagrobków.” mimo ukrytych źródeł | C5 | potwierdzony | `08-empty-kind-with-place`: miejsce „Tarnów”, rodzaj „Nagrobki 0”, komunikat „Nie masz jeszcze nagrobków.”, a pod filtrami „Ukryto 5 źródeł bez miejsca”. Tekst sugeruje brak nagrobków w drzewie. |
| Licznik rodzajów ucina przy 3-cyfrowych wartościach na 375 px (D9) | D9 | nie ucina | `10-kind-toggle-mobile`: pomiar `scrollWidth` = `clientWidth` (327 = 327) dla grupy i każdego z czterech przełączników (np. „Wszystkie18”). Przy obecnych danych (liczby dwucyfrowe) brak przepełnienia. Odroczone, bez naprawy. |

Ujęcia bez wniosku z klas: `01-gallery-desktop`, `03-table-desktop`, `06-filters-active-mobile`, `07-year-reversed-desktop`.
