'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { toLatinDigits } from '@cafe/locale';
import { api, toFormState } from '@/lib/api';
import { parseTomanInput } from '@/lib/money';
import type { BulkPriceResult, FormState, Product } from '@/lib/types';

/**
 * Menu mutations. Amounts are typed in toman and sent to the API as integer rial.
 * Authorisation is enforced by the API (catalog.manage / prices.manage / availability.manage).
 */

const INVALID_MONEY = 'مبلغ را فقط با عدد وارد کنید (مثلاً ۸۵۰۰۰).';

function text(formData: FormData, key: string): string | null {
  const value = formData.get(key);
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();

  return trimmed === '' ? null : trimmed;
}

function int(formData: FormData, key: string): number | null {
  const value = text(formData, key);

  return value === null ? null : Number(toLatinDigits(value));
}

function refresh(): void {
  revalidatePath('/dashboard/menu', 'layout');
}

export interface QuickAddState extends FormState {
  created?: string;
}

export async function quickAddProduct(_prev: QuickAddState, formData: FormData): Promise<QuickAddState> {
  const price = parseTomanInput(formData.get('price'));

  if (price === null || Number.isNaN(price)) {
    return { ok: false, errors: { price: price === null ? 'قیمت را وارد کنید.' : INVALID_MONEY } };
  }

  try {
    const { data } = await api<{ data: Product }>('/catalog/products/quick', {
      method: 'POST',
      body: { name: text(formData, 'name'), price, category_id: text(formData, 'category_id') },
    });
    refresh();

    return { ok: true, message: `«${data.name}» به منو اضافه شد.`, created: data.id };
  } catch (error) {
    return toFormState(error);
  }
}

