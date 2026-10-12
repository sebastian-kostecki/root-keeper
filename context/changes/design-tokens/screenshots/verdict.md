# Werdykt bramki wizualnej (design-tokens, faza 4)

Porównanie zrzutów `before/` (przed zmianą tokenów) i `after/` (po fazie 2–3, build z 12:53).
Zrzuty w rozdzielczości 1440 px; dla `/persons` strona ma wysokość 6508 px, więc oceniam ją z pomniejszenia.

## Jasny motyw

- **/tree (jasny):** tło przesunęło się z chłodnej bieli na papierowe, ciepłe; menu zostaje ciemne. Tytuł „Jan Kowalski” i logo „Root Keeper” są pisane szeryfowo (Newsreader). Imiona na kartach są szeryfowe i wąskie karty łamią je w środku słowa („Kowalsk / i”, „Wiśniew / ska”). To wada także w „przed” (łamanie w sans), ale szeryf ją nasila. Kontrast tekstu dobry.
- **/persons (jasny):** papierowe tło, tytuł „Osoby” szeryfowy, akcent mchu na przycisku „Dodaj osobę” i w aktywnym przełączniku „Tabela”. Wiersz w fokusie ma pełny pierścień mchowy 3 px. Nic nie odstaje.
- **/sources (jasny):** papierowe tło, szeryfowy tytuł „Źródła”, zielony przycisk „Dodaj źródło”. Kafle i miniatury bez zmian. Nic nie odstaje.
- **/places (jasny):** szeryfowy tytuł „Miejsca”, papierowe tło, zielony przycisk „Dodaj miejscowość”, zakładki Miejscowości/Parafie bez zmian w kształcie. Nic nie odstaje.

## Ciemny motyw

- **/tree (ciemny):** tło i menu ciemne w obu wersjach; karty płci są wyraźnie widoczne (róż i błękit jaśniejsze niż w „przed”). Tytuł i imiona szeryfowe, łamanie słów jak w jasnym motywie. Kontrast tekstu na kartach dobry.
- **/persons (ciemny):** ciemne tło z zieloną liniją menu, tytuł szeryfowy, jasny mietowy przycisk z ciemnym tekstem (czytelny). Pierścień fokusu w jasnym mchu, wyraźny na ciemnym tle.
- **/sources (ciemny):** ciemne tło, szeryfowy tytuł, jasny przycisk z ciemnym tekstem. **Odstaje:** miniatury dokumentów (kafle z liniami kartki) w „przed” były beżowe na ciemnym tle, w „po” są prawie tak ciemne jak tło, linie słabo widoczne. Wygląda na zmianę tokenu powierzchni lub tła tych miniatur w ciemnym motywie. Wymaga sprawdzenia w `source-thumbnail.tsx` i w tokenach `--source*`, zanim zaakceptujemy zrzut.
- **/places (ciemny):** ciemne tło, szeryfowy tytuł, jasny przycisk z ciemnym tekstem, tabela czytelna. Nic nie odstaje.

## Fokus (log `boxShadow`)

Log z ostatniego przebiegu (po przebudowie buildu, patrz niżej):

- Jasny: `rgb(44, 90, 67) 0px 0px 0px 3px`, `outlineStyle: none` — pełny kolor `ring`, 3 px, bez przezroczystości.
- Ciemny: `rgb(134, 199, 162) 0px 0px 0px 3px`, `outlineStyle: none` — pełny kolor `ring`, 3 px, bez przezroczystości.

Pierwszy przebieg dał `oklab(… / 0.5) 0px 0px 0px 3px` (krycie 50%). Przyczyną był nieaktualny build w `public/build` (z 12:20, przed commitem fazy 2 z 12:47). Po `npm run build` log pokazuje pełny kolor. Zrzuty „po” wykonano dopiero z nowym buildem.

## Wnioski

Zmiany tokenów są widoczne i spójne: papierowe tło, mech jako jedyny akcent, ciemne menu w obu motywach, szeryfowe tytuły. Jedyne zastrzeżenie to miniatury źródeł w ciemnym motywie (patrz wyżej) i łamanie imion na wąskich kartach drzewa, które szeryf nasila. Oba do decyzji użytkownika przed akceptacją.
