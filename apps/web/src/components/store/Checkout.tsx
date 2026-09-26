'use client';

import Link from 'next/link';
import { createElement, useEffect, useMemo, useRef, useState, useTransition, type ReactNode } from 'react';
import {
  Banknote, Bike, CalendarClock, ChevronRight, Clock, CreditCard, Lock, MapPin, Minus, Plus, ShoppingBag, Store, Tag, Trash2, Wallet, X, Zap,
} from 'lucide-react';
import { Alert, Button, cx, EmptyState, Skeleton } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { loadPreorderSlots, placeOrder, quoteCart, switchOrderType, updateCartLine, type PreorderSlots } from '@/app/actions/storefront';
import { useSubmissionKey } from '@/components/useSubmissionKey';
import type { CustomerAddress, QuoteLine } from '@/lib/storefront-types';
import { illustrationFor } from './ProductVisuals';
import { useStore } from './StoreProvider';

/** A numbered step card: the checkout reads top to bottom like a short form. */
function Step({ n, title, hint, children }: { n: number; title: string; hint?: ReactNode; children: ReactNode }) {
  return (
    <section aria-labelledby={`step-${n}`} className="rounded-3xl border border-border bg-surface p-4 shadow-[var(--shadow-sm)] sm:p-5">
      <header className="mb-3.5 flex items-center gap-2.5">
        <span aria-hidden="true" className="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-bold text-brand">{formatNumber(n)}</span>
        <h2 id={`step-${n}`} className="font-bold">{title}</h2>
        {hint ? <span className="ms-auto text-xs text-text-muted">{hint}</span> : null}
      </header>
      {children}
    </section>
  );
}

function Option({ checked, onChange, icon, title, hint, disabled, name }: {
  checked: boolean; onChange: () => void; icon: ReactNode; title: string; hint?: string; disabled?: boolean; name: string;
}) {
  return (
    <label className={cx(
      'relative flex flex-1 cursor-pointer items-center gap-3 rounded-2xl border-2 px-3 py-3 transition-all duration-[var(--duration-base)] has-[:focus-visible]:shadow-[var(--focus-ring)]',
      checked ? 'border-brand bg-brand-soft/60' : 'border-transparent bg-surface-muted hover:bg-surface-muted/70',
      disabled && 'cursor-not-allowed opacity-50',
    )}>
      <input type="radio" name={name} checked={checked} onChange={onChange} disabled={disabled} className="sr-only" />
      <span className={cx('flex size-10 shrink-0 items-center justify-center rounded-xl transition-colors', checked ? 'bg-brand text-on-brand' : 'bg-surface text-text-muted')}>{icon}</span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-semibold">{title}</span>
        {hint ? <span className="block text-xs leading-5 text-text-muted">{hint}</span> : null}
      </span>
      <span aria-hidden="true" className={cx('size-5 shrink-0 rounded-full border-2 transition-all', checked ? 'border-brand bg-brand shadow-[inset_0_0_0_3px_var(--color-surface)]' : 'border-border-strong')} />
    </label>
  );
}

function Line({ line, busy, onChange }: { line: QuoteLine; busy: boolean; onChange: (qty: number) => void }) {
  return (
    <li className="flex gap-3 py-3.5 first:pt-0 last:pb-0">
      <span aria-hidden="true" className="photo-fallback flex size-14 shrink-0 items-center justify-center rounded-2xl">
        {createElement(illustrationFor(line.product_name), { className: 'size-6', strokeWidth: 1.5 })}
      </span>
      <div className="min-w-0 flex-1">
        <p className="font-semibold leading-6">{line.product_name}</p>
        {line.variant_name || line.modifiers.length ? (
          <p className="mt-0.5 flex flex-wrap gap-1">
            {line.variant_name ? <span className="rounded-md bg-surface-muted px-1.5 py-0.5 text-[11px] text-text-muted">{line.variant_name}</span> : null}
            {line.modifiers.map((m) => <span key={m.modifier_id} className="rounded-md bg-surface-muted px-1.5 py-0.5 text-[11px] text-text-muted">{m.name}</span>)}
          </p>
        ) : null}
        {line.note ? <p className="mt-0.5 text-xs text-text-subtle">«{line.note}»</p> : null}
        {line.problem ? <p className="mt-1 rounded-lg bg-danger-soft px-2 py-1 text-xs text-danger">{line.problem.message}</p> : null}
        <div className="mt-2 flex items-center justify-between gap-2">
          <span className="tabular text-sm font-bold">{formatMoney(line.line_total)}</span>
          <div className="flex h-9 items-center gap-0.5 rounded-full bg-surface-muted p-0.5">
            <button type="button" disabled={busy} onClick={() => onChange(line.quantity + 1)} aria-label={`یکی بیشتر ${line.product_name}`} className="flex size-8 items-center justify-center rounded-full hover:bg-surface"><Plus className="size-4" /></button>
            <span className="tabular w-6 text-center text-sm font-bold" aria-label="تعداد">{formatNumber(line.quantity)}</span>
            <button type="button" disabled={busy} onClick={() => onChange(line.quantity - 1)} aria-label={line.quantity > 1 ? `یکی کمتر ${line.product_name}` : `حذف ${line.product_name}`} className="flex size-8 items-center justify-center rounded-full hover:bg-surface">
              {line.quantity > 1 ? <Minus className="size-4" /> : <Trash2 className="size-4 text-danger" />}
            </button>
          </div>
        </div>
      </div>
    </li>
  );
}

