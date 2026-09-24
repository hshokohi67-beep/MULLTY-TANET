'use server';

import { redirect } from 'next/navigation';
import { toLatinDigits } from '@cafe/locale';
import { api, ApiError, toFormState } from '@/lib/api';
import { forgetDevice, kdsApi, storeDevice } from '@/lib/kds';
import type { FormState } from '@/lib/types';

/** Kitchen-screen mutations. The API checks the device/staff token, branch and station on every call. */

export interface KdsResult {
  ok: boolean;
  message?: string;
}

async function run(path: string, body?: unknown): Promise<KdsResult> {
  try {
    await kdsApi(path, { method: 'POST', body });
  } catch (error) {
    return { ok: false, message: error instanceof ApiError ? error.message : 'خطایی رخ داد.' };
  }

  return { ok: true };
}

export async function kdsItem(itemId: string, action: 'start' | 'ready' | 'recall'): Promise<KdsResult> {
  return run(`/kds/items/${encodeURIComponent(itemId)}/${action}`);
}

export async function kdsBump(orderId: string, stationIds: string[]): Promise<KdsResult> {
  for (const stationId of stationIds) {
    const result = await run(`/kds/orders/${encodeURIComponent(orderId)}/bump`, { station_id: stationId });
    if (!result.ok) return result;
  }

  return { ok: true };
}

export async function kdsAcknowledge(requestId: string): Promise<KdsResult> {
  return run(`/kds/table-requests/${encodeURIComponent(requestId)}/acknowledge`);
}

export async function pairDevice(_prev: FormState, formData: FormData): Promise<FormState> {
  const tenant = String(formData.get('tenant') ?? '').trim();
  const code = toLatinDigits(String(formData.get('code') ?? '')).replace(/\D/g, '');

  if (!/^[a-z0-9-]{2,60}$/.test(tenant)) return { ok: false, message: 'نشانی اتصال کامل نیست؛ لینک را دوباره از پنل مدیریت باز کنید.' };
  if (code.length !== 6) return { ok: false, errors: { code: 'کد ۶ رقمی را کامل وارد کنید.' } };

  try {
    const result = await api<{ token: string }>('/public/kds/pair', { method: 'POST', auth: false, tenant, body: { code } });
    await storeDevice(result.token, tenant);
  } catch (error) {
    return toFormState(error);
  }

  redirect('/kds');
}

export async function unpairDevice(): Promise<void> {
  await forgetDevice();
  redirect('/kds/pair');
}