export async function saveProductDetails(productId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const nutrition: Record<string, number> = {};
  for (const key of ['calories', 'caffeine_mg', 'protein_g']) {
    const value = int(formData, `nutrition.${key}`);
    if (value !== null) nutrition[key] = value;
  }

  try {
    await api(`/catalog/products/${productId}`, {
      method: 'PUT',
      body: {
        name: text(formData, 'name'),
        description: text(formData, 'description'),
        is_active: formData.get('is_active') === 'on',
        is_featured: formData.get('is_featured') === 'on',
        temperature: mood(formData),
        sort: int(formData, 'sort') ?? 0,
        category_ids: formData.getAll('category_ids').map(String),
        nutrition: Object.keys(nutrition).length ? nutrition : null,
        dietary_tags: formData.getAll('dietary_tags').map(String),
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: 'مشخصات محصول ذخیره شد.' };
}

export async function saveVariants(productId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const ids = formData.getAll('variant_id').map(String);
  const names = formData.getAll('variant_name').map(String);
  const prices = formData.getAll('variant_price');
  const errors: Record<string, string> = {};

  const variants = ids.map((id, i) => {
    const basePrice = parseTomanInput(prices[i] ?? null);
    if (basePrice === null || Number.isNaN(basePrice)) errors[`variants.${i}.base_price`] = basePrice === null ? 'قیمت را وارد کنید.' : INVALID_MONEY;

    return { id: id || null, name: names[i]?.trim() || null, base_price: basePrice };
  });

  if (Object.keys(errors).length) return { ok: false, message: 'قیمت‌ها را بررسی کنید.', errors };

  try {
    await api(`/catalog/products/${productId}/variants`, { method: 'PUT', body: { variants } });
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: 'سایزها و قیمت‌ها ذخیره شد.' };
}

export async function saveBranchPrices(productId: string, branchId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const prices = formData.getAll('variant_id').map((variantId, i) => {
    const amount = parseTomanInput(formData.getAll('amount')[i] ?? null);

    return { variant_id: String(variantId), amount };
  });

  if (prices.some((p) => Number.isNaN(p.amount))) return { ok: false, message: INVALID_MONEY };

  try {
    await api(`/catalog/products/${productId}/branch-prices`, { method: 'PUT', body: { branch_id: branchId, prices } });
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: 'قیمت‌های این شعبه ذخیره شد. خانه‌ی خالی یعنی همان قیمت پایه.' };
}

export async function saveProductModifierGroups(productId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  try {
    await api(`/catalog/products/${productId}/modifier-groups`, {
      method: 'PUT',
      body: { modifier_group_ids: formData.getAll('modifier_group_ids').map(String) },
    });
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: 'افزودنی‌های محصول ذخیره شد.' };
}

export async function uploadProductImage(productId: string, _prev: FormState, formData: FormData): Promise<FormState> {
  const image = formData.get('image');
  if (!(image instanceof File) || image.size === 0) return { ok: false, errors: { image: 'یک تصویر انتخاب کنید.' } };

  const upload = new FormData();
  upload.append('image', image);

  try {
    await api(`/catalog/products/${productId}/images`, { method: 'POST', formData: upload });
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: 'تصویر اضافه شد.' };
}

export async function deleteProductImage(productId: string, imageId: string): Promise<void> {
  await api(`/catalog/products/${productId}/images/${imageId}`, { method: 'DELETE' });
  refresh();
}

export async function setAvailability(productId: string, branchId: string, status: 'available' | 'sold_out' | 'hidden'): Promise<void> {
  await api(`/catalog/products/${productId}/availability`, { method: 'PUT', body: { branch_id: branchId, status } });
  refresh();
}

export async function deleteProduct(productId: string): Promise<void> {
  await api(`/catalog/products/${productId}`, { method: 'DELETE' });
  refresh();
  redirect('/dashboard/menu');
}

/** Menu mood from a form select: 'hot' | 'cold', anything else = neutral/inherit. */
function mood(formData: FormData): 'hot' | 'cold' | null {
  const value = formData.get('temperature');

  return value === 'hot' || value === 'cold' ? value : null;
}

export async function saveCategory(categoryId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  try {
    const saved = await api<{ data: { id: string } }>(categoryId ? `/catalog/categories/${categoryId}` : '/catalog/categories', {
      method: categoryId ? 'PUT' : 'POST',
      body: {
        name: text(formData, 'name'),
        parent_id: text(formData, 'parent_id'),
        sort: int(formData, 'sort') ?? 0,
        is_active: categoryId ? formData.get('is_active') === 'on' : true,
        temperature: mood(formData),
      },
    });

    // The round picture on the menu chip (re-encoded by the API).
    const image = formData.get('image');
    if (image instanceof File && image.size > 0) {
      const upload = new FormData();
      upload.append('image', image);
      await api(`/catalog/categories/${saved.data.id}/image`, { method: 'POST', formData: upload });
    } else if (formData.get('remove_image') === 'on') {
      await api(`/catalog/categories/${saved.data.id}/image`, { method: 'DELETE' });
    }
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: categoryId ? 'دسته‌بندی ذخیره شد.' : 'دسته‌بندی اضافه شد.' };
}

export async function deleteCategory(categoryId: string): Promise<FormState> {
  try {
    await api(`/catalog/categories/${categoryId}`, { method: 'DELETE' });
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: 'دسته‌بندی حذف شد.' };
}

export async function saveModifierGroup(groupId: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  const ids = formData.getAll('modifier_id').map(String);
  const names = formData.getAll('modifier_name').map(String);
  const prices = formData.getAll('modifier_price');
  const defaults = new Set(formData.getAll('modifier_default').map(String));

  const modifiers = [];
  for (let i = 0; i < names.length; i++) {
    if (!names[i]?.trim()) continue;
    const delta = parseTomanInput(prices[i] ?? null) ?? 0;
    if (Number.isNaN(delta)) return { ok: false, message: INVALID_MONEY };
    modifiers.push({ id: ids[i] || null, name: names[i].trim(), price_delta: delta, is_default: defaults.has(String(i)) });
  }

  try {
    await api(groupId ? `/catalog/modifier-groups/${groupId}` : '/catalog/modifier-groups', {
      method: groupId ? 'PUT' : 'POST',
      body: {
        name: text(formData, 'name'),
        min_select: int(formData, 'min_select') ?? 0,
        max_select: int(formData, 'max_select') ?? 0,
        modifiers,
      },
    });
  } catch (error) {
    return toFormState(error);
  }

  refresh();

  return { ok: true, message: 'گروه افزودنی ذخیره شد.' };
}

export interface BulkState extends FormState {
  result?: BulkPriceResult;
}

export async function bulkPrices(_prev: BulkState, formData: FormData): Promise<BulkState> {
  const operation = String(formData.get('operation') ?? '');
  const raw = text(formData, 'value');
  const isPercent = operation.startsWith('percent_');
  const preview = formData.get('intent') !== 'apply';

  let value: number;
  if (isPercent) {
    const percent = raw === null ? Number.NaN : Number(toLatinDigits(raw).replace('٫', '.'));
    if (!Number.isFinite(percent) || percent <= 0) return { ok: false, errors: { value: 'درصد را درست وارد کنید (مثلاً ۱۰ یا ۱۲٫۵).' } };
    value = Math.round(percent * 100); // basis points
  } else {
    const rials = parseTomanInput(raw);
    if (rials === null || Number.isNaN(rials)) return { ok: false, errors: { value: INVALID_MONEY } };
    value = rials;
  }

  const scope = String(formData.get('scope') ?? 'all');
  const target = scope === 'all' ? { all: true } : { category_ids: formData.getAll('category_ids').map(String) };

  try {
    const { data, message } = await api<{ data: BulkPriceResult; message: string | null }>('/catalog/prices/bulk', {
      method: 'POST',
      body: {
        target,
        branch_id: text(formData, 'branch_id'),
        operation,
        value,
        round_to: int(formData, 'round_to') ?? 0,
        preview,
      },
    });

    if (!preview) refresh();

    return { ok: true, result: data, message: message ?? undefined };
  } catch (error) {
    return toFormState(error);
  }
}
