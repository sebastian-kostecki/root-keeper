---
project: root-keeper
researched_at: 2026-09-18
recommended_platform: Self-hosted on the kostecki.dev VPS via kostecki-dev-infra (Docker Compose + Traefik)
runner_up: Dedicated new VPS, same kostecki-dev-infra pattern (isolated host)
context_type: mvp
tech_stack:
  language: php
  framework: laravel
  runtime: docker (postgres 18-alpine)
---

## Recommendation

**Deploy root-keeper as a fourth application on the existing kostecki.dev VPS, joined to `kostecki-dev-infra`'s Traefik via the same pattern already used by `household-treasurer`.**

`tech-stack.md` already made deployment a hard constraint, not an open choice: self-host on a VPS behind Traefik, because genealogy data and scans must never reach a third party. That rules out every cloud platform in this skill's default research pool (Cloudflare, Vercel, Netlify, Fly.io, Railway, Render) before any scoring — none of them are "bring your own box." Given self-hosting is mandatory, the real decision is *which* self-hosting path, and `household-treasurer` is a near-exact sibling: Laravel + Inertia + PostgreSQL, single small-scale user, already deployed via `kostecki-dev-infra`'s documented, scripted, repeatable pattern (`docker-compose.prod.yml`, `scripts/deploy.sh`, `scripts/artisan.sh`). Root-keeper's own `compose.yaml` already uses `postgres:18-alpine` for local dev (Sail), matching that pattern exactly — there is no adapter to write, only a new instance of an existing template.

The currently live apps on that VPS are **landing**, **household-treasurer**, and **vaultwarden** (`wallet-master` was torn down — see `remove-wallet-master.md`). That's lighter shared load than a first read of the infra repo suggests: one static nginx site, one Laravel+Postgres app of the same shape as root-keeper, and one lightweight Rust binary (Vaultwarden). Adding root-keeper as a fourth Postgres-backed Laravel app is the same class of load already proven to run there.

## Platform Comparison

Cloud-hosted platforms are hard-filtered out by `tech-stack.md`'s self-host requirement, so the comparison is between self-hosting approaches, scored against the five agent-friendly criteria from `references/agent-friendly-criteria.md`:

| Approach | CLI-first | Managed/Serverless | Agent-readable docs | Stable deploy API | MCP/Integration | Total |
|---|---|---|---|---|---|---|
| Shared VPS via kostecki-dev-infra (Recommended) | Pass | Fail (self-managed by design — inherent to the self-host constraint) | Pass | Pass | Fail (SSH + CLI only, acceptable per criteria — MCP is a bonus, not required) | 3 Pass / 2 Fail |
| Dedicated new VPS, same pattern | Pass | Fail | Pass | Pass | Fail | 3 Pass / 2 Fail (same score, worse cost) |
| From-scratch Docker+Traefik (no infra-repo reuse) | Partial (scripts don't exist yet, must be written) | Fail | Partial (generic Docker/Traefik docs, not tailored to this VPS's quirks) | Partial (untested for this project) | Fail | 1 Pass / 2 Partial / 2 Fail |

Managed/serverless necessarily fails for every row — self-hosting on a VPS is the opposite of managed by definition, and that trade-off was already accepted in `tech-stack.md` in exchange for full data control.

### 1. Shared VPS via kostecki-dev-infra (Recommended)

Zero new infrastructure cost, zero new tooling to learn, and a working template one repo over (`household-treasurer`) with the identical stack shape (Laravel + Inertia + PostgreSQL). `root-keeper`'s own `compose.yaml` already runs `postgres:18-alpine`, so the production `docker-compose.prod.yml` is a near-direct copy. The gap is that `kostecki-dev-infra` doesn't know about root-keeper yet — `bootstrap-vps.sh`, `.env.example`, `README.md`, and `ARCHITECTURE.md` all need a new entry (see Getting Started).

### 2. Dedicated new VPS, same pattern

