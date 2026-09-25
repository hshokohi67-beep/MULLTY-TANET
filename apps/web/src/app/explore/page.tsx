import type { Metadata } from 'next';
import Link from 'next/link';
import { Suspense, type ReactNode } from 'react';
import { ChevronLeft, ChevronRight, Compass, MapPin, SearchX, Sparkles } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber, toPersianDigits } from '@cafe/locale';
import { BrandStyles, CATEGORY_ICONS, StoreCard } from '@/components/explore/ExploreParts';
import { getMarketplaceHome, searchStores } from '@/lib/marketplace';
import type { StoreCard as Card } from '@/lib/marketplace-types';
import { ExploreFilters, ExploreSearch } from './ExploreControls';

export const metadata: Metadata = { title: 'کافه‌گردی: کافه‌ها و شیرینی‌فروشی‌های نزدیک' };

const KEYS = ['q', 'city', 'category', 'price', 'open_now', 'sort', 'lat', 'lng', 'page'] as const;

/** Marketplace home, or search results as soon as any filter is set. */
export default async function ExplorePage({ searchParams }: PageProps<'/explore'>) {
  const params = await searchParams;
  const query = new URLSearchParams();
  for (const key of KEYS) {
    const v = params[key];
    if (typeof v === 'string' && v.trim() !== '') query.set(key, v.trim().slice(0, 80));
  }
  const amenities = (Array.isArray(params['amenities[]']) ? params['amenities[]'] : typeof params['amenities[]'] === 'string' ? [params['amenities[]']] : []).slice(0, 5);
  amenities.forEach((a) => query.append('amenities[]', a));
  const searching = [...query.keys()].some((k) => k !== 'page');

  const home = await getMarketplaceHome();
  const results = searching ? await searchStores(query).catch(() => null) : null;
  const link = (patch: Record<string, string | null>) => {
    const next = new URLSearchParams(query);
    for (const [k, v] of Object.entries(patch)) {
      if (v === null) next.delete(k); else next.set(k, v);
    }
    if (!('page' in patch)) next.delete('page');
    const s = next.toString();

    return s ? `/explore?${s}` : '/explore';
  };
  const activeCategory = query.get('category');
  const all: Card[] = [...home.featured, ...home.popular, ...home.newest, ...(results?.data ?? [])];

  return (
    <div className="flex flex-col gap-10 pb-12">
      <BrandStyles stores={all} />
      <section className="relative overflow-hidden border-b border-border bg-gradient-to-b from-brand-soft/70 to-bg">
        <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 sm:px-6 sm:py-14">
          <div className="max-w-2xl">
            <p className="inline-flex items-center gap-1.5 rounded-full bg-surface/80 px-3 py-1 text-xs font-semibold text-brand-strong shadow-[var(--shadow-sm)]"><Compass className="size-3.5" aria-hidden="true" />{formatNumber(home.total)} کافه و شیرینی‌فروشی</p>
            <h1 className="mt-3 text-3xl font-black leading-tight sm:text-4xl">کافه‌ی بعدی‌تان را پیدا کنید</h1>
            <p className="mt-2 text-text-muted">منو، ساعت کاری و سفارش آنلاین؛ از قهوه‌ی تخصصی تا بستنی سنتی.</p>
          </div>
          <ExploreSearch q={query.get('q') ?? ''} city={query.get('city') ?? ''} cities={home.cities} />
          <nav aria-label="دسته‌ها" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
            {home.categories.map((c) => {
              const Icon = CATEGORY_ICONS[c.key];
              const active = activeCategory === c.key;

              return (
                <Link key={c.key} href={link({ category: active ? null : c.key })} aria-current={active ? 'true' : undefined}
                  className={cx('inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-sm shadow-[var(--shadow-sm)] transition-colors',
                    active ? 'border-brand bg-brand text-on-brand' : 'border-border bg-surface hover:border-brand')}>
                  {Icon ? <Icon className="size-4" aria-hidden="true" /> : null}{c.label}
                  <span className={cx('text-xs', active ? 'opacity-80' : 'text-text-subtle')}>{toPersianDigits(c.count)}</span>
                </Link>
              );
            })}
          </nav>
        </div>
      </section>

      <div className="mx-auto flex w-full max-w-6xl flex-col gap-10 px-4 sm:px-6">
        {searching ? (
          <section aria-labelledby="results" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <h2 id="results" className="text-xl font-bold">
                {results ? `${formatNumber(results.meta.total)} نتیجه` : 'جست‌وجو انجام نشد'}
                {query.get('city') ? <span className="text-base font-normal text-text-muted"> در {query.get('city')}</span> : null}
              </h2>
              <Link href="/explore" className="text-sm text-brand hover:underline">پاک کردن فیلترها</Link>
            </div>
            <Suspense>
              <ExploreFilters amenities={home.amenities} selected={amenities} price={query.get('price') ?? ''} openNow={query.get('open_now') === '1'} sort={query.get('sort') ?? ''} />
            </Suspense>
            {results && results.data.length ? (
              <>
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                  {results.data.map((s, i) => <StoreCard key={`${s.store}-${s.branch}`} s={s} priority={i < 3} />)}
                </div>
                {results.meta.last_page > 1 ? (
                  <nav aria-label="صفحه‌ها" className="flex items-center justify-center gap-2">
                    {results.meta.page > 1 ? <Link href={link({ page: String(results.meta.page - 1) })} className="flex size-10 items-center justify-center rounded-lg border border-border bg-surface" aria-label="صفحه‌ی قبل"><ChevronRight className="size-4" /></Link> : null}
                    <span className="text-sm text-text-muted">صفحه‌ی {toPersianDigits(results.meta.page)} از {toPersianDigits(results.meta.last_page)}</span>
                    {results.meta.page < results.meta.last_page ? <Link href={link({ page: String(results.meta.page + 1) })} className="flex size-10 items-center justify-center rounded-lg border border-border bg-surface" aria-label="صفحه‌ی بعد"><ChevronLeft className="size-4" /></Link> : null}
                  </nav>
                ) : null}
              </>
            ) : (
              <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-surface px-6 py-16 text-center">
                <span className="flex size-14 items-center justify-center rounded-2xl bg-surface-muted text-text-subtle"><SearchX className="size-7" aria-hidden="true" /></span>
                <p className="font-semibold">چیزی با این مشخصات پیدا نشد</p>
                <p className="max-w-sm text-sm text-text-muted">فیلترها را کمتر کنید یا شهر دیگری را امتحان کنید.</p>
              </div>
            )}
          </section>
        ) : (
          <>
            {home.featured.length ? <Row title="ویژه‌ها" icon={<Sparkles className="size-5 text-brand" aria-hidden="true" />} stores={home.featured} /> : null}
            {home.popular.length ? <Row title="محبوب‌ها" stores={home.popular} /> : null}
            {home.cities.length ? (
              <section aria-labelledby="cities" className="flex flex-col gap-4">
                <h2 id="cities" className="text-xl font-bold">شهرها</h2>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                  {home.cities.map((c) => (
                    <Link key={c.city} href={link({ city: c.city })} className="flex items-center gap-3 rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)] transition-colors hover:border-brand">
                      <span className="flex size-10 items-center justify-center rounded-xl bg-brand-soft text-brand"><MapPin className="size-5" aria-hidden="true" /></span>
                      <span><span className="block font-semibold">{c.city}</span><span className="text-xs text-text-muted">{toPersianDigits(c.count)} کافه</span></span>
                    </Link>
                  ))}
                </div>
              </section>
            ) : null}
            {/* With few cafés the newest row would just repeat the popular one. */}
            {home.total > 8 && home.newest.length ? <Row title="تازه به کافه‌گردی پیوسته‌اند" stores={home.newest} /> : null}
            {home.total === 0 ? (
              <div className="rounded-2xl border border-dashed border-border bg-surface px-6 py-16 text-center">
                <p className="font-semibold">به‌زودی کافه‌ها اینجا معرفی می‌شوند</p>
                <p className="mt-1 text-sm text-text-muted">کافه‌داران می‌توانند از پنل، بخش «بازارگاه»، کافه‌شان را معرفی کنند.</p>
              </div>
            ) : null}
          </>
        )}
      </div>
    </div>
  );
}

function Row({ title, icon, stores }: { title: string; icon?: ReactNode; stores: Card[] }) {
  return (
    <section className="flex flex-col gap-4" aria-label={title}>
      <h2 className="flex items-center gap-2 text-xl font-bold">{icon}{title}</h2>
      <div className="-mx-4 grid auto-cols-[82%] grid-flow-col gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:auto-cols-auto sm:grid-flow-row sm:grid-cols-2 sm:px-0 lg:grid-cols-4">
        {stores.map((s, i) => <StoreCard key={`${title}-${s.store}`} s={s} priority={i < 2} />)}
      </div>
    </section>
  );
}
