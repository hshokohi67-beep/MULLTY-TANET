# کافه‌یار: Multi-tenant Cafe & Restaurant SaaS

Persian-first, RTL-first, Iran-ready SaaS for cafes and restaurants. Modular monolith.

```
apps/api          Laravel 13 API (modules: Core, Identity, Customers, …)
apps/web          Next.js 16: dashboard, storefront (later: super admin, marketplace)
packages/ui       Design system (tokens + accessible RTL components)
packages/locale   Persian formatters: money (rial→toman), Jalali dates, digits, phone, search
legacy/           Read-only snapshot of the old WordPress plugins (reference only)
docs/             Discovery report and per-phase plans/reports
```

- Discovery & roadmap: `docs/discovery/00-discovery-report.md`
- Phase reports: `docs/phases/phase-01-report.md` (foundation; run locally + demo logins), `docs/phases/phase-02-report.md` (menu/catalog), `docs/phases/phase-03-report.md` (tables/QR, carts, checkout, orders, discounts, delivery zones), `docs/phases/phase-04-report.md` (payments: Zarinpal, counter payments, refunds), `docs/phases/phase-05-report.md` (customer club: wallet, points, tiers, cashback, referral, birthday), `docs/phases/phase-06-report.md` (kitchen display: stations, routing, device pairing), `docs/phases/phase-06b-report.md` (design system, app shell, management overview), `docs/phases/phase-06c-report.md` (customisable dashboard, 18 widgets, end-of-day report), `docs/phases/phase-07-report.md` (storefront: menu, checkout, QR table mode, customer account, PWA/SEO), `docs/phases/phase-07b-report.md` (stories, hot/cold menu mood, category photos, storefront polish), `docs/phases/phase-07c-report.md` (scheduled pre-orders, glass, cart redesign), `docs/phases/phase-08-report.md` (command palette, global search, notification bell, setup progress), `docs/phases/phase-09-report.md` (inventory, recipes, cost of goods, purchasing), `docs/phases/phase-10-report.md` (expenses, staff, shifts, attendance, payroll, profit widget), `docs/phases/phase-11-report.md` (analytics aggregates, reports, Excel/CSV/PDF export), `docs/phases/phase-12-report.md` (plans, subscriptions, invoices, entitlement gate, platform admin)

Quality gates (also in CI): `php artisan test`, `vendor/bin/phpstan analyse`, `vendor/bin/pint --test` in `apps/api`; `npm run typecheck`, `npm run test`, `npm run web:lint`, `npm run web:build` at the root.
