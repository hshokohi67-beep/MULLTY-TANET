# Backup & disaster recovery runbook

Status: new in Phase 15. `backup:run` and this runbook have not yet been exercised against a real
production database — treat the steps below as reviewed, not battle-tested, until the first real
restore drill.

## What's covered

- **Database (MySQL):** `php artisan backup:run` dumps the whole database with `mysqldump
  --single-transaction` (a consistent snapshot without locking tables), gzips it, and uploads it to
  the `backup.disk` filesystem disk (`local` in development; point `BACKUP_DISK` at an S3-compatible
  bucket in production, same as `MEDIA_DISK`). It's scheduled daily at 03:30 Asia/Tehran
  (`routes/console.php`).
- **Retention:** backups older than `BACKUP_KEEP_DAYS` (default 14) are deleted on every run, based
  on the date already encoded in the backup's own filename (`cafe-{env}-{UTC timestamp}.sql.gz`) —
  not the file's storage-reported modified time, which some disks don't preserve reliably.

## What's NOT covered (know this before you need it)

- **Uploaded media** (product photos, branding, ad creatives, marketplace photos) is not part of
  this backup. It relies entirely on the durability of the object-storage provider behind
  `MEDIA_DISK` (e.g. S3 versioning/replication). If that provider has no versioning enabled, a
  destructive bug in the app could delete media with no way back — check the bucket's own settings.
- **Redis** (cache, queues, broadcasting) is not backed up. Losing it loses in-flight jobs and cache
  state, not durable business data — acceptable, but plan a restart procedure that re-primes caches.
- **Point-in-time recovery** isn't available from this command alone: it's one full dump a day, so
  the worst-case data loss (RPO) is just under 24 hours. If that's not good enough for launch,
  enable MySQL binary logging and ship binlogs separately (not built here).
- **The restore path below is untested against production-scale data.** Time it on a realistic copy
  before relying on the RTO estimate.

## Recovery targets (proposed — confirm with the business before launch)

- **RPO (data loss):** up to 24 hours (one daily dump). Tighten with binlog shipping if the business
  needs less.
- **RTO (time to restore):** proposed 1 hour for a single tenant's worth of data on a fresh database;
  unmeasured for the whole platform. Time a real drill and update this number.

## Restore procedure

1. **Identify the backup.** List objects on the backup disk (e.g. `aws s3 ls s3://<bucket>/backups/`
   or, locally, `ls storage/app/private/backups`) and pick the file whose timestamp is just before
   the incident.
2. **Download and decompress:**
   ```bash
   gunzip -c cafe-production-20260925-033000.sql.gz > restore.sql
   ```
3. **Restore into a scratch database first, never directly into production:**
   ```bash
   mysql -h <host> -u <user> -p --show-warnings -e "CREATE DATABASE cafe_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -h <host> -u <user> -p cafe_restore_check < restore.sql
   ```
4. **Sanity-check the scratch database** before going further: row counts on a few large tables
   (`orders`, `payments`, `wallet_transactions`), and that `tenants` has the expected number of rows.
5. **Only once verified**, restore into the real database. Prefer restoring into a fresh database and
   swapping the application's `DB_DATABASE` over a maintenance window, rather than dropping the live
   one first — that keeps a rollback path open if the dump turns out to be bad.
6. **After restoring**, run `php artisan migrate --force` in case migrations landed between the dump
   and the incident, then spot-check the app (login, an order, a payment) before reopening traffic.
7. **Write down** what caused the incident, the dump used, and how long the restore actually took —
   feed it back into the RTO estimate above.

## Regular drills

Run the restore procedure against a scratch database on a schedule (quarterly is a reasonable
starting point) even with no incident, specifically to keep this runbook honest and to catch a
silently-broken backup (wrong credentials, a disk that stopped receiving uploads, etc.) before it's
needed for real.
