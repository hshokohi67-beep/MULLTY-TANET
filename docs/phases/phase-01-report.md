# Phase 1 — Foundation & Multi-Tenancy: Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-01-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Backend (`apps/api`, Laravel 13.33, PHP ≥ 8.3)
| Area | Delivered |
|---|---|
| Tenancy | `TenantContext` (scoped per request/job), `BelongsToTenant` trait + `TenantScope` (**fail-closed**: querying tenant data without a tenant throws), immutable `tenant_id`, explicit `bypass()` for platform code, `ResolveTenant` middleware (`X-Tenant` slug / `X-Tenant-Domain` host), suspended-tenant blocking, composite `(id, tenant_id)` foreign keys so child rows cannot point at another tenant's parent |
| Core | tenants, domains, settings (typed registry, secrets encrypted), branding + logo upload, branches, opening hours (split shifts, overnight), open-status evaluator, append-only audit log |
| Identity | staff users (one user → many tenants), memberships, code-defined permission catalogue (`permissions:sync`), per-tenant roles (owner/manager/cashier/kitchen/waiter), `Gate::before` permission checks, last-owner and owner-only rules, team management |
| Customer auth | tenant-scoped customers (same phone at two tenants = two customers), OTP login: 5-digit CSPRNG code, HMAC-hashed in cache, 2-min TTL, 5 attempts, 60s cooldown, daily cap, never returned by the API; Sanctum tokens bound to the customer's tenant |
| Localization | `PhoneNormalizer`, `PersianTextNormalizer`, `PersianNumber`, `JalaliDate` (ICU persian calendar), `Weekday` (Saturday-first), `Money` (integer rial, overflow-safe) + `MoneyFormatter` (toman/rial), full `lang/fa` validation/messages, Persian JSON errors for every exception type |
| SMS | `SmsProvider` interface; `log` / `array` (dev/test only, refused elsewhere) and **Kavenegar** (HTTPS, no SDK, key never logged) drivers |
| Security | rate limits (staff login, OTP request/verify, uploads, public), trusted-proxy config for Cloudflare, security headers, CORS allow-list, generic 500s in production |

### Frontend (`apps/web` Next.js 16.3 + `packages/ui` + `packages/locale`)
- **BFF auth**: the staff token lives only in an HttpOnly cookie on the Next server and is never exposed to browser JavaScript; `proxy.ts` gate plus server-side membership checks; open-redirect-safe login.
- Screens (all Persian and RTL): login, tenant picker, dashboard shell (permission-filtered navigation, skip link), overview with setup progress, branches list/create/edit, weekly hours editor (Persian 24-hour picker, "apply to all days"), team (list + add member), settings (business profile, branding + logo, SEO, general settings with a write-only secret field), storefront placeholder `/s/[tenant]` (ISR, Persian metadata, OpenGraph, schema.org JSON-LD), Persian 404/error pages.
- `@cafe/locale`: money, numbers, Jalali date/time, phone and postal-code formatting, search normalisation. The same rules as the backend, verified by tests.
- `@cafe/ui`: design tokens (light/dark, reduced motion), Button, TextField/TextArea/Select/Checkbox (label, hint and error wired with ARIA), Card, Badge, Alert, EmptyState, Ltr island, ClockSelect.
- Self-hosted Vazirmatn variable font (OFL) with no CDN dependency, which matters for availability in Iran.

### Infrastructure
`docker-compose.yml` (MySQL 8.4, Redis 7, Mailpit), `.github/workflows/ci.yml` (Pint, Larastan, tests on sqlite **and MySQL**, locale tests, typecheck, lint, build), `.env.example` documented.

## 2. Verification (run locally)

| Check | Result |
|---|---|
| `php artisan test` | **104 passed** (313 assertions): unit (phone, text, Jalali, money, opening hours) and feature (tenant scope, **isolation harness** over 17 endpoint cases, resolution, staff auth, OTP, permissions, branches, settings/branding/uploads, platform) |
| Larastan level 6 | **0 errors** |
| Pint | passed |
| `@cafe/locale` tests | 5 passed |
| `tsc` (web, ui, locale), ESLint | clean |
| `next build` | success |
| Manual end-to-end (API :8765 + web :3765) | login page, dashboard, branches, team, settings, hours editor rendered correctly in Chrome; the proxy redirects without a session; selecting a tenant the user doesn't belong to → tenant picker; storefront SSR shows Persian title/OG/JSON-LD; unknown tenant → 404 |

## 3. Security review

- **Tenant isolation:** enforced in three layers: the model scope (fail-closed), membership middleware (runs *before* route-model binding so non-members can't probe IDs), and composite FKs in the database. A foreign ID inside your own tenant returns 404.
- **Legacy critical bugs not reproduced:** identity comes only from the token, never from request data; the environment comes from config, never from a Host header; OTP is brute-force-resistant; QR/table enumeration is not applicable yet (Phase 3 will use opaque tokens).
- **Secrets:** encrypted at rest, never serialised (`#[Hidden]`), masked in responses, `[REDACTED]` in the audit log, write-only in the UI.
- **Uploads:** raster types only (SVG rejected), content-sniffed, size/dimension limits, random filenames under a tenant prefix.
- **Browser:** no API token in JS, HttpOnly + SameSite=Lax cookies, security headers on both apps, Server Actions protected by Next's built-in Origin check.

## 4. Deviations from the plan / known gaps

1. **MySQL tests not run locally.** The local MySQL root password is unknown, so tests ran on sqlite. CI runs the suite on MySQL 8.4.
2. **Docker Compose is unverified locally** (Docker isn't installed on this machine).
3. **No staff password reset yet.** Owners are provisioned by the platform admin. Recommend adding staff OTP login in Phase 8 (the OTP infrastructure already exists).
4. **No super-admin UI yet**, only the API (`/platform/tenants`). The UI belongs to the Super Admin experience.
5. **No Playwright E2E yet**, as planned: the first E2E journey arrives with the storefront (Phase 7).
6. Larastan was added after a temporary packagist outage; it is installed and passing now.

## 5. Running locally

```bash
# API (port 8765 chosen because 8000/8010/3000 are used by other local projects)
cd apps/api && composer install && cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed && php artisan storage:link
php artisan serve --port=8765

# Web
npm install            # at repo root
cd apps/web && cp .env.example .env.local && npm run dev -- -p 3765
```
Demo logins (password `password`): owner `09120000001`, cashier `09120000002`, second-cafe owner `09120000003`, platform admin `admin@example.test`. OTP codes in local mode are written to `storage/logs/laravel.log`.
