'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError, toFormState } from '@/lib/api';
import { parseTomanInput } from '@/lib/money';
import type { FormState } from '@/lib/types';

/** Dashboard customisation, goals and shift notes. The API checks every permission. */

export interface LayoutWidget { key: string; size: 'sm' | 'md' | 'lg' }

export async function saveLayout(widgets: LayoutWidget[]): Promise<{ ok: boolean; message?: string }> {
  try {
    await api('/dashboard/layout', { method: 'PUT', body: { widgets } });
  } catch (error) {
    return { ok: false, message: error instanceof ApiError ? error.message : 'ذخیره نشد.' };
  }

  revalidatePath('/dashboard');

  return { ok: true };
}

export async function resetLayout(): Promise<void> {
  await api('/dashboard/layout', { method: 'DELETE' });
  revalidatePath('/dashboard');
}

export async function saveGoals(_prev: FormState, formData: FormData): Promise<FormState> {
  const daily = parseTomanInput(formData.get('daily'));
  const monthly = parseTomanInput(formData.get('monthly'));
  if (Number.isNaN(daily) || Number.isNaN(monthly)) return { ok: false, message: 'مبلغ را فقط با عدد وارد کنید.' };

  try {
    await api('/tenant/settings', { method: 'PATCH', body: { settings: { 'goals.daily_sales': daily ?? 0, 'goals.monthly_sales': monthly ?? 0 } } });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard');

  return { ok: true, message: 'هدف فروش ذخیره شد.' };
}

export async function addShiftNote(_prev: FormState, formData: FormData): Promise<FormState> {
  const body = String(formData.get('body') ?? '').trim();
  if (!body) return { ok: false, errors: { body: 'متن یادداشت را بنویسید.' } };

  try {
    await api('/dashboard/shift-notes', { method: 'POST', body: { body } });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard');

  return { ok: true };
}

export async function deleteShiftNote(id: string): Promise<void> {
  await api(`/dashboard/shift-notes/${id}`, { method: 'DELETE' });
  revalidatePath('/dashboard');
}
