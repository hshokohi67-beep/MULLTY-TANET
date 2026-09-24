# Phase 5 — Customer Club: Implementation Plan

**Status:** Implemented (see `phase-05-report.md`) · **Depends on:** Phase 4 (approved 2026-09-24)

## 1. Scope
Replace the legacy "LCM Customer Club" with a correct, tenant-scoped club:
- customer profiles with a Jalali birthday;
- a staff customer list with CSV export;
- a **wallet with an exact ledger**;
- **loyalty points**;
- **tiers** (spend-based, never auto-downgrade);
- **cashback rules** (per category, threshold-based);
- **points → wallet redemption**;
- **basic referral** (D4);
- **birthday gift** (once per Jalali year);
- paying orders **with the wallet** (whole or part, the rest online or at the counter);
- **tier-specific discounts**.

Rewards are granted on order **completion** (D8) and **reversed on refund**.

Delivered in steps, each tested before the next:
- **5a:** profiles + staff customers.
- **5b:** wallet/points ledgers + manual adjustments.
- **5c:** wallet payments and automatic wallet refunds.
- **5d:** earning engine (points, cashback, tiers) with reversal.
- **5e:** redeem, referral, birthday.
- **5f:** tier discounts.
- **5g:** dashboard UI.

Out of scope:
- weekly challenges and badges (V2 extension points, D4);
- item likes (V2, D5);
- bulk SMS campaigns (Notifications phase);
- storefront account UI (Phase 7; the customer API is built now);
- legacy member import (D1).

## 2. Database (tenant-owned, ULID, composite FKs)

| Table | Key columns | Notes |
|---|---|---|
| `customers` (+cols) | birth_month, birth_day (Jalali, nullable), referral_code (unique per tenant), referred_by_id, referred_at, staff_note, marketing_opt_in | Birthday is Jalali month/day: the gift is on the Jalali date and the year is often not given |
| `wallets` | customer_id (unique), balance (rial, signed) | Balance may go negative **only** through a reward clawback; a negative wallet can't pay |
| `wallet_transactions` | wallet_id, type, amount (signed), balance_after, order_id?, payment_id?, description, actor_type/actor_id, idempotency_key (unique per tenant) | Append-only. **Invariant:** balance = Σ amount = last balance_after |
| `loyalty_accounts` | customer_id (unique), points (signed), lifetime_spend (rial), tier_id?, tier_since | |
| `loyalty_transactions` | account_id, type, points (signed), balance_after, order_id?, description, actor, idempotency_key | Append-only, same invariant |
| `loyalty_tiers` | name, min_spend (rial), color, points_multiplier (basis points, 10000 = ×1), perks, sort | |
| `cashback_rules` | name, category_id? (null = whole order), min_spend, kind (fixed/percent), value, max_reward?, is_active | Rules add up (legacy parity) |
| `loyalty_order_awards` | order_id (unique), base_amount, points_full, cashback_full, spend_full, points_posted, cashback_posted, spend_posted, snapshot JSON | The award computed once at completion; refunds scale what is posted |

Scalar program settings (in `TenantSettingsRegistry`):
- `loyalty.enabled`
- `loyalty.points_per_100k`: points per 100,000 rial (10,000 toman)
- `loyalty.point_value`: rial value of one point
- `loyalty.min_redeem_points`
- `loyalty.birthday_wallet_gift`, `loyalty.birthday_points`
- `loyalty.referral_referrer_reward`, `loyalty.referral_referee_reward`
- `wallet.payments_enabled`

