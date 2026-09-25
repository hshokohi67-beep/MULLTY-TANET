'use client';

import { useActionState, useEffect, useMemo, useState } from 'react';
import { Banknote, Check, CreditCard, Landmark, Pencil, Plus, ReceiptText, Trash2, X } from 'lucide-react';
import { Badge, Button, Card, cx, Dialog, EmptyState, SelectField, TextField } from '@cafe/ui';
import { formatJalaliLong, formatMoney } from '@cafe/locale';
import { deleteExpense, deleteExpenseCategory, saveExpense, saveExpenseCategory } from '@/app/actions/operations';
import { FormStatus } from '@/components/FormStatus';
import { JalaliDateField } from '@/components/JalaliDateField';
import { MoneyField } from '@/components/MoneyField';
import { EXPENSE_METHODS, type Expense, type ExpenseCategory, type ExpenseMethod } from '@/lib/operations-types';
import type { FormState } from '@/lib/types';

type Option = { id: string; name: string };

const METHOD_ICON: Record<ExpenseMethod, typeof Banknote> = { cash: Banknote, card: CreditCard, transfer: Landmark };
/** Category dot colours: four token hues, cycling by the category's slot. */
const DOT = ['bg-brand', 'bg-info', 'bg-accent', 'bg-warning'];

export function ExpenseList({ expenses, categories, branches, defaultDate, monthLabel }: {
  expenses: Expense[]; categories: ExpenseCategory[]; branches: Option[]; defaultDate: string; monthLabel: string;
}) {
  const [editing, setEditing] = useState<Expense | 'new' | null>(null);
  const [filter, setFilter] = useState('');
  const branchName = (id: string) => (branches.length > 1 ? branches.find((b) => b.id === id)?.name : undefined);

  const days = useMemo(() => {
    const list = filter ? expenses.filter((e) => e.category.id === filter) : expenses;
    const map = new Map<string, Expense[]>();
    for (const e of list) map.set(e.spent_on, [...(map.get(e.spent_on) ?? []), e]);

    return [...map.entries()];
  }, [expenses, filter]);
  const usedCategories = useMemo(() => {
    const seen = new Map<string, Expense['category']>();
    for (const e of expenses) seen.set(e.category.id, e.category);

    return [...seen.values()];
  }, [expenses]);

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        {usedCategories.length > 1 ? (
          <div className="flex flex-wrap gap-1.5" role="group" aria-label="فیلتر دسته">
            {[{ id: '', name: 'همه' }, ...usedCategories].map((c) => (
              <button key={c.id} type="button" onClick={() => setFilter(c.id)} aria-pressed={filter === c.id}
                className={cx('rounded-full border px-3 py-1 text-sm transition-colors', filter === c.id ? 'border-brand bg-brand-soft font-semibold text-brand-strong' : 'border-border bg-surface text-text-muted hover:text-text')}>
                {c.name}
              </button>
            ))}
          </div>
        ) : null}
        <Button icon={<Plus />} onClick={() => setEditing('new')} className="ms-auto">ثبت هزینه</Button>
      </div>

      <Card>
        {days.length === 0 ? (
          <EmptyState icon={<ReceiptText />} title={`در ${monthLabel} هزینه‌ای ثبت نشده`}
            description="اجاره، قبض برق، تعمیر دستگاه… را اینجا ثبت کنید تا سود واقعی در پیشخوان دیده شود."
            action={<Button icon={<Plus />} onClick={() => setEditing('new')}>اولین هزینه</Button>} />
        ) : (
          <div className="divide-y divide-border">
            {days.map(([day, rows]) => (
              <section key={day} aria-label={formatJalaliLong(`${day}T12:00:00Z`, 'UTC', true)}>
                <header className="flex items-center justify-between bg-surface-muted/50 px-4 py-2 text-xs">
                  <span className="font-semibold text-text-muted">{formatJalaliLong(`${day}T12:00:00Z`, 'UTC', true)}</span>
                  <span className="tabular text-text-subtle">{formatMoney(rows.reduce((s, e) => s + e.amount, 0))}</span>
                </header>
                <ul className="divide-y divide-border">
                  {rows.map((e) => {
                    const Icon = METHOD_ICON[e.method] ?? Banknote;

                    return (
                      <li key={e.id}>
                        <button type="button" onClick={() => setEditing(e)} className="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-surface-muted/60">
                          <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface-muted text-text-muted" title={e.method_label}><Icon className="size-4" aria-hidden="true" /></span>
                          <span className="min-w-0 flex-1">
                            <span className="flex flex-wrap items-center gap-2 font-medium">
                              <span className={cx('size-2 rounded-full', DOT[e.category.color % DOT.length])} aria-hidden="true" />{e.category.name}
                              {branchName(e.branch_id) ? <Badge>{branchName(e.branch_id)}</Badge> : null}
                            </span>
                            <span className="block truncate text-xs text-text-muted">{[e.payee, e.note, e.method_label].filter(Boolean).join(' • ')}</span>
                          </span>
                          <span className="tabular font-semibold">{formatMoney(e.amount)}</span>
                          <Pencil className="size-3.5 text-text-subtle" aria-hidden="true" />
                        </button>
                      </li>
                    );
                  })}
                </ul>
              </section>
            ))}
          </div>
        )}
      </Card>

      <Dialog open={editing !== null} onClose={() => setEditing(null)} variant="drawer" title={editing === 'new' ? 'ثبت هزینه' : 'ویرایش هزینه'}>
        {editing !== null ? (
          <ExpenseForm key={editing === 'new' ? 'new' : editing.id} expense={editing === 'new' ? null : editing} categories={categories} branches={branches}
            defaultDate={defaultDate} onDone={() => setEditing(null)} />
        ) : null}
      </Dialog>
    </div>
  );
}

