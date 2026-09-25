'use client';

import Link from 'next/link';
import { useActionState, useEffect, useMemo, useState } from 'react';
import { History, Minus, Package, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { Badge, Button, Card, Checkbox, cx, Dialog, EmptyState, SelectField, TextField } from '@cafe/ui';
import { formatMoney } from '@cafe/locale';
import { adjustStock, deleteIngredient, saveIngredient } from '@/app/actions/inventory';
import { FormStatus } from '@/components/FormStatus';
import { MoneyField } from '@/components/MoneyField';
import { useSubmissionKey } from '@/components/useSubmissionKey';
import { entryUnits, formatQty, type Ingredient } from '@/lib/inventory-types';
import type { FormState } from '@/lib/types';

type Option = { id: string; name: string };

/** Stock bar: fill relative to three times the warning level (or the largest stock), coloured by state. */
function StockBar({ quantity, threshold, max }: { quantity: number; threshold: number; max: number }) {
  const scale = Math.max(threshold * 3, max, 1);
  const ratio = Math.max(0, Math.min(1, quantity / scale));
  const tone = quantity < 0 ? 'bg-danger' : threshold > 0 && quantity <= threshold ? 'bg-warning' : 'bg-success';

  return (
    <span className="relative block h-1.5 w-full overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
      <span className={cx('absolute inset-y-0 start-0 rounded-full', tone)} style={{ width: `${ratio * 100}%` }} />
      {threshold > 0 ? <span className="absolute inset-y-0 w-0.5 bg-text-subtle/60" style={{ insetInlineStart: `${(threshold / scale) * 100}%` }} /> : null}
    </span>
  );
}

export function InventoryManager({ ingredients, branches, canManage, lowOnly }: { ingredients: Ingredient[]; branches: Option[]; canManage: boolean; lowOnly: boolean }) {
  const [query, setQuery] = useState('');
  const [onlyLow, setOnlyLow] = useState(lowOnly);
  const [branchId, setBranchId] = useState(branches[0]?.id ?? '');
  const [editing, setEditing] = useState<Ingredient | 'new' | null>(null);
  const [adjusting, setAdjusting] = useState<Ingredient | null>(null);

  const list = useMemo(() => {
    const q = query.trim();

    return ingredients.filter((i) => (!q || i.name.includes(q)) && (!onlyLow || i.is_low || i.is_negative));
  }, [ingredients, query, onlyLow]);

  const qtyIn = (i: Ingredient) => i.stocks.find((s) => s.branch_id === branchId)?.quantity ?? 0;

  return (
    <>
      <div className="flex flex-wrap items-end gap-3">
        <label className="relative min-w-52 flex-1">
          <span className="sr-only">جست‌وجو</span>
          <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-text-subtle" aria-hidden="true" />
          <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="جست‌وجوی ماده‌ی اولیه…"
            className="h-10 w-full rounded-lg border border-border-strong bg-surface ps-9 pe-3 text-sm focus-visible:shadow-[var(--focus-ring)] focus-visible:outline-none" />
        </label>
        {branches.length > 1 ? (
          <div className="w-44">
            <SelectField label="شعبه" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
              {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </SelectField>
          </div>
        ) : null}
        <label className="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-border bg-surface px-3 text-sm has-[:checked]:border-warning has-[:checked]:bg-warning-soft">
          <input type="checkbox" checked={onlyLow} onChange={(e) => setOnlyLow(e.target.checked)} className="accent-[var(--color-warning)]" />
          فقط رو به اتمام‌ها
        </label>
        {canManage ? <Button icon={<Plus />} onClick={() => setEditing('new')}>ماده‌ی جدید</Button> : null}
      </div>

      <Card>
        {list.length === 0 ? (
          <EmptyState icon={<Package />} title={ingredients.length ? 'موردی پیدا نشد' : 'هنوز ماده‌ی اولیه‌ای تعریف نکرده‌اید'}
            description={ingredients.length ? 'فیلترها را تغییر دهید.' : 'قهوه، شیر، لیوان و… را تعریف کنید، برای محصولات دستور پخت بگذارید و با ثبت خرید، موجودی خودکار حساب می‌شود.'}
            action={canManage && !ingredients.length ? <Button icon={<Plus />} onClick={() => setEditing('new')}>اولین ماده</Button> : undefined} />
        ) : (
          <ul className="divide-y divide-border">
            {list.map((i) => {
              const q = qtyIn(i);
              const low = i.low_stock_threshold > 0 && q <= i.low_stock_threshold;
              const max = Math.max(...list.filter((x) => x.unit === i.unit).map(qtyIn));

              return (
                <li key={i.id} className={cx('grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-2 px-4 py-3.5 sm:grid-cols-[minmax(10rem,1.3fr)_minmax(9rem,1fr)_8rem_auto]', !i.is_active && 'opacity-55')}>
                  <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-2 font-medium">
                      {i.name}
                      {q < 0 ? <Badge tone="danger" dot>منفی</Badge> : low ? <Badge tone="warning" dot>رو به اتمام</Badge> : null}
                      {!i.is_active ? <Badge>غیرفعال</Badge> : null}
                    </p>
                    <p className="tabular text-xs text-text-muted">{formatMoney(i.cost_per_big_unit)} برای هر {i.big_unit_label}</p>
                  </div>
                  <div className="order-3 col-span-2 flex flex-col gap-1 sm:order-none sm:col-span-1">
                    <span className={cx('tabular text-sm font-semibold', q < 0 ? 'text-danger' : low ? 'text-warning' : '')}>{formatQty(q, i.unit)}</span>
                    <StockBar quantity={q} threshold={i.low_stock_threshold} max={max} />
                  </div>
                  <span className="tabular hidden text-sm text-text-muted sm:block">{formatMoney(Math.max(0, Math.round(q * i.avg_cost / 1000)))}</span>
                  <div className="flex items-center gap-1 justify-self-end">
                    {canManage ? (
                      <Button size="sm" variant="secondary" onClick={() => setAdjusting(i)} aria-label={`ثبت ضایعات یا اصلاح ${i.name}`}>ضایعات / اصلاح</Button>
                    ) : null}
                    <Link href={`/dashboard/inventory/${i.id}`} aria-label={`گردش ${i.name}`} className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text"><History className="size-4" /></Link>
                    {canManage ? <button type="button" onClick={() => setEditing(i)} aria-label={`ویرایش ${i.name}`} className="flex size-9 items-center justify-center rounded-lg text-text-muted hover:bg-surface-muted hover:text-text"><Pencil className="size-4" /></button> : null}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </Card>

      <Dialog open={editing !== null} onClose={() => setEditing(null)} variant="drawer" title={editing === 'new' ? 'ماده‌ی اولیه‌ی جدید' : 'ویرایش ماده'}>
        {editing !== null ? <IngredientForm key={editing === 'new' ? 'new' : editing.id} ingredient={editing === 'new' ? null : editing} onDone={() => setEditing(null)} /> : null}
      </Dialog>
      <Dialog open={adjusting !== null} onClose={() => setAdjusting(null)} title={adjusting ? `ضایعات یا اصلاح «${adjusting.name}»` : ''} size="sm">
        {adjusting ? <AdjustForm key={adjusting.id} ingredient={adjusting} branches={branches} branchId={branchId} onDone={() => setAdjusting(null)} /> : null}
      </Dialog>
    </>
  );
}

function IngredientForm({ ingredient, onDone }: { ingredient: Ingredient | null; onDone: () => void }) {
  const [state, action, pending] = useActionState<FormState, FormData>(saveIngredient.bind(null, ingredient?.id ?? null), { ok: false });
  const [unit, setUnit] = useState<Ingredient['unit']>(ingredient?.unit ?? 'g');
  const [deleting, setDeleting] = useState<string | null>(null);
  const e = state.errors ?? {};
  const big = unit === 'g' ? 'کیلوگرم' : unit === 'ml' ? 'لیتر' : 'عدد';

  useEffect(() => { if (state.ok) onDone(); }, [state.ok, onDone]);

  return (
    <form action={action} className="flex flex-col gap-4">
      <FormStatus state={state} />
      <TextField label="نام" name="name" required maxLength={120} defaultValue={ingredient?.name ?? ''} placeholder="مثلاً قهوه‌ی عربیکا" error={e.name} />
      <SelectField label="واحد پایه" name="unit" value={unit} onChange={(ev) => setUnit(ev.target.value as Ingredient['unit'])} disabled={Boolean(ingredient)}
        hint={ingredient ? 'واحد پایه بعد از ساخت تغییر نمی‌کند.' : 'موجودی و دستور پخت با این واحد حساب می‌شود.'}>
        <option value="g">وزن (گرم / کیلوگرم)</option>
        <option value="ml">حجم (میلی‌لیتر / لیتر)</option>
        <option value="pcs">تعداد (عدد)</option>
      </SelectField>
      {ingredient ? <input type="hidden" name="unit" value={unit} /> : (
        <MoneyField label={`قیمت فعلی هر ${big} (تومان)`} name="cost_per_big_unit" hint="برای شروع؛ از این به بعد قیمت با هر خرید به‌روز می‌شود." />
      )}
      <div className="grid grid-cols-2 gap-3">
        <TextField label="نام بسته (اختیاری)" name="pack_label" maxLength={60} defaultValue={ingredient?.pack_label ?? ''} placeholder="مثلاً پاکت ۱ لیتری" error={e.pack_label} />
        <TextField label={`اندازه‌ی بسته (${unit === 'g' ? 'گرم' : unit === 'ml' ? 'میلی‌لیتر' : 'عدد'})`} name="pack_size" inputMode="decimal" ltr defaultValue={ingredient?.pack_size != null ? String(ingredient.pack_size) : ''} error={e.pack_size} />
      </div>
      <TextField label={`حد هشدار موجودی (${big})`} name="low_stock_threshold" inputMode="decimal" ltr
        defaultValue={ingredient ? String(unit === 'pcs' ? ingredient.low_stock_threshold : ingredient.low_stock_threshold / 1000) : ''}
        hint="وقتی موجودی به این مقدار برسد هشدار «رو به اتمام» می‌آید." error={e.low_stock_threshold} />
      {ingredient ? <Checkbox name="is_active" defaultChecked={ingredient.is_active} label="فعال" /> : null}
      <div className="flex items-center gap-2 border-t border-border pt-4">
        <Button type="submit" loading={pending}>{ingredient ? 'ذخیره' : 'افزودن'}</Button>
        <Button variant="ghost" onClick={onDone}>انصراف</Button>
        {ingredient ? (
          <button type="button" className="ms-auto inline-flex items-center gap-1 text-sm text-danger hover:underline"
            onClick={async () => { const r = await deleteIngredient(ingredient.id); if (r.ok) onDone(); else setDeleting(r.message ?? 'حذف نشد.'); }}>
            <Trash2 className="size-4" aria-hidden="true" />حذف
          </button>
        ) : null}
      </div>
      {deleting ? <p role="alert" className="text-sm text-danger">{deleting}</p> : null}
    </form>
  );
}

function AdjustForm({ ingredient, branches, branchId, onDone }: { ingredient: Ingredient; branches: Option[]; branchId: string; onDone: () => void }) {
  const [key, renewKey] = useSubmissionKey();
  const [state, action, pending] = useActionState<FormState, FormData>(async (prev, fd) => {
    const r = await adjustStock(key, prev, fd);
    if (!r.ok) renewKey();
    return r;
  }, { ok: false });
  const [type, setType] = useState<'waste' | 'adjustment'>('waste');
  const [direction, setDirection] = useState<1 | -1>(1);
  const units = entryUnits(ingredient.unit, { label: ingredient.pack_label, size: ingredient.pack_size });

  useEffect(() => { if (state.ok) onDone(); }, [state.ok, onDone]);

  return (
    <form action={action} className="flex flex-col gap-4">
      <FormStatus state={state} />
      <input type="hidden" name="ingredient_id" value={ingredient.id} />
      <input type="hidden" name="type" value={type} />
      <input type="hidden" name="direction" value={type === 'adjustment' ? direction : -1} />
      <div className="grid grid-cols-2 gap-2">
        {([['waste', 'ضایعات', 'خراب شد، ریخت، تاریخ گذشت'], ['adjustment', 'اصلاح موجودی', 'اشتباه ثبت، هدیه، جابه‌جایی']] as const).map(([v, t, h]) => (
          <button key={v} type="button" onClick={() => setType(v)} aria-pressed={type === v}
            className={cx('rounded-xl border-2 px-3 py-2.5 text-start transition-colors', type === v ? 'border-brand bg-brand-soft/60' : 'border-transparent bg-surface-muted')}>
            <span className="block text-sm font-semibold">{t}</span>
            <span className="block text-[11px] text-text-muted">{h}</span>
          </button>
        ))}
      </div>
      {branches.length > 1 ? (
        <SelectField label="شعبه" name="branch_id" defaultValue={branchId}>{branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</SelectField>
      ) : <input type="hidden" name="branch_id" value={branchId} />}
      <div className="flex items-end gap-2">
        {type === 'adjustment' ? (
          <div className="flex h-10 rounded-lg bg-surface-muted p-0.5">
            <button type="button" onClick={() => setDirection(1)} aria-pressed={direction === 1} aria-label="افزایش" className={cx('flex w-10 items-center justify-center rounded-md', direction === 1 && 'bg-surface shadow-[var(--shadow-sm)] text-success')}><Plus className="size-4" /></button>
            <button type="button" onClick={() => setDirection(-1)} aria-pressed={direction === -1} aria-label="کاهش" className={cx('flex w-10 items-center justify-center rounded-md', direction === -1 && 'bg-surface shadow-[var(--shadow-sm)] text-danger')}><Minus className="size-4" /></button>
          </div>
        ) : null}
        <div className="flex-1"><TextField label="مقدار" name="quantity" required inputMode="decimal" ltr error={state.errors?.quantity} /></div>
        <div className="w-32">
          <SelectField label="واحد" name="unit" defaultValue={units[0].value}>{units.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}</SelectField>
        </div>
      </div>
      <TextField label={type === 'waste' ? 'توضیح (اختیاری)' : 'دلیل'} name="note" required={type === 'adjustment'} maxLength={300} error={state.errors?.note} />
      <div className="flex gap-2">
        <Button type="submit" loading={pending}>ثبت</Button>
        <Button variant="ghost" onClick={onDone}>انصراف</Button>
      </div>
    </form>
  );
}
