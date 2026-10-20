# Werdykt wizualny — szczegóły miejscowości

Stan: zrzuty z 2026-10-20 (uzupełnione w przeglądzie implementacji, ustalenie F1), serwer E2E `scripts/e2e-server.sh` na 127.0.0.1:8100, baza `e2e` zasiana `TreeViewDemoSeeder`, konto `tree-demo@example.com`; `node shots.mjs <abs>/screenshots`. Skrypt dodaje własną nieistniejącą wieś („Stare Zalesie <sufiks>”, 49.9061, 20.4547, „gm. Lipnica, pow. bocheński, Galicja”, „zajęta pod zalew 1975”) i usuwa ją na końcu. 1440×900 i 375×812, motyw jasny i ciemny.

| Widok | Zrzuty | Status | Uzasadnienie |
|---|---|---|---|
| Lista `/places` z kolumną Rodzaj i plakietką | `01`, `04`, `07`, `10` | OK | Kolumna „Rodzaj” między Nazwą a Opisem („wieś” / „—”); plakietka „nie istnieje” w przyklejonej komórce nazwy, na 375 px nazwa zawija się w 3 linie obok plakietki, bez nakładania na „Rodzaj”. |
| Panel nieistniejącej miejscowości | `02`, `05`, `08`, `11` | OK | Plakietka „nie istnieje” i opis zaniku nad formularzem; pola w kolejności Nazwa → Rodzaj → Współrzędne → Przynależność historyczna → Opis → Nie istnieje dziś → Co się stało; współrzędne znormalizowane „49.9061, 20.4547”; „Pokaż na mapie” → `https://www.openstreetmap.org/?mlat=49.9061&mlon=20.4547#map=14/49.9061/20.4547`. |
| Podpowiedź miejsca urodzenia (`/persons?add=person`) | `03`, `06`, `09`, `12` | OK | Opcja „Stare Zalesie … nie istnieje” (nazwa dostępna z dopiskiem), dopisek w `text-muted-foreground`; wpisany fragment nazwy dopasowuje miejscowość, „Dodaj miejscowość „…”” pod spodem. |

## Czego nie sprawdzono

- Dopisku w pozostałych miejscach wyboru (zamieszkanie, cmentarz, siedziba parafii, dialog scalania) — ten sam `LocalityPicker` / `SuggestField` z `hintOf`, pokryte przeglądem kodu.
- Komunikatu błędnych współrzędnych pod polem (punkt ręczny 2.6) — pokryty testami Pest (dataset błędnych wartości).
