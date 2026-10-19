# Werdykt bramki wizualnej — oś czasu w zakładce Wydarzenia

Skrypt: `shots.mjs` (`SHOTS_PERSON=<id> SHOTS_TAG=p<id> node shots.mjs .`), dev app, demo-drzewo
(`TreeViewDemoSeeder`). Light + dark, 1366×768 i 390×844, `deviceScaleFactor: 2`. Stany: domyślny,
hover linku, focus linku (Tab).

Sceny (demo-drzewo nie ma jednej osoby z pełną sceną z planu, więc trzy osoby razem):

- `p173` Zbigniew Dziesiąty — małżeństwo, 10 dzieci, wiek przy każdym wierszu, krewni vs własne.
- `p125` Józef Kowalski — data przybliżona (ślub „ok. 1945”), zgon żony z wiekiem, własny zgon bez
  daty (`?`).
- `p161` Genowefa Pełnodanowska — własny zgon z wiekiem, pochowanie bez roku i bez wieku.

Nie pokryte zrzutem (pokrywa Pest `PersonTimelineTest`): dziecko zmarłe po własnym zgonie osoby,
dwa śluby.

## Ocena

| Kryterium | Wynik |
| --- | --- |
| Czytelność kolumny roku | OK — rok i wiek wyrównane do prawej, `tabular-nums`, „ok.” nad rokiem czytelne |
| Znaczniki `primary` / `tree-line` / pusty w dark | OK — własne jasnozielone, krewni szarzy, pusty z obrysem; wszystkie rozróżnialne |
| Przerywane odcinki | OK — zgon → pochowanie i zgon → `?` przerywane, reszta ciągła, linia kończy się na ostatnim znaczniku |
| Brak poziomego scrolla na 390 | OK — `scrollWidth <= clientWidth` w obu motywach |
| Link krewnego: hover / focus | OK — `text-primary`, podkreślenie na hover, widoczny focus ring (światło i dark) |
| „?” dla wpisu bez daty / pusta kolumna pochowania | OK |

Otwartych uwag brak.

## Poprawka po przeglądzie ręcznym

Pierwsza wersja miała odstęp `pb-4` na `<li>`, więc kolumna z linią kończyła się 16 px przed
następnym wierszem i linia była urwana między znacznikami (wyglądała na przerywaną). Odstęp przeniesiono
do kolumn (`timeline.tsx`); linia sięga teraz następnego znacznika. Zrzuty wykonano ponownie.
