'use client';

import { useRouter } from 'next/navigation';
import { useEffect, useMemo, useState, useTransition } from 'react';
import { Check, CreditCard, Minus, Plus, Sparkles, X } from 'lucide-react';
import { Alert, Badge, Button, cx, Dialog, Spinner } from '@cafe/ui';
import { formatJalaliDate, formatMoney, formatNumber, toPersianDigits } from '@cafe/locale';
import { checkoutSelection, payInvoice, quoteSelection, setCancelled } from '@/app/actions/billing';
import type { Addon, FeatureDef, Plan, Quote, Selection, Subscription } from '@/lib/billing-types';

type Cycle = 'monthly' | 'yearly';
type Current = { planId: string; cycle: Cycle; status: Subscription['status']; addons: Subscription['addons'] };

const LIMIT_TEXT: Record<string, (n: string) => string> = {
  branches: (n) => `${n} شعبه`,
  staff: (n) => `${n} عضو تیم`,
  products: (n) => `${n} محصول در منو`,
  monthly_orders: (n) => `${n} سفارش در ماه`,
};
const UNLIMITED: Record<string, string> = { branches: 'شعبه‌ی نامحدود', staff: 'عضو تیم نامحدود', products: 'محصول نامحدود', monthly_orders: 'سفارش نامحدود' };

