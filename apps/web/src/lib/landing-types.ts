/**
 * A café's landing page (mirrors the API's LandingSchema). Shared by the storefront, which renders
 * it, and the panel editor, which previews it live with the same components.
 */
export type LandingTemplate = 'night' | 'bright' | 'warm' | 'bold';
export type LandingFont = 'vazirmatn' | 'samim';
export type LandingType = 'light' | 'bold';
export type LandingHero = 'cover' | 'center' | 'split' | 'poster';
export type LandingTexture = 'glow' | 'none' | 'grain' | 'dots' | 'art';
export type LandingCorners = 'soft' | 'sharp' | 'round';
export type LandingMotion = 'subtle' | 'none' | 'lively';

export interface LandingDesign {
  template: LandingTemplate;
  font: LandingFont;
  type: LandingType;
  hero: LandingHero;
  texture: LandingTexture;
  corners: LandingCorners;
  motion: LandingMotion;
}

export type SectionKey = 'story' | 'highlights' | 'featured' | 'marquee' | 'gallery' | 'visit';

export interface Highlight { value: string; unit: string | null; label: string }

export interface LandingContent {
  hero: { eyebrow: string | null; title: string; subtitle: string | null; cta_label: string };
  story: { variant: 'photo_end' | 'photo_start' | 'text'; title: string | null; text: string | null };
  highlights: { variant: 'bento' | 'row' | 'numbers'; title: string | null; items: Highlight[] };
  featured: { variant: 'showcase' | 'grid' | 'carousel'; title: string | null; product_ids: string[] };
  marquee: { variant: 'outline' | 'solid'; phrases: string[] };
  gallery: { variant: 'masonry' | 'strip'; title: string | null };
  visit: { variant: 'cards' | 'compact'; title: string | null };
}

export interface LandingSection { key: SectionKey; visible: boolean }

export interface LandingMediaItem {
  id: string;
  url: string;
  thumb_url: string;
  width: number | null;
  height: number | null;
  caption: string | null;
  bytes: number;
}

export interface LandingMedia {
  hero_photo: LandingMediaItem | null;
  hero_video: LandingMediaItem | null;
  story_photo: LandingMediaItem | null;
  gallery: LandingMediaItem[];
}

/** What the storefront receives (only when published). */
export interface PublicLanding {
  design: LandingDesign;
  content: LandingContent;
  sections: LandingSection[];
  media: LandingMedia;
}

/** What the panel edits. */
export interface StaffLanding extends PublicLanding {
  is_published: boolean;
  published_at: string | null;
}

/** Persian labels for every option, in the API's order (the first is the default). */
export const DESIGN_LABELS: { [K in keyof LandingDesign]: { title: string; options: Record<LandingDesign[K], string> } } = {
  template: { title: 'حال‌وهوا', options: { night: 'شب', bright: 'روشن', warm: 'گرم', bold: 'پررنگ' } },
  hero: { title: 'چیدمان سردر', options: { cover: 'تمام‌صفحه', center: 'وسط‌چین', split: 'دوستونه', poster: 'پوستر' } },
  font: { title: 'فونت تیترها', options: { vazirmatn: 'وزیرمتن', samim: 'صمیم' } },
  type: { title: 'وزن تیترها', options: { light: 'نازک و شیک', bold: 'درشت و محکم' } },
  texture: { title: 'بافت پس‌زمینه', options: { glow: 'درخشش', none: 'ساده', grain: 'دانه‌دانه', dots: 'نقطه‌ای', art: 'نقش خوراکی' } },
  corners: { title: 'گوشه‌ها', options: { soft: 'نرم', sharp: 'تیز', round: 'گرد' } },
  motion: { title: 'حرکت', options: { subtle: 'آرام', none: 'بدون حرکت', lively: 'پرجنب‌وجوش' } },
};

export const SECTION_LABELS: Record<SectionKey, { title: string; hint: string; variants: Record<string, string> }> = {
  story: { title: 'داستان ما', hint: 'چند خط درباره‌ی کافه، با یک عکس', variants: { photo_end: 'عکس کنار متن', photo_start: 'عکس اول', text: 'فقط متن' } },
  highlights: { title: 'ویژگی‌ها', hint: 'تا ۴ کارت با یک عدد درشت', variants: { bento: 'کاشی‌کاری', row: 'یک ردیف', numbers: 'فقط عددها' } },
  featured: { title: 'محصولات منتخب', hint: 'تا ۶ محصول از منو، با قیمت زنده', variants: { showcase: 'ویترینی', grid: 'شبکه‌ای', carousel: 'اسلایدی' } },
  marquee: { title: 'نوار متحرک', hint: 'تا ۴ عبارت کوتاه که روی صفحه می‌لغزند', variants: { outline: 'کم‌رنگ', solid: 'رنگی' } },
  gallery: { title: 'گالری', hint: 'تا ۱۲ عکس از فضا و محصولات', variants: { masonry: 'آجری', strip: 'نواری' } },
  visit: { title: 'آدرس و ساعت کاری', hint: 'از اطلاعات شعبه‌ها، خودکار', variants: { cards: 'کارتی', compact: 'فشرده' } },
};
