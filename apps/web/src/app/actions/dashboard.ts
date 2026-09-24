'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { toLatinDigits } from '@cafe/locale';
import { api, toFormState } from '@/lib/api';
import type { Branch, FormState } from '@/lib/types';

/**
 * Dashboard mutations. Every call goes to the API with the staff token and selected tenant;
 * the API enforces membership and permissions. Nothing here is trusted for authorisation.
 */

function text(formData: FormData, key: string): string | null {
  const value = formData.get(key);
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

function digits(formData: FormData, key: string): string | null {
  const value = text(formData, key);

  return value === null ? null : toLatinDigits(value);
}

function number(formData: FormData, key: string): number | null {
  const value = digits(formData, key);

  return value === null ? null : Number(value);
}

export async function saveBranch(branchId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const body = {
    name: text(formData, 'name'),
    slug: text(formData, 'slug')?.toLowerCase() ?? null,
    phone: digits(formData, 'phone'),
    province: text(formData, 'province'),
    city: text(formData, 'city'),
    address: text(formData, 'address'),
    postal_code: digits(formData, 'postal_code'),
    latitude: number(formData, 'latitude'),
    longitude: number(formData, 'longitude'),
    is_active: formData.get('is_active') === 'on',
  };

  let saved: Branch;
  try {
    const response = await api<{ data: Branch }>(branchId ? `/branches/${branchId}` : '/branches', {
      method: branchId ? 'PUT' : 'POST',
      body,
    });
    saved = response.data;
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard', 'layout');

  if (!branchId) {
    redirect(`/dashboard/branches/${saved.id}?created=1`);
  }

  return { ok: true, message: 'تغییرات شعبه ذخیره شد.' };
}

export async function saveOpeningHours(branchId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  let intervals: unknown;
  try {
    intervals = JSON.parse(String(formData.get('intervals') ?? '[]'));
  } catch {
    return { ok: false, message: 'اطلاعات ساعات کاری معتبر نیست.' };
  }

  try {
    await api(`/branches/${branchId}/opening-hours`, { method: 'PUT', body: { intervals } });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard', 'layout');

  return { ok: true, message: 'ساعات کاری ذخیره شد.' };
}

export async function updateTenantProfile(_prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api('/tenant', {
      method: 'PATCH',
      body: { name: text(formData, 'name'), display_currency_unit: text(formData, 'display_currency_unit') },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard', 'layout');

  return { ok: true, message: 'اطلاعات کسب‌وکار ذخیره شد.' };
}

export async function updateBranding(_prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api('/tenant/branding', {
      method: 'PATCH',
      body: {
        primary_color: text(formData, 'primary_color'),
        theme: text(formData, 'theme'),
        seo_title: text(formData, 'seo_title'),
        seo_description: text(formData, 'seo_description'),
      },
    });

    const logo = formData.get('logo');
    if (logo instanceof File && logo.size > 0) {
      const upload = new FormData();
      upload.append('logo', logo);
      await api('/tenant/branding/logo', { method: 'POST', formData: upload });
    }

    const cover = formData.get('cover');
    if (cover instanceof File && cover.size > 0) {
      const upload = new FormData();
      upload.append('cover', cover);
      await api('/tenant/branding/cover', { method: 'POST', formData: upload });
    } else if (formData.get('remove_cover') === 'on') {
      await api('/tenant/branding/cover', { method: 'DELETE' });
    }
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard', 'layout');

  return { ok: true, message: 'برند و سئو ذخیره شد.' };
}

export async function updateSettings(_prev: FormState, formData: FormData): Promise<FormState> {
  const settings: Record<string, string | boolean | null> = {
    'contact.phone': digits(formData, 'contact.phone'),
    'contact.instagram': text(formData, 'contact.instagram'),
  };

  // Secrets are write-only: an empty field means "keep the current value".
  const apiKey = text(formData, 'integrations.sms.kavenegar_api_key');
  if (apiKey !== null) {
    settings['integrations.sms.kavenegar_api_key'] = apiKey;
  }

  try {
    await api('/tenant/settings', { method: 'PATCH', body: { settings } });
  } catch (error) {
    const state = toFormState(error);
    // Map "settings.contact.phone" style keys back to form field names.
    state.errors = Object.fromEntries(Object.entries(state.errors ?? {}).map(([k, v]) => [k.replace(/^settings\./, ''), v]));

    return state;
  }

  revalidatePath('/dashboard/settings');

  return { ok: true, message: 'تنظیمات ذخیره شد.' };
}

export async function updatePaymentSettings(_prev: FormState, formData: FormData): Promise<FormState> {
  const settings: Record<string, string | boolean | null> = {
    'payments.online.enabled': formData.get('payments.online.enabled') === 'on',
  };

  // Write-only secret: an empty field keeps the current merchant ID.
  const merchant = text(formData, 'payments.zarinpal.merchant_id');
  if (merchant !== null) {
    settings['payments.zarinpal.merchant_id'] = merchant;
  }

  try {
    await api('/tenant/settings', { method: 'PATCH', body: { settings } });
  } catch (error) {
    const state = toFormState(error);
    state.errors = Object.fromEntries(Object.entries(state.errors ?? {}).map(([k, v]) => [k.replace(/^settings\./, ''), v]));

    return state;
  }

  revalidatePath('/dashboard/settings');

  return { ok: true, message: 'تنظیمات پرداخت ذخیره شد.' };
}

export async function updateReportSettings(_prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api('/tenant/settings', {
      method: 'PATCH',
      body: { settings: { 'reports.daily_sms': formData.get('reports.daily_sms') === 'on', 'reports.daily_sms_hour': Number(formData.get('reports.daily_sms_hour') ?? 23) } },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/settings');

  return { ok: true, message: 'تنظیمات گزارش ذخیره شد.' };
}

export async function updatePreorderSettings(_prev: FormState, formData: FormData): Promise<FormState> {
  const n = (key: string) => Number(toLatinDigits(String(formData.get(key) ?? '0')) || 0);
  try {
    await api('/tenant/settings', {
      method: 'PATCH',
      body: { settings: {
        'orders.allow_preorder_when_closed': formData.get('orders.allow_preorder_when_closed') === 'on',
        'preorder.lead_minutes': n('preorder.lead_minutes'),
        'preorder.max_days': n('preorder.max_days'),
        'preorder.slot_minutes': n('preorder.slot_minutes'),
        'preorder.slot_capacity': n('preorder.slot_capacity'),
        'preorder.release_minutes': n('preorder.release_minutes'),
      } },
    });
  } catch (error) {
    const state = toFormState(error);
    // Setting errors come back as settings.<key>; show them under the field.
    return { ...state, errors: Object.fromEntries(Object.entries(state.errors ?? {}).map(([k, v]) => [k.replace(/^settings\./, ''), v])) };
  }

  revalidatePath('/dashboard/settings');

  return { ok: true, message: 'تنظیمات پیش‌سفارش ذخیره شد.' };
}

export async function addTeamMember(_prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api('/team', {
      method: 'POST',
      body: {
        name: text(formData, 'name'),
        phone: digits(formData, 'phone'),
        password: text(formData, 'password'),
        role_ids: formData.getAll('role_ids').map(String),
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard', 'layout');

  return { ok: true, message: 'همکار جدید به تیم اضافه شد.' };
}

export async function updateMemberRoles(memberId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api(`/team/${memberId}/roles`, { method: 'PUT', body: { role_ids: formData.getAll('role_ids').map(String) } });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/team');

  return { ok: true, message: 'نقش‌ها به‌روز شد.' };
}
