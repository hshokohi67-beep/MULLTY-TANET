# Phase 12: Billing, Plans and Entitlements (Plan)

Roadmap row (discovery §E.2):
- **Scope:** plans, features, plan features, add-ons, subscriptions, invoices, usage, effective entitlements (plan + add-ons + overrides), trial/grace/read-only states.
- **Retrofit:** entitlement checks go into phases 2–11 through one `Entitlements` gate.
- **Risk:** data-retention rules on downgrade.

## 1. Model

- **Features are defined in code** (`Billing\Support\FeatureCatalog`), because each one is enforced by code:
  - on/off switches: `online_payments`, `loyalty`, `inventory`, `operations`, `reports`, `stories`, `custom_domain`;
  - limits: `branches`, `staff`, `products`, `monthly_orders` (a soft limit that warns only; customer orders are never blocked).
- **Plans and add-ons are platform data** (editable by the platform admin, seeded by the migration). All prices are in rial; yearly = 10 × monthly.

| Plan | Monthly | Branches | Staff | Products | Orders/month | Features |
|---|---|---|---|---|---|---|
| **پایه** (Starter) | 490,000 T | 1 | 5 | 150 | 1,500 | menu, QR, storefront, orders, KDS, dashboard, customers |
| **حرفه‌ای** (Pro) | 1,190,000 T | 2 | 15 | ∞ | 6,000 | + online payments, club, inventory, staff/expenses, reports, stories |
| **زنجیره‌ای** (Chain) | 2,900,000 T | 10 | ∞ | ∞ | ∞ | + custom domain |

  Add-ons:
  - +1 branch: 350,000 T/month;
  - +5 staff: 150,000 T/month;
  - custom domain for Pro: 200,000 T/month.
- **Tenant-owned tables:**
  - `subscriptions`: one per tenant — plan, cycle, status `trialing|active|cancelled`, trial end, current period start/end, a scheduled plan change;
  - `subscription_addons`;
  - `entitlement_overrides`: platform grants per feature, with expiry and reason;
  - `billing_invoices`: global Jalali-year number, JSON lines, subtotal, credit, VAT, total, status `open|paid|void`;
  - `billing_payments`: gateway attempts.
- **Stored vs. computed state:** the stored status is minimal; the effective state is computed from timestamps, so no cron is needed to flip it:
  - `trial`: before the trial ends;
  - `active`: inside the paid period;
  - `grace`: up to 7 days after an unpaid end, full access with a warning;
  - `read_only`: after grace, or after a cancelled period ends.
- **Read-only:** reads work; writes are refused with `subscription_read_only` (HTTP 402). Billing and logout still work. Public checkout is refused politely. Nothing is ever deleted.
- **Downgrade and retention:** no data is deleted. Above a limit you keep what you have but cannot add more. A feature turned off hides its screens and endpoints but keeps its data (restored when you upgrade again).
- **New tenants** start a 14-day Pro trial (creation hook). The migration gives existing tenants the same trial.

## 2. Money flow

- **`quote(plan, cycle, addons)`:**
  - the lines (plan and add-ons for the cycle);
  - a credit for the unused days of the current paid plan when switching mid-period;
  - VAT (`billing.vat_rate`, default 10%);
  - the total.
- **Price-based modes:**
  - cheaper while active: `scheduled` — takes effect at the period end, no payment;
  - same plan while active: `renew` — extends from the current end;
  - otherwise: `pay_now` — starts today.
- **Checkout:**
  1. The API creates an open invoice.
  2. It opens a gateway session using the **platform** merchant (`billing.gateway` fake|zarinpal, `BILLING_ZARINPAL_MERCHANT`). Gateway calls happen outside transactions and are logged.
  3. The web return route calls `verify`.
  4. On success the invoice is paid and the subscription is applied idempotently under a lock.
- **`billing:renewals`** (daily):
  - issues the renewal invoice 7 days before the period ends (with a scheduled plan change applied);
  - sends a reminder SMS to the owner 7 days before, 1 day before, and when read-only starts;
  - bank transfers can be marked paid by the platform admin.

## 3. Enforcement (one gate)

- `App\Support\Entitlements\EntitlementGate`: a contract in `Support`, a permissive default, and the real implementation bound by Billing (so lower modules don't depend on Billing).
  - `enabled($feature)`, `limit($feature)`, `ensureCanAdd($feature, $currentCount)` → `plan_limit_reached` (402), `assertWritable(Request)`.
  - Route middleware `feature:<key>` → `feature_not_in_plan` (402).
  - Per-request memo plus a versioned cache.
- **Retrofit:**
  - route groups: `feature:inventory` (inventory/purchasing), `feature:operations` (expenses/staff/time clock), `feature:reports` (reports), `feature:loyalty` (staff club and public club actions), `feature:stories` (story management; public stories stay empty when off);
  - online payments: `GatewayFactory::onlineAvailable()` also requires `online_payments`;
  - limits: branch create, team member add, product create;
  - side effects skipped when a feature is off: stock consumption, loyalty rewards;
  - `ResolveTenant` calls `assertWritable` for staff and public writes.

## 4. API

**Tenant** (`billing.manage`, owner by default; the state is also readable with `tenant.view`):
- `GET /billing` (state, plan, add-ons, entitlements, usage, next invoice);
- `GET /billing/plans`;
- `POST /billing/quote`;
- `POST /billing/checkout`;
- `POST /billing/invoices/{invoice}/verify`;
- `GET /billing/invoices`, `GET /billing/invoices/{invoice}`;
- `POST /billing/cancel`, `POST /billing/resume`.

**Platform** (`actor:platform`):
- `GET /platform/subscriptions`;
- `GET/PUT /platform/plans/{plan}`;
- `POST /platform/tenants/{tenant}/extend`;
- `POST/DELETE /platform/tenants/{tenant}/overrides`;
- `POST /platform/invoices/{invoice}/mark-paid`.

## 5. Web

- **`/dashboard/billing`:**
  - status hero (plan, state, days left);
  - usage meters;
  - plan cards with a monthly/yearly toggle;
  - add-ons;
  - a checkout dialog with the quote;
  - invoices, with a printable invoice at `/print/invoice/[id]`.
- **AppShell banner:** trial ending, grace, read-only.
- **Locked navigation:** items for features outside the plan show a lock and lead to the upgrade view. `requireFeature()` on gated pages.
- **`/platform`** (platform admins only): tenants with subscription state and actions (extend, override, mark paid), plan editor. A user-menu link appears for platform admins.

## 6. Tests

- State timeline: trial → grace → read-only; paid period → grace → read-only; cancelled.
- Entitlements: plan, add-ons and overrides with expiry.
- Feature middleware and limits on each retrofitted spot.
- Read-only allows GET and billing and blocks writes.
- Quote proration, VAT, scheduled downgrade.
- Checkout with the fake gateway: verify is idempotent and a failed payment is handled.
- Renewal invoice and reminder.
- Platform endpoints and permission; isolation (billing endpoints, a foreign invoice).

## 7. Risks

- Existing tests assume full features: the test base gives tenants an active Chain subscription unless a test says otherwise.
- Enforcing read-only inside `ResolveTenant` affects every tenant route: an explicit allow-list plus tests.
- Money correctness: integer rial, proration rounded to whole toman, VAT on the discounted subtotal.
