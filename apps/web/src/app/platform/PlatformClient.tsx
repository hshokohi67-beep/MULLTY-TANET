'use client';

import { useState, useTransition } from 'react';
import { CalendarPlus, Gift, Landmark, Save, X } from 'lucide-react';
import { Badge, Button, Card, Checkbox, Dialog, EmptyState, SelectField, TextField, type Tone } from '@cafe/ui';
import { formatJalaliDate, formatMoney, toPersianDigits, toLatinDigits } from '@cafe/locale';
import { extendSubscription, markInvoicePaid, removeOverride, setOverride, updatePlan } from '@/app/actions/platform';
import { JalaliDateField } from '@/components/JalaliDateField';
import type { FeatureDef, Features, Plan } from '@/lib/billing-types';

export interface PlatformRow {
  tenant: { id: string; name: string; slug: string; status: string; created_at: string | null };
  subscription: { plan: { key: string; name: string }; cycle: string; status: string; state: string; state_label: string; ends_at: string | null; addons: { name: string; quantity: number }[] } | null;
  open_invoices: { id: string; number: string; total: number; kind: string }[];
  overrides: { feature: string; value: boolean | number | null; reason: string; expires_at: string | null }[];
}

const TONE: Record<string, Tone> = { trial: 'info', active: 'success', grace: 'warning', read_only: 'danger' };
type Action = { kind: 'extend' | 'grant'; row: PlatformRow } | { kind: 'paid'; row: PlatformRow; invoice: PlatformRow['open_invoices'][number] };

