'use client';

import { useActionState, useState, useTransition } from 'react';
import { Badge, Button, Card, CardHeader, Checkbox, ClockSelect, Ltr, SelectField, TextField } from '@cafe/ui';
import { formatMoney, formatNumber, formatPercent, IRANIAN_WEEK, toPersianDigits, WEEKDAY_LABELS } from '@cafe/locale';
import { deleteDiscount, saveDiscount } from '@/app/actions/commerce';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { rialToTomanInput } from '@/lib/money';
import type { Discount, FormState } from '@/lib/types';

function summary(d: Discount): string {
  const amount = d.kind === 'percent' ? formatPercent(d.value / 10000, 1) : formatMoney(d.value);
  const parts = [`${amount} تخفیف`];
  if (d.min_order > 0) parts.push(`برای خرید بالای ${formatMoney(d.min_order)}`);
  if (d.max_discount) parts.push(`حداکثر ${formatMoney(d.max_discount)}`);
  if (d.schedule?.from && d.schedule.to) parts.push(`ساعت ${toPersianDigits(d.schedule.from)} تا ${toPersianDigits(d.schedule.to)}`);
  if (d.schedule?.weekdays?.length) parts.push(IRANIAN_WEEK.filter((w) => d.schedule?.weekdays?.includes(w)).map((w) => WEEKDAY_LABELS[w]).join('، '));

  return parts.join(' • ');
}

