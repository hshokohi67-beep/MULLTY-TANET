import Link from 'next/link';
import {
  Accessibility, Bean, CakeSlice, ChefHat, CigaretteOff, Coffee, Croissant, EggFried, IceCreamCone, Laptop, Leaf, MapPin, Moon, Music,
  PawPrint, Salad, Sandwich, Sparkles, SquareParking, Trees, Users, UtensilsCrossed, Wifi, type LucideIcon,
} from 'lucide-react';
import { brandCss, cx } from '@cafe/ui';
import { formatTime, toPersianDigits } from '@cafe/locale';
import { PRICE_LABELS, type StoreCard as Card } from '@/lib/marketplace-types';

export const CATEGORY_ICONS: Record<string, LucideIcon> = {
  cafe: Coffee, specialty_coffee: Bean, cafe_restaurant: UtensilsCrossed, restaurant: ChefHat, fast_food: Sandwich, breakfast: EggFried,
  bakery: Croissant, dessert: CakeSlice, ice_cream: IceCreamCone, tea_house: Leaf, healthy: Salad,
};

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
      <span className={cx('size-1.5 rounded-full', isOpen ? 'bg-success' : 'bg-text-subtle')} aria-hidden="true" />
      {isOpen ? 'باز است' : next ? `بسته • باز می‌شود ${formatTime(next, timezone)}` : 'بسته'}
    </span>
  );
}

/** A result card: cover (or a menu photo, or a brand-coloured placeholder), logo, open state, essentials. */
export function StoreCard({ s, priority = false }: { s: Card; priority?: boolean }) {
  const Icon = CATEGORY_ICONS[s.categories[0]?.key ?? 'cafe'] ?? Coffee;

  return (
    <Link href={`/explore/${s.store}`} data-brand={s.store}
      className="group flex flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-[var(--shadow-sm)] transition-[box-shadow,transform] duration-[var(--duration-base)] hover:-translate-y-0.5 hover:shadow-[var(--shadow-md)] focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none">
      <div className="relative aspect-[16/10] overflow-hidden bg-brand-soft">
        {s.image_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
          <img src={s.image_url} alt="" loading={priority ? 'eager' : 'lazy'} className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]" />
        ) : (
          <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand-soft to-surface-muted text-brand">
            <Icon className="size-14 opacity-70" strokeWidth={1.25} aria-hidden="true" />
          </div>
        )}
        <div className="absolute inset-x-0 top-0 flex items-start justify-between p-2.5">
          {s.is_featured ? <span className="inline-flex items-center gap-1 rounded-full bg-brand px-2.5 py-1 text-xs font-semibold text-on-brand shadow-[var(--shadow-sm)]"><Sparkles className="size-3" aria-hidden="true" />ویژه</span> : <span />}
          <OpenPill isOpen={s.is_open} next={s.next_opening_at} className="shadow-[var(--shadow-sm)] backdrop-blur-sm" />
        </div>
        {s.logo_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
          <img src={s.logo_url} alt="" className="absolute -bottom-5 start-4 size-12 rounded-xl border-2 border-surface bg-surface object-cover shadow-[var(--shadow-md)]" />
        ) : null}
      </div>
      <div className={cx('flex flex-1 flex-col gap-1.5 p-4', s.logo_url && 'pt-7')}>
        <div className="flex items-start justify-between gap-2">
          <h3 className="font-bold leading-tight">{s.name}{s.branch_name ? <span className="text-sm font-normal text-text-muted"> • {s.branch_name}</span> : null}</h3>
          <PriceLevel level={s.price_level} className="mt-1.5 text-text-muted" />
        </div>
        {s.headline ? <p className="line-clamp-1 text-sm text-text-muted">{s.headline}</p> : null}
        <p className="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 pt-1.5 text-xs text-text-muted">
          <span className="inline-flex items-center gap-1"><MapPin className="size-3.5" aria-hidden="true" />{s.city}</span>
          {s.distance_km !== null ? <span>• {s.distance_km < 1 ? 'کمتر از ۱' : toPersianDigits(s.distance_km.toFixed(1))} کیلومتر</span> : null}
          {s.categories.slice(0, 2).map((c) => <span key={c.key}>• {c.label}</span>)}
        </p>
      </div>
    </Link>
  );
}