export function TenantTable({ rows, features }: { rows: PlatformRow[]; features: FeatureDef[] }) {
  const [action, setAction] = useState<Action | null>(null);
  const [pending, start] = useTransition();
  const label = (key: string) => features.find((f) => f.key === key)?.label ?? key;
  const value = (v: boolean | number | null) => (v === true ? 'روشن' : v === false ? 'خاموش' : v === null ? 'نامحدود' : toPersianDigits(v));

  if (rows.length === 0) return <Card><EmptyState icon={<Landmark />} title="هنوز کافه‌ای ثبت نشده" /></Card>;

  return (
    <Card>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[56rem] text-sm">
          <thead className="bg-surface-muted/60 text-xs text-text-muted">
            <tr>
              <th className="px-4 py-2.5 text-start font-medium">کافه</th>
              <th className="px-4 py-2.5 text-start font-medium">پلن</th>
              <th className="px-4 py-2.5 text-start font-medium">وضعیت</th>
              <th className="px-4 py-2.5 text-start font-medium">پایان</th>
              <th className="px-4 py-2.5 text-start font-medium">امکان ویژه</th>
              <th className="px-4 py-2.5 text-end font-medium">کارها</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {rows.map((r) => (
              <tr key={r.tenant.id} className="align-top">
                <td className="px-4 py-3">
                  <p className="font-medium">{r.tenant.name}</p>
                  <p className="text-xs text-text-muted" dir="ltr" style={{ textAlign: 'end' }}>{r.tenant.slug}</p>
                </td>
                <td className="px-4 py-3">
                  {r.subscription ? <>{r.subscription.plan.name}<span className="text-xs text-text-muted"> • {r.subscription.status === 'trialing' ? 'آزمایشی' : r.subscription.cycle === 'yearly' ? 'سالانه' : 'ماهانه'}</span></> : '—'}
                  {r.subscription?.addons.length ? <p className="text-xs text-text-muted">{r.subscription.addons.map((a) => a.name).join('، ')}</p> : null}
                </td>
                <td className="px-4 py-3">{r.subscription ? <Badge tone={TONE[r.subscription.state] ?? 'neutral'} dot>{r.subscription.status === 'cancelled' ? 'لغوشده' : r.subscription.state_label}</Badge> : null}</td>
                <td className="px-4 py-3 text-text-muted">{r.subscription?.ends_at ? formatJalaliDate(r.subscription.ends_at) : '—'}</td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap gap-1">
                    {r.overrides.map((o) => (
                      <span key={o.feature} title={o.reason} className="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">
                        {label(o.feature)}: {value(o.value)}{o.expires_at ? ` تا ${formatJalaliDate(o.expires_at)}` : ''}
                        <button type="button" disabled={pending} onClick={() => start(async () => { await removeOverride(r.tenant.id, o.feature); })} aria-label={`حذف ${label(o.feature)}`} className="rounded-full hover:bg-surface"><X className="size-3" /></button>
                      </span>
                    ))}
                  </div>
                </td>
                <td className="px-4 py-3">
                  <div className="flex flex-wrap justify-end gap-1">
                    {r.open_invoices.map((i) => (
                      <Button key={i.id} size="sm" variant="secondary" icon={<Landmark />} onClick={() => setAction({ kind: 'paid', row: r, invoice: i })}>حواله {toPersianDigits(i.number)}</Button>
                    ))}
                    <Button size="sm" variant="ghost" icon={<CalendarPlus />} onClick={() => setAction({ kind: 'extend', row: r })}>تمدید</Button>
                    <Button size="sm" variant="ghost" icon={<Gift />} onClick={() => setAction({ kind: 'grant', row: r })}>امکان ویژه</Button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Dialog open={action !== null} onClose={() => setAction(null)} size="sm"
        title={action ? `${action.kind === 'extend' ? 'تمدید دستی' : action.kind === 'grant' ? 'امکان ویژه' : 'تأیید حواله'} • ${action.row.tenant.name}` : ''}>
        {action?.kind === 'extend' ? <ExtendForm tenantId={action.row.tenant.id} onDone={() => setAction(null)} /> : null}
        {action?.kind === 'grant' ? <GrantForm tenantId={action.row.tenant.id} features={features} onDone={() => setAction(null)} /> : null}
        {action?.kind === 'paid' ? <PaidForm tenantId={action.row.tenant.id} invoice={action.invoice} onDone={() => setAction(null)} /> : null}
      </Dialog>
    </Card>
  );
}

function useSubmit(onDone: () => void) {
  const [pending, start] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const submit = (fn: () => Promise<{ ok: boolean; message?: string }>) => start(async () => {
    const r = await fn();
    if (r.ok) onDone(); else setError(r.message ?? 'انجام نشد.');
  });

  return { pending, error, submit };
}

function ExtendForm({ tenantId, onDone }: { tenantId: string; onDone: () => void }) {
  const [days, setDays] = useState('7');
  const [reason, setReason] = useState('');
  const { pending, error, submit } = useSubmit(onDone);

  return (
    <form className="flex flex-col gap-4" onSubmit={(e) => { e.preventDefault(); submit(() => extendSubscription(tenantId, Number(toLatinDigits(days)), reason)); }}>
      <TextField label="چند روز" value={days} onChange={(e) => setDays(e.target.value)} inputMode="numeric" ltr required />
      <TextField label="دلیل" value={reason} onChange={(e) => setReason(e.target.value)} required maxLength={200} placeholder="مثلاً قطعی درگاه، راه‌اندازی دیرهنگام" />
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
      <Button type="submit" loading={pending}>ثبت تمدید</Button>
    </form>
  );
}

function GrantForm({ tenantId, features, onDone }: { tenantId: string; features: FeatureDef[]; onDone: () => void }) {
  const [feature, setFeature] = useState(features[0]?.key ?? '');
  const [on, setOn] = useState(true);
  const [limit, setLimit] = useState('');
  const [reason, setReason] = useState('');
  const [expires, setExpires] = useState(false);
  const { pending, error, submit } = useSubmit(onDone);
  const def = features.find((f) => f.key === feature);

  return (
    <form className="flex flex-col gap-4" onSubmit={(e) => {
      e.preventDefault();
      const fd = new FormData(e.currentTarget);
      const v = def?.type === 'switch' ? on : limit.trim() === '' ? null : Number(toLatinDigits(limit));
      submit(() => setOverride(tenantId, feature, v, reason, expires ? `${String(fd.get('expires'))}T23:59:00+03:30` : null));
    }}>
      <SelectField label="امکان" value={feature} onChange={(e) => setFeature(e.target.value)}>
        {features.map((f) => <option key={f.key} value={f.key}>{f.label}</option>)}
      </SelectField>
      {def?.type === 'switch'
        ? <Checkbox checked={on} onChange={(e) => setOn(e.target.checked)} label="روشن باشد" />
        : <TextField label="سقف (خالی = نامحدود)" value={limit} onChange={(e) => setLimit(e.target.value)} inputMode="numeric" ltr />}
      <TextField label="دلیل" value={reason} onChange={(e) => setReason(e.target.value)} required maxLength={200} />
      <Checkbox checked={expires} onChange={(e) => setExpires(e.target.checked)} label="تاریخ انقضا دارد" />
      {expires ? <JalaliDateField label="تا تاریخ" name="expires" years={2} /> : null}
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
      <Button type="submit" loading={pending}>ثبت</Button>
    </form>
  );
}

function PaidForm({ tenantId, invoice, onDone }: { tenantId: string; invoice: PlatformRow['open_invoices'][number]; onDone: () => void }) {
  const [reference, setReference] = useState('');
  const { pending, error, submit } = useSubmit(onDone);

  return (
    <form className="flex flex-col gap-4" onSubmit={(e) => { e.preventDefault(); submit(() => markInvoicePaid(tenantId, invoice.id, reference)); }}>
      <p className="rounded-lg bg-surface-muted px-3 py-2 text-sm">صورت‌حساب {toPersianDigits(invoice.number)} • {formatMoney(invoice.total)}</p>
      <TextField label="شماره‌ی پیگیری حواله" value={reference} onChange={(e) => setReference(e.target.value)} required maxLength={100} />
      {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
      <Button type="submit" loading={pending}>تأیید پرداخت و فعال‌سازی</Button>
    </form>
  );
}

/** One plan's prices, limits and switches. */
export function PlanEditor({ plan, features }: { plan: Plan; features: FeatureDef[] }) {
  const [name, setName] = useState(plan.name);
  const [tagline, setTagline] = useState(plan.tagline ?? '');
  const [monthly, setMonthly] = useState(String(plan.monthly_price / 10));
  const [yearly, setYearly] = useState(String(plan.yearly_price / 10));
  const [isPublic, setIsPublic] = useState(plan.is_public);
  const [values, setValues] = useState<Features>(plan.features);
  const [saved, setSaved] = useState(false);
  const { pending, error, submit } = useSubmit(() => setSaved(true));
  const toman = (v: string) => Number(toLatinDigits(v).replace(/[٬,]/g, '')) * 10;

  return (
    <Card>
      <form className="flex flex-col gap-4 p-5" onSubmit={(e) => {
        e.preventDefault();
        setSaved(false);
        submit(() => updatePlan(plan.id, { name, tagline: tagline || null, monthly_price: toman(monthly), yearly_price: toman(yearly), is_public: isPublic, features: values }));
      }}>
        <div className="flex items-center gap-2">
          <h2 className="text-lg font-bold">{plan.name}</h2>
          <span className="text-xs text-text-muted" dir="ltr">{plan.key}</span>
          {plan.is_trial_plan ? <Badge tone="info">پلن دوره‌ی آزمایشی</Badge> : null}
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <TextField label="نام" value={name} onChange={(e) => setName(e.target.value)} required />
          <TextField label="شعار" value={tagline} onChange={(e) => setTagline(e.target.value)} />
          <TextField label="ماهانه (تومان)" value={monthly} onChange={(e) => setMonthly(e.target.value)} inputMode="numeric" ltr />
          <TextField label="سالانه (تومان)" value={yearly} onChange={(e) => setYearly(e.target.value)} inputMode="numeric" ltr />
        </div>
        <fieldset className="rounded-xl bg-surface-muted p-4">
          <legend className="px-1 text-sm font-semibold">امکانات</legend>
          <div className="grid gap-2 sm:grid-cols-3">
            {features.filter((f) => f.type === 'switch').map((f) => (
              <Checkbox key={f.key} checked={values[f.key] === true} onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.checked }))} label={f.label} />
            ))}
          </div>
        </fieldset>
        <fieldset className="grid gap-3 sm:grid-cols-4">
          <legend className="mb-2 text-sm font-semibold">سقف‌ها (خالی = نامحدود)</legend>
          {features.filter((f) => f.type === 'limit').map((f) => (
            <TextField key={f.key} label={f.label} inputMode="numeric" ltr
              value={values[f.key] === null || values[f.key] === undefined ? '' : String(values[f.key])}
              onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value.trim() === '' ? null : Number(toLatinDigits(e.target.value)) }))} />
          ))}
        </fieldset>
        <Checkbox checked={isPublic} onChange={(e) => setIsPublic(e.target.checked)} label="در صفحه‌ی پلن‌ها نمایش داده شود" />
        {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}
        {saved ? <p role="status" className="text-sm text-success">ذخیره شد.</p> : null}
        <Button type="submit" icon={<Save />} loading={pending} className="self-start">ذخیره</Button>
      </form>
    </Card>
  );
}
