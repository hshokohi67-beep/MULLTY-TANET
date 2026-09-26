'use client';

import { useActionState, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { Copy, LogOut, RotateCcw, Share2, Trash2 } from 'lucide-react';
import { Button, Checkbox, TextField } from '@cafe/ui';
import { formatMoney, formatNumber, JALALI_MONTHS, jalaliMonthDays, toLatinDigits } from '@cafe/locale';
import { applyReferral, deleteAddress, logoutCustomer, redeemPoints, reorder, updateProfile } from '@/app/actions/storefront';
import { FormStatus } from '@/components/FormStatus';
import type { Customer } from '@/lib/storefront-types';
import type { FormState } from '@/lib/types';
import { useStore } from './StoreProvider';

export function RedeemPoints({ points, min, pointValue }: { points: number; min: number; pointValue: number }) {
  const { tenant, announce } = useStore();
  const router = useRouter();
  const [value, setValue] = useState(String(points));
  const [pending, start] = useTransition();
  const n = Number(toLatinDigits(value)) || 0;

  if (points < min) {
    return <p className="text-xs text-text-muted">از {formatNumber(min)} امتیاز می‌توانید امتیازها را به اعتبار کیف پول تبدیل کنید.</p>;
  }

  return (
    <form className="flex items-end gap-2" onSubmit={(e) => {
      e.preventDefault();
      start(async () => {
        const result = await redeemPoints(tenant, n);
        announce(result.ok ? (result.message ?? 'تبدیل شد.') : result.message, result.ok ? 'success' : 'error');
        if (result.ok) router.refresh();
      });
    }}>
      <div className="flex-1">
        <TextField label="تبدیل امتیاز به کیف پول" inputMode="numeric" value={value} onChange={(e) => setValue(e.target.value)} hint={n > 0 ? `= ${formatMoney(n * pointValue)}` : undefined} />
      </div>
      <Button type="submit" variant="secondary" loading={pending} disabled={n < min || n > points}>تبدیل</Button>
    </form>
  );
}

export function ReferralBox({ code, canApply }: { code: string; canApply: boolean }) {
  const { tenant, store, announce } = useStore();
  const [friend, setFriend] = useState('');
  const [pending, start] = useTransition();
  const text = `با کد معرف من (${code}) در ${store.name} عضو شو و هدیه بگیر!`;

  const share = async () => {
    if (navigator.share) {
      try { await navigator.share({ title: store.name, text, url: `${window.location.origin}/s/${tenant}` }); } catch { /* cancelled */ }
    } else {
      await navigator.clipboard.writeText(code);
      announce('کد معرف کپی شد');
    }
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <span className="flex-1 rounded-xl border border-dashed border-border-strong px-3 py-2 text-center font-mono text-lg tracking-widest" dir="ltr">{code}</span>
        <button type="button" onClick={async () => { await navigator.clipboard.writeText(code); announce('کد معرف کپی شد'); }} aria-label="کپی کد معرف" className="flex size-10 items-center justify-center rounded-xl bg-surface-muted hover:text-brand"><Copy className="size-4" /></button>
        <button type="button" onClick={share} aria-label="اشتراک‌گذاری" className="flex size-10 items-center justify-center rounded-xl bg-surface-muted hover:text-brand"><Share2 className="size-4" /></button>
      </div>
      {canApply ? (
        <form className="flex items-end gap-2" onSubmit={(e) => {
          e.preventDefault();
          start(async () => {
            const result = await applyReferral(tenant, friend);
            announce(result.ok ? (result.message ?? 'ثبت شد.') : result.message, result.ok ? 'success' : 'error');
            if (result.ok) setFriend('');
          });
        }}>
          <div className="flex-1"><TextField label="کد معرف دوستتان" value={friend} onChange={(e) => setFriend(e.target.value)} maxLength={12} ltr /></div>
          <Button type="submit" variant="secondary" loading={pending} disabled={!friend.trim()}>ثبت</Button>
        </form>
      ) : null}
    </div>
  );
}

export function ReorderButton({ orderId, branchId }: { orderId: string; branchId: string }) {
  const { tenant, announce, reload } = useStore();
  const router = useRouter();
  const [pending, start] = useTransition();

  return (
    <Button size="sm" variant="secondary" icon={<RotateCcw />} loading={pending} onClick={() => start(async () => {
      const result = await reorder(tenant, orderId, branchId);
      if (!result.ok) {
        announce(result.message, 'error');
        return;
      }
      await reload();
      if (result.data.skipped.length) announce(`${result.data.skipped.join('، ')} دیگر موجود نیست`, 'error');
      router.push(`/s/${tenant}/cart`);
    })}>
      سفارش دوباره
    </Button>
  );
}

export function ProfileForm({ customer }: { customer: Customer }) {
  const { tenant } = useStore();
  const [state, action, pending] = useActionState<FormState, FormData>(updateProfile.bind(null, tenant), { ok: false });
  const [month, setMonth] = useState(customer.birth_month ?? 0);

  return (
    <form action={action} className="flex flex-col gap-3">
      <FormStatus state={state} />
      <TextField label="نام" name="name" defaultValue={customer.name ?? ''} maxLength={120} error={state.errors?.name} />
      <fieldset disabled={customer.birthday_locked}>
        <legend className="mb-1.5 text-sm font-medium">تاریخ تولد <span className="font-normal text-text-muted">{customer.birthday_locked ? '(ثبت شده؛ برای تغییر با کافه تماس بگیرید)' : '(برای هدیه‌ی تولد؛ فقط یک بار)'}</span></legend>
        <div className="flex gap-2">
          <select name="birth_day" defaultValue={customer.birth_day ?? ''} aria-label="روز" className="h-11 rounded-lg border border-border-strong bg-surface px-2 text-sm disabled:opacity-60">
            <option value="">روز</option>
            {Array.from({ length: month ? jalaliMonthDays(month) : 31 }, (_, i) => <option key={i + 1} value={i + 1}>{formatNumber(i + 1)}</option>)}
          </select>
          <select name="birth_month" value={month || ''} onChange={(e) => setMonth(Number(e.target.value))} aria-label="ماه" className="h-11 flex-1 rounded-lg border border-border-strong bg-surface px-2 text-sm disabled:opacity-60">
            <option value="">ماه</option>
            {JALALI_MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
          </select>
        </div>
      </fieldset>
      <Checkbox name="marketing_opt_in" defaultChecked={customer.marketing_opt_in} label="پیشنهادها و تخفیف‌ها را پیامک کن" />
      <Button type="submit" loading={pending} className="self-start">ذخیره</Button>
    </form>
  );
}

export function DeleteAddress({ id }: { id: string }) {
  const { tenant, announce } = useStore();
  const router = useRouter();
  const [confirm, setConfirm] = useState(false);
  const [pending, start] = useTransition();

  if (!confirm) {
    return <button type="button" onClick={() => setConfirm(true)} aria-label="حذف آدرس" className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-danger-soft hover:text-danger"><Trash2 className="size-4" /></button>;
  }

  return (
    <span className="flex items-center gap-1 text-sm">
      <button type="button" disabled={pending} onClick={() => start(async () => {
        const result = await deleteAddress(tenant, id);
        if (!result.ok) announce(result.message, 'error');
        router.refresh();
      })} className="rounded-lg bg-danger px-2.5 py-1 font-medium text-white">حذف شود</button>
      <button type="button" onClick={() => setConfirm(false)} className="rounded-lg px-2.5 py-1 text-text-muted hover:bg-surface-muted">نه</button>
    </span>
  );
}

export function LogoutButton() {
  const { tenant, reload } = useStore();
  const router = useRouter();
  const [pending, start] = useTransition();

  return (
    <Button variant="ghost" icon={<LogOut />} loading={pending} onClick={() => start(async () => {
      await logoutCustomer(tenant);
      await reload();
      router.push(`/s/${tenant}/menu`);
    })}>
      خروج
    </Button>
  );
}
