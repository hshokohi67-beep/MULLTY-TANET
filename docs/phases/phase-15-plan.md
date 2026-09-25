# Phase 15: Hardening (Plan)

Discovery scope (§E): load tests, security review/pen-test checklist, backups/DR, observability, CSP, dependency audit, legacy cut-over runbook. Depends on all prior phases; this is the last phase before general availability.

The project has no real production traffic yet (§F D1), so this phase produces the tooling, checklists and runbooks a launch needs, fixes anything the review finds, and documents what still needs a live environment to finish (running load tests against staging, wiring a real Sentry DSN, taking the first real backup).

## 1. Approach

### Security review

A systematic pass over every module, written up as `docs/phases/phase-15-security-review.md` (findings + fixes, not a plan). Checks:

- **Tenant isolation completeness.** Cross-check every route in every `Modules/*/routes.php` against `tests/Feature/Tenancy/TenantIsolationTest.php` so no tenant-owned endpoint is missing; add any that are.
- **Cross-tenant queries.** Grep for direct model queries that could skip the global scope, and confirm every `TenantContext::bypass()` call carries the required comment.
- **Platform vs. tenant boundaries.** Every `Platform` controller requires `actor:platform`; no route mixes tenant and platform auth.
- **Secrets.** Gateway credentials, SMS credentials, VAPID keys, OAuth-style tokens are encrypted at rest and never logged; check `PaymentLog` and other loggers for accidental payloads.
- **Bearer tokens** (QR, cart, session, tracking, ad-event HMAC) stay out of logged URLs and are hashed/HMAC'd, per the existing rule.
- **File uploads** go through `ImageProcessor`; no raw upload is ever served publicly; validate MIME/size/dimensions on every upload endpoint.
- **Mass assignment / FormRequest coverage** on write endpoints added in later phases (Advertising, Billing, Marketplace) that came after the last full pass.
- **Rate limiting coverage** for public/guest endpoints added since Phase 12 (ads events, marketplace search).
- **IDOR patterns from the legacy audit** (§D.4 of the discovery report): confirm no endpoint trusts an id/phone from the request body in place of the authenticated actor.
- Any real finding is fixed in this phase, not deferred.

### Dependency audit

- `composer audit` and `npm audit --workspaces` run clean today; wire both into CI (`ci.yml`) so a future vulnerable dependency fails the build.
- Document the policy: security-only auto-merge is out of scope (no Dependabot config exists yet); this phase only adds the CI gate.

### CSP

- **API** already sends a strict `default-src 'none'` CSP (data endpoints, nothing to render) — unchanged.
- **Web** (`apps/web/next.config.ts`) has no CSP today. Add one scoped to what the app actually loads: self scripts/styles, the object-storage host(s) for images, Google Fonts is not used (self-hosted Vazirmatn per discovery §D.3.8) — confirm and lock down `font-src 'self'`. No inline scripts beyond Next's own hashes/nonces, so start `script-src 'self'` and adjust only if the build reports a violation.

### Observability

- **Health.** Laravel's default `/up` only checks the app boots. Add a `/up` readiness check (DB + cache) via a custom health check binding, still framework-native (no new package).
- **Correlation.** A `RequestId` middleware stamps every request/response with `X-Request-Id` (incoming id honoured if present, generated otherwise) and pushes it onto the log context, so a support ticket's request id finds every log line.
- **Structured logs.** A `json` log channel (Monolog's built-in JSON formatter) for production, selectable via `LOG_CHANNEL`; no new dependency.
- **Error tracking hook.** Nothing is wired to a real service (no DSN exists yet); document in the report exactly what env var to set and which provider to add when one is chosen, so this isn't a silent gap.
- **Slow query log.** A threshold (`DB::listen`) that logs (not throws) queries over e.g. 500 ms outside tests, tagged with the request id.

### Backups / DR

- `app/Console/Commands/BackupDatabase.php`: `mysqldump` (or `pg_dump`-ready, but the project is MySQL-only) piped to gzip, written to the `backups` disk (local by default, S3-compatible in production via existing `filesystems.php` conventions), filename `cafe-{env}-{UTC timestamp}.sql.gz`, with retention (delete backups older than N days, configurable).
- Scheduled daily in `routes/console.php`, off the request path.
- `docs/ops/backup-restore-runbook.md`: how to restore, RPO/RTO targets, what is and isn't covered (uploaded media relies on the object-storage provider's own durability/versioning, not this command).

### Load testing

- k6 scripts under `apps/api/tests/load/` for the highest-traffic paths: storefront menu read, cart/checkout, order-tracking poll, KDS poll. Each script states its pass/fail thresholds (p95 latency, error rate).
- `docs/ops/load-testing.md`: how to run them against a staging URL with a seeded tenant, and how to read the results. Not executed against a live deployment from this session (none exists); the scripts and thresholds are the deliverable.

### Legacy cut-over runbook

- `docs/ops/legacy-cutover-runbook.md`, reflecting the discovery decision (§F D1: lightweight catalog importer only, no order/wallet/loyalty importer): onboarding checklist for moving one café off the legacy WordPress site — catalog import, branding, staff accounts, QR reprint, DNS/domain cut-over, a rollback point, and the "which legacy data is not migrated" list surfaced to the owner up front.

## 2. API

- `GET /up`: extended readiness (DB + cache), still unauthenticated and outside `api/*` (unchanged path/behaviour for infra probes).
- No new public endpoints. `RequestId` middleware applies to all responses (api + web routes group).
- New Artisan commands: `backup:run` (scheduled), plus whatever the security review turns up.

## 3. Web

- CSP header added to `next.config.ts`.
- No new screens; this phase is infra/process, not a feature surface.

## 4. Security

- This phase *is* the security review; findings and fixes are tracked in `phase-15-security-review.md` and folded into the relevant module's code directly (no separate security module).
- Backup files must never leave the trusted storage disk unencrypted-in-transit; the command uses the existing filesystem disk credentials (no new secret store).
- CSP additions are tested against a real page load (dev server) before being called done, per the empty-CSP risk of silently breaking the app.

## 5. Tests

- `RequestId` middleware: incoming id honoured; one generated when absent; present on error responses too.
- `/up`: 200 when DB/cache are healthy; 503 when DB is down (mocked).
- `backup:run`: writes a file to the fake `backups` disk with the expected name pattern, prunes files older than retention, non-zero exit on `mysqldump` failure.
- Any code fix from the security review gets its own regression test (isolation harness entry, authz test, etc.).
- API: full suite green, Larastan level 6 (no ignores/baseline), Pint clean.
- Web: typecheck, lint, build green; manual check that the CSP doesn't break the storefront, dashboard or platform screens (images, fonts, scripts all load).

## 6. Risks

- **CSP false negatives:** a directive too strict silently breaks an image or a script in production only. Mitigated by testing real page loads (storefront, dashboard, platform) against the new CSP before merging, and keeping `report-only` out of scope only because there's no report endpoint to receive violations yet — noted as a follow-up.
- **Backups without a working restore drill:** the command and runbook are new and untested against real production data. The report will say so plainly rather than implying it's battle-tested.
- **Load test scripts are unvalidated against a live target** (no staging environment in this session). They are reviewed for correctness but their thresholds are a starting point, not a guarantee.
- **Observability without a live error-tracking backend** means the hook is dormant until a DSN is configured; the report calls this out as a hard requirement before launch, not an oversight.
