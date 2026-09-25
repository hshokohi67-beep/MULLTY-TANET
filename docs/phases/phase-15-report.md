# Phase 15: Hardening (Report)

Plan: `phase-15-plan.md`. Status: done, with one caveat on local verification (§4 below).

## 1. What changed

### Security review

Full review written up in the conversation and folded straight into fixes (no separate findings
doc was needed — every finding was small enough to fix immediately):

- **`UploadTenantLogo`** stored the raw uploaded bytes directly, unlike every other public photo
  upload in the app. It now goes through `ImageProcessor` like `UploadTenantCover` does: re-encoded
  to WebP (≤ 512 px), EXIF stripped, pixel-capped. The stored filename changed shape accordingly
  (`{ulid}-logo.webp` instead of `{ulid}.{original extension}`); the existing test was updated and
  a new one added covering replace-deletes-old-file.
- **`PaymentLog::SECRET_KEYS`** redacted `card_hash` but not `card_pan` — added, so a gateway
  response that happens to include an unmasked PAN field is never written to the append-only
  `PaymentTransaction` log unredacted.
- **Tenant isolation coverage gaps**: `TenantIsolationTest` covers every tenant-scoped route added
  through Phase 14b except a handful missed along the way. Added: the branding logo upload route
  and `GET /permissions` to the standard forbidden-cross-tenant check; a dedicated test for
  `GET /kds/me` and for a *fresh, unpaired* pairing code (the previous KDS setup fixture always
  paired its device immediately, so no test exercised an unconsumed code against the wrong
  tenant); and a new test asserting every Customers + Loyalty customer-club endpoint (addresses,
  orders, profile, club, wallet/points ledgers, redeem, referral, wallet-payment) rejects a
  customer token minted for a different tenant.
- Everything else checked — platform route authz, IDOR patterns, file-upload validation elsewhere,
  rate limiting on every guest/public route, mass-assignment on later-phase FormRequests — had no
  findings; see the checklist in `phase-15-plan.md` §1 for exactly what was checked.

### Dependency audit

- `composer audit` and `npm audit --workspaces --audit-level=high` added as CI steps
  (`.github/workflows/ci.yml`). Both run clean today (`npm audit`: 0 vulnerabilities, confirmed in
  this session; `composer audit` could not be run locally — see §4).

### CSP

- **API**: unchanged, already strict (`default-src 'none'`).
- **Web**: had no CSP at all. Added one to `apps/web/next.config.ts`:
  `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';
  img-src 'self' data: https:; font-src 'self'; connect-src 'self'; object-src 'none';
  base-uri 'none'; form-action 'self'; frame-ancestors 'none'`.
  - `script-src`/`style-src` need `'unsafe-inline'` for the theme-flash-prevention snippet in
    `layout.tsx`, the per-page JSON-LD blocks (storefront + product pages), and Tailwind/Next's own
    inline styles — there's no per-request nonce plumbing yet (`src/proxy.ts` only runs on a
    subset of routes, not the whole site), so a nonce-based tightening is a good follow-up once
    that's generalised.
  - `img-src` allows `https:` broadly because the media disk (local storage vs. an S3-compatible
    bucket) is a per-deployment choice, not a fixed host; verified against a real build that every
    resource the app actually serves is same-origin (`/_next/static/...`) regardless.
  - Verified for real: `npm run build` succeeded, `npm start` on :3765, and `curl` against `/login`
    confirmed the header renders exactly as configured and the page's own inline theme script is
    present (so `'unsafe-inline'` is load-bearing, not precautionary).

### Observability

- **`RequestId` middleware** (`app/Support/Http/Middleware/RequestId.php`), prepended to the `api`
  group: honours an incoming `X-Request-Id` if it looks sane (`^[A-Za-z0-9._-]{1,64}$`), otherwise
  mints a ULID; pushes it onto Laravel's `Context` (so every log line for the request carries it
  automatically) and echoes it back as a response header, including on error responses.
- **`/up` readiness**: `ObservabilityServiceProvider` listens for `DiagnosingHealth` and checks the
  database (`DB::connection()->getPdo()`) and cache (`Cache::store()->put(...)`), throwing if
  either fails — Laravel's default `/up` otherwise only proves the app booted, not that it can
  serve a real request.
- **Structured logs**: a `json` channel in `config/logging.php` (Monolog's `JsonFormatter` over
  `php://stderr` by default, `LOG_JSON_STREAM`-configurable) for a production log aggregator; not
  the default channel (`single` stays friendlier for local dev) — set `LOG_CHANNEL=json` in
  production.
- **Slow query log**: `DB::listen` in the same provider logs (never throws) queries ≥ 500 ms, SQL
  and timing only — no bindings, since a `WHERE` clause can carry a phone number or token.
- **Still needed before launch, called out explicitly rather than left implicit**: nothing is wired
  to a real error-tracking backend (no Sentry/equivalent DSN exists yet in this codebase or
  environment) — the request-id/context plumbing above is what a future integration would hook
  into, but until one is added, unhandled exceptions are only as visible as the configured log
  channel.

### Backups / DR

