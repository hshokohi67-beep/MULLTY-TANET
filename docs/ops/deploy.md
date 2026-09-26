# Deploy: shared cPanel for the pilot, a VPS later

> Persian version: [deploy.fa.md](deploy.fa.md). Keep both in sync.

The app has three parts:

- **API**: Laravel on PHP 8.4, with a queue and a per-minute scheduler.
- **Web**: Next.js, a long-running Node process.
- **Database**: MySQL 8.

Every café gets its own address automatically, `{slug}.{your-domain}`. That needs **one** wildcard DNS record and **one** wildcard certificate, and no per-café domains.

## 1. What the host must provide (ask before buying)

| Need | Why |
|---|---|
| cPanel **"Setup Node.js App"** (CloudLinux Node.js Selector / Passenger), Node 22+, ≥ 1 GB RAM for the app | the web app is a Node process (≈150 MB idle, ≈300–500 MB busy) |
| PHP **8.4** with `gd`, `intl`, `sodium`, `pdo_mysql`, `mbstring`, `fileinfo` | the API |
| MySQL **8** | the database |
| **SSH/Terminal**, `composer` | install and migrate |
| **Cron every minute** | scheduler and queue |
| Wildcard subdomain `*` in cPanel "Domains", plus a **wildcard SSL** certificate (Let's Encrypt with DNS validation, or a purchased wildcard; cPanel AutoSSL usually can't issue wildcards) | café subdomains |

Build the web app on your own machine: `next build` needs ~1.5–2 GB of RAM, which shared plans don't give. Upload the result.

## 2. DNS (once)

```
A      cafeyar.ir        → server IP
A      *.cafeyar.ir      → server IP      (every café: narenj.cafeyar.ir, eram.cafeyar.ir, …)
A      api.cafeyar.ir    → server IP      (or a sub-path; see §4)
```

## 3. Environment

**API (`apps/api/.env`):**

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.cafeyar.ir
DB_CONNECTION=mysql
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1

TENANT_SUBDOMAIN_BASE=cafeyar.ir        # café subdomains
STOREFRONT_SUBDOMAINS=true              # payment callbacks return to the café's own host
STOREFRONT_SCHEME=https
STOREFRONT_URL=https://cafeyar.ir

SMS_PROVIDER=raygan                     # login codes (platform line)
RAYGAN_USERNAME=…
RAYGAN_PASSWORD=…
RAYGAN_SENDER=…

PAYMENTS_DRIVER=zarinpal
ZARINPAL_SANDBOX=false
BILLING_GATEWAY=zarinpal
BACKUP_DISK=s3                          # optional, recommended
```

**Web (set these at build time too: `next.config` and the `NEXT_PUBLIC_*` values are baked into the build):**

```
API_URL=https://api.cafeyar.ir
STOREFRONT_URL=https://cafeyar.ir
MEDIA_ORIGIN=https://api.cafeyar.ir
NEXT_PUBLIC_STORE_BASE_DOMAIN=cafeyar.ir
NEXT_PUBLIC_STORE_PROTOCOL=https
```

## 4. Shared cPanel steps

1. **API**
   - Upload `apps/api`. Point `api.cafeyar.ir`'s document root at `apps/api/public`.
   - Run `composer install --no-dev -o`, then `php artisan key:generate`, `php artisan migrate --force`, `php artisan permissions:sync` and `php artisan storage:link`.
2. **Web**
   - Locally: `npm ci && npm run build --workspace web` (with the web environment above).
   - Upload `apps/web/.next`, `apps/web/public`, `apps/web/package.json`, the workspace `packages/` and `node_modules` (or run `npm ci --omit=dev` over SSH).
   - In "Setup Node.js App", set the root to `apps/web`, the start command to `npx next start -p $PORT`, and add the environment above.
   - Map `cafeyar.ir` **and** `*.cafeyar.ir` to this app.
3. **Cron**, every minute:

   ```
   * * * * * cd ~/apps/api && php artisan schedule:run >> /dev/null 2>&1
   * * * * * cd ~/apps/api && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
   ```

4. **Check**: `php artisan ops:preflight` must end with "Ready for production".
5. **Smoke test** each of these:
   - `https://cafeyar.ir/explore`;
   - `https://{a café}.cafeyar.ir` (its menu);
   - log in to the panel;
   - a test order with online payment. The gateway must return to the café's subdomain.

## 5. Later: a VPS (2 vCPU / 4 GB to start)

Same environment. Instead of the cPanel steps:

- Caddy or nginx in front (wildcard certificate via DNS challenge);
- `php-fpm` for the API;
- a systemd service for `next start`;
- `queue:work` under systemd or supervisor;
- the same cron line for `schedule:run`.

Moving over is a matter of copying the database and `storage/app/public`, then pointing DNS at the new IP.
