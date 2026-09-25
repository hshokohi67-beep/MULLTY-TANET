'use client';

import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { Alert, Button, Card, cx, SelectField } from '@cafe/ui';
import { toLatinDigits } from '@cafe/locale';
import { submitCount } from '@/app/actions/inventory';
import { useSubmissionKey } from '@/components/useSubmissionKey';
import { entryUnits, formatQty, type Ingredient } from '@/lib/inventory-types';

/** A counting sheet: book quantity next to an input; only filled rows are sent, and the difference shows live. */
export function StockCount({ ingredients, branches }: { ingredients: Ingredient[]; branches: { id: string; name: string }[] }) {
  const router = useRouter();
  const [branchId, setBranchId] = useState(branches[0]?.id ?? '');
  const [values, setValues] = useState<Record<string, { counted: string; unit: string }>>({});
  const [result, setResult] = useState<{ ok: boolean; message?: string } | null>(null);
  const [pending, start] = useTransition();
  const [key, renewKey] = useSubmissionKey();

  const book = (i: Ingredient) => i.stocks.find((s) => s.branch_id === branchId)?.quantity ?? 0;
  const factor = (i: Ingredient, unit: string) => (unit === 'kg' || unit === 'l' ? 1000 : unit === 'pack' ? i.pack_size ?? 1 : 1);
  const counted = (i: Ingredient) => {
    const v = values[i.id];
    if (!v || v.counted.trim() === '') return null;
    const n = Number(toLatinDigits(v.counted).replace('٫', '.'));
    return Number.isFinite(n) ? n * factor(i, v.unit) : null;
  };
  const filled = ingredients.filter((i) => counted(i) !== null);

  const submit = () => start(async () => {
    const r = await submitCount(key, branchId, filled.map((i) => ({ ingredient_id: i.id, counted: Number(toLatinDigits(values[i.id].counted).replace('٫', '.')), unit: values[i.id].unit })));
    setResult(r);
    renewKey();
    if (r.ok) { setValues({}); router.refresh(); }
  });

  return (
    <div className="flex flex-col gap-4">
      {branches.length > 1 ? (
        <div className="w-56"><SelectField label="شعبه" value={branchId} onChange={(e) => { setBranchId(e.target.value); setValues({}); }}>{branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</SelectField></div>
      ) : null}
      {result ? <Alert tone={result.ok ? 'success' : 'danger'}>{result.message}</Alert> : null}
      <Card>
        <ul className="divide-y divide-border">
          {ingredients.map((i) => {
            const units = entryUnits(i.unit, { label: i.pack_label, size: i.pack_size });
            const unit = values[i.id]?.unit ?? units[0].value;
            const c = counted(i);
            const diff = c === null ? null : Math.round((c - book(i)) * 1000) / 1000;

            return (
              <li key={i.id} className="grid grid-cols-[1fr_auto] items-center gap-3 px-4 py-3 sm:grid-cols-[1fr_9rem_auto_8rem]">
                <div>
                  <p className="font-medium">{i.name}</p>
                  <p className="text-xs text-text-muted">در دفتر: {formatQty(book(i), i.unit)}</p>
                </div>
                <label className="sr-only" htmlFor={`count-${i.id}`}>شمارش {i.name}</label>
                <input id={`count-${i.id}`} inputMode="decimal" dir="ltr" value={values[i.id]?.counted ?? ''} placeholder="—"
                  onChange={(e) => setValues((v) => ({ ...v, [i.id]: { counted: e.target.value, unit } }))}
                  className="h-10 w-full rounded-lg border border-border-strong bg-surface px-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
                <select aria-label="واحد" value={unit} onChange={(e) => setValues((v) => ({ ...v, [i.id]: { counted: v[i.id]?.counted ?? '', unit: e.target.value } }))}
                  className="h-10 rounded-lg border border-border-strong bg-surface px-2 text-sm">
                  {units.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                </select>
                <span className={cx('tabular text-sm', diff === null || Math.abs(diff) < 0.001 ? 'text-text-subtle' : diff < 0 ? 'text-danger' : 'text-success')}>
                  {diff === null ? '' : Math.abs(diff) < 0.001 ? 'بدون اختلاف' : `${diff > 0 ? '+' : ''}${formatQty(diff, i.unit)}`}
                </span>
              </li>
            );
          })}
        </ul>
      </Card>
      <div className="sticky bottom-4 flex items-center gap-3 self-start rounded-2xl border border-border bg-surface p-2 ps-4 shadow-[var(--shadow-lg)]">
        <span className="text-sm text-text-muted">{new Intl.NumberFormat('fa-IR').format(filled.length)} قلم شمرده شده</span>
        <Button onClick={submit} loading={pending} disabled={filled.length === 0}>ثبت انبارگردانی</Button>
      </div>
    </div>
  );
}
