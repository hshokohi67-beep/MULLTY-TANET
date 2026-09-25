import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { Bike, Clock, MapPin, Store } from 'lucide-react';
import { Alert, cx } from '@cafe/ui';
import { formatClock, formatJalaliDate, formatJalaliDateTime, formatTime } from '@cafe/locale';
import { initialOf } from '@/components/explore/Art';
import { MenuBrowser } from '@/components/store/MenuBrowser';
import { StoreHeroArt } from '@/components/store/StoreArt';
import { getMenu, getStorefront, getStories, storeUrl } from '@/lib/storefront';
import type { StoreBranch } from '@/lib/storefront-types';

const DAY: Record<number, string> = { 1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday' };

function pickBranch(branches: StoreBranch[], slug: unknown): StoreBranch | undefined {
  return branches.find((b) => b.slug === slug) ?? branches[0];
}

export async function generateMetadata({ params, searchParams }: PageProps<'/s/[tenant]'>): Promise<Metadata> {
  const { tenant } = await params;
  const store = await getStorefront(tenant);
  if (!store) return {};
  const branch = pickBranch(store.branches, (await searchParams).branch);
  const title = store.branding?.seo_title ?? `منو و سفارش آنلاین ${store.name}`;

  return {
    title: { absolute: title },
    alternates: { canonical: branch && branch !== store.branches[0] ? `?branch=${branch.slug}` : storeUrl(tenant) },
    openGraph: { title, description: store.branding?.seo_description ?? undefined },
  };
}

const ISO_DAY: Record<string, number> = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };

/** Today's opening hours in the café's time zone, e.g. "۸:۰۰ تا ۲۳:۰۰". */
function todayHours(branch: StoreBranch, timezone: string): string | null {
  const day = ISO_DAY[new Intl.DateTimeFormat('en-US', { weekday: 'short', timeZone: timezone }).format(new Date())];
  const today = branch.opening_hours.filter((h) => h.weekday === day);

  return today.length ? today.map((h) => `${formatClock(h.opens_at)} تا ${formatClock(h.closes_at)}`).join('، ') : null;
}

function openLabel(branch: StoreBranch, timezone: string): string {
  if (branch.is_open) return 'باز است';
  if (!branch.next_opening_at) return 'بسته است';
  const next = new Date(branch.next_opening_at);
  const sameDay = formatJalaliDate(next, timezone) === formatJalaliDate(new Date(), timezone);

  return `بسته است • باز می‌شود ${sameDay ? `ساعت ${formatTime(next, timezone)}` : formatJalaliDateTime(next, timezone)}`;
}

