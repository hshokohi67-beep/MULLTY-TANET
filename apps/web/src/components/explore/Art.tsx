import {
  Bean, Building2, CakeSlice, Castle, ChefHat, Coffee, Croissant, CupSoda, EggFried, Flower2, IceCreamCone, Landmark, Leaf, MapPin, Mountain, Salad,
  Sandwich, Sparkles, Trees, UtensilsCrossed, Waves, type LucideIcon,
} from 'lucide-react';
import { cx } from '@cafe/ui';

/*
 * Generated art for cafés without photos: every café gets its own cover from its brand colour
 * (applied through `data-brand` + brandCss on an ancestor), its category's icons and a monogram,
 * so a page of photo-less cafés still looks varied and alive. Pure markup, no images.
 */

export const CATEGORY_ICONS: Record<string, LucideIcon> = {
  cafe: Coffee, specialty_coffee: Bean, cafe_restaurant: UtensilsCrossed, restaurant: ChefHat, fast_food: Sandwich, breakfast: EggFried,
  bakery: Croissant, dessert: CakeSlice, ice_cream: IceCreamCone, tea_house: Leaf, healthy: Salad,
};

/** A secondary icon per category, so the pattern isn't one glyph repeated. */
const PARTNER: Record<string, LucideIcon> = {
  cafe: CakeSlice, specialty_coffee: Coffee, cafe_restaurant: Coffee, restaurant: UtensilsCrossed, fast_food: CupSoda, breakfast: Coffee,
  bakery: CakeSlice, dessert: Coffee, ice_cream: CupSoda, tea_house: Flower2, healthy: CupSoda,
};

/** Soft tinted tones (token pairs, both themes) for category tiles and city art. */
export const TONES = {
  brand: 'bg-brand-soft text-brand',
  accent: 'bg-accent-soft text-accent',
  warning: 'bg-warning-soft text-warning',
  danger: 'bg-danger-soft text-danger',
  success: 'bg-success-soft text-success',
  info: 'bg-info-soft text-info',
  hot: 'bg-mood-hot-soft text-mood-hot-ink',
  cold: 'bg-mood-cold-soft text-mood-cold-ink',
} as const;
export type Tone = keyof typeof TONES;

export const CATEGORY_TONE: Record<string, Tone> = {
  cafe: 'accent', specialty_coffee: 'brand', cafe_restaurant: 'warning', restaurant: 'danger', fast_food: 'hot', breakfast: 'warning',
  bakery: 'accent', dessert: 'danger', ice_cream: 'cold', tea_house: 'success', healthy: 'success',
};

const CITY_ICONS: Record<string, LucideIcon> = {
  تهران: Building2, اصفهان: Landmark, شیراز: Flower2, مشهد: Sparkles, تبریز: Castle, کرج: Mountain, رشت: Trees, کیش: Waves, یزد: Landmark, کرمان: Castle,
};
const CITY_TONES: Tone[] = ['brand', 'accent', 'info', 'success', 'danger', 'cold', 'warning', 'hot'];

export function cityArt(city: string, index: number): { icon: LucideIcon; tone: Tone } {
  return { icon: CITY_ICONS[city] ?? MapPin, tone: CITY_TONES[index % CITY_TONES.length] };
}

/** A stable small number from a string (layout variant per café). */
function hash(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
  return h;
}

/** The monogram letter: the first letter of the most distinctive (last) word of the name. */
export function initialOf(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  const word = words[words.length - 1] ?? name;

  return Array.from(word)[0] ?? '•';
}

/** x%, y%, rotation, size (rem) per pattern item; three layouts. */
const LAYOUTS: [number, number, number, number][][] = [
  [[6, 10, -12, 2.2], [30, 62, 18, 1.6], [58, 14, 8, 1.8], [82, 58, -20, 2.4], [70, 86, 12, 1.4], [14, 80, 26, 1.8], [44, 36, -6, 1.2], [92, 20, 30, 1.3]],
  [[10, 20, 20, 1.6], [26, 78, -14, 2.4], [50, 8, -24, 1.4], [64, 64, 10, 2], [88, 30, -8, 2.2], [40, 44, 34, 1.2], [80, 88, 16, 1.5], [4, 56, -30, 1.3]],
  [[18, 12, 6, 1.8], [8, 44, -18, 1.4], [36, 84, 22, 2], [52, 30, -10, 2.6], [76, 10, 28, 1.4], [90, 52, -24, 1.8], [66, 80, 4, 1.3], [24, 58, 14, 1.2]],
];

