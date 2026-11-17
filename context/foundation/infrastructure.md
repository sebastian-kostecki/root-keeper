---
project: root-keeper
researched_at: 2026-09-21
recommended_platform: Self-hosted on the kostecki.dev VPS via kostecki-dev-infra (Docker Compose + Traefik)
runner_up: Dedicated new VPS, same kostecki-dev-infra pattern (isolated host)
context_type: mvp
tech_stack:
  language: php
  framework: laravel
  runtime: docker (postgres 18-alpine)
---

## Recommendation

**Deploy root-keeper as a fourth application on the existing kostecki.dev VPS, joined to `kostecki-dev-infra`'s Traefik, at `genealog.kostecki.dev`. Serve it DNS-only (grey cloud), not through the Cloudflare proxy.**

`tech-stack.md` made deployment a hard constraint, not an open choice: self-host on a VPS behind Traefik, because genealogy data and scans must never reach a third party (`prd.md`, Non-Functional Requirements). That rules out every cloud platform in this skill's default pool (Cloudflare, Vercel, Netlify, Fly.io, Railway, Render) before any scoring. Given self-hosting is mandatory, the real decision is *which* self-hosting path. `household-treasurer` is a near-exact sibling (Laravel + Inertia + PostgreSQL, small scale), already deployed through the same documented, scripted pattern.

**Refresh, 2026-09-21.** The first version of this document (2026-09-18, commit `c3ab0bf`) recommended the shared VPS and listed the gaps to close before first deploy. Most are now closed in both repos (see the Risk Register). This refresh re-runs the cross-check against the platform *as built* and found new issues, the most important being that the documented Cloudflare **Proxied** setting terminates TLS at a third party, which conflicts with the PRD's own data rule. The platform decision is unchanged; the operational recommendations are not.

## Platform Comparison

Cloud-hosted platforms are hard-filtered out by the self-host requirement, so the comparison is between self-hosting approaches, scored against the five agent-friendly criteria from `references/agent-friendly-criteria.md`:

| Approach | CLI-first | Managed/Serverless | Agent-readable docs | Stable deploy API | MCP/Integration | Total |
|---|---|---|---|---|---|---|
| Shared VPS via kostecki-dev-infra (Recommended) | Pass | Fail (self-managed by design, inherent to the self-host constraint) | Pass | Partial (`scripts/deploy.sh` is deterministic but has no rollback and no CI trigger) | Fail (SSH + CLI only; MCP is a bonus, not required) | 2 Pass / 1 Partial / 2 Fail |
| Dedicated new VPS, same pattern | Pass | Fail | Pass | Partial | Fail | 2 Pass / 1 Partial / 2 Fail (same score, worse cost) |
| From-scratch Docker+Traefik (no infra-repo reuse) | Partial | Fail | Partial | Partial | Fail | 1 Pass-equivalent at best; not seriously considered |

