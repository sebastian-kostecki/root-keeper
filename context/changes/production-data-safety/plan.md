# Bezpieczeństwo danych produkcyjnych — plan implementacji

## Overview

F-01 z roadmapy (M-1): zanim na produkcję trafi prawdziwe drzewo, backupy mają być szyfrowane, alarmy o awarii backupu mają docierać, przed każdą migracją ma powstawać backup bazy, a odtworzenie backupu z plikiem skanu ma zostać raz przećwiczone i spisane. PRD nie ma kosza ani cofania, więc utrata danych bez przećwiczonego odtworzenia jest nieodwracalna (Guardrails: „Wprowadzone dane i wgrane skany nie giną”; NFR: RPO 24 h).

**Decyzja właściciela (2026-09-22):** Cloudflare zostaje przed `genealog.kostecki.dev` jako Proxied — celowo, dla ochrony DDoS/bot na edge'u. To koliduje z NFR PRD „brak podmiotów trzecich" (ruch i skany przechodzą przez Cloudflare); wymaga przeglądu i uzgodnienia zapisu NFR w `context/foundation/prd.md` oraz outcome F-01 w `context/foundation/roadmap.md` przez właściciela, poza tym planem. Faza 2 poniżej dokumentuje ten stan jako świadomy, zamiast przełączać na DNS-only.

Większość pracy to kroki ręczne właściciela (Cloudflare, `.env.prod`, VPS). Kod ogranicza się do `scripts/deploy.sh` i `scripts/lib/production-deploy.sh`.

## Current State Analysis

- **Ruch idzie przez Cloudflare — świadomie.** Sprawdzone 2026-09-22: `dig +short genealog.kostecki.dev` → `188.114.97.11`, `188.114.96.11`; `curl -vI https://genealog.kostecki.dev/up` → `server: cloudflare`, `cf-ray`, certyfikat Google Trust Services (WE1). Wstępnie planowano przełączenie na DNS-only (patrz commit `kostecki-dev-infra@765914d`), ale właściciel potwierdził 2026-09-22, że Cloudflare Proxied zostaje ze względu na ochronę DDoS/bot; dokumentacja infra przywrócona i uzupełniona o to uzasadnienie (`kostecki-dev-infra@0a2091f`, rewert `765914d`): `kostecki-dev-infra/docs/root-keeper.md` (sekcje „DNS” i „Cloudflare (Proxied)”) oraz `kostecki-dev-infra/docs/SETUP.md:44`.
- **Backupy nieszyfrowane.** `config/backup.php:78` — `encryption` = `none`, gdy `BACKUP_ARCHIVE_PASSWORD` jest puste; `.env.prod.example` dostarcza je puste. Archiwa z nocnych przebiegów od pierwszego deployu leżą w `/storage/root-keeper-backups` jawnym zipem (zawierają tylko dane testowe — brak kodu domenowego).
- **Alarmy nie dotrą.** `config/backup.php:88-93` — `BackupHasFailed`, `UnhealthyBackupWasFound`, `CleanupHasFailed` idą wyłącznie mailem; `MAIL_HOST` w `.env.prod.example` jest pusty. `backup.notifications.mail.to` spada na `MAIL_FROM_ADDRESS` (`noreply@…`), gdy `BACKUP_NOTIFICATION_MAIL` jest puste.
- **Migracja bez kopii.** `scripts/deploy.sh:30-36`: `up -d` (nowy kod startuje) → `migrate --force`; brak `backup:run` przed.
- **Brak próby odtworzenia.** Nikt nie odtworzył archiwum; nie ma zapisanej procedury. Kodu wgrywania skanów jeszcze nie ma (S-02).
- **Harmonogram i źródła backupu są pokryte testem** (`tests/Feature/BackupScheduleTest.php`) i nie zmieniają się w tym planie.

## Desired End State

