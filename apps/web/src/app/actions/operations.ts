'use server';

import { revalidatePath } from 'next/cache';
import { api, ApiError, toFormState } from '@/lib/api';
import { parseTomanInput } from '@/lib/money';
import type { TimeClock } from '@/lib/operations-types';
import type { FormState } from '@/lib/types';

/** Expenses, staff, shifts, attendance and the time clock. The API authorises everything. */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;
const DATE = /^\d{4}-\d{2}-\d{2}$/;

function text(formData: FormData, key: string): string | null {
  const v = formData.get(key);
  if (typeof v !== 'string') return null;
  const t = v.trim();

  return t === '' ? null : t;
}

function money(formData: FormData, key: string): number | null {
  const v = text(formData, key);

  return v === null ? null : parseTomanInput(v);
}

async function run(path: string, method: 'POST' | 'PUT' | 'DELETE', body?: Record<string, unknown>): Promise<FormState> {
  try {
    await api(path, { method, body });
  } catch (error) {
    return toFormState(error);
  }

  return { ok: true };
}

/* --------------------------------- expenses --------------------------------- */

export async function saveExpense(id: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  if (id && !ULID.test(id)) return { ok: false, message: 'هزینه نامعتبر است.' };
  const r = await run(id ? `/expenses/${id}` : '/expenses', id ? 'PUT' : 'POST', {
    branch_id: text(formData, 'branch_id'),
    category_id: text(formData, 'category_id'),
    amount: money(formData, 'amount'),
    spent_on: text(formData, 'spent_on'),
    method: text(formData, 'method') ?? 'cash',
    payee: text(formData, 'payee'),
    note: text(formData, 'note'),
  });
  if (!r.ok) return r;
  revalidatePath('/dashboard/expenses');

  return { ok: true, message: id ? 'ذخیره شد.' : 'هزینه ثبت شد.' };
}

export async function deleteExpense(id: string): Promise<FormState> {
  if (!ULID.test(id)) return { ok: false, message: 'هزینه نامعتبر است.' };
  const r = await run(`/expenses/${id}`, 'DELETE');
  if (r.ok) revalidatePath('/dashboard/expenses');

  return r;
}

export async function saveExpenseCategory(id: string | null, name: string, isActive = true): Promise<FormState> {
  if (id && !ULID.test(id)) return { ok: false, message: 'دسته نامعتبر است.' };
  const r = await run(id ? `/expense-categories/${id}` : '/expense-categories', id ? 'PUT' : 'POST', { name: name.trim(), is_active: isActive });
  if (r.ok) revalidatePath('/dashboard/expenses');

  return r;
}

export async function deleteExpenseCategory(id: string): Promise<FormState> {
  if (!ULID.test(id)) return { ok: false, message: 'دسته نامعتبر است.' };
  const r = await run(`/expense-categories/${id}`, 'DELETE');
  if (r.ok) revalidatePath('/dashboard/expenses');

  return r;
}

/* ----------------------------------- staff ----------------------------------- */

function refreshStaff() {
  revalidatePath('/dashboard/staff');
}

export async function saveEmployee(id: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  if (id && !ULID.test(id)) return { ok: false, message: 'کارمند نامعتبر است.' };
  const r = await run(id ? `/staff/employees/${id}` : '/staff/employees', id ? 'PUT' : 'POST', {
    name: text(formData, 'name'),
    phone: text(formData, 'phone'),
    position: text(formData, 'position'),
    branch_id: text(formData, 'branch_id'),
    user_id: text(formData, 'user_id'),
    pay_type: text(formData, 'pay_type') ?? 'hourly',
    rate: money(formData, 'rate') ?? 0,
    hired_on: text(formData, 'hired_on'),
    is_active: id ? formData.get('is_active') === 'on' : true,
  });
  if (!r.ok) return r;
  refreshStaff();

  return { ok: true, message: id ? 'ذخیره شد.' : 'کارمند اضافه شد.' };
}

export async function saveShift(id: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  if (id && !ULID.test(id)) return { ok: false, message: 'شیفت نامعتبر است.' };
  const r = await run(id ? `/staff/shifts/${id}` : '/staff/shifts', id ? 'PUT' : 'POST', {
    employee_id: text(formData, 'employee_id'),
    starts_at: text(formData, 'starts_at'),
    ends_at: text(formData, 'ends_at'),
    note: text(formData, 'note'),
  });
  if (!r.ok) return r;
  refreshStaff();

  return { ok: true, message: 'شیفت ذخیره شد.' };
}

export async function deleteShift(id: string): Promise<FormState> {
  if (!ULID.test(id)) return { ok: false, message: 'شیفت نامعتبر است.' };
  const r = await run(`/staff/shifts/${id}`, 'DELETE');
  if (r.ok) refreshStaff();

  return r;
}

export async function copyWeek(from: string, to: string, branchId: string | null): Promise<FormState & { copied?: number; skipped?: number }> {
  if (!DATE.test(from) || !DATE.test(to)) return { ok: false, message: 'تاریخ نامعتبر است.' };
  try {
    const { data } = await api<{ data: { copied: number; skipped: number } }>('/staff/shifts/copy-week', { method: 'POST', body: { from, to, branch_id: branchId } });
    refreshStaff();

    return { ok: true, ...data };
  } catch (error) {
    return toFormState(error);
  }
}

export async function saveAttendance(id: string | null, _prev: FormState, formData: FormData): Promise<FormState> {
  if (id && !ULID.test(id)) return { ok: false, message: 'رکورد نامعتبر است.' };
  const r = await run(id ? `/staff/attendance/${id}` : '/staff/attendance', id ? 'PUT' : 'POST', {
    employee_id: text(formData, 'employee_id'),
    clock_in_at: text(formData, 'clock_in_at'),
    clock_out_at: text(formData, 'clock_out_at'),
    note: text(formData, 'note'),
  });
  if (!r.ok) return r;
  refreshStaff();

  return { ok: true, message: 'ثبت شد.' };
}

export async function deleteAttendance(id: string): Promise<FormState> {
  if (!ULID.test(id)) return { ok: false, message: 'رکورد نامعتبر است.' };
  const r = await run(`/staff/attendance/${id}`, 'DELETE');
  if (r.ok) refreshStaff();

  return r;
}

/* -------------------------------- time clock -------------------------------- */

/** The signed-in user's employee card (null when they aren't linked to one, or lack the permission). */
export async function loadTimeClock(): Promise<TimeClock | null> {
  try {
    return (await api<{ data: TimeClock | null }>('/time-clock/me')).data;
  } catch (error) {
    if (error instanceof ApiError) return null;
    throw error;
  }
}

export async function punch(direction: 'in' | 'out'): Promise<FormState & { clock?: TimeClock | null }> {
  try {
    await api(`/time-clock/${direction}`, { method: 'POST' });
  } catch (error) {
    return toFormState(error);
  }
  refreshStaff();

  return { ok: true, message: direction === 'in' ? 'ورود ثبت شد.' : 'خروج ثبت شد.', clock: await loadTimeClock() };
}
