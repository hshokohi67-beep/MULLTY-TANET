'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';
import type { AdQuote, Campaign, CampaignInput } from '@/lib/ads-types';

/** The café's ad campaigns. The API authorises (`ads.manage`), prices and validates everything. */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;
const DATE = /^\d{4}-\d{2}-\d{2}$/;

type Result<T> = { ok: true; data: T } | { ok: false; message: string; errors?: Record<string, string> };

function fail(error: unknown): { ok: false; message: string; errors?: Record<string, string> } {
  if (error instanceof ApiError) {
    return { ok: false, message: error.message, errors: Object.fromEntries(Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? ''])) };
  }
  throw error;
}

function clean(input: CampaignInput): CampaignInput {
  return {
    name: input.name.trim().slice(0, 80),
    placement: input.placement.slice(0, 24),
    start_date: DATE.test(input.start_date) ? input.start_date : '',
    days: Math.max(1, Math.min(30, Math.floor(Number(input.days) || 1))),
    cities: input.cities.slice(0, 10).map((c) => c.trim().slice(0, 60)).filter(Boolean),
    headline: input.headline.trim().slice(0, 40),
    body: input.body.trim().slice(0, 90),
    cta: input.cta.slice(0, 12),
  };
}

export async function quoteCampaign(input: Pick<CampaignInput, 'placement' | 'start_date' | 'days'>, campaignId: string | null): Promise<Result<AdQuote>> {
  if (!DATE.test(input.start_date) || (campaignId !== null && !ULID.test(campaignId))) return { ok: false, message: 'تاریخ نامعتبر است.' };
  try {
    const body = { placement: input.placement.slice(0, 24), start_date: input.start_date, days: Math.max(1, Math.min(30, Math.floor(input.days))), ...(campaignId ? { campaign: campaignId } : {}) };

    return { ok: true, data: (await api<{ data: AdQuote }>('/ads/quote', { method: 'POST', body })).data };
  } catch (error) {
    return fail(error);
  }
}

export async function saveCampaign(campaignId: string | null, input: CampaignInput): Promise<Result<Campaign>> {
  if (campaignId !== null && !ULID.test(campaignId)) return { ok: false, message: 'کمپین نامعتبر است.' };
  try {
    const { data } = await api<{ data: Campaign }>(campaignId ? `/ads/campaigns/${campaignId}` : '/ads/campaigns', { method: campaignId ? 'PUT' : 'POST', body: clean(input) });
    revalidatePath('/dashboard/ads');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function uploadCampaignImage(campaignId: string, formData: FormData): Promise<Result<Campaign>> {
  const image = formData.get('image');
  if (!ULID.test(campaignId) || !(image instanceof File) || image.size === 0) return { ok: false, message: 'تصویری انتخاب نشده است.' };
  const body = new FormData();
  body.append('image', image);
  try {
    const { data } = await api<{ data: Campaign }>(`/ads/campaigns/${campaignId}/image`, { method: 'POST', formData: body });
    revalidatePath('/dashboard/ads');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

/** submit | cancel | remove-image */
export async function campaignAction(campaignId: string, action: 'submit' | 'cancel' | 'remove-image'): Promise<Result<Campaign>> {
  if (!ULID.test(campaignId)) return { ok: false, message: 'کمپین نامعتبر است.' };
  try {
    const { data } = action === 'remove-image'
      ? await api<{ data: Campaign }>(`/ads/campaigns/${campaignId}/image`, { method: 'DELETE' })
      : await api<{ data: Campaign }>(`/ads/campaigns/${campaignId}/${action}`, { method: 'POST' });
    revalidatePath('/dashboard/ads');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

/** Opens the gateway for an approved campaign; the browser is sent to the returned URL. */
export async function payCampaign(campaignId: string): Promise<Result<{ redirect_url: string }>> {
  if (!ULID.test(campaignId)) return { ok: false, message: 'کمپین نامعتبر است.' };
  try {
    return { ok: true, data: (await api<{ data: { redirect_url: string } }>(`/ads/campaigns/${campaignId}/pay`, { method: 'POST' })).data };
  } catch (error) {
    return fail(error);
  }
}
