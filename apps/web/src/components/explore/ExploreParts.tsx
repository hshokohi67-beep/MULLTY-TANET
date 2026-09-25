import Link from 'next/link';
import {
  Accessibility, BadgePercent, Bike, CigaretteOff, CreditCard, Laptop, MapPin, Megaphone, Moon, Music, PawPrint, Sparkles, SquareParking, Timer, Trees,
  Users, Wifi, type LucideIcon,
} from 'lucide-react';
import { brandCss, cx } from '@cafe/ui';
import { formatTime, toPersianDigits } from '@cafe/locale';
import { PRICE_LABELS, type StoreCard as Card } from '@/lib/marketplace-types';
import { CATEGORY_ICONS, CATEGORY_TONE, CoverArt, Logo, TONES, cityArt } from './Art';
import { FavoriteButton } from './ExploreClient';
import { Tracked } from './Showcase';

export { CATEGORY_ICONS };

export const AMENITY_ICONS: Record<string, LucideIcon> = {
  wifi: Wifi, outdoor: Trees, parking: SquareParking, family: Users, pet_friendly: PawPrint, workspace: Laptop, live_music: Music,
  no_smoking: CigaretteOff, wheelchair: Accessibility, late_night: Moon,
};

/** Each café keeps its own brand colour, contrast-checked for both themes (never raw hex in markup). */
export function BrandStyles({ stores }: { stores: { store: string; primary_color: string | null }[] }) {
  const seen = new Set<string>();
  const css = stores
    .filter((s) => (seen.has(s.store) ? false : (seen.add(s.store), true)))
    .map((s) => brandCss(s.primary_color, `[data-brand="${s.store}"]`))
    .filter(Boolean)
    .join('');

  return css ? <style dangerouslySetInnerHTML={{ __html: css }} /> : null;
}

export function PriceLevel({ level, className }: { level: number | null; className?: string }) {
  if (!level) return null;

  return (
    <span className={cx('inline-flex items-center gap-0.5', className)} title={PRICE_LABELS[level]} aria-label={`قیمت: ${PRICE_LABELS[level]}`}>
      {[1, 2, 3, 4].map((i) => <span key={i} className={cx('size-1.5 rounded-full', i <= level ? 'bg-current' : 'bg-current opacity-25')} />)}
    </span>
  );
}

export function OpenPill({ isOpen, next, timezone = 'Asia/Tehran', className }: { isOpen: boolean; next: string | null; timezone?: string; className?: string }) {
  return (
    <span className={cx('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', isOpen ? 'bg-success-soft text-success' : 'bg-surface-muted text-text-muted', className)}>
      <span className={cx('size-1.5 rounded-full', isOpen ? 'animate-pulse bg-success' : 'bg-text-subtle')} aria-hidden="true" />
      {isOpen ? 'باز است' : next ? `بسته • باز می‌شود ${formatTime(next, timezone)}` : 'بسته'}
    </span>
  );
}

const place = (s: Card) => (s.district ? `${s.district}، ${s.city}` : s.city);

/** Photo (cover, else a menu photo) or the café's generated art. */
function Visual({ s, size = 'md', priority = false, className }: { s: Card; size?: 'sm' | 'md' | 'lg'; priority?: boolean; className?: string }) {
  return s.image_url ? (
    // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
    <img src={s.image_url} alt="" loading={priority ? 'eager' : 'lazy'} className={cx('size-full object-cover transition-transform duration-500 group-hover:scale-[1.04]', className)} />
  ) : (
    <CoverArt store={s.store} name={s.name} category={s.categories[0]?.key} size={size} className={cx('transition-transform duration-500 group-hover:scale-[1.04]', className)} />
  );
}

