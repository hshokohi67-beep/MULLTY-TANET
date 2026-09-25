import type { Metadata } from 'next';
import Link from 'next/link';
import { Suspense, type ReactNode } from 'react';
import {
  BadgePercent, Bike, ChevronLeft, ChevronRight, Clock, Compass, CreditCard, Leaf, List, Map as MapIcon, MapPin, Moon, PiggyBank, SearchX, Sparkles, Timer, Truck, WheatOff,
} from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber, toPersianDigits } from '@cafe/locale';
import { FavoritesChip, LocationBar, ResultsMap, SearchBox } from '@/components/explore/ExploreClient';
import { BrandStyles, CATEGORY_ICONS, StoreCard } from '@/components/explore/ExploreParts';
import { getMarketplaceHome, searchStores } from '@/lib/marketplace';
import type { StoreCard as Card } from '@/lib/marketplace-types';
import { ExploreFilters } from './ExploreControls';

export const metadata: Metadata = { title: 'کافه‌گردی: کافه‌ها و شیرینی‌فروشی‌های نزدیک' };

const LOCATION = ['province', 'city', 'district'] as const;
const SINGLE = ['q', ...LOCATION, 'category', 'price', 'open_now', 'sort', 'lat', 'lng', 'page',
  'featured', 'offers', 'delivery', 'free_delivery', 'online_payment', 'preorder', 'late', 'new'] as const;
const LISTS = ['amenities', 'dietary', 'stores'] as const;

/** Quick filters: one tap each, all combinable. */
const QUICK: { key: string; value?: string; label: string; icon: typeof Sparkles; list?: 'dietary' }[] = [
  { key: 'open_now', label: 'الان باز است', icon: Clock },
  { key: 'offers', label: 'تخفیف‌دار', icon: BadgePercent },
  { key: 'featured', label: 'ویژه', icon: Sparkles },
  { key: 'price', value: '1', label: 'اقتصادی', icon: PiggyBank },
  { key: 'delivery', label: 'ارسال با پیک', icon: Bike },
  { key: 'free_delivery', label: 'ارسال رایگان', icon: Truck },
  { key: 'online_payment', label: 'پرداخت آنلاین', icon: CreditCard },
  { key: 'preorder', label: 'پیش‌سفارش', icon: Timer },
  { key: 'late', label: 'تا دیروقت', icon: Moon },
  { key: 'new', label: 'تازه‌ها', icon: Compass },
  { key: 'vegan', label: 'گزینه‌ی گیاهی', icon: Leaf, list: 'dietary' },
  { key: 'gluten_free', label: 'بدون گلوتن', icon: WheatOff, list: 'dietary' },
];