/** The menu home: which branch, open or closed, and the full menu (cached HTML; cart loads on the client). */
export default async function StorefrontHome({ params, searchParams }: PageProps<'/s/[tenant]'>) {
  const { tenant } = await params;
  const query = await searchParams;
  const store = await getStorefront(tenant);
  if (!store) notFound();

  const branch = pickBranch(store.branches, query.branch);
  const [menu, stories] = await Promise.all([
    branch ? getMenu(tenant, branch.slug) : Promise.resolve(null),
    getStories(tenant, branch?.slug),
  ]);

  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CafeOrCoffeeShop',
    name: store.name,
    url: storeUrl(tenant),
    hasMenu: storeUrl(tenant),
    servesCuisine: 'Cafe',
    ...(store.branding?.logo_url ? { logo: store.branding.logo_url, image: store.branding.logo_url } : {}),
    ...(store.contact.phone ? { telephone: store.contact.phone } : {}),
    ...(branch?.address ? { address: { '@type': 'PostalAddress', streetAddress: branch.address, addressLocality: branch.city ?? undefined, addressCountry: 'IR' } } : {}),
    ...(branch?.latitude != null ? { geo: { '@type': 'GeoCoordinates', latitude: branch.latitude, longitude: branch.longitude } } : {}),
    ...(branch?.opening_hours.length ? {
      openingHoursSpecification: branch.opening_hours.map((h) => ({ '@type': 'OpeningHoursSpecification', dayOfWeek: `https://schema.org/${DAY[h.weekday]}`, opens: h.opens_at, closes: h.closes_at })),
    } : {}),
  };

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, '\\u003c') }} />

      <section className="pt-4">
        {query.qr === 'invalid' ? (
          <div className="mb-4"><Alert tone="warning" title="کد این میز معتبر نیست">کد QR روی میز را دوباره اسکن کنید یا از گارسون کمک بگیرید.</Alert></div>
        ) : null}

        {/* Hero: the café's cover photo (or its colour with a quiet pattern) and the essentials. */}
        <div className="relative isolate overflow-hidden rounded-[1.75rem] shadow-[var(--shadow-md)]">
          {store.branding?.cover_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
            <img src={store.branding.cover_url} alt="" fetchPriority="high" className="absolute inset-0 -z-10 size-full object-cover" />
          ) : <StoreHeroArt name={store.name} />}
          <div aria-hidden="true" className={cx('absolute inset-0 -z-10 bg-gradient-to-t', store.branding?.cover_url ? 'from-scrim via-scrim/40 to-scrim/5' : 'from-scrim/55 via-transparent to-transparent')} />

          <div className="flex min-h-52 flex-col justify-end gap-3 p-5 pt-10 text-on-media sm:min-h-64 sm:p-7">
            {store.branding?.logo_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
              <img src={store.branding.logo_url} alt="" className="size-16 rounded-2xl bg-surface object-contain p-1 shadow-[var(--shadow-lg)] ring-2 ring-on-media/40 sm:size-20" />
            ) : (
              <span aria-hidden="true" className="glass-light flex size-16 items-center justify-center rounded-2xl text-3xl font-black shadow-[var(--shadow-lg)] sm:size-20 sm:text-4xl">{initialOf(store.name)}</span>
            )}
            <h1 className="text-3xl font-black leading-tight [text-shadow:0_2px_12px_rgb(0_0_0/0.35)] sm:text-4xl">{store.name}</h1>
            {store.branding?.seo_description ? <p className="line-clamp-2 max-w-xl text-sm leading-6 text-on-media-muted">{store.branding.seo_description}</p> : null}
            {branch ? (
              <ul className="flex flex-wrap gap-2 text-xs font-medium">
                <li className="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1.5 text-text shadow-[var(--shadow-sm)]">
                  <span aria-hidden="true" className={cx('size-2 rounded-full', branch.is_open ? 'bg-success' : 'bg-warning')} />
                  {openLabel(branch, store.timezone)}
                </li>
                {todayHours(branch, store.timezone) ? (
                  <li className="inline-flex items-center gap-1.5 glass-light rounded-full px-3 py-1.5"><Clock className="size-3.5" aria-hidden="true" />امروز {todayHours(branch, store.timezone)}</li>
                ) : null}
                <li className="inline-flex items-center gap-1.5 glass-light rounded-full px-3 py-1.5">
                  {branch.delivery ? <Bike className="size-3.5" aria-hidden="true" /> : <Store className="size-3.5" aria-hidden="true" />}
                  {branch.delivery ? 'ارسال با پیک و تحویل در کافه' : 'تحویل در کافه'}
                </li>
                {store.branches.length > 1 || branch.address ? (
                  <li className="inline-flex items-center gap-1.5 glass-light rounded-full px-3 py-1.5"><MapPin className="size-3.5" aria-hidden="true" />{branch.name}</li>
                ) : null}
              </ul>
            ) : null}
          </div>
        </div>

        {store.branches.length > 1 ? (
          <nav aria-label="انتخاب شعبه" className="no-scrollbar mt-4 flex gap-2 overflow-x-auto">
            {store.branches.map((b) => (
              <Link key={b.id} href={b === store.branches[0] ? `/s/${tenant}` : `/s/${tenant}?branch=${encodeURIComponent(b.slug)}`}
                aria-current={b.id === branch?.id ? 'page' : undefined}
                className={cx('inline-flex shrink-0 items-center gap-2 rounded-2xl border px-3.5 py-2 text-sm transition-colors',
                  b.id === branch?.id ? 'border-brand bg-brand-soft font-semibold' : 'border-border bg-surface text-text-muted hover:text-text')}>
                <span aria-hidden="true" className={cx('size-1.5 rounded-full', b.is_open ? 'bg-success' : 'bg-text-subtle')} />
                {b.name}
              </Link>
            ))}
          </nav>
        ) : null}

        {branch && !branch.is_open ? (
          <p className="mt-3 flex items-start gap-2 rounded-2xl bg-warning-soft px-4 py-3 text-sm text-warning">
            <Clock className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {store.features.preorder_when_closed ? 'الان بسته‌ایم، ولی می‌توانید برای ساعت‌های کاری پیش‌سفارش بدهید.' : 'الان سفارش نمی‌گیریم؛ منو را ببینید و در ساعت کاری برگردید.'}
          </p>
        ) : null}
      </section>

      {menu ? <MenuBrowser menu={menu} stories={stories} /> : (
        <p className="py-16 text-center text-text-muted">منوی این کافه هنوز منتشر نشده است.</p>
      )}
    </>
  );
}