/** A result card: visual, open state, labels (sponsored / featured / offer), essentials and services. */
export function StoreCard({ s, priority = false }: { s: Card; priority?: boolean }) {
  const chip = 'inline-flex items-center gap-1 rounded-full bg-surface-muted px-2 py-0.5';
  const card = (
    <Link href={`/explore/${s.store}`} data-brand={s.store}
      className="group flex w-full flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-sm)] transition-[box-shadow,transform] duration-[var(--duration-base)] hover:-translate-y-1 hover:shadow-[var(--shadow-lg)] focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none">
      <div className="relative">
      <div className="relative aspect-[16/10] overflow-hidden">
        <Visual s={s} priority={priority} />
        <div className="absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-scrim/55 to-transparent" aria-hidden="true" />
        <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-2.5">
          <span className="flex flex-wrap items-center gap-1.5">
            <OpenPill isOpen={s.is_open} next={s.next_opening_at} className="shadow-[var(--shadow-sm)]" />
            {s.ad ? <span className="glass-light inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold text-on-media"><Megaphone className="size-3" aria-hidden="true" />تبلیغ</span>
              : s.is_featured ? <span className="inline-flex items-center gap-1 rounded-full bg-brand px-2.5 py-1 text-xs font-semibold text-on-brand shadow-[var(--shadow-sm)]"><Sparkles className="size-3" aria-hidden="true" />ویژه</span> : null}
          </span>
          <FavoriteButton store={s.store} name={s.name} />
        </div>
        {s.offer ? (
          <span className="absolute bottom-2.5 end-2.5 inline-flex max-w-[64%] items-center gap-1 rounded-lg bg-danger px-2 py-1 text-xs font-bold text-on-danger shadow-[var(--shadow-md)]">
            <BadgePercent className="size-3.5 shrink-0" aria-hidden="true" /><span className="truncate">{s.offer}</span>
          </span>
        ) : null}
      </div>
        <Logo url={s.logo_url} name={s.name} className="absolute -bottom-6 start-4 size-12 rounded-2xl border-[3px] border-surface text-xl shadow-[var(--shadow-md)]" />
      </div>
      <div className="flex flex-1 flex-col gap-1.5 p-4 pt-8">
        <div className="flex items-start justify-between gap-2">
          <h3 className="font-bold leading-tight">{s.name}{s.branch_name ? <span className="text-sm font-normal text-text-muted"> • {s.branch_name}</span> : null}</h3>
          <PriceLevel level={s.price_level} className="mt-1.5 shrink-0 text-text-muted" />
        </div>
        {s.ad ? <p className="line-clamp-1 text-sm font-medium text-brand">{s.ad.headline}</p>
          : s.headline ? <p className="line-clamp-1 text-sm text-text-muted">{s.headline}</p> : null}
        <p className="mt-auto flex flex-wrap items-center gap-1.5 pt-2 text-xs text-text-muted">
          <span className={chip}><MapPin className="size-3" aria-hidden="true" />{place(s)}</span>
          {s.distance_km !== null ? <span className={chip}>{s.distance_km < 1 ? 'کمتر از ۱' : toPersianDigits(s.distance_km.toFixed(1))} کیلومتر</span> : null}
          {s.categories.slice(0, 1).map((c) => <span key={c.key} className={chip}>{c.label}</span>)}
        </p>
        {s.services.delivery || s.services.online_payment || s.services.preorder ? (
          <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border pt-2.5 text-xs text-text-muted [&>span]:whitespace-nowrap">
            {s.services.delivery ? <span className={cx('inline-flex items-center gap-1', s.free_delivery && 'font-semibold text-success')}><Bike className="size-3.5" aria-hidden="true" />{s.free_delivery ? 'ارسال رایگان' : 'ارسال'}</span> : null}
            {s.services.online_payment ? <span className="inline-flex items-center gap-1"><CreditCard className="size-3.5" aria-hidden="true" />پرداخت آنلاین</span> : null}
            {s.services.preorder ? <span className="inline-flex items-center gap-1"><Timer className="size-3.5" aria-hidden="true" />پیش‌سفارش</span> : null}
          </p>
        ) : null}
      </div>
    </Link>
  );

  return s.ad ? <Tracked token={s.ad.token} className="flex w-full">{card}</Tracked> : card;
}

/** Featured: a large visual card with the essentials over the image. */
export function SpotlightCard({ s, priority = false }: { s: Card; priority?: boolean }) {
  return (
    <Link href={`/explore/${s.store}`} data-brand={s.store}
      className="group relative flex aspect-[4/5] w-full overflow-hidden rounded-3xl shadow-[var(--shadow-md)] transition-shadow hover:shadow-[var(--shadow-lg)] focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none sm:aspect-[5/4]">
      <div className="absolute inset-0"><Visual s={s} size="lg" priority={priority} /></div>
      <div className="absolute inset-0 bg-gradient-to-t from-scrim via-scrim/30 to-transparent" aria-hidden="true" />
      <div className="absolute inset-x-0 top-0 flex items-start justify-between p-3">
        <span className="glass-light inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold text-on-media"><Sparkles className="size-3.5" aria-hidden="true" />ویژه</span>
        <FavoriteButton store={s.store} name={s.name} />
      </div>
      <div className="relative mt-auto flex w-full flex-col gap-2 p-4 text-on-media sm:p-5">
        <span className="flex items-center gap-2.5">
          <Logo url={s.logo_url} name={s.name} className="size-11 shrink-0 rounded-xl text-lg ring-2 ring-on-media/40" />
          <span className="min-w-0">
            <span className="block truncate text-lg font-black leading-tight">{s.name}</span>
            <span className="block truncate text-xs text-on-media-muted">{place(s)}{s.categories[0] ? ` • ${s.categories[0].label}` : ''}</span>
          </span>
        </span>
        {s.headline ? <p className="line-clamp-2 text-sm text-on-media-muted">{s.headline}</p> : null}
        <span className="flex flex-wrap items-center gap-1.5 text-xs">
          <span className="glass-light inline-flex items-center gap-1 rounded-full px-2 py-0.5"><span className={cx('size-1.5 rounded-full', s.is_open ? 'bg-success' : 'bg-on-media-muted')} aria-hidden="true" />{s.is_open ? 'باز است' : 'بسته'}</span>
          {s.price_level ? <span className="glass-light rounded-full px-2 py-0.5">{PRICE_LABELS[s.price_level]}</span> : null}
          {s.offer ? <span className="inline-flex items-center gap-1 rounded-full bg-danger px-2 py-0.5 font-bold text-on-danger"><BadgePercent className="size-3" aria-hidden="true" />{s.offer}</span> : null}
        </span>
      </div>
    </Link>
  );
}

