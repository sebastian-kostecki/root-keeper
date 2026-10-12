# Zdjęcie osoby na karcie drzewa (S-16) — Plan Brief

> Full plan: `context/changes/person-photo-on-card/plan.md`

## What & Why

Autor chce dodać zdjęcie osoby i widzieć je na karcie drzewa; karta bez zdjęcia pokazuje inicjały (FR-015). Drzewo bez zdjęć wygląda ubogo, a dla genealoga twarz to szybki punkt orientacji. FR-015 jest pierwsze do odcięcia przy braku czasu, więc plan powtarza istniejące wzorce zamiast budować nowe mechanizmy.

## Starting Point

Osoba nie ma zdjęcia ani kolumn na plik. Źródła mają sprawdzony wzorzec:
- prywatny dysk `local`;
- pliki `{prefix}/{tree}/{uuid}`;
- miniatury z GD (`GenerateSourceThumbnail`);
- serwowanie przez kontroler z polityką i `Cache-Control: private`.

Karty drzewa i przodków dzielą jeden `CardBody`: wyśrodkowaną kolumnę bez awatara w kolumnie siatki 10rem. `PersonAvatar` z inicjałami jest tylko w panelu i na liście osób.

## Desired End State

W panelu osoby awatar otwiera menu „Dodaj/Zmień/Usuń zdjęcie”; wybór pliku od razu go zapisuje. Karta osoby w drzewie i widoku przodków ma po lewej pasek na całą wysokość ze zdjęciem (kadr środka) albo z inicjałami w kolorze płci, a obok imię, daty i wiek wyrównane do lewej. Awatary w panelu, liście i tabeli też pokazują zdjęcie. Pliki znikają przy podmianie, usunięciu osoby i konta, a odtwarzanie z kopii je przywraca i sprawdza.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Skąd zdjęcie | Własny plik osoby | Proste i niezależne od opisu źródeł; portrety nie mieszają się z dokumentami. |
| Kadrowanie | Automatyczny kadr środka (CSS `object-cover`) | Zero nowego kodu obrazu i zależności; autor może przyciąć zdjęcie przed wgraniem. |
| Układ karty | Zdjęcie po lewej, dane obok wyrównane do lewej | Wybór autora; zastępuje dzisiejszą wyśrodkowaną kolumnę. |
| Kształt | Pasek na całą wysokość karty (~4,5rem, ~3:4) | Największe zdjęcie przy danej wysokości, portrety kadrują się naturalnie. |
| Bez zdjęcia | Ten sam pasek z inicjałami w kolorze płci | Spójne z `PersonAvatar` w panelu i na liście. |
| Wąskie karty | Kolumna 10rem → 12rem w drzewie i przodkach; miejsce na oko zabiera tylko pierwsza linia imienia, dane wyrównane do góry | Daty i wiek mają ≥ 6rem obok paska (jak dziś), bez dalszego poszerzania drzewa. |
| Dodawanie | Menu na awatarze w nagłówku panelu, upload od razu | Zdjęcie zmienia się tam, gdzie je widać, bez kolizji z edycją sekcji. |
| Przechowywanie | `persons/{tree}/{uuid}` na dysku `local` + miniatura 400 px z `GenerateSourceThumbnail` | Kopiuje sprawdzony wzorzec skanów; kopia obejmuje go bez nowej pracy. |
| Nieudana miniatura | Błąd walidacji, nic nie zostaje zapisane | Zdjęcie bez miniatury nigdzie by się nie pokazało. |
| Cache | `photo_version` (UUID miniatury) w `?v=` | Podmiana widoczna od razu mimo `max-age=86400`. |
| Zasięg | Karty, nagłówek panelu, lista i tabela osób | Wszędzie tam, gdzie jest `PersonAvatar` lub `CardBody`; zakładka Rodzina bez zmian. |

## Scope

**In scope:**
- kolumny na `people` i endpointy POST/DELETE/GET `persons/{person}/photo`;
- sprzątanie plików przy podmianie, usunięciu zdjęcia, osoby i konta;
- `RestoreBackup` liczy, czyści i weryfikuje zdjęcia;
- `PersonAvatar` ze zdjęciem i menu zdjęcia w panelu;
- zdjęcie w skutkach usunięcia osoby;
- nowy układ `CardBody`, kolumna 12rem;
- testy Pest i jeden E2E.

**Out of scope:**
- wybór zdjęcia ze źródeł;
- narzędzie kadrowania i punkt kadru;
- podgląd przed zapisem i lightbox;
- trasa oryginału;
- zdjęcia w zakładce Rodzina i w wyborze osoby;
- galeria zdjęć osoby;
- nowe zależności.

## Architecture / Approach

Upload (`POST persons/{person}/photo`) przechodzi przez `PersonPhotoStoreRequest` do `StorePersonPhoto`. Akcja zapisuje oryginał i miniaturę, aktualizuje wiersz, a potem usuwa stare pliki. Zasoby (`SheetPersonResource`, `PersonResource`) wysyłają `photo_version`, a front buduje z niej URL przez Wayfinder (`personPhotoUrl`). `GET persons/{person}/photo` strumieniuje miniaturę po `Gate::authorize('view')`. Karta i awatar renderują `<img alt="">` z fallbackiem na inicjały, więc nazwa dostępna kart się nie zmienia.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend zdjęcia osoby | Kolumny, endpointy, sprzątanie plików, `RestoreBackup`, `photo_version`, Pest | Osierocone pliki przy podmianie lub usunięciu osoby; luka w odtwarzaniu kopii |
| 2. Zdjęcie w panelu i awatarach | Menu zdjęcia, `PersonAvatar` ze zdjęciem, skutki usunięcia | Upload z panelu wywołuje strażnika niezapisanych zmian |
| 3. Nowy układ karty | Pasek zdjęcia/inicjałów, dane obok, kolumna 12rem | Czytelność drzewa na 1366 px po poszerzeniu |
| 4. E2E zdjęcia na karcie | `person-photo-on-card.spec.ts` przez `/10x-e2e` | Lokator obrazka z `alt=""` (dopuszczalny `getByTestId`) |

**Prerequisites:** S-05 i S-06 (drzewo, panel osoby) są gotowe; Sail i serwer E2E (`scripts/e2e-server.sh`) działają.
**Estimated effort:** ~3–4 sesje w 4 fazach (faza 1 największa).

## Open Risks & Assumptions

- Poszerzenie kolumny o 20% zmniejszy skalę dopasowanego drzewa. Jeśli na 1366 px będzie nieczytelne, trzeba skorygować szerokość paska lub kolumny, ale nie wracać do 10rem.
- Panoramiczne zdjęcia (np. grupowe) dadzą miniaturę o małej wysokości, rozmytą w pasku. To akceptowalne przy kadrze środka.
- Zakładamy, że prod ma rozszerzenie EXIF. Bez niego orientacja może być zła, ale upload działa (jak przy źródłach).

## Success Criteria (Summary)

- Zdjęcie dodane w panelu jest od razu widoczne na karcie drzewa, karcie przodka, w panelu i na liście osób; po podmianie od razu widać nowe.
- Karta bez zdjęcia ma pasek z inicjałami w kolorze płci, a drzewo zostaje czytelne od 1366 px do 2560 px.
- Żaden plik zdjęcia nie zostaje po usuniętej osobie lub koncie, a odtwarzanie z kopii przywraca i weryfikuje zdjęcia.
