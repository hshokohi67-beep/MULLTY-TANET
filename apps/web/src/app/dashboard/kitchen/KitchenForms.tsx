'use client';

import { useActionState, useEffect, useState, useTransition } from 'react';
import QRCode from 'qrcode';
import { Alert, Badge, Button, Card, CardHeader, Checkbox, SelectField, TextField } from '@cafe/ui';
import { formatJalaliDateTime, toPersianDigits } from '@cafe/locale';
import {
  createDevice, deleteStation, repairDevice, revokeDevice, saveKitchenSettings, saveRouting, saveStation, type PairingResult,
} from '@/app/actions/kitchen';
import { FormStatus } from '@/components/FormStatus';
import type { Branch, FormState, KitchenSetup, Product } from '@/lib/types';

type Station = KitchenSetup['stations'][number];
type Device = KitchenSetup['devices'][number];

export function StationEditor({ station, branchId }: { station?: Station; branchId: string }) {
  const [open, setOpen] = useState(false);
  const [state, action, pending] = useActionState<FormState, FormData>(saveStation.bind(null, station?.id ?? null), { ok: false });
  const [deleting, startDelete] = useTransition();
  const [deleteError, setDeleteError] = useState<string | null>(null);

  if (!open) {
    return station ? (
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="font-medium">
            {station.name}
            {station.is_default ? <span className="ms-2"><Badge tone="brand">پیش‌فرض</Badge></span> : null}
            {!station.is_active ? <span className="ms-2"><Badge>غیرفعال</Badge></span> : null}
          </p>
          <p className="text-xs text-text-muted">دیرکرد پس از {toPersianDigits(station.late_after_minutes)} دقیقه • {toPersianDigits(station.product_ids.length)} آیتم اختصاصی</p>
        </div>
        <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>ویرایش</Button>
      </div>
    ) : (
      <Button size="sm" variant="secondary" onClick={() => setOpen(true)}>افزودن ایستگاه</Button>
    );
  }

  return (
    <form action={action} className="flex flex-col gap-3 rounded-md border border-border p-4">
      <FormStatus state={state} />
      {deleteError ? <Alert tone="danger">{deleteError}</Alert> : null}
      <input type="hidden" name="branch_id" value={branchId} />
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField label="نام ایستگاه" name="name" defaultValue={station?.name} required placeholder="مثلاً بار قهوه" error={state.errors?.name} />
        <TextField label="دیرکرد پس از (دقیقه)" name="late_after_minutes" defaultValue={String(station?.late_after_minutes ?? 7)} inputMode="numeric" ltr error={state.errors?.late_after_minutes} />
      </div>
      <div className="flex flex-wrap gap-6">
        <Checkbox label="ایستگاه پیش‌فرض (آیتم‌های بدون مسیر اینجا می‌آیند)" name="is_default" defaultChecked={station?.is_default ?? false} />
        {station ? <Checkbox label="فعال" name="is_active" defaultChecked={station.is_active} /> : null}
      </div>
      <div className="flex gap-2">
        <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
        <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>بستن</Button>
        {station ? (
          <Button size="sm" variant="ghost" loading={deleting} onClick={() => startDelete(async () => {
            const result = await deleteStation(station.id);
            setDeleteError(result.ok ? null : result.message ?? null);
          })}>حذف</Button>
        ) : null}
      </div>
    </form>
  );
}

/** Which products each station prepares. A product can belong to one station per branch. */
export function RoutingEditor({ station, products, takenBy }: { station: Station; products: Product[]; takenBy: Record<string, string> }) {
  const [open, setOpen] = useState(false);
  const [state, action, pending] = useActionState<FormState, FormData>(saveRouting.bind(null, station.id), { ok: false });

  if (!open) {
    return <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>آیتم‌های این ایستگاه</Button>;
  }

  return (
    <form action={action} className="mt-3 flex flex-col gap-3 rounded-md border border-border p-4">
      <FormStatus state={state} />
      <p className="text-sm text-text-muted">
        آیتم‌هایی که انتخاب نشوند به ایستگاه پیش‌فرض می‌روند. انتخاب آیتمی که در ایستگاه دیگری است، آن را به اینجا منتقل می‌کند.
      </p>
      <div className="grid max-h-80 gap-1 overflow-y-auto sm:grid-cols-2">
        {products.map((p) => (
          <label key={p.id} className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-surface-muted has-[:checked]:bg-brand-soft">
            <input type="checkbox" name="product_ids" value={p.id} defaultChecked={station.product_ids.includes(p.id)} className="accent-[var(--color-brand)]" />
            <span className="flex-1">{p.name}</span>
            {takenBy[p.id] && takenBy[p.id] !== station.name ? <span className="text-xs text-text-subtle">{takenBy[p.id]}</span> : null}
          </label>
        ))}
      </div>
      <div className="flex gap-2">
        <Button type="submit" size="sm" loading={pending}>ذخیره</Button>
        <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>بستن</Button>
      </div>
    </form>
  );
}

