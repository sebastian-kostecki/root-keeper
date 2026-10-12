# Werdykt zrzutów — sources-places-polish

Zrzuty „przed”: `before/` (faza 1). Zrzuty „po”: `after/` (faza 3, świeży build, ten sam `shots.mjs`).
Motywy: jasny i ciemny. Ocena wizualna wykonana na zrzutach jasnych. Zrzuty ciemne (11–32 w `after/`) wygenerowane, ale NIE oglądane wizualnie w tej sesji — ocena w sekcji „Motyw ciemny” opiera się wyłącznie na logach i na tym, że ta sama klasa/komponent co w jasnym; do potwierdzenia przez człowieka.

## Widoki zmienione w fazie 3

| Widok | Zrzut po | Zmiana | Ocena |
|---|---|---|---|
| Pusty spis `/places` (miejscowości) | `09-places-empty-light.png` | Tytuł „Brak miejscowości”, przycisk „Dodaj miejscowość” jako jedyny `default` | OK |
| Pusty spis `/places?kind=parafie` | `10-places-parishes-empty-light.png` | Tytuł „Brak parafii” | OK (tytuł zgodny z mapą `emptyTreeCopy`) |
| Pusta lista `/sources` | `08-sources-empty-light.png` | Tytuł „Brak źródeł” | OK |
| Ustawienia profilu | `05-settings-profile-light.png` | Breadcrumb/Head „Ustawienia profilu”, menu „Profil / Zabezpieczenia / Wygląd”, nagłówek „Ustawienia” z opisem, sekcje „Profil” i „Usunięcie konta” po polsku | OK dla zakresu planu. **Uwaga:** etykiety pól i przycisków („Name”, „Email address”, „Save”, „Delete account”, „Warning”) zostają po angielsku — poza zakresem planu („Teksty formularzy ustawień … bez zmian”). Do osobnej zmiany. |
| Ustawienia zabezpieczeń / wyglądu | `06-…`, `07-…` | Nagłówki po polsku | Sprawdzone w zrzutach; etykiety formularzy po angielsku (jak wyżej) |
| Panel miejscowości | `22-locality-panel-light.png` | Faza 2, bez zmian w fazie 3 | **Odstaje:** przycisk „Scal z…” w nagłówku jest przycięty do „Scal z…” (ucięty tekst) przy szerokości panelu. Patrz „Otwarte”. |
| Panel parafii | `23-parish-panel-light.png` | Faza 2 | **Odstaje:** ta sama ucięta etykieta „Scal z…”; tytuł „Parafia rzymskokatolicka…” ucięty przez `truncate` (zgodnie z planem: „szerokość tytułu w nagłówku”). |
| Galeria `/sources`, fokus karty | `29-sources-focus-card-light.png`, log `boxShadow` | Faza 1 | Log: `rgb(250,248,244) 0 0 0 2px` + `rgb(44,90,67) 0 0 0 5px` — pierścień z odstępem, odróżnialny od zaznaczenia. Pierwsza kolumna nie jest przycięta. OK |

## Motyw ciemny

- Pusty spis i ustawienia (`19-…`, `15-…`, `16-…`, `17-…`) — NIE oglądane; do potwierdzenia.
- Panele w ciemnym motywie (`25-…`, `26-…`, `27-…`) — NIE oglądane; prawdopodobnie ta sama ucięta etykieta „Scal z…” (ten sam układ).
- Galeria w ciemnym (`30-sources-focus-card-dark.png`) — log fokusu: `rgb(19,24,20) 0 0 0 2px` + `rgb(134,199,162) 0 0 0 5px`, pierścień z odstępem.

## Otwarte (do decyzji, nie blokuje werdyktu technicznego)

1. **„Scal z…” ucięte w nagłówku panelu miejscowości i parafii** (w jasnym i ciemnym). Nagłówek ma 24 rem; przy dłuższym tytule rekordu przycisk się zwęża. Propozycja: skrócić tytuł (`truncate` już jest) albo przenieść „Scal z…” do stopki obok „Zapisz …” — decyzja projektowa, nie poprawiam bez zgody.
2. **Etykiety formularzy ustawień po angielsku** — poza zakresem planu; osobna zmiana.
3. **F3 (ciemna miniatura na `/sources`)** — zamknięty w fazie 1: brak podglądu ma bursztynową powierzchnię `bg-source-surface` i ikonę `ImageOff` w `text-source` w obu motywach (`08`, `18`, galeria).

## Status

- Zrzuty „po” wygenerowane i przejrzane: TAK.
- Werdykt zaakceptowany przez człowieka: NIE — czeka na decyzję (punkt 1) i potwierdzenie.
