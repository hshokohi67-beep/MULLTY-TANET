'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';
import type { Branch } from '@/lib/types';

export interface SearchItem { id: string; title: string; subtitle: string; href: string; sold_out?: boolean; active?: boolean }
export interface SearchGroup { group: 'products' | 'categories' | 'orders' | 'customers'; label: string; items: SearchItem[] }
export interface AlertItem { type: string; severity: 'danger' | 'warning' | 'info'; title: string; count: number; href: string }
export interface SetupData { steps: { key: string; title: string; hint: string; href: string; done: boolean; essential: boolean }[]; done: number; total: number }

/** Command palette record search (the API decides which groups this user may see). */
export async function searchDashboard(q: string): Promise<SearchGroup[]> {
  const term = q.trim().slice(0, 60);
  if (!term) return [];
  try {
    return (await api<{ data: SearchGroup[] }>(`/dashboard/search?q=${encodeURIComponent(term)}`)).data;
  } catch (error) {
    if (error instanceof ApiError) return [];
    throw error;
  }
}

/** «تمام شد / موجود شد» from the palette: applies to every branch. */
export async function toggleSoldOut(productId: string, soldOut: boolean): Promise<{ ok: boolean; message?: string }> {
  if (!/^[0-9a-z]{26}$/i.test(productId)) return { ok: false, message: 'محصول نامعتبر است.' };
  try {
    const { data: branches } = await api<{ data: Branch[] }>('/branches');
    await Promise.all(branches.map((b) => api(`/catalog/products/${productId}/availability`, {
      method: 'PUT',
      body: { branch_id: b.id, status: soldOut ? 'sold_out' : 'available' },
    })));
  } catch (error) {
    return { ok: false, message: error instanceof ApiError ? error.message : 'انجام نشد.' };
  }
  revalidatePath('/dashboard', 'layout');

  return { ok: true };
}

export async function loadAlerts(): Promise<AlertItem[]> {
  try {
    return (await api<{ data: AlertItem[] }>('/dashboard/alerts')).data;
  } catch {
    return [];
  }
}

export async function skipSetupStep(step: string, skip: boolean): Promise<SetupData | null> {
  try {
    const { data } = await api<{ data: SetupData }>('/dashboard/setup/skip', { method: 'POST', body: { step, skip } });
    revalidatePath('/dashboard');

    return data;
  } catch {
    return null;
  }
}
