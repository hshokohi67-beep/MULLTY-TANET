# Phase 15b: Role-aware help centre, go-live preflight, media paths (Report)

Plan: `phase-15b-plan.md`. Status: done.

## 1. What was built

### Help centre (`/dashboard/help`, `/platform/help`)

- **Content**: typed data in `apps/web/src/lib/help/`, 31 café-side topics plus 4 platform topics.
  - **Role guides**: owner, manager, cashier, kitchen/bar, waiter. Each covers a day in the role, what to do first and the relevant tabs.
  - **One topic per screen**: overview, command palette and bell, time clock, orders, kitchen screen, kitchen setup, tables and QR, menu, discounts, stories, delivery zones, marketplace, ads, the storefront as customers see it, stock, purchasing, staff and payroll, expenses, customers, club, payments and refunds, reports, branches, team and roles, settings, subscription.
  - **Inside each topic**: numbered steps, worked examples with real amounts and times, tips, warnings, FAQs and related topics.
  - **Platform topics**: subscribers and plans, marketplace moderation and city photos, ad review and placement prices.
- **Visibility follows the reader's role**:
  - A topic opens only if its permissions match the sidebar's. For example, a cashier sees 13 topics and the owner sees 31.
  - Role guides are shown to their own role; the owner sees every role guide, to train the team.
  - A topic outside the reader's role returns 404.
  - Topics outside the plan are marked with a lock.
- **Index page**:
  - "your role" card, fed by the new `memberships[].roles` in `/auth/staff/me`;
  - search over titles, steps, examples and FAQs;
  - topics grouped like the sidebar.
- **Topic page**:
  - numbered sections with a table of contents (desktop);
  - example, tip and warning boxes;
  - FAQs, related topics, previous/next;
  - «رفتن به همین بخش» ("go to this section").
- **Navigation**:
  - «راهنمای کامل» ("full guide") sits in every user's sidebar.
  - A «راهنما» ("help") button in the top bar (tablet and desktop) opens the current screen's topic. Only a path → key map reaches the client bundle, not the help text.
  - The command palette finds it («راهنما»).
  - The platform header links to `/platform/help`.

### Go-live preflight

- `php artisan ops:preflight [--json]` runs 20 checks and exits non-zero on any blocking failure. Each failed check prints the exact fix.
- **Checks**:
  - environment, debug, app key, HTTPS API and storefront URLs;
  - MySQL, a real queue, a shared cache (warning only);
  - real store and subscription gateways, sandbox off, a real SMS provider;
  - trusted proxies (`app.trusted_proxies`), secure cookies, log channel (warning only), off-server backups (warning only), storage link;
  - no demo accounts or demo cafés, and a real platform admin.

### Media paths without internal ids

- New tenants get a random `media_key`; existing tenants are backfilled by a migration.
- Every new upload goes under `t/{media_key}/…`:
  - logo, cover, category and product photos, stories, and the WooCommerce importer.
  - Product photos no longer include the product id either.
- Existing files keep their `tenants/{id}/…` paths and keep working.

### Also

- `OrderHistoryTest` failed when run near midnight Tehran time, because "+10 minutes" crossed into another business date. It now runs at a fixed midday.

## 2. Verification

- **API**: Pint clean, Larastan 0, **513 tests: 512 passed, 1 skipped** (the MySQL-only backup test).
  - New `PreflightAndRolesTest`:
    - preflight flags a dev setup, and passes with a production-like config;
    - `me` returns role names.
  - Updated media-path tests assert the key path and that no tenant id appears.
- **Web**: typecheck, lint and build green.
- **Visual check**:
  - help index as cashier and as owner (1280 px);
  - cashier role guide at 390 px, dark;
  - a cashier opening the billing topic gets 404;
  - no horizontal overflow at 390 px on help, orders and overview.
- **Fixed during the check**: the extra top-bar button overflowed phones by 35 px. It is now hidden below `sm`, since the sidebar has the link.
