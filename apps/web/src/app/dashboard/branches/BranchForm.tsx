'use client';

import { useActionState } from 'react';
import { Button, Card, CardHeader, Checkbox, TextAreaField, TextField } from '@cafe/ui';
import { saveBranch } from '@/app/actions/dashboard';
import { FormStatus } from '@/components/FormStatus';
import type { Branch, FormState } from '@/lib/types';

export function BranchForm({ branch, readOnly = false }: { branch?: Branch; readOnly?: boolean }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveBranch.bind(null, branch?.id ?? null), { ok: false });
  const e = state.errors ?? {};

  return (
    <Card>
      <CardHeader title="مشخصات شعبه" />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <TextField label="نام شعبه" name="name" defaultValue={branch?.name} required error={e.name} placeholder="شعبه ونک" />
          <TextField
            label="شناسه‌ی نشانی"
            name="slug"
            defaultValue={branch?.slug}
            required
            ltr
            error={e.slug}
            hint="فقط حروف انگلیسی کوچک، عدد و خط تیره؛ مثل vanak"
          />
          <TextField label="تلفن" name="phone" defaultValue={branch?.phone ?? ''} inputMode="tel" ltr error={e.phone} placeholder="۰۲۱۸۸۷۷۶۶۵۵" />
          <TextField label="کد پستی" name="postal_code" defaultValue={branch?.postal_code ?? ''} inputMode="numeric" ltr error={e.postal_code} hint="۱۰ رقم، بدون خط تیره" />
          <TextField label="استان" name="province" defaultValue={branch?.province ?? ''} error={e.province} />
          <TextField label="شهر" name="city" defaultValue={branch?.city ?? ''} error={e.city} />
          <div className="sm:col-span-2">
            <TextAreaField label="آدرس" name="address" defaultValue={branch?.address ?? ''} error={e.address} placeholder="خیابان، کوچه، پلاک" />
          </div>
          <TextField label="عرض جغرافیایی" name="latitude" defaultValue={branch?.latitude ?? ''} inputMode="decimal" ltr error={e.latitude} hint="برای محدوده‌ی ارسال؛ انتخاب روی نقشه در فاز سفارش‌گیری اضافه می‌شود." />
          <TextField label="طول جغرافیایی" name="longitude" defaultValue={branch?.longitude ?? ''} inputMode="decimal" ltr error={e.longitude} />
          <Checkbox label="شعبه فعال است" name="is_active" defaultChecked={branch?.is_active ?? true} hint="شعبه‌ی غیرفعال سفارش نمی‌گیرد." className="sm:col-span-2" />
        </fieldset>
        {!readOnly ? (
          <div>
            <Button type="submit" loading={pending}>{branch ? 'ذخیره‌ی تغییرات' : 'ایجاد شعبه'}</Button>
          </div>
        ) : null}
      </form>
    </Card>
  );
}