Same technical shape, but isolates root-keeper's genealogy data from vaultwarden's password vault and household-treasurer's finances on a fully separate host — no shared Traefik, no shared `docker.sock`, no shared Let's Encrypt account. Costs a second VPS bill for an MVP with `qps: low` and `data_volume: small` (~500 scans in year one per `prd.md`), which is disproportionate to the actual scale, but is the strongest available answer to the isolation question below.

### 3. From-scratch Docker+Traefik (no infra-repo reuse)

Reinvents what `kostecki-dev-infra` already solved: SSL automation, network wiring, deploy scripting. No scale or cost benefit over option 1, only rework and a higher chance of missing something the existing pattern already handles (e.g. the Traefik/Docker version coupling documented in the infra repo's own troubleshooting section). Not seriously considered.

## Anti-Bias Cross-Check: Shared VPS via kostecki-dev-infra

### Devil's Advocate — Weaknesses

1. **No resource limits on any container today.** Neither `household-treasurer`'s nor `vaultwarden`'s compose files set `mem_limit`/`cpus`/`pids_limit`. Adding root-keeper as a fourth unlimited container means a runaway process (e.g. a large scan upload triggering heavy PHP memory use) can still starve the other apps on the same host, and vice versa — this isn't new to root-keeper, but root-keeper inherits it unless mitigated (see Risk Register).
2. **No backup path exists yet for root-keeper's actual asset.** The two backup mechanisms already in `kostecki-dev-infra` (Spatie DB dumps for `household-treasurer`, restic/Backrest for vaultwarden's SQLite) don't cover a new app automatically. Root-keeper's scans (the thing the PRD explicitly guards — "dane i skany nie giną") have no host-side backup destination until one is deliberately wired.
3. **`bootstrap-vps.sh` doesn't know root-keeper exists.** It hardcodes the app list it provisions directories for. Running it as-is today creates no `/srv/apps/root-keeper` or `/storage/root-keeper-backups` — the script needs an edit before first deploy, or the deploy fails on a missing directory.
4. **Shared blast radius across very different data sensitivities.** Traefik mounts `/var/run/docker.sock:ro` and fronts every app from one process; a Traefik-level or container-escape compromise puts root-keeper's genealogy data at the same exposure level as vaultwarden's password vault, despite `tech-stack.md`'s explicit goal of full data control. Self-hosting satisfies "no third party sees the data" but doesn't by itself give root-keeper isolation from the *other* apps on the box.
5. **Shared Let's Encrypt account.** All subdomains issue certificates through one ACME account/email. A misconfigured DNS or repeated failed issuance while onboarding root-keeper's subdomain could, in the worst case, brush against Let's Encrypt's rate limits and affect renewal for the already-live apps.

### Pre-Mortem — How This Could Fail

Six months after root-keeper shipped on the shared VPS, a disaster unfolds. The developer assumed the existing pattern would "just work" the way it did for household-treasurer, so `bootstrap-vps.sh` and the backup scripts were updated for directories but the Spatie backup config was only pointed at the Postgres dump — not at `storage/app/private`, where scans actually live. A disk-full incident on the shared `/storage` mount, triggered by an unrelated Backrest retention change for vaultwarden, cascades: Traefik keeps serving, but writes across all apps start failing silently. The developer notices only when root-keeper stops accepting new scan uploads — no alerting exists — and by then several weeks of uploaded certificate scans are gone with no offsite copy, because the only copy ever lived on the app's own bind-mounted disk. The PRD's own guardrail ("skany nie giną") is violated by exactly the shared-host convenience that made day-one deployment trivial, because no step in the deploy chain forced the "does this app's unique asset have a backup path" question.

### Unknown Unknowns

