---
change_id: scans-become-sources
title: Scans become sources
status: implementing
created: 2026-09-29
updated: 2026-09-29
archived_at: null
---

## Notes

<!-- Free-form notes for this change: links, ad-hoc context, decisions that don't belong in research/frame/plan. -->

### Deploy runbook (files move in this change; `deploy.sh` backs up only the DB)

1. Full backup: `./scripts/artisan.sh backup:run` (files and DB), then `./scripts/artisan.sh backup:list`.
2. Record the confirmed-event count before the deploy (SQL below).
3. Run `scripts/deploy.sh` (migrations run as `sail`, so `sources/` directories stay readable for PHP).
4. The same count after the deploy must be equal. `sources` must have no row whose `path` starts with `scans/`, and the migration output must name no missing file.
5. `ls -ld storage/app/private/sources storage/app/private/sources/*` shows `sail`-owned directories. Open an old source's thumbnail and full view, then upload a test source, open it, and delete it.

Count queries (before: `event_scan` / `scan_id`; after: `event_source` / `source_id`):

```sql
-- before deploy
select count(distinct event_id) from event_scan;
-- after deploy
select count(distinct event_id) from event_source;
select count(*) from sources where path like 'scans/%' or thumbnail_path like 'scans/%';
```
