# Werdykt wizualny: tree-fits-screen (faza 3)

Zrzuty: `after/*.png`, skrypt `shots.mjs` (viewport, bez `fullPage`, po dopasowaniu startowym). Seeder demo ma nowe pary: 8 dzieci (Dariusz Ósmy) i 10 dzieci (Zbigniew Dziesiąty). Ręczny zoom został usunięty, więc nie ma zrzutu „po Dopasuj”. Skala podana niżej to skala zastosowana przy starcie (odczyt z transformacji). Żaden zrzut nie przewija strony w pionie.

## Poprawka wprowadzona w tej fazie

Pierwsze uruchomienie pokazało błąd: slot „Dodaj dziecko” poszerzał zmierzoną treść (o ok. 11 rem przy 3 dzieciach i 10,75 rem przy 8), więc 3 i 8 dzieci na 1366 startowało w kotwicy 75% z ucięciem (skala całości 0,68 i 0,69), a nie „całość”. Poprawka w `family-sheet.tsx`:
- slot ma zawsze szerokość 1 kolumny (nie szerokość karty dziecka);
- stoi obok ostatniego dziecka przy 1–3 dzieciach (przy 3 poszerza treść o ok. 0,4 rem);
- od 4 dzieci stoi pod ostatnim dzieckiem, wyrównany do prawej, i nie poszerza arkusza.

Korekta po przeglądzie (`reviews/impl-review.md` F3, 2026-10-05): pierwsza wersja liczyła położenie z szerokości rzędu i tolerancji 1 rem. Wbrew wcześniejszemu opisowi („obok do 5, pod od 6”) dawała: obok przy 1–3, pod przy 4, obok przy 5–6, pod od 7. Zastąpiona jednym progiem (`SLOT_BESIDE_MAX_CHILDREN = 3`), a zestaw dostał zrzuty 4 i 6 dzieci (seeder: Czesław Czwarty, Szymon Szósty).

Po poprawce: 3 dzieci 0,772, 8 dzieci 0,769, oba w całości.

## Zrzuty

| Zrzut | Skala | Ocena |
|---|---|---|
| proband-1366-menu-open | 0,775 całość | OK: całość, belka i zejścia do 2 dzieci, slot obok rzędu, brak ucięć. Tekst czytelny (nazwa 16 px x 0,775). |
| proband-1366-menu-closed | 0,914 całość | OK: szerszy obszar daje większą skalę. |
| proband-1920 | 1,177 całość | OK: ostry tekst. |
| proband-2560 | 1,5 (limit) | OK: limit 150% działa, drzewo wypełnia ok. 2/3 szerokości, tekst ostry (transformacja 2D bez `will-change`). |
| couple-0-children-1366 / 2560 | 0,775 / 1,5 | OK: para bez dzieci (Leszek Zerowy, dodany po przeglądzie F9), komunikat „Brak dzieci w tym związku.” i slot pod parą. |
| couple-3-children-1366 / 2560 | 0,772 / 1,5 | OK: całość, pień do belki, 3 zejścia, slot obok rzędu. |
| couple-4-children-1366 | 0,769 całość | OK: szerokie karty, rząd w szerokości dziadków, belka i 4 zejścia, slot pod ostatnim dzieckiem. |
| couple-5-children-1366 / 2560 | 0,775 / 1,5 | OK: wąskie karty, belka i 5 zejść, slot pod ostatnim dzieckiem. |
| couple-6-children-1366 | 0,775 całość | OK: wąskie karty, belka i 6 zejść, slot pod ostatnim dzieckiem. |
| couple-8-children-1366 / 2560 | 0,769 / 1,5 | OK: całość (kryterium ≥75% spełnione), rząd w szerokości dziadków, slot pod ostatnim dzieckiem. Na 2560 limit 150%. |
| couple-10-children-1366 | 0,75 kotwica | Zgodnie z planem: start w 75% wyśrodkowany na parze, rząd wychodzi poza kadr po obu stronach, slot (pod ostatnim dzieckiem) poza kadrem po prawej, dostępny po przeciągnięciu. |
| couple-10-children-2560 | 1,304 całość | OK: całość mieści się, bez ucięć, slot pod ostatnim dzieckiem. |
| narrow-proband (canvas 670 px) | 0,75 kotwica | Zgodnie z planem: wyśrodkowane na parze, reszta poza kadrem (przeciąganie). |
| narrow-couple-8-children (670 px) | 0,75 kotwica | Zgodnie z planem: wyśrodkowane na parze, belka ciągnie się poza kadr. |
| dark-couple-5-children-1366 | 0,775 całość | OK: kontrast linii, kart i slotu w trybie ciemnym wystarczający. |

## Kryteria

- Skala ≥75% dla ≤8 dzieci: spełnione (0,769 do 1,5).
- 10 dzieci na 1366: 75% wyśrodkowane na parze: spełnione.
- Limit 150% na 2560: spełnione, bez rozmytego tekstu.
- Belka i zejścia: wszędzie jedna belka, pień od pary, zejście do każdego dziecka.
- Slot: obok rzędu przy 1–3 dzieciach, pod ostatnim dzieckiem od 4 (odstępstwo od „obok” dla szerokich rzędów, patrz poprawka).
- Zasłanianie kart: nie dotyczy, bo pasek kontrolek został usunięty razem z ręcznym zoomem.

## Uwagi

- Przy kotwicy (10 dzieci, zwężenie) część drzewa jest poza kadrem. Bez ręcznego zoomu jedyną drogą jest przeciąganie.
- Przy kotwicy slot „Dodaj dziecko” w szerokich rzędach może być poza kadrem.
