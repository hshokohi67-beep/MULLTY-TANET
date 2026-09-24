'use client';

import { useActionState, useEffect, useState, useTransition } from 'react';
import { CircleCheck, CircleAlert } from 'lucide-react';
import { Button, Checkbox, TextAreaField, TextField } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { checkDelivery, saveAddress, type ZoneCheck } from '@/app/actions/storefront';
import { FormStatus } from '@/components/FormStatus';
import type { CustomerAddress } from '@/lib/storefront-types';
import type { FormState } from '@/lib/types';
import { MapPicker, type Point } from './MapPicker';
import { useStore } from './StoreProvider';

/** Iranian address form with a map pin and a live "do you deliver here?" check. */
export function AddressForm({ address, next }: { address: CustomerAddress | null; next: string }) {
  const { tenant, store } = useStore();
  const [state, action, pending] = useActionState<FormState, FormData>(saveAddress.bind(null, tenant, address?.id ?? null), { ok: false });
  const [point, setPoint] = useState<Point | null>(address?.latitude != null && address.longitude != null ? { lat: address.latitude, lng: address.longitude } : null);
  const [zone, setZone] = useState<{ ok: true; data: ZoneCheck } | { ok: false; message: string } | null>(null);
  const [checking, startCheck] = useTransition();

  const deliveryBranch = store.branches.find((b) => b.delivery) ?? store.branches[0];
  const center = deliveryBranch?.latitude != null && deliveryBranch.longitude != null ? { lat: deliveryBranch.latitude, lng: deliveryBranch.longitude } : { lat: 35.6997, lng: 51.338 };

  useEffect(() => {
    if (state.ok) window.location.assign(next);
  }, [state.ok, next]);

  const pick = (p: Point) => {
    setPoint(p);
    if (!deliveryBranch?.delivery) return;
    startCheck(async () => setZone(await checkDelivery(tenant, deliveryBranch.id, p.lat, p.lng)));
  };

  return (
    <form action={action} className="flex flex-col gap-4">
      <FormStatus state={state} />

      <div>
        <p className="mb-2 text-sm font-medium">محل تحویل روی نقشه</p>
        <MapPicker value={point} center={center} onChange={pick} />
        <input type="hidden" name="latitude" value={point?.lat.toFixed(7) ?? ''} />
        <input type="hidden" name="longitude" value={point?.lng.toFixed(7) ?? ''} />
        <div aria-live="polite" className="mt-2 min-h-6 text-sm">
          {checking ? <span className="text-text-muted">در حال بررسی محدوده‌ی ارسال…</span>
            : zone?.ok ? (
              <span className="inline-flex items-center gap-1.5 text-success">
                <CircleCheck className="size-4" aria-hidden="true" />
                ارسال داریم • {zone.data.fee ? `هزینه‌ی ارسال ${formatMoney(zone.data.fee)}` : 'ارسال رایگان'}{zone.data.eta_minutes ? ` • حدود ${formatNumber(zone.data.eta_minutes)} دقیقه` : ''}{zone.data.min_order ? ` • حداقل سفارش ${formatMoney(zone.data.min_order)}` : ''}
              </span>
            ) : zone && !zone.ok ? (
              <span className="inline-flex items-center gap-1.5 text-warning"><CircleAlert className="size-4" aria-hidden="true" />{zone.message}</span>
            ) : !point ? <span className="text-text-muted">برای محاسبه‌ی هزینه‌ی ارسال، محل را روی نقشه مشخص کنید.</span> : null}
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        <TextField label="عنوان" name="title" required maxLength={40} defaultValue={address?.title ?? 'خانه'} error={state.errors?.title} />
        <TextField label="شهر" name="city" required maxLength={60} defaultValue={address?.city ?? deliveryBranch?.city ?? ''} error={state.errors?.city} />
      </div>
      <TextAreaField label="آدرس" name="address" required maxLength={500} rows={2} defaultValue={address?.address ?? ''} error={state.errors?.address} placeholder="خیابان، کوچه…" />
      <div className="grid grid-cols-3 gap-3">
        <TextField label="پلاک" name="building_number" maxLength={20} inputMode="numeric" defaultValue={address?.building_number ?? ''} error={state.errors?.building_number} />
        <TextField label="طبقه" name="floor" maxLength={10} inputMode="numeric" defaultValue={address?.floor ?? ''} error={state.errors?.floor} />
        <TextField label="واحد" name="unit" maxLength={10} inputMode="numeric" defaultValue={address?.unit ?? ''} error={state.errors?.unit} />
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField label="نام گیرنده" name="recipient_name" maxLength={120} defaultValue={address?.recipient_name ?? ''} hint="اگر خودتان نیستید" error={state.errors?.recipient_name} />
        <TextField label="موبایل گیرنده" name="recipient_phone" type="tel" ltr defaultValue={address?.recipient_phone ?? ''} error={state.errors?.recipient_phone} />
      </div>
      <TextField label="کد پستی" name="postal_code" inputMode="numeric" ltr maxLength={10} defaultValue={address?.postal_code ?? ''} error={state.errors?.postal_code} />
      <TextField label="توضیح برای پیک" name="notes" maxLength={300} defaultValue={address?.notes ?? ''} placeholder="مثلاً: زنگ دوم" error={state.errors?.notes} />
      <Checkbox name="is_default" defaultChecked={address?.is_default ?? true} label="آدرس پیش‌فرض من" />
      <Button type="submit" size="lg" loading={pending}>ذخیره‌ی آدرس</Button>
    </form>
  );
}
