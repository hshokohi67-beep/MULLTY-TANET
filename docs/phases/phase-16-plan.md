# Phase 16: SMS centre, automatic subdomains, free-delivery zones (Plan)

## Owner decisions

1. Customer-facing SMS goes out from **each café's own SMS panel and line**, so the legal responsibility for what a café sends stays with the café.
   - The platform only provides the tool.
   - **Login codes (OTP)** stay on the platform's own line, **RayganSMS (Trez)**, so login never depends on a café's account.
2. **Supported panels:** Kavenegar, Melipayamak, SMS.ir, FarazSMS (IPPanel) and RayganSMS/Trez.
3. **Domains:**
   - Every café automatically gets `{slug}.{STORE_BASE_DOMAIN}`: one wildcard DNS record and one wildcard certificate.
   - No per-café domains, servers or CNAMEs, and nothing to configure in the café panel.
   - A shared cPanel host is acceptable for the pilot if it has a Node.js app, PHP 8.4, SSH, per-minute cron and wildcard subdomain plus SSL support.
4. **Delivery zones:** an explicit «ارسال رایگان در این محدوده» ("free delivery in this zone") switch.

## 1. SMS

### Platform line

New `raygan` driver in `App\Support\Sms`:

| Purpose | Endpoint |
|---|---|
| Login codes | `https://smspanel.trez.ir/SendMessageWithCode.ashx` (our own code, sent through Trez's OTP service line) |
| Plain text | `https://RayganSMS.com/SendMessageWithPost.ashx` |

- Credentials: `RAYGAN_USERNAME`, `RAYGAN_PASSWORD`, `RAYGAN_SENDER`.
- The platform line keeps sending: OTP (customers and staff) and subscription reminders to café owners.

### Café lines: new module `Messaging`

**Drivers** (HTTPS, no SDKs). Credentials are never logged and are stored encrypted.

| Panel | Endpoint and credentials |
|---|---|
| Kavenegar | `/v1/{key}/sms/send.json` |
| Melipayamak | REST `SendSMS/SendSMS`, username + password + from |
| SMS.ir | `POST /v1/send/bulk`, `x-api-key` + line number |
| FarazSMS/IPPanel | `POST https://api2.ippanel.com/api/v1/sms/send/webservice/single`, `apikey` + sender |
| RayganSMS | `SendMessageWithPost.ashx`, username + password + line |

**Tables:**

- `sms_accounts`: one per café. Provider, encrypted credentials, sender line, active, `verified_at`, last error.
- `sms_templates`: one per café and message key. Enabled flag and editable text with placeholders.
- `sms_messages`: the send log. Kind, recipient, text, parts, status, provider reference or error, campaign. Pruned after 180 days.
- `sms_campaigns`: audience filter, text, schedule, status and counters.

**Automatic messages** (each can be switched off and edited; sent only when the café's line is connected):

- «سفارش آماده است» ("order ready"): takeaway and pre-orders become ready.
- «سفارش ارسال شد» ("order sent"): a delivery goes out.
- Birthday gift: moved from the platform line.
- Referral reward: when both rewards are paid.
- The owner's end-of-day report: moved from the platform line.

**Campaigns:**

- **Audience:**
  - only customers who opted in to marketing;
  - optionally narrowed by club tier, birth month, "not seen for N days" or "ordered at least once".
- **Before sending:** live recipient count and SMS parts (70/67 characters per part in Persian).
- **Timing:** now, or a scheduled Jalali date and time.
- **Quiet hours:** 08:00–21:00 Tehran; outside them, sending waits until 08:00.
- The opt-out footer «لغو۱۱» is always appended.
- Sending is queued in chunks, with sent/failed counters shown live.

**Panel `/dashboard/sms`** (new permission `sms.manage`: owner and manager). Tabs:

- «اتصال پنل پیامک» ("SMS panel connection"): choose the panel, credentials, sender line, and «ارسال آزمایشی» ("test send") to the owner's phone.
- «پیام‌های خودکار» ("automatic messages"): switch, text, live preview with placeholders.
- «کمپین‌ها» ("campaigns").
- «گزارش ارسال» ("send log"): masked phone numbers.

**Clean-up:** the old unused «کلید API کاوه‌نگار» ("Kavenegar API key") field in general settings is removed. A migration moves an existing key into `sms_accounts` (Kavenegar).

## 2. Automatic subdomains

- **Web proxy:** a request whose host is `{slug}.{STORE_BASE_DOMAIN}` is served by that café's storefront.
  - `/` becomes `/s/{slug}`, and `/x` becomes `/s/{slug}/x`.
  - `/s/{slug}/…` passes through; another café's `/s/{other}` returns 404.
- **Cookies:** storefront cookies are host-only with path `/` on a subdomain (isolated per café by the browser) and keep `/s/{slug}` on the main host.
- **Links:** the panel shows the café's address everywhere it matters: settings, tables/QR (new QR codes encode the subdomain URL), share links and the marketplace «منو و سفارش» ("menu and order") button.
- **API:** the payment gateway's callback base follows the storefront host.
- **Docs:** `docs/ops/deploy.md` covers the wildcard DNS record, the wildcard certificate (Let's Encrypt DNS-01 or the host's panel), shared-cPanel steps (Node.js app, build locally, cron for the scheduler and the queue) and the VPS path later.

## 3. Free delivery zones

- Zone flag `is_free`:
  - the fee becomes 0 and «ارسال رایگان از» ("free delivery from") is ignored;
  - the zone shows green on the map with a «رایگان» ("free") badge;
  - it feeds the marketplace «ارسال رایگان» ("free delivery") fact.

## 4. Security

- SMS credentials are encrypted at rest, write-only in the API (masked), and never logged. Kavenegar keys sit in the URL path, so the HTTP client logs are scrubbed.
- Campaigns are restricted to opted-in customers, enforced in the query and re-checked per chunk.
- Sending is throttled per café, with a daily cap on campaign recipients (setting, default 5,000).
- Every campaign send is audited.
- **Subdomain proxy:** only `[a-z0-9-]{2,64}` slugs; another café's path never leaks. Cookies are host-only, so café A's cookies are never sent to café B.

## 5. Tests

- **Drivers:** request shape and success/failure parsing for every panel (HTTP fakes).
- **Platform OTP** through Raygan.
- **Routing:**
  - automatic messages are skipped without a connected line and never use the platform line;
  - birthday and daily report move to the café's line;
  - order ready and sent messages fire.
- **Campaigns:** audience filters, opt-in enforced, quiet-hours scheduling, footer, counters, daily cap.
- **Permissions**, and the isolation harness for every new endpoint.
- **Web:** typecheck, lint and build.
- **Subdomain rewrite:** checked in the browser against a `*.localhost` host.
- **Visual checks.**
