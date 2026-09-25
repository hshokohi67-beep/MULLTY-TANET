'use client';

import { useActionState, useEffect, useState } from 'react';
import { Pencil, Plus } from 'lucide-react';
import { Button, Checkbox, TextField } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { saveSupplier } from '@/app/actions/inventory';
import { FormStatus } from '@/components/FormStatus';
import type { Supplier } from '@/lib/inventory-types';
import type { FormState } from '@/lib/types';

export function SupplierList({ suppliers }: { suppliers: Supplier[] }) {
  const [editing, setEditing] = useState<Supplier | 'new' | null>(suppliers.length === 0 ? 'new' : null);

  return (
    <div className="flex flex-col gap-2 p-4 pt-0">
      {suppliers.map((s) => editing !== 'new' && editing?.id === s.id ? (
        <SupplierForm key={s.id} supplier={s} onDone={() => setEditing(null)} />
      ) : (
        <div key={s.id} className="flex items-center gap-2 rounded-xl bg-surface-muted/60 px-3 py-2.5">
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium">{s.name}{!s.is_active ? ' (غیرفعال)' : ''}</p>
            <p className="text-xs text-text-muted">{formatNumber(s.orders)} سفارش{s.owed > 0 ? <> • <span className="text-warning">بدهی {formatMoney(s.owed)}</span></> : null}</p>
          </div>
          <button type="button" onClick={() => setEditing(s)} aria-label={`ویرایش ${s.name}`} className="flex size-8 items-center justify-center rounded-lg text-text-muted hover:bg-surface"><Pencil className="size-4" /></button>
        </div>
      ))}
      {editing === 'new' ? <SupplierForm supplier={null} onDone={() => setEditing(null)} /> : (
        <Button variant="ghost" icon={<Plus />} onClick={() => setEditing('new')}>تأمین‌کننده‌ی جدید</Button>
      )}
    </div>
  );
}

function SupplierForm({ supplier, onDone }: { supplier: Supplier | null; onDone: () => void }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveSupplier.bind(null, supplier?.id ?? null), { ok: false });
  useEffect(() => { if (state.ok) onDone(); }, [state.ok, onDone]);

  return (
    <form action={action} className="flex flex-col gap-3 rounded-xl border border-border p-3">
      <FormStatus state={state} />
      <TextField label="نام" name="name" required maxLength={120} defaultValue={supplier?.name ?? ''} error={state.errors?.name} />
      <TextField label="تلفن" name="phone" inputMode="tel" ltr defaultValue={supplier?.phone ?? ''} />
      <TextField label="یادداشت" name="notes" maxLength={500} defaultValue={supplier?.notes ?? ''} placeholder="شماره کارت، روز تحویل…" />
      {supplier ? <Checkbox name="is_active" defaultChecked={supplier.is_active} label="فعال" /> : null}
      <div className="flex gap-2">
        <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
        <Button size="sm" variant="ghost" onClick={onDone}>انصراف</Button>
      </div>
    </form>
  );
}