/** Marketplace home, or results (list or map) as soon as a place or any filter is chosen. */
export default async function ExplorePage({ searchParams }: PageProps<'/explore'>) {
  const params = await searchParams;
  const query = new URLSearchParams();
  for (const key of SINGLE) {
    const v = params[key];
    if (typeof v === 'string' && v.trim() !== '') query.set(key, v.trim().slice(0, 80));
  }
  for (const key of LISTS) {
    const raw = params[`${key}[]`];
    const values = (Array.isArray(raw) ? raw : typeof raw === 'string' ? [raw] : []).slice(0, key === 'stores' ? 50 : 5);
    values.forEach((v) => query.append(`${key}[]`, v));
  }
  const view = params.view === 'map' ? 'map' : 'list';
  const fav = params.fav === '1';
  const filtering = [...query.keys()].some((k) => k !== 'page' && !(LOCATION as readonly string[]).includes(k)) || fav;
  const located = LOCATION.some((k) => query.get(k));

  const home = await getMarketplaceHome();
  const apiQuery = new URLSearchParams(query);
  if (view === 'map') apiQuery.set('per_page', '200');
  // Choosing a place (or any filter) opens that area's cafés right away.
  const results = filtering || located ? await searchStores(apiQuery).catch(() => null) : null;

  /** A link to this page with some parameters changed (view and favourites kept unless changed). */
  const link = (patch: Record<string, string | null>, opts: { list?: 'dietary' } = {}) => {
    const next = new URLSearchParams(query);
    for (const [k, v] of Object.entries(patch)) {
      if (opts.list) {
        const current = next.getAll(`${opts.list}[]`);
        next.delete(`${opts.list}[]`);
        (current.includes(k) ? current.filter((x) => x !== k) : [...current, k]).forEach((x) => next.append(`${opts.list}[]`, x));
      } else if (k !== 'view') {
        if (v === null) next.delete(k); else next.set(k, v);
      }
    }
    if (!('page' in patch)) next.delete('page');
    if (fav) next.set('fav', '1');
    const nextView = 'view' in patch ? patch.view : view === 'map' ? 'map' : null;
    if (nextView) next.set('view', nextView);
    const s = next.toString();

    return s ? `/explore?${s}` : '/explore';
  };
  const clearFilters = () => {
    const next = new URLSearchParams();
    LOCATION.forEach((k) => { const v = query.get(k); if (v) next.set(k, v); });
    if (view === 'map') next.set('view', 'map');

    return next.toString() ? `/explore?${next}` : '/explore';
  };
  const isOn = (q: (typeof QUICK)[number]) => (q.list ? query.getAll(`${q.list}[]`).includes(q.key) : query.get(q.key) === (q.value ?? '1'));
  const activeCategory = query.get('category');
  const where = [query.get('district'), query.get('city') ?? query.get('province')].filter(Boolean).join('، ');
  const all: Card[] = [...home.featured, ...home.popular, ...home.newest, ...home.collections.flatMap((c) => c.stores), ...(results?.data ?? [])];
  const chip = (on: boolean) => cx('inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors', on ? 'border-brand bg-brand text-on-brand' : 'border-border bg-surface text-text-muted hover:border-brand hover:text-text');
  const collectionHref = (filter: Record<string, string | string[]>) => {
    const p = new URLSearchParams();
    LOCATION.forEach((k) => { const v = query.get(k); if (v) p.set(k, v); });
    for (const [k, v] of Object.entries(filter)) {
      if (Array.isArray(v)) v.forEach((x) => p.append(`${k}[]`, x)); else p.set(k, v);
    }

    return `/explore?${p}`;
  };
  const viewTab = (on: boolean) => cx('inline-flex items-center gap-1.5 rounded-md px-3 py-1.5', on ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text');

  return (
    <div className="flex flex-col gap-10 pb-12">
      <BrandStyles stores={all} />
      <section className="relative border-b border-border bg-gradient-to-b from-brand-soft/70 to-bg">
        <div className="mx-auto flex max-w-6xl flex-col gap-5 px-4 py-8 sm:px-6 sm:py-12">
          <div className="max-w-2xl">
            <p className="inline-flex items-center gap-1.5 rounded-full bg-surface/80 px-3 py-1 text-xs font-semibold text-brand-strong shadow-[var(--shadow-sm)]"><Compass className="size-3.5" aria-hidden="true" />{formatNumber(home.total)} کافه و شیرینی‌فروشی</p>
            <h1 className="mt-3 text-3xl font-black leading-tight sm:text-4xl">{where ? `کافه‌های ${where}` : 'کافه‌ی بعدی‌تان را پیدا کنید'}</h1>
            <p className="mt-2 text-text-muted">منو، ساعت کاری، تخفیف‌ها و سفارش آنلاین؛ از قهوه‌ی تخصصی تا بستنی سنتی.</p>
          </div>
          <Suspense>
            <SearchBox q={query.get('q') ?? ''} />
          </Suspense>
          <Suspense>
            <LocationBar places={home.places} province={query.get('province') ?? ''} city={query.get('city') ?? ''} district={query.get('district') ?? ''} />
          </Suspense>
          <nav aria-label="دسته‌ها" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
            {home.categories.map((c) => {
              const Icon = CATEGORY_ICONS[c.key];
              const active = activeCategory === c.key;

              return (
                <Link key={c.key} href={link({ category: active ? null : c.key })} aria-current={active ? 'true' : undefined}
                  className={cx('inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-sm shadow-[var(--shadow-sm)] transition-colors', active ? 'border-brand bg-brand text-on-brand' : 'border-border bg-surface hover:border-brand')}>
                  {Icon ? <Icon className="size-4" aria-hidden="true" /> : null}{c.label}
                  <span className={cx('text-xs', active ? 'opacity-80' : 'text-text-subtle')}>{toPersianDigits(c.count)}</span>
                </Link>
              );
            })}
          </nav>
        </div>
      </section>

      <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 sm:px-6">
        <nav aria-label="فیلتر سریع" className="-mx-4 -mt-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
          <FavoritesChip active={fav} />
          {QUICK.map((q) => {
            const on = isOn(q);
            const Icon = q.icon;

            return (
              <Link key={q.key} href={q.list ? link({ [q.key]: null }, { list: q.list }) : link({ [q.key]: on ? null : (q.value ?? '1') })} aria-current={on ? 'true' : undefined} className={chip(on)}>
                <Icon className="size-4" aria-hidden="true" />{q.label}
              </Link>
            );
          })}
        </nav>

        {results ? (
          <section aria-labelledby="results" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <h2 id="results" className="text-xl font-bold">
                {fav ? 'علاقه‌مندی‌های شما' : `${formatNumber(results.meta.total)} کافه`}
                {where ? <span className="text-base font-normal text-text-muted"> در {where}</span> : null}
              </h2>
              <div className="flex items-center gap-3">
                {filtering ? <Link href={clearFilters()} className="text-sm text-brand hover:underline">پاک کردن فیلترها</Link> : null}
                <div className="flex rounded-lg bg-surface-muted p-0.5 text-sm" role="group" aria-label="نوع نمایش">
                  <Link href={link({ view: null })} aria-current={view === 'list' ? 'true' : undefined} className={viewTab(view === 'list')}><List className="size-4" aria-hidden="true" />فهرست</Link>
                  <Link href={link({ view: 'map' })} aria-current={view === 'map' ? 'true' : undefined} className={viewTab(view === 'map')}><MapIcon className="size-4" aria-hidden="true" />نقشه</Link>
                </div>
              </div>
            </div>
            <Suspense>
              <ExploreFilters amenities={home.amenities} selected={query.getAll('amenities[]')} sort={query.get('sort') ?? ''} />
            </Suspense>
            {results.data.length === 0 ? (
              <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-surface px-6 py-16 text-center">
                <span className="flex size-14 items-center justify-center rounded-2xl bg-surface-muted text-text-subtle"><SearchX className="size-7" aria-hidden="true" /></span>
                <p className="font-semibold">{fav ? 'هنوز کافه‌ای را نشان نکرده‌اید' : 'چیزی با این مشخصات پیدا نشد'}</p>
                <p className="max-w-sm text-sm text-text-muted">{fav ? 'روی قلب کارت‌ها بزنید تا اینجا جمع شوند.' : 'فیلترها را کمتر کنید یا محله‌ی دیگری را امتحان کنید.'}</p>
              </div>
            ) : view === 'map' ? (
              <ResultsMap stores={results.data} />
            ) : (
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
            )}
          </section>
        ) : null}

        {!filtering ? (
          <>
            {home.featured.length ? <Row title="ویژه‌ها" icon={<Sparkles className="size-5 text-brand" aria-hidden="true" />} stores={home.featured} /> : null}
            {home.collections.map((c) => <Row key={c.key} title={c.title} subtitle={c.subtitle} stores={c.stores} more={collectionHref(c.filter)} />)}
            {home.popular.length && !located ? <Row title="محبوب‌ها" stores={home.popular} /> : null}
            {home.places.length && !located ? (
              <section aria-labelledby="cities" className="flex flex-col gap-4">
                <h2 id="cities" className="text-xl font-bold">شهرها</h2>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                  {home.places.flatMap((p) => p.cities.map((c) => ({ ...c, province: p.province }))).map((c) => (
                    <Link key={`${c.province}-${c.city}`} href={`/explore?province=${encodeURIComponent(c.province)}&city=${encodeURIComponent(c.city)}`} className="flex items-center gap-3 rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)] transition-colors hover:border-brand">
                      <span className="flex size-10 items-center justify-center rounded-xl bg-brand-soft text-brand"><MapPin className="size-5" aria-hidden="true" /></span>
                      <span><span className="block font-semibold">{c.city}</span><span className="text-xs text-text-muted">{toPersianDigits(c.count)} کافه{c.districts.length ? ` • ${toPersianDigits(c.districts.length)} محله` : ''}</span></span>
                    </Link>
                  ))}
                </div>
              </section>
            ) : null}
            {home.total > 8 && home.newest.length && !located ? <Row title="تازه به کافه‌گردی پیوسته‌اند" stores={home.newest} /> : null}
            {home.total === 0 ? (
              <div className="rounded-2xl border border-dashed border-border bg-surface px-6 py-16 text-center">
                <p className="font-semibold">به‌زودی کافه‌ها اینجا معرفی می‌شوند</p>
                <p className="mt-1 text-sm text-text-muted">کافه‌داران می‌توانند از پنل، بخش «بازارگاه»، کافه‌شان را معرفی کنند.</p>
              </div>
            ) : null}
          </>
        ) : null}
      </div>
    </div>
  );
}

function Row({ title, subtitle, icon, stores, more }: { title: string; subtitle?: string; icon?: ReactNode; stores: Card[]; more?: string }) {
  return (
    <section className="flex flex-col gap-4" aria-label={title}>
      <div className="flex items-end justify-between gap-3">
        <div>
          <h2 className="flex items-center gap-2 text-xl font-bold">{icon}{title}</h2>
          {subtitle ? <p className="text-sm text-text-muted">{subtitle}</p> : null}
        </div>
        {more ? <Link href={more} className="shrink-0 text-sm font-medium text-brand hover:underline">همه</Link> : null}
      </div>
      <div className="-mx-4 grid auto-cols-[82%] grid-flow-col gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:auto-cols-auto sm:grid-flow-row sm:grid-cols-2 sm:px-0 lg:grid-cols-4">
        {stores.map((s, i) => <StoreCard key={`${title}-${s.store}`} s={s} priority={i < 2} />)}
      </div>
    </section>
  );
}
