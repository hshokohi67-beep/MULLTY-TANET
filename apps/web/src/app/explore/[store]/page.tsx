import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { createElement } from 'react';
import { ArrowRight, BadgePercent, Bike, Leaf, Clock, Coffee, CreditCard, MapPin, Navigation, Phone, ShoppingBag, Sparkles, Timer, UtensilsCrossed } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatMoney, formatPhone, toPersianDigits } from '@cafe/locale';
import { CoverArt, Logo } from '@/components/explore/Art';
import { AMENITY_ICONS, BrandStyles, OpenPill, PriceLevel, StoreCard } from '@/components/explore/ExploreParts';
import { Rail } from '@/components/explore/Showcase';
import { illustrationFor } from '@/components/store/ProductVisuals';
import { FavoriteButton, ShareButton } from '@/components/explore/ExploreClient';
import { getStoreProfile } from '@/lib/marketplace';
import { storeHref } from '@/lib/store-links';
import { PRICE_LABELS } from '@/lib/marketplace-types';

export async function generateMetadata({ params }: PageProps<'/explore/[store]'>): Promise<Metadata> {
  const s = await getStoreProfile((await params).store);
  if (!s) return { title: 'پیدا نشد' };
  const city = s.branches[0]?.city ?? '';

  return {
    title: `${s.name}${city ? ` در ${city}` : ''}`,
    description: s.headline ?? s.about ?? `${s.name}: منو، ساعت کاری و سفارش آنلاین`,
    openGraph: { title: s.name, description: s.headline ?? undefined, images: s.cover_url ? [s.cover_url] : s.logo_url ? [s.logo_url] : undefined },
  };
}

const SERVICES = [
  ['dine_in', 'سرو در سالن', UtensilsCrossed],
  ['takeaway', 'بیرون‌بر', ShoppingBag],
  ['delivery', 'ارسال با پیک', Bike],
  ['online_payment', 'پرداخت آنلاین', CreditCard],
  ['preorder', 'پیش‌سفارش', Timer],
] as const;

