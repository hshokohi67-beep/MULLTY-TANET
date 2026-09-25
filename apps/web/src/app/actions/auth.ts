'use server';

import { redirect } from 'next/navigation';
import { api, toFormState } from '@/lib/api';
import { endSession, selectTenant, startSession } from '@/lib/session';
import type { FormState, MeResponse, StaffUser } from '@/lib/types';
import { toLatinDigits } from '@cafe/locale';

/** Only same-site relative paths are allowed as post-login targets (no open redirects). */
function safeNext(value: FormDataEntryValue | null): string {
  const next = typeof value === 'string' ? value : '';

  return next.startsWith('/') && !next.startsWith('//') && !next.startsWith('/\\') ? next : '/dashboard';
}

export async function login(_prev: FormState, formData: FormData): Promise<FormState> {
  const identifier = toLatinDigits(String(formData.get('identifier') ?? '')).trim();
  const password = String(formData.get('password') ?? '');
  let target = safeNext(formData.get('next'));

  try {
    const result = await api<{ token: string; user: StaffUser }>('/auth/staff/login', {
      method: 'POST',
      auth: false,
      tenant: false,
      body: { identifier, password, device_name: 'dashboard' },
    });
    await startSession(result.token);
    // A platform admin who runs no café lands in the platform panel, not the café picker.
    if (result.user.is_platform_admin && !formData.get('next')) {
      const me = await api<MeResponse>('/auth/staff/me', { tenant: false });
      if (me.memberships.length === 0) target = '/platform';
    }
  } catch (error) {
    return toFormState(error);
  }

  redirect(target);
}

export async function logout(): Promise<void> {
  try {
    await api('/auth/staff/logout', { method: 'POST', tenant: false });
  } catch {
    // The token may already be expired; the local session is cleared either way.
  }
  await endSession();
  redirect('/login');
}

export async function chooseTenant(formData: FormData): Promise<void> {
  const slug = String(formData.get('slug') ?? '');
  const me = await api<MeResponse>('/auth/staff/me', { tenant: false });

  // Only tenants the API says this user belongs to can be selected.
  if (me.memberships.some((m) => m.tenant.slug === slug)) {
    await selectTenant(slug);
    redirect('/dashboard');
  }

  redirect('/select-tenant');
}