function ExpenseForm({ expense, categories, branches, defaultDate, onDone }: { expense: Expense | null; categories: ExpenseCategory[]; branches: Option[]; defaultDate: string; onDone: () => void }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveExpense.bind(null, expense?.id ?? null), { ok: false });
  const [method, setMethod] = useState<ExpenseMethod>(expense?.method ?? 'cash');
  const [error, setError] = useState<string | null>(null);
  const e = state.errors ?? {};
  // A category that was deactivated after use stays selectable on its own expenses.
  const options = expense && !categories.some((c) => c.id === expense.category.id) ? [...categories, { ...expense.category, is_active: false, in_use: true }] : categories;

  useEffect(() => { if (state.ok) onDone(); }, [state.ok, onDone]);

  return (
    <form action={action} className="flex flex-col gap-4">
      <FormStatus state={state} />
      <MoneyField label="مبلغ (تومان)" name="amount" required defaultValue={expense ? String(expense.amount / 10) : ''} error={e.amount} />
      <SelectField label="دسته" name="category_id" required defaultValue={expense?.category.id ?? options[0]?.id} error={e.category_id}>
        {options.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
      </SelectField>
      <JalaliDateField label="تاریخ" name="spent_on" defaultValue={expense?.spent_on ?? defaultDate} />
      {e.spent_on ? <p className="-mt-2 text-sm text-danger">{e.spent_on}</p> : null}
      <fieldset>
        <legend className="mb-1.5 text-sm font-medium">روش پرداخت</legend>
        <input type="hidden" name="method" value={method} />
        <div className="grid grid-cols-3 gap-2">
          {(Object.keys(EXPENSE_METHODS) as ExpenseMethod[]).map((m) => {
            const Icon = METHOD_ICON[m];

            return (
              <button key={m} type="button" onClick={() => setMethod(m)} aria-pressed={method === m}
                className={cx('flex flex-col items-center gap-1 rounded-xl border-2 px-2 py-2.5 text-sm transition-colors', method === m ? 'border-brand bg-brand-soft/60 font-semibold' : 'border-transparent bg-surface-muted text-text-muted')}>
                <Icon className="size-4" aria-hidden="true" />{EXPENSE_METHODS[m]}
              </button>
            );
          })}
        </div>
      </fieldset>
      {branches.length > 1 ? (
        <SelectField label="شعبه" name="branch_id" defaultValue={expense?.branch_id ?? branches[0]?.id}>{branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</SelectField>
      ) : <input type="hidden" name="branch_id" value={expense?.branch_id ?? branches[0]?.id ?? ''} />}
      <TextField label="دریافت‌کننده (اختیاری)" name="payee" maxLength={120} defaultValue={expense?.payee ?? ''} placeholder="مثلاً صاحب‌خانه، اداره‌ی برق" error={e.payee} />
      <TextField label="توضیح (اختیاری)" name="note" maxLength={300} defaultValue={expense?.note ?? ''} error={e.note} />
      <div className="flex items-center gap-2 border-t border-border pt-4">
        <Button type="submit" loading={pending}>{expense ? 'ذخیره' : 'ثبت'}</Button>
        <Button variant="ghost" onClick={onDone}>انصراف</Button>
        {expense ? (
          <button type="button" className="ms-auto inline-flex items-center gap-1 text-sm text-danger hover:underline"
            onClick={async () => { const r = await deleteExpense(expense.id); if (r.ok) onDone(); else setError(r.message ?? 'حذف نشد.'); }}>
            <Trash2 className="size-4" aria-hidden="true" />حذف
          </button>
        ) : null}
      </div>
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
    </form>
  );
}

/** Add, rename, hide or delete (only when unused) expense categories, inline. */
export function CategoryManager({ categories }: { categories: ExpenseCategory[] }) {
  const [editing, setEditing] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [adding, setAdding] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const act = async (fn: () => Promise<FormState>) => {
    setBusy(true);
    const r = await fn();
    setBusy(false);
    setError(r.ok ? null : (r.errors?.name ?? r.message ?? 'انجام نشد.'));

    return r.ok;
  };

  return (
    <div className="flex flex-col gap-3 p-4">
      <ul className="flex flex-col gap-1">
        {categories.map((c) => (
          <li key={c.id} className={cx('group flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-surface-muted/60', !c.is_active && 'opacity-55')}>
            <span className={cx('size-2 shrink-0 rounded-full', DOT[c.color % DOT.length])} aria-hidden="true" />
            {editing === c.id ? (
              <form className="flex flex-1 items-center gap-1" onSubmit={async (ev) => { ev.preventDefault(); if (await act(() => saveExpenseCategory(c.id, name, c.is_active))) setEditing(null); }}>
                <input value={name} onChange={(ev) => setName(ev.target.value)} autoFocus maxLength={80} aria-label="نام دسته"
                  className="h-8 min-w-0 flex-1 rounded-md border border-border-strong bg-surface px-2 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
                <button type="submit" disabled={busy} aria-label="ذخیره" className="flex size-8 items-center justify-center rounded-md text-success hover:bg-success-soft"><Check className="size-4" /></button>
                <button type="button" onClick={() => setEditing(null)} aria-label="انصراف" className="flex size-8 items-center justify-center rounded-md text-text-muted hover:bg-surface-muted"><X className="size-4" /></button>
              </form>
            ) : (
              <>
                <span className="flex-1 truncate text-sm">{c.name}</span>
                {!c.is_active ? <Badge>پنهان</Badge> : null}
                <span className="flex opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 max-sm:opacity-100">
                  <button type="button" onClick={() => { setEditing(c.id); setName(c.name); }} aria-label={`تغییر نام ${c.name}`} className="flex size-7 items-center justify-center rounded-md text-text-muted hover:bg-surface hover:text-text"><Pencil className="size-3.5" /></button>
                  {c.in_use ? (
                    <button type="button" disabled={busy} onClick={() => act(() => saveExpenseCategory(c.id, c.name, !c.is_active))} className="rounded-md px-1.5 text-xs text-text-muted hover:bg-surface hover:text-text">
                      {c.is_active ? 'پنهان' : 'نمایش'}
                    </button>
                  ) : (
                    <button type="button" disabled={busy} onClick={() => act(() => deleteExpenseCategory(c.id))} aria-label={`حذف ${c.name}`} className="flex size-7 items-center justify-center rounded-md text-text-muted hover:bg-danger-soft hover:text-danger"><Trash2 className="size-3.5" /></button>
                  )}
                </span>
              </>
            )}
          </li>
        ))}
      </ul>
      <form className="flex gap-2" onSubmit={async (ev) => { ev.preventDefault(); if (adding.trim() && await act(() => saveExpenseCategory(null, adding))) setAdding(''); }}>
        <input value={adding} onChange={(ev) => setAdding(ev.target.value)} placeholder="دسته‌ی جدید…" maxLength={80} aria-label="دسته‌ی جدید"
          className="h-9 min-w-0 flex-1 rounded-lg border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        <Button type="submit" size="sm" variant="secondary" icon={<Plus />} loading={busy} disabled={!adding.trim()}>افزودن</Button>
      </form>
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
      <p className="text-[11px] text-text-subtle">دسته‌ای که هزینه دارد حذف نمی‌شود؛ می‌توانید پنهانش کنید.</p>
    </div>
  );
}
