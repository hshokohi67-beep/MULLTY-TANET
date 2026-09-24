# Phase 0 — Discovery Report: Legacy Analysis, Feature Mapping, Migration Map, Roadmap

**Status:** Draft for approval · **Date:** 2026-09-24 · **Scope:** Analysis only. No production code has been written.

**Sources analysed:** `legacy/live-cafe-menu` (v4.1, ~6,000 lines) and `legacy/lcm-customer-club` (v1.0.0, DB schema 1.4, ~2,850 lines). See `legacy/README.md` for how the snapshot was assembled. Every claim below cites a file under `legacy/`.

---

## 0. Executive summary

1. **The legacy system is not two standalone plugins. It is a WooCommerce storefront with two plugins layered on top.** WooCommerce provides the products, variations, categories, stock, orders, order statuses, checkout, payment gateways and `total_spent`. The plugins add a QR/table menu UI, a KDS, a wallet, loyalty, discount groups, SMS and Web Push. **Migration must read WooCommerce data (posts/postmeta, or HPOS `wc_orders`) and WordPress users, not only the plugins' custom tables.**
2. **Single-tenant, single-branch, and in practice local-only.** Both plugins stop loading unless the host is `dentall-clinic.local` (`live-cafe-menu.php:14-27`, `lcm-customer-club.php:16-29`). There may be **no production data at all**. This changes how much migration tooling is worth building (see §F, decision D1).
3. **Customer identity is a raw phone string typed by the user.** The client keeps it in `localStorage` and in a JS-set cookie, and **many server endpoints trust a `phone` value in the POST body without authentication.** That is a critical IDOR class of bug (§D.4). The new system must not reproduce it.
4. **Wallet balances do not reconcile with the wallet ledger.** Several code paths change `wallet_balance` without writing a ledger row (§A.2.10). Migration needs an opening-balance reconciliation entry.
5. **The business rules are worth keeping:** a wallet that can pay part of an order with the rest paid online or at the counter, category-threshold cashback, spend-based tier upgrades that never auto-downgrade, a points→wallet conversion, a Jalali birthday gift once per Jalali year, pre-orders when the cafe is closed, a daily "lucky item", a waiter call, 24-hour rating/like windows, and Persian ی/ک search normalisation.
6. **The master schema is missing several things the legacy system uses:** customer tiers/segments, order ratings and product likes, reservations, referrals, branch opening hours, product nutrition/diet attributes, OTP storage, and SMS provider configuration. A few legacy features (badges, weekly challenges, referral) are listed as V2 in the master prompt but are live in the legacy code. §D and §F list the decisions needed.
7. **Two phase-order conflicts need resolving:** (a) customer OTP/SMS is needed at checkout (Phase 3), but Notifications is not scheduled until later; (b) the Definition of Done requires UI in every phase, but the Tenant Dashboard is Phase 8. §E proposes a fix for both.

---

## A. Legacy Feature Inventory

### A.1 Plugin 1 — Live Cafe Menu (`legacy/live-cafe-menu`)

#### A.1.1 Features

| # | Feature | Where | Notes |
|---|---|---|---|
| 1 | Mobile menu page `/live-menu/` (RTL, dark/light theme, Vazirmatn) | `public/menu-template.php`, `public/menu-grid-partial.php` | Full-page template overriding the WP theme. Category tabs switch over AJAX, with history push/pop. |
| 2 | Product cards | `menu-grid-partial.php` | Image, short description, price, **group-discounted price**, variations shown as sizes, **upsell products shown as add-ons**, calories (`_lcm_product_calory` post meta), diet badges from WC product-tag slugs (`gluten-free, vegan, dairy-free, sugar-free, low-calorie, spicy, high-protein`), out-of-stock overlay, low-stock badge (≤5), best-seller badge (≥3 orders), "liked by N" badge (≥3 distinct phones). |
| 3 | Fallback add-on suggestions | `menu-grid-partial.php:227-235` | If a product has no upsells, 3 add-ons are picked by **regex on product titles** (hot drinks: chocolate/syrup/espresso/milk/cream; cold drinks: ice/ice-cream/mint/lemon/jelly). |
| 4 | Search overlay | `live-cafe-menu.php:1110-1165` | Direct SQL over title/content/excerpt, normalising `ي→ی, ك→ک, ة→ه, ۀ→ه`. Minimum 2 characters, 15 results. |
| 5 | Filter panel | `menu-template.php:1340-1360` | Client-side filters: max calories, diet tags. Tenant setting `lcm_max_calories`. |
| 6 | Client-side cart | `menu-template.php` (`$globalCartQueue`, `localStorage.lcm_live_cart_hub`) | Floating cart drawer, order type selector (salon/takeaway), order note (≤200 chars). |
| 7 | **Shared table cart** | `lcm_sync_table_cart` (`live-cafe-menu.php:551-593`) | Transient `lcm_table_cart_{table_id}` with a 12h TTL. Every device at the table reads and writes the same cart. |
| 8 | QR table entry | `?table_id=N` → PHP session + cookie `lcm_table_id` (`:253-261`) | QR images come from **api.qrserver.com** (third-party). Table count comes from option `lcm_table_count` (1..N). |
| 9 | Checkout → WooCommerce order | `lcm_add_custom_product_to_cart` (`:294-549`) | Creates the WC order directly (not through the WC cart). Details in A.1.9. |
| 10 | Payment modal | `menu-template.php:1040-1145, 1944-2170` | Online / club wallet / pay at counter ("salon"). If the wallet is short, the customer chooses how to pay the remainder: online or at the counter. |
| 11 | Business hours + pre-order | `lcm_is_cafe_currently_open`, `lcm_get_next_reopen_text` (`:613-664`) | Per-weekday open/close/closed. When closed, the customer must pick a delivery time `HH:MM`, stored as `_lcm_requested_time`. Overnight hours (close < open) are **not supported**. The admin UI orders days Saturday-first. |
| 12 | Daily lucky item | `lcm_get_daily_lucky_item` (`:670-714`) | Deterministic `crc32(Y-m-d) % count(products)`, X% off until a cutoff hour, cached until midnight. Does not stack with the group discount (the higher of the two wins). |
| 13 | Best sellers | `lcm_get_top_products` (`:721-755`) | Queries both `wc_orders` (HPOS) and `posts` storage. Cached 6h, invalidated on processing/completed. |
| 14 | Order tracking page (WC checkout / order-received override) | `public/checkout-template.php` | Requires the order key. Polls `lcm_get_order_status` every 8s. Stepper: registered → preparing → ready. Shows the wallet deduction and the remainder. |
| 15 | Web Push "order ready" | `includes/lcm-push-helpers.php`, SW at `/lcm-push-sw.js` | Hand-written RFC 8291/8292 (VAPID, aes128gcm) with no library. The file itself says it was never tested against a real push service. Triggered on `woocommerce_order_status_completed`. |
| 16 | PWA manifest | `/lcm-manifest.json` (`:136-172`) | Per-site name, logo and theme colour; `dir: rtl`, `lang: fa`. |
| 17 | KDS `/kitchen-display/` | `public/kds-template.php`, `:1297-1481` | One shared PIN (default **`1234`**) unlocks a PHP session. Polls `processing` orders FIFO (limit 40). Shows the card, item grouping (`×2`), table/takeaway, customer name + **tier label**, note, requested time. Card turns late after `lcm_kds_timer_minutes` (default 7). Buttons: preparing → ready. **Ready sets the WC order to `completed`**, which fires push + email. Beep via WebAudio. No stations. |
| 18 | Call waiter | `lcm_call_waiter` / `lcm_get_waiter_calls` / `lcm_ack_waiter_call` (`:1336-1385`) | Transient map `table_id → created_ts` (30 min), 60s cooldown per table, acknowledged from the KDS. |
| 19 | Table reservation `/reservation/` | `public/reservation-template.php`, `lcm_submit_reservation` (`:1245-1295`), admin list | Name, phone, guests (1–50), Gregorian date + time, note. Statuses `pending → confirmed → completed / cancelled`. Admin is emailed. 60s cooldown per phone. |
| 20 | Customer panel (in the menu) | `lcm_ajax_get_user_panel_data` (`:795-1097`) | Name, wallet, last 8 ledger rows, last 5 orders with **reorder**, **1–5 star rating** and **per-item like** (24h window from order creation), referral code, saved city/address, Jalali birthday countdown, tier progress bar, group discount banner, badges, points + redeem, weekly challenge progress, **30-day personal analytics** (order count, spend, 4-week histogram, "caffeine estimate" = coffee items × 80 mg). |
| 21 | Profile edit | `lcm_ajax_update_user_profile` (`:1603-1625`) | Name, city, address (a single address in WP user meta `billing_city`, `billing_address_1`). |
| 22 | Admin dashboard | `admin/lcm-admin-panel.php:97-337` | Today's sales/orders/takeaway count/most-used table, last 8 orders, branding + settings form. |
| 23 | WC checkout simplification | `lcm_simplify_checkout_fields` (`:1666-1688`) | Last name, postcode and email optional; company/country removed; address required only for takeaway. |
| 24 | "Fitness product" detection | `lcm_backend_is_product_fitness` (`:281-288`) | Regex on the product name and categories. **Dead code** (not called). |

