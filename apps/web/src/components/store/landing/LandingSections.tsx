import type { ReactNode } from 'react';
import { AtSign, Clock, MapPin, Navigation, Phone } from 'lucide-react';
import { cx, Ltr } from '@cafe/ui';
import { formatClock, formatMoney } from '@cafe/locale';
import { MoodChip, ProductPhoto } from '@/components/store/ProductVisuals';
import type { LandingDesign, PublicLanding } from '@/lib/landing-types';
import type { Menu, MenuProduct, Storefront } from '@/lib/storefront-types';
import { LandingGallery } from './LandingGallery';
import { ArtLayer, Go, Heading, hoursToday, mapsHref, nth, todayIn } from './parts';

export interface SectionProps { tenant: string; store: Storefront; landing: PublicLanding; menu: Menu | null; preview?: boolean }

const Shell = ({ id, design, seed, tone, className, children }: { id: string; design: LandingDesign; seed: number; tone?: 'brand' | 'muted'; className?: string; children: ReactNode }) => (
  <section id={id} className={cx('l-texture overflow-hidden py-20 @xl:py-28', tone === 'brand' && 'bg-brand text-on-brand', tone === 'muted' && 'bg-surface-muted', className)}>
    <ArtLayer design={design} seed={seed} />
    <div className="mx-auto max-w-6xl px-5 @xl:px-8">{children}</div>
  </section>
);

const paragraphs = (text: string) => text.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean);

export function StorySection({ landing }: SectionProps) {
  const { story } = landing.content;
  const photo = landing.media.story_photo;
  if (!story.text && !story.title) return null;
  const withPhoto = story.variant !== 'text' && photo;

  if (!withPhoto) {
    return (
      <Shell id="story" design={landing.design} seed={1}>
        <div className="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
          <span aria-hidden="true" data-reveal className="l-display text-7xl leading-none text-brand">«</span>
          {story.title ? <h2 data-reveal className="l-display text-3xl @xl:text-5xl">{story.title}</h2> : null}
          {story.text ? paragraphs(story.text).map((p, i) => <p key={i} data-reveal style={nth(i + 1)} className="text-lg leading-9 text-text-muted @xl:text-xl">{p}</p>) : null}
        </div>
      </Shell>
    );
  }

  return (
    <Shell id="story" design={landing.design} seed={1}>
      <div className="grid items-center gap-10 @3xl:grid-cols-2 @3xl:gap-16">
        <div className={cx('flex flex-col gap-5', story.variant === 'photo_start' && '@3xl:order-last')}>
          {story.title ? <h2 data-reveal style={nth(1)} className="l-display text-3xl @xl:text-5xl">{story.title}</h2> : null}
          {story.text ? paragraphs(story.text).map((p, i) => <p key={i} data-reveal style={nth(i + 2)} className="text-lg leading-9 text-text-muted">{p}</p>) : null}
        </div>
        <div data-reveal style={nth(1)} className="l-card relative aspect-[4/5] overflow-hidden bg-surface-muted shadow-[var(--shadow-lg)]">
          {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage */}
          <img src={photo.url} alt={photo.caption ?? ''} loading="lazy" decoding="async" className="size-full object-cover transition-transform duration-1000 hover:scale-105" />
        </div>
      </div>
    </Shell>
  );
}

export function HighlightsSection({ landing }: SectionProps) {
  const { highlights } = landing.content;
  if (highlights.items.length === 0) return null;
  const bold = landing.design.template === 'bold';
  const card = cx('l-card flex flex-col justify-between gap-6 border p-7 @xl:p-9', bold ? 'border-on-brand/25 bg-on-brand/10' : 'border-border bg-surface/70');

  const value = (h: (typeof highlights.items)[number], size: string) => (
    <p className={cx('l-display leading-none', size)}>{h.value}{h.unit ? <span className={cx('ms-1 text-[0.45em]', bold ? 'text-on-brand/70' : 'text-text-subtle')}>{h.unit}</span> : null}</p>
  );

  return (
    <Shell id="highlights" design={landing.design} seed={2} tone={bold ? 'brand' : undefined}>
      <Heading title={highlights.title} />
      {highlights.variant === 'numbers' ? (
        <div className="flex flex-wrap justify-center gap-y-10">
          {highlights.items.map((h, i) => (
            <div key={i} data-reveal style={nth(i)} className={cx('flex min-w-40 flex-1 flex-col items-center gap-3 px-6 text-center', i > 0 && '@xl:border-s', bold ? 'border-on-brand/25' : 'border-border')}>
              {value(h, 'text-6xl @xl:text-7xl')}
              <p className={cx('max-w-52 leading-7', bold ? 'text-on-brand/85' : 'text-text-muted')}>{h.label}</p>
            </div>
          ))}
        </div>
      ) : (
        <div className={cx('grid gap-4', highlights.variant === 'bento' ? '@3xl:grid-cols-3 @3xl:auto-rows-[minmax(15rem,auto)]' : '@xl:grid-cols-2 @5xl:grid-cols-4')}>
          {highlights.items.map((h, i) => (
            <div key={i} data-reveal style={nth(i)} className={cx(card, highlights.variant === 'bento' && (i === 0 || i === 3) && '@3xl:col-span-2')}>
              {value(h, highlights.variant === 'bento' && (i === 0 || i === 3) ? 'text-7xl @xl:text-8xl' : 'text-6xl')}
              <p className={cx('text-lg leading-8', bold ? 'text-on-brand/85' : 'text-text-muted')}>{h.label}</p>
            </div>
          ))}
        </div>
      )}
    </Shell>
  );
}