/** Big code + QR a tablet can scan to open the pairing page. */
function PairingCode({ result, tenant }: { result: PairingResult; tenant: string }) {
  const [origin, setOrigin] = useState('');
  const [qr, setQr] = useState<string | null>(null);
  const url = origin ? `${origin}/kds/pair?t=${encodeURIComponent(tenant)}` : '';

  useEffect(() => {
    const timer = setTimeout(() => setOrigin(window.location.origin), 0);
    return () => clearTimeout(timer);
  }, []);

  useEffect(() => {
    if (!url) return;
    QRCode.toDataURL(url, { width: 320, margin: 1 }).then(setQr, () => setQr(null));
  }, [url]);

  if (!result.code) return null;

  return (
    <div className="flex flex-wrap items-center gap-5 rounded-lg border border-brand bg-brand-soft/40 p-4">
      {/* eslint-disable-next-line @next/next/no-img-element -- generated data URL */}
      {qr ? <img src={qr} alt="کد QR صفحه‌ی اتصال" className="size-32 rounded-md bg-white p-1" /> : null}
      <div className="flex-1">
        <p className="text-sm text-text-muted">کد اتصال «{result.deviceName}» (۱۰ دقیقه اعتبار):</p>
        <p dir="ltr" className="my-1 text-end text-4xl font-black tracking-[0.3em] text-brand">{toPersianDigits(result.code)}</p>
        <p className="text-xs text-text-muted">روی تبلت، QR را اسکن کنید یا این نشانی را باز کنید و کد را بزنید:</p>
        <p dir="ltr" className="mt-1 break-all text-end text-xs">{url}</p>
      </div>
    </div>
  );
}

export function DevicesPanel({ devices, branches, stations, tenant }: { devices: Device[]; branches: Branch[]; stations: Station[]; tenant: string }) {
  const [created, createAction, creating] = useActionState<PairingResult, FormData>(createDevice, { ok: false });
  const [repaired, setRepaired] = useState<PairingResult | null>(null);
  const [branchId, setBranchId] = useState(branches[0]?.id ?? '');
  const [busy, startBusy] = useTransition();
  const shown = repaired ?? created;
  const stationName = (id: string | null) => stations.find((s) => s.id === id)?.name ?? 'همه‌ی ایستگاه‌ها';
  const statusBadge = (d: Device) => d.status === 'paired'
    ? <Badge tone="success">متصل</Badge>
    : d.status === 'revoked' ? <Badge tone="danger">لغو شده</Badge> : <Badge tone="warning">منتظر اتصال</Badge>;

  return (
    <Card>
      <CardHeader title="دستگاه‌های آشپزخانه" description="هر تبلت کد اتصال خودش را می‌گیرد؛ به‌جای رمز مشترک. با «لغو»، دستگاه گم‌شده فوراً قطع می‌شود." />
      <div className="flex flex-col gap-4 p-5">
        {shown.message ? <Alert tone="danger">{shown.message}</Alert> : null}
        <PairingCode result={shown} tenant={tenant} />

        <form action={createAction} className="grid items-end gap-3 sm:grid-cols-4">
          <TextField label="نام دستگاه" name="name" required placeholder="مثلاً تبلت بار" />
          <SelectField label="شعبه" name="branch_id" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </SelectField>
          <SelectField label="ایستگاه" name="station_id" defaultValue="">
            <option value="">همه‌ی ایستگاه‌ها</option>
            {stations.filter((s) => s.branch_id === branchId).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </SelectField>
          <Button type="submit" loading={creating}>ساخت کد اتصال</Button>
        </form>

        {devices.length > 0 ? (
          <ul className="divide-y divide-border rounded-md border border-border">
            {devices.map((d) => (
              <li key={d.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                <div>
                  <p className="font-medium">{d.name} <span className="ms-1">{statusBadge(d)}</span></p>
                  <p className="text-xs text-text-muted">
                    {branches.find((b) => b.id === d.branch_id)?.name} • {stationName(d.station_id)}
                    {d.last_seen_at ? ` • آخرین فعالیت ${formatJalaliDateTime(d.last_seen_at)}` : ''}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button size="sm" variant="ghost" loading={busy} onClick={() => startBusy(async () => setRepaired(await repairDevice(d.id)))}>کد تازه</Button>
                  {d.status !== 'revoked' ? (
                    <Button size="sm" variant="ghost" loading={busy} onClick={() => startBusy(async () => { await revokeDevice(d.id); })}>لغو</Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </Card>
  );
}

export function KitchenSettingsForm({ dineIn, takeaway }: { dineIn: boolean; takeaway: boolean }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveKitchenSettings, { ok: false });

  return (
    <Card>
      <CardHeader title="پس از آماده شدن" description="وقتی همه‌ی آیتم‌های سفارش در آشپزخانه آماده شد، سفارش «آماده» می‌شود. برای کدام نوع سفارش همان لحظه «تحویل شد» هم ثبت شود؟" />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <Checkbox label="سفارش‌های سالن و میز (QR)" name="kds.auto_complete_dine_in" defaultChecked={dineIn} hint="معمولاً گارسون همان لحظه سفارش را سر میز می‌برد." />
        <Checkbox label="سفارش‌های بیرون‌بر و تلفنی" name="kds.auto_complete_takeaway" defaultChecked={takeaway} hint="اگر خاموش باشد، صندوق‌دار هنگام تحویل «تحویل شد» را می‌زند." />
        <p className="text-xs text-text-muted">سفارش‌های ارسالی (پیک) هیچ‌وقت خودکار بسته نمی‌شوند. امتیاز و کش‌بک باشگاه با «تحویل شد» داده می‌شود.</p>
        <div><Button type="submit" loading={pending}>ذخیره</Button></div>
      </form>
    </Card>
  );
}
