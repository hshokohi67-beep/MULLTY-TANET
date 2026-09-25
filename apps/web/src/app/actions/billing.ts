'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';
import type { Quote, Selection } from '@/lib/billing-types';

/** Subscription actions for the café owner. The API authorises (`billing.manage`) and prices everything. */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

type Result<T> = { ok: true; data: T } | { ok: false; message: string };

function fail(error: unknown): { ok: false; message: string } {
  if (error instanceof ApiError) return { ok: false, message: error.message };
  throw error;
}

function clean(selection: Selection): Selection {
  return {
    plan_id: selection.plan_id,
    cycle: selection.cycle === 'yearly' ? 'yearly' : 'monthly',
    addons: selection.addons.filter((a) => ULID.test(a.addon_id) && a.quantity > 0).map((a) => ({ addon_id: a.addon_id, quantity: Math.min(20, Math.floor(a.quantity)) })),
  };
}

export async function quoteSelection(selection: Selection): Promise<Result<Quote>> {
  if (!ULID.test(selection.plan_id)) return { ok: false, message: 'پلن نامعتبر است.' };
  try {
    return { ok: true, data: (await api<{ data: Quote }>('/billing/quote', { method: 'POST', body: clean(selection) })).data };
  } catch (error) {
    return fail(error);
  }
}

/** Starts the checkout: either a gateway URL to go to, or a change scheduled for the period end. */
export async function checkoutSelection(selection: Selection): Promise<Result<{ mode: string; redirect_url: string | null }>> {
  if (!ULID.test(selection.plan_id)) return { ok: false, message: 'پلن نامعتبر است.' };
  try {
    const { data } = await api<{ data: { mode: string; redirect_url: string | null } }>('/billing/checkout', { method: 'POST', body: clean(selection) });
    revalidatePath('/dashboard/billing');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function payInvoice(id: string): Promise<Result<{ redirect_url: string }>> {
  if (!ULID.test(id)) return { ok: false, message: 'صورت‌حساب نامعتبر است.' };
  try {
    return { ok: true, data: (await api<{ data: { redirect_url: string } }>(`/billing/invoices/${id}/pay`, { method: 'POST' })).data };
  } catch (error) {
    return fail(error);
  }
}

export async function setCancelled(cancel: boolean): Promise<Result<null>> {
  try {
    await api(cancel ? '/billing/cancel' : '/billing/resume', { method: 'POST' });
    revalidatePath('/dashboard', 'layout');

    return { ok: true, data: null };
  } catch (error) {
    return fail(error);
  }
}