/**
 * Checkout on one calm page: the order, how and when you get it, how you pay; a live summary
 * from the server's pricer (the same numbers the order will have). Phones keep the total and the
 * action in a glass bar at the bottom.
 */
export function Checkout({ addresses, walletBalance }: { addresses: CustomerAddress[]; walletBalance: number }) {
  const { tenant, store, session, setCart, announce } = useStore();
  const cart = session?.cart ?? null;
  const [coupon, setCoupon] = useState('');
  const [couponOpen, setCouponOpen] = useState(false);
  const [appliedCoupon, setAppliedCoupon] = useState('');
  const [addressId, setAddressId] = useState(addresses.find((a) => a.is_default)?.id ?? addresses[0]?.id ?? '');
  const [slots, setSlots] = useState<PreorderSlots | null>(null);
  const [when, setWhen] = useState<'now' | 'later'>('now');
  const [dayIndex, setDayIndex] = useState(0);
  const [slot, setSlot] = useState('');
  const [payment, setPayment] = useState<'cash' | 'online'>(store.features.online_payment ? 'online' : 'cash');
  const [useWallet, setUseWallet] = useState(walletBalance > 0);
  const [note, setNote] = useState('');
  const [contactName, setContactName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, startBusy] = useTransition();
  const [placing, startPlacing] = useTransition();
  const [key, renewKey] = useSubmissionKey();
  const quoted = useRef('');

  const isTable = cart?.order_type === 'qr_table';
  const isDelivery = cart?.order_type === 'delivery';
  const branchId = cart?.branch.id;
  const branch = store.branches.find((b) => b.id === branchId);
  const signedIn = Boolean(session?.customer);
  const scheduledFor = when === 'later' ? slot : '';

  // Slots for pickup/delivery orders; when the café is closed, pre-order is the only way.
  useEffect(() => {
    if (!branchId || isTable) return;
    let cancelled = false;
    void loadPreorderSlots(tenant, branchId).then((data) => {
      if (cancelled || !data) return;
      setSlots(data);
      if (!data.open_now) {
        setWhen('later');
        const first = data.days.flatMap((d, i) => d.slots.filter((s) => s.available).map((s) => ({ i, s })))[0];
        if (first) { setDayIndex(first.i); setSlot(first.s.start); }
      }
    });

    return () => { cancelled = true; };
  }, [tenant, branchId, isTable]);

  // Re-quote when anything that changes the price or validity changes.
  useEffect(() => {
    if (!cart) return;
    const signature = JSON.stringify([cart.order_type, cart.quote.lines.length, appliedCoupon, isDelivery ? addressId : '', scheduledFor]);
    if (signature === quoted.current) return;
    quoted.current = signature;
    if (!appliedCoupon && !(isDelivery && addressId) && !scheduledFor) return;
    startBusy(async () => {
      const result = await quoteCart(tenant, { coupon: appliedCoupon, addressId: isDelivery ? addressId : undefined, scheduledFor });
      if (result.ok) setCart(result.data);
    });
  }, [cart, appliedCoupon, addressId, scheduledFor, isDelivery, tenant, setCart]);

  const itemCount = useMemo(() => cart?.quote.lines.reduce((n, l) => n + l.quantity, 0) ?? 0, [cart]);

  if (!session) {
    return (
      <div className="mx-auto grid max-w-5xl gap-5 pt-5 lg:grid-cols-[1fr_24rem]">
        <div className="flex flex-col gap-4"><Skeleton className="h-8 w-44" /><Skeleton className="h-52 rounded-3xl" /><Skeleton className="h-32 rounded-3xl" /></div>
        <Skeleton className="h-80 rounded-3xl" />
      </div>
    );
  }

  if (!cart || cart.quote.lines.length === 0) {
    return (
      <div className="pt-10">
        <EmptyState icon={<ShoppingBag />} title="سبد خرید خالی است" description="از منو چیزی انتخاب کنید؛ اینجا منتظرش هستیم."
          action={<Link href={`/s/${tenant}/menu`} className="inline-flex h-11 items-center rounded-xl bg-brand px-5 text-sm font-semibold text-on-brand hover:bg-brand-strong">رفتن به منو</Link>} />
      </div>
    );
  }

  const q = cart.quote;
  const walletUse = useWallet && signedIn ? Math.min(walletBalance, q.total) : 0;
  const due = q.total - walletUse;
  const needsLogin = !isTable && !signedIn;
  const needsAddress = isDelivery && !addressId;
  const needsSlot = when === 'later' && !slot;
  const day = slots?.days[dayIndex];
  const chosenSlot = slots?.days.flatMap((d) => d.slots.map((s) => ({ ...s, day: d.label }))).find((s) => s.start === slot);
  const freeGap = q.delivery?.free_delivery_min && !q.delivery.free_delivery_applied ? Math.max(0, q.delivery.free_delivery_min - q.subtotal) : 0;
  // "Closed now" is only a problem when ordering for now.
  const issues = q.issues.filter((i) => !(when === 'later' && i.code === 'branch_closed'));

  const change = (ref: string, quantity: number) => startBusy(async () => {
    const result = await updateCartLine(tenant, ref, quantity);
    if (result.ok) setCart(result.data);
    else announce(result.message, 'error');
  });

  const switchType = (type: 'takeaway' | 'delivery') => startBusy(async () => {
    const result = await switchOrderType(tenant, type);
    if (result.ok) { quoted.current = ''; setCart(result.data); } else announce(result.message, 'error');
  });

  const submit = () => startPlacing(async () => {
    setError(null);
    const result = await placeOrder(tenant, {
      key, payment, useWallet: walletUse > 0, coupon: appliedCoupon || undefined,
      addressId: isDelivery ? addressId : undefined, scheduledFor: scheduledFor || undefined, note, contactName,
    });
    if (!result.ok) {
      setError(result.message);
      if (result.code !== 'idempotency_conflict') renewKey();
      return;
    }
    setCart(null);
    if (result.message) announce(result.message, 'error');
    window.location.assign(result.data.redirect);
  });

  const action = needsLogin ? (
    <Link href={`/s/${tenant}/login?next=cart`} className="flex h-12 items-center justify-center rounded-2xl bg-brand px-4 text-center text-sm font-semibold text-on-brand shadow-[var(--shadow-sm)] hover:bg-brand-strong">
      ورود و ثبت سفارش
    </Link>
  ) : (
    <Button size="lg" className="h-12 rounded-2xl" onClick={submit} loading={placing} disabled={busy || needsAddress || needsSlot || issues.length > 0 || !q.lines.every((l) => !l.problem)}>
      {payment === 'online' && due > 0 ? `پرداخت ${formatMoney(due)}` : 'ثبت سفارش'}
    </Button>
  );

  let step = 1;

  return (
    <div className="mx-auto grid max-w-5xl gap-5 pt-5 pb-16 lg:grid-cols-[1fr_24rem] lg:pb-0">
      <div className="flex flex-col gap-4">
        <header className="flex items-end justify-between gap-3">
          <div>
            <Link href={`/s/${tenant}/menu`} className="inline-flex items-center gap-1 text-sm text-text-muted hover:text-text"><ChevronRight className="size-4" aria-hidden="true" />ادامه‌ی خرید</Link>
            <h1 className="mt-1 text-2xl font-black">تکمیل سفارش</h1>
          </div>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1.5 text-xs text-text-muted ring-1 ring-border">
            <MapPin className="size-3.5" aria-hidden="true" />{cart.branch.name}{cart.table ? ` • ${cart.table.label}` : ''}
          </span>
        </header>

        <Step n={step++} title="سفارش شما" hint={`${formatNumber(itemCount)} مورد`}>
          <ul className="divide-y divide-border">
            {q.lines.map((line) => <Line key={line.ref} line={line} busy={busy} onChange={(qty) => change(line.ref, qty)} />)}
          </ul>
          <Link href={`/s/${tenant}/menu`} className="mt-3 flex h-11 items-center justify-center gap-1.5 rounded-2xl border border-dashed border-border-strong text-sm font-medium text-brand hover:bg-brand-soft/40">
            <Plus className="size-4" aria-hidden="true" />افزودن آیتم دیگر
          </Link>
        </Step>

        {!isTable ? (
          <Step n={step++} title="نحوه‌ی دریافت">
            <div className="grid gap-2 sm:grid-cols-2">
              <Option name="type" checked={cart.order_type === 'takeaway'} onChange={() => switchType('takeaway')} icon={<Store className="size-5" />} title="تحویل در کافه" hint="خودتان تحویل می‌گیرید" />
              <Option name="type" checked={isDelivery} onChange={() => switchType('delivery')} disabled={!branch?.delivery} icon={<Bike className="size-5" />} title="ارسال با پیک" hint={branch?.delivery ? 'به آدرس شما' : 'این شعبه ارسال ندارد'} />
            </div>

            {isDelivery && signedIn ? (
              <div className="mt-3 flex flex-col gap-2">
                {addresses.map((a) => (
                  <Option key={a.id} name="address" checked={addressId === a.id} onChange={() => setAddressId(a.id)} icon={<MapPin className="size-5" />}
                    title={a.title} hint={`${a.address}${a.has_location ? '' : ' • بدون موقعیت روی نقشه'}`} />
                ))}
                <Link href={`/s/${tenant}/account/addresses/new?next=cart`} className="flex h-11 items-center justify-center gap-1.5 rounded-2xl border border-dashed border-border-strong text-sm font-medium text-brand hover:bg-brand-soft/40">
                  <Plus className="size-4" aria-hidden="true" />آدرس جدید
                </Link>
                {q.delivery ? (
                  <p className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-surface-muted px-3 py-2 text-xs text-text-muted">
                    <span>محدوده‌ی «{q.delivery.zone_name}»</span>
                    {q.delivery.eta_minutes ? <span className="inline-flex items-center gap-1"><Clock className="size-3.5" aria-hidden="true" />حدود {formatNumber(q.delivery.eta_minutes)} دقیقه</span> : null}
                    <span>{q.delivery_fee ? `هزینه‌ی ارسال ${formatMoney(q.delivery_fee)}` : 'ارسال رایگان'}</span>
                  </p>
                ) : null}
                {freeGap > 0 && q.delivery?.free_delivery_min ? (
                  <div className="rounded-xl bg-success-soft px-3 py-2.5">
                    <p className="text-xs font-medium text-success">فقط {formatMoney(freeGap)} دیگر تا ارسال رایگان</p>
                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-success/15"><div className="h-full rounded-full bg-success" style={{ width: `${Math.min(100, (q.subtotal / q.delivery.free_delivery_min) * 100)}%` }} /></div>
                  </div>
                ) : null}
              </div>
            ) : null}
          </Step>
        ) : null}

        {!isTable ? (
          <Step n={step++} title="زمان دریافت" hint={chosenSlot && when === 'later' ? `${chosenSlot.day}، ساعت ${chosenSlot.label}` : undefined}>
            <div className="grid gap-2 sm:grid-cols-2">
              <Option name="when" checked={when === 'now'} onChange={() => setWhen('now')} disabled={slots !== null && !slots.open_now}
                icon={<Zap className="size-5" />} title="همین حالا" hint={slots && !slots.open_now ? 'الان بسته‌ایم' : 'به محض آماده شدن'} />
              <Option name="when" checked={when === 'later'} onChange={() => setWhen('later')} disabled={slots !== null && slots.days.length === 0}
                icon={<CalendarClock className="size-5" />} title="پیش‌سفارش" hint="روز و ساعت دلخواه" />
            </div>

            {when === 'later' ? (
              slots === null ? <Skeleton className="mt-3 h-28 rounded-2xl" /> : slots.days.length === 0 ? (
                <p className="mt-3 rounded-xl bg-surface-muted px-3 py-3 text-sm text-text-muted">فعلاً زمانی برای پیش‌سفارش باز نیست.</p>
              ) : (
                <div className="mt-3">
                  <div role="tablist" aria-label="روز" className="no-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    {slots.days.map((d, i) => (
                      <button key={d.date} type="button" role="tab" aria-selected={i === dayIndex} onClick={() => setDayIndex(i)}
                        className={cx('shrink-0 rounded-xl px-3.5 py-2 text-sm transition-colors', i === dayIndex ? 'bg-text font-semibold text-bg' : 'bg-surface-muted text-text-muted hover:text-text')}>
                        {d.label}
                      </button>
                    ))}
                  </div>
                  <div role="radiogroup" aria-label="ساعت" className="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6">
                    {day?.slots.map((s) => (
                      <button key={s.start} type="button" role="radio" aria-checked={slot === s.start} disabled={!s.available} onClick={() => setSlot(s.start)}
                        aria-label={s.available ? `ساعت ${s.label}` : `ساعت ${s.label} (پر شده)`}
                        className={cx('tabular h-10 rounded-xl text-sm transition-all',
                          slot === s.start ? 'bg-brand font-bold text-on-brand shadow-[var(--shadow-sm)]'
                            : s.available ? 'bg-surface-muted hover:bg-brand-soft' : 'cursor-not-allowed bg-surface-muted text-text-subtle line-through opacity-60')}>
                        {s.label}
                      </button>
                    ))}
                  </div>
                  {needsSlot ? <p className="mt-2 text-xs text-text-muted">یک ساعت انتخاب کنید.</p> : null}
                </div>
              )
            ) : null}
          </Step>
        ) : (
          <Step n={step++} title="نام شما" hint="اختیاری">
            <input aria-label="نام شما" value={contactName} onChange={(e) => setContactName(e.target.value)} maxLength={120} placeholder="تا گارسون راحت‌تر پیدایتان کند"
              className="h-12 w-full rounded-2xl border border-border-strong bg-surface px-4 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
          </Step>
        )}

        <Step n={step++} title="پرداخت">
          <div className="grid gap-2 sm:grid-cols-2">
            {store.features.online_payment ? (
              <Option name="pay" checked={payment === 'online'} onChange={() => setPayment('online')} icon={<CreditCard className="size-5" />} title="پرداخت آنلاین" hint="همه‌ی کارت‌های عضو شتاب" />
            ) : null}
            <Option name="pay" checked={payment === 'cash'} onChange={() => setPayment('cash')} icon={<Banknote className="size-5" />}
              title={isDelivery ? 'پرداخت در محل' : 'پرداخت در کافه'} hint="نقد یا کارت‌خوان" />
          </div>
          {signedIn && walletBalance > 0 ? (
            <label className={cx('mt-2 flex cursor-pointer items-center gap-3 rounded-2xl border-2 px-3 py-3 transition-colors has-[:focus-visible]:shadow-[var(--focus-ring)]', useWallet ? 'border-brand bg-brand-soft/60' : 'border-transparent bg-surface-muted')}>
              <span className={cx('flex size-10 items-center justify-center rounded-xl', useWallet ? 'bg-brand text-on-brand' : 'bg-surface text-text-muted')}><Wallet className="size-5" aria-hidden="true" /></span>
              <span className="flex-1">
                <span className="block text-sm font-semibold">استفاده از کیف پول</span>
                <span className="tabular block text-xs text-text-muted">موجودی {formatMoney(walletBalance)}</span>
              </span>
              <input type="checkbox" role="switch" checked={useWallet} onChange={(e) => setUseWallet(e.target.checked)} className="sr-only" />
              <span aria-hidden="true" className={cx('relative h-6 w-11 shrink-0 rounded-full transition-colors', useWallet ? 'bg-brand' : 'bg-border-strong')}>
                <span className={cx('absolute top-0.5 size-5 rounded-full bg-white shadow transition-all', useWallet ? 'start-[1.375rem]' : 'start-0.5')} />
              </span>
            </label>
          ) : null}
          <label htmlFor="order-note" className="mt-4 mb-1.5 block text-sm font-medium">توضیح برای کافه <span className="font-normal text-text-muted">(اختیاری)</span></label>
          <textarea id="order-note" value={note} onChange={(e) => setNote(e.target.value)} maxLength={300} rows={2} placeholder="مثلاً: کمی کم‌شیرین، بدون نی"
            className="w-full resize-none rounded-2xl border border-border-strong bg-surface px-4 py-2.5 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        </Step>
      </div>

      <aside className="lg:sticky lg:top-4 lg:self-start">
        <div className="flex flex-col gap-4 rounded-3xl border border-border bg-surface p-5 shadow-[var(--shadow-md)]">
          <h2 className="font-bold">خلاصه‌ی سفارش</h2>

          {q.discount ? (
            <div className="flex items-center gap-2 rounded-2xl bg-success-soft px-3 py-2 text-sm text-success">
              <Tag className="size-4" aria-hidden="true" />
              <span className="flex-1 font-medium">{q.discount.code ?? q.discount.name}</span>
              {appliedCoupon ? (
                <button type="button" onClick={() => { setAppliedCoupon(''); setCoupon(''); }} aria-label="حذف کد تخفیف" className="rounded-full p-1 hover:bg-success/10"><X className="size-4" /></button>
              ) : null}
            </div>
          ) : couponOpen ? (
            <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); setAppliedCoupon(coupon.trim()); }}>
              <label htmlFor="coupon" className="sr-only">کد تخفیف</label>
              <input id="coupon" value={coupon} onChange={(e) => setCoupon(e.target.value)} maxLength={40} placeholder="کد تخفیف" dir="ltr"
                className="h-11 min-w-0 flex-1 rounded-xl border border-border-strong bg-surface px-3 text-sm uppercase focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
              <Button type="submit" variant="secondary" disabled={!coupon.trim()}>اعمال</Button>
            </form>
          ) : (
            <button type="button" onClick={() => setCouponOpen(true)} className="inline-flex items-center gap-1.5 self-start text-sm font-medium text-brand hover:underline">
              <Tag className="size-4" aria-hidden="true" />کد تخفیف دارید؟
            </button>
          )}

          <dl aria-live="polite" className={cx('flex flex-col gap-2.5 text-sm transition-opacity', busy && 'opacity-60')}>
            <div className="flex justify-between"><dt className="text-text-muted">جمع آیتم‌ها</dt><dd className="tabular">{formatMoney(q.subtotal)}</dd></div>
            {q.discount ? <div className="flex justify-between text-success"><dt>تخفیف</dt><dd className="tabular">− {formatMoney(q.discount.amount)}</dd></div> : null}
            {isDelivery ? <div className="flex justify-between"><dt className="text-text-muted">هزینه‌ی ارسال</dt><dd className="tabular">{q.delivery ? (q.delivery_fee ? formatMoney(q.delivery_fee) : 'رایگان') : '—'}</dd></div> : null}
            {walletUse > 0 ? <div className="flex justify-between text-success"><dt>از کیف پول</dt><dd className="tabular">− {formatMoney(walletUse)}</dd></div> : null}
            <div className="mt-1 flex items-baseline justify-between border-t border-dashed border-border-strong pt-3">
              <dt className="font-semibold">{walletUse > 0 ? 'قابل پرداخت' : 'مبلغ کل'}</dt>
              <dd className="tabular text-xl font-black">{formatMoney(due)}</dd>
            </div>
          </dl>

          {when === 'later' && chosenSlot ? (
            <p className="flex items-center gap-2 rounded-2xl bg-info-soft px-3 py-2 text-sm text-info"><CalendarClock className="size-4 shrink-0" aria-hidden="true" />پیش‌سفارش برای {chosenSlot.day}، ساعت {chosenSlot.label}</p>
          ) : null}
          {issues.length ? (
            <ul className="flex flex-col gap-1.5">
              {issues.map((i) => <li key={i.code} className="rounded-xl bg-warning-soft px-3 py-2 text-sm text-warning">{i.message}</li>)}
            </ul>
          ) : null}
          {error ? <Alert tone="danger">{error}</Alert> : null}

          <div className="hidden lg:flex lg:flex-col">{action}</div>
          {payment === 'online' && !needsLogin ? (
            <p className="flex items-center justify-center gap-1.5 text-[11px] text-text-subtle"><Lock className="size-3" aria-hidden="true" />پرداخت امن از درگاه شاپرک</p>
          ) : null}
          {needsAddress && signedIn ? <p className="text-center text-xs text-text-muted">برای ارسال، یک آدرس انتخاب یا اضافه کنید.</p> : null}
        </div>
      </aside>

      {/* Phones: the total and the action stay in reach, on glass. */}
      <div className="fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
        <div className="glass mx-auto flex max-w-xl items-center gap-3 rounded-[1.4rem] p-2 ps-4">
          <div className="shrink-0">
            <p className="text-[11px] text-text-muted">{walletUse > 0 ? 'قابل پرداخت' : 'مبلغ کل'}</p>
            <p className="tabular text-lg font-black leading-6">{formatMoney(due)}</p>
          </div>
          <div className="flex min-w-0 flex-1 flex-col">{action}</div>
        </div>
      </div>
    </div>
  );
}
