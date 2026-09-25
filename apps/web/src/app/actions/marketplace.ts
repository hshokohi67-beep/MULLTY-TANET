'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';
import { requireStaff } from '@/lib/auth';

/** Marketplace listing (owner/manager) and moderation (platform). The API authorises everything. */

export interface ListingInput {
  is_listed: boolean; headline: string | null; about: string | null; categories: string[]; amenities: string[]; price_level: number | null;
}

type Result = { ok: true } | { ok: false; message: string; errors?: Record<string, string> };

function fail(error: unknown): Result {
  if (error instanceof ApiError) {
    return { ok: false, message: error.message, errors: Object.fromEntries(Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? ''])) };
  }
  throw error;
}

export async function saveListing(input: ListingInput): Promise<Result> {
  try {
    await api('/marketplace/listing', {
      method: 'PUT',
      body: {
        is_listed: input.is_listed,
        headline: input.headline?.trim() || null,
        about: input.about?.trim() || null,
        categories: input.categories.slice(0, 3),
        amenities: input.amenities,
        price_level: input.price_level,
      },
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath('/dashboard/marketplace');

  return { ok: true };
}

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

export async function moderate(tenantId: string, action: 'hide' | 'unhide' | 'feature', value: string | null): Promise<Result> {
  const me = await requireStaff();
  if (!me.user.is_platform_admin || !ULID.test(tenantId)) return { ok: false, message: 'دسترسی ندارید.' };
  try {
    const body = action === 'hide' ? { reason: value } : action === 'feature' ? { until: value } : {};
    await api(`/platform/marketplace/${tenantId}/${action}`, { method: 'POST', body, tenant: false });
  } catch (error) {
    return fail(error);
  }
  revalidatePath('/platform/marketplace');

  return { ok: true };
}
