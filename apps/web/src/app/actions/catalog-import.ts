'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError } from '@/lib/api';

/** «ورود از اکسل». The API reads the file, checks every row and does the work; this only forwards. */

export type ImportField = 'name' | 'category' | 'price' | 'description' | 'calories' | 'type';
export interface ImportRow {
  line: number;
  name: string;
  category: string[];
  price: number | null;
  description: string | null;
  calories: number | null;
  status: 'create' | 'update' | 'same' | 'error' | 'skip';
  problems: string[];
  changes?: { price?: { from: number | null; to: number }; description?: boolean; calories?: boolean };
}
export interface ImportPlan {
  columns: Partial<Record<ImportField, number>>;
  header: string[];
  unit: 'toman' | 'rial';
  rows: ImportRow[];
  summary: { create: number; update: number; same: number; error: number; skip: number; total: number };
  new_categories: string[];
}
export interface ImportResult { created: number; updated: number; categories: number; not_created: number }
type Fail = { ok: false; message: string };

function fail(error: unknown): Fail {
  if (error instanceof ApiError) return { ok: false, message: Object.values(error.errors)[0]?.[0] ?? error.message };
  throw error;
}

function forward(formData: FormData): FormData {
  const body = new FormData();
  const file = formData.get('file');
  if (file instanceof File) body.append('file', file);
  const unit = formData.get('unit');
  body.append('unit', unit === 'rial' ? 'rial' : 'toman');
  for (const field of ['name', 'category', 'price', 'description', 'calories', 'type']) {
    const v = formData.get(`columns[${field}]`);
    if (typeof v === 'string' && /^\d{1,2}$/.test(v)) body.append(`columns[${field}]`, v);
  }

  return body;
}

export async function previewImport(formData: FormData): Promise<{ ok: true; plan: ImportPlan } | Fail> {
  try {
    return { ok: true, plan: (await api<{ data: ImportPlan }>('/catalog/import/preview', { method: 'POST', formData: forward(formData) })).data };
  } catch (error) {
    return fail(error);
  }
}

export async function runImport(formData: FormData): Promise<{ ok: true; result: ImportResult } | Fail> {
  try {
    const { data } = await api<{ data: ImportResult }>('/catalog/import', { method: 'POST', formData: forward(formData) });
    revalidatePath('/dashboard/menu');

    return { ok: true, result: data };
  } catch (error) {
    return fail(error);
  }
}
