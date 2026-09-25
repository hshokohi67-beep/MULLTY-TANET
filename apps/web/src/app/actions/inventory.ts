'use server';

import { revalidatePath } from 'next/cache';
import { toLatinDigits } from '@cafe/locale';
import { api, toFormState } from '@/lib/api';
import { parseTomanInput } from '@/lib/money';
import type { FormState } from '@/lib/types';

/** Inventory & purchasing mutations. The API authorises everything (inventory.* / purchasing.manage). */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

function text(formData: FormData, key: string): string | null {
  const v = formData.get(key);
  if (typeof v !== 'string') return null;
  const t = v.trim();

  return t === '' ? null : t;
}

function num(formData: FormData, key: string): number | null {
  const v = text(formData, key);
  if (v === null) return null;
  const n = Number(toLatinDigits(v).replace(/[٬,]/g, '').replace('٫', '.'));

  return Number.isFinite(n) ? n : NaN;
}

/** Money typed in toman → rial (null when empty). */
function money(formData: FormData, key: string): number | null {
  const v = text(formData, key);

  return v === null ? null : parseTomanInput(v);
}

function refresh() {
  revalidatePath('/dashboard/inventory', 'layout');
  revalidatePath('/dashboard/purchases', 'layout');
}

export async function saveIngredient(id: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  if (id && !ULID.test(id)) return { ok: false, message: 'ماده نامعتبر است.' };
  const unit = text(formData, 'unit') ?? 'g';
  // The threshold is typed in the big unit (kg / L) for weight and volume.
  const threshold = num(formData, 'low_stock_threshold');
  try {
    await api(id ? `/inventory/ingredients/${id}` : '/inventory/ingredients', {
      method: id ? 'PUT' : 'POST',
      body: {
        name: text(formData, 'name'),
        unit,
        pack_label: text(formData, 'pack_label'),
        pack_size: num(formData, 'pack_size'),
        low_stock_threshold: threshold === null ? 0 : unit === 'pcs' ? threshold : threshold * 1000,
        cost_per_big_unit: id ? undefined : money(formData, 'cost_per_big_unit'),
        is_active: id ? formData.get('is_active') === 'on' : true,
      },
    });
  } catch (error) {
    return toFormState(error);
  }
  refresh();

  return { ok: true, message: id ? 'ذخیره شد.' : 'ماده‌ی اولیه اضافه شد.' };
}

export async function deleteIngredient(id: string): Promise<FormState> {
  if (!ULID.test(id)) return { ok: false, message: 'ماده نامعتبر است.' };
  try {
    await api(`/inventory/ingredients/${id}`, { method: 'DELETE' });
  } catch (error) {
    return toFormState(error);
  }
  refresh();

  return { ok: true };
}

export async function adjustStock(key: string, _prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api('/inventory/adjustments', {
      method: 'POST',
      headers: { 'Idempotency-Key': key },
      body: {
        ingredient_id: text(formData, 'ingredient_id'),
        branch_id: text(formData, 'branch_id'),
        type: text(formData, 'type'),
        // The form sends a positive amount and the direction (+/−) separately.
        quantity: (num(formData, 'quantity') ?? 0) * (formData.get('direction') === '-1' ? -1 : 1),
        unit: text(formData, 'unit'),
        note: text(formData, 'note'),
      },
    });
  } catch (error) {
    return toFormState(error);
  }
  refresh();

  return { ok: true, message: 'ثبت شد.' };
}

export async function submitCount(key: string, branchId: string, lines: { ingredient_id: string; counted: number; unit: string }[]): Promise<FormState> {
  try {
    const res = await api<{ data: { adjusted: number } }>('/inventory/counts', { method: 'POST', headers: { 'Idempotency-Key': key }, body: { branch_id: branchId, lines } });
    refresh();

    return { ok: true, message: res.data.adjusted ? `${new Intl.NumberFormat('fa-IR').format(res.data.adjusted)} قلم اصلاح شد.` : 'موجودی‌ها با دفتر یکی بود.' };
  } catch (error) {
    return toFormState(error);
  }
}

export interface RecipeInput {
  variants: { variant_id: string; items: { ingredient_id: string; quantity: number; unit: string }[] }[];
  modifiers: { modifier_id: string; items: { ingredient_id: string; quantity: number; unit: string }[] }[];
}

export async function saveRecipe(productId: string, recipe: RecipeInput): Promise<FormState> {
  if (!ULID.test(productId)) return { ok: false, message: 'محصول نامعتبر است.' };
  try {
    await api(`/catalog/products/${productId}/recipe`, { method: 'PUT', body: recipe });
  } catch (error) {
    return toFormState(error);
  }
  revalidatePath(`/dashboard/menu/${productId}`);

  return { ok: true, message: 'دستور پخت ذخیره شد.' };
}

export async function saveSupplier(id: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  if (id && !ULID.test(id)) return { ok: false, message: 'تأمین‌کننده نامعتبر است.' };
  try {
    await api(id ? `/inventory/suppliers/${id}` : '/inventory/suppliers', {
      method: id ? 'PUT' : 'POST',
      body: { name: text(formData, 'name'), phone: text(formData, 'phone'), notes: text(formData, 'notes'), is_active: id ? formData.get('is_active') === 'on' : true },
    });
  } catch (error) {
    return toFormState(error);
  }
  refresh();

  return { ok: true, message: 'ذخیره شد.' };
}

export interface PurchaseInput {
  supplier_id: string;
  branch_id: string;
  expected_on: string | null;
  note: string | null;
  items: { ingredient_id: string; quantity: number; unit: string; unit_price: number }[];
}

export async function savePurchase(id: string | null, input: PurchaseInput): Promise<FormState & { id?: string }> {
  if (id && !ULID.test(id)) return { ok: false, message: 'سفارش نامعتبر است.' };
  try {
    const res = await api<{ data: { id: string } }>(id ? `/inventory/purchases/${id}` : '/inventory/purchases', { method: id ? 'PUT' : 'POST', body: input });
    refresh();

    return { ok: true, id: res.data.id };
  } catch (error) {
    return toFormState(error);
  }
}

export async function purchaseAction(id: string, action: 'order' | 'cancel' | 'receive', key?: string, lines?: { item_id: string; quantity: number; unit: string; unit_price?: number }[]): Promise<FormState> {
  if (!ULID.test(id)) return { ok: false, message: 'سفارش نامعتبر است.' };
  try {
    await api(`/inventory/purchases/${id}/${action}`, {
      method: 'POST',
      headers: key ? { 'Idempotency-Key': key } : undefined,
      body: action === 'receive' && lines ? { lines } : {},
    });
  } catch (error) {
    return toFormState(error);
  }
  refresh();

  return { ok: true, message: action === 'receive' ? 'تحویل ثبت شد و موجودی به‌روز شد.' : action === 'order' ? 'سفارش ثبت شد.' : 'لغو شد.' };
}

export async function paySupplier(id: string, _prev: FormState, formData: FormData): Promise<FormState> {
  if (!ULID.test(id)) return { ok: false, message: 'سفارش نامعتبر است.' };
  try {
    await api(`/inventory/purchases/${id}/payments`, {
      method: 'POST',
      body: { amount: money(formData, 'amount'), method: text(formData, 'method'), note: text(formData, 'note') },
    });
  } catch (error) {
    return toFormState(error);
  }
  refresh();

  return { ok: true, message: 'پرداخت ثبت شد.' };
}
