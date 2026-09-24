'use server';

import { revalidatePath } from 'next/cache';
import { toLatinDigits } from '@cafe/locale';
import { api, toFormState } from '@/lib/api';
import { parseTomanInput } from '@/lib/money';
import type { FormState } from '@/lib/types';

/** Customer club mutations (customers, wallet/points adjustments, program). The API authorises everything. */

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

/** A toman amount with an optional sign ("-۵۰٬۰۰۰") → signed rial. */
function signedToman(formData: FormData, key: string): number | null {
  const raw = text(formData, key);
  if (raw === null) return null;
  const negative = /^[-−]/.test(toLatinDigits(raw));
  const rials = parseTomanInput(raw.replace(/^[-−+]/, ''));

  return rials === null || Number.isNaN(rials) ? Number.NaN : negative ? -rials : rials;
}

export async function updateCustomer(customerId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const month = int(formData, 'birth_month');
  const day = int(formData, 'birth_day');

  try {
    await api(`/customers/${customerId}`, {
      method: 'PATCH',
      body: {
        name: text(formData, 'name'),
        birth_month: month || null,
        birth_day: month ? day || null : null,
        staff_note: text(formData, 'staff_note'),
        marketing_opt_in: formData.get('marketing_opt_in') === 'on',
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath(`/dashboard/customers/${customerId}`);

  return { ok: true, message: 'اطلاعات مشتری ذخیره شد.' };
}

export async function adjustWallet(customerId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const amount = signedToman(formData, 'amount');
  if (amount === null || Number.isNaN(amount) || amount === 0) return { ok: false, errors: { amount: INVALID_MONEY } };

  try {
    await api(`/customers/${customerId}/wallet-adjustments`, {
      method: 'POST',
      body: { amount, reason: text(formData, 'reason'), idempotency_key: text(formData, 'idempotency_key') },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath(`/dashboard/customers/${customerId}`);

  return { ok: true, message: 'کیف پول به‌روز شد.' };
}

export async function adjustPoints(customerId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const raw = text(formData, 'amount');
  const amount = raw === null ? Number.NaN : Number(toLatinDigits(raw).replace('−', '-'));
  if (!Number.isInteger(amount) || amount === 0) return { ok: false, errors: { amount: 'تعداد امتیاز را با عدد وارد کنید (منفی برای کسر).' } };

  try {
    await api(`/customers/${customerId}/points-adjustments`, {
      method: 'POST',
      body: { amount, reason: text(formData, 'reason'), idempotency_key: text(formData, 'idempotency_key') },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath(`/dashboard/customers/${customerId}`);

  return { ok: true, message: 'امتیاز به‌روز شد.' };
}

export async function payOrderWithWallet(orderId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const amount = parseTomanInput(formData.get('amount'));
  if (Number.isNaN(amount)) return { ok: false, errors: { amount: INVALID_MONEY } };

  try {
    await api(`/orders/${orderId}/wallet-payment`, {
      method: 'POST',
      body: { amount, idempotency_key: text(formData, 'idempotency_key') },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/orders', 'layout');

  return { ok: true, message: 'پرداخت با کیف پول انجام شد.' };
}

export async function saveProgram(_prev: FormState, formData: FormData): Promise<FormState> {
  const money = (key: string) => parseTomanInput(formData.get(key)) ?? 0;
  const values = {
    'loyalty.enabled': formData.get('loyalty.enabled') === 'on',
    'wallet.payments_enabled': formData.get('wallet.payments_enabled') === 'on',
    'loyalty.points_per_100k': int(formData, 'loyalty.points_per_100k') ?? 0,
    'loyalty.point_value': money('loyalty.point_value'),
    'loyalty.min_redeem_points': int(formData, 'loyalty.min_redeem_points') ?? 1,
    'loyalty.birthday_wallet_gift': money('loyalty.birthday_wallet_gift'),
    'loyalty.birthday_points': int(formData, 'loyalty.birthday_points') ?? 0,
    'loyalty.referral_referrer_reward': money('loyalty.referral_referrer_reward'),
    'loyalty.referral_referee_reward': money('loyalty.referral_referee_reward'),
  };

  if (Object.values(values).some((v) => typeof v === 'number' && Number.isNaN(v))) {
    return { ok: false, message: 'مقادیر را فقط با عدد وارد کنید.' };
  }

  try {
    await api('/loyalty/program', { method: 'PUT', body: values });
  } catch (error) {
    const state = toFormState(error);
    // "loyalty\.point_value" style keys → form field names.
    state.errors = Object.fromEntries(Object.entries(state.errors ?? {}).map(([k, v]) => [k.replaceAll('\\', ''), v]));

    return state;
  }

  revalidatePath('/dashboard/club');

  return { ok: true, message: 'تنظیمات باشگاه ذخیره شد.' };
}

export async function saveTier(tierId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const minSpend = parseTomanInput(formData.get('min_spend'));
  const multiplier = Number(toLatinDigits(text(formData, 'multiplier') ?? '1').replace('٫', '.'));
  if (minSpend === null || Number.isNaN(minSpend)) return { ok: false, errors: { min_spend: INVALID_MONEY } };
  if (!Number.isFinite(multiplier) || multiplier < 0) return { ok: false, errors: { points_multiplier: 'ضریب را با عدد وارد کنید، مثلاً ۱٫۵' } };

  try {
    await api(tierId ? `/loyalty/tiers/${tierId}` : '/loyalty/tiers', {
      method: tierId ? 'PUT' : 'POST',
      body: {
        name: text(formData, 'name'),
        min_spend: minSpend,
        color: text(formData, 'color') ?? '#94A3B8',
        points_multiplier: Math.round(multiplier * 10000),
        perks: text(formData, 'perks'),
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/club');

  return { ok: true, message: 'سطح ذخیره شد.' };
}

export async function deleteTier(tierId: string): Promise<FormState> {
  try {
    await api(`/loyalty/tiers/${tierId}`, { method: 'DELETE' });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/club');

  return { ok: true };
}

export async function saveCashbackRule(ruleId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const kind = text(formData, 'kind') === 'percent' ? 'percent' : 'fixed';
  const minSpend = parseTomanInput(formData.get('min_spend')) ?? 0;
  const maxReward = parseTomanInput(formData.get('max_reward'));
  const rawValue = text(formData, 'value');
  const value = kind === 'percent'
    ? Math.round(Number(toLatinDigits(rawValue ?? '').replace('٫', '.')) * 100)
    : parseTomanInput(rawValue);

  if (value === null || Number.isNaN(value) || Number.isNaN(minSpend) || Number.isNaN(maxReward)) {
    return { ok: false, errors: { value: INVALID_MONEY } };
  }

  try {
    await api(ruleId ? `/loyalty/cashback-rules/${ruleId}` : '/loyalty/cashback-rules', {
      method: ruleId ? 'PUT' : 'POST',
      body: {
        name: text(formData, 'name'),
        category_id: text(formData, 'category_id'),
        kind,
        value,
        min_spend: minSpend,
        max_reward: maxReward,
        is_active: ruleId ? formData.get('is_active') === 'on' : true,
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/club');

  return { ok: true, message: 'قانون کش‌بک ذخیره شد.' };
}

export async function deleteCashbackRule(ruleId: string): Promise<FormState> {
  try {
    await api(`/loyalty/cashback-rules/${ruleId}`, { method: 'DELETE' });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/club');

  return { ok: true };
}
