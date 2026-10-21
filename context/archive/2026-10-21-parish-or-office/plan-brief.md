# Parafia / urząd z wezwaniem — Plan Brief

> Full plan: `context/changes/parish-or-office/plan.md`
> Research: `context/changes/parish-or-office/research.md`

## What & Why

Parafia jest dziś identyfikowana wolnym tekstem nazwy + wyznaniem, a siedziba jest opcjonalna, więc „Parafia w Lubomlu” i „Luboml” to niepowiązane byty, a akty USC czy okręgów bóżniczych nie mają miejsca. Zmiana robi z parafii **prowadzącego akta**: siedziba (wymagana) + rodzaj + wyznanie + „Wezwanie / wyróżnik”, z etykietą jak w Geneteka („Płock św. Bartłomieja (rzym.-kat.)”, „Gostynin USC”).

## Starting Point

Klucz `(tree_id, lower(name), coalesce(religion,''))` żyje w indeksie, regule walidacji i `MergeParish`; `Parish::label()` daje „Nazwa (rzymskokatolickie)”, a 8 miejsc czyta surowe `name`. `MergeLocality` przenosi parafie bez kontroli kolizji. Wzorzec do skopiowania istnieje: cmentarze (wymagana miejscowość w kluczu, reguła `implicit`, `moveInto()`).

## Desired End State

Zakładka „Parafie i urzędy” na `/places` i pole „Parafia / urząd” w źródle pokazują etykiety z pól. Panel ma Rodzaj, Siedzibę (wymaganą), Wezwanie / wyróżnik i Wyznanie; stary rekord pokazuje „Dawną nazwę” tylko do odczytu z przyciskiem „Usuń dawną nazwę”. Duplikat pod nowym kluczem jest niemożliwy, także po scaleniu miejscowości. Istniejące dane nie tracą żadnej wartości.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Format etykiety | Geneteka ze skrótem wyznania, dopisek rodzaju po siedzibie | Genealog rozpoznaje parafię po siedzibie i wezwaniu | Research (decyzja 1) |
| Dawna nazwa | `name` zostaje tylko do odczytu, można ją tylko usunąć; stary wiersz bez siedziby ma etykietę z dawnej nazwy | „Copy first, drop later” | Research (decyzja 2) |
| Wyróżnik | Jedno pole „Wezwanie / wyróżnik” (św. Bartłomieja, Fara, Cyrkuł I) | Jeden wyróżnik wystarcza do klucza i etykiety | Research (decyzja 3) |
| Nazwy w UI i w kodzie | „Parafie i urzędy”, „Parafia / urząd”; trasy, slug, klucze filtra bez zmian | Zmiana dotyczy etykiet, nie identyfikatorów | Research (decyzja 4) |
| Klucz | `(tree, siedziba, rodzaj, wyznanie, lower(wyróżnik))`, indeks częściowy `WHERE locality_id IS NOT NULL` | Stare wiersze bez siedziby nie mogą kolidować między sobą | Plan |
| Siedziba w bazie | Nullable dla starych wierszy, wymagana w walidacji każdego zapisu; CHECK „siedziba albo dawna nazwa” | Precedens cmentarzy, brak sensownego placeholdera | Research → Plan |
| Kolizje w migracji | W grupach o tej samej siedzibie i wyznaniu cała dawna nazwa kopiowana do wyróżnika | Bez utraty danych, deploy przechodzi, stary indeks gwarantuje unikalność | Plan |
| Rodzaj „inny” | Wyróżnik wymagany, bez dopisku rodzaju w etykiecie | Etykieta zawsze mówi, co to za urząd | Plan |
| Tekst z „Dodaj „X”” | Dokładna nazwa miejscowości → siedziba; inaczej pusta siedziba (bez zmian w `SuggestField`) | Etykiety zaczynają się od siedziby | Plan |
| Scalanie miejscowości | Najpierw scala kolidujące parafie (`MergeParish::moveInto()`), jak cmentarze | Inaczej przeniesienie złamie indeks | Research → Plan |
| Uzupełnianie przy scalaniu parafii | Pola klucza celu uzupełniane razem i tylko przy wolnym kluczu; dawna nazwa niezależnie | Zachowuje regułę „cel wygrywa” i indeks | Plan |