export function DiscountEditor({ discount, tiers = [] }: { discount?: Discount; tiers?: { id: string; name: string }[] }) {
  const [open, setOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [state, action, pending] = useActionState<FormState, FormData>(saveDiscount.bind(null, discount?.id ?? null), { ok: false });
  const [kind, setKind] = useState<'percent' | 'fixed'>(discount?.kind ?? 'percent');
  const [timed, setTimed] = useState(Boolean(discount?.schedule?.from));
  const [from, setFrom] = useState(discount?.schedule?.from ?? '15:00');
  const [to, setTo] = useState(discount?.schedule?.to ?? '18:00');
  const [deleting, startDelete] = useTransition();
  const e = state.errors ?? {};

  if (!discount && !creating) {
    return <div><Button onClick={() => setCreating(true)}>تخفیف جدید</Button></div>;
  }

  if (discount && !open) {
    return (
      <Card>
        <CardHeader
          title={discount.name}
          description={summary(discount)}
          actions={<>
            {discount.code ? <Badge tone="brand"><Ltr>{discount.code}</Ltr></Badge> : <Badge tone="info">خودکار</Badge>}
            {discount.rules?.some((r) => r.type === 'tier') ? <Badge tone="warning">ویژه‌ی {tiers.find((t) => t.id === discount.rules?.find((r) => r.type === 'tier')?.target)?.name ?? 'سطح'}</Badge> : null}
            {!discount.is_active ? <Badge>غیرفعال</Badge> : null}
            <span className="text-xs text-text-muted">{formatNumber(discount.used_count)} بار استفاده</span>
            <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>ویرایش</Button>
          </>}
        />
      </Card>
    );
  }

  const defaultValue = discount ? (discount.kind === 'percent' ? toPersianDigits(String(discount.value / 100)) : rialToTomanInput(discount.value)) : '';

  return (
    <Card>
      <CardHeader title={discount ? `ویرایش «${discount.name}»` : 'تخفیف جدید'} />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <div className="grid gap-4 sm:grid-cols-3">
          <TextField label="نام" name="name" defaultValue={discount?.name} required placeholder="مثلاً ساعت خوش" error={e.name} />
          <TextField label="کد تخفیف" name="code" defaultValue={discount?.code ?? ''} ltr placeholder="خالی = خودکار" hint="فقط حروف انگلیسی و عدد، مثل YALDA" error={e.code} />
          <SelectField label="نوع" name="kind" value={kind} onChange={(ev) => setKind(ev.target.value as 'percent' | 'fixed')} error={e.kind}>
            <option value="percent">درصدی</option>
            <option value="fixed">مبلغ ثابت</option>
          </SelectField>
          {kind === 'percent' ? (
            <TextField key="percent" label="درصد" name="value" defaultValue={discount?.kind === 'percent' ? defaultValue : ''} inputMode="decimal" ltr required error={e.value} />
          ) : (
            <MoneyField key="fixed" label="مبلغ (تومان)" name="value" defaultValue={discount?.kind === 'fixed' ? defaultValue : ''} required error={e.value} />
          )}
          <MoneyField label="حداقل خرید (تومان)" name="min_order" defaultValue={rialToTomanInput(discount?.min_order ?? 0)} error={e.min_order} />
          {kind === 'percent' ? (
            <MoneyField label="سقف تخفیف (تومان)" name="max_discount" defaultValue={rialToTomanInput(discount?.max_discount)} hint="خالی = بدون سقف" error={e.max_discount} />
          ) : <span />}
          <TextField label="سقف کل استفاده" name="usage_limit" defaultValue={discount?.usage_limit != null ? String(discount.usage_limit) : ''} inputMode="numeric" ltr hint="خالی = نامحدود" error={e.usage_limit} />
          <TextField label="سقف استفاده‌ی هر مشتری" name="per_customer_limit" defaultValue={discount?.per_customer_limit != null ? String(discount.per_customer_limit) : ''} inputMode="numeric" ltr hint="نیاز به ورود مشتری دارد" error={e.per_customer_limit} />
        </div>

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-sm font-medium">روزهای فعال</legend>
          <div className="flex flex-wrap gap-2">
            {IRANIAN_WEEK.map((w) => (
              <label key={w} className="flex items-center gap-1.5 rounded-md border border-border px-2.5 py-1 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                <input type="checkbox" name="weekdays" value={w} defaultChecked={discount?.schedule?.weekdays?.includes(w)} className="accent-[var(--color-brand)]" />
                {WEEKDAY_LABELS[w]}
              </label>
            ))}
          </div>
          <p className="text-xs text-text-muted">هیچ روزی انتخاب نشود یعنی همه‌ی روزها.</p>
        </fieldset>

        <div className="flex flex-wrap items-center gap-3">
          <Checkbox label="فقط در ساعات مشخص" name="timed" checked={timed} onChange={(ev) => setTimed(ev.target.checked)} />
          {timed ? (
            <div className="flex items-center gap-2 rounded-md border border-border px-2 py-1 text-sm">
              <ClockSelect label="ساعت شروع" value={from} onChange={setFrom} minuteStep={15} />
              <span aria-hidden="true">تا</span>
              <ClockSelect label="ساعت پایان" value={to} onChange={setTo} minuteStep={15} />
              <input type="hidden" name="from" value={from} />
              <input type="hidden" name="to" value={to} />
            </div>
          ) : null}
        </div>

        <input type="hidden" name="rules_json" value={JSON.stringify(discount?.rules ?? [])} />
        {tiers.length > 0 ? (
          <div className="max-w-xs">
            <SelectField label="فقط برای سطح باشگاه" name="tier_id" defaultValue={discount?.rules?.find((r) => r.type === 'tier')?.target ?? ''}>
              <option value="">همه‌ی مشتریان</option>
              {tiers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </SelectField>
          </div>
        ) : null}

        <Checkbox label="فعال" name="is_active" defaultChecked={discount?.is_active ?? true} />

        <div className="flex gap-2">
          <Button type="submit" loading={pending}>ذخیره</Button>
          <Button variant="ghost" onClick={() => (discount ? setOpen(false) : setCreating(false))}>بستن</Button>
          {discount ? (
            <Button variant="ghost" loading={deleting} onClick={() => startDelete(() => deleteDiscount(discount.id))}>
              {discount.used_count > 0 ? 'غیرفعال کردن' : 'حذف'}
            </Button>
          ) : null}
        </div>
      </form>
    </Card>
  );
}
