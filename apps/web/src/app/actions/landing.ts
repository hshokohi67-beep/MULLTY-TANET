'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';
import type { LandingContent, LandingDesign, LandingSection, StaffLanding } from '@/lib/landing-types';

/** The landing page editor. The API authorises (`storefront.manage`) and validates every value. */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;
const KINDS = ['hero_photo', 'hero_video', 'story_photo', 'gallery'] as const;
type Result<T> = { ok: true; data: T } | { ok: false; message: string; errors?: Record<string, string> };

function fail(error: unknown): { ok: false; message: string; errors?: Record<string, string> } {
  if (error instanceof ApiError) {
    return { ok: false, message: error.message, errors: Object.fromEntries(Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? ''])) };
  }
  throw error;
}

export async function saveLanding(page: { is_published: boolean; design: LandingDesign; content: LandingContent; sections: LandingSection[] }): Promise<Result<StaffLanding>> {
  try {
    const { data } = await api<{ data: StaffLanding }>('/storefront/landing', { method: 'PUT', body: page });
    revalidatePath('/dashboard/landing');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

/** One photo or the hero video; the file goes to the API as is (it re-encodes / checks it). */
export async function uploadLandingMedia(formData: FormData): Promise<Result<StaffLanding>> {
  const kind = String(formData.get('kind'));
  const file = formData.get('file');
  if (!KINDS.includes(kind as (typeof KINDS)[number]) || !(file instanceof File) || file.size === 0) {
    return { ok: false, message: 'فایلی انتخاب نشده است.' };
  }

  const body = new FormData();
  body.append('kind', kind);
  body.append('file', file);
  const caption = formData.get('caption');
  if (typeof caption === 'string' && caption.trim()) body.append('caption', caption.trim().slice(0, 120));

  try {
    const { data } = await api<{ data: StaffLanding }>('/storefront/landing/media', { method: 'POST', formData: body });
    revalidatePath('/dashboard/landing');

    return { ok: true, data };
  } catch (error) {
    return fail(error);
  }
}

export async function captionLandingMedia(id: string, caption: string): Promise<Result<null>> {
  if (!ULID.test(id)) return { ok: false, message: 'عکس نامعتبر است.' };
  try {
    await api(`/storefront/landing/media/${id}`, { method: 'PATCH', body: { caption: caption.trim().slice(0, 120) } });

    return { ok: true, data: null };
  } catch (error) {
    return fail(error);
  }
}

export async function deleteLandingMedia(id: string): Promise<Result<null>> {
  if (!ULID.test(id)) return { ok: false, message: 'عکس نامعتبر است.' };
  try {
    await api(`/storefront/landing/media/${id}`, { method: 'DELETE' });
    revalidatePath('/dashboard/landing');

    return { ok: true, data: null };
  } catch (error) {
    return fail(error);
  }
}

export async function reorderLandingMedia(ids: string[]): Promise<Result<null>> {
  const clean = ids.filter((id) => ULID.test(id)).slice(0, 12);
  try {
    await api('/storefront/landing/media/order', { method: 'PUT', body: { ids: clean } });

    return { ok: true, data: null };
  } catch (error) {
    return fail(error);
  }
}
