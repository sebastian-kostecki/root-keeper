# Import „od zera” poza produkcją — Plan Brief

> Full plan: `context/changes/import-from-scratch-outside-prod/plan.md`

## What & Why

Komenda `import:research` dostaje opcję `--from-scratch` (S-10, FR-020, nice-to-have). Poza produkcją, po tabeli liczb i potwierdzeniu, usuwa wszystkie osoby i fakty drzewa wskazanego konta, a potem importuje jak do pustego drzewa. Dzięki temu autor może powtarzać import na koncie testowym bez ręcznego czyszczenia i bez setek pytań o przywracanie. Na produkcji opcja jest niedostępna, bo pomyłka w e-mailu skasowałaby drzewo (Socrates przy FR-020).

## Starting Point

Import umie już pierwszy zapis, ponowny import, dopasowanie osób i miejsc oraz źródła ze skanami. Pamięta, co zapisał (`repo_snapshots`). Samo usunięcie osób z drzewa nie wystarcza. Fakty nie mają FK do osób, więc zostałyby bez uczestników. Pamięć importu oznaczyłaby każdą osobę jako „usuniętą w aplikacji” z pytaniem „przywrócić?”, a wcześniejsze „zostaw” blokowałyby ją po cichu.

## Desired End State

`import:research <email> <repo> --from-scratch` lokalnie:
1. Czyta repo i pokazuje tabelę: osoby, fakty, relacje, zamieszkania, zdjęcia, powiązania ze źródłami, pochowani na nagrobkach, pamięć importu.
2. Pyta tak/nie z domyślnym „nie”.
3. Po „tak” usuwa wszystko w jednej transakcji, usuwa zdjęcia z dysku i przechodzi zwykły pierwszy import.

Wynik jest taki sam jak pierwszy import do pustego drzewa, a źródła ze skanami zostają i podpinają się do nowych faktów. Na produkcji, bez terminala i przy repo bez poprawnych osób komenda odmawia, zanim cokolwiek usunie.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Zakres usuwania | Osoby, fakty, relacje, zamieszkania, warianty, zdjęcia, pamięć importu osób i faktów; źródła, skany, pamięć `ZR-` i miejsca zostają | Dokładnie FR-020; skany nie kopiują się od nowa, a pewne miejsca dopasowują się bez pytań |
| Osoby dodane ręcznie | Usuwane razem z resztą | „Od zera” to puste drzewo osób; poza produkcją to konto testowe lub lokalne |
| Moment usunięcia | Od razu po potwierdzeniu, przed klasyfikacją, w osobnej transakcji | Cała dalsza ścieżka importu działa bez zmian na prawdziwie pustym drzewie |
| Potwierdzenie | Tabela liczb + tak/nie z domyślnym „nie”; bez terminala odmowa; bez `--force` | Wzorzec `RestoreBackup` i końcowego pytania importu |
| Strażnik produkcji | `app()->isProduction()` przed odczytem repo | Brak `APP_ENV` też oznacza produkcję, więc strażnik jest bezpieczny domyślnie |
| Pamięć importu | Usuwane klucze spoza `ZR-` (`RepoKeys::kindOf`), także `deletion_kept` | Bez tego każda osoba wraca jako „usunięta w aplikacji” lub jest blokowana po cichu |
| Środowisko worktree | `composer install` + kopia `.env` w worktree, polecenia w kontenerze głównego Sail z katalogiem worktree | Bez drugiego stosu; dowiązanie `vendor/` ładowałoby `app/` z `develop` |

## Scope

**In scope:**
- `TreeClearance`, `PlanTreeClearance`, `ClearTree` z testami.
- Opcja `--from-scratch` ze strażnikami (produkcja, terminal, puste repo), tabelą, pytaniem i uczciwymi komunikatami po odmowie zapisu.
- Testy komendy z drugim kontem.

**Out of scope:**
- Usuwanie źródeł, skanów i miejsc oraz sprzątanie nieużywanych miejsc.
- `--force` i potwierdzanie przepisaniem e-maila.
- Kopia bazy poza produkcją.
- Usuwanie „wirtualne” razem z zapisem.
- Zmiany w klasyfikacji, dopasowaniu, planowaniu i zapisie importu.
- UI i migracje.

## Architecture / Approach

Decide/apply jak w reszcie importu:
- `PlanTreeClearance` liczy, co zniknie.
- `ClearTree` w jednej transakcji usuwa zdarzenia, potem osoby (resztę zabierają kaskady bazy), potem snapshoty spoza `ZR-`. Po commicie usuwa katalog `persons/{tree_id}`.
- Komenda: konto → strażnik produkcji → odczyt repo i raport → strażnik terminala i pustego repo → tabela + pytanie → `ClearTree` → istniejąca ścieżka importu bez zmian.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Akcje czyszczenia drzewa | Liczenie i usuwanie osób, faktów, pamięci i zdjęć jednego drzewa | Kolejność zdarzenia → osoby; usunięcie zdjęć tylko po commicie; nic z drugiego konta |
| 2. Opcja `--from-scratch` w komendzie | Strażnicy, tabela, pytanie, usunięcie, zwykły import, komunikaty, testy komendy | Strażnik produkcji musi stać przed odczytem repo; po usunięciu import nie może pytać o przywracanie |

**Prerequisites:** S-02 (gotowe). Jednorazowe przygotowanie worktree: kopia `.env` i `composer install` w kontenerze `root-keeper-laravel.test-1` (polecenia w planie). Baza `testing` jest wspólna z głównym checkoutem, więc testów nie uruchamia się równolegle.
**Estimated effort:** ~1 sesja, 2 fazy.

## Open Risks & Assumptions

- Po odmowie końcowego zapisu drzewo zostaje puste. To świadomie zaakceptowane, bo dotyczy tylko kont poza produkcją, a komunikat mówi to wprost.
- Czyszczenie pamięci kasuje zapamiętane dopasowania miejsc, więc wątpliwe i nowe miejsca import zapyta ponownie. Pewne dopasowania przechodzą bez pytań.
- Ręczne źródła tracą powiązania z faktami. Wszystkie źródła tracą listy pochowanych na nagrobkach (import ich nie odtwarza); tabela przed pytaniem pokazuje ich liczbę.

## Success Criteria (Summary)

- Na koncie testowym `--from-scratch` z „tak” daje drzewo jak po pierwszym imporcie, bez pytań „Usunięte w aplikacji”, a źródła ze skanami są podpięte do faktów.
- Na produkcji, bez terminala i przy pustym repo nic nie zostaje usunięte. Drugie konto zawsze pozostaje nietknięte.
