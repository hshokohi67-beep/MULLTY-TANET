import { Navigation, UtensilsCrossed } from 'lucide-react';
import { cx } from '@cafe/ui';
import { initialOf } from '@/components/explore/Art';
import { StoreHeroArt } from '@/components/store/StoreArt';
import type { LandingDesign, PublicLanding } from '@/lib/landing-types';
import type { Storefront } from '@/lib/storefront-types';
import { HeroMedia } from './HeroMedia';
import { ArtLayer, Go, mapsHref, nth } from './parts';

interface HeroProps { tenant: string; store: Storefront; landing: PublicLanding; preview?: boolean }

/** The sticky top bar: logo, name, and the way to the menu. Glass only because it floats over photos. */
export function LandingHeader({ tenant, store, landing, preview }: HeroProps) {
  const overMedia = landing.design.hero === 'cover' || landing.design.hero === 'center';
  const logo = store.branding?.logo_url;

  return (
    <header className={cx('sticky top-0 z-30 -mb-16 h-16', overMedia ? 'glass-light text-on-media' : 'glass')}>
      <div className="mx-auto flex h-full max-w-6xl items-center gap-3 px-4 @xl:px-6">
        <span className="flex min-w-0 flex-1 items-center gap-2.5">
          {logo ? (
            // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
            <img src={logo} alt="" className="l-card-sm size-9 bg-surface object-contain p-0.5" />
          ) : <span aria-hidden="true" className="l-card-sm flex size-9 items-center justify-center bg-brand font-black text-on-brand">{initialOf(store.name)}</span>}
          <span className="truncate font-bold">{store.name}</span>
        </span>
        <Go href={`/s/${tenant}/menu`} preview={preview} className="l-pill inline-flex h-10 items-center gap-1.5 bg-brand px-4 text-sm font-semibold text-on-brand shadow-[var(--shadow-sm)] hover:bg-brand-strong">
          <UtensilsCrossed className="size-4" aria-hidden="true" />{landing.content.hero.cta_label}
        </Go>
      </div>
    </header>
  );
}

function Actions({ tenant, store, landing, preview, onMedia, onBrand }: HeroProps & { onMedia?: boolean; onBrand?: boolean }) {
  const branch = store.branches[0];
  const route = branch ? mapsHref(branch) : null;

  return (
    <div className="l-rise flex flex-wrap items-center gap-3" style={nth(3)}>
      <Go href={`/s/${tenant}/menu`} preview={preview}
        className={cx('l-pill inline-flex h-12 items-center gap-2 px-6 font-semibold shadow-[var(--shadow-md)] transition-transform hover:-translate-y-0.5',
          onBrand ? 'bg-on-brand text-brand' : 'bg-brand text-on-brand hover:bg-brand-strong')}>
        <UtensilsCrossed className="size-4" aria-hidden="true" />{landing.content.hero.cta_label}
      </Go>
      {route ? (
        <Go href={route} preview={preview} external
          className={cx('l-pill inline-flex h-12 items-center gap-2 border px-5 text-sm font-medium transition-colors',
            onMedia ? 'border-on-media/40 text-on-media hover:bg-on-media/10' : onBrand ? 'border-on-brand/40 text-on-brand hover:bg-on-brand/10' : 'border-border-strong hover:bg-surface-muted')}>
          <Navigation className="size-4" aria-hidden="true" />مسیریابی
        </Go>
      ) : null}
      {branch ? (
        <span className={cx('inline-flex items-center gap-2 text-sm', onMedia ? 'text-on-media-muted' : onBrand ? 'text-on-brand/80' : 'text-text-muted')}>
          <span aria-hidden="true" className={cx('size-2 rounded-full', branch.is_open ? 'bg-success' : 'bg-warning')} />
          {branch.is_open ? 'الان باز است' : 'الان بسته است'}
        </span>
      ) : null}
    </div>
  );
}

function Words({ landing, onMedia, onBrand, center, big }: { landing: PublicLanding; onMedia?: boolean; onBrand?: boolean; center?: boolean; big?: boolean }) {
  const { eyebrow, title, subtitle } = landing.content.hero;

  return (
    <>
      {eyebrow ? <span className={cx('l-eyebrow l-rise', onMedia ? 'text-on-media-muted' : onBrand ? 'text-on-brand/75' : 'text-text-subtle')} style={nth(0)}>{eyebrow}</span> : null}
      <h1 className={cx('l-display l-rise', big ? 'text-5xl @xl:text-7xl @5xl:text-8xl' : 'text-4xl @xl:text-6xl', onMedia && '[text-shadow:0_2px_24px_rgb(0_0_0/0.35)]')} style={nth(1)}>{title}</h1>
      {subtitle ? (
        <p className={cx('l-rise max-w-xl text-lg leading-8', center && 'mx-auto', onMedia ? 'text-on-media-muted' : onBrand ? 'text-on-brand/85' : 'text-text-muted')} style={nth(2)}>{subtitle}</p>
      ) : null}
    </>
  );
}