/** One line of a ranked list («برترین‌ها»). */
export function RankItem({ s, rank }: { s: Card; rank: number }) {
  return (
    <Link href={`/explore/${s.store}`} data-brand={s.store} className="group flex items-center gap-3 rounded-2xl p-2 transition-colors hover:bg-surface-muted focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none">
      <span className="w-9 shrink-0 text-center text-4xl font-black leading-none text-brand opacity-40 transition-opacity group-hover:opacity-80" aria-hidden="true">{toPersianDigits(rank)}</span>
      <span className="relative size-16 shrink-0 overflow-hidden rounded-xl shadow-[var(--shadow-sm)]"><Visual s={s} size="sm" /></span>
      <span className="min-w-0 flex-1">
        <span className="block truncate font-bold">{s.name}</span>
        <span className="block truncate text-xs text-text-muted">{place(s)}{s.categories[0] ? ` • ${s.categories[0].label}` : ''}</span>
        <span className="mt-1 flex items-center gap-2 text-xs">
          <span className={cx('inline-flex items-center gap-1', s.is_open ? 'text-success' : 'text-text-subtle')}><span className={cx('size-1.5 rounded-full', s.is_open ? 'bg-success' : 'bg-text-subtle')} aria-hidden="true" />{s.is_open ? 'باز است' : 'بسته'}</span>
          <PriceLevel level={s.price_level} className="text-text-muted" />
          {s.offer ? <span className="inline-flex items-center gap-0.5 font-semibold text-danger"><BadgePercent className="size-3" aria-hidden="true" />تخفیف</span> : null}
        </span>
      </span>
    </Link>
  );
}

/** A category as a large round, tinted tile. */
export function CategoryTile({ category, label, count, href, active }: { category: string; label: string; count: number; href: string; active: boolean }) {
  const Icon = CATEGORY_ICONS[category];

  return (
    <Link href={href} aria-current={active ? 'true' : undefined} className="group flex w-[4.75rem] shrink-0 flex-col items-center gap-1.5 text-center focus-visible:outline-none sm:w-24">
      <span className={cx('flex size-16 items-center justify-center rounded-[1.4rem] shadow-[var(--shadow-sm)] ring-2 ring-offset-2 ring-offset-bg transition-transform duration-[var(--duration-base)] group-hover:-translate-y-0.5 group-focus-visible:shadow-[var(--focus-ring)] sm:size-[4.5rem]',
        TONES[CATEGORY_TONE[category] ?? 'brand'], active ? 'ring-brand' : 'ring-transparent')}>
        {Icon ? <Icon className="size-7" strokeWidth={1.6} aria-hidden="true" /> : null}
      </span>
      <span className={cx('text-xs leading-tight', active ? 'font-bold text-brand' : 'font-semibold')}>{label}</span>
      <span className="text-[10px] text-text-subtle">{toPersianDigits(count)} کافه</span>
    </Link>
  );
}

/** A city as an art tile (tinted, with a big faint landmark icon). */
export function CityTile({ city, count, districts, href, index }: { city: string; count: number; districts: number; href: string; index: number }) {
  const { icon: Icon, tone } = cityArt(city, index);

  return (
    <Link href={href} className={cx('group relative flex h-28 overflow-hidden rounded-2xl p-4 shadow-[var(--shadow-sm)] transition-shadow hover:shadow-[var(--shadow-md)] focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none sm:h-32', TONES[tone])}>
      <Icon className="absolute -bottom-4 -end-3 size-28 opacity-20 transition-transform duration-500 group-hover:-rotate-6 group-hover:scale-110" strokeWidth={1.2} aria-hidden="true" />
      <span className="relative mt-auto">
        <span className="block text-xl font-black">{city}</span>
        <span className="text-xs opacity-80">{toPersianDigits(count)} کافه{districts ? ` • ${toPersianDigits(districts)} محله` : ''}</span>
      </span>
    </Link>
  );
}
