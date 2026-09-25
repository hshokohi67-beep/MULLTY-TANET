'use client';

import { useActionState, useEffect, useState } from 'react';
import { Link2, Pencil, Phone, Plus, Users } from 'lucide-react';
import { Badge, Button, Checkbox, cx, Dialog, EmptyState, SelectField, TextField } from '@cafe/ui';
import { formatMoney, formatPhone } from '@cafe/locale';
import { saveEmployee } from '@/app/actions/operations';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import type { Employee } from '@/lib/operations-types';
import type { FormState } from '@/lib/types';

type Option = { id: string; name: string };
type Member = { id: string; name: string; role: string | null };

function initials(name: string): string {
  return name.trim().split(/\s+/).slice(0, 2).map((p) => p[0]).join('');
}

/** The people on the payroll (not the same as panel users: a dishwasher may never log in). */
export function EmployeeList({ employees, branches, members }: { employees: Employee[]; branches: Option[]; members: Member[] }) {
  const [editing, setEditing] = useState<Employee | 'new' | null>(null);
  const memberName = (id: string | null) => members.find((m) => m.id === id)?.name;

  return (
    <div className="flex flex-col gap-3">
      <div className="flex justify-end">
        <Button icon={<Plus />} onClick={() => setEditing('new')}>کارمند جدید</Button>
      </div>

      {employees.length === 0 ? (
        <div className="rounded-2xl border border-border bg-surface">
          <EmptyState icon={<Users />} title="هنوز کارمندی ثبت نشده"
            description="هر کسی که حقوق یا دستمزد می‌گیرد را با نرخ ساعتی یا حقوق ماهانه اضافه کنید؛ اگر حساب پنل دارد به آن وصلش کنید تا خودش ورود و خروج بزند."
            action={<Button icon={<Plus />} onClick={() => setEditing('new')}>اولین کارمند</Button>} />
        </div>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {employees.map((e) => (
            <li key={e.id}>
              <button type="button" onClick={() => setEditing(e)}
                className={cx('group flex w-full items-start gap-3 rounded-2xl border border-border bg-surface p-4 text-start shadow-[var(--shadow-sm)] transition-[border-color,box-shadow] hover:border-border-strong hover:shadow-[var(--shadow-md)]', !e.is_active && 'opacity-60')}>
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-bold text-brand-strong" aria-hidden="true">{initials(e.name)}</span>
                <span className="min-w-0 flex-1">
                  <span className="flex flex-wrap items-center gap-1.5 font-semibold">{e.name}{!e.is_active ? <Badge>غیرفعال</Badge> : null}</span>
                  <span className="block text-xs text-text-muted">{e.position ?? 'بدون سمت'}{branches.length > 1 ? ` • ${branches.find((b) => b.id === e.branch_id)?.name ?? ''}` : ''}</span>
                  <span className="tabular mt-2 block text-sm">{formatMoney(e.rate)} <span className="text-xs text-text-muted">{e.pay_type === 'hourly' ? 'در ساعت' : 'در ماه'}</span></span>
                  <span className="mt-2 flex flex-wrap gap-1.5">
                    {e.user_id ? <Badge tone="brand"><Link2 className="size-3" aria-hidden="true" />{memberName(e.user_id) ?? 'حساب پنل'}</Badge> : <Badge>بدون حساب پنل</Badge>}
                    {e.phone ? <Badge><Phone className="size-3" aria-hidden="true" /><span dir="ltr">{formatPhone(e.phone)}</span></Badge> : null}
                  </span>
                </span>
                <Pencil className="size-4 text-text-subtle opacity-0 transition-opacity group-hover:opacity-100" aria-hidden="true" />
              </button>
            </li>
          ))}
        </ul>
      )}

      <Dialog open={editing !== null} onClose={() => setEditing(null)} variant="drawer" title={editing === 'new' ? 'کارمند جدید' : 'ویرایش کارمند'}>
        {editing !== null ? (
          <EmployeeForm key={editing === 'new' ? 'new' : editing.id} employee={editing === 'new' ? null : editing} branches={branches}
            members={members.filter((m) => !employees.some((e) => e.user_id === m.id && (editing === 'new' || e.id !== editing.id)))} onDone={() => setEditing(null)} />
        ) : null}
      </Dialog>
    </div>
  );
}