/** The café's picks, or (when none were picked) its «پیشنهاد ما» items, then the first dishes. */
function featuredProducts(landing: PublicLanding, menu: Menu | null): MenuProduct[] {
  const products = menu?.products ?? [];
  const picked = landing.content.featured.product_ids.map((id) => products.find((p) => p.id === id)).filter((p): p is MenuProduct => p !== undefined);
  if (picked.length) return picked;
  const featured = products.filter((p) => p.is_featured && p.is_available);

  return (featured.length ? featured : products.filter((p) => p.is_available)).slice(0, 4);
}

const price = (p: MenuProduct) => (p.variants.length > 1 ? `از ${formatMoney(p.price_from)}` : formatMoney(p.price_from));

export function FeaturedSection({ tenant, landing, menu, preview }: SectionProps) {
  const products = featuredProducts(landing, menu);
  if (products.length === 0) return null;
  const { variant, title } = landing.content.featured;
  const categoryOf = (p: MenuProduct) => menu?.categories.find((c) => p.category_ids.includes(c.id))?.name ?? null;
  const href = (p: MenuProduct) => `/s/${tenant}/p/${encodeURIComponent(p.slug)}`;

  return (
    <Shell id="featured" design={landing.design} seed={3}>
      <Heading eyebrow="از منوی ما" title={title} center={variant !== 'showcase'} />

      {variant === 'showcase' ? (
        <div className="flex flex-col gap-20 @xl:gap-28">
          {products.map((p, i) => (
            <Go key={p.id} href={href(p)} preview={preview} className={cx('group grid items-center gap-8 @3xl:grid-cols-2 @3xl:gap-16')}>
              <div data-reveal data-mood={p.temperature ?? undefined} className={cx('l-card relative aspect-[4/5] overflow-hidden shadow-[var(--shadow-lg)]', i % 2 === 1 && '@3xl:order-last')}>
                <ProductPhoto product={p} sizes="tile" className="size-full transition-transform duration-1000 group-hover:scale-105" />
              </div>
              <div data-reveal style={nth(1)} className="flex flex-col gap-4">
                {categoryOf(p) ? <span className="l-eyebrow text-text-subtle">{categoryOf(p)}</span> : null}
                <h3 className="l-display text-3xl @xl:text-5xl">{p.name}</h3>
                {p.description ? <p className="max-w-lg text-lg leading-8 text-text-muted">{p.description}</p> : null}
                <div className="flex items-center gap-3">
                  <span className="text-lg font-semibold tabular">{price(p)}</span>
                  <MoodChip mood={p.temperature} />
                </div>
                <span aria-hidden="true" className="h-px w-12 bg-border-strong transition-all duration-500 group-hover:w-24 group-hover:bg-brand" />
              </div>
            </Go>
          ))}
        </div>
      ) : (
        <div className={cx(variant === 'grid' ? 'grid grid-cols-2 gap-4 @5xl:grid-cols-3' : 'no-scrollbar -mx-5 flex snap-x snap-mandatory gap-4 overflow-x-auto px-5 pb-3 @xl:-mx-8 @xl:px-8')}>
          {products.map((p, i) => (
            <Go key={p.id} href={href(p)} preview={preview}
              className={cx('group flex flex-col gap-3', variant === 'carousel' && 'w-60 shrink-0 snap-start @xl:w-72')}>
              <div data-reveal style={nth(i % 3)} data-mood={p.temperature ?? undefined} className="l-card relative aspect-square overflow-hidden shadow-[var(--shadow-md)]">
                <ProductPhoto product={p} sizes="tile" className="size-full transition-transform duration-700 group-hover:scale-105" />
              </div>
              <div className="flex flex-col gap-1 px-1">
                <span className="flex items-center gap-2 font-bold">{p.name}<MoodChip mood={p.temperature} /></span>
                <span className="text-sm text-text-muted tabular">{price(p)}</span>
              </div>
            </Go>
          ))}
        </div>
      )}

      <div data-reveal className="mt-14 flex justify-center">
        <Go href={`/s/${tenant}/menu`} preview={preview} className="l-pill inline-flex h-12 items-center border border-border-strong px-6 font-semibold transition-colors hover:border-brand hover:text-brand">
          دیدن همه‌ی منو
        </Go>
      </div>
    </Shell>
  );
}

