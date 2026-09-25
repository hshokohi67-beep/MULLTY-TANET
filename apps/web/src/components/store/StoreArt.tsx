import { createElement } from 'react';
import { CakeSlice, Coffee, Croissant, CupSoda, EggFried, IceCreamCone, Leaf, Pizza, Salad, Sandwich, UtensilsCrossed, type LucideIcon } from 'lucide-react';
import { cx } from '@cafe/ui';
import { initialOf } from '@/components/explore/Art';

/*
 * Generated art for a store's own menu: used where the store has no photo, so every store still
 * looks like itself (its brand colour comes from the `.store` scope set by the layout).
 */

/** Stable small number from a string (pattern variant). */
export function variantOf(key: string, count: number): number {
  let h = 0;
  for (let i = 0; i < key.length; i++) h = (h * 31 + key.charCodeAt(i)) >>> 0;

  return h % count;
}

const HERO_ICONS: LucideIcon[] = [Coffee, Croissant, CakeSlice, CupSoda, IceCreamCone, EggFried, Sandwich, Pizza, Salad, Leaf, UtensilsCrossed, Coffee];
const HERO_SPOTS: [number, number, number, number][] = [
  [4, 16, -14, 2.6], [15, 70, 12, 2], [27, 30, 22, 1.5], [38, 82, -8, 2.2], [50, 12, 16, 1.8], [60, 58, -20, 2.6],
  [70, 24, 30, 1.6], [80, 76, -12, 2.1], [90, 40, 8, 2.4], [96, 88, -26, 1.5], [44, 50, 6, 1.2], [22, 96, -18, 1.4],
];

/** Hero background without a cover photo: brand gradient, soft glows, food icons and a big monogram. */
export function StoreHeroArt({ name }: { name: string }) {
  return (
    <div aria-hidden="true" className="absolute inset-0 -z-10 overflow-hidden bg-brand text-on-brand">
      <div className="absolute inset-0 bg-gradient-to-br from-brand via-brand to-brand-strong" />
      <div className="absolute -end-16 -top-24 size-80 rounded-full bg-on-brand/15 blur-3xl" />
      <div className="absolute -bottom-28 start-1/4 size-96 rounded-full bg-brand-strong blur-3xl" />
      <div className="absolute inset-0 opacity-[0.13]">
        {HERO_SPOTS.map(([x, y, r, s], i) => createElement(HERO_ICONS[i % HERO_ICONS.length], {
          key: i, className: 'absolute', strokeWidth: 1.4,
          style: { insetInlineStart: `${x}%`, top: `${y}%`, width: `${s}rem`, height: `${s}rem`, transform: `translate(-50%, -50%) rotate(${r}deg)` },
        }))}
      </div>
      <span className="absolute -bottom-10 end-4 select-none text-[11rem] font-black leading-none opacity-[0.12] sm:end-10 sm:text-[14rem]">{initialOf(name)}</span>
    </div>
  );
}

/** Where the small pattern copies sit on a product tile (three layouts). */
const TILE_SPOTS: [number, number, number, number][][] = [
  [[22, 22, -18, 0.26], [78, 26, 16, 0.2], [24, 78, 24, 0.2], [78, 76, -10, 0.24]],
  [[76, 20, 20, 0.24], [20, 42, -24, 0.2], [80, 64, 12, 0.2], [32, 80, -8, 0.26]],
  [[24, 20, 10, 0.2], [52, 82, -16, 0.22], [80, 34, -28, 0.26], [18, 68, 18, 0.2]],
];

/** A photo-less product tile: faint copies of its illustration around the main one (layout per item). */
export function TilePattern({ icon, seed, className }: { icon: LucideIcon; seed: string; className?: string }) {
  return (
    <span aria-hidden="true" className={cx('pointer-events-none absolute inset-0 opacity-[0.22]', className)}>
      {TILE_SPOTS[variantOf(seed, TILE_SPOTS.length)].map(([x, y, r, s], i) => createElement(icon, {
        key: i, className: 'absolute', strokeWidth: 1.5,
        style: { insetInlineStart: `${x}%`, top: `${y}%`, width: `${s * 100}%`, height: `${s * 100}%`, transform: `translate(-50%, -50%) rotate(${r}deg)` },
      }))}
    </span>
  );
}
