# Phase 1 — Foundation & Multi-Tenancy: Implementation Plan

**Status:** In progress · **Depends on:** Phase 0 (approved 2026-09-24, decisions in discovery report §G)

## 1. Repository analysis

The repo is empty apart from `legacy/` (a read-only snapshot) and `docs/`. There is nothing to refactor. Environment available locally: PHP 8.4 (intl, sodium, pdo_mysql, pdo_sqlite, gmp, bcmath), Composer 2.10, Node 24 / npm 11, MySQL 8.4 (running), Redis 5 binary (Laragon, not running), **no Docker**.

## 2. Dependencies (kept minimal; each one justified)

| Package | Why |
|---|---|
| `laravel/framework` 13.x | The chosen backend framework |
| `laravel/sanctum` | First-party API tokens for staff and customers (two tokenable models). Replaces the legacy WP cookie/`localStorage` phone identity |
| `larastan/larastan`, `laravel/pint` (dev) | Static analysis + code style (Definition of Done) |
| `next` 16, `react`, `typescript`, `tailwindcss` | The chosen frontend stack |
| `vazirmatn` (npm) | **Self-hosted** Persian font. The legacy system depended on a CDN, which is unreliable in Iran |

Deliberately **not** added: spatie/permission (its team model doesn't fit the required `tenant_user_roles` table), stancl/tenancy (single-DB tenancy is small and security-critical enough that we own it), nwidart/modules (plain PSR-4 folders suffice), SMS SDKs (plain Laravel HTTP client), Jalali libraries (PHP `intl` with `@calendar=persian` and JS `Intl` with `-u-ca-persian` are built in and correct). GSAP / Framer Motion are deferred to Phase 7 (storefront), where motion is actually needed.

## 3. Structure

```
apps/api            Laravel 13 (modular monolith)
  app/Modules/<Module>/{Models,Actions,Data,Http/{Controllers,Requests,Resources,Middleware},Policies,Events,Listeners,Database/{Migrations,Factories,Seeders},Providers,Support}
  app/Support/{Tenancy,Localization,Money,Audit,Sms}
apps/web            Next.js 16 App Router, TypeScript, Tailwind (RTL-first)
packages/locale     Shared TS formatters (money, numbers, Jalali date/time, phone, postal code) and a Persian normaliser
packages/ui         Design tokens + base components (RTL, a11y, reduced motion)
docker-compose.yml  MySQL 8.4, Redis 7, Mailpit (for teammates/CI; not verifiable locally)
.github/workflows   CI: api (pint, larastan, tests on MySQL), web (lint, typecheck, build)
```

Phase 1 modules: **Core** (tenants, domains, settings, branding, branches, opening hours), **Tenancy** (context, resolution, scoping, isolation harness), **Identity** (staff users, tenant membership, roles/permissions, customer OTP auth), plus shared **Audit** and **Sms** support. Phase 1 creates a minimal `customers` table (id, tenant, phone, name) because customer OTP login needs it. Phase 5 extends it.

## 4. Database changes (all tables use ULID primary keys to prevent enumeration)

| Table | Key columns | Notes |
|---|---|---|
| `tenants` | id, name, slug (unique), status (`trial/active/suspended/archived`), timezone (`Asia/Tehran`), locale (`fa`), currency (`IRR`), display_currency_unit (`toman`) | Root aggregate |
| `tenant_domains` | tenant_id, domain (unique), type (`subdomain/custom`), is_primary, verified_at | Host → tenant resolution |
| `tenant_settings` | tenant_id, key, value (text), is_encrypted, unique(tenant_id,key) | Secrets are encrypted at rest and masked in resources |
| `tenant_branding` | tenant_id (unique), logo_path, primary_color, theme, seo_title, seo_description | SEO basics per tenant |
| `branches` | tenant_id, name, slug, phone, province, city, address, postal_code, latitude, longitude, is_active, unique(tenant_id,slug) | Exists from day one |
| `branch_opening_hours` | tenant_id, branch_id, weekday (ISO 1=Mon..7=Sun), opens_at, closes_at, sort | Overnight when closes_at ≤ opens_at; several intervals per day are allowed |
| `users` | id, name, email (unique, nullable), phone_e164 (unique, nullable), password, is_platform_admin, last_login_at | Staff/platform identity (**not** customers) |
| `tenant_users` | tenant_id, user_id, status, unique(tenant_id,user_id) | Membership |
| `permissions` | id, key (unique), group | Global catalogue, seeded from code |
| `roles` | tenant_id, key, name, is_system, unique(tenant_id,key) | Per tenant; default roles seeded on tenant creation |
| `role_permissions` | role_id, permission_id | — |
| `tenant_user_roles` | tenant_user_id, role_id | — |
| `customers` (minimal) | tenant_id, phone_e164, name, unique(tenant_id, phone_e164) | Tenant-scoped identity (master §5) |
| `audit_logs` | tenant_id (nullable), actor_type, actor_id, action, subject_type, subject_id, changes (JSON, secrets masked), ip, user_agent, created_at | Append-only |
| framework | `personal_access_tokens`, `cache`, `jobs`, `failed_jobs`, `job_batches`, `sessions` | Framework-required |

## 5. API (v1, JSON, Persian messages)

- `POST /api/v1/auth/staff/login`, `POST /auth/staff/logout`, `GET /auth/staff/me` (includes memberships + permissions per tenant)
- `POST /api/v1/auth/customer/otp/request`, `POST /auth/customer/otp/verify` (tenant-scoped via `X-Tenant`), `POST /auth/customer/logout`, `GET /auth/customer/me`
- Tenant dashboard (header `X-Tenant: <slug>`, staff token + membership + permission):
  `GET/PATCH /tenant` · `GET/PUT /tenant/branding` · `GET/PUT /tenant/settings` (secrets masked) · `CRUD /branches` · `PUT /branches/{id}/opening-hours` · `GET /branches/{id}/open-status` · `GET /roles`, `GET /permissions`, team `GET/POST /team`, `PUT /team/{id}/roles` · `GET /audit-logs`
- Public: `GET /api/v1/public/tenant` (resolved by Host or `X-Tenant`: name, branding, SEO; public fields only)
- Platform admin: `GET/POST /api/v1/platform/tenants` (creates the tenant + owner + default roles + first branch)

## 6. Security implications and controls

1. **Fail-closed tenant scoping.** Tenant-owned models use a `BelongsToTenant` global scope. Querying one with **no** tenant context throws instead of returning every tenant's rows. Only explicit platform code may call `Tenancy::bypass()`.
2. `tenant_id` is set automatically from the context on create and **cannot be changed** after creation (a guard in the model event).
3. Tenant resolution: `X-Tenant` slug, or Host → `tenant_domains`. For staff: membership is verified on every request. For customers: the token's customer must belong to the resolved tenant. Suspended tenants get 403.
4. Route model binding goes through tenant-scoped queries, so a foreign ID returns **404** (not 403) to avoid leaking whether it exists.
5. OTP: 5-digit CSPRNG, stored **hashed** in cache, 2-min TTL, 5 verify attempts, 60s resend cooldown, per-phone daily cap, per-IP limit. The environment is decided by config, never by request headers (legacy critical bug D.4-2).
6. Real client IP behind Cloudflare via the `TRUSTED_PROXIES` config. All auth endpoints are rate-limited.
7. Secrets: encrypted casts, never serialised (`$hidden` + masked resource), and every change is written to the audit log with the value redacted.
8. Staff passwords: bcrypt/argon, generic Persian error on failure (no user enumeration), login throttle.
9. Security headers middleware (nosniff, frame deny, referrer policy). CORS limited to configured frontend origins.

## 7. Localization foundation

Backend: `Money` (integer rial) + `MoneyFormatter` (toman/rial, Persian digits, `٬` separator), `PersianNumber`, `JalaliDate` (intl persian calendar: format + Jalali↔Gregorian), `PhoneNormalizer` (09…, ۰۹…, +98…, 0098…, 98…, 9… → `+989XXXXXXXXX`), `PersianTextNormalizer` (ي/ك/ة/ۀ, digits, ZWNJ, diacritics, whitespace), `lang/fa` validation + auth + app messages, default locale `fa`, Persian exception rendering (no `Unauthorized`/`ValidationException` text leaks to clients).
Frontend: the same formatters in `packages/locale` (Intl-based), `<html lang="fa" dir="rtl">`, self-hosted Vazirmatn, logical CSS properties, and LTR islands for technical values (email, domain, token, SKU).

## 8. Tests

- Unit: PhoneNormalizer (all input forms, invalids), PersianTextNormalizer, Money/MoneyFormatter, JalaliDate (1405/07/02 ⇔ 2026-09-24, leap years), opening-hours "is open" incl. overnight.
- Feature: staff login/logout/me; OTP request/verify happy path, wrong code, attempt lockout, cooldown, expiry, tenant separation of customers (same phone → two customers); tenant resolution (header, host, unknown, suspended); permissions (owner vs cashier); branch CRUD + validation (Persian messages); settings secrets masked + audited.
- **Isolation harness** (`TenantIsolationTestCase`): for every tenant-scoped endpoint, Tenant A's credentials against Tenant B's resources → 404/403, lists never contain B's rows, and a model query without context throws. It is reusable in every later phase.
- Frontend: typecheck, lint, build. Playwright is set up in Phase 7 (first E2E journey).

## 9. Risks

| Risk | Mitigation |
|---|---|
| No Docker locally → compose file unverified | Local dev uses Laragon MySQL/sqlite. Compose + CI run on GitHub |
| MySQL root credentials unknown | Tests run on sqlite in-memory locally and on MySQL in CI. The dev `.env` defaults to sqlite until credentials are provided |
| Laravel 13 / Next 16 are recent majors | Pin exact versions in lockfiles |
| Scope creep in the dashboard shell | Phase 1 UI = login, tenant switcher, RTL layout, settings/branches/team screens only |
