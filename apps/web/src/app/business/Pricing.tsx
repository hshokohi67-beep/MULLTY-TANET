'use client';

import Link from 'next/link';
import { useState, type CSSProperties } from 'react';
import { Check, Minus, Sparkles } from 'lucide-react';
import { cx } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';

export interface PublicPlan {
  key: string;
  name: string;
  tagline: string | null;
  monthly_price: number;
  yearly_price: number;
  features: Record<string, boolean | number | null>;
  is_trial_plan: boolean;
}

export interface PlanFeature { key: string; label: string; type: 'switch' | 'limit' }

/** Features the pricing table leaves out: not offered yet (custom domain) or sold separately. */
const HIDDEN = ['custom_domain', 'marketplace_featured'];

const LIMIT_TEXT: Record<string, (n: number) => string> = {
  branches: (n) => (n === 1 ? 'یک شعبه' : `تا ${formatNumber(n)} شعبه`),
  staff: (n) => `تا ${formatNumber(n)} همکار`,
  products: (n) => `تا ${formatNumber(n)} محصول`,
  monthly_orders: (n) => `تا ${formatNumber(n)} سفارش در ماه`,
};
const UNLIMITED: Record<string, string> = { branches: 'شعبه‌ی نامحدود', staff: 'همکار نامحدود', products: 'محصول نامحدود', monthly_orders: 'سفارش نامحدود' };

/** Plans with a monthly / yearly switch (yearly = 10 months' price). Prices are rial, shown in toman. */
export function Pricing({ plans, features, vat, trialDays }: { plans: PublicPlan[]; features: PlanFeature[]; vat: number; trialDays: number }) {
  const [yearly, setYearly] = useState(false);
  const popular = plans.find((p) => p.key === 'pro')?.key ?? plans[Math.floor(plans.length / 2)]?.key;
  const shown = features.filter((f) => !HIDDEN.includes(f.key));

  return (
    <div className="flex flex-col items-center gap-8">
      <div role="group" aria-label="دوره‌ی پرداخت" className="flex items-center gap-1 rounded-full bg-surface-muted p-1 text-sm">
        {[false, true].map((y) => (
          <button key={String(y)} type="button" aria-pressed={yearly === y} onClick={() => setYearly(y)}
            className={cx('inline-flex h-10 items-center gap-1.5 rounded-full px-5 transition-colors', yearly === y ? 'bg-surface font-semibold shadow-[var(--shadow-sm)]' : 'text-text-muted')}>
            {y ? 'سالانه' : 'ماهانه'}
            {y ? <span className="rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-semibold text-success">۲ ماه رایگان</span> : null}
          </button>
        ))}
      </div>

      <div className="grid w-full gap-5 lg:grid-cols-3">
        {plans.map((p, i) => {
          const featured = p.key === popular;
          const price = yearly ? p.yearly_price : p.monthly_price;

          return (
            <article key={p.key} data-reveal style={{ '--i': i } as CSSProperties}
              className={cx('l-card relative flex flex-col gap-5 border p-6', featured ? 'border-brand bg-surface shadow-[var(--shadow-lg)] lg:-my-3 lg:py-9' : 'border-border bg-surface/80')}>
              {featured ? <span className="absolute -top-3 start-6 inline-flex items-center gap-1 rounded-full bg-brand px-3 py-1 text-xs font-bold text-on-brand"><Sparkles className="size-3.5" aria-hidden="true" />پرطرفدار</span> : null}
              <div>
                <h3 className="text-xl font-black">{p.name}</h3>
                {p.tagline ? <p className="mt-1 text-sm leading-6 text-text-muted">{p.tagline}</p> : null}
              </div>
              <p className="flex items-baseline gap-1.5">
                <span className="tabular text-3xl font-black">{formatMoney(price)}</span>
                <span className="text-sm text-text-muted">/ {yearly ? 'سال' : 'ماه'}</span>
              </p>
              <ul className="flex flex-1 flex-col gap-2.5 text-sm">
                {shown.map((f) => {
                  const v = p.features[f.key];
                  if (f.type === 'limit') {
                    return <li key={f.key} className="flex items-center gap-2"><Check className="size-4 shrink-0 text-success" aria-hidden="true" />{v === null || v === undefined ? UNLIMITED[f.key] ?? f.label : (LIMIT_TEXT[f.key]?.(Number(v)) ?? `${f.label}: ${formatNumber(Number(v))}`)}</li>;
                  }

                  return (
                    <li key={f.key} className={cx('flex items-center gap-2', !v && 'text-text-subtle')}>
                      {v ? <Check className="size-4 shrink-0 text-success" aria-hidden="true" /> : <Minus className="size-4 shrink-0" aria-hidden="true" />}
                      <span className={cx(!v && 'line-through decoration-text-subtle/40')}>{f.label}</span>
                      <span className="sr-only">{v ? '(دارد)' : '(ندارد)'}</span>
                    </li>
                  );
                })}
              </ul>
              <Link href="/signup" className={cx('flex h-12 items-center justify-center rounded-2xl font-bold transition-colors', featured ? 'bg-brand text-on-brand hover:bg-brand-strong' : 'border border-border-strong hover:border-brand hover:text-brand')}>
                {formatNumber(trialDays)} روز رایگان امتحان کنید
              </Link>
            </article>
          );
        })}
      </div>
      <p className="text-center text-xs leading-6 text-text-subtle">قیمت‌ها به تومان و بدون {formatNumber(vat)}٪ مالیات بر ارزش افزوده است. دوره‌ی آزمایشی با همه‌ی امکانات «حرفه‌ای» است و کارت بانکی نمی‌خواهد.</p>
    </div>
  );
}
