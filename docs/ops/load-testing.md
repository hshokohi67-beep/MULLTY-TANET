# Load testing

Status: new in Phase 15. The scripts below (`apps/api/tests/load/*.js`, [k6](https://k6.io)) have
been reviewed for correctness but **not run against a live deployment** — there is no staging
environment in this session. Their thresholds are a starting point, not a guarantee; tighten or
relax them once you have a real baseline.

## Scripts

| Script | Covers | Needs |
|---|---|---|
| `menu-browse.js` | Storefront shell + menu read (guest, no writes) | Just a tenant slug |
| `checkout-flow.js` | Join table → read menu → cart → checkout (cash) | A seeded table QR token |
| `order-tracking.js` | The tracking page's poll (places one order in `setup()`, then polls it) | A seeded table QR token |
| `kds-poll.js` | The kitchen screen's poll | A paired KDS device token |

`menu-browse.js` is the one to run first — it needs no setup and represents the highest-traffic
path (every visitor reads the menu; comparatively few complete a checkout).

## Running them

1. Install k6 (`brew install k6`, or see <https://k6.io/docs/get-started/installation/>).
2. Point them at a **staging or load-test environment, never production** — these scripts create
   real orders and hit real rate limits.
3. Seed one tenant with at least a branch, a few products, and (for `checkout-flow.js` /
   `order-tracking.js`) a table with a printed/known QR token. For `kds-poll.js`, pair a device
   from the dashboard (Kitchen → Devices) and copy its token.
4. Run, e.g.:
   ```bash
   k6 run -e BASE_URL=https://staging.example.com -e TENANT_SLUG=cafe-demo apps/api/tests/load/menu-browse.js
   k6 run -e BASE_URL=https://staging.example.com -e TENANT_SLUG=cafe-demo -e TABLE_QR_TOKEN=... apps/api/tests/load/checkout-flow.js
   ```
5. Watch the summary k6 prints at the end: `http_req_duration` (p95, compare against the script's
   threshold), `http_req_failed` (should stay under the threshold), and any custom metric
   (`checkout_success` in `checkout-flow.js`).

## Reading the results

- **A threshold failure isn't automatically a launch blocker** — it's a signal to look at *why*:
  slow queries (check the `Slow query` log lines from Phase 15's observability work), a missing
  index, N+1 loading, or simply an environment (staging box) too small to judge production
  capacity from.
- **Rate limits will trip under load on purpose.** `checkout` and `storefront` both have
  `throttle:` middleware (see each module's `ServiceProvider::boot()`); a sustained load well
  above real traffic will start seeing 429s, which is the rate limiter doing its job, not a bug.
  Size the test's `target`/`vus` around a realistic peak (a busy café's simultaneous QR orders),
  not an arbitrary large number.
- **Run `menu-browse.js` and `checkout-flow.js` together** (two k6 processes, or k6's own
  scenarios) before trusting either result in isolation — the real failure mode is checkout
  slowing down *because* menu reads are saturating the same database connections.

## What's still needed before launch

- An actual run against a staging environment sized like production, with results recorded
  somewhere durable (not just this file).
- A decision on target concurrency: how many simultaneous tables/orders the platform needs to
  support per tenant and in aggregate across tenants — these scripts don't set that number, they
  just make it measurable.
- If results reveal a real bottleneck, the fix belongs in the relevant module, not in loosening
  these thresholds.
