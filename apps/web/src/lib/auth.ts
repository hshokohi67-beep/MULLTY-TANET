import 'server-only';
import { cache } from 'react';
import { redirect } from 'next/navigation';
import { api, ApiError } from './api';
import { getStaffToken, getTenantSlug } from './session';
import type { MeResponse, Membership } from './types';

/** The signed-in staff user, fetched once per request. Redirects to login when the session is missing/expired. */
export const requireStaff = cache(async (): Promise<MeResponse> => {
  if (!(await getStaffToken())) {
    redirect('/login');
  }

  try {
    return await api<MeResponse>('/auth/staff/me', { tenant: false });
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      redirect('/login?expired=1');
    }
    throw error;
  }
});

export interface DashboardContext extends MeResponse {
  membership: Membership;
  can: (permission: string) => boolean;
}

/** Staff user + the tenant selected in this browser. Redirects to the tenant picker when none/invalid. */
export const requireMembership = cache(async (): Promise<DashboardContext> => {
  const me = await requireStaff();
  const slug = await getTenantSlug();
  const membership = me.memberships.find((m) => m.tenant.slug === slug);

  if (!membership) {
    redirect('/select-tenant');
  }

  return { ...me, membership, can: (permission) => membership.permissions.includes(permission) };
});
