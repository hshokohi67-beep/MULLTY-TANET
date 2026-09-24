'use client';

import { useActionState, useTransition } from 'react';
import { Button, Checkbox, TextField } from '@cafe/ui';
import { toPersianDigits } from '@cafe/locale';
import { deleteDeliveryZone, saveDeliveryZone } from '@/app/actions/commerce';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { rialToTomanInput } from '@/lib/money';
import type { DeliveryZone, FormState } from '@/lib/types';

export function ZoneForm({ branchId, zone }: { branchId: string; zone?: DeliveryZone }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveDeliveryZone.bind(null, zone?.id ?? null), { ok: false });
  const [deleting, startDelete] = useTransition();
  const e = state.errors ?? {};

  return (
    <form action={action} key={zone ? zone.id : state.ok ? state.message : 'new'} className="flex flex-col gap-3 rounded-md border border-border p-4">
      <p className="text-sm font-semibold">{zone ? zone.name : 'محدوده‌ی جدید'}</p>
      <FormStatus state={state} />
      <input type="hidden" name="branch_id" value={branchId} />
      <div className="grid gap-3 sm:grid-cols-3">
        <TextField label="نام" name="name" defaultValue={zone?.name} required placeholder="مثلاً تا ۳ کیلومتر" error={e.name} />
        <TextField label="شعاع (کیلومتر)" name="radius_km" defaultValue={zone ? toPersianDigits(String(zone.radius_m / 1000)) : ''} inputMode="decimal" ltr required error={e.radius_m} />
        <TextField label="زمان تقریبی (دقیقه)" name="eta_minutes" defaultValue={zone?.eta_minutes != null ? String(zone.eta_minutes) : ''} inputMode="numeric" ltr error={e.eta_minutes} />
        <MoneyField label="هزینه‌ی ارسال (تومان)" name="delivery_fee" defaultValue={rialToTomanInput(zone?.delivery_fee ?? 0)} error={e.delivery_fee} />
        <MoneyField label="ارسال رایگان از (تومان)" name="free_delivery_min" defaultValue={rialToTomanInput(zone?.free_delivery_min)} hint="خالی = همیشه با هزینه" error={e.free_delivery_min} />
        <MoneyField label="حداقل سفارش (تومان)" name="min_order" defaultValue={rialToTomanInput(zone?.min_order ?? 0)} error={e.min_order} />
      </div>
      {zone ? <Checkbox label="فعال" name="is_active" defaultChecked={zone.is_active} /> : null}
      <div className="flex gap-2">
        <Button type="submit" size="sm" loading={pending}>{zone ? 'ذخیره' : 'افزودن محدوده'}</Button>
        {zone ? (
          <Button size="sm" variant="ghost" loading={deleting} onClick={() => startDelete(() => deleteDeliveryZone(zone.id))}>حذف</Button>
        ) : null}
      </div>
    </form>
  );
}
