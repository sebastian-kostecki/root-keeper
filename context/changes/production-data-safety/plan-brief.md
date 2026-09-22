# Bezpieczeństwo danych produkcyjnych — Plan Brief

> Full plan: `context/changes/production-data-safety/plan.md`

## What & Why

F-01 z roadmapy M-1: zamknąć luki produkcji, zanim wejdzie prawdziwe drzewo. PRD wymaga, żeby dane i skany nie trafiały do podmiotów trzecich i nie ginęły (RPO 24 h), a nie ma kosza ani cofania — utrata danych bez przećwiczonego odtworzenia jest nieodwracalna.

## Starting Point

Aplikacja działa na VPS, ale 2026-09-22 ruch nadal idzie przez Cloudflare (proxy, `cf-ray`, certyfikat Google Trust Services). Spatie robi nocne backupy bazy i `storage/app`, lecz bez szyfrowania (puste `BACKUP_ARCHIVE_PASSWORD`), z alarmem mailowym bez skonfigurowanego SMTP; `deploy.sh` migruje bez backupu, a odtworzenia nikt nie próbował.

## Desired End State

`genealog.kostecki.dev` wskazuje prosto na VPS z certyfikatem Let's Encrypt. Archiwa są szyfrowane AES-256 hasłem, które ma kopię poza VPS, a mail alarmowy dociera. `deploy.sh` odmawia deployu bez sekretów i robi backup bazy przed uruchomieniem nowego kodu. Odtworzenie archiwum z plikiem w roli skanu zostało raz wykonane i spisane w runbooku.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Kanał alarmów | Mail przez istniejący SMTP kostecki.dev | Zero nowego kodu; alert niesie tylko metadane backupu, nie dane. |
| Hasło archiwów | Kopia poza VPS (poza `.env.prod`) | Utrata VPS nie może zabrać hasła razem z archiwami. |
| Backup przed migracją nieudany | Przerwij deploy; pomiń tylko przy braku kontenera | Migracja nigdy nie rusza bazy bez świeżej kopii. |
| Miejsce backupu w deployu | W starym, działającym kontenerze, przed `up -d` | Po `up -d` nowy kod już chodzi na starym schemacie. |
| Próba odtworzenia | Teraz, z ręcznie wrzuconym plikiem testowym | F-01 kończy się przed prawdziwymi danymi; ten sam format archiwum. |
| Runbook i poprawka DNS | `kostecki-dev-infra/docs/root-keeper.md` (+ wiersz w `SETUP.md`) | Tam już żyje dokumentacja deployu tej aplikacji. |
| Automatyczni strażnicy | Preflight `.env.prod` w `deploy.sh` | Blokuje deploy z nieszyfrowanym backupem, niemym alarmem lub offsite. |

## Scope

**In scope:**
- Preflight `.env.prod` i backup `--only-db` przed migracją w `scripts/deploy.sh` / `scripts/lib/production-deploy.sh`; komentarze w `.env.prod.example`
- DNS-only dla `genealog` + poprawka dokumentacji infra
- Sekrety SMTP i hasła archiwów, pierwszy chroniony deploy, usunięcie archiwów sprzed szyfrowania
- Próba odtworzenia i przebudowy, sekcja „Restore” w runbooku

**Out of scope:**
- Wymiarowanie backupów skanów (limity, retencja, restic) — po 20–30 prawdziwych skanach
- Testy Pest na konfiguracji backupu, blokada w samej aplikacji, heartbeat
- Offsite, rollback `DEPLOY_REF`, DNS-only dla innych aplikacji na VPS
- Powtórka próby z prawdziwym skanem z UI (należy do S-02)

## Architecture / Approach

Kod najpierw (musi być na `main`, żeby pierwszy deploy miał strażników), potem kroki ręczne w kolejności ryzyka: DNS (podmiot trzeci widzi ruch już teraz), sekrety i deploy, próba odtworzenia na już zaszyfrowanym archiwum. Zmiany w `kostecki-dev-infra` to osobne commity w tamtym repo.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Strażnicy deployu | Preflight `.env.prod` + backup bazy przed `up -d` | Bash bez testów w CI — weryfikacja atrapami |
| 2. DNS-only | Ruch bez Cloudflare, cert Let's Encrypt, poprawione docs | Traefik mógł nigdy nie wystawić certyfikatu (ukrywało to proxy) |
| 3. Sekrety i chroniony deploy | Szyfrowane archiwa, działający mail, wdrożeni strażnicy | Montaż pojedynczego pliku `.env.prod` + `config:cache` trzymają stare wartości |
| 4. Próba odtworzenia i runbook | Przećwiczone odtworzenie, spisana procedura | AES-zip wymaga `7z`, dump tylko do Postgresa 18 |

**Prerequisites:** dostęp SSH do VPS, panel Cloudflare, dane skrzynki SMTP kostecki.dev, miejsce na kopię hasła poza VPS; merge `develop` → `main` przed fazą 3.
**Estimated effort:** ~1 sesja na fazę 1, ~1–2 sesje na kroki ręczne faz 2–4.

## Open Risks & Assumptions

- Przy pierwszym deployu z tą zmianą trzeba ręcznie `git pull` przed `deploy.sh` (skrypt aktualizuje sam siebie w trakcie działania).
- Pierwszy backup przed migracją po dodaniu hasła może być jeszcze nieszyfrowany (stary kontener, stara konfiguracja) — akceptowalne, dane testowe.
- Backupy nadal leżą na dysku podpiętym do tego samego VPS — zaakceptowane dla MVP (infrastructure.md, Risk Register).
- Próba z plikiem testowym nie sprawdza skanu wgranego przez UI — powtórka w S-02.

## Success Criteria (Summary)

- Z zewnątrz: `genealog.kostecki.dev` bez Cloudflare, z certyfikatem Let's Encrypt.
- Najnowsze archiwum jest AES-256, odszyfrowuje się hasłem spoza VPS, a mail alarmowy dociera.
- Plik z archiwum wraca z tą samą sumą SHA-256, dump wczytuje się do Postgresa 18, procedura jest spisana.
