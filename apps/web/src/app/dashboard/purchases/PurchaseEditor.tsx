'use client';

import { useRouter } from 'next/navigation';
import { useMemo, useState, useTransition } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Alert, Button, Card, SelectField, TextField } from '@cafe/ui';
import { formatMoney, toLatinDigits } from '@cafe/locale';
import { savePurchase } from '@/app/actions/inventory';
import { entryUnits, type Ingredient, type PurchaseOrder } from '@/lib/inventory-types';
import { parseTomanInput } from '@/lib/money';

type Option = { id: string; name: string };
interface Line { key: string; ingredient_id: string; quantity: string; unit: string; price: string }

const newKey = () => Math.random().toString(36).slice(2);

/** Build or edit a draft purchase order in purchase units (kg, L, pack…), with live totals. */
export function PurchaseEditor({ purchase, suppliers, branches, ingredients }: { purchase: PurchaseOrder | null; suppliers: Option[]; branches: Option[]; ingredients: Ingredient[] }) {
  const router = useRouter();
  const byId = useMemo(() => Object.fromEntries(ingredients.map((i) => [i.id, i])), [ingredients]);
  const [supplierId, setSupplierId] = useState(purchase?.supplier?.id ?? suppliers[0]?.id ?? '');
  const [branchId, setBranchId] = useState(purchase?.branch?.id ?? branches[0]?.id ?? '');
  const [note, setNote] = useState(purchase?.note ?? '');
  const [lines, setLines] = useState<Line[]>(() => purchase?.items?.length
    ? purchase.items.map((i) => {
      const big = i.ingredient.unit === 'pcs' ? 1 : 1000;
      return { key: newKey(), ingredient_id: i.ingredient.id, quantity: String(i.quantity / big), unit: i.ingredient.unit === 'g' ? 'kg' : i.ingredient.unit === 'ml' ? 'l' : 'pcs', price: String(Math.round((i.unit_price * big) / 1000 / 10)) };
    })
    : [{ key: newKey(), ingredient_id: '', quantity: '', unit: '', price: '' }]);
  const [error, setError] = useState<string | null>(null);
  const [pending, start] = useTransition();

  const n = (v: string) => Number(toLatinDigits(v).replace('٫', '.'));
  const lineTotal = (l: Line) => {
    const qty = n(l.quantity);
    const price = parseTomanInput(l.price);
    return Number.isFinite(qty) && price !== null && !Number.isNaN(price) ? Math.round(qty * price) : 0;
  };
  const total = lines.reduce((s, l) => s + lineTotal(l), 0);
  const update = (key: string, patch: Partial<Line>) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)));

  const save = () => start(async () => {
    setError(null);
    const items = lines.filter((l) => l.ingredient_id && l.quantity).map((l) => ({
      ingredient_id: l.ingredient_id,
      quantity: n(l.quantity),
      unit: l.unit || entryUnits(byId[l.ingredient_id].unit)[0].value,
      unit_price: parseTomanInput(l.price) ?? 0,
    }));
    const r = await savePurchase(purchase?.id ?? null, { supplier_id: supplierId, branch_id: branchId, expected_on: purchase?.expected_on ?? null, note: note || null, items });
    if (!r.ok) { setError(r.message ?? Object.values(r.errors ?? {})[0] ?? 'ذخیره نشد.'); return; }
    router.push(`/dashboard/purchases/${r.id}`);
  });

  if (suppliers.length === 0) {
    return <Alert tone="info" title="اول یک تأمین‌کننده اضافه کنید">از صفحه‌ی «خرید»، بخش تأمین‌کننده‌ها، نام فروشنده‌ی مواد را ثبت کنید.</Alert>;
  }

  return (
    <div className="flex flex-col gap-4">
      {error ? <Alert tone="danger">{error}</Alert> : null}
      <Card className="grid gap-4 p-5 sm:grid-cols-3">
        <SelectField label="تأمین‌کننده" value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>{suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</SelectField>
        {branches.length > 1 ? <SelectField label="تحویل به شعبه" value={branchId} onChange={(e) => setBranchId(e.target.value)}>{branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</SelectField> : null}
        <TextField label="یادداشت" value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} />
      </Card>

      <Card>
        <ul className="divide-y divide-border">
          {lines.map((l) => {
            const ing = byId[l.ingredient_id];
            const units = ing ? entryUnits(ing.unit, { label: ing.pack_label, size: ing.pack_size }) : [];
            const unit = l.unit || units[0]?.value || '';
            const unitLabel = units.find((u) => u.value === unit)?.label ?? '';

            return (
              <li key={l.key} className="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(10rem,1.5fr)_7rem_8rem_minmax(8rem,1fr)_8rem_auto] sm:items-end">
                <SelectField label="ماده" value={l.ingredient_id} onChange={(e) => update(l.key, { ingredient_id: e.target.value, unit: '' })}>
                  <option value="">انتخاب…</option>
                  {ingredients.map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
                </SelectField>
                <TextField label="مقدار" inputMode="decimal" ltr value={l.quantity} onChange={(e) => update(l.key, { quantity: e.target.value })} />
                <SelectField label="واحد" value={unit} onChange={(e) => update(l.key, { unit: e.target.value })} disabled={!ing}>
                  {units.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                </SelectField>
                <TextField label={unitLabel ? `قیمت هر ${unitLabel} (تومان)` : 'قیمت (تومان)'} inputMode="numeric" ltr value={l.price}
                  onChange={(e) => update(l.key, { price: e.target.value })} hint={ing && ing.cost_per_big_unit ? `آخرین میانگین: ${formatMoney(ing.cost_per_big_unit)} / ${ing.big_unit_label}` : undefined} />
                <p className="tabular pb-2.5 text-sm font-semibold">{formatMoney(lineTotal(l))}</p>
                <button type="button" onClick={() => setLines((ls) => (ls.length > 1 ? ls.filter((x) => x.key !== l.key) : ls))} aria-label="حذف ردیف"
                  className="mb-1 flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-danger-soft hover:text-danger"><Trash2 className="size-4" /></button>
              </li>
            );
          })}
        </ul>
        <div className="flex items-center justify-between border-t border-border px-4 py-3">
          <Button variant="ghost" icon={<Plus />} onClick={() => setLines((ls) => [...ls, { key: newKey(), ingredient_id: '', quantity: '', unit: '', price: '' }])}>ردیف جدید</Button>
          <p className="text-sm">جمع: <span className="tabular text-lg font-black">{formatMoney(total)}</span></p>
        </div>
      </Card>

      <div className="flex gap-2">
        <Button onClick={save} loading={pending} disabled={!lines.some((l) => l.ingredient_id && l.quantity)}>{purchase ? 'ذخیره‌ی پیش‌نویس' : 'ذخیره به‌عنوان پیش‌نویس'}</Button>
        <Button variant="ghost" onClick={() => router.back()}>انصراف</Button>
      </div>
    </div>
  );
}
