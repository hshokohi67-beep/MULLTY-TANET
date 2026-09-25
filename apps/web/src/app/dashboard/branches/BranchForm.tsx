'use client';

import { useActionState, useState } from 'react';
import { MapPin, X } from 'lucide-react';
import { Button, Card, CardHeader, Checkbox, TextAreaField, TextField } from '@cafe/ui';
import { toPersianDigits } from '@cafe/locale';
import { saveBranch } from '@/app/actions/dashboard';
import { FormStatus } from '@/components/FormStatus';
import { MapPicker, type Point } from '@/components/store/MapPicker';
import type { Branch, FormState } from '@/lib/types';

/** Default map centre when a branch has no location yet (Tehran). */
const TEHRAN: Point = { lat: 35.6997, lng: 51.338 };

export function BranchForm({ branch, readOnly = false }: { branch?: Branch; readOnly?: boolean }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveBranch.bind(null, branch?.id ?? null), { ok: false });
  const [point, setPoint] = useState<Point | null>(
    branch?.latitude != null && branch?.longitude != null ? { lat: Number(branch.latitude), lng: Number(branch.longitude) } : null,
  );
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
          <TextField label="استان" name="province" defaultValue={branch?.province ?? ''} error={e.province} placeholder="تهران" />
          <TextField label="شهر" name="city" defaultValue={branch?.city ?? ''} error={e.city} placeholder="تهران" />
          <TextField label="محله / منطقه" name="district" defaultValue={branch?.district ?? ''} error={e.district} placeholder="مثلاً ونک یا منطقه‌ی ۳"
            hint="در «خوراک‌گردی» مشتری‌ها با انتخاب محله پیدایتان می‌کنند." />
          <div className="sm:col-span-2">
            <TextAreaField label="آدرس" name="address" defaultValue={branch?.address ?? ''} error={e.address} placeholder="خیابان، کوچه، پلاک" />
          </div>

          <div className="flex flex-col gap-2 sm:col-span-2">
            <div className="flex items-center justify-between gap-2">
              <p className="text-sm font-medium">محل شعبه روی نقشه</p>
              {point ? (
                <span className="flex items-center gap-2 text-xs text-text-muted" dir="ltr">
                  {toPersianDigits(point.lat.toFixed(5))}، {toPersianDigits(point.lng.toFixed(5))}
                  {!readOnly ? <button type="button" onClick={() => setPoint(null)} aria-label="پاک کردن محل" className="rounded-full p-1 hover:bg-surface-muted"><X className="size-3.5" /></button> : null}
                </span>
              ) : null}
            </div>
            {readOnly ? (
              point ? null : <p className="text-sm text-text-muted">محل شعبه ثبت نشده است.</p>
            ) : (
              <MapPicker value={point} center={point ?? TEHRAN} onChange={setPoint} pinTitle="محل شعبه" height="h-72"
                label="نقشه: برای مشخص کردن محل شعبه روی نقشه بزنید یا نشانگر را بکشید" />
            )}
            <p className="flex items-start gap-1.5 text-xs text-text-muted">
              <MapPin className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
              برای محدوده‌ی ارسال، مسیریابی مشتری و «نزدیک من» در خوراک‌گردی لازم است.
            </p>
            {e.latitude || e.longitude ? <p className="text-sm text-danger">{e.latitude ?? e.longitude}</p> : null}
            <input type="hidden" name="latitude" value={point ? point.lat.toFixed(7) : ''} />
            <input type="hidden" name="longitude" value={point ? point.lng.toFixed(7) : ''} />
          </div>

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
