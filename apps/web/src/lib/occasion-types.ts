/** The platform's occasions calendar as a café sees it (GET /occasions/upcoming). */

export type OccasionKind = 'national' | 'religious_joy' | 'mourning' | 'ramadan' | 'global' | 'drink_day' | 'season';
export type OccasionTone = 'festive' | 'calm' | 'mourning';
export type SeasonTheme = 'nowruz' | 'yalda' | 'autumn' | 'winter' | 'summer' | 'ramadan';

export interface UpcomingOccasion {
  id: string;
  key: string;
  title: string;
  description: string | null;
  kind: OccasionKind;
  kind_label: string;
  tone: OccasionTone;
  starts_on: string;
  ends_on: string;
  days_left: number;
  is_ongoing: boolean;
  /** Hijri dates are estimates until the platform confirms them (moon sighting). */
  approximate: boolean;
  is_holiday: boolean;
  lead_days: number;
  in_lead: boolean;
  ideas: string[];
  campaign_text: string | null;
  theme: SeasonTheme | null;
  status: 'planned' | 'dismissed' | 'done' | null;
}

export const KIND_LABELS: Record<OccasionKind, string> = {
  national: 'ملی و ایرانی',
  religious_joy: 'مذهبی (شاد)',
  mourning: 'سوگواری',
  ramadan: 'ماه رمضان',
  global: 'جهانی',
  drink_day: 'روزهای جهانی نوشیدنی و خوراکی',
  season: 'فصل‌ها',
};

export const THEME_LABELS: Record<SeasonTheme, string> = {
  nowruz: 'نوروزی (سبزه و شکوفه)',
  yalda: 'یلدا (انار)',
  autumn: 'پاییزی (برگ)',
  winter: 'زمستانی (برف)',
  summer: 'تابستانی',
  ramadan: 'رمضان (فانوس و ماه)',
};

/** "امروز" / "فردا" / "۱۶ روز دیگر" / "در جریان". */
export function occasionWhen(o: Pick<UpcomingOccasion, 'days_left' | 'is_ongoing'>, persian: (n: number) => string): string {
  if (o.is_ongoing) return 'در جریان';
  if (o.days_left === 0) return 'امروز';
  if (o.days_left === 1) return 'فردا';

  return `${persian(o.days_left)} روز دیگر`;
}

/** Where the card's one-click actions go, prefilled for the occasion (dates are café days, YYYY-MM-DD). */
export function discountHref(o: UpcomingOccasion): string {
  const q = new URLSearchParams({ occasion: o.key, name: o.title, from: o.starts_on, to: o.ends_on });

  return `/dashboard/discounts?${q}`;
}

export function collectionHref(o: UpcomingOccasion): string {
  const q = new URLSearchParams({ new: '1', occasion: o.key, title: `ویژه‌ی ${o.title}`, from: o.starts_on, to: o.ends_on });
  if (o.theme) q.set('theme', o.theme);

  return `/dashboard/menu/collections?${q}`;
}
