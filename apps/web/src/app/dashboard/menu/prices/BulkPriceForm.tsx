'use client';

import { useActionState, useState } from 'react';
import { Alert, Button, Card, CardHeader, SelectField, TextField } from '@cafe/ui';
import { formatMoney, formatNumber } from '@cafe/locale';
import { bulkPrices, type BulkState } from '@/app/actions/catalog';
import type { Branch, Category } from '@/lib/types';

const OPERATIONS = [
  { value: 'percent_increase', label: 'افزایش درصدی' },
  { value: 'percent_decrease', label: 'کاهش درصدی' },
  { value: 'fixed_increase', label: 'افزایش مبلغ ثابت' },
  { value: 'fixed_decrease', label: 'کاهش مبلغ ثابت' },
  { value: 'exact', label: 'قیمت دقیق' },
];

// Rial values; labels in toman.
const ROUNDING = [
  { value: '0', label: 'بدون گرد کردن' },
  { value: '10000', label: 'به نزدیک‌ترین ۱٬۰۰۰ تومان' },
  { value: '50000', label: 'به نزدیک‌ترین ۵٬۰۰۰ تومان' },
  { value: '100000', label: 'به نزدیک‌ترین ۱۰٬۰۰۰ تومان' },
];

export function BulkPriceForm({ categories, branches }: { categories: Category[]; branches: Branch[] }) {
  const [state, action, pending] = useActionState<BulkState, FormData>(bulkPrices, { ok: false });
  const [scope, setScope] = useState<'all' | 'categories'>('all');
  const [operation, setOperation] = useState('percent_increase');
  const isPercent = operation.startsWith('percent_');
  const result = state.result;
  const e = state.errors ?? {};
  // Any edit after a preview hides "apply": what gets applied must be what was previewed.
  const [stale, setStale] = useState(false);
  const [seenResult, setSeenResult] = useState(result);
  if (result !== seenResult) {
    setSeenResult(result);
    setStale(false);
  }

  return (
    <div className="flex flex-col gap-5">
      <Card>
        <CardHeader title="تنظیم تغییر" />
        <form action={action} onChange={() => setStale(true)} className="flex flex-col gap-4 p-5">
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-1 text-sm font-medium">روی کدام آیتم‌ها؟</legend>
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" name="scope" value="all" checked={scope === 'all'} onChange={() => setScope('all')} className="accent-[var(--color-brand)]" />
              همه‌ی منو
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" name="scope" value="categories" checked={scope === 'categories'} onChange={() => setScope('categories')} className="accent-[var(--color-brand)]" />
              دسته‌بندی‌های انتخابی (با زیرمجموعه‌ها)
            </label>
            {scope === 'categories' ? (
              <div className="flex flex-wrap gap-2 ps-6">
                {categories.map((c) => (
                  <label key={c.id} className="flex items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                    <input type="checkbox" name="category_ids" value={c.id} className="accent-[var(--color-brand)]" />
                    {c.name}
                  </label>
                ))}
              </div>
            ) : null}
            {e.target ? <p role="alert" className="text-xs text-danger">{e.target}</p> : null}
          </fieldset>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <SelectField label="نوع تغییر" name="operation" value={operation} onChange={(ev) => setOperation(ev.target.value)} error={e.operation}>
              {OPERATIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </SelectField>
            <TextField
              label={isPercent ? 'درصد' : 'مبلغ (تومان)'}
              name="value"
              inputMode="decimal"
              ltr
              required
              error={e.value}
              placeholder={isPercent ? '۱۰' : '۵۰۰۰'}
            />
            <SelectField label="گرد کردن نتیجه" name="round_to" defaultValue="10000" error={e.round_to}>
              {ROUNDING.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
            </SelectField>
            <SelectField label="قیمت‌ها" name="branch_id" defaultValue="" error={e.branch_id} hint="تغییر قیمت یک شعبه، یک قیمت اختصاصی برای همان شعبه می‌سازد.">
              <option value="">قیمت پایه (همه‌ی شعبه‌ها)</option>
              {branches.map((b) => <option key={b.id} value={b.id}>فقط شعبه‌ی {b.name}</option>)}
            </SelectField>
          </div>

          {state.message && !result ? <Alert tone={state.ok ? 'success' : 'danger'}>{state.message}</Alert> : null}

          <div className="flex flex-wrap gap-2">
            <Button type="submit" name="intent" value="preview" variant="secondary" loading={pending}>پیش‌نمایش</Button>
            {result?.preview && result.changed_count > 0 && !stale ? (
              <Button type="submit" name="intent" value="apply" loading={pending}>
                اعمال روی {formatNumber(result.changed_count)} قیمت
              </Button>
            ) : null}
            {result?.preview && stale ? <p className="self-center text-xs text-text-muted">تنظیمات تغییر کرد؛ دوباره پیش‌نمایش بگیرید.</p> : null}
          </div>
        </form>
      </Card>

      {result ? (
        <Card>
          <CardHeader
            title={result.preview ? 'پیش‌نمایش (هنوز ذخیره نشده)' : 'اعمال شد'}
            description={result.changed_count === 0 ? 'هیچ قیمتی تغییر نمی‌کند.' : `${formatNumber(result.changed_count)} قیمت تغییر ${result.preview ? 'می‌کند' : 'کرد'}.`}
          />
          {!result.preview && state.message ? <div className="px-5 pt-4"><Alert tone="success">{state.message}</Alert></div> : null}
          {result.rows.length > 0 ? (
            <div className="max-h-[28rem] overflow-auto">
              <table className="w-full text-sm">
                <caption className="sr-only">فهرست تغییر قیمت‌ها</caption>
                <thead className="sticky top-0 bg-surface-muted text-text-muted">
                  <tr>
                    <th scope="col" className="px-5 py-2 text-start font-medium">آیتم</th>
                    <th scope="col" className="px-5 py-2 text-start font-medium">قیمت فعلی</th>
                    <th scope="col" className="px-5 py-2 text-start font-medium">قیمت جدید</th>
                    <th scope="col" className="px-5 py-2 text-start font-medium">تغییر</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {result.rows.map((row) => {
                    const diff = row.new_amount - row.old_amount;

                    return (
                      <tr key={row.variant_id}>
                        <td className="px-5 py-2">{row.product}{row.variant ? <span className="text-text-muted"> • {row.variant}</span> : null}</td>
                        <td className="px-5 py-2 whitespace-nowrap">{formatMoney(row.old_amount)}</td>
                        <td className="px-5 py-2 whitespace-nowrap font-medium">{formatMoney(row.new_amount)}</td>
                        <td className={`px-5 py-2 whitespace-nowrap ${diff > 0 ? 'text-danger' : 'text-success'}`}>
                          {diff > 0 ? '+' : '−'}{formatMoney(Math.abs(diff))}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : null}
        </Card>
      ) : null}
    </div>
  );
}
