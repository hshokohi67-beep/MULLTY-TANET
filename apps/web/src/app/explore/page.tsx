import type { Metadata } from 'next';
import Link from 'next/link';
import { Suspense, type ReactNode } from 'react';
import {
  ArrowLeft, BadgePercent, Bike, ChevronLeft, ChevronRight, Clock, Compass, CreditCard, EggFried, Laptop, Leaf, List, Map as MapIcon, Moon, PiggyBank,
  SearchX, Sparkles, Timer, Trees, Trophy, Truck, WheatOff, type LucideIcon,
} from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatNumber, toPersianDigits } from '@cafe/locale';
import { HeroPattern, TONES, type Tone } from '@/components/explore/Art';
import { FavoritesChip, LocationBar, ResultsMap, SearchBox } from '@/components/explore/ExploreClient';
import { BrandStyles, CategoryTile, CityTile, RankItem, SpotlightCard, StoreCard } from '@/components/explore/ExploreParts';
import { BannerCarousel, Rail } from '@/components/explore/Showcase';
import { getMarketplaceHome, searchStores } from '@/lib/marketplace';
import type { StoreCard as Card } from '@/lib/marketplace-types';
import { ExploreFilters } from './ExploreControls';
import { HeroCollage } from './HeroCollage';

export const metadata: Metadata = { title: 'خوراک‌گردی: کافه‌ها، رستوران‌ها و شیرینی‌فروشی‌های نزدیک' };

const LOCATION = ['province', 'city', 'district'] as const;
const SINGLE = ['q', ...LOCATION, 'category', 'price', 'open_now', 'sort', 'lat', 'lng', 'page',
  'featured', 'offers', 'delivery', 'free_delivery', 'online_payment', 'preorder', 'late', 'new'] as const;
const LISTS = ['amenities', 'dietary', 'stores'] as const;

