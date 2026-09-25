'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';
import { requireStaff } from '@/lib/auth';
import type { Features } from '@/lib/billing-types';

/** Platform-admin actions (the API enforces `actor:platform`; this only avoids pointless calls). */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;
type Result = { ok: true } | { ok: false; message: string };

async function run(path: string, method: 'POST' | 'PUT' | 'DELETE', body?: Record<string, unknown>): Promise<Result> {
  const me = await requireStaff();
  if (!me.user.is_platform_admin) return { ok: false, message: 'دسترسی ندارید.' };
  try {
    await api(path, { method, body, tenant: false });
  } catch (error) {
    if (error instanceof ApiError) return { ok: false, message: error.message };
    throw error;
  }
  revalidatePath('/platform', 'layout');

  return { ok: true };
}

export async function extendSubscription(tenantId: string, days: number, reason: string): Promise<Result> {
  if (!ULID.test(tenantId)) return { ok: false, message: 'کافه نامعتبر است.' };

  return run(`/platform/tenants/${tenantId}/extend`, 'POST', { days, reason });
}

export async function setOverride(tenantId: string, feature: string, value: boolean | number | null, reason: string, expiresAt: string | null): Promise<Result> {
  if (!ULID.test(tenantId)) return { ok: false, message: 'کافه نامعتبر است.' };

  return run(`/platform/tenants/${tenantId}/overrides`, 'POST', { feature, value, reason, expires_at: expiresAt });
}

export async function removeOverride(tenantId: string, feature: string): Promise<Result> {
  if (!ULID.test(tenantId) || !/^[a-z_]{2,40}$/.test(feature)) return { ok: false, message: 'نامعتبر.' };

  return run(`/platform/tenants/${tenantId}/overrides/${feature}`, 'DELETE');
}

export async function markInvoicePaid(tenantId: string, invoiceId: string, reference: string): Promise<Result> {
  if (!ULID.test(tenantId) || !ULID.test(invoiceId)) return { ok: false, message: 'نامعتبر.' };

  return run(`/platform/tenants/${tenantId}/invoices/${invoiceId}/mark-paid`, 'POST', { reference });
}

export async function updatePlan(planId: string, data: { name: string; tagline: string | null; monthly_price: number; yearly_price: number; is_public: boolean; features: Features }): Promise<Result> {
  if (!ULID.test(planId)) return { ok: false, message: 'پلن نامعتبر است.' };

  return run(`/platform/plans/${planId}`, 'PUT', data);
}
