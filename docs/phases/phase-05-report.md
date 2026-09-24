# Phase 5 — Customer Club: Completion Report

**Date:** 2026-09-24 · **Plan:** `phase-05-plan.md` · **Status:** Implemented, awaiting approval

## 1. What was built

### Backend (`app/Modules/Loyalty`, plus `Customers`)
| Area | Delivered |
|---|---|
| Profiles (`Customers`) | Jalali birth month/day. The customer can set it once (it triggers a gift); staff can correct it. Staff note, marketing opt-in, a referral code (7 characters, no look-alike letters), referrer. `GET/PATCH /customer/profile` |
| Wallet ledger | `wallets` + append-only `wallet_transactions` (updates and deletes blocked in the model). **One posting action** (`PostWalletTransaction`) locks the wallet row, writes `balance_after` and updates the balance in one transaction; idempotency keys on every system posting. Overdrafts are refused, except for reward clawbacks, which may go negative. A negative wallet can't pay |
| Points ledger | `loyalty_accounts` + `loyalty_transactions`, with the same guarantees (`PostPointsTransaction`) |
| Wallet payments | `POST /customer/orders/{order}/wallet-payment` (own order only; anyone else's looks like a 404) and `POST /orders/{order}/wallet-payment` (staff, optional amount, idempotent). The payment is min(balance, remaining, requested). The rest can be paid online or at the counter. An online order fully covered by the wallet is released to the kitchen |
| Wallet refunds | A refund of a wallet payment always goes back into the wallet; `PaymentRefunded` is handled inside the refund transaction. **Cancelling or rejecting an order refunds its wallet payments automatically.** This replaces hold/capture with the same guarantees and one code path (plan §3) |
| Earning (D8) | On `OrderCompleted` only, for orders with a customer, while the club is on. Base = what was really paid and kept: unrecorded counter payments count, the wallet part and refunds don't. Points per 100,000 rial × the tier multiplier; cashback = the sum of all matching rules (whole order or category incl. subcategories, threshold, % or fixed, cap). The award is frozen per order (`loyalty_order_awards`) with a snapshot of the rules used |
| Reversal | After any refund the award is rescaled to what is still paid, and only the difference is posted, so it's idempotent and safe to re-run. Lifetime spend goes down too, but **the tier never drops automatically** (legacy rule) |
| Tiers | Spend-based and upward-only, with a points multiplier, colour and perks. A tier with members can't be deleted |
| Redeem | `POST /customer/points/redeem`: minimum points, converted at the point value. The points debit and the wallet credit are one transaction |
| Referral (D4) | `POST /customer/referral`: once, within 7 days of sign-up, before the first completed order, never with your own code. **Both rewards are paid when the referred customer's first order completes** (legacy paid at sign-up, which was abusable) |
| Birthday | `loyalty:birthdays` runs hourly. For each tenant, from 09:00 local time on the Jalali birthday: wallet gift and/or points + SMS, once per Jalali year (idempotency key). Esfand 30 birthdays are celebrated on Esfand 29 in common years. The gift stands even if the SMS fails |
| Tier discounts | New discount rule `tier`. Discounts defines a `CustomerTierLookup` contract with a null default; Loyalty binds the real one (dependency direction kept) |
| Staff API | Customer list (search by name or phone in any digits, tier and birth-month filters, club columns in one query), detail with club summary and recent orders, profile edit, both ledgers, manual wallet/points adjustments (a delta + reason, audited), **CSV export**, and program settings, tiers and cashback rules |
| CSV export | UTF-8 BOM (Excel shows Persian correctly), Persian headers, Jalali dates, toman, formula-injection guard, streamed in chunks, audited. It has its own permission because it contains phone numbers |
| Permissions & settings | `customers.view`, `customers.manage`, `customers.export`, `wallet.adjust`, `loyalty.manage`. Manager gets all five; cashier gets view. Nine program settings live in `TenantSettingsRegistry` |
| Demo data | Club on, three tiers (برنزی, نقره‌ای, طلایی — bronze, silver, gold), two cashback rules, birthday and referral rewards, three members with points and cashback history |

### Frontend (dashboard)
- **مشتریان (customers):** list with search, tier and birth-month filters; wallet, points and orders per row; CSV download (streamed through the Next server, so the token stays server-side).
- **Customer page:**
  - cards for wallet (with an explanation when it is negative), points and their value, tier with a progress bar to the next one, and lifetime spend + referral code;
  - profile form with a Jalali month/day picker (no native date input) and a staff note;
  - manual wallet and points adjustments;
  - both ledgers with the balance after each row;
  - recent orders.
- **باشگاه مشتریان (customer club):** program settings; tiers (add, edit, delete); cashback rules with a plain Persian summary («۵٪ کش‌بک برای کل سفارش از ۵۰۰٬۰۰۰ تومان به بالا», "5% cashback on the whole order from 500,000 toman up").
- **Order page:**
  - «پرداخت از کیف پول باشگاه» ("pay from club wallet") shows the balance and is prefilled with min(balance, remaining);
  - a wallet payment's refund goes to the wallet automatically;
  - the customer name links to their page.
- **Discounts:** optional «فقط برای سطح باشگاه» ("only for club tier"); other rules set through the API are kept on save.
- `@cafe/locale` gains `JALALI_MONTHS` and `jalaliMonthDays`. The idempotency-key hook moved to a shared component.

## 2. Verification

| Check | Result |
|---|---|
| `php artisan test` | **290 passed** (2514 assertions). New: wallet (9, including a 120-step randomised ledger-invariant test), rewards (9), referral + birthday + profile (6), staff customers + export + program (3) |
| Isolation harness | **86 tests**: every new endpoint, plus B's customer, tier, rule, wallet balance and CSV rows are unreachable from A |
| Larastan level 6 | **0 errors** · Pint: passed |
| Web | `tsc`, ESLint, `next build`: clean; `@cafe/locale` tests pass |
| Rendered pages (HTTP, logged in) | customers, filtered list, customer detail, club, discounts, orders: 200, Persian amounts, no `NaN`/`undefined`. The CSV download returns `text/csv` with the BOM, Persian headers and Jalali dates |

## 3. Security review
- **Money correctness:**
  - a single posting path per ledger;
  - row locks, taken in the order order → payment → wallet;
  - idempotency keys on every system posting;
  - append-only ledgers;
  - the invariant balance = Σ ledger = last `balance_after` is tested after randomised sequences.
- **Abuse:**
  - no cashback on money paid from the wallet;
  - referral rewards only after a real completed order;
  - the birthday can be set once by the customer and pays once per Jalali year;
  - a clawback makes the wallet negative instead of losing money.
- **Privacy:**
  - customers see only their own club;
  - the export is a separate, audited permission;
  - staff notes are never shown to the customer.
- **Bug found and fixed during testing:** the CSV body is streamed after the tenant middleware has finished, so the stream now re-enters the tenant context explicitly. It would have failed in production.

## 4. Known gaps / notes
1. **Weekly challenges, badges and item likes** are V2 extension points (D4/D5).
2. **Bulk SMS campaigns** belong to the Notifications phase. Birthday SMS goes through the platform SMS provider.
3. **The customer-facing club UI** (wallet, points, redeem, referral entry) comes with the storefront (Phase 7). The API is complete.
4. **The category cashback share is approximate for item-level discounts:** the order discount is spread over lines in proportion to their value. Exact for order-level discounts.
5. **The scheduler must run** for `loyalty:birthdays` (hourly) and `payments:reconcile` (every minute).
6. **Incident during this phase:** the session ended mid-write and `TenantIsolationTest.php` was left zero-filled. It was rebuilt byte-for-byte by replaying the recorded edits, confirmed against the Phase 4 result (69 tests, 567 assertions) before the Phase 5 cases were added, and a full-repo scan found no other damaged file. The project has no git repository yet; **I recommend `git init` + a first commit** so this can't cost work again.

## 5. Try it
```bash
cd apps/api && php artisan migrate:fresh --seed && php artisan serve --port=8765
cd apps/web && npm run dev -- -p 3765     # login 09120000001 / password → «مشتریان» (Customers), «باشگاه مشتریان» (Customer club)
php artisan loyalty:birthdays            # or: php artisan schedule:work
```