/**
 * Cover art in the café's brand colours: `lg` for the profile header, `sm` for thumbnails.
 * Needs a `data-brand` ancestor for the café's own colour (falls back to the platform teal).
 */
export function CoverArt({ store, name, category, size = 'md', className }: { store: string; name: string; category?: string; size?: 'sm' | 'md' | 'lg'; className?: string }) {
  const scale = size === 'lg' ? 1.5 : size === 'sm' ? 0.55 : 1;
  const h = hash(store);
  const layout = LAYOUTS[h % LAYOUTS.length];
  const A = CATEGORY_ICONS[category ?? 'cafe'] ?? Coffee;
  const B = PARTNER[category ?? 'cafe'] ?? CakeSlice;
  const tilt = (h >> 3) % 2 === 0 ? 'bg-gradient-to-br' : 'bg-gradient-to-bl';

  return (
    <div aria-hidden="true" className={cx('relative size-full overflow-hidden bg-brand text-on-brand', className)}>
      <div className={cx('absolute inset-0 from-brand via-brand to-brand-strong', tilt)} />
      <div className="absolute -end-12 -top-16 size-56 rounded-full bg-on-brand/15 blur-2xl" />
      <div className="absolute -bottom-20 -start-10 size-64 rounded-full bg-brand-strong/70 blur-3xl" />
      <div className="absolute inset-0 opacity-[0.16]">
        {layout.map(([x, y, r, s], i) => {
          const Icon = i % 3 === 1 ? B : A;

          return <Icon key={i} className="absolute" strokeWidth={1.5} style={{ insetInlineStart: `${x}%`, top: `${y}%`, width: `${s * scale}rem`, height: `${s * scale}rem`, transform: `translate(-50%, -50%) rotate(${r}deg)` }} />;
        })}
      </div>
      <span className="absolute inset-0 flex items-center justify-center">
        <span className={cx('flex items-center justify-center rounded-full bg-on-brand/15 font-black ring-1 ring-on-brand/30', { sm: 'size-9 text-lg', md: 'size-16 text-3xl', lg: 'size-28 text-6xl' }[size])}>{initialOf(name)}</span>
      </span>
    </div>
  );
}

/** The café's logo, or its monogram on a brand-soft tile. */
export function Logo({ url, name, className }: { url: string | null; name: string; className?: string }) {
  return url ? (
    // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage
    <img src={url} alt="" className={cx('bg-surface object-cover', className)} />
  ) : (
    <span aria-hidden="true" className={cx('flex items-center justify-center bg-brand-soft font-black text-brand', className)}>{initialOf(name)}</span>
  );
}

/** Decorative café icons scattered over a hero background (very faint, token coloured). */
export function HeroPattern({ className = 'text-brand opacity-[0.07]' }: { className?: string }) {
  const icons: [LucideIcon, number, number, number, number][] = [
    [Coffee, 6, 18, -14, 2.4], [Croissant, 16, 72, 12, 2], [Bean, 30, 30, 24, 1.4], [CakeSlice, 46, 84, -8, 1.8], [CupSoda, 62, 16, 16, 2.2],
    [IceCreamCone, 74, 64, -20, 1.8], [Leaf, 88, 26, 30, 1.6], [EggFried, 94, 80, -12, 2], [Coffee, 54, 48, 8, 1.2], [Bean, 82, 44, -30, 1.1],
  ];

  return (
    <div aria-hidden="true" className={cx('pointer-events-none absolute inset-0 overflow-hidden', className)}>
      {icons.map(([Icon, x, y, r, s], i) => (
        <Icon key={i} className="absolute" strokeWidth={1.4} style={{ insetInlineStart: `${x}%`, top: `${y}%`, width: `${s}rem`, height: `${s}rem`, transform: `rotate(${r}deg)` }} />
      ))}
    </div>
  );
}