## 3. Rules
- **Ledgers:** every change goes through one action per ledger (`PostWalletTransaction`, `PostPointsTransaction`). Each one locks the account row, writes the transaction with `balance_after`, and uses an idempotency key (for example `cashback:{order}:{version}` or `birthday:{customer}:{jalali year}`). Balances are never set directly; staff adjustments are a delta with a mandatory reason, and they are audited.
- **Wallet payment:**
  - Endpoints: `POST /customer/orders/{order}/wallet-payment` (the customer's own order) or `POST /orders/{order}/wallet-payment` (staff; the order must have a customer).
  - The debit is min(balance, remaining due), recorded as a `Payment` with method `wallet` and a wallet transaction.
  - If that settles an order waiting for online payment, the order is released to the kitchen.
- **Wallet refunds:** a refund on a wallet payment credits the wallet (not cash). When an order is cancelled or rejected, its wallet payments are refunded to the wallet automatically. This replaces a hold/capture step with capture + automatic reversal: equivalent guarantees, one code path.
- **Earning (on `OrderCompleted`):**
  - Only for orders with a customer, while the program is enabled.
  - Base = total − net wallet-paid − refunded.
  - Points = floor(base / 100,000 × rate) × the tier multiplier.
  - Cashback = the sum of the matching rules. A category rule uses that category's line totals, and the order discount is spread across lines proportionally.
  - Lifetime spend += base, then the tier is recomputed upwards only.
  - Cashback is not earned on the wallet-paid part, so there is no reward loop.
- **Reversal:** after a refund on a completed order, the award is rescaled by the ratio net-paid now / base at completion. Delta transactions bring what was posted to the new target. Lifetime spend is reduced too, but the tier never drops automatically.
- **Redeem:** at least `min_redeem_points`; converts whole points to rial at `point_value`.
- **Referral:**
  - `POST /customer/referral {code}` is allowed once, within 7 days of sign-up, before the customer's first completed order, and never with their own code.
  - Both rewards are granted when the referred customer's **first order completes**. Legacy paid immediately, which was abusable.
- **Birthday:** `loyalty:birthdays` runs hourly.
  - For each tenant whose local time is past 09:00, customers whose Jalali birth month/day is today get the gift once per Jalali year (idempotency key), plus an SMS.
  - Esfand 30 birthdays are celebrated on Esfand 29 in non-leap years.
  - Customers can set their birthday once; staff can correct it.
- **Tier discounts:** a new discount rule type `tier`. Discounts defines a `CustomerTierLookup` contract with a null default binding; Loyalty binds the real one, so the dependency direction is kept.

## 4. API
- **Customer:**
  - `GET/PATCH /customer/profile`
  - `GET /customer/club` (wallet, points, tier + progress to next, referral code, program terms)
  - `GET /customer/wallet/transactions`, `GET /customer/points/transactions`
  - `POST /customer/points/redeem`, `POST /customer/referral`
  - `POST /customer/orders/{order}/wallet-payment`
- **Staff:**
  - `GET /customers` (search by name/phone, tier, birth month; cursor pagination)
  - `GET /customers/export` (CSV, UTF-8 BOM, Persian headers, Jalali dates)
  - `GET/PATCH /customers/{customer}`
  - `GET /customers/{customer}/wallet-transactions`, `GET /customers/{customer}/points-transactions`
  - `POST /customers/{customer}/wallet-adjustments`, `POST /customers/{customer}/points-adjustments`
  - `POST /orders/{order}/wallet-payment`
  - `GET/PUT /loyalty/program` (settings), CRUD `/loyalty/tiers`, CRUD `/loyalty/cashback-rules`
- **Permissions:**

  | Permission | Allows |
  |---|---|
  | `customers.view` | view customers |
  | `customers.manage` | edit customers |
  | `customers.export` | CSV export |
  | `wallet.adjust` | manual wallet/points adjustments |
  | `loyalty.manage` | program settings, tiers, cashback rules |

  Manager gets all five; cashier gets `customers.view`. The wallet payment uses `payments.record`.

## 5. Security
- Money correctness:
  - row locks on wallet and loyalty accounts;
  - integer rial end to end;
  - idempotency keys on every system-generated transaction;
  - append-only ledgers (updates are blocked in the models).
- Property-style tests: random operation sequences keep **balance = Σ transactions** and never let the wallet pay beyond its balance.
- Customers see only their own club data. Staff adjustments are audited with a reason.
- The CSV export is a separate permission (it contains phone numbers) and is audited.
- Isolation harness: every new endpoint, plus a check that tenant B's customer can't be adjusted or paid from through tenant A.

## 6. Tests
- Ledger invariants (randomised sequences).
- Idempotent postings.
- Wallet payment: partial and full, the pending_payment release, a negative balance blocked.
- An automatic wallet refund on cancel.
- Earning on completion: points, cashback, category rules, tier multiplier, tier upgrade, never downgrade.
- Proportional reversal after a partial or full refund.
- Redeem limits.
- Referral: window, self-code, reward on first completion only.
- Birthday: correct Jalali day, once per year, the Esfand 30 rule, tenant local time.
- Tier discount rule.
- Profile rules: birthday set once.
- CSV content.
- Permissions and isolation.

## 7. Risks
| Risk | Mitigation |
|---|---|
| Ledger drift (the legacy bug) | Single posting action + invariant tests + balance_after on every row |
| Reward loops / abuse | No cashback on the wallet-paid part; referral paid on first completion; birthday once per Jalali year and set once by the customer |
| Clawback after the customer spent the reward | A negative balance is allowed only for clawbacks and blocks wallet spending until it is covered |
| Scope size | Seven tested steps; storefront UI stays in Phase 7 |
