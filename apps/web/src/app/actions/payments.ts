'use server';

import { revalidatePath } from 'next/cache';
import { api, toFormState } from '@/lib/api';
import { parseTomanInput } from '@/lib/money';
import type { FormState } from '@/lib/types';

/** Counter payments and refunds. The idempotency key comes from the form, so a double submit is harmless. */

const INVALID_MONEY = 'مبلغ را فقط با عدد وارد کنید.';

function text(formData: FormData, key: string): string | null {
  const value = formData.get(key);
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

export async function recordPayment(orderId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const amount = parseTomanInput(formData.get('amount'));
  if (Number.isNaN(amount)) return { ok: false, errors: { amount: INVALID_MONEY } };

  try {
    await api(`/orders/${orderId}/payments`, {
      method: 'POST',
      body: {
        method: text(formData, 'method'),
        amount,
        reference: text(formData, 'reference'),
        note: text(formData, 'note'),
        idempotency_key: text(formData, 'idempotency_key'),
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/orders', 'layout');
  revalidatePath('/dashboard/payments');

  return { ok: true, message: 'پرداخت ثبت شد.' };
}

export async function recordRefund(paymentId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const amount = parseTomanInput(formData.get('amount'));
  if (amount === null || Number.isNaN(amount)) return { ok: false, errors: { amount: INVALID_MONEY } };

  try {
    await api(`/payments/${paymentId}/refunds`, {
      method: 'POST',
      body: {
        amount,
        method: text(formData, 'method'),
        reference: text(formData, 'reference'),
        reason: text(formData, 'reason'),
        idempotency_key: text(formData, 'idempotency_key'),
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/orders', 'layout');
  revalidatePath('/dashboard/payments');

  return { ok: true, message: 'بازگشت وجه ثبت شد.' };
}
