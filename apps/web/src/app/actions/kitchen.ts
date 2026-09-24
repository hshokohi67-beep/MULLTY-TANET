'use server';

import { revalidatePath } from 'next/cache';
import { toLatinDigits } from '@cafe/locale';
import { api, ApiError, toFormState } from '@/lib/api';
import type { FormState } from '@/lib/types';

/** Kitchen setup (stations, routing, devices). The API requires kds.manage. */

function text(formData: FormData, key: string): string | null {
  const value = formData.get(key);
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

export async function saveStation(stationId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const late = Number(toLatinDigits(text(formData, 'late_after_minutes') ?? '7'));

  try {
    await api(stationId ? `/kitchen/stations/${stationId}` : '/kitchen/stations', {
      method: stationId ? 'PUT' : 'POST',
      body: {
        ...(stationId ? {} : { branch_id: text(formData, 'branch_id') }),
        name: text(formData, 'name'),
        late_after_minutes: Number.isFinite(late) ? late : 7,
        is_default: formData.get('is_default') === 'on',
        ...(stationId ? { is_active: formData.get('is_active') === 'on' } : {}),
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/kitchen');

  return { ok: true, message: 'ایستگاه ذخیره شد.' };
}

export async function deleteStation(stationId: string): Promise<FormState> {
  try {
    await api(`/kitchen/stations/${stationId}`, { method: 'DELETE' });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/kitchen');

  return { ok: true };
}

export async function saveRouting(stationId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api(`/kitchen/stations/${stationId}/products`, { method: 'PUT', body: { product_ids: formData.getAll('product_ids').map(String) } });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/kitchen');

  return { ok: true, message: 'مسیر آیتم‌ها ذخیره شد.' };
}

export interface PairingResult {
  ok: boolean;
  message?: string;
  code?: string;
  deviceName?: string;
}

export async function createDevice(_prev: PairingResult, formData: FormData): Promise<PairingResult> {
  try {
    const result = await api<{ data: { name: string }; pairing_code: string }>('/kitchen/devices', {
      method: 'POST',
      body: { name: text(formData, 'name'), branch_id: text(formData, 'branch_id'), station_id: text(formData, 'station_id') },
    });
    revalidatePath('/dashboard/kitchen');

    return { ok: true, code: result.pairing_code, deviceName: result.data.name };
  } catch (error) {
    return { ok: false, message: error instanceof ApiError ? error.message : 'خطایی رخ داد.' };
  }
}

export async function repairDevice(deviceId: string): Promise<PairingResult> {
  try {
    const result = await api<{ data: { name: string }; pairing_code: string }>(`/kitchen/devices/${deviceId}/repair`, { method: 'POST' });
    revalidatePath('/dashboard/kitchen');

    return { ok: true, code: result.pairing_code, deviceName: result.data.name };
  } catch (error) {
    return { ok: false, message: error instanceof ApiError ? error.message : 'خطایی رخ داد.' };
  }
}

export async function revokeDevice(deviceId: string): Promise<void> {
  await api(`/kitchen/devices/${deviceId}/revoke`, { method: 'POST' });
  revalidatePath('/dashboard/kitchen');
}

export async function saveKitchenSettings(_prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api('/tenant/settings', {
      method: 'PATCH',
      body: {
        settings: {
          'kds.auto_complete_dine_in': formData.get('kds.auto_complete_dine_in') === 'on',
          'kds.auto_complete_takeaway': formData.get('kds.auto_complete_takeaway') === 'on',
        },
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/kitchen');

  return { ok: true, message: 'تنظیمات آشپزخانه ذخیره شد.' };
}
