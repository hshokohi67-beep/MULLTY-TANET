import { createElement } from 'react';
import { Cherry, Flower2, Leaf, Moon, Snowflake, Sparkle, Sun, type LucideIcon } from 'lucide-react';
import type { MenuCollectionView } from '@/lib/storefront-types';

type Theme = NonNullable<MenuCollectionView['theme']>;

const ICONS: Record<Theme, LucideIcon[]> = {
  autumn: [Leaf, Leaf, Leaf],
  winter: [Snowflake, Snowflake, Snowflake],
  yalda: [Cherry, Leaf, Cherry],
  nowruz: [Flower2, Leaf, Flower2],
  summer: [Sun, Leaf, Sun],
  ramadan: [Moon, Sparkle, Moon],
};

/** Fixed spots so server and client render the same (left %, delay s, duration s, size rem, rotate). */
const SPOTS: [number, number, number, number, number][] = [
  [3, 0, 19, 1.4, -20], [11, 6, 23, 1, 15], [22, 11, 21, 1.2, 40], [37, 3, 26, 0.9, -10],
  [63, 8, 24, 1.1, 25], [78, 1, 20, 1.3, -35], [88, 13, 25, 1, 10], [96, 5, 22, 1.2, -5],
];

/**
 * A seasonal decoration for the shop pages, from the menu collection on now. It sits *behind* the
 * page (cards and text cover it), a few slow, faint pieces; with reduced motion they stay still.
 * Never chosen for mourning occasions (the collection editor offers no theme there).
 */
export function SeasonDecor({ theme }: { theme: Theme }) {
  const icons = ICONS[theme];

  return (
    <div aria-hidden="true" data-season={theme} className="season-decor pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      {SPOTS.map(([left, delay, duration, size, rotate], i) => (
        <span key={i} className="season-piece absolute top-0" style={{ left: `${left}%`, animationDelay: `-${delay}s`, animationDuration: `${duration}s`, ['--r' as string]: `${rotate}deg` }}>
          {createElement(icons[i % icons.length], { style: { width: `${size}rem`, height: `${size}rem` }, strokeWidth: 1.5 })}
        </span>
      ))}
    </div>
  );
}