/** Photo or video when there is one; otherwise the generated brand art. */
function Media({ store, landing, drift = true }: { store: Storefront; landing: PublicLanding; drift?: boolean }) {
  const photo = landing.media.hero_photo?.url ?? store.branding?.cover_url ?? null;
  const video = landing.media.hero_video?.url ?? null;

  return photo || video ? <HeroMedia photo={photo} video={video} drift={drift} className="-z-10" /> : <StoreHeroArt name={store.name} />;
}

/** Four hero layouts; the café picks one («تمام‌صفحه»، «وسط‌چین»، «دوستونه»، «پوستر»). */
export function LandingHero(props: HeroProps) {
  const { store, landing } = props;
  const design: LandingDesign = landing.design;
  const onBrand = design.template === 'bold';

  if (design.hero === 'cover' || design.hero === 'center') {
    const center = design.hero === 'center';

    return (
      <section className={cx('relative isolate flex min-h-[92svh] flex-col overflow-hidden text-on-media', center ? 'justify-center' : 'justify-end')}>
        <Media store={store} landing={landing} />
        <div aria-hidden="true" className={cx('absolute inset-0 -z-10', center ? 'bg-scrim/55' : 'bg-gradient-to-t from-scrim via-scrim/45 to-scrim/10')} />
        <div className={cx('mx-auto flex w-full max-w-6xl flex-col gap-5 px-5 pb-16 pt-28 @xl:px-8 @xl:pb-24', center && 'items-center text-center')}>
          {center && store.branding?.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
            <img src={store.branding.logo_url} alt="" className="l-card l-rise size-20 bg-surface object-contain p-1.5 shadow-[var(--shadow-lg)]" />
          ) : null}
          <Words landing={landing} onMedia center={center} big />
          <div className={cx(center && 'flex justify-center')}><Actions {...props} onMedia /></div>
        </div>
      </section>
    );
  }

  if (design.hero === 'split') {
    return (
      <section className={cx('l-texture overflow-hidden', onBrand && 'bg-brand text-on-brand')}>
        <ArtLayer design={design} />
        <div className="mx-auto grid max-w-6xl items-center gap-10 px-5 pb-16 pt-28 @xl:px-8 @5xl:grid-cols-[1.05fr_1fr] @5xl:gap-16 @5xl:pb-24 @5xl:pt-32">
          <div className="flex flex-col gap-5">
            <Words landing={landing} onBrand={onBrand} big />
            <Actions {...props} onBrand={onBrand} />
          </div>
          <div className="l-rise relative" style={nth(2)}>
            <div aria-hidden="true" className={cx('l-card absolute -inset-3 -z-10 rotate-2', onBrand ? 'bg-on-brand/15' : 'bg-brand-soft')} />
            <div className="l-card relative isolate aspect-[4/5] overflow-hidden shadow-[var(--shadow-lg)] @xl:aspect-[5/5] @5xl:aspect-[4/5]">
              <Media store={store} landing={landing} drift={false} />
            </div>
          </div>
        </div>
      </section>
    );
  }

  // poster: the name as giant outlined type, the photo in an arch, the words below.
  return (
    <section className={cx('l-texture overflow-hidden pt-24 text-center', onBrand && 'bg-brand text-on-brand')}>
      <ArtLayer design={design} />
      <p aria-hidden="true" className={cx('l-display l-rise select-none truncate px-2 text-[19cqw] leading-none @xl:text-[15cqw]', onBrand ? 'text-on-brand/25' : 'l-stroke')}>{store.name}</p>
      <div className="l-rise relative isolate mx-auto -mt-[7cqw] aspect-[3/4] w-[min(78cqw,24rem)] overflow-hidden rounded-t-full shadow-[var(--shadow-lg)]" style={nth(1)}>
        <Media store={store} landing={landing} drift={false} />
      </div>
      <div className="mx-auto flex max-w-3xl flex-col items-center gap-5 px-5 pb-20 pt-10">
        <Words landing={landing} onBrand={onBrand} center />
        <Actions {...props} onBrand={onBrand} />
      </div>
    </section>
  );
}