export function MarqueeSection({ landing }: SectionProps) {
  const { phrases, variant } = landing.content.marquee;
  if (phrases.length === 0) return null;
  const bold = landing.design.template === 'bold';

  return (
    <section aria-hidden="true" className={cx('overflow-hidden border-y py-8 @xl:py-10', bold ? 'border-transparent bg-brand text-on-brand' : 'border-border')}>
      <div className="l-marquee-track flex w-max whitespace-nowrap">
        {[0, 1, 2, 3].flatMap((k) => phrases.map((p, i) => (
          <span key={`${k}-${i}`} className="flex items-center">
            <span className={cx('l-display text-5xl @xl:text-7xl', variant === 'outline' && !bold ? 'l-stroke' : bold ? 'text-on-brand' : 'text-brand')}>{p}</span>
            {/* A solid star between phrases (an outlined dot would read as the Persian zero). */}
            <span className={cx('mx-6 text-2xl @xl:mx-10 @xl:text-4xl', bold ? 'text-on-brand/60' : 'text-brand')}>✦</span>
          </span>
        )))}
      </div>
    </section>
  );
}

export function GallerySection({ landing }: SectionProps) {
  const photos = landing.media.gallery;
  if (photos.length === 0) return null;

  return (
    <Shell id="gallery" design={landing.design} seed={4}>
      <Heading title={landing.content.gallery.title} />
      <LandingGallery photos={photos} variant={landing.content.gallery.variant} />
    </Shell>
  );
}

export function VisitSection({ store, landing, preview }: SectionProps) {
  if (store.branches.length === 0) return null;
  const today = todayIn(store.timezone);
  const { variant, title } = landing.content.visit;
  const btn = 'l-pill inline-flex h-10 items-center gap-2 border border-border px-4 text-sm transition-colors hover:border-brand hover:text-brand';

  return (
    <Shell id="visit" design={landing.design} seed={5} tone="muted">
      <Heading eyebrow="آدرس و ساعت کاری" title={title} />
      <div className={cx('grid gap-4', variant === 'cards' ? (store.branches.length > 1 ? '@3xl:grid-cols-2' : '@3xl:grid-cols-[1.3fr_1fr]') : '')}>
        {store.branches.map((b, i) => {
          const route = mapsHref(b);
          const phone = b.phone ?? store.contact.phone;
          const hours = hoursToday(b, store.timezone);

          return (
            <article key={b.id} data-reveal style={nth(i)}
              className={cx('l-card flex border border-border bg-surface', variant === 'cards' ? 'flex-col gap-5 p-7' : 'flex-col gap-3 p-5 @xl:flex-row @xl:items-center @xl:justify-between')}>
              <div className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="text-xl font-bold">{b.name}</h3>
                  <span className={cx('l-pill inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold', b.is_open ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning')}>
                    <span aria-hidden="true" className={cx('size-1.5 rounded-full', b.is_open ? 'bg-success' : 'bg-warning')} />{b.is_open ? 'باز است' : 'بسته است'}
                  </span>
                </div>
                {b.address ? <p className="flex items-start gap-1.5 text-text-muted"><MapPin className="mt-1 size-4 shrink-0" aria-hidden="true" />{b.city ? `${b.city}، ` : ''}{b.address}</p> : null}
                <p className="flex items-center gap-1.5 text-sm text-text-muted"><Clock className="size-4 shrink-0" aria-hidden="true" />امروز {hours ?? 'تعطیل'}</p>
              </div>

              {variant === 'cards' && b.opening_hours.length > 0 ? (
                <ul className="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                  {b.opening_hours.map((h, k) => (
                    <li key={k} className={cx('flex justify-between gap-2', h.weekday === today ? 'font-semibold text-brand' : 'text-text-muted')}>
                      <span>{h.weekday_label}</span><span className="tabular">{formatClock(h.opens_at)} تا {formatClock(h.closes_at)}</span>
                    </li>
                  ))}
                </ul>
              ) : null}

              <div className="flex flex-wrap gap-2">
                {route ? <Go href={route} external preview={preview} className={btn}><Navigation className="size-4" aria-hidden="true" />مسیریابی</Go> : null}
                {phone ? <Go href={`tel:${phone}`} external preview={preview} className={btn}><Phone className="size-4" aria-hidden="true" /><Ltr>{phone}</Ltr></Go> : null}
              </div>
            </article>
          );
        })}
      </div>
      {store.contact.instagram ? (
        <div data-reveal className="mt-8 flex justify-center">
          <Go href={`https://instagram.com/${store.contact.instagram}`} external preview={preview} className={btn}><AtSign className="size-4" aria-hidden="true" /><Ltr>{store.contact.instagram}</Ltr></Go>
        </div>
      ) : null}
    </Shell>
  );
}
