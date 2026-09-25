# Legacy cut-over runbook

For moving one café off the legacy WordPress site (`live-cafe-menu` + `lcm-customer-club`) onto
this platform. Scope follows the discovery decision (§F D1, confirmed §G): **the legacy system
never carried real production orders, wallets or loyalty data** — only a lightweight catalog
import exists (`catalog:import-woocommerce`, Phase 2). There is no order/wallet/loyalty importer,
and this runbook does not pretend otherwise.

Read this together with `docs/discovery/00-discovery-report.md` §C (the full field-by-field
migration map) if a specific piece of legacy data's fate needs checking.

## What moves, and what doesn't

**Moves (via `catalog:import-woocommerce`):**
- Products, variations (as variants), categories, prices, stock status, featured images.
- Idempotent and re-runnable — running it again updates rather than duplicates.

**Does not move — tell the café owner this up front, before they agree to switch:**
- Customer accounts, wallet balances, loyalty points and tiers, discount groups.
- Order history (nothing carries over; the new platform starts at zero orders).
- Reservations (export the `lcm_reservations` table to CSV yourself if the café wants a record;
  no importer reads it).
- The KDS PIN, VAPID push keys, and any Trez.ir SMS credentials (dead ends — the new platform uses
  its own kitchen-device pairing and SMS provider, see the platform settings screen).
- Anything not reachable through WooCommerce's product data (custom regex-based add-on
  suggestions, the daily "lucky item" logic, badges, weekly challenges — none of these have a V1
  equivalent to import into; recreate the parts that matter through the new dashboard's own
  screens: discounts, loyalty tiers, cashback rules).

## Pre-migration checklist

1. **Confirm this café actually has product data worth importing** — some legacy installs may be
   near-empty test sites (per the discovery report, the legacy system mostly ran on
   `dentall-clinic.local`, not real production hosts).
2. **Get read access to the WordPress database** (not the WordPress admin — a DB connection). Add
   it to `apps/api/.env` as the `legacy_wp` connection (`LEGACY_WP_DB_*`, already defined in
   `config/database.php`) or point `--connection` at another connection you define.
3. **Confirm the WooCommerce currency** was toman, not rial (the legacy discovery found toman
   throughout; if this café's store used a different unit, pass `--unit=rial`). Getting this wrong
   makes every price off by 1,000x.
4. **Create the tenant** on the new platform first (through the normal signup/onboarding flow, or
   an internal provisioning script) — the importer requires an existing tenant slug, it does not
   create tenants.
5. **Tell the café owner explicitly** which legacy data will not carry over (the list above), and
   get their sign-off before proceeding. This avoids a support ticket three weeks later asking
   where a customer's wallet balance went.

## Migration steps

1. **Dry run first, always:**
   ```bash
   php artisan catalog:import-woocommerce <tenant-slug> --connection=legacy_wp --prefix=wp_ --dry-run
   ```
   Review the output: category and product counts, anything it flags as skipped or ambiguous.
2. **Run for real**, optionally with images (slower — each image is downloaded and re-encoded via
   `ImageProcessor`):
   ```bash
   php artisan catalog:import-woocommerce <tenant-slug> --connection=legacy_wp --prefix=wp_ --with-images
   ```
3. **Spot-check in the dashboard**: category tree matches the old menu's structure, a handful of
   products have correct prices and photos, variations (sizes) show up correctly, out-of-stock
   items are marked unavailable.
4. **Rebuild what the importer can't**: modifier groups / add-ons (legacy upsells aren't
   auto-converted — recreate the café's real add-ons as modifier groups, they're usually few),
   discounts, loyalty program (tiers, cashback rules), delivery zones, business hours, staff
   accounts, branding (logo, cover, colours).
5. **Print new table QR codes.** Legacy QR codes point at `?table_id=N` URLs that don't exist on
   this platform; every table needs a freshly issued, opaque QR token from Tables → QR in the
   dashboard. There is no compatibility redirect — plan for a same-day swap of the printed codes.
6. **Re-pair (or newly pair) KDS tablets** — the legacy PIN doesn't carry over; pair each tablet
   from Kitchen → Devices.
7. **Configure SMS and payment providers** for this tenant (Kavenegar/Zarinpal or whatever this
   café will actually use) — Trez.ir credentials are not migrated and would not work against the
   new platform regardless.

## Go-live

1. **Pick a low-traffic window.** Even though legacy data mostly doesn't carry over, avoid
   switching mid-service.
2. **Point the domain/DNS** (or the platform's tenant-domain mapping) at the new storefront.
3. **Take the legacy site offline or read-only** at the same time — a customer placing an order on
   the old system after cut-over is an order that silently never reaches this platform.
4. **Watch the new tenant's first hour**: orders module (`/dashboard/orders`), payment webhooks
   (`PaymentLog`), and the KDS screen with the café's actual staff.
5. **Keep the legacy WordPress site and its database untouched but offline** for at least a few
   weeks after cut-over, in case something in the pre-migration checklist was missed and needs a
   second look at the source data. Don't delete it as part of this runbook.

## Rollback

Because no orders/wallet/loyalty data moves, rollback is simple and low-risk: point the
domain/DNS back at the legacy WordPress site and reopen it. The only new-platform state created
(catalog, any staff accounts, any orders placed during the failed window) can be left in place or
the tenant suspended — it does not corrupt anything on the legacy side, since migration is
read-only against the WordPress database.
