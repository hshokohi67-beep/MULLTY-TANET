# راهنمای راه‌اندازی: هاست اشتراکی سی‌پنل برای شروع، VPS در ادامه

> نسخه‌ی فارسی `deploy.md`. هر وقت یکی تغییر کرد، دیگری را هم به‌روز کنید.

برنامه سه بخش دارد:

- **API:** لاراول روی PHP 8.4، به‌همراه صف کارها و زمان‌بندی دقیقه‌ای.
- **وب:** Next.js، یعنی یک برنامه‌ی Node که همیشه روشن است.
- **دیتابیس:** MySQL 8.

هر کافه خودکار آدرس خودش را می‌گیرد، مثل `narenj.cafeyar.ir`. برای این کار فقط **یک** رکورد DNS از نوع wildcard و **یک** گواهی SSL از نوع wildcard لازم است. برای هیچ کافه‌ای دامنه‌ی جدا لازم نیست.

## ۱. قبل از خرید هاست این‌ها را از فروشنده بپرسید

| نیاز | چرا |
|---|---|
| بخش **«Setup Node.js App»** در سی‌پنل، Node نسخه‌ی ۲۲ به بالا، حداقل ۱ گیگ رم برای برنامه | بخش وب یک برنامه‌ی Node است (بیکار حدود ۱۵۰ مگ، زیر بار ۳۰۰ تا ۵۰۰ مگ) |
| PHP **8.4** با افزونه‌های `gd`، `intl`، `sodium`، `pdo_mysql`، `mbstring` و `fileinfo` | بخش API |
| MySQL **8** | دیتابیس |
| دسترسی **SSH/Terminal** و `composer` | نصب و ساخت جدول‌ها |
| **Cron هر یک دقیقه** | زمان‌بندی و صف (پیامک، کمپین، گزارش‌ها) |
| ساب‌دامین wildcard (`*`) در بخش «Domains» سی‌پنل، به‌همراه **SSL wildcard** | آدرس اختصاصی هر کافه |

SSL از نوع wildcard را یا از Let's Encrypt با تأیید DNS بگیرید یا بخرید. AutoSSL سی‌پنل معمولاً wildcard صادر نمی‌کند.

**نکته:** بخش وب را روی کامپیوتر خودتان build کنید. `next build` حدود ۱.۵ تا ۲ گیگ رم می‌خواهد و هاست اشتراکی این مقدار را نمی‌دهد. بعد نتیجه را آپلود کنید.

## ۲. تنظیم DNS (فقط یک بار)

```
A      cafeyar.ir        → IP سرور
A      *.cafeyar.ir      → IP سرور      (همه‌ی کافه‌ها: narenj.cafeyar.ir، eram.cafeyar.ir، …)
A      api.cafeyar.ir    → IP سرور
```

## ۳. متغیرهای محیطی

**API (فایل `apps/api/.env`):**

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

TENANT_SUBDOMAIN_BASE=cafeyar.ir        # ساب‌دامین کافه‌ها
STOREFRONT_SUBDOMAINS=true              # برگشت از درگاه به آدرس خود کافه
STOREFRONT_SCHEME=https
STOREFRONT_URL=https://cafeyar.ir

SMS_PROVIDER=raygan                     # کد ورود (خط پلتفرم)
RAYGAN_USERNAME=…
RAYGAN_PASSWORD=…
RAYGAN_SENDER=…

PAYMENTS_DRIVER=zarinpal
ZARINPAL_SANDBOX=false
BILLING_GATEWAY=zarinpal
BACKUP_DISK=s3                          # اختیاری، ولی توصیه می‌شود
```

**وب:** این مقادیر را موقع build هم تنظیم کنید. مقادیر `NEXT_PUBLIC_*` و `API_URL` داخل خروجی build ثبت می‌شوند و بعداً عوض نمی‌شوند.

```
API_URL=https://api.cafeyar.ir
STOREFRONT_URL=https://cafeyar.ir
MEDIA_ORIGIN=https://api.cafeyar.ir
NEXT_PUBLIC_STORE_BASE_DOMAIN=cafeyar.ir
NEXT_PUBLIC_STORE_PROTOCOL=https
```

## ۴. قدم‌های راه‌اندازی روی سی‌پنل

**۱. API**

۱. پوشه‌ی `apps/api` را آپلود کنید.
۲. ریشه‌ی سایت (document root) دامنه‌ی `api.cafeyar.ir` را روی `apps/api/public` بگذارید.
۳. این دستورها را در ترمینال به ترتیب اجرا کنید:
   - `composer install --no-dev -o`
   - `php artisan key:generate`
   - `php artisan migrate --force`
   - `php artisan permissions:sync`
   - `php artisan storage:link`

**۲. وب**

۱. روی کامپیوتر خودتان، با متغیرهای بخش ۳، اجرا کنید: `npm ci && npm run build --workspace web`
۲. این‌ها را آپلود کنید:
   - `apps/web/.next`
   - `apps/web/public`
   - `apps/web/package.json`
   - پوشه‌ی `packages/`
   - `node_modules`؛ یا به‌جای آپلود، روی سرور `npm ci --omit=dev` بزنید.
۳. در «Setup Node.js App»:
   - ریشه را `apps/web` بگذارید.
   - دستور اجرا را `npx next start -p $PORT` بگذارید.
   - متغیرهای وب را اضافه کنید.
۴. هم `cafeyar.ir` و هم `*.cafeyar.ir` را به همین برنامه وصل کنید.

**۳. Cron:** این دو خط را در Cron Jobs سی‌پنل، با اجرای هر یک دقیقه، اضافه کنید:

```
* * * * * cd ~/apps/api && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/apps/api && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

**۴. بررسی آمادگی:** `php artisan ops:preflight` را اجرا کنید. خروجی باید با «Ready for production» تمام شود.

**۵. تست نهایی:** این موارد را یکی‌یکی امتحان کنید:
- صفحه‌ی `https://cafeyar.ir/explore` باز شود.
- آدرس یک کافه (`https://{اسم کافه}.cafeyar.ir`) منوی همان کافه را نشان دهد.
- ورود به پنل کار کند.
- یک سفارش آزمایشی با پرداخت آنلاین ثبت کنید. درگاه باید به آدرس خود کافه برگردد.

## ۵. بعداً: انتقال به VPS (برای شروع ۲ هسته و ۴ گیگ رم)

متغیرها همان‌ها هستند. به‌جای قدم‌های سی‌پنل:

- Caddy یا nginx جلوی برنامه بگذارید. SSL از نوع wildcard را با DNS challenge بگیرید.
- `php-fpm` برای API.
- یک سرویس systemd برای `next start`.
- `queue:work` زیر systemd یا supervisor.
- همان خط cron برای `schedule:run`.

**انتقال:** کافی است دیتابیس و پوشه‌ی `storage/app/public` را منتقل کنید و DNS را به IP جدید بدهید.
