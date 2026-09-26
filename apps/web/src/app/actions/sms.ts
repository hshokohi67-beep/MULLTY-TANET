'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';
import type { SmsAccountView, SmsAudience, SmsCampaignView, SmsLogRow } from '@/lib/sms-types';

/** The café's SMS centre. The API authorises (`sms.manage`), validates and never returns secrets. */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;
type Result<T> = { ok: true; data: T } | { ok: false; message: string };

function fail(error: unknown): { ok: false; message: string } {
  if (error instanceof ApiError) return { ok: false, message: error.message };
  throw error;
}

function audience(a: SmsAudience): SmsAudience {
  return {
    tier_id: a.tier_id && ULID.test(a.tier_id) ? a.tier_id : null,
    birth_month: a.birth_month && a.birth_month >= 1 && a.birth_month <= 12 ? Math.floor(a.birth_month) : null,
    inactive_days: a.inactive_days && a.inactive_days >= 7 ? Math.min(730, Math.floor(a.inactive_days)) : null,
    has_ordered: Boolean(a.has_ordered),
  };
}

export async function saveSmsAccount(provider: string, fields: Record<string, string>, isActive: boolean): Promise<Result<SmsAccountView>> {
  const clean = Object.fromEntries(Object.entries(fields).slice(0, 5).map(([k, v]) => [k.slice(0, 20), String(v).trim().slice(0, 200)]));
  try {
    const { data } = await api<{ data: SmsAccountView }>('/sms/account', { method: 'PUT', body: { provider: provider.slice(0, 20), fields: clean, is_active: isActive } });
    revalidatePath('/dashboard/sms');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function sendTestSms(phone: string): Promise<Result<{ sent: boolean }>> {
  try {
    const { data } = await api<{ data: { sent: boolean } }>('/sms/account/test', { method: 'POST', body: phone.trim() ? { phone: phone.trim().slice(0, 20) } : {} });
    revalidatePath('/dashboard/sms');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function saveSmsTemplates(templates: Record<string, { enabled: boolean; body: string }>): Promise<Result<null>> {
  try {
    await api('/sms/templates', { method: 'PUT', body: { templates } });
    revalidatePath('/dashboard/sms');

    return { ok: true, data: null };
  } catch (error) {
    return fail(error);
  }
}

export async function countAudience(a: SmsAudience): Promise<number | null> {
  try {
    return (await api<{ data: { count: number } }>('/sms/audience', { method: 'POST', body: { audience: audience(a) } })).data.count;
  } catch {
    return null;
  }
}

export async function saveSmsCampaign(id: string | null, input: { name: string; body: string; audience: SmsAudience }): Promise<Result<SmsCampaignView>> {
  if (id !== null && !ULID.test(id)) return { ok: false, message: 'کمپین نامعتبر است.' };
  try {
    const body = { name: input.name.trim().slice(0, 80), body: input.body.trim().slice(0, 600), audience: audience(input.audience) };
    const { data } = await api<{ data: SmsCampaignView }>(id ? `/sms/campaigns/${id}` : '/sms/campaigns', { method: id ? 'PUT' : 'POST', body });
    revalidatePath('/dashboard/sms');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function campaignCommand(id: string, command: 'schedule' | 'cancel', sendAt: string | null = null): Promise<Result<SmsCampaignView>> {
  if (!ULID.test(id)) return { ok: false, message: 'کمپین نامعتبر است.' };
  try {
    const { data } = await api<{ data: SmsCampaignView }>(`/sms/campaigns/${id}/${command}`, { method: 'POST', body: command === 'schedule' && sendAt ? { send_at: sendAt } : {} });
    revalidatePath('/dashboard/sms');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function loadSmsLogs(page: number, status: string): Promise<{ data: SmsLogRow[]; meta: { page: number; last_page: number; total: number } } | null> {
  const q = new URLSearchParams({ page: String(Math.max(1, Math.floor(page))) });
  if (['sent', 'failed', 'skipped'].includes(status)) q.set('status', status);
  try {
    return await api(`/sms/logs?${q}`);
  } catch {
    return null;
  }
}
