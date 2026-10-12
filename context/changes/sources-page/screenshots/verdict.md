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

## Po (stan po fazie 4)

Zrzuty: `after/01`–`12` (ten sam `shots.mjs`, te same ujęcia i serwer). `11-filters-expanded-mobile` i `12-visit-error-desktop` są nowe: nie mają pary „przed”, bo wtedy nie istniały. Pomiary z przebiegu skryptu.

| Para | Wniosek | Przed | Po | Ocena |
|---|---|---|---|---|
| `02-gallery-mobile` ↔ `before/02` | C4 (wysokość paska 375 px) | 288 px | 132 px (pasek od y=194) | zamierzona: pasek się zwinął, pierwsza karta wyżej |
| `04-table-mobile-selected` ↔ `before/04` | C2 (lepka komórka) | tło `oklch(1 0 0)` (`bg-background`), reszta wiersza przezroczysta | tło `oklch(0.97 0 0)` = `muted`, równe tłu wiersza | zamierzona. Uwaga: na zrzucie na 375 px otwiera się panel źródła i zasłania tabelę, więc wynik potwierdza pomiar, nie wygląd; to kadr z `before`, bez zmiany |
| `05-focus-selected-card` ↔ `before/05` | C1 (fokus na otwartej karcie) | `box-shadow` tylko ring 2 px `primary`, pierścień fokusu przesłonięty | pierścień fokusu `ring/50` 3 px obecny (`oklab(0.556 / 0.5) 0 0 0 3px`), `:focus-visible` = true | zamierzona |
| `07-year-reversed-desktop` ↔ `before/07` | C3 (odwrócony zakres) | pusta lista bez wyjaśnienia | komunikat „„Rok od” nie może być późniejszy niż „Rok do”.”, oba pola `aria-invalid`, wartości 1900/1850 zostają | zamierzona |
| `08-empty-kind-with-place` ↔ `before/08` | C5 (pusty rodzaj przy filtrze) | „Nie masz jeszcze nagrobków.” | tekst o filtrach i akcje „Pokaż wszystkie rodzaje” / „Wyczyść filtry” (wg planu, Faza 3.5) | do potwierdzenia przez człowieka: kadr wykonany, treść nie była porównywana pikselowo |
| `09-thumbnail-broken` ↔ `before/09` | C5 (uszkodzona miniatura) | ikona przeglądarki w rogu pustego pola | ikona `ImageOff` (fallback) | do potwierdzenia przez człowieka |
| `10-kind-toggle-mobile` ↔ `before/10` | D9 (licznik rodzajów) | scrollWidth = clientWidth (327 = 327), nie ucina | bez zmian (327 = 327) | zamierzona brak zmiany; D9 nadal odroczone |
| `01-gallery-desktop` ↔ `before/01` | desktop bez zmian (pasek jak przed) | pasek w jednym wierszu | pasek w jednym wierszu (zgodnie z planem: desktop bez zmian) | do potwierdzenia przez człowieka: kadr nie był porównywany pikselowo |
| `03-table-desktop` ↔ `before/03` | tabela desktop bez zmian | jak przed | jak przed | zamierzona brak zmiany |
| `06-filters-active-mobile` ↔ `before/06` | aktywny filtr na 375 px | blok zawsze rozwinięty | blok zwinięty przy wejściu (Faza 2) | do potwierdzenia przez człowieka: kadr nie był oglądany po zmianie |
| `11-filters-expanded-mobile` | nowe: rozwinięcie „Filtry (2)” | brak | cztery pola z etykietami, „Ukryto 14 źródeł bez roku” | zamierzona |
| `12-visit-error-desktop` | nowe: nieudana wizyta rodzaju | brak (cichy brak reakcji) | „Nie udało się zastosować filtrów.” + „Spróbuj ponownie” przy pasku | zamierzona |

## Deferred (poza zakresem, z powodem)

- D1: ręczny pierścień w innych listach (`parishes-table.tsx`, `localities-table.tsx`, `tree/card-styles.ts` itd.). Jeden widok na zmianę.
- D2: dwa „Dodaj źródło” przy pustym drzewie. Osobna decyzja o pustym stanie.
- D3: 404 dla cudzego `?source=`. Dotyczy trasy panelu, nie listy.
- D4: `cursor: default` na elementach listy. Kosmetyka poza zakresem `/sources`.
- D5: `shadow-[1px_0_0_…]` w `lib/table-styles.ts`. Wspólna stała z `/persons`, zmiana wymaga osobnej decyzji o tokenie cienia.
- D6: rozjazd parsowania roku serwer/klient. Dotyczy logiki backendu, poza zakresem UI.
- D7: nieistniejące `place`/`archive` w URL. Filtr je czyści; brak wpływu na widok.
- D8: `style={{ width }}` w `source-panel.tsx`. Wnętrze panelu źródła poza zakresem.
- D9: przepełnienie przełącznika rodzajów przy 3-cyfrowych licznikach na 375 px. Pomiar `after/10` = `before/10` (327 = 327), przy obecnych danych nie ucina. Odroczone bez naprawy; wracamy, gdy licznik osiągnie trzy cyfry.