/** Plan cards with a monthly/yearly switch; choosing one opens the checkout with a live quote. */
export function PlanPicker({ plans, addons, features, current, highlight }: { plans: Plan[]; addons: Addon[]; features: FeatureDef[]; current: Current; highlight: string | null }) {
  const [cycle, setCycle] = useState<Cycle>(current.status === 'trialing' ? 'monthly' : current.cycle);
  const [chosen, setChosen] = useState<Plan | null>(null);
  const switches = features.filter((f) => f.type === 'switch');

  return (
    <section aria-labelledby="plans-title" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="plans-title" className="text-lg font-bold">پلن‌ها</h2>
          <p className="text-sm text-text-muted">قیمت‌ها به تومان و بدون مالیات بر ارزش افزوده است.</p>
        </div>
        <div role="radiogroup" aria-label="دوره‌ی پرداخت" className="flex rounded-full bg-surface-muted p-1 text-sm">
          {(['monthly', 'yearly'] as const).map((c) => (
            <button key={c} type="button" role="radio" aria-checked={cycle === c} onClick={() => setCycle(c)}
              className={cx('flex items-center gap-1.5 rounded-full px-4 py-1.5', cycle === c ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted hover:text-text')}>
              {c === 'monthly' ? 'ماهانه' : 'سالانه'}
              {c === 'yearly' ? <span className="rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-semibold text-success">۲ ماه رایگان</span> : null}
            </button>
          ))}
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        {plans.map((p) => {
          const isCurrent = p.id === current.planId && current.status !== 'trialing';
          const recommended = p.key === 'pro' && !isCurrent;
          const lacks = highlight && p.features[highlight] === false;
          const perMonth = cycle === 'yearly' ? Math.round(p.yearly_price / 12 / 10) * 10 : p.monthly_price;

          return (
            <article key={p.id} className={cx('relative flex flex-col gap-4 rounded-2xl border bg-surface p-5 shadow-[var(--shadow-sm)] transition-shadow',
              recommended ? 'border-brand shadow-[var(--shadow-md)]' : 'border-border', lacks && 'opacity-60')}>
              {recommended ? <span className="absolute -top-3 start-5 inline-flex items-center gap-1 rounded-full bg-brand px-3 py-1 text-xs font-semibold text-on-brand"><Sparkles className="size-3" aria-hidden="true" />پیشنهاد ما</span> : null}
              <div>
                <p className="flex items-center gap-2 text-lg font-bold">{p.name}{isCurrent ? <Badge tone="success">پلن فعلی</Badge> : null}</p>
                {p.tagline ? <p className="mt-1 min-h-10 text-sm text-text-muted">{p.tagline}</p> : null}
              </div>
              <div>
                <p className="tabular text-2xl font-bold">{formatMoney(perMonth, { withUnit: false })} <span className="text-sm font-normal text-text-muted">تومان در ماه</span></p>
                <p className="h-5 text-xs text-text-subtle">{cycle === 'yearly' ? `${formatMoney(p.yearly_price)} یک‌جا برای ۱۲ ماه` : ''}</p>
              </div>
              <ul className="flex flex-1 flex-col gap-2 text-sm">
                {(['branches', 'staff', 'products', 'monthly_orders'] as const).map((k) => {
                  const v = p.features[k];

                  return <li key={k} className="flex items-center gap-2"><Check className="size-4 shrink-0 text-success" aria-hidden="true" />{typeof v === 'number' ? LIMIT_TEXT[k](formatNumber(v)) : UNLIMITED[k]}</li>;
                })}
                {switches.map((f) => (
                  <li key={f.key} className={cx('flex items-center gap-2', p.features[f.key] !== true && 'text-text-subtle', highlight === f.key && 'font-semibold')}>
                    {p.features[f.key] === true ? <Check className="size-4 shrink-0 text-success" aria-hidden="true" /> : <X className="size-4 shrink-0" aria-hidden="true" />}
                    <span>{f.label}</span>
                    <span className="sr-only">{p.features[f.key] === true ? '(دارد)' : '(ندارد)'}</span>
                  </li>
                ))}
              </ul>
              <Button variant={recommended ? 'primary' : 'secondary'} onClick={() => setChosen(p)}>
                {isCurrent && cycle === current.cycle ? 'تمدید یا افزونه' : isCurrent ? 'تغییر دوره' : 'انتخاب این پلن'}
              </Button>
            </article>
          );
        })}
      </div>

      <Dialog open={chosen !== null} onClose={() => setChosen(null)} title={chosen ? `پلن ${chosen.name} • ${cycle === 'yearly' ? 'سالانه' : 'ماهانه'}` : ''} size="md">
        {chosen ? <Checkout key={`${chosen.id}${cycle}`} plan={chosen} cycle={cycle} addons={addons.filter((a) => !a.plans || a.plans.includes(chosen.key))} current={current} onDone={() => setChosen(null)} /> : null}
      </Dialog>
    </section>
  );
}

function Checkout({ plan, cycle, addons, current, onDone }: { plan: Plan; cycle: Cycle; addons: Addon[]; current: Current; onDone: () => void }) {
  const router = useRouter();
  // Keep the café's add-ons when staying on the same plan.
  const [qty, setQty] = useState<Record<string, number>>(() => Object.fromEntries(
    plan.id === current.planId ? current.addons.map((a) => [a.id, a.quantity]) : [],
  ));
  const [quote, setQuote] = useState<Quote | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);
  const [loading, startQuote] = useTransition();
  const [paying, startPay] = useTransition();

  const selection: Selection = useMemo(() => ({
    plan_id: plan.id,
    cycle,
    addons: Object.entries(qty).filter(([, q]) => q > 0).map(([addon_id, quantity]) => ({ addon_id, quantity })),
  }), [plan.id, cycle, qty]);

  useEffect(() => {
    let alive = true;
    startQuote(async () => {
      const r = await quoteSelection(selection);
      if (!alive) return;
      if (r.ok) { setQuote(r.data); setError(null); } else { setError(r.message); }
    });

    return () => { alive = false; };
  }, [selection]);

  const confirm = () => startPay(async () => {
    const r = await checkoutSelection(selection);
    if (!r.ok) { setError(r.message); return; }
    if (r.data.redirect_url) { window.location.assign(r.data.redirect_url); return; }
    setDone(r.data.mode === 'scheduled' ? 'تغییر پلن ثبت شد و از پایان دوره‌ی فعلی اعمال می‌شود.' : 'اشتراک به‌روز شد.');
    router.refresh();
  });

  if (done) {
    return (
      <div className="flex flex-col items-center gap-3 py-6 text-center">
        <span className="flex size-12 items-center justify-center rounded-full bg-success-soft text-success"><Check className="size-6" aria-hidden="true" /></span>
        <p className="font-semibold">{done}</p>
        <Button onClick={onDone}>باشه</Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-5">
      {addons.length ? (
        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-sm font-semibold">افزونه‌ها</legend>
          {addons.map((a) => {
            const isSwitch = Object.values(a.grants).some((g) => g === true);
            const q = qty[a.id] ?? 0;
            const set = (n: number) => setQty((prev) => ({ ...prev, [a.id]: Math.max(0, Math.min(isSwitch ? 1 : 10, n)) }));

            return (
              <div key={a.id} className={cx('flex items-center gap-3 rounded-xl border px-3 py-2.5', q > 0 ? 'border-brand bg-brand-soft/40' : 'border-border')}>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium">{a.name}</p>
                  <p className="text-xs text-text-muted">{a.description} • {formatMoney(cycle === 'yearly' ? a.yearly_price : a.monthly_price)} {cycle === 'yearly' ? 'در سال' : 'در ماه'}</p>
                </div>
                {isSwitch ? (
                  <button type="button" role="switch" aria-checked={q > 0} aria-label={a.name} onClick={() => set(q > 0 ? 0 : 1)}
                    className={cx('relative h-6 w-11 rounded-full transition-colors', q > 0 ? 'bg-brand' : 'bg-border-strong')}>
                    <span className={cx('absolute top-0.5 size-5 rounded-full bg-surface shadow transition-all', q > 0 ? 'start-[22px]' : 'start-0.5')} />
                  </button>
                ) : (
                  <div className="flex items-center gap-1 rounded-lg bg-surface-muted p-0.5">
                    <button type="button" onClick={() => set(q - 1)} disabled={q === 0} aria-label={`کم کردن ${a.name}`} className="flex size-8 items-center justify-center rounded-md hover:bg-surface disabled:opacity-40"><Minus className="size-4" /></button>
                    <span className="tabular w-6 text-center text-sm font-semibold" aria-live="polite">{toPersianDigits(q)}</span>
                    <button type="button" onClick={() => set(q + 1)} aria-label={`افزودن ${a.name}`} className="flex size-8 items-center justify-center rounded-md hover:bg-surface"><Plus className="size-4" /></button>
                  </div>
                )}
              </div>
            );
          })}
        </fieldset>
      ) : null}

      <div className={cx('rounded-xl bg-surface-muted p-4 text-sm transition-opacity', loading && 'opacity-60')} aria-busy={loading}>
        {quote ? (
          <div className="flex flex-col gap-2">
            {quote.lines.map((l) => <p key={l.label} className="flex justify-between gap-3"><span>{l.label}</span><span className="tabular">{formatMoney(l.amount)}</span></p>)}
            {quote.credit ? <p className="flex justify-between gap-3 text-success"><span>اعتبار روزهای باقی‌مانده‌ی پلن فعلی</span><span className="tabular">−{formatMoney(quote.credit)}</span></p> : null}
            {quote.mode !== 'scheduled' ? <p className="flex justify-between gap-3 text-text-muted"><span>مالیات بر ارزش افزوده ({toPersianDigits(quote.vat_rate)}٪)</span><span className="tabular">{formatMoney(quote.vat)}</span></p> : null}
            <p className="mt-1 flex justify-between gap-3 border-t border-border pt-2 text-base font-bold"><span>{quote.mode === 'scheduled' ? 'پرداخت لازم نیست' : 'مبلغ قابل پرداخت'}</span><span className="tabular">{formatMoney(quote.total)}</span></p>
            <p className="text-xs text-text-muted">
              {quote.mode === 'scheduled' && quote.period_start ? `این تغییر از ${formatJalaliDate(quote.period_start)} (پایان دوره‌ی فعلی) اعمال می‌شود.` : null}
              {quote.mode === 'renew' && quote.period_start && quote.period_end ? `تمدید از ${formatJalaliDate(quote.period_start)} تا ${formatJalaliDate(quote.period_end)}.` : null}
              {quote.mode === 'pay_now' && quote.period_end ? `از امروز تا ${formatJalaliDate(quote.period_end)}.` : null}
            </p>
          </div>
        ) : <p className="flex items-center gap-2 text-text-muted"><Spinner />در حال محاسبه…</p>}
      </div>

      {quote?.warnings.length ? (
        <Alert tone="warning" title="پیش از تأیید بدانید">
          <ul className="list-disc ps-4">{quote.warnings.map((w) => <li key={w}>{w}</li>)}</ul>
        </Alert>
      ) : null}
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}

      <div className="flex gap-2">
        <Button icon={<CreditCard />} onClick={confirm} loading={paying} disabled={!quote || loading}>
          {quote?.mode === 'scheduled' ? 'ثبت تغییر پلن' : 'پرداخت و فعال‌سازی'}
        </Button>
        <Button variant="ghost" onClick={onDone}>انصراف</Button>
      </div>
      {quote?.mode !== 'scheduled' ? <p className="text-[11px] text-text-subtle">به درگاه پرداخت امن منتقل می‌شوید و پس از پرداخت به همین صفحه برمی‌گردید.</p> : null}
    </div>
  );
}

export function PayInvoiceButton({ id, small = false }: { id: string; small?: boolean }) {
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);

  return (
    <span className="inline-flex flex-col items-end">
      <Button size={small ? 'sm' : 'md'} icon={<CreditCard />} loading={pending}
        onClick={() => start(async () => { const r = await payInvoice(id); if (r.ok) window.location.assign(r.data.redirect_url); else setError(r.message); })}>
        پرداخت
      </Button>
      {error ? <span role="alert" className="mt-1 text-xs text-danger">{error}</span> : null}
    </span>
  );
}