- **`backup:run`** (`app/Support/Backup/Console/BackupDatabaseCommand.php`): MySQL only (checks
  the default connection's driver and fails cleanly otherwise); `mysqldump --single-transaction
  --quick --no-tablespaces` piped to `gzip`, uploaded to the `backup.disk` filesystem disk (`local`
  by default; point `BACKUP_DISK` at an S3-compatible bucket in production, same shape as
  `MEDIA_DISK`), named `backups/cafe-{env}-{UTC timestamp}.sql.gz`. The DB password travels via the
  `MYSQL_PWD` env var, never on the command line. Scheduled daily at 03:30 Asia/Tehran.
- **Retention**: `BACKUP_KEEP_DAYS` (default 14), pruned by the date already encoded in each
  backup's filename — not the storage disk's reported modified time, which isn't reliable on every
  disk driver.
- **`docs/ops/backup-restore-runbook.md`**: what's covered (DB only) and explicitly what isn't
  (media, Redis, point-in-time recovery), a restore procedure (scratch database first, sanity
  checks, only then swap production), proposed RPO/RTO numbers marked as unconfirmed, and a note
  that neither the command nor the runbook has been through a real restore drill yet.

### Load testing

- Four k6 scripts under `apps/api/tests/load/`: `menu-browse.js` (guest, no setup needed),
  `checkout-flow.js` and `order-tracking.js` (need a seeded table QR token), `kds-poll.js` (needs a
  paired device token). Each states its own thresholds (p95 latency, error rate).
- `docs/ops/load-testing.md`: how to seed a tenant, run them, and read the results, plus an
  explicit note that rate limits will (correctly) trip under load well above realistic traffic.
- **Not run against a live deployment** — no staging environment exists in this session. The
  scripts were reviewed against the actual route contracts (checkout's guest-vs-customer rule,
  cart token headers, etc.) but their pass/fail thresholds are a starting point.

### Legacy cut-over runbook

- `docs/ops/legacy-cutover-runbook.md`: what the existing `catalog:import-woocommerce` importer
  moves and, explicitly, the long list of what it doesn't (customers, wallets, loyalty, orders,
  reservations, KDS PIN, push/SMS credentials) — matching the discovery report's D1 decision.
  Pre-migration checklist, migration steps (dry run first), go-live steps (new QR codes, re-paired
  KDS devices, DNS cut-over), and a rollback note (safe and simple, since migration is read-only
  against the legacy WordPress database).

## 2. API

- `RequestId` middleware, prepended to the `api` group.
- `ObservabilityServiceProvider` (health check listener, slow query log, registers `backup:run`).
- `backup:run` Artisan command; `config/backup.php`.
- No new HTTP endpoints; `/up`'s existing path and contract are unchanged, only its health
  semantics are stricter now.

## 3. Web

- CSP header added to `next.config.ts`. No new screens — this phase is infra/process, matching the
  plan.

## 4. Verification — what ran, and what could not run here

- **Web**: `npm run build`, `npm run typecheck`, `npm run lint` all ran and passed in this session
  (after `npm ci`, which succeeded — the npm registry was reachable). The CSP change was verified
  against a real production build served on :3765, not just written and assumed correct.
- **API**: this session's outbound network access to `api.github.com`/`github.com` was flaky enough
  (confirmed via the environment's own proxy status: repeated connection resets to
  `api.github.com`, and both the GitHub API zipball and plain archive endpoints returned 403 to a
  direct `curl`) that `composer install` could never finish — every attempt (dist, source,
  multiple retries) failed on the `phpunit/phpunit` dependency chain specifically. This is an
  environment/network limitation of this session, not a project issue: `vendor/` has never been
  more than partially populated here, so **Larastan, Pint and the test suite could not be run
  locally in this session**, including for the new tests added in this phase
  (`ObservabilityTest`, `BackupDatabaseCommandTest`, the `TenantIsolationTest` and
  `SettingsAndBrandingTest` additions).
- **What was done instead, to reduce risk**: every new and modified PHP file was checked with
  `php -l` (syntax only) and hand-reviewed against the exact framework/library APIs it uses (the
  Laravel 13 `DiagnosingHealth` health-check pattern, Symfony Process's `fromShellCommandline`,
  Laravel's `Context` facade auto-merging into log records, Monolog's `JsonFormatter`) rather than
  guessed. The backup command's tests are written to genuinely exercise `mysqldump` against the
  real MySQL service in CI's second test job, and to skip (not fake-pass) under the sqlite job.
  `composer audit` and `npm audit` are wired into CI so the dependency-audit gate is real even
  though it couldn't be exercised here.
- **This means CI, not this session, is the actual gate for the API side of this phase.** The
  first CI run after this branch is pushed is load-bearing in a way it normally wouldn't be —
  worth watching closely rather than assuming green.

## 5. Risks

- **CSP** ships with `'unsafe-inline'` for scripts and styles — a real (if standard) trade-off, not
  a full lockdown. A nonce-based tightening needs `src/proxy.ts`'s matcher generalised to run on
  every route first; that's a larger, separate change.
- **Backups are new and unexercised.** The command and runbook have been written carefully but not
  proven against a real restore. Treat the RPO/RTO numbers in the runbook as proposals to confirm,
  not measured facts.
- **Load test thresholds are unvalidated** against any live target — see §1.
- **No error-tracking backend is wired up.** This is flagged as a hard requirement before launch in
  the observability section above, not something to discover later.
- **The API side of this phase is unverified locally**, per §4. If CI surfaces a Larastan or test
  failure, it should be treated as a real finding to fix, not a fluke — nothing here was validated
  beyond syntax checking and careful reading in this session.
