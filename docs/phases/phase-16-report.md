# Phase 16 report: SMS centre, café subdomains, free delivery zones

## What shipped

### SMS
Two lines, kept strictly apart:

- **The platform line** (`SmsProvider`, `SMS_PROVIDER=raygan`)
  - Sends customer and staff login codes and platform messages only.
  - New `raygan` driver (RayganSMS/Trez):
    - text: POST `SendMessageWithPost.ashx`, one call per recipient;
    - codes: GET `smspanel.trez.ir/SendMessageWithCode.ashx`.
  - A send counts as successful only when the response is a message id (> 1000) or `2`.
- **The café's own line** (new `Messaging` module)
  - Every café connects its own panel, so the legal and financial responsibility is the café's. Supported panels: Kavenegar, Melipayamak, SMS.ir, IPPanel/FarazSMS, Raygan/Trez (`Support\Sms\SmsDrivers`).
  - Credentials are stored `encrypted:array` and are never returned. The UI shows masked values; a blank field keeps the saved secret.
  - Everything goes through `SendCafeSms`, which never throws and writes a row to `sms_logs` for each message: sent, failed or skipped.
  - The old `integrations.sms.kavenegar_api_key` setting is migrated into `sms_accounts`, then removed.

#### Automatic messages
- Templates `order_ready`, `order_sent` and `birthday`. Each can be turned on or off and edited.
- The first two are queued (`afterCommit`) from `OrderStatusChanged`.
- Birthday gifts and the daily owner report now use the café line (`CafeMessenger`), not the platform line.

#### Campaigns
- Sent only to customers who opted in to marketing.
- Audience filters: tier, birth month, inactive days, has ordered. The screen shows a live count.
- Can be scheduled or sent now.
- Sending is limited to 08:00–21:00 Tehran time, at most 5,000 messages a day per café.
- «لغو۱۱» ("send 11 to unsubscribe") is always appended.
- Sent by `sms:campaigns`, which runs every minute in chunks with a cursor.
- Logs are pruned after 180 days (`sms:prune`).

#### Screen and permission
- New `/dashboard/sms` screen «پیامک» ("SMS") with four tabs:
  - connect (with a test send);
  - automatic messages;
  - campaigns;
  - delivery log.
- Guarded by the new `sms.manage` permission (owner and manager).
- It has a help topic, a sidebar entry and a command-palette entry.

### Automatic café subdomains
- With `NEXT_PUBLIC_STORE_BASE_DOMAIN=cafeyar.ir`, the proxy rewrites `{slug}.cafeyar.ir/*` to `/s/{slug}/*`.
  - A path for another café on that host returns 404.
  - Some names are reserved and never treated as cafés: `www`, `api`, `admin`, and others.
- On the café's own host, storefront cookies are host-only with path `/`. On the shared host they stay scoped to `/s/{slug}`.
  - The scope is derived from the Host header (`slugFromHost`), not from a header the proxy adds, because server actions on rewritten routes don't reliably carry proxy headers.
- Links to a café's storefront point at its subdomain everywhere:
  - dashboard;
  - table QR codes;
  - «خوراک‌گردی» ("food-hopping", the marketplace) cards and banners.
- With `STOREFRONT_SUBDOMAINS=true`, online payment callbacks return to the subdomain (`StorefrontUrl`).
- No custom domains or CNAME: one wildcard DNS record and one wildcard certificate cover every café.

### Free delivery zones
- New «ارسال رایگان در این محدوده» ("free delivery in this zone") switch.
  - When it is on, the delivery fee is 0 and the fee fields are hidden.
  - Free zones are drawn in the success colour on the map.
- Example: a free 2 km zone inside a paid 5 km zone. The smallest zone that contains the address wins.

### Deploy
- `docs/ops/deploy.md` covers the shared cPanel pilot:
  - requirements;
  - wildcard DNS and SSL;
  - environment;
  - cron;
  - preflight;
  - smoke tests.
- It also covers the move to a VPS later.

## Checks
- API: 533 tests pass (1 skipped). Larastan level 6 reports 0 errors; Pint passes.
- Web: `tsc`, ESLint and `next build` are clean.
- Isolation harness: all `/sms` endpoints are added, with an `SmsCampaign` fixture.
- Visual checks (390 px, light and dark):
  - SMS centre tabs;
  - campaign empty state;
  - free-zone switch;
  - the storefront on `cafe-nemooneh.menu.localhost`. The cart persists across reloads there, and another café's path returns 404.

## Confirm on the first live send
- **Raygan/Trez:** the vendor doesn't document the exact response body. We parse a bare number or JSON `Code`/`Result`. Check the first real OTP and the failure log `[sms:raygan]`.
- **IPPanel:** check `status: "OK"` on the first send from a café.

## Risks and notes
- Shared cPanel needs "Setup Node.js App" and a wildcard certificate, which AutoSSL usually can't issue.
- Build the web app locally. `NEXT_PUBLIC_*` values and `API_URL` are baked in at build time.
- A café that has no panel connected simply skips automatic SMS. The skip is logged; it isn't an error.
