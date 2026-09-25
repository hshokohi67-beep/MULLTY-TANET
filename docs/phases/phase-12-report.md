# Phase 12: Billing, Plans and Entitlements (Report)

Plan: `phase-12-plan.md`. Status: done, awaiting approval.

## 1. What was built

### One entitlement gate for every module (`app/Support/Entitlements`)

- `EntitlementGate`:
  - `enabled(feature)`, `limit(feature)`, `ensureCanAdd(feature, count)` → `plan_limit_reached` (402), `assertWritable(request)` → `subscription_read_only` / `store_unavailable` (402);
  - a permissive default binding (so lower modules never depend on Billing); Billing binds the real, request-scoped `Entitlements`;
  - the state is computed from the clock on every call; the memo is cleared when a request ends and whenever a subscription, add-on or override changes.
- Route middleware `feature:<key>` → `feature_not_in_plan` (402). It runs after membership, so a stranger still gets 403.
- **Retrofit into phases 2–11:**

| Where | Gate |
|---|---|
| Inventory + purchasing routes | `feature:inventory` |
| Expenses, staff, time clock | `feature:operations` |
| Reports and export | `feature:reports` |
| Club management | `feature:loyalty` |
| Story management | `feature:stories` (public stories return an empty list) |
| Online payments | `GatewayFactory::onlineAvailable()` |
| Branch / team member / product creation | `ensureCanAdd` (branches / staff / products) |
| Stock consumption, new loyalty rewards | skipped when the feature is off (restores and reversals always run) |
| Every tenant write | `ResolveTenant` → `assertWritable` (billing and logout allowed) |
| Dashboard widgets and alerts | plan-bound widgets are hidden (not deleted from layouts) and their endpoints return 404; alerts follow the plan |

- Customer wallets stay usable whatever the plan: customers' money is never locked.

### Module `Billing`

- **Catalogue:** plans and add-ons are platform data, seeded by the migration; features are defined in code (`FeatureCatalog`).

  | Plan | Monthly | Branches / staff / products / orders a month |
  |---|---|---|
  | پایه (Starter) | 490,000 T | 1 / 5 / 150 / 1,500 |
  | حرفه‌ای (Pro) | 1,190,000 T | 2 / 15 / ∞ / 6,000 (+ online payments, club, inventory, staff/expenses, reports, stories) |
  | زنجیره‌ای (Chain) | 2,900,000 T | 10 / ∞ / ∞ / ∞ (+ custom domain) |

  - Yearly = 10 × monthly.
  - Add-ons: +1 branch, +5 staff, custom domain (Pro only).
  - `monthly_orders` only warns; customer orders are never refused.
- **Subscription** (one per tenant): the stored status is `trialing | active | cancelled`; the effective state is computed:
  - `trial`;
  - `active`;
  - `grace`: 7 days after an unpaid end, full access with a warning;
  - `read_only`: reads and paying work; nothing is deleted.

  New cafés get a 14-day Pro trial (`Tenant::created` hook). The migration gave existing cafés the same.
