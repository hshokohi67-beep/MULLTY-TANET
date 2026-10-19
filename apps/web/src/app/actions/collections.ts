'use server';

import { revalidatePath } from 'next/cache';
import { api, toFormState } from '@/lib/api';

export interface CollectionInput {
  title: string;
  subtitle: string | null;
  theme: string | null;
  starts_at: string | null;
  ends_at: string | null;
  occasion_key: string | null;
  is_active: boolean;
  product_ids: string[];
}

/** Creates or updates a «منوی مناسبتی» collection. */
export async function saveCollection(id: string | null, input: CollectionInput): Promise<{ ok: boolean; message?: string; errors?: Record<string, string> }> {
  try {
    await api(id ? `/catalog/collections/${encodeURIComponent(id)}` : '/catalog/collections', { method: id ? 'PUT' : 'POST', body: input });
  } catch (error) {
    const state = toFormState(error);

    return { ok: false, message: state.message, errors: state.errors };
  }
  revalidatePath('/dashboard/menu/collections');

  return { ok: true };
}

export async function deleteCollection(id: string): Promise<void> {
  await api(`/catalog/collections/${encodeURIComponent(id)}`, { method: 'DELETE' });
  revalidatePath('/dashboard/menu/collections');
}
