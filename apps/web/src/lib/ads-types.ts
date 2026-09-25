/** The café's ads (`/ads`) and the platform review (`/platform/ads`). Amounts are integer rial. */

export type CampaignStatus = 'draft' | 'pending' | 'approved' | 'rejected' | 'paid' | 'suspended' | 'cancelled';

export interface Campaign {
  id: string; name: string; placement: string; status: CampaignStatus; phase: 'scheduled' | 'running' | 'ended' | null;
  start_date: string; days: number; starts_at: string; ends_at: string; cities: string[];
  headline: string; body: string | null; cta: string; cta_label: string; image_url: string | null; image_small_url: string | null;
  daily_price: number; amount: number; review_note: string | null; payment_issue: string | null;
  submitted_at: string | null; reviewed_at: string | null; paid_at: string | null; suspended_at: string | null;
  editable: boolean; impressions: number; clicks: number; ctr: number | null; created_at: string;
}

export interface Placement { key: string; name: string; description: string; daily_price: number; capacity: number; requires_image: boolean; is_active: boolean }

export interface AdsOverview {
  summary: { impressions: number; clicks: number; ctr: number | null; spend: number; live: number; awaiting: number };
  series: { date: string; impressions: number; clicks: number }[];
  campaigns: Campaign[];
  placements: Placement[];
  cities: string[];
  ctas: { key: string; label: string }[];
  vat_rate: number;
}

export interface AdQuote { daily_price: number; days: number; subtotal: number; vat_rate: number; vat: number; total: number; starts_at: string; ends_at: string; available: boolean; remaining: number }

export interface CampaignInput { name: string; placement: string; start_date: string; days: number; cities: string[]; headline: string; body: string; cta: string }

export interface PlatformCampaign extends Campaign { tenant: { name: string | null; slug: string | null } }

/** Label and badge tone of a campaign's state (status, or the phase once paid). */
export function campaignState(c: Pick<Campaign, 'status' | 'phase' | 'payment_issue'>): { label: string; tone: 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'info' | 'accent' } {
  if (c.payment_issue) return { label: 'پرداخت‌شده؛ در انتظار بررسی', tone: 'warning' };
  if (c.status === 'paid') {
    return c.phase === 'running' ? { label: 'در حال نمایش', tone: 'success' } : c.phase === 'scheduled' ? { label: 'زمان‌بندی‌شده', tone: 'info' } : { label: 'پایان‌یافته', tone: 'neutral' };
  }

  return ({
    draft: { label: 'پیش‌نویس', tone: 'neutral' },
    pending: { label: 'در انتظار بررسی', tone: 'warning' },
    approved: { label: 'تأیید شد؛ آماده‌ی پرداخت', tone: 'brand' },
    rejected: { label: 'نیاز به اصلاح', tone: 'danger' },
    suspended: { label: 'متوقف‌شده', tone: 'danger' },
    cancelled: { label: 'لغوشده', tone: 'neutral' },
  } as const)[c.status as Exclude<CampaignStatus, 'paid'>];
}
