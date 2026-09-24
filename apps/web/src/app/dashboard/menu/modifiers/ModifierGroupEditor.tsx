'use client';

import { useActionState, useState } from 'react';
import { Badge, Button, Card, CardHeader, TextField } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { saveModifierGroup } from '@/app/actions/catalog';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { rialToTomanInput } from '@/lib/money';
import type { FormState, ModifierGroup } from '@/lib/types';

interface Row {
  key: string;
  id: string;
  name: string;
  price: string;
  isDefault: boolean;
}

function selectionLabel(min: number, max: number): string {
  if (max === 0) return min > 0 ? `دست‌کم ${formatNumber(min)} انتخاب` : 'اختیاری، بدون محدودیت';
  if (min === max) return `دقیقاً ${formatNumber(min)} انتخاب`;

  return `${formatNumber(min)} تا ${formatNumber(max)} انتخاب`;
}

export function ModifierGroupEditor({ group, readOnly = false }: { group?: ModifierGroup; readOnly?: boolean }) {
  const [open, setOpen] = useState(!group);
  const [state, action, pending] = useActionState<FormState, FormData>(saveModifierGroup.bind(null, group?.id ?? null), { ok: false });
  const [rows, setRows] = useState<Row[]>(() =>
    group?.modifiers?.length
      ? group.modifiers.map((m) => ({ key: m.id, id: m.id, name: m.name, price: rialToTomanInput(m.price_delta), isDefault: m.is_default }))
      : [{ key: 'new-0', id: '', name: '', price: '', isDefault: false }],
  );
  const e = state.errors ?? {};

  if (group && !open) {
    return (
      <Card>
        <CardHeader
          title={group.name}
          description={`${selectionLabel(group.min_select, group.max_select)} • ${formatNumber(group.products_count ?? 0)} آیتم`}
          actions={
            <>
              {group.is_required ? <Badge tone="brand">اجباری</Badge> : null}
              {!readOnly ? <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>ویرایش</Button> : null}
            </>
          }
        />
        <ul className="flex flex-wrap gap-2 p-4">
          {group.modifiers?.map((m) => (
            <li key={m.id} className="rounded-md border border-border px-3 py-1 text-sm">
              {m.name}
              <span className="ms-2 text-text-muted">{m.price_delta === 0 ? 'رایگان' : `+${formatMoney(m.price_delta)}`}</span>
              {m.is_default ? <span className="ms-2 text-xs text-brand">پیش‌فرض</span> : null}
            </li>
          ))}
        </ul>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader title={group ? `ویرایش «${group.name}»` : 'گروه جدید'} />
      <form action={action} key={group ? group.id : state.ok ? state.message : 'new'} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <TextField label="نام گروه" name="name" defaultValue={group?.name} required error={e.name} placeholder="مثلاً نوع شیر" />
            <TextField label="حداقل انتخاب" name="min_select" defaultValue={String(group?.min_select ?? 0)} inputMode="numeric" ltr error={e.min_select} hint="۰ یعنی اختیاری" />
            <TextField label="حداکثر انتخاب" name="max_select" defaultValue={String(group?.max_select ?? 0)} inputMode="numeric" ltr error={e.max_select} hint="۰ یعنی بدون محدودیت" />
          </div>

          <fieldset className="flex flex-col gap-3">
            <legend className="mb-1 text-sm font-medium">گزینه‌ها</legend>
            {rows.map((row, i) => (
              <div key={row.key} className="grid items-start gap-3 sm:grid-cols-[2fr_1fr_auto_auto]">
                <input type="hidden" name="modifier_id" value={row.id} />
                <TextField label="نام گزینه" name="modifier_name" defaultValue={row.name} error={e[`modifiers.${i}.name`]} placeholder="مثلاً شیر بادام" />
                <MoneyField label="قیمت اضافه (تومان)" name="modifier_price" defaultValue={row.price} hint="۰ یعنی رایگان" error={e[`modifiers.${i}.price_delta`]} />
                <label className="flex items-center gap-2 text-sm sm:mt-8">
                  <input type="checkbox" name="modifier_default" value={String(i)} defaultChecked={row.isDefault} className="accent-[var(--color-brand)]" />
                  پیش‌فرض
                </label>
                {rows.length > 1 ? (
                  <Button size="sm" variant="ghost" className="sm:mt-7" onClick={() => setRows(rows.filter((r) => r.key !== row.key))}>حذف</Button>
                ) : <span />}
              </div>
            ))}
            {rows.length < 30 ? (
              <div><Button size="sm" variant="secondary" onClick={() => setRows([...rows, { key: crypto.randomUUID(), id: '', name: '', price: '', isDefault: false }])}>افزودن گزینه</Button></div>
            ) : null}
          </fieldset>
        </fieldset>
        {!readOnly ? (
          <div className="flex gap-2">
            <Button type="submit" loading={pending}>ذخیره‌ی گروه</Button>
            {group ? <Button variant="ghost" onClick={() => setOpen(false)}>بستن</Button> : null}
          </div>
        ) : null}
      </form>
    </Card>
  );
}