/** Cancel at period end (with a second, explicit tap) or resume. */
export function SubscriptionActions({ status, state }: { status: Subscription['status']; state: Subscription['state'] }) {
  const router = useRouter();
  const [confirming, setConfirming] = useState(false);
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const run = (cancel: boolean) => start(async () => {
    const r = await setCancelled(cancel);
    if (r.ok) { setConfirming(false); router.refresh(); } else { setError(r.message); }
  });

  if (status === 'cancelled' && state === 'active') {
    return (
      <div className="flex flex-wrap items-center gap-3 border-t border-border pt-4 text-sm">
        <span className="flex-1 text-text-muted">اشتراک لغو شده و در پایان دوره فقط‌خواندنی می‌شود.</span>
        <Button size="sm" variant="secondary" loading={pending} onClick={() => run(false)}>ادامه‌ی اشتراک</Button>
      </div>
    );
  }
  if (status !== 'active' || state !== 'active') return null;

  return (
    <div className="flex flex-wrap items-center gap-3 border-t border-border pt-4 text-sm">
      {confirming ? (
        <>
          <span className="flex-1 text-danger">لغو شود؟ تا پایان دوره کار می‌کند و اطلاعات پاک نمی‌شود.</span>
          <Button size="sm" variant="danger" loading={pending} onClick={() => run(true)}>بله، لغو شود</Button>
          <Button size="sm" variant="ghost" onClick={() => setConfirming(false)}>نه</Button>
        </>
      ) : (
        <button type="button" onClick={() => setConfirming(true)} className="text-xs text-text-subtle hover:text-danger hover:underline">لغو اشتراک در پایان دوره</button>
      )}
      {error ? <p role="alert" className="w-full text-xs text-danger">{error}</p> : null}
    </div>
  );
}
