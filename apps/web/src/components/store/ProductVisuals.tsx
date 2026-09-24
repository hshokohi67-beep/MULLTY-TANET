import { createElement } from 'react';
import { CakeSlice, Coffee, CupSoda, EggFried, Flame, Salad, Sandwich, Snowflake, UtensilsCrossed, type LucideIcon } from 'lucide-react';
import { cx } from '@cafe/ui';
import type { MenuProduct, Mood } from '@/lib/storefront-types';

export const MOOD_LABEL: Record<Mood, string> = { hot: 'گرم', cold: 'سرد' };

/** Flame/snowflake chip, so the warm/cool colour is never the only cue. */
export function MoodChip({ mood, className }: { mood: Mood | null; className?: string }) {
  if (!mood) return null;
  const Icon = mood === 'hot' ? Flame : Snowflake;

  return (
    <span data-mood={mood} className={cx('inline-flex items-center gap-0.5 rounded-full bg-[var(--mood-soft)] px-1.5 py-0.5 text-[11px] font-semibold leading-4 text-[var(--mood-ink)]', className)}>
      <Icon className="size-3" aria-hidden="true" />{MOOD_LABEL[mood]}
    </span>
  );
}

// Illustration for items without a photo, picked from the name (purely decorative).
const ILLUSTRATIONS: [RegExp, LucideIcon][] = [
  [/کیک|دسر|چیزکیک|براونی|تارت|شیرینی|کوکی|بستنی|وافل|پنکیک/, CakeSlice],
  [/املت|صبحانه|تخم|نیمرو|پنیر|نان|کره|مربا/, EggFried],
  [/ساندویچ|برگر|پیتزا|پنینی|تست|رول/, Sandwich],
  [/سالاد|بول/, Salad],
  [/لیموناد|سودا|اسموتی|شیک|آبمیوه|موهیتو|یخ|آیس|فراپه|خنک/, CupSoda],
  [/قهوه|اسپرسو|لاته|کاپوچینو|موکا|آمریکانو|ماکیاتو|کورتادو|چای|دمنوش|شکلات داغ|عربیکا/, Coffee],
];

export function illustrationFor(text: string, mood: Mood | null = null): LucideIcon {
  const hit = ILLUSTRATIONS.find(([re]) => re.test(text));
  if (hit) return hit[1];

  // Nothing in the name: the mood is the next best hint.
  return mood === 'cold' ? CupSoda : mood === 'hot' ? Coffee : UtensilsCrossed;
}

const illustration = (product: MenuProduct) => illustrationFor(`${product.name} ${product.description ?? ''}`, product.temperature);

/**
 * The product photo, or a soft mood-tinted tile with a line illustration. Sits inside a
 * `data-mood` card so the glow colour follows the item.
 */
export function ProductPhoto({ product, className, sizes = 'thumb', priority = false }: {
  product: MenuProduct;
  className?: string;
  /** thumb: small cover tile • tile: large cover tile • hero: the whole photo, uncropped */
  sizes?: 'thumb' | 'tile' | 'hero';
  priority?: boolean;
}) {
  const image = product.images[0];
  return (
    <div className={cx('mood-photo overflow-hidden', className)}>
      {image && sizes !== 'tile' ? (
        // The whole product (never cropped) on a soft mood-tinted backdrop: product shots are
        // rarely square, and a cropped cup or cake looks broken.
        <span className={cx('photo-fallback flex size-full items-center justify-center', sizes === 'hero' ? 'p-3' : 'p-1.5')}>
          {/* eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage, sized by CSS */}
          <img src={image.url} alt={image.alt ?? product.name} width={image.width ?? undefined} height={image.height ?? undefined}
            loading={priority ? 'eager' : 'lazy'} decoding="async" className={cx("max-h-full max-w-full object-contain drop-shadow-[0_8px_14px_rgb(0_0_0/0.12)]", sizes === "hero" ? "rounded-2xl" : "rounded-xl")} />
        </span>
      ) : image ? (
        // eslint-disable-next-line @next/next/no-img-element -- tenant media from object storage, sized by CSS
        <img src={image.url} alt={image.alt ?? product.name} width={image.width ?? undefined} height={image.height ?? undefined}
          loading={priority ? 'eager' : 'lazy'} decoding="async" className="size-full object-cover object-top" />
      ) : (
        <span aria-hidden="true" className="photo-fallback flex size-full items-center justify-center">
          {createElement(illustration(product), { className: cx('opacity-80', sizes === 'thumb' ? 'size-10' : 'size-20'), strokeWidth: 1.25 })}
        </span>
      )}
    </div>
  );
}

export const hasPhoto = (product: MenuProduct) => product.images.length > 0;
