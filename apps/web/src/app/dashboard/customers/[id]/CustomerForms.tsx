'use client';

import { useActionState, useState } from 'react';
import { Button, Card, CardHeader, Checkbox, SelectField, TextAreaField, TextField } from '@cafe/ui';
import { JALALI_MONTHS, jalaliMonthDays, toPersianDigits } from '@cafe/locale';
import { adjustPoints, adjustWallet, updateCustomer } from '@/app/actions/club';
import { FormStatus } from '@/components/FormStatus';
import { useSubmissionKey } from '@/components/useSubmissionKey';
import type { CustomerDetail, FormState } from '@/lib/types';

export function CustomerProfileForm({ customer, readOnly }: { customer: CustomerDetail; readOnly: boolean }) {
  const [state, action, pending] = useActionState<FormState, FormData>(updateCustomer.bind(null, customer.id), { ok: false });
  const [month, setMonth] = useState(customer.birth_month ?? 0);
  const e = state.errors ?? {};

  return (
    <Card>
      <CardHeader title="اطلاعات مشتری" />
      <form action={action} className="flex flex-col gap-4 p-5">
        <FormStatus state={state} />
        <fieldset disabled={readOnly || pending} className="grid gap-4 sm:grid-cols-2">
          <TextField label="نام" name="name" defaultValue={customer.name ?? ''} error={e.name} />
          <div className="grid grid-cols-2 gap-2">
            <SelectField label="ماه تولد" name="birth_month" value={String(month)} onChange={(ev) => setMonth(Number(ev.target.value))} error={e.birth_month}>
              <option value="0">—</option>
              {JALALI_MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
            </SelectField>
            <SelectField label="روز" name="birth_day" defaultValue={String(customer.birth_day ?? '')} disabled={month === 0} error={e.birth_day}>
              <option value="">—</option>
              {Array.from({ length: month ? jalaliMonthDays(month) : 31 }, (_, i) => i + 1).map((d) => <option key={d} value={d}>{toPersianDigits(d)}</option>)}
            </SelectField>
          </div>
          <div className="sm:col-span-2">
            <TextAreaField label="یادداشت کارکنان" name="staff_note" defaultValue={customer.staff_note ?? ''} maxLength={500} hint="فقط کارکنان کافه این یادداشت را می‌بینند؛ مثلاً «قهوه را بدون شکر می‌خورد»." />
          </div>
          <Checkbox label="دریافت پیامک‌های تبلیغاتی" name="marketing_opt_in" defaultChecked={customer.marketing_opt_in} />
        </fieldset>
        {customer.birthday_locked ? <p className="text-xs text-text-muted">تاریخ تولد را خود مشتری ثبت کرده است؛ تغییر آن فقط از همین‌جا ممکن است.</p> : null}
        {!readOnly ? <div><Button type="submit" loading={pending}>ذخیره</Button></div> : null}
      </form>
    </Card>
  );
}

/** Manual wallet or points delta with a mandatory reason. A leading minus deducts. */
export function AdjustmentForm({ customerId, kind }: { customerId: string; kind: 'wallet' | 'points' }) {
  const [key, renew] = useSubmissionKey();
  const [state, action, pending] = useActionState<FormState, FormData>(async (prev, formData) => {
    const result = await (kind === 'wallet' ? adjustWallet : adjustPoints)(customerId, prev, formData);
    if (result.ok) renew();

    return result;
  }, { ok: false });
  const e = state.errors ?? {};

  return (
    <form key={key} action={action} className="flex flex-col gap-3 rounded-md border border-border p-4">
      <p className="text-sm font-semibold">{kind === 'wallet' ? 'افزایش یا کاهش کیف پول' : 'افزایش یا کاهش امتیاز'}</p>
      <FormStatus state={state} />
      <input type="hidden" name="idempotency_key" value={key} />
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField
          label={kind === 'wallet' ? 'مبلغ (تومان)' : 'تعداد امتیاز'}
          name="amount"
          inputMode="numeric"
          ltr
          required
          error={e.amount}
          hint={kind === 'wallet' ? 'به تومان؛ مثلاً ۵۰۰۰۰ برای افزودن و -۵۰۰۰۰ برای کسر' : 'منفی برای کسر، مثلاً -۲۰'}
        />
        <TextField label="دلیل" name="reason" required error={e.reason} placeholder="مثلاً جبران تأخیر سفارش" />
      </div>
      <div><Button type="submit" size="sm" variant="secondary" loading={pending}>ثبت</Button></div>
    </form>
  );
}