#### A.1.2 Screens

Customer: menu `/live-menu/` (with cart drawer, payment modal, account drawer with 2 tabs, search overlay, filter overlay), order tracking (WC order-received), reservation `/reservation/`.
Staff: KDS `/kitchen-display/` (PIN gate).
Admin (wp-admin, `manage_options`): Dashboard + general settings, KDS settings, Table QR codes, Reservations.

#### A.1.3 Database

| Table | Columns | Created by |
|---|---|---|
| `{prefix}lcm_push_subscriptions` | `id, phone varchar(50), endpoint varchar(500), p256dh, auth, created_at`; KEY phone | `lcm_create_push_table` (`:40-57`) |
| `{prefix}lcm_reservations` | `id, name, phone, guests tinyint, reserved_at datetime, note varchar(500), status varchar(20) default 'pending', created_at`; KEY status, reserved_at | `lcm_create_reservations_table` (`:64-84`) |

The menu plugin also **reads and writes the Customer Club tables** `lcm_club_members`, `lcm_wallet_ledger`, `lcm_order_ratings`, `lcm_liked_items`, and **all WooCommerce tables**. Pages created on activation: `live-menu`, `kitchen-display`, `reservation`.

**WooCommerce order meta written:** `_lcm_order_type` (`salon|takeaway`), `_lcm_table_id` (int **or** the literal strings `'بیرون‌بر 🛍️'` / `'سالن'`), `_lcm_payment_method` (`online|آنلاین|wallet|کیف پول باشگاه|salon`), `_lcm_remaining_amount`, `_lcm_remaining_payment_method` (`online|salon`), `_lcm_payment_type` (`partial_wallet|full_wallet`), `_payment_method_title`, `_lcm_wallet_deducted`, `_lcm_kitchen_note`, `_lcm_requested_time` (`HH:MM`), `_lcm_kds_status` (`new|preparing|ready`), `_lcm_cashback_given` (`yes`). WC order notes (the comments table) carry human-readable wallet, address and pre-order notes.
**Product data used:** WC `_price`, variations, upsell IDs (add-ons), `product_cat`, `product_tag` (diet), stock status/qty, featured image, post meta `_lcm_product_calory` (**no admin UI sets this**; it is a hidden dependency).
**User meta used:** `billing_city`, `billing_address_1`. WP users are created with `login = phone`, `email = {phone}@example.com`, role `subscriber`.

#### A.1.4 Settings (wp_options)

`lcm_cafe_name`, `lcm_cafe_logo` (URL), `lcm_menu_theme` (`dark|light`), `lcm_max_calories`, `lcm_table_count`, `lcm_business_hours` (array[0..6] of `{open, close, closed}`, index = PHP `date('w')`, 0 = Sunday), `lcm_lucky_item_enabled|_discount|_cutoff_hour`, `lcm_kds_pin` (**plaintext**), `lcm_kds_timer_minutes`, `lcm_vapid_keys` (**EC private key PEM in plaintext**), `lcm_push_db_version`, `lcm_reservations_db_version`.
Transients: `lcm_table_cart_{id}`, `lcm_active_waiter_calls`, `lcm_waiter_cooldown_{id}`, `lcm_order_rate_{md5 ip}`, `lcm_kds_pin_fails_{md5 ip}`, `lcm_reservation_cooldown_{md5 phone}`, `lcm_lucky_item_{date}`, `lcm_top_products_cache`, `lcm_radar_latest_orders`, `lcm_kds_orders_cache`.
Session/cookies: PHP session (`lcm_table_id`, `lcm_order_type`, `lcm_kds_unlocked`); cookie `lcm_table_id` (HttpOnly, 30d); cookie `lcm_user_phone` (set by JS, **not HttpOnly**, 180d); cookie `lcm_user_group` (still **read** server-side, no longer set).

#### A.1.5 Hooks

Actions: `init` (SW intercept at priority 0, rewrite rule, session start at priority 1), `template_redirect` (manifest), `plugins_loaded` (DB upgrades), `admin_menu`, `admin_init`, `admin_enqueue_scripts`, `woocommerce_checkout_create_order` (table id → order meta), `woocommerce_admin_order_data_after_billing_address`, `woocommerce_order_status_completed` (push, cache clear), `woocommerce_order_status_processing` (cache clear), `woocommerce_order_status_changed` (cache clear). Filters: `query_vars`, `page_template`, `template_include` (priority 99, checkout override), `woocommerce_checkout_fields`. Activation hooks create the tables and pages.

#### A.1.6 AJAX endpoints (`admin-ajax.php`, all registered for guests and logged-in users)

| Action | Auth / CSRF | Rate limit | Effect |
|---|---|---|---|
| `lcm_add_custom_product_to_cart` | nonce `lcm_public_actions` | 10 / 5 min / IP | Creates the order, **deducts the wallet of the phone in POST** |
| `lcm_sync_table_cart` | nonce | — | Reads or overwrites **any** table's cart |
| `lcm_get_user_panel_data` | **none**; phone from WP login **or POST** | — | Returns wallet, orders, address, referral code, etc. for **any phone** |
| `lcm_update_user_profile` | **none** | — | Renames any member and overwrites their address |
| `lcm_rate_order`, `lcm_toggle_item_like` | phone must equal the order's billing phone (the phone is guessable) | — | Rating/like |
| `lcm_save_push_subscription` | **none** | — | Binds any browser to any phone |
| `lcm_search_products`, `lcm_get_menu_grid`, `lcm_get_products_basic_info` | none (public data) | — | Catalog read. `basic_info` applies the group discount from the **`lcm_user_phone` cookie** |
| `lcm_submit_reservation` | none | 60s / phone | Creates a reservation, emails admin |
| `lcm_call_waiter` | nonce | 60s / table | Adds a waiter call |
| `lcm_kds_check_pin` | — | 10 fails / 15 min / IP | Unlocks the KDS session |
| `lcm_kds_get_orders`, `lcm_kds_update_status`, `lcm_get_waiter_calls`, `lcm_ack_waiter_call` | KDS session flag, **no nonce** | — | KDS |
| `lcm_get_order_status` | order id + order key | — | Tracking |

Non-AJAX endpoints: `/lcm-push-sw.js`, `/lcm-manifest.json`, `?table_id=`.
**REST routes: none. Shortcodes: none. Custom roles/capabilities: none** (admin screens use `manage_options`; the KDS uses a shared PIN).

#### A.1.7 Integrations

WooCommerce (hard dependency), browser Web Push services (FCM/Mozilla via VAPID), `api.qrserver.com` (QR images), `wp_mail` (reservation email; WC order emails). CDN assets: `unpkg.com/lucide@latest` (**unpinned**), jsDelivr Vazirmatn v33.003, cdnjs lottie 5.12.2, one flaticon PNG. **There is no Iranian payment gateway integration.** "Online" payment hands off to whatever WC gateway is installed (`live-cafe-menu.php:509-518`, `menu-template.php:1702-1703`).

#### A.1.8 Security mechanisms present

Nonces on order/cart/waiter; transient rate limits (IP/phone/table); `hash_equals` for the PIN; PIN brute-force lockout; order-key check for tracking; `$wpdb->prepare` used consistently; `sanitize_*` on inputs; output mostly escaped; `try/catch` around order side effects so SMTP failures don't break ordering; order note length cap; `requested_time` regex; server-side price lookup (the client price is not trusted when creating an order).

#### A.1.9 Order logic (the rules that must be preserved)