- The `./storage:/var/www/html/storage` bind mount used by `household-treasurer` (and the pattern root-keeper should copy) already persists scans across deploys and rebuilds — this resolves the "deploy wipes uploads" failure mode by default, *if* root-keeper's `docker-compose.prod.yml` copies this exact mount; it is easy to omit if written from a generic Laravel Docker tutorial instead of from this sibling.
- `root-keeper`'s local `.env` currently sets `DB_CONNECTION=sqlite`, which is stale relative to `compose.yaml` (Postgres) — if that drift reaches a production `.env.prod`, root-keeper would be the only Laravel app on this VPS without a proven backup story, since neither Spatie nor restic here has ever backed up a SQLite file.
- `config/filesystems.php`'s `local` disk already points at `storage_path('app/private')` with `serve => true` — this is exactly the "scans served only through authorized routes" behavior `tech-stack.md` calls for, but it means the scans directory needs its *own* line in whatever backs up root-keeper (Spatie's file-backup feature, not just its DB-dump feature, which is what `household-treasurer` currently uses).
- Root-keeper has no `spatie/laravel-backup` dependency yet (checked `composer.json`) — the backup story isn't "already there and just needs pointing," it needs to be added as part of shipping to production.

## Operational Story

- **Preview deploys**: None in MVP — `kostecki-dev-infra`'s pattern has no branch-preview mechanism; every deploy targets `treasurer.kostecki.dev`-style production URLs directly. Root-keeper should follow the same `git checkout <production-branch>` + `scripts/deploy.sh` flow; a staging subdomain is possible later by cloning the same compose file with a different `Host()` label, but is out of MVP scope.
- **Secrets**: Live in `root-keeper/.env.prod` on the VPS only (gitignored, never committed), mounted read-only into the app container as `.env` — same as `household-treasurer/.env.prod`. `APP_KEY` is generated once via `docker compose ... run --rm app php artisan key:generate`. Only the developer with SSH access to the VPS can read them.
- **Rollback**: `git checkout <previous-commit>` in `/srv/apps/root-keeper`, then re-run `scripts/deploy.sh` (rebuilds the image, re-runs migrations forward only — Laravel migrations here are not auto-reversed on rollback, so a schema-breaking migration needs a manual down-migration or a restore from backup).
- **Approval**: Provisioning the VPS directory, editing `bootstrap-vps.sh`, and the first `docker compose up -d` are manual, human-run steps (matches this project's own CLAUDE.md posture: destructive/irreversible infra actions are human-only). Routine subsequent deploys (`./scripts/deploy-root-keeper.sh` from `kostecki-dev-infra`) can be run by an agent once the first deploy is verified.
- **Logs**: `docker compose --env-file .env.prod -f docker-compose.prod.yml logs -f app` for app logs; `docker logs traefik` for routing/SSL issues; `journalctl` for any host-level systemd timer added for backups. No MCP or structured log API exists in this setup — plain CLI only, consistent with the "start with CLI" default for MVP scale.

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Scans have no backup path; a disk failure or accidental deletion loses genealogy source material with no recovery | Pre-mortem | M | H | Add `spatie/laravel-backup` to root-keeper, configure it to back up **both** the Postgres DB and `storage/app/private` (scans), write dumps to a new `/storage/root-keeper-backups` host path, mirroring but extending the DB-only pattern `household-treasurer` uses |
| `bootstrap-vps.sh` and `.env.example` don't provision root-keeper; first deploy fails on missing directories or silently lands in the wrong place | Devil's advocate | H | L | Before first deploy: add `/srv/apps/root-keeper` and `/storage/root-keeper-backups` to `bootstrap-vps.sh`, add `VPS_ROOT_KEEPER_DIR` to `kostecki-dev-infra/.env.example`, add a row to `README.md`/`ARCHITECTURE.md` |
| No per-container resource limits; a memory/CPU spike in one app degrades or crashes the others sharing the VPS | Devil's advocate | M | M | Set `mem_limit`, `cpus`, and `pids_limit` on root-keeper's `app` and `pgsql` services in `docker-compose.prod.yml` (works without Swarm mode); consider retrofitting the same to `household-treasurer` and `vaultwarden` as a follow-up, not blocking root-keeper's own deploy |
| Shared Traefik/`docker.sock`/Let's Encrypt account gives root-keeper the same blast radius as vaultwarden's password vault, despite different data sensitivity | Devil's advocate | L | H | Accept for MVP (matches the shared-VPS decision); keep root-keeper's own Postgres in its own container/volume (not a shared DB server) and its own `root-keeper-internal` bridge network so a compromised sibling container has no direct network path to root-keeper's DB — re-evaluate a dedicated VPS (runner-up) if genealogy data sensitivity grows |
| Deploy process rebuilds the app container and could wipe scans if the storage bind mount is set up incorrectly | Unknown unknowns | L | H | Copy `household-treasurer`'s exact `./storage:/var/www/html/storage` bind mount in root-keeper's `docker-compose.prod.yml`; verify after first deploy that a test scan survives a `docker compose up -d --build` |
| `.env` (local) says `DB_CONNECTION=sqlite` while `compose.yaml` and the intended production stack use Postgres — drift could reach `.env.prod` | Research finding | L | M | When writing `.env.prod.example`, set `DB_CONNECTION=pgsql` explicitly from the household-treasurer template, don't copy from the stale local `.env` |
| Certificate scans and personal data mixed on the same disk/host as three other apps with no documented per-app disk quota | Unknown unknowns | L | M | Monitor `/storage` free space as part of routine ops (`df -h /storage`); Backrest/restic retention settings for other apps should not be changed without checking free space impact on root-keeper's own backup destination |

## Getting Started

1. **Pick and register the subdomain** (e.g. `drzewo.kostecki.dev` or similar — final name is the user's call): add the DNS A record → VPS IP, Cloudflare Proxied, SSL mode Full (strict).
2. **Register root-keeper in `kostecki-dev-infra`**: add `/srv/apps/root-keeper` and `/storage/root-keeper-backups` to `scripts/bootstrap-vps.sh`, add `VPS_ROOT_KEEPER_DIR=/srv/apps/root-keeper` to `.env.example`, add a row to `README.md` and `ARCHITECTURE.md`, add a `scripts/deploy-root-keeper.sh` that mirrors `scripts/deploy-household-treasurer.sh`.
3. **In the root-keeper repo, add the production Docker files**, copied from `household-treasurer` and adapted: `docker/8.5/Dockerfile.prod` (multi-stage composer + npm + PHP 8.5 runtime), `docker-compose.prod.yml` (`app` + `pgsql` services, `root-keeper-internal` + `proxy` networks, Traefik labels for the chosen subdomain, `./storage:/var/www/html/storage` bind mount, `mem_limit`/`cpus` set on both services), `.env.prod.example` (`DB_CONNECTION=pgsql`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`), `scripts/deploy.sh` and `scripts/artisan.sh` copied from household-treasurer with paths/names adjusted.
4. **Add `spatie/laravel-backup`** (`composer require spatie/laravel-backup`) and configure it to include the Postgres DB *and* the `storage/app/private` scans directory as backup sources, writing to a `BACKUP_HOST_DIR` mounted from `/storage/root-keeper-backups` — this is new relative to `household-treasurer`'s DB-only backup and is root-keeper's most important deploy-time gap to close before real data goes in.
5. **First deploy on the VPS**:
   ```bash
   cd /srv/apps/root-keeper
   cp .env.prod.example .env.prod
   # edit .env.prod: APP_KEY (blank for now), DB_PASSWORD, mail, chosen domain
   docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm app php artisan key:generate
   ./scripts/deploy.sh
   curl -I https://<chosen-subdomain>.kostecki.dev/up   # expect HTTP/2 200
   ```

## Out of Scope

The following were not evaluated in this research:
- Docker image configuration (Dockerfile itself)
- CI/CD pipeline setup
- Production-scale architecture (multi-region, HA, DR) — explicitly inappropriate for a `qps: low`, single-user MVP
- Retrofitting resource limits or backup coverage onto the *existing* apps (`household-treasurer`, `vaultwarden`) — flagged as a worthwhile follow-up, not blocking root-keeper's own deploy