- **Quote** (`QuoteBuilder`):
  - `renew`: same selection, extends from the current end;
  - `scheduled`: cheaper while paid, applied at the period end, nothing to pay;
  - `pay_now`: starts today, crediting the unused time at the cycle's daily rate, rounded to whole toman;
  - VAT 10% on (subtotal − credit);
  - downgrade warnings in plain words: which features turn off (data kept) and which limits you are already over (nothing deleted, you just can't add).
- **Checkout** (`ManageBilling`):
  1. Creates an invoice with a gap-free Jalali-year number (e.g. `1405-000042`, locked sequence).
  2. Opens a session on the **platform's own** gateway (`BILLING_GATEWAY`, `BILLING_ZARINPAL_MERCHANT`), outside any transaction, with every request/response kept on `billing_payments.log`.
  3. On return, `verify` checks with the gateway and applies the invoice exactly once (lock on the invoice). Refreshing the return page changes nothing.
  - If the credit covers everything, nothing is charged.
- **Renewals** (`billing:renewals`, daily 09:00 Tehran):
  - the renewal invoice a week before the end (with a scheduled plan change);
  - owner SMS at 7 days, 1 day and at read-only, each sent once.
- **Cancel/resume:** a cancelled subscription runs to its period end, then becomes read-only (no grace).
- **Platform admin API:**
  - all subscriptions with state, open invoices and overrides;
  - plan editing;
  - extend the trial or period (with a reason);
  - grant or remove an override (with optional expiry);
  - mark a bank transfer paid (applies the invoice);
  - a café's effective features.
- **Permission:** `billing.manage` (owner). `GET /billing/status` needs only `tenant.view` (banner, locks).

### Web

- **«اشتراک و پرداخت»** ("Subscription and payments", `/dashboard/billing`):
  - hero card: plan, state, days left against the real period, add-ons, scheduled change, open invoice with «پرداخت» ("pay"), cancel with a second tap, or resume;
  - usage against limits;
  - plan cards with a monthly/yearly switch («۲ ماه رایگان», "2 months free"), a «پیشنهاد ما» ("our pick") badge, and every feature listed with a check or cross;
  - checkout dialog: add-on steppers and toggles, a live quote (lines, credit, VAT, total, period), downgrade warnings, then the gateway;
  - invoices table with a printable invoice (`/print/invoice/[id]`, A4, Save as PDF);
  - gateway return: `/billing/return` verifies and lands on `?payment=ok|failed|cancelled`.
- **App shell:**
  - one-line banner for grace, read-only, a trial ending (≤ 5 days) or a period ending (≤ 3 days), with «تمدید اشتراک» ("renew subscription");
  - screens outside the plan keep their navigation item with a lock and redirect to the upgrade view (`requireFeature`), which explains what the feature does;
  - pages that only *use* gated data (menu recipe, customers and discounts tiers) quietly skip it;
  - bell alert `subscription`.
- **`/platform`** (platform admins only; linked from the user menu and the tenant picker):
  - subscribers table with state, end date and overrides (removable);
  - dialogs for extending, granting a feature, and confirming a transfer;
  - plan editor (prices, feature switches, limits, visibility).

## 2. Verification

- API: 458 tests green. `tests/Feature/Billing/BillingTest.php`, 5 scenarios:
  1. Timeline: trial → bell warning at 5 days → grace (writes still work) → read-only (staff write 402, public write 402, reads and quote allowed) → paying restores everything.
  2. Starter gates:
     - reports, inventory, expenses, club and stories return 402; public stories return empty; plan-bound widgets are gone from the catalogue and return 404;
     - the branch limit is lifted by the add-on; the staff limit is enforced;
     - a platform override switches reports on until it expires.
  3. Quote, checkout and verify:
     - Pro: 11,900,000 + 10% VAT = 13,090,000 rial;
     - verify is idempotent; the invoice number is `1405-…`;
     - same selection gives `renew`; an upgrade half-way gets about 50% credit, in whole toman;
     - a downgrade is `scheduled` with a merged warning;
     - a wrong authority returns 422.
  4. Renewals and cancellation:
     - the renewal invoice for the scheduled Starter plan is 5,390,000 rial; running the command twice creates one invoice and one SMS;
     - paying the renewal extends the period by one month without moving its start;
     - cancel/resume; after a cancelled period ends, read-only without grace, and resume is refused.
  5. Permissions and platform:
     - a manager can't open `/billing` but can read the status; the owner can't reach `/platform`;
     - platform: listing, extend, yearly Chain transfer (290,000,000 + VAT = 319,000,000) marked paid, plan editing.
- The test base puts every tenant on an active Chain plan, so all 453 earlier tests run unchanged with full features.
- Isolation harness (197): every billing endpoint; a tenant-B invoice cannot be seen, paid or verified from tenant A (and no payment row is created).
- Larastan level 6: 0 errors. Pint clean.
- Web: typecheck, lint and build green. `@cafe/ui` 6 and `@cafe/locale` 8 tests green.
- Visual check (headless Chrome), including a full browser checkout through the fake gateway back to `?payment=ok`:
  - subscription page (trial, active, grace);
  - checkout dialog;
  - locked navigation and the upgrade message;
  - printed invoice;
  - platform subscribers (light) and plans (dark);
  - grace banner at 390 px, dark, with no horizontal overflow.
- Fixed during the check:
  - the gateway return redirected to the server's own host (now a relative `Location`);
  - an early renewal moved the period start into the future, which inflated the upgrade credit (the start is now kept and the credit uses the daily rate);
  - the progress bar assumed 30 days;
  - repetitive downgrade warnings were merged;
  - the plan editor now groups switches and limits;
  - a duplicated "sold out" bell alert from Phase 8 was removed.

## 3. Notes

- **Production:** set `BILLING_GATEWAY=zarinpal` and `BILLING_ZARINPAL_MERCHANT` (the platform's merchant). `STOREFRONT_URL` must be the public web origin (it is the gateway return base).
- **Scheduler:** schedule `billing:renewals` (daily) together with the existing jobs.
- **Data retention:** nothing is deleted on downgrade, cancellation or read-only. Archiving long read-only cafés is a platform decision for the Hardening phase.
- **Dev:** the demo café paid twice for Pro through the fake gateway during the check and is now active until ۱۴۰۵/۰۹/۰۴. Temporary dev overrides were removed.