The "Stable deploy API" score dropped from Pass to Partial since the first version. The scripts now exist and were read, and they turned out to have no working rollback path (see Devil's Advocate #4). Managed/serverless necessarily fails for every row: self-hosting is the opposite of managed by definition, and that trade-off was accepted in `tech-stack.md` in exchange for full data control.

### Shortlisted Platforms

#### 1. Shared VPS via kostecki-dev-infra (Recommended)

Zero new infrastructure cost and a working, now-implemented template. The stack is built: `docker-compose.prod.yml`, `docker/8.5/Dockerfile.prod`, `scripts/deploy.sh`, `scripts/artisan.sh`, `.env.prod.example`, Spatie backups on a nightly schedule, and the registration in `kostecki-dev-infra` (`bootstrap-vps.sh`, `.env.example`, `README.md`, `docs/root-keeper.md`, `scripts/deploy-root-keeper.sh`). The live apps on the VPS are **landing**, **household-treasurer** and **vaultwarden**.

#### 2. Dedicated new VPS, same pattern

Same technical shape, but isolates root-keeper's genealogy data from vaultwarden's password vault and household-treasurer's finances: no shared Traefik, no shared `docker.sock`, no shared Let's Encrypt account. It costs a second VPS bill for an MVP with `qps: low` and `data_volume: small`, which is disproportionate to the scale. It remains the strongest answer to the shared-blast-radius risk if data sensitivity grows.

#### 3. From-scratch Docker+Traefik (no infra-repo reuse)

Reinvents what `kostecki-dev-infra` already solved (SSL automation, network wiring, deploy scripting) with no scale or cost benefit. Now moot: the pattern has been adopted.

## Anti-Bias Cross-Check: Shared VPS via kostecki-dev-infra

### Devil's Advocate — Weaknesses

1. **Cloudflare Proxied contradicts the PRD's third-party rule.** `kostecki-dev-infra/docs/root-keeper.md` and `docs/SETUP.md` configure `genealog` as an A record with the orange cloud (Proxied). With the proxy on, Cloudflare terminates TLS and sees every scan, every page and every session cookie in plaintext. `prd.md` requires that data and scans not reach "podmioty trzecie ani usługi zewnętrzne, nad którymi właściciel instancji nie ma kontroli". The first version of this document recommended Proxied without noticing.
2. **Spatie retention is likely too small for scans.** `config/backup.php` sets a 5000 MB cap (`delete_oldest_backups_when_using_more_megabytes_than` and the `MaximumStorageInMegabytes` health check) with 7-day retention, but every nightly archive is a full zip of `storage/app`. At the PRD's ~500 scans in year one and an assumed 3–5 MB each (an estimate, not measured), one archive is about 1.5–2.5 GB. Seven of them exceed the cap several times over, so `backup:clean` would quietly cut retention to 1–2 archives and `backup:monitor` would start failing.
3. **Backups are local, unencrypted, and alert by email only.** `BACKUP_ARCHIVE_PASSWORD` is empty, so `encryption` resolves to `none`. `BACKUP_OFFSITE_ENABLED=false`, so the only copies sit under `/storage/root-keeper-backups`. Failure notifications go to `mail` only, and `MAIL_HOST` is blank in `.env.prod.example`, so an unconfigured SMTP means a failed backup alerts nobody. If offsite is later enabled without a password, scans land in a third-party bucket in plaintext.
4. **Deploy is manual, ordered unsafely, and cannot roll back.** The three workflows (`lint`, `static-analysis`, `tests`) contain no deploy step; deploys are SSH + `scripts/deploy.sh` (manual by decision, #12). The script runs `up -d` and then `migrate --force` with no `backup:run` in between, and the PRD has no trash or undo. The rollback in the first version of this document (`git checkout <previous-commit>` then `deploy.sh`) does not work with the scripts as written: `production_assert_branch` aborts unless HEAD is on `main`, and `git pull --ff-only origin main` would pull the bad commit straight back.
5. **`artisan serve` is the production web server.** The container runs PHP's built-in server under supervisord. Concurrency depends on `PHP_CLI_SERVER_WORKERS=4` (set in the mounted `.env`, not in the container environment) actually being honoured, and `post_max_size`/`upload_max_filesize` are 100M, so a few slow scan uploads could occupy every worker. `household-treasurer` uses the same pattern, so this is a "verify", not a defect claim. **Resolved 2026-11-17:** production now runs nginx + PHP-FPM (`docker/8.5/nginx.conf`, `docker/8.5/php-fpm.conf`); nginx buffers uploads, so a slow upload no longer holds a PHP worker, and `PHP_CLI_SERVER_WORKERS` is gone.

### Pre-Mortem — How This Could Fail

Six months after real data went in, root-keeper's nightly Spatie archive had grown past the 5000 MB cap without anyone noticing. `backup:clean` was quietly deleting down to the newest one or two archives, and `backup:monitor` emails went to an SMTP server that was never configured. Meanwhile the owner, following the PRD's "no trash, explicit confirmation" model, deleted a person and their scans by mistake and ran a schema-changing migration in the same week. Two days later the loss was noticed. The only archive on disk already contained the post-damage state, the retained history was one night deep, and nobody had ever rehearsed a restore, so it was unclear whether the unencrypted zip and the Postgres 18 dump would even load into a fresh instance. The rollback attempt failed because `deploy.sh` refuses to run off `main` and pulls the latest commit. The PRD's guardrail, "wprowadzone dane i wgrane skany nie giną", was violated by three small defaults that each looked reasonable: a byte cap copied from a DB-only sibling, an empty mail config, and a rollback story that was written down but never executed.

### Unknown Unknowns

- **Cloudflare Proxied also changes what "self-hosted" means.** Even with encrypted backups and a private VPS, a proxied hostname puts a third party in the request path. Switching to DNS-only is a one-click change, but the Let's Encrypt certificate then has to be issued by Traefik for real (with the proxy on, browsers only ever see Cloudflare's edge certificate), so verify the issuer after the switch.
- **`/storage` is a separate disk mounted into the VPS** (confirmed by the owner 2026-09-21; `kostecki-dev-infra/docs/backups.md` also requires `mountpoint /storage`). That protects the backups from a failure or reinstall of the VPS system disk. It is still attached to the same VPS, so it does not protect against a mistaken `rm`, ransomware or compromise of the host, or loss of the provider account. Treat it as "different disk", not "offsite".
- **`config:cache` freezes environment changes.** `deploy.sh` caches config, and `.env.prod` is mounted read-only as `.env`. Editing `.env.prod` (for example fixing `MAIL_HOST`) has no effect until the config is re-cached or the app is redeployed.
- **Backups already exist as a better tool in the same infra.** Backrest/restic (deployed for Vaultwarden at `backup.kostecki.dev`) does encrypted, deduplicated, incremental backups. That fits a slowly growing pile of scans far better than nightly full zips, but it would need a new plan in `kostecki-dev-infra`, not just a change in this repo.
- **Version pinning matters for restores.** `Dockerfile.prod` pins `postgresql-client-18` to match the `postgres:18-alpine` server. A restore procedure has to use the same major version, or a dump taken today may not load later.

## Operational Story

- **Preview deploys**: None in MVP. `kostecki-dev-infra`'s pattern has no branch-preview mechanism; every deploy goes to `https://genealog.kostecki.dev`. CI (`lint`, `static-analysis`, `tests`) runs on push and pull request, so verification happens before merge, not on a preview URL.
- **Secrets**: Live in `/srv/apps/root-keeper/.env.prod` on the VPS only (gitignored), mounted read-only into the app container as `.env`. `APP_KEY` is generated once with `docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app php artisan key:generate`. Only someone with SSH access to the VPS can read them. Changes need a redeploy (config is cached).
- **Rollback**: With the scripts as they are, roll back by `git revert` on `main` (commit and push), then run `./scripts/deploy.sh` on the VPS. Do not `git checkout <sha>` on the VPS: `deploy.sh` requires branch `main` and fast-forwards to `origin/main`. Migrations only go forward, so a schema-changing release needs a manual down-migration or a restore from backup. Time-to-revert is one image rebuild plus migrations, several minutes, with a short outage while the containers are recreated.
- **Approval**: Human-only: DNS changes, editing `.env.prod`, first deploy, restoring from a backup, anything touching the `keeper-pgsql` volume or `docker compose down -v`, and Traefik/`kostecki-dev-infra` changes. An agent may read logs, run read-only `scripts/artisan.sh` commands, and run routine `scripts/deploy.sh` after a human merge to `main`, but only once a pre-deploy backup step exists and only for releases without destructive migrations.
- **Logs**: `docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f app` for the app (PHP server writes to stdout/stderr); `storage/logs/queue.log` and `storage/logs/scheduler.log` on the host under `/srv/apps/root-keeper/storage/logs/` (the storage directory is bind-mounted); `docker logs traefik` for routing and certificate issues; `./scripts/artisan.sh <command>` for one-off artisan commands. Health check: `curl -I https://genealog.kostecki.dev/up`. No MCP or structured log API exists; plain CLI only.
- **Research repo import**: The research repo (`my-gen-tree`, private) is a git clone at `/srv/research/my-gen-tree` (`RESEARCH_REPO_HOST_DIR`), pulled through the read-only deploy key `~/.ssh/my_gen_tree_deploy` (host alias `github-my-gen-tree` in `/root/.ssh/config`), and mounted read-only into the app container at `/research`. It is outside `storage/`, so `deploy.sh`'s `chown` and the nightly backup never touch it; the repo's own copy is on GitHub, and imported scans are copied into `storage/app`. After a research session, push from the laptop, then: `ssh -t -p10175 root@halina175.mikrus.xyz 'git -C /srv/research/my-gen-tree pull --ff-only && cd /srv/apps/root-keeper && ./scripts/artisan.sh import:research <email> /research'`. `-t` is required: the import asks questions, and without a terminal the final "Zapisać…?" defaults to no, so nothing is written. The import takes its own `backup:run --only-db` before the first write. Running it is human-only (it writes to the owner's tree).

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Cloudflare Proxied terminates TLS at a third party, contradicting the PRD's "no third parties" rule | Devil's advocate | H (it is the documented setting) | H | Set the `genealog` A record to **DNS-only (grey cloud)** and update `kostecki-dev-infra/docs/root-keeper.md` and `docs/SETUP.md`. Verify with `curl -vI https://genealog.kostecki.dev/up` that the certificate issuer is Let's Encrypt |
| Spatie retention silently collapses because full archives exceed the 5000 MB cap | Devil's advocate | H | H | Measure `du -sh storage/app/private` once real scans exist, then set `delete_oldest_backups_when_using_more_megabytes_than` and `MaximumStorageInMegabytes` from the measured archive size ×7. Alternative: keep Spatie for the DB only and put `storage/app/private` on a Backrest/restic plan in `kostecki-dev-infra` |
| Backup failure alerts never arrive (`MAIL_HOST` blank) and archives are unencrypted | Devil's advocate / Unknown unknowns | M | H | Fill `MAIL_*` in `.env.prod`, send a test mail, and set `BACKUP_ARCHIVE_PASSWORD` before real data (the app is already deployed, so do this now). Never set `BACKUP_OFFSITE_ENABLED=true` unless the archive is encrypted and the endpoint is owner-controlled |
| Backups sit on a disk attached to the same VPS: a mistaken delete, ransomware or host compromise reaches them too | Unknown unknowns | L | H | Accepted for MVP; the separate mounted disk covers system-disk failure. If the tree becomes irreplaceable, add a periodic copy to a machine you control (for example a restic repo on a second host or your workstation); this keeps the no-third-party rule |
| No tested restore path; a destructive mistake or bad migration is unrecoverable within the PRD's 24 h window | Pre-mortem | M | H | Before loading real data, run `./scripts/artisan.sh backup:run`, restore the archive into a throwaway Postgres 18 instance, and confirm a scan opens. Write the procedure down and repeat after any backup-config change |
| Rollback instruction does not work (`deploy.sh` requires `main` and pulls latest) | Devil's advocate | M | M | Use `git revert` on `main` + redeploy (Operational Story). Optionally add an explicit `DEPLOY_REF` path to `scripts/deploy.sh` later; not needed for MVP |
| Migrations run without a fresh backup taken just before | Devil's advocate | M | H | Add `$COMPOSE exec -T app php artisan backup:run --only-db` (the flag is already used in `scripts/artisan.sh`'s usage text) before `migrate --force` in `scripts/deploy.sh` |
| `tech-stack.md` says auto-deploy-on-merge, but no deploy workflow exists | Research finding | H | L | Resolved 2026-09-21 (#12): deploys stay manual (`scripts/deploy.sh` on the VPS); `tech-stack.md` amended to `manual-promotion` |
| `artisan serve` under load or slow 100 MB uploads occupies all workers | Devil's advocate | L | M | Resolved 2026-11-17: nginx + PHP-FPM, nginx buffers request bodies |
| Shared Traefik/`docker.sock`/Let's Encrypt account gives root-keeper the same blast radius as vaultwarden's password vault | Devil's advocate (carried) | L | H | Accepted for MVP. Root-keeper's Postgres is its own container/volume on its own `root-keeper-internal` network, and no ports are published. Re-evaluate the dedicated VPS (runner-up) if sensitivity grows |
| `/storage` fills up (shared by four apps' backups) and writes fail across apps | Pre-mortem (carried) | L | M | Monitor `df -h /storage`. Do not change other apps' retention without checking free space |
| Deploy rebuild wipes scans if the storage bind mount is wrong | Unknown unknowns (carried) | L | H | The `./storage:/var/www/html/storage` mount is in place. After the first real deploy, confirm a test scan survives `docker compose up -d --build` |

### Closed since 2026-09-18

Verified in the repositories (not by running anything on the VPS):

- Root-keeper registered in `kostecki-dev-infra` (`bootstrap-vps.sh` creates `/srv/apps/root-keeper` and `/storage/root-keeper-backups`; `.env.example` has `VPS_ROOT_KEEPER_DIR`; docs and `deploy-root-keeper.sh` added).
- `spatie/laravel-backup ^10.3` added, backing up the Postgres DB *and* `storage/app` (which contains `private/`, where scans live), with `backup:run`/`clean`/`monitor` scheduled at 03:45/04:15/04:45 Europe/Warsaw.
- Resource limits set: app `mem_limit 1g`, `cpus 1.0`, `pids_limit 256`; Postgres `512m`, `0.5`, `128`.
- `./storage:/var/www/html/storage` bind mount copied from the sibling app.
- `.env.prod.example` uses `DB_CONNECTION=pgsql` (`.env.example` still says `sqlite`, and the production image build overrides it deliberately).
- Own Postgres container, volume and internal network; `pg_dump` client pinned to version 18.

Confirmed by the owner (2026-09-21): the first deploy has run on the VPS, and `/storage` is a separate disk mounted into it. Not verified: that a test scan survives `docker compose up -d --build`, and that a backup has ever been restored.

## Getting Started

The repo already contains the production stack, so the remaining work is finishing and hardening it. Commands below are taken from the scripts in this repository, not from generic tutorials.

1. **First deploy: done** (confirmed by the owner). Re-check the state after each change with `curl -I https://genealog.kostecki.dev/up` (expect HTTP/2 200). Confirm `.env.prod` has real values for `MAIL_*` and `BACKUP_NOTIFICATION_MAIL`, since the template ships them blank.
2. **Switch the `genealog` DNS record to DNS-only (grey cloud)** in Cloudflare and update the two infra docs that still say Proxied. The owner reports the record was changed on 2026-09-21, but a check the same day still saw Cloudflare (`188.114.96.0/23` and `2a06:98c1::` addresses from 1.1.1.1 and 8.8.8.8, `server: cloudflare` and a Google Trust Services edge certificate), so it is **not yet confirmed**. Done means: `dig +short genealog.kostecki.dev` returns the VPS address, and `curl -vI https://genealog.kostecki.dev/up` shows a Let's Encrypt issuer and no `cf-ray` header. If Traefik has never issued a certificate for this host (the proxy may have hidden that), check `docker logs traefik` for ACME errors.
3. **Set backup secrets and mail before real data goes in**: `BACKUP_ARCHIVE_PASSWORD`, working `MAIL_*`, then redeploy so the cached config picks them up. Send a test mail through the configured SMTP.
4. **Do one restore drill and one rebuild drill**: run `./scripts/artisan.sh backup:run`, restore the archive into a throwaway Postgres 18 instance and open a scan; then confirm a test scan survives `docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build`.
5. **Bound the scan size, then size the backups against it.** Real scan sizes are unknown, and no upload code exists yet (`app/` has only `User` and the settings controllers), so nothing limits a scan except the 100M in `docker/8.5/php.ini`. When FR-013 is built, put an explicit `max:` on the upload validation (for example 10 MB). That gives a hard worst case: 500 scans × the limit is the largest possible archive, and the two 5000 MB thresholds can be set from it. After the first 20–30 real scans, measure with `du -sh /srv/apps/root-keeper/storage/app/private`, extrapolate to ~500, and only then decide between raising the Spatie limits and moving scans onto a Backrest/restic plan in `kostecki-dev-infra`.
6. **Add a pre-migration backup**: add `backup:run --only-db` before `migrate --force` in `scripts/deploy.sh`. Deploy automation is decided: deploys stay manual for the MVP (#12, `tech-stack.md` amended).

## Out of Scope

The following were not evaluated in this research:
- Docker image configuration (the Dockerfile itself)
- CI/CD pipeline setup
- Production-scale architecture (multi-region, HA, DR); inappropriate for a `qps: low`, single-user MVP
- Retrofitting resource limits, backup coverage or the DNS-only change onto the *existing* apps (`household-treasurer`, `vaultwarden`); flagged as a worthwhile follow-up
- Anything running on the VPS itself: no SSH access was used, so the live state of Traefik, `/storage` and DNS was read from repository docs, plus the owner's statement that the first deploy has run and `/storage` is a mounted disk