function EmployeeForm({ employee, branches, members, onDone }: { employee: Employee | null; branches: Option[]; members: Member[]; onDone: () => void }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveEmployee.bind(null, employee?.id ?? null), { ok: false });
  const [payType, setPayType] = useState<Employee['pay_type']>(employee?.pay_type ?? 'hourly');
  const e = state.errors ?? {};

  useEffect(() => { if (state.ok) onDone(); }, [state.ok, onDone]);

  return (
    <form action={action} className="flex flex-col gap-4">
      <FormStatus state={state} />
      <TextField label="نام" name="name" required maxLength={120} defaultValue={employee?.name ?? ''} error={e.name} />
      <div className="grid grid-cols-2 gap-3">
        <TextField label="سمت (اختیاری)" name="position" maxLength={60} defaultValue={employee?.position ?? ''} placeholder="باریستا، صندوق…" error={e.position} />
        <TextField label="موبایل (اختیاری)" name="phone" inputMode="tel" ltr defaultValue={employee?.phone ?? ''} error={e.phone} />
      </div>
      {branches.length > 1 ? (
        <SelectField label="شعبه" name="branch_id" defaultValue={employee?.branch_id ?? branches[0]?.id}>{branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</SelectField>
      ) : <input type="hidden" name="branch_id" value={employee?.branch_id ?? branches[0]?.id ?? ''} />}

      <fieldset className="flex flex-col gap-3 rounded-xl bg-surface-muted p-3">
        <legend className="sr-only">دستمزد</legend>
        <input type="hidden" name="pay_type" value={payType} />
        <div className="grid grid-cols-2 gap-1 rounded-lg bg-surface p-1">
          {([['hourly', 'ساعتی'], ['monthly', 'حقوق ماهانه']] as const).map(([v, t]) => (
            <button key={v} type="button" onClick={() => setPayType(v)} aria-pressed={payType === v}
              className={cx('rounded-md py-1.5 text-sm transition-colors', payType === v ? 'bg-brand text-on-brand font-semibold' : 'text-text-muted hover:text-text')}>{t}</button>
          ))}
        </div>
        <MoneyField label={payType === 'hourly' ? 'دستمزد هر ساعت (تومان)' : 'حقوق ماهانه (تومان)'} name="rate" required
          defaultValue={employee ? String(employee.rate / 10) : ''} error={e.rate} />
        {payType === 'monthly' ? <p className="text-[11px] text-text-muted">برای محاسبه‌ی هزینه‌ی روزانه، حقوق بر ساعت استاندارد ماه تقسیم می‌شود.</p> : null}
      </fieldset>

      <SelectField label="حساب پنل (برای ورود و خروج خودکار)" name="user_id" defaultValue={employee?.user_id ?? ''} error={e.user_id}
        hint={members.length ? 'اگر وصل شود، خودش از نوار بالای پنل ورود و خروج می‌زند.' : 'عضوی از «تیم و دسترسی‌ها» برای اتصال پیدا نشد.'}>
        <option value="">— وصل نیست —</option>
        {members.map((m) => <option key={m.id} value={m.id}>{m.name}{m.role ? ` (${m.role})` : ''}</option>)}
      </SelectField>
      {employee ? <Checkbox name="is_active" defaultChecked={employee.is_active} label="فعال" hint="کارمند غیرفعال در برنامه‌ی شیفت و ورود و خروج نمی‌آید؛ سوابقش می‌ماند." /> : null}
      <div className="flex gap-2 border-t border-border pt-4">
        <Button type="submit" loading={pending}>{employee ? 'ذخیره' : 'افزودن'}</Button>
        <Button variant="ghost" onClick={onDone}>انصراف</Button>
      </div>
    </form>
  );
}
