'use server';

import { revalidatePath } from 'next/cache';
import { api, toFormState } from '@/lib/api';
import type { OccasionKind } from '@/lib/occasion-types';

/** The café's answer to an upcoming occasion (planned / dismissed / done, or null to undo). */
export async function setOccasionState(id: string, status: 'planned' | 'dismissed' | 'done' | null): Promise<{ ok: boolean; message?: string }> {
  try {
    await api(`/occasions/${encodeURIComponent(id)}/state`, { method: 'PUT', body: { status } });
  } catch (error) {
    return { ok: false, message: toFormState(error).message ?? 'ذخیره نشد.' };
  }
  revalidatePath('/dashboard/occasions');
  revalidatePath('/dashboard');

  return { ok: true };
}

/** Which kinds of occasions the café follows and whether it is reminded (needs settings.update). */
export async function saveOccasionPreferences(kinds: OccasionKind[], notify: boolean): Promise<{ ok: boolean; message?: string }> {
  try {
    await api('/tenant/settings', { method: 'PATCH', body: { settings: { 'occasions.kinds': kinds.join(','), 'occasions.notify': notify } } });
  } catch (error) {
    return { ok: false, message: toFormState(error).message ?? 'ذخیره نشد.' };
  }
  revalidatePath('/dashboard/occasions');

  return { ok: true };
}
