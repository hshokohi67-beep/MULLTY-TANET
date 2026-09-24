'use server';

import { revalidatePath } from 'next/cache';
import { api, toFormState } from '@/lib/api';
import type { FormState } from '@/lib/types';

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;
const DURATIONS: Record<string, number> = { '24': 24, '72': 72, '168': 168, '336': 336, '720': 720 };

/**
 * Create or edit a story. The photo is forwarded as is (the API re-encodes it); the duration
 * select turns into an end time counted from now ("keep" leaves an existing story's end alone).
 */
export async function saveStory(storyId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  if (storyId && !ULID.test(storyId)) return { ok: false, message: 'استوری نامعتبر است.' };

  const body = new FormData();
  const image = formData.get('image');
  if (image instanceof File && image.size > 0) body.append('image', image);

  const linkType = String(formData.get('link_type') ?? 'none');
  body.append('link_type', linkType);
  const target = linkType === 'product' ? formData.get('link_product') : linkType === 'category' ? formData.get('link_category') : linkType === 'url' ? formData.get('link_url') : null;
  body.append('link_target', typeof target === 'string' ? target.trim() : '');
  for (const key of ['caption', 'cta_label', 'branch_id']) {
    const value = formData.get(key);
    body.append(key, typeof value === 'string' ? value.trim() : '');
  }
  body.append('is_active', formData.get('is_active') === 'on' ? '1' : '0');

  const hours = DURATIONS[String(formData.get('duration'))];
  if (hours) {
    const now = new Date();
    if (!storyId) body.append('starts_at', now.toISOString());
    body.append('ends_at', new Date(now.getTime() + hours * 3600_000).toISOString());
  }

  try {
    await api(storyId ? `/stories/${storyId}` : '/stories', { method: 'POST', formData: body });
  } catch (error) {
    return toFormState(error);
  }

  revalidatePath('/dashboard/stories');

  return { ok: true, message: storyId ? 'استوری ذخیره شد.' : 'استوری منتشر شد.' };
}

export async function deleteStory(storyId: string): Promise<FormState> {
  if (!ULID.test(storyId)) return { ok: false, message: 'استوری نامعتبر است.' };
  try {
    await api(`/stories/${storyId}`, { method: 'DELETE' });
  } catch (error) {
    return toFormState(error);
  }
  revalidatePath('/dashboard/stories');

  return { ok: true };
}

export async function toggleStory(storyId: string, active: boolean): Promise<FormState> {
  if (!ULID.test(storyId)) return { ok: false, message: 'استوری نامعتبر است.' };
  const body = new FormData();
  body.append('is_active', active ? '1' : '0');
  try {
    await api(`/stories/${storyId}`, { method: 'POST', formData: body });
  } catch (error) {
    return toFormState(error);
  }
  revalidatePath('/dashboard/stories');

  return { ok: true };
}

export async function reorderStories(ids: string[]): Promise<FormState> {
  if (!ids.every((id) => ULID.test(id))) return { ok: false, message: 'ترتیب نامعتبر است.' };
  try {
    await api('/stories/order', { method: 'PUT', body: { ids } });
  } catch (error) {
    return toFormState(error);
  }
  revalidatePath('/dashboard/stories');

  return { ok: true };
}
