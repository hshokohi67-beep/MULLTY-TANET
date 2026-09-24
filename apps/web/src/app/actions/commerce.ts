'use server';

import { revalidatePath } from 'next/cache';
import { toLatinDigits } from '@cafe/locale';
import { api, toFormState } from '@/lib/api';
import { parseTomanInput } from '@/lib/money';
import type { FormState } from '@/lib/types';

/** Ordering-side mutations (orders board, tables/QR, delivery zones, discounts). The API authorises everything. */

const INVALID_MONEY = 'مبلغ را فقط با عدد وارد کنید.';

function text(formData: FormData, key: string): string | null {
  const value = formData.get(key);
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

function int(formData: FormData, key: string): number | null {
  const value = text(formData, key);

  return value === null ? null : Number(toLatinDigits(value));
}

export async function transitionOrder(orderId: string, status: string, note?: string): Promise<FormState> {
  try {
    await api(`/orders/${orderId}/status`, { method: 'POST', body: { status, note: note || null } });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/orders', 'layout');

  return { ok: true };
}

export async function acknowledgeTableRequest(requestId: string): Promise<void> {
  await api(`/table-requests/${requestId}/acknowledge`, { method: 'POST' });
  revalidatePath('/dashboard/orders');
}

export async function saveTable(tableId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api(tableId ? `/tables/${tableId}` : '/tables', {
      method: tableId ? 'PUT' : 'POST',
      body: {
        ...(tableId ? {} : { branch_id: text(formData, 'branch_id') }),
        label: text(formData, 'label'),
        capacity: int(formData, 'capacity'),
        ...(tableId ? { is_active: formData.get('is_active') === 'on' } : {}),
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/tables');

  return { ok: true, message: tableId ? 'میز ذخیره شد.' : 'میز اضافه شد. حالا برایش کد QR بسازید.' };
}

export interface QrState extends FormState {
  token?: string;
}

/** Issues a new QR for the table. The raw token is returned once so the QR can be shown/printed now. */
export async function issueTableQr(tableId: string): Promise<QrState> {
  try {
    const { data } = await api<{ data: { qr_token: string; message: string } }>(`/tables/${tableId}/qr`, { method: 'POST' });
    revalidatePath('/dashboard/tables');

    return { ok: true, token: data.qr_token, message: data.message };
  } catch (error) {
    return toFormState(error);
  }
}

export async function closeTableSession(tableId: string): Promise<void> {
  await api(`/tables/${tableId}/close-session`, { method: 'POST' });
  revalidatePath('/dashboard/tables');
}

export async function saveDeliveryZone(zoneId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const fee = parseTomanInput(formData.get('delivery_fee'));
  const free = parseTomanInput(formData.get('free_delivery_min'));
  const min = parseTomanInput(formData.get('min_order'));
  const radiusKm = Number(toLatinDigits(text(formData, 'radius_km') ?? '').replace('٫', '.'));

  if ([fee, free, min].some((v) => Number.isNaN(v))) return { ok: false, message: INVALID_MONEY };
  if (!Number.isFinite(radiusKm) || radiusKm <= 0) return { ok: false, errors: { radius_m: 'شعاع را به کیلومتر وارد کنید (مثلاً ۳ یا ۲٫۵).' } };

  try {
    await api(zoneId ? `/delivery-zones/${zoneId}` : '/delivery-zones', {
      method: zoneId ? 'PUT' : 'POST',
      body: {
        ...(zoneId ? {} : { branch_id: text(formData, 'branch_id') }),
        name: text(formData, 'name'),
        radius_m: Math.round(radiusKm * 1000),
        delivery_fee: fee ?? 0,
        free_delivery_min: free,
        min_order: min ?? 0,
        eta_minutes: int(formData, 'eta_minutes'),
        is_active: zoneId ? formData.get('is_active') === 'on' : true,
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/delivery');

  return { ok: true, message: 'محدوده‌ی ارسال ذخیره شد.' };
}

export async function deleteDeliveryZone(zoneId: string): Promise<void> {
  await api(`/delivery-zones/${zoneId}`, { method: 'DELETE' });
  revalidatePath('/dashboard/delivery');
}

export async function saveDiscount(discountId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const kind = String(formData.get('kind') ?? 'percent');
  const rawValue = text(formData, 'value');
  let value: number;

  if (kind === 'percent') {
    const percent = Number(toLatinDigits(rawValue ?? '').replace('٫', '.'));
    if (!Number.isFinite(percent) || percent <= 0 || percent > 100) return { ok: false, errors: { value: 'درصد باید بین ۱ تا ۱۰۰ باشد.' } };
    value = Math.round(percent * 100);
  } else {
    const rials = parseTomanInput(rawValue);
    if (rials === null || Number.isNaN(rials)) return { ok: false, errors: { value: INVALID_MONEY } };
    value = rials;
  }

  const min = parseTomanInput(formData.get('min_order'));
  const cap = parseTomanInput(formData.get('max_discount'));
  if (Number.isNaN(min) || Number.isNaN(cap)) return { ok: false, message: INVALID_MONEY };

  const weekdays = formData.getAll('weekdays').map(Number);
  const from = text(formData, 'from');
  const to = text(formData, 'to');
  const timed = formData.get('timed') === 'on';

  // The editor only manages the tier restriction; any other rules (set through the API) are kept.
  const RULE_KEYS: Record<string, string> = { product: 'product_ids', category: 'category_ids', branch: 'branch_ids', order_type: 'order_types', customer: 'customer_ids' };
  const existing = JSON.parse(text(formData, 'rules_json') ?? '[]') as { type: string; target: string }[];
  const rules: Record<string, string[]> = {};
  for (const r of existing) {
    const key = RULE_KEYS[r.type];
    if (key) (rules[key] ??= []).push(r.target);
  }
  const tierId = text(formData, 'tier_id');
  if (tierId) rules.tier_ids = [tierId];

  try {
    await api(discountId ? `/discounts/${discountId}` : '/discounts', {
      method: discountId ? 'PUT' : 'POST',
      body: {
        name: text(formData, 'name'),
        code: text(formData, 'code'),
        kind,
        value,
        applies_to: 'order',
        min_order: min ?? 0,
        max_discount: cap,
        usage_limit: int(formData, 'usage_limit'),
        per_customer_limit: int(formData, 'per_customer_limit'),
        is_active: formData.get('is_active') === 'on',
        rules,
        schedule: weekdays.length || timed ? { weekdays: weekdays.length ? weekdays : null, from: timed ? from : null, to: timed ? to : null } : null,
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/discounts');

  return { ok: true, message: 'تخفیف ذخیره شد.' };
}

export async function deleteDiscount(discountId: string): Promise<void> {
  await api(`/discounts/${discountId}`, { method: 'DELETE' });
  revalidatePath('/dashboard/discounts');
}