- Cloudflare pozostaje Proxied przed `genealog.kostecki.dev` (SSL/TLS mode Full (strict), origin TLS przez Traefik/Let's Encrypt) — świadomie, dla ochrony DDoS/bot; dokumentacja infra opisuje to jako decyzję z uzasadnieniem, nie jako pozostałość do naprawienia. NFR PRD „brak podmiotów trzecich” pozostaje w kolizji z tą decyzją do rozstrzygnięcia przez właściciela osobno.
- W działającej aplikacji `backup.backup.encryption` = `default` (AES-256), najnowsze archiwum jest zaszyfrowane, a hasło ma kopię poza VPS.
- Mail testowy wysłany przez skonfigurowany SMTP na adres `backup.notifications.mail.to` dociera.
- `scripts/deploy.sh` odmawia deployu, gdy w `.env.prod` brakuje hasła archiwów lub konfiguracji maila albo włączono offsite; przy działającym kontenerze robi `backup:run --only-db` przed `up -d` i przerywa deploy, gdy backup się nie powiedzie.
- Raz przeprowadzono odtworzenie: plik testowy z archiwum ma tę samą sumę SHA-256 co oryginał, a dump bazy wczytał się do tymczasowego Postgresa 18 z tymi samymi tabelami; plik testowy przetrwał `up -d --build`. Procedura jest spisana w `kostecki-dev-infra/docs/root-keeper.md` („Restore”).

### Key Discoveries:

- `backup:run` w spatie/laravel-backup 10.3.3 zwraca `FAILURE` przy błędzie (`vendor/spatie/laravel-backup/src/Commands/BackupCommand.php:125`), więc `set -euo pipefail` w `deploy.sh` wystarczy, by przerwać deploy.
- `--only-db` jest już podane jako przykład w `scripts/artisan.sh:18`.
- `.env.prod` jest montowany jako pojedynczy plik (`docker-compose.prod.yml:22`, `:ro`), a `deploy.sh:40` robi `config:cache` — zmiany w `.env.prod` nie działają, dopóki kontener nie widzi nowego pliku i konfiguracja nie jest ponownie zcache'owana.
- Archiwum ma `relative_path` = `storage/app` (`config/backup.php:44`), więc skany są w archiwum pod `private/…`, a dump w `db-dumps/`.
- `Dockerfile.prod` pinuje `postgresql-client-18` do serwera `postgres:18-alpine` — odtwarzać tylko do Postgresa 18.

## What We're NOT Doing

- Wymiarowanie backupów skanów (limity 5000 MB, retencja, ewentualny Backrest/restic) — roadmapa odkłada to do zmierzenia 20–30 prawdziwych skanów (Unknown F-01, Block: no).
- Testy Pest na `config/backup.php` i blokada w samej aplikacji przy braku hasła — odrzucone na rzecz preflightu w `deploy.sh`.
- Heartbeat / monitoring „scheduler nie działa” — kanałem alarmów zostaje sam mail.
- Kopia offsite (`BACKUP_OFFSITE_ENABLED` zostaje `false`; preflight to wymusza).
- Mechanizm rollbacku w `deploy.sh` (`DEPLOY_REF`) — rollback zostaje `git revert` na `main` + deploy (infrastructure.md, Operational Story).
- Przełączenie na DNS-only — odrzucone decyzją właściciela 2026-09-22: Cloudflare Proxied zostaje ze względu na ochronę DDoS/bot (patrz Faza 2).
- Powtórka próby odtworzenia z prawdziwym skanem wgranym przez UI — należy do S-02 jako krok ręczny, nie do tego planu.

## Implementation Approach

Najpierw kod (faza 1), bo musi wejść na `main`, zanim pierwszy deploy chroniony preflightem i backupem przed migracją będzie możliwy. Potem kroki ręczne w kolejności ryzyka: DNS-only (faza 2) — podmiot trzeci widzi ruch już teraz i krok nie zależy od kodu; sekrety i pierwszy chroniony deploy (faza 3); na końcu próba odtworzenia na zaszyfrowanym archiwum i spisanie procedury (faza 4). Zmiany w `kostecki-dev-infra` to osobne commity w tamtym repo; ich SHA zapisujemy w notatce fazy, nie w `## Progress`.

## Critical Implementation Details

- **Samoaktualizacja skryptu.** `deploy.sh` robi `git pull` w trakcie własnego działania, a bash czyta skrypt przyrostowo. Przy pierwszym deployu z tą zmianą najpierw ręcznie `git pull --ff-only origin main` na VPS, potem `./scripts/deploy.sh` — inaczej pierwszy przebieg wykona starą wersję bez strażników (albo pomiesza wersje).
- **Kolejność backupu.** Backup przed migracją ma iść w *działającym, starym* kontenerze, przed `$COMPOSE up -d` — po `up -d` nowy kod już działa na starym schemacie. Stary kontener ma zcache'owaną starą konfigurację, więc pierwszy taki backup po dodaniu hasła może wyjść jeszcze nieszyfrowany; to akceptowalne (dane testowe), a faza 3 sprawdza szyfrowanie na archiwum zrobionym po deployu.
- **Montaż pojedynczego pliku.** Edytor zapisujący `.env.prod` nowym plikiem (nowy i-węzeł) sprawia, że kontener widzi starą treść aż do odtworzenia kontenera. Po edycji sekretów weryfikować przez `./scripts/artisan.sh config:show …`, a gdy wartość jest stara — `docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --force-recreate app` i ponownie `config:cache`.
- **Odszyfrowanie archiwum.** `encryption=default` to ZIP AES-256; `unzip` go nie rozpakuje — potrzebny `7z` (np. `apk add 7zip` w tymczasowym kontenerze `postgres:18-alpine`).

## Phase 1: Strażnicy deployu

### Overview

`deploy.sh` przestaje przepuszczać deploy z nieszyfrowanymi backupami, niemym alarmem lub włączonym offsite, i robi backup bazy przed uruchomieniem nowego kodu i migracją.

### Changes Required:

#### 1. Preflight `.env.prod`

**File**: `scripts/lib/production-deploy.sh`

**Intent**: Nowa funkcja sprawdzająca plik środowiska przed jakąkolwiek zmianą na serwerze, żeby deploy nie mógł przejść z konfiguracją łamiącą NFR.

**Contract**: `production_assert_env_ready <env-file>` — kończy `exit 1` z komunikatem wskazującym nazwę klucza (nigdy wartość), gdy: `BACKUP_ARCHIVE_PASSWORD`, `MAIL_HOST` lub `BACKUP_NOTIFICATION_MAIL` jest puste (brak klucza, pusta wartość, `""`, `null`), albo `BACKUP_OFFSITE_ENABLED` jest prawdą (`true`/`1`/`yes`/`on`, bez rozróżniania wielkości liter). Czyta ostatnie wystąpienie `KEY=` w pliku, zdejmuje otaczające cudzysłowy. Nie `source`'uje pliku.

#### 2. Backup bazy przed migracją

**File**: `scripts/lib/production-deploy.sh`

**Intent**: Funkcja robiąca backup samej bazy w działającym kontenerze, żeby migracja nigdy nie ruszała bazy bez świeżej kopii.

**Contract**: `production_backup_before_migrate "<compose-cmd>"` — gdy usługa `app` działa (`<compose> ps --status running --quiet app` niepuste), uruchamia `<compose> exec -T app php artisan backup:run --only-db`; błąd propaguje się jako niezerowy exit (deploy przerwany, stary kod działa dalej). Gdy kontener nie działa (pierwszy deploy), wypisuje, że backup jest pomijany, i kontynuuje.

#### 3. Wpięcie w deploy

**File**: `scripts/deploy.sh`

**Intent**: Wywołać preflight zaraz po sprawdzeniu istnienia `.env.prod` (przed `production_pull_latest`), a backup przed migracją po `build app` i przed `up -d`.

**Contract**: Kolejność kroków: sprawdzenie `.env.prod` → `production_assert_env_ready .env.prod` → pull → build → katalog backupów → `production_backup_before_migrate "$COMPOSE"` → `up -d` → uprawnienia → `migrate --force` → cache. Komunikat błędu `.env.prod`-missing (linia 14) wymienia też `BACKUP_ARCHIVE_PASSWORD`.

#### 4. Szablon środowiska

**File**: `.env.prod.example`

**Intent**: Szablon mówi, które klucze są wymagane przez preflight i że hasło archiwów musi mieć kopię poza VPS.

**Contract**: Komentarze nad `MAIL_*` i `BACKUP_*`: wymagane przez `deploy.sh` (`MAIL_HOST`, `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_NOTIFICATION_MAIL`), `BACKUP_OFFSITE_ENABLED` musi zostać `false`, hasło przechowywać także poza VPS. Wartości domyślne bez zmian.

### Success Criteria:

#### Automated Verification:

- Składnia skryptów poprawna: `bash -n scripts/deploy.sh scripts/lib/production-deploy.sh`
- Preflight odrzuca plik z pustym `BACKUP_ARCHIVE_PASSWORD`, pustym `MAIL_HOST`, pustym `BACKUP_NOTIFICATION_MAIL` oraz z `BACKUP_OFFSITE_ENABLED=true` (każdy przypadek osobno, exit 1, komunikat z nazwą klucza, bez wartości) — sprawdzone przez `source scripts/lib/production-deploy.sh` i wywołanie funkcji na plikach tymczasowych w katalogu scratch
- Preflight przepuszcza kompletny plik (wartości w cudzysłowach i bez) z `BACKUP_OFFSITE_ENABLED=false` (exit 0)
- `production_backup_before_migrate` z atrapą komendy compose, dla której `ps` zwraca pusty wynik, pomija backup z komunikatem i kończy exit 0; z atrapą, dla której `exec` kończy się błędem, zwraca niezerowy exit
- Istniejące testy backupu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/BackupScheduleTest.php`

#### Manual Verification:

- Przegląd diffu `scripts/deploy.sh`: backup przed migracją stoi przed `up -d`, preflight przed `production_pull_latest`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Cloudflare Proxied dla genealog.kostecki.dev — świadoma decyzja, nie DNS-only

### Overview

**Re-planned 2026-09-22** (pierwotnie: przełączenie na DNS-only; odrzucone przez właściciela — patrz Overview planu i `## Progress` niżej). Cloudflare pozostaje Proxied przed `genealog.kostecki.dev` dla ochrony DDoS/bot. Ta faza już nie zmienia infrastruktury — potwierdza istniejący, zamierzony stan i dokumentuje uzasadnienie, żeby odtworzenie serwera lub przyszła zmiana nie cofnęły tej decyzji bez kontekstu.

### Changes Required:

#### 1. Dokumentacja infra

**File**: `kostecki-dev-infra/docs/root-keeper.md`, `kostecki-dev-infra/docs/SETUP.md`

**Intent**: Dokumentacja potwierdza Proxied jako celową decyzję (DDoS/bot), nie jako krok do naprawienia.

**Contract**: `root-keeper.md` sekcje „DNS” i „Cloudflare (Proxied)” zawierają uzasadnienie (ochrona DDoS/bot) i notatkę o kolizji z NFR PRD „brak podmiotów trzecich”, z komendą weryfikacji (`curl -svI`, oczekiwane `cf-ray` i `server: cloudflare`). `SETUP.md` wiersz checklisty `genealog.kostecki.dev` (linia 44) mówi „Cloudflare Proxied”. **Zrobione**: `kostecki-dev-infra@0a2091f` (rewert wcześniejszego, odrzuconego commitu `765914d` na DNS-only).

### Success Criteria:

#### Automated Verification:

- `curl -svI https://genealog.kostecki.dev/up` zwraca `HTTP/2 200` z nagłówkami `cf-ray` i `server: cloudflare` (potwierdza, że ruch faktycznie idzie przez Cloudflare, zgodnie z decyzją)

#### Manual Verification:

- Rekord `genealog` w panelu Cloudflare ma status „Proxied” (pomarańczowa chmurka), SSL/TLS mode „Full (strict)”
- Dokumentacja w `kostecki-dev-infra` (`root-keeper.md`, `SETUP.md`) potwierdzona i scommitowana z uzasadnieniem DDoS/bot
- NFR PRD „brak podmiotów trzecich” i outcome F-01 w roadmapie oznaczone przez właściciela do przeglądu poza tym planem (nie blokuje dalszych faz)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Sekrety, alarmy i pierwszy chroniony deploy

### Overview

Produkcja ma hasło archiwów (z kopią poza VPS) i działający SMTP; kod z fazy 1 jest wdrożony i jego strażnicy zadziałali; stare nieszyfrowane archiwa znikają.

### Changes Required:

#### 1. `.env.prod` na VPS (ręcznie, właściciel)

**File**: `/srv/apps/root-keeper/.env.prod`

**Intent**: Włączyć szyfrowanie archiwów i dostarczanie alarmów.

**Contract**: `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD` — dane istniejącej skrzynki SMTP dla kostecki.dev; `BACKUP_NOTIFICATION_MAIL` — adres, który właściciel faktycznie czyta; `BACKUP_ARCHIVE_PASSWORD` — długie losowe hasło, zapisane także poza VPS (menedżer haseł / nośnik niezależny od tego serwera); `BACKUP_OFFSITE_ENABLED=false`.

#### 2. Wdrożenie kodu z fazy 1 (ręcznie, właściciel)

**File**: `main` na GitHubie, `/srv/apps/root-keeper` na VPS

**Intent**: Uruchomić deploy już z nowymi strażnikami.

**Contract**: Merge `develop` → `main`; na VPS `git pull --ff-only origin main`, potem `./scripts/deploy.sh` (patrz Critical Implementation Details — samoaktualizacja skryptu).

#### 3. Usunięcie archiwów sprzed szyfrowania (ręcznie, właściciel)

**File**: `/storage/root-keeper-backups/`

**Intent**: Nie zostawiać nieszyfrowanych kopii, gdy istnieje już zweryfikowane zaszyfrowane archiwum.

**Contract**: Usunąć tylko archiwa, których `7z l -slt` nie pokazuje jako AES; dopiero po potwierdzeniu, że najnowsze archiwum jest zaszyfrowane i się rozpakowuje.

### Success Criteria:

#### Automated Verification:

- `./scripts/deploy.sh` na VPS przechodzi preflight i wypisuje wykonanie backupu przed migracją (kontener działał)
- `./scripts/artisan.sh config:show backup.backup.encryption` zwraca `default`
- `./scripts/artisan.sh config:show backup.notifications.mail.to` zwraca adres właściciela (nie `noreply@…`)
- `./scripts/artisan.sh backup:run` kończy się sukcesem, a `./scripts/artisan.sh backup:list` pokazuje nowe archiwum
- `7z l -slt` na najnowszym archiwum pokazuje metodę `AES-256`, a `7z t -p<hasło z kopii poza VPS>` przechodzi

#### Manual Verification:

- Kopia `BACKUP_ARCHIVE_PASSWORD` istnieje poza VPS i jest identyczna z tą w `.env.prod`
- Mail testowy wysłany z kontenera (`./scripts/artisan.sh tinker --execute 'Mail::raw("root-keeper: test alarmu backupu", fn ($m) => $m->to(config("backup.notifications.mail.to"))->subject("root-keeper test"));'`) dotarł do skrzynki właściciela (nie do spamu)
- Deploy z celowo pustym `BACKUP_ARCHIVE_PASSWORD` w kopii `.env.prod` jest odrzucony przez preflight (test na kopii pliku lub przez chwilową zmianę i natychmiastowe przywrócenie)
- Archiwa sprzed szyfrowania usunięte z `/storage/root-keeper-backups/`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Próba odtworzenia i runbook

### Overview

Jedno przećwiczone odtworzenie zaszyfrowanego archiwum z plikiem w roli skanu oraz sprawdzenie, że plik przetrwa przebudowę; procedura spisana tak, żeby dało się ją powtórzyć pod presją.

### Changes Required:

#### 1. Próba odtworzenia (ręcznie, właściciel, na VPS)

**File**: `/srv/apps/root-keeper/storage/app/private/restore-drill/`, tymczasowy kontener `postgres:18-alpine`

**Intent**: Udowodnić, że archiwum da się odszyfrować hasłem spoza VPS, że plik skanu wraca bit w bit, a dump bazy wczytuje się do Postgresa 18.

**Contract**: (1) Umieścić testowy obraz w `storage/app/private/restore-drill/`, zapisać jego `sha256sum`. (2) `./scripts/artisan.sh backup:run`. (3) W tymczasowym katalogu i kontenerze `postgres:18-alpine` (bez sieci `proxy`, bez wolumenu `keeper-pgsql`) rozpakować archiwum przez `7z` hasłem z kopii poza VPS, wczytać dump z `db-dumps/` do świeżej bazy. (4) Porównać `sha256sum` pliku z `private/restore-drill/` z oryginałem; porównać listę tabel i `count(*)` z `users` i `migrations` z produkcją. (5) Usunąć kontener tymczasowy i rozpakowane pliki. Nie dotykać produkcyjnej bazy ani wolumenu.

#### 2. Próba przebudowy (ręcznie, właściciel)

**File**: `/srv/apps/root-keeper`

**Intent**: Potwierdzić, że pliki w `storage/app/private` przetrwają przebudowę obrazu (bind mount `./storage`).

**Contract**: `docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build`, potem ponowne `sha256sum` pliku testowego wewnątrz kontenera. Po próbie usunąć `storage/app/private/restore-drill/`.

#### 3. Runbook „Restore”

**File**: `kostecki-dev-infra/docs/root-keeper.md`

**Intent**: Spisać faktycznie wykonane kroki (nie generyczny tutorial), żeby odtworzenie w ramach RPO 24 h nie zależało od pamięci.

**Contract**: Nowa sekcja „Restore” po „Backups”: gdzie leżą archiwa, skąd wziąć hasło (kopia poza VPS), odszyfrowanie `7z` (AES — nie `unzip`), wczytanie dumpu wyłącznie do Postgresa 18, przywrócenie `private/` do `storage/app/private`, pełne odtworzenie produkcji (zatrzymanie `app`, odtworzenie bazy, pliki, `deploy.sh`), próba kontrolna; data wykonanej próby. Sekcja „Backups” uzupełniona o szyfrowanie, preflight i backup przed migracją. Osobny commit w `kostecki-dev-infra`; SHA zanotować w tej fazie.

### Success Criteria:

#### Automated Verification:

- `sha256sum` pliku odtworzonego z archiwum równa się sumie oryginału
- Dump wczytuje się do `postgres:18-alpine` bez błędów (`psql -v ON_ERROR_STOP=1`)
- `sha256sum` pliku testowego w kontenerze po `up -d --build` równa się sumie oryginału

#### Manual Verification:

- Lista tabel i liczności `users` / `migrations` w odtworzonej bazie zgadzają się z produkcją
- Kontener tymczasowy, rozpakowane pliki i `storage/app/private/restore-drill/` usunięte; `curl -I https://genealog.kostecki.dev/up` zwraca 200
- Sekcja „Restore” w `kostecki-dev-infra/docs/root-keeper.md` opisuje kroki faktycznie wykonane w próbie, z datą, i jest scommitowana

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów Pest (decyzja: strażnikiem jest preflight w `deploy.sh`, nie test konfiguracji). Funkcje bashowe weryfikowane w fazie 1 wywołaniami na plikach tymczasowych i atrapach komendy compose.

### Integration Tests:

- Regresja: `tests/Feature/BackupScheduleTest.php` (harmonogram i źródła backupu bez zmian).

### Manual Testing Steps:

1. Faza 2: `curl -vI` potwierdza, że ruch nadal idzie przez Cloudflare (`cf-ray`, `server: cloudflare`); panel Cloudflare pokazuje Proxied + Full (strict).
2. Faza 3: deploy przechodzi preflight i robi backup przed migracją; `config:show` pokazuje `encryption=default`; mail testowy dociera; archiwum AES.
3. Faza 4: odtworzenie do tymczasowego Postgresa 18 i porównanie sum SHA-256; plik przetrwa `up -d --build`.

## Performance Considerations

Backup `--only-db` przed każdą migracją wydłuża deploy o czas zrzutu bazy (dziś sekundy; baza jest mała). Archiwa `--only-db` wliczają się do retencji 7 dni i limitu 5000 MB — pomijalne wobec archiwów ze skanami, które wymiaruje osobna decyzja po S-02.

## Migration Notes

Brak migracji schematu. Jedyna zmiana danych to usunięcie archiwów sprzed szyfrowania (faza 3), wyłącznie z danymi testowymi, po zweryfikowaniu zaszyfrowanego archiwum.

## References

- Roadmapa: `context/foundation/roadmap.md` (F-01)
- Badanie infrastruktury: `context/foundation/infrastructure.md` (Risk Register, Getting Started 2–6, Operational Story)
- PRD: `context/foundation/prd.md` (Guardrails, Non-Functional Requirements)
- Deploy: `scripts/deploy.sh:30-36`, `scripts/lib/production-deploy.sh`, `scripts/artisan.sh:18`
- Backup: `config/backup.php:78`, `config/backup.php:88-93`, `bootstrap/app.php:29-49`, `tests/Feature/BackupScheduleTest.php`
- Infra: `kostecki-dev-infra/docs/root-keeper.md`, `kostecki-dev-infra/docs/SETUP.md:44`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Strażnicy deployu

#### Automated

- [x] 1.1 Składnia skryptów poprawna (bash -n) — e775c58
- [x] 1.2 Preflight odrzuca każdy brakujący sekret i włączony offsite — e775c58
- [x] 1.3 Preflight przepuszcza kompletny plik — e775c58
- [x] 1.4 Backup przed migracją pomija brak kontenera i propaguje błąd — e775c58
- [x] 1.5 Istniejące testy backupu przechodzą — e775c58

#### Manual

- [x] 1.6 Przegląd kolejności kroków w deploy.sh — e775c58

### Phase 2: Cloudflare Proxied dla genealog.kostecki.dev — świadoma decyzja, nie DNS-only

#### Automated

- [x] 2.7 curl potwierdza HTTP/2 200 z cf-ray i server: cloudflare — 5eb60bb

#### Manual

- [x] 2.8 Rekord genealog ma status Proxied, SSL/TLS mode Full (strict) — 5eb60bb
- [x] 2.9 Dokumentacja infra potwierdzona i scommitowana z uzasadnieniem DDoS/bot — 5eb60bb
- [x] 2.10 NFR PRD i outcome F-01 w roadmapie oznaczone do przeglądu przez właściciela — 5eb60bb

### Phase 3: Sekrety, alarmy i pierwszy chroniony deploy

#### Automated

- [x] 3.1 Deploy przechodzi preflight i robi backup przed migracją — 3c8bd4b
- [x] 3.2 config:show backup.backup.encryption zwraca default — 3c8bd4b
- [x] 3.3 config:show backup.notifications.mail.to zwraca adres właściciela — 3c8bd4b
- [x] 3.4 backup:run kończy się sukcesem i archiwum jest na liście — 3c8bd4b
- [x] 3.5 Najnowsze archiwum jest AES-256 i odszyfrowuje się hasłem spoza VPS — 3c8bd4b

#### Manual

- [x] 3.6 Kopia hasła archiwów istnieje poza VPS — 3c8bd4b
- [x] 3.7 Mail testowy dotarł do skrzynki właściciela — 3c8bd4b
- [x] 3.8 Preflight odrzuca deploy z pustym hasłem archiwów — 3c8bd4b
- [x] 3.9 Archiwa sprzed szyfrowania usunięte — 3c8bd4b

### Phase 4: Próba odtworzenia i runbook

#### Automated

- [x] 4.1 Suma SHA-256 odtworzonego pliku równa oryginałowi
- [x] 4.2 Dump wczytuje się do Postgresa 18 bez błędów
- [x] 4.3 Plik testowy przetrwał up -d --build

#### Manual

- [x] 4.4 Tabele i liczności w odtworzonej bazie zgodne z produkcją
- [x] 4.5 Artefakty próby usunięte, aplikacja odpowiada 200
- [x] 4.6 Sekcja Restore w dokumentacji infra spisana i scommitowana