1. **Cart flattening:** the whole client cart is sent as `main_id` = the first item (variation id if one was chosen) plus a comma list `addons` = every other item, its variation, and every add-on (`menu-template.php:2100-2124`). **Quantities become repeated line items (qty 1 each), and add-on→parent links are lost.**
2. The price of each line is the WC price minus the **best** of (customer group discount if the product matches the group's categories/products, lucky-item discount). Discounts do not stack.
3. Order type is `salon` (dine-in / at table) or `takeaway`. **Takeaway attaches the member's saved address with the note "ارسال به این آدرس" ("send to this address"), so "takeaway" in practice also covers delivery.**
4. `_lcm_table_id` = table number, `'سالن'` (salon without a table), or `'بیرون‌بر 🛍️'` (takeaway).
5. The **wallet is deducted at order creation**: `min(balance, total)`, and the remainder is paid either `online` or `salon`.
6. Status: pay at counter → `processing`; wallet fully covers the order, or wallet + counter → `processing`; anything with an online part → `pending` and redirect to the WC payment URL.
7. `processing` = "sent to kitchen". The KDS only shows `processing`. KDS "ready" → `completed`.
8. On `processing` (Customer Club): cashback, a wallet deduction if not already done, tier upgrade, challenge claim. On `completed`: tier upgrade, challenge claim, push.
9. Pre-order when closed: `requested_time`, no date (assumed to be the next opening).

### A.2 Plugin 2 — LCM Customer Club (`legacy/lcm-customer-club`)

#### A.2.1 Features

| # | Feature | Where | Notes |
|---|---|---|---|
| 1 | Sign-up / login popup with OTP | `public/views/view-popup.php`, `assets/js/public-script.js`, `LCM_Ajax::save_member` | Shown on pages listed in `lcm_target_pages`, or with `?lcm_test=1`. Fields: phone, OTP, name, Jalali birth day/month, referral code. |
| 2 | OTP via Trez.ir SOAP | `class-lcm-ajax.php:278-332` | `AutoSendCode` / `CheckSendCode` (Trez generates the code). Falls back to a local 4-digit code sent by plain SMS. |
| 3 | WP login after OTP | `lcm_create_wp_user` (`lcm-customer-club.php:126-150`) | One-shot `lcm_otp_verified_{phone}` flag (2 min). Creates a WP user and sets the auth cookie. |
| 4 | Logout | `lcm_quick_logout` | `wp_logout()` + clears localStorage |
| 5 | Members (leads) | table `lcm_club_members`, admin "Leads" tab | Filter by group and Jalali birth month, **CSV export** (client-side), **birthday within 7 days** highlight, manual group change. |
| 6 | Dynamic discount groups | `includes/class-lcm-discounts.php` | Unlimited groups `{slug, label, color, percent, min_spend, categories[], products[]}`. One-time migration from the old 3 fixed groups (bodybuilder/diet/normal). |
| 7 | **Tiers (auto-upgrade)** | `lcm_maybe_auto_upgrade_group` | A group with `min_spend > 0` is a tier. On processing/completed, lifetime WC `total_spent` ≥ threshold → upgrade. **Never auto-downgrades.** New members start at the lowest tier (or the first group). |
| 8 | Wallet | column `wallet_balance`, table `lcm_wallet_ledger` | Credits: cashback, referral, birthday gift, points conversion, admin edit. Debits: order payment. |
| 9 | Cashback engine | `LCM_Ajax::add_cashback_on_order` | Rules `[{category_slug, min_spend, reward}]`. **Per order**, the order's spend in that category (summed across items) ≥ `min_spend` → a fixed `reward` goes to the wallet. Multiple rules add up. Idempotent through `_lcm_cashback_given`. |
| 10 | Loyalty points | column `loyalty_points`, table `lcm_points_ledger` | **Only earned from weekly challenges.** Redeem converts all whole units to the wallet: every `lcm_points_conversion_rate` points (default 10) = 1,000 toman. |
| 11 | Weekly challenge | `class-lcm-engagement.php:104-174` | A single challenge `{label, category_slug, target_count, points_reward}`. Progress = this week's (since Monday) orders containing ≥1 item of that category. Claimed once per ISO week (`lcm_challenge_claims`). |
| 12 | Badges | `lcm_compute_user_badges` | Computed on each view, never stored. 9 badges using hard-coded category slugs and hours (early <9, night ≥21), plus rating and referral counts. |
| 13 | Referral | `lcm_generate_referral_code` | Code `CAFE` + `rand(1000,9999)`. The new member and the referrer **both** get `lcm_referral_reward` in their wallet. |
| 14 | Birthday gift | WP-Cron `lcm_daily_birthday_check_hook` at 09:00 daily | Matches the **Jalali** day/month. Adds a wallet gift + push + SMS once per Jalali year (flag option `lcm_birthday_sent_{phone}_{jy}`). |
| 15 | Bulk SMS campaign | `handle_bulk_sms_sending` | To everyone or to one group, in a single Trez request. |
| 16 | Direct message | `send_direct_message` | Push and/or SMS to one member. Autocomplete search. |
| 17 | Manual wallet edit | `lcm_update_wallet_balance` | **Sets an absolute balance. No ledger row.** |
| 18 | Satisfaction tab | admin view | Average rating, last 100 ratings, last 100 likes. |

#### A.2.2 Screens

Customer: OTP popup (on the configured pages). Everything else for the customer lives in the menu plugin's account drawer.
Admin: one page with 9 tabs: General (referral reward, Trez credentials, target pages) · Discount groups · Challenge & points (challenge, conversion rate, birthday gift) · Cashback rules · Leads & birthdays (+CSV) · SMS campaign · Wallet management · Satisfaction · Direct message.

#### A.2.3 Database (`class-lcm-db.php`, DB_VERSION 1.4)

| Table | Columns | Keys |
|---|---|---|
| `lcm_club_members` | `id mediumint, name, phone varchar(50), birth_day tinyint (Jalali), birth_month tinyint (Jalali), user_group varchar(100), wallet_balance bigint, loyalty_points bigint, avatar varchar(10), saved_address varchar(500), referral_code varchar(20), referred_by_code varchar(20), created_at` | UNIQUE phone, UNIQUE referral_code |
| `lcm_wallet_ledger` | `id, phone, amount bigint (+/-), balance_after bigint, reason varchar(255), created_at` | KEY phone |
| `lcm_order_ratings` | `id, order_id (WC), phone, stars 1-5, created_at, updated_at` | UNIQUE order_id |
| `lcm_liked_items` | `id, order_id (WC), product_id (WC), phone, created_at` | UNIQUE (order_id, product_id) |
| `lcm_points_ledger` | same shape as the wallet ledger | KEY phone |
| `lcm_challenge_claims` | `id, phone, week_key ('2026-W29'), claimed_at` | UNIQUE (phone, week_key) |

There are no foreign keys. **`phone` is the join key everywhere.** `avatar` and `saved_address` are declared but unused (the address actually lives in WP user meta).

#### A.2.4 Settings

`lcm_referral_reward`, `lcm_target_pages[]`, `lcm_sms_api_key` (**actually the Trez username**), `lcm_sms_sender_num` (**actually the Trez password, in plaintext**), `lcm_sms_dedicated_number` (sender line), `lcm_discount_groups`, `lcm_weekly_challenge`, `lcm_points_conversion_rate`, `lcm_birthday_gift_enabled`, `lcm_birthday_gift_amount`, `lcm_cashback_multi_rules`. Superseded but still registered: `lcm_cashback_cat`, `lcm_cashback_min_spend`, `lcm_cashback_reward`, `lcm_fitness_discount`, `lcm_diet_discount`, `lcm_normal_discount`. Bookkeeping: `lcm_club_db_version`, one `lcm_birthday_sent_*` option per member per year.
Transients: `lcm_otp_{phone}` (2 min), `lcm_otp_cooldown_{phone}` (45s), `lcm_otp_daily_{phone}` (5/day), `lcm_otp_verified_{phone}` (2 min).

#### A.2.5 Hooks

`plugins_loaded` (DB upgrade), `wp_enqueue_scripts` (popup assets; injects `lcm_ajax_object`), `wp_footer` (popup), `admin_menu`, `admin_init`, `init` (schedules cron), cron `lcm_daily_birthday_check_hook`, deactivation (clears cron), `woocommerce_order_status_processing` → cashback, wallet deduction, tier; `woocommerce_order_status_completed` → tier + challenge.

#### A.2.6 AJAX endpoints

| Action | Auth | Effect |
|---|---|---|
| `lcm_save_club_member` (guest) | OTP | Request/verify OTP; register; referral rewards |
| `lcm_create_wp_user` (guest) | one-shot verified flag | Create/login WP user |
| `lcm_quick_logout` | — | Logout |
| `lcm_get_live_wallet` (guest) | **none** (phone in POST) | Balance + discount of **any phone** |
| `lcm_redeem_points_to_wallet` (guest) | **none** (phone in POST) | Converts **anyone's** points |
| `lcm_send_bulk_sms`, `lcm_update_wallet_balance`, `lcm_admin_set_member_group`, `lcm_send_direct_message`, `lcm_search_members_for_dm` | `manage_options` + nonce `lcm_admin_actions` | Admin |

REST: none. Shortcodes: none. Roles: none (uses WP `subscriber` for customers and `manage_options` for admin).

#### A.2.7 Integrations

Trez.ir SOAP (**over plain `http://`**): `FastSend.asmx` for OTP, `trezsmswebservice.asmx` for text/bulk. Requires the PHP SOAP extension. Web Push through the menu plugin's helpers. WooCommerce for `total_spent` and order data.

#### A.2.8 Security mechanisms present

OTP cooldown of 45s and 5/day per phone; a one-shot "verified" flag before login (added to fix an earlier account-takeover bug); admin nonces + capability checks; group is never taken from client input at sign-up (fixes an earlier privilege bug); cashback idempotency flag; the DB version upgrade path.

#### A.2.9 Customer logic

Identity = the phone string exactly as typed (**no normalisation**; `۰۹۱۲…`, `+98…` and `0912…` become different members). Profile = name + Jalali birth day/month + group + referral. Address = one free-text city + address in WP user meta. The "lifestyle" column in admin is the discount group.

#### A.2.10 Wallet logic (including defects)

- Unit: **toman, integer** (`bigint`), displayed with `number_format` + "تومان". Order totals are floats.
- Ledger rows are written for cashback, referral, birthday, points conversion, and the Club's own `deduct_wallet_on_order`.
- **No ledger row is written** for: (a) the menu plugin's deduction at order creation (`live-cafe-menu.php:459-507`), which is the **main** debit path; (b) admin manual edits (`class-lcm-admin.php:219-235`). **The ledger therefore cannot reconstruct the balance.**
- No refund on cancellation/failed payment: the wallet is debited when a `pending` online-remainder order is created and is never credited back if that order is abandoned.
- Read-modify-write updates without transactions or row locks, so concurrent requests can lose updates.
- The Club's `deduct_wallet_on_order` is effectively dead for orders from the menu (the `_lcm_wallet_deducted` guard), but would run for WC-checkout orders with `_lcm_payment_method = wallet`.

#### A.2.11 Loyalty logic (including defects)

- Cashback is granted on `processing`, i.e. at creation for counter-paid orders, **before money is collected**, and is **never reversed** on cancel/refund.
- The tier uses lifetime `total_spent` (WC), which includes processing orders. There is no downgrade.
- Points come only from challenges. The conversion is all-or-nothing in whole units.
- The badge "birthday order" compares the **Jalali** birth day/month with **Gregorian** order dates (a bug).
- The week key uses server `date()` (probably UTC) while progress uses "monday this week" (server tz). This drifts from Iran-local weeks (Iranian weeks start on Saturday).
- The days-until-birthday calculation assumes 30-day months (menu) and a hand-rolled Jalali conversion (admin); the two differ.

---

## B. Feature Mapping (legacy → new SaaS module)

Legend: **V1** = implement in V1 · **V1-min** = simplified V1 · **EXT** = extension point only (V2) · **DROP** = not carried forward · **?** = needs a decision (§F).

| Legacy feature | New module(s) | New tables | Plan |
|---|---|---|---|
| WC products, categories, variations (sizes) | Catalog | products, categories, product_categories, product_variants, product_prices, product_images | V1 |
| WC upsells used as add-ons | Catalog | modifier_groups, modifiers, product_modifier_groups | V1: convert to a modifier group "افزودنی‌ها" ("add-ons") per product. See D.1.6 |
| Fallback add-on regex | — | — | DROP (replace with explicit modifier groups + smart defaults in the Quick Add UI) |
| Stock status / low-stock badge | Catalog (availability) + Inventory | product_availability, inventory_stock | V1: availability flag; stock-driven availability in Phase 9 |
| Calories, diet tags, max-calorie filter | Catalog | **gap**: nutrition/diet attributes | ? Propose `products.nutrition` JSON + `products.dietary_tags` JSON (no new table) |
| Search with ی/ک normalisation | Catalog (Search service) | `products.search_text` normalised column | V1 (a master requirement anyway; extend with digits, ZWNJ, whitespace) |
| Best-seller badge | Analytics → Catalog | product_sales_metrics | V1 (from aggregates, not live queries) |
| "Liked by N" badge, per-item like | Customers/Feedback | **gap** | ? |
| Order rating (1–5, 24h edit window) | Commerce/Feedback | **gap** | ? |
| Daily lucky item | Discounts | discounts (+ rotation generator) | ? V1 as a discount "type: daily_rotation", or EXT |
| Customer-group % discount (by categories/products) | Discounts + Customers | discounts, discount_rules; **gap**: customer groups/tiers | V1 (customer-specific discount is already in the master list) |
| Client cart / shared table cart | Commerce + Tables/QR | carts, cart_items, order_sessions | V1: server-side cart per session; a table session holds several carts (the master allows separate orders per table) |
| QR `?table_id=N` | Tables/QR | restaurant_tables, table_qr_codes, order_sessions | V1 with **opaque rotating tokens**, generated server-side (no third-party QR API) |
| Call waiter | Tables/QR + KDS realtime | table_session_requests | V1 |
| Order creation (salon/takeaway) | Commerce | orders, order_items, order_item_modifiers, order_status_history | V1: `dine_in`/`qr_table`/`takeaway`/`delivery`/… + `order_source` |
| Takeaway-with-address | Commerce + Delivery | customer_addresses, delivery_zones, orders.address_snapshot | V1: becomes a real `delivery` type with a zone check |
| Order note, requested time (pre-order) | Commerce | orders.customer_note, orders.scheduled_for (datetime) | V1 (fixes the date-less pre-order) |
| Business hours | Core (Branches) | **gap**: branch opening hours | V1: `branches.opening_hours` JSON **or** `branch_opening_hours` table (support overnight + holidays) |
| Wallet partial payment + remainder online/counter | Payments + Wallet | payments (multiple per order), wallets, wallet_transactions | V1: one order → N payments (wallet + gateway/cash) |
| "Online" via WC gateway | Payments | payment_gateways, tenant_payment_methods, payment_transactions | V1: adapter architecture (Zarinpal first) |
| Order tracking page + polling | Storefront + Commerce | order_status_history | V1 (realtime via Reverb, polling fallback) |
| Web Push (VAPID) | Notifications | customer_devices, notifications, notification_templates | V1-min (use a maintained library; per-tenant VAPID keys) |
| PWA manifest per cafe | Storefront + Branding | tenant_branding | V1 |
| KDS (PIN, FIFO, late timer, preparing/ready) | KDS | kitchen_stations, kitchen_station_products, kitchen_order_items, kitchen_events | V1 + stations. Replace the shared PIN with a staff login or per-device pairing |
| Tier label on the KDS card | KDS + Loyalty | — | V1 (read-only projection) |
| Reservations | **gap** (master: "no full reservation management in V1") | — | ? V1-min request form, or EXT |
| Admin daily dashboard | Analytics + Dashboard | daily_sales_metrics, hourly_sales_metrics | V1 |
| Branding settings (name, logo, theme) | Core | tenant_branding, tenant_settings | V1 |
| Domain licensing | Billing/Tenancy | subscriptions, tenant_domains | DROP (replaced by subscriptions + custom domains) |
| OTP login (Trez) | Identity + Notifications | **gap**: OTP store (Redis, no table) · SMS provider config (tenant_settings encrypted) | V1: `SmsProviderInterface` (Kavenegar/Melipayamak/IPPanel/Trez adapters) |
| WP user per customer | Identity | — | DROP. Customers are tenant-scoped records, not platform users |
| Club members | Customers | customers, customer_preferences | V1 |
| Birthday (Jalali d/m) + countdown | Customers | customers.birth_month/day (Jalali) or birth_date | V1 (decision on storage, §D.2.4) |
| Birthday gift cron | Loyalty (+ scheduler) | loyalty_rules (trigger=birthday), wallet_transactions | V1 |
| Discount groups as tiers | Loyalty | **gap**: loyalty tiers | V1: tiers are separate from discounts; a tier *may* grant a discount |
| Cashback (category threshold → wallet) | Loyalty engine | loyalty_rules, loyalty_transactions, wallet_transactions | V1 (with reversal on refund) |
| Points ledger + redeem to wallet | Loyalty + Wallet | loyalty_accounts, loyalty_transactions | V1 |
| Weekly challenge | Loyalty | — | EXT per master ("challenges" = advanced) → ? |
| Badges | Loyalty | — | EXT per master → ? |
| Referral (both rewarded) | Customers + Loyalty | **gap** | EXT per master ("advanced referral") → ? basic referral in V1? |
| Bulk SMS / direct message | Notifications (+ campaigns) | notifications, notification_templates, campaigns, campaign_targets | V1-min |
| Leads list, filters, CSV export | Customers + Dashboard | — | V1 (Excel/CSV with correct Persian/RTL) |
| Manual wallet adjust | Wallet | wallet_transactions (type=adjustment, actor, reason) + audit_logs | V1 (delta with a reason, never an absolute set) |
| Satisfaction tab | Analytics/Feedback | depends on ratings decision | ? |
| Personal 30-day analytics / caffeine estimate | Storefront account | customer_metrics | EXT (fun, not core) |
| Reorder from history | Storefront + Commerce | — | V1 (cheap, high value) |
| Checkout field simplification | Storefront checkout | — | V1 (native minimal checkout) |
| Email on reservation | Notifications | — | Follows the reservation decision |

---

## C. Data Migration Map

**Principles.** Migration runs **per tenant**. It is idempotent and re-runnable (`legacy_id_map(tenant_id, source, source_table, source_id, target_table, target_id)`, stored in the importer's own schema and dropped after cut-over). It has a dry-run mode with a reconciliation report. Money is converted with the central Money layer. All timestamps are converted to UTC from the WordPress `timezone_string` (default `Asia/Tehran`). The importer is an Artisan command in a `LegacyImport` support package, **not** a runtime module.

Every imported row gets `tenant_id = T` and, where branch-scoped, `branch_id = B0` (the tenant's first branch, created by the importer).

### C.1 Customer Club tables

| Legacy | Destination | Transformation | Relationships | Possible data loss | Risks |
|---|---|---|---|---|---|
| `lcm_club_members` | `customers` | `phone` → **normalised E.164** (`+989xxxxxxxxx`) via `PhoneNormalizer` (Persian/Arabic digits, `0098`, `+98`, `98`, `09`). `name` trimmed; numeric-only names → null (legacy shows "مشتری عزیز", "dear customer"). `birth_day/birth_month` → Jalali birth month/day. `created_at` → `created_at` (tz-converted). `referral_code` → `customers.referral_code` (unique per tenant, if referral is kept). | Parent of wallet, loyalty, addresses, orders | `avatar`, `saved_address` (unused), and non-normalisable phones → reported, skipped | **Duplicates after normalisation** (same person entered as `0912…` and `۰۹۱۲…`). Merge rule: keep the oldest record, **sum** wallet and points, keep the highest tier, log the merge. Invalid numbers go into a quarantine report. |
| `lcm_club_members.user_group` | `loyalty_tiers` (gap) and/or `customer_segments` (gap) → customer assignment | Groups with `min_spend > 0` → tier; groups with `min_spend = 0` → segment (e.g. "bodybuilder", "diet"). Group `percent/categories/products` → a `discounts` row + `discount_rules` targeting that tier/segment. | customers | Group colour (keep as tier metadata) | Slugs whose groups were deleted → fallback tier |
| `lcm_club_members.wallet_balance` | `wallets` + `wallet_transactions` | Create a wallet (currency per tenant). See the ledger row below. | customer | — | **Reconciliation** (below) |
| `lcm_wallet_ledger` | `wallet_transactions` | Import each row as type `legacy_import` with `amount`, `balance_after`, and `balance_before = balance_after − amount`, `description = reason`, `reference = legacy:ledger:{id}`, `created_at`. Then append **one** `migration_reconciliation` transaction so the final balance equals `wallet_balance`, with `description` = "تراز افتتاحیه مهاجرت" ("migration opening balance"). | wallet, (order via parsed `#123` in reason → order reference when resolvable) | None, but historical `balance_before` values may not chain | The main order debits are **missing** from the legacy ledger; the reconciliation entry absorbs the gap. Reason-string parsing is best-effort. |
| `lcm_club_members.loyalty_points` + `lcm_points_ledger` | `loyalty_accounts` + `loyalty_transactions` | Same approach as the wallet (import history, then reconcile to the current balance) | customer | — | Low |
| `lcm_order_ratings` | **gap**: `order_feedback` (proposed) | `order_id` → new order id via id map; `stars`; timestamps | order, customer | Rows whose order can't be mapped | Needs a table decision |
| `lcm_liked_items` | **gap**: `product_feedback` / order_item like flag (proposed) | order + product via id maps | order, product, customer | Unmappable rows | Needs a decision |
| `lcm_challenge_claims` | loyalty_transactions metadata (if challenges are EXT) | Keep only as historical reference in the points history | — | Claim table dropped | Low |
| referral links (`referred_by_code`) | `customers.referred_by_customer_id` (if kept) | Resolve the code → customer | customer | — | Codes that point to nobody |

### C.2 Live Cafe Menu tables

| Legacy | Destination | Transformation | Data loss | Risks |
|---|---|---|---|---|
| `lcm_push_subscriptions` | `customer_devices` | Link by normalised phone → customer; store endpoint/keys encrypted | **Effectively all.** Subscriptions are bound to the **origin** and the **VAPID public key**. They survive only if the storefront keeps the same origin **and** the legacy VAPID pair is imported into tenant settings (encrypted). | Otherwise mark them inactive; customers re-subscribe on their next visit |
| `lcm_reservations` | Depends on D3: `reservations` (V1-min) or an archive export (CSV) | `reserved_at` Gregorian local → UTC; phone normalised; status as-is | If EXT: exported, not imported | Low |

### C.3 WooCommerce / WordPress data (the hidden dependency)

| Legacy source | Destination | Transformation | Data loss | Risks |
|---|---|---|---|---|
| `product_cat` terms (+ parent) | `categories` (nested) | name, slug (per the platform slug policy), parent, order (`term_order`/meta) | Term descriptions if unused | Persian slugs vs the Latin slug policy (D6) |
| `product` posts (simple) | `products` + `product_prices` (branch B0) + `product_categories` | title, excerpt/content → description, `_price` → price (Money), `menu_order` → sort, featured (`_featured` / `product_visibility`), `_lcm_product_calory` → nutrition.calories, diet `product_tag` slugs → dietary_tags | Unused WC fields (tax class, shipping, SKU if empty) | Sale prices (`_sale_price`, schedule) → map to a discount or drop (decision at import) |
| `product_variation` posts | `product_variants` + `product_prices` | attribute label (e.g. size) → variant name; `_price` | Multi-attribute variations collapse into one label | Variable products with no variations |
| `_upsell_ids` | `modifier_groups` ("افزودنی‌ها", "add-ons", multi-select, 0..n) + `modifiers` (name/price copied from the add-on product) + `product_modifier_groups` | One shared group per unique add-on set | Add-on products stay sellable products too (see D.1.6) | Price drift between modifier and product afterwards |
| `_stock_status`, `_manage_stock`, `_stock` | `product_availability` (is_available) · ingredient stock is **not** derivable | — | Stock quantities don't map to ingredient inventory | Must not be passed off as ingredient stock |
| Featured image / gallery attachments | `product_images` → object storage | Download the file, re-upload, generate variants; keep alt text | Broken attachment URLs | Hotlinked external images |
| WC orders (`shop_order` posts **or** HPOS `wc_orders` + `wc_orders_meta`) | `orders` | order number preserved as `legacy_number`; **type mapping:** `_lcm_order_type=salon` + numeric `_lcm_table_id` → `qr_table` (source `qr`); `salon` without a table → `dine_in`; `takeaway` with an address note → `delivery` (address snapshot built from billing city/address_1); `takeaway` without an address → `takeaway`; orders without `_lcm_*` meta → `online` (source `web`). **Status mapping:** `pending`→`pending_payment`, `on-hold`→`pending_payment`, `processing`→`accepted` (+ `_lcm_kds_status=preparing` → `preparing`), `completed`→`completed`, `cancelled`/`failed`→`cancelled`, `refunded`→`refunded`. **Payment status** derived: `_lcm_payment_type=full_wallet`→paid; `partial_wallet` + remaining>0 + status pending → partially_paid; `salon` → paid if completed, otherwise unpaid; WC `_date_paid` present → paid. `customer_note` ← `_lcm_kitchen_note`; `scheduled_for` ← order date + `_lcm_requested_time` (next occurrence). Totals via Money (float → int, rounding rule logged). | Add-on→parent linkage (legacy never stored it); WC order notes (import as `order_status_history` notes, best-effort) | **Very large volumes** → chunked import; HPOS vs posts detection; orders by guests without a club member → customer created by billing phone, or left as a guest order |
| WC order line items (`woocommerce_order_items` + itemmeta) | `order_items` | name snapshot, product/variant via id map (nullable if deleted), unit price = line total (qty 1 per legacy row), qty merged when identical lines are in the same order | Modifier structure (add-ons imported as standalone items) | Deleted products → keep the name snapshot only |
| `_lcm_wallet_deducted`, `_lcm_remaining_amount`, `_lcm_remaining_payment_method`, `_payment_method` | `payments` (per order: a wallet payment row + a cash/gateway row) | Build synthetic `payments` with status matching the payment status. **No gateway transactions** (no gateway authority/ref ids available unless the WC gateway stored them). | Gateway references | Totals that don't add up → flagged |
| `_lcm_cashback_given` | `loyalty_transactions` reference (idempotency marker) | Ensures a re-run never re-grants | — | — |
| WP users (login=phone), `billing_city`, `billing_address_1` | `customer_addresses` (one default address, title "آدرس ذخیره‌شده" ("saved address"), city + address, no coordinates) | Link by normalised phone | WP users themselves (not migrated as platform users) | **Addresses have no lat/lng → cannot be zone-checked** until the customer confirms a map pin (UX: "please confirm your location") |
| WP admin users | `users` + `tenant_users` (owner) | Created manually by the tenant owner at onboarding. **Passwords are not migrated.** | — | — |

### C.4 Settings (wp_options) → tenant configuration

| Legacy option | Destination | Notes |
|---|---|---|
| `lcm_cafe_name`, `lcm_cafe_logo`, `lcm_menu_theme` | `tenants.name`, `tenant_branding` (logo → object storage, theme) | — |
| `lcm_table_count` | `restaurant_tables` 1..N for B0 + new QR tokens | **Every printed QR code must be reprinted.** Optional grace period: a legacy `?table_id=N` redirect mapped per tenant domain, time-limited. |
| `lcm_business_hours` | branch opening hours | Re-index weekday (PHP `w` 0=Sunday → ISO/Saturday-first as designed). `closed` → closed day |
| `lcm_lucky_item_*` | discount (daily rotation) if kept | — |
| `lcm_kds_pin` | **not migrated** | Replaced by staff accounts or device pairing |
| `lcm_kds_timer_minutes` | `kitchen_stations.late_after_minutes` (default) | — |
| `lcm_max_calories` | storefront filter setting | — |
| `lcm_vapid_keys` | tenant notification settings (encrypted) | Only useful if the origin is preserved |
| `lcm_sms_api_key` / `lcm_sms_sender_num` / `lcm_sms_dedicated_number` | tenant SMS provider config (encrypted, masked) | **Swapped semantics**: `api_key`=username, `sender_num`=password |
| `lcm_referral_reward` | loyalty rule (referral) | If kept |
| `lcm_discount_groups` | tiers/segments + discounts | See C.1 |
| `lcm_weekly_challenge` | EXT, or loyalty rule | — |
| `lcm_points_conversion_rate` | loyalty setting (`points_per_unit`, `unit_value` = 1,000 toman) | — |
| `lcm_birthday_gift_enabled/amount` | loyalty rule (trigger birthday, wallet credit) | — |
| `lcm_birthday_sent_*` | loyalty_transactions dedupe keys for the current Jalali year | Prevents a double gift in the cut-over year |
| `lcm_cashback_multi_rules` | loyalty rules (trigger order_completed, condition category spend ≥ X, reward wallet credit Y) | Legacy grants on *processing*; new grants on **completed** (decision D8) |
| Superseded options, `lcm_target_pages`, db versions, all transients | **DROP** | Ephemeral |

---

## D. Architecture Validation

### D.1 Conflicts between the legacy system and the proposed architecture

1. **WooCommerce ownership.** The new platform owns catalog, orders, checkout and payments natively. The migration therefore has to read WC internals: two order storage modes (posts vs HPOS, both queried in `live-cafe-menu.php:726-744`), plus WC price and meta semantics.
2. **Identity model.** Legacy: the phone string is the global key and customers are WP users. New: a tenant-scoped `customers` row with a normalised phone (**unique (tenant_id, phone_e164)**), and no platform user for customers. This matches master §5 and the Persian requirement §9.
3. **Order-type semantics.** Legacy `takeaway` is used for pickup **and** delivery. New: `takeaway` ≠ `delivery`, and delivery requires an address with coordinates + zone. Legacy addresses have no coordinates.
4. **Cart model.** Legacy flattens the cart and loses quantities and add-on parents. New: `cart_items` with `quantity` and `order_item_modifiers`. **Historical orders cannot be upgraded to the new structure.**
5. **Payment model.** Legacy: one order, and the wallet is deducted before the payment outcome is known. New (master §13): payment is independent from the order, has its own state machine, and one order can have several payments. The wallet debit must be **authorised/held** and captured only when the order is placed successfully (or reversed on failure/timeout).
6. **Add-ons are products.** Legacy add-ons are standalone WC products (sold via upsell). New: modifiers are not products. Decision: migrate them as modifiers **and** keep them as sellable products only if they were also sold alone (a detectable fact in order history).
7. **Discount groups = tiers + segments + discounts.** The master schema has `loyalty_rules` and `discounts` but **no tier or segment entity**. Merging them the way the legacy does would lead to hard-coded rules.
8. **Loyalty timing.** Legacy rewards on `processing` (before payment for counter orders). The master says `OrderCompleted` triggers loyalty. This is a behaviour change, but the right one.
9. **KDS.** Legacy: a single implicit station, a shared PIN, and KDS "ready" **completes the order**. New: stations + kitchen item states, with order completion as a separate step (a dine-in order is completed when served/paid; takeaway when picked up). Decision D9 is whether "all items ready" auto-completes, as in the legacy.
10. **Reservations.** Present in the legacy system, while the master says "do not implement full table reservation management in V1".
11. **V2 features that are live.** Badges, weekly challenges and referral are live in the legacy system; the master lists them as advanced or extension points.
12. **Currency unit.** The legacy code assumes **toman** in all labels and in the points conversion (`*1000`). The actual WC store currency (IRT vs IRR) must be confirmed per site before prices are imported.

### D.2 Missing requirements (in the legacy system, absent from the master schema)

| # | Missing item | Proposal (smallest justified change) |
|---|---|---|
| 1 | Customer tiers / segments | `loyalty_tiers` (tenant, name, color, min_spend, sort, benefits JSON) + `customers.loyalty_tier_id`; segments as `customer_tags` (or deferred) |
| 2 | Branch opening hours incl. overnight and holidays | `branch_opening_hours` (branch, weekday, opens_at, closes_at, is_closed) + `branch_closures` (date-range exceptions). A JSON column is acceptable for V1 if exceptions are deferred |
| 3 | Pre-order / scheduled orders | `orders.scheduled_for` (datetime) + a branch setting `allow_preorder_when_closed` |
| 4 | Birthday (Jalali month/day without a year) | `customers.birth_date` (nullable, Gregorian) **plus** `birth_jalali_month`, `birth_jalali_day` (since legacy has no year) |
| 5 | Order feedback (rating) & product likes | One table `order_feedback` (order, customer, rating, comment, editable_until) + `order_item_feedback` (liked flag), or defer to V2 |
| 6 | Reservations | V1-min `reservations` table **or** defer (D3) |
| 7 | Referral | `customers.referral_code` + `referred_by_customer_id`, with the reward as a loyalty rule; or defer (D4) |
| 8 | OTP | No table: Redis with a TTL + attempt counter + hashed code |
| 9 | SMS / push provider configuration | Encrypted `tenant_settings` entries (the platform-level default provider is in env) |
| 10 | Product nutrition / diet flags | JSON columns on `products` (no new table) |
| 11 | Wallet holds (authorise → capture) | `wallet_transactions.type in (hold, capture, release)` or a `status` column; no new table |
| 12 | Customer auth tokens | Laravel Sanctum's `personal_access_tokens` (framework table, justified) |
| 13 | Import bookkeeping | `legacy_id_map` (importer-only, dropped after cut-over) |
| 14 | Platform/framework tables | `jobs`, `failed_jobs`, `job_batches`, `cache`, `sessions` (if DB driver) — framework-required, not domain tables |

### D.3 Hidden dependencies

1. WooCommerce + its active payment gateway plugin (for the "online" part of payments).
2. WP-Cron firing, which needs site traffic. The birthday gift silently depends on it.
3. PHP SOAP extension (Trez). PHP ≥ 8.1 `openssl_pkey_derive` (push).
4. PHP sessions on every request. They break full-page caching and depend on sticky sessions in multi-server setups.
5. `_lcm_product_calory` has no editor; it was presumably filled by hand or by the older `cafe-live-menu-pro` prototype.
6. Hard-coded category slugs (`coffee, قهوه, hot-drinks, گرم, protein, diet, رژیمی, پروتئین, cold-drinks, سرد, iced`) drive badges and the caffeine estimate. Hard-coded Persian regexes drive the fallback add-ons.
7. WC order emails fire on `completed` (triggered from the KDS). A failing SMTP was the reason for the `try/catch` wrappers.
8. Third-party runtime assets: `api.qrserver.com`, `unpkg lucide@latest`, jsDelivr, cdnjs, flaticon. **In Iran some CDNs are intermittently blocked.** The new stack should self-host fonts, icons and QR generation.
9. `REMOTE_ADDR`-based rate limits. **Behind Cloudflare every visitor would share Cloudflare's IPs**, so the limits must use `CF-Connecting-IP` with trusted proxies configured.
10. The two plugins call each other through `function_exists` checks. Cross-module calls are implicit and untyped. In the new system they become module contracts and events.

### D.4 Security risks found in the legacy code (these must not be reproduced)

| Sev | Issue | Evidence | New-system control |
|---|---|---|---|
| **Critical** | Unauthenticated actions keyed by a phone in POST: read another customer's wallet/orders/address/referral code; **pay with another customer's wallet**; rename members/overwrite addresses; redeem their points; subscribe to their notifications | `lcm_get_user_panel_data`, `lcm_add_custom_product_to_cart` (wallet), `lcm_update_user_profile`, `lcm_redeem_points_to_wallet`, `lcm_get_live_wallet`, `lcm_save_push_subscription` | Customer identity comes **only** from an authenticated token (Sanctum) bound to (tenant, customer). Never from the request body |
| **Critical** | OTP disclosure via the **client-controlled `Host` header**: if `HTTP_HOST` contains `.local`/`localhost`, the OTP is returned in the JSON response. On servers that accept arbitrary Host headers this is a full account takeover | `class-lcm-ajax.php:136-149, 170-178` | Environment from config (`APP_ENV`), never from request data. OTPs are never returned in responses outside the `local` env |
| **High** | OTP brute force: 4 digits, `rand()`, 2-min TTL, **no limit on verify attempts** | `class-lcm-ajax.php:128, 174-192` | 5–6 digit CSPRNG code, hashed at rest, max 5 attempts per code, per-phone and per-IP limits, lockout |
| **High** | Group discount from forgeable cookies (`lcm_user_phone` set by JS; `lcm_user_group` still read): anyone can price with any member's tier | `menu-grid-partial.php:16-24`, `live-cafe-menu.php:376-378, 1198-1203` | Pricing resolves the customer from the auth token server-side. The discount engine is recomputed at checkout |
| **High** | Predictable table IDs + shared table cart transient: anyone can read/overwrite any table's cart, order "to" any table, call the waiter for any table | `lcm_sync_table_cart`, `?table_id=` | Opaque QR tokens (≥128-bit), rotatable; table session scoping; carts belong to a session + device |
| **High** | Wallet debited for orders that may never be paid; no reversal. Races on balance updates | A.2.10 | Ledger-first wallet, DB transaction + `SELECT … FOR UPDATE`, hold/capture/release, idempotency keys |
| **High** | Admin wallet edit sets an absolute balance with no ledger row or audit | `class-lcm-admin.php:219-235` | Only signed delta adjustments with a reason → ledger + `audit_logs` |
| **Medium** | Shared KDS PIN (default `1234`, plaintext option), session flag, no CSRF on KDS mutations, no per-staff attribution | `lcm_kds_*` | Staff auth + permission `kds.operate`; device pairing codes; `kitchen_events` records the actor |
| **Medium** | Secrets in plaintext options: SMS password, VAPID private key | `lcm_sms_sender_num`, `lcm_vapid_keys` | Laravel encrypted casts; masked in UI; never returned or logged; changes audited |
| **Medium** | SMS over plain HTTP SOAP (credentials in cleartext on the wire) | `http://smspanel.trez.ir/...` | HTTPS-only provider adapters |
| **Medium** | Unpinned third-party script `unpkg.com/lucide@latest` on customer and KDS pages (supply chain) | templates | Bundled assets only. CSP |
| **Medium** | Rate limits keyed on `REMOTE_ADDR` (ineffective or global behind Cloudflare) | several | Laravel RateLimiter on the real client IP + customer + tenant keys |
| **Medium** | Referral code space of 9,000 (`CAFE` + 4 digits from `rand()`) | `lcm_generate_referral_code` | Longer CSPRNG code, unique per tenant |
| **Low** | Some admin output echoed without escaping (`$table_id`, item names, group labels) | `lcm-admin-panel.php:205-207`, `view-settings.php:377` | React escaping by default; no `dangerouslySetInnerHTML` for user data |
| **Low** | Debug bypass `?lcm_test=1` forces the popup on any page | `class-lcm-public.php:18-20` | No debug switches in production code paths |
| **Low** | QR target URLs leaked to `api.qrserver.com` | admin QR page | Server-side QR generation |

**Things done right that are worth keeping:** server-side price lookup, order-key-protected tracking, the idempotency flag for cashback, the one-shot OTP-verified flag, nonce use on the main mutations, `hash_equals` for secrets, and wrapping notification side effects so that ordering never fails because of email or push.

### D.5 Migration risks (summary)

1. **There may be no production data** (domain lock to `dentall-clinic.local`). Confirm before investing in a full importer.
2. The wallet ledger cannot be reconciled; handled with an explicit reconciliation entry plus a report showing per-customer deltas.
3. Phone normalisation merges duplicates; money has to be summed during the merge.
4. Order line structure is lossy (quantity/add-on parent).
5. Addresses have no coordinates, so delivery is blocked until each customer confirms a pin.
6. Printed QR codes are invalidated.
7. Push subscriptions are lost unless the origin and VAPID keys are preserved.
8. Timezone ambiguity: MySQL `CURRENT_TIMESTAMP` columns (server tz) vs WP-local `current_time()` values. The importer must detect the MySQL session time zone and the WP `timezone_string`.
9. Float→integer money rounding, and the IRT/IRR unit of the WC store.
10. HPOS vs legacy post storage, plus volume (batch/chunk; the importer runs as a queued job).

### D.6 Localization validation (Persian-first requirements vs legacy)

The legacy system already shows several Persian-first behaviours worth keeping: RTL everywhere, Vazirmatn, Saturday-first weekday display, `ی/ک` search normalisation, Jalali birthday, "تومان" formatting, and a Persian UI copy tone. The gaps the new platform must close centrally are: **phone normalisation**, **Persian digits in UI** (legacy mostly shows Latin digits via `number_format`), **Jalali date pickers** (the reservation form uses Gregorian `<input type=date>`), **ZWNJ/digit/whitespace search normalisation**, **Iran timezone and Saturday-start weeks** in loyalty periods, a **single Jalali conversion** (legacy has two different algorithms), and **self-hosted fonts** (CDN reliability in Iran).

---

## E. Implementation Roadmap (with dependencies)

### E.1 Proposed adjustments to the master phase plan

- **A1. Localization + design-system foundation belongs in Phase 1.** Money (integer minor units, IRT/IRR display), `PersianNumberFormatter`, `JalaliDate` (one implementation), `PhoneNormalizer`, `PersianTextNormalizer` (search), Persian validation messages, `Asia/Tehran` tenant timezone, Saturday-first week helpers, and in the frontend the RTL tokens, self-hosted Vazirmatn, and shared formatters. Every later phase depends on these.
- **A2. Minimal Notifications/SMS in Phase 1 (Identity).** Customer OTP login is required before checkout (Phase 3). Build `SmsProviderInterface` + a log/fake driver + one real Iranian adapter now. The full Notifications module (templates, preferences, campaigns, push) stays later.
- **A3. The dashboard shell ships in Phase 1; each phase ships its own dashboard screens.** Without this, the Definition of Done ("UI where applicable") cannot be met in Phases 2–6. Phase 8 then becomes dashboard *experience*: overview, command palette, global search, onboarding, setup progress, settings search.
- **A4. Legacy import is built incrementally** (a catalog importer in Phase 2, orders in Phase 3, payments in Phase 4, customers/wallet/loyalty in Phase 5), each with dry-run + reconciliation. Its size depends on D1.

### E.2 Phases

| Phase | Deliverables (V1 scope) | Depends on | Key risks |
|---|---|---|---|
| **0 Discovery** | This report; decisions D1–D12 | — | — |
| **1 Foundation & Tenancy** | Monorepo (`apps/api` Laravel, `apps/web` Next.js, `packages/ui` design system), Docker dev, CI (tests, Pint/PHPStan/Larastan, ESLint/TS, Playwright skeleton). Modules: Core (tenants, tenant_domains, tenant_settings, tenant_branding, branches + opening hours), Identity (users, tenant_users, roles, permissions, role_permissions, tenant_user_roles; staff auth; **customer OTP auth**), audit_logs, **tenant-resolution middleware + global tenant scope + `BelongsToTenant` trait**, rate limiting with the Cloudflare real IP, encrypted settings. A1 + A2 + A3 foundations. **Cross-tenant isolation test harness** (reusable for every later module) | 0 | Tenant scoping mistakes are the #1 security risk, so the harness is mandatory before any business module |
| **2 Catalog** | categories (nested), products, variants, product_prices (branch), price_change_logs, bulk price ops (±%, ±fixed, exact; audited), images (object storage, upload validation), availability, modifier groups/modifiers, nutrition/diet JSON, normalised search. Dashboard catalog screens with Quick Add. Catalog importer (WC) | 1 | Price model complexity; image pipeline |
| **3 Commerce** | Tables/QR (secure tokens, sessions, session requests incl. call waiter), carts/cart_items, orders (7 types + source), order items/modifiers, order state machine + history, address snapshots, customer addresses, delivery zones (radius, polygon-ready) + eligibility pipeline, scheduled/pre-orders, discount engine V1 (percent, fixed, product, category, min order, time-based, customer/tier-specific, coupon) | 2 | Deciding order-state transitions; discount stacking rules |
| **4 Payments** | Payment state machine, `PaymentGatewayInterface` + Zarinpal adapter (+ IDPay/NextPay later), cash/POS/manual methods, idempotency keys, webhook/callback verification + replay protection, transaction log, refunds, encrypted tenant credentials | 3 | Iranian gateway callback quirks (GET redirects, verify-after-return) |
| **5 Customer Club** | Customers (tenant-scoped, normalised phone, Jalali birthday), wallet ledger (hold/capture/release, transactions + row locks), loyalty engine (points, cashback, tiers, rules; `OrderCompleted` listener; reversal on refund), birthday scheduler job, points→wallet redeem, (referral if D4). Customer/wallet/loyalty importer with reconciliation | 3, 4 | Money correctness → property-based tests on ledger invariants |
| **6 KDS** | Stations, product→station routing, kitchen_order_items + events, Reverb realtime with a polling fallback, late timer, sounds, waiter-call feed, staff/device auth | 3 (+1 realtime infra) | Tablet reliability / reconnects |
| **7 Storefront** | Next.js SSR/ISR, mobile-first RTL: menu, product sheet with modifiers, cart, address + map pin + zone check, checkout, payment redirect, tracking (realtime), account (wallet, points, tier, history, reorder), PWA manifest per tenant, Web Push opt-in, SEO (metadata, OG, canonical, sitemap, robots, Restaurant/LocalBusiness/Product/Breadcrumb schema), a11y | 2–6 | Performance on low-end Android; CDN reachability in Iran |
| **8 Dashboard experience** | Overview + actionable alerts ("۳ محصول رو به اتمام است", "3 products are low on stock"), command palette, global and settings search, onboarding + setup progress, empty states | 1–7 | Scope creep |
| **9 Inventory / Recipes / COGS** | ingredients, locations (single), stock, movements, adjustments, recipes, sale-time decrement, `order_item_cost_snapshots`, purchasing (suppliers, POs, payments) | 2, 3 | Unit conversions (g/ml/pcs) |
| **10 Operations** | expenses + categories, employees, roles, shifts, assignments, attendance | 1 | — |
| **11 Analytics** | Aggregation tables + queued rollups (daily/hourly/product/customer/inventory/financial), comparisons with base values, branch comparison, Jalali-period reports, Excel/CSV/PDF with Persian fonts | 3–10 | Late-arriving data (refunds) → idempotent re-aggregation |
| **12 Billing** | plans, features, plan_features, add-ons, subscriptions, invoices, usage, effective entitlements (plan + add-ons + overrides), trial/grace/read-only states | 1 (entitlement checks retrofitted into 2–11 via one `Entitlements` gate) | Downgrade data retention rules |
| **13 Marketplace** | Public store profiles (explicit public projection, no tenant-private data), categories, cities, search, featured listings | 7, 12 | Data leakage → dedicated public read models |
| **14 Advertising** | Campaigns, creatives, placements, targeting, events, metrics (lightweight), sponsored/featured/organic labelling | 13 | — |
| **15 Hardening** | Load tests, security review/pen-test checklist, backups/DR, observability, CSP, dependency audit, legacy cut-over runbook | all | — |

Critical path: **1 → 2 → 3 → 4 → 5 → 7**. Phase 6 can run alongside 4–5 after 3. Phases 9 and 10 can run alongside 7–8.

---

## F. Decisions needed before Phase 1

| ID | Question | Recommendation |
|---|---|---|
| **D1** | Is the legacy system running in production anywhere with real customers, wallets and orders? (The code only runs on `dentall-clinic.local`.) | If not, build a **lightweight catalog importer only** (WC → catalog) for onboarding, and skip the full order/wallet importer |
| **D2** | Legacy `takeaway` orders that carried an address: import as `delivery`? | Yes (rule in C.3) |
| **D3** | Reservations in V1? | Defer to V2 (the master says no reservation management in V1); export legacy rows to CSV |
| **D4** | Referral, badges and weekly challenge in V1 (legacy parity) or V2? | Basic referral (code + one reward rule) in V1; badges and challenges as V2 extension points; keep the points ledger history |
| **D5** | Ratings and item likes in V1? | V1-min: order rating only (one table). Likes → V2 |
| **D6** | Slug policy: Persian or Latin slugs, platform-wide? | Persian slugs for SEO on storefront pages, with stable numeric/ULID IDs in URLs as the source of truth |
| **D7** | Store currency: were WC prices in toman or rial? | Store integer **rial** internally and display toman by default (the Money layer handles it) |
| **D8** | Cashback/loyalty on `completed` instead of on `processing`? | Yes (master §15; avoids rewarding unpaid orders) |
| **D9** | Should "all items ready" on the KDS auto-complete the order (legacy behaviour)? | Configurable per order type: default auto-complete for takeaway/QR, manual for delivery |
| **D10** | Daily lucky item in V1? | V1 as a discount "daily rotation" option (small, and the legacy system has it) |
| **D11** | First SMS provider and first payment gateway to implement | SMS: Kavenegar (+ keep Trez for legacy tenants if D1 = yes). Gateway: Zarinpal |
| **D12** | Repo layout and versions | Monorepo; Laravel 12 / PHP 8.3 (available locally), Next.js (App Router) + TS; MySQL 8; Redis 7; Docker Compose for dev |

---

## Appendix — Legacy constants worth remembering

- Order rate limit: 10 orders / 5 min / IP. KDS PIN lockout: 10 failures / 15 min. Waiter cooldown: 60s / table. Reservation cooldown: 60s / phone. OTP: TTL 2 min, cooldown 45s, 5 per day per phone.
- Rating/like window: 24h from **order creation**. KDS late threshold: 7 min. Low-stock badge ≤ 5. Best-seller and like badges need ≥ 3.
- Points conversion: `N` points (default 10) = 1,000 toman. The lucky item defaults to 20% off until 14:00.
- Tier = the highest group with `min_spend ≤ lifetime spend`; never auto-downgrades.

---

## G. Decision log (2026-09-24)

| ID | Decision |
|---|---|
| Roadmap A1–A4 | **Approved.** Localization + design-system foundation, minimal SMS/OTP and the dashboard shell move into Phase 1; the importer is built incrementally. |
| D1 | The legacy system took real orders, but only at test level; there is no real production output. → **Only a lightweight catalog importer (WooCommerce → Catalog) for onboarding.** No order/wallet/loyalty importer. |
| D7 | WooCommerce prices were in **toman**. Internal storage stays integer rial; toman is the default display unit. |
| D11 | **Zarinpal** is the first payment gateway. **Kavenegar** is the first SMS provider. |
| Report language | The report stays in English. All chat is in Persian. |
| Other items (D2–D6, D8–D10, D12) | The user gave no specific answer, so the §F recommendations apply by default. They can still be changed before the related phase. |