## Scope

**In scope:**
- Enum `ParishKind`, `Religion::shortLabel()`, migracja z testem, nowy klucz i etykieta
- Zapis z wymaganą siedzibą, usuwanie dawnej nazwy, sortowanie po etykiecie
- Scalanie parafii i miejscowości pod nowym kluczem
- Panel, okno dodawania (z siedzibą wybraną po wpisanej nazwie), lista, źródło, filtry
- Vitest, Pest, specyfikacje E2E, PRD FR-001/FR-008, reguły `CLAUDE.md`, zrzuty

**Out of scope:**
- Automatyczne rozbijanie dawnych nazw; usunięcie kolumny `name`
- Archiwum jako rekord; parafie w panelu miejscowości
- Zmiana nazw w kodzie, tras, slugów; znacznik „nie istnieje” w etykiecie parafii
- Wyznanie wynikające z rodzaju; osobne rodzaje dla okręgu bóżniczego i metrykalnego

## Architecture / Approach

Klucz trzymany spójnie w czterech miejscach: indeks częściowy, reguła `implicit` w `ParishValidationRules`, `MergeParish::moveInto()` i `MergeLocality::mergeCollidingParishes()`. Etykietę buduje wyłącznie `Parish::label()` (wymaga załadowanej `locality`), klient tylko ją wyświetla i po niej szuka. Zapis przechodzi przez `parishDetails()` → `StoreParish/UpdateParish::handle(..., array $details)` jak u cmentarzy.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Schemat, migracja i zapis | Nowe pola, klucz, etykieta, wymagana siedziba, testy migracji | Kolizje w danych produkcyjnych; przepisanie wielu testów opartych na `name` |
| 2. Scalanie | `moveInto()`, scalanie kolidujących parafii przy scalaniu miejscowości | Reguły uzupełniania łamiące indeks |
| 3. UI „Parafia / urząd” | Panel, okno, lista, źródło, nazwy, Vitest | Przepisanie fixtur Vitest na nowy kształt |
| 4. E2E, dokumentacja, zrzuty | Zielone specyfikacje, PRD i `CLAUDE.md`, bramka wizualna | Specyfikacje oparte na `name` |

**Prerequisites:** zarchiwizowane `locality-details` i `cemeteries` (są); Sail uruchomiony; baza `e2e` dla Playwright.
**Estimated effort:** ~3–4 sesje w 4 fazach (największa faza 1).

## Open Risks & Assumptions

- Danych produkcyjnych nie sprawdzano: rekordy w grupach kolizji dostaną brzydkie etykiety do ręcznej poprawki.
- `down()` nie przejdzie, gdy nowe rekordy dają tę samą nazwę i wyznanie pod starym kluczem (opisane w komentarzu migracji).
- Skróty wyznań poza „rzym.-kat.”, „gr.-kat.”, „ewang.”, „mojż.” („prawosł.”, „inne”) przyjęte bez weryfikacji w Geneteka.
- Między fazą 1 a 3 UI wysyła stare pola — fazy idą jedna po drugiej, bez wdrożenia pośrodku.

## Success Criteria (Summary)

- Autor dodaje „Płock św. Bartłomieja (rzym.-kat.)” i „Gostynin USC” i nie może dodać drugiego takiego samego rekordu.
- „Parafia w Lubomlu” po migracji ma nietkniętą nazwę i źródła, a autor przenosi ją na siedzibę Luboml i usuwa dawną nazwę.
- Scalenie dwóch miejscowości z tą samą parafią daje jedną parafię ze źródłami obu.
