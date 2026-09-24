'use client';

import QRCode from 'qrcode';
import { useActionState, useEffect, useState, useTransition } from 'react';
import { Alert, Badge, Button, Card, SelectField, TextField } from '@cafe/ui';
import { formatJalaliDate, formatTime } from '@cafe/locale';
import { closeTableSession, issueTableQr, saveTable, type QrState } from '@/app/actions/commerce';
import { FormStatus } from '@/components/FormStatus';
import type { Branch, FormState, RestaurantTable } from '@/lib/types';

export function AddTableForm({ branches }: { branches: Branch[] }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveTable.bind(null, null), { ok: false });

  return (
    <Card className="p-4">
      <form action={action} key={state.ok ? state.message : 'form'} className="flex flex-col gap-3">
        <p className="text-sm font-semibold">میز جدید</p>
        <FormStatus state={state} />
        <div className="grid items-start gap-3 sm:grid-cols-[2fr_1fr_1fr_auto]">
          <TextField label="نام میز" name="label" required placeholder="مثلاً میز ۱۲ یا تراس ۳" error={state.errors?.label} />
          <TextField label="ظرفیت" name="capacity" inputMode="numeric" ltr error={state.errors?.capacity} />
          <SelectField label="شعبه" name="branch_id" defaultValue={branches[0]?.id} error={state.errors?.branch_id}>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </SelectField>
          <Button type="submit" loading={pending} className="sm:mt-7">افزودن</Button>
        </div>
      </form>
    </Card>
  );
}

export function TableRow({ table, storefrontBase, canManage, canCloseSession }: { table: RestaurantTable; storefrontBase: string; canManage: boolean; canCloseSession: boolean }) {
  const [qr, setQr] = useState<QrState | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [pending, start] = useTransition();

  const issue = () =>
    start(async () => {
      setConfirming(false);
      setQr(await issueTableQr(table.id));
    });

  return (
    <li className="flex flex-col gap-3 px-4 py-3">
      <div className="flex flex-wrap items-center gap-3">
        <span className="font-medium">{table.label}</span>
        {!table.is_active ? <Badge>غیرفعال</Badge> : null}
        {table.open_session ? <Badge tone="info">میز فعال از {formatTime(table.open_session.opened_at)}</Badge> : null}
        <span className="text-xs text-text-muted">
          {table.qr ? `کد QR فعال (ساخته‌شده در ${table.qr.issued_at ? formatJalaliDate(table.qr.issued_at) : '—'})` : 'بدون کد QR'}
        </span>
        <div className="ms-auto flex gap-1">
          {table.open_session && canCloseSession ? (
            <Button size="sm" variant="ghost" loading={pending} onClick={() => start(() => closeTableSession(table.id))}>بستن میز</Button>
          ) : null}
          {canManage ? (
            table.qr && !confirming ? (
              <Button size="sm" variant="secondary" onClick={() => setConfirming(true)}>ساخت QR جدید</Button>
            ) : !table.qr ? (
              <Button size="sm" loading={pending} onClick={issue}>ساخت QR</Button>
            ) : null
          ) : null}
        </div>
      </div>

      {confirming ? (
        <Alert tone="warning" action={<div className="flex gap-2"><Button size="sm" loading={pending} onClick={issue}>بله، کد جدید بساز</Button><Button size="sm" variant="ghost" onClick={() => setConfirming(false)}>انصراف</Button></div>}>
          با ساخت کد جدید، QR چاپ‌شده‌ی فعلی روی این میز دیگر کار نمی‌کند.
        </Alert>
      ) : null}

      {qr?.message && !qr.ok ? <Alert tone="danger">{qr.message}</Alert> : null}
      {qr?.token ? <QrPreview label={table.label} url={`${storefrontBase}/${qr.token}`} /> : null}
    </li>
  );
}

/** Rendered in the browser; the token never leaves the dashboard except inside the printed code. */
function QrPreview({ label, url }: { label: string; url: string }) {
  const [src, setSrc] = useState<string | null>(null);

  useEffect(() => {
    QRCode.toDataURL(url, { width: 480, margin: 2, errorCorrectionLevel: 'M' }).then(setSrc, () => setSrc(null));
  }, [url]);

  if (!src) return null;

  const print = () => {
    const safe = label.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] ?? c);
    const win = window.open('', '_blank', 'noopener=no,width=480,height=640');
    if (!win) return;
    win.document.write(`<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>${safe}</title>
      <style>body{font-family:Vazirmatn,Tahoma,sans-serif;text-align:center;padding:24px}img{width:320px;height:320px}h1{font-size:28px;margin:8px}p{color:#555}</style>
      </head><body><h1>${safe}</h1><img src="${src}" alt=""><p>برای دیدن منو و سفارش، کد را اسکن کنید</p>
      <script>window.onload=()=>{window.print()}</script></body></html>`);
    win.document.close();
  };

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-md border border-border p-3">
      {/* eslint-disable-next-line @next/next/no-img-element -- locally generated data URL */}
      <img src={src} alt={`کد QR ${label}`} className="size-40" />
      <div className="flex flex-col gap-2 text-sm">
        <p className="font-medium">کد QR «{label}» آماده است.</p>
        <p className="text-text-muted">این کد فقط همین حالا نمایش داده می‌شود؛ همین الان چاپ یا ذخیره کنید.</p>
        <div className="flex gap-2">
          <Button size="sm" onClick={print}>چاپ</Button>
          <a href={src} download={`qr-${label}.png`} className="inline-flex h-8 items-center rounded-md border border-border-strong px-3 text-sm hover:bg-surface-muted">دانلود تصویر</a>
        </div>
      </div>
    </div>
  );
}