/** Quick filters: one tap each, all combinable. */
const QUICK: { key: string; value?: string; label: string; icon: LucideIcon; list?: 'dietary' }[] = [
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

/** Look of each curated row. */
const COLLECTION_LOOK: Record<string, { icon: LucideIcon; tone: Tone }> = {
  work: { icon: Laptop, tone: 'info' }, offers: { icon: BadgePercent, tone: 'danger' }, late: { icon: Moon, tone: 'brand' },
  budget: { icon: PiggyBank, tone: 'success' }, breakfast: { icon: EggFried, tone: 'warning' }, outdoor: { icon: Trees, tone: 'success' },
};

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
  const sponsored = results?.sponsored ?? [];
  const banners = results ? results.banners : home.banners;
  const all: Card[] = [...home.featured, ...home.popular, ...home.newest, ...home.collections.flatMap((c) => c.stores), ...(results?.data ?? []), ...sponsored];
  const cityCount = home.places.reduce((n, p) => n + p.cities.length, 0);
  const home_ = !filtering && !located;
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
    <div className="flex flex-col pb-16">
      <BrandStyles stores={[...all, ...banners]} />

      {/* Hero: layered glow + faint café pattern, search and place, a collage of real cafés. */}
      <section className="relative overflow-hidden border-b border-border">
        <div aria-hidden="true" className="absolute inset-0 bg-[radial-gradient(ellipse_80%_70%_at_100%_0%,var(--color-brand-soft),transparent_65%),radial-gradient(ellipse_60%_60%_at_0%_100%,var(--color-accent-soft),transparent_60%)]" />
        <HeroPattern />
        <div className={cx('relative mx-auto grid max-w-6xl items-center gap-8 px-4 sm:px-6', home_ ? 'py-8 sm:py-12 lg:grid-cols-[1.2fr_0.8fr] lg:py-16' : 'py-7 sm:py-9')}>
          <div className="flex min-w-0 flex-col gap-5">
            <p className="inline-flex w-fit items-center gap-2 rounded-full border border-border bg-surface/80 px-3 py-1.5 text-xs font-semibold text-text-muted shadow-[var(--shadow-sm)] backdrop-blur-sm">
              <span className="flex size-5 items-center justify-center rounded-full bg-brand text-on-brand"><Compass className="size-3" aria-hidden="true" /></span>
              {formatNumber(home.total)} کافه، رستوران و شیرینی‌فروشی در {toPersianDigits(cityCount)} شهر
            </p>
            <h1 className="text-[2rem] font-black leading-[1.25] [text-wrap:balance] sm:text-5xl sm:leading-[1.2]">
              {where ? <>خوراک‌گردی در <Accent>{where}</Accent></> : <>جای خوشمزه‌ی بعدی را <Accent>پیدا کنید</Accent></>}
            </h1>
            <p className="max-w-xl text-text-muted sm:text-lg">منو، ساعت کاری، تخفیف‌ها و سفارش آنلاین؛ از قهوه‌ی تخصصی و صبحانه تا غذای گرم، شیرینی و بستنی سنتی.</p>
            <Suspense>
              <SearchBox q={query.get('q') ?? ''} />
            </Suspense>
            <Suspense>
              <LocationBar places={home.places} province={query.get('province') ?? ''} city={query.get('city') ?? ''} district={query.get('district') ?? ''} />
            </Suspense>
            {home_ && home.total > 0 ? (
              <ul className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-text-muted" aria-label="در یک نگاه">
                <li className="inline-flex items-center gap-2">
                  <span className="relative flex size-2.5" aria-hidden="true"><span className="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-50" /><span className="relative inline-flex size-2.5 rounded-full bg-success" /></span>
                  <span><b className="text-text">{toPersianDigits(home.open_now)}</b> مکان همین حالا باز است</span>
                </li>
                <li className="inline-flex items-center gap-2"><Sparkles className="size-4 text-accent" aria-hidden="true" />سفارش آنلاین، بدون واسطه</li>
              </ul>
            ) : null}
          </div>
          {home_ ? <HeroCollage stores={(home.featured.length >= 3 ? home.featured : [...home.featured, ...home.popular.filter((p) => !home.featured.some((f) => f.store === p.store))]).slice(0, 3)} /> : null}
        </div>
      </section>

      {/* Categories as big round tiles. */}
      {home.categories.length ? (
        <nav aria-label="دسته‌ها" className="mx-auto w-full max-w-6xl">
          <div className="flex gap-2 overflow-x-auto px-4 py-6 [scrollbar-width:none] sm:gap-3 sm:px-6 [&::-webkit-scrollbar]:hidden">
            {home.categories.map((c) => (
              <CategoryTile key={c.key} category={c.key} label={c.label} count={c.count} href={link({ category: activeCategory === c.key ? null : c.key })} active={activeCategory === c.key} />
            ))}
          </div>
        </nav>
      ) : null}

      <div className="mx-auto flex w-full max-w-6xl flex-col gap-12 px-4 sm:px-6">
        {home_ || (results && banners.length > 0 && results.meta.page === 1) ? (
          <BannerCarousel banners={banners} house={home_ && banners.length < 2} />
        ) : null}

        <nav aria-label="فیلتر سریع" className="sticky top-16 z-20 -mx-4 -my-6 flex gap-2 overflow-x-auto border-b border-border bg-bg/85 px-4 py-3 backdrop-blur-md [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:rounded-2xl sm:border sm:px-3 [&::-webkit-scrollbar]:hidden">
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
              <h2 id="results" className="text-xl font-black sm:text-2xl">
                {fav ? 'علاقه‌مندی‌های شما' : `${formatNumber(results.meta.total)} مکان`}
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
            {results.data.length === 0 && sponsored.length === 0 ? (
              <div className="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-border bg-surface px-6 py-16 text-center">
                <span className="flex size-16 items-center justify-center rounded-3xl bg-surface-muted text-text-subtle"><SearchX className="size-8" aria-hidden="true" /></span>
                <p className="text-lg font-semibold">{fav ? 'هنوز کافه‌ای را نشان نکرده‌اید' : 'چیزی با این مشخصات پیدا نشد'}</p>
                <p className="max-w-sm text-sm text-text-muted">{fav ? 'روی قلب کارت‌ها بزنید تا اینجا جمع شوند.' : 'فیلترها را کمتر کنید یا محله‌ی دیگری را امتحان کنید.'}</p>
                {filtering && !fav ? <Link href={clearFilters()} className="mt-1 inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-on-brand hover:bg-brand-strong">پاک کردن فیلترها</Link> : null}
              </div>
            ) : view === 'map' ? (
              <ResultsMap stores={[...sponsored, ...results.data]} />
            ) : (
              <>
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                  {sponsored.map((s) => <StoreCard key={`ad-${s.store}`} s={s} priority />)}
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

        {/* The curated rows are platform-wide: under a chosen place they would mix in other cities. */}
        {home_ ? (
          <>
            {home.featured.length ? (
              <section aria-labelledby="featured" className="flex flex-col gap-5">
                <SectionHead id="featured" title="ویژه‌های خوراک‌گردی" subtitle="جاهایی که این روزها باید سر زد" icon={Sparkles} tone="accent" />
                {home.featured.length <= 3 ? (
                  <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{home.featured.map((s, i) => <SpotlightCard key={s.store} s={s} priority={i < 2} />)}</div>
                ) : (
                  <Rail label="ویژه‌ها" itemClass="w-[85%] sm:w-[calc((100%-1rem)/2)] lg:w-[calc((100%-2rem)/3)]">{home.featured.map((s) => <SpotlightCard key={s.store} s={s} />)}</Rail>
                )}
              </section>
            ) : null}

            {home.collections.map((c) => {
              const look = COLLECTION_LOOK[c.key] ?? { icon: Compass, tone: 'brand' as Tone };

              return (
                <section key={c.key} aria-labelledby={`c-${c.key}`} className="flex flex-col gap-5">
                  <SectionHead id={`c-${c.key}`} title={c.title} subtitle={c.subtitle} icon={look.icon} tone={look.tone} more={collectionHref(c.filter)} />
                  <Rail label={c.title}>{c.stores.map((s) => <StoreCard key={s.store} s={s} />)}</Rail>
                </section>
              );
            })}

            {home.popular.length >= 3 && !located ? (
              <section aria-labelledby="top" className="flex flex-col gap-5 rounded-3xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)] sm:p-6">
                <SectionHead id="top" title="برترین‌های خوراک‌گردی" subtitle="محبوب‌ترین‌ها بر اساس سفارش‌ها و بازدیدها" icon={Trophy} tone="warning" />
                <ol className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                  {home.popular.slice(0, 8).map((s, i) => <li key={s.store}><RankItem s={s} rank={i + 1} /></li>)}
                </ol>
              </section>
            ) : null}

            {home.places.length && !located ? (
              <section aria-labelledby="cities" className="flex flex-col gap-5">
                <SectionHead id="cities" title="خوراک‌گردی در شهرها" subtitle="شهرتان را انتخاب کنید" icon={Compass} tone="info" />
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                  {home.places.flatMap((p) => p.cities.map((c) => ({ ...c, province: p.province }))).map((c, i) => (
                    <CityTile key={`${c.province}-${c.city}`} city={c.city} count={c.count} districts={c.districts.length} index={i} imageUrl={c.image_url}
                      href={`/explore?province=${encodeURIComponent(c.province)}&city=${encodeURIComponent(c.city)}`} />
                  ))}
                </div>
              </section>
            ) : null}

            {home.total > 8 && home.newest.length && !located ? (
              <section aria-labelledby="newest" className="flex flex-col gap-5">
                <SectionHead id="newest" title="تازه به خوراک‌گردی پیوسته‌اند" icon={Compass} tone="brand" />
                <Rail label="تازه‌ها">{home.newest.map((s) => <StoreCard key={s.store} s={s} />)}</Rail>
              </section>
            ) : null}

            {home.total === 0 ? (
              <div className="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-border bg-surface px-6 py-16 text-center">
                <span className="flex size-16 items-center justify-center rounded-3xl bg-brand-soft text-brand"><Compass className="size-8" aria-hidden="true" /></span>
                <p className="text-lg font-semibold">به‌زودی فروشگاه‌ها اینجا معرفی می‌شوند</p>
                <p className="max-w-sm text-sm text-text-muted">صاحبان کسب‌وکار می‌توانند از پنل، بخش «بازارگاه»، فروشگاهشان را معرفی کنند.</p>
              </div>
            ) : !located ? (
              <OwnerBand />
            ) : null}
          </>
        ) : null}
      </div>
    </div>
  );
}

/** The key word of the headline, in the brand colour with a hand-drawn underline. */
function Accent({ children }: { children: ReactNode }) {
  return (
    <span className="relative inline-block text-brand">
      {children}
      <svg aria-hidden="true" viewBox="0 0 200 12" preserveAspectRatio="none" className="absolute -bottom-1.5 start-0 h-2.5 w-full text-accent">
        <path d="M2 9 C 40 3, 90 2, 198 6" fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
      </svg>
    </span>
  );
}

function SectionHead({ id, title, subtitle, icon: Icon, tone, more }: { id: string; title: string; subtitle?: string; icon: LucideIcon; tone: Tone; more?: string }) {
  return (
    <div className="flex items-end justify-between gap-3">
      <div className="flex items-center gap-3">
        <span className={cx('flex size-11 shrink-0 items-center justify-center rounded-2xl', TONES[tone])}><Icon className="size-5" aria-hidden="true" /></span>
        <div>
          <h2 id={id} className="text-xl font-black leading-tight sm:text-2xl">{title}</h2>
          {subtitle ? <p className="text-sm text-text-muted">{subtitle}</p> : null}
        </div>
      </div>
      {more ? (
        <Link href={more} className="inline-flex shrink-0 items-center gap-1 rounded-full border border-border bg-surface px-3 py-1.5 text-sm font-medium text-brand shadow-[var(--shadow-sm)] hover:border-brand">
          همه<ArrowLeft className="size-3.5" aria-hidden="true" />
        </Link>
      ) : null}
    </div>
  );
}

/** Calm call to action for café owners at the end of the home page. */
function OwnerBand() {
  return (
    <section className="relative overflow-hidden rounded-3xl bg-brand-strong px-6 py-10 text-on-brand shadow-[var(--shadow-lg)] sm:px-10">
      <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-br from-brand to-brand-strong" />
      <HeroPattern className="text-on-brand opacity-[0.1]" />
      <div className="relative flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-2xl font-black">کافه، رستوران یا شیرینی‌فروشی دارید؟</h2>
          <p className="mt-1 max-w-lg opacity-85">منوی آنلاین، سفارش از میز و پیک، باشگاه مشتریان و جایی در خوراک‌گردی؛ همه در کافه‌یار.</p>
        </div>
        <Link href="/login" className="inline-flex h-12 shrink-0 items-center gap-2 rounded-xl bg-on-brand px-6 font-bold text-brand-strong shadow-[var(--shadow-md)] hover:opacity-90">
          ورود به پنل کافه‌یار<ArrowLeft className="size-4" aria-hidden="true" />
        </Link>
      </div>
    </section>
  );
}