/** A café's public profile in the marketplace: what it is, where, when, and a way into its menu. */
export default async function StorePage({ params }: PageProps<'/explore/[store]'>) {
  const s = await getStoreProfile((await params).store);
  if (!s) notFound();
  const todayIso = ((new Date().getUTCDay() + 6) % 7) + 1; // ISO weekday (the tenant is in Iran; good enough for highlighting)
  const open = s.branches.some((b) => b.is_open);

  return (
    <div data-brand={s.store} className="pb-28 sm:pb-16">
      <BrandStyles stores={[s, ...s.similar]} />
      <div className="relative h-60 overflow-hidden bg-brand-soft sm:h-80">
        {s.cover_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
          <img src={s.cover_url} alt="" className="size-full object-cover" />
        ) : (
          <CoverArt store={s.store} name={s.name} category={s.categories[0]?.key} size="lg" />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-scrim/60 via-transparent to-scrim/20" aria-hidden="true" />
        <Link href="/explore" className="glass absolute start-4 top-4 inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium"><ArrowRight className="size-4" aria-hidden="true" />خوراک‌گردی</Link>
      </div>

      <div className="mx-auto -mt-14 flex max-w-5xl flex-col gap-8 px-4 sm:px-6">
        <section className="relative flex flex-col gap-4 rounded-3xl border border-border bg-surface p-5 shadow-[var(--shadow-lg)] sm:flex-row sm:items-end sm:p-6">
          <Logo url={s.logo_url} name={s.name} className="-mt-14 size-24 shrink-0 rounded-3xl border-4 border-surface text-4xl shadow-[var(--shadow-lg)] sm:mt-0 sm:size-28" />
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-2xl font-black sm:text-3xl">{s.name}</h1>
              {s.is_featured ? <span className="inline-flex items-center gap-1 rounded-full bg-brand px-2.5 py-1 text-xs font-semibold text-on-brand"><Sparkles className="size-3" aria-hidden="true" />ویژه</span> : null}
            </div>
            {s.headline ? <p className="mt-1 text-text-muted">{s.headline}</p> : null}
            <p className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-text-muted">
              {s.categories.map((c) => <span key={c.key}>{c.label}</span>)}
              {s.price_level ? <span className="inline-flex items-center gap-1.5">• <PriceLevel level={s.price_level} />{PRICE_LABELS[s.price_level]}</span> : null}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <FavoriteButton store={s.store} name={s.name} className="size-12 border border-border" />
            <ShareButton title={s.name} />
            <Link href={storeHref(s.storefront_path)} className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-brand px-6 font-semibold text-on-brand shadow-[var(--shadow-md)] hover:bg-brand-strong sm:flex-none">
              <Coffee className="size-5" aria-hidden="true" />دیدن منو و سفارش
            </Link>
          </div>
        </section>

        {s.offers.length ? (
          <section aria-label="پیشنهادهای ویژه" className="flex flex-wrap gap-2">
            {s.offers.map((o) => (
              <span key={o} className="inline-flex items-center gap-2 rounded-xl border border-danger/30 bg-danger-soft px-3.5 py-2 text-sm font-semibold text-danger">
                <BadgePercent className="size-4" aria-hidden="true" />{o}
              </span>
            ))}
          </section>
        ) : null}

        <ul className="flex flex-wrap gap-2" aria-label="خدمات">
          {SERVICES.filter(([key]) => s.services[key]).map(([key, label, SIcon]) => (
            <li key={key} className="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-3.5 py-2 text-sm"><SIcon className="size-4 text-brand" aria-hidden="true" />{label}</li>
          ))}
          {s.dietary.map((d) => (
            <li key={d.key} className="inline-flex items-center gap-2 rounded-full border border-success/30 bg-success-soft px-3.5 py-2 text-sm text-success"><Leaf className="size-4" aria-hidden="true" />{d.label}</li>
          ))}
        </ul>

        <div className="grid gap-8 lg:grid-cols-[3fr_2fr]">
          <div className="flex flex-col gap-8">
            {s.about ? (
              <section aria-labelledby="about">
                <h2 id="about" className="mb-2 text-lg font-bold">درباره‌ی {s.name}</h2>
                <p className="leading-8 text-text-muted">{s.about}</p>
              </section>
            ) : null}
            {s.highlights.length ? (
              <section aria-labelledby="menu" className="flex flex-col gap-3">
                <div className="flex items-end justify-between">
                  <h2 id="menu" className="text-lg font-bold">از منو</h2>
                  <Link href={storeHref(s.storefront_path)} className="text-sm font-medium text-brand hover:underline">منوی کامل</Link>
                </div>
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                  {s.highlights.map((h) => (
                    <li key={h.name} className="group overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-sm)]">
                      <div className="aspect-square bg-surface-muted">
                        {h.image_url ? (
                          // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
                          <img src={h.image_url} alt={h.name} loading="lazy" className="size-full object-cover transition-transform duration-500 group-hover:scale-105" />
                        )
                          : <div className="flex size-full items-center justify-center bg-brand-soft text-brand">{createElement(illustrationFor(h.name), { className: 'size-12 opacity-80', strokeWidth: 1.25, 'aria-hidden': true })}</div>}
                      </div>
                      <div className="p-3">
                        <p className="line-clamp-1 text-sm font-semibold">{h.name}</p>
                        {h.price_from !== null ? <p className="tabular text-xs text-text-muted">{formatMoney(h.price_from)}</p> : null}
                      </div>
                    </li>
                  ))}
                </ul>
              </section>
            ) : null}
            {s.amenities.length ? (
              <section aria-labelledby="amenities">
                <h2 id="amenities" className="mb-3 text-lg font-bold">امکانات</h2>
                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                  {s.amenities.map((a) => {
                    const AIcon = AMENITY_ICONS[a.key];

                    return <li key={a.key} className="flex items-center gap-2 rounded-xl bg-surface-muted px-3 py-2.5 text-sm">{AIcon ? <AIcon className="size-4 text-brand" aria-hidden="true" /> : null}{a.label}</li>;
                  })}
                </ul>
              </section>
            ) : null}
          </div>

          <aside aria-label="شعبه‌ها" className="flex flex-col gap-4">
            {s.branches.map((b) => {
              const today = b.hours.filter((h) => h.weekday === todayIso);
              const map = b.latitude !== null && b.longitude !== null ? `https://www.google.com/maps/dir/?api=1&destination=${b.latitude},${b.longitude}` : null;

              return (
                <section key={b.slug} className="flex flex-col gap-3 rounded-2xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)]">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <h3 className="font-bold">{s.branches.length > 1 ? b.name : 'نشانی و ساعت کاری'}</h3>
                      <p className="text-xs text-text-muted">{[b.district, b.city, b.province !== b.city ? b.province : null].filter(Boolean).join('، ')}</p>
                    </div>
                    <OpenPill isOpen={b.is_open} next={b.next_opening_at} />
                  </div>
                  {b.address ? <p className="flex items-start gap-2 text-sm"><MapPin className="mt-0.5 size-4 shrink-0 text-text-subtle" aria-hidden="true" />{b.address}</p> : null}
                  <p className="flex items-center gap-2 text-sm"><Clock className="size-4 text-text-subtle" aria-hidden="true" />
                    امروز: {today.length ? today.map((h) => `${toPersianDigits(h.opens_at)} تا ${toPersianDigits(h.closes_at)}`).join(' و ') : b.hours.length ? 'تعطیل' : 'باز (ساعت کاری ثبت نشده)'}
                  </p>
                  {b.hours.length ? (
                    <details className="text-sm">
                      <summary className="cursor-pointer text-xs font-medium text-brand">ساعت کاری هفته</summary>
                      <ul className="mt-2 flex flex-col gap-1">
                        {b.hours.map((h, i) => (
                          <li key={i} className={cx('flex justify-between gap-2 rounded-md px-2 py-1', h.weekday === todayIso && 'bg-brand-soft font-semibold')}>
                            <span>{h.label}</span><span className="tabular">{toPersianDigits(h.opens_at)}–{toPersianDigits(h.closes_at)}</span>
                          </li>
                        ))}
                      </ul>
                    </details>
                  ) : null}
                  <div className="flex gap-2">
                    {b.phone ? <a href={`tel:${b.phone}`} className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border text-sm hover:bg-surface-muted"><Phone className="size-4" aria-hidden="true" /><span dir="ltr">{formatPhone(b.phone)}</span></a> : null}
                    {map ? <a href={map} target="_blank" rel="noopener noreferrer" className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border border-border text-sm hover:bg-surface-muted"><Navigation className="size-4" aria-hidden="true" />مسیریابی</a> : null}
                  </div>
                </section>
              );
            })}
          </aside>
        </div>

        {s.similar.length ? (
          <section aria-labelledby="similar" className="flex flex-col gap-4">
            <h2 id="similar" className="text-xl font-black">کافه‌های مشابه</h2>
            <Rail label="کافه‌های مشابه">{s.similar.map((x) => <StoreCard key={x.store} s={x} />)}</Rail>
          </section>
        ) : null}
      </div>

      {/* Phones: the way in stays at hand while scrolling. */}
      <div className="glass fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 sm:hidden">
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-bold">{s.name}</p>
          <p className={cx('text-xs', open ? 'text-success' : 'text-text-muted')}>{open ? 'همین حالا باز است' : 'فعلاً بسته است'}</p>
        </div>
        <Link href={storeHref(s.storefront_path)} className="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-bold text-on-brand shadow-[var(--shadow-md)]">
          <Coffee className="size-4" aria-hidden="true" />منو و سفارش
        </Link>
      </div>
    </div>
  );
}
