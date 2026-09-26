/** The café's SMS centre (`/sms`). */

export interface SmsDriverField { key: string; label: string; secret: boolean }
export interface SmsDriver { key: string; label: string; site: string; fields: SmsDriverField[] }

export interface SmsAccountView {
  provider: string;
  fields: Record<string, { is_set: boolean; masked: string | null; value: string | null }>;
  is_active: boolean;
  verified_at: string | null;
  last_error: string | null;
}

export interface SmsTemplateView {
  key: string; label: string; hint: string; default: string; enabled: boolean; body: string;
  placeholders: { key: string; label: string }[];
}

export interface SmsAudience { tier_id?: string | null; birth_month?: number | null; inactive_days?: number | null; has_ordered?: boolean }

export interface SmsCampaignView {
  id: string; name: string; body: string; audience: SmsAudience; status: 'draft' | 'scheduled' | 'sending' | 'done' | 'cancelled';
  parts: number; scheduled_at: string | null; started_at: string | null; finished_at: string | null;
  recipients: number; sent: number; failed: number; created_at: string;
}

export interface SmsCentre {
  account: SmsAccountView | null;
  drivers: SmsDriver[];
  templates: SmsTemplateView[];
  stats: { sent: number; failed: number; skipped: number; parts: number; opted_in: number };
  campaigns: SmsCampaignView[];
  tiers: { id: string; name: string }[];
  rules: { quiet_from: number; quiet_to: number; daily_cap: number; footer: string };
}

export interface SmsLogRow { id: string; kind: string; recipient: string; body: string; parts: number; status: 'sent' | 'failed' | 'skipped'; error: string | null; provider: string | null; created_at: string }

export const SMS_KIND_LABELS: Record<string, string> = {
  order_ready: 'سفارش آماده', order_sent: 'ارسال سفارش', birthday: 'تولد', daily_report: 'گزارش روزانه', campaign: 'کمپین', test: 'آزمایشی',
};

export const SMS_ERRORS: Record<string, string> = {
  not_connected: 'پنل پیامک وصل نبود', connection: 'ارتباط با پنل برقرار نشد', exception: 'خطای ناشناخته',
};

/** Parts of an SMS: Persian 70 per part (67 when split), Latin 160 (153). */
export function smsParts(text: string): number {
  const length = [...text].length;
  if (length === 0) return 0;
  const unicode = /[^\x00-\x7F]/.test(text);
  const [single, multi] = unicode ? [70, 67] : [160, 153];

  return length <= single ? 1 : Math.ceil(length / multi);
}
